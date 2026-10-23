"""Read existing public AADR metadata only; emit group counts and source provenance.
No genotype, personal upload, family history, or restricted research file is read.
"""
import csv, collections, json, pathlib, re, sys
if len(sys.argv) != 2: raise SystemExit('Usage: audit_public_modern_metadata.py PUBLIC_ANNOTATION_FILE')
path = pathlib.Path(sys.argv[1])
groups = collections.defaultdict(lambda: {'n': 0, '_individuals': set(), 'source_doi_counts': collections.Counter(), 'publication_counts': collections.Counter(), 'repository_counts': collections.Counter(), 'data_type_counts': collections.Counter()})
with path.open(encoding='utf-8-sig') as handle:
    reader = csv.reader(handle, delimiter='\t')
    header = next(reader)
    for row in reader:
        if len(row) < 22: continue
        group = row[14]
        if not re.search(r'English|Irish|Scott|Welsh|Egypt|Ashken|Jew|Palestin|Druze|Bedouin|Orcad|Punj|Balochi|Pathan|Sindhi|Israel|Khazar', group, re.I): continue
        try: modern = float(row[10]) <= 0
        except ValueError: modern = row[10].strip() in ('..', '') and not re.search(r'\d|_BA|_IA|_N|_HG|_Roman', group)
        if not modern: continue
        item = groups[group]
        item['n'] += 1
        item['_individuals'].add(row[2])
        for key, index in [('source_doi_counts', 7), ('publication_counts', 6), ('repository_counts', 8), ('data_type_counts', 21)]:
            item[key][row[index]] += 1
for item in groups.values():
    item['unique_individual_ids'] = len(item.pop('_individuals'))
print(json.dumps({'source_metadata': str(path), 'status': 'metadata_inventory_only_not_reuse_authorization', 'groups': dict(sorted(groups.items()))}, indent=2))
