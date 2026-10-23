"""Private published-reference composition worker; GPL-3.0-or-later.

Reference/method: Florian Prive (2022), doi:10.1093/bioinformatics/btac348.
near_pd adapts Matrix::nearPD, copyright Martin Maechler 2007-2019 and
Jens Oehlschlaegel 2007, GPL-2.0-or-later. No genotype logging or network calls.
"""
import json,os,sys
from pathlib import Path
import numpy as np
import quadprog

CORRECTION=np.array([1,1,1,1.008,1.021,1.034,1.052,1.074,1.099,1.123,1.15,1.195,1.256,1.321,1.382,1.443])
def near_pd(matrix):
    x=(matrix+matrix.T)/2;ds=np.zeros_like(x)
    for _ in range(100):
        y=x.copy();r=y-ds;d,q=np.linalg.eigh(r);keep=d>1e-6*d[-1]
        if not keep.any():raise ValueError('invalid covariance')
        x=(q[:,keep]*d[keep])@q[:,keep].T;ds=x-r
        if np.linalg.norm(y-x,np.inf)/np.linalg.norm(y,np.inf)<=1e-7:break
    else:raise ValueError('covariance did not converge')
    d,q=np.linalg.eigh(x);eps=1e-8*abs(d[-1])
    if d[0]<eps:
        old=np.diag(x).copy();x=(q*np.maximum(d,eps))@q.T
        scale=np.sqrt(np.maximum(eps,old)/np.diag(x));x=scale[:,None]*x*scale[None,:]
    return (x+x.T)/2
def solve(x,y):
    d=near_pd(x.T@x);linear=x.T@y;k=x.shape[1];scale=np.max(np.abs(d))
    q=quadprog.solve_qp(d/scale,linear/scale,np.column_stack([-np.ones(k),np.eye(k)]),np.r_[-1.,np.zeros(k)],meq=1)[0]
    if not np.isfinite(q).all() or q.min()<-1e-7 or abs(q.sum()-1)>1e-6:raise ValueError('invalid solution')
    q=np.clip(q,0,1)
    return q/q.sum()
def merge(values,groups,components):
    result=np.zeros(len(components));by_source={source:i for i,c in enumerate(components) for source in c['source_groups']}
    for source,value in zip(groups,values):result[by_source[str(source)]]+=value
    return result
def calculate(F,L,genotypes,chromosomes,groups,components):
    observed=genotypes>=0;g=genotypes[observed]/2;f=F[observed];l=L[observed];ch=chromosomes[observed]
    if len(g)<50000 or len(np.unique(ch))<15:return dict(available=False,status='insufficient_model_markers',matched_markers=len(g))
    centered=g-g.mean();den=np.linalg.norm(centered)
    fc=f-f.mean(axis=0);fd=np.linalg.norm(fc,axis=0)
    if den<=0 or (fd<=0).any():return dict(available=False,status='invalid_genotype_variance',matched_markers=len(g))
    correlations=(fc.T@centered)/(fd*den)
    if not np.isfinite(correlations).all() or correlations.mean() < -.2:return dict(available=False,status='allele_orientation_mismatch',matched_markers=len(g))
    x=l.T@f;y=(l.T@g)*CORRECTION;q=solve(x,y);pred=f@q
    pc=pred-pred.mean();cor=float(pc@centered/(np.linalg.norm(pc)*den))
    if not np.isfinite(cor) or cor<=.6:return dict(available=False,status='reference_alignment_insufficient',matched_markers=len(g))
    values=merge(q,groups,components);replicates=[]
    for chromosome in np.unique(ch):
        block=ch==chromosome;xx=x-l[block].T@f[block];yy=y-(l[block].T@g[block])*CORRECTION
        replicates.append(merge(solve(xx,yy),groups,components))
    replicates=np.array(replicates);low=replicates.min(axis=0);high=replicates.max(axis=0)
    result=[]
    for c,v,lo,hi in zip(components,values,low,high):
        result.append(dict(code=c['code'],label=c['label'],percent=float(100*v),sensitivity_low=float(100*lo),sensitivity_high=float(100*hi),small_component=bool(v<.05),reference_scope=c['reference_scope']))
    return dict(available=True,status='reference_model_proportions',components=result,matched_markers=len(g),chromosome_count=len(np.unique(ch)),quality=dict(correlation=cor,correlation_minimum=.6,correlation_role='alignment_check_not_ancestry_confidence',max_component_sensitivity_percentage_points=float(100*np.max(high-low))),sensitivity_method='leave_one_chromosome_out_not_confidence_interval')
def main():
    os.umask(0o077)
    if len(sys.argv)!=5 or sys.argv[4] not in ['37','38']:raise ValueError('arguments')
    bundle=Path(sys.argv[1]);source=Path(sys.argv[2]);output=Path(sys.argv[3])
    meta=json.loads((bundle/'model.json').read_text());asset=np.load(bundle/('weights'+sys.argv[4]+'.npz'),allow_pickle=False)
    F=asset['frequency'];L=asset['loading'];g=np.full(len(F),-1,dtype=np.int8)
    seen=set()
    with source.open() as f:
        for row in f:
            if len(row)>60:raise ValueError('invalid input')
            parts=row.strip().split('\t')
            if len(parts)!=2 or not all(s.isdigit() for s in parts):raise ValueError('invalid input')
            index,dose=map(int,parts)
            if index<0 or index>=len(g) or dose not in [0,1,2] or index in seen:raise ValueError('invalid input')
            seen.add(index);g[index]=dose
    report=calculate(F,L,g,asset['chromosome'],asset['groups'],meta['components'])
    with output.open('x') as f:json.dump(report,f,allow_nan=False,separators=(',',':'))
if __name__=='__main__':
    try:main()
    except Exception:sys.exit(2)
