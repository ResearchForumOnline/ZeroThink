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
$fixture = __DIR__.'/synthetic-verification-v3-not-personal.txt';
$report = zl_dna_full_report_path($fixture, 'public-synthetic-fixture.txt');
$absent = array_filter($report['data_pack_status'], fn($pack) => empty($pack['installed']));
local_check(count($absent) >= 5, 'Missing-pack fixture unexpectedly has installed reference packs');
foreach ($report['source_versions'] as $source) {
    local_check(!preg_match('/expanding|being expanded|queued/i', $source['status']), 'A source status claims active expansion without evidence');
}
$graph = zl_dna_lineage_public_graph_status();
local_check($graph['installed'] === false && $graph['pack_band'] === 'not_installed' && str_contains($graph['pack_note'], 'not installed'), 'Missing lineage graph status is inaccurate');
$markdown = zl_dna_public_report_text(zl_dna_full_markdown($report));
foreach ($absent as $pack) {
    $name = $pack['display_name'] ?? $pack['label'] ?? 'Source pack';
    local_check(str_contains($markdown, '- '.$name.': not installed - '), 'Generated Markdown disguises an absent pack');
}
local_check(str_contains($markdown, 'Legacy individual-sample source pack: not installed; comparison unavailable'), 'Legacy individual-sample Markdown disguises an unavailable comparison');
local_check(!preg_match('/expanding|being expanded|queued for expansion/i', $markdown), 'Generated synthetic report claims active source expansion');
$installedReport = $report;
$installedReport['data_pack_status'] = [['display_name'=>'Synthetic installed pack', 'installed'=>true, 'label'=>'test only']];
$installedMarkdown = zl_dna_full_markdown($installedReport);
local_check(str_contains($installedMarkdown, 'Synthetic installed pack: active - test only'), 'Installed status was changed to unavailable');
echo "PASS: local-mode, actual synthetic report, absent-pack status and installed-status assertions\n";
echo "EXPECTED: optional reference/model packs unavailable in the source-only checkout; no biological interpretation asserted.\n";
