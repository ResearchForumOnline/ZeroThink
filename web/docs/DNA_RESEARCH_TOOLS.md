# DNA research tools: source recovery and boundaries

This recovery adds **28 distinct reusable research tools** alongside the existing
12 reference-model scripts. The 28 recovered originals total 125,971 bytes;
the adapted sources total 128,392 bytes. A shared explicit-path helper, synthetic
tests and this provenance inventory are additional maintenance code, not new
research algorithms. Ten existing builder/validator files also receive path or
runtime portability fixes; two existing source files remain unchanged.

The audit covers **204 script occurrences, 132 distinct SHA-256 hashes and
1,354,381 bytes after exact-content deduplication** across the seven September 5
DNA stage `scripts` folders. These counts include historical JavaScript snapshots,
deployment scripts and QA, not just Python algorithms. Every candidate has a named
decision in [SOURCE_INVENTORY.json](../scripts/reference-models/SOURCE_INVENTORY.json),
including all duplicate occurrences and a grouped unique-hash ledger. The companion-asset ledger also accounts for three CSS/HTML snapshot occurrences (two distinct hashes), ten generated synthetic QA JSON reports and two copies of third-party map JSON. No other script-folder file extensions were found outside Python bytecode caches. This is a
source recovery, not a claim that private historical analyses or every old stage
can be rerun unchanged.

The maintained DNA application UI and analysis modules are in `dna.php`, `app/`
and `assets/`. Most large excluded bytes are reference data, model assets,
dependencies, compressed duplicate source packages and generated outputs.
The July baseline `scripts/build_ancestry_reference.py` was already public; it is
not counted among the 28 additions.

## What is included

The additions recover regional source selection and aggregate pack construction,
leave-one-out HWE diagnostics, exploratory mixture feasibility, public-reference
alignment and composition evaluation, GrafAnc bundle assembly and bounded audits,
synthetic fixture generators, ancient pack checks, and explicit local retrieval of
selected public-reference byte ranges. They preserve research limitations, source
hash checks and cohort/coverage gates. No personal genotypes, reference databases,
model weights, donor-row files, private reports or generated evaluation outputs are
included.

All tools are command-line research utilities. Several historical builders execute
work at module scope and should be invoked as scripts, not imported as libraries.
The reusable sibling imports (`evaluate_bigsnpr_composition`,
`build_population_candidate`, `validate_regional_hwe`) do not open datasets or run
an evaluation on import. Required path configuration may fail immediately if absent.

## Local configuration and dependencies

Existing explicit CLI arguments are retained. `build_ancient_origins.py` now requires
`--data-root` explicitly. Historical absolute machine paths are replaced by the
following environment variables; **unset or relative paths fail**. Set only the
variables needed by the script you intentionally run. Outputs belong in a private
local working directory outside this source checkout.

| Variable | Meaning |
| --- | --- |
| `ZDNA_DATA_ROOT` | Site data root containing locally acquired `sources/` and aggregate `packs/` |
| `ZDNA_UPGRADE_ROOT` | Isolated population candidate working/output directory |
| `ZDNA_ORIGINS_ROOT` | Ancient/archaic staged pack and validation output directory |
| `ZDNA_ORIGINS_DATA` | Explicit ancient source metadata, selection and retrieved-range directory |
| `ZDNA_DISCOVERY_ROOT` | Isolated discovery fixture working directory |
| `ZDNA_ETHNICITY_ROOT` | Isolated regional feasibility working directory |
| `ZDNA_REGIONAL_DATA` | Current public regional metadata, selections and retrieved reference rows |
| `ZDNA_GRAFANC_ROOT` | GrafAnc prototype root containing `grafanc-source`, separately built executable and bundle |
| `ZDNA_COMPOSITION_ROOT` | Aggregate composition source, bundle and evaluation working directory |
| `ZDNA_FIXTURE_ROOT` | Isolated directory containing explicitly synthetic `tests/` fixtures |

Python dependencies vary: NumPy, SciPy, quadprog and cyvcf2 are used by relevant
builders/evaluators. GrafAnc utilities require a separately acquired/built upstream
GrafAnc executable and applicable HTSlib dependencies. Some bundle/permission and
native-executable steps retain POSIX runtime assumptions. Source path portability
does not establish support for every operating system or Python/package version.
No dependencies or reference data are automatically installed by the application.

Example synthetic-only verification from the repository root:

```text
python -B -m unittest discover -s web/scripts/reference-models -p test_synthetic_research.py -v
```

`download_regional_ranges.py --data-root LOCAL_DIRECTORY` reads a caller-prepared
`regional_selection.json`. Its optional `--selection`/`--manifest` arguments can
name an ancient selection and its expected output manifest. It accepts only the
official Harvard Dataverse HTTPS host, verifies 206 status, exact Content-Range and
length, rejects escaped/duplicate/pre-existing output targets, and hashes each row.
It does not claim to verify the full dataset checksum after fetching selected rows.
The original owner-specific SSH sink/ingest transport is excluded.

The extensionless compatibility wrapper passes Bash syntax validation and exact argument-forwarding checks against a temporary synthetic stub; it never executed the real downloader during verification.

Download helpers are explicit opt-in commands. `fetch_grafanc_public.py` acquires
pinned upstream code, notices, aggregate assets and a binary; the author-regression
helper separately downloads public individual test fixtures when intentionally run.
Those fixtures and all per-individual evaluation artifacts must remain private and
outside Git. No retrieval or reference evaluation was run for this recovery.

## Verification and interpretation

All 42 Python files in this directory parse with Python AST. Six synthetic unit
tests pass: missing/relative configuration rejection; constructed HWE cohort
separation with missing calls; invalid/all-missing/single-group HWE rejection;
mocked correct reference retrieval; output-path escape rejection before network;
and wrong-status/range/truncated-response rejection without writing a row.
The range tests use four invented bytes and a mocked HTTP response. No actual
participant input, downloaded model, private review asset or reference data is used.

These are plumbing and mathematical sanity checks. They do not establish consumer
ancestry accuracy, nationality/religion/community membership, clinical validity,
independent cohort calibration or applicability to a particular person's DNA.
Chromosome omission and constructed mixtures remain sensitivity diagnostics.
`evaluate_ashkenazi_trace.py` intentionally retains its accepted historical bundle
and worker SHA-256 pins; it refuses a different model until deliberately reviewed.

## Licensing

`evaluate_bigsnpr_composition.py` and the existing `composition_worker.py` are
GPL-3.0-or-later, with the Florian Prive (2022) method attribution and the
Martin Maechler/Jens Oehlschlaegel Matrix nearest-positive-definite adaptation
notice retained. `bigsnpr_additional_checks.py` and `validate_composition_bundle.py`
import that evaluator and carry an explicit GPL-3.0-or-later notice for the combined
program. See [COPYING.GPL-3.0](../scripts/reference-models/COPYING.GPL-3.0) and
[THIRD_PARTY_NOTICES.md](../THIRD_PARTY_NOTICES.md). The repository's general
Apache-2.0 grant does not relicense these GPL tools. Separately acquired GrafAnc,
reference data and native dependencies retain their upstream terms and notices.

## Recovery provenance

All original names below are source-relative stage identifiers, not personal
filesystem paths. The complete source SHA-256 and adapted destination hash are
also recorded in the machine inventory. No tool is renamed; all are co-located
under `web/scripts/reference-models/` to preserve ordinary sibling imports.
Numerical methods are retained. Path configuration is explicit; the HWE helper
also rejects structurally invalid inputs before calculating a diagnostic, and the
composition sealer no longer changes interpreter symlinks.

| Tool | Original source | Original SHA-256 | Recovery |
| --- | --- | --- | --- |
| `audit_public_modern_metadata.py` | `_dna_ethnicity_upgrade_20260905/scripts/audit_public_modern_metadata.py` | `e4da2145ab723c06cfa31ee1e48fdd686f37b9ee12f21a72676c5d309589f0be` | restored portable source |
| `audit_regional_candidate.py` | `_dna_ethnicity_upgrade_20260905/scripts/audit_regional_candidate.py` | `69d103861cf78ff5e3ca2cfb56abc173d3b7025c3ef07d8f6dd0548c74c8200f` | restored portable source |
| `bigsnpr_additional_checks.py` | `_dna_country_release_20260905/scripts/bigsnpr_additional_checks.py` | `622ab09b7b3633b48220bf3c8fb641e5311740668ea231db32a2c7fbee62d27b` | restored portable source |
| `build_ancient_origins.py` | `_dna_discovery_upgrade_20260905/scripts/build_ancient_origins.py` | `e8514fcfb054f56ac6cbcedc11f979905a8077eade2d1b83a2c3a9e134c61d7b` | existing builder path configuration |
| `build_archaic_markers.py` | `_dna_discovery_upgrade_20260905/scripts/build_archaic_markers.py` | `bf83a11b76993a19b13c305282653154b1642d1d871f7e3419ebd77c397e979b` | existing builder path configuration |
| `build_bigsnpr_public_challenge.py` | `_dna_country_release_20260905/scripts/build_bigsnpr_public_challenge.py` | `7f1cd7e038a2ff42cdf184d6293deca88bbe871fb480fc34380320c4a394f072` | restored portable source |
| `build_composition_bridge.py` | `_dna_country_release_20260905/scripts/build_composition_bridge.py` | `b577b411e1ec1924ce81e19f36ed0b2bc28727c954d1c8bc87a7b6fab643b6f5` | existing builder path configuration |
| `build_composition_union.py` | `_dna_country_release_20260905/scripts/build_composition_union.py` | `218df747a0cddf84a11a3cc13cef6ecb461571eaad15692beb20e8023583a756` | existing builder path configuration |
| `build_discovery_stress_fixture.py` | `_dna_discovery_upgrade_20260905/scripts/build_discovery_stress_fixture.py` | `9c4229bd47464b737b538a2ea276e0bff812c84d574cbe7411008287f727619d` | restored portable source |
| `build_discovery_traits_fixture.py` | `_dna_discovery_upgrade_20260905/scripts/build_discovery_traits_fixture.py` | `bab3db160d6505c9f78c046f1e77fa5a5a7756ad7208fdc333af73db284b1866` | restored portable source |
| `build_grafanc_bundle.py` | `_dna_customer_release_20260905/scripts/build_grafanc_bundle.py` | `94102f7503b86a7ec05a761c562edfbe1f570301e8e78d1fe644895c144ece20` | restored portable source |
| `build_origins_synthetic_fixture.py` | `_dna_discovery_upgrade_20260905/scripts/build_origins_synthetic_fixture.py` | `e96eca9688e4082d2dd8741f0ed49d91570a88d8e149492996581f5418a224ef` | restored portable source |
| `build_population_candidate.py` | `_dna_discovery_upgrade_20260905/scripts/build_population_candidate.py` | `fc499aeab5de99d99b133b6bdc5e012abdf64d66ddb985d32aed0a0837393cce` | existing builder path configuration |
| `build_reference_catalog.py` | `_dna_discovery_upgrade_20260905/scripts/build_reference_catalog.py` | `bb5d2610ffdff1fccc0558ad6f4563d772c9969c622fe9cbcb9b58a7a538cd6e` | existing builder path configuration |
| `build_regional_refs.py` | `_dna_ethnicity_upgrade_20260905/scripts/build_regional_refs.py` | `1e032863f3eedd293163e71c14383381451673fd2e096ca43f05e249d35f9511` | restored portable source |
| `check_grafanc_author_regression.py` | `_dna_customer_release_20260905/scripts/check_grafanc_author_regression.py` | `440c89831914a831c09b0983a1166d855a5e2d003ec04fdb644240ab3cfe4250` | restored portable source |
| `composition_worker.py` | `_dna_country_release_20260905/scripts/composition_worker.py` | `d859f5603c921a15eabcad4cecd5449460d55b02691bf556f5857252be5b079c` | existing builder path configuration |
| `create_grafanc_synthetic.py` | `_dna_customer_release_20260905/scripts/create_grafanc_synthetic.py` | `f91322135defa85e289302a1822238fe6d2d54cb15d7b866d4c52ee7bd400f99` | restored portable source |
| `create_regional_synthetic_fixture.py` | `_dna_ethnicity_upgrade_20260905/scripts/create_regional_synthetic_fixture.py` | `d592fb55fdf41fe2fc2264224c1041d1a236579456a37b909b72ea45b958d722` | restored portable source |
| `download_regional_ranges.py` | `_dna_ethnicity_upgrade_20260905/scripts/download_regional_ranges.py` | `c54b100ceb06f97668de1d8e751e7b86eaa745411c85592e65099154af881bf1` | restored portable source |
| `evaluate_ashkenazi_trace.py` | `_dna_validation_release_20260905/scripts/evaluate_ashkenazi_trace.py` | `b56dabd85424f87636806f62d54daa6e448492012d00bb2fd30df935fdb7c315` | restored portable source |
| `evaluate_bigsnpr_composition.py` | `_dna_country_release_20260905/scripts/evaluate_bigsnpr_composition.py` | `22f2b94592bbbdc0d334d130171372d3f9c5a473e3c662a16c12abdfabd7d67c` | restored portable source |
| `evaluate_grafanc_public.py` | `_dna_customer_release_20260905/scripts/evaluate_grafanc_public.py` | `ccbfbb69cf3c4d7ce99c74ec0c81948b5e295f6352464384eb1ce9d657faf8f3` | restored portable source |
| `fetch_bigsnpr_reference.py` | `_dna_country_release_20260905/scripts/fetch_bigsnpr_reference.py` | `62309b6713da0e867e9c36cf8b9aa6fa9959288a7637fbfb89e9170b4643922e` | existing builder path configuration |
| `fetch_grafanc_public.py` | `_dna_customer_release_20260905/scripts/fetch_grafanc_public.py` | `833d45780fbfb5af2cad6c28a56b38f7b5b5fb1470dddd5b4ee3301fc9e06a5d` | restored portable source |
| `make_myheritage_browser_fixture.py` | `_dna_discovery_upgrade_20260905/scripts/make_myheritage_browser_fixture.py` | `9240b72cf8a882cae7228dba863c1fbf474d8911553fe6a69b5ea81909bcb921` | restored portable source |
| `merge_synthetic_ancestry_fixture.py` | `_dna_ethnicity_upgrade_20260905/scripts/merge_synthetic_ancestry_fixture.py` | `ff6592ed63ba95e90b7e4fa7083617e7a40ef0d1858f2f9e005ef39cd8695577` | restored portable source |
| `repair_public_chr21.py` | `_dna_discovery_upgrade_20260905/scripts/repair_public_chr21.py` | `14ca56683153c4ad41892a6347bead9d859bd22fccaf9620e66ec533ac6111fe` | restored portable source |
| `seal_archaic_reference.py` | `_dna_discovery_upgrade_20260905/scripts/seal_archaic_reference.py` | `cbd3c6066c41571385ca7998ef55ed4c3533bc8c3e33f95778c8c12c840153bc` | restored portable source |
| `seal_composition_bundle.py` | `_dna_country_release_20260905/scripts/seal_composition_bundle.py` | `c1cea117e19c21e50e1a3792407bceeee91a39f97324b0d7211b0685977b3415` | existing builder path configuration |
| `select_ancient_sources.py` | `_dna_discovery_upgrade_20260905/scripts/select_ancient_sources.py` | `ca7ba2b53b6913139ef9739e7f6384103c7bf56a30088d37f4c0aa93f49c764b` | restored portable source |
| `select_regional_sources.py` | `_dna_ethnicity_upgrade_20260905/scripts/select_regional_sources.py` | `0bc4268f092c703d2a1eb9e0d01a386be2f314b579f5231eb096502a0944e0b1` | restored portable source |
| `validate_ancient_reference_alleles.py` | `_dna_discovery_upgrade_20260905/scripts/validate_ancient_reference_alleles.py` | `01fcc69ef4dfb147ec29bb3f8e8c8cd7824308d196630b21daa7123ffb926edc` | existing builder path configuration |
| `validate_ancient_source_rows.py` | `_dna_discovery_upgrade_20260905/scripts/validate_ancient_source_rows.py` | `f58f10bef9d7bcc171dd659dcb117e4f734f978d92d12e66bbf88b82d84243f5` | existing builder path configuration |
| `validate_composition_bundle.py` | `_dna_country_release_20260905/scripts/validate_composition_bundle.py` | `df0670c0af77a2ac6e1572547a67338350fdbad10b2fe5624851d83883ebf4ca` | existing builder path configuration |
| `validate_population_candidate_subset.py` | `_dna_discovery_upgrade_20260905/scripts/validate_population_candidate_subset.py` | `1430e1debc8e1b295fb36aa328dfd08d4daba90fa61786d698b7ce9fffd252ba` | restored portable source |
| `validate_regional_hwe.py` | `_dna_ethnicity_upgrade_20260905/scripts/validate_regional_hwe.py` | `6ea82cef2475196a1c471e2c44ec874b543a0b9d553cab8162bbd6e240ed1116` | restored portable source |
| `validate_regional_mixture.py` | `_dna_ethnicity_upgrade_20260905/scripts/validate_regional_mixture.py` | `0e2368fc55cbdcd48d82023926f8068ac9a87d93d7e5766f944a293d0379100f` | restored portable source |
| `verify_final_ancient_pack.py` | `_dna_discovery_upgrade_20260905/scripts/verify_final_ancient_pack.py` | `840e3a3d29f81126baca37bd5c835ea24b701d5825b37beb56d62564bb8d7514` | restored portable source |
| `verify_grafanc_forward_reference.py` | `_dna_customer_release_20260905/scripts/verify_grafanc_forward_reference.py` | `5e7ab7bb449726c42e815adf36116433ebd816d796b2daae4e45b0b606302109` | restored portable source |

## Explicit exclusions and completeness accounting

The machine inventory classifies every September script occurrence as an exact
public duplicate, restored tool, superseded revision, historical host operation,
UI generator/snapshot, stage-specific QA harness, exploratory reference probe,
private transport or private owner review. An exclusion is not a declaration that
the historical file has no authorship value: original stages remain local and the
hash ledger makes each choice reviewable.

- `build_ancient_origins_v1.py` is superseded by the later public builder.
- Deployment, rollback, FPM/socket setup, cache invalidation and one-off UI patch
  generators refer to historical hosts/stages. Maintained UI and builders are
  public; these operations are not portable research dependencies.
- Historical preview/screenshot harnesses and extracted UI snapshots are not
  additional production interfaces. Private HTML/results and donor model assets
  are not published through this recovery.
- `local_composition_audit.py` and its `test_local_audit_gates.py` are an owner
  review pipeline tied to private model/output conventions. The reusable
  composition worker and public-reference validation remain public separately.
- `dna_ancestry_analysis_20260713/analysis.py` (27,920 bytes) is an authored
  exploratory owner analysis with reference-only PCA projection, HWE mixture and
  chromosome diagnostics, hypothesis-specific source lists, a target input path,
  cached reference genotypes and individual-level report exports. It is retained
  privately, not presented as an omitted production engine or published as a
  generic validated ancestry product. Its hash is in the inventory.
- The three `dna_private_audit` Python files total 27,814 bytes and operate on
  private owner documents/reports. Their names, hashes and exclusions are recorded;
  no PDF contents, extracted text or contact sheets are included.
- The extensionless compatibility installer is represented by a portable wrapper
  delegating to the maintained underscore builder, rather than publishing the
  original historical host configuration.

## Why the local folders are much larger than the public source

These are logical file sizes including repeated stage snapshots, not a sum of
unique research code. Archive member metadata was inspected without extracting
payloads. Some reference files have generic suffixes, so top-directory evidence
is more accurate than extension-only classification.

| Local collection | Logical bytes | Relevant accounting |
| --- | ---: | --- |
| Seven September DNA stages | 547,641,467 | Includes repeated source, AADR metadata/reference rows, SQLite packs, model assets, media, archives and a validation venv |
| `dna_ancestry_analysis_20260713` | 6,532,437,085 | `reference` + `reference_full`: 6,010,695,146 bytes; dependency/runtime files: 470,741,294 bytes; authored analysis: 27,920 bytes |
| `dna_private_audit` | 8,389,436 | PDF contact sheets: 8,264,770 bytes; authored private audit scripts: 27,814 bytes |
| `_zerothink_full_audit_20260713T220124Z` | 100,227,267 | Dependencies: 65,386,310 bytes; duplicate archives: 19,621,902 bytes; recognized source: 3,009,774 bytes, plus five SQL schema files (8,977 bytes) |
| `_zerothink_release_final_20260714` | 56,558,469 | Ten archives: 56,556,357 bytes; package/config snapshots overlap the recovered app |

Reference/model/database byte exclusion is therefore substantial and deliberate;
the missing authored script set was measured separately and is accounted for by
name and hash. No claim is made that every private report or historical host
operation belongs in an open-source application release.
