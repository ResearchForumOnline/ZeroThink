<?php
require_once __DIR__.'/dna_composition_interpretation.php';
// Local published-reference model proportions. Genotypes never leave the private runtime.
function zcomp_boundary() {
    return 'These are reference-model ancestry proportions, not verified country percentages, nationality, religion or community membership. Missing reference groups can be redistributed among those present. Britain, Ireland and Scandinavia share Northwest Europe; England, Scotland and Wales are not separately modeled. Small components and neighboring groups can change with marker coverage. Chromosome-omission ranges show sensitivity, not confidence intervals. Correlation checks allele alignment; it is not ancestry confidence.';
}
function zcomp_bundle_path() {
    $path = trim((string) getenv('ZEROTHINK_COMPOSITION_BUNDLE'));
    return rtrim($path !== '' ? $path : '/mnt/data/zerothink/dna-engines/bigsnpr-16858534-v3', '/\\');
}
function zcomp_bundle() {
    static $cache = [];
    $path = zcomp_bundle_path(); if (isset($cache[$path])) { return $cache[$path]; }
    $manifest = $path.'/manifest.json';
    if (!is_readable($manifest) || hash_file('sha256', $manifest) !== '94a2a30e47d94cb7e53fc47d02a4bfb6432ec3416f572427ad07bec114592b18') { throw new RuntimeException('Pinned composition source unavailable'); }
    $data = json_decode(file_get_contents($manifest), true);
    if (!is_array($data) || ($data['schema_version'] ?? '') !== 'bigsnpr-runtime-v1') { throw new RuntimeException('Composition manifest unavailable'); }
    foreach ($data['files'] as $relative => $expected) {
        if (strpos($relative,'..') !== false || substr($relative,0,1) === '/') { throw new RuntimeException('Invalid composition source'); }
        $file = $path.'/'.$relative;
        if (!is_readable($file) || filesize($file) !== $expected['bytes'] || hash_file('sha256',$file) !== $expected['sha256']) { throw new RuntimeException('Composition source mismatch'); }
    }
    if (!is_executable($path.'/runtime/bin/python') || !is_executable('/usr/bin/timeout') || !is_executable('/usr/bin/prlimit') || !function_exists('proc_open') || !class_exists('SQLite3')) { throw new RuntimeException('Local composition runtime unavailable'); }
    $model = json_decode(file_get_contents($path.'/model.json'),true);
    if (($model['schema_version'] ?? '') !== 'bigsnpr-composition-v1' || count($model['components'] ?? []) !== 18) { throw new RuntimeException('Composition model unavailable'); }
    return $cache[$path] = $model;
}
function zl_dna_composition_catalog() {
    $out = ['available'=>false,'status'=>'model_unavailable','source_name'=>'bigsnpr / Privé 2022','source_url'=>'https://doi.org/10.1093/bioinformatics/btac348','source_version'=>'Figshare16858534.v3 / bigsnpr4bc7f817','minimum_markers'=>50000,'minimum_chromosomes'=>15,'boundary'=>zcomp_boundary()];
    try { $m=zcomp_bundle(); return array_merge($out,['available'=>true,'status'=>'published_model_installed','reference_groups'=>$m['components'],'marker_count_by_build'=>$m['marker_count_by_build'],'data_license'=>'CC BY 4.0','software_license'=>'GPL-3.0-or-later']); }
    catch (Throwable $e) { return $out; }
}
function zcomp_decode_output($text,$expected_count,$expected_chromosomes,$model) {
    $r=json_decode($text,true);
    if (!is_array($r) || !is_bool($r['available'] ?? null) || ($r['matched_markers'] ?? -1) !== $expected_count) { throw new RuntimeException('Invalid model result'); }
    if (!$r['available']) {
        if (!in_array($r['status'] ?? '', ['insufficient_model_markers','invalid_genotype_variance','allele_orientation_mismatch','reference_alignment_insufficient'],true)) { throw new RuntimeException('Invalid model status'); }
        return ['available'=>false,'status'=>$r['status'],'matched_markers'=>$expected_count];
    }
    if (($r['status'] ?? '') !== 'reference_model_proportions' || ($r['chromosome_count'] ?? 0) !== $expected_chromosomes || count($r['components'] ?? []) !== 18 || ($r['sensitivity_method'] ?? '') !== 'leave_one_chromosome_out_not_confidence_interval') { throw new RuntimeException('Incomplete model result'); }
    $sum=0.; $safe=[];
    foreach ($model['components'] as $i=>$source) {
        $c=$r['components'][$i] ?? [];
        if (($c['code'] ?? '') !== $source['code'] || ($c['label'] ?? '') !== $source['label']) { throw new RuntimeException('Unknown model component'); }
        foreach (['percent','sensitivity_low','sensitivity_high'] as $field) { if (!is_numeric($c[$field] ?? null) || !is_finite((float)$c[$field]) || $c[$field]<0 || $c[$field]>100) { throw new RuntimeException('Invalid model number'); } }
        if ($c['sensitivity_low'] > $c['sensitivity_high']) { throw new RuntimeException('Invalid model sensitivity'); }
        $sum+=(float)$c['percent'];
        $safe[]=['code'=>$source['code'],'label'=>$source['label'],'percent'=>(float)$c['percent'],'sensitivity_low'=>(float)$c['sensitivity_low'],'sensitivity_high'=>(float)$c['sensitivity_high'],'small_component'=>(float)$c['percent']<5.,'reference_scope'=>$source['reference_scope']];
    }
    if (abs($sum-100.)>0.000001) { throw new RuntimeException('Model proportions do not sum to one'); }
    $q=$r['quality'] ?? []; $cor=$q['correlation'] ?? null; $range=$q['max_component_sensitivity_percentage_points'] ?? null;
    if (!is_numeric($cor) || !is_finite((float)$cor) || $cor<=.6 || $cor>1 || !is_numeric($range) || !is_finite((float)$range) || $range<0 || $range>100) { throw new RuntimeException('Invalid model quality'); }
    return ['available'=>true,'status'=>'reference_model_proportions','components'=>$safe,'matched_markers'=>$expected_count,'chromosome_count'=>$expected_chromosomes,'quality'=>['correlation'=>(float)$cor,'correlation_minimum'=>.6,'correlation_role'=>'alignment_check_not_ancestry_confidence','max_component_sensitivity_percentage_points'=>(float)$range],'sensitivity_method'=>'leave_one_chromosome_out_not_confidence_interval'];
}
function zl_dna_composition_report_path($path,$name,$summary,$classification=null) {
    $catalog=zl_dna_composition_catalog();
    $out=array_merge($catalog,['installed'=>$catalog['available'],'available'=>false,'components'=>[],'matched_markers'=>0,'chromosome_count'=>0,'headline'=>'Reference-model ancestry composition','plain'=>'The published composition model is not currently available.','method'=>'Published allele-frequency and PCA-loading projection with the author’s fixed shrinkage correction, a constrained mixture fit and the required merge into 18 reference groups. No imputation or inferred strand flips.','confidence_status'=>'not_consumer_calibrated']);
    if (!$catalog['available']) { return $out; }
    $gate=zlt_file_gate($summary);
    if (empty($gate['ready']) || !in_array($gate['assembly'] ?? '',['GRCh37','GRCh38'],true)) { $out['status']='needs_file_validation'; $out['plain']='Confirm an unambiguous genome build and forward allele orientation before estimating reference-model proportions.'; return $out; }
    if (is_array($classification) && ($classification['available'] ?? false) === true && ($classification['broad']['code'] ?? '') === 'OCN') { $out['status']='reference_region_unsupported'; $out['plain']='The broad ancestry model indicates Oceania, which this composition reference does not represent. A percentage breakdown is withheld.'; return $out; }
    $dir=null; $proc=null; $pipes=[]; $old_umask=null;
    try {
        $bundle=zcomp_bundle_path(); $model=zcomp_bundle(); $build=$gate['assembly']==='GRCh37'?'37':'38';
        $input=zlg_collect_model_calls($path,$gate['assembly'],$bundle.'/marker_index'.$build.'.sqlite',zl_dna_max_raw_rows());
        $out['diagnostics']=$input['diagnostics']; $out['matched_markers']=$input['diagnostics']['matched_markers']; $out['chromosome_count']=$input['diagnostics']['chromosome_count'];
        if ($input['diagnostics']['input_incomplete'] || !empty($summary['truncated'])) { $out['status']='input_incomplete'; $out['plain']='The supported scan limit was reached. No partial composition estimate is presented.'; return $out; }
        if ($out['matched_markers']<50000 || $out['chromosome_count']<15) { $out['status']='insufficient_model_markers'; $out['plain']='This file provides '.number_format($out['matched_markers']).' directly called model markers. This release requires at least 50,000 across 15 autosomes. That coverage rule is not an accuracy percentage.'; return $out; }
        $root=zl_storage_root().'/dna-composition-tmp'; if (!is_dir($root) && !mkdir($root,0700,true)) { throw new RuntimeException('Private workspace unavailable'); }
        $dir=$root.'/request-'.bin2hex(random_bytes(16)); if (!mkdir($dir,0700)) { $dir=null; throw new RuntimeException('Private workspace unavailable'); }
        $old_umask=umask(0077); $infile=$dir.'/input.tsv'; $output=$dir.'/output.json'; $handle=fopen($infile,'x'); if (!$handle) { throw new RuntimeException('Private input unavailable'); }
        try {
            $ordinal=0;
            foreach ($input['definitions'] as $key=>$definition) {
                if (isset($input['calls'][$key])) { $parts=explode("\t",$definition); $dose=substr_count($input['calls'][$key],$parts[2]); if (fwrite($handle,$ordinal."\t".$dose."\n")===false) { throw new RuntimeException('Private input incomplete'); } }
                $ordinal++;
            }
        } finally { fclose($handle); }
        unset($input);
        $cmd=['/usr/bin/timeout','--signal=TERM','--kill-after=2','30','/usr/bin/prlimit','--as=805306368','--cpu=25','--',$bundle.'/runtime/bin/python',$bundle.'/worker.py',$bundle,$infile,$output,$build];
        $env=['PATH'=>'/usr/bin:/bin','LANG'=>'C','LC_ALL'=>'C','OPENBLAS_NUM_THREADS'=>'1','OMP_NUM_THREADS'=>'1','MKL_NUM_THREADS'=>'1','PYTHONDONTWRITEBYTECODE'=>'1','PYTHONNOUSERSITE'=>'1'];
        $proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$dir,$env,['bypass_shell'=>true]);
        if (!is_resource($proc)) { throw new RuntimeException('Local composition process unavailable'); }
        fclose($pipes[0]); unset($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false); $start=microtime(true); $bytes=0; $exit_code=null;
        do {
            foreach ($pipes as $pipe) { $bytes+=strlen((string)stream_get_contents($pipe)); }
            $state=proc_get_status($proc); if (!$state['running']) { $exit_code=$state['exitcode']; break; }
            if (microtime(true)-$start>35 || $bytes>131072) { proc_terminate($proc,9); throw new RuntimeException('Composition resource limit'); }
            usleep(10000);
        } while (true);
        foreach ($pipes as $pipe) { fclose($pipe); } $pipes=[]; proc_close($proc); $proc=null;
        if ($exit_code!==0 || !is_file($output) || filesize($output)>32768) { throw new RuntimeException('Composition process incomplete'); }
        $result=zcomp_decode_output(file_get_contents($output),$out['matched_markers'],$out['chromosome_count'],$model);
        $out=array_merge($out,$result);
        if (!$out['available']) { $out['plain']='The directly called markers did not pass the published model’s allele-alignment or variance checks. No percentage estimate is presented.'; return $out; }
        $leaders=$out['components']; usort($leaders,function($a,$b) { return $b['percent'] <=> $a['percent']; });
        $headline=[]; foreach ($leaders as $leader) { if ($leader['percent']<1. || count($headline)>=3) { continue; } $headline[]=$leader['label'].' '.number_format($leader['percent'],1).'%'; }
        if ($headline) { $out['headline']=implode(' · ',$headline); }
        $out['plain']='These percentages describe the combination of published reference groups that fits the observed markers. They are model estimates, not proof of ancestry from individual countries. A zero estimate does not rule out a small ancestry component, and a small positive estimate does not confirm one. Components below 5% and neighboring groups need particular caution; the reported ranges show sensitivity to omitting each chromosome.';
        $out['interpretation']=zl_dna_composition_interpretation($out);
        return $out;
    } catch (Throwable $e) { $out['available']=false; $out['status']='model_processing_incomplete'; $out['components']=[]; $out['plain']='The local composition model did not complete its source, input or output checks. No partial percentages are presented.'; return $out; }
    finally {
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
        if (is_resource($proc)) { proc_terminate($proc,9); proc_close($proc); }
        if ($dir!==null) { foreach (['input.tsv','output.json'] as $own) { $file=$dir.'/'.$own; if (is_file($file) && !is_link($file)) { unlink($file); } } if (is_dir($dir)) { rmdir($dir); } }
        if ($old_umask!==null) { umask($old_umask); }
    }
}
function zl_dna_composition_agent_context($report) {
    $components=[];
    if (!empty($report['available'])) { foreach ((array)($report['components']??[]) as $c) { $components[]=['label'=>(string)$c['label'],'reference_model_percent'=>(float)$c['percent'],'small_component'=>!empty($c['small_component']),'chromosome_sensitivity_range'=>[(float)$c['sensitivity_low'],(float)$c['sensitivity_high']],'reference_scope'=>(string)$c['reference_scope']]; } }
    return ['available'=>!empty($report['available']),'status'=>(string)($report['status']??'unavailable'),'source_name'=>'bigsnpr / Privé 2022','source_version'=>(string)($report['source_version']??''),'components'=>$components,'matched_markers'=>(int)($report['matched_markers']??0),'sensitivity_method'=>'chromosome_omission_not_confidence_interval','interpretation_boundary'=>'A zero model weight cannot rule out ancestry, especially a small contribution. A small positive weight does not confirm one. Numerical implementation checks do not establish biological accuracy.','boundary'=>zcomp_boundary()];
}
function zl_dna_composition_markdown($report) {
    $lines=['## Reference-model ancestry composition','','**'.($report['headline']??'Composition unavailable').'**','',(string)($report['plain']??''),''];
    if (!empty($report['available'])) {
        $lines[]='| Reference group | Model proportion | Chromosome-omission range |'; $lines[]='|---|---:|---:|';
        foreach ((array)$report['components'] as $c) { $p=(float)$c['percent']; $shown=$p<=.000001?'0':($p<.1?'<0.1':($p>=99.95&&$p<100?'>99.9':number_format($p,1))); $lines[]='| '.$c['label'].' | '.$shown.'%'.($p>.000001&&!empty($c['small_component'])?' (small component)':'').' | '.number_format((float)$c['sensitivity_low'],1).'–'.number_format((float)$c['sensitivity_high'],1).'% |'; }
        $lines[]=''; $lines[]='Ranges show sensitivity to chromosome omission, not confidence intervals.';
        $lines[]=''; $lines[]=zl_dna_composition_interpretation_markdown($report['interpretation'] ?? zl_dna_composition_interpretation($report));
    }
    $lines[]=''; $lines[]='Directly called model markers: '.number_format((int)($report['matched_markers']??0)).'.'; $lines[]=''; $lines[]=zcomp_boundary(); $lines[]=''; $lines[]='Source: Privé (2022), '.$report['source_version'].' — https://doi.org/10.1093/bioinformatics/btac348';
    return implode("\n",$lines);
}
