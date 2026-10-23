from research_paths import require_path
import sqlite3,json,hashlib
from pathlib import Path
stage=require_path('ZDNA_ORIGINS_ROOT')
path=stage/'archaic_markers.sqlite'
manifest=json.loads((stage/'archaic_reference_manifest.json').read_text())
metadata=manifest['metadata']
metadata.update({'source_version':metadata['pack_version'],'assembly':'GRCh37','evidence_type':'published_introgression_tag','tag_count_by_group':metadata['group_counts'],'ascertainment':'19 non-African 1000 Genomes populations; no SGDP donor files; conservative distinct-archaic tags','source_hashes':{s['source_name']:s['file_hash_sha256'] for s in manifest['sources']}})
with sqlite3.connect(path) as con:
 con.execute('CREATE INDEX IF NOT EXISTS idx_archaic_build_locus ON archaic_markers(build,chrom,pos)')
 con.executemany('INSERT OR REPLACE INTO pack_meta VALUES(?,?)',[(k,json.dumps(v,separators=(',',':')) if isinstance(v,(dict,list)) else str(v)) for k,v in metadata.items()])
 con.commit()
 assert con.execute('PRAGMA quick_check').fetchone()[0]=='ok'
manifest['output_sha256']=hashlib.sha256(path.read_bytes()).hexdigest()
manifest['output_bytes']=path.stat().st_size
(stage/'archaic_reference_manifest.json').write_text(json.dumps(manifest,indent=2))
print(json.dumps({'sha256':manifest['output_sha256'],'bytes':manifest['output_bytes'],'counts':metadata['group_counts']}))
