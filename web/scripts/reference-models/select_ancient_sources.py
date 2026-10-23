"""Select only published ancient AADR individuals from current v66.p1 metadata."""
from research_paths import require_path
import csv, json, hashlib, re
from pathlib import Path

ROOT = require_path('ZDNA_ORIGINS_DATA')
IND = 'v66.p1_compatibility_HO.aadr.patch.PUB.ind'
ANNO = 'v66.p1_compatibility_HO.aadr.PUB.anno'
meta = json.loads((ROOT/'aadr_dataverse_metadata.json').read_text(encoding='utf-8-sig'))
files = {x['dataFile']['filename']: x['dataFile'] for x in meta['latestVersion']['files']}
for name in [IND, ANNO]:
    assert hashlib.md5((ROOT/name).read_bytes()).hexdigest() == files[name]['checksum']['value']
inds = [x.split() for x in (ROOT/IND).read_text().splitlines()]
indices = {x[0]: i for i,x in enumerate(inds)}
rows = list(csv.DictReader((ROOT/ANNO).open(encoding='utf-8-sig'), delimiter='\t'))
keys = list(rows[0]); id_key = keys[0]
cov_key = next(k for k in keys if k.endswith('(Computed using easystats on Compatibility_HO snpset)'))
date_key = next(k for k in keys if k.startswith('Date mean'))
GROUPS = {
 'WHG': dict(group_label='Western hunter-gatherer references', region='Western Europe', description='Loschbour, Bichon and La Brana 1; a small published western European hunter-gatherer reference set.', cap=3),
 'EHG': dict(group_label='Eastern hunter-gatherer references', region='Karelia and Samara', description='Published Mesolithic hunter-gatherer references from Karelia and the Samara region.', cap=4),
 'CHG': dict(group_label='Caucasus hunter-gatherer references', region='Western Georgia', description='Kotias Klde and Satsurblia; two published ancient Caucasus hunter-gatherer individuals.', cap=2),
 'ANATOLIA_N': dict(group_label='Anatolian early farmer references', region='Northwestern Anatolia', description='Published Neolithic individuals from Barcin and Mentese in northwestern Anatolia.', cap=12),
 'STEPPE_EMBA': dict(group_label='Steppe early Bronze Age references', region='Samara steppe', description='Published Yamnaya-associated early Bronze Age individuals from the Samara region; a regional reference subset.', cap=12),
 'IRAN_N': dict(group_label='Zagros early farmer references', region='Ganj Dareh, Iran', description='Published Neolithic individuals from Ganj Dareh in the Zagros mountains.', cap=4),
}

def code_for(r):
    ident, g = r['Individual ID'], r['Group ID']
    if ident in ['Loschbour', 'Bichon', 'LaBrana1']: return 'WHG'
    if ident in ['I0061', 'I0211', 'I0124', 'I6413']: return 'EHG'
    # NEO281 may represent Kotias under a different identifier: omit it conservatively.
    if ident in ['KK1', 'SATP']: return 'CHG'
    if g == 'Turkey_N' and (r['Locality'].startswith('Barc') or r['Locality'].startswith('Mente')): return 'ANATOLIA_N'
    if g == 'Russia_Samara_EBA_Yamnaya': return 'STEPPE_EMBA'
    if g == 'Iran_GanjDareh_N': return 'IRAN_N'

selected = []
for code, group in GROUPS.items():
    best = {}
    for r in rows:
        if code_for(r) != code or r[id_key] not in indices or r['ASSESSMENT'] != 'Pass': continue
        if r['Family relations'].strip() not in ['', '..']: continue
        try: coverage = int(r[cov_key]); age = float(r[date_key])
        except ValueError: continue
        if coverage < 60000 or age < 3000: continue
        item = dict(sample_id=r[id_key], individual_id=r['Individual ID'], group_code=code,
          source_group=r['Group ID'], sample_index=indices[r[id_key]], coverage=coverage,
          date_mean_bp=age, locality=r['Locality'], country=r['Political Entity'],
          source_doi=r['doi for publication of this representation of the data'],
          persistent_genetic_id=r['Persistent Genetic ID'], assessment=r['ASSESSMENT'],
          family_relations=r['Family relations'], genotype_mode='diploid' if r[id_key].endswith('.DG') else 'pseudohaploid')
        # Each ancient person receives one representation. Prefer diploid if available.
        ranking = (item['genotype_mode']=='diploid', coverage, item['sample_id'])
        previous = best.get(item['individual_id'])
        if previous is None or ranking > previous[0]: best[item['individual_id']] = (ranking,item)
    candidates = sorted((v[1] for v in best.values()),key=lambda x:(-x['coverage'],x['sample_id']))[:group['cap']]
    assert len(candidates)>=2, (code,len(candidates))
    selected.extend(candidates)

header = (ROOT/'current_tgeno_header.bin').read_bytes().decode().strip('\0').split()
assert header[0] == 'TGENO' and int(header[1])==len(inds)
geno = files['v66.p1_compatibility_HO.aadr.patch.PUB.geno']
row_bytes = (int(header[2])+3)//4
assert 48+len(inds)*row_bytes == geno['filesize']
for x in selected:
    x['byte_start']=48+x['sample_index']*row_bytes
    x['byte_end']=x['byte_start']+row_bytes-1
    x['row_filename']='ancient_rows/'+x['sample_id']+'.bin'
out=dict(schema_version=1, groups=GROUPS,samples=selected,genotype_file=geno,header=header,row_bytes=row_bytes,
    retrieval_url='https://dataverse.harvard.edu/api/access/datafile/'+str(geno['id']),
    selection_note='Ancient-only current v66.p1 rows; Pass assessment, >=60000 HO-compatibility calls, no listed family relationships, one representation per Individual ID, highest marker coverage. NEO281 omitted to avoid possible alternate Kotias identification. No modern donors processed.')
(ROOT/'ancient_selection.json').write_text(json.dumps(out,indent=2)+'\n')
print(json.dumps({'groups':{c:[x['sample_id'] for x in selected if x['group_code']==c] for c in GROUPS},'total_samples':len(selected),'download_bytes':len(selected)*row_bytes,'header':header},indent=2))
