"""Private public-reference diagnostic; emit aggregate results only."""
import argparse, collections, json
from pathlib import Path
import numpy as np

def validate(path):
    data=np.load(path,allow_pickle=False)
    return validate_data(data['dosage'],data['groups'])

def validate_data(doses,labels):
    doses=np.asarray(doses);labels=np.asarray(labels)
    if doses.ndim!=2 or labels.ndim!=1 or len(labels)!=len(doses) or doses.shape[1]==0:
        raise ValueError('Expected a nonempty sample-by-marker dosage matrix and one label per sample')
    if not np.isin(doses,[-1,0,1,2]).all():
        raise ValueError('Dosages must be -1 (missing), 0, 1 or 2')
    if np.any(np.sum(doses>=0,axis=1)==0):
        raise ValueError('Every diagnostic sample needs at least one observed marker')
    codes=sorted(set(labels))
    if len(codes)<2 or any(np.sum(labels==c)<2 for c in codes):
        raise ValueError('Leave-one-out comparison needs at least two groups and two samples per group')
    confusion={c:collections.Counter() for c in codes}; margins={c:[] for c in codes}; missing=[]
    counts={c:np.sum(doses[labels==c]>=0,axis=0)*2 for c in codes}
    sums={c:np.maximum(doses[labels==c],0).sum(axis=0) for c in codes}
    for i,label in enumerate(labels):
        d=doses[i]; valid=d>=0; losses={}
        for code in codes:
            an=counts[code]-(2*valid if code==label else 0)
            ac=sums[code]-(np.maximum(d,0) if code==label else 0)
            p=(ac+.5)/(an+1)
            losses[code]=float(np.mean(-(d[valid]*np.log(p[valid])+(2-d[valid])*np.log1p(-p[valid])+(d[valid]==1)*np.log(2))))
        order=sorted(codes,key=lambda c:(losses[c],c));confusion[label][order[0]]+=1
        margins[label].append(losses[order[1]]-losses[order[0]])
        missing.append(int(valid.sum()))
    return dict(method='Leave-one-individual-out HWE genotype negative log likelihood; Jeffreys allele-frequency smoothing (ALT copies + 0.5)/(observed alleles + 1), with donor removed before smoothing. Same common loci; natural log mean, smaller is better.',sample_count=len(labels),marker_count=doses.shape[1],minimum_callable_markers=min(missing),same_label_top_count=sum(confusion[c][c] for c in codes),by_group={c:dict(sample_count=int(np.sum(labels==c)),same_label_top_count=confusion[c][c],predictions=dict(confusion[c]),median_best_vs_next_loss_gap=float(np.median(margins[c]))) for c in codes},limitations='Internal same-source cohort diagnostic, not consumer accuracy, membership probabilities, independent cohort validation, or admixture. Loci are linked and cohorts small; HWE and representativeness assumptions may fail. Selection uses cohort call availability.')

if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('matrix',type=Path);p.add_argument('output',type=Path);a=p.parse_args()
    result=validate(a.matrix);a.output.write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))
