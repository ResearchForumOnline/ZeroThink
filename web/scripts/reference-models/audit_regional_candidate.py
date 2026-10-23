"""Immutable addon artifact QA; prints aggregate metadata only."""
import argparse, hashlib, json, sqlite3
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('pack',type=Path);a=p.parse_args()
manifest=json.loads(a.pack.with_suffix('.manifest.json').read_text())
actual=hashlib.sha256(a.pack.read_bytes()).hexdigest();assert actual==manifest['file_hash_sha256']
assert not Path(str(a.pack)+'-wal').exists() and not Path(str(a.pack)+'-journal').exists()
db=sqlite3.connect('file:'+str(a.pack)+'?mode=ro',uri=True)
assert db.execute('PRAGMA integrity_check').fetchone()[0]=='ok'
assert db.execute('PRAGMA journal_mode').fetchone()[0]=='delete'
tables={r[0] for r in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
assert tables=={'pack_meta','regional_reference_groups','regional_reference_markers','sources'}
counts=db.execute('SELECT group_code,COUNT(*),COUNT(DISTINCT chrom) FROM regional_reference_markers GROUP BY group_code').fetchall()
assert len(counts)==10 and all(row[1:]==(30408,22) for row in counts)
assert db.execute('SELECT COUNT(*) FROM (SELECT chrom,pos,COUNT(*) n FROM regional_reference_markers GROUP BY chrom,pos HAVING n<>10)').fetchone()[0]==0
assert db.execute("SELECT COUNT(*) FROM regional_reference_markers WHERE build<>'GRCh37' OR ref||alt NOT IN('AC','CA','GT','TG') OR alt_frequency<0 OR alt_frequency>1 OR allele_number<>2*observed_samples").fetchone()[0]==0
meta=dict(db.execute('SELECT key,value FROM pack_meta'));assert meta['ranking_status']=='withheld_reference_nonseparability'
db.close()
print(json.dumps(dict(status='passed',sha256=actual,bytes=a.pack.stat().st_size,group_count=10,source_individuals=manifest['sample_count'],markers_per_group=30408,chromosomes=22,sqlite_integrity='ok',journal_mode='delete',pending_journal=False,runtime_tables=sorted(tables),personal_or_donor_genotype_tables=False),indent=2))
