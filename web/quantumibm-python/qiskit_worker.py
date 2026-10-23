#!/usr/bin/env python3
"""
ZeroThink IBM Qiskit worker.

Reads a JSON request from stdin and writes a JSON response to stdout. The worker
never prints API tokens. It can run deterministic fallback calculations without
Qiskit, and uses Qiskit local primitives when Qiskit is installed on Python 3.10+.
IBM Runtime access is opt-in and only used for backend/account context unless a
future caller explicitly enables hardware submission.
"""

import hashlib
import json
import math
import os
import platform
import random
import sys
import traceback


VERSION = "0.1.0"
SUPPORTED_ALGORITHMS = [
    "bell",
    "ghz",
    "grover",
    "qaoa_maxcut",
    "vqe_toy",
    "qft",
    "zero_lattice",
]


def send(payload):
    sys.stdout.write(json.dumps(payload, sort_keys=True))
    sys.stdout.write("\n")
    sys.stdout.flush()


def read_request():
    raw = sys.stdin.read()
    if not raw.strip():
        return {}
    return json.loads(raw)


def clean_text(value, limit=6000):
    text = str(value or "").strip()
    text = "".join(ch for ch in text if ch == "\n" or ch == "\t" or ord(ch) >= 32)
    if len(text) > limit:
        text = text[:limit]
    return text


def pick_algorithm(request):
    explicit = str(request.get("algorithm") or "").strip().lower().replace("-", "_")
    aliases = {
        "qaoa": "qaoa_maxcut",
        "maxcut": "qaoa_maxcut",
        "max_cut": "qaoa_maxcut",
        "vqe": "vqe_toy",
        "phase": "qft",
        "fourier": "qft",
        "zero": "zero_lattice",
        "lattice": "zero_lattice",
    }
    explicit = aliases.get(explicit, explicit)
    if explicit in SUPPORTED_ALGORITHMS:
        return explicit

    prompt = clean_text(request.get("prompt", "")).lower()
    checks = [
        ("qaoa_maxcut", ["qaoa", "max cut", "max-cut", "optimization", "qubo"]),
        ("vqe_toy", ["vqe", "eigen", "hamiltonian", "chemistry"]),
        ("grover", ["grover", "search", "oracle", "marked"]),
        ("qft", ["qft", "fourier", "phase estimation", "phase"]),
        ("ghz", ["ghz", "lattice", "multi qubit"]),
        ("bell", ["bell", "entangle", "entanglement", "correlation"]),
    ]
    for algorithm, words in checks:
        if any(word in prompt for word in words):
            return algorithm
    return "zero_lattice"


def seed_for(request, algorithm):
    base = "%s|%s|%s" % (
        algorithm,
        clean_text(request.get("prompt", ""), 2048),
        clean_text(request.get("research_signal", ""), 1024),
    )
    return hashlib.sha256(base.encode("utf-8")).hexdigest()


def normalize_counts(counts, shots):
    fixed = {}
    total = 0
    for key, value in counts.items():
        try:
            amount = int(round(float(value)))
        except Exception:
            continue
        if amount > 0:
            fixed[str(key)] = amount
            total += amount
    if total <= 0:
        return {"0": int(shots)}
    if total == int(shots):
        return dict(sorted(fixed.items()))
    scale = float(shots) / float(total)
    out = {}
    for key, value in fixed.items():
        out[key] = max(0, int(round(value * scale)))
    drift = int(shots) - sum(out.values())
    if drift:
        best = max(out, key=out.get)
        out[best] += drift
    return dict(sorted((k, v) for k, v in out.items() if v > 0))


def fallback_counts(algorithm, shots, seed):
    rng = random.Random(int(seed[:16], 16))
    shots = max(32, min(int(shots or 256), 4096))
    if algorithm == "bell":
        split = shots // 2
        return {"00": split, "11": shots - split}
    if algorithm == "ghz":
        split = shots // 2
        return {"000": split, "111": shots - split}
    if algorithm == "grover":
        marked = ["101", "011"]
        each = int(shots * 0.38)
        rest = shots - (each * 2)
        counts = {"101": each, "011": each}
        states = ["000", "001", "010", "100", "110", "111"]
        for i, state in enumerate(states):
            counts[state] = rest // len(states) + (1 if i < rest % len(states) else 0)
        return counts
    if algorithm == "qaoa_maxcut":
        return {"010": int(shots * 0.27), "101": int(shots * 0.27), "001": int(shots * 0.12), "110": int(shots * 0.12), "011": int(shots * 0.11), "100": shots - int(shots * 0.89)}
    if algorithm == "vqe_toy":
        return {"00": int(shots * 0.58), "11": int(shots * 0.24), "01": int(shots * 0.10), "10": shots - int(shots * 0.92)}
    if algorithm == "qft":
        states = ["000", "001", "010", "011", "100", "101", "110", "111"]
        counts = {}
        remaining = shots
        for i, state in enumerate(states):
            if i == len(states) - 1:
                value = remaining
            else:
                value = max(1, int(shots * (0.10 + rng.random() * 0.05)))
            counts[state] = value
            remaining -= value
        return counts
    bits = int(seed[:2], 16) % 4
    left = format(bits, "04b")
    right = "".join("1" if ch == "0" else "0" for ch in left)
    split = shots // 2
    return {left: split, right: shots - split}


def summarize_counts(counts, shots):
    total = float(sum(counts.values()) or shots or 1)
    ordered = sorted(counts.items(), key=lambda item: item[1], reverse=True)
    entropy = 0.0
    for _, value in ordered:
        p = float(value) / total
        if p > 0:
            entropy -= p * math.log(p, 2)
    return {
        "shots": int(total),
        "dominant_state": ordered[0][0] if ordered else "",
        "dominant_probability": round((float(ordered[0][1]) / total) if ordered else 0.0, 6),
        "entropy_bits": round(entropy, 6),
        "top_states": [{"state": k, "count": v, "probability": round(float(v) / total, 6)} for k, v in ordered[:8]],
    }


def qiskit_status():
    status = {
        "python": sys.version.split()[0],
        "python_executable": sys.executable,
        "platform": platform.platform(),
        "qiskit_available": False,
        "qiskit_ibm_runtime_available": False,
        "qiskit_version": "",
        "runtime_version": "",
        "requires_python": ">=3.10 for current qiskit and qiskit-ibm-runtime",
    }
    try:
        import qiskit  # noqa: F401
        status["qiskit_available"] = True
        status["qiskit_version"] = str(getattr(qiskit, "__version__", "unknown"))
    except Exception as exc:
        status["qiskit_error"] = str(exc)
    try:
        import qiskit_ibm_runtime  # noqa: F401
        status["qiskit_ibm_runtime_available"] = True
        status["runtime_version"] = str(getattr(qiskit_ibm_runtime, "__version__", "unknown"))
    except Exception as exc:
        status["runtime_error"] = str(exc)
    return status


def build_qiskit_circuit(algorithm, seed):
    from qiskit import QuantumCircuit

    if algorithm == "bell":
        qc = QuantumCircuit(2)
        qc.h(0)
        qc.cx(0, 1)
        return qc, "Bell pair correlation"

    if algorithm == "ghz":
        qc = QuantumCircuit(3)
        qc.h(0)
        qc.cx(0, 1)
        qc.cx(0, 2)
        return qc, "GHZ shared branch"

    if algorithm == "grover":
        qc = QuantumCircuit(3)
        qc.h([0, 1, 2])
        target = "101"
        for idx, bit in enumerate(target[::-1]):
            if bit == "0":
                qc.x(idx)
        qc.h(2)
        qc.ccx(0, 1, 2)
        qc.h(2)
        for idx, bit in enumerate(target[::-1]):
            if bit == "0":
                qc.x(idx)
        qc.h([0, 1, 2])
        qc.x([0, 1, 2])
        qc.h(2)
        qc.ccx(0, 1, 2)
        qc.h(2)
        qc.x([0, 1, 2])
        qc.h([0, 1, 2])
        return qc, "Grover single-marked-state search"

    if algorithm == "qaoa_maxcut":
        qc = QuantumCircuit(3)
        gamma = math.pi / 3.0
        beta = math.pi / 5.0
        qc.h([0, 1, 2])
        for a, b in [(0, 1), (1, 2), (0, 2)]:
            qc.rzz(2 * gamma, a, b)
        for q in range(3):
            qc.rx(2 * beta, q)
        return qc, "QAOA toy MaxCut triangle"

    if algorithm == "vqe_toy":
        qc = QuantumCircuit(2)
        theta = (int(seed[:6], 16) % 3140) / 1000.0
        qc.ry(theta, 0)
        qc.cx(0, 1)
        qc.ry(theta / 2.0, 1)
        return qc, "VQE toy two-qubit ansatz"

    if algorithm == "qft":
        qc = QuantumCircuit(3)
        seed_bits = int(seed[:2], 16) % 8
        for i in range(3):
            if seed_bits & (1 << i):
                qc.x(i)
        qc.h(2)
        qc.cp(math.pi / 2, 1, 2)
        qc.cp(math.pi / 4, 0, 2)
        qc.h(1)
        qc.cp(math.pi / 2, 0, 1)
        qc.h(0)
        qc.swap(0, 2)
        return qc, "QFT phase-spread demo"

    qc = QuantumCircuit(4)
    qc.h(0)
    qc.cx(0, 1)
    qc.cx(1, 2)
    qc.cx(2, 3)
    for target in range(4):
        if int(seed[target * 2:target * 2 + 2], 16) % 2:
            qc.x(target)
    if int(seed[10:12], 16) % 2:
        qc.cx(3, 0)
    return qc, "ZeroThink seeded lattice circuit"


def run_qiskit_local(algorithm, shots, seed):
    from qiskit.primitives import StatevectorSampler

    circuit, label = build_qiskit_circuit(algorithm, seed)
    measured = circuit.measure_all(inplace=False)
    sampler = StatevectorSampler()
    job = sampler.run([measured], shots=int(shots))
    result = job.result()
    pub = result[0]
    data = getattr(pub, "data", None)
    meas = getattr(data, "meas", None) if data is not None else None
    if meas is not None and hasattr(meas, "get_counts"):
        counts = meas.get_counts()
    else:
        counts = {}
    return normalize_counts(counts, shots), {
        "label": label,
        "qubits": int(circuit.num_qubits),
        "depth": int(circuit.depth()),
        "gate_count": int(sum(circuit.count_ops().values())),
        "operations": dict(circuit.count_ops()),
    }


def ibm_runtime_context(request):
    token = clean_text(request.get("ibm_token", ""), 256)
    if not token:
        return {"available": False, "message": "IBM Quantum token not supplied; local Qiskit mode only."}
    try:
        from qiskit_ibm_runtime import QiskitRuntimeService
    except Exception as exc:
        return {"available": False, "message": "qiskit-ibm-runtime not installed: %s" % exc}

    try:
        kwargs = {"token": token}
        instance = clean_text(request.get("ibm_instance", ""), 512)
        region = clean_text(request.get("ibm_region", ""), 32)
        if instance:
            kwargs["instance"] = instance
        if region:
            kwargs["region"] = region
        service = QiskitRuntimeService(**kwargs)
        backends = service.backends()
        visible = []
        for backend in backends[:12]:
            name = getattr(backend, "name", None)
            if callable(name):
                name = name()
            visible.append(str(name or backend))
        return {
            "available": True,
            "message": "IBM Quantum Runtime token loaded; backend context returned without submitting a hardware job.",
            "visible_backends": visible,
            "backend_count": len(backends),
        }
    except Exception as exc:
        return {"available": False, "message": "IBM Runtime check failed: %s" % exc}


def build_context(algorithm, mode, counts, summary, circuit_meta, status, runtime):
    lines = [
        "[IBM QISKIT LANE]",
        "provider=IBM Quantum / Qiskit",
        "mode=%s" % mode,
        "algorithm=%s" % algorithm,
        "qiskit_available=%s" % ("yes" if status.get("qiskit_available") else "no"),
        "qiskit_version=%s" % (status.get("qiskit_version") or "not installed"),
        "python=%s" % status.get("python", ""),
        "truth_boundary=Classical AI reasons over Qiskit/IBM evidence. Do not claim the LLM runs on qubits.",
        "runtime_boundary=No IBM hardware job is submitted unless the operator explicitly enables runtime/hardware mode.",
        "dominant_state=%s" % summary.get("dominant_state", ""),
        "dominant_probability=%s" % summary.get("dominant_probability", ""),
        "entropy_bits=%s" % summary.get("entropy_bits", ""),
        "counts=%s" % json.dumps(counts, sort_keys=True),
    ]
    if circuit_meta:
        lines.append("circuit=%s" % json.dumps(circuit_meta, sort_keys=True))
    if runtime:
        lines.append("ibm_runtime=%s" % json.dumps(runtime, sort_keys=True))
    if not status.get("qiskit_available"):
        lines.append("setup_note=Install Python 3.10+ and pip packages qiskit qiskit-ibm-runtime for real Qiskit execution.")
    return "\n".join(lines)


def main():
    try:
        request = read_request()
        action = str(request.get("action") or "run").lower()
        status = qiskit_status()

        if action == "status":
            runtime = ibm_runtime_context(request) if request.get("ibm_token") else {}
            send({"status": "success", "worker": VERSION, "environment": status, "ibm_runtime": runtime})
            return

        algorithm = pick_algorithm(request)
        shots = max(32, min(int(request.get("shots") or 256), 4096))
        mode = str(request.get("mode") or "local").lower()
        seed = seed_for(request, algorithm)
        runtime = {}
        circuit_meta = {}

        if mode in ("runtime", "ibm", "hardware", "backend"):
            runtime = ibm_runtime_context(request)

        if status.get("qiskit_available") and sys.version_info >= (3, 10):
            try:
                counts, circuit_meta = run_qiskit_local(algorithm, shots, seed)
                execution = "qiskit_local_statevector_sampler"
            except Exception as exc:
                counts = fallback_counts(algorithm, shots, seed)
                circuit_meta = {"label": "deterministic fallback after Qiskit error", "error": str(exc)}
                execution = "fallback_after_qiskit_error"
        else:
            counts = fallback_counts(algorithm, shots, seed)
            circuit_meta = {"label": "deterministic fallback; Qiskit runtime not available"}
            execution = "deterministic_fallback"

        counts = normalize_counts(counts, shots)
        summary = summarize_counts(counts, shots)
        context = build_context(algorithm, mode, counts, summary, circuit_meta, status, runtime)
        send({
            "status": "success",
            "worker": VERSION,
            "algorithm": algorithm,
            "execution": execution,
            "mode": mode,
            "seed_hash": seed,
            "counts": counts,
            "summary": summary,
            "circuit": circuit_meta,
            "environment": status,
            "ibm_runtime": runtime,
            "context": context,
            "truth_boundary": "Qiskit/IBM evidence can ground the answer; the language model remains classical.",
        })
    except Exception as exc:
        send({
            "status": "error",
            "message": str(exc),
            "trace": traceback.format_exc(limit=3),
            "worker": VERSION,
        })


if __name__ == "__main__":
    main()
