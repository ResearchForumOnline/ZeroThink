#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${ZEROTHINK_BASE_URL:-https://zerothink.talktoai.org}"
INSTALL_ROOT="${HOME}/.zerothink"
BIN_DIR="${HOME}/.local/bin"
PYTHON_BIN=""

if command -v python3 >/dev/null 2>&1; then
  PYTHON_BIN="python3"
elif command -v python >/dev/null 2>&1; then
  PYTHON_BIN="python"
else
  echo "Python 3 is required but was not found."
  exit 1
fi

cat <<'EOF'
███████╗███████╗██████╗  ██████╗
╚══███╔╝██╔════╝██╔══██╗██╔═══██╗
  ███╔╝ █████╗  ██████╔╝██║   ██║
 ███╔╝  ██╔══╝  ██╔══██╗██║   ██║
███████╗███████╗██║  ██║╚██████╔╝
╚══════╝╚══════╝╚═╝  ╚═╝ ╚═════╝
EOF
echo "Installing ZeroThink CLI..."
echo

mkdir -p "${INSTALL_ROOT}" "${BIN_DIR}"
curl -fsSL "${BASE_URL}/cli-assets/zerothink.py" -o "${INSTALL_ROOT}/zerothink.py"
chmod +x "${INSTALL_ROOT}/zerothink.py"

if ! "${PYTHON_BIN}" - <<'PY' >/dev/null 2>&1
import importlib.util, sys
sys.exit(0 if importlib.util.find_spec("prompt_toolkit") else 1)
PY
then
  if "${PYTHON_BIN}" -m pip install --user --disable-pip-version-check prompt_toolkit >/dev/null 2>&1; then
    echo "Installed prompt_toolkit for interactive autocomplete."
  else
    echo "Could not install prompt_toolkit automatically. The CLI will still work without autocomplete."
  fi
fi

cat > "${BIN_DIR}/zerothink" <<EOF
#!/usr/bin/env bash
exec ${PYTHON_BIN} "\${HOME}/.zerothink/zerothink.py" "\$@"
EOF
chmod +x "${BIN_DIR}/zerothink"

echo "Installed to ${INSTALL_ROOT}/zerothink.py"
echo "Launcher created at ${BIN_DIR}/zerothink"
echo

case ":${PATH}:" in
  *":${BIN_DIR}:"*) ;;
  *)
    echo "Add ${BIN_DIR} to your PATH if needed."
    echo 'Example: export PATH="$HOME/.local/bin:$PATH"'
    ;;
esac

echo
echo "Next steps:"
echo "  zerothink                    # first run opens the guided setup"
echo "  zerothink tutorial           # rerun the guide anytime"
echo "  zerothink help quickstart"
echo "  zerothink help examples"
echo "  zerothink login"
echo "  zerothink key set groq      # optional no-Google direct mode"
echo "  zerothink access folder .   # safer repo-only mode"
