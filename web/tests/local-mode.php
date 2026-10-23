<?php
define('ZEROTHINK_LOCAL_LAB', true);
putenv('ZEROTHINK_OFFLOAD_URL=https://example.invalid');
putenv('ZEROTHINK_OFFLOAD_TOKEN=synthetic-only');
require dirname(__DIR__).'/app/zero_library.php';
function local_check($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
$config=zl_offload_config();$status=zl_offload_node_status();
local_check($config['configured']===false && $config['enabled']===false && $config['url']==='', 'Inherited remote configuration remains reachable');
local_check($status['configured']===false && $status['enabled']===false && $status['health']===null, 'Local mode probes remote health');
local_check(str_contains(zl_dna_public_report_text('reference panel not installed'), 'not installed'), 'Absent reference was disguised');
local_check(str_contains(zl_dna_public_report_text('Not installed'), 'Not installed'), 'Absent source status was disguised');
local_check(str_contains(zl_dna_public_report_text('pack_required'), 'not installed'), 'Required pack status was disguised');
echo "PASS: 5 local-mode offline and missing-pack assertions\n";
