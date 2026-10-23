"""Synthetic software input only: alleles are constructed, not donor genotypes."""
from research_paths import require_path
import sqlite3,json
from pathlib import Path
stage=require_path('ZDNA_ORIGINS_ROOT')
rows={}
def add(chrom,pos,ref,alt,rsid=None):
 key=(str(chrom),int(pos))
 if key not in rows:rows[key]=(rsid or 'syn'+str(chrom)+'_'+str(pos),ref+alt)
with sqlite3.connect('file:'+str(require_path('ZDNA_DATA_ROOT')/'packs/ancestry_core.sqlite')+'?mode=ro',uri=True) as c:
 for chrom in range(1,23):
  for rsid,pos,ref,alt in c.execute("SELECT rsid,pos,ref,alt FROM ancestry_reference_markers WHERE panel_name='1000G_Phase3_optimized_population' AND population_code='GBR' AND chrom=? ORDER BY pos LIMIT500".replace('LIMIT500','LIMIT 500'),(str(chrom),)):add(chrom,pos,ref,alt,rsid)
with sqlite3.connect(stage/'ancient_origins.sqlite') as c:
 for chrom in range(1,23):
  for pos,ref,alt in c.execute("SELECT pos,ref,alt FROM ancient_origin_markers WHERE group_code='WHG' AND chrom=? ORDER BY pos LIMIT 200",(str(chrom),)):add(chrom,pos,ref,alt)
with sqlite3.connect(stage/'archaic_markers.sqlite') as c:
 for group in ['neanderthal','denisovan']:
  for chrom in range(1,23):
   for rsid,pos,ref,alt in c.execute('SELECT rsid,pos,ref,alt FROM archaic_markers WHERE archaic_group=? AND chrom=? ORDER BY pos LIMIT50'.replace('LIMIT50','LIMIT 50'),(group,str(chrom))):add(chrom,pos,ref,alt,rsid)
def write(path):
 with path.open('w') as f:
  f.write('# SYNTHETIC SOFTWARE TEST ONLY - NOT A PERSON OR ETHNICITY SAMPLE\n# Genome build GRCh37\n# Reported strand: forward\n# rsid\tchromosome\tposition\tgenotype\n')
  for (chrom,pos),(rsid,gt) in sorted(rows.items(),key=lambda x:(int(x[0][0]),x[0][1])):f.write(f'{rsid}\t{chrom}\t{pos}\t{gt}\n')
small=stage/'runtime/tests/synthetic-origins-all-sections.txt';write(small)
small_count=len(rows)
for n in range(550000-len(rows)):
 chrom=str(n%22+1);pos=100000000+n//22
 if (chrom,pos) not in rows:rows[(chrom,pos)]=('synStress'+str(n),'AA')
large=stage/'runtime/tests/synthetic-origins-550k.txt';write(large)
print(json.dumps({'small_rows':small_count,'large_rows':len(rows),'large_bytes':large.stat().st_size,'notice':'Constructed synthetic calls only. No donor genotypes.'}))
