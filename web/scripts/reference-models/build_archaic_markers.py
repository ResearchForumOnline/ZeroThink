"""Build conservative, source-attributed archaic-associated tag inventory.

This is a variant lookup panel, NOT an individual introgression/percentage model.
Only public 1000G aggregate SPrime output is used; SGDP Papuan files are excluded.
"""
from research_paths import require_path
import csv,hashlib,io,json,sqlite3,subprocess,tarfile,time
from collections import Counter,defaultdict
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

STAGE=require_path('ZDNA_ORIGINS_ROOT')
SOURCE=STAGE/'archaic-source'
OUT=STAGE/'archaic_markers.sqlite'
VERSION='Browning2018-Sprime-1000G-distinct-tags-v1'
DOI='https://doi.org/10.17632/y7hyt83vxr.1'
POPULATIONS={'BEB','CDX','CEU','CHB','CHS','CLM','FIN','GBR','GIH','IBS','ITU','JPT','KHV','MXL','PEL','PJL','PUR','STU','TSI'}

def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
def download(item):
 p=SOURCE/item['filename']; details=item['content_details']
 if not p.exists():
  subprocess.run(['curl','--fail','--location','--silent','--show-error','--retry','2','--max-time','90','--output',str(p),details['download_url']],check=True)
 assert p.stat().st_size==details['size'],p.name+' size mismatch'
 assert sha(p)==details['sha256_hash'],p.name+' SHA256 mismatch'
 return {'source_name':p.name,'source_url':details['download_url'],'version_or_release':'2018-02-23,version1','downloaded_at':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'file_hash_sha256':sha(p),'bytes':p.stat().st_size,'license_note':'CC BY 4.0. Sharon Browning (2018), DOI10.17632/y7hyt83vxr.1; filtered and aggregated by ZeroThink.'}

def segment_type(rows):
 # Conservative application thresholds, explicitly our selection rule, not claimed
 # to reproduce a complete SPrime pipeline or its published individual estimates.
 n=[r['NMATCH'] for r in rows if r['NMATCH'] in ('match','mismatch')]
 d=[r['DMATCH'] for r in rows if r['DMATCH'] in ('match','mismatch')]
 if len(n)<30 or len(d)<30:return None
 nr=n.count('match')/len(n); dr=d.count('match')/len(d)
 if nr>=.6 and dr<=.3:return 'neanderthal'
 if dr>=.6 and nr<=.3:return 'denisovan'
 return None

def main():
 assert not OUT.exists(),'Preserve any prior build; choose a new output version.'
 source_files=json.loads((SOURCE/'files.txt').read_text())
 selected=[x for x in source_files if x['filename'].split('_')[0] in POPULATIONS and x['filename'].endswith('_sprime_results.tar.gz')]
 assert {x['filename'].split('_')[0] for x in selected}==POPULATIONS
 with ThreadPoolExecutor(max_workers=4) as pool: sources=list(pool.map(download,selected))
 counts=Counter(); tags={}; locus_alleles=defaultdict(set)
 for item in selected:
  pop=item['filename'].split('_')[0]
  with tarfile.open(SOURCE/item['filename']) as tar:
   for member in tar:
    if not member.isfile() or not member.name.endswith('.ND_match'): continue
    rows=list(csv.DictReader(io.TextIOWrapper(tar.extractfile(member)),delimiter='\t'))
    groups=defaultdict(list)
    for r in rows: groups[r['SEGMENT']].append(r)
    counts['source_rows']+=len(rows)
    for segment,group_rows in groups.items():
     group=segment_type(group_rows)
     if group is None:continue
     counts[group+'_segments']+=1
     for r in group_rows:
      chrom=r['CHROM'].removeprefix('chr'); ref=r['REF'];alt=r['ALT']
      if not chrom.isdigit() or not 1<=int(chrom)<=22 or len(ref)!=1 or len(alt)!=1 or ref not in 'ACGT' or alt not in 'ACGT' or ref==alt:continue
      if ''.join(sorted(ref+alt)) in ('AT','CG'):continue
      if int(r['SCORE'])<=150000 or r['ALLELE'] not in ('0','1'):continue
      wanted,other=('NMATCH','DMATCH') if group=='neanderthal' else ('DMATCH','NMATCH')
      if r[wanted]!='match' or r[other]!='mismatch':continue
      allele=ref if r['ALLELE']=='0' else alt;pos=int(r['POS'])
      key=(chrom,pos,ref,alt,allele,group)
      locus_alleles[(chrom,pos)].add((ref,alt,allele,group))
      if key not in tags:tags[key]={'rsid':r['ID'] if r['ID'].startswith('rs') else chrom+':'+str(pos),'populations':set(),'segments':set()}
      tags[key]['populations'].add(pop);tags[key]['segments'].add(pop+':'+chrom+':'+segment)
  print('processed',pop,'tags',len(tags),flush=True)
 # A locus with conflicting alleles or archaic assignment is excluded completely.
 tags={k:v for k,v in tags.items() if len(locus_alleles[k[:2]])==1}
 counts['distinct_markers']=len(tags)
 for key in tags:counts[key[-1]+'_markers']+=1
 con=sqlite3.connect(OUT)
 con.executescript('''PRAGMA journal_mode=DELETE;
 CREATE TABLE pack_meta(key TEXT PRIMARY KEY,value TEXT NOT NULL);
 CREATE TABLE archaic_markers(rsid TEXT NOT NULL,chrom TEXT NOT NULL,pos INTEGER NOT NULL,build TEXT NOT NULL,ref TEXT NOT NULL,alt TEXT NOT NULL,archaic_allele TEXT NOT NULL,archaic_group TEXT NOT NULL,source_version TEXT NOT NULL,source_populations TEXT NOT NULL,source_segment_count INTEGER NOT NULL,PRIMARY KEY(chrom,pos));
 CREATE INDEX idx_archaic_rsid ON archaic_markers(rsid);
 CREATE INDEX idx_archaic_build_locus ON archaic_markers(build,chrom,pos);
 CREATE TABLE data_sources(source_name TEXT,source_url TEXT,version_or_release TEXT,downloaded_at TEXT,file_hash_sha256 TEXT,license_note TEXT,imported_row_count INTEGER);
 ''')
 records=[]
 for k,v in sorted(tags.items(),key=lambda t:(int(t[0][0]),t[0][1])):
  chrom,pos,ref,alt,allele,group=k
  records.append((v['rsid'],chrom,pos,'GRCh37',ref,alt,allele,group,VERSION,','.join(sorted(v['populations'])),len(v['segments'])))
 con.executemany('INSERT INTO archaic_markers VALUES(?,?,?,?,?,?,?,?,?,?,?)',records)
 rules={'segment_filter':'At least30comparable tagged sites against each archaic genome; target matchrate>=0.6,other<=0.3; per-site targetmatch and othermismatch. These conservative application thresholds select tags, not personalized introgressed segments.','variant_filter':'Autosomal biallelic nonpalindromic SNPs; GRCh37 forward reference/alternate alleles from published1000G VCF-derived columns; SPrime score>150000; conflicting loci excluded; duplicate source-population calls deduplicated.','meaning':'Count of published archaic-associated alleles carried at tested loci. Not genome-wide archaic percentage, not proof a tagged locus in this person was introgressed, not a phased segment caller.','coverage_limit':'Discovery19non-African1000G study populations, referencebias and LD; Denisovan panel is especially incomplete for Oceanian and other unrepresented ancestries. Zero carried tags is not zero archaic ancestry.'}
 metadata={'pack_name':'archaic_markers','pack_version':VERSION,'source_url':DOI,'source_name':'Browning et al.2018 SPrime published1000G tag variants','build':'GRCh37','orientation':'forward','built_at':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'marker_count':len(records),'group_counts':dict(Counter(x[7] for x in records)),'license':'CC BY4.0','attribution':'Sharon R.Browning, Brian L.Browning, YingZhou, SerenaTucci, Joshua M.Akey (2018), Cell173:53-61.e9, DOI10.1016/j.cell.2018.02.031. Dataset SharonBrowning (2018), DOI10.17632/y7hyt83vxr.1. Filtered/aggregated byZeroThink.','rules':rules}
 metadata.update({'source_version':VERSION,'assembly':'GRCh37','evidence_type':'published_introgression_tag','tag_count_by_group':metadata['group_counts'],'ascertainment':'19 non-African 1000 Genomes populations; no SGDP donor files; conservative distinct-archaic tags','source_hashes':{s['source_name']:s['file_hash_sha256'] for s in sources}})
 con.executemany('INSERT INTO pack_meta VALUES(?,?)',[(k,json.dumps(v,separators=(',',':')) if isinstance(v,(dict,list)) else str(v)) for k,v in metadata.items()])
 for s in sources:con.execute('INSERT INTO data_sources VALUES(?,?,?,?,?,?,?)',(s['source_name'],s['source_url'],s['version_or_release'],s['downloaded_at'],s['file_hash_sha256'],s['license_note'],sum(1 for v in tags.values() if s['source_name'].split('_')[0] in v['populations'])))
 con.commit();assert con.execute('PRAGMA quick_check').fetchone()[0]=='ok'
 assert con.execute('SELECT COUNT(*) FROM archaic_markers WHERE archaic_allele NOT IN (ref,alt)').fetchone()[0]==0
 assert con.execute('SELECT COUNT(DISTINCT chrom) FROM archaic_markers').fetchone()[0]==22
 con.close()
 manifest={'schema_version':1,'metadata':metadata,'counts':dict(counts),'sources':sources,'output_sha256':sha(OUT),'output_bytes':OUT.stat().st_size,'validation':'Repository SHA256+byte count verified for all19archives; header contract from hashedREADME; deduplicated locus keys; allele-in-ref-alt invariant; all22autosomes; SQLitequick_checkok. Software synthetic tests separate from source validation.'}
 (STAGE/'archaic_reference_manifest.json').write_text(json.dumps(manifest,indent=2))
 print(json.dumps({'path':str(OUT),'counts':dict(counts),'sha256':manifest['output_sha256'],'bytes':manifest['output_bytes']}))

if __name__=='__main__':main()
