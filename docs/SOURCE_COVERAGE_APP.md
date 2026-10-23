# ZeroThink application source coverage audit — 23 October 2026

This audit compares the restored `web/` source with the desktop `ZeroThink`, `DEPLOY_ZEROTHINK`, July original/full-audit trees, July frozen runtime/validation archives and post-gate deltas, and the two July reasoning/over-refusal staging copies. It concerns application source and presentation assets. It is not a claim that every historic experiment, deployment file, dataset or running service is included, or that all hosted features work without installation and configuration.

## What the comparison establishes

At the audit baseline, the published repository included 179 PHP files and 22 Python files, plus JavaScript/CSS/HTML, schemas, tests and CLI assets. The comparison excluded private/runtime directories, vendor dependencies, backup copies and credential configuration. Paths and source hashes were compared; function-name inventories helped distinguish newer functionality from safety rewrites. Later research-tool additions are documented separately in [DNA_RESEARCH_TOOLS.md](../web/docs/DNA_RESEARCH_TOOLS.md).

| Application component | Coverage observed |
| --- | --- |
| Original PHP front end | Landing, studio, research, DNA, library, history, media, document creation, quantum, administration and account/payment page source is present. |
| Original PHP backend | Corresponding APIs, bootstrap, routing/authentication, provider catalogue, research helpers, library/DNA engine, vault/security and subscription components are present. Private configuration and user rows are excluded. |
| DNA engine | Restored PHP analysis/reporting source and later interpretation/privacy modules are present. The public engine contains substantially more DNA safety/evidence handling than the older main desktop copy. Pack contents and individual genomes are separate inputs. |
| CLI and supporting services | Original `cli-assets/zerothink.py`, PHP CLI entry points and IBM/Quantum Zero Python service source are present, alongside the independent portable Node CLI. External service credentials, runtimes and model installations are not included. |
| Newer local-model aliases | July desktop Qwen3 Q5/F16 aliases and their catalogue entries were missing from the frozen-base restoration. They are now restored without changing the current default or copying the desktop request-process implementation. |
| Static presentation assets | The missing PWA manifest and referenced Quantum Zero video are restored. Existing manifest icon targets are present. The local DNA-only router's scope is unchanged. |

No additional substantial PHP, Python or JavaScript application module was found solely in the compared main desktop tree. Remaining missing executable paths are principally historical database repair/admin injection/probe utilities and production host operations. This is a bounded finding over the named copies, not proof of universal completeness.

The three source files in `postgate-review-delta-v3.tgz` matched the restored source byte-for-byte at audit time. Differences from earlier frozen files include later DNA/privacy handling and today's local-mode/security changes; hashes should not be mistaken for evidence that the older file is preferable.

## Restored assets and provenance

| Restored path under `web/` | Origin | Size | SHA-256 |
| --- | --- | ---: | --- |
| `manifest.json` | Main desktop `ZeroThink/manifest.json`, unchanged | 395 bytes | `d3f57268c3a10bfafa051cc5767d85af6f394ed7848c2f6fbf3dfe4799c7a1dd` |
| `assets/video/quantum_zero_agent.mp4` | Main desktop presentation asset, unchanged; same bytes in July full-audit tree | 5,870,221 bytes | `90f88a1427fe1059f5a3aa10c2cd1da7cb29a2ff2635734127417613084da4a5` |

The manifest is ordinary PWA metadata: name, short name, start URL, display, colours and two existing icon references. It has no cryptographic `key` field and contains no API credential. This is distinct from an extension manifest's public identity key.

The H.264 video is about ten seconds long, 784 × 1168 pixels at 24 fps; all 241 frames decoded successfully. Sampled first, middle and last frames show a stylized maze/brain scene and faceless suited figure, without visible personal records, DNA or credentials. Its local project provenance is recorded; a separate generator or third-party rights record was not located, so this audit does not independently establish third-party rights. The university logo remains excluded because local possession does not establish permission to redistribute that institution's branding.

## Narrow newer-code recovery

The main desktop `api_agent.php` had a 31 July modification time and added `openzeroqwen3-q5`, `openzeroqwen3-f16` and canonical `zero-qwen3-*:latest` forms. The restored dispatch map now accepts all eight exact legacy/canonical aliases and the catalogue offers the two canonical IDs. These are names for separately installed local models; there is no automatic download, live-availability or inference-quality assertion.

The current OpenZero Ministral default and other compatibility aliases remain unchanged. The desktop file also contained a different `proc_open` request path. That process implementation was not copied over the hardened public backend. Model alias recovery does not require that transport change.

Original source hashes for this narrow recovery:

- Main desktop `api_agent.php`: `0bdc399894431f56eead7c4358784d021963b5953606d5fef17d32ba64c0a78a`.
- Main desktop `app/core/model_catalog.php`: `7006e349f8c92bd302b2a2c9c7f7ef1d171e0bc64f660d4a01292e857287f389`.

## Portable operational tool and deliberate exclusions

`web/ops/build_safe_bundle.ps1` is a portable replacement for the historical source bundler. It exports a clean, committed Git snapshot with `git archive`, refuses existing output files and output inside the repository, and rejects common tracked runtime/private paths and symbolic links. It does not copy untracked files, deploy, alter services or delete directories. Publication still requires source, secret and licence review; filename guards cannot prove that a source tree is free of secrets.

The historical July full-audit bundler was 6,225 bytes, SHA-256 `19b70277927276311e7e76397e86c7fa56ee6bad6f25dbc3dbee7dd9e610d2ea`. Its owner-specific defaults, recursive staging cleanup and broader runtime-copy behaviour were replaced rather than republished unchanged. To archive an already reviewed, committed checkout, choose a new ZIP path outside the repository:

```powershell
pwsh -File web/ops/build_safe_bundle.ps1 -OutputZip ../ZeroThink-reviewed-source.zip
```

The following exclusions are intentional:

- Production watchdog/maintenance service scripts, systemd units/timers, firewall hooks and live web-server configuration. These can restart owner services or alter the host and are not required to run application source locally.
- Historical database seeding, repair, injection, privilege-granting, backup/restore, session-test and diagnostic web scripts. Their absence should not be addressed by restoring administrative shortcuts or populated databases. The empty public schema and explicit setup contract replace that deployment state.
- Downloaded databases/packs, raw DNA, uploads, user memories, runtime storage, vendor trees, model weights, credentials, private configuration and third-party branding without verified redistribution rights.
- The optional historical `private/zpepper.php` interception/cache module. It was deliberately omitted because of its encoded/evaluated implementation and shared prompt-cache behavior; the guarded optional integration is not the DNA engine. Reproducing that plugin's former behavior is outside this public source restoration.
- Old text conversation/source bundles and unrelated third-party coding-agent checkouts. Filename references in early design notes do not establish a missing current runtime module or ownership of third-party code.

The extensionless DNA-pack compatibility installer and additional research builders are assessed in the separate DNA-tool restoration. They must not inherit historical owner-host tool paths or deployment assumptions.

## Validation and remaining scope

`web/scripts/tests/test_local_model_aliases.php` extracts and executes the actual pure API normalization/allowlist/provider helpers without bootstrapping an account, database or API. **24 checks passed**: eight unprefixed and eight provider-prefixed alias resolutions, catalogue/allowlist/provider consistency, rejection of an unreviewed model tag and preservation of the current default. Both changed PHP source files passed syntax validation. No inference, network or process request was made by the alias test.

The new bundler passed PowerShell syntax validation and synthetic-fixture checks for successful committed-source export, exclusion of an ignored synthetic environment file, refusal to replace an archive, refusal of dirty source and refusal of a committed runtime-data path. No real private data, service operation or deployment was used.

These checks validate the scoped recovery. They do not establish the complete hosted application's deployment, billing/authentication integration, configured provider access, downloaded reference-pack correctness, model availability, scientific calibration or production readiness. The database-free local entry point continues to provide its documented DNA-lab subset.
