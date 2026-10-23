"""Build a NEW source-aligned modern reference addon from selected public rows.
Scientific output is descriptive allele sharing, never a fitted ethnic percentage.
"""
import argparse, collections, datetime, hashlib, json, math, sqlite3
from pathlib import Path
import numpy as np
from validate_regional_hwe import validate_data

VERSION='AADR v66.p1 compatibility_HO / regional-public-HO-20260905.2'
SOURCE='https://doi.org/10.7910/DVN/FFIDCW'
BOUNDARY=('These are descriptive shared-allele comparisons with small published research cohorts, not ethnicity percentages, '
          'nationality, religion, community membership or direct ancestry. The reference groups and marker selection affect scores; '
          'high shared-allele scores are common across populations. Scores are comparable only within this panel on the same loci. '
          'The internal tests fail to separate several named cohorts, including Egyptian and Ashkenazi references; no closest-origin ranking is provided. '
          'No consumer ethnicity calibration or historical Khazar identification is provided.')
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def decode(data,n):
 b=np.frombuffer(data,dtype=np.uint8)
 assert len(b)==(n+3)//4
 return np.column_stack([(b>>shift)&3 for shift in [6,4,2,0]]).reshape(-1)[:n]

def build(root,out):
 assert not out.exists(),'Refusing to overwrite existing reference artifact'
 sel=json.loads((root/'regional_selection.json').read_text())
 retrieval=json.loads((root/'regional_range_manifest.json').read_text(encoding='utf-8-sig'))
 meta=json.loads((root/'aadr_dataverse_metadata.json').read_text(encoding='utf-8-sig'))
 assert meta['latestVersion']['versionNumber']==14 and meta['latestVersion']['license']['rightsIdentifier']=='CC0-1.0'
 registry={f['dataFile']['filename']:f['dataFile'] for f in meta['latestVersion']['files'] if not f['restricted']}
 sources=[]
 names=['v66.p1_compatibility_HO.aadr.patch.PUB.ind','v66.p1_compatibility_HO.aadr.patch.PUB.snp','v66.p1_compatibility_HO.aadr.PUB.anno','aadr_v66.p1__README.docx']
 for name in names:
  data=(root/name).read_bytes();official=registry[name]
  assert len(data)==official['filesize'] and hashlib.md5(data).hexdigest()==official['checksum']['value'],name
  sources.append(dict(filename=name,bytes=len(data),sha256=sha(root/name),verified_md5=official['checksum']['value'],url='https://dataverse.harvard.edu/api/access/datafile/'+str(official['id'])))
 inds=[x.split() for x in (root/names[0]).read_text().splitlines()]
 snps=[x.split() for x in (root/names[1]).read_text().splitlines()]
 header=(root/'current_tgeno_header.bin').read_bytes().decode().strip('\0').split()
 assert header==sel['header'] and header[0]=='TGENO' and len(inds)==int(header[1]) and len(snps)==int(header[2])
 assert 48+len(inds)*sel['row_bytes']==sel['genotype_file']['filesize']
 assert decode(bytes([0x1b,0xe4]),8).tolist()==[0,1,2,3,3,2,1,0]
 assert (2-decode(bytes([0x18]),3).astype(int)).tolist()==[2,1,0]
 manifest_rows={r['sample_id']:r for r in retrieval['rows']}
 assert set(manifest_rows)=={s['sample_id'] for s in sel['samples']}
 arrays=[]
 for sample in sel['samples']:
  assert sample['data_type']=='HumanOrigins' and sample['assessment']=='Pass' and sample['family_relations'] in ['', '..']
  assert '2019.02.035' not in sample['source_doi'] and 'Jacobs' not in sample['source_publication']
  rec=manifest_rows[sample['sample_id']];path=root/sample['row_filename']
  assert path.stat().st_size==sel['row_bytes'] and sha(path)==rec['sha256']
  assert rec['status']==206 and rec['byte_start']==sample['byte_start'] and rec['byte_end']==sample['byte_end']
  assert inds[sample['sample_index']][0]==sample['sample_id']
  arrays.append(decode(path.read_bytes(),len(snps)))
 assert len(set(s['individual_id'] for s in sel['samples']))==len(sel['samples'])
 matrix=np.array(arrays,dtype=np.uint8); del arrays
 audit=json.loads((root/'ancient_origins.vcf-locus-validation.json').read_text())
 assert audit['completed_chromosomes']==22
 allowed={(x['chrom'],x['pos'],x['ref'],x['alt']) for x in audit['loci'] if x['status']=='exact_ref_alt'}
 assert len(allowed)==30826
 groups=sel['groups'];codes=list(groups)
 masks={c:np.array([s['group_code']==c for s in sel['samples']]) for c in codes}
 duplicates=collections.Counter((s[1],s[3]) for s in snps)
 valid=np.array([(s[1],int(s[3]),s[4],s[5]) in allowed and duplicates[(s[1],s[3])]==1 and ''.join(sorted(s[4:6])) in ['AC','GT'] for s in snps])
 for c,m in masks.items():valid &= (matrix[m]!=3).sum(axis=0)>=math.ceil(int(m.sum())*.9)
 chosen=np.flatnonzero(valid);assert len(chosen)>10000
 chrom_counts=collections.Counter(snps[i][1] for i in chosen);assert len(chrom_counts)==22
 calls=matrix[:,chosen];del matrix
 doses=np.where(calls==3,-1,2-calls.astype(np.int8)); fractions=np.where(doses<0,np.nan,doses/2)
 frequencies={c:np.nanmean(fractions[m],axis=0) for c,m in masks.items()}
 observed={c:np.sum(calls[m]!=3,axis=0) for c,m in masks.items()}
 # Leave each person out of their own cohort before calculating the same statistic.
 confusion={c:collections.Counter() for c in codes}; ties=0;correct=0
 for i,s in enumerate(sel['samples']):
  available=~np.isnan(fractions[i]); ref={}
  for c,m in masks.items():
   include=m.copy();include[i]=False
   ref[c]=np.nanmean(fractions[include],axis=0);available &= ~np.isnan(ref[c])
  scores={c:float(np.mean(fractions[i,available]*ref[c][available]+(1-fractions[i,available])*(1-ref[c][available]))) for c in codes}
  order=sorted(codes,key=lambda c:(-scores[c],c));confusion[s['group_code']][order[0]]+=1
  correct+=order[0]==s['group_code'];ties+=round(scores[order[0]]*100,1)==round(scores[order[1]]*100,1)
 validation=dict(status='internal_descriptive_reference_diagnostic',samples=len(sel['samples']),same_label_top_count=correct,display_ties=ties,by_group={c:dict(sample_count=int(masks[c].sum()),same_label_top_count=confusion[c][c],predictions=dict(confusion[c])) for c in codes},method='Leave-one-individual-out, exact deployed expected allele-sharing statistic; same marker intersection and reference cohorts.',limitations='Not consumer accuracy, calibration or independent cohort validation. SNP selection uses reference call availability and a previously validated locus allowlist. Cohort batch effects, relatives not listed in metadata, related marker linkage and unequal small sample sizes remain. Do not attach these rates as personal ancestry confidence.')
 validation['hwe_alternative']=validate_data(doses,np.array([s['group_code'] for s in sel['samples']]))
 validation['release_decision']='Descriptive cohort evidence only; ranks and closest group withheld. Both sharing and HWE fail to separate key requested cohorts. No consumer ethnicity percentages.'
 marker_filter='Unique GRCh37 autosomal nonpalindromic transversions (AC/GT), exact REF and ALT membership independently confirmed against 1000 Genomes Phase3 VCFs; confirming VCF records need not be strictly biallelic. Direct user calls must contain only this source allele pair. At least 90% called individuals in every group.'
 now=datetime.datetime.now(datetime.timezone.utc).isoformat();out.parent.mkdir(parents=True,exist_ok=True)
 con=sqlite3.connect(out)
 con.executescript('''PRAGMA journal_mode=DELETE;
 CREATE TABLE pack_meta(key TEXT PRIMARY KEY,value TEXT NOT NULL);
 CREATE TABLE regional_reference_groups(group_code TEXT PRIMARY KEY,group_label TEXT NOT NULL,region TEXT NOT NULL,reference_role TEXT NOT NULL,sample_count INTEGER NOT NULL,marker_count INTEGER NOT NULL,source_version TEXT NOT NULL,source_url TEXT NOT NULL,source_dois TEXT NOT NULL,limitations TEXT NOT NULL);
 CREATE TABLE regional_reference_markers(chrom TEXT NOT NULL,pos INTEGER NOT NULL,build TEXT NOT NULL,ref TEXT NOT NULL,alt TEXT NOT NULL,group_code TEXT NOT NULL,alt_frequency REAL NOT NULL CHECK(alt_frequency>=0 AND alt_frequency<=1),observed_samples INTEGER NOT NULL CHECK(observed_samples>=2),allele_number INTEGER NOT NULL,PRIMARY KEY(chrom,pos,group_code));
 CREATE INDEX regional_reference_coordinate_idx ON regional_reference_markers(build,chrom,pos);
 CREATE TABLE sources(source_name TEXT,source_url TEXT,version_or_release TEXT,downloaded_at TEXT,file_hash_sha256 TEXT,license_note TEXT,imported_row_count INTEGER,provenance_json TEXT);
 ''')
 packmeta=dict(schema_version='regional-refs-v1',source_version=VERSION,genome_build='GRCh37',strand='forward',status='uncalibrated_reference_evidence',source_url=SOURCE,group_count=str(len(groups)),sample_count=str(len(sel['samples'])),marker_count=str(len(chosen)),created_at_utc=now,genotype_coding='TGENO 0=ALT/ALT, 1=REF/ALT, 2=REF/REF, 3=missing; diploid Human Origins only',frequency_method='ALT copies/(2*observed individuals), called donors only; identical loci in all groups',marker_filter=marker_filter,limitations=BOUNDARY,selection_note=sel['selection_note'],validation=json.dumps(validation),ranking_status='withheld_reference_nonseparability')
 con.executemany('INSERT INTO pack_meta VALUES(?,?)',packmeta.items())
 for c,g in groups.items():
  dois=sorted(set(s['source_doi'] for s in sel['samples'] if s['group_code']==c))
  limits=BOUNDARY+(' This cohort contains only seven quality-passing individuals.' if c=='ASH' else '')
  con.execute('INSERT INTO regional_reference_groups VALUES(?,?,?,?,?,?,?,?,?,?)',(c,g['label'],g['region'],g['role'],int(masks[c].sum()),len(chosen),VERSION,SOURCE,json.dumps(dois),limits))
  con.executemany('INSERT INTO regional_reference_markers VALUES(?,?,?,?,?,?,?,?,?)',((snps[i][1],int(snps[i][3]),'GRCh37',snps[i][4],snps[i][5],c,float(frequencies[c][j]),int(observed[c][j]),2*int(observed[c][j])) for j,i in enumerate(chosen)))
 for source in sources:
  con.execute('INSERT INTO sources VALUES(?,?,?,?,?,?,?,?)',('Allen Ancient DNA Resource',source['url'],VERSION,now,source['sha256'],'CC0-1.0 currentpublicDataverse14.0; citeAADR andoriginalpapers',len(sel['samples']),json.dumps(source)))
 con.execute('INSERT INTO sources VALUES(?,?,?,?,?,?,?,?)',('AADR current-public genotype byte ranges',sel['retrieval_url'],VERSION,now,sha(root/'regional_range_manifest.json'),'CC0-1.0 selectedcurrentpublicindividualsonly',len(sel['samples']),json.dumps({'range_manifest_sha256':sha(root/'regional_range_manifest.json'),'full_file_md5_verified':False,'expected_full_md5':sel['genotype_file']['checksum']['value']})))
 con.commit();assert con.execute('PRAGMA integrity_check').fetchone()[0]=='ok';con.close()
 # This private reference genotype matrix is for an independent out-of-panel
 # model challenge. It is not copied into the runtime pack, API or UI.
 np.savez_compressed(root/'regional-challenge.private.npz',dosage=doses,groups=np.array([s['group_code'] for s in sel['samples']]),chromosome=np.array([snps[i][1] for i in chosen]),position=np.array([int(snps[i][3]) for i in chosen]),reference=np.array([snps[i][4] for i in chosen]),alternate=np.array([snps[i][5] for i in chosen]))
 report=out.with_suffix('.validation.json');report.write_text(json.dumps(validation,indent=2)+'\n')
 manifest=dict(source_name='Allen Ancient DNA Resource',source_url=SOURCE,version_or_release=VERSION,dataverse_version='14.0',downloaded_at=now,file_hash_sha256=sha(out),license_note='CC0-1.0 per currentpublicDataverse14.0. Only selectedcurrentpublicHumanOriginsindividuals used. CiteAADR andoriginalpublications. WithdrawnJacobs2019samples, nonpublicreleases andQuestionable/QCremoveexcluded.',imported_row_count=len(chosen)*len(groups),pack_filename=out.name,pack_bytes=out.stat().st_size,group_count=len(groups),sample_count=len(sel['samples']),markers_per_group=len(chosen),chromosome_counts=dict(chrom_counts),groups={c:dict(groups[c],sample_count=int(masks[c].sum()),min_observed_samples=int(observed[c].min())) for c in codes},schema_version='regional-refs-v1',genome_build='GRCh37',strand='forward',status='uncalibrated_reference_evidence',limitations=BOUNDARY,source_files=sources,selected_ranges_manifest_sha256=sha(root/'regional_range_manifest.json'),selection_manifest_sha256=sha(root/'regional_selection.json'),genotype_full_file_md5_verified=False,genotype_full_file_expected_md5=sel['genotype_file']['checksum']['value'],independent_marker_allowlist_sha256=sha(root/'ancient_origins.vcf-locus-validation.json'),validation_file=report.name,validation_sha256=sha(report),builder_sha256=sha(Path(__file__)),sqlite_integrity='ok',sqlite_journal_mode='delete')
 manifest['marker_filter']=marker_filter
 manifest['ranking_status']='withheld_reference_nonseparability'
 manifest['authorization_note']='Current Dataverse version 14.0 marks these v66.p1 PUB files unrestricted under CC0-1.0. Each selected modern sample must be a quality-passing Human Origins row in the fresh current public annotation and individual list. One representation per Individual ID; annotated relatives, questionable or removed samples, and Jacobs2019/decommissioned releases are excluded. This does not authorize other AADR, HGDP, SGDP or EGA access-controlled samples.'
 out.with_suffix('.manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
 print(json.dumps({k:manifest[k] for k in ['file_hash_sha256','pack_bytes','group_count','sample_count','markers_per_group']},indent=2))
 print('LOO diagnostic:',correct,'/',len(sel['samples']),'same-label first; not consumer calibration')
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--data-root',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();build(a.data_root,a.output)
