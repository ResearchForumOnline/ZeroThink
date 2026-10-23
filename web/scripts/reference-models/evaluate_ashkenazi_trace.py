"""Constructed trace-mixture evaluation only; never reads individual genotypes.

Optional mask JSON: {"build":"GRCh37","ordinals":[0,1,...]}; or a GRCh37 NPY
array of ordinals. Order is the installed weights/index order. No genotypes.
"""
import argparse, hashlib, importlib.util, json, os, time
from pathlib import Path
import numpy as np

os.umask(0o077)
PIN='94a2a30e47d94cb7e53fc47d02a4bfb6432ec3416f572427ad07bec114592b18'
WORKER='d859f5603c921a15eabcad4cecd5449460d55b02691bf556f5857252be5b079c'

def main():
    p=argparse.ArgumentParser();p.add_argument('--bundle',required=True);p.add_argument('--output',required=True);p.add_argument('--mask');p.add_argument('--seeds',type=int,default=20);p.add_argument('--mixed50-only',action='store_true');a=p.parse_args()
    assert 5<=a.seeds<=100
    bundle=Path(a.bundle);output=Path(a.output);assert not output.exists()
    assert hashlib.sha256((bundle/'manifest.json').read_bytes()).hexdigest()==PIN
    assert hashlib.sha256((bundle/'worker.py').read_bytes()).hexdigest()==WORKER
    manifest=json.loads((bundle/'manifest.json').read_text());build='37';mask=None
    if a.mask:
        if Path(a.mask).suffix=='.npy':mask={'build':'GRCh37','ordinals':np.load(a.mask,allow_pickle=False)}
        else:mask=json.loads(Path(a.mask).read_text())
        assert set(mask)<=set(['build','ordinals','source','description'])
        assert mask['build'] in ['GRCh37','GRCh38'];build=mask['build'][-2:]
    for name in ['model.json','weights'+build+'.npz']:
        assert hashlib.sha256((bundle/name).read_bytes()).hexdigest()==manifest['files'][name]['sha256']
    spec=importlib.util.spec_from_file_location('installed_worker',bundle/'worker.py');w=importlib.util.module_from_spec(spec);spec.loader.exec_module(w)
    m=json.loads((bundle/'model.json').read_text());asset=np.load(bundle/('weights'+build+'.npz'),allow_pickle=False)
    F=asset['frequency'];L=asset['loading'];chrom=asset['chromosome'];groups=list(asset['groups']);full_count=len(F)
    if mask:
        selected=np.asarray(mask['ordinals']);assert selected.ndim==1 and selected.dtype.kind in 'iu'
        assert len(np.unique(selected))==len(selected) and selected.min()>=0 and selected.max()<len(F)
        selected=np.sort(selected);F=F[selected];L=L[selected];chrom=chrom[selected]
    if len(F)<50000 or len(np.unique(chrom))<15:raise ValueError('Mask below installed release coverage floor')
    X=L.T@F;fc=F-F.mean(axis=0);fd=np.linalg.norm(fc,axis=0)
    ash=groups.index('Ashkenazi');out_ash=[c['code'] for c in m['components']].index('ASH')
    northwest=(2000*F[:,groups.index('United Kingdom')]+2000*F[:,groups.index('Ireland')]+416*F[:,groups.index('Scandinavia')])/4416
    pakistan=F[:,groups.index('Pakistan')]
    backgrounds={'Pakistan':pakistan,'Northwest_Europe_source_mix':northwest,'Pakistan75_NorthwestEurope25':.75*pakistan+.25*northwest}
    levels=[0,1,3,5,10]
    if a.mixed50_only:backgrounds={'Pakistan50_NorthwestEurope50':.5*pakistan+.5*northwest};levels=[0,3]
    start=time.monotonic();rows=[];parity=[];centroids=[]
    def cached(g):
        centered=g/2-(g/2).mean();den=np.linalg.norm(centered)
        corr_all=(fc.T@centered)/(fd*den)
        assert np.isfinite(corr_all).all() and corr_all.mean()>=-.2
        q=w.solve(X,(L.T@(g/2))*w.CORRECTION);pred=F@q;pc=pred-pred.mean();cor=float(pc@centered/(np.linalg.norm(pc)*den))
        merged=w.merge(q,groups,m['components']);return merged,cor
    def summarize(items):
        values=np.array([r['estimate_percent'] for r in items if r['available']]);err=values-items[0]['true_percent']
        return dict(n=len(items),available=len(values),mean=float(values.mean()),median=float(np.median(values)),min=float(values.min()),max=float(values.max()),p05=float(np.quantile(values,.05)),p95=float(np.quantile(values,.95)),mean_bias_percentage_points=float(err.mean()),mean_absolute_error_percentage_points=float(np.abs(err).mean()),nonzero_over_0_1_count=int((values>=.1).sum()),at_least_1_count=int((values>=1).sum()),at_least_3_count=int((values>=3).sum()),at_least_5_count=int((values>=5).sum()))
    for bi,(background,bg) in enumerate(backgrounds.items()):
        for percent in levels:
            p_ash=percent/100;frequency=(1-p_ash)*bg+p_ash*F[:,ash]
            centroid,_=cached(2*frequency)
            centroids.append(dict(background=background,true_percent=percent,centroid_estimate_percent=float(100*centroid[out_ash]),boundary='Algebra-only mean-frequency diagnostic with fixed published correction; not an individual genotype test'))
            for seed in range(a.seeds):
                rng=np.random.default_rng(2026090500+(bi+int(a.mixed50_only)*3)*1000+seed)
                g=(rng.random((2,len(F)))<frequency).sum(axis=0).astype(np.int8)
                q,cor=cached(g);record=dict(background=background,true_percent=percent,seed=seed,estimate_percent=float(100*q[out_ash]),correlation=cor,available=bool(cor>.6))
                if seed<2:
                    exact=w.calculate(F,L,g,chrom,groups,m['components']);assert exact['available']==record['available']
                    if exact['available']:
                        delta=max(abs(c['percent']/100-q[i]) for i,c in enumerate(exact['components']));assert delta<1e-10
                        ac=exact['components'][out_ash];record['omission_low']=ac['sensitivity_low'];record['omission_high']=ac['sensitivity_high'];parity.append(delta)
                rows.append(record)
            print(json.dumps(dict(mask='provided' if mask else 'full',background=background,true_percent=percent,summary=summarize(rows[-a.seeds:]),elapsed=round(time.monotonic()-start,2))),flush=True)
    positive=[]
    for seed in range(0 if a.mixed50_only else a.seeds):
        rng=np.random.default_rng(2026099500+seed);g=rng.binomial(2,F[:,ash]).astype(np.int8);q,cor=cached(g)
        positive.append(dict(true_percent=100,estimate_percent=float(100*q[out_ash]),available=bool(cor>.6)))
    aggregate=[]
    for background in backgrounds:
        for percent in levels:
            items=[r for r in rows if r['background']==background and r['true_percent']==percent]
            aggregate.append(dict(background=background,true_percent=percent,**summarize(items)))
    paired=[]
    for background in backgrounds:
        zero={r['seed']:r['estimate_percent'] for r in rows if r['background']==background and r['true_percent']==0}
        for percent in [n for n in levels if n>0]:
            changes=[r['estimate_percent']-zero[r['seed']] for r in rows if r['background']==background and r['true_percent']==percent]
            paired.append(dict(background=background,true_percent=percent,paired_mean_change=float(np.mean(changes)),paired_min_change=min(changes),paired_max_change=max(changes)))
    report=dict(status='constructed_trace_validation',source_manifest_sha256=PIN,worker_sha256=WORKER,build='GRCh'+build,full_index_markers=full_count,observed_markers=len(F),chromosomes=len(np.unique(chrom)),mask_sha256=hashlib.sha256(Path(a.mask).read_bytes()).hexdigest() if mask else None,seeds=a.seeds,aggregate=aggregate,paired_response=paired,positive_control=summarize(positive) if positive else None,exact_worker_parity_tests=len(parity),exact_worker_max_coefficient_delta=max(parity),projection_dimensions=list(X.shape),projection_rank=int(np.linalg.matrix_rank(X)),centroid_diagnostics=centroids,constructed_only_rows=rows,elapsed_seconds=round(time.monotonic()-start,3),limitations=['Only constructed independently drawn alleles; no natural linkage, real ancestry tracts or reference-misspecification noise.','Same published reference frequencies generate and fit these controls; this is not independent consumer accuracy calibration.','Paired seeds reduce Monte Carlo differences across trace levels but cannot establish a person-specific detection probability.','Omission ranges are sensitivity checks, not confidence intervals.','No personal genotype or participant raw data is read, exported or logged.'])
    output.parent.mkdir(parents=True,exist_ok=True,mode=0o700);output.write_text(json.dumps(report,indent=2,allow_nan=False)+'\n')
    print(json.dumps(dict(status='complete',markers=len(F),tests=len(rows)+len(positive),parity=len(parity),elapsed=report['elapsed_seconds'],output=str(output))),flush=True)

if __name__=='__main__':main()
