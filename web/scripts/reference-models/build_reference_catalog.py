"""Read-only ancestry-pack inventory. Emits public reference metadata, never user DNA."""
import argparse, sqlite3, json, os, hashlib
from datetime import datetime, timezone

p=argparse.ArgumentParser()
p.add_argument('--database',required=True)
p.add_argument('--output',required=True)
p.add_argument('--validation-manifest')
a=p.parse_args()
db=sqlite3.connect('file:'+os.path.abspath(a.database)+'?mode=ro',uri=True)
db.row_factory=sqlite3.Row
meta=dict(db.execute('select key,value from pack_meta'))
known={
 '1000G_Phase3_optimized_population':('1000 Genomes population references','https://www.internationalgenome.org/data-portal/data-collection/phase-3'),
 '1000G_Phase3_optimized_superpop':('1000 Genomes broad reference groups','https://www.internationalgenome.org/data-portal/data-collection/phase-3'),
 'gnomAD_specialist_frequency':('gnomAD frequency context','https://gnomad.broadinstitute.org/')}
panels=[]
for row in db.execute('select panel_name,count(*) row_count,count(distinct rsid) marker_count,count(distinct population_code) population_count,group_concat(distinct source_version) source_version from ancestry_reference_markers group by panel_name'):
    item=dict(row)
    title,url=known.get(item['panel_name'],(item['panel_name'],'https://www.internationalgenome.org/'))
    item.update(display_name=title,source_url=url)
    item['populations']=[dict(r) for r in db.execute('select population_code code,population_label label,count(distinct rsid) marker_count from ancestry_reference_markers where panel_name=? group by population_code,population_label order by population_label',(item['panel_name'],))]
    item['chromosomes']=[dict(r) for r in db.execute('select chrom,count(distinct rsid) marker_count from ancestry_reference_markers where panel_name=? group by chrom order by cast(chrom as integer),chrom',(item['panel_name'],))]
    panels.append(item)
chroms=[dict(r) for r in db.execute('select chrom,count(distinct rsid) marker_count from ancestry_reference_markers group by chrom order by cast(chrom as integer),chrom')]
builds=[r[0] for r in db.execute('select distinct build from ancestry_reference_markers')]
h=hashlib.sha256()
with open(a.database,'rb') as f:
    for chunk in iter(lambda:f.read(1024*1024),b''):h.update(chunk)
result=dict(schema_version=1,audited_at=datetime.now(timezone.utc).isoformat(),pack_version=meta.get('pack_version','unknown'),assembly=', '.join(builds),chromosomes=chroms,panels=panels,
    database_size_bytes=os.path.getsize(a.database),database_mtime=int(os.path.getmtime(a.database)),database_sha256=h.hexdigest(),
    limitations=['Population groups overlap between sources; counts must not be added as independent populations.','Reference similarity is not an ancestry percentage or a probability of identity.','Reference sample counts and model calibration are not documented in the legacy pack.','gnomAD specialist frequencies are context; they are not a harmonized independent population classifier.','Sex-chromosome rows in the legacy pack are excluded from the autosomal ancestry comparison.'])
if a.validation_manifest:
    with open(a.validation_manifest,encoding='utf-8') as f: validation=json.load(f)
    if validation['candidate_sha256']!=h.hexdigest():raise ValueError('Validation manifest does not match this pack')
    counts=validation['training_sample_counts']
    result['reference_evaluation']={'training_samples':sum(counts.values()),'heldout_samples':len(validation['heldout_sample_ids']),'marker_count':validation['marker_count'],'calibration_status':'internal_reference_diagnostics_only; ancestry composition remains uncalibrated'}
    result['limitations'][2]='Expanded 1000 Genomes population references include audited training counts and internal held-out diagnostics. Fine-group labels and ancestry composition remain uncalibrated; legacy reference components have incomplete sample metadata.'
    for panel in panels:
        if panel['panel_name']=='1000G_Phase3_optimized_population':
            panel['training_sample_count']=sum(counts.values())
            for population in panel['populations']:population['training_sample_count']=counts.get(population['code'])
db.close()
with open(a.output,'x',encoding='utf-8') as f:json.dump(result,f,indent=2,ensure_ascii=False)
print(json.dumps({'output':a.output,'panels':len(panels),'sha256':h.hexdigest()}))
