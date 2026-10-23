"""Isolated evaluation of the published bigsnpr individual-composition method.

GPL-3.0-or-later. Method and reference: Florian Prive, Bioinformatics 2022,
doi:10.1093/bioinformatics/btac348. The nearest-PD routine is a Python adaptation
of Matrix::nearPD (Martin Maechler 2007-2019; Jens Oehlschlaegel 2007,
GPL-2.0-or-later), preserving the documented default parameters.
No individual public-source calls or per-person predictions are exported.
"""
from research_paths import require_path
import collections, hashlib, json, os, time
from pathlib import Path
import numpy as np
import quadprog
from scipy.optimize import minimize

BASE=require_path('ZDNA_COMPOSITION_ROOT')
OUT=BASE/'evaluation'
CORRECTION=np.array([1,1,1,1.008,1.021,1.034,1.052,1.074,1.099,1.123,1.15,1.195,1.256,1.321,1.382,1.443])
def near_pd(matrix):
    """Matrix::nearPD default covariance mode: Higham/Dykstra + posdefify."""
    x=(matrix+matrix.T)/2;ds=np.zeros_like(x)
    for it in range(100):
        y=x.copy();r=y-ds;d,q=np.linalg.eigh(r);keep=d>1e-6*d[-1]
        if not keep.any():raise ValueError('negative semidefinite matrix')
        x=(q[:,keep]*d[keep])@q[:,keep].T;ds=x-r
        conv=np.linalg.norm(y-x,np.inf)/np.linalg.norm(y,np.inf)
        if conv<=1e-7:break
    else:raise ValueError('nearPD failed to converge')
    d,q=np.linalg.eigh(x);eps=1e-8*abs(d[-1])
    if d[0]<eps:
        old_diag=np.diag(x).copy();d=np.maximum(d,eps);x=(q*d)@q.T
        scale=np.sqrt(np.maximum(eps,old_diag)/np.diag(x));x=scale[:,None]*x*scale[None,:]
    return (x+x.T)/2
def qp_fit(x,y,independent_check=False):
    d=near_pd(x.T@x);linear=x.T@y;n=x.shape[1]
    scale=np.max(np.abs(d));d=d/scale;linear=linear/scale
    solution=quadprog.solve_qp(d,linear,np.column_stack([-np.ones(n),np.eye(n)]),np.r_[-1.,np.zeros(n)],meq=1)[0]
    if not np.isfinite(solution).all() or solution.min()<-1e-7 or abs(solution.sum()-1)>1e-6:raise ValueError('invalid convex solution')
    if independent_check:
        other=minimize(lambda a:0.5*a@d@a-linear@a,np.ones(n)/n,jac=lambda a:d@a-linear,method='SLSQP',bounds=[(0,1)]*n,constraints={'type':'eq','fun':lambda a:a.sum()-1,'jac':lambda a:np.ones(n)},options={'ftol':1e-13,'maxiter':3000})
        objective=lambda a:0.5*a@d@a-linear@a
        assert other.success and abs(objective(solution)-objective(other.x))<1e-8
    return np.clip(solution,0,1)
def merge_labels(groups):
    return ['Europe (North West)' if s in ['Scandinavia','United Kingdom','Ireland'] else 'Europe (East)' if s in ['Europe (South East)','Europe (North East)'] else s for s in groups]
def corr(a,b):
    ac=a-a.mean();bc=b-b.mean();den=np.linalg.norm(ac)*np.linalg.norm(bc)
    return float(ac@bc/den) if den else float('nan')
def fit(genotype,F,L,groups,indices=None,omitted=None,independent_check=False,correct=True):
    observed=genotype>=0
    if indices is not None:observed &= indices
    f=F[observed];l=L[observed];g=genotype[observed]/2
    kept=np.ones(F.shape[1],dtype=bool)
    if omitted is not None:kept[omitted]=False
    f=f[:,kept];labels=np.array(groups)[kept]
    x=l.T@f;y=(l.T@g)*(CORRECTION if correct else 1)
    q=qp_fit(x,y,independent_check)
    merged=collections.defaultdict(float)
    for label,value in zip(merge_labels(labels),q):merged[label]+=float(value)
    estimate=f@q
    return dict(coefficients=dict(merged),correlation=corr(estimate,g),marker_count=len(g),projected_residual_norm=float(np.linalg.norm(y-x@q)))
def summarize(rows,labels):
    out={}
    for group in sorted(set(labels)):
        chosen=[r for r,label in zip(rows,labels) if label==group]
        keys=list(chosen[0]['coefficients'])
        out[group]=dict(samples=len(chosen),called_marker_min=min(r['marker_count'] for r in chosen),called_marker_max=max(r['marker_count'] for r in chosen),correlation_min=min(r['correlation'] for r in chosen),correlation_mean=float(np.mean([r['correlation'] for r in chosen])),passes_author_individual_correlation_gate=sum(r['correlation']>0.6 for r in chosen),mean_model_percent={k:round(100*float(np.mean([r['coefficients'][k] for r in chosen])),2) for k in keys},dominant_group_counts=dict(collections.Counter(max(r['coefficients'],key=r['coefficients'].get) for r in chosen)))
    return out
def main():
    os.umask(0o077);start=time.monotonic();model=np.load(OUT/'model-HO-aggregate.npz');public=np.load(OUT/'public-HO.private.npz')
    F=model['frequency'];L=model['loading'];groups=list(model['groups']);calls=public['calls'];labels=list(public['labels']);rng=np.random.default_rng(20260905)
    tests={};probe=rng.normal(size=(12,21));pd=near_pd(probe.T@probe)
    assert np.linalg.eigvalsh(pd).min()>0 and np.allclose(pd,pd.T);tests['near_pd_positive']=True
    independent=qp_fit(probe,rng.normal(size=12),True);tests['independent_qp_objective_parity']=True
    # Exact aggregate-frequency reconstruction is an algebra check with no external projection correction.
    errors=[]
    for group in range(len(groups)):
        target=np.zeros(len(groups));target[group]=1
        q=qp_fit(L.T@F,L.T@F[:,group]);errors.append(float(np.linalg.norm((L.T@F)@q-L.T@F[:,group])))
    tests['aggregate_projection_reconstruction_max_error']=max(errors)
    rows=[]
    for i,genotype in enumerate(calls):
        rows.append(fit(genotype,F,L,groups))
        if (i+1)%25==0:print(json.dumps(dict(public_evaluated=i+1,elapsed=round(time.monotonic()-start,1))),flush=True)
    print(json.dumps(dict(public_summary=summarize(rows,labels))),flush=True)
    synthetic=[];synthetic_labels=[]
    for group in range(len(groups)):
        for repeat in range(5):
            g=rng.binomial(2,F[:,group]).astype(np.int8)
            synthetic.append(fit(g,F,L,groups));synthetic_labels.append(groups[group])
    missing=[]
    for name in ['Ashkenazi','Middle East','Africa (North)','Finland','Pakistan']:
        index=groups.index(name)
        for repeat in range(4):
            g=rng.binomial(2,F[:,index]).astype(np.int8)
            result=fit(g,F,L,groups,omitted=index);result['omitted_source']=name
            missing.append(result)
    missing_summary={}
    for name in sorted(set(r['omitted_source'] for r in missing)):
        chosen=[r for r in missing if r['omitted_source']==name]
        missing_summary[name]=dict(cases=len(chosen),passes_correlation_gate=sum(r['correlation']>0.6 for r in chosen),mean_correlation=float(np.mean([r['correlation'] for r in chosen])),mean_projected_residual_norm=float(np.mean([r['projected_residual_norm'] for r in chosen])),dominant_group_counts=dict(collections.Counter(max(r['coefficients'],key=r['coefficients'].get) for r in chosen)))
    mixtures=[]
    for a,b in [('United Kingdom','Middle East'),('Ashkenazi','United Kingdom'),('Africa (North)','Middle East'),('Pakistan','Middle East')]:
        ia,ib=groups.index(a),groups.index(b)
        for proportion in [.25,.5,.75]:
            for rep in range(3):
                freq=proportion*F[:,ia]+(1-proportion)*F[:,ib];g=rng.binomial(2,freq).astype(np.int8)
                r=fit(g,F,L,groups);expected=collections.defaultdict(float);expected[merge_labels([a])[0]]+=proportion;expected[merge_labels([b])[0]]+=1-proportion
                err=sum(abs(v-expected.get(k,0)) for k,v in r['coefficients'].items())/2
                mixtures.append(dict(source_pair=[a,b],first_source_fraction=proportion,absolute_misallocated_fraction=err,correlation=r['correlation']))
    # Deterministic marker-mask sensitivity: independent random masks, not a named consumer chip.
    mask_results=[]
    for code in ['ASH','EGY','PAL','FRE','BAL']:
        genotype=calls[labels.index(code)];full=rows[labels.index(code)]
        runs=[fit(genotype,F,L,groups,indices=rng.random(len(genotype))<0.5) for _ in range(10)]
        keys=list(full['coefficients']);widths={k:100*(max(r['coefficients'][k] for r in runs)-min(r['coefficients'][k] for r in runs)) for k in keys}
        mask_results.append(dict(source_group=code,constructed_masks=10,mask_retention=0.5,maximum_component_range_percentage_points=max(widths.values()),component_ranges_percentage_points=widths))
    report=dict(status='executed_isolated_published_method',method='Prive2022 bigsnpr PCA-projection constrained mixture, with published correction, nearest-PD and author group merges',source_doi='10.6084/m9.figshare.16858534.v3',groups=groups,display_groups=list(dict.fromkeys(merge_labels(groups))),tests=tests,public_summary=summarize(rows,labels),synthetic_pure_reference=summarize(synthetic,synthetic_labels),missing_reference_challenge=missing_summary,synthetic_mixtures=dict(cases=len(mixtures),mean_misallocated_fraction=float(np.mean([r['absolute_misallocated_fraction'] for r in mixtures])),max_misallocated_fraction=max(r['absolute_misallocated_fraction'] for r in mixtures),cases_summary=mixtures),mask_sensitivity=mask_results,elapsed_seconds=round(time.monotonic()-start,2),privacy='Only aggregate summaries exported. Existing allowed public source calls retained in private staging. Synthetic calls are constructed independently from aggregate frequencies; no customer DNA used.',validation_boundary='The author primarily validates cohort-level ancestry summaries. This is an individual-level compatibility and constructed-data challenge, not calibrated consumer genealogy or ethnicity accuracy; missing-source correlation is explicitly tested.')
    (OUT/'composition-validation.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(dict(status=report['status'],public_individuals=len(calls),common_loci=len(F),tests=tests,missing_reference_challenge=missing_summary,synthetic_mixture_mean_error=report['synthetic_mixtures']['mean_misallocated_fraction'],elapsed_seconds=report['elapsed_seconds'])),flush=True)
if __name__=='__main__':main()
