"""Challenge pinned GrafAnc using only previously allowlisted current public HO samples.
All individual-level calls and predictions remain in private server staging.
"""
from research_paths import require_path
import collections, hashlib, json, os, subprocess, time
from pathlib import Path
import numpy as np
BASE=require_path('ZDNA_GRAFANC_ROOT')
PUBLIC=require_path('ZDNA_REGIONAL_DATA')
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def decode(data,n):
 b=np.frombuffer(data,dtype=np.uint8)
 return np.column_stack([(b>>s)&3 for s in [6,4,2,0]]).reshape(-1)[:n]
sel=json.loads((PUBLIC/'regional_selection.json').read_text()); ranges=json.loads((PUBLIC/'regional_range_manifest.json').read_text(encoding='utf-8-sig')); hashes={r['sample_id']:r['sha256'] for r in ranges['rows']}
snps=[x.split() for x in (PUBLIC/'v66.p1_compatibility_HO.aadr.patch.PUB.snp').read_text().splitlines()]
dupes=collections.Counter((s[1],s[3]) for s in snps)
refpath=BASE/'grafanc-source/cpp/data/AncSnpPopAFs.txt'; lines=refpath.read_text().splitlines(); ref={}
for line in lines[1:]:
 s=line.split();ref[(s[0],s[1])]=s[:6]
selected=[];excluded=collections.Counter()
for i,s in enumerate(snps):
 key=(s[1],s[3]);r=ref.get(key)
 if r is None:continue
 if dupes[key]!=1:excluded['duplicate_source_coordinate']+=1;continue
 if ''.join(sorted(s[4:6])) in ['AT','CG']:excluded['palindromic_pair']+=1;continue
 if set(s[4:6])!=set(r[4:6]):excluded['allele_pair_mismatch']+=1;continue
 if s[0].startswith('rs') and s[0][2:].isdigit() and s[0][2:]!=r[3]:excluded['rsid_mismatch']+=1;continue
 selected.append((i,r,s[4]==r[4]))
matrix=[]
for sample in sel['samples']:
 assert sample['data_type']=='HumanOrigins' and sample['assessment']=='Pass'
 path=PUBLIC/sample['row_filename'];assert sha(path)==hashes[sample['sample_id']]
 calls=decode(path.read_bytes(),len(snps));dose=np.where(calls==3,-1,2-calls.astype(np.int8))
 matrix.append([dose[i] if same or dose[i]<0 else 2-dose[i] for i,r,same in selected])
matrix=np.array(matrix,dtype=np.int8)
outdir=BASE/'evaluation';outdir.mkdir(mode=0o700,exist_ok=True);os.chmod(outdir,0o700)
vcf=outdir/'current-public-HO.private.vcf'
with os.fdopen(os.open(str(vcf),os.O_CREAT|os.O_WRONLY|os.O_TRUNC,0o600),'w') as f:
 f.write('##fileformat=VCFv4.2\n##reference=GRCh37\n##FORMAT=<ID=GT,Number=1,Type=String,Description="Genotype">\n')
 for chrom in range(1,23):f.write('##contig=<ID=%d>\n'%chrom)
 f.write('#CHROM\tPOS\tID\tREF\tALT\tQUAL\tFILTER\tINFO\tFORMAT\t'+'\t'.join('PUBLIC%03d'%i for i in range(len(sel['samples'])))+'\n')
 for j,(_,r,_) in enumerate(selected):
  g=['./.' if d<0 else ['0/0','0/1','1/1'][d] for d in matrix[:,j]]
  f.write('\t'.join([r[0],r[1],'rs'+r[3],r[4],r[5],'.','PASS','.','GT']+g)+'\n')
start=time.monotonic(); env=dict(os.environ,GRAFPATH=str(BASE/'grafanc-source/cpp'));pred=outdir/'current-public-HO.private.tsv'
result=subprocess.run([str(BASE/'grafanc-built'),str(vcf),str(pred),'--threads','1','--maxmem','32'],env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,timeout=120)
(outdir/'execution.private.log').write_bytes(result.stdout)
# Pinned upstream main returns 1 on successful completion; require complete parsed output.
assert result.returncode in [0,1] and pred.is_file()
rows=[x.split() for x in pred.read_text().splitlines()];header=rows[0];summaries={};total=0
for row in rows[1:]:
 d=dict(zip(header,row));idx=int(d['Sample'][6:]);code=sel['samples'][idx]['group_code'];g=summaries.setdefault(code,dict(samples=0,called_marker_min=10**9,called_marker_max=0,broad_predictions={},regional_predictions={}))
 g['samples']+=1;n=int(d['SNPs']);g['called_marker_min']=min(g['called_marker_min'],n);g['called_marker_max']=max(g['called_marker_max'],n)
 group=d['AncGroupID'];broad=str(int(group)//100*100)
 for k,value in [('broad_predictions',broad),('regional_predictions',group)]:g[k][value]=g[k].get(value,0)+1
 total+=1
report=dict(status='executed_published_model',model_revision='b5e51c78b5004a8388418ce56755bb2f71f24eb5',binary_sha256=sha(BASE/'grafanc-built'),frequency_asset_sha256=sha(refpath),source_individuals=total,common_tool_markers=len(selected),chromosome_count=len(set(r[0] for _,r,_ in selected)),source_alignment='Unique GRCh37 exact coordinate, nonpalindromic direct allele-pair equality to published GrafAnc table; reverse source allele order causes explicit dosage swap, no strand complement. Source rsIDs must agree when present.',excluded=dict(excluded),groups=summaries,elapsed_seconds=round(time.monotonic()-start,3),privacy='Current public allowlisted sources only; individual genotypes and predictions remain private. This aggregate contains no donor IDs or genotypes.',validation_boundary='Cross-dataset challenge, not a verified independent holdout: reference weights are primarily UKBB plus 1KGP PUR, while HGDP is used in the author classifier definition and benchmark. Some AADR Human Origins samples overlap HGDP, so label-selection overlap is possible. No consumer accuracy estimate or ancestry percentages are claimed.')
(outdir/'grafanc-public-challenge.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
