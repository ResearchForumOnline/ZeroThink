<?php
// ZERO VAULT MANAGER - v2.3
// Authenticated, same-session JSON API for encrypted provider-key metadata.

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

ob_start();
require_once __DIR__ . '/app/bootstrap.php';
if (file_exists(__DIR__ . '/app/core/vault_security.php')) {
    require_once __DIR__ . '/app/core/vault_security.php';
}
ob_end_clean();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function zt_vault_reply($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    zt_vault_reply(['status' => 'error', 'message' => 'POST required.'], 405);
}

$user = function_exists('current_user_record') ? current_user_record() : null;
$uid = (int) ($user['id'] ?? 0);
if ($uid <= 0) {
    zt_vault_reply(['status' => 'error', 'message' => 'Not logged in.'], 403);
}

$content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (strpos($content_type, 'application/json') !== 0) {
    zt_vault_reply(['status' => 'error', 'message' => 'JSON request required.'], 415);
}

$inputRaw = (string) file_get_contents('php://input');
$in = json_decode($inputRaw, true);
if (!is_array($in)) {
    zt_vault_reply(['status' => 'error', 'message' => 'Invalid JSON request.'], 400);
}

$action = strtolower(trim((string) ($in['action'] ?? '')));
if (!in_array($action, ['load', 'save', 'wipe'], true)) {
    zt_vault_reply(['status' => 'error', 'message' => 'Unknown vault action.'], 400);
}

$csrf_token = trim((string) ($in['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($csrf_token)) {
    zt_vault_reply([
        'status' => 'error',
        'message' => 'Your secure session expired. Refresh Studio and try again.',
    ], 403);
}

function ensure_vault_user_columns($db) {
    static $ready = false;
    if ($ready || !$db) {
        return;
    }

    $required = [
        'api_key_groq',
        'api_key_openai',
        'api_key_gemini',
        'api_key_xai',
        'api_key_nvidia',
        'api_key_quantum',
        'api_key_ibm_quantum',
        'api_key_featherless',
        'api_key_zerothink',
        'ibm_quantum_instance',
        'ibm_quantum_region',
        'serper_api_key',
        'system_prompt',
    ];

    if (function_exists('zt_vault_secret_fields')) {
        foreach (zt_vault_secret_fields() as $plainColumn) {
            $cipherColumn = zt_vault_cipher_column($plainColumn);
            if (!in_array($cipherColumn, $required, true)) {
                $required[] = $cipherColumn;
            }
        }
    }

    foreach ($required as $column) {
        try {
            $check = $db->query("SHOW COLUMNS FROM users LIKE " . $db->quote($column));
            if (!$check || !$check->fetch(PDO::FETCH_ASSOC)) {
                $db->exec("ALTER TABLE users ADD COLUMN `{$column}` TEXT NULL");
            }
        } catch (Throwable $e) {
            // Ignore patch failures here; the calling code will surface useful DB errors if needed.
        }
    }

    $ready = true;
}

try {
    $db = db();
    ensure_vault_user_columns($db);
    $isPro = function_exists('zt_vault_user_is_pro') ? zt_vault_user_is_pro($db, (int) $uid) : false;
    $secretFields = function_exists('zt_vault_secret_fields') ? zt_vault_secret_fields() : [];
    $canEncrypt = function_exists('zt_vault_can_encrypt') && zt_vault_can_encrypt();

    if ($action === 'save') {
        if (!$canEncrypt) {
            foreach ($secretFields as $inputKey => $plainColumn) {
                if (array_key_exists($inputKey, $in) && trim((string) $in[$inputKey]) !== '') {
                    zt_vault_reply([
                        'status' => 'error',
                        'message' => 'Vault encryption is temporarily unavailable. No provider secrets were changed.',
                    ], 503);
                }
            }
        }
        $sets = [];
        $params = [];

        foreach ($secretFields as $inputKey => $plainColumn) {
            if (!array_key_exists($inputKey, $in)) {
                continue;
            }
            if (in_array($inputKey, ['featherless', 'zerothink_api'], true) && !$isPro) {
                continue;
            }

            $value = trim((string) ($in[$inputKey] ?? ''));
            $cipherColumn = zt_vault_cipher_column($plainColumn);
            if ($canEncrypt) {
                $sets[] = "`{$plainColumn}` = NULL";
                $sets[] = "`{$cipherColumn}` = ?";
                $params[] = zt_vault_encrypt_secret($value);
            } else {
                $sets[] = "`{$plainColumn}` = ?";
                $sets[] = "`{$cipherColumn}` = NULL";
                $params[] = $value;
            }
            unset($_SESSION[$plainColumn]);
        }

        $plainTextFields = [
            'ibm_instance' => 'ibm_quantum_instance',
            'ibm_region' => 'ibm_quantum_region',
            'persona' => 'system_prompt',
        ];
        foreach ($plainTextFields as $inputKey => $column) {
            if (!array_key_exists($inputKey, $in)) {
                continue;
            }
            $sets[] = "`{$column}` = ?";
            $params[] = (string) ($in[$inputKey] ?? '');
        }

        if ($sets) {
            $params[] = $uid;
            $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        unset($_SESSION['serper_api_key'], $_SESSION['ibm_quantum_instance'], $_SESSION['ibm_quantum_region']);

        zt_vault_reply([
            'status' => 'success',
            'message' => 'Vault Saved.',
            'encrypted' => $canEncrypt,
            'pro' => $isPro,
        ]);
    } 
    elseif ($action === 'load') {
        $columns = [
            'ibm_quantum_instance',
            'ibm_quantum_region',
            'system_prompt',
        ];
        foreach ($secretFields as $plainColumn) {
            $columns[] = $plainColumn;
            $columns[] = zt_vault_cipher_column($plainColumn);
        }
        $selectColumns = implode(', ', array_map(static function ($column) {
            return '`' . str_replace('`', '', $column) . '`';
        }, array_unique($columns)));

        $stmt = $db->prepare("SELECT {$selectColumns} FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) $row = [];

        $data = [
            'ibm_quantum_instance' => (string) ($row['ibm_quantum_instance'] ?? ''),
            'ibm_quantum_region' => (string) ($row['ibm_quantum_region'] ?? ''),
            'system_prompt' => (string) ($row['system_prompt'] ?? ''),
            'vault_encrypted' => $canEncrypt,
            'is_pro' => $isPro,
        ];

        foreach ($secretFields as $inputKey => $plainColumn) {
            if (in_array($inputKey, ['featherless', 'zerothink_api'], true) && !$isPro) {
                $data[$plainColumn] = '';
                $data[$plainColumn . '_saved'] = false;
                continue;
            }
            $secret = zt_vault_secret_from_row($row, $plainColumn);
            $data[$plainColumn] = '';
            $data[$plainColumn . '_saved'] = $secret !== '';
        }

        zt_vault_reply(['status' => 'success', 'data' => $data]);
    }
    elseif ($action === 'wipe') {
        $sets = [
            'ibm_quantum_instance=NULL',
            'ibm_quantum_region=NULL',
            'system_prompt=NULL',
        ];
        foreach ($secretFields as $plainColumn) {
            $sets[] = "`{$plainColumn}`=NULL";
            $sets[] = "`" . zt_vault_cipher_column($plainColumn) . "`=NULL";
            unset($_SESSION[$plainColumn]);
        }
        unset($_SESSION['serper_api_key'], $_SESSION['ibm_quantum_instance'], $_SESSION['ibm_quantum_region']);

        $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute([$uid]);
        zt_vault_reply(['status' => 'success', 'message' => 'Wiped']);
    }
} catch (Throwable $e) {
    try {
        $request_id = bin2hex(random_bytes(6));
    } catch (Throwable $id_error) {
        $request_id = substr(hash('sha256', microtime(true) . ':' . getmypid()), 0, 12);
    }
    error_log('ZeroThink vault error [' . $request_id . ']: ' . $e->getMessage());
    zt_vault_reply([
        'status' => 'error',
        'message' => 'The vault request could not be completed safely. Try again or contact support with reference ' . $request_id . '.',
        'request_id' => $request_id,
    ], 500);
}
