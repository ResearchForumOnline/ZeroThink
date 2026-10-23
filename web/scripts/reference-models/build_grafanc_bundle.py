"""Assemble immutable private runtime bundle from pinned public aggregate sources."""
from research_paths import require_path
import collections, hashlib, json, os, re, shutil, sqlite3
from pathlib import Path
BASE=require_path('ZDNA_GRAFANC_ROOT');SOURCE=BASE/'grafanc-source';OUT=BASE/'bundle'
assert not OUT.exists(),'New artifact only'
(OUT/'data').mkdir(parents=True);(OUT/'licenses').mkdir();(OUT/'bin').mkdir()
shutil.copy2(BASE/'grafanc-built',OUT/'bin/grafanc');os.chmod(OUT/'bin/grafanc',0o755)
shutil.copy2(SOURCE/'cpp/data/AncSnpPopAFs.txt',OUT/'data/AncSnpPopAFs.txt')
shutil.copy2(SOURCE/'LICENSE',OUT/'licenses/GrafAnc-MIT.txt');shutil.copy2(BASE/'htslib-1.20/LICENSE',OUT/'licenses/HTSlib-LICENSE.txt')
shutil.copy2(SOURCE/'SOURCE_MANIFEST.json',OUT/'SOURCE_MANIFEST.json')
rows=[x.split() for x in (SOURCE/'cpp/data/AncSnpPopAFs.txt').read_text().splitlines()];header=rows.pop(0)
assert len(rows)==282424 and len(header)==32
counts37=collections.Counter((x[0],x[1]) for x in rows);counts38=collections.Counter((x[0],x[2]) for x in rows);rsCounts=collections.Counter(x[3] for x in rows)
db=sqlite3.connect(OUT/'data/marker_index.sqlite');db.executescript('PRAGMA journal_mode=DELETE; CREATE TABLE markers(chrom TEXT,pos37 INTEGER,pos38 INTEGER,rsid TEXT PRIMARY KEY,ref TEXT,alt TEXT); CREATE UNIQUE INDEX position37 ON markers(chrom,pos37); CREATE UNIQUE INDEX position38 ON markers(chrom,pos38);')
accepted=[];excluded=collections.Counter()
for r in rows:
 if not r[0].isdigit() or not 1<=int(r[0])<=22:excluded['nonautosomal']+=1;continue
 if counts37[(r[0],r[1])]!=1 or counts38[(r[0],r[2])]!=1 or rsCounts[r[3]]!=1:excluded['nonunique']+=1;continue
 if ''.join(sorted(r[4:6])) not in ['AC','AG','CT','GT']:excluded['palindromic_or_invalid_pair']+=1;continue
 accepted.append((r[0],int(r[1]),int(r[2]),'rs'+r[3],r[4],r[5]))
db.executemany('INSERT INTO markers VALUES(?,?,?,?,?,?)',accepted);db.commit();assert db.execute('PRAGMA integrity_check').fetchone()[0]=='ok';db.close()
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
files={str(p.relative_to(OUT)):dict(sha256=sha(p),bytes=p.stat().st_size) for p in OUT.rglob('*') if p.is_file()}
regions={}
for line in (SOURCE/'GrafAncDocumentation.md').read_text().splitlines():
 m=re.match(r'\|\s*(\d{3})\s*\|\s*([^|]+)\|\s*([^|]*)\|',line)
 if m and not m[2].strip() in ['AFR','MEN','EUR','SAS','EAS','AMR','OCN','MIX']:regions[m[1]]=dict(label=m[2].strip(),reference_scope=m[3].strip())
assert len(regions)==40
manifest=dict(schema_version='grafanc-bundle-v1',model='GrafAnc',version='1.0.0 / b5e51c78b5004a8388418ce56755bb2f71f24eb5',revision='b5e51c78b5004a8388418ce56755bb2f71f24eb5',source_url='https://github.com/jimmy-penn/grafanc',paper_url='https://doi.org/10.1016/j.xhgg.2025.100530',license='MIT; University of Pennsylvania2025; notices retained',reference_provenance='Published aggregate allele frequencies from26reference columns, primarily UKBiobank plus1000Genomes PUR, redistributed with the author MITrepository. No UKBiobank individual-level data accessed.',reference_columns=header[6:],source_marker_count=282424,usable_marker_count=len(accepted),index_excluded=dict(excluded),genome_builds=['GRCh37','GRCh38'],minimum_broad_markers=10000,minimum_regional_markers=50000,regions=regions,files=files,validation_boundary='Published population-structure classifier. Current public HO challenge checks cross-source compatibility and broad MENA handling but is not independent consumer calibration. No generalized out-of-reference detection guaranteed; three-way barycentric fields never exposed.',binary_success_exit_code=1,sqlite_integrity='ok',sqlite_journal_mode='delete')
(OUT/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
print(json.dumps(dict(bundle=str(OUT),manifest_sha256=sha(OUT/'manifest.json'),usable_marker_count=len(accepted),region_groups=len(regions),files=files),indent=2))
