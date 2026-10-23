<?php
declare(strict_types=1);

function crypto_encrypt(string $plaintext, string $masterKeyB64): string {
  $master = base64_decode($masterKeyB64, true);
  if($master === false) throw new RuntimeException("Bad master key");
  // Prefer libsodium
  if(function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
    $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, '', $nonce, $master);
    return 'v1s:' . base64_encode($nonce.$cipher);
  }
  // Fallback: OpenSSL AES-256-GCM
  $nonce = random_bytes(12);
  $tag = '';
  $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', hash('sha256',$master,true), OPENSSL_RAW_DATA, $nonce, $tag);
  if($cipher === false) throw new RuntimeException("Encrypt failed");
  return 'v1g:' . base64_encode($nonce.$tag.$cipher);
}

function crypto_decrypt(string $packed, string $masterKeyB64): string {
  $master = base64_decode($masterKeyB64, true);
  if($master === false) throw new RuntimeException("Bad master key");
  if(str_starts_with($packed,'v1s:')) {
    $bin = base64_decode(substr($packed,4), true);
    if($bin === false) throw new RuntimeException("Bad cipher");
    $nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
    $nonce = substr($bin,0,$nonceLen);
    $cipher = substr($bin,$nonceLen);
    return sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher,'',$nonce,$master);
  }
  if(str_starts_with($packed,'v1g:')) {
    $bin = base64_decode(substr($packed,4), true);
    if($bin === false) throw new RuntimeException("Bad cipher");
    $nonce = substr($bin,0,12);
    $tag = substr($bin,12,16);
    $cipher = substr($bin,28);
    $pt = openssl_decrypt($cipher,'aes-256-gcm', hash('sha256',$master,true), OPENSSL_RAW_DATA, $nonce, $tag);
    if($pt === false) throw new RuntimeException("Decrypt failed");
    return $pt;
  }
  throw new RuntimeException("Unknown cipher version");
}
