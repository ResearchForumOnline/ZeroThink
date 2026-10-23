<?php
declare(strict_types=1);

function providers_available(int $uid): array {
  $st = db()->prepare("SELECT provider FROM api_keys WHERE user_id=?");
  $st->execute([$uid]);
  $rows = $st->fetchAll();
  return array_values(array_map(fn($r)=>$r['provider'], $rows));
}

function provider_get_key(int $uid, string $provider): ?string {
  $st = db()->prepare("SELECT key_cipher FROM api_keys WHERE user_id=? AND provider=?");
  $st->execute([$uid, $provider]);
  $row = $st->fetch();
  if(!$row) return null;
  $master = app_config('security.master_key_b64','');
  if(!$master) throw new RuntimeException("Master key missing");
  return crypto_decrypt($row['key_cipher'], $master);
}

function provider_save_key(int $uid, string $provider, string $keyPlain): void {
  $master = app_config('security.master_key_b64','');
  if(!$master) throw new RuntimeException("Master key missing");
  $cipher = crypto_encrypt($keyPlain, $master);
  db()->prepare("INSERT INTO api_keys(user_id,provider,key_cipher,created_at) VALUES(?,?,?,?)
                 ON DUPLICATE KEY UPDATE key_cipher=VALUES(key_cipher)")
    ->execute([$uid, $provider, $cipher, now()]);
  log_event('keys_saved', ['provider'=>$provider]);
}

function provider_chat(string $provider, string $model, string $apiKey, array $messages, array $opts=[]): array {
  if($provider === 'openai') return provider_openai($model, $apiKey, $messages, $opts);
  if($provider === 'google') return provider_gemini($model, $apiKey, $messages, $opts);
  if($provider === 'anthropic') return provider_anthropic($model, $apiKey, $messages, $opts);
  if($provider === 'groq') return provider_groq($model, $apiKey, $messages, $opts);
  if($provider === 'nvidia') return provider_nvidia($model, $apiKey, $messages, $opts);
throw new RuntimeException("Unknown provider");
}

function curl_json(string $url, array $headers, array $body, int $timeout=35): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_TIMEOUT=>$timeout,
    CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json'], $headers),
    CURLOPT_POSTFIELDS=>json_encode($body),
  ]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if($resp === false) throw new RuntimeException("cURL error: ".$err);
  $json = json_decode($resp, true);
  if($code < 200 || $code >= 300) {
    $msg = is_array($json) ? (json_encode($json)) : $resp;
    throw new RuntimeException("Provider HTTP $code: ".$msg);
  }
  if(!is_array($json)) throw new RuntimeException("Bad JSON from provider");
  return $json;
}

// OpenAI-compatible
function provider_openai(string $model, string $key, array $messages, array $opts): array {
  $url = rtrim(app_config('providers.openai.base_url','https://api.openai.com'),'/') . '/v1/chat/completions';
  $body = [
    'model'=>$model,
    'messages'=>$messages,
    'temperature'=>$opts['temperature'] ?? 0.7,
  ];
  $json = curl_json($url, ['Authorization: Bearer '.$key], $body);
  $text = $json['choices'][0]['message']['content'] ?? '';
  return ['text'=>$text,'raw'=>$json];
}

// Gemini (Generative Language API) - simple text mode
function provider_gemini(string $model, string $key, array $messages, array $opts): array {
  $base = rtrim(app_config('providers.google.base_url','https://generativelanguage.googleapis.com'),'/');
  $url = $base . '/v1beta/models/'.urlencode($model).':generateContent?key='.urlencode($key);

  $parts = [];
  foreach($messages as $m){
    $role = $m['role'] ?? 'user';
    $text = $m['content'] ?? '';
    $parts[] = ['role'=>$role==='assistant'?'model':'user', 'parts'=>[['text'=>$text]]];
  }
  $body = [
    'contents'=>$parts,
    'generationConfig'=>[
      'temperature'=>$opts['temperature'] ?? 0.7,
    ]
  ];
  $json = curl_json($url, [], $body);
  $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
  return ['text'=>$text,'raw'=>$json];
}

// Anthropic (Messages API)
function provider_anthropic(string $model, string $key, array $messages, array $opts): array {
  $url = rtrim(app_config('providers.anthropic.base_url','https://api.anthropic.com'),'/') . '/v1/messages';
  // Convert: system + user/assistant
  $system = '';
  $anth = [];
  foreach($messages as $m){
    if(($m['role'] ?? '') === 'system'){ $system .= ($m['content'] ?? '')."\n"; continue; }
    $anth[] = ['role'=>$m['role'], 'content'=>$m['content']];
  }
  $body = [
    'model'=>$model,
    'max_tokens'=> $opts['max_tokens'] ?? 800,
    'temperature'=>$opts['temperature'] ?? 0.7,
    'system'=> trim($system),
    'messages'=>$anth
  ];
  $headers = [
    'x-api-key: '.$key,
    'anthropic-version: 2023-06-01'
  ];
  $json = curl_json($url, $headers, $body);
  $text = $json['content'][0]['text'] ?? '';
  return ['text'=>$text,'raw'=>$json];
}

// Groq (OpenAI-compatible)
function provider_groq(string $model, string $key, array $messages, array $opts): array {
  $url = rtrim(app_config('providers.groq.base_url','https://api.groq.com/openai'),'/') . '/v1/chat/completions';
  $body = [
    'model'=>$model,
    'messages'=>$messages,
    'temperature'=>$opts['temperature'] ?? 0.7,
    'max_tokens'=>$opts['max_tokens'] ?? 32768,
  ];
  $json = curl_json($url, ['Authorization: Bearer '.$key], $body);
  $text = $json['choices'][0]['message']['content'] ?? '';
  return ['text'=>$text,'raw'=>$json];
}

// Nvidia NIM (DeepSeek / Llama 3)
function provider_nvidia(string $model, string $key, array $messages, array $opts): array {
  $url = 'https://integrate.api.nvidia.com/v1/chat/completions';
  $body = [
    'model'=>$model,
    'messages'=>$messages,
    'temperature'=>$opts['temperature'] ?? 0.6,
    'max_tokens'=> 4096
  ];
  $json = curl_json($url, ['Authorization: Bearer '.$key], $body);
  $text = $json['choices'][0]['message']['content'] ?? '';
  return ['text'=>$text,'raw'=>$json];
}

