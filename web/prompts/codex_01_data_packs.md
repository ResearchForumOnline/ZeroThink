Implement the ZeroThink DNA Lab local data-pack system.

Use files in:
- config/dna_data_sources.zerothink.json
- scripts/update_dna_data_packs.sh
- scripts/build_health_core.py
- scripts/build_ancestry_reference.py
- scripts/fetch_wikidata_lineage_subset.py
- schemas/dna_data_manifest.schema.json
- sql/dna_pack_schema.sql

Requirements:
- Add data/sources, data/cache, data/packs to .gitignore.
- Make scripts work from repo root.
- Build local SQLite or DuckDB packs.
- Every import must record source_name, source_url, version_or_release, downloaded_at, sha256 hash, license_note, imported_row_count.
- Do not commit downloaded source data.
- Add tests for manifest creation and hash recording.
- Use official URLs in config/dna_data_sources.zerothink.json.
- If a source is too big, implement builder-only/manual mode, not runtime remote fetch.

Return the changed files and test commands.
