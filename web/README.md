# ZeroThink DNA Lab and full research application

This is the restored original PHP ZeroThink application by Shafaet Brady Hussain,
with the later DNA Lab **v1.7.1** release. It includes the actual research studio,
DNA parser and evidence engine, ancestry/origin modules, traits, local sound and
portrait tools, exports, API vault and historical account-backed web interfaces.
The lightweight Node engine at the repository root is a separate companion.

## Run DNA Lab without a database or account

Install PHP 8.2 or newer, with SQLite3 enabled for reference-pack matching. From
this `web` directory:

```console
php -d upload_max_filesize=32M -d post_max_size=160M -d memory_limit=1024M -S 127.0.0.1:8787 local-router.php
```

Open **http://127.0.0.1:8787/dna**. The loopback router runs the real DNA interface
and selected DNA upload actions without MySQL, Google login or a subscription.
Keep the server bound to `127.0.0.1`. Stop it with Ctrl+C when finished. It rejects
external hosts, remote clients, cross-origin requests and non-DNA API routes.

Uploads require consent and CSRF protection. Raw-file saving is opt-in. Optional
saves use `ZEROTHINK_PRIVATE_STORAGE_ROOT`, or `.zerothink_storage` next to this
web folder by default. Store that directory privately. The local router serves
only reviewed assets and DNA endpoints; it never serves the storage directory.
Local mode blocks DNA Agent and external research services, including inherited
offload configuration. Font Awesome's remote stylesheet is omitted in local mode.

An offline command-line report also works without MySQL or a model:

```console
php scripts/dna-report.php /path/to/private-raw-dna.txt /path/to/new-private-report.md
```

Choose a new output path. The report contains sensitive summaries even though it
omits raw genotype rows. The command does not copy the raw input or call an API.

## Included capabilities and separate references

| Included source | Required extra reference material |
| --- | --- |
| Raw consumer/VCF parsing, call quality, build/orientation gates | None for file checks |
| Health research cards with exact-allele matching | Installed allele-aware ClinVar pack for reference matches |
| Reference ancestry and regional comparisons | Licensed ancestry and regional packs |
| Published composition and GrafAnc integrations | Pinned, separately installed model bundles |
| Ancient/archaic exploration and evidence ledger | Aligned ancient/archaic packs |
| Trait associations, sound and artistic portrait controls | Coverage/source requirements reported by each module |
| GEDCOM and lineage claim ledger | User-provided records and optional lineage reference pack |
| Markdown and readable/print exports | Browser print for PDF |

**No reference databases, participant genotypes or model binaries are included.**
Missing references stay unavailable, including in Markdown. A clone without those
packs cannot reproduce an installation that has them. It must not invent ancestry
percentages or claim those sources are currently being expanded.

`scripts/update_dna_data_packs.sh`, the builders, `config/`, `schemas/` and
`sql/dna_pack_schema.sql` retain the original source-pack contracts. Read a builder
before running it: downloads are explicit, can be large, and remain subject to the
source's terms. `scripts/reference-models/` preserves the later model/ancient
builders. Those historical build recipes contain the original operator's Linux
staging paths; adapt them deliberately to your machine. They are not an automatic
one-command reproduction of the sealed production bundles.

`ZEROTHINK_GRAFANC_BUNDLE` and `ZEROTHINK_COMPOSITION_BUNDLE` select installed
bundles. Runtime checks verify their pinned manifests, dependencies and hashes.
The published-model execution paths currently require the documented Linux
Python/resource-limit runtime; this is separate from running the base PHP lab on
Windows. Do not weaken verification to make an unverified bundle load.

## Account-backed full web application

The full studio, model connections, API vault, research templates, paper/PDF tools,
CLI pairing and account/admin source are retained. They require a separate
self-hosted installation: PHP extensions, Composer dependencies, an empty MySQL
database and your own provider/auth settings. This publication does not deploy a
website or copy the previous production account system.

1. Install Composer dependencies with `composer install --no-dev` in `web`.
2. Create a new MySQL database. `sql/application-schema.sql` contains **only empty
   historical CREATE TABLE definitions**, with foreign-key checks restored after
   import. Existing application migrations add later feature tables/columns; this
   is not a tested reproduction of the private production schema.
3. Copy `private/config.example.php` to the ignored `private/config.local.php`,
   or configure `ZEROTHINK_DB_DSN`, `ZEROTHINK_DB_USER`, `ZEROTHINK_DB_PASSWORD`
   in the server process environment. Set your own Google OAuth values and vault
   encryption key. `.env.example` lists configuration names and empty examples.
   The DB environment fallback reads process variables; the example PHP config
   does likewise. Merely copying `.env.example` does not configure DB access.
4. Configure HTTPS and the supplied Apache deny rules; equivalent nginx rules
   must protect source/config/data/runtime paths too. Do not use the development
   server for a public account-backed installation.
5. Create and manage your own users through your reviewed auth deployment. Only
   explicit administrator roles grant architect privileges: the previous owner's
   email address or historical user ID is not an authorization mechanism.

Hosted payments and provider credentials remain optional deployment integrations,
not preconfigured services. A complete public hosted-service security review,
live OAuth/payment/provider integration test and database migration audit are
still needed before exposing that mode to users. Local DNA tests do not establish
those separate properties.

## Scientific and licensing boundaries

Outputs support research, not diagnosis, treatment, nationality/religion assignment
or proof of named/royal ancestry. Model proportions, allele similarity, artistic
portraits and sonification have distinct meanings; no test suite proves clinical
validity, biological accuracy, novelty or quantum advantage.

Original project code is Apache-2.0 under the repository license, except files
with their own notices. `scripts/reference-models/composition_worker.py` is
**GPL-3.0-or-later**, including its attributed `nearPD` adaptation. Reference data,
GrafAnc, bigsnpr, external libraries, map and other artwork retain their licenses.
See [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md). Installed reference material
is excluded rather than implicitly relicensed.
