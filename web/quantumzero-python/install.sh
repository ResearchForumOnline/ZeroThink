#!/usr/bin/env bash
set -euo pipefail
HOME_DIR="${HOME}/.quantumzero"
mkdir -p "$HOME_DIR"
TARGET="${HOME_DIR}/quantumzero_agent.py"
URL="https://zerothink.talktoai.org/quantumzero-python/quantumzero_agent.py"
if command -v curl >/dev/null 2>&1; then
  curl -fsSL "$URL" -o "$TARGET"
else
  python3 - <<PY
import urllib.request
urllib.request.urlretrieve("$URL", "$TARGET")
PY
fi
chmod +x "$TARGET"
echo "QuantumZero Agent installed to $TARGET"
echo ""
echo "Run:"
echo "  python3 $TARGET setup"
echo "  python3 $TARGET doctor"
echo "  python3 $TARGET ask --mission evidence"
