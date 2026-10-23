#!/usr/bin/env python3
"""Fair public held-out comparison on legacy loci, with no genotype export."""
import csv, hashlib, json, os, sqlite3, time
from pathlib import Path
os.environ['OPENBLAS_NUM_THREADS']='1'
import numpy as np
from cyvcf2 import VCF
from build_population_candidate import ROOT, DATA, ACTIVE, OUT, PANEL, REPORT, diagnostic, sha

def main():
    started=time.time(); report=json.loads(REPORT.read_text())
    result_path=ROOT/'population_candidate_validation_supplement.json'
    if result_path.exists(): raise RuntimeError('Refusing to overwrite validation supplement')
    old=sqlite3.connect('file:'+str(ACTIVE)+'?mode=ro',uri=True)
    legacy={tuple(r) for r in old.execute('select distinct chrom,pos,ref,alt from ancestry_reference_markers where panel_name=?',(PANEL,))}; old.close()
    candidate=sqlite3.connect('file:'+str(OUT)+'?mode=ro',uri=True)
    pops=sorted(report['training_sample_counts']); pidx={p:i for i,p in enumerate(pops)}
    with open(report['sample_panel']['path']) as f: metadata={r['sample']:r for r in csv.DictReader(f,delimiter='\t')}
    held=report['heldout_sample_ids']; truth=np.array([pidx[metadata[s]['pop']] for s in held]); n=len(held)
    pop_super={p:next(r['super_pop'] for r in metadata.values() if r['pop']==p) for p in pops}
    freq={}
    for c,pos,ref,alt,p,af in candidate.execute('select chrom,pos,ref,alt,population_code,allele_frequency from ancestry_reference_markers where panel_name=?',(PANEL,)):
        key=(c,pos,ref,alt)
        if key in legacy: freq.setdefault(key,np.full(len(pops),np.nan))[pidx[p]]=af
    candidate.close()
    values={name:[np.zeros((n,len(pops))),np.zeros((n,len(pops)))] for name in ['training_only_legacy_loci_similarity','training_only_legacy_loci_hwe']}
    matched=0
    for c in sorted({k[0] for k in freq},key=int):
        path=next((DATA/'sources/1000g').glob('ALL.chr%s.*genotypes.vcf.gz'%c)); v=VCF(str(path),strict_gt=True,threads=1)
        ids=np.array([v.samples.index(s) for s in held])
        for key in sorted([k for k in freq if k[0]==c],key=lambda k:k[1]):
            _,pos,ref,alt=key; variant=None
            for r in v('%s:%d-%d'%(c,pos,pos)):
                if r.POS==pos and r.REF==ref and r.ALT==[alt]: variant=r; break
            if variant is None: raise RuntimeError('Previously accepted source allele no longer resolves')
            gt=np.asarray(variant.genotypes,dtype=np.int16)[ids,:2]; valid=(gt>=0).all(axis=1); dose=gt.sum(axis=1); af=freq[key]; assert np.all(np.isfinite(af))
            similarity=1-np.abs(dose[:,None]-2*af)/2
            p=np.clip(af,0.005,0.995); hwe=np.where(dose[:,None]==0,2*np.log1p(-p),np.where(dose[:,None]==1,np.log(2*p*(1-p)),2*np.log(p)))
            for name,scores in [('training_only_legacy_loci_similarity',similarity),('training_only_legacy_loci_hwe',hwe)]:
                values[name][0]+=np.where(valid[:,None],scores,0); values[name][1]+=valid[:,None]
            matched+=1
        v.close(); print(json.dumps({'stage':'fair_subset_validation','chromosome':c,'processed':matched}),flush=True)
    result={'status':'offline_diagnostic_only','legacy_loci_count':matched,'heldout_samples':len(held),'training_donors_identical_to_candidate':True,'candidate_sha256':report['candidate_sha256'],'results':{k:diagnostic(*v,truth,pops,pop_super) for k,v in values.items()},'interpretation':'Both old-loci and expanded-loci frequencies now use the same training donors and shared population denominators. This isolates the coverage expansion from legacy missing-denominator and holdout-leakage effects. Only the 26-population named panel is evaluated. Scores use unrounded math; the website rounds displayed scores and may reorder ties.','limitations':report['limits'],'seconds':round(time.time()-started,2)}
    result['results']['training_only_legacy_loci_similarity_rounded']=diagnostic(*values['training_only_legacy_loci_similarity'],truth,pops,pop_super,round_similarity=True)
    result_path.write_text(json.dumps(result,indent=2,sort_keys=True)+'\n'); os.chmod(result_path,0o600)
    print(json.dumps({'stage':'fair_subset_complete','markers':matched,'seconds':result['seconds'],'metrics':{k:{m:v[m] for m in ['top1_fraction','top3_fraction','superpopulation_of_top1_fraction']} for k,v in result['results'].items()}}),flush=True)

if __name__=='__main__': main()
