<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = (string) file_get_contents($root . '/api_chat_manager.php');
$checks = 0;
$failures = [];

function zt_chat_assert(bool $condition, string $message): void {
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

zt_chat_assert(
    strpos($source, "if (\$method === 'GET')") !== false
        && strpos($source, "if (\$action === 'list')") !== false
        && strpos($source, "if (\$action === 'get')") !== false,
    'GET must remain an explicitly read-only list/get branch.'
);
zt_chat_assert(
    strpos($source, "'JSON request required.'") !== false
        && strpos($source, 'json_decode((string) file_get_contents(\'php://input\'), true)') !== false,
    'Chat mutations must require a JSON POST body.'
);
zt_chat_assert(
    strpos($source, 'zt_csrf_validate($csrf_token)') !== false
        && strpos($source, 'HTTP_X_CSRF_TOKEN') !== false,
    'Chat mutations must pass shared CSRF validation.'
);
zt_chat_assert(
    strpos($source, '$db->beginTransaction()') !== false
        && strpos($source, '$db->commit()') !== false
        && strpos($source, '$db->rollBack()') !== false,
    'Chat deletion must be transactional with rollback support.'
);
zt_chat_assert(
    strpos($source, 'SELECT id FROM chats WHERE id = ? AND user_id = ? FOR UPDATE') !== false,
    'Chat deletion must lock and verify the owned chat before deleting children.'
);
zt_chat_assert(
    strpos($source, 'INNER JOIN chats c ON c.id = m.chat_id') !== false
        && strpos($source, 'WHERE m.chat_id = ? AND c.user_id = ?') !== false,
    'Message deletion must restate chat ownership in the destructive SQL.'
);
zt_chat_assert(
    strpos($source, "DELETE FROM messages WHERE chat_id = ?") === false,
    'The old unscoped cross-account message deletion must not return.'
);
zt_chat_assert(
    strpos($source, 'DELETE FROM chats WHERE id = ? AND user_id = ?') !== false
        && strpos($source, '$delete_chat->rowCount() !== 1') !== false,
    'Parent chat deletion must remain owner-scoped and verify one affected row.'
);
zt_chat_assert(
    strpos($source, 'catch (Throwable $e)') !== false
        && strpos($source, "'request_id' => \$request_id") !== false
        && !preg_match('/[\'\"]message[\'\"]\s*=>\s*\$e->getMessage\s*\(/', $source),
    'Unexpected errors must be logged behind a generic request-id response.'
);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . ' of ' . $checks . " chat manager security checks failed\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo 'OK: ' . $checks . " chat manager security checks passed\n";
