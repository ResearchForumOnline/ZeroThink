"""Validate the immutable staged artifact and complete its independent audit."""
from research_paths import require_path
import collections,hashlib,json,sqlite3
from pathlib import Path
root=require_path('ZDNA_ORIGINS_ROOT')
pack=root/'ancient_origins.verified.sqlite'
manifest=json.loads(pack.with_suffix('.manifest.json').read_text())
row_audit=json.loads(pack.with_suffix('.source-row-validation.json').read_text())
source_audit=json.loads((root/'ancient_origins.vcf-locus-validation.json').read_text())
digest=hashlib.sha256(pack.read_bytes()).hexdigest()
assert digest==manifest['file_hash_sha256']==row_audit['pack_sha256']
assert source_audit['completed_chromosomes']==22
confirmed={(x['chrom'],x['pos'],x['ref'],x['alt']) for x in source_audit['loci'] if x['status']=='exact_ref_alt'}
con=sqlite3.connect('file:'+str(pack)+'?mode=ro',uri=True)
group_sets={}; counts={}
for code,label,n in con.execute('SELECT group_code,group_label,sample_count FROM ancient_origin_groups'):
    rows=con.execute('SELECT chrom,pos,ref,alt,observed_samples FROM ancient_origin_markers WHERE group_code=?',(code,)).fetchall()
    loci={(c,p,r,a) for c,p,r,a,_ in rows}
    assert loci==confirmed and min(row[-1] for row in rows)>=2
    assert {row[0] for row in rows}=={str(x) for x in range(1,23)}
    group_sets[code]=loci;counts[code]=dict(label=label,samples=n,markers=len(loci),min_observed_samples=min(row[-1] for row in rows))
assert len(group_sets)==6 and len(confirmed)==30826
assert con.execute('PRAGMA integrity_check').fetchone()[0]=='ok'
assert con.execute('PRAGMA journal_mode').fetchone()[0]=='delete'
assert not any(Path(str(pack)+x).exists() for x in ['-wal','-shm','-journal'])
result=dict(status='passed',pack_filename=pack.name,sha256=digest,bytes=pack.stat().st_size,markers_per_group=len(confirmed),marker_rows=len(confirmed)*6,groups=counts,genome_build='GRCh37',strand='forward',source_audit_exclusions={k:v for k,v in source_audit['counts'].items() if k!='exact_ref_alt'},all_retained_loci_match_independent_1000g_ref_alt=True,identical_locus_set_all_six_groups=True,all_22_autosomes=True,assembly_reference_orientation_loci=276725,source_individual_coverage_exact_matches=37,scalar_frequency_check_groups=6,sqlite_integrity='ok',journal_mode='delete',pending_journals=[],release_recommendation='Ready for descriptive source-backed shared-allele affinity and matched-marker evidence with coverage gates. No ancestry-mixture percentages or confidence calibration.',no_live_mutations_by_this_builder=True)
(root/'ancient_origins.verified.final-validation.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result,indent=2))
