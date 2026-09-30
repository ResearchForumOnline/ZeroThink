// Copyright 2026 Shafaet Brady Hussain. SPDX-License-Identifier: Apache-2.0
"use strict";
const net = require("node:net");
const dns = require("node:dns/promises");
const MAX_RESPONSE_BYTES = 524288;
function isLoopback(host) { return ["localhost", "127.0.0.1", "::1", "[::1]"].includes(String(host).toLowerCase()); }
function forbiddenAddress(value) {
  const address = String(value).toLowerCase().replace(/^\[|\]$/g, "");
  if (net.isIP(address) === 4) { const parts = address.split(".").map(Number); return parts[0] === 0 || (parts[0] === 169 && parts[1] === 254) || parts[0] >= 224; }
  if (net.isIP(address) === 6) return address === "::" || /^fe[89ab][0-9a-f]:/.test(address) || address.startsWith("ff") || address.startsWith("::ffff:169.254.") || address.startsWith("::ffff:0.");
  return false;
}
function validateEndpoint(value, allowRemote = false) {
  let url;
  try { url = new URL(value); } catch { throw new TypeError("Supply a valid completion endpoint URL."); }
  if (!["http:", "https:"].includes(url.protocol) || url.username || url.password || url.search || url.hash || forbiddenAddress(url.hostname)) throw new TypeError("Endpoint URLs must not contain credentials, queries, fragments or metadata addresses.");
  if (url.protocol === "http:" && !isLoopback(url.hostname)) throw new TypeError("Non-loopback endpoints require HTTPS.");
  if (!isLoopback(url.hostname) && !allowRemote) throw new TypeError("Remote completion sends the selected evidence to your configured server. Pass --allow-remote explicitly.");
  return url;
}
async function readBoundedJson(response) {
  if (!response.body) throw new Error("The completion endpoint returned no response body.");
  const reader = response.body.getReader(), chunks = []; let total = 0;
  try {
    while (true) {
      const { value, done } = await reader.read(); if (done) break;
      total += value.byteLength;
      if (total > MAX_RESPONSE_BYTES) { await reader.cancel(); throw new RangeError("The completion endpoint exceeded the response limit."); }
      chunks.push(value);
    }
  } finally { reader.releaseLock(); }
  try { return JSON.parse(Buffer.concat(chunks).toString("utf8")); } catch { throw new Error("The completion endpoint returned invalid JSON."); }
}
function createCompletionAdapter(options = {}) {
  const provider = options.provider;
  if (!["ollama", "compatible"].includes(provider)) throw new TypeError("Choose ollama or compatible provider.");
  const endpoint = validateEndpoint(options.endpoint || (provider === "ollama" ? "http://127.0.0.1:11434/api/chat" : ""), options.allowRemote === true);
  const model = String(options.model || "").trim();
  if (!model || model.length > 192 || /[\u0000-\u001f]/.test(model)) throw new TypeError("Supply an installed/provider model ID with --model or ZEROTHINK_MODEL.");
  const key = String(options.apiKey || "");
  if (/[\r\n\u0000]/.test(key) || key.length > 8192) throw new TypeError("The API key format is invalid.");
  const fetchImpl = options.fetchImpl || globalThis.fetch;
  const lookup = options.lookup || dns.lookup;
  return async ({ messages, maxTokens, signal }) => {
    if (!isLoopback(endpoint.hostname)) {
      const addresses = await lookup(endpoint.hostname.replace(/^\[|\]$/g, ""), { all: true });
      if (!Array.isArray(addresses) || !addresses.length || addresses.some((entry) => forbiddenAddress(entry.address))) throw new Error("The configured endpoint resolved to a prohibited metadata or special-use address.");
    }
    const body = provider === "ollama" ? { model, messages, stream: false, think: false, options: { num_predict: maxTokens, num_ctx: 16384, temperature: 0.2 } } : { model, messages, stream: false, max_tokens: maxTokens, temperature: 0.2 };
    const response = await fetchImpl(endpoint.toString(), { method: "POST", headers: { "Content-Type": "application/json", ...(key ? { Authorization: `Bearer ${key}` } : {}) }, body: JSON.stringify(body), signal, redirect: "manual" });
    if (response.status >= 300 && response.status < 400) throw new Error("Completion redirects are refused to protect your key and selected source text.");
    if (!response.ok) throw new Error(`The configured completion endpoint returned HTTP ${response.status}. Check its model and your key privately.`);
    const payload = await readBoundedJson(response);
    const content = provider === "ollama" ? payload?.message?.content : payload?.choices?.[0]?.message?.content;
    if (typeof content !== "string" || !content.trim()) throw new Error("The completion endpoint returned no answer text.");
    return { content, model: String(payload.model || model).slice(0, 192), usage: provider === "ollama" ? { inputTokens: Number(payload.prompt_eval_count) || 0, outputTokens: Number(payload.eval_count) || 0 } : { inputTokens: Number(payload?.usage?.prompt_tokens) || 0, outputTokens: Number(payload?.usage?.completion_tokens) || 0 } };
  };
}
module.exports = { MAX_RESPONSE_BYTES, isLoopback, forbiddenAddress, validateEndpoint, readBoundedJson, createCompletionAdapter };
