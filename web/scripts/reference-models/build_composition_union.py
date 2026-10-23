"""Add already validated HO loci to GRCh37; keep GRCh38 on exact paired bridge."""
from research_paths import require_path
import hashlib,json,os,shutil,sqlite3
from pathlib import Path
import numpy as np
os.umask(0o077)
BASE=require_path('ZDNA_COMPOSITION_ROOT');OLD=BASE/'bundle';NEW=BASE/'bundle-v2'
if NEW.exists():assert not any(NEW.iterdir()), 'Preserve earlier nonempty candidate'
else:NEW.mkdir(mode=0o700)
old=np.load(OLD/'weights.npz');ho=np.load(BASE/'evaluation/model-HO-aggregate.npz');assert np.array_equal(old['groups'],ho['groups'])
F0=old['frequency'];L0=old['loading'];HF=ho['frequency'];HL=ho['loading'];HG=ho['loci']
db=sqlite3.connect('file:'+str(OLD/'marker_index.sqlite')+'?mode=ro',uri=True);defs=list(db.execute('SELECT chrom,pos37,pos38,rsid,ref,alt FROM markers ORDER BY CAST(chrom AS INTEGER),pos37'));db.close();assert len(defs)==len(F0)
allrows={ (str(d[0]),int(d[1])):(d,F0[i],L0[i]) for i,d in enumerate(defs)}
common=0
for i,row in enumerate(HG):
    c,p,a0,a1,rsid=row;key=(str(c),int(p));d=(str(c),int(p),None,str(rsid),str(a0),str(a1))
    if key in allrows:
        prior=allrows[key];assert tuple(prior[0][3:])==tuple(d[3:]) and np.array_equal(prior[1],HF[i]) and np.array_equal(prior[2],HL[i]);common+=1
    else:allrows[key]=(d,HF[i],HL[i])
ordered=[allrows[k] for k in sorted(allrows,key=lambda k:(int(k[0]),k[1]))]
F=np.array([r[1] for r in ordered]);L=np.array([r[2] for r in ordered]);definitions=[r[0] for r in ordered]
np.savez_compressed(NEW/'weights37.npz',frequency=F,loading=L,chromosome=np.array([int(d[0]) for d in definitions],dtype=np.int8),groups=old['groups'])
db=sqlite3.connect(NEW/'marker_index37.sqlite');db.execute('CREATE TABLE markers(chrom TEXT,pos37 INTEGER,pos38 INTEGER,rsid TEXT PRIMARY KEY,ref TEXT,alt TEXT)');db.executemany('INSERT INTO markers VALUES(?,?,?,?,?,?)',definitions);db.execute('CREATE UNIQUE INDEX c37 ON markers(chrom,pos37)');db.execute('CREATE UNIQUE INDEX c38 ON markers(chrom,pos38)');db.commit();assert db.execute('PRAGMA integrity_check').fetchone()[0]=='ok';db.close()
shutil.copy2(OLD/'weights.npz',NEW/'weights38.npz');shutil.copy2(OLD/'marker_index.sqlite',NEW/'marker_index38.sqlite');shutil.copytree(OLD/'runtime',NEW/'runtime',symlinks=True)
meta=json.loads((OLD/'model.json').read_text());meta['usable_marker_count']=len(definitions);meta['marker_count_by_build']={'GRCh37':len(definitions),'GRCh38':len(defs)};meta['grch37_union']=dict(grafanc_bridge=len(defs),independently_aligned_HO=len(HF),common=common,union=len(definitions));meta['source_alignment']='GRCh37 union of uniquely aligned nonpalindromic HO/source loci and paired GrafAnc/source loci; GRCh38 uses only verified author-paired positions. Every frequency/loading remains the exact published row.'
(NEW/'model.json').write_text(json.dumps(meta,indent=2)+'\n');shutil.copy2(BASE/'composition_worker.py',NEW/'worker.py');shutil.copy2(OLD/'SOURCE_MANIFEST.json',NEW/'SOURCE_MANIFEST.json')
print(json.dumps(dict(status='built_union',counts=meta['marker_count_by_build'],union=meta['grch37_union'],bundle=str(NEW))),flush=True)
