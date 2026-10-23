# QuantumZero Agent Python

Standalone Python companion for the ZeroThink QuantumZero research lane.

It is designed to be useful without hype:

- Groq GPT-OSS 120B is the classical reasoning engine.
- IonQ Quantum Cloud supplies backend telemetry, job IDs, and probability results when requested.
- The public packet is safe to show. Private research scaffolding stays out of the output.
- Piper TTS is supported for better open-source offline voice when installed locally.

## Install

Windows PowerShell:

```powershell
irm https://zerothink.talktoai.org/quantumzero-python/install.ps1 | iex
```

Linux / macOS:

```bash
curl -fsSL https://zerothink.talktoai.org/quantumzero-python/install.sh | bash
```

Manual:

Download `quantumzero_agent.py`, then run:

```bash
python quantumzero_agent.py setup
python quantumzero_agent.py doctor
python quantumzero_agent.py missions
```

You can also use environment variables instead of saving keys:

```bash
export GROQ_API_KEY="..."
export IONQ_API_KEY="..."
```

PowerShell:

```powershell
$env:GROQ_API_KEY="..."
$env:IONQ_API_KEY="..."
```

## First Run

```bash
python quantumzero_agent.py ask --mission evidence
python quantumzero_agent.py ask --mission entropy --voice
python quantumzero_agent.py ask "Compare two product ideas with a quantum-informed A/B read" --mission ab
```

## Low Usage Modes

Telemetry only, no new IonQ job:

```bash
python quantumzero_agent.py ask --mission optimizer --mode telemetry
```

Auto proof job on simulator:

```bash
python quantumzero_agent.py ask --mission evidence --mode auto --backend simulator --shots 128
```

Hardware QPU requires explicit confirmation:

```bash
python quantumzero_agent.py ask "Run a tiny proof job" --backend qpu.aria-1 --confirm-qpu
```

## IonQ Utilities

```bash
python quantumzero_agent.py ionq status
python quantumzero_agent.py ionq recent
python quantumzero_agent.py ionq job YOUR_JOB_ID
python quantumzero_agent.py ionq results YOUR_JOB_ID
```

## Better Voice With Piper

Browser speech voices can sound weak. For a better open-source offline voice, install Piper and a voice model, then run:

```bash
python quantumzero_agent.py setup
python quantumzero_agent.py ask --mission evidence --voice
```

During setup, set:

- Piper executable path, or leave blank if `piper` is on PATH.
- Piper `.onnx` voice model path.

Reference projects:

- Piper TTS: https://github.com/rhasspy/piper
- Piper development note: https://github.com/OHF-Voice/piper1-gpl
- faster-whisper for optional future speech-to-text: https://github.com/SYSTRAN/faster-whisper

## Truth Boundary

QuantumZero Agent does not claim the LLM itself runs on quantum hardware.

It is hybrid quantum-informed AI:

- Classical model reasoning.
- Real quantum-cloud telemetry and job evidence in the context layer.
- Clear distinction between physical evidence, symbolic research interpretation, and practical next steps.
