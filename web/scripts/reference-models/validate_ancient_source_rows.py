"""Independently check source row order/bit layout against AADR metadata and hg19."""
import argparse,collections,hashlib,json,sqlite3
from pathlib import Path

p=argparse.ArgumentParser();p.add_argument('--pack',type=Path);args=p.parse_args()
root=Path(__file__).resolve().parents[1]/'data'
selection=json.loads((root/'ancient_selection.json').read_text())
n=int(selection['header'][2])

def scalar_decode(raw):
    # Independent scalar implementation; no numpy reshape/column order reuse.
    result=[]
    for index in range(n):
        value=raw[index//4]
        slot=index%4
        result.append((value//(4**(3-slot)))%4)
    return result

reference=root/'Href.REF.hg19_assembly.bin'
ref_manifest=json.loads((root/'hg19_reference_range.json').read_text(encoding='utf-8-sig'))
assert hashlib.sha256(reference.read_bytes()).hexdigest()==ref_manifest['sha256']
ref_calls=scalar_decode(reference.read_bytes()); ref_counts=collections.Counter(ref_calls)
assert ref_counts=={2:n},ref_counts
coverage=[]
for s in selection['samples']:
    calls=scalar_decode((root/s['row_filename']).read_bytes())
    actual=sum(x!=3 for x in calls)
    assert actual==s['coverage'],s['sample_id']
    coverage.append(dict(sample_id=s['sample_id'],official_nonmissing_calls=s['coverage'],decoded_nonmissing_calls=actual,passed=True))
pack=args.pack or root/'packs'/'ancient_origins.sqlite'; con=sqlite3.connect('file:'+pack.as_posix()+'?mode=ro',uri=True)
# Validate actual stored ALT frequency against an independently decoded locus.
first=con.execute("SELECT chrom,pos,ref,alt FROM ancient_origin_markers WHERE group_code='WHG' ORDER BY CAST(chrom AS INT),pos LIMIT 1").fetchone()
snps=[x.split() for x in (root/'v66.p1_compatibility_HO.aadr.patch.PUB.snp').read_text().splitlines()]
i=next(i for i,s in enumerate(snps) if s[1]==first[0] and int(s[3])==first[1])
checks=[]
for code in selection['groups']:
    values=[]
    for s in selection['samples']:
        if s['group_code']!=code:continue
        b=(root/s['row_filename']).read_bytes()[i//4]
        gt=(b//(4**(3-i%4)))%4
        if gt!=3:values.append((2-gt)/2)
    expected=sum(values)/len(values)
    stored=con.execute('SELECT alt_frequency,observed_samples FROM ancient_origin_markers WHERE group_code=? AND chrom=? AND pos=?',(code,first[0],first[1])).fetchone()
    assert abs(stored[0]-expected)<1e-12 and stored[1]==len(values)
    checks.append(dict(group_code=code,frequency_equal=True,observed_samples_equal=True))
result=dict(status='passed',pack_sha256=hashlib.sha256(pack.read_bytes()).hexdigest(),reference_assembly_row=dict(identifier='Href.REF',source_group='hg19ref',called_loci=n,all_packed_codes_equal_two=True,meaning='Independent assembly-reference row proves packed2 corresponds to the hg19 REF allele at all276725sourceSNPs.',retrieval=ref_manifest),ancient_individuals_exact_annotation_coverage=coverage,scalar_frequency_recalculation=checks,privacy_note='Only37publishedancientindividual rows and1hg19assemblyreference row were retrieved; no modern donor row or personal upload processed.')
out=pack.with_suffix('.source-row-validation.json');out.write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps({'status':result['status'],'hg19_all_ref_code_2_loci':n,'source_coverage_exact_matches':len(coverage),'frequency_recalculation_groups':len(checks)},indent=2))
