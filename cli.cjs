#!/usr/bin/env node
// Copyright 2026 Shafaet Brady Hussain. SPDX-License-Identifier: Apache-2.0
"use strict";
const fs = require("node:fs/promises");
const path = require("node:path");
const { runResearch, getProcesses, LIMITS, VERSION } = require("./engine.cjs");
const { createCompletionAdapter } = require("./providers.cjs");
const HELP = `ZeroThink ${VERSION} — your self-hosted research workspace\n\nnode cli.cjs --question "Compare evidence for a hypothesis" --document notes.md\n\nOptions:\n  --document PATH       Explicit selected .txt/.md/.json/.csv file; repeat up to32\n  --process ID          One of the nine research processes (--list-processes)\n  --mode MODE           quick, research (default), review\n  --passes N            1..3 bounded calls (default3; quick defaults1)\n  --tokens N            Requested output-token ceiling across passes,384..12000\n  --provider TYPE       ollama or compatible. Omit for offline evidence map.\n  --model ID            Installed/model ID; alternatively ZEROTHINK_MODEL\n  --endpoint URL        Full endpoint; alternatively ZEROTHINK_ENDPOINT\n  --allow-remote        Explicit consent to send sources to configured remoteLLM\n  --output PATH         Save new report file; never overwrite an existingfile\n  --json                Output structured JSON instead of Markdown\n  --list-processes      Print the nine research templates\n  --help                Show help\n\nAPI key: ZEROTHINK_API_KEY environment variable only; never a CLIargument.\nNo account, database, owner endpoint, telemetry, auto-scan or commandexecution.\n`;
function parseArgs(argv) {
  const values = { documents: [] }, boolean = new Set(["help", "json", "allow-remote", "list-processes"]), supported = new Set(["question", "document", "process", "mode", "passes", "tokens", "provider", "model", "endpoint", "output"]);
  for (let index = 0; index < argv.length; index++) {
    const name = argv[index].slice(2);
    if (!argv[index].startsWith("--")) throw new TypeError("Use named --options; --help shows the command syntax.");
    if (boolean.has(name)) { values[name] = true; continue; }
    if (!supported.has(name) || !argv[index + 1] || argv[index + 1].startsWith("--")) throw new TypeError("An unknown option or missing value was supplied. Use --help.");
    const value = argv[++index];
    if (name === "document") values.documents.push(value); else values[name] = value;
  }
  return values;
}
async function selectedDocument(filename, index) {
  const resolved = path.resolve(filename), extension = path.extname(resolved).toLowerCase();
  if (![".txt", ".md", ".json", ".csv"].includes(extension)) throw new TypeError("Select .txt, .md, .json or .csv research documents.");
  const handle = await fs.open(resolved, "r");
  try {
    const stat = await handle.stat();
    if (!stat.isFile() || stat.size > 1048576) throw new RangeError("Each selected text file is limited to 1 MiB.");
    const buffer = Buffer.alloc(stat.size + 1), { bytesRead } = await handle.read(buffer, 0, buffer.length, 0);
    if (bytesRead > stat.size) throw new RangeError("The selected file changed during import. Try again.");
    const text = buffer.subarray(0, bytesRead).toString("utf8");
    if (text.includes("\u0000") || text.includes("\uFFFD")) throw new TypeError("Use UTF-8 text documents without binary data.");
    return { id: `document-${index + 1}`, title: path.basename(resolved), text };
  } finally { await handle.close(); }
}
async function main(argv = process.argv.slice(2), env = process.env, io = { stdout: process.stdout, stderr: process.stderr }) {
  const args = parseArgs(argv);
  if (args.help) { io.stdout.write(HELP); return; }
  if (args["list-processes"]) { io.stdout.write(`${JSON.stringify(getProcesses(), null, 2)}\n`); return; }
  if (args.documents.length > LIMITS.documents) throw new RangeError("Select at most32 documents.");
  const documents = await Promise.all(args.documents.map(selectedDocument));
  const controller = new AbortController(), cancel = () => controller.abort(); process.once("SIGINT", cancel);
  try {
    const complete = args.provider ? createCompletionAdapter({ provider: args.provider, endpoint: args.endpoint || env.ZEROTHINK_ENDPOINT, model: args.model || env.ZEROTHINK_MODEL, apiKey: env.ZEROTHINK_API_KEY, allowRemote: args["allow-remote"] }) : undefined;
    const report = await runResearch({ question: args.question, documents, processId: args.process, mode: args.mode, maxPasses: args.passes === undefined ? undefined : Number(args.passes), tokenBudget: args.tokens === undefined ? undefined : Number(args.tokens), signal: controller.signal, onProgress: (event) => io.stderr.write(`[${event.stage}] ${event.message}\n`) }, { complete });
    const output = args.json ? JSON.stringify(report, null, 2) : report.markdown;
    if (args.output) { await fs.writeFile(path.resolve(args.output), `${output}\n`, { flag: "wx", mode: 0o600 }); io.stderr.write("Saved your new report file locally.\n"); }
    else io.stdout.write(`${output}\n`);
    return report;
  } finally { process.removeListener("SIGINT", cancel); }
}
if (require.main === module) main().catch((error) => { process.stderr.write(`${error.name === "AbortError" ? "Cancelled" : "Error"}: ${error.message}\n`); process.exitCode = error.name === "AbortError" ? 130 : 1; });
module.exports = { HELP, parseArgs, selectedDocument, main };
