#!/usr/bin/env python3
"""Offline feasibility audit. Public source genotypes stay in memory on this host.

Writes only aggregate diagnostics and public-reference frequencies to an isolated
prototype directory. Never opens a customer file or modifies a live data pack.
"""
from research_paths import require_path
import os
os.environ['OPENBLAS_NUM_THREADS'] = '1'
os.environ['OMP_NUM_THREADS'] = '1'
import csv
import argparse
import hashlib
import itertools
import json
import math
import sqlite3
import time
from collections import Counter
from pathlib import Path
import numpy as np
from scipy.optimize import minimize
from cyvcf2 import VCF

BASE = require_path('ZDNA_UPGRADE_ROOT')
OUT = require_path('ZDNA_ETHNICITY_ROOT') / 'science-prototype'
MANIFEST = BASE / 'population_candidate_manifest.json'
PANEL = '1000G_Phase3_optimized_population'
GROUPS = {
    'AFR': ['ESN', 'GWD', 'LWK', 'MSL', 'YRI'],
    'EAS': ['CDX', 'CHB', 'CHS', 'JPT', 'KHV'],
    'EUR': ['CEU', 'FIN', 'GBR', 'IBS', 'TSI'],
    'SAS': ['BEB', 'GIH', 'ITU', 'PJL', 'STU'],
}
CODES = list(GROUPS)
LABELS = ['West/East African references', 'East Asian references', 'European references', 'South Asian references']
SOURCE_ALLOWLIST = None
REBUILD_FROM_ALLOWLIST = False
CHALLENGE_NPZ = None
LENGTHS = np.array([249250621,243199373,198022430,191154276,180915260,171115067,159138663,146364022,141213431,135534747,135006516,133851895,115169878,107349540,102531392,90354753,81195210,78077248,59128983,63025520,48129895,51304566])

def emit(value):
    print(json.dumps(value, sort_keys=True), flush=True)

def fit(y, f, method='hwe'):
    mask = np.isfinite(y) & (y >= 0) & np.isfinite(f).all(axis=1)
    yy = y[mask].astype(float)
    ff = f[mask].astype(float)
    if len(yy) < 4:
        return None
    k = ff.shape[1]
    if method == 'hwe':
        def objective(q):
            p = np.clip(ff @ q, 1e-7, 1-1e-7)
            val = -np.mean(yy * np.log(p) + (2-yy) * np.log1p(-p))
            jac = ff.T @ ((2*p-yy)/(p*(1-p))) / len(yy)
            return val, jac
    else:
        def objective(q):
            residual = ff @ q - yy/2
            return float(np.mean(residual**2)), 2*ff.T@residual/len(yy)
    result = minimize(objective, np.full(k, 1/k), jac=True, method='SLSQP',
                      bounds=[(0, 1)]*k,
                      constraints={'type':'eq', 'fun':lambda q:q.sum()-1, 'jac':lambda q:np.ones(k)},
                      options={'ftol':1e-11, 'maxiter':250})
    if not result.success or not np.isfinite(result.x).all() or abs(result.x.sum()-1) > 1e-6:
        raise RuntimeError('Mixture optimizer failed: ' + result.message)
    q = np.clip(result.x, 0, 1)
    q /= q.sum()
    p = np.clip(ff @ q, 1e-7, 1-1e-7)
    return {'q':q, 'n':len(yy), 'objective':float(result.fun),
            'pearson':float(np.mean((yy-2*p)**2 / np.maximum(2*p*(1-p), 0.02))),
            'observed_heterozygosity':float(np.mean(yy == 1)),
            'expected_heterozygosity':float(np.mean(2*p*(1-p))),
            'iterations':int(result.nit)}

def summaries(values):
    x = np.asarray(values, dtype=float)
    return {'n':int(x.size), 'min':float(x.min()), 'median':float(np.median(x)),
            'p90':float(np.quantile(x, .9)), 'max':float(x.max()), 'mean':float(x.mean())}

def single_source_objectives(y, f):
    mask = (y>=0)&np.isfinite(y)
    p=np.clip(f[mask],1e-7,1-1e-7)
    return -np.mean(y[mask,None]*np.log(p)+(2-y[mask,None])*np.log1p(-p),axis=0)

def source_data():
    manifest = json.loads(MANIFEST.read_text())
    db_path = Path(manifest['candidate_path'])
    assert db_path.resolve() != (OUT / 'broad-reference.npz').resolve()
    with open(manifest['sample_panel']['path']) as handle:
        metadata = {r['sample']:r for r in csv.DictReader(handle, delimiter='\t')}
    held = manifest['heldout_sample_ids']
    training = manifest['training_sample_ids']
    assert len(held) == 130 and len(training) == 2357 and not set(held) & set(training)
    populations = sorted(manifest['training_sample_counts'])
    pi = {p:i for i,p in enumerate(populations)}
    pop_to_group = {p:g for g,pp in GROUPS.items() for p in pp}
    db = sqlite3.connect('file:' + str(db_path) + '?mode=ro', uri=True)
    # Source-only, deterministic coverage thinning; no held-out genotype selection.
    loci = [tuple(r) for r in db.execute('SELECT chrom,pos,ref,alt FROM ancestry_reference_markers WHERE panel_name=? AND population_code=? ORDER BY CAST(chrom AS INTEGER),pos', (PANEL, populations[0]))]
    assert len(loci) == 50001 and len(set(loci)) == len(loci)
    allowed = None
    if CHALLENGE_NPZ:
        with np.load(CHALLENGE_NPZ,allow_pickle=False) as challenge:
            allowed = set(zip(challenge['chromosome'].astype(str).tolist(),challenge['position'].astype(int).tolist(),challenge['reference'].astype(str).tolist(),challenge['alternate'].astype(str).tolist()))
    elif SOURCE_ALLOWLIST:
        allowdb = sqlite3.connect('file:' + str(SOURCE_ALLOWLIST) + '?mode=ro', uri=True)
        allowed = set(tuple(r) for r in allowdb.execute('SELECT DISTINCT chrom,pos,ref,alt FROM ancient_origin_markers WHERE build=?', ('GRCh37',)))
        allowdb.close()
    if REBUILD_FROM_ALLOWLIST:
        assert allowed is not None
        loci=sorted(allowed,key=lambda k:(int(k[0]),k[1]))
    bins = {}
    for key in loci:
        if allowed is not None and key not in allowed:
            continue
        c,p,ref,alt = key
        assert 1 <= int(c) <= 22 and {ref,alt} not in [{'A','T'},{'C','G'}]
        slot = (c, int(p)//500000)
        if slot not in bins or abs(p % 500000-250000) < abs(bins[slot][1] % 500000-250000):
            bins[slot] = key
    chosen = sorted(bins.values(), key=lambda r:(int(r[0]),r[1]))
    ix = {key:i for i,key in enumerate(chosen)}
    af = np.full((len(chosen),26), np.nan)
    if not REBUILD_FROM_ALLOWLIST:
        for c,p,ref,alt,pop,f in db.execute('SELECT chrom,pos,ref,alt,population_code,allele_frequency FROM ancestry_reference_markers WHERE panel_name=?', (PANEL,)):
            idx = ix.get((c,p,ref,alt))
            if idx is not None:
                assert np.isnan(af[idx,pi[pop]])
                af[idx,pi[pop]] = f
    db.close()
    if not REBUILD_FROM_ALLOWLIST:
        assert np.isfinite(af).all() and np.all((af>=0)&(af<=1))
    calls = np.full((len(chosen),len(held),2), -1, dtype=np.int8)
    train_calls = np.full((len(chosen),len(training)), -1, dtype=np.int8)
    source_audit = []
    source_excluded=Counter()
    for source in manifest['source_files']:
        chrom = str(source['chromosome'])
        positions = [(i,k) for i,k in enumerate(chosen) if k[0] == chrom]
        path = Path(source['file'])
        assert path.stat().st_size == source['size_bytes'], 'Source size drift'
        vcf = VCF(str(path), strict_gt=True, threads=1)
        hi = np.array([vcf.samples.index(s) for s in held])
        ti = np.array([vcf.samples.index(s) for s in training])
        pop_idx = {p:np.array([vcf.samples.index(s) for s in training if metadata[s]['pop']==p]) for p in populations}
        max_diff = 0.0
        for i,key in positions:
            c,pos,ref,alt = key
            records = [r for r in vcf(f'{c}:{pos}-{pos}') if r.POS==pos and r.REF==ref and r.ALT==[alt]]
            if len(records) != 1:
                if REBUILD_FROM_ALLOWLIST:
                    source_excluded['not_one_exact_biallelic_record']+=1
                    continue
                raise RuntimeError('Public source locus contract failed at %s:%s:%s:%s; exact biallelic records=%d' % (*key,len(records)))
            row = records[0]
            assert row.FILTER in [None, '.', 'PASS']
            all_genotypes = np.asarray(row.genotypes, dtype=np.int8)
            gt = all_genotypes[:,:2]
            assert np.all((gt>=-1)&(gt<=1))
            held_called = (gt[hi]>=0).all(axis=1)
            assert np.all(all_genotypes[hi,2][held_called] == 1), 'Held-out source haplotypes are not phased'
            calls[i] = gt[hi]
            train_gt = gt[ti]
            train_calls[i] = np.where((train_gt>=0).all(axis=1), train_gt.sum(axis=1), -1)
            for p,indices in pop_idx.items():
                pp = gt[indices]
                assert np.mean(pp>=0)>=.98, 'Training source call rate below98%'
                measured = float(pp[pp>=0].mean())
                if REBUILD_FROM_ALLOWLIST:
                    af[i,pi[p]]=measured
                else:
                    max_diff = max(max_diff, abs(measured-af[i,pi[p]]))
        vcf.close()
        assert max_diff < 1e-12, 'Stored training frequencies differ from public source'
        source_audit.append({'chromosome':chrom, 'loci':len(positions), 'max_frequency_difference':max_diff})
        emit({'stage':'public_source_verified', 'chromosome':chrom, 'loci':len(positions)})
    if REBUILD_FROM_ALLOWLIST:
        complete=np.flatnonzero(np.isfinite(af).all(axis=1))
        af=af[complete];calls=calls[complete];train_calls=train_calls[complete]
        chosen=[chosen[i] for i in complete]
    assert np.isfinite(af).all() and np.all((af>=0)&(af<=1))
    # Greedy LD pruning within each broad training pool; no heldout calls used.
    pool_idx = [np.array([i for i,s in enumerate(training) if metadata[s]['pop'] in GROUPS[g]]) for g in CODES]
    retained = []
    pruned = 0
    for i,key in enumerate(chosen):
        nearby = [j for j in retained[-4:] if chosen[j][0]==key[0] and key[1]-chosen[j][1]<=1000000]
        reject = False
        for j in nearby:
            for ids in pool_idx:
                a,b = train_calls[i,ids],train_calls[j,ids]
                valid = (a>=0)&(b>=0)
                aa,bb = a[valid].astype(float),b[valid].astype(float)
                aa-=aa.mean(); bb-=bb.mean()
                denom = np.dot(aa,aa)*np.dot(bb,bb)
                if denom > 0 and np.dot(aa,bb)**2/denom > 0.1:
                    reject = True
                    break
            if reject: break
        if reject: pruned+=1
        else: retained.append(i)
    del train_calls
    selected = np.array(retained)
    af = af[selected]
    calls = calls[selected]
    chosen = [chosen[i] for i in retained]
    f = np.stack([af[:,[pi[p] for p in GROUPS[g]]].mean(axis=1) for g in CODES],axis=1)
    # Equal study-population weighting is explicit; no missing per-locus AN inferred.
    locus_hash = hashlib.sha256(('\n'.join(':'.join(map(str,k)) for k in chosen)+'\n').encode()).hexdigest()
    return manifest, metadata, held, populations, pop_to_group, chosen, f, af, calls, {
        'source_candidate_sha256':manifest['candidate_sha256'], 'source_panel':PANEL,
        'training_samples':len(training), 'heldout_samples':len(held),
        'starting_shared_loci':len(loci), 'coordinate_thinned_loci':len(bins),
        'frequency_origin':'recomputed from public genotypes at aligned challenge loci using the unchanged training/holdout split' if REBUILD_FROM_ALLOWLIST else 'existing training-only50001locus candidate, selected frequencies independently recomputed',
        'source_allowlist':str(SOURCE_ALLOWLIST) if SOURCE_ALLOWLIST else None,
        'source_allowlist_intersection_loci':sum(k in allowed for k in loci) if allowed is not None else 50001,
        'ld_removed_loci':pruned, 'retained_loci':len(chosen), 'retained_loci_sha256':locus_hash,
        'chromosome_loci':dict(Counter(k[0] for k in chosen)),
        'source_frequency_recomputation':source_audit,
        'source_excluded_loci':dict(source_excluded),
        'ld_method':'one locus nearest each 500kb bin centre; remove r2>0.1 within 1Mb in any of four broad training pools',
        'frequency_method':'arithmetic mean of five training-only study-population frequencies per broad region',
    }

def challenge_results(loci,f,fine,amr_f,fit_threshold):
    if not CHALLENGE_NPZ:
        return None
    with np.load(CHALLENGE_NPZ,allow_pickle=False) as challenge:
        keys=list(zip(challenge['chromosome'].astype(str).tolist(),challenge['position'].astype(int).tolist(),challenge['reference'].astype(str).tolist(),challenge['alternate'].astype(str).tolist()))
        lookup={k:i for i,k in enumerate(keys)}
        assert len(lookup)==len(keys) and all(k in lookup for k in loci)
        take=np.array([lookup[k] for k in loci])
        labels=challenge['groups'].astype(str)
        observed=challenge['dosage'][:,take]
        assert observed.shape[1]==len(loci)
        assert np.all((observed>=-1)&(observed<=2))
        rows=[]
        for i,group in enumerate(labels):
            y=observed[i]
            a=fit(y,f); b=fit(y,fine)
            rows.append({'group':group,'q4':a['q'],'q20':b['q'].reshape(4,5).sum(axis=1),
                         'pearson':a['pearson'],'called':a['n'],'heterozygosity':a['observed_heterozygosity'],
                         'amr_preferred':bool(b['objective']>single_source_objectives(y,amr_f).min())})
        result={}
        for group in sorted(set(labels)):
            rr=[r for r in rows if r['group']==group]
            result[group]={'n':len(rr),'called_loci':summaries([r['called'] for r in rr]),
                           'mean_pooled4_percent':dict(zip(CODES,(100*np.mean([r['q4'] for r in rr],axis=0)).round(2).tolist())),
                           'mean_aggregated20_percent':dict(zip(CODES,(100*np.mean([r['q20'] for r in rr],axis=0)).round(2).tolist())),
                           'fit_pearson':summaries([r['pearson'] for r in rr]),
                           'pooled4_fit_rejected':sum(r['pearson']>fit_threshold for r in rr),
                           'amr_preferred_over20':sum(r['amr_preferred'] for r in rr)}
    return {'source':'Current public AADR v66.p1 HO quality-Pass modern sources; source agent provenance manifest',
            'source_coordinate_allele_contract':'exact GRCh37 chromosome/position/REF/ALT intersection; no complements, imputation or allele guessing',
            'shared_model_loci':len(loci),'total_public_participants':len(labels),'by_source_group':result,
            'limitations':['Challenge groups test absent-source behavior; their true four-pool ancestry fractions are not known.',
                'The present-day controls are cross-source checks; individual identity overlap with1000G training has not been independently audited.',
                'No participant genotype, ID or row is returned by this validation.']}

def unit_tests():
    rng = np.random.default_rng(9551)
    f = rng.uniform(.02,.98,(30000,4))
    q = np.array([.1,.2,.3,.4])
    y = rng.binomial(2, f@q)
    a = fit(y,f); b=fit(2-y,1-f)
    assert abs(a['q'].sum()-1)<1e-10 and np.all(a['q']>=0)
    assert np.max(np.abs(a['q']-q))<.02
    assert np.max(np.abs(a['q']-b['q']))<1e-6
    assert np.max(np.abs(a['q']-fit(y[::-1],f[::-1])['q']))<1e-6
    assert fit(np.full(12,-1),f[:12]) is None
    assert np.max(np.abs(fit(y,f,'least_squares')['q']-q))<.02
    return {'checks':6,'status':'passed','data':'entirely artificial alleles/frequencies'}

def main():
    started=time.time()
    OUT.mkdir(parents=True,exist_ok=True)
    result_path=OUT/'results/regional-mixture-validation.json'
    if result_path.exists(): raise RuntimeError('Refusing to overwrite prior validation')
    os.umask(0o077)
    units=unit_tests()
    manifest,metadata,held,pops,pop_to_group,loci,f,af,calls,source=source_data()
    chrom=np.array([int(k[0]) for k in loci])
    pos=np.array([k[1] for k in loci])
    dosage=np.where((calls>=0).all(axis=2),calls.sum(axis=2),-1)
    fitted=[fit(dosage[:,i],f) for i in range(len(held))]
    # One predeclared exploratory alternative:20 source components, then aggregate.
    # It is evaluated on reused heldouts; no fresh external-validation claim.
    fine_pops=[p for g in CODES for p in GROUPS[g]]
    fine=af[:,[pops.index(p) for p in fine_pops]]
    amr_pops=['CLM','MXL','PEL','PUR']
    amr_f=af[:,[pops.index(p) for p in amr_pops]]
    aggregated=[]
    for i in range(len(held)):
        fit20=fit(dosage[:,i],fine)
        q4=fit20['q'].reshape(4,5).sum(axis=1)
        amr_obj=single_source_objectives(dosage[:,i],amr_f)
        aggregated.append({'q':q4,'pearson':fit20['pearson'],'objective':fit20['objective'],
                           'best_single_amr_gain':float(fit20['objective']-amr_obj.min()),
                           'best_single_amr_label':amr_pops[int(amr_obj.argmin())]})
    bypop={}
    calibration=[]; confirmation=[]; out_of_panel=[]; admixed=[]
    for pop in pops:
        ids=[i for i,s in enumerate(held) if metadata[s]['pop']==pop]
        group=pop_to_group.get(pop)
        q=np.array([fitted[i]['q'] for i in ids])
        item={'n':len(ids),'mean_reference_percent':dict(zip(CODES,(100*q.mean(axis=0)).round(2).tolist())),
              'fit_pearson':summaries([fitted[i]['pearson'] for i in ids])}
        alternative_q=np.array([aggregated[i]['q'] for i in ids])
        item['alternative_source20']={'mean_reference_percent':dict(zip(CODES,(100*alternative_q.mean(axis=0)).round(2).tolist())),
            'amr_single_source_preferred':sum(aggregated[i]['best_single_amr_gain']>0 for i in ids),
            'amr_single_source_gain':summaries([aggregated[i]['best_single_amr_gain'] for i in ids])}
        if group:
            gi=CODES.index(group)
            item['expected_broad_study_group']=group
            item['correct_largest_component']=int((q.argmax(axis=1)==gi).sum())
            item['matching_component_percent']=summaries(100*q[:,gi])
            item['alternative_source20']['correct_largest_component']=int((alternative_q.argmax(axis=1)==gi).sum())
            item['alternative_source20']['matching_component_percent']=summaries(100*alternative_q[:,gi])
            calibration+=ids[:2]; confirmation+=ids[2:]
        elif pop in ['ASW','ACB']: admixed+=ids
        else: out_of_panel+=ids
        bypop[pop]=item
    # Development calibration uses only first2 heldouts/pop; remaining3 are a check.
    fit_threshold=max(fitted[i]['pearson'] for i in calibration)+.05
    rejected=lambda ids:sum(fitted[i]['pearson']>fit_threshold for i in ids)
    rng=np.random.default_rng(202609051)
    group_donors={g:[i for i,s in enumerate(held) if metadata[s]['pop'] in GROUPS[g]] for g in CODES}
    block_size=5000000
    block_counts=np.ceil(LENGTHS/block_size).astype(int)
    block_offsets=np.concatenate([[0],np.cumsum(block_counts)])
    site_blocks=block_offsets[chrom-1]+(pos//block_size)
    block_lengths=np.concatenate([np.minimum(block_size,L-np.arange(math.ceil(L/block_size))*block_size) for L in LENGTHS])
    mixtures=[]; synthetic_cases=[]
    definitions=[]
    for a,b in itertools.combinations(range(4),2):
        for fraction in [.1,.25,.5,.75,.9]:
            q=np.zeros(4);q[a]=fraction;q[b]=1-fraction
            definitions.append(q)
    definitions += [np.eye(4)[j] for j in range(4)] + [np.full(4,.25)]
    for trial,wanted in enumerate(definitions):
        for replicate in range(3):
            y=np.zeros(len(loci),dtype=np.int8)
            ancestry_by_hap=[]
            valid=np.ones(len(loci),dtype=bool)
            for hap in range(2):
                ancestry=rng.choice(4,size=len(block_lengths),p=wanted)
                ancestry_by_hap.append(ancestry)
                # Fixed public held-out reference donor per region/chromosome/haplotype.
                for g in range(4):
                    for c in range(1,23):
                        take=(chrom==c)&(ancestry[site_blocks]==g)
                        donor=int(rng.choice(group_donors[CODES[g]]))
                        allele=calls[take,donor,hap]
                        valid[take]&=allele>=0
                        y[take]+=np.maximum(allele,0)
            y[~valid]=-1
            truth=np.bincount(np.concatenate([a[site_blocks] for a in ancestry_by_hap]),minlength=4)/(2*len(loci))
            physical=np.bincount(np.concatenate(ancestry_by_hap),weights=np.tile(block_lengths,2),minlength=4)/(2*block_lengths.sum())
            actual=fit(y,f)
            ls=fit(y,f,'least_squares')
            actual20=fit(y,fine)
            q20=actual20['q'].reshape(4,5).sum(axis=1)
            amr_gain=float(actual20['objective']-single_source_objectives(y,amr_f).min())
            error=100*(actual['q']-truth)
            mixtures.append({'requested_percent':(100*wanted).tolist(),'realized_marker_percent':(100*truth).round(3).tolist(),
                             'realized_physical_percent':(100*physical).round(3).tolist(),'estimated_percent':(100*actual['q']).round(3).tolist(),
                             'max_absolute_error_pp':float(np.max(abs(error))), 'squared_error':float(np.mean(error**2)),
                             'physical_max_absolute_error_pp':float(np.max(abs(100*(actual['q']-physical)))),
                             'ls_max_absolute_error_pp':float(np.max(abs(100*(ls['q']-truth)))),
                             'alternative20_max_absolute_error_pp':float(np.max(abs(100*(q20-truth)))),
                             'alternative20_squared_error':float(np.mean((100*(q20-truth))**2)),
                             'alternative20_amr_single_source_preferred':amr_gain>0,
                             'alternative20_amr_gain':amr_gain,
                             'pearson':actual['pearson'],'fit_rejected':actual['pearson']>fit_threshold})
            if replicate==0 and (trial%5==2 or trial>=30): synthetic_cases.append((y,truth,actual))
    # Known-frequency model simulation is an implementation check, not external validation.
    generative=[]
    for q in definitions:
        y=rng.binomial(2,np.clip(f@q,0,1))
        a=fit(y,f)
        generative.append(float(100*np.max(np.abs(a['q']-q))))
    omission=[]
    for y,truth,a in synthetic_cases:
        estimates=np.array([fit(y[chrom!=c],f[chrom!=c])['q'] for c in range(1,23)])
        mean=estimates.mean(axis=0)
        se=np.sqrt(21/22*np.sum((estimates-mean)**2,axis=0))
        omission.append({'true_marker_percent':(100*truth).round(3).tolist(),
                         'estimate_percent':(100*a['q']).round(3).tolist(),
                         'jackknife_se_pp':(100*se).round(3).tolist(),
                         'approx_95_contains_truth':((truth>=a['q']-1.96*se)&(truth<=a['q']+1.96*se)).tolist()})
    missingness={}
    for n in [500,1500,3000]:
        if n>len(loci):continue
        take=np.linspace(0,len(loci)-1,n,dtype=int)
        errors=[]
        for y,truth,a in synthetic_cases:
            aa=fit(y[take],f[take])
            errors.append(float(100*np.max(np.abs(aa['q']-truth))))
        missingness[str(n)]={'mosaic_max_component_error_pp':summaries(errors),'chromosomes':len(set(chrom[take].tolist()))}
    # Deliberately pathological calls should never be mistaken for real reference fit.
    pathologies={}
    for label,y in {'all_reference':np.zeros(len(loci)), 'all_alternate':np.full(len(loci),2), 'all_heterozygous':np.ones(len(loci)), 'coinflip_hwe':rng.binomial(2,.5,len(loci))}.items():
        a=fit(y,f)
        pathologies[label]={'percent':(100*a['q']).round(2).tolist(),'pearson':a['pearson'],'fit_rejected':a['pearson']>fit_threshold,
                            'observed_heterozygosity':a['observed_heterozygosity'],'expected_heterozygosity':a['expected_heterozygosity']}
    challenge=challenge_results(loci,f,fine,amr_f,fit_threshold)
    # Frequencies and public coordinates only; public donor calls are never serialized.
    np.savez_compressed(OUT/'broad-reference.npz',frequencies=f,chromosome=chrom,position=pos,
                        reference=np.array([k[2] for k in loci]),alternate=np.array([k[3] for k in loci]),groups=np.array(CODES),
                        fine_frequencies=fine,fine_populations=np.array(fine_pops),
                        all26_frequencies=af,all26_populations=np.array(pops))
    report={'status':'offline_feasibility_only','created_utc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'source':source,
            'prototype_code_sha256':hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
            'groups':dict(zip(CODES,LABELS)),'group_source_populations':GROUPS,'synthetic_unit_checks':units,
            'method':'Fixed-frequency supervised HWE binomial mixture maximum likelihood, simplex constrained; separate simplex least-squares comparison',
            'output_meaning':'Model coefficients relative to four present-day source pools, not normalized affinity and not observed genealogical proportions',
            'heldout_by_population':bypop,'fit_control':{'statistic':'mean squared dosage residual divided by max(2p(1-p),0.02)',
                'threshold':fit_threshold,'threshold_derivation':'maximum of40 development-heldout reference participants plus0.05; development convention, not externally calibrated',
                'development_samples':len(calibration),'confirmation_samples':len(confirmation),'confirmation_rejected':rejected(confirmation),
                'amr_out_of_panel_samples':len(out_of_panel),'amr_rejected':rejected(out_of_panel),
                'asw_acb_admixed_samples':len(admixed),'asw_acb_rejected':rejected(admixed)},
            'alternative_source20':{'method':'simplex-constrained20study-population coefficients aggregated to4regions; exploratory alternative after observing pooled4 results',
                'interpretation':'Development diagnostic using same public holdouts, not an independent replication or validated country percentages',
                'mosaic_component_rmse_pp':float(np.sqrt(np.mean([m['alternative20_squared_error'] for m in mixtures]))),
                'mosaic_max_component_error_pp':summaries([m['alternative20_max_absolute_error_pp'] for m in mixtures]),
                'ood_comparison':'Best single excludedAMR reference vs flexible20-source model, positive mean log-likelihood gain favours excluded reference; zero threshold specified before results',
                'amr_reference_preferred_in_100_source_holdouts':sum(aggregated[i]['best_single_amr_gain']>0 for i in calibration+confirmation),
                'amr_reference_preferred_in_20_amr_holdouts':sum(aggregated[i]['best_single_amr_gain']>0 for i in out_of_panel),
                'amr_reference_preferred_in_10_asw_acb_holdouts':sum(aggregated[i]['best_single_amr_gain']>0 for i in admixed),
                'amr_reference_preferred_in_105_mosaics':sum(m['alternative20_amr_single_source_preferred'] for m in mixtures)},
            'heldout_mosaic_simulation':{'n':len(mixtures),'block_size_bp':block_size,'marker_error_max_pp':summaries([m['max_absolute_error_pp'] for m in mixtures]),
                'physical_error_max_pp':summaries([m['physical_max_absolute_error_pp'] for m in mixtures]),
                'component_rmse_pp':float(np.sqrt(np.mean([m['squared_error'] for m in mixtures]))),
                'ls_error_max_pp':summaries([m['ls_max_absolute_error_pp'] for m in mixtures]),
                'fit_rejected':sum(m['fit_rejected'] for m in mixtures),'trials':mixtures},
            'known_frequency_simulation':{'n':len(generative),'max_component_error_pp':summaries(generative),'purpose':'implementation self-consistency only'},
            'jackknife_sensitivity':{'method':'delete one chromosome at a time,22blocks; approximate1.96SE experiment, no clinical/consumer calibration',
                'cases':omission,'component_coverage':np.mean([o['approx_95_contains_truth'] for o in omission],axis=0).tolist()},
            'marker_subset_stress':missingness,'pathological_controls':pathologies,
            'aadr_out_of_panel_challenge':challenge,
            'unsupported':['Indigenous American ancestry (AMR references are admixed)','Middle Eastern/North African references','Oceanian references',
                'English/Scottish/Welsh/Irish splits','Ashkenazi Jewish references','Khazar ancestry'],
            'limits':['Study labels are not independently known pure ancestry fractions.',
                'Four modern regional reference pools do not span all human ancestry; a closed simplex always produces100% even for absent sources.',
                'Public donor joint calling/phasing and source chip ascertainment predate holdout split.',
                'All four reference regions contain within-region structure and older admixture; model values depend on selected sources.',
                'Chromosome jackknife does not include reference choice, ancestry-model error or all sources of genotype error.',
                'No actual vendor masks or independently characterized mixed-ancestry consumer cohort tested.',
                'No customer data used; public evaluation genotypes remain in process memory and are never emitted.'],
            'elapsed_seconds':round(time.time()-started,2)}
    result_path.parent.mkdir(parents=True,exist_ok=True)
    result_path.write_text(json.dumps(report,indent=2,sort_keys=True)+'\n')
    emit({'stage':'complete','report':str(result_path),'seconds':report['elapsed_seconds'],'retained_loci':len(loci),
          'mosaic_rmse_pp':report['heldout_mosaic_simulation']['component_rmse_pp'],'fit_control':report['fit_control'],
          'synthetic_unit_checks':units})

if __name__=='__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('--allowlist-ancient-pack',type=Path)
    parser.add_argument('--run-name',default='')
    parser.add_argument('--rebuild-from-allowlist',action='store_true')
    parser.add_argument('--challenge-npz',type=Path)
    args=parser.parse_args()
    SOURCE_ALLOWLIST=args.allowlist_ancient_pack
    REBUILD_FROM_ALLOWLIST=args.rebuild_from_allowlist
    CHALLENGE_NPZ=args.challenge_npz
    if args.run_name:
        if not all(c.isalnum() or c in '_-' for c in args.run_name):
            raise RuntimeError('Invalid isolated run name')
        OUT=OUT/args.run_name
    main()
