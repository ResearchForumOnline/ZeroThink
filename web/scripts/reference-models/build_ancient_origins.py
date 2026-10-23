"""Build an ancient-only reference pack from verified current AADR TGENO rows.

No user genotypes or obsolete modern AADR rows are read. TGENO decoding is an
independent implementation of the documented two-bit file format. The primary
panel uses non-palindromic transversions, reducing deamination/strand ambiguity.
"""
import argparse, collections, datetime, hashlib, json, sqlite3
from pathlib import Path
import numpy as np

VERSION = 'AADR v66.p1 compatibility_HO / ancient-transversions-20260905.2'
SOURCE = 'https://doi.org/10.7910/DVN/FFIDCW'
LIMITS = ('Descriptive shared-allele affinity to a small curated reference set, not inherited DNA percentage, '
          'ethnicity, direct descent or a fitted admixture model. Ancient individuals span different eras and sites; '
          'capture ascertainment, missing calls, pseudohaploid sampling, residual error, linkage and reference '
          'selection affect scores. Small groups are particularly uncertain. No consumer-ancestry calibration.')

def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()

def decode(row, count):
    packed = np.frombuffer(row, dtype=np.uint8)
    assert len(packed) == (count+3)//4
    return np.column_stack([(packed >> shift) & 3 for shift in [6,4,2,0]]).reshape(-1)[:count]

def build(root, output, reference_validation=None):
    assert not output.exists(), 'Refusing to overwrite an existing reference pack'
    selection = json.loads((root/'ancient_selection.json').read_text())
    retrieval = json.loads((root/'ancient_range_manifest.json').read_text(encoding='utf-8-sig'))
    meta = json.loads((root/'aadr_dataverse_metadata.json').read_text(encoding='utf-8-sig'))
    registry = {x['dataFile']['filename']:x['dataFile'] for x in meta['latestVersion']['files']}
    source_files=[]
    for name in ['v66.p1_compatibility_HO.aadr.patch.PUB.ind','v66.p1_compatibility_HO.aadr.patch.PUB.snp','v66.p1_compatibility_HO.aadr.PUB.anno','aadr_v66.p1__README.docx']:
        data=(root/name).read_bytes(); official=registry[name]
        assert len(data)==official['filesize'] and hashlib.md5(data).hexdigest()==official['checksum']['value'], name
        source_files.append(dict(filename=name,bytes=len(data),sha256=sha(root/name),md5_verified=official['checksum']['value'],url='https://dataverse.harvard.edu/api/access/datafile/'+str(official['id'])))
    inds=[x.split() for x in (root/'v66.p1_compatibility_HO.aadr.patch.PUB.ind').read_text().splitlines()]
    snps=[x.split() for x in (root/'v66.p1_compatibility_HO.aadr.patch.PUB.snp').read_text().splitlines()]
    header=(root/'current_tgeno_header.bin').read_bytes().decode().strip('\0').split()
    assert header==selection['header'] and header[0]=='TGENO'
    assert len(inds)==int(header[1]) and len(snps)==int(header[2])
    assert len(inds)*selection['row_bytes']+48==selection['genotype_file']['filesize']
    # Fixture from the format specification, not the encoder implementation.
    assert decode(bytes([0x1b,0xe4]),8).tolist()==[0,1,2,3,3,2,1,0]
    assert (1-decode(bytes([0x18]),3)/2).tolist()==[1,.5,0]
    row_manifest={x['sample_id']:x for x in retrieval['rows']}
    arrays=[]
    for sample in selection['samples']:
        rec=row_manifest[sample['sample_id']]; p=root/sample['row_filename']
        assert sha(p)==rec['sha256'] and p.stat().st_size==selection['row_bytes']
        assert rec['status']==206 and rec['byte_start']==sample['byte_start'] and rec['byte_end']==sample['byte_end']
        assert inds[sample['sample_index']][0]==sample['sample_id']
        calls=decode(p.read_bytes(),len(snps))
        if sample['genotype_mode']=='pseudohaploid': assert not np.any(calls==1), sample['sample_id']
        arrays.append(calls)
    matrix=np.array(arrays,dtype=np.uint8)
    groups=selection['groups']; codes=list(groups)
    masks={code:np.array([s['group_code']==code for s in selection['samples']]) for code in codes}
    valid=np.ones(len(snps),dtype=bool)
    for mask in masks.values(): valid &= (matrix[mask]!=3).sum(axis=0)>=2
    common_before_filter=int(valid.sum())
    coordinate_counts=collections.Counter((s[1],s[3]) for s in snps)
    for i,s in enumerate(snps):
        valid[i] &= s[1] in [str(x) for x in range(1,23)] and int(s[3])>0 and ''.join(sorted(s[4:6])) in ['AC','GT'] and coordinate_counts[(s[1],s[3])]==1
    independent_reference=None
    if reference_validation:
        audit=json.loads(reference_validation.read_text())
        assert audit['completed_chromosomes']==22 and len(audit['loci'])==int(valid.sum())
        confirmed={(x['chrom'],x['pos'],x['ref'],x['alt']) for x in audit['loci'] if x['status']=='exact_ref_alt'}
        excluded=collections.Counter(x['status'] for x in audit['loci'] if x['status']!='exact_ref_alt')
        for i in np.flatnonzero(valid):
            s=snps[i]; valid[i] &= (s[1],int(s[3]),s[4],s[5]) in confirmed
        independent_reference=dict(source='1000 Genomes Phase3 GRCh37 VCFs',source_url='https://www.internationalgenome.org/data-portal/data-collection/phase-3',audit_sha256=sha(reference_validation),checked_markers=len(audit['loci']),confirmed_markers=len(confirmed),excluded_counts=dict(excluded),note='Retain only exact ordered REF/ALT coordinate matches in the independent public VCF. Original candidate is preserved; no source files altered.')
    chosen=np.flatnonzero(valid)
    assert len(chosen)>10000
    chrom_counts=collections.Counter(snps[i][1] for i in chosen)
    assert set(chrom_counts)=={str(x) for x in range(1,23)}
    gts=matrix[:,chosen]
    fractions=np.where(gts==3,np.nan,1-gts.astype(float)/2)
    freq={c:np.nanmean(fractions[m],axis=0) for c,m in masks.items()}
    counts={c:np.sum(gts[m]!=3,axis=0) for c,m in masks.items()}
    output.parent.mkdir(parents=True,exist_ok=True)
    con=sqlite3.connect(output)
    con.executescript('''
      PRAGMA journal_mode=DELETE;
      CREATE TABLE pack_meta(key TEXT PRIMARY KEY,value TEXT NOT NULL);
      CREATE TABLE ancient_origin_groups(group_code TEXT PRIMARY KEY,group_label TEXT NOT NULL,description TEXT NOT NULL,region TEXT NOT NULL,period_label TEXT NOT NULL,sample_count INTEGER NOT NULL,marker_count INTEGER NOT NULL,source_version TEXT NOT NULL,source_url TEXT NOT NULL,limitations TEXT NOT NULL,time_start_bp REAL,time_end_bp REAL,representative_years_bp REAL);
      CREATE TABLE ancient_origin_markers(chrom TEXT NOT NULL,pos INTEGER NOT NULL,build TEXT NOT NULL,ref TEXT NOT NULL,alt TEXT NOT NULL,group_code TEXT NOT NULL,group_label TEXT NOT NULL,alt_frequency REAL NOT NULL CHECK(alt_frequency>=0 AND alt_frequency<=1),allele_number REAL NOT NULL,source_version TEXT NOT NULL,observed_samples INTEGER NOT NULL CHECK(observed_samples>=2),PRIMARY KEY(group_code,chrom,pos));
      CREATE INDEX ancient_origin_coordinate_idx ON ancient_origin_markers(build,chrom,pos);
      CREATE TABLE ancient_origin_samples(sample_id TEXT PRIMARY KEY,individual_id TEXT NOT NULL UNIQUE,group_code TEXT NOT NULL,source_group TEXT NOT NULL,genotype_mode TEXT NOT NULL,date_mean_bp REAL NOT NULL,locality TEXT NOT NULL,source_doi TEXT,coverage INTEGER NOT NULL,source_row_sha256 TEXT NOT NULL);
      CREATE TABLE sources(source_name TEXT,source_url TEXT,version_or_release TEXT,downloaded_at TEXT,file_hash_sha256 TEXT,license_note TEXT,imported_row_count INTEGER,provenance_json TEXT);
    ''')
    utc=datetime.datetime.now(datetime.timezone.utc).isoformat()
    packmeta=dict(schema_version='1',source_version=VERSION,genome_build='GRCh37',strand='forward',status='uncalibrated_reference_evidence',source_url=SOURCE,genotype_coding='TGENO 0=ALT/ALT,1=REF/ALT,2=REF/REF,3=missing; pseudohaploid0/2 are one observed allele',frequency_method='Equal-individual mean ALT fractions; pseudo0/1 and diploid0/.5/1. allele_number records1 per pseudohaploid call and2 per diploid call; it is not the weighting denominator.',marker_filter='Autosomes1-22, GRCh37 unique coordinate, nonpalindromic transversions only (AC/GT), at least2observedindividuals pergroup, identicalmarkers in everygroup.',marker_count=str(len(chosen)),group_count=str(len(groups)),sample_count=str(len(selection['samples'])),created_at_utc=utc,limitations=LIMITS,selection_note=selection['selection_note'])
    if independent_reference:
        packmeta['marker_filter']+=' All retained REF/ALT pairs independently agree with1000GenomesPhase3GRCh37VCFs.'
        packmeta['independent_allele_validation']=json.dumps(independent_reference)
    con.executemany('INSERT INTO pack_meta VALUES(?,?)',packmeta.items())
    for sample in selection['samples']:
        con.execute('INSERT INTO ancient_origin_samples VALUES(?,?,?,?,?,?,?,?,?,?)',(sample['sample_id'],sample['individual_id'],sample['group_code'],sample['source_group'],sample['genotype_mode'],sample['date_mean_bp'],sample['locality'],sample['source_doi'],sample['coverage'],row_manifest[sample['sample_id']]['sha256']))
    for code, group in groups.items():
        samples=[s for s in selection['samples'] if s['group_code']==code]
        dates=[s['date_mean_bp'] for s in samples]
        period=f'{round(max(dates)):,}–{round(min(dates)):,} years BP'
        con.execute('INSERT INTO ancient_origin_groups VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',(code,group['group_label'],group['description'],group['region'],period,len(samples),len(chosen),VERSION,SOURCE,LIMITS,max(dates),min(dates),sum(dates)/len(dates)))
        weights=np.array([2 if s['genotype_mode']=='diploid' else 1 for s in samples])[:,None]
        allele_numbers=((gts[masks[code]]!=3)*weights).sum(axis=0)
        con.executemany('INSERT INTO ancient_origin_markers VALUES(?,?,?,?,?,?,?,?,?,?,?)',((snps[i][1],int(snps[i][3]),'GRCh37',snps[i][4],snps[i][5],code,group['group_label'],float(freq[code][j]),int(allele_numbers[j]),VERSION,int(counts[code][j])) for j,i in enumerate(chosen)))
    for file in source_files:
        con.execute('INSERT INTO sources VALUES(?,?,?,?,?,?,?,?)',('Allen Ancient DNA Resource',file['url'],VERSION,utc,file['sha256'],'CC0 1.0 (Dataverse current dataset metadata); cite AADR and original publications.',len(chosen) if file['filename'].endswith('.snp') else len(selection['samples']),json.dumps(file)))
    con.execute('INSERT INTO sources VALUES(?,?,?,?,?,?,?,?)',('AADR ancient-only genotype byte ranges',selection['retrieval_url'],VERSION,utc,sha(root/'ancient_range_manifest.json'),'CC0 1.0 (Dataverse current dataset metadata); selected ancient individuals only.',len(selection['samples']),json.dumps(retrieval)))
    con.commit(); assert con.execute('PRAGMA integrity_check').fetchone()[0]=='ok'; con.close()
    # Public-source leave-one-individual-out descriptive audit. This is not consumer calibration.
    loo=[]
    for i,sample in enumerate(selection['samples']):
        scores={}; own=sample['group_code']; available=~np.isnan(fractions[i])
        ref_freq={}
        for c,m in masks.items():
            include=m.copy(); include[i]=False
            ref_freq[c]=np.nanmean(fractions[include],axis=0)
            available &= ~np.isnan(ref_freq[c])
        for c in codes:
            d=fractions[i,available]; p=ref_freq[c][available]
            scores[c]=float(np.mean(d*p+(1-d)*(1-p)))
        ranked=sorted(scores,key=lambda c:(-scores[c],c))
        loo.append(dict(sample_id=sample['sample_id'],source_group=own,called_shared_loci=int(available.sum()),top_group=ranked[0],source_group_rank=ranked.index(own)+1,scores=scores))
    validation=dict(decoder_fixture='PASS: 0x1B,0xE4 ->0,1,2,3,3,2,1,0; REF-count orientation fixture PASS',metadata_md5='PASS',all_row_sha256='PASS',current_header=header,header_file_size='PASS',pseudohaploid_no_heterozygotes='PASS',common_before_transversion_filter=common_before_filter,common_primary_marker_count=len(chosen),groups={c:dict(sample_count=int(m.sum()),min_observed_samples=int(counts[c].min()),max_observed_samples=int(counts[c].max())) for c,m in masks.items()},chromosome_counts=dict(chrom_counts),public_source_leave_one_individual_out=dict(warning='Descriptive reference separability only. Not a consumer validation, ancestry proportion calibration or independent ancient discovery cohort. CHG has one remaining individual after holdout.',sample_count=len(loo),same_label_top_count=sum(x['source_group']==x['top_group'] for x in loo),records=loo),sqlite_integrity='ok',sqlite_journal_mode='delete')
    report=output.with_suffix('.validation.json'); report.write_text(json.dumps(validation,indent=2)+'\n')
    manifest=dict(source_name='Allen Ancient DNA Resource',source_url=SOURCE,version_or_release=VERSION,dataverse_version='14.0',downloaded_at=utc,file_hash_sha256=sha(output),license_note='CC0 1.0 per current Dataverse metadata; original studies and AADR should be cited. Source may include specific future corrections. Only current-release ancient rows included.',imported_row_count=len(chosen)*len(groups),pack_filename=output.name,pack_bytes=output.stat().st_size,group_count=len(groups),sample_count=len(selection['samples']),markers_per_group=len(chosen),schema_version=1,genome_build='GRCh37',strand='forward',genotype_coding=packmeta['genotype_coding'],frequency_method=packmeta['frequency_method'],marker_filter=packmeta['marker_filter'],status=packmeta['status'],limitations=LIMITS,source_files=source_files,selected_genotype_ranges_manifest_sha256=sha(root/'ancient_range_manifest.json'),genotype_full_file_md5_verified=False,genotype_full_file_expected_md5=selection['genotype_file']['checksum']['value'],source_retrieval_note=retrieval['verification_note'],selected_samples=selection['samples'],validation_file=report.name,validation_sha256=sha(report),builder_sha256=sha(Path(__file__)),format_documentation=['https://github.com/DReichLab/AdmixTools/blob/master/convertf/README','https://github.com/DReichLab/AdmixTools/blob/master/src/geno.c'])
    if independent_reference: manifest['independent_allele_validation']=independent_reference
    output.with_suffix('.manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
    print(json.dumps({k:manifest[k] for k in ['pack_filename','file_hash_sha256','pack_bytes','markers_per_group','group_count','sample_count']},indent=2))
    print('Public leave-one-out same-label top:',sum(x['source_group']==x['top_group'] for x in loo),'/',len(loo),'(not consumer calibration)')

if __name__=='__main__':
    p=argparse.ArgumentParser(); p.add_argument('--data-root',type=Path,required=True);p.add_argument('--output',type=Path);p.add_argument('--reference-validation',type=Path)
    args=p.parse_args();build(args.data_root,args.output or args.data_root/'packs'/'ancient_origins.sqlite',args.reference_validation)
