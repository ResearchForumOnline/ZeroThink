# ZeroThink IBM Qiskit Lane

This is the server-side Python worker for the IBM Quantum / Qiskit lane.

## Setup

Use Python 3.10 or newer for current Qiskit packages:

```bash
cd /home/talkto/zerothink.talktoai.org
python3.10 -m venv quantumibm-python/.venv
quantumibm-python/.venv/bin/python -m pip install --upgrade pip
quantumibm-python/.venv/bin/python -m pip install -r quantumibm-python/requirements.txt
```

If your Python binary is somewhere else, set `QISKIT_PYTHON` for the PHP/web environment.

If PHP process spawning is disabled on the host, ZeroThink returns a deterministic PHP fallback packet instead of failing. Real Qiskit execution then needs either PHP permission to launch the configured Python worker or a separate sidecar service that the web app can call.

## Keys

Users should save their own IBM Quantum token in ZeroThink Neural Vault. Optional IBM instance/CRN and region can also be saved. Local Qiskit simulation does not require a token; IBM Runtime/backend access does.

## Manual Check

```bash
echo '{"action":"run","algorithm":"bell","shots":256,"prompt":"test"}' | quantumibm-python/.venv/bin/python quantumibm-python/qiskit_worker.py
```

The worker returns JSON only. It never prints API tokens.
