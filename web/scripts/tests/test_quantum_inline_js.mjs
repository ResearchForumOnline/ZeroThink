import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';

const here = path.dirname(url.fileURLToPath(import.meta.url));
const root = path.resolve(here, '..', '..');
const pages = ['quantum_zero.php', 'quantum_zero_chat.php'];
let checks = 0;

for (const page of pages) {
  const source = fs.readFileSync(path.join(root, page), 'utf8');
  const scripts = [...source.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)]
    .map(match => match[1])
    .filter(script => script.includes('/api_quantum_voice.php'));
  if (scripts.length !== 1) throw new Error(`${page}: expected one active application script`);
  const script = scripts[0].replace(/<\?(?:php|=)[\s\S]*?\?>/g, '"test-csrf-token"');
  new Function(script);
  checks++;
  const calls = script.match(/\/api_quantum_voice\.php/g) || [];
  if (calls.length !== 2) throw new Error(`${page}: expected two voice API calls`);
  if ((script.match(/'X-CSRF-Token':qzCsrfToken/g) || []).length < 2) {
    throw new Error(`${page}: both voice calls must carry the CSRF header`);
  }
  checks++;
}

console.log(`OK: ${checks} quantum inline JavaScript checks passed`);
