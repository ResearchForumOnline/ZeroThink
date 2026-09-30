"use strict";
const test = require("node:test"), assert = require("node:assert/strict"), fs = require("node:fs/promises"), os = require("node:os"), path = require("node:path"), { spawnSync } = require("node:child_process");
const { parseArgs, selectedDocument, main } = require("../cli.cjs");
const root = path.resolve(__dirname, "..");
test("CLI parsing permits documented options and refuses positional keys or unknown flags", () => {
  assert.deepEqual(parseArgs(["--question", "battery", "--document", "a.md", "--document", "b.md", "--json"]).documents, ["a.md", "b.md"]); assert.throws(() => parseArgs(["--api-key", "private"])); assert.throws(() => parseArgs(["private"])); assert.throws(() => parseArgs(["--question"]));
});
test("standalone offline CLI produces a structured report from synthetic selected files", () => {
  const result = spawnSync(process.execPath, ["cli.cjs", "--question", "energy efficiency measurements", "--document", "examples/synthetic-evidence.md", "--json"], { cwd: root, encoding: "utf8", env: { ...process.env, ZEROTHINK_API_KEY: "NEVER_PRINT_THIS_SYNTHETIC_KEY" } }); assert.equal(result.status, 0, result.stderr); const report = JSON.parse(result.stdout); assert.equal(report.status, "offline"); assert.equal(report.evidence[0].sourceId, "S1"); assert.ok(!`${result.stdout}${result.stderr}`.includes("NEVER_PRINT_THIS_SYNTHETIC_KEY"));
});
test("CLI supports process discovery without model setup", () => {
  const result = spawnSync(process.execPath, ["cli.cjs", "--list-processes"], { cwd: root, encoding: "utf8" }); assert.equal(result.status, 0); assert.equal(JSON.parse(result.stdout).length, 9);
});
test("import rejects binary text and unsupported formats; output never overwrites", async (t) => {
  const dir = await fs.mkdtemp(path.join(os.tmpdir(), "zerothink-cli-")); t.after(() => fs.rm(dir, { recursive: true, force: true })); const binary = path.join(dir, "binary.txt"), unsupported = path.join(dir, "private.env"), output = path.join(dir, "output.md"); await fs.writeFile(binary, Buffer.from([65, 0, 66])); await fs.writeFile(unsupported, "private"); await fs.writeFile(output, "existing");
  await assert.rejects(selectedDocument(binary, 0), /UTF-8/); await assert.rejects(selectedDocument(unsupported, 0), /research documents/);
  const io = { stdout: { write() {} }, stderr: { write() {} } }; await assert.rejects(main(["--question", "battery", "--output", output], {}, io), { code: "EEXIST" }); assert.equal(await fs.readFile(output, "utf8"), "existing");
});
test("declared docs environment defaults cannot silently enable cloud provider", async () => {
  let stdout = ""; const result = await main(["--question", "battery"], { ZEROTHINK_ENDPOINT: "https://example.invalid/chat", ZEROTHINK_API_KEY: "test" }, { stdout: { write(value) { stdout += value; } }, stderr: { write() {} } }); assert.equal(result.status, "offline"); assert.match(stdout, /offline/);
});
