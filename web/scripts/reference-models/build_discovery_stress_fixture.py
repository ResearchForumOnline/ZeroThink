"""Construct realistic-size parser workload; never reads a person's genotype file."""
from research_paths import require_path
from pathlib import Path
import json

source = require_path('ZDNA_ORIGINS_ROOT') / 'runtime/tests/synthetic-origins-550k.txt'
target = require_path('ZDNA_DISCOVERY_ROOT') / 'runtime/tests/synthetic-discovery-rsid-550k.txt'
if target.exists():
    raise SystemExit('Output already exists; preserve it and inspect before rerunning')
count = 0
with source.open() as inp, target.open('x') as out:
    out.write('# SYNTHETIC SOFTWARE WORKLOAD ONLY. All calls are constructed; not a person or accuracy benchmark.\n')
    for line in inp:
        if line.startswith('#') or not line.strip():
            out.write(line)
            continue
        fields = line.rstrip('\n').split('\t')
        if not fields[0].startswith('rs'):
            fields[0] = 'rs' + str(9900000000 + count)
        out.write('\t'.join(fields) + '\n')
        count += 1
print(json.dumps({'rows': count, 'bytes': target.stat().st_size, 'output': str(target), 'purpose': 'synthetic rsID parsing and sound-memory workload'}))
