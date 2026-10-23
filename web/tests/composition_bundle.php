<?php
require __DIR__.'/../app/dna_composition.php';
$checks=0;
function cb_check($value,$message) { global $checks; if (!$value) { throw new RuntimeException($message); } $checks++; }
function cb_reject($call,$message) { try { $call(); } catch (RuntimeException $e) { cb_check(true,$message); return; } throw new RuntimeException($message); }
$legacy=zcomp_manifest_profile('94a2a30e47d94cb7e53fc47d02a4bfb6432ec3416f572427ad07bec114592b18');
$scoped=zcomp_manifest_profile('5330a197b6b0203cbb985ebfd50bf68ae1fbcd8f1dc190933329d27db33d2747');
cb_check($legacy['schema']==='bigsnpr-runtime-v1' && $legacy['builds']===['GRCh37','GRCh38'],'Original manifest retains both build requirements');
cb_check(in_array('runtime/bin/python',$legacy['required'],true) && in_array('weights38.npz',$legacy['required'],true),'Legacy runtime and 38 assets remain required');
cb_check($scoped['builds']===['GRCh37'] && count($scoped['required'])===8,'Scoped manifest requires all eight published files');
foreach (['',str_repeat('0',64),'94a2a30e47d94cb7e53fc47d02a4bfb6432ec3416f572427ad07bec114592b19',null] as $bad) { cb_reject(fn()=>zcomp_manifest_profile($bad),'Unknown manifest must fail closed'); }
cb_check(zcomp_build_suffix('GRCh37',$scoped['builds'])==='37','Scoped 37 build accepted');
cb_check(zcomp_build_suffix('GRCh38',$legacy['builds'])==='38','Legacy 38 build retained');
foreach (['GRCh38','GRCh36','37','grch37','',null] as $bad) { cb_reject(fn()=>zcomp_build_suffix($bad,$scoped['builds']),'Unsupported build must fail closed'); }
$fake=['_supported_builds'=>['GRCh37'],'_python_executable'=>'/opt/operator env/bin/python','_bundle_path'=>'/opt/reference pack'];
$cmd=zcomp_worker_command($fake,'/tmp/private input.tsv','/tmp/private output.json','GRCh37');
cb_check(array_slice($cmd,0,8)===['/usr/bin/timeout','--signal=TERM','--kill-after=2','30','/usr/bin/prlimit','--as=805306368','--cpu=25','--'],'Wall, memory and CPU controls retained');
cb_check($cmd[8]===$fake['_python_executable'] && $cmd[11]==='/tmp/private input.tsv' && end($cmd)==='37','Executable and paths remain distinct arguments');
cb_reject(fn()=>zcomp_worker_command($fake,'unused','unused','GRCh38'),'Command cannot broaden scoped build');
$temp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'zcomp-source-test-'.bin2hex(random_bytes(8));
if (!mkdir($temp,0700)) { throw new RuntimeException('Cannot create synthetic source fixture'); }
try {
    $file=$temp.'/fixture.txt'; file_put_contents($file,'synthetic source only');
    $expected=['bytes'=>filesize($file),'sha256'=>hash_file('sha256',$file)];
    cb_check(zcomp_source_file($temp,'fixture.txt',$expected,true)===$file,'Matching source file accepted');
    foreach (['../fixture.txt','x/../fixture.txt','/fixture.txt','x//fixture.txt','x\\fixture.txt','C:/fixture.txt','file://fixture.txt',"fixture.txt\0"] as $bad) { cb_reject(fn()=>zcomp_source_file($temp,$bad,$expected,true),'Noncanonical source path rejected'); }
    cb_reject(fn()=>zcomp_source_file($temp,'fixture.txt',['bytes'=>$expected['bytes']+1,'sha256'=>$expected['sha256']],true),'Wrong size rejected');
    cb_reject(fn()=>zcomp_source_file($temp,'fixture.txt',['bytes'=>$expected['bytes'],'sha256'=>str_repeat('0',64)],true),'Wrong hash rejected');
    cb_reject(fn()=>zcomp_source_file($temp,'fixture.txt',['bytes'=>(string)$expected['bytes'],'sha256'=>$expected['sha256']],true),'Malformed metadata rejected');
    file_put_contents($file,'tampered source only');
    cb_reject(fn()=>zcomp_source_file($temp,'fixture.txt',$expected,true),'Same-length changed bytes rejected without stale cache');
    file_put_contents($temp.'/manifest.json',json_encode(['schema_version'=>'zerothink-composition-reference-v1','files'=>[]]));
    cb_reject(fn()=>zcomp_verified_source($temp),'Matching schema with an unpinned manifest rejected');
} finally { foreach (['fixture.txt','manifest.json'] as $own) { if (is_file($temp.'/'.$own)) { unlink($temp.'/'.$own); } } rmdir($temp); }
if (isset($argv[1])) {
    $source=zcomp_verified_source($argv[1]);
    cb_check($source['profile']['name']==='public_grch37' && $source['supported_builds']===['GRCh37'],'Actual scoped manifest and all hashes verified');
    cb_check(array_keys($source['model']['marker_count_by_build'])===['GRCh37'],'Uninstalled historical 38 count withheld');
    cb_check($source['model']['marker_count_by_build']['GRCh37']===345907 && count($source['model']['components'])===18,'Actual model metadata retained');
    $previous=getenv('ZEROTHINK_COMPOSITION_BUNDLE'); $previousPython=getenv('ZEROTHINK_COMPOSITION_PYTHON');
    putenv('ZEROTHINK_COMPOSITION_BUNDLE='.$argv[1]); putenv('ZEROTHINK_COMPOSITION_PYTHON');
    try {
        $catalog=zl_dna_composition_catalog();
        cb_check($catalog['source_installed']===true && $catalog['available']===false && $catalog['status']==='local_runtime_unavailable','Missing explicit runtime is distinct from source installation');
        cb_check($catalog['supported_builds']===['GRCh37'] && !isset($catalog['marker_count_by_build']['GRCh38']),'Public catalogue advertises only installed build');
        cb_reject(fn()=>zcomp_bundle(),'No implicit runtime discovery or Windows control bypass');
    } finally { $previous===false?putenv('ZEROTHINK_COMPOSITION_BUNDLE'):putenv('ZEROTHINK_COMPOSITION_BUNDLE='.$previous); $previousPython===false?putenv('ZEROTHINK_COMPOSITION_PYTHON'):putenv('ZEROTHINK_COMPOSITION_PYTHON='.$previousPython); }
}
if (isset($argv[2])) {
    $text=file_get_contents($argv[2]); $profile=zcomp_manifest_profile(hash('sha256',$text)); $manifest=json_decode($text,true);
    cb_check($profile['name']==='legacy_runtime' && count($manifest['files'])===1477,'Original legacy manifest remains recognized');
    foreach ($profile['required'] as $name) { cb_check(isset($manifest['files'][$name]),'Legacy required path matches original manifest'); }
}
echo json_encode(['status'=>'passed','checks'=>$checks,'actual_scoped_bundle_checked'=>isset($argv[1]),'legacy_manifest_only_checked'=>isset($argv[2]),'worker_or_inference_executed'=>false]) . "\n";
