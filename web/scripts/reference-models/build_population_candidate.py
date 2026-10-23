#!/usr/bin/env python3
"""Build an inactive public-reference candidate; never writes the active pack.

Public held-out donors are used only in memory for aggregate diagnostic evaluation.
They are not raw-DNA test fixtures and no donor genotype rows are logged/exported.
"""
import csv
import hashlib
import json
import os
import re
from pathlib import Path
import sqlite3
import time
from collections import Counter, defaultdict
from datetime import datetime, timezone

os.environ['OPENBLAS_NUM_THREADS']='1'
os.environ['OMP_NUM_THREADS']='1'
import numpy as np
from cyvcf2 import VCF

ROOT=Path('/mnt/data/zerothink/dna-upgrade-20260905').resolve()
DATA=Path('/home/talkto/zerothink.talktoai.org/data').resolve()
ACTIVE=DATA/'packs/ancestry_core.sqlite'
OUT=ROOT/'ancestry_core.population-balanced-candidate.sqlite'
REPORT=ROOT/'population_candidate_manifest.json'
PANEL='1000G_Phase3_optimized_population'
VERSION='Phase3 20130502 / balanced shared-marker candidate 20260905 / training donors only'
TARGET=50000
HOLD_PER_POP=5
LENGTHS=[249250621,243199373,198022430,191154276,180915260,171115067,159138663,146364022,141213431,135534747,135006516,133851895,115169878,107349540,102531392,90354753,81195210,78077248,59128983,63025520,48129895,51304566]

def sha(path):
    h=hashlib.sha256()
    with open(path,'rb') as f:
        for b in iter(lambda:f.read(8*1024*1024),b''): h.update(b)
    return h.hexdigest()

def md5(path):
    h=hashlib.md5()
    with open(path,'rb') as f:
        for b in iter(lambda:f.read(8*1024*1024),b''): h.update(b)
    return h.hexdigest()

def public_vcf_path(chrom):
    directory=ROOT/'public-source-repair' if chrom==21 else DATA/'sources/1000g'
    return next(directory.glob('ALL.chr%d.*genotypes.vcf.gz'%chrom))

def write_report(value):
    temp=REPORT.with_suffix('.json.tmp')
    temp.write_text(json.dumps(value,indent=2,sort_keys=True)+'\n')
    os.replace(temp,REPORT)

def allowed(k):
    return k[0] in [str(c) for c in range(1,23)] and len(k[2])==len(k[3])==1 and k[2] in 'ACGT' and k[3] in 'ACGT' and k[2]!=k[3] and {k[2],k[3]} not in [{'A','T'},{'C','G'}]

def diagnostic(scores,denoms,truth,pops,pop_super,round_similarity=False):
    values=np.divide(scores,denoms,out=np.full_like(scores,-np.inf),where=denoms>0)
    if round_similarity: values=np.round(values*100,1)
    pred=np.argmax(values,axis=1)
    detail={}
    for j,p in enumerate(pops):
        ids=np.flatnonzero(truth==j)
        detail[p]={'n':int(len(ids)),'top1':int(np.sum(pred[ids]==j)), 'predictions':dict(Counter(pops[int(v)] for v in pred[ids]))}
    ties=values==values.max(axis=1)[:,None]
    return {'n':len(truth),'top1_correct':int(np.sum(pred==truth)), 'top1_fraction':float(np.mean(pred==truth)), 'top3_fraction':float(np.mean([int(t) in np.argsort(values[i])[-3:] for i,t in enumerate(truth)])), 'superpopulation_of_top1_fraction':float(np.mean([pop_super[pops[int(a)]]==pop_super[pops[int(b)]] for a,b in zip(pred,truth)])), 'top_tie_donors':int(np.sum(ties.sum(axis=1)>1)), 'true_label_among_tied_best':int(np.sum(ties[np.arange(len(truth)),truth])), 'score_rounding':'100x then one decimal' if round_similarity else 'unrounded', 'by_population':detail,'note':'Internal public-reference classification diagnostic, not an ethnicity accuracy estimate or a calibrated clinical/ancestry probability.'}

def main():
    assert OUT.parent==ROOT and OUT.resolve()!=ACTIVE.resolve()
    if OUT.exists() or REPORT.exists(): raise RuntimeError('Refusing to overwrite an existing candidate or manifest')
    started=time.time(); created=datetime.now(timezone.utc).isoformat()
    os.umask(0o077)
    source=sqlite3.connect('file:'+str(ACTIVE)+'?mode=ro',uri=True)
    before={'sha256':sha(ACTIVE),'size':ACTIVE.stat().st_size,'mtime_ns':ACTIVE.stat().st_mtime_ns}
    source_meta=dict(source.execute('select key,value from pack_meta'))
    labels={r[0]:r[1] for r in source.execute('select distinct population_code,population_label from ancestry_reference_markers where panel_name=?',(PANEL,))}
    panel_path=DATA/'sources/1000g/integrated_call_samples_v3.20130502.ALL.panel'
    with panel_path.open() as f: metadata={r['sample']:r for r in csv.DictReader(f,delimiter='\t')}
    pops=sorted({r['pop'] for r in metadata.values()}); assert len(pops)==26
    pop_super={p:next(r['super_pop'] for r in metadata.values() if r['pop']==p) for p in pops}
    repair=json.loads((ROOT/'public-source-repair/source_repair_manifest.json').read_text())
    pedigree_path=ROOT/'public-source-repair/integrated_call_samples_v3.20250704.ALL.ped'
    edges={s:set() for s in metadata}
    with pedigree_path.open() as f:
        for row in csv.DictReader(f,delimiter='\t'):
            sample=row['Individual ID']
            if sample not in edges: continue
            for field in ['Paternal ID','Maternal ID','Siblings','Second Order','Third Order','Children']:
                for other in re.findall(r'(?:HG|NA)\d+',row.get(field,'') or ''):
                    if other in edges and other!=sample: edges[sample].add(other); edges[other].add(sample)
    held=[]; held_blocked=set()
    for p in pops:
        donors=[s for s,r in metadata.items() if r['pop']==p]
        chosen=[]
        for s in sorted(donors,key=lambda s:hashlib.sha256(('zerothink-public-holdout-v1:'+s).encode()).digest()):
            if s not in held_blocked:
                chosen.append(s); held_blocked.add(s); held_blocked.update(edges[s])
                if len(chosen)==HOLD_PER_POP: break
        assert len(chosen)==HOLD_PER_POP
        held.extend(chosen)
    held_set=set(held); truth=np.array([pops.index(metadata[s]['pop']) for s in held])
    train_blocked=set(held_blocked); training=set()
    for s in sorted(metadata,key=lambda s:hashlib.sha256(('zerothink-public-training-v1:'+s).encode()).digest()):
        if s not in train_blocked:
            training.add(s); train_blocked.add(s); train_blocked.update(edges[s])
    assert not any(edges[s]&training for s in held_set|training)
    legacy={}
    for rid,c,pos,ref,alt,p,af,w in source.execute('select rsid,chrom,pos,ref,alt,population_code,allele_frequency,marker_weight from ancestry_reference_markers where panel_name=?',(PANEL,)):
        k=(str(c),int(pos),str(ref),str(alt))
        if allowed(k): legacy.setdefault(k,{'rid':rid,'freq':{},'weights':{}})['freq'][p]=float(af); legacy[k]['weights'][p]=float(w)
    candidates={}
    for rid,c,pos,ref,alt in source.execute("select distinct rsid,chrom,pos,ref,alt from ancestry_reference_markers where panel_name='1000G_Phase3_optimized_superpop'"):
        k=(str(c),int(pos),str(ref),str(alt))
        if allowed(k): candidates.setdefault(k,rid)
    for k,r in legacy.items(): candidates.setdefault(k,r['rid'])
    selected=set(legacy)
    remaining=TARGET-len(selected)
    for chrom in range(1,23):
        eligible=sorted([k for k in candidates if k[0]==str(chrom) and k not in selected],key=lambda k:k[1])
        quota=min(len(eligible),round(remaining*LENGTHS[chrom-1]/sum(LENGTHS)))
        if quota:
            indices=np.linspace(0,len(eligible)-1,quota,dtype=int)
            selected.update(eligible[int(i)] for i in indices)
    print(json.dumps({'stage':'selected','markers':len(selected),'legacy_compatible_markers':len(legacy),'public_heldout':len(held),'candidate_only':str(OUT)}),flush=True)
    dest=sqlite3.connect(str(OUT)); source.backup(dest,pages=2048,sleep=0.01)
    dest.execute('delete from ancestry_reference_markers where panel_name=?',(PANEL,)); dest.commit()
    n=len(held); shape=(n,len(pops))
    scenarios={name:[np.zeros(shape),np.zeros(shape)] for name in ['legacy_similarity_in_sample','candidate_similarity','candidate_hwe_log_likelihood','candidate_hwe_10pct_markers','candidate_hwe_50pct_missing']}
    skipped=Counter(); chroms={}; source_records=[]; inserted=0; all_markers=[]
    sample_counts=Counter(r['pop'] for s,r in metadata.items() if s in training)
    min_an={p:99999 for p in pops}; max_an={p:0 for p in pops}
    for chrom in range(1,23):
        start=time.time(); chrom_keys=sorted([k for k in selected if k[0]==str(chrom)],key=lambda k:k[1])
        path=public_vcf_path(chrom); index=Path(str(path)+'.tbi')
        expected=repair['expected'][path.name]; expected_index=repair['expected'][index.name]
        source_md5=md5(path); index_md5=md5(index)
        assert path.stat().st_size==expected['expected_bytes'] and source_md5==expected['expected_md5'], 'Official source MD5 mismatch: '+str(path)
        assert index.stat().st_size==expected_index['expected_bytes'] and index_md5==expected_index['expected_md5'], 'Official index MD5 mismatch: '+str(index)
        vcf=VCF(str(path),strict_gt=True,threads=1)
        assert len(vcf.samples)==2504 and set(vcf.samples)==set(metadata)
        hold_idx=np.array([vcf.samples.index(s) for s in held])
        pop_idx=[np.array([i for i,s in enumerate(vcf.samples) if metadata[s]['pop']==p and s in training]) for p in pops]
        batch=[]; kept=0
        for k in chrom_keys:
            c,pos,ref,alt=k; variant=None
            for r in vcf('%s:%d-%d'%(c,pos,pos)):
                if r.POS==pos and r.REF==ref and r.ALT==[alt]: variant=r; break
            if variant is None: skipped['exact_allele_not_found']+=1; continue
            if variant.FILTER not in [None,'PASS','.']: skipped['vcf_filter']+=1; continue
            gt=np.asarray(variant.genotypes,dtype=np.int16)[:,:2]
            if np.any((gt>1)): skipped['non_biallelic_gt']+=1; continue
            counts=[]; freq=[]
            for p,ids in zip(pops,pop_idx):
                alleles=gt[ids]; called=alleles>=0; an=int(called.sum()); ac=int(alleles[called].sum())
                counts.append(an); freq.append(ac/an if an else np.nan)
            if any(an<2*sample_counts[p]*0.98 for p,an in zip(pops,counts)): skipped['training_callrate_below_98pct']+=1; continue
            af=np.array(freq); held_gt=gt[hold_idx]; valid=(held_gt>=0).all(axis=1); dose=held_gt.sum(axis=1)
            h=hashlib.sha256(('%s:%d:%s:%s'%k).encode()).digest(); hashnum=int.from_bytes(h[:8],'big')
            similarity=1-np.abs(dose[:,None]-2*af[None,:])/2
            p=np.clip(af,0.005,0.995); hwe=np.where(dose[:,None]==0,2*np.log1p(-p),np.where(dose[:,None]==1,np.log(2*p*(1-p)),2*np.log(p)))
            for name,values,mask in [('candidate_similarity',similarity,valid),('candidate_hwe_log_likelihood',hwe,valid),('candidate_hwe_10pct_markers',hwe,valid if hashnum%10==0 else np.zeros(n,dtype=bool)),('candidate_hwe_50pct_missing',hwe,valid & ((np.arange(n)+hashnum%2)%2==0))]:
                scores,denom=scenarios[name]; scores+=np.where(mask[:,None],values,0); denom+=mask[:,None]
            if k in legacy:
                scores,denom=scenarios['legacy_similarity_in_sample']; old=legacy[k]
                for j,pop in enumerate(pops):
                    if pop in old['freq']:
                        w=old['weights'][pop]; vals=1-np.abs(dose-2*old['freq'][pop])/2
                        scores[:,j]+=np.where(valid,vals*w,0); denom[:,j]+=valid*w
            rid=legacy[k]['rid'] if k in legacy else candidates[k]
            for pop,afreq,an in zip(pops,freq,counts):
                batch.append((rid,c,pos,'GRCh37',ref,alt,PANEL,pop,labels.get(pop,pop),pop_super[pop],float(afreq),1.0,VERSION))
                min_an[pop]=min(min_an[pop],an); max_an[pop]=max(max_an[pop],an)
            all_markers.append('%s:%d:%s:%s'%k); kept+=1
            if len(batch)>=13000:
                dest.executemany('insert into ancestry_reference_markers values (?,?,?,?,?,?,?,?,?,?,?,?,?)',batch); inserted+=len(batch); batch=[]; dest.commit()
        if batch: dest.executemany('insert into ancestry_reference_markers values (?,?,?,?,?,?,?,?,?,?,?,?,?)',batch); inserted+=len(batch); dest.commit()
        vcf.close()
        source_records.append({'chromosome':chrom,'file':str(path),'size_bytes':path.stat().st_size,'sha256':sha(path),'md5':source_md5,'official_md5_verified':True,'index_sha256':sha(index),'index_md5':index_md5,'official_index_md5_verified':True,'source_url':'https://ftp.1000genomes.ebi.ac.uk/vol1/ftp/release/20130502/'+path.name,'marker_count':kept,'imported_row_count':kept*26})
        chroms[str(chrom)]={'requested':len(chrom_keys),'retained':kept,'seconds':round(time.time()-start,2)}
        print(json.dumps({'stage':'chromosome_complete','chromosome':chrom,**chroms[str(chrom)],'elapsed_seconds':round(time.time()-started,1)}),flush=True)
    validation={name:diagnostic(*value,truth,pops,pop_super) for name,value in scenarios.items()}
    # Synthetic mathematical invariants contain no donor genotypes.
    assert np.isclose((1-0.2)**2+2*0.2*(1-0.2)+0.2**2,1)
    assert np.isclose(1-abs(0-2*0.2)/2,1-abs(2-2*0.8)/2)
    assert inserted==len(all_markers)*26
    counts=dest.execute('select population_code,count(*) from ancestry_reference_markers where panel_name=? group by population_code',(PANEL,)).fetchall()
    assert len(counts)==26 and len(set(row[1] for row in counts))==1
    assert dest.execute('pragma quick_check').fetchone()[0]=='ok'
    status='offline_candidate_not_activated'
    for key,value in {'population_candidate_status':status,'population_candidate_version':VERSION,'population_candidate_markers':str(len(all_markers)),'population_candidate_heldout_donors':str(len(held)),'population_candidate_no_ethnicity_accuracy_claim':'true'}.items(): dest.execute('insert or replace into pack_meta values (?,?)',(key,value))
    for rec in source_records:
        dest.execute('insert or replace into data_sources (id,source_name,source_url,version_or_release,downloaded_at,file_hash_sha256,license_note,imported_row_count,pack_name,pack_version,local_path,importer_version) values (?,?,?,?,?,?,?,?,?,?,?,?)',('balanced-population-chr'+str(rec['chromosome']),'1000 Genomes Phase3 public genotypes / training donor subset',rec['source_url'],'20130502 v5b; candidate built '+created,'unknown: pre-existing local source',rec['sha256'],'IGSR public 1000 Genomes data without embargo; cite source; no reidentification.',rec['imported_row_count'],'ancestry_core','candidate-20260905',rec['file'],'balanced-population-builder-1'))
    dest.commit(); dest.close(); source.close()
    after={'sha256':sha(ACTIVE),'size':ACTIVE.stat().st_size,'mtime_ns':ACTIVE.stat().st_mtime_ns}
    assert before==after, 'Active pack changed during candidate build; investigate external writer'
    report={'status':status,'created_utc':created,'elapsed_seconds':round(time.time()-started,2),'candidate_path':str(OUT),'candidate_sha256':sha(OUT),'candidate_size_bytes':OUT.stat().st_size,'active_before':before,'active_after':after,'source_active_pack_meta':source_meta,'assembly':'GRCh37','population_count':26,'marker_count':len(all_markers),'population_rows':inserted,'shared_marker_set_sha256':hashlib.sha256(('\n'.join(all_markers)+'\n').encode()).hexdigest(),'sample_panel':{'path':str(panel_path),'sha256':sha(panel_path),'samples':2504},'heldout_sample_ids':held,'training_sample_counts':dict(sample_counts),'min_called_alleles_by_population':min_an,'max_called_alleles_by_population':max_an,'selection':'Existing non-palindromic named-panel markers plus deterministic coordinate-spread SNPs from the existing chip-position superpopulation set, with additional-marker chromosome quotas proportional to GRCh37 chromosome length; exact REF/ALT match to public genotypes; AF includes 0 and 1, same retained loci for every population.','filters':{'diploid_autosomes':'1-22','variant':'biallelic A/C/G/T SNVs only','ambiguous_strand':'A/T and C/G excluded','vcf_filter':'PASS or missing','per_population_training_callrate_min':0.98},'synthetic_invariants':'passed: probability normalization, complement symmetry, shared count and SQLite integrity','limits':['Same 26 study populations, no additional ethnic groups.','Selected from existing chip-position ascertainment, not a new unbiased genome-wide survey.','Coordinate spreading is not linkage-disequilibrium pruning; scores are not confidence probabilities.','Five deterministic held-out donors per population; Phase3 is described by its source as unrelated, no independent kinship recomputation performed.','Held-out donors are excluded from candidate population frequencies; legacy pack included them, so legacy comparison is in-sample and favors the legacy baseline.','Inherited superpopulation and specialist tables are unchanged and may include the held-out donors; evaluation is solely of the named 26-population panel.','No real customer files, consumer array-vendor-specific masks, relatives, or out-of-reference populations tested.','Broad HWE diagnostic is not the deployed similarity scoring method.','Not calibrated ancestry percentages, ethnicity determination, diagnosis or production-ready validation.'],'chromosomes':chroms,'excluded_markers':dict(skipped),'source_files':source_records,'validation':validation,'activation_recommendation':'Hold inactive pending review of aggregate diagnostics and production matcher compatibility. No automatic activation.'}
    write_report(report)
    report['pedigree']={'source_url':'https://ftp.1000genomes.ebi.ac.uk/vol1/ftp/release/20130502/'+pedigree_path.name,'sha256':sha(pedigree_path),'training_samples':len(training),'excluded_known_relatives':sorted(set(metadata)-training-held_set),'method':'Use documented parents, children, siblings, second-order and third-order relations from the 20250704 public pedigree; no related pair in holdouts, no holdout-training related pair, and deterministic greedy removal of known within-training pairs. No independent genotype-based kinship inference.'}
    report['training_sample_ids']=sorted(training)
    report['limits']=[v for v in report['limits'] if not v.startswith('Five deterministic')]
    report['limits'].append('Five public held-out donors per population with published pedigree separation through third-order relationships; no new genotype-based kinship estimates or external-cohort validation. Source genotypes were jointly called/phased before this split.')
    report['source_repair_manifest']=str(ROOT/'public-source-repair/source_repair_manifest.json')
    report['official_checksum_catalog_url']=repair['source_catalog_url']
    report['runtime']={'numpy':np.__version__,'cyvcf2':__import__('cyvcf2').__version__,'threads':1,'priority':'nice 19 / ionice idle'}
    write_report(report)
    print(json.dumps({'stage':'complete','markers':len(all_markers),'rows':inserted,'candidate_sha256':report['candidate_sha256'],'seconds':report['elapsed_seconds'],'diagnostics':{k:{m:v[m] for m in ['top1_fraction','top3_fraction','superpopulation_of_top1_fraction']} for k,v in validation.items()}}),flush=True)

if __name__=='__main__': main()
