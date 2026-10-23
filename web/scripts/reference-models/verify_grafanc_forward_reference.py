"""Bounded independent GRCh37 allele-definition audit; reads no genotypes."""
from research_paths import require_path
import collections, json, sqlite3
from pathlib import Path
from cyvcf2 import VCF
BASE=require_path('ZDNA_GRAFANC_ROOT');db=sqlite3.connect('file:'+str(BASE/'bundle/data/marker_index.sqlite')+'?mode=ro',uri=True)
results=collections.Counter();bychrom={}
for chrom in range(1,23):
 rows=db.execute('SELECT pos37,ref,alt FROM markers WHERE chrom=? ORDER BY pos37',(str(chrom),)).fetchall()
 chosen=[rows[int(i*(len(rows)-1)/49)] for i in range(50)]
 directory=require_path('ZDNA_UPGRADE_ROOT') / 'public-source-repair' if chrom==21 else require_path('ZDNA_DATA_ROOT') / 'sources/1000g'
 vcf=VCF(str(next(directory.glob('ALL.chr%d.*genotypes.vcf.gz'%chrom))),threads=1);counts=collections.Counter()
 for pos,ref,alt in chosen:
  recs=[r for r in vcf('%d:%d-%d'%(chrom,pos,pos)) if r.POS==pos]
  if any(r.REF==ref and alt in r.ALT for r in recs):status='exact_forward_ref_alt_member'
  elif any(r.REF==alt and ref in r.ALT for r in recs):status='direct_pair_swapped_ref_alt'
  elif any(r.REF==ref.translate(str.maketrans('ACGT','TGCA')) and alt.translate(str.maketrans('ACGT','TGCA')) in r.ALT for r in recs):status='complemented_pair'
  elif any(r.REF==alt.translate(str.maketrans('ACGT','TGCA')) and ref.translate(str.maketrans('ACGT','TGCA')) in r.ALT for r in recs):status='complemented_pair_swapped'
  elif not recs:status='not_in_phase3'
  elif any(r.REF==ref for r in recs):status='ref_agrees_alt_not_present'
  else:status='reference_disagreement'
  counts[status]+=1;results[status]+=1
 vcf.close();bychrom[str(chrom)]=dict(counts)
db.close();report=dict(status='completed',sampled_loci=1100,chromosomes=22,selection='50 evenly spaced model-index entries per chromosome',counts=dict(results),by_chromosome=bychrom,source='Existing official 1000 Genomes Phase3 GRCh37 VCFs; repaired official chromosome21 source',limitation='Bounded allele-definition audit only, not every model locus; ALT membership does not require strictly biallelic confirming records. No VCF genotype field decoded.')
(BASE/'evaluation/grafanc-forward-reference-audit.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
