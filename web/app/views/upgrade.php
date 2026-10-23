<?php
$upgradeUser = current_user_record(true);
if (!$upgradeUser) {
    header('Location: /auth/google?return=' . rawurlencode('/upgrade'));
    exit;
}
$paypalReady = zt_paypal_server_verification_ready();
$paypalPlans = zt_paypal_plan_ids();
$paypalBinding = zt_paypal_custom_id((int) $upgradeUser['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upgrade | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="/assets/css/index.css?v=35">
    <?php if ($paypalReady): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars(rawurlencode(zt_paypal_client_id()), ENT_QUOTES, 'UTF-8') ?>&vault=true&intent=subscription" data-sdk-integration-source="button-factory"></script>
    <?php endif; ?>
    <style>
        body { overflow-y: auto; }
        .pricing-container { display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; padding: 50px 20px; max-width: 1000px; margin: 0 auto; }
        .price-card { background: rgba(10, 10, 10, 0.9); border: 1px solid #333; border-radius: 12px; padding: 40px; width: 350px; text-align: center; position: relative; overflow: hidden; }
        .price-card:hover { transform: translateY(-5px); border-color: #a855f7; }
        .price-card.popular { border: 1px solid #a855f7; box-shadow: 0 0 20px rgba(168, 85, 247, 0.2); }
        .badge { background: #a855f7; color: #000; padding: 5px 15px; font-weight: bold; position: absolute; top: 20px; right: -30px; transform: rotate(45deg); width: 100px; font-size: 0.8rem; }
        .plan-name { font-size: 1.5rem; color: #fff; margin-bottom: 10px; font-family: 'Courier New'; }
        .price { font-size: 3rem; color: #fff; font-weight: 800; margin-bottom: 20px; }
        .period { font-size: 1rem; color: #666; }
        .features { list-style: none; padding: 0; text-align: left; margin-bottom: 30px; color: #aaa; }
        .features li { padding: 10px 0; border-bottom: 1px solid #222; }
        .features li::before { content: "✓"; color: #0f0; margin-right: 10px; }
        .back-link { position: absolute; top: 20px; left: 20px; color: #666; text-decoration: none; }
        .support-box { margin-top: 40px; padding: 20px; border-top: 1px solid #333; color: #aaa; font-size: 0.9rem; }
        .support-title { color: #fff; font-weight: bold; margin-bottom: 10px; }
        .billing-notice { color: #f6c86b; border: 1px solid #6a5124; background: rgba(106,81,36,.18); border-radius: 8px; padding: 12px; line-height: 1.45; }
    </style>
</head>
<body>
    <a href="/studio" class="back-link">← Return to Studio</a>
    <div style="text-align:center; margin-top:40px;">
        <img src="/assets/img/logo_nobg.png" style="width:60px;">
        <h2 style="color:#fff;">ZeroThink <span style="color:#a855f7">Upgrade</span></h2>
    </div>
    <div class="pricing-container">
        <div class="price-card">
            <div class="plan-name">OPERATOR</div>
            <div class="price">$5<span class="period">/mo</span></div>
            <ul class="features">
                <li>ZeroThink Logic Access</li>
                <li>Sovereign Reasoning Engine</li>
                <li>Priority Processing</li>
            </ul>
            <?php if ($paypalReady): ?>
                <div id="paypal-monthly"></div>
            <?php else: ?>
                <p class="billing-notice">Secure online activation is temporarily unavailable, so no payment can be initiated here. Contact support if you need access now.</p>
            <?php endif; ?>
        </div>
        <div class="price-card popular">
            <div class="badge">BEST VALUE</div>
            <div class="plan-name">SOVEREIGN</div>
            <div class="price">$50<span class="period">/yr</span></div>
            <ul class="features">
                <li>All Monthly Features</li>
                <li>2 Months Free</li>
                <li>Sovereign Badge</li>
            </ul>
            <?php if ($paypalReady): ?>
                <div id="paypal-yearly"></div>
            <?php else: ?>
                <p class="billing-notice">Payment buttons will return after server-side PayPal verification is configured. Your account has not been charged.</p>
            <?php endif; ?>
        </div>
        
        <div style="width:100%; text-align:center;">
            <div class="support-box">
                <div class="support-title">NEED HELP CHOOSING?</div>
                <p>Use monthly access for quick experiments, yearly access for regular ZeroThink work, or contact support for private research and implementation help.</p>
                <a href="/faq" style="color:#a855f7; text-decoration:none;">Read the FAQ</a>
                <span style="color:#777;"> / </span>
                <a href="mailto:shaf@talktoai.org" style="color:#00ffff; text-decoration:none;">Contact support</a>
            </div>
        </div>
    </div>
    <?php if ($paypalReady): ?>
    <script>
    const csrfToken = <?= json_encode(zt_csrf_token(), JSON_UNESCAPED_SLASHES) ?>;
    const accountBinding = <?= json_encode($paypalBinding, JSON_UNESCAPED_SLASHES) ?>;
    const paypalPlans = <?= json_encode($paypalPlans, JSON_UNESCAPED_SLASHES) ?>;
    paypal.Buttons({
        style: { shape: 'rect', color: 'silver', layout: 'vertical', label: 'subscribe' },
        createSubscription: function(data, actions) { return actions.subscription.create({ plan_id: paypalPlans.monthly, custom_id: accountBinding }); },
        onApprove: function(data, actions) { activateSub(data.subscriptionID, 'monthly'); }
    }).render('#paypal-monthly');

    paypal.Buttons({
        style: { shape: 'rect', color: 'gold', layout: 'vertical', label: 'subscribe' },
        createSubscription: function(data, actions) { return actions.subscription.create({ plan_id: paypalPlans.yearly, custom_id: accountBinding }); },
        onApprove: function(data, actions) { activateSub(data.subscriptionID, 'yearly'); }
    }).render('#paypal-yearly');

    async function activateSub(subID, plan) {
        alert("Processing Sovereign Access...");
        try {
            const response = await fetch('/api_subscribe.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ sub_id: subID, plan: plan, csrf_token: csrfToken }) });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status !== 'success') {
                alert(payload.message || 'Payment was approved, but ZeroThink could not verify access yet. Please retry or contact support.');
                return;
            }
            window.location.href = '/studio';
        } catch (error) {
            alert('ZeroThink could not confirm the upgrade right now. Your PayPal approval was not silently discarded; please retry or contact support.');
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>
