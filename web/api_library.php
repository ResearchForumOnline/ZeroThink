<?php
ob_start();
ini_set('display_errors', '0');
@ini_set('memory_limit', '1024M');
@ini_set('max_execution_time', '600');
@ini_set('max_input_time', '600');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/zero_library.php';

ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (!defined('ZL_DNA_MAX_RAW_UPLOAD_BYTES')) {
    define('ZL_DNA_MAX_RAW_UPLOAD_BYTES', 30 * 1024 * 1024);
}
if (!defined('ZL_DNA_MAX_TOTAL_UPLOAD_BYTES')) {
    define('ZL_DNA_MAX_TOTAL_UPLOAD_BYTES', 160 * 1024 * 1024);
}

class ZlApiPublicException extends RuntimeException {}

function zl_api_reply($payload, $status = 200) {
    if (ob_get_length()) {
        @ob_clean();
    }
    http_response_code((int) $status);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        $json = json_encode([
            'status' => 'error',
            'message' => 'Could not encode the server response as JSON.',
            'json_error' => json_last_error_msg(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    echo $json;
    exit;
}

function zl_api_bytes_label($bytes) {
    $bytes = max(0, (int) $bytes);
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error || !in_array((int) ($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error while generating the DNA report. Try again or contact support if it continues.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

function zl_api_upload_preflight() {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
    if ($method !== 'POST') {
        return;
    }
    $content_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($content_length > ZL_DNA_MAX_TOTAL_UPLOAD_BYTES) {
        zl_api_reply([
            'status' => 'error',
            'message' => 'The upload is too large for this DNA report. Use raw DNA files up to ' . zl_api_bytes_label(ZL_DNA_MAX_RAW_UPLOAD_BYTES) . ' each, with a total request under ' . zl_api_bytes_label(ZL_DNA_MAX_TOTAL_UPLOAD_BYTES) . '.',
        ], 413);
    }
    $content_type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($content_length > 0 && strpos($content_type, 'multipart/form-data') !== false && empty($_POST) && empty($_FILES)) {
        zl_api_reply([
            'status' => 'error',
            'message' => 'The upload reached ZeroThink empty. The browser request was larger than a server/PHP body limit, so the raw DNA file was not processed. Try one raw file at a time, keep each file under ' . zl_api_bytes_label(ZL_DNA_MAX_RAW_UPLOAD_BYTES) . ', and retry.',
        ], 413);
    }
}

function zl_api_require_csrf($token, $message = 'Your secure session expired. Refresh this page and try again.') {
    if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($token)) {
        zl_api_reply(['status' => 'error', 'message' => $message], 403);
    }
}

function zl_api_require_dna_csrf($token) {
    zl_api_require_csrf($token, 'Your secure upload session expired. Refresh DNA Lab and try again.');
}

function zl_api_dna_rate_limit() {
    $now = time();
    $events = array_values(array_filter((array) ($_SESSION['dna_upload_events'] ?? []), function ($ts) use ($now) {
        return is_numeric($ts) && (int) $ts >= $now - 60;
    }));
    if (count($events) >= 6) {
        zl_api_reply(['status' => 'error', 'message' => 'DNA Lab is already processing several requests. Wait a minute, then retry.'], 429);
    }
    $events[] = $now;
    $_SESSION['dna_upload_events'] = $events;
}

function zl_api_validate_dna_upload($file, $label = 'DNA file') {
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new ZlApiPublicException($label . ' upload failed with code ' . $error . '.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > ZL_DNA_MAX_RAW_UPLOAD_BYTES) {
        throw new ZlApiPublicException($label . ' must be a non-empty text genotype file no larger than ' . zl_api_bytes_label(ZL_DNA_MAX_RAW_UPLOAD_BYTES) . '.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp) || !is_file($tmp)) {
        throw new ZlApiPublicException($label . ' did not pass the HTTP upload check.');
    }
    $name = zl_safe_name((string) ($file['name'] ?? 'dna-raw.txt'));
    $ext = zl_file_ext($name);
    if (!in_array($ext, ['txt', 'csv', 'tsv', 'vcf', 'dna', 'genome', 'raw'], true)) {
        throw new ZlApiPublicException($label . ' type is not supported. Use an uncompressed TXT, CSV, TSV, VCF, DNA, GENOME, or RAW export.');
    }
    $head = zl_file_head_bytes($tmp, 65536);
    if ($head === '' || strpos($head, "\0") !== false || preg_match('/^(?:PK\x03\x04|\x1F\x8B|%PDF|MZ|\x7FELF)/s', $head)) {
        throw new ZlApiPublicException($label . ' must be an uncompressed text genotype export. Archives, executables, and binary files are not accepted.');
    }
    if (preg_match('/<\?(?:php|=)|<script\b/i', $head)) {
        throw new ZlApiPublicException($label . ' contains executable markup and was rejected.');
    }
    if (!zl_text_looks_like_genotype_data($head)) {
        throw new ZlApiPublicException($label . ' does not look like a supported raw-genotype table or VCF. Check the provider export and try again.');
    }
    return $tmp;
}

function zl_api_http_json($url, $headers = [], $timeout = 30) {
    $ch = curl_init($url);
    $base_headers = [
        'Accept: application/json',
        'User-Agent: ZeroThink-DNA-Lab/1.0 (research-only; shaf@talktoai.org)',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int) $timeout,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array_merge($base_headers, (array) $headers),
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        throw new ZlApiPublicException('The external research provider could not be reached. Try again shortly.');
    }
    $json = json_decode((string) $raw, true);
    if ($code >= 400) {
        $msg = is_array($json) ? (string) ($json['error'] ?? $json['message'] ?? '') : '';
        if ($msg === '') {
            $msg = substr(trim((string) $raw), 0, 240);
        }
        error_log('ZeroThink research provider HTTP ' . $code . ': ' . substr($msg, 0, 400));
        throw new ZlApiPublicException('The external research provider returned an error (HTTP ' . $code . ').');
    }
    return is_array($json) ? $json : ['raw' => (string) $raw];
}

function zl_api_user_key($uid, $column, $fallback = '') {
    if (defined('ZEROTHINK_LOCAL_LAB') && ZEROTHINK_LOCAL_LAB === true) { return ''; }
    $allowed = ['api_key_alphagenome', 'api_key_ncbi'];
    if (!in_array($column, $allowed, true)) {
        return '';
    }
    $fallback = trim((string) $fallback);
    if ($fallback !== '') {
        return $fallback;
    }
    try {
        $db = db();
        $stmt = $db->prepare("SELECT {$column} FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $uid]);
        return trim((string) $stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

function zl_api_markdown_table($rows) {
    if (!$rows) {
        return 'No rows returned.';
    }
    $lines = ['| Field | Value |', '|---|---|'];
    foreach ($rows as $field => $value) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $lines[] = '| ' . str_replace('|', '\\|', (string) $field) . ' | ' . str_replace('|', '\\|', (string) $value) . ' |';
    }
    return implode("\n", $lines);
}

function zl_dna_provider_status($uid) {
    return [
        'local_zero_library' => [
            'status' => 'ready',
            'cost' => 'no API tokens',
            'features' => ['GC/AT/N content', 'skew', 'CpG observed/expected', 'motifs', 'ORFs', 'translation preview', 'reverse complement', 'top k-mers'],
        ],
        'health_research' => [
            'status' => 'ready',
            'cost' => 'no API tokens for local watchlist',
            'best_for' => 'consumer raw DNA quality summary, research marker detection, and ClinVar lookup preparation',
            'truth_boundary' => 'Not medical advice. No diagnosis, treatment, medication, prevention, or personal risk score.',
        ],
        'heritage_research' => [
            'status' => 'ready',
            'cost' => 'no API tokens for reference-readiness and marker matching',
            'best_for' => 'raw-file coverage, ancestry-reference readiness, and population-marker context',
            'truth_boundary' => 'Does not output ethnicity percentages without a proper reference panel and confidence model.',
        ],
        'alphagenome' => [
            'status' => zl_api_user_key($uid, 'api_key_alphagenome') !== '' ? 'key saved' : 'key not saved',
            'mode' => 'official Python SDK / API key lane',
            'best_for' => 'regulatory variant effect and multimodal sequence-to-function prediction',
            'docs' => 'https://github.com/google-deepmind/alphagenome',
            'truth_boundary' => 'ZeroThink stores the key and builds safe request manifests. The official AlphaGenome client is Python SDK based, so server execution requires the AlphaGenome bridge worker to be installed.',
        ],
        'ensembl_vep' => [
            'status' => 'ready',
            'key_required' => false,
            'docs' => 'https://rest.ensembl.org/documentation/',
            'best_for' => 'public variant consequence lookup by rsID or region/allele',
        ],
        'ncbi_entrez' => [
            'status' => zl_api_user_key($uid, 'api_key_ncbi') !== '' ? 'optional key saved' : 'works without key at lower rate limits',
            'key_required' => false,
            'docs' => 'https://www.ncbi.nlm.nih.gov/home/develop/api/',
            'best_for' => 'Gene, nucleotide, protein, SNP, PubMed search and summaries',
        ],
    ];
}

function zl_dna_alphagenome_manifest($uid, $input) {
    $sequence = zl_dna_clean((string) ($input['sequence'] ?? $input['text'] ?? ''));
    $interval = trim((string) ($input['interval'] ?? 'chr22:35677410-36725986'));
    $variant = trim((string) ($input['variant'] ?? 'chr22:36201698:A>C'));
    if ($sequence !== '' && strlen($sequence) > 1000000) {
        throw new ZlApiPublicException('AlphaGenome preview supports up to 1,000,000 base pairs per sequence window.');
    }
    $has_key = zl_api_user_key($uid, 'api_key_alphagenome', (string) ($input['alphagenome_key'] ?? '')) !== '';
    $snippet = <<<'PY'
from alphagenome.data import genome
from alphagenome.models import dna_client

API_KEY = "YOUR_ALPHAGENOME_API_KEY"
model = dna_client.create(API_KEY)

# Example shape. Replace interval/variant with your research target.
interval = genome.Interval(chromosome="chr22", start=35677410, end=36725986)
variant = genome.Variant(
    chromosome="chr22",
    position=36201698,
    reference_bases="A",
    alternate_bases="C",
)
outputs = model.predict_variant(
    interval=interval,
    variant=variant,
    ontology_terms=["UBERON:0001157"],
    requested_outputs=[dna_client.OutputType.RNA_SEQ],
)
print(outputs)
PY;
    return [
        'provider' => 'AlphaGenome',
        'key_saved' => $has_key,
        'interval' => $interval,
        'variant' => $variant,
        'sequence_length' => strlen($sequence),
        'sequence_hash' => $sequence !== '' ? hash('sha256', $sequence) : null,
        'not_sent_to_provider' => true,
        'bridge_status' => 'manifest-only until the Python AlphaGenome bridge worker is installed',
        'official_docs' => 'https://github.com/google-deepmind/alphagenome',
        'python_sdk_template' => $snippet,
        'research_boundary' => 'Research only. Do not use for clinical, diagnostic, ancestry, or treatment decisions.',
    ];
}

$user = function_exists('request_user_record') ? request_user_record() : current_user_record();
$uid = (int) ($user['id'] ?? ($_SESSION['user_id'] ?? 0));
if ($uid <= 0) {
    zl_api_reply(['status' => 'error', 'message' => 'Auth required.'], 403);
}

function zl_api_try_store_optional_library_file($uid, $file, $tmp_path, &$notice = null) {
    $notice = null;
    $name = (string) ($file['name'] ?? 'dna-raw.txt');
    $size = (int) ($file['size'] ?? (is_file($tmp_path) ? filesize($tmp_path) : 0));
    if ($size > zl_library_max_save_bytes()) {
        $notice = 'Analyzed successfully, but the raw file was not saved. Zero Library saves are limited to ' . zl_library_max_save_mb() . ' MB per file.';
        return null;
    }
    try {
        return zl_store_upload($uid, $name, $tmp_path, [
            'classification' => 'sensitive_genotype',
            'indexable' => false,
        ]);
    } catch (Throwable $e) {
        error_log('ZeroThink sensitive DNA save failed: ' . $e->getMessage());
        $notice = 'Analyzed successfully, but the private DNA vault could not save the raw file. The report is still available in this browser session.';
        return null;
    }
}

try {
    zl_api_upload_preflight();
    $file_action = strtolower((string) ($_POST['action'] ?? ''));
    $dna_file_actions = ['dna_raw_file', 'dna_health_file', 'dna_heritage_file', 'dna_full_file', 'dna_lineage_file', 'dna_sound_file', 'dna_theory_file', 'dna_ancient_file'];
    if (!empty($_FILES['dna_file']) || (!empty($_FILES['file']) && in_array($file_action, $dna_file_actions, true))) {
        zl_api_require_dna_csrf((string) ($_POST['csrf_token'] ?? ''));
        if ((string) ($_POST['dna_consent'] ?? '') !== '1') {
            zl_api_reply([
                'status' => 'error',
                'message' => 'Confirm the DNA privacy and research-use notice before uploading.',
            ], 422);
        }
        zl_api_dna_rate_limit();
        $file = !empty($_FILES['dna_file']) ? $_FILES['dna_file'] : $_FILES['file'];
        $tmp = zl_api_validate_dna_upload($file, 'DNA file');
        $analysis_options = [
            'declared_build' => (string) ($_POST['declared_build'] ?? 'auto'),
            'reported_orientation' => (string) ($_POST['reported_orientation'] ?? 'auto'),
            'include_sensitive_health' => !empty($_POST['include_sensitive_health']),
        ];

        $gedcom_text = '';
        if (!empty($_FILES['gedcom_file']) && empty($_FILES['gedcom_file']['error']) && is_uploaded_file((string) ($_FILES['gedcom_file']['tmp_name'] ?? ''))) {
            if ((int) ($_FILES['gedcom_file']['size'] ?? 0) > 8 * 1024 * 1024) {
                throw new ZlApiPublicException('GEDCOM file is too large for the lineage pre-check.');
            }
            $gedcom_name = zl_safe_name((string) ($_FILES['gedcom_file']['name'] ?? 'family-tree.ged'));
            if (!in_array(zl_file_ext($gedcom_name), ['ged', 'gedcom', 'txt'], true)) {
                throw new ZlApiPublicException('GEDCOM context must be an uncompressed GED, GEDCOM, or TXT file.');
            }
            $gedcom_text = (string) file_get_contents((string) $_FILES['gedcom_file']['tmp_name']);
            if ($gedcom_text === '' || strpos($gedcom_text, "\0") !== false || stripos($gedcom_text, '0 HEAD') === false) {
                throw new ZlApiPublicException('The family-tree file does not look like a valid text GEDCOM export.');
            }
        }
        $lineage_input = [
            'family_side' => (string) ($_POST['family_side'] ?? 'unknown'),
            'surnames' => (string) ($_POST['surnames'] ?? ''),
            'places' => (string) ($_POST['places'] ?? ''),
            'date_ranges' => (string) ($_POST['date_ranges'] ?? ''),
            'story_notes' => (string) ($_POST['story_notes'] ?? ''),
            'gedcom_text' => $gedcom_text,
        ];
        $relative_dna_context = [];
        if (!empty($_FILES['relative_dna_files']) && is_array($_FILES['relative_dna_files']['name'] ?? null)) {
            $names = (array) $_FILES['relative_dna_files']['name'];
            $tmps = (array) $_FILES['relative_dna_files']['tmp_name'];
            $sizes = (array) $_FILES['relative_dna_files']['size'];
            $errors = (array) $_FILES['relative_dna_files']['error'];
            $limit = min(3, count($names));
            for ($i = 0; $i < $limit; $i++) {
                $err = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);
                if ($err === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $relative_file = [
                    'name' => (string) ($names[$i] ?? 'relative-dna.txt'),
                    'tmp_name' => (string) ($tmps[$i] ?? ''),
                    'size' => (int) ($sizes[$i] ?? 0),
                    'error' => $err,
                ];
                $rel_tmp = zl_api_validate_dna_upload($relative_file, 'Relative DNA file ' . ($i + 1));
                $relative_dna_context[] = zl_dna_relatedness_compare_paths(
                    $tmp,
                    (string) ($file['name'] ?? 'dna-raw.txt'),
                    $rel_tmp,
                    (string) ($names[$i] ?? 'relative-dna.txt')
                );
            }
        }
        $lineage_input['relative_dna_context'] = $relative_dna_context;
        $parent_dna_input = [];
        foreach (['mother' => 'mother_dna_file', 'father' => 'father_dna_file'] as $side => $field) {
            if (!empty($_FILES[$field]) && empty($_FILES[$field]['error']) && is_uploaded_file((string) ($_FILES[$field]['tmp_name'] ?? ''))) {
                $parent_dna_input[$side . '_dna_path'] = zl_api_validate_dna_upload($_FILES[$field], ucfirst($side) . ' DNA file');
                $parent_dna_input[$side . '_dna_name'] = (string) ($_FILES[$field]['name'] ?? ($side . '-dna.txt'));
            }
        }

        if ($file_action === 'dna_full_file') {
            $report = zl_dna_full_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), array_merge($analysis_options, $lineage_input, $parent_dna_input));
            $stored = null;
            $library_notice = null;
            if (!empty($_POST['save_to_library'])) {
                $stored = zl_api_try_store_optional_library_file($uid, $file, $tmp, $library_notice);
            }
            zl_api_reply([
                'status' => 'success',
                'kind' => 'dna_full_file',
                'report' => $report,
                'markdown' => zl_dna_public_report_text(zl_dna_full_markdown($report)),
                'agent_context' => zl_dna_agent_context($report, 'full'),
                'library_file' => $stored,
                'library_notice' => $library_notice,
            ]);
        }

        if ($file_action === 'dna_lineage_file') {
            $raw = zl_dna_apply_analysis_options(zl_dna_raw_analyze_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt')), $analysis_options);
            $lineage_input['dna_path'] = $tmp;
            $lineage_input['dna_name'] = (string) ($file['name'] ?? 'dna-raw.txt');
            $report = zl_dna_lineage_report($gedcom_text, $lineage_input, $raw);
            zl_api_reply([
                'status' => 'success',
                'kind' => 'dna_lineage_file',
                'report' => $report,
                'markdown' => zl_dna_public_report_text(zl_dna_lineage_markdown($report)),
                'agent_context' => zl_dna_agent_context(['summary' => $raw, 'evidence_cards' => $report['evidence_cards'] ?? []], 'lineage'),
            ]);
        }

        if ($file_action === 'dna_sound_file') {
            $report = zl_dna_sonic_profile_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'));
            zl_api_reply([
                'status' => 'success',
                'kind' => 'dna_sound_file',
                'report' => $report,
                'profile' => $report['profile'] ?? null,
                'markdown' => zl_dna_public_report_text(zl_dna_sonic_markdown($report)),
                'agent_context' => zl_dna_agent_context($report, 'sound'),
            ]);
        }

        if ($file_action === 'dna_theory_file') {
            $report = zl_dna_theoretical_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), array_merge($analysis_options, $lineage_input, $parent_dna_input));
            zl_api_reply([
                'status' => 'success',
                'kind' => 'dna_theory_file',
                'report' => $report,
                'markdown' => zl_dna_public_report_text(zl_dna_theoretical_markdown($report)),
                'agent_context' => zl_dna_agent_context($report, 'theory'),
            ]);
        }

        if ($file_action === 'dna_ancient_file') {
            $raw = zl_dna_apply_analysis_options(zl_dna_raw_analyze_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt')), $analysis_options);
            $heritage = zl_dna_heritage_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), $raw);
            $report = zl_dna_ancient_grave_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), $raw, $heritage);
            $report['origins'] = zl_dna_origins_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), $raw, $heritage);
            $report['origins']['composition'] = zl_dna_composition_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), $raw, $report['origins']['classification'] ?? null);
            $report['origins']['composition']['place_context'] = zl_dna_composition_country_context($report['origins']['composition']);
            $report['country_context'] = zl_dna_country_context_report($report['origins']);
            zl_api_reply([
                'status' => 'success',
                'kind' => 'dna_ancient_file',
                'report' => $report,
                'markdown' => zl_dna_public_report_text(zl_dna_composition_markdown($report['origins']['composition']) . "\n\n" . zl_dna_composition_country_markdown($report['origins']['composition']['place_context']) . "\n\n" . zl_dna_country_context_markdown($report['country_context']) . "\n\n" . zl_dna_origins_markdown($report['origins']) . "\n\n" . zl_dna_ancient_grave_markdown($report)),
                'agent_context' => zl_dna_agent_context(['summary' => $raw, 'ancient_graves' => $report, 'origins' => $report['origins'], 'evidence_cards' => $report['evidence_cards'] ?? []], 'ancient'),
            ]);
        }

        if ($file_action === 'dna_health_file') {
            $raw = zl_dna_apply_analysis_options(zl_dna_raw_analyze_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt')), $analysis_options);
            $report = zl_dna_health_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), $raw, $analysis_options);
            zl_api_reply([
                'status' => 'success',
                'kind' => 'dna_health_file',
                'report' => $report,
                'markdown' => zl_dna_public_report_text(zl_dna_marker_markdown('DNA Health Research Screen', $report, 'health')),
                'agent_context' => zl_dna_agent_context($report, 'health'),
            ]);
        }

        if ($file_action === 'dna_heritage_file') {
            $raw = zl_dna_apply_analysis_options(zl_dna_raw_analyze_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt')), $analysis_options);
            $report = zl_dna_heritage_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), $raw);
            $report['origins'] = zl_dna_origins_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), $raw, $report);
            $report['origins']['composition'] = zl_dna_composition_report_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt'), $raw, $report['origins']['classification'] ?? null);
            $report['origins']['composition']['place_context'] = zl_dna_composition_country_context($report['origins']['composition']);
            $report['country_context'] = zl_dna_country_context_report($report['origins']);
            zl_api_reply([
                'status' => 'success',
                'kind' => 'dna_heritage_file',
                'report' => $report,
                'markdown' => zl_dna_public_report_text(zl_dna_composition_markdown($report['origins']['composition']) . "\n\n" . zl_dna_composition_country_markdown($report['origins']['composition']['place_context']) . "\n\n" . zl_dna_country_context_markdown($report['country_context']) . "\n\n" . zl_dna_origins_markdown($report['origins']) . "\n\n" . zl_dna_marker_markdown('DNA Heritage Research Screen', $report, 'heritage')),
                'agent_context' => zl_dna_agent_context($report, 'heritage'),
            ]);
        }

        $analysis = zl_dna_apply_analysis_options(zl_dna_raw_analyze_path($tmp, (string) ($file['name'] ?? 'dna-raw.txt')), $analysis_options);
        $stored = null;
        $library_notice = null;
        if (!empty($_POST['save_to_library'])) {
            $stored = zl_api_try_store_optional_library_file($uid, $file, $tmp, $library_notice);
        }
        zl_api_reply([
            'status' => 'success',
            'kind' => 'dna_raw_file',
            'analysis' => $analysis,
            'markdown' => zl_dna_public_report_text(zl_dna_raw_markdown($analysis)),
            'agent_context' => zl_dna_agent_context(['summary' => $analysis], 'raw'),
            'library_file' => $stored,
            'library_notice' => $library_notice,
        ]);
    }

    if (!empty($_FILES['file'])) {
        zl_api_require_csrf(
            (string) ($_POST['csrf_token'] ?? ''),
            'Your secure library session expired. Refresh Zero Library and try again.'
        );
        $file = $_FILES['file'];
        if (!empty($file['error'])) {
            throw new ZlApiPublicException('Upload failed with code ' . (int) $file['error']);
        }
        if ((int) ($file['size'] ?? 0) > zl_library_max_save_bytes()) {
            throw new ZlApiPublicException('Zero Library saves are limited to ' . zl_library_max_save_mb() . ' MB per file.');
        }
        $stored = zl_store_upload($uid, (string) ($file['name'] ?? 'library-file.txt'), (string) ($file['tmp_name'] ?? ''));
        $message = !empty($stored['indexable'])
            ? 'Catalogued and added to private search.'
            : 'Stored as a private vault item. Its contents are not searchable and are never added to model context.';
        zl_api_reply(['status' => 'success', 'message' => $message, 'file' => $stored, 'index' => zl_build_index($uid, true)]);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST ?: [];
    }
    $action = strtolower(trim((string) ($input['action'] ?? 'status')));

    if ($action === 'status') {
        zl_api_reply([
            'status' => 'success',
            'library' => zl_build_index($uid, false),
            'allowed_exts' => zl_allowed_exts(),
            'scope' => [
                'mode' => 'account-private',
                'owner_hash' => substr(hash('sha256', 'zero-library-user-' . $uid), 0, 16),
                'storage' => 'server-side user scoped directory',
                'public_access' => 'blocked by web rules',
                'max_saved_file_mb' => zl_library_max_save_mb(),
                'max_saved_file_bytes' => zl_library_max_save_bytes(),
            ],
        ]);
    }
    if ($action === 'dna_status') {
        require_once __DIR__ . '/app/dna_reference_catalog.php';
        zl_api_reply(['status' => 'success', 'providers' => zl_dna_provider_status($uid), 'reference_catalog' => zl_dna_reference_catalog(), 'origins_catalog' => zl_dna_origins_catalog(), 'ancestry_model_catalog'=>zl_dna_grafanc_catalog(), 'ancestry_composition_catalog'=>zl_dna_composition_catalog(), 'country_context_catalog'=>zl_dna_country_context_catalog(), 'ancestry_story_catalog' => ['regions'=>zlas_regions(), 'population_labels'=>zlas_population_labels(), 'requested_region_coverage'=>zlas_requested_region_coverage(), 'regional_refs'=>zl_dna_regional_refs_catalog()]]);
    }
    if ($action === 'index' || $action === 'reindex') {
        zl_api_require_csrf(
            (string) ($input['csrf_token'] ?? ''),
            'Your secure library session expired. Refresh Zero Library and try again.'
        );
        zl_api_reply(['status' => 'success', 'library' => zl_build_index($uid, true)]);
    }
    if ($action === 'search') {
        zl_api_reply(['status' => 'success', 'hits' => zl_search($uid, trim((string) ($input['query'] ?? '')), (int) ($input['limit'] ?? 8))]);
    }
    if ($action === 'context') {
        $max_chars = max(2000, min(60000, (int) ($input['max_chars'] ?? 14000)));
        zl_api_reply(['status' => 'success', 'context' => zl_context_pack($uid, trim((string) ($input['query'] ?? '')), $max_chars)]);
    }
    if ($action === 'dna') {
        $raw = (string) ($input['sequence'] ?? $input['text'] ?? '');
        $seq = zl_dna_extract_sequence($raw);
        if ($seq === '') {
            $seq = zl_dna_clean($raw);
        }
        $analysis = zl_dna_analyze($seq);
        zl_api_reply(['status' => 'success', 'analysis' => $analysis, 'markdown' => zl_dna_public_report_text(zl_dna_markdown($analysis))]);
    }
    if ($action === 'dna_raw_text') {
        $raw = (string) ($input['text'] ?? $input['raw'] ?? '');
        if (trim($raw) === '') {
            throw new ZlApiPublicException('Paste raw genotype text first.');
        }
        $analysis = zl_dna_raw_analyze($raw, (string) ($input['name'] ?? 'pasted-raw-dna.txt'));
        zl_api_reply(['status' => 'success', 'kind' => 'dna_raw_text', 'analysis' => $analysis, 'markdown' => zl_dna_public_report_text(zl_dna_raw_markdown($analysis))]);
    }
    if ($action === 'dna_health_text') {
        $raw = (string) ($input['text'] ?? $input['raw'] ?? '');
        if (trim($raw) === '') {
            throw new ZlApiPublicException('Paste raw genotype text first.');
        }
        $report = zl_dna_health_report($raw, (string) ($input['name'] ?? 'pasted-raw-dna.txt'));
        zl_api_reply(['status' => 'success', 'kind' => 'dna_health_text', 'report' => $report, 'markdown' => zl_dna_public_report_text(zl_dna_marker_markdown('DNA Health Research Screen', $report, 'health'))]);
    }
    if ($action === 'dna_heritage_text') {
        $raw = (string) ($input['text'] ?? $input['raw'] ?? '');
        if (trim($raw) === '') {
            throw new ZlApiPublicException('Paste raw genotype text first.');
        }
        $report = zl_dna_heritage_report($raw, (string) ($input['name'] ?? 'pasted-raw-dna.txt'));
        zl_api_reply(['status' => 'success', 'kind' => 'dna_heritage_text', 'report' => $report, 'markdown' => zl_dna_public_report_text(zl_dna_marker_markdown('DNA Heritage Research Screen', $report, 'heritage'))]);
    }
    if ($action === 'dna_alphagenome_manifest') {
        $manifest = zl_dna_alphagenome_manifest($uid, $input);
        zl_api_reply([
            'status' => 'success',
            'manifest' => $manifest,
            'markdown' => "## AlphaGenome Request Manifest\n\n" . zl_api_markdown_table([
                'Provider' => 'Google DeepMind AlphaGenome',
                'Key saved' => $manifest['key_saved'] ? 'yes' : 'no',
                'Interval' => $manifest['interval'],
                'Variant' => $manifest['variant'],
                'Sequence length' => $manifest['sequence_length'],
                'Sequence hash' => $manifest['sequence_hash'] ?: 'none',
                'Bridge status' => $manifest['bridge_status'],
                'Research boundary' => $manifest['research_boundary'],
            ]) . "\n\n### Python SDK Template\n```python\n" . $manifest['python_sdk_template'] . "\n```",
        ]);
    }
    if ($action === 'dna_clinvar_lookup') {
        $raw_rsids = (string) ($input['rsids'] ?? $input['query'] ?? '');
        preg_match_all('/rs\d+/i', $raw_rsids, $matches);
        $rsids = array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
        $rsids = array_slice($rsids, 0, 12);
        if (!$rsids) {
            throw new ZlApiPublicException('Enter one or more RSIDs, e.g. rs429358 rs7412.');
        }
        $api_key = zl_api_user_key($uid, 'api_key_ncbi', (string) ($input['ncbi_key'] ?? ''));
        $items = [];
        foreach ($rsids as $rsid) {
            $qs = [
                'db' => 'clinvar',
                'term' => $rsid . '[All Fields]',
                'retmode' => 'json',
                'retmax' => 5,
                'tool' => 'ZeroThink',
                'email' => 'shaf@talktoai.org',
            ];
            if ($api_key !== '') {
                $qs['api_key'] = $api_key;
            }
            $search = zl_api_http_json('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?' . http_build_query($qs), [], 30);
            $ids = (array) ($search['esearchresult']['idlist'] ?? []);
            $summary = null;
            if ($ids) {
                $summary_qs = [
                    'db' => 'clinvar',
                    'id' => implode(',', $ids),
                    'retmode' => 'json',
                    'tool' => 'ZeroThink',
                    'email' => 'shaf@talktoai.org',
                ];
                if ($api_key !== '') {
                    $summary_qs['api_key'] = $api_key;
                }
                $summary = zl_api_http_json('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?' . http_build_query($summary_qs), [], 30);
            }
            $items[] = [
                'rsid' => $rsid,
                'clinvar_ids' => $ids,
                'summary' => $summary,
                'clinvar_url' => 'https://www.ncbi.nlm.nih.gov/clinvar/?term=' . rawurlencode($rsid),
            ];
            usleep(140000);
        }
        $lines = ['## NCBI ClinVar Lookup', '', '**Boundary:** Public ClinVar assertions are research context only. This is not medical advice or diagnosis.', ''];
        foreach ($items as $item) {
            $lines[] = '- ' . strtoupper($item['rsid']) . ': ' . ($item['clinvar_ids'] ? implode(', ', $item['clinvar_ids']) : 'no ClinVar IDs found');
            $lines[] = '  Link: ' . $item['clinvar_url'];
        }
        zl_api_reply([
            'status' => 'success',
            'provider' => 'NCBI ClinVar via E-utilities',
            'rsids' => $rsids,
            'items' => $items,
            'markdown' => zl_dna_public_report_text(implode("\n", $lines)),
        ]);
    }
    if ($action === 'dna_ensembl_vep') {
        $species = strtolower(trim((string) ($input['species'] ?? 'human')));
        $region = trim((string) ($input['region'] ?? ''));
        $allele = strtoupper(trim((string) ($input['allele'] ?? '')));
        if (!preg_match('/^[a-z_]+$/', $species)) {
            throw new ZlApiPublicException('Use a valid Ensembl species token, e.g. human.');
        }
        if (!preg_match('/^[A-Za-z0-9_.-]+:\d+-\d+(?::-?1)?$/', $region)) {
            throw new ZlApiPublicException('Use Ensembl region format, e.g. 9:22125503-22125502:1.');
        }
        if (!preg_match('/^[ACGTN-]{1,80}$/', $allele)) {
            throw new ZlApiPublicException('Use a DNA allele such as A, C, G, T, N, or -.');
        }
        $url = 'https://rest.ensembl.org/vep/' . rawurlencode($species) . '/region/' . rawurlencode($region) . '/' . rawurlencode($allele) . '?';
        $payload = zl_api_http_json($url, ['Content-Type: application/json'], 35);
        $summary = [];
        if (isset($payload[0]) && is_array($payload[0])) {
            $first = $payload[0];
            $summary = [
                'input' => $first['input'] ?? ($region . '/' . $allele),
                'most_severe_consequence' => $first['most_severe_consequence'] ?? 'unknown',
                'transcript_consequences' => count((array) ($first['transcript_consequences'] ?? [])),
                'regulatory_feature_consequences' => count((array) ($first['regulatory_feature_consequences'] ?? [])),
            ];
        }
        zl_api_reply([
            'status' => 'success',
            'provider' => 'Ensembl VEP REST',
            'summary' => $summary,
            'payload' => $payload,
            'markdown' => zl_dna_public_report_text("## Ensembl VEP Result\n\n" . zl_api_markdown_table($summary ?: ['Result' => 'No concise consequence summary returned.']) . "\n\nResearch-only public annotation. Not medical advice."),
        ]);
    }
    if ($action === 'dna_ncbi_search') {
        $query = trim((string) ($input['query'] ?? ''));
        $db_name = strtolower(trim((string) ($input['db'] ?? 'gene')));
        $allowed = ['gene', 'nuccore', 'protein', 'snp', 'pubmed'];
        if ($query === '' || strlen($query) > 240) {
            throw new ZlApiPublicException('Enter a short NCBI search query.');
        }
        if (!in_array($db_name, $allowed, true)) {
            $db_name = 'gene';
        }
        $retmax = max(1, min(10, (int) ($input['retmax'] ?? 5)));
        $api_key = zl_api_user_key($uid, 'api_key_ncbi', (string) ($input['ncbi_key'] ?? ''));
        $qs = [
            'db' => $db_name,
            'term' => $query,
            'retmode' => 'json',
            'retmax' => $retmax,
            'tool' => 'ZeroThink',
            'email' => 'shaf@talktoai.org',
        ];
        if ($api_key !== '') {
            $qs['api_key'] = $api_key;
        }
        $search = zl_api_http_json('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?' . http_build_query($qs), [], 30);
        $ids = (array) ($search['esearchresult']['idlist'] ?? []);
        $summary = null;
        if ($ids) {
            $summary_qs = [
                'db' => $db_name,
                'id' => implode(',', array_slice($ids, 0, $retmax)),
                'retmode' => 'json',
                'tool' => 'ZeroThink',
                'email' => 'shaf@talktoai.org',
            ];
            if ($api_key !== '') {
                $summary_qs['api_key'] = $api_key;
            }
            $summary = zl_api_http_json('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?' . http_build_query($summary_qs), [], 30);
        }
        zl_api_reply([
            'status' => 'success',
            'provider' => 'NCBI Entrez E-utilities',
            'db' => $db_name,
            'query' => $query,
            'ids' => $ids,
            'summary' => $summary,
            'markdown' => zl_dna_public_report_text("## NCBI Search\n\n" . zl_api_markdown_table([
                'Source' => $db_name,
                'Query' => $query,
                'IDs' => $ids ? implode(', ', $ids) : 'none',
                'API key used' => $api_key !== '' ? 'yes' : 'no',
            ]) . "\n\nResearch-only public source lookup. Not medical advice."),
        ]);
    }
    if ($action === 'quantum_chess') {
        zl_api_reply(['status' => 'success', 'state' => zl_quantum_chess_state((string) ($input['move'] ?? ''), (string) ($input['seed'] ?? ''))]);
    }

    zl_api_reply(['status' => 'error', 'message' => 'Unknown library action.'], 400);
} catch (Throwable $e) {
    $request_id = bin2hex(random_bytes(6));
    error_log('ZeroThink API error [' . $request_id . ']: ' . $e->getMessage());
    $message = $e instanceof ZlApiPublicException
        ? $e->getMessage()
        : 'The request could not be completed safely. Try again or contact support with reference ' . $request_id . '.';
    zl_api_reply(['status' => 'error', 'message' => $message, 'request_id' => $request_id], $e instanceof ZlApiPublicException ? 400 : 500);
}
?>
