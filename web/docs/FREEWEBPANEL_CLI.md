# ZeroThink FreeWebPanel CLI

ZeroThink CLI includes a first-class FreeWebPanel server operations mode:

```bash
zerothink freewebpanel ACTION [options]
zerothink fwp ACTION [options]
```

`fwp` is an exact alias for `freewebpanel`.

## Start here

```bash
zerothink fwp help
zerothink fwp status
zerothink fwp doctor --verbose
zerothink fwp health --json
```

The status and health commands report:

- detected panel root
- operating system and Python runtime
- disk capacity, use, and free space
- FreeWebPanel executable availability
- nginx, Apache, PHP-FPM, MariaDB/MySQL, SSL, and panel service state
- known configuration and log paths
- warnings such as missing services or low disk space

## Focused checks

```bash
zerothink fwp config
zerothink fwp config --verbose --lines 40
zerothink fwp domains --domain example.com
zerothink fwp apache
zerothink fwp php
zerothink fwp ssl --domain example.com
zerothink fwp permissions --path /var/www/example.com
zerothink fwp logs --lines 100
zerothink fwp logs --log-path /var/log/nginx/error.log --lines 60
```

Configuration and log previews redact common passwords, tokens, secrets, private keys, database passwords, SMTP passwords, and API keys by default.

## Backup

Always preview backup scope first:

```bash
zerothink fwp backup --path /opt/freewebpanel --dry-run
```

Create the reviewed archive:

```bash
zerothink fwp backup \
  --path /opt/freewebpanel \
  --include-config \
  --output /root/freewebpanel-backups \
  --yes
```

Database credentials are never guessed or printed. If `--include-db` is selected, the CLI records that a separate provider-approved database dump is required.

## Install, repair, update, and deploy

Change-oriented commands print a plan first:

```bash
zerothink fwp install --server-mode ubuntu --hostname panel.example.com --email admin@example.com --dry-run
zerothink fwp install --server-mode rhel-family --hostname panel.example.com --email admin@example.com --preflight-only
zerothink fwp install --server-mode openzero-aios --hostname web.example.com --email admin@example.com --dry-run
zerothink fwp repair --backup-first --dry-run
zerothink fwp update --backup-first --dry-run
zerothink fwp deploy --path ./release --domain example.com --backup-first --dry-run
```

After reviewing the plan, use `--yes` on the target Linux server. Ubuntu is the primary lane. AlmaLinux, Rocky Linux, and other supported RHEL-family servers use the RHEL-family lane. OpenZero and ZeroMint AIOS servers use the OpenZero AIOS lane. Install downloads only the official HTTPS installer selected for the chosen lane. ZeroThink checks the Bash header, reports the SHA-256 digest, requires Linux root, and removes the temporary script after execution. Repair, update, and deploy require the local FreeWebPanel executable.

Existing-panel replacement is handled as a migration workflow, not a casual install. Use the replacement flag only after verified off-server backups:

```bash
zerothink fwp install --server-mode ubuntu --replace-panel generic --confirm-panel-replace BACKUP_READY --hostname panel.example.com --email admin@example.com --dry-run
```

## Automation

Use JSON for scripts and monitors:

```bash
zerothink fwp health --json
zerothink fwp permissions --path /var/www/example.com --json
zerothink fwp domains --domain example.com --json
```

Useful shared flags:

- `--dry-run`
- `--backup-first`
- `--path PATH`
- `--domain DOMAIN`
- `--server-mode MODE`
- `--json`
- `--verbose`
- `--quiet`
- `--redact`
- `--no-redact`
- `--non-interactive`
- `--yes`

## Safety model

- Read-only inspection is the default.
- Changes require explicit approval.
- Backup paths are shown before an archive is written.
- Secret redaction is enabled by default.
- No site-owned API key is required.
- Unknown installer scripts are not fetched or executed.
- Existing ZeroThink SSH, SCP, and remote-file commands remain available for saved server profiles.
