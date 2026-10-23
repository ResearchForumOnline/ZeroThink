"""Create seeded independent synthetic calls from published aggregate frequencies only."""
from research_paths import require_path
import hashlib, json, sqlite3
from pathlib import Path
import numpy as np
BASE=require_path('ZDNA_GRAFANC_ROOT');bundle=BASE/'bundle'
db=sqlite3.connect('file:'+str(bundle/'data/marker_index.sqlite')+'?mode=ro',uri=True);allow={r[0] for r in db.execute('SELECT rsid FROM markers')};db.close()
rng=np.random.default_rng(20260905);out=BASE/'evaluation/SYNTHETIC-grafanc-reference-not-personal.csv';n=0
with (bundle/'data/AncSnpPopAFs.txt').open() as source,out.open('w') as dest:
 next(source);dest.write('# CONSTRUCTED SYNTHETIC SOFTWARE TEST: NOT A PERSON OR DONOR\n# GRCh37 forward strand; independent seeded HWE calls from published UKBBEUR aggregate frequencies\nrsid,chromosome,position,genotype\n')
 for line in source:
  r=line.split();rsid='rs'+r[3]
  if rsid not in allow:continue
  d=int(rng.binomial(2,float(r[6])));genotype=[r[4]*2,r[4]+r[5],r[5]*2][d]
  dest.write(','.join([rsid,r[0],r[1],genotype])+'\n');n+=1
print(json.dumps(dict(status='constructed_synthetic_only',markers=n,seed=20260905,sha256=hashlib.sha256(out.read_bytes()).hexdigest(),source='Published aggregate frequency asset only; no individual source genotypes used',path=str(out))))
