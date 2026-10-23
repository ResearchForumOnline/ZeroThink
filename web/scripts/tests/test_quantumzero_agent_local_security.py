from __future__ import annotations

import datetime
import importlib.util
import json
import os
import stat
import sys
import tempfile
import types
import unittest
from unittest import mock
from pathlib import Path


AGENT_PATH = (
    Path(__file__).resolve().parents[2]
    / "quantumzero-python"
    / "quantumzero_agent.py"
)
SPEC = importlib.util.spec_from_file_location("quantumzero_agent_local_security", AGENT_PATH)
assert SPEC is not None and SPEC.loader is not None
AGENT = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = AGENT
SPEC.loader.exec_module(AGENT)


class FrozenDateTime:
    @classmethod
    def now(cls) -> datetime.datetime:
        return datetime.datetime(2026, 7, 14, 12, 34, 56, 123456)


class QuantumZeroAgentLocalSecurityTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name) / ".quantumzero"
        self.original_paths = (
            AGENT.CONFIG_DIR,
            AGENT.CONFIG_PATH,
            AGENT.CACHE_DIR,
            AGENT.LOG_DIR,
            AGENT._dt,
        )
        AGENT.CONFIG_DIR = self.root
        AGENT.CONFIG_PATH = self.root / "config.json"
        AGENT.CACHE_DIR = self.root / "cache"
        AGENT.LOG_DIR = self.root / "logs"
        AGENT._dt = types.SimpleNamespace(datetime=FrozenDateTime)
        self.original_hardened_roots = set(AGENT._HARDENED_CONFIG_DIRS)
        AGENT._HARDENED_CONFIG_DIRS.clear()

    def tearDown(self) -> None:
        (
            AGENT.CONFIG_DIR,
            AGENT.CONFIG_PATH,
            AGENT.CACHE_DIR,
            AGENT.LOG_DIR,
            AGENT._dt,
        ) = self.original_paths
        AGENT._HARDENED_CONFIG_DIRS.clear()
        AGENT._HARDENED_CONFIG_DIRS.update(self.original_hardened_roots)
        self.temp.cleanup()

    def test_run_logs_are_unique_and_never_overwrite(self) -> None:
        AGENT.ensure_dirs()
        occupied = AGENT.LOG_DIR / "run_20260714_123456_123456.json"
        occupied.write_text("owner data that must survive", encoding="utf-8")

        first = AGENT.save_run({"run": 1})
        second = AGENT.save_run({"run": 2})

        self.assertEqual(occupied.read_text(encoding="utf-8"), "owner data that must survive")
        self.assertNotEqual(first, second)
        self.assertEqual(json.loads(first.read_text(encoding="utf-8")), {"run": 1})
        self.assertEqual(json.loads(second.read_text(encoding="utf-8")), {"run": 2})
        self.assertTrue(first.name.endswith("_0001.json"))
        self.assertTrue(second.name.endswith("_0002.json"))

    def test_existing_files_are_migrated_to_private_modes_once(self) -> None:
        for directory in (AGENT.CONFIG_DIR, AGENT.CACHE_DIR, AGENT.LOG_DIR):
            directory.mkdir(parents=True, exist_ok=True)
        existing = (
            AGENT.CONFIG_PATH,
            AGENT.CACHE_DIR / "old-cache.json",
            AGENT.LOG_DIR / "old-run.json",
        )
        for path in existing:
            path.write_text("{}", encoding="utf-8")

        with mock.patch.object(AGENT.os, "name", "posix"), mock.patch.object(
            AGENT, "_enforce_private_mode"
        ) as enforce:
            AGENT.ensure_dirs()
            for directory in (AGENT.CONFIG_DIR, AGENT.CACHE_DIR, AGENT.LOG_DIR):
                self.assertIn(mock.call(directory, 0o700), enforce.call_args_list)
            for path in existing:
                self.assertIn(mock.call(path, 0o600), enforce.call_args_list)

            first_pass_calls = enforce.call_count
            AGENT.ensure_dirs()
            self.assertEqual(enforce.call_count, first_pass_calls + 3)

    @unittest.skipUnless(os.name == "posix", "POSIX mode bits are not meaningful on Windows")
    def test_credentials_cache_and_logs_are_owner_only_on_posix(self) -> None:
        AGENT.save_config({"groq_api_key": "test-only"})
        AGENT.cache_set("test", {"value": 1})
        run_path = AGENT.save_run({"answer": "private"})

        for directory in (AGENT.CONFIG_DIR, AGENT.CACHE_DIR, AGENT.LOG_DIR):
            self.assertEqual(stat.S_IMODE(directory.stat().st_mode), 0o700)
        cache_path = next(AGENT.CACHE_DIR.glob("*.json"))
        for path in (AGENT.CONFIG_PATH, cache_path, run_path):
            self.assertEqual(stat.S_IMODE(path.stat().st_mode), 0o600)


if __name__ == "__main__":
    unittest.main()
