# SPDX-License-Identifier: GPL-3.0-or-later
# Uses evaluate_bigsnpr_composition; see its Prive/Matrix attribution and COPYING.GPL-3.0.
"""Validate the exact final union/paired assets, exporting constructed tests and aggregates only."""
from research_paths import require_path
import importlib.util,json,sqlite3,time,hashlib,os
from pathlib import Path
import numpy as np
from evaluate_bigsnpr_composition import fit,summarize
BASE=require_path('ZDNA_COMPOSITION_ROOT');B=BASE/'bundle-v2';E=BASE/'evaluation';os.umask(0o077)
spec=importlib.util.spec_from_file_location('worker',B/'worker.py');w=importlib.util.module_from_spec(spec);spec.loader.exec_module(w)
meta=json.loads((B/'model.json').read_text());rng=np.random.default_rng(20260907);start=time.monotonic()
source=np.load(E/'model-HO-aggregate.npz');public=np.load(E/'public-HO.private.npz');labels=list(public['labels']);calls=public['calls'];HF=source['frequency'];HL=source['loading'];HO=source['loci']
report=dict(status='exact_bundle_validation',builds={})
for build in ['37','38']:
    a=np.load(B/('weights'+build+'.npz'));F=a['frequency'];L=a['loading'];groups=list(a['groups']);chrom=a['chromosome']
    db=sqlite3.connect('file:'+str(B/('marker_index'+build+'.sqlite'))+'?mode=ro',uri=True);defs=list(db.execute('SELECT chrom,pos37,pos38,rsid,ref,alt FROM markers ORDER BY CAST(chrom AS INTEGER),pos37'));db.close();assert len(defs)==len(F)
    idx={(str(d[0]),int(d[1])):i for i,d in enumerate(defs)};shared=[(j,idx[(str(r[0]),int(r[1]))]) for j,r in enumerate(HO) if (str(r[0]),int(r[1])) in idx]
    si=np.array([j for j,i in shared]);ti=np.array([i for j,i in shared]);assert np.array_equal(F[ti],HF[si]) and np.array_equal(L[ti],HL[si])
    available=0;rows=[];max_delta=0.
    for n,g0 in enumerate(calls):
        g=np.full(len(F),-1,dtype=np.int8);g[ti]=g0[si]
        actual=w.calculate(F,L,g,chrom,groups,meta['components'])
        if actual['available']:
            available+=1;baseline=fit(g0[si],HF[si],HL[si],groups)
            actual_q={c['code']:c['percent']/100 for c in actual['components']}
            for c in meta['components']:
                source_name= 'Europe (North West)' if c['code']=='EUR_NW' else 'Europe (East)' if c['code']=='EUR_E' else c['source_groups'][0]
                max_delta=max(max_delta,abs(actual_q[c['code']]-baseline['coefficients'][source_name]))
            rows.append(dict(coefficients={c['label']:c['percent']/100 for c in actual['components']},correlation=actual['quality']['correlation'],marker_count=actual['matched_markers']))
        else:assert actual['status']=='insufficient_model_markers'
    build_report=dict(asset_markers=len(F),public_intersection_markers=len(shared),public_evaluated=len(calls),public_available=available,public_projection_coefficient_max_absolute_delta=max_delta)
    if available:assert available==230 and max_delta<1e-6;build_report['public_summary']=summarize(rows,labels)
    synthetic=[]
    for pair in [('United Kingdom','Middle East'),('Ashkenazi','United Kingdom'),('Africa (North)','Middle East'),('Pakistan','Middle East')]:
        ia,ib=[groups.index(x) for x in pair]
        for proportion in [.25,.5,.75]:
            dose=rng.binomial(2,proportion*F[:,ia]+(1-proportion)*F[:,ib]).astype(np.int8)
            result=w.calculate(F,L,dose,chrom,groups,meta['components']);assert result['available']
            truth={}
            for c in meta['components']:truth[c['code']]=(proportion if pair[0] in c['source_groups'] else 0)+(1-proportion if pair[1] in c['source_groups'] else 0)
            err=sum(abs(c['percent']/100-truth[c['code']]) for c in result['components'])/2
            synthetic.append(dict(pair=pair,first_fraction=proportion,misallocated_fraction=err,max_omission_range=result['quality']['max_component_sensitivity_percentage_points']))
    # Fixed explicit demo input: independent 50/50 Northwest-Europe / Middle-East reference draws.
    demo=rng.binomial(2,.5*F[:,groups.index('United Kingdom')]+.5*F[:,groups.index('Middle East')]).astype(np.int8)
    demo_result=w.calculate(F,L,demo,chrom,groups,meta['components']);assert demo_result['available']
    fixture=E/('SYNTHETIC-composition-GRCh'+build+'-not-personal.csv')
    with fixture.open('w') as dest:
        dest.write('# CONSTRUCTED SYNTHETIC SOFTWARE TEST: NOT A PERSON OR DONOR\n# GRCh'+build+' forward strand; independent seeded draws from published aggregate reference frequencies\nrsid,chromosome,position,genotype\n')
        for d,g in zip(defs,demo):dest.write(','.join([d[3],str(d[0]),str(d[1] if build=='37' else d[2]),[d[4]*2,d[4]+d[5],d[5]*2][int(g)]])+'\n')
    (E/('SYNTHETIC-composition-GRCh'+build+'-expected.json')).write_text(json.dumps(demo_result,indent=2)+'\n')
    masks=[]
    for n in [49999,50000,100000]:
        selected=rng.choice(len(F),n,replace=False);g=np.full(len(F),-1,dtype=np.int8);g[selected]=demo[selected]
        r=w.calculate(F,L,g,chrom,groups,meta['components']);assert r['available']==(n>=50000)
        masks.append(dict(markers=n,available=r['available'],status=r['status'],correlation=r.get('quality',{}).get('correlation')))
    for dose in [0,1,2]:assert not w.calculate(F,L,np.full(len(F),dose,dtype=np.int8),chrom,groups,meta['components'])['available']
    build_report.update(synthetic_mixtures=synthetic,synthetic_mean_misallocated_fraction=float(np.mean([r['misallocated_fraction'] for r in synthetic])),synthetic_max_misallocated_fraction=max(r['misallocated_fraction'] for r in synthetic),synthetic_fixture_sha256=hashlib.sha256(fixture.read_bytes()).hexdigest(),marker_masks=masks,constant_variance_withheld=True)
    report['builds']['GRCh'+build]=build_report
    print(json.dumps(dict(build=build,markers=len(F),public_available=available,public_parity_max_delta=max_delta,synthetic_mean_error=build_report['synthetic_mean_misallocated_fraction'],elapsed=round(time.monotonic()-start,1))),flush=True)
report['elapsed_seconds']=round(time.monotonic()-start,2);report['boundary']='Exact deployed-asset method/plumbing checks. Constructed mixtures have no natural haplotype structure; real public donor labels are not known pedigree proportions. No consumer accuracy percentage or OOD guarantee.'
(E/'exact-bundle-validation.json').write_text(json.dumps(report,indent=2)+'\n')
