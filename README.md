# ZeroThink — DNA Lab and Research Workbench

The original PHP ZeroThink application and DNA Lab v1.7.1 are restored in
[`web/`](web/README.md). This is the large application: its DNA backend, research
studio, model/API connections, command-line client and web interfaces. The small
Node engine at the repository root is a separate companion, documented in
[docs/LOCAL_ENGINE.md](docs/LOCAL_ENGINE.md).

Created by **Shafaet Brady Hussain**. The source restoration combines the audited
July application and security fixes with the September DNA releases. The
follow-up completeness audit also restores omitted reference builders,
validation/evaluation utilities and referenced presentation assets. See
[restoration scope and provenance](docs/FULL_APPLICATION_RESTORATION.md).

## Explore the original application

| Component | Source |
| --- | --- |
| DNA Lab interface and upload/report workflows | [`web/dna.php`](web/dna.php), [`web/api_library.php`](web/api_library.php) |
| DNA parser, quality/build/orientation checks, evidence ledger and lineage | [`web/app/zero_library.php`](web/app/zero_library.php) |
| Origins, ancestry composition, regional references, traits and customer reports | [`web/app/`](web/app/) |
| Original large Python command-line client | [`web/cli-assets/zerothink.py`](web/cli-assets/zerothink.py) |
| Research workbench, studio and paper/PDF tools | [`web/studio.php`](web/studio.php), [`web/research-paper-creator.php`](web/research-paper-creator.php) |
| Agent, model/provider connections, media and quantum interfaces | [`web/api_agent.php`](web/api_agent.php), [`web/api_quantum_zero.php`](web/api_quantum_zero.php), [`web/quantumibm-python/`](web/quantumibm-python/) |
| DNA reference builders and research validation source | [`web/scripts/`](web/scripts/), [`web/scripts/reference-models/`](web/scripts/reference-models/) |
| Recovered DNA evaluation/research utilities and complete candidate ledger | [`web/scripts/reference-models/SOURCE_INVENTORY.json`](web/scripts/reference-models/SOURCE_INVENTORY.json), [tool provenance](web/docs/DNA_RESEARCH_TOOLS.md) |
| Local Node research companion | [`engine.cjs`](engine.cjs), [`cli.cjs`](cli.cjs), [companion setup and tests](docs/LOCAL_ENGINE.md) |

The interface and algorithms are source code. Separately licensed reference
datasets, calibrated model bundles and service credentials are additional inputs;
publishing code does not manufacture those inputs or validate a biological claim.

Reviewed reference distributions have a separate [dataset catalogue](https://github.com/ResearchForumOnline/research-datasets)
and [datasets-2026.10.23 release](https://github.com/ResearchForumOnline/research-datasets/releases/tag/datasets-2026.10.23).
The DNA pack IDs are `composition-grch37-2026.09.05` and
`ancient-origins-grch37-2026.09.05`. Their installation, provenance and licences
travel with the reference release; this source checkout does not download them.
The public composition pack supports GRCh37 only and requires the documented
Linux Python/resource controls. Read [pinned bundle compatibility](docs/COMPOSITION_BUNDLES.md)
before installing it. Dataset publication is separate from application-source publication.

## Run the local DNA Lab

Install PHP 8.2 or newer; enable SQLite3 for installed reference packs. From `web`:

```console
php -d upload_max_filesize=32M -d post_max_size=160M -d memory_limit=1024M -S 127.0.0.1:8787 local-router.php
```

Open **http://127.0.0.1:8787/dna**. This mode runs the real DNA interface without
MySQL, login or a subscription. It requires consent, keeps CSRF/loopback controls,
and blocks external DNA Agent/offload requests. Missing reference packs remain
unavailable. Raw-file saving is opt-in; reports can still contain sensitive
summaries. See [full setup, pack requirements and limits](web/README.md).

An offline report command is also included, run from `web`:

```console
php scripts/dna-report.php /path/to/private-input.txt /path/to/new-private-report.md
```

The account-backed web mode requires its own Composer dependencies, database,
authentication/provider settings and deployment review. Publication of its source
is separate from deployment and hosted-service acceptance testing.

## What is excluded

Private DNA and participant data, account/database rows, credentials, runtime
storage, uploads, downloaded references/model binaries, dependencies and unsafe
historical database/host repair utilities are excluded. Original local files were
preserved. The included SQL contains empty schema definitions only. Historical
archives with thousands of vendor files are not additional modern application
code; the coverage documents explain the distinction and deliberate exclusions.

## Verify and contribute

```console
npm run check
python tools/check_web_source.py --php php
```

Checks use synthetic fixtures and local HTTP endpoints. They cover source
hygiene, parser/report/privacy contracts, static asset completeness, consent,
CSRF and local routing. They do not establish medical validity, ancestry
accuracy, scientific novelty or quantum advantage. Dataset-dependent research
evaluations need their documented, separately installed inputs.

Contributions should preserve provenance, consent, privacy, source limitations
and explicit provider selection. Do not submit personal DNA, private reports,
credentials or account records. The author's preserved papers and research
artifacts are in the [research repository](https://github.com/ResearchForumOnline/research).

## Licenses

Original project code is Apache-2.0 except individually licensed components and
third-party material. The independently invoked composition worker is
GPL-3.0-or-later. Dataset/provider/dependency terms remain separate. Read the
[third-party notices](web/THIRD_PARTY_NOTICES.md) and component licenses. This
release does not relicense separately released noncommercial ZMath code or
private former-service data.
