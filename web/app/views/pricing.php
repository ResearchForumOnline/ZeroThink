<?php
$site = site_settings();
$brand = $site['brand'] ?? 'ZeroThink';
$paypal = $site['paypal_email'] ?? 'paypal@talktoai.org';
$sol = $site['solana_address'] ?? '';
?>
<div class="section">
  <div class="hero hero-slim" style="background-image:url('/assets/img/hero_banner.jpg')">
    <div class="hero-overlay"></div>
    <div class="container">
      <h1 class="h1"><?= e($brand) ?> Pricing</h1>
      <p class="sub">Start free. Upgrade when you’re ready to run deeper agents, bigger context, and faster routing.</p>
    </div>
  </div>

  <div class="container">
    <div class="grid3">
      <div class="card pad price">
        <div class="tag">Starter</div>
        <div class="price-num">$0</div>
        <div class="sub">Bring your own API keys. Core Studio + Agent Mode.</div>
        <ul class="list">
          <li>Docs grounding (RAG-lite)</li>
          <li>Depth 1–2 agents</li>
        </ul>
        <a class="btn" href="/register">Start free</a>
      </div>

      <div class="card pad price glow">
        <div class="tag">Pro</div>
        <div class="price-num">$19<span class="muted">/mo</span></div>
        <div class="sub">For daily builders. Max agent depth + higher limits.</div>
        <ul class="list">
          <li>Depth 3 (Research → Critic → Final)</li>
          <li>Priority routing + logs</li>
          <li>Higher doc limits</li>
        </ul>

        <div class="paybox">
          <div class="sub">PayPal subscription (manual):</div>
          <div class="mono"><?= e($paypal) ?></div>
          <div class="sub" style="margin-top:8px">Or donate via Solana:</div>
          <div class="mono break"><?= e($sol) ?></div>
        </div>

        <a class="btn" href="/login">Go Pro</a>
      </div>

      <div class="card pad price">
        <div class="tag">Enterprise</div>
        <div class="price-num">Custom</div>
        <div class="sub">White-label + multi-tenant + SLAs.</div>
        <ul class="list">
          <li>Team workspaces</li>
          <li>Policy controls</li>
          <li>Custom routing rules</li>
        </ul>
        <a class="btn secondary" href="/compare">See the engine</a>
      </div>
    </div>

    <div class="card pad" style="margin-top:18px">
      <h2 style="margin:0 0 8px">What you’re actually paying for</h2>
      <div class="sub">Most “AI apps” are one model and a textbox. ZeroThink is a routing + agent framework that turns your keys into a real intelligence stack.</div>
      <div class="grid2" style="margin-top:14px">
        <div class="mini">
          <div class="h3">ZeroAgents</div>
          <div class="sub">Sequential specialist passes like modern Python agent stacks—built in pure PHP.</div>
        </div>
        <div class="mini">
          <div class="sub">Pick the right provider/model automatically based on risk, complexity, and evidence need.</div>
        </div>
      </div>
    </div>
  </div>
</div>
