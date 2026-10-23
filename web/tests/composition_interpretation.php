<?php
require_once __DIR__.'/../app/dna_composition_interpretation.php';
$checks = 0;
function ci_check($ok, $label) { global $checks; $checks++; if (!$ok) { throw new RuntimeException($label); } }
function ci_fixture($target = 0.0) {
    $codes = ['AFR_W','AFR_S','AFR_E','AFR_N','MENA','ASH','EUR_IT','EUR_E','EUR_FIN','EUR_NW','EUR_SW','AMR','SAS_SL','SAS_PK','SAS_BD','EAS','EAS_JP','EAS_PH'];
    $r = ['available'=>true,'status'=>'reference_model_proportions','components'=>[]];
    foreach ($codes as $code) { $r['components'][] = ['code'=>$code,'label'=>$code==='ASH'?'Ashkenazi':$code,'percent'=>$code==='ASH'?$target:($code==='SAS_PK'?100-$target:0.0)]; }
    return $r;
}
foreach ([[0,'not_assigned'],[-0.0,'not_assigned'],[1e-16,'numerical_residue'],[1e-6,'numerical_residue'],[1.000001e-6,'small_model_component'],[0.01,'small_model_component'],[3.2,'small_model_component'],[4.999,'small_model_component'],[5,'model_component'],[100,'model_component']] as [$p,$expected]) { ci_check(zci_component_status($p)===$expected,'Exact threshold classification '.$expected); }
foreach ([null,true,false,'0','3.2',NAN,INF,-INF,-0.1,100.1,[],new stdClass()] as $bad) { ci_check(zci_component_status($bad)==='invalid','Invalid value rejected'); }
foreach ([0.0,1e-16,1e-6,0.0000011,0.03,3.2,5.0,100.0] as $p) {
    $r=ci_fixture($p); $before=serialize($r); $out=zl_dna_composition_interpretation($r);
    ci_check($out['available'] && count($out['components'])===18,'Complete model interpreted');
    ci_check($out['components'][5]['model_percent']===$p && serialize($r)===$before,'Exact weight preserved and source immutable');
    ci_check($out['components'][5]['can_rule_out_ancestry']===false && $out['components'][5]['confirms_ancestry']===false,'No presence/absence claim');
}
$r=ci_fixture(0); $out=zl_dna_composition_interpretation($r);
ci_check(strpos($out['components'][5]['meaning'],'does not rule out')!==false,'Zero is non-exclusion');
ci_check(strpos(zl_dna_composition_interpretation(ci_fixture(1e-16))['components'][5]['meaning'],'not a detected ancestry')!==false,'Numerical residue is not detection');
ci_check(strpos(zl_dna_composition_interpretation(ci_fixture(3.2))['components'][5]['meaning'],'not a confirmed')!==false,'Small positive not confirmed ancestry');
ci_check(strpos($out['boundary'],'not biological accuracy')!==false,'Implementation parity not biological validation');
$badCases=[];
$x=$r;$x['available']=false;$badCases[]=$x;
$x=$r;$x['available']='true';$badCases[]=$x;
$x=$r;$x['chart_eligible']=false;$badCases[]=$x;
$x=$r;$x['status']='insufficient_model_markers';$badCases[]=$x;
$x=$r;array_pop($x['components']);$badCases[]=$x;
$x=$r;$x['components'][0]['percent']=1;$badCases[]=$x;
$x=$r;$x['components'][0]['percent']=NAN;$badCases[]=$x;
$x=$r;$x['components'][0]['percent']='0';$badCases[]=$x;
$x=$r;$x['components'][0]['code']=$x['components'][1]['code'];$badCases[]=$x;
$x=$r;$x['components'][0]['code']='../invalid';$badCases[]=$x;
$x=$r;$x['components'][0]['label']="bad\nlabel";$badCases[]=$x;
$x=$r;$x['components'][0]=null;$badCases[]=$x;
foreach ($badCases as $bad) { $out=zl_dna_composition_interpretation($bad); ci_check(!$out['available'] && $out['components']===[],'Unavailable or malformed model gives no partial interpretation'); }
$r=ci_fixture(3.2);$r['raw_genotypes']=['private'];$r['components'][5]['rsid']='rs_private';$r['components'][5]['allele_count']=2;
$out=zl_dna_composition_interpretation($r);$encoded=json_encode($out);
ci_check(strpos($encoded,'private')===false && strpos($encoded,'allele_count')===false && strpos($encoded,'raw_genotypes')===false,'Output whitelist drops genotype detail');
$md=zl_dna_composition_interpretation_markdown($out);
ci_check(strpos($md,'What the model weights mean')!==false && strpos($md,'not confidence intervals')!==false,'Export includes interpretation and range boundary');
ci_check(strpos($md,'Neither threshold is an ancestry detection limit')!==false,'Threshold roles explicit');
ci_check(strpos($md,'Ashkenazi')!==false && strpos($md,'not a confirmed small ancestry')!==false,'Named small component has source-consistent caution');
ci_check(zl_dna_composition_interpretation_markdown(zl_dna_composition_interpretation(['available'=>false]))==='','No invented export for unavailable model');
$r=ci_fixture();$r['components'][0]['label']='A | <script>'; $md=zl_dna_composition_interpretation_markdown(zl_dna_composition_interpretation($r));
ci_check(strpos($md,'A \\| &lt;script&gt;')!==false,'Export escapes table and HTML syntax');
echo json_encode(['status'=>'passed','checks'=>$checks,'scope'=>'constructed model outputs only; no raw DNA; weights unmodified'])."\n";
