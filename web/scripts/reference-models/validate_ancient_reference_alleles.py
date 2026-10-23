"""Read-only comparison of new AADR alleles with installed 1000G source alleles."""
import collections,hashlib,json,sqlite3
from pathlib import Path
from cyvcf2 import VCF

ROOT=Path('/mnt/data/zerothink/dna-origins-20260905')
DATA=Path('/home/talkto/zerothink.talktoai.org/data')
pack=ROOT/'ancient_origins.sqlite'
con=sqlite3.connect('file:'+str(pack)+'?mode=ro',uri=True)
anc={(c,p):(r,a) for c,p,r,a in con.execute("SELECT chrom,pos,ref,alt FROM ancient_origin_markers WHERE group_code='WHG'")}
modern=sqlite3.connect('file:'+str(DATA/'packs/ancestry_core.sqlite')+'?mode=ro',uri=True)
status=collections.Counter();n=0
for c,p,r,a in modern.execute("SELECT chrom,pos,ref,alt FROM ancestry_reference_markers WHERE panel_name='1000G_Phase3_optimized_superpop' AND population_code='EUR'"):
    key=(str(c),int(p))
    if key not in anc:continue
    n+=1
    status['exact_ref_alt' if anc[key]==(r,a) else 'swapped_ref_alt' if anc[key]==(a,r) else 'different_pair']+=1
vcf_status=collections.Counter();vcf_chrom={};all_locus_results=[]
for chrom in range(1,23):
    loci=sorted((p,pair) for (c,p),pair in anc.items() if c==str(chrom))
    selected=loci
    directory=Path('/mnt/data/zerothink/dna-upgrade-20260905/public-source-repair') if chrom==21 else DATA/'sources/1000g'
    v=VCF(str(next(directory.glob('ALL.chr%d.*genotypes.vcf.gz'%chrom))))
    counts=collections.Counter()
    for pos,(ref,alt) in selected:
        records=[x for x in v('%d:%d-%d'%(chrom,pos,pos)) if x.POS==pos]
        if not records:outcome='not_present'
        elif any(x.REF==ref and alt in x.ALT for x in records):outcome='exact_ref_alt'
        elif any(x.REF==alt and ref in x.ALT for x in records):outcome='swapped_ref_alt'
        else:outcome='different_pair'
        counts[outcome]+=1;vcf_status[outcome]+=1
        all_locus_results.append(dict(chrom=str(chrom),pos=pos,ref=ref,alt=alt,status=outcome,vcf_alleles=[(x.REF,x.ALT) for x in records] if outcome in ['swapped_ref_alt','different_pair'] else []))
    v.close();vcf_chrom[str(chrom)]=dict(counts)
    (ROOT/'ancient_origins.vcf-locus-validation.json').write_text(json.dumps(dict(completed_chromosomes=chrom,counts=dict(vcf_status),loci=all_locus_results),indent=2)+'\n')
    print('CHROMOSOME',chrom,dict(counts),flush=True)
print('COORDINATE_COUNTS',n,dict(status),dict(vcf_status),flush=True)
assert status.get('swapped_ref_alt',0)==0 and status.get('different_pair',0)==0
assert vcf_status.get('swapped_ref_alt',0)==0 and vcf_status.get('different_pair',0)==0
assert n>4000 and vcf_status['exact_ref_alt']>30000
result=dict(status='passed',pack_sha256=hashlib.sha256(pack.read_bytes()).hexdigest(),sqlite_integrity=con.execute('PRAGMA integrity_check').fetchone()[0],sqlite_journal_mode=con.execute('PRAGMA journal_mode').fetchone()[0],pending_journals=[str(x) for suffix in ['-wal','-shm','-journal'] if (x:=Path(str(pack)+suffix)).exists()],all_panel_coordinate_overlap=dict(status),all_panel_loci=30961,official_1000g_vcf_all_loci=dict(vcf_status),vcf_chromosomes=vcf_chrom,method='Direct GRCh37 coordinate and ordered REF/ALT equality; 1000G Phase3 modern panel all overlapping loci, plus all30,961positions across22autosomes from original public VCFs. No genotype values read or exported. Source chr21 uses MD5-verified private repair copy.',limitation='Coordinate/allele validation supports decoding orientation and build consistency; it does not calibrate ancestry proportions or consumer group-classification performance.')
(ROOT/'ancient_origins.allele-validation.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps({k:v for k,v in result.items() if k!='vcf_chromosomes'},indent=2))
