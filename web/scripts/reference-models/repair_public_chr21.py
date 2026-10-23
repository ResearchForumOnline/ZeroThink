from research_paths import require_path
import hashlib, json, os, urllib.request
from pathlib import Path
from datetime import datetime,timezone
ROOT=require_path('ZDNA_UPGRADE_ROOT').resolve()
DEST=ROOT/'public-source-repair'; DEST.mkdir(mode=0o700,exist_ok=True)
BASE='https://ftp.1000genomes.ebi.ac.uk/vol1/ftp/'
expected={}
with urllib.request.urlopen(BASE+'current.tree',timeout=60) as response:
    catalog_headers=dict(response.headers)
    for line in response:
        columns=line.decode().rstrip().split('\t')
        if len(columns)>=5 and columns[0].startswith('ftp/release/20130502/'):
            filename=columns[0].split('/')[-1]
            if filename.startswith('ALL.chr') and '.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz' in filename:
                expected[filename]={'expected_bytes':int(columns[2]),'expected_md5':columns[4],'upstream_mtime':columns[3]}
print(json.dumps({'stage':'official_checksums','entries':len(expected)}),flush=True)
name='ALL.chr21.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz'
downloads=[]
for filename in [name,name+'.tbi','integrated_call_samples_v3.20250704.ALL.ped','README_phase3_callset_20150220','README_known_issues_20200731']:
    output=DEST/filename
    if output.exists(): raise RuntimeError('Refusing to overwrite staged source: '+str(output))
    url=BASE+'release/20130502/'+filename
    h=hashlib.sha256(); m=hashlib.md5(); count=0
    with urllib.request.urlopen(url,timeout=60) as response, open(str(output)+'.part','xb') as f:
        headers=dict(response.headers)
        for block in iter(lambda:response.read(8*1024*1024),b''):
            count+=len(block); h.update(block); m.update(block); f.write(block)
    if filename in expected:
        assert count==expected[filename]['expected_bytes']
        assert m.hexdigest()==expected[filename]['expected_md5']
    os.rename(str(output)+'.part',output); os.chmod(output,0o600)
    item={'file':str(output),'source_url':url,'downloaded_at':datetime.now(timezone.utc).isoformat(),'size_bytes':count,'sha256':h.hexdigest(),'md5':m.hexdigest(),'official_md5_verified':filename in expected,'headers':headers}
    if filename in expected: item.update(expected[filename])
    downloads.append(item); print(json.dumps({'stage':'download_verified','filename':filename,'size':count,'official_md5_verified':filename in expected}),flush=True)
result={'source_catalog_url':BASE+'current.tree','catalog_headers':catalog_headers,'expected':expected,'downloads':downloads,'note':'Original live source files were not modified. Catalog is upstream historical inventory; exact version filenames and MD5 checked.'}
(DEST/'source_repair_manifest.json').write_text(json.dumps(result,indent=2,sort_keys=True)+'\n')
print('pedigree_header', (DEST/'integrated_call_samples_v3.20250704.ALL.ped').read_text().splitlines()[0],flush=True)
