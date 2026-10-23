# Featherless and OpenZero Vault Setup

This patch adds Pro-only vault support for:

- Featherless API key (`api_key_featherless`)
- ZeroThink/OpenZero API key (`api_key_zerothink`)
- encrypted vault columns for provider keys when a server-side master key is configured

## Model Policy

Local OpenZero models must stay under 15 GB. The current safe local lane is:

- `glm4:9b-q5` - GLM 4 9B Q5, about 6.6 GB GGUF
- `hermes3:8b-llama3.1-q5_K_M` - fallback
- `gemma4:e4b` - fallback

Large models such as GLM 5.2 are exposed only through Featherless hosted mode, not as local downloads.

## Featherless

Featherless uses an OpenAI-compatible API base:

```txt
https://api.featherless.ai/v1
```

Users must provide their own Featherless key in Neural Vault. The UI labels this as Pro/paid hosted because large hosted models may require a paid Featherless plan.

Allowed Featherless model aliases:

- `shafire/talktoaiZERO`
- `zai-org/GLM-5.2`
- `zai-org/GLM-4.5`
- `Qwen/Qwen3-Coder-480B-A35B-Instruct`
- `deepseek-ai/DeepSeek-V3.2`
- `moonshotai/Kimi-K2-Instruct`

## OpenZero API

Set these outside the public web root or in the existing private environment system:

```txt
ZEROTHINK_API_BASE_URL=https://your-openzero-gateway.example/v1
ZEROTHINK_API_KEY=oz_live_xxx
ZEROTHINK_OPENZERO_TRIAL_LIMIT=5
```

`OPENZERO_API_BASE_URL` and `OPENZERO_API_KEY` are also accepted.

The backend builds only `/v1/chat/completions` from the configured base URL. Users cannot supply URLs.

ZeroThink now uses this order for OpenZero keys:

1. An inline key sent with the request.
2. The user's saved OpenZero Machine API Key in Neural Vault.
3. The site-level OpenZero key above, as a shared trial fallback.

The shared site fallback is intentionally limited to 5 messages per Pro account by default. After that, the user is told to install OpenZero, create an API key on their own node, and save that key in Neural Vault. This avoids silently burning shared CPU on the TalkToAI/OpenZero machines.

Do not add browser-controlled OpenZero upstream URLs. If a user's own OpenZero server should be reachable from ZeroThink web, register the upstream server-side through a controlled deployment path instead of accepting arbitrary URLs from the client.

## Vault Encryption

Generate a private base64 key on the server and store it outside the public web root:

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

Configure:

```txt
ZEROTHINK_VAULT_MASTER_KEY_B64=base64_key_here
```

After this is configured, new vault saves write encrypted `*_cipher` values and clear the matching plaintext key column. Existing plaintext values are still readable as fallback until the user saves again.

Vault load responses do not return full saved API keys to the browser. They return saved/not-saved flags; users can enter a new value to replace a key or use Wipe to clear all vault secrets.

## Manual Database Step

After a database backup, run:

```bash
mysql -u USER -p DATABASE < sql/20260628_190304_secure_vault_featherless_openzero.sql
mysql -u USER -p DATABASE < sql/20260628_195643_openzero_trial_usage.sql
```

Do not drop old plaintext columns on live until all users have resaved keys and a rollback plan exists.

## Rollback

Disable the UI/provider by restoring the backed-up PHP files. The new nullable columns can remain in the database harmlessly.
