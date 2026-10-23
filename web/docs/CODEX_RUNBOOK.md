# Codex Runbook for ZeroThink DNA Lab v2

## Safe branch

```bash
git checkout -b dna-lab-v2-local-evidence-engine
```

## Install helpers

```bash
python3 -m pip install -r requirements-dna-packs.txt
```

## Build data packs

```bash
bash scripts/update_dna_data_packs.sh
```

## Ask Codex in this order

1. `prompts/codex_00_repo_audit.md`
2. `prompts/codex_01_data_packs.md`
3. `prompts/codex_02_parser_quality_normalization.md`
4. `prompts/codex_03_health_and_ancestry_engines.md`
5. `prompts/codex_04_lineage_engine.md`
6. `prompts/codex_05_ui_one_click.md`
7. `prompts/codex_06_pdf_agent_privacy.md`
8. `prompts/codex_07_tests_safety.md`

## Done means

- upload still works
- one-click report works
- raw-quality cards work
- local data packs are read from SQLite/DuckDB
- no raw DNA is logged
- health cards include warning
- ancestry cards avoid identity claims
- royal/lineage cards avoid proof claims
- tests pass
