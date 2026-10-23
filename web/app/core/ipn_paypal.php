<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/subscription_security.php';

function zt_paypal_ipn_verified(string $raw): bool
{
    if ($raw === '' || strlen($raw) > 131072 || !function_exists('curl_init')) {
        return false;
    }

    $response = '';
    $ch = curl_init('https://ipnpb.paypal.com/cgi-bin/webscr');
    $options = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'cmd=_notify-validate&' . $raw,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Connection: Close'],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response): int {
            if (strlen($response) + strlen($chunk) > 1024) {
                return 0;
            }
            $response .= $chunk;
            return strlen($chunk);
        },
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    curl_setopt_array($ch, $options);
    $ok = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_errno($ch);
    curl_close($ch);
    return $ok !== false && $error === 0 && $status === 200 && trim($response) === 'VERIFIED';
}

function zt_paypal_ipn_allowed_amount(string $rawAmount, string $currency): bool
{
    if (strtoupper(trim($currency)) !== 'USD' || !is_numeric($rawAmount)) {
        return false;
    }
    $amount = (float) $rawAmount;
    $configured = zt_subscription_config('PAYPAL_IPN_ALLOWED_AMOUNTS_USD', ['paypal', 'ipn_allowed_amounts_usd'], '5,50');
    foreach (explode(',', $configured) as $allowed) {
        if (is_numeric(trim($allowed)) && abs($amount - (float) trim($allowed)) < 0.005) {
            return true;
        }
    }
    return false;
}

function ipn_paypal_handle(): void
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '' || strlen($raw) > 131072) {
        http_response_code(400);
        echo 'NO_INPUT';
        return;
    }
    if (!zt_paypal_ipn_verified($raw)) {
        http_response_code(200);
        echo 'INVALID';
        return;
    }

    parse_str($raw, $data);
    $expectedReceiver = zt_paypal_receiver_email();
    $receiver = strtolower(trim((string) ($data['receiver_email'] ?? $data['business'] ?? '')));
    if (!filter_var($expectedReceiver, FILTER_VALIDATE_EMAIL) || !hash_equals($expectedReceiver, $receiver)) {
        error_log('ZeroThink ignored a verified PayPal IPN with an unconfigured or mismatched receiver.');
        http_response_code(200);
        echo 'RECEIVER_REJECTED';
        return;
    }

    $txnType = strtolower(trim((string) ($data['txn_type'] ?? '')));
    $paymentStatus = strtoupper(trim((string) ($data['payment_status'] ?? '')));
    $subscriptionId = strtoupper(trim((string) ($data['subscr_id'] ?? $data['recurring_payment_id'] ?? '')));
    $custom = trim((string) ($data['custom'] ?? ''));
    $signedUserId = zt_paypal_custom_id_user_id($custom);
    $legacyUserId = ctype_digit($custom) ? (int) $custom : 0;
    $userId = $signedUserId > 0 ? $signedUserId : $legacyUserId;

    if (!zt_paypal_valid_subscription_id($subscriptionId)) {
        http_response_code(200);
        echo 'SUBSCRIPTION_REJECTED';
        return;
    }

    $paymentTypes = ['subscr_payment', 'recurring_payment'];
    $cancelTypes = ['subscr_cancel', 'subscr_eot', 'recurring_payment_suspended', 'recurring_payment_profile_cancel'];
    try {
        $db = db();
        $db->beginTransaction();

        if (in_array($txnType, $paymentTypes, true)) {
            $amount = (string) ($data['mc_gross'] ?? $data['amount'] ?? '');
            $currency = (string) ($data['mc_currency'] ?? $data['currency_code'] ?? '');
            if ($paymentStatus !== 'COMPLETED' || !zt_paypal_ipn_allowed_amount($amount, $currency)) {
                $db->rollBack();
                http_response_code(200);
                echo 'PAYMENT_REJECTED';
                return;
            }

            // Existing linked subscriptions remain bound even if a legacy IPN
            // omits custom. New links require a signed or numeric user binding.
            $linked = $db->prepare('SELECT id FROM users WHERE subscription_id = ? LIMIT 1 FOR UPDATE');
            $linked->execute([$subscriptionId]);
            $linkedUserId = (int) $linked->fetchColumn();
            $userId = $linkedUserId > 0 ? $linkedUserId : $userId;
            if ($userId <= 0) {
                $db->rollBack();
                http_response_code(200);
                echo 'USER_REJECTED';
                return;
            }

            $target = $db->prepare('SELECT id, disabled FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
            $target->execute([$userId]);
            $user = $target->fetch(PDO::FETCH_ASSOC);
            if (!$user || !empty($user['disabled'])) {
                $db->rollBack();
                http_response_code(200);
                echo 'USER_REJECTED';
                return;
            }

            $duplicate = $db->prepare('SELECT id FROM users WHERE subscription_id = ? AND id <> ? LIMIT 1 FOR UPDATE');
            $duplicate->execute([$subscriptionId, $userId]);
            if ($duplicate->fetchColumn()) {
                $db->rollBack();
                http_response_code(200);
                echo 'DUPLICATE_REJECTED';
                return;
            }

            $update = $db->prepare("UPDATE users
                SET plan = CASE WHEN plan = 'god_mode' THEN 'god_mode' ELSE 'pro' END,
                    is_pro = 1,
                    subscription_status = 'active',
                    subscription_id = ?,
                    subscription_end = NULL
                WHERE id = ? AND COALESCE(disabled, 0) = 0
                LIMIT 1");
            $update->execute([$subscriptionId, $userId]);
            $db->commit();
            http_response_code(200);
            echo 'OK';
            return;
        }

        if (in_array($txnType, $cancelTypes, true)) {
            $update = $db->prepare("UPDATE users
                SET plan = CASE WHEN plan = 'god_mode' THEN 'god_mode' ELSE 'free' END,
                    is_pro = CASE WHEN plan = 'god_mode' THEN 1 ELSE 0 END,
                    subscription_status = 'cancelled',
                    subscription_end = NOW()
                WHERE subscription_id = ?
                LIMIT 1");
            $update->execute([$subscriptionId]);
            $db->commit();
            http_response_code(200);
            echo $update->rowCount() > 0 ? 'CANCELLED' : 'IGNORED';
            return;
        }

        $db->rollBack();
    } catch (Throwable $e) {
        if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('ZeroThink PayPal IPN storage failed.');
        http_response_code(500);
        echo 'STORAGE_ERROR';
        return;
    }

    http_response_code(200);
    echo 'IGNORED';
}
