# ZeroThink Account API Keys

ZeroThink account API keys are issued from `/master` by an architect/admin user.
They are separate from provider keys in Neural Vault. Provider keys call upstream
services; account API keys authenticate a user account without a Google web
session.

Do not confuse these with the OpenZero Machine API Key in Neural Vault:

- `ztapi_...` authenticates a ZeroThink account.
- `oz_...` authenticates an OpenZero node/gateway as a model provider.
- Featherless keys authenticate Featherless hosted models.

## Master workflow

1. Sign in with the admin Google account.
2. Open `/master`.
3. Use `Create ZeroThink API Key` for `shafaet85@gmail.com`, or use the per-user
   `CREATE API KEY` button in the user table.
4. Copy the `ztapi_...` token immediately. The plaintext token is shown once.
5. Use the key as `Authorization: Bearer ztapi_...`.

Revoking from `/master` marks active tokens as revoked. Tokens are not deleted,
so there is an audit trail with hint, label, creator, creation time, last-used
time, expiry, and revoke reason.

## Server fallback

After the migration is applied, this command can mint the admin key without raw
SQL:

```bash
php scripts/mint_zerothink_api_key.php shafaet85@gmail.com "Shafaet admin ZeroThink API"
```

## Database

Run after a database backup:

```sql
SOURCE sql/20260628_191941_zerothink_api_tokens.sql;
```

The app also creates the table lazily if the DB user has `CREATE TABLE`
permission, but the migration is safer for production change control.
