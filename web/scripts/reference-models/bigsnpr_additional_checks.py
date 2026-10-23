# SPDX-License-Identifier: GPL-3.0-or-later
# Uses evaluate_bigsnpr_composition; see its Prive/Matrix attribution and COPYING.GPL-3.0.
"""Aggregate-only chromosome-block, sparse-mask and source-overlap diagnostics."""
from research_paths import require_path
import hashlib,json,collections,time
from pathlib import Path
import numpy as np
from evaluate_bigsnpr_composition import BASE,OUT,fit,merge_labels
PUB=require_path('ZDNA_REGIONAL_DATA')
model=np.load(OUT/'model-HO-aggregate.npz');public=np.load(OUT/'public-HO.private.npz')
F=model['frequency'];L=model['loading'];groups=list(model['groups']);loci=model['loci'];calls=public['calls'];labels=list(public['labels']);rng=np.random.default_rng(20260906)
blocks=[];masks=[]
for code in sorted(set(labels)):
    idx=labels.index(code);g=calls[idx]
    full=fit(g,F,L,groups)
    runs=[fit(g,F,L,groups,indices=loci[:,0]!=str(c)) for c in range(1,23)]
    widths={k:100*(max(r['coefficients'][k] for r in runs)-min(r['coefficients'][k] for r in runs)) for k in full['coefficients']}
    blocks.append(dict(group=code,representative_samples=1,omitted_blocks=22,max_range_percentage_points=max(widths.values()),component_ranges_percentage_points=widths))
    for n in [50000,100000]:
        selected=np.zeros(len(F),dtype=bool); selected[rng.choice(np.where(g>=0)[0],n,replace=False)]=True
        r=fit(g,F,L,groups,indices=selected)
        shift=sum(abs(r['coefficients'][k]-full['coefficients'][k]) for k in full['coefficients'])/2
        masks.append(dict(group=code,markers=n,correlation=r['correlation'],absolute_reallocated_fraction=shift))
panels=list(require_path('ZDNA_DATA_ROOT') / 'sources/1000g'.glob('*.panel'))
panel=next((p for p in panels if 'integrated_call_samples_v3' in p.name),None)
assert panel is not None
ids=set(line.split()[0] for line in panel.read_text().splitlines()[1:] if line.strip())
selection=json.loads((PUB/'regional_selection.json').read_text())
direct=sum(s['sample_id'] in ids for s in selection['samples'])
text_alias=sum(any(value in ids for value in s.values() if isinstance(value,str)) for s in selection['samples'])
report=dict(status='completed',chromosome_omission=blocks,marker_mask_checks=masks,source_overlap=dict(public_challenge_samples=len(selection['samples']),public_1000g_panel_samples=len(ids),direct_id_matches=direct,exact_metadata_field_matches=text_alias,panel_sha256=hashlib.sha256(panel.read_bytes()).hexdigest(),boundary='Exact identifier audit; no UK Biobank participant identifiers accessed. Does not establish that differently named participants can never overlap. All challenge rows are documented HumanOrigins source data, not imported UKBB individual genotypes.'),interpretation='Chromosome omission and constructed random masks measure sensitivity to which observed loci are used; they are not ancestry confidence intervals or a validation of a particular consumer chip.')
(OUT/'additional-checks.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(dict(status='completed',source_overlap=report['source_overlap'],chromosome_max_ranges={r['group']:r['max_range_percentage_points'] for r in blocks},max_50k_reallocation=max(r['absolute_reallocated_fraction'] for r in masks if r['markers']==50000))),flush=True)
