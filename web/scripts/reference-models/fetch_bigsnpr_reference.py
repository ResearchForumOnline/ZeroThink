"""Fetch only the author's CC BY 4.0 aggregate frequencies/loadings into new private staging."""
from research_paths import require_path
import hashlib, json, os, time, urllib.request
from pathlib import Path

BASE = require_path('ZDNA_COMPOSITION_ROOT')
os.umask(0o077)
BASE.mkdir(mode=0o700, exist_ok=True)
SOURCE = BASE / 'source'
SOURCE.mkdir(mode=0o700, exist_ok=True)
api = 'https://api.figshare.com/v2/articles/16858534/versions/3'
with urllib.request.urlopen(api, timeout=30) as response:
    meta = json.load(response)
assert meta['license']['name'] == 'CC BY 4.0'
expected = {31620953: ('projection.csv.gz', 887617024, '8dcbe16489db26cded42ef6b375e096c'), 31620968: ('ref_freqs.csv.gz', 890450581, '18be8a9b428630a5196d362083fef940')}
assert sum(s for _, s, _ in expected.values()) < 2_000_000_000
(SOURCE / 'figshare-v3-metadata.json').write_text(json.dumps(meta, indent=2)+'\n')
manifest = dict(source='Florian Prive, published aggregate reference for bigsnpr ancestry model', doi=meta['doi'], license=meta['license'], downloaded_utc=time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()), files=[])
for item in meta['files']:
    if item['id'] not in expected:
        continue
    name, size, md5 = expected[item['id']]
    assert item['size'] == size and item['computed_md5'] == md5 and item['name'] == name
    dest=SOURCE/name
    if dest.exists():
        assert dest.stat().st_size == size
    else:
        part=SOURCE/(name+'.part')
        assert not part.exists(), 'Preserve incomplete earlier source; choose a new verified resume procedure'
        n=0; start=time.monotonic()
        with urllib.request.urlopen(item['download_url'], timeout=90) as source, part.open('xb') as output:
            while True:
                block=source.read(4*1024*1024)
                if not block:break
                n+=len(block); assert n <= size
                output.write(block)
        assert n == size
        part.rename(dest)
        print(json.dumps(dict(downloaded=name, bytes=n, seconds=round(time.monotonic()-start, 1))), flush=True)
    digest=hashlib.sha256(); old=hashlib.md5()
    with dest.open('rb') as handle:
        for block in iter(lambda:handle.read(8*1024*1024), b''):
            digest.update(block);old.update(block)
    assert old.hexdigest() == md5
    manifest['files'].append(dict(name=name,bytes=size,md5=md5,sha256=digest.hexdigest(),url=item['download_url']))
(SOURCE/'SOURCE_MANIFEST.json').write_text(json.dumps(manifest,indent=2)+'\n')
print(json.dumps(manifest),flush=True)
