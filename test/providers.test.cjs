"use strict";
const test = require("node:test"), assert = require("node:assert/strict"), http = require("node:http");
const { validateEndpoint, createCompletionAdapter, readBoundedJson, MAX_RESPONSE_BYTES } = require("../providers.cjs");
test("loopback HTTP is accepted while insecure remote endpoints and URL secrets are rejected", () => {
  assert.equal(validateEndpoint("http://127.0.0.1:11434/api/chat").protocol, "http:");
  for (const url of ["http://example.com/chat", "https://user:key@example.com/chat", "https://example.com/chat?key=x", "https://example.com/chat#key", "http://169.254.169.254/chat", "file:///x"]) assert.throws(() => validateEndpoint(url, true));
});
test("remote endpoints require explicit consent and a model ID", () => {
  assert.throws(() => validateEndpoint("https://example.com/v1/chat/completions"), /allow-remote/); assert.equal(validateEndpoint("https://example.com/v1/chat/completions", true).hostname, "example.com"); assert.throws(() => createCompletionAdapter({ provider: "ollama" }), /model ID/);
});
test("literal key line breaks are rejected before constructing request headers", () => {
  assert.throws(() => createCompletionAdapter({ provider: "ollama", model: "synthetic", apiKey: "key\nheader" }), /key format/);
});
test("metadata DNS resolution is rejected before credentials or source text are sent", async () => {
  let calls = 0; const complete = createCompletionAdapter({ provider: "compatible", model: "synthetic", endpoint: "https://metadata.example.invalid/chat", allowRemote: true, apiKey: "test", lookup: async () => [{ address: "169.254.169.254" }], fetchImpl: async () => { calls++; } }); await assert.rejects(complete({ messages: [], maxTokens: 10 }), /prohibited metadata/); assert.equal(calls, 0);
});
test("completion redirects are refused and remote error bodies are not echoed", async () => {
  for (const response of [new Response(null, { status: 302, headers: { Location: "https://other.invalid" } }), new Response("SECRET_REMOTE_BODY", { status: 401 })]) {
    const complete = createCompletionAdapter({ provider: "ollama", model: "synthetic", fetchImpl: async () => response }); await assert.rejects(complete({ messages: [], maxTokens: 10 }), (error) => !error.message.includes("SECRET_REMOTE_BODY"));
  }
});
test("response JSON bytes are bounded and malformed JSON is rejected", async () => {
  await assert.rejects(readBoundedJson(new Response("x".repeat(MAX_RESPONSE_BYTES + 1))), RangeError); await assert.rejects(readBoundedJson(new Response("not-json")), /invalid JSON/);
});
test("real loopback HTTP compatible and Ollama requests use proper body formats", async (t) => {
  const observed = []; const server = http.createServer(async (request, response) => { let raw = ""; for await (const chunk of request) raw += chunk; const body = JSON.parse(raw); observed.push({ path: request.url, body, key: request.headers.authorization }); response.setHeader("Content-Type", "application/json"); response.end(JSON.stringify(request.url === "/api/chat" ? { model: "synthetic", message: { content: "Local evidence [S1].", thinking: "not returned" }, prompt_eval_count: 7, eval_count: 9 } : { model: "synthetic", choices: [{ message: { content: "Compatible evidence [S1]." } }], usage: { completion_tokens: 9 } })); });
  await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve)); t.after(() => new Promise((resolve) => server.close(resolve))); const origin = `http://127.0.0.1:${server.address().port}`;
  for (const provider of ["ollama", "compatible"]) { const complete = createCompletionAdapter({ provider, endpoint: `${origin}${provider === "ollama" ? "/api/chat" : "/v1/chat/completions"}`, model: "synthetic", apiKey: "synthetic-key" }); const result = await complete({ messages: [{ role: "user", content: "synthetic selected evidence" }], maxTokens: 111 }); assert.match(result.content, /evidence/); assert.equal(result.usage.outputTokens, 9); }
  assert.equal(observed[0].body.options.num_predict, 111); assert.equal(observed[0].body.think, false); assert.equal(observed[1].body.max_tokens, 111); assert.equal(observed[0].key, "Bearer synthetic-key");
});
