"""Combine two explicitly constructed software fixtures; never accepts private input paths."""
from research_paths import require_path
import csv
from pathlib import Path
root = require_path('ZDNA_ETHNICITY_ROOT') / 'runtime/tests'
inputs = [root/'SYNTHETIC-discovery-six-loci-not-personal.csv', root/'SYNTHETIC-regional-reference-not-personal.csv']
rows = {}
for path in inputs:
    source = path.read_text()
    assert 'SYNTHETIC' in source[:1000].upper() and 'not' in source[:1000].lower(), 'Expected explicit artificial fixture label'
    for row in csv.reader(line for line in source.splitlines() if line and not line.startswith('#')):
        if len(row) != 4 or not row[2].isdigit(): continue
        rows.setdefault((row[1], int(row[2])), row)
out = root/'SYNTHETIC-ancestry-all-features-not-personal.csv'
assert not out.exists(), 'Preserve previous fixture'
with out.open('w', newline='') as handle:
    handle.write('# MyHeritage DNA raw data.\n# SYNTHETIC SOFTWARE TEST ONLY. Not a person\'s DNA.\n# Genotypes constructed for software verification, not real ancestry.\n# The genotype is reported on the forward (+) strand with respect to\n# the human reference build 37.\n')
    writer = csv.writer(handle)
    writer.writerow(['RSID','CHROMOSOME','POSITION','RESULT'])
    writer.writerows(rows.values())
print(f'Constructed combined fixture: {len(rows)} markers; original fixtures retained.')
