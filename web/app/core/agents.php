<?php
declare(strict_types=1);

/**
 * ZeroAgents (PHP)
 * Emulates multi-agent orchestration frameworks commonly built in Python,
 * but stays pure-PHP: sequential calls + structured messages + internal scratchpads.
 *
 * Depth levels:
 * 1 = single-pass (fast)
 * 2 = Research -> Final
 * 3 = Research -> Critic -> Final (max quality)
 */

function agent_run_chat(array $args): array {
  $prov = $args['provider'];
  $model = $args['model'];
  $apiKey = $args['apiKey'];
  $prompt = $args['prompt'];
  $persona = $args['persona'] ?? 'zero';
  $scores = $args['scores'] ?? ['risk'=>0.0,'complexity'=>0.0,'evidence'=>0.0];
  $context = $args['context'] ?? '';
  $sources = $args['sources'] ?? [];
  $depth = (int)($args['depth'] ?? 1);
  $depth = max(1, min(3, $depth));

  $meta = [
    'depth'=>$depth,
    'steps'=>[],
  ];

  $baseSystem = agent_system_prompt($persona, true, $scores);

  $groundingSystem = '';
  if($context){
    $groundingSystem = "You may use the following user-provided documents as grounding. If you cite, cite as (doc <id>). Do not invent citations.\n\n".$context;
  }

  // Step 1: Research (internal)
  $research = '';
  if($depth >= 2){
    $sys = "You are ZeroThink Research Agent. Produce:\n- Key facts to look up / verify\n- Assumptions\n- Plan of attack\n- Questions that must be answered\nKeep it tight. Do NOT write the final answer.";
    $msgs = [['role'=>'system','content'=>$sys]];
    if($groundingSystem) $msgs[] = ['role'=>'system','content'=>$groundingSystem];
    $msgs[] = ['role'=>'user','content'=>$prompt];
    $r = provider_chat($prov, $model, $apiKey, $msgs, ['temperature'=>0.3,'max_tokens'=>650]);
    $research = trim((string)($r['text'] ?? ''));
    $meta['steps'][] = ['name'=>'research','tokens'=>$r['usage'] ?? null];
  }

  // Step 2: Critic (internal)
  $critique = '';
  if($depth >= 3){
    $sys = "You are ZeroThink Critic. Given the user's request and the research notes, identify:\n- Missing edge cases\n- Logical gaps\n- Safety concerns\n- Better structure\nReturn only actionable critique + improved outline. Do NOT write the final answer.";
    $msgs = [['role'=>'system','content'=>$sys]];
    if($groundingSystem) $msgs[] = ['role'=>'system','content'=>$groundingSystem];
    $msgs[] = ['role'=>'user','content'=>"."];
    $msgs[] = ['role'=>'user','content'=>"USER REQUEST:\n".$prompt."\n\nRESEARCH NOTES:\n".$research];
    $r = provider_chat($prov, $model, $apiKey, $msgs, ['temperature'=>0.25,'max_tokens'=>650]);
    $critique = trim((string)($r['text'] ?? ''));
    $meta['steps'][] = ['name'=>'critic','tokens'=>$r['usage'] ?? null];
  }

  // Step 3: Final
  $msgs = [['role'=>'system','content'=>$baseSystem]];
  if($groundingSystem) $msgs[] = ['role'=>'system','content'=>$groundingSystem];

  if($depth >= 2 && $research){
    $msgs[] = ['role'=>'system','content'=>"INTERNAL RESEARCH (do not reveal unless asked):\n".$research];
  }
  if($depth >= 3 && $critique){
    $msgs[] = ['role'=>'system','content'=>"INTERNAL CRITIQUE (do not reveal unless asked):\n".$critique];
  }

  // Force structure + show meta badges
  $scoresLine = "ROUTER SCORES: risk=".number_format((float)$scores['risk'],2).", complexity=".number_format((float)$scores['complexity'],2).", evidence=".number_format((float)$scores['evidence'],2);
  $msgs[] = ['role'=>'system','content'=>"Output requirements:\n- Start with a 1-line answer\n- Then sections with headings\n- Keep it high-signal\n- If you used docs, cite as (doc <id>)\n- Never fabricate sources\n".$scoresLine];

  $msgs[] = ['role'=>'user','content'=>$prompt];

  $r = provider_chat($prov, $model, $apiKey, $msgs, ['temperature'=>0.65,'max_tokens'=>1100]);
  $meta['steps'][] = ['name'=>'final','tokens'=>$r['usage'] ?? null];

  $text = trim((string)($r['text'] ?? ''));

  return ['text'=>$text,'meta'=>$meta,'sources'=>$sources];
}
