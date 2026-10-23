"""Construct a six-locus integration input from our synthetic MyHeritage test file."""
from research_paths import require_path
import csv
from pathlib import Path

root = require_path('ZDNA_FIXTURE_ROOT')
source = root / 'tests/SYNTHETIC-myheritage-origins-not-personal.csv'
target = root / 'tests/SYNTHETIC-discovery-six-loci-not-personal.csv'
loci = [
    ('rs12913832', '15', '28365618', 'GG'),
    ('rs1800407', '15', '28230318', 'CT'),
    ('rs12896399', '14', '92773663', 'GT'),
    ('rs16891982', '5', '33951693', 'CG'),
    ('rs1393350', '11', '89011046', 'GA'),
    ('rs12203592', '6', '396321', 'CT'),
]
known_ids = {row[0] for row in loci}
known_positions = {(row[1], row[2]) for row in loci}
text = source.read_text(encoding='utf-8-sig')
assert 'SYNTHETIC SOFTWARE TEST ONLY' in text
assert not target.exists()
with target.open('x', encoding='utf-8', newline='') as out:
    for line in text.splitlines():
        if line.startswith('#'):
            out.write(line + '\n')
            continue
        fields = next(csv.reader([line]))
        if len(fields) >= 3 and (fields[0] in known_ids or (fields[1], fields[2]) in known_positions):
            continue
        out.write(line + '\n')
    writer = csv.writer(out, quoting=csv.QUOTE_ALL, lineterminator='\n')
    writer.writerows(loci)
print('Created explicitly synthetic six-locus fixture; no personal DNA used.')
