"""Align aggregate model weights to current allowed public HO calls; keep calls private.

No personal test upload is read. GRCh37, unique coordinates, direct nonpalindromic
allele pairs, agreeing canonical rsIDs. Author alleles are coding alleles.
"""
from research_paths import require_path
import collections, gzip, hashlib, json, os, time
from pathlib import Path
import numpy as np

os.umask(0o077)
BASE=require_path('ZDNA_COMPOSITION_ROOT')
PUB=require_path('ZDNA_REGIONAL_DATA')
OUT=BASE/'evaluation';OUT.mkdir(mode=0o700,exist_ok=True)
def sha(p):
    h=hashlib.sha256()
    with p.open('rb') as f:
        for b in iter(lambda:f.read(8*1024*1024),b''):h.update(b)
    return h.hexdigest()
snps=[r.split() for r in (PUB/'v66.p1_compatibility_HO.aadr.patch.PUB.snp').read_text().splitlines()]
counts=collections.Counter((s[1],s[3]) for s in snps)
targets={(s[1],s[3]):(i,s) for i,s in enumerate(snps) if counts[s[1],s[3]]==1 and s[1] in list(map(str,range(1,23))) and ''.join(sorted(s[4:6])) in ['AC','AG','CT','GT']}
frequency=[];loading=[];loci=[];sourceindices=[];swapped=[];seen=set();excluded=collections.Counter();rows=0;start=time.monotonic()
with gzip.open(BASE/'source/ref_freqs.csv.gz','rt') as f,gzip.open(BASE/'source/projection.csv.gz','rt') as p:
    fh=f.readline().strip().split(',');ph=p.readline().strip().split(',');assert fh[:5]==ph[:5]
    groups=fh[5:];assert len(groups)==21 and len(ph[5:])==16
    for fl,pl in zip(f,p):
        rows+=1
        a=fl.rstrip('\n').split(',',5);b=pl.rstrip('\n').split(',',5)
        if a[:5]!=b[:5]:raise RuntimeError('Published file row alignment differs')
        key=(a[0],a[1]);source=targets.get(key)
        if source is None:continue
        if key in seen:raise RuntimeError('Duplicate selected model coordinate')
        seen.add(key);i,s=source
        if set(s[4:6])!=set(a[2:4]):excluded['allele_pair_mismatch']+=1;continue
        if s[0].startswith('rs') and s[0][2:].isdigit() and s[0]!=a[4]:excluded['rsid_mismatch']+=1;continue
        af=np.fromstring(a[5],sep=',');pc=np.fromstring(b[5],sep=',')
        if len(af)!=21 or len(pc)!=16 or not np.isfinite(pc).all() or not ((af>=0)&(af<=1)).all():raise RuntimeError('Invalid aggregate row')
        frequency.append(af);loading.append(pc);loci.append(a[:5]);sourceindices.append(i);swapped.append(s[4]!=a[2])
    assert f.readline()=='' and p.readline()==''
assert rows==5816590 and len(loci)>100000
frequency=np.array(frequency);loading=np.array(loading);loci=np.array(loci);sourceindices=np.array(sourceindices);swapped=np.array(swapped)
selection=json.loads((PUB/'regional_selection.json').read_text());ranges=json.loads((PUB/'regional_range_manifest.json').read_text(encoding='utf-8-sig'));hashes={r['sample_id']:r['sha256'] for r in ranges['rows']}
calls=[];labels=[]
for sample in selection['samples']:
    assert sample['data_type']=='HumanOrigins' and sample['assessment']=='Pass'
    path=PUB/sample['row_filename'];assert sha(path)==hashes[sample['sample_id']]
    bytes_=np.frombuffer(path.read_bytes(),dtype=np.uint8)
    codes=((bytes_[sourceindices//4] >> (6-2*(sourceindices%4))) & 3).astype(np.int8)
    dose=np.where(codes==3,-1,2-codes)
    dose=np.where(swapped & (dose>=0),2-dose,dose)
    calls.append(dose);labels.append(sample['group_code'])
np.savez_compressed(OUT/'public-HO.private.npz',calls=np.array(calls,dtype=np.int8),labels=np.array(labels),loci=loci)
np.savez_compressed(OUT/'model-HO-aggregate.npz',frequency=frequency,loading=loading,loci=loci,groups=np.array(groups))
report=dict(status='source_aligned',source_rows=rows,matched_loci=len(loci),chromosomes=len(set(loci[:,0])),groups=groups,public_individuals=len(calls),public_groups=dict(collections.Counter(labels)),excluded=dict(excluded),alignment='Unique GRCh37 coordinate; direct nonpalindromic allele-pair equality; canonical rsID agreement; source dosage explicitly reversed when coding-allele order differs; no strand complements or genotype imputation',source_snp_sha256=sha(PUB/'v66.p1_compatibility_HO.aadr.patch.PUB.snp'),selection_manifest_sha256=sha(PUB/'regional_selection.json'),aggregate_npz_sha256=sha(OUT/'model-HO-aggregate.npz'),elapsed_seconds=round(time.monotonic()-start,2),privacy='Individual calls remain in private server evaluation only; aggregate asset contains reference frequencies and loadings only.')
(OUT/'alignment.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report),flush=True)
