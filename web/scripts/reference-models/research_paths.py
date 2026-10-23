"""Explicit local paths for offline research tools; no machine-specific defaults."""
import os
from pathlib import Path

def require_path(name):
    value = os.environ.get(name, '').strip()
    if not value:
        raise RuntimeError(f'Set {name} to an explicit local path; see DNA_RESEARCH_TOOLS.md')
    path = Path(value).expanduser()
    if not path.is_absolute():
        raise ValueError(f'{name} must be an absolute local path')
    return path.resolve()
