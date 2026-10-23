<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$historyApi = (string) file_get_contents($root . '/api_history.php');
$shareApi = (string) file_get_contents($root . '/api_share.php');
$filesApi = (string) file_get_contents($root . '/api_files.php');
$knowledgeApi = (string) file_get_contents($root . '/api_knowledge.php');
$feedbackApi = (string) file_get_contents($root . '/api_feedback.php');
$cancelApi = (string) file_get_contents($root . '/api_cancel_subscription.php');
$subscribeApi = (string) file_get_contents($root . '/api_subscribe.php');
$upgradeApi = (string) file_get_contents($root . '/api_upgrade_success.php');
$templatesApi = (string) file_get_contents($root . '/api_research_templates.php');
$historyPage = (string) file_get_contents($root . '/history.php');
$filesPage = (string) file_get_contents($root . '/files.php');
$knowledgePage = (string) file_get_contents($root . '/app/views/knowledge.php');
$studio = (string) file_get_contents($root . '/studio.php');
$upgradePage = (string) file_get_contents($root . '/upgrade.php');
$upgradeView = (string) file_get_contents($root . '/app/views/upgrade.php');
$templatesPage = (string) file_get_contents($root . '/docs_custom_research_processes.php');

$checks = 0;
$failures = [];

function zt_mutation_assert(bool $condition, string $message): void {
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function zt_no_raw_exception_response(string $source): bool {
    return !preg_match('/[\'\"]message[\'\"]\s*=>\s*\$e->getMessage\s*\(/', $source);
}

zt_mutation_assert(
    strpos($historyApi, "'POST required.'") !== false
        && strpos($historyApi, "'JSON request required.'") !== false
        && strpos($historyApi, 'zt_csrf_validate($csrf_token)') !== false,
    'History mutations must require POST, JSON, and shared CSRF.'
);
zt_mutation_assert(
    strpos($historyApi, "['delete', 'rename', 'nuke', 'wipe_memory']") !== false
        && strpos($historyApi, 'DELETE FROM chat_history WHERE id = ? AND user_id = ?') !== false
        && strpos($historyApi, 'UPDATE chat_history SET title = ? WHERE id = ? AND user_id = ?') !== false,
    'History actions must be allowlisted and owner-scoped.'
);
zt_mutation_assert(
    zt_no_raw_exception_response($historyApi)
        && strpos($historyApi, "'request_id' => \$request_id") !== false,
    'History errors must stay behind a generic request id.'
);
zt_mutation_assert(
    strpos($historyPage, 'const HISTORY_CSRF_TOKEN = <?= json_encode(') !== false
        && strpos($historyPage, "'X-CSRF-Token': HISTORY_CSRF_TOKEN") !== false
        && strpos($historyPage, "'Content-Type': 'application/json'") !== false,
    'Memory Stream must send CSRF-protected JSON mutations.'
);

zt_mutation_assert(
    strpos($shareApi, "'POST required.'") !== false
        && strpos($shareApi, 'zt_csrf_validate($csrf_token)') !== false
        && strpos($shareApi, 'FOR UPDATE') !== false,
    'Share-token creation must require POST/CSRF and lock the owned row.'
);
zt_mutation_assert(
    strpos($shareApi, 'WHERE id = ? AND user_id = ?') !== false
        && strpos($shareApi, '$db->beginTransaction()') !== false
        && zt_no_raw_exception_response($shareApi),
    'Share-token mutation must stay owner-scoped, transactional, and non-leaking.'
);
zt_mutation_assert(
    strpos($historyPage, "secureHistoryPost('/api_share.php', { id })") !== false,
    'History sharing UI must use the CSRF-aware helper.'
);

zt_mutation_assert(
    strpos($filesApi, "if (\$method === 'POST')") !== false
        && strpos($filesApi, 'zt_csrf_validate($csrf_token)') !== false,
    'Private Files mutations must enter the POST CSRF gate.'
);
zt_mutation_assert(
    strpos($filesApi, "if (\$method === 'POST' && \$action === 'upload')") !== false
        && strpos($filesApi, "if (\$method === 'POST' && \$action === 'delete')") !== false
        && strpos($filesApi, "if (\$method === 'GET' && \$action === 'download')") !== false,
    'Upload/delete must be POST-only while download remains read-only GET.'
);
zt_mutation_assert(
    strpos($filesApi, "\$_POST['name'] ?? ''") !== false
        && strpos($filesApi, "\$_GET['name'] ?? ''") !== false,
    'Delete must read its name only from POST while download retains GET compatibility.'
);
zt_mutation_assert(
    substr_count($filesPage, "fd.append('csrf_token', FILES_CSRF_TOKEN)") === 2
        && substr_count($filesPage, "fetch('/api_files.php', {") === 2
        && strpos($filesPage, 'api_files.php?action=delete') === false
        && strpos($filesPage, 'api_files.php?action=upload') === false,
    'Private Files UI must send both mutations to the clean endpoint with CSRF.'
);

zt_mutation_assert(
    strpos($knowledgeApi, "if (\$method === 'GET')") !== false
        && strpos($knowledgeApi, 'zt_csrf_validate($csrf_token)') !== false
        && strpos($knowledgeApi, "'JSON request required.'") !== false,
    'Knowledge read compatibility must remain GET while writes require JSON/CSRF.'
);
zt_mutation_assert(
    strpos($knowledgeApi, 'file_put_contents($temporary, $encoded, LOCK_EX)') !== false
        && strpos($knowledgeApi, 'rename($temporary, $file)') !== false
        && strpos($knowledgeApi, 'The previous data remains in place.') !== false,
    'Knowledge updates must use same-directory atomic activation and preserve old data on failure.'
);
zt_mutation_assert(
    strpos($knowledgePage, 'const KNOWLEDGE_CSRF_TOKEN = <?= json_encode(') !== false
        && strpos($knowledgePage, "'X-CSRF-Token': KNOWLEDGE_CSRF_TOKEN") !== false,
    'Knowledge Core UI must send the session CSRF token.'
);

zt_mutation_assert(
    strpos($feedbackApi, "'POST required.'") !== false
        && strpos($feedbackApi, 'zt_csrf_validate($csrf_token)') !== false
        && zt_no_raw_exception_response($feedbackApi),
    'Feedback writes must require POST/CSRF and hide database errors.'
);
zt_mutation_assert(
    strpos($studio, "fetch('/api_feedback.php'") !== false
        && strpos($studio, 'message: msg, csrf_token: ZT_SESSION_CSRF_TOKEN') !== false
        && strpos($studio, "'X-CSRF-Token': ZT_SESSION_CSRF_TOKEN") !== false,
    'Studio feedback caller must send the session CSRF token.'
);

foreach ([
    'subscription cancellation' => $cancelApi,
    'subscription activation' => $subscribeApi,
    'upgrade confirmation' => $upgradeApi,
] as $label => $source) {
    zt_mutation_assert(
        strpos($source, "'application/json'") !== false
            && strpos($source, 'zt_csrf_validate(') !== false
            && strpos($source, 'current_user_record(true)') !== false,
        ucfirst($label) . ' must require JSON, shared CSRF, and a live enabled user.'
    );
}
zt_mutation_assert(
    strpos($upgradePage, 'csrf_token: csrfToken') !== false
        && strpos($upgradeView, 'csrf_token: csrfToken') !== false,
    'Both upgrade views must send CSRF with the verified PayPal callback.'
);
zt_mutation_assert(
    strpos($templatesApi, 'zt_csrf_validate(') !== false
        && strpos($templatesApi, "'JSON request required.'") !== false
        && zt_no_raw_exception_response($templatesApi)
        && strpos($templatesPage, 'csrf_token: csrfToken') !== false,
    'Custom research-template writes must require JSON/CSRF and hide internal errors.'
);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . ' of ' . $checks . " mutation security checks failed\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo 'OK: ' . $checks . " mutation security checks passed\n";
