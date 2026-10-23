"""Build new aggregate runtime bridge from pinned GrafAnc definitions and bigsnpr weights."""
from research_paths import require_path
import gzip,hashlib,json,os,sqlite3,shutil,time
from pathlib import Path
import numpy as np
os.umask(0o077)
BASE=require_path('ZDNA_COMPOSITION_ROOT');BUNDLE=BASE/'bundle';BUNDLE.mkdir(mode=0o700,exist_ok=False)
GRAF=require_path('ZDNA_GRAFANC_ROOT') / 'bundle/data/marker_index.sqlite'
assert hashlib.sha256(GRAF.read_bytes()).hexdigest()=='c3e8272c979a168484fa3469339e8237cec5e49ae11a1157fadf5fb584944917'
db=sqlite3.connect('file:'+str(GRAF)+'?mode=ro',uri=True);wanted={(str(c),str(p)):(str(p38),r,a,b) for c,p,p38,r,a,b in db.execute('SELECT chrom,pos37,pos38,rsid,ref,alt FROM markers')};db.close()
frequency=[];loading=[];definitions=[];excluded={};rows=0
with gzip.open(BASE/'source/ref_freqs.csv.gz','rt') as f,gzip.open(BASE/'source/projection.csv.gz','rt') as p:
    groups=f.readline().strip().split(',')[5:];next(p)
    for fl,pl in zip(f,p):
        rows+=1;a=fl.rstrip().split(',',5);b=pl.rstrip().split(',',5)
        assert a[:5]==b[:5]
        w=wanted.get((a[0],a[1]))
        if w is None:continue
        if a[4]!=w[1] or set(a[2:4])!=set(w[2:4]):excluded['identifier_or_allele_mismatch']=excluded.get('identifier_or_allele_mismatch',0)+1;continue
        frequency.append(np.fromstring(a[5],sep=','));loading.append(np.fromstring(b[5],sep=','));definitions.append((a[0],int(a[1]),int(w[0]),a[4],a[2],a[3]))
    assert f.readline()=='' and p.readline()==''
assert rows==5816590 and len(definitions)>100000
order=sorted(range(len(definitions)),key=lambda i:(int(definitions[i][0]),definitions[i][1]));definitions=[definitions[i] for i in order]
F=np.array(frequency)[order];L=np.array(loading)[order];assert F.shape[1]==21 and L.shape[1]==16 and np.isfinite(L).all() and ((F>=0)&(F<=1)).all()
np.savez_compressed(BUNDLE/'weights.npz',frequency=F,loading=L,chromosome=np.array([int(d[0]) for d in definitions],dtype=np.int8),groups=np.array(groups))
db=sqlite3.connect(BUNDLE/'marker_index.sqlite');db.execute('CREATE TABLE markers(chrom TEXT,pos37 INTEGER,pos38 INTEGER,rsid TEXT PRIMARY KEY,ref TEXT,alt TEXT)');db.executemany('INSERT INTO markers VALUES(?,?,?,?,?,?)',definitions);db.execute('CREATE UNIQUE INDEX c37 ON markers(chrom,pos37)');db.execute('CREATE UNIQUE INDEX c38 ON markers(chrom,pos38)');db.commit();assert db.execute('PRAGMA integrity_check').fetchone()[0]=='ok';db.close()
spec=[('AFR_W','West Africa',['Africa (West)'],'West African reference group; chiefly Nigerian, Ghanaian and Sierra Leonean source participants.'),('AFR_S','Southern Africa',['Africa (South)'],'Published southern African grouping, including Zimbabwean, Ugandan, South African and Zambian source participants.'),('AFR_E','East Africa',['Africa (East)'],'Published eastern African grouping, including Somali, Ethiopian, Sudanese and Eritrean source participants.'),('AFR_N','North Africa',['Africa (North)'],'Published North African reference includes Algeria, Morocco, Egypt, Libya and Tunisia. These countries are not individually estimated.'),('MENA','Middle East',['Middle East'],'Published Middle Eastern reference is mainly participants born in Iraq and Iran, with Turkey and Syria also represented. No separate country percentages.'),('ASH','Ashkenazi',['Ashkenazi'],'Published PC-defined Ashkenazi diaspora reference group; not a religion, citizenship or community-membership test.'),('EUR_IT','Italy',['Italy'],'Published Italian reference group; similarity is not nationality proof.'),('EUR_E','East Europe',['Europe (South East)','Europe (North East)'],'Author-required merge of northeastern and southeastern European research references.'),('EUR_FIN','Finland',['Finland'],'Finnish UK Biobank reference combined with the 1000 Genomes FIN reference.'),('EUR_NW','Northwest Europe',['Scandinavia','United Kingdom','Ireland'],'Author-required merge of United Kingdom, Ireland and Scandinavian references. England, Scotland and Wales are not separately modeled.'),('EUR_SW','Southwest Europe',['Europe (South West)'],'Published southwestern European grouping, mainly Spain and Portugal, also including French source participants.'),('AMR','South America',['South America'],'Published South American reference combined with 1000 Genomes PEL; a research source grouping, not a country or Indigenous-community membership test.'),('SAS_SL','Sri Lanka',['Sri Lanka'],'Published Sri Lankan reference group.'),('SAS_PK','Pakistan',['Pakistan'],'Published Pakistani reference group; neighboring South Asian and Iranian-related variation can overlap.'),('SAS_BD','Bangladesh',['Bangladesh'],'Bangladeshi UK Biobank reference combined with 1000 Genomes BEB.'),('EAS','East Asia',['Asia (East)'],'Published East Asian grouping with several East and Southeast Asian source locations.'),('EAS_JP','Japan',['Japan'],'Japanese UK Biobank reference combined with 1000 Genomes JPT.'),('EAS_PH','Philippines',['Philippines'],'Published Philippine reference group.')]
components=[dict(code=c,label=l,source_groups=s,reference_scope=r) for c,l,s,r in spec]
meta=dict(schema_version='bigsnpr-composition-v1',source_name='bigsnpr / Prive 2022',source_url='https://doi.org/10.1093/bioinformatics/btac348',source_version='Figshare16858534.v3 / bigsnpr4bc7f817',data_doi='10.6084/m9.figshare.16858534.v3',data_license='CC BY 4.0',software_license='GPL-3.0-or-later',usable_marker_count=len(definitions),source_marker_count=5816590,bridge_source='Pinned GrafAnc GRCh37/GRCh38 identifier bridge; frequencies/loadings are exclusively the published bigsnpr asset',bridge_index_sha256=hashlib.sha256(GRAF.read_bytes()).hexdigest(),index_excluded=excluded,minimum_markers=50000,minimum_chromosomes=15,minimum_rule='Engineering coverage floor tested with constructed masks; not an author-calibrated consumer accuracy guarantee',components=components,correction=[1,1,1,1.008,1.021,1.034,1.052,1.074,1.099,1.123,1.15,1.195,1.256,1.321,1.382,1.443])
(BUNDLE/'model.json').write_text(json.dumps(meta,indent=2)+'\n')
shutil.copy2(BASE/'composition_worker.py',BUNDLE/'worker.py');shutil.copy2(BASE/'source/SOURCE_MANIFEST.json',BUNDLE/'SOURCE_MANIFEST.json')
print(json.dumps(dict(status='built_aggregate_bridge',markers=len(definitions),excluded=excluded,bundle=str(BUNDLE))),flush=True)
