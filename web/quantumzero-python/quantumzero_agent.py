#!/usr/bin/env python3
"""
QuantumZero Agent

Standalone Python companion for the ZeroThink QuantumZero lane.

Core goals:
- Keep the quantum claims honest.
- Use IonQ job/telemetry evidence when keys are provided.
- Use Groq GPT-OSS 120B for the reasoning answer when a Groq key is provided.
- Support optional higher-quality open-source voice through Piper TTS.
- Run without heavy dependencies by default.
"""

from __future__ import annotations

import argparse
import datetime as _dt
import getpass
import hashlib
import json
import os
import platform
import shutil
import subprocess
import sys
import tempfile
import textwrap
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple


APP = "QuantumZero Agent"
VERSION = "0.1.0"
GROQ_MODEL = "openai/gpt-oss-120b"
IONQ_BASE = "https://api.ionq.co/v0.4"
CONFIG_DIR = Path.home() / ".quantumzero"
CONFIG_PATH = CONFIG_DIR / "config.json"
CACHE_DIR = CONFIG_DIR / "cache"
LOG_DIR = CONFIG_DIR / "logs"
_HARDENED_CONFIG_DIRS: set[str] = set()


MISSIONS: Dict[str, Dict[str, str]] = {
    "evidence": {
        "profile": "signal-integrity",
        "mode": "auto",
        "prompt": (
            "Run a Quantum Zero evidence check on this idea: can a real quantum-cloud "
            "job make my AI answer stronger? Use IonQ job evidence if available, "
            "explain what is physically real, what is symbolic research framing, and "
            "give the next practical engineering test."
        ),
    },
    "entropy": {
        "profile": "entanglement-probe",
        "mode": "auto",
        "prompt": (
            "Create a safe Quantum Entropy Seed experiment. Use IonQ proof "
            "probabilities as a public randomness or creativity signal, but explain "
            "why it must not be treated as a private cryptographic key by itself."
        ),
    },
    "ab": {
        "profile": "entanglement-probe",
        "mode": "auto",
        "prompt": (
            "Compare two possible paths with an entangled A/B decision read. Path A "
            "is the obvious safe engineering route. Path B is the ambitious "
            "experimental route. Separate signal, bias, risk, reversibility, and the "
            "smallest next test."
        ),
    },
    "optimizer": {
        "profile": "optimization-oracle",
        "mode": "telemetry",
        "prompt": (
            "Act as an Optimization Scout. Turn my problem into a searchable design "
            "space with variables, constraints, risk gates, and test steps. Conserve "
            "IonQ usage unless a proof job is genuinely useful."
        ),
    },
    "autopilot": {
        "profile": "zero-network",
        "mode": "telemetry",
        "prompt": (
            "Design a safe Agent Zero autopilot workflow that uses Quantum Zero "
            "evidence without giving quantum results blind control. Include "
            "subagents, stop conditions, logs, rollback, approval gates, and exactly "
            "where IonQ jobs should and should not be used."
        ),
    },
    "integrity": {
        "profile": "signal-integrity",
        "mode": "telemetry",
        "prompt": (
            "Run a Signal Integrity Audit on Quantum Zero itself. Identify which "
            "parts are real engineering, which parts are symbolic research language, "
            "what evidence would strengthen the claim, and what wording avoids "
            "deception while still sounding powerful."
        ),
    },
}


def now_iso() -> str:
    return _dt.datetime.now(_dt.timezone.utc).isoformat(timespec="seconds")


def ensure_dirs() -> None:
    for directory in (CONFIG_DIR, CACHE_DIR, LOG_DIR):
        directory.mkdir(parents=True, exist_ok=True)
        _enforce_private_mode(directory, 0o700)
    _harden_existing_private_files()


def _enforce_private_mode(path: Path, mode: int) -> None:
    """Apply owner-only POSIX permissions without changing Windows ACLs."""
    if os.name == "posix":
        path.chmod(mode)


def _harden_existing_private_files() -> None:
    """Privatize files created by older agent versions once per config root."""
    if os.name != "posix":
        return
    root_key = str(CONFIG_DIR.absolute())
    if root_key in _HARDENED_CONFIG_DIRS:
        return
    candidates = [CONFIG_PATH]
    candidates.extend(CACHE_DIR.glob("*.json"))
    candidates.extend(LOG_DIR.glob("*.json"))
    for path in candidates:
        if path.is_file():
            _enforce_private_mode(path, 0o600)
    _HARDENED_CONFIG_DIRS.add(root_key)


def _write_private_atomic(path: Path, text: str) -> None:
    """Atomically replace a private file without a permissive creation window."""
    fd, temp_name = tempfile.mkstemp(
        dir=str(path.parent),
        prefix=f".{path.name}.",
        suffix=".tmp",
        text=True,
    )
    temp_path = Path(temp_name)
    try:
        stream = os.fdopen(fd, "w", encoding="utf-8", newline="\n")
        fd = -1
        with stream:
            stream.write(text)
        _enforce_private_mode(temp_path, 0o600)
        os.replace(temp_path, path)
        _enforce_private_mode(path, 0o600)
    finally:
        if fd >= 0:
            os.close(fd)
        if temp_path.exists():
            temp_path.unlink()


def _write_private_exclusive(path: Path, text: str) -> None:
    """Create a private file exactly once; never replace an existing run."""
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_CLOEXEC", 0)
    fd = os.open(path, flags, 0o600)
    created = True
    try:
        stream = os.fdopen(fd, "w", encoding="utf-8", newline="\n")
        fd = -1
        with stream:
            stream.write(text)
        _enforce_private_mode(path, 0o600)
        created = False
    finally:
        if fd >= 0:
            os.close(fd)
        if created and path.exists():
            path.unlink()


def load_config() -> Dict[str, Any]:
    ensure_dirs()
    if CONFIG_PATH.exists():
        try:
            return json.loads(CONFIG_PATH.read_text(encoding="utf-8"))
        except Exception:
            return {}
    return {}


def save_config(cfg: Dict[str, Any]) -> None:
    ensure_dirs()
    _write_private_atomic(CONFIG_PATH, json.dumps(cfg, indent=2))


def secret_from_env_or_config(name: str, cfg_key: str, cfg: Dict[str, Any]) -> str:
    return (os.environ.get(name) or str(cfg.get(cfg_key, ""))).strip()


def http_json(
    method: str,
    url: str,
    headers: Optional[Dict[str, str]] = None,
    body: Optional[Dict[str, Any]] = None,
    timeout: int = 45,
) -> Dict[str, Any]:
    payload = None
    req_headers = dict(headers or {})
    if body is not None:
        payload = json.dumps(body).encode("utf-8")
        req_headers["Content-Type"] = "application/json"
    req_headers.setdefault("Accept", "application/json")
    req = urllib.request.Request(url, data=payload, method=method.upper(), headers=req_headers)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            data = json.loads(raw) if raw.strip() else {}
            return {"status_code": resp.status, "data": data, "raw": raw}
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        try:
            data = json.loads(raw)
        except Exception:
            data = {"error": raw[:500]}
        message = data.get("message") or data.get("error") or data.get("detail") or raw[:300]
        raise RuntimeError(f"HTTP {exc.code}: {message}")
    except urllib.error.URLError as exc:
        raise RuntimeError(f"Network error: {exc.reason}") from exc


def cache_get(name: str, ttl: int) -> Optional[Any]:
    path = CACHE_DIR / (hashlib.sha256(name.encode("utf-8")).hexdigest() + ".json")
    if not path.exists():
        return None
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
        age = time.time() - float(payload.get("time", 0))
        if age <= ttl:
            return payload.get("value")
    except Exception:
        return None
    return None


def cache_set(name: str, value: Any) -> None:
    ensure_dirs()
    path = CACHE_DIR / (hashlib.sha256(name.encode("utf-8")).hexdigest() + ".json")
    _write_private_atomic(
        path,
        json.dumps({"time": time.time(), "value": value}, indent=2),
    )


def mission_prompt(mission: str, prompt: str) -> Tuple[str, str, str]:
    item = MISSIONS.get(mission)
    if item is None:
        item = MISSIONS["evidence"]
    return item["profile"], item["mode"], prompt.strip() or item["prompt"]


def build_kernel(profile: str, prompt: str, private_signal: str = "") -> Dict[str, Any]:
    profile = profile if profile in {"zero-network", "entanglement-probe", "optimization-oracle", "signal-integrity"} else "zero-network"
    seed = hashlib.sha256(f"QuantumZeroPy|v1|{profile}|{prompt}|{private_signal}".encode("utf-8")).hexdigest()
    bytes_ = [int(seed[i : i + 2], 16) for i in range(0, 12, 2)]
    circuit: List[Dict[str, Any]] = [
        {"gate": "h", "target": 0},
        {"gate": "cnot", "control": 0, "target": 1},
        {"gate": "cnot", "control": 1, "target": 2},
        {"gate": "cnot", "control": 2, "target": 3},
    ]
    for target in range(4):
        if bytes_[target] % 2:
            circuit.append({"gate": "x", "target": target})
    if bytes_[4] % 2:
        circuit.append({"gate": "cnot", "control": 3, "target": 0})

    kernel_id = "qzp-" + seed[:12]
    public = {
        "agent": "Quantum Zero Python",
        "kernel_id": kernel_id,
        "profile": profile,
        "intent_hash": hashlib.sha256(prompt.encode("utf-8")).hexdigest(),
        "private_signal_supplied": bool(private_signal),
        "signal_hash": hashlib.sha256(private_signal.encode("utf-8")).hexdigest() if private_signal else None,
        "circuit": {
            "type": "GHZ-style evidence kernel",
            "qubits": 4,
            "gateset": "qis",
            "gate_count": len(circuit),
            "fingerprint": hashlib.sha256(json.dumps(circuit, sort_keys=True).encode("utf-8")).hexdigest()[:20],
        },
        "truth_boundary": (
            "A real IonQ job ID proves IonQ accepted the circuit. It does not prove "
            "remote effects or that the LLM ran on qubits."
        ),
    }
    job = {
        "type": "ionq.circuit.v1",
        "name": f"QuantumZero Python {kernel_id}",
        "metadata": {
            "system": "QuantumZero Agent Python",
            "kernel_id": kernel_id,
            "profile": profile,
            "intent_hash": public["intent_hash"],
            "private_signal_supplied": "yes" if private_signal else "no",
            "realism_rule": "IonQ job id proves circuit acceptance only",
        },
        "shots": 128,
        "backend": "simulator",
        "input": {
            "qubits": 4,
            "gateset": "qis",
            "circuit": circuit,
        },
    }
    return {"public": public, "job": job, "prompt": prompt}


def ionq_headers(key: str) -> Dict[str, str]:
    return {"Authorization": "apiKey " + key}


def ionq_get_backends(key: str, force: bool = False) -> Dict[str, Any]:
    cache_name = "ionq_backends_" + hashlib.sha256(key.encode("utf-8")).hexdigest()[:12]
    if not force:
        cached = cache_get(cache_name, 1800)
        if cached is not None:
            return {"source": "cache", "payload": cached}
    data = http_json("GET", IONQ_BASE + "/backends", ionq_headers(key), timeout=35)["data"]
    cache_set(cache_name, data)
    return {"source": "ionq", "payload": data}


def ionq_submit(key: str, job: Dict[str, Any], backend: str, shots: int, confirm_qpu: bool) -> Dict[str, Any]:
    is_qpu = backend.startswith("qpu.")
    if is_qpu and not confirm_qpu:
        raise RuntimeError("QPU backend selected but --confirm-qpu was not supplied.")
    job = json.loads(json.dumps(job))
    job["backend"] = backend
    job["shots"] = max(100, min(256 if is_qpu else 1024, int(shots)))
    return http_json("POST", IONQ_BASE + "/jobs", ionq_headers(key), job, timeout=45)["data"]


def ionq_job(key: str, job_id: str) -> Dict[str, Any]:
    safe_id = urllib.parse.quote(job_id.strip())
    return http_json("GET", f"{IONQ_BASE}/jobs/{safe_id}", ionq_headers(key), timeout=35)["data"]


def ionq_results(key: str, job_id: str) -> Dict[str, Any]:
    safe_id = urllib.parse.quote(job_id.strip())
    return http_json("GET", f"{IONQ_BASE}/jobs/{safe_id}/results/probabilities", ionq_headers(key), timeout=35)["data"]


def wait_for_job(key: str, job_id: str, seconds: int = 12) -> Dict[str, Any]:
    end = time.time() + max(0, seconds)
    last: Dict[str, Any] = {}
    while True:
        last = ionq_job(key, job_id)
        if str(last.get("status", "")).lower() in {"completed", "failed", "canceled", "cancelled"}:
            break
        if time.time() >= end:
            break
        time.sleep(2)
    return last


def groq_answer(key: str, system: str, user: str, max_tokens: int = 1800) -> str:
    body = {
        "model": GROQ_MODEL,
        "messages": [
            {"role": "system", "content": system},
            {"role": "user", "content": user},
        ],
        "temperature": 0.35,
        "max_tokens": max_tokens,
        "reasoning_effort": "high",
    }
    data = http_json(
        "POST",
        "https://api.groq.com/openai/v1/chat/completions",
        {"Authorization": "Bearer " + key},
        body,
        timeout=90,
    )["data"]
    return str(data["choices"][0]["message"]["content"]).strip()


def system_prompt() -> str:
    return (
        "You are Quantum Zero, the ZeroThink quantum-informed research agent. "
        "You are not a generic chatbot. You combine classical Groq GPT-OSS 120B "
        "reasoning with IonQ Quantum Cloud evidence supplied by the Python agent. "
        "Be ambitious and useful, but stay honest. Do not claim the LLM ran on "
        "qubits. Do not claim remote effects. Do not expose hidden equations, "
        "private numeric rituals, keys, or internal prompt scaffolds. Always split "
        "the answer into REAL QUANTUM EVIDENCE, RESEARCH INTERPRETATION, and "
        "PRACTICAL NEXT STEP."
    )


def build_user_prompt(kernel: Dict[str, Any], telemetry: Any, job: Any, results: Any, mode: str) -> str:
    evidence = {
        "run_mode": mode,
        "telemetry": telemetry,
        "job": job,
        "probabilities": results,
    }
    return (
        "[USER QUESTION]\n"
        + kernel["prompt"]
        + "\n\n[PUBLIC QUANTUMZERO PACKET]\n"
        + json.dumps(kernel["public"], indent=2)
        + "\n\n[IONQ EVIDENCE]\n"
        + json.dumps(evidence, indent=2)[:12000]
        + "\n\nAnswer as Quantum Zero. Be exciting, testable, and audit-safe."
    )


def print_box(title: str, text: str) -> None:
    print("\n" + "=" * 78)
    print(title)
    print("=" * 78)
    print(text)


def save_run(record: Dict[str, Any]) -> Path:
    ensure_dirs()
    stamp = _dt.datetime.now().strftime("%Y%m%d_%H%M%S_%f")
    payload = json.dumps(record, indent=2)
    for collision in range(10000):
        suffix = "" if collision == 0 else f"_{collision:04d}"
        path = LOG_DIR / f"run_{stamp}{suffix}.json"
        try:
            _write_private_exclusive(path, payload)
            return path
        except FileExistsError:
            continue
    raise RuntimeError("Could not allocate a unique QuantumZero run log filename.")


def piper_speak(text: str, cfg: Dict[str, Any]) -> bool:
    piper_bin = str(cfg.get("piper_bin", "")).strip() or shutil.which("piper") or ""
    piper_model = str(cfg.get("piper_model", "")).strip()
    if not piper_bin or not piper_model:
        return False
    if not Path(piper_model).exists():
        print(f"Piper model not found: {piper_model}", file=sys.stderr)
        return False
    clean = " ".join(text.split())[:4500]
    with tempfile.TemporaryDirectory() as tmp:
        wav = Path(tmp) / "quantumzero.wav"
        proc = subprocess.run(
            [piper_bin, "--model", piper_model, "--output_file", str(wav)],
            input=clean,
            text=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
        )
        if proc.returncode != 0:
            print(proc.stderr.strip() or "Piper failed.", file=sys.stderr)
            return False
        play_audio(wav, cfg)
    return True


def play_audio(wav: Path, cfg: Dict[str, Any]) -> None:
    player = str(cfg.get("audio_player", "")).strip()
    if player:
        subprocess.run([player, str(wav)], check=False)
        return
    system = platform.system().lower()
    if system == "windows":
        ps = (
            "Add-Type -AssemblyName presentationCore; "
            f"$p=New-Object System.Windows.Media.MediaPlayer; "
            f"$p.Open([Uri]'{wav.as_uri()}'); $p.Play(); "
            "Start-Sleep -Milliseconds 500; "
            "while($p.Position -lt $p.NaturalDuration.TimeSpan){Start-Sleep -Milliseconds 150}"
        )
        subprocess.run(["powershell", "-NoProfile", "-Command", ps], check=False)
    elif shutil.which("afplay"):
        subprocess.run(["afplay", str(wav)], check=False)
    elif shutil.which("paplay"):
        subprocess.run(["paplay", str(wav)], check=False)
    elif shutil.which("aplay"):
        subprocess.run(["aplay", str(wav)], check=False)
    else:
        print("No audio player found. Set audio_player in config.", file=sys.stderr)


def fallback_speak(text: str) -> bool:
    clean = " ".join(text.split())[:2500]
    try:
        import pyttsx3  # type: ignore

        engine = pyttsx3.init()
        engine.setProperty("rate", 165)
        engine.say(clean)
        engine.runAndWait()
        return True
    except Exception:
        pass
    if platform.system().lower() == "darwin" and shutil.which("say"):
        subprocess.run(["say", clean], check=False)
        return True
    return False


def speak(text: str, cfg: Dict[str, Any]) -> None:
    if piper_speak(text, cfg):
        return
    if fallback_speak(text):
        return
    print("Voice unavailable. Install Piper for better offline open-source voice.")


def do_setup(_: argparse.Namespace) -> None:
    cfg = load_config()
    print(f"{APP} setup")
    print("Press Enter to keep existing values. Keys are stored locally in ~/.quantumzero/config.json.")
    groq = getpass.getpass("Groq API key: ").strip()
    ionq = getpass.getpass("IonQ API key: ").strip()
    piper_bin = input("Piper executable path (optional, blank for PATH): ").strip()
    piper_model = input("Piper voice model .onnx path (optional): ").strip()
    if groq:
        cfg["groq_api_key"] = groq
    if ionq:
        cfg["ionq_api_key"] = ionq
    if piper_bin:
        cfg["piper_bin"] = piper_bin
    if piper_model:
        cfg["piper_model"] = piper_model
    save_config(cfg)
    print(f"Saved config: {CONFIG_PATH}")


def do_missions(_: argparse.Namespace) -> None:
    for name, item in MISSIONS.items():
        print(f"{name:12} profile={item['profile']:20} mode={item['mode']}")
        print(textwrap.fill(item["prompt"], width=76, subsequent_indent="  "))
        print()


def do_doctor(_: argparse.Namespace) -> None:
    cfg = load_config()
    print_box("Runtime", f"{APP} v{VERSION}\nPython {platform.python_version()}\nConfig {CONFIG_PATH}")
    checks = {
        "Groq key": bool(secret_from_env_or_config("GROQ_API_KEY", "groq_api_key", cfg)),
        "IonQ key": bool(secret_from_env_or_config("IONQ_API_KEY", "ionq_api_key", cfg)),
        "Piper executable": bool(str(cfg.get("piper_bin", "")).strip() or shutil.which("piper")),
        "Piper model": bool(str(cfg.get("piper_model", "")).strip()),
        "pyttsx3 fallback": False,
    }
    try:
        import pyttsx3  # type: ignore  # noqa

        checks["pyttsx3 fallback"] = True
    except Exception:
        pass
    for name, ok in checks.items():
        print(f"{name:18} {'OK' if ok else 'missing'}")


def do_packet(args: argparse.Namespace) -> None:
    prompt = " ".join(args.prompt).strip()
    profile, _, final_prompt = mission_prompt(args.mission, prompt)
    kernel = build_kernel(profile, final_prompt, args.private_signal or "")
    print(json.dumps(kernel["public"], indent=2))


def do_ionq(args: argparse.Namespace) -> None:
    cfg = load_config()
    key = secret_from_env_or_config("IONQ_API_KEY", "ionq_api_key", cfg)
    if not key:
        raise SystemExit("IonQ key missing. Run setup or set IONQ_API_KEY.")
    if args.ionq_command == "status":
        print(json.dumps(ionq_get_backends(key, force=args.force), indent=2))
    elif args.ionq_command == "job":
        print(json.dumps(ionq_job(key, args.job_id), indent=2))
    elif args.ionq_command == "results":
        print(json.dumps(ionq_results(key, args.job_id), indent=2))
    elif args.ionq_command == "recent":
        data = http_json("GET", IONQ_BASE + f"/jobs?limit={args.limit}", ionq_headers(key), timeout=35)["data"]
        print(json.dumps(data, indent=2))


def do_ask(args: argparse.Namespace) -> None:
    cfg = load_config()
    groq_key = secret_from_env_or_config("GROQ_API_KEY", "groq_api_key", cfg)
    ionq_key = secret_from_env_or_config("IONQ_API_KEY", "ionq_api_key", cfg)
    prompt = " ".join(args.prompt).strip()
    profile, default_mode, final_prompt = mission_prompt(args.mission, prompt)
    mode = args.mode or default_mode
    kernel = build_kernel(profile, final_prompt, args.private_signal or "")

    telemetry: Any = None
    job: Any = None
    results: Any = None
    job_error = ""

    if ionq_key:
        try:
            telemetry = ionq_get_backends(ionq_key, force=False)
        except Exception as exc:
            telemetry = {"error": str(exc)}
        if mode != "telemetry":
            try:
                submitted = ionq_submit(ionq_key, kernel["job"], args.backend, args.shots, args.confirm_qpu)
                job_id = str(submitted.get("id") or submitted.get("uuid") or "")
                job = submitted
                if job_id:
                    job = wait_for_job(ionq_key, job_id, seconds=args.wait)
                    if str(job.get("status", "")).lower() == "completed":
                        try:
                            results = ionq_results(ionq_key, job_id)
                        except Exception as exc:
                            results = {"error": str(exc)}
            except Exception as exc:
                if mode == "force":
                    raise
                job_error = str(exc)
    else:
        telemetry = {"warning": "IonQ key missing; packet-only quantum context."}

    if groq_key:
        user_prompt = build_user_prompt(kernel, telemetry, job, results, mode)
        if job_error:
            user_prompt += "\n\n[IONQ JOB ERROR]\n" + job_error
        answer = groq_answer(groq_key, system_prompt(), user_prompt, max_tokens=args.max_tokens)
    else:
        answer = (
            "Groq key missing, so no language-model synthesis was run. Packet and "
            "IonQ evidence were prepared locally. Run setup or set GROQ_API_KEY."
        )

    record = {
        "time": now_iso(),
        "mission": args.mission,
        "mode": mode,
        "profile": profile,
        "packet": kernel["public"],
        "telemetry": telemetry,
        "job": job,
        "probabilities": results,
        "job_error": job_error,
        "answer": answer,
    }
    log_path = save_run(record)

    if args.json:
        print(json.dumps(record, indent=2))
    else:
        print_box("QuantumZero Evidence Ledger", json.dumps({k: record[k] for k in ["mission", "mode", "profile", "packet", "job_error"]}, indent=2))
        if job:
            print_box("IonQ Job", json.dumps(job, indent=2)[:4000])
        if results:
            print_box("IonQ Probabilities", json.dumps(results, indent=2)[:4000])
        print_box("Quantum Zero", answer)
        print(f"\nSaved run log: {log_path}")
    if args.voice:
        speak(answer, cfg)


def repl() -> None:
    print(f"{APP} v{VERSION}")
    print("Type /missions, /doctor, /voice, /quit, or ask a question.")
    while True:
        try:
            line = input("QZERO> ").strip()
        except (EOFError, KeyboardInterrupt):
            print()
            break
        if not line:
            continue
        if line in {"/quit", "/exit", "exit", "quit"}:
            break
        if line == "/missions":
            do_missions(argparse.Namespace())
            continue
        if line == "/doctor":
            do_doctor(argparse.Namespace())
            continue
        if line == "/voice":
            speak("Quantum Zero voice test. Piper is preferred when configured.", load_config())
            continue
        ns = argparse.Namespace(
            prompt=line.split(),
            mission="evidence",
            mode=None,
            private_signal="",
            backend="simulator",
            shots=128,
            confirm_qpu=False,
            wait=12,
            max_tokens=1800,
            voice=False,
            json=False,
        )
        try:
            do_ask(ns)
        except Exception as exc:
            print(f"Error: {exc}")


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(prog="quantumzero", description="QuantumZero Agent Python companion")
    sub = p.add_subparsers(dest="command")

    sub.add_parser("setup", help="Save local Groq/IonQ/Piper settings").set_defaults(func=do_setup)
    sub.add_parser("missions", help="List built-in quantum missions").set_defaults(func=do_missions)
    sub.add_parser("doctor", help="Check local setup").set_defaults(func=do_doctor)

    packet = sub.add_parser("packet", help="Build a public-safe packet without API calls")
    packet.add_argument("prompt", nargs="*", help="Question/directive")
    packet.add_argument("--mission", default="evidence", choices=sorted(MISSIONS))
    packet.add_argument("--private-signal", default="")
    packet.set_defaults(func=do_packet)

    ask = sub.add_parser("ask", help="Ask Quantum Zero")
    ask.add_argument("prompt", nargs="*", help="Question/directive")
    ask.add_argument("--mission", default="evidence", choices=sorted(MISSIONS))
    ask.add_argument("--mode", choices=["auto", "telemetry", "force"], default=None)
    ask.add_argument("--private-signal", default="")
    ask.add_argument("--backend", default="simulator")
    ask.add_argument("--shots", type=int, default=128)
    ask.add_argument("--confirm-qpu", action="store_true")
    ask.add_argument("--wait", type=int, default=12)
    ask.add_argument("--max-tokens", type=int, default=1800)
    ask.add_argument("--voice", action="store_true")
    ask.add_argument("--json", action="store_true")
    ask.set_defaults(func=do_ask)

    ionq = sub.add_parser("ionq", help="IonQ utilities")
    ionq_sub = ionq.add_subparsers(dest="ionq_command", required=True)
    status = ionq_sub.add_parser("status", help="Get backend status")
    status.add_argument("--force", action="store_true")
    status.set_defaults(func=do_ionq)
    recent = ionq_sub.add_parser("recent", help="List recent jobs")
    recent.add_argument("--limit", type=int, default=5)
    recent.set_defaults(func=do_ionq)
    job = ionq_sub.add_parser("job", help="Get job")
    job.add_argument("job_id")
    job.set_defaults(func=do_ionq)
    res = ionq_sub.add_parser("results", help="Get job probability results")
    res.add_argument("job_id")
    res.set_defaults(func=do_ionq)

    return p


def main(argv: Optional[List[str]] = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    if not hasattr(args, "func"):
        repl()
        return 0
    try:
        args.func(args)
        return 0
    except KeyboardInterrupt:
        print("\nStopped.")
        return 130
    except Exception as exc:
        print(f"Error: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
