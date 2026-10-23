from research_paths import require_path
from pathlib import Path
import csv
root=require_path('ZDNA_FIXTURE_ROOT')
source=root/'tests/synthetic-origins-all-sections.txt'
target=root/'tests/SYNTHETIC-myheritage-origins-not-personal.csv'
header='''# MyHeritage DNA raw data.
# SYNTHETIC SOFTWARE TEST ONLY. This is not a person's DNA.
# For each SNP, we provide the identifier, chromosome
# number, base pair position and genotype. The genotype
# is reported on the forward (+) strand with respect to
# the human reference build 37.
'''
with target.open('w',newline='',encoding='utf-8') as out:
 out.write(header)
 writer=csv.writer(out,quoting=csv.QUOTE_ALL)
 writer.writerow(['RSID','CHROMOSOME','POSITION','RESULT'])
 for line in source.read_text().splitlines():
  if line and not line.startswith('#'):writer.writerow(line.split('\t'))
print(target, target.stat().st_size)
