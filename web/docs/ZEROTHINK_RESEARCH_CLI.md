# ZeroThink Research CLI

ZeroThink CLI v3.7.0 adds an evidence-first research workbench for papers,
articles, literature surveys, reviews, and proposals.

## Local sources

```powershell
zerothink research scan --source .\papers
zerothink research paper "AI-assisted literature discovery" `
  --source .\papers `
  --source .\notes.md `
  --stage outline `
  --citation-style "APA 7"
```

## Saved-server sources

Save a neutral SSH profile privately on the machine:

```bash
zerothink server add research server.example.com \
  --user researcher \
  --port 22 \
  --cwd /srv/research
```

Then inspect or use approved remote paths:

```bash
zerothink research scan \
  --server research \
  --remote-path /srv/research/reports

zerothink research review "Production reliability evidence" \
  --server research \
  --remote-path /srv/research/reports \
  --stage critique
```

## Workflow stages

- `protocol`: reproducible search and screening protocol
- `evidence`: source ledger, claim matrix, contradictions, and gaps
- `outline`: evidence-linked paper structure
- `draft`: calibrated research draft
- `critique`: reviewer-style major and minor concerns
- `revision`: prioritized revision plan
- `full`: integrated workflow with all stages visibly separated

## Source controls

- `--source PATH`: local file or folder; repeat as needed
- `--server PROFILE`: saved SSH profile
- `--remote-path PATH`: remote file or folder; repeat as needed
- `--library`: compact private Zero Library evidence pack
- `--web`: live research when the current account route supports it
- `--max-files N`: source inspection cap
- `--dry-run`: inventory sources without a model call
- `--output FILE`: choose the Markdown export path

## Research integrity

ZeroThink does not treat filenames, snippets, or model output as proof. The
research contract forbids invented citations, authors, quotations, DOIs, URLs,
page numbers, statistics, and findings. Unverified claims remain marked for
human checking.

Common credential files, private keys, dependency directories, and oversized
files are excluded or inventoried by default. `--include-sensitive` exists for
trusted terminals, but should be used only after reviewing the exact scope.

