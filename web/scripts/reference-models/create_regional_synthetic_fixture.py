"""Construct calls from source locus alleles only, never reference donor calls."""
import argparse, json, sqlite3
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('pack',type=Path);p.add_argument('output',type=Path);a=p.parse_args()
con=sqlite3.connect('file:'+str(a.pack)+'?mode=ro',uri=True)
rows=con.execute('SELECT chrom,pos,ref,alt FROM regional_reference_markers GROUP BY chrom,pos ORDER BY CAST(chrom AS INTEGER),pos').fetchall();con.close()
a.output.parent.mkdir(parents=True,exist_ok=True)
with a.output.open('w') as f:
 f.write('# CONSTRUCTED SYNTHETIC SOFTWARE TEST: NOT A PERSON OR DONOR\n# GRCh37 forward strand; cycling reference/reference, reference/alternate, alternate/alternate\nrsid,chromosome,position,genotype\n')
 for i,(chrom,pos,ref,alt) in enumerate(rows):
  # Explicit internal synthetic IDs; coordinate parser accepts these as consumer records.
  genotype=[ref+ref,ref+alt,alt+alt][i%3]
  f.write('SYNTHETIC_%d,%s,%s,%s\n'%(i+1,chrom,pos,genotype))
print(json.dumps(dict(status='constructed',loci=len(rows),chromosomes=len(set(r[0] for r in rows)),source='frequency-only pack locus alleles; no genotype or donor tables read')))
