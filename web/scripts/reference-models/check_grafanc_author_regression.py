"""Reproduce author public 1000 Genomes fixtures; no individual data leave private staging."""
from research_paths import require_path
import collections, hashlib, json, os, subprocess, urllib.request
from pathlib import Path
REV='b5e51c78b5004a8388418ce56755bb2f71f24eb5';BASE=require_path('ZDNA_GRAFANC_ROOT');OUT=BASE/'evaluation/author-public-regression';OUT.mkdir(mode=0o700,exist_ok=True);os.chmod(OUT,0o700)
def get(url):return urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'ZeroThink-public-source-audit/1.0'}),timeout=40).read()
tree=json.loads(get('https://api.github.com/repos/jimmy-penn/grafanc/git/trees/'+REV+'?recursive=1'));entries={e['path']:e for e in tree['tree'] if e['type']=='blob'}
files=[];tables={}
for mode in ['rs','gb37','gb38']:
 name='TGP_40_50K_'+mode
 for suffix in ['bed','bim','fam']:
  key='medium_testing/input/'+name+'.'+suffix;entry=entries[key];data=get('https://raw.githubusercontent.com/jimmy-penn/grafanc/'+REV+'/'+key)
  assert len(data)==entry['size'] and hashlib.sha1(('blob '+str(len(data))+'\0').encode()+data).hexdigest()==entry['sha']
  path=OUT/(name+'.'+suffix);path.write_bytes(data);os.chmod(path,0o600);files.append(dict(source=key,bytes=len(data),sha256=hashlib.sha256(data).hexdigest(),git_blob_sha=entry['sha']))
 output=OUT/(name+'.private.tsv');result=subprocess.run([str(BASE/'grafanc-built'),str(OUT/(name+'.bed')),str(output),'--threads','1','--maxmem','100'],env=dict(os.environ,GRAFPATH=str(BASE/'bundle')),stdout=subprocess.PIPE,stderr=subprocess.STDOUT,timeout=60)
 assert result.returncode==1 and output.is_file();(OUT/(name+'.private.log')).write_bytes(result.stdout)
 rows=[line.split() for line in output.read_text().splitlines()];assert rows[0][0]=='Sample' and len(rows)==41
 tables[mode]=rows
parity={}
for mode in ['gb37','gb38']:
 assert tables['rs'][0]==tables[mode][0]
 assert [r[0] for r in tables['rs'][1:]]==[r[0] for r in tables[mode][1:]]
 numeric=[abs(float(a)-float(b)) for left,right in zip(tables['rs'][1:],tables[mode][1:]) for a,b in zip(left[2:-1],right[2:-1])]
 parity[mode]=dict(max_absolute_numeric_difference=max(numeric),same_model_labels=sum(a[-1]==b[-1] for a,b in zip(tables['rs'][1:],tables[mode][1:])),same_called_marker_count=sum(a[1]==b[1] for a,b in zip(tables['rs'][1:],tables[mode][1:])))
header=tables['rs'][0];records=[dict(zip(header,r)) for r in tables['rs'][1:]];counts=collections.Counter(str(int(r['AncGroupID'])//100*100) for r in records)
report=dict(status='executed',source_revision=REV,public_source='Author repository medium_testing/input/TGP_40_50K_* documented 1000 Genomes test fixtures',samples=40,minimum_called_markers=min(int(r['SNPs']) for r in records),maximum_called_markers=max(int(r['SNPs']) for r in records),broad_model_counts=dict(counts),rsid_grch37_grch38_exact_output_parity=tables['rs']==tables['gb37']==tables['gb38'],format_comparison=parity,files=files,validation_boundary='Author public fixture regression, not an independent holdout or consumer calibration. Individual source calls, IDs and output remain private. No labels are treated as ground-truth nationality or ethnicity.')
(BASE/'evaluation/grafanc-author-regression.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps({k:v for k,v in report.items() if k!='files'},indent=2))
