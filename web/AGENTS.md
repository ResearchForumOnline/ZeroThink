# ZeroThink DNA Lab v2 - Agent Instructions

## Product Goal

Build ZeroThink DNA Lab as a real local-first DNA evidence engine.

The user experience must stay simple:
- Upload a raw DNA file.
- Click one main button.
- Receive clear cards, charts, downloads, and an evidence ledger.

The backend must stay evidence-led:
- Raw DNA parser.
- Raw file quality scoring.
- Genotype normalization.
- Genome-build guess with warnings.
- Local database/data-pack matching where installed.
- ZDNA-MAP scoring integration.
- Health research evidence cards.
- Heritage/genetic-affinity analysis.
- Royal/lineage clue ledger with optional GEDCOM.
- PDF/Markdown export.
- DNA Agent follow-up using summaries only.

## Non-Negotiable Wording Rules

Do not output:
- "you are royal"
- "you are Jewish"
- "you are Chinese"
- "you are English"
- "you are Native American"
- "you have this disease"
- "you should take/stop this medicine"

Use evidence language:
- "signals consistent with..."
- "genetic affinity to..."
- "possible clue..."
- "not enough evidence..."
- "requires genealogy records..."
- "research support only..."
- "discuss with a qualified health professional..."

## Royal / Lineage Truth Boundary

Raw DNA alone cannot prove royal lineage.

Royal/lineage checks must use:
- DNA raw-file quality.
- Optional GEDCOM.
- Surnames.
- Locations.
- Dates.
- Family-story notes.
- Public historical graph data when installed.
- Source/evidence chain.

Output must be a claim ledger, not a final identity statement.

Allowed conclusion bands:
- no_evidence
- family_story_only
- weak_clue
- plausible_route
- documentary_support_needed
- dna_supported_clue
- strong_lineage_case
- not_provable_from_current_data

## Health Truth Boundary

Health output is research support only, not diagnosis, treatment, medication dosing, prevention, or medical advice.

Every health card must include:
- Source database.
- Source version.
- Variant/rsID where applicable.
- Genotype match status where applicable.
- Evidence level.
- Confidence band.
- Limitations.
- Next step.

## Privacy Rules

- Never log raw genotype rows.
- Never send raw DNA rows to an LLM.
- Never send raw DNA to third-party APIs during normal analysis.
- Raw DNA storage is opt-in only.
- Default behavior: parse, analyze, generate report, discard raw file.
- Store hashes and summaries, not raw rows, unless the user explicitly chooses private save.
- Add tests/assertions that raw DNA is not logged or sent to the DNA Agent.

## Data Pack Rules

- Do not commit downloaded source databases to git.
- Store data under `data/sources`, `data/cache`, and `data/packs`.
- Add downloaded data folders and binary packs to `.gitignore`.
- Use `README_Codex_start_here.md`, `DNA_DATABASE_RESEARCH.md`, `config/dna_data_sources.zerothink.json`, `sql/dna_pack_schema.sql`, and `schemas/dna_evidence_card.schema.json` as the local DNA pack contract.
- Prefer `scripts/update_dna_data_packs.sh` for the official pack workflow. `scripts/update-dna-data-packs` is kept as a compatibility wrapper/alternate installer.
- Every imported source must create a manifest record:
  - source_name
  - source_url
  - version_or_release
  - downloaded_at
  - file_hash_sha256
  - license_note
  - imported_row_count

## ZDNA-MAP Math System

Preserve existing special math/scoring modules and wrap them behind clear interfaces.

Interface goals:
- scoreAncestrySignals()
- scoreHealthEvidence()
- scoreQuestionSignal()
- combineConfidenceBands()

Confidence must depend on:
- raw file quality
- overlap marker count
- population panel coverage
- score distance between top labels
- ambiguity penalty
- calibration accuracy for that label
- database/source version

## Engineering Rules

- Keep the current ZeroThink dark/neon UI style.
- Prefer small, testable modules.
- Use synthetic DNA fixtures only in tests.
- Every report must include confidence, limitations, and source versions.
- Do not silently make medical, ethnicity, identity, or lineage claims.
