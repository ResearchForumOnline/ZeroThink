# ZeroThink — DNA Lab and Research Workbench

**The original full PHP ZeroThink application and DNA Lab v1.7.1 are now restored
in [`web/`](web/README.md).** The Node engine described below is a separate
companion; the previous public repository did not include the full DNA Lab.

From `web`, with PHP 8.2+ installed (SQLite3 enabled for reference packs):

```console
php -d upload_max_filesize=32M -d post_max_size=160M -d memory_limit=1024M -S 127.0.0.1:8787 local-router.php
```

Open **http://127.0.0.1:8787/dna**. The actual DNA interface now runs locally
without MySQL, login or a subscription. The restored source includes raw DNA/VCF
parsing, quality/build/orientation checks, ZDNA-MAP scoring, reference ancestry,
ancient origins, traits, lineage evidence, DNA Sound, portrait tools and exports,
plus the original research studio, API vault and account-backed web source.

**Private databases, raw DNA, accounts, credentials, runtime vaults and downloaded
reference/model files are excluded.** Licensed reference packs install separately;
missing packs remain unavailable. The full account-backed hosted application needs
separate configuration and review. See [full setup and limits](web/README.md),
[restoration provenance](docs/FULL_APPLICATION_RESTORATION.md), and
[third-party licenses](web/THIRD_PARTY_NOTICES.md). Original project source is
Apache-2.0 except individually licensed components such as the GPL Python worker.

Verify with `npm run check` and `python tools/check_web_source.py --php php`.
The original companion guide is preserved in [docs/LOCAL_ENGINE.md](docs/LOCAL_ENGINE.md).

## ZeroThink Local companion

**Your research workspace, on your computer or your own server.**

Created by **Shafaet Brady Hussain**. ZeroThink Local preserves and develops the
author's earlier ZeroThink research-process and orchestration concepts as a small,
portable **Apache-2.0 open-source** engine. It has no required company website,
account, hosted database, registration server or admin login.

The engine also powers the local ZeroThink workspace in [ZERO ONE Desktop](https://github.com/ResearchForumOnline/ZERO-ONE-Desktop).
This repository is independently usable: you do not need the desktop app.

## What works

| Without a model or API | With your explicitly selected model |
| --- | --- |
| Import selected UTF-8 text documents | Draft a source-grounded answer |
| Rank overlapping document excerpts deterministically | Run a public critique checklist |
| Produce evidence maps and source ledgers | Revise the draft in up to three calls |
| Use nine research processes and validation checklists | Keep progress, cancellation and token ceilings |
| Export Markdown or structured JSON | Identify unknown or missing selected-source IDs |

The default CLI run is fully offline. Optional model calls use local Ollama or an
endpoint you select, including your own OpenAI-compatible server, Groq or OpenAI.
Keys come from your process environment and are never written by this package.
There is no telemetry or automatic provider fallback.

## Start in one minute

Install **Node.js 22 or later**, download and extract the [latest release](https://github.com/ResearchForumOnline/ZeroThink/releases/latest), then open a terminal in the extracted folder:

```console
node cli.cjs --question "What evidence is required to compare energy efficiency?" --document examples/synthetic-evidence.md --process gap-analysis
```

This works with **no model, API, login, internet connection or npm dependencies**.
The included document is a clearly labeled fictional fixture, not scientific data.

For your documents, repeat `--document` with selected `.txt`, `.md`, `.json` or
`.csv` files. ZeroThink never scans folders or imports account data automatically.

```console
node cli.cjs --question "Where do these papers disagree?" --document paper-notes.md --document experiment.md --process literature-review --output research-output.md
node cli.cjs --list-processes
node cli.cjs --help
```

An output file must be new. ZeroThink refuses to overwrite an existing file.

## Use local Ollama

Install Ollama separately and choose a model already installed on your computer.
Replace the example model ID with the model shown by your own `ollama list`.

```console
node cli.cjs --question "Critique my experiment design" --document experiment.md --provider ollama --model YOUR_INSTALLED_MODEL --process paper-draft --passes 3 --tokens 3072
```

The default endpoint is `http://127.0.0.1:11434/api/chat`. This is a loopback
service, not an owner-operated cloud. ZeroThink does not download a model or
rewrite its weights. Ollama's current API format is documented in the
[official chat API reference](https://docs.ollama.com/api/chat).

## Use your own server or provider

Supply the **full chat-completions endpoint**, a model ID and your own key.
Remote endpoints require HTTPS and the explicit `--allow-remote` flag because
the question and selected excerpts will be sent to the chosen server.

PowerShell example for a user-owned compatible server:

```powershell
$env:ZEROTHINK_ENDPOINT = 'https://YOUR-SERVER.example/v1/chat/completions'
$env:ZEROTHINK_MODEL = 'YOUR-SERVER-MODEL'
# Set ZEROTHINK_API_KEY privately if your server requires a key.
node cli.cjs --question "Review my source notes" --document notes.md --provider compatible --allow-remote
```

For Groq, explicitly select `https://api.groq.com/openai/v1/chat/completions`.
For OpenAI, explicitly select `https://api.openai.com/v1/chat/completions`.
Neither provider is selected automatically. Provider terms, model availability
and quotas belong to the user's provider account; this release promises no free
token allowance. Never put a key in a URL, CLI argument, document or report.

`ZEROTHINK_ENDPOINT`, `ZEROTHINK_MODEL` and `ZEROTHINK_API_KEY` configure optional
calls only when `--provider` is present. Leaving out `--provider` stays offline.
HTTP is permitted only for literal loopback/localhost endpoints. Redirects are
refused, response sizes are bounded, and metadata/link-local destinations are
rejected. Cancellation signals the client request; it cannot force a remote
provider to stop already-running inference or reverse provider charges.

## Nine research processes

| ID | Process |
| --- | --- |
| `literature-review` | Compare supplied literature and make evidence gaps visible |
| `evidence-map` | Retrieve relevant excerpts and identify unanswered questions |
| `expert-discovery` | Compare supplied public evidence about relevant expertise |
| `collaboration-plan` | Draft roles and a source-backed collaboration plan |
| `study-plan` | Sequence learning or supervision from supplied constraints |
| `gap-analysis` | Find unsupported claims and missing comparisons |
| `claim-ledger` | Separate excerpt support, interpretation and unverified claims |
| `paper-draft` | Draft, critique and revise a paper for researcher review |
| `custom` | Apply a general bounded workflow to the user's question |

Expert discovery and collaboration processes work from supplied evidence. They
do not invent biographies, browse profiles silently or contact people.

## Embed the engine

Only `engine.cjs` and `templates.cjs` are needed for offline research. Include
`LICENSE` and `NOTICE` when redistributing them.

```js
const { runResearch, getProcesses } = require('./engine.cjs');

const report = await runResearch({
  question: 'What is supported, and what still needs testing?',
  processId: 'claim-ledger',
  mode: 'research',
  documents: [{ id: 'note-1', title: 'My selected notes', text: '...' }],
  maxPasses: 3,
  tokenBudget: 3072,
  signal: abortController.signal,
  onProgress: event => console.log(event.stage, event.message)
}, {
  // Omit complete for the deterministic offline result.
  complete: async ({ messages, maxTokens, signal, stage }) => {
    // Invoke the operator-selected runtime. Keep credentials in your backend.
    return { content: 'A selected-source answer [S1].' };
  }
});
```

`complete` returns a string or `{ content, model?, usage? }`. It receives
`{ messages, maxTokens, signal, stage }`, where `stage` is `draft`, `critique` or
`revise`. `getProcesses()` returns copies of the nine metadata records.

### Input and result contracts

Input fields:

- `question`: required nonempty string, at most 12,000 characters.
- `documents`: at most 32 records `{ id?, title?, text, sourceUrl? }`.
- `processId`: one of the IDs above; `templateId` and `template` are aliases.
- `mode`: `quick`, `research` or `review`; default `research`.
- `maxPasses`: 1–3. Default 1 in quick mode, otherwise 3.
- `tokenBudget`: 384–12,000 requested output tokens across calls; default 3,600.
- `timeoutMs`: 100–600,000 per stage; default 120,000.
- `signal`, `onProgress`: optional cancellation and visible progress.
- `webSearch`: false by default. True requires an explicitly supplied `search`
  adapter returning up to six `{ title, text|snippet, sourceUrl|url, id? }` records.
  No search service or key is bundled. Snippets are labeled as incomplete evidence.

The engine accepts at most 1,048,576 characters per selected document and 2,097,152
characters for the whole corpus. The CLI additionally limits a selected file to
1 MiB of bytes. It chunks the complete accepted corpus into 1,600-character spans
with 200-character overlap; it does not silently discard the tail of a document.
At most 12 excerpts of 1,000 characters enter model prompts. Retrieval is lexical
ranking inspired by BM25, not a semantic model or independent scientific check.

Result:

```js
{
  version, status: 'offline' | 'completed', mode, question,
  answer, markdown,
  evidence: [{ sourceId, documentId, title, sourceUrl, excerpt, chunkId, score }],
  citations: { valid: [], unknown: [], missing: false },
  steps: [{ id, label, status: 'completed' }],
  metrics: { passes, requestedTokens, sourceCount, retrievedCount },
  warnings: []
}
```

Source IDs are canonical `[S1]`, `[S2]`, etc. The original selected-document ID
is retained separately. Cancellation rejects with `AbortError`; timeout rejects
with `TimeoutError`. Progress exposes stage status, not private chain-of-thought.
Model reasoning tags are removed from returned text. Source URLs are metadata
only and are never fetched by the engine; credentials, queries and fragments are
omitted from that metadata to reduce accidental disclosure.

## Evidence and scope

A valid source ID proves only that the answer references a retrieved excerpt.
It does **not** prove that a claim is true, that the cited excerpt supports the
claim, or that a discovery is novel. Model agreement and repeated revision are
not substitutes for measurements, proofs, reproducible experiments or peer review.

This release does not provide autonomous computer takeover, medical/DNA analysis,
distributed inference, a new cipher, QPU access, AGI, weight rewriting or an
unbounded self-improvement loop. It runs useful, inspectable research workflows
with a deliberate ceiling. Existing ZERO ONE Browser Pilot and other tools have
their own control boundaries.

The old hosted service and its private records remain separate. This repository
contains new portable code and synthetic fixtures, with concept provenance in
[PROVENANCE.json](PROVENANCE.json). The author's wider preserved papers are in the
[research repository](https://github.com/ResearchForumOnline/research).

## Tests and contribution

```console
npm run check
```

The initial release passes **36 tests**, including deterministic retrieval,
the tail of a 1 MiB source, synthetic three-pass generation, citation warnings,
cancellation, timeout, no-key output, CLI exports, and real loopback HTTP
requests to mock Ollama/OpenAI-compatible services. CI repeats these checks on
Windows, macOS and Linux. These are software checks, not model-quality benchmarks
or independent scientific validation.

Contributions can add adapters, retrieval improvements or processes. Preserve
the offline default, selected-source boundary, cancellation, source provenance,
licenses and tests. Do not submit credentials, user records or generated private
research reports.

## License

Apache-2.0, copyright **2026 Shafaet Brady Hussain**. This is an open-source
release of the new portable ZeroThink engine. Other projects and historical
research retain their own licenses; in particular, this license does not relicense
the separately released noncommercial ZMath code or private former service data.
