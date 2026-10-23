"""Select current publicly released modern reference metadata; no genotypes read."""
import argparse, csv, hashlib, json
from pathlib import Path

GROUPS = {
 'ASH': {'source_group':'Jew_Ashkenazi','label':'Ashkenazi Jewish research references','region':'European Jewish diaspora','role':'requested_region','cap':100},
 'EGY': {'source_group':'Egyptian','label':'Egyptian research references','region':'Northeast Africa','role':'requested_region','cap':100},
 'PAL': {'source_group':'Palestinian','label':'Palestinian research references','region':'Levant','role':'requested_region','cap':100},
 'DRU': {'source_group':'Druze','label':'Druze research references','region':'Levant','role':'requested_region','cap':100},
 'BDA': {'source_group':'BedouinA','label':'Bedouin A research references','region':'Levant and Arabian Peninsula','role':'requested_region','cap':100},
 'BDB': {'source_group':'BedouinB','label':'Bedouin B research references','region':'Levant and Arabian Peninsula','role':'requested_region','cap':100},
 'FRE': {'source_group':'French','label':'French research comparison','region':'Western Europe','role':'comparison_control','cap':24},
 'YOR': {'source_group':'Yoruba','label':'Yoruba research comparison','region':'West Africa','role':'comparison_control','cap':24},
 'HAN': {'source_group':'Han','label':'Han Chinese research comparison','region':'East Asia','role':'comparison_control','cap':24},
 'BAL': {'source_group':'Balochi','label':'Balochi research comparison','region':'South and Central Asia','role':'comparison_control','cap':24},
}

def select(root):
 meta=json.loads((root/'aadr_dataverse_metadata.json').read_text(encoding='utf-8-sig'))
 assert meta['latestVersion']['versionNumber']==14
 assert meta['latestVersion']['license']['rightsIdentifier']=='CC0-1.0'
 files={x['dataFile']['filename']:x['dataFile'] for x in meta['latestVersion']['files'] if not x['restricted']}
 names=['v66.p1_compatibility_HO.aadr.patch.PUB.ind','v66.p1_compatibility_HO.aadr.patch.PUB.snp','v66.p1_compatibility_HO.aadr.PUB.anno']
 for name in names:
  assert hashlib.md5((root/name).read_bytes()).hexdigest()==files[name]['checksum']['value'],name
 inds=[line.split() for line in (root/names[0]).read_text().splitlines()]
 indices={row[0]:i for i,row in enumerate(inds)}
 rows=list(csv.DictReader((root/names[2]).open(encoding='utf-8-sig'),delimiter='\t'))
 keys=list(rows[0]); ident=keys[0]
 cov_key=next(k for k in keys if k.endswith('(Computed using easystats on Compatibility_HO snpset)'))
 selected=[]; exclusions={}
 for code,g in GROUPS.items():
  best={};excluded={}
  for r in rows:
   if r['Group ID']!=g['source_group'] or r['Data type']!='HumanOrigins': continue
   reason=None
   if r[ident] not in indices: reason='not_in_current_public_genotype_index'
   elif r['ASSESSMENT']!='Pass':reason='not_pass_assessment'
   elif r['Family relations'].strip() not in ['', '..']:reason='documented_family_relation'
   elif 'Jacobs' in r['Publication abbreviation'] or '2019.02.035' in r['doi for publication of this representation of the data']:reason='withdrawn_study'
   try: coverage=int(r[cov_key])
   except ValueError: coverage=0
   if coverage<240000:reason=reason or 'low_source_coverage'
   if reason:
    excluded[reason]=excluded.get(reason,0)+1;continue
   item=dict(sample_id=r[ident],individual_id=r['Individual ID'],group_code=code,source_group=r['Group ID'],sample_index=indices[r[ident]],coverage=coverage,source_doi=r['doi for publication of this representation of the data'],source_publication=r['Publication abbreviation'],assessment=r['ASSESSMENT'],family_relations=r['Family relations'],genotype_mode='diploid',data_type=r['Data type'])
   previous=best.get(item['individual_id'])
   if previous is None or (coverage,item['sample_id'])>(previous['coverage'],previous['sample_id']):best[item['individual_id']]=item
  candidates=sorted(best.values(),key=lambda x:(-x['coverage'],x['sample_id']))[:g['cap']]
  assert len(candidates)>=5,(code,len(candidates),excluded)
  selected.extend(candidates); exclusions[code]=excluded
 assert len({s['individual_id'] for s in selected})==len(selected)
 header=(root/'current_tgeno_header.bin').read_bytes().decode().strip('\0').split()
 assert header[0]=='TGENO' and int(header[1])==len(inds)
 row_bytes=(int(header[2])+3)//4;geno=files['v66.p1_compatibility_HO.aadr.patch.PUB.geno']
 assert 48+len(inds)*row_bytes==geno['filesize']
 for s in selected:
  s['byte_start']=48+s['sample_index']*row_bytes;s['byte_end']=s['byte_start']+row_bytes-1;s['row_filename']='regional_rows/'+s['sample_id']+'.bin'
 out=dict(schema_version=1,groups=GROUPS,samples=selected,exclusions=exclusions,genotype_file=geno,header=header,row_bytes=row_bytes,retrieval_url='https://dataverse.harvard.edu/api/access/datafile/'+str(geno['id']),selection_note='Current v66.p1 public allowlist, HumanOrigins diploid calls only, Pass assessment, no listed family relation, >=240000 compatibility_HO calls; one representation per Individual ID. Exact unqualified source groups; QCremove/outlier labels excluded. Comparison controls capped at24 by call coverage; target groups all retained. No withdrawn Jacobs2019 sample, signed-letter release or user upload.')
 (root/'regional_selection.json').write_text(json.dumps(out,indent=2)+'\n')
 print(json.dumps({'selected_groups':{c:sum(s['group_code']==c for s in selected) for c in GROUPS},'samples':len(selected),'download_bytes':len(selected)*row_bytes,'exclusions':exclusions},indent=2))
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--data-root',type=Path,required=True);select(p.parse_args().data_root)
