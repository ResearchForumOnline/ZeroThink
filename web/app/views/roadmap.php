<?php
$site = site_settings();
$brand = $site['brand'] ?? 'ZeroThink';
?>
<div class="section">
  <div class="container">
    <h1 class="h1"><?= e($brand) ?> Roadmap</h1>
    <p class="sub">Built fast, shipped clean. Here’s what’s next.</p>

    <div class="timeline">
      <div class="titem card pad">
        <div class="tag">Now</div>
        <h3 style="margin:10px 0 6px">v3.2 Shock UI + ZeroAgents</h3>
        <div class="sub">Agent pipeline (depth 1–3), secure external config (/home/talkto/zerothinks), enhanced landing + pricing.</div>
      </div>

      <div class="titem card pad">
        <div class="tag">Next</div>
        <h3 style="margin:10px 0 6px">Streaming + Live Tool Calls</h3>
        <div class="sub">Server-sent events (SSE) streaming responses + tool hooks (Docs search, JSON validate, code lint) using PHP-only.</div>
      </div>

      <div class="titem card pad">
        <div class="tag">Soon</div>
        <h3 style="margin:10px 0 6px">Team Workspaces</h3>
        <div class="sub">Org accounts, shared vault, per-workspace docs, and admin policy controls.</div>
      </div>

      <div class="titem card pad">
        <div class="tag">Later</div>
        <h3 style="margin:10px 0 6px">Arena Mode</h3>
        <div class="sub">Run the same prompt across providers and score results (quality/safety/latency), then auto-learn routing weights.</div>
      </div>
    </div>

    <div class="card pad" style="margin-top:16px">
      <h2 style="margin:0 0 8px">Want a feature fast?</h2>
      <p class="sub">If you tell me the exact UX (what buttons, what output, what limits), I can map it into PHP routes + views cleanly.</p>
      <a class="btn" href="/studio">Back to Studio</a>
    </div>
  </div>
</div>
