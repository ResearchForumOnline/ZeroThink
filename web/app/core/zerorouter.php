<?php
declare(strict_types=1);

/**
 * ZeroRouter v3
 * - "Math of goodness" practical edition: safe, reliable, measurable routing.
 * - Computes risk/complexity/evidence-demand to pick provider+model.
 */
function zerorouter_score(string $prompt): array {
  $p = function_exists('mb_strtolower') ? mb_strtolower($prompt) : strtolower($prompt);
  $len = function_exists('mb_strlen') ? mb_strlen($p) : strlen($p);

  $risk = 0.0;
  $riskWords = ['password','exploit','hack','malware','credit card','ssn','dox','weapon','bomb','suicide','kill','terror'];
  foreach($riskWords as $w){ if(str_contains($p, $w)) $risk += 0.22; }

  $complex = 0.0;
  $complex += min(0.6, $len / 1600);
  $complexWords = ['prove','derive','optimize','theorem','complexity','formal','counterexample','rigor','benchmark'];
  foreach($complexWords as $w){ if(str_contains($p, $w)) $complex += 0.08; }

  $evidence = 0.0;
  $evidenceWords = ['cite','source','evidence','compare','audit','verify','calculate','test'];
  foreach($evidenceWords as $w){ if(str_contains($p, $w)) $evidence += 0.10; }

  // clamp
  $risk = max(0.0, min(1.0, $risk));
  $complex = max(0.0, min(1.0, $complex));
  $evidence = max(0.0, min(1.0, $evidence));

  // Quantum-ish mixing (stable, not "bs math")
  // base 3/6/9/10/20/42/52 influence: weight sets
  $w = [3=>0.11,6=>0.13,9=>0.17,10=>0.09,20=>0.15,42=>0.20,52=>0.15];
  $signal = $w[42]*$complex + $w[20]*$evidence + $w[9]*$risk + $w[6]*(($len%52)/52) + $w[3]*(($len%42)/42) + $w[10]*(($len%20)/20) + $w[52]*(($len%9)/9);
  $signal = max(0.0, min(1.0, $signal));

  return [
    'risk'=>$risk,
    'complexity'=>$complex,
    'evidence'=>$evidence,
    'signal'=>$signal
  ];
}

function zerorouter_pick(array $scores, array $availableProviders, bool $agentMode): array {
  // Preference ladders; if key missing, skip.
  $risk = (float)$scores['risk'];
  $complex = (float)$scores['complexity'];
  $evidence = (float)$scores['evidence'];

  // If high-risk, choose "most controllable" provider (policy + stable) — prefer OpenAI/Anthropic if present.
  if($risk >= 0.55){
    foreach(['openai','anthropic','google','xai','groq'] as $p){
      if(in_array($p, $availableProviders, true)) return ['provider'=>$p,'model'=>default_model($p, $agentMode, true)];
    }
  }

  // If agent mode, lean towards stronger reasoning
  if($agentMode && $complex >= 0.45){
    foreach(['openai','anthropic','xai','google','groq'] as $p){
      if(in_array($p, $availableProviders, true)) return ['provider'=>$p,'model'=>default_model($p, true, false)];
    }
  }

  // Fast lane for low complexity
  if($complex < 0.25 && $evidence < 0.25){
    foreach(['groq','xai','openai','google','anthropic'] as $p){
      if(in_array($p, $availableProviders, true)) return ['provider'=>$p,'model'=>default_model($p, $agentMode, false)];
    }
  }

  // Balanced
  foreach(['xai','openai','google','anthropic','groq'] as $p){
    if(in_array($p, $availableProviders, true)) return ['provider'=>$p,'model'=>default_model($p, $agentMode, false)];
  }

  return ['provider'=>'none','model'=>'none'];
}

function default_model(string $provider, bool $agentMode, bool $highRisk): string {
  $m = app_config("providers.$provider.default_model", '');
  if($m) return $m;
  // safe generic fallbacks
  if($provider==='xai') return 'grok-4.5';
  if($provider==='openai') return $agentMode ? 'gpt-5.6-luna' : 'gpt-5.4-mini';
  if($provider==='google') return $agentMode ? 'gemini-3.5-flash' : 'gemini-3.5-flash';
  if($provider==='anthropic') return $agentMode ? 'claude-3-5-sonnet-latest' : 'claude-3-haiku-latest';
  if($provider==='groq') return $agentMode ? 'openai/gpt-oss-120b' : 'openai/gpt-oss-20b';
  return 'default';
}
