Implement Royal / Lineage Clues v1.

Inputs:
- family side: mother / father / unknown
- surnames
- locations
- approximate date ranges
- family story notes
- optional GEDCOM upload
- uploaded DNA quality summary

Backend:
- Parse GEDCOM if uploaded.
- Build user ancestor graph.
- Read local lineage_core.sqlite public graph.
- Fuzzy-match user tree nodes to public graph nodes by name/date/place.
- Find possible paths to known public historical/royal/noble people where local graph supports it.
- Score path evidence completeness.
- Output claim ledger and missing-evidence checklist.

Rules:
- Never output "you are royal".
- Do not call it proof unless every edge has strong source evidence, and even then phrase it as "documentary evidence supports...".
- DNA can only support a lineage claim in limited ways:
  - Y-DNA direct paternal line
  - mtDNA direct maternal line
  - autosomal DNA only if match/segment evidence is available
- Use conclusion bands from AGENTS.md.

Add tests that banned royal phrases never appear.
