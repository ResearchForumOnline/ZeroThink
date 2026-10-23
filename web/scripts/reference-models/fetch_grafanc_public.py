"""Pin published GrafAnc code and aggregate reference assets, excluding genotype fixtures."""
from research_paths import require_path
import concurrent.futures, hashlib, json, urllib.request
from pathlib import Path
REV='b5e51c78b5004a8388418ce56755bb2f71f24eb5'
ROOT=require_path('ZDNA_GRAFANC_ROOT')/'grafanc-source'
def get(url):
 return urllib.request.urlopen(urllib.request.Request(url,headers={'User-Agent':'ZeroThink-public-source-audit/1.0'}),timeout=90).read()
tree=json.loads(get('https://api.github.com/repos/jimmy-penn/grafanc/git/trees/'+REV+'?recursive=1'))
entries=[e for e in tree['tree'] if e['type']=='blob' and (e['path'].startswith('cpp/') or e['path'] in ['README.md','LICENSE','GrafAncDocumentation.md','PopMeanScores.txt','bin/grafanc'])]
def fetch(e):
 path=ROOT/e['path'];path.parent.mkdir(parents=True,exist_ok=True)
 data=get('https://raw.githubusercontent.com/jimmy-penn/grafanc/'+REV+'/'+e['path'])
 assert len(data)==e['size']
 assert hashlib.sha1(('blob '+str(len(data))+'\0').encode()+data).hexdigest()==e['sha']
 path.write_bytes(data)
 return dict(path=e['path'],bytes=len(data),sha256=hashlib.sha256(data).hexdigest(),git_blob_sha=e['sha'])
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:files=list(pool.map(fetch,entries))
manifest=dict(repository='https://github.com/jimmy-penn/grafanc',revision=REV,license='MIT; 2025 University of Pennsylvania notice retained',selection='Code, published aggregate frequency reference, documentation, prebuilt binary; no public or restricted individual genotype fixture downloaded',files=files)
(ROOT/'SOURCE_MANIFEST.json').write_text(json.dumps(manifest,indent=2)+'\n')
print(json.dumps(dict(status='downloaded',files=len(files),bytes=sum(f['bytes'] for f in files),revision=REV)))
