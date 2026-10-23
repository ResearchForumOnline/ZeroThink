#!/usr/bin/env python3
"""ZeroThink CLI.

Cross-platform terminal client for ZeroThink.
It supports two access lanes:
- linked account mode using the same ZeroThink web account and vault keys
- direct key mode for Groq or NVIDIA using machine-local keys only
"""

import argparse
import base64
import contextlib
import getpass
import hashlib
import hmac
import html
import io
import json
import os
import pathlib
import platform
import re
import shlex
import shutil
import stat
import secrets
import subprocess
import sys
import tarfile
import tempfile
import textwrap
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import webbrowser
import zipfile
import zlib
from typing import Dict, List, Optional, Tuple

try:
    from prompt_toolkit import PromptSession
    from prompt_toolkit.completion import Completer, Completion
    from prompt_toolkit.history import FileHistory
    PROMPT_TOOLKIT_AVAILABLE = True
except Exception:
    PromptSession = None
    Completer = object  # type: ignore[assignment]
    Completion = None  # type: ignore[assignment]
    FileHistory = None  # type: ignore[assignment]
    PROMPT_TOOLKIT_AVAILABLE = False

try:
    import paramiko
    PARAMIKO_AVAILABLE = True
except Exception:
    paramiko = None  # type: ignore[assignment]
    PARAMIKO_AVAILABLE = False

VERSION = "3.7.2"
BASE_URL = os.getenv("ZEROTHINK_BASE_URL", "https://zerothink.talktoai.org").rstrip("/")
CONFIG_DIR = pathlib.Path.home() / ".zerothink"
CONFIG_FILE = CONFIG_DIR / "config.json"
HISTORY_FILE = CONFIG_DIR / "history.txt"
ROUTE_FILE = CONFIG_DIR / "last_route.json"
SESSIONS_DIR = CONFIG_DIR / "sessions"
EXPORTS_DIR = CONFIG_DIR / "exports"
AGENTS_DIR = CONFIG_DIR / "agents"
LIBRARY_DIR = CONFIG_DIR / "library"
LIBRARY_INDEX_FILE = CONFIG_DIR / "library_index.json"
LAST_ROUTE_INFO: Dict[str, str] = {}
DIRECT_KEY_PROVIDERS = ("groq", "nvidia")
STEERING_MODES = ("auto", "plan", "confirm")
TUTORIAL_VERSION = 1
CONTEXT_FILENAMES = (
    "ZEROTHINK.md",
    ".zerothink.md",
    "ZEROTHINK.txt",
    "CLAUDE.md",
    "AGENTS.md",
    ".claude/CLAUDE.md",
)
COMMAND_FILE_NAMES = ("ZEROTHINK.commands.json", ".zerothink.commands.json")
CRYPTO_MAGIC = b"ZTZP1\0"
CRYPTO_DEFAULT_ITERATIONS = 420000
CRYPTO_MIN_ITERATIONS = 50000
CRYPTO_EXTENSION = ".ztz"

BANNER = r"""
███████╗███████╗██████╗  ██████╗
╚══███╔╝██╔════╝██╔══██╗██╔═══██╗
  ███╔╝ █████╗  ██████╔╝██║   ██║
 ███╔╝  ██╔══╝  ██╔══██╗██║   ██║
███████╗███████╗██║  ██║╚██████╔╝
╚══════╝╚══════╝╚═╝  ╚═╝ ╚═════╝
"""

ASCII_BANNER = r"""
=====================
    ZERO THINK CLI
=====================
"""

HELP_TOPICS = {
    "overview": """
    ZeroThink CLI aka Agent Zero is the terminal operator and coding client for ZeroThink.

    It has two access modes:
    - linked account mode using the same ZeroThink account as the website
    - direct key mode using a local Groq or NVIDIA key without Google login

    What it can do:
    - chat with your saved provider + model defaults
    - execute plain-English build and fix tasks through the local tool stack in the interactive prompt
    - attach files, folders, and archives for code analysis
    - encrypt/decrypt local files and folder bundles with the Z-Pepper crypto envelope
    - build a no-token local Zero Library index in front of the agent and send only compact snippets when needed
    - create evidence-first papers, articles, surveys, reviews, and proposals from scoped local or saved-server files
    - run local DNA/RNA sequence analysis without a model call
    - save, export, and resume local session checkpoints
    - auto-load project guidance from ZEROTHINK.md, CLAUDE.md, AGENTS.md, or .claude/CLAUDE.md
    - search the web locally and keep browsed pages inside the session
    - run local file and shell workflows
    - save SSH server profiles for remote work
    - switch between safer repo-only mode and full-machine mode

    Best first commands:
    - zerothink
    - zerothink tutorial
    - zerothink help quickstart
    - zerothink status
    - zerothink context show
    - zerothink library add .
    - zerothink library search "payment callback"
    - zerothink game new mini-runner --title "Mini Runner"
    - zerothink research paper "AI governance" --source ./papers --stage outline
    - zerothink dna analyze sample.fasta
    - zerothink search "latest nginx php-fpm hardening"
    - zerothink crypto encrypt secrets.txt --pattern "my zpepper"
    - zerothink session list
    - zerothink login
    - zerothink key set groq
    - zerothink access folder .
    """,
    "about": """
    ZeroThink CLI identity:

    - Name: ZeroThink CLI aka Agent Zero
    - Role: terminal operator, coding client, and automation shell for ZeroThink
    - Style: practical terminal help, not a generic chatbot identity

    What Agent Zero can do:
    - chat through the current provider/model route
    - inspect, read, write, append, replace, copy, move, and remove local files
    - scan repos, read large files, and attach zips, folders, and URLs
    - run local shell commands
    - manage local checkpoints, exports, sessions, plans, and saved agents
    - search the web locally, fetch pages, keep browser-page context, and attach pages
    - use MCP servers and Playwright browser automation
    - manage SSH profiles, run remote commands, transfer files, and edit remote files
    - build research source inventories and export citation-disciplined Markdown from local or remote evidence
    - self-drive bounded tasks with autopilot and named agents such as zero1, zero2, and zero3

    What it should not claim:
    - it should not describe itself as a generic AI assistant or GPT product
    - it should not invent hidden powers that are not actually wired into the CLI

    Best commands:
    - zerothink about
    - zerothink capabilities
    - zerothink runtime
    - zerothink status
    - /about
    - /capabilities
    """,
    "tutorial": """
    First-run tutorial:

    - The first plain `zerothink` launch now offers a guided setup.
    - It helps you choose linked-account mode or no-Google direct mode.
    - It can switch you into safer folder-only access.
    - It can detect local environment keys like GROQ_API_KEY or NVIDIA_API_KEY.
    - It does not force a choice forever: you can skip and rerun later.

    Ways to open it again:
    - zerothink tutorial
    - /tutorial

    What the tutorial explains:
    - linked account mode = same ZeroThink account, Vault-backed provider keys, normal account features
    - direct key mode = Groq/NVIDIA only, local or env key, no memory/history/web/Pro account features
    """,
    "quickstart": """
    Quickstart for complete beginners:

    1. Install the CLI.
       Windows:
       irm https://zerothink.talktoai.org/cli-assets/install.ps1 | iex

       Linux / macOS:
       curl -fsSL https://zerothink.talktoai.org/cli-assets/install.sh | bash

    2. Start the built-in guide if you want step-by-step setup.

       zerothink
       or
       zerothink tutorial

    3. Pick one of these two login paths.

       Normal linked-account path:
       zerothink login

       No-Google direct-key path:
       zerothink key set groq
       or
       zerothink key set nvidia

    4. Put the CLI in safer folder-only mode for one repo.
       zerothink access folder .

    5. Start the interactive terminal.
       zerothink

    6. Inside the prompt, ask a plain-English question.
       ZERO> summarize this project
       ZERO> /scan
       ZERO> /read README.md
       ZERO> /he<TAB>

    7. If you want the CLI to stage action tasks before running them, switch steering mode.
       ZERO> /steer plan
       ZERO> make a new project folder for shafaet.com
       ZERO> /go
       ZERO> /stop

    Direct mode reminder:
    - the CLI can auto-detect GROQ_API_KEY or NVIDIA_API_KEY from your shell
    - direct mode does not use ZeroThink account memory/history/Pro features

    If you do not know what to type next, run:
    - zerothink help examples
    - /help examples
    """,
    "repl": """
    Interactive prompt basics:

    - Just type a normal sentence to chat with ZeroThink.
    - If the line is an action request like "make a new project folder" or "fix this config", Agent Zero now routes it into real execution mode by default.
    - Commands start with a slash, like /help or /read.
    - Press TAB to autocomplete slash commands, providers, models, and common paths when prompt-toolkit is available.
    - /attach adds files, folders, or zip archives to the next prompts.
    - /files shows what is currently attached.
    - /checkpoint saves the current local session and /resume loads it back.
    - /context show prints the active ZEROTHINK.md project guidance file if one exists.
    - /detach all clears attachments.
    - /steer auto runs action prompts immediately, while /steer plan or /steer confirm stages them first.
    - /go runs the currently staged task.
    - /stop discards the staged task or requests stop for the latest active saved agent.
    - /quit exits the session.

    Common beginner flow:
    - /scan
    - /tree
    - /read path/to/file
    - /search-web production php-fpm tuning
    - /page 1
    - /attach-page 1
    - explain what this code does
    """,
    "sessions": """
    Local checkpointing and export:

    - /checkpoint NAME saves the current local CLI session
    - /resume NAME loads it back into the REPL
    - /history shows recent local turns
    - /export writes the current session to a Markdown file
    - /new clears the current local session without changing your saved config

    Top-level management:
    - zerothink session list
    - zerothink session show NAME
    - zerothink session export NAME [PATH]
    - zerothink session rm NAME

    Important note:
    - this is local machine checkpointing, not ZeroThink account memory
    - direct-key mode still does not gain website memory/history/Pro state
    """,
    "plan": """
    Local plan board:

    ZeroThink CLI now keeps a lightweight local task board inside the REPL.

    Best use:
    - break a larger coding job into steps
    - keep track of what is done vs still open
    - checkpoint the plan with the rest of the local session

    Inside the REPL:
    - /plan
    - /plan add review auth flow
    - /plan done 1
    - /plan undo 1
    - /plan drop 1
    - /plan clear

    Alias:
    - /todo

    Notes:
    - the plan board is local to this CLI session and its checkpoints
    - it is not the same as ZeroThink account memory/history
    """,
    "context": """
    Project context files:

    ZeroThink CLI can auto-load a project guidance file when it sees:
    - ZEROTHINK.md
    - .zerothink.md
    - ZEROTHINK.txt
    - CLAUDE.md
    - AGENTS.md
    - .claude/CLAUDE.md

    Best use:
    - coding standards
    - architecture notes
    - important commands
    - test/run instructions
    - constraints like "never touch production config"

    Useful commands:
    - zerothink context
    - zerothink context show
    - zerothink context on
    - zerothink context off

    Inside the REPL:
    - /context
    - /context show
    - /context on
    - /context off
    """,
    "fetch": """
    URL fetch and web context:

    ZeroThink CLI can fetch normal web pages and attach the cleaned text
    to prompts locally.

    Top-level commands:
    - zerothink fetch URL
    - zerothink chat --url URL "summarize this"

    Inside the REPL:
    - /fetch URL
    - /attach-url URL
    - /detach-url URL

    Notes:
    - this is local URL fetching, not website memory/history
    - direct mode can still use local URL fetch and browser tools
    """,
    "browser": """
    Local browser and search tools:

    ZeroThink CLI now has a local browser-style research lane.

    Top-level:
    - zerothink search "query"
    - zerothink browse https://example.com

    Inside the REPL:
    - /search-web production php hardening
    - /pages
    - /page 1
    - /attach-page 1
    - /clear-pages
    - /browse https://example.com/spec

    What it does:
    - search stores numbered results in the current local session
    - page open fetches and cleans the page text locally
    - attach-page adds that page URL to the active prompt context
    - checkpoints and exports keep the browser state too

    Important:
    - this is a local CLI browser helper, not a full desktop browser
    - it is useful for docs, changelogs, ops notes, and research work
    - direct mode can still use these local browser tools
    """,
    "mcp": """
    MCP and Playwright automation:

    ZeroThink CLI can register local stdio MCP servers and call their tools.

    Best first setup:
    - zerothink mcp add-playwright
    - zerothink mcp list
    - zerothink mcp tools playwright
    - zerothink playwright open https://example.com

    Call a tool:
    - zerothink mcp call playwright browser_navigate --arg url=https://example.com
    - zerothink playwright call browser_snapshot

    Inside the REPL:
    - /mcp list
    - /mcp tools playwright
    - /playwright open https://example.com
    - /playwright call browser_snapshot

    Notes:
    - this is a local MCP client layer, not a hosted ZeroThink web feature
    - the built-in Playwright shortcut uses `npx --yes @playwright/mcp@latest --headless --isolated`
    - Node.js 18+ is the safe baseline for Playwright MCP
    - ZeroThink CLI speaks newline-delimited stdio MCP, which is the current MCP transport path most tools expect
    """,
    "custom": """
    Custom command packs:

    ZeroThink CLI can auto-load local command packs from:
    - ZEROTHINK.commands.json
    - .zerothink.commands.json

    Simple command pack example:
    {
      "review": {
        "prompt": "Review {args} with findings first."
      },
      "test": {
        "shell": "pytest {args}"
      }
    }

    Helpful commands:
    - zerothink custom
    - zerothink custom show

    Inside the REPL:
    - /custom
    - /custom show

    Prompt commands send a templated chat request.
    Shell commands run locally and require machine mode.
    """,
    "access": """
    Access modes:

    machine mode
    - full local machine access
    - can run zerothink exec, ssh, scp
    - best for advanced operators

    folder mode
    - locks file actions to one workspace root
    - safer for repos and shared machines
    - best default for most people

    Commands:
    - zerothink access status
    - zerothink access folder .
    - zerothink access machine
    - zerothink workspace /path/to/project
    """,
    "files": """
    File and archive workflows:

    Attach code or text to chat:
    - zerothink chat --file app.py "explain this file"
    - /attach src/
    - /attach logs.zip

    Browse the workspace:
    - zerothink fs ls .
    - zerothink fs tree . --depth 2
    - zerothink fs read README.md --start 1 --lines 80
    - zerothink fs search "TODO|FIXME" .

    Zip workflows:
    - zerothink zip list backup.zip
    - zerothink zip extract backup.zip extracted
    - zerothink zip create code-pack.zip src README.md

    Safety notes:
    - zip extraction blocks unsafe archive paths
    - local attachments are capped so sessions do not bloat forever
    """,
    "remote": """
    Remote server workflows:

    Save a server profile once:
    - zerothink server add research server.example.com --user researcher --port 22 --cwd /srv/research

    Password-capable profiles:
    - zerothink server add research server.example.com --user researcher --port 22 --ask-pass
    - zerothink server add research server.example.com --user researcher --password-env ZEROTHINK_RESEARCH_PASS

    Inspect saved profiles:
    - zerothink server list
    - zerothink server show research

    Run remote commands:
    - zerothink ssh research "pwd && ls"

    Transfer files:
    - zerothink scp put research notes.md /srv/research/notes.md
    - zerothink scp get research /srv/research/results.csv results.csv

    Remote file workspace:
    - zerothink remote ls research /srv/research
    - zerothink remote read research /srv/research/notes.md
    - zerothink remote write research /srv/research/status.txt --text "reviewed"

    Note:
    - remote actions require machine mode
    - folder mode blocks ssh/scp/remote on purpose
    """,
    "research": """
    Evidence-first research paper, article, review, proposal, and survey workflows:

    Work from local files:
    - zerothink research paper "AI-assisted literature discovery" --source ./papers --source ./notes.md
    - zerothink research article "Operational AI governance" --source ./evidence --stage draft
    - zerothink research survey "Research-agent evaluation methods" --source ./library --citation-style "APA 7"

    Work from a saved server profile:
    - zerothink research review "Production reliability evidence" --server research --remote-path /srv/research/reports
    - zerothink research scan --server research --remote-path /srv/research/reports

    Control the workflow:
    - --stage protocol|evidence|outline|draft|critique|revision|full
    - --question "Exact research question"
    - --method "PRISMA-style review"
    - --audience "Journal reviewers"
    - --citation-style Harvard
    - --web to enable live research when the current account route supports it
    - --library to add a compact private Zero Library evidence pack
    - --output paper.md to choose the Markdown export path
    - --dry-run to inspect the source plan without calling a model

    Integrity rules:
    - supplied files are evidence inputs, not automatic proof that every claim is true
    - citations, quotations, statistics, authors, DOIs, URLs, and page numbers must be traceable
    - search snippets are discovery leads, not confirmation that full text was read
    - common secret files, credentials, private keys, and dependency folders are excluded by default
    - use --include-sensitive only in a trusted terminal after reviewing the source scope
    """,
    "freewebpanel": """
    FreeWebPanel server mode:

    ZeroThink includes a first-class operator lane for inspecting, backing up, checking,
    explaining, and planning work on FreeWebPanel servers.

    Start here:
    - zerothink freewebpanel help
    - zerothink fwp status
    - zerothink fwp doctor --verbose
    - zerothink fwp backup --path /opt/freewebpanel --dry-run

    Focused checks:
    - zerothink fwp domains --domain example.com
    - zerothink fwp apache
    - zerothink fwp php
    - zerothink fwp ssl --domain example.com
    - zerothink fwp permissions --path /var/www/example.com
    - zerothink fwp logs --lines 80

    Change planning:
    - zerothink fwp install --server-mode ubuntu --hostname panel.example.com --email admin@example.com --dry-run
    - zerothink fwp install --server-mode rhel-family --hostname panel.example.com --email admin@example.com --preflight-only
    - zerothink fwp install --server-mode openzero-aios --hostname web.example.com --email admin@example.com --dry-run
    - zerothink fwp repair --backup-first --dry-run
    - zerothink fwp update --backup-first --dry-run
    - zerothink fwp deploy --path ./release --domain example.com --dry-run

    Safety:
    - inspection commands are read-only
    - change-oriented commands default to a plan and require explicit --yes
    - --backup-first is the recommended setting before repair, update, or deploy
    - --redact removes common secret values from config and log previews
    - --json emits machine-readable status output
    """,
    "agents": """
    Agent and autopilot workflows:

    Fire off a self-driven worker:
    - zerothink autopilot "audit this repo and fix the auth bugs"

    Create or run named agents:
    - zerothink agent create zero1 "map the codebase and list risks"
    - zerothink agent run zero1 --steps 8
    - zerothink agent stop zero1
    - zerothink agent step zero1
    - zerothink agent list
    - zerothink crypto status
    - zerothink crypto encrypt FILE
    - zerothink crypto decrypt FILE.ztz
    - zerothink agent show zero1
    - zerothink agent log zero1

    Built-in subagents:
    - the autopilot controller can spawn child workers like zero2, zero3, and zero4
    - child workers run side tasks and report back into the parent log

    What agents can use:
    - local file reads, scans, writes, and replacements
    - local shell execution
    - browser search/fetch
    - SSH, SCP, and remote file reads/writes on saved server profiles
    - Playwright MCP and other MCP tools only when machine mode allows it

    Note:
    - agent mode is local orchestration, not the hidden desktop runtime
    - folder mode still fences local file actions to the workspace root
    - if a run is in progress, you can request a stop with `zerothink stop zero1`, `zerothink agent stop zero1`, or `Ctrl+C` from the current terminal
    """,
    "models": """
    Provider and model control:

    Show curated models:
    - zerothink models
    - zerothink models coding
    - zerothink models groq
    - zerothink models nvidia

    Set local defaults:
    - zerothink config provider groq
    - zerothink config model openai/gpt-oss-120b

    Current coding defaults:
    - Groq: openai/gpt-oss-120b
    - NVIDIA: qwen/qwen3-coder-480b-a35b-instruct

    Change inside the interactive prompt:
    - /provider groq
    - /model openai/gpt-oss-120b
    - /models coding
    - /web off

    Reminder:
    - linked account mode uses the same ZeroThink account and vault-backed provider keys as the website
    - direct key mode works with Groq and NVIDIA only and stores keys locally on this machine
    - CLI trace mode is retired; the full ZeroThink reasoning lane stays in the web app
    """,
    "library": """
    Zero Library:

    This is the no-token knowledge layer in front of Agent Zero.
    It indexes local folders and files using normal Python, then sends only compact evidence snippets when you ask for them.

    Why it matters:
    - search and indexing cost zero AI tokens
    - big repos, notes, FASTA files, logs, and docs can stay local
    - chat only receives a small evidence pack instead of whole folders

    Commands:
    - zerothink library status
    - zerothink library add .
    - zerothink library add C:\\path\\to\\research
    - zerothink library index
    - zerothink library files
    - zerothink library search "paypal callback"
    - zerothink library context "model routing bug"
    - zerothink library remove C:\\path\\to\\research

    Interactive:
    - /library status
    - /library add .
    - /library search query
    - /library context query

    Chat shortcut:
    - mention @library in a prompt and the CLI will attach a compact local evidence pack automatically.
    """,
    "dna": """
    DNA Lab:

    Agent Zero can do deterministic research-only sequence analysis locally.
    This is not medical, ancestry, diagnostic, or clinical interpretation.

    Commands:
    - zerothink dna analyze sample.fasta
    - zerothink dna analyze ATGGCCATTGTAATGGGCCGCTGAAAGGGTGCCCGATAG

    What it returns:
    - length and base counts
    - GC and AT percentage
    - start/stop/CpG/poly-A/poly-T motif counts
    - longest forward ORFs
    - translation previews
    - reverse-complement preview

    If you use @library with DNA terms, the local library can also retrieve relevant sequence files before chat.
    """,
    "examples": """
    Real prompts you can copy:

    Understand a repo:
    - zerothink access folder .
    - zerothink
    - /plan add map the auth flow
    - /scan
    - describe the architecture

    Debug a file:
    - zerothink chat --file app.py "find likely bugs and explain them simply"

    Search a codebase:
    - zerothink fs search "api_key|secret|token" .

    Research a live issue:
    - zerothink search "nginx upstream prematurely closed connection php-fpm"
    - zerothink browse https://nginx.org/en/docs/

    MCP browser automation:
    - zerothink mcp add-playwright
    - zerothink mcp tools playwright

    Package files for review:
    - zerothink zip create audit-pack.zip src README.md composer.json

    Remote check:
    - zerothink ssh prod "systemctl status nginx"

    FreeWebPanel check:
    - zerothink fwp doctor --verbose
    - zerothink fwp backup --path /opt/freewebpanel --dry-run
    """,
    "commands": """
    Most-used commands:

    Account:
    - zerothink about
    - zerothink capabilities
    - zerothink tutorial
    - zerothink login
    - zerothink logout
    - zerothink status
    - zerothink me
    - zerothink doctor
    - zerothink key status
    - zerothink key set groq
    - zerothink key clear groq
    - zerothink models coding
    - zerothink search "query"
    - zerothink browse URL
    - zerothink mcp list
    - zerothink mcp tools playwright
    - zerothink autopilot "task"
    - zerothink agent list
    - zerothink crypto status
    - zerothink site new my-site --theme cyber
    - zerothink game new mini-runner --title "Mini Runner"
    - zerothink library status
    - zerothink library search "billing"
    - zerothink dna analyze sample.fasta

    Interactive:
    - zerothink
    - /about
    - /capabilities
    - /tutorial
    - /help
    - /guide
    - /steer plan
    - /go
    - /stop
    - /plan add fix login flow
    - /plan
    - /checkpoint release-audit
    - /resume release-audit
    - /context show
    - /custom show
    - /search-web latest gemma ollama docs
    - /page 1
    - /attach-page 1
    - /mcp list
    - /attach-url https://example.com
    - /autopilot audit this repo and fix the auth flow
    - /agent list
    - /crypto status
    - /game new mini-runner
    - /pdf create report.pdf --title Report --text "summary"
    - /remote doctor prod /var/www/html
    - /library status
    - /library search billing flow
    - /dna analyze ATGGCCATTGTAATGGGCCGCTGAAAGGGTGCCCGATAG
    - /encrypt FILE
    - /decrypt FILE.ztz
    - /review src/app.py
    - /attach PATH
    - /files
    - /quit

    Workspace:
    - zerothink fs ls .
    - zerothink fs tree .
    - zerothink fs read FILE
    - zerothink fs search REGEX .
    - zerothink context show
    - zerothink session list
    - zerothink custom show

    Execution:
    - zerothink exec "git status"
    - zerothink ssh NAME COMMAND
    - zerothink scp put NAME LOCAL REMOTE
    - zerothink remote read NAME PATH
    - zerothink remote doctor NAME /var/www/html
    - zerothink server check NAME /var/www/html
    - zerothink pdf create report.pdf --title "Report" --file notes.md
    - zerothink remote write NAME PATH --text "..."
    - zerothink autopilot "task"
    - zerothink agent run zero1 --steps 8
    - zerothink crypto pack bundle.ztz ./project
    - zerothink crypto unpack bundle.ztz ./restored
    - zerothink site new ./launch-page --theme zero
    - zerothink fetch URL
    - zerothink search "query"
    - zerothink browse URL
    - zerothink review PATH
    - zerothink update

    FreeWebPanel:
    - zerothink freewebpanel help
    - zerothink fwp status
    - zerothink fwp health --json
    - zerothink fwp logs --lines 80
    - zerothink fwp backup --path /opt/freewebpanel --dry-run
    - zerothink fwp repair --backup-first --dry-run
    """,
    "skills": """
    Agent Zero skill catalog:

    - coding: inspect, edit, search, scaffold, run commands, review, and package code
    - files: read, write, replace, zip, encrypt, decrypt, scan, and attach local context
    - PDF: create simple text/report PDFs locally with `zerothink pdf create`
    - servers: use saved SSH profiles for remote health checks, file reads, searches, edits, and commands
    - browser: local web search, URL fetch, saved page context, Playwright MCP automation
    - agents: autopilot and named workers such as zero1, zero2, and zero3
    - research: source inventory, evidence ledger, outlines, drafts, critiques, and proposals
    - crypto: local Z-Pepper encrypted file and folder bundles

    Useful commands:
    - zerothink skills
    - zerothink pdf create report.pdf --title "Server Notes" --text "Checked logs"
    - zerothink server check prod /var/www/html
    - zerothink remote doctor prod /var/www/html
    - zerothink autopilot "check server prod files and summarize risks"
    """,
    "pdf": """
    PDF creation:

    Agent Zero can create simple local PDF reports without external packages.
    This is for notes, summaries, logs, checklists, research drafts, and operator reports.

    Useful commands:
    - zerothink pdf create report.pdf --title "Report" --text "Hello"
    - zerothink pdf create report.pdf --title "Deploy Notes" --file notes.md
    - /pdf create report.pdf --title "Report" --text "Hello"

    Notes:
    - the PDF writer is local-only and does not upload the source text
    - it is intentionally text-first, not a full desktop publishing engine
    """,
    "crypto": """
    Z-Pepper file crypto:

    Agent Zero can encrypt local files or folder bundles without sending contents to the website.
    The envelope uses:
    - passphrase
    - optional Z-Pepper pattern derived with the old Zero 7045 seed
    - PBKDF2-HMAC-SHA256
    - compressed payload
    - authenticated HMAC verification before decrypt output is written

    Useful commands:
    - zerothink crypto status
    - zerothink crypto zpepper set
    - zerothink crypto zpepper clear
    - zerothink crypto encrypt secrets.txt
    - zerothink crypto encrypt ./private-folder
    - zerothink crypto lock secrets.txt
    - zerothink crypto decrypt secrets.txt.ztz
    - zerothink crypto unlock private-folder.ztz
    - zerothink crypto inspect secrets.txt.ztz
    - zerothink crypto pack project.ztz ./project
    - zerothink crypto unpack project.ztz ./project-restored

    Safer usage:
    - omit --passphrase so the CLI prompts securely
    - use --pass-env NAME if automation must provide the passphrase
    - use --pattern or a saved local zpepper for the second layer

    Notes:
    - direct-key mode and linked-account mode do not change this; crypto is local-first
    - losing the passphrase or the Z-Pepper pattern means the file cannot be recovered
    - for regulated enterprise secrets, keep offline backups and consider external audited tools too
    """,
}

REPL_COMMANDS = [
    "help",
    "about",
    "capabilities",
    "skills",
    "guide",
    "tutorial",
    "tips",
    "login",
    "logout",
    "key",
    "whoami",
    "status",
    "runtime",
    "doctor",
    "update",
    "library",
    "lib",
    "dna",
    "history",
    "plan",
    "steer",
    "go",
    "stop",
    "todo",
    "checkpoint",
    "resume",
    "export",
    "new",
    "context",
    "custom",
    "fetch",
    "search-web",
    "browse",
    "pages",
    "page",
    "attach-page",
    "clear-pages",
    "playwright",
    "pw",
    "attach-url",
    "detach-url",
    "review",
    "autopilot",
    "team",
    "agent",
    "crypto",
    "pdf",
    "encrypt",
    "decrypt",
    "lock",
    "unlock",
    "protect",
    "files",
    "access",
    "workspace",
    "plugins",
    "server",
    "servers",
    "mcp",
    "ssh",
    "scp",
    "remote",
    "game",
    "pwd",
    "cd",
    "ls",
    "tree",
    "scan",
    "read",
    "search",
    "exec",
    "attach",
    "detach",
    "provider",
    "model",
    "models",
    "zero",
    "web",
    "site",
    "zip",
    "clear",
    "quit",
    "exit",
]

BOOLEAN_COMPLETIONS = ["on", "off", "true", "false", "yes", "no", "1", "0"]

RAW_TAIL_REPL_COMMANDS = {
    "attach",
    "attach-url",
    "cd",
    "checkpoint",
    "detach",
    "detach-url",
    "export",
    "fetch",
    "browse",
    "search-web",
    "resume",
    "workspace",
}

PATH_NUMERIC_TAIL_REPL_COMMANDS = {
    "ls": 0,
    "scan": 0,
    "tree": 1,
    "read": 2,
}

PROVIDERS = {
    "groq": {
        "env": "GROQ_API_KEY",
        "base_url": "https://api.groq.com/openai/v1/chat/completions",
        "default_model": "openai/gpt-oss-120b",
    },
    "nvidia": {
        "env": "NVIDIA_API_KEY",
        "base_url": "https://integrate.api.nvidia.com/v1/chat/completions",
        "default_model": "qwen/qwen3-coder-480b-a35b-instruct",
    },
    "openai": {
        "env": "OPENAI_API_KEY",
        "base_url": "https://api.openai.com/v1/chat/completions",
        "default_model": "gpt-5.5",
    },
    "xai": {
        "env": "XAI_API_KEY",
        "base_url": "https://api.x.ai/v1/chat/completions",
        "default_model": "grok-4-latest",
    },
    "gemini": {
        "env": "GEMINI_API_KEY",
        "base_url": "https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}",
        "default_model": "gemini-2.5-pro",
    },
}

MODEL_CATALOG = {
    "groq": [
        "openai/gpt-oss-120b",
        "groq/compound",
        "groq/compound-mini",
        "openai/gpt-oss-20b",
        "qwen/qwen3.6-27b",
        "llama-3.1-8b-instant",
        "allam-2-7b",
    ],
    "nvidia": [
        "qwen/qwen3-coder-480b-a35b-instruct",
        "openai/gpt-oss-120b",
        "qwen/qwen2.5-coder-32b-instruct",
        "qwen/qwen3-next-80b-a3b-instruct",
        "qwen/qwen3-5-122b-a10b",
        "meta/llama-3.3-70b-instruct",
        "meta/llama-3.1-405b-instruct",
        "meta/llama-4-maverick-17b-128e-instruct",
        "google/gemma-4-31b-it",
        "google/gemma-3-27b-it",
        "google/gemma-3n-e4b-it",
        "google/codegemma-7b",
        "google/codegemma-1.1-7b",
        "deepseek-ai/deepseek-v3.2",
        "deepseek-ai/deepseek-v3.1",
        "deepseek-ai/deepseek-r1",
        "deepseek-ai/deepseek-r1-distill-qwen-32b",
        "mistralai/mistral-large",
        "mistralai/mistral-large-2-instruct",
        "mistralai/mistral-large-3-675b-instruct-2512",
        "mistralai/mistral-medium-3-instruct",
        "mistralai/mistral-nemotron",
        "mistralai/devstral-2-123b-instruct-2512",
        "mistralai/mistral-small-3.1-24b-instruct-2503",
        "mistralai/codestral-22b-instruct-v0.1",
        "mistralai/mamba-codestral-7b-v0.1",
        "mistralai/mixtral-8x22b-instruct",
        "nvidia/nemotron-3-super-120b-a12b",
        "nvidia/nemotron-3-nano-30b-a3b",
        "nvidia/llama-3.3-nemotron-super-49b-v1.5",
        "nvidia/llama-3.1-nemotron-ultra-253b-v1",
    ],
    "openai": [
        "gpt-5.5",
        "gpt-5.5-pro",
        "gpt-5.4",
        "gpt-5.4-mini",
        "gpt-5.4-nano",
        "gpt-5.2",
        "gpt-5.2-pro",
        "gpt-5-pro",
        "gpt-5.1",
        "gpt-5",
        "gpt-5-mini",
        "gpt-5-nano",
    ],
    "xai": [
        "grok-4-latest",
        "grok-4",
        "grok-4-fast-reasoning",
        "grok-4-fast-non-reasoning",
        "grok-4-1-fast-reasoning",
        "grok-4-1-fast-non-reasoning",
        "grok-code-fast-1",
        "grok-3",
        "grok-3-mini",
    ],
    "gemini": [
        "gemini-2.5-pro",
        "gemini-2.5-flash",
        "gemini-2.5-flash-lite",
        "gemini-3-pro-preview",
        "gemini-3-flash-preview",
    ],
}

AUTOPILOT_MODEL_FALLBACKS = {
    "groq": ["qwen/qwen3.6-27b", "groq/compound-mini", "openai/gpt-oss-20b"],
    "nvidia": ["meta/llama-3.3-70b-instruct", "openai/gpt-oss-120b", "google/gemma-4-31b-it"],
    "openai": ["gpt-5.4-mini", "gpt-5.4-nano", "gpt-5.2"],
    "xai": ["grok-4-fast-reasoning", "grok-code-fast-1"],
    "gemini": ["gemini-2.5-flash", "gemini-2.5-pro"],
}

AUTOPILOT_DEFAULT_MODELS = {
    "groq": "openai/gpt-oss-120b",
    "nvidia": "qwen/qwen3-coder-480b-a35b-instruct",
    "openai": "gpt-5.5",
    "xai": "grok-4-fast-reasoning",
    "gemini": "gemini-2.5-flash",
}

MODEL_CODING_PICKS = {
    "groq": [
        "openai/gpt-oss-120b",
        "qwen/qwen3.6-27b",
        "openai/gpt-oss-20b",
        "groq/compound-mini",
    ],
    "nvidia": [
        "qwen/qwen3-coder-480b-a35b-instruct",
        "openai/gpt-oss-120b",
        "meta/llama-3.3-70b-instruct",
        "google/gemma-4-31b-it",
        "qwen/qwen2.5-coder-32b-instruct",
        "mistralai/codestral-22b-instruct-v0.1",
    ],
    "openai": [
        "gpt-5.5",
        "gpt-5.5-pro",
        "gpt-5.4",
        "gpt-5.4-mini",
        "gpt-5.2",
        "gpt-5-pro",
        "gpt-5.1",
    ],
    "xai": [
        "grok-code-fast-1",
        "grok-4-fast-reasoning",
    ],
    "gemini": [
        "gemini-2.5-pro",
        "gemini-2.5-flash",
    ],
}

MODEL_NOTES = {
    "groq": {
        "openai/gpt-oss-120b": "best overall Groq coding default",
        "openai/gpt-oss-20b": "smaller lower-latency GPT-OSS",
        "groq/compound": "agentic system with built-in tools",
        "groq/compound-mini": "fast agentic system",
        "qwen/qwen3.6-27b": "newer Qwen preview model on Groq",
        "allam-2-7b": "Arabic-English bilingual model on Groq",
    },
    "nvidia": {
        "qwen/qwen3-coder-480b-a35b-instruct": "largest NVIDIA coding pick",
        "openai/gpt-oss-120b": "strong reasoning and coding",
        "meta/llama-3.3-70b-instruct": "reliable general coding fallback",
        "google/gemma-4-31b-it": "frontier reasoning and coding",
        "qwen/qwen2.5-coder-32b-instruct": "smaller dedicated coder",
        "mistralai/codestral-22b-instruct-v0.1": "dedicated Mistral code model",
    },
    "openai": {
        "gpt-5.5": "current OpenAI flagship for complex coding and professional work",
        "gpt-5.5-pro": "heaviest premium OpenAI lane",
        "gpt-5.4": "strong lower-cost OpenAI coding lane",
        "gpt-5.4-mini": "fast mini OpenAI coding and subagent lane",
        "gpt-5.2": "previous frontier fallback",
        "gpt-5.2-pro": "previous premium OpenAI lane",
        "gpt-5-pro": "high-compute GPT-5 reasoning option",
        "gpt-5.1": "older flagship fallback",
        "gpt-5-mini": "older lower-cost OpenAI fallback",
    },
}

MODEL_ALIASES = {
    "groq": {
        "gpt-oss-120b": "openai/gpt-oss-120b",
        "gpt-oss-20b": "openai/gpt-oss-20b",
        "qwen-2.5-32b": "openai/gpt-oss-120b",
        "qwen-2.5-coder-32b": "openai/gpt-oss-120b",
        "qwen-3-32b": "openai/gpt-oss-120b",
        "qwen/qwen3-32b": "openai/gpt-oss-120b",
        "qwen-qwq-32b": "openai/gpt-oss-120b",
        "deepseek-r1-distill-qwen-32b": "openai/gpt-oss-120b",
        "deepseek-r1-distill-llama-70b": "openai/gpt-oss-120b",
        "kimi-k2": "openai/gpt-oss-120b",
        "moonshotai/kimi-k2-instruct-0905": "openai/gpt-oss-120b",
        "llama-3.3-70b": "openai/gpt-oss-120b",
        "llama-3.3-70b-versatile": "openai/gpt-oss-120b",
        "llama-3.3-70b-specdec": "openai/gpt-oss-120b",
        "llama-4-maverick": "openai/gpt-oss-120b",
        "meta-llama/llama-4-maverick-17b-128e-instruct": "openai/gpt-oss-120b",
        "llama-4-scout": "openai/gpt-oss-120b",
        "meta-llama/llama-4-scout-17b-16e-instruct": "openai/gpt-oss-120b",
        "qwen3.6-27b": "qwen/qwen3.6-27b",
        "qwen-3.6-27b": "qwen/qwen3.6-27b",
        "allam": "allam-2-7b",
    },
    "openai": {
        "gpt-5-turbo": "gpt-5.5",
        "gpt-5-o": "gpt-5.5",
        "gpt-5.5 pro": "gpt-5.5-pro",
        "gpt-5.4-pro": "gpt-5.5-pro",
        "o3-max": "gpt-5.5",
        "gpt-5.2 pro": "gpt-5.2-pro",
        "gpt-5 pro": "gpt-5-pro",
        "gpt-5.1-chat": "gpt-5.1",
        "gpt-5.1-chat-latest": "gpt-5.1",
    },
    "gemini": {
        "gemini-3": "gemini-3-pro-preview",
        "gemini-3-flash": "gemini-3-flash-preview",
        "gemini-3-pro": "gemini-3-pro-preview",
        "gemini-2.5-pro-preview-03-25": "gemini-2.5-pro",
        "gemini-2.5-pro-preview-05-06": "gemini-2.5-pro",
        "gemini-2.5-flash-preview-05-20": "gemini-2.5-flash",
    },
    "nvidia": {
        "gpt-oss-120b": "openai/gpt-oss-120b",
        "qwen3-coder-480b": "qwen/qwen3-coder-480b-a35b-instruct",
        "qwen3-next-80b": "qwen/qwen3-next-80b-a3b-instruct",
        "qwen3.5-122b": "qwen/qwen3-5-122b-a10b",
        "mistral-large": "mistralai/mistral-large",
        "mistral-large-2": "mistralai/mistral-large-2-instruct",
        "mistral-large-3": "mistralai/mistral-large-3-675b-instruct-2512",
        "mistral-medium-3": "mistralai/mistral-medium-3-instruct",
        "mistral-small-3.1": "mistralai/mistral-small-3.1-24b-instruct-2503",
        "llama-4-maverick": "meta/llama-4-maverick-17b-128e-instruct",
        "deepseek-v3": "deepseek-ai/deepseek-v3.1",
        "deepseek-v3.1": "deepseek-ai/deepseek-v3.1",
        "deepseek-v3.2": "deepseek-ai/deepseek-v3.2",
        "gemma-4-31b": "google/gemma-4-31b-it",
        "gemma-3-27b": "google/gemma-3-27b-it",
        "gemma-3n-e4b": "google/gemma-3n-e4b-it",
        "codegemma-7b": "google/codegemma-7b",
        "codegemma-1.1-7b": "google/codegemma-1.1-7b",
        "mistral-nemotron": "mistralai/mistral-nemotron",
        "devstral-2-123b": "mistralai/devstral-2-123b-instruct-2512",
    },
}

DEFAULT_CONFIG = {
    "base_url": BASE_URL,
    "provider": "groq",
    "model": "openai/gpt-oss-120b",
    "zero_mode": False,
    "use_web": False,
    "steering_mode": "auto",
    "access_mode": "machine",
    "workspace_root": "",
    "project_context_enabled": True,
    "servers": {},
    "mcp_servers": {},
    "direct_keys": {
        "groq": "",
        "nvidia": "",
    },
    "auth_token": "",
    "auth_expires_at": "",
    "user_email": "",
    "tutorial_version": 0,
    "library_paths": [],
}

TEXT_EXTENSIONS = {
    ".txt", ".md", ".php", ".phtml", ".py", ".js", ".jsx", ".ts", ".tsx",
    ".html", ".css", ".scss", ".json", ".sql", ".log", ".java", ".c", ".cc",
    ".cpp", ".h", ".hpp", ".cs", ".go", ".rs", ".rb", ".swift", ".kt", ".kts",
    ".scala", ".xml", ".yml", ".yaml", ".toml", ".ini", ".env", ".sh", ".bash",
    ".zsh", ".ps1", ".vue", ".svelte", ".astro", ".csv", ".lock", ".properties",
    ".gradle", ".conf", ".cfg", ".mjs", ".cjs", ".mts", ".cts", ".proto",
    ".graphql", ".gql", ".diff", ".patch", ".prisma", ".ipynb", ".tf", ".tfvars",
    ".pem", ".pub", ".editorconfig", ".gitignore", ".dockerignore", ".gitmodules",
    ".fa", ".fasta", ".fastq", ".fq", ".gb", ".gbk",
}
IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp", ".gif"}
DIRECTORY_SAMPLE_LIMIT = 10
ARCHIVE_SAMPLE_LIMIT = 8
MAX_READ_LINES = 240
MAX_SCAN_FILES = 800
MAX_SEARCH_HITS = 200
MAX_EXEC_OUTPUT = 32000
MAX_PROJECT_CONTEXT_CHARS = 12000
MAX_SESSION_CONTEXT_CHARS = 16000
MAX_HISTORY_PREVIEW = 14
MAX_FETCH_BYTES = 250000
MAX_URL_ATTACHMENTS = 8
MAX_SEARCH_RESULTS = 8
SEARCH_USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/125.0 Safari/537.36 ZeroThinkCLI"
)
MAX_BROWSER_PAGES = 20
MAX_BROWSER_PAGE_PREVIEW_CHARS = 5000
MAX_BROWSER_PAGE_SAVE_CHARS = 12000
MCP_PROTOCOL_VERSION = "2025-11-25"
MCP_IO_TIMEOUT = 45
MCP_STARTUP_TIMEOUT = 120
MCP_CALL_TIMEOUT = 180
MCP_STDERR_TAIL = 12000
MAX_ATTACHMENTS = 24
MAX_PLAN_ITEMS = 40
MAX_PLAN_TEXT = 280
MAX_AGENT_STEPS = 12
MAX_AGENT_CHILDREN = 4
MAX_AGENT_DEPTH = 2
MAX_AGENT_LOG_CHARS = 12000
MAX_AGENT_OBSERVATION_CHARS = 6000
MAX_AGENT_SUMMARY_OBSERVATION_CHARS = 1200
MAX_SEARCH_FILE_BYTES = 2 * 1024 * 1024
OPERATOR_SKIP_DIRS = {
    ".git", ".hg", ".svn", ".idea", ".vscode", ".pytest_cache", ".mypy_cache",
    "__pycache__", "node_modules", "vendor", "backups", ".venv", "venv",
    ".runtime", ".cache", "cache", "tmp", "temp", "dist", "build", "coverage",
}
OPERATOR_SKIP_FILE_SUFFIXES = {
    ".pyc", ".pyo", ".zip", ".tar", ".tgz", ".gz", ".7z", ".rar", ".exe",
    ".dll", ".so", ".dylib", ".png", ".jpg", ".jpeg", ".webp", ".gif",
    ".mp4", ".mov", ".avi", ".pdf",
}
MAX_LIBRARY_FILES = 1200
MAX_LIBRARY_FILE_BYTES = 20 * 1024 * 1024
MAX_LIBRARY_CONTEXT_CHARS = 24000
MAX_RESEARCH_FILES = 80
MAX_RESEARCH_FILE_CHARS = 16000
MAX_RESEARCH_CONTEXT_CHARS = 220000
RESEARCH_KINDS = ("paper", "article", "survey", "review", "proposal")
RESEARCH_STAGES = ("protocol", "evidence", "outline", "draft", "critique", "revision", "full")
RESEARCH_SKIP_DIRS = {
    ".git", ".hg", ".svn", "__pycache__", "node_modules", "vendor", ".venv",
    "venv", ".runtime", ".cache", "cache", "tmp", "temp",
}
RESEARCH_SENSITIVE_NAMES = {
    ".env", ".htpasswd", "id_rsa", "id_dsa", "id_ecdsa", "id_ed25519",
    "credentials", "credentials.json", "secrets.json", "auth.json",
}
RESEARCH_SENSITIVE_TOKENS = (
    "password", "passwd", "credential", "secret", "api_key", "apikey",
    "access_token", "refresh_token", "private_key", "wallet", "seed_phrase",
)
FREEWEBPANEL_COMMANDS = (
    "help",
    "install",
    "status",
    "check",
    "health",
    "doctor",
    "backup",
    "repair",
    "update",
    "config",
    "domains",
    "logs",
    "permissions",
    "apache",
    "php",
    "ssl",
    "deploy",
    "explain",
)
FREEWEBPANEL_SERVER_MODES = (
    "auto",
    "ubuntu",
    "rhel-family",
    "alma",
    "rocky",
    "openzero-aios",
    "zeromint",
    "cwp",
    "cwp-pro",
    "directadmin",
    "cpanel",
    "apache",
    "local",
    "generic",
)
FREEWEBPANEL_INSTALLER_URLS = {
    "ubuntu": "https://freewebpanel.com/downloads/thcz-install.sh",
    "auto": "https://freewebpanel.com/downloads/thcz-install.sh",
    "generic": "https://freewebpanel.com/downloads/thcz-install.sh",
    "rhel-family": "https://freewebpanel.com/downloads/freewebpanel-rhel-family-install.sh",
    "alma": "https://freewebpanel.com/downloads/freewebpanel-rhel-family-install.sh",
    "rocky": "https://freewebpanel.com/downloads/freewebpanel-rhel-family-install.sh",
    "openzero-aios": "https://freewebpanel.com/downloads/freewebpanel-openzero-aios-install.sh",
    "zeromint": "https://freewebpanel.com/downloads/freewebpanel-openzero-aios-install.sh",
}
FREEWEBPANEL_PATH_CANDIDATES = (
    "/opt/freewebpanel",
    "/srv/freewebpanel",
    "/usr/local/freewebpanel",
    "/var/www/freewebpanel",
    "/var/www/html",
    "/home/freewebpanel",
)
FREEWEBPANEL_CONFIG_CANDIDATES = (
    "/etc/freewebpanel",
    "/etc/thcz",
    "/opt/freewebpanel/config",
    "/srv/freewebpanel/config",
    "/usr/local/freewebpanel/config",
)
FREEWEBPANEL_LOG_PATTERNS = (
    "/var/log/freewebpanel/*.log",
    "/var/log/thcz/*.log",
    "/var/log/nginx/error.log",
    "/var/log/nginx/access.log",
    "/var/log/apache2/error.log",
    "/var/log/httpd/error_log",
    "/var/log/php*-fpm.log",
    "/var/log/letsencrypt/letsencrypt.log",
)
FREEWEBPANEL_BACKUP_CANDIDATES = (
    "/var/backups/freewebpanel",
    "/root/freewebpanel-backups",
    "/opt/freewebpanel/backups",
    "/srv/freewebpanel/backups",
)
FREEWEBPANEL_SECRET_RE = re.compile(
    r"(?i)\b(password|passwd|secret|token|api[_-]?key|private[_-]?key|smtp[_-]?pass|mysql[_-]?pass|db[_-]?pass|paypal)\b\s*([:=])\s*(['\"]?)([^\s'\";]+)(['\"]?)"
)


def ensure_private_permissions(path: pathlib.Path) -> None:
    try:
        if os.name == "nt":
            return
        os.chmod(path, 0o700 if path.is_dir() else 0o600)
    except OSError:
        pass


def fresh_config() -> dict:
    fresh = dict(DEFAULT_CONFIG)
    fresh["direct_keys"] = normalize_direct_keys(fresh)
    return fresh


def print_banner() -> None:
    config = load_config()
    current_dir = pathlib.Path.cwd().resolve()
    try:
        sys.stdout.write(BANNER)
    except UnicodeEncodeError:
        sys.stdout.write(ASCII_BANNER)
    sys.stdout.write(f"ZERO THINK CLI // AGENT ZERO // v{VERSION}\n")
    sys.stdout.write(f"Host: {config.get('base_url', BASE_URL)}\n")
    sys.stdout.write(f"Access: {access_mode_from(config)}\n")
    workspace_root = workspace_root_from_config(config)
    if workspace_root:
        sys.stdout.write(f"Workspace: {workspace_root}\n")
    sys.stdout.write(f"Current dir: {current_dir}\n")
    sys.stdout.write(f"Platform: {platform.system()} {platform.release()}\n\n")


def safe_console_text(value: str) -> str:
    text = str(value or "")
    encoding = getattr(sys.stdout, "encoding", None) or "utf-8"
    try:
        text.encode(encoding)
        return text
    except Exception:
        return text.encode(encoding, errors="replace").decode(encoding, errors="replace")


def normalize_help_topic(topic: Optional[str]) -> str:
    value = (topic or "overview").strip().lower()
    aliases = {
        "about": "about",
        "identity": "about",
        "agent": "about",
        "agent-zero": "about",
        "agentzero": "about",
        "encrypt": "crypto",
        "decrypt": "crypto",
        "zpepper": "crypto",
        "library": "library",
        "lib": "library",
        "knowledge": "library",
        "dna": "dna",
        "genome": "dna",
        "capability": "about",
        "capabilities": "about",
        "start": "quickstart",
        "beginner": "quickstart",
        "setup": "quickstart",
        "intro": "overview",
        "guide": "quickstart",
        "tutorial": "tutorial",
        "wizard": "tutorial",
        "cli": "commands",
        "command": "commands",
        "servers": "remote",
        "ssh": "remote",
        "scp": "remote",
        "freewebpanel": "freewebpanel",
        "fwp": "freewebpanel",
        "webpanel": "freewebpanel",
        "hosting-panel": "freewebpanel",
        "workspace": "access",
        "archives": "files",
        "zip": "files",
        "model": "models",
        "session": "sessions",
        "checkpoint": "sessions",
        "resume": "sessions",
        "history": "sessions",
        "plan": "plan",
        "plans": "plan",
        "task": "plan",
        "tasks": "plan",
        "todo": "plan",
        "contexts": "context",
        "project": "context",
        "browser": "browser",
        "browse": "browser",
        "web": "browser",
        "search": "browser",
        "research": "research",
        "paper": "research",
        "papers": "research",
        "article": "research",
        "survey": "research",
        "literature": "research",
        "mcp": "mcp",
        "playwright": "mcp",
        "automation": "mcp",
        "agent": "agents",
        "agents": "agents",
        "autopilot": "agents",
        "team": "agents",
        "crypto": "crypto",
        "security": "crypto",
        "pdf": "pdf",
        "report": "pdf",
        "reports": "pdf",
        "skill": "skills",
        "skills": "skills",
        "examples": "examples",
        "example": "examples",
    }
    return aliases.get(value, value if value in HELP_TOPICS else "overview")


def print_help_topics() -> None:
    print("Help topics:")
    for name in ["overview", "about", "skills", "tutorial", "quickstart", "repl", "sessions", "plan", "context", "fetch", "browser", "research", "mcp", "custom", "access", "files", "pdf", "remote", "freewebpanel", "agents", "crypto", "library", "dna", "models", "examples", "commands"]:
        print(f"  - {name}")


def print_cli_help(topic: Optional[str] = None, interactive: bool = False) -> None:
    selected = normalize_help_topic(topic)
    print_banner()
    print(f"HELP TOPIC: {selected.upper()}\n")
    print(textwrap.dedent(HELP_TOPICS[selected]).strip())
    print("")
    print_help_topics()
    print("")
    if interactive:
        print("Interactive shortcuts:")
        print("  /about")
        print("  /capabilities")
        print("  /tutorial")
        print("  /help quickstart")
        print("  /help examples")
        print("  /help agents")
        print("  /plan")
        print("  /steer plan")
        print("  /go")
        print("  /stop")
        print("  /status")
        print("  /runtime")
        print("  /autopilot map this repo and find the auth flow")
        print("  /agent list")
        print("  /crypto status")
        print("  /encrypt secrets.txt")
        print("  /search-web")
        print("  /context")
        print("  /checkpoint")
        print("  /update")
        print("  /guide")
        print("  /quit")
    else:
        print("Try next:")
        print("  zerothink tutorial")
        print("  zerothink help quickstart")
        print("  zerothink help examples")
        print("  zerothink")


def print_skill_catalog() -> None:
    print(textwrap.dedent(HELP_TOPICS["skills"]).strip())


def load_config() -> dict:
    if not CONFIG_FILE.exists():
        return fresh_config()
    try:
        data = json.loads(CONFIG_FILE.read_text(encoding="utf-8"))
        if not isinstance(data, dict):
            backup = CONFIG_DIR / f"config.corrupt-{current_timestamp_slug()}.json"
            try:
                CONFIG_FILE.replace(backup)
                print(f"Warning: invalid config was moved to {backup}", file=sys.stderr)
            except Exception:
                print("Warning: invalid config was ignored and a fresh config was loaded.", file=sys.stderr)
            return fresh_config()
        merged = dict(DEFAULT_CONFIG)
        merged.update(data)
        if not isinstance(merged.get("servers"), dict):
            merged["servers"] = {}
        if not isinstance(merged.get("mcp_servers"), dict):
            merged["mcp_servers"] = {}
        merged["direct_keys"] = normalize_direct_keys(merged)
        merged["zero_mode"] = False
        merged["access_mode"] = "folder" if str(merged.get("access_mode", "machine")).strip().lower() in {"folder", "workspace", "project"} else "machine"
        merged["steering_mode"] = normalize_steering_mode(merged.get("steering_mode", "auto"))
        merged["workspace_root"] = str(merged.get("workspace_root") or "").strip()
        merged["project_context_enabled"] = boolish(merged.get("project_context_enabled", True))
        return merged
    except Exception as exc:
        backup = CONFIG_DIR / f"config.corrupt-{current_timestamp_slug()}.json"
        try:
            if CONFIG_FILE.exists():
                CONFIG_FILE.replace(backup)
                print(f"Warning: corrupt config was moved to {backup}: {exc}", file=sys.stderr)
        except Exception:
            print(f"Warning: corrupt config could not be read: {exc}", file=sys.stderr)
        return fresh_config()


def save_config(config: dict) -> None:
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    ensure_private_permissions(CONFIG_DIR)
    config["direct_keys"] = normalize_direct_keys(config)
    config["project_context_enabled"] = boolish(config.get("project_context_enabled", True))
    config["steering_mode"] = normalize_steering_mode(config.get("steering_mode", "auto"))
    temp_path = CONFIG_FILE.with_suffix(".json.tmp")
    temp_path.write_text(json.dumps(config, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    temp_path.replace(CONFIG_FILE)
    ensure_private_permissions(CONFIG_FILE)


def current_timestamp_slug() -> str:
    return time.strftime("%Y%m%d-%H%M%S")


def safe_session_name(name: Optional[str]) -> str:
    raw = str(name or "").strip()
    if raw.lower().endswith(".json"):
        raw = raw[:-5]
    cleaned = re.sub(r"[^a-zA-Z0-9._-]+", "-", raw).strip(".-_")
    return cleaned or f"session-{current_timestamp_slug()}"


def session_path(name: str) -> pathlib.Path:
    return SESSIONS_DIR / f"{safe_session_name(name)}.json"


def list_saved_sessions() -> List[dict]:
    if not SESSIONS_DIR.exists():
        return []
    items: List[dict] = []
    for path in sorted(SESSIONS_DIR.glob("*.json"), key=lambda item: item.stat().st_mtime, reverse=True):
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
            if isinstance(data, dict):
                transcript = data.get("transcript") if isinstance(data.get("transcript"), list) else []
                items.append(
                    {
                        "name": str(data.get("name") or path.stem),
                        "saved_at": str(data.get("saved_at") or ""),
                        "provider": str(data.get("provider") or ""),
                        "model": str(data.get("model") or ""),
                        "turns": len(transcript),
                        "path": path,
                    }
                )
        except Exception:
            continue
    return items


def load_saved_session(name: str) -> dict:
    path = session_path(name)
    if not path.exists():
        raise RuntimeError(f"Saved session not found: {safe_session_name(name)}")
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        raise RuntimeError(f"Could not read saved session: {exc}") from exc
    if not isinstance(data, dict):
        raise RuntimeError("Saved session file is invalid.")
    if not isinstance(data.get("transcript"), list):
        data["transcript"] = []
    data["plan_items"] = normalize_plan_items(data.get("plan_items"))
    data["browser_pages"] = normalize_browser_pages(data.get("browser_pages"))
    data["name"] = str(data.get("name") or path.stem)
    return data


def remove_saved_session(name: str) -> None:
    path = session_path(name)
    if not path.exists():
        raise RuntimeError(f"Saved session not found: {safe_session_name(name)}")
    path.unlink()


def normalize_plan_items(items: Optional[List[dict]]) -> List[dict]:
    normalized: List[dict] = []
    for raw in list(items or []):
        if not isinstance(raw, dict):
            continue
        text = str(raw.get("text") or "").strip()
        if not text:
            continue
        normalized.append(
            {
                "text": text[:MAX_PLAN_TEXT],
                "done": bool(raw.get("done", False)),
            }
        )
        if len(normalized) >= MAX_PLAN_ITEMS:
            break
    return normalized


def print_plan_items(plan_items: Optional[List[dict]]) -> None:
    items = normalize_plan_items(plan_items)
    if not items:
        print("No local plan items yet. Use /plan add <task>.")
        return
    done = sum(1 for item in items if item.get("done"))
    print(f"Plan items: {len(items) - done} open / {done} done")
    for idx, item in enumerate(items, start=1):
        marker = "x" if item.get("done") else " "
        print(f"{idx:>2}. [{marker}] {item.get('text')}")


def handle_plan_action(plan_items: Optional[List[dict]], args: List[str]) -> List[dict]:
    items = normalize_plan_items(plan_items)
    action = str(args[0] if args else "show").strip().lower()
    if action in {"show", "status", "list"}:
        print_plan_items(items)
        return items
    if action == "clear":
        print("Local plan cleared.")
        return []
    if action == "add":
        text = " ".join(args[1:]).strip()
        if not text:
            raise RuntimeError("Usage: /plan add <task>")
        if len(items) >= MAX_PLAN_ITEMS:
            raise RuntimeError(f"Plan limit reached ({MAX_PLAN_ITEMS}). Clear or drop an item first.")
        items.append({"text": text[:MAX_PLAN_TEXT], "done": False})
        print(f"Added plan item {len(items)}.")
        print_plan_items(items)
        return items
    if action in {"done", "undo", "drop", "rm", "remove", "del"}:
        if len(args) < 2:
            raise RuntimeError("Usage: /plan done N | /plan undo N | /plan drop N")
        try:
            index = int(args[1])
        except ValueError as exc:
            raise RuntimeError("Plan index must be a number.") from exc
        if index < 1 or index > len(items):
            raise RuntimeError(f"Plan index out of range. Current size: {len(items)}")
        item = items[index - 1]
        if action == "done":
            item["done"] = True
            print(f"Completed plan item {index}.")
        elif action == "undo":
            item["done"] = False
            print(f"Reopened plan item {index}.")
        else:
            removed = items.pop(index - 1)
            print(f"Removed plan item {index}: {removed.get('text')}")
        print_plan_items(items)
        return items
    raise RuntimeError("Usage: /plan show | /plan add <task> | /plan done N | /plan undo N | /plan drop N | /plan clear")


def save_session_checkpoint(
    name: Optional[str],
    transcript: List[dict],
    provider: str,
    model: str,
    zero_mode: bool,
    use_web: bool,
    current_dir: pathlib.Path,
    attachments: List[str],
    plan_items: Optional[List[dict]] = None,
    url_attachments: Optional[List[str]] = None,
    browser_pages: Optional[List[dict]] = None,
) -> pathlib.Path:
    SESSIONS_DIR.mkdir(parents=True, exist_ok=True)
    ensure_private_permissions(SESSIONS_DIR)
    path = session_path(name or "")
    payload = {
        "version": 1,
        "name": path.stem,
        "saved_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "provider": provider,
        "model": model,
        "zero_mode": bool(zero_mode),
        "use_web": bool(use_web),
        "current_dir": str(current_dir),
        "attachments": list(attachments),
        "plan_items": normalize_plan_items(plan_items),
        "url_attachments": list(url_attachments or []),
        "browser_pages": normalize_browser_pages(browser_pages),
        "transcript": transcript,
    }
    path.write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    ensure_private_permissions(path)
    return path


def format_transcript_for_context(transcript: List[dict], max_chars: int = MAX_SESSION_CONTEXT_CHARS) -> str:
    blocks: List[str] = []
    used = 0
    for item in reversed(transcript):
        role = str(item.get("role") or "assistant").upper()
        content = str(item.get("content") or "").strip()
        if not content:
            continue
        block = f"{role}:\n{content}"
        block += "\n"
        if used + len(block) > max_chars and blocks:
            break
        blocks.append(block)
        used += len(block)
        if len(blocks) >= MAX_HISTORY_PREVIEW:
            break
    blocks.reverse()
    return "\n".join(blocks).strip()


def export_session_markdown(
    transcript: List[dict],
    provider: str,
    model: str,
    zero_mode: bool,
    use_web: bool,
    current_dir: pathlib.Path,
    attachments: List[str],
    plan_items: Optional[List[dict]] = None,
    url_attachments: Optional[List[str]] = None,
    browser_pages: Optional[List[dict]] = None,
    output_path: Optional[pathlib.Path] = None,
) -> pathlib.Path:
    EXPORTS_DIR.mkdir(parents=True, exist_ok=True)
    ensure_private_permissions(EXPORTS_DIR)
    target = output_path or (EXPORTS_DIR / f"zerothink-session-{current_timestamp_slug()}.md")
    lines = [
        "# ZeroThink CLI Session Export",
        "",
        f"- Exported: {time.strftime('%Y-%m-%d %H:%M:%S')}",
        f"- Provider: `{provider}`",
        f"- Model: `{model}`",
        "- CLI trace mode: `off`",
        f"- Web search: `{'on' if use_web else 'off'}`",
        f"- Current dir: `{current_dir}`",
        f"- Steering mode: `{steering_mode_from(config)}`",
        f"- Attachments: `{len(attachments)}`",
        "",
    ]
    if attachments:
        lines.append("## Attachments")
        lines.append("")
        for item in attachments:
            lines.append(f"- `{item}`")
        lines.append("")
    if plan_items:
        lines.append("## Plan Board")
        lines.append("")
        for idx, item in enumerate(normalize_plan_items(plan_items), start=1):
            state = "done" if item.get("done") else "open"
            lines.append(f"{idx}. [{state}] {item.get('text')}")
        lines.append("")
    if url_attachments:
        lines.append("## URL Attachments")
        lines.append("")
        for item in url_attachments:
            lines.append(f"- {item}")
        lines.append("")
    if browser_pages:
        lines.append("## Browser Pages")
        lines.append("")
        for idx, item in enumerate(normalize_browser_pages(browser_pages), start=1):
            lines.append(f"{idx}. **{item.get('title') or item.get('url')}**")
            lines.append(f"   - URL: {item.get('url')}")
            snippet = str(item.get("snippet") or "").strip()
            if snippet:
                lines.append(f"   - Snippet: {snippet}")
        lines.append("")
    lines.extend(["## Transcript", ""])
    for item in transcript:
        role = str(item.get("role") or "assistant").capitalize()
        content = str(item.get("content") or "").strip()
        lines.append(f"### {role}")
        lines.append("")
        lines.append(content or "_(empty)_")
        lines.append("")
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text("\n".join(lines).strip() + "\n", encoding="utf-8")
    ensure_private_permissions(target)
    return target


def print_saved_sessions() -> None:
    sessions = list_saved_sessions()
    if not sessions:
        print("No saved local sessions.")
        return
    for item in sessions:
        print(
            f"- {item['name']} | {item['saved_at'] or 'unknown time'} | "
            f"{item['provider'] or '?'} | {item['turns']} turns"
        )


def project_context_enabled(config: dict) -> bool:
    return boolish(config.get("project_context_enabled", True))


def active_working_dir(config: dict, cwd: Optional[pathlib.Path] = None) -> pathlib.Path:
    if access_mode_from(config) == "folder":
        root = workspace_root_from_config(config)
        if root:
            return root
    return (cwd or pathlib.Path.cwd()).resolve()


def find_project_context_file(current_dir: pathlib.Path, config: dict) -> Optional[pathlib.Path]:
    if not project_context_enabled(config):
        return None
    root_limit = workspace_root_from_config(config) if access_mode_from(config) == "folder" else None
    current = current_dir.resolve()
    seen = set()
    while True:
        if current in seen:
            break
        seen.add(current)
        for name in CONTEXT_FILENAMES:
            candidate = current / name
            if candidate.exists() and candidate.is_file():
                return candidate
        if root_limit and current == root_limit:
            break
        if current.parent == current:
            break
        current = current.parent
    return None


def read_project_context(current_dir: pathlib.Path, config: dict) -> Tuple[Optional[pathlib.Path], str]:
    path = find_project_context_file(current_dir, config)
    if not path:
        return None, ""
    try:
        raw = path.read_text(encoding="utf-8", errors="replace").replace("\x00", "")
    except Exception as exc:
        return path, f"[Could not read context file: {exc}]"
    if len(raw) <= MAX_PROJECT_CONTEXT_CHARS:
        return path, raw.strip()
    clipped = raw[:MAX_PROJECT_CONTEXT_CHARS].rstrip()
    return path, clipped + "\n\n[Context clipped locally to keep prompts sane.]"


def build_local_session_prompt(
    prompt: str,
    transcript: Optional[List[dict]] = None,
    context_path: Optional[pathlib.Path] = None,
    context_text: str = "",
    current_dir: Optional[pathlib.Path] = None,
    config: Optional[dict] = None,
    provider_name: str = "",
    model_name: str = "",
    mode_name: str = "",
    mode_source: str = "",
    attachments: Optional[List[str]] = None,
    url_attachments: Optional[List[str]] = None,
) -> str:
    config = dict(config or {})
    current_dir = (current_dir or active_working_dir(config)).expanduser().resolve()
    access_mode = access_mode_from(config)
    workspace_root = workspace_root_from_config(config) if access_mode == "folder" else None
    server_names = ", ".join(sorted(get_server_profiles(config).keys())[:12]) or "(none)"
    mcp_names = ", ".join(sorted(get_mcp_servers(config).keys())[:12]) or "(none)"
    attached_paths = [str(item).strip() for item in list(attachments or []) if str(item).strip()]
    attached_urls = [str(item).strip() for item in list(url_attachments or []) if str(item).strip()]
    sections: List[str] = [
        (
            "ZEROTHINK CLI IDENTITY\n"
            "You are ZeroThink CLI aka Agent Zero, the terminal operator and coding client for ZeroThink.\n"
            "Do not describe yourself as a generic AI assistant, ChatGPT, GPT-4, or a generic language model.\n"
            "If asked who you are or what you can do, answer as ZeroThink CLI / Agent Zero and mention real terminal abilities only.\n"
            "Real CLI abilities include local file reads and edits, shell commands, repo scans, zip and archive workflows, local web search, page browsing, Playwright/MCP tools, SSH/SCP/remote file operations, plan boards, checkpoints, autopilot, and named agents.\n"
            "Do not invent website-only or hidden powers that are not actually available in the CLI lane."
        )
    ]
    sections.append(
        "WORKING STYLE\n"
        "Reason privately in phases: orient, inspect, plan, act, verify, report.\n"
        "If the task depends on files, shell output, browser pages, MCP tools, or remote systems, inspect first and ground claims in real observations.\n"
        "If the CLI has the tool access to do the work, prefer doing the work over telling the user to code or click manually.\n"
        "When coding, preserve the existing structure and style unless the user asks for a broader rewrite.\n"
        "When a repo guidance file exists, treat it as local authority unless the user explicitly overrides it.\n"
        "Do not expose hidden chain-of-thought. Give concise conclusions, touched paths, checks, and blockers instead."
    )
    route_label = provider_name or str(config.get("provider") or "").strip() or "groq"
    model_label = model_name or str(config.get("model") or "").strip() or PROVIDERS.get(route_label, {}).get("default_model", "")
    sections.append(
        "CLI OPERATING STATE\n"
        f"Current directory: {current_dir}\n"
        f"Access mode: {access_mode}\n"
        f"Workspace root: {workspace_root if workspace_root else '(none)'}\n"
        f"Provider: {route_label}\n"
        f"Model: {model_label}\n"
        f"Auth lane: {mode_name or '(unknown)'}\n"
        f"Auth source: {mode_source or '(unknown)'}\n"
        f"Saved server profiles: {server_names}\n"
        f"Saved MCP servers: {mcp_names}\n"
        f"Attached local paths: {', '.join(attached_paths[:8]) if attached_paths else '(none)'}\n"
        f"Attached URLs: {', '.join(attached_urls[:8]) if attached_urls else '(none)'}"
    )
    context_text = str(context_text or "").strip()
    if context_text:
        label = context_path.name if context_path else "ZEROTHINK.md"
        sections.append(
            "PROJECT CONTEXT FILE\n"
            f"Source: {label}\n"
            "Use this as local project guidance if relevant.\n\n"
            f"{context_text}"
        )
    history_block = format_transcript_for_context(transcript or [])
    if history_block:
        sections.append(
            "RECENT LOCAL SESSION CONTEXT\n"
            "This is local CLI checkpoint/session context, not website memory.\n\n"
            f"{history_block}"
        )
    sections.append(f"USER REQUEST\n{prompt.strip()}")
    return "\n\n---\n\n".join(sections)


def print_context_status(current_dir: pathlib.Path, config: dict, show_preview: bool = False) -> None:
    enabled = project_context_enabled(config)
    print(f"Project context: {'on' if enabled else 'off'}")
    path, text = read_project_context(current_dir, config)
    if not enabled:
        print("Context loading is disabled.")
        return
    if not path:
        print("No ZEROTHINK.md, .zerothink.md, ZEROTHINK.txt, CLAUDE.md, AGENTS.md, or .claude/CLAUDE.md found from the current folder upward.")
        return
    print(f"Context file: {path}")
    print(f"Context chars loaded: {len(text)}")
    if show_preview:
        print("")
        print(text or "(context file is empty)")


def fetch_url_text(url: str) -> str:
    cleaned = str(url or "").strip()
    if not cleaned:
        raise RuntimeError("URL is required.")
    if not re.match(r"^https?://", cleaned, flags=re.IGNORECASE):
        raise RuntimeError("Only http:// and https:// URLs are supported.")
    req = urllib.request.Request(
        cleaned,
        headers={
            "User-Agent": f"ZeroThinkCLI/{VERSION}",
            "Accept": "text/html, text/plain, application/json;q=0.9, */*;q=0.5",
        },
        method="GET",
    )
    with urllib.request.urlopen(req, timeout=60) as response:
        raw = response.read(MAX_FETCH_BYTES + 1)
        charset = response.headers.get_content_charset() or "utf-8"
        text = raw.decode(charset, errors="replace")
    if len(raw) > MAX_FETCH_BYTES:
        text = text[:MAX_FETCH_BYTES] + "\n\n[Remote page clipped locally for safety.]"
    text = re.sub(r"(?is)<script\b.*?</script>", " ", text)
    text = re.sub(r"(?is)<style\b.*?</style>", " ", text)
    text = re.sub(r"(?s)<[^>]+>", " ", text)
    text = re.sub(r"&nbsp;", " ", text)
    text = re.sub(r"&amp;", "&", text)
    text = re.sub(r"&lt;", "<", text)
    text = re.sub(r"&gt;", ">", text)
    text = re.sub(r"\r", "", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    text = re.sub(r"[ \t]{2,}", " ", text)
    text = text.strip()
    if not text:
        raise RuntimeError("Fetched page did not contain usable text.")
    return text


def clean_html_fragment(fragment: str) -> str:
    text = html.unescape(str(fragment or ""))
    text = re.sub(r"(?is)<script\b.*?</script>", " ", text)
    text = re.sub(r"(?is)<style\b.*?</style>", " ", text)
    text = re.sub(r"(?s)<[^>]+>", " ", text)
    text = re.sub(r"\r", "", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    text = re.sub(r"[ \t]{2,}", " ", text)
    return text.strip()


def unwrap_search_result_url(url: str) -> str:
    cleaned = str(url or "").strip()
    if not cleaned:
        return ""
    if cleaned.startswith("//"):
        cleaned = "https:" + cleaned
    parsed = urllib.parse.urlparse(cleaned)
    if "duckduckgo.com" in parsed.netloc and parsed.path.startswith("/l/"):
        target = urllib.parse.parse_qs(parsed.query).get("uddg", [""])[0]
        if target:
            return urllib.parse.unquote(target)
    return cleaned


def normalize_web_search_query(query: str) -> str:
    cleaned = re.sub(r"\s+", " ", str(query or "").replace("\r", " ").replace("\n", " ")).strip()
    if not cleaned:
        return ""
    marker_match = re.search(r"(?is)(latest user request|user request|request)\s*:\s*(.+)$", cleaned)
    if marker_match:
        cleaned = marker_match.group(2).strip()
    prefixes = [
        r"do\s+a\s+web\s+search\s+for",
        r"search\s+the\s+web\s+for",
        r"web\s+search\s+for",
        r"search\s+online\s+for",
        r"internet\s+search\s+for",
        r"research\s+online\s+for",
        r"research\s+for",
        r"research\s+on",
        r"research\s+about",
        r"look\s+up",
        r"find\s+info\s+on",
        r"find\s+information\s+on",
        r"search\s+for",
        r"research",
        r"search",
    ]
    for prefix in prefixes:
        cleaned = re.sub(rf"(?i)^\s*{prefix}\s*[:\-]?\s*", "", cleaned).strip()
    if re.search(r"(?i)\bionis\b", cleaned) and re.search(r"(?i)\b(quantum|ai|artificial intelligence|qubit|qpu)\b", cleaned):
        cleaned = re.sub(r"(?i)\bionis\b", "IonQ", cleaned)
    cleaned = re.sub(r"(?i)\bion\s+q\b", "IonQ", cleaned)
    return cleaned[:300]


def fetch_search_page(url: str, timeout: int = 45) -> str:
    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": SEARCH_USER_AGENT,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.7",
            "Accept-Language": "en-US,en;q=0.9",
        },
        method="GET",
    )
    with urllib.request.urlopen(req, timeout=timeout) as response:
        raw = response.read(MAX_FETCH_BYTES + 1)
        charset = response.headers.get_content_charset() or "utf-8"
        return raw.decode(charset, errors="replace")


def add_search_result(results: List[dict], seen: set, title: str, url: str, snippet: str, max_results: int) -> None:
    cleaned_url = unwrap_search_result_url(html.unescape(str(url or "")))
    cleaned_title = clean_html_fragment(title)
    cleaned_snippet = clean_html_fragment(snippet)
    if not cleaned_url or not cleaned_title or cleaned_url in seen:
        return
    if not re.match(r"^https?://", cleaned_url, flags=re.IGNORECASE):
        return
    if len(cleaned_title) < 3:
        return
    results.append({"title": cleaned_title[:240], "url": cleaned_url, "snippet": cleaned_snippet[:700], "text": ""})
    seen.add(cleaned_url)


def parse_duckduckgo_results(page: str, max_results: int) -> List[dict]:
    results: List[dict] = []
    seen = set()
    patterns = [
        r'(?is)<a(?=[^>]*\bclass=["\'][^"\']*\bresult__a\b[^"\']*["\'])(?=[^>]*\bhref=["\']([^"\']+)["\'])[^>]*>(.*?)</a>',
        r'(?is)<a(?=[^>]*\bclass=["\'][^"\']*\bresult-link\b[^"\']*["\'])(?=[^>]*\bhref=["\']([^"\']+)["\'])[^>]*>(.*?)</a>',
    ]
    for pattern in patterns:
        for match in re.finditer(pattern, page):
            url = match.group(1)
            title = match.group(2)
            tail = page[match.end():match.end() + 2200]
            snippet_match = re.search(
                r'(?is)<(?:a|div|td)[^>]+class=["\'][^"\']*(?:result__snippet|result-snippet)[^"\']*["\'][^>]*>(.*?)</(?:a|div|td)>',
                tail,
            )
            snippet = snippet_match.group(1) if snippet_match else ""
            add_search_result(results, seen, title, url, snippet, max_results)
            if len(results) >= max_results:
                return results
    return results


def parse_bing_results(page: str, max_results: int) -> List[dict]:
    results: List[dict] = []
    seen = set()
    for match in re.finditer(r'(?is)<li[^>]+class=["\'][^"\']*\bb_algo\b[^"\']*["\'][^>]*>(.*?)</li>', page):
        block = match.group(1)
        link = re.search(r'(?is)<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>', block)
        if not link:
            continue
        snippet_match = re.search(r'(?is)<p[^>]*>(.*?)</p>', block)
        add_search_result(results, seen, link.group(2), link.group(1), snippet_match.group(1) if snippet_match else "", max_results)
        if len(results) >= max_results:
            return results
    if not results:
        for match in re.finditer(r'(?is)<h2[^>]*>\s*<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>\s*</h2>', page):
            add_search_result(results, seen, match.group(2), match.group(1), "", max_results)
            if len(results) >= max_results:
                break
    return results


def parse_mojeek_results(page: str, max_results: int) -> List[dict]:
    results: List[dict] = []
    seen = set()
    skip_titles = {"blog", "community", "newsletter", "summary", "images", "news", "settings", "next"}
    for match in re.finditer(r'(?is)<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>', page):
        title = clean_html_fragment(match.group(2))
        url = html.unescape(match.group(1))
        if not title or title.strip().lower() in skip_titles:
            continue
        title_lower = title.strip().lower()
        if title_lower.startswith("http") or title_lower.startswith("www."):
            continue
        if "mojeek" in title_lower and any(host in url for host in ("mojeek.com", "blocksurvey.io", "buttondown.email")):
            continue
        if "mojeek.com/search" in url or "mojeek.com/" in url and len(title) < 20:
            continue
        add_search_result(results, seen, title, url, "", max_results)
        if len(results) >= max_results:
            break
    return results


def first_party_search_results(query: str, max_results: int) -> List[dict]:
    lowered = str(query or "").lower()
    if not any(token in lowered for token in ("zerothink", "zero think", "talktoai", "talk to ai", "openzero", "zero ai")):
        return []
    candidates = [
        {
            "title": "ZeroThink",
            "url": "https://zerothink.talktoai.org/",
            "snippet": "ZeroThink main site for the AI studio, CLI, research workflows, and Zero ecosystem tools.",
        },
        {
            "title": "ZeroThink CLI",
            "url": "https://zerothink.talktoai.org/cli",
            "snippet": "ZeroThink command-line client, local agent tools, natural search/research commands, and install guidance.",
        },
        {
            "title": "ZeroThink FAQ",
            "url": "https://zerothink.talktoai.org/faq",
            "snippet": "Frequently asked questions and protocol notes for using ZeroThink and the TalkToAI ecosystem.",
        },
        {
            "title": "ZeroThink Research",
            "url": "https://zerothink.talktoai.org/research",
            "snippet": "Research workflow material, AI reasoning lanes, and advanced ZeroThink project context.",
        },
        {
            "title": "$AI Utility",
            "url": "https://zerothink.talktoai.org/token",
            "snippet": "$AI utility information for the ZeroThink and TalkToAI ecosystem.",
        },
        {
            "title": "TalkToAI",
            "url": "https://talktoai.org/",
            "snippet": "TalkToAI.org main platform page and research workflow focus.",
        },
    ]
    return candidates[:max_results]


def search_web(query: str, max_results: int = MAX_SEARCH_RESULTS) -> List[dict]:
    cleaned = normalize_web_search_query(query)
    if not cleaned:
        raise RuntimeError("Search query is required.")
    sources = [
        ("DuckDuckGo", "https://html.duckduckgo.com/html/?" + urllib.parse.urlencode({"q": cleaned})),
        ("DuckDuckGo Lite", "https://lite.duckduckgo.com/lite/?" + urllib.parse.urlencode({"q": cleaned})),
        ("Bing", "https://www.bing.com/search?" + urllib.parse.urlencode({"q": cleaned, "count": str(max_results)})),
    ]
    errors: List[str] = []
    for source, url in sources:
        try:
            page = fetch_search_page(url)
            results = parse_bing_results(page, max_results) if source == "Bing" else parse_duckduckgo_results(page, max_results)
            if results:
                return results
        except Exception as exc:
            errors.append(f"{source}: {exc}")
    known = first_party_search_results(cleaned, max_results)
    if known:
        return known
    try:
        page = fetch_search_page("https://www.mojeek.com/search?" + urllib.parse.urlencode({"q": cleaned}))
        results = parse_mojeek_results(page, max_results)
        if results:
            return results
    except Exception as exc:
        errors.append(f"Mojeek: {exc}")
    detail = f" Query: {cleaned}." + (f" Tried: {'; '.join(errors)}" if errors else "")
    raise RuntimeError("Search returned no usable results." + detail)


def fallback_search_results(query: str, reason: str = "", max_results: int = MAX_SEARCH_RESULTS) -> List[dict]:
    cleaned = normalize_web_search_query(query)
    if not cleaned:
        return []

    results: List[dict] = []
    seen = set()

    def push(title: str, url: str, snippet: str) -> None:
        add_search_result(results, seen, title, url, snippet, max_results)

    lowered = cleaned.lower()
    reason_text = (" " + str(reason).strip()[:220]) if str(reason or "").strip() else ""
    if "site:arxiv.org" in lowered or "arxiv" in lowered:
        arxiv_query = re.sub(r"(?i)\bsite:arxiv\.org\b", "", cleaned).strip() or cleaned
        push(
            f"arXiv search: {arxiv_query}",
            "https://arxiv.org/search/?" + urllib.parse.urlencode({
                "query": arxiv_query,
                "searchtype": "all",
                "abstracts": "show",
                "order": "-announced_date_first",
                "size": "50",
            }),
            "Academic fallback search because the local web search providers returned no parseable results." + reason_text,
        )

    push(
        f"Web search fallback: {cleaned}",
        "https://www.bing.com/search?" + urllib.parse.urlencode({"q": cleaned}),
        "Fallback search link because the local web search providers returned no parseable results." + reason_text,
    )
    push(
        f"DuckDuckGo search: {cleaned}",
        "https://duckduckgo.com/?" + urllib.parse.urlencode({"q": cleaned}),
        "Secondary fallback search link for the same query.",
    )
    return results[:max_results]


def safe_search_web(query: str, max_results: int = MAX_SEARCH_RESULTS) -> List[dict]:
    try:
        return search_web(query, max_results=max_results)
    except Exception as exc:
        fallback = fallback_search_results(query, str(exc), max_results=max_results)
        if fallback:
            return fallback
        raise


def normalize_browser_pages(pages: Optional[List[dict]]) -> List[dict]:
    normalized: List[dict] = []
    for raw in list(pages or []):
        if not isinstance(raw, dict):
            continue
        url = str(raw.get("url") or "").strip()
        if not url:
            continue
        normalized.append(
            {
                "title": str(raw.get("title") or url).strip()[:240],
                "url": url,
                "snippet": str(raw.get("snippet") or "").strip()[:700],
                "text": str(raw.get("text") or "").strip()[:MAX_BROWSER_PAGE_SAVE_CHARS],
            }
        )
        if len(normalized) >= MAX_BROWSER_PAGES:
            break
    return normalized


def print_browser_pages(pages: Optional[List[dict]]) -> None:
    items = normalize_browser_pages(pages)
    if not items:
        print("No local browser pages yet. Use /search-web or /browse first.")
        return
    print(f"Browser pages: {len(items)}")
    for idx, item in enumerate(items, start=1):
        title = safe_console_text(str(item.get("title") or item.get("url") or "").strip())
        print(f"{idx:>2}. {title}")
        print(f"    {safe_console_text(str(item.get('url') or '').strip())}")
        snippet = str(item.get("snippet") or "").strip()
        if snippet:
            print(f"    {safe_console_text(snippet[:180])}")


def print_browser_page(item: dict, index: int = 0) -> None:
    title = safe_console_text(str(item.get("title") or item.get("url") or "Untitled").strip())
    url = safe_console_text(str(item.get("url") or "").strip())
    text = safe_console_text(str(item.get("text") or "").strip())
    snippet = safe_console_text(str(item.get("snippet") or "").strip())
    label = f"Page {index}" if index else "Page"
    print(f"{label}: {title}")
    print(f"URL: {url}")
    if snippet:
        print(f"Snippet: {snippet}")
    if text:
        preview = text[:MAX_BROWSER_PAGE_PREVIEW_CHARS].rstrip()
        print("")
        print(preview)
        if len(text) > len(preview):
            print("\n[Page preview clipped locally.]")


def fetch_browser_page(url: str, title: str = "", snippet: str = "") -> dict:
    text = fetch_url_text(url)
    return {
        "title": str(title or url).strip()[:240],
        "url": str(url).strip(),
        "snippet": str(snippet or "").strip()[:700],
        "text": text[:MAX_BROWSER_PAGE_SAVE_CHARS],
    }


def upsert_browser_page(pages: Optional[List[dict]], page: dict) -> List[dict]:
    items = normalize_browser_pages(pages)
    url = str(page.get("url") or "").strip()
    if not url:
        return items
    updated = [item for item in items if str(item.get("url") or "").strip() != url]
    updated.insert(0, {
        "title": str(page.get("title") or url).strip()[:240],
        "url": url,
        "snippet": str(page.get("snippet") or "").strip()[:700],
        "text": str(page.get("text") or "").strip()[:MAX_BROWSER_PAGE_SAVE_CHARS],
    })
    return normalize_browser_pages(updated)


def build_url_contexts(urls: List[str]) -> str:
    chunks = []
    for raw in urls[:MAX_URL_ATTACHMENTS]:
        url = str(raw or "").strip()
        text = fetch_url_text(url)
        if len(text) > MAX_FETCH_BYTES:
            text = text[:MAX_FETCH_BYTES]
        chunks.append(f"WEB FETCH\nURL: {url}\n\n{text}")
    return "\n\n==== NEXT WEB FETCH ====\n\n".join(chunks)


def coerce_cli_value(raw: str):
    value = str(raw or "").strip()
    if value == "":
        return ""
    lowered = value.lower()
    if lowered in {"true", "false"}:
        return lowered == "true"
    if lowered == "null":
        return None
    if re.fullmatch(r"-?\d+", value):
        try:
            return int(value)
        except Exception:
            return value
    if re.fullmatch(r"-?\d+\.\d+", value):
        try:
            return float(value)
        except Exception:
            return value
    if (value.startswith("{") and value.endswith("}")) or (value.startswith("[") and value.endswith("]")):
        try:
            return json.loads(value)
        except Exception:
            return value
    return value


def parse_key_value_args(values: List[str]) -> dict:
    parsed: dict = {}
    for raw in list(values or []):
        text = str(raw or "").strip()
        if not text:
            continue
        if "=" not in text:
            raise RuntimeError(f"Expected key=value, got: {text}")
        key, value = text.split("=", 1)
        key = key.strip()
        if not key:
            raise RuntimeError(f"Invalid key=value pair: {text}")
        parsed[key] = coerce_cli_value(value)
    return parsed


def _mcp_read_frame(stream) -> dict:
    first_line = stream.readline()
    if not first_line:
        raise RuntimeError("MCP server closed the connection.")
    stripped = first_line.strip()
    if stripped.lower().startswith(b"content-length:"):
        try:
            content_length = int(stripped.decode("utf-8", errors="replace").split(":", 1)[1].strip())
        except Exception as exc:
            raise RuntimeError(f"Invalid MCP Content-Length header: {safe_console_text(stripped.decode('utf-8', errors='replace'))}") from exc
        while True:
            line = stream.readline()
            if not line:
                raise RuntimeError("MCP server closed the connection.")
            if line in {b"\r\n", b"\n"}:
                break
        body = stream.read(content_length)
    else:
        body = stripped
    if not body:
        raise RuntimeError("MCP server returned an empty response body.")
    try:
        return json.loads(body.decode("utf-8", errors="replace"))
    except Exception as exc:
        raise RuntimeError(f"Could not decode MCP response: {exc}") from exc


def _mcp_read_frame_with_timeout(stream, timeout: int) -> dict:
    holder: dict = {}
    error: List[Exception] = []

    def worker():
        try:
            holder["message"] = _mcp_read_frame(stream)
        except Exception as exc:  # noqa: BLE001
            error.append(exc)

    thread = threading.Thread(target=worker, daemon=True)
    thread.start()
    thread.join(max(1, int(timeout)))
    if thread.is_alive():
        raise RuntimeError("Timed out waiting for MCP server response.")
    if error:
        raise error[0]
    message = holder.get("message")
    if not isinstance(message, dict):
        raise RuntimeError("MCP server did not return a valid JSON object.")
    return message


def _mcp_send_frame(stream, payload: dict) -> None:
    body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    stream.write(body + b"\n")
    stream.flush()


def _mcp_collect_stderr(stream, bucket: List[bytes]) -> None:
    try:
        while True:
            chunk = stream.read(4096)
            if not chunk:
                break
            bucket.append(bytes(chunk))
            while sum(len(part) for part in bucket) > MCP_STDERR_TAIL and bucket:
                bucket.pop(0)
    except Exception:
        pass


def _mcp_stderr_tail(stderr_chunks: List[bytes]) -> str:
    if not stderr_chunks:
        return ""
    raw = b"".join(stderr_chunks)
    if len(raw) > MCP_STDERR_TAIL:
        raw = raw[-MCP_STDERR_TAIL:]
    return raw.decode("utf-8", errors="replace").strip()


def _mcp_with_stderr_context(message: str, stderr_chunks: List[bytes], argv: Optional[List[str]] = None) -> RuntimeError:
    parts = [str(message).strip()]
    if argv:
        parts.append(f"MCP command: {' '.join(argv)}")
    stderr_text = _mcp_stderr_tail(stderr_chunks)
    if stderr_text:
        parts.append(f"MCP stderr:\n{stderr_text}")
    return RuntimeError("\n".join(part for part in parts if part))


def _mcp_request(proc: subprocess.Popen, request_id: int, method: str, params: Optional[dict] = None, timeout: int = MCP_IO_TIMEOUT) -> dict:
    _mcp_send_frame(
        proc.stdin,
        {
            "jsonrpc": "2.0",
            "id": request_id,
            "method": method,
            "params": params or {},
        },
    )
    deadline = time.time() + max(5, int(timeout))
    while True:
        remaining = max(1, int(deadline - time.time()))
        if remaining <= 0:
            raise RuntimeError(f"MCP request timed out: {method}")
        message = _mcp_read_frame_with_timeout(proc.stdout, remaining)
        if "id" in message and "method" in message:
            _mcp_send_frame(
                proc.stdin,
                {
                    "jsonrpc": "2.0",
                    "id": message.get("id"),
                    "error": {
                        "code": -32601,
                        "message": "ZeroThink CLI does not support server-initiated requests in this operation.",
                    },
                },
            )
            continue
        if message.get("id") != request_id:
            continue
        if "error" in message and message["error"]:
            error = message["error"]
            if isinstance(error, dict):
                raise RuntimeError(f"MCP error: {error.get('message') or error}")
            raise RuntimeError(f"MCP error: {error}")
        return message.get("result") or {}


def run_mcp_stdio(profile: dict, action: str, tool_name: str = "", arguments: Optional[dict] = None, timeout: int = 0) -> dict:
    command = str(profile.get("command") or "").strip()
    if not command:
        raise RuntimeError("MCP profile is missing a command.")
    argv = [require_program(command)]
    argv.extend([str(item) for item in list(profile.get("args") or []) if str(item).strip()])
    cwd_raw = str(profile.get("cwd") or "").strip()
    cwd = pathlib.Path(cwd_raw).expanduser().resolve() if cwd_raw else None
    env = os.environ.copy()
    for key, value in dict(profile.get("env") or {}).items():
        env[str(key)] = str(value)
    startup_timeout = max(10, int(profile.get("startup_timeout") or MCP_STARTUP_TIMEOUT))
    call_timeout = max(startup_timeout, int(profile.get("call_timeout") or MCP_CALL_TIMEOUT))
    active_timeout = max(10, int(timeout or (call_timeout if action == "call" else startup_timeout)))
    proc = subprocess.Popen(
        argv,
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        cwd=str(cwd) if cwd else None,
        env=env,
    )
    stderr_chunks: List[bytes] = []
    stderr_thread = threading.Thread(target=_mcp_collect_stderr, args=(proc.stderr, stderr_chunks), daemon=True)
    stderr_thread.start()
    try:
        _mcp_request(
            proc,
            1,
            "initialize",
            {
                "protocolVersion": MCP_PROTOCOL_VERSION,
                "capabilities": {},
                "clientInfo": {"name": "ZeroThink CLI", "version": VERSION},
            },
            timeout=active_timeout,
        )
        _mcp_send_frame(proc.stdin, {"jsonrpc": "2.0", "method": "notifications/initialized"})
        if action == "tools":
            return _mcp_request(proc, 2, "tools/list", {}, timeout=active_timeout)
        if action == "call":
            if not tool_name:
                raise RuntimeError("Tool name is required for an MCP call.")
            return _mcp_request(proc, 2, "tools/call", {"name": tool_name, "arguments": arguments or {}}, timeout=max(active_timeout, 90))
        raise RuntimeError(f"Unsupported MCP action: {action}")
    except Exception as exc:
        raise _mcp_with_stderr_context(str(exc), stderr_chunks, argv) from None
    finally:
        try:
            if proc.stdin:
                proc.stdin.close()
        except Exception:
            pass
        try:
            proc.terminate()
            proc.wait(timeout=5)
        except Exception:
            try:
                proc.kill()
            except Exception:
                pass


def print_mcp_tools(profile: dict) -> None:
    result = run_mcp_stdio(profile, "tools", timeout=int(profile.get("startup_timeout") or MCP_STARTUP_TIMEOUT))
    tools = list(result.get("tools") or [])
    if not tools:
        print("No MCP tools were reported by this server.")
        return
    print(f"MCP tools: {len(tools)}")
    for item in tools:
        if not isinstance(item, dict):
            continue
        name = str(item.get("name") or "").strip()
        desc = str(item.get("description") or "").strip()
        print(f"- {name}")
        if desc:
            print(f"  {desc}")


def print_mcp_call_result(result: dict) -> None:
    content = list(result.get("content") or [])
    if not content:
        print(json.dumps(result, indent=2, ensure_ascii=False))
        return
    for idx, item in enumerate(content, start=1):
        if not isinstance(item, dict):
            print(safe_console_text(str(item)))
            continue
        item_type = str(item.get("type") or "").strip().lower()
        if item_type == "text":
            print(safe_console_text(str(item.get("text") or "").strip()))
        elif item_type in {"image", "audio"}:
            print(f"[{item_type} content returned]")
        elif item_type == "resource":
            resource = item.get("resource") or {}
            if isinstance(resource, dict):
                print(json.dumps(resource, indent=2, ensure_ascii=False))
            else:
                print(safe_console_text(str(resource)))
        else:
            print(json.dumps(item, indent=2, ensure_ascii=False))
        if idx < len(content):
            print("")


def ensure_playwright_profile(config: dict, name: str = "playwright") -> dict:
    profile_name = str(name or "playwright").strip() or "playwright"
    servers = get_mcp_servers(config)
    if profile_name not in servers:
        servers[profile_name] = build_playwright_mcp_profile(headless=True, isolated=True)
        save_mcp_servers(config, servers)
        config = load_config()
    return get_mcp_profile(config, profile_name)


def run_playwright_open(profile: dict, url: str, timeout: int = 0) -> dict:
    return run_mcp_stdio(
        profile,
        "call",
        tool_name="browser_navigate",
        arguments={"url": str(url).strip()},
        timeout=max(30, int(timeout or profile.get("call_timeout") or MCP_CALL_TIMEOUT)),
    )


def find_command_file(current_dir: pathlib.Path, config: dict) -> Optional[pathlib.Path]:
    root_limit = workspace_root_from_config(config) if access_mode_from(config) == "folder" else None
    current = current_dir.resolve()
    seen = set()
    while True:
        if current in seen:
            break
        seen.add(current)
        for name in COMMAND_FILE_NAMES:
            candidate = current / name
            if candidate.exists() and candidate.is_file():
                return candidate
        if root_limit and current == root_limit:
            break
        if current.parent == current:
            break
        current = current.parent
    return None


def load_custom_commands(current_dir: pathlib.Path, config: dict) -> Tuple[Optional[pathlib.Path], Dict[str, dict]]:
    path = find_command_file(current_dir, config)
    if not path:
        return None, {}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        return path, {}
    if not isinstance(data, dict):
        return path, {}
    commands: Dict[str, dict] = {}
    for name, spec in data.items():
        key = str(name or "").strip().lower()
        if not key or not isinstance(spec, dict):
            continue
        if spec.get("prompt") or spec.get("shell"):
            commands[key] = spec
    return path, commands


def render_custom_template(template: str, args: List[str], current_dir: pathlib.Path) -> str:
    arg_text = " ".join(args).strip()
    quoted = " ".join(shlex.quote(item) for item in args)
    return str(template).format(
        args=arg_text,
        args_quoted=quoted,
        cwd=str(current_dir),
    )


def print_custom_command_status(current_dir: pathlib.Path, config: dict, show_detail: bool = False) -> None:
    path, commands = load_custom_commands(current_dir, config)
    if not path:
        print("No custom command pack found.")
        print("Look for ZEROTHINK.commands.json or .zerothink.commands.json in this repo.")
        return
    print(f"Custom command pack: {path}")
    if not commands:
        print("No valid commands found inside the file.")
        return
    print(f"Loaded commands: {len(commands)}")
    for name in sorted(commands.keys()):
        spec = commands[name]
        kind = "shell" if spec.get("shell") else "prompt"
        print(f"  - {name} ({kind})")
        if show_detail:
            body = spec.get("shell") or spec.get("prompt") or ""
            print(f"    {body}")


def resolve_custom_command_name(raw: str, commands: Dict[str, dict]) -> str:
    candidate = str(raw or "").strip().lower()
    if not candidate:
        return ""
    if candidate in commands:
        return candidate
    matches = [name for name in commands.keys() if name.startswith(candidate)]
    if len(matches) == 1:
        return matches[0]
    return candidate


def is_tty_session() -> bool:
    try:
        return bool(sys.stdin.isatty() and sys.stdout.isatty())
    except Exception:
        return False


def prompt_choice(prompt: str, options: List[Tuple[str, str]], default: int = 1) -> str:
    print(prompt)
    for index, (_key, label) in enumerate(options, start=1):
        print(f"  {index}. {label}")
    while True:
        raw = input(f"Choose 1-{len(options)} [{default}]: ").strip()
        if not raw:
            return options[max(0, min(default - 1, len(options) - 1))][0]
        if raw.isdigit():
            idx = int(raw)
            if 1 <= idx <= len(options):
                return options[idx - 1][0]
        lowered = raw.lower()
        for key, _label in options:
            if lowered == key.lower():
                return key
        print("Please choose one of the shown options.")


def prompt_yes_no(prompt: str, default: bool = True) -> bool:
    suffix = "[Y/n]" if default else "[y/N]"
    while True:
        raw = input(f"{prompt} {suffix}: ").strip().lower()
        if not raw:
            return default
        if raw in {"y", "yes"}:
            return True
        if raw in {"n", "no"}:
            return False
        print("Please answer yes or no.")


def prompt_text(prompt: str, default: str = "") -> str:
    label = f"{prompt} [{default}]" if default else prompt
    raw = input(f"{label}: ").strip()
    return raw or default


def normalize_direct_keys(source: dict) -> Dict[str, str]:
    raw = source.get("direct_keys") if isinstance(source, dict) else {}
    clean = {name: "" for name in DIRECT_KEY_PROVIDERS}
    if isinstance(raw, dict):
        for name in DIRECT_KEY_PROVIDERS:
            clean[name] = str(raw.get(name) or "").strip()
    return clean


def mask_secret(secret: str) -> str:
    value = str(secret or "").strip()
    if not value:
        return ""
    if len(value) <= 8:
        return "*" * len(value)
    return value[:4] + "..." + value[-4:]


def local_direct_key(config: dict, provider: str) -> Tuple[str, str]:
    provider_name = str(provider or "").strip().lower()
    if provider_name not in DIRECT_KEY_PROVIDERS:
        return "", ""
    direct_keys = normalize_direct_keys(config)
    saved = str(direct_keys.get(provider_name) or "").strip()
    if saved:
        return saved, "local config"
    env_name = str(PROVIDERS.get(provider_name, {}).get("env") or "").strip()
    env_value = str(os.getenv(env_name, "")).strip() if env_name else ""
    if env_value:
        return env_value, f"env:{env_name}"
    return "", ""


def set_direct_key(provider: str, value: str) -> None:
    provider_name = str(provider or "").strip().lower()
    if provider_name not in DIRECT_KEY_PROVIDERS:
        raise RuntimeError("Direct key mode currently supports Groq and NVIDIA only.")
    secret = str(value or "").strip()
    if not secret:
        secret = getpass.getpass(f"Enter {provider_name.upper()} API key: ").strip()
    if not secret:
        raise RuntimeError("No API key supplied.")
    config = load_config()
    direct_keys = normalize_direct_keys(config)
    direct_keys[provider_name] = secret
    config["direct_keys"] = direct_keys
    save_config(config)
    print(f"Saved local {provider_name.upper()} key to {CONFIG_FILE}.")


def clear_direct_key(provider: str) -> None:
    provider_name = str(provider or "").strip().lower()
    if provider_name not in DIRECT_KEY_PROVIDERS:
        raise RuntimeError("Direct key mode currently supports Groq and NVIDIA only.")
    config = load_config()
    direct_keys = normalize_direct_keys(config)
    direct_keys[provider_name] = ""
    config["direct_keys"] = direct_keys
    save_config(config)
    print(f"Cleared local {provider_name.upper()} key.")


def print_direct_key_status(config: dict) -> None:
    print("Direct key mode:")
    for provider_name in DIRECT_KEY_PROVIDERS:
        key, source = local_direct_key(config, provider_name)
        if key:
            print(f"  {provider_name}: ready via {source} ({mask_secret(key)})")
        else:
            print(f"  {provider_name}: not set")
    print("Direct mode works with Groq and NVIDIA only.")


def set_provider_defaults(config: dict, provider_name: str) -> dict:
    provider = str(provider_name or "").strip().lower()
    if provider in PROVIDERS:
        config["provider"] = provider
        config["model"] = normalize_model(provider, PROVIDERS[provider]["default_model"])
    return config


def tutorial_completed(config: Optional[dict] = None) -> bool:
    current = config if isinstance(config, dict) else load_config()
    try:
        return int(current.get("tutorial_version", 0) or 0) >= TUTORIAL_VERSION
    except Exception:
        return False


def mark_tutorial_complete(config: Optional[dict] = None) -> dict:
    current = config if isinstance(config, dict) else load_config()
    current["tutorial_version"] = TUTORIAL_VERSION
    save_config(current)
    return current


def describe_account_and_key_state(config: dict) -> None:
    if str(config.get("auth_token") or "").strip():
        email = str(config.get("user_email") or "").strip()
        print(f"- Linked account detected: {email if email else 'this machine is already paired'}")
    else:
        print("- No linked ZeroThink account is currently paired on this machine.")
    for provider_name in DIRECT_KEY_PROVIDERS:
        key, source = local_direct_key(config, provider_name)
        if key:
            print(f"- {provider_name.upper()} direct key detected via {source} ({mask_secret(key)})")


def prompt_workspace_for_folder_mode(current_dir: pathlib.Path) -> pathlib.Path:
    default_dir = current_dir.resolve()
    if prompt_yes_no(f"Use the current folder as the workspace root?\n  {default_dir}", default=True):
        return default_dir
    while True:
        chosen = pathlib.Path(prompt_text("Folder path", str(default_dir))).expanduser()
        try:
            target = chosen.resolve()
        except Exception:
            print("That path could not be resolved. Try again.")
            continue
        if target.exists() and target.is_dir():
            return target
        print("That folder was not found. Try again.")


def print_tutorial_summary(config: dict) -> None:
    provider = str(config.get("provider", "groq")).lower()
    if provider not in PROVIDERS:
        provider = "groq"
    model = normalize_model(provider, str(config.get("model", PROVIDERS[provider]["default_model"])))
    print("")
    print("SETUP SUMMARY")
    print(f"- Provider: {provider}")
    print(f"- Model: {model}")
    print(f"- Access mode: {access_mode_from(config)}")
    workspace_root = workspace_root_from_config(config)
    if workspace_root:
        print(f"- Workspace root: {workspace_root}")
    if str(config.get("auth_token") or "").strip():
        print("- Account mode: linked account mode is ready.")
    else:
        print("- Account mode: direct mode only, so account memory/history/web/Pro features stay off.")
    print("")
    print("Best next commands:")
    print("  zerothink")
    print("  /guide")
    print("  /help examples")
    print("  /scan")
    print("")


def access_mode_from(config: dict) -> str:
    return "folder" if str(config.get("access_mode", "machine")).strip().lower() in {"folder", "workspace", "project"} else "machine"


def normalize_steering_mode(value) -> str:
    lowered = str(value or "auto").strip().lower()
    aliases = {
        "safe": "plan",
        "plan-first": "plan",
        "ask": "confirm",
        "ask-first": "confirm",
        "confirm-first": "confirm",
        "execute": "auto",
        "execute-first": "auto",
        "run": "auto",
    }
    lowered = aliases.get(lowered, lowered)
    return lowered if lowered in STEERING_MODES else "auto"


def steering_mode_from(config: dict) -> str:
    return normalize_steering_mode(config.get("steering_mode", "auto"))


def workspace_root_from_config(config: dict) -> Optional[pathlib.Path]:
    raw = str(config.get("workspace_root") or "").strip()
    if not raw:
        return None
    try:
        return pathlib.Path(raw).expanduser().resolve()
    except Exception:
        return pathlib.Path(os.path.abspath(os.path.expanduser(raw)))


def get_server_profiles(config: dict) -> Dict[str, dict]:
    raw = config.get("servers")
    if not isinstance(raw, dict):
        return {}
    profiles: Dict[str, dict] = {}
    for name, profile in raw.items():
        if not isinstance(profile, dict):
            continue
        key = str(name).strip()
        if not key:
            continue
        profiles[key] = {
            "host": str(profile.get("host") or "").strip(),
            "user": str(profile.get("user") or "").strip(),
            "port": int(profile.get("port") or 22),
            "cwd": str(profile.get("cwd") or "").strip(),
            "identity": str(profile.get("identity") or "").strip(),
            "password": str(profile.get("password") or "").strip(),
            "password_env": str(profile.get("password_env") or "").strip(),
        }
    return profiles


def save_server_profiles(config: dict, profiles: Dict[str, dict]) -> None:
    config["servers"] = profiles
    save_config(config)


def get_mcp_servers(config: dict) -> Dict[str, dict]:
    raw = config.get("mcp_servers")
    if not isinstance(raw, dict):
        return {}
    servers: Dict[str, dict] = {}
    for name, profile in raw.items():
        if not isinstance(profile, dict):
            continue
        key = str(name or "").strip()
        if not key:
            continue
        command = str(profile.get("command") or "").strip()
        if not command:
            continue
        args = profile.get("args")
        env = profile.get("env")
        normalized_args = [str(item) for item in list(args or []) if str(item).strip()] if isinstance(args, list) else []
        looks_like_playwright = command.lower() == "npx" and any("@playwright/mcp" in item for item in normalized_args)
        if looks_like_playwright and "--yes" not in normalized_args:
            normalized_args = ["--yes"] + normalized_args
        if looks_like_playwright and "--isolated" not in normalized_args and "--save-session" not in normalized_args:
            normalized_args.append("--isolated")
        servers[key] = {
            "command": command,
            "args": normalized_args,
            "cwd": str(profile.get("cwd") or "").strip(),
            "transport": "stdio",
            "description": str(profile.get("description") or "").strip(),
            "env": {str(k): str(v) for k, v in (env.items() if isinstance(env, dict) else []) if str(k).strip()},
            "startup_timeout": max(10, int(profile.get("startup_timeout") or (180 if looks_like_playwright else MCP_STARTUP_TIMEOUT))),
            "call_timeout": max(10, int(profile.get("call_timeout") or (240 if looks_like_playwright else MCP_CALL_TIMEOUT))),
        }
    return servers


def save_mcp_servers(config: dict, servers: Dict[str, dict]) -> None:
    config["mcp_servers"] = servers
    save_config(config)


def format_mcp_command(profile: dict) -> str:
    parts = [str(profile.get("command") or "").strip()] + [str(item) for item in list(profile.get("args") or []) if str(item).strip()]
    return " ".join(parts).strip()


def build_playwright_mcp_profile(headless: bool = False, browser_name: str = "", isolated: bool = True) -> dict:
    args = ["--yes", "@playwright/mcp@latest"]
    if headless:
        args.append("--headless")
    if browser_name:
        args.append(f"--browser={browser_name}")
    if isolated:
        args.append("--isolated")
    return {
        "command": "npx",
        "args": args,
        "cwd": "",
        "transport": "stdio",
        "description": "Playwright MCP browser automation",
        "env": {},
        "startup_timeout": 180,
        "call_timeout": 240,
    }


def print_mcp_servers(config: dict) -> None:
    servers = get_mcp_servers(config)
    print(f"MCP servers: {len(servers)}")
    for name in sorted(servers.keys()):
        profile = servers[name]
        desc = str(profile.get("description") or "").strip()
        startup_timeout = int(profile.get("startup_timeout") or MCP_STARTUP_TIMEOUT)
        call_timeout = int(profile.get("call_timeout") or MCP_CALL_TIMEOUT)
        suffix = f" // {desc}" if desc else ""
        suffix += f" [startup {startup_timeout}s / call {call_timeout}s]"
        print(f"  - {name}: {format_mcp_command(profile)}{suffix}")


def get_mcp_profile(config: dict, name: str) -> dict:
    servers = get_mcp_servers(config)
    profile = servers.get(name)
    if not profile:
        raise RuntimeError(f"Unknown MCP server: {name}")
    return profile


def assert_path_allowed(path: pathlib.Path, config: dict) -> pathlib.Path:
    resolved = path.resolve()
    if access_mode_from(config) != "folder":
        return resolved
    root = workspace_root_from_config(config)
    if root is None:
        raise RuntimeError("Folder mode is active but no workspace root is set. Run `zerothink access folder PATH` first.")
    try:
        resolved.relative_to(root)
    except ValueError as exc:
        raise RuntimeError(f"Folder mode is active. Path is outside the workspace root: {resolved}") from exc
    return resolved


def resolve_scoped_path(value: Optional[str], config: dict, cwd: Optional[pathlib.Path] = None) -> pathlib.Path:
    return assert_path_allowed(resolve_cli_path(value, cwd), config)


def show_access_status(config: dict) -> None:
    mode = access_mode_from(config)
    root = workspace_root_from_config(config)
    print(f"Access mode: {mode}")
    if root:
        print(f"Workspace root: {root}")
    elif mode == "folder":
        print("Workspace root: not set")
    servers = get_server_profiles(config)
    print(f"Server profiles: {len(servers)}")


def set_access_mode(mode: str, workspace: Optional[pathlib.Path] = None) -> dict:
    config = load_config()
    normalized = "folder" if mode == "folder" else "machine"
    config["access_mode"] = normalized
    if workspace is not None:
        config["workspace_root"] = str(workspace.resolve())
    elif normalized == "machine":
        config["workspace_root"] = str(config.get("workspace_root") or "")
    save_config(config)
    return config


def require_machine_mode(config: dict) -> None:
    if access_mode_from(config) == "folder":
        raise RuntimeError("This action is disabled in folder mode. Switch with `zerothink access machine` if you want full-machine execution.")


def normalize_model(provider: str, model: str) -> str:
    provider = provider.lower().strip()
    model = model.strip()
    if not model:
        return PROVIDERS[provider]["default_model"]
    return MODEL_ALIASES.get(provider, {}).get(model, model)


def http_json(url: str, payload: dict, headers: dict) -> dict:
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers=headers,
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=240) as response:
            raw = response.read().decode("utf-8", errors="replace")
            return json.loads(raw)
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        try:
            parsed = json.loads(body)
            message = (
                parsed.get("error", {}).get("message")
                or parsed.get("message")
                or parsed.get("reply")
                or body[:400]
            )
        except Exception:
            message = body[:400]
        raise RuntimeError(f"API error ({exc.code}): {message}") from None
    except urllib.error.URLError as exc:
        raise RuntimeError(f"Network error: {exc.reason}") from None


def boolish(value) -> bool:
    if isinstance(value, bool):
        return value
    return str(value).strip().lower() in {"1", "true", "yes", "on", "enabled", "y"}


def base_url_from(config: dict) -> str:
    return str(config.get("base_url") or BASE_URL).rstrip("/")


def auth_headers(config: dict) -> dict:
    token = str(config.get("auth_token") or "").strip()
    if not token:
        raise RuntimeError("Not logged in. Run `zerothink login` first.")
    return {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {token}",
        "X-ZeroThink-Token": token,
        "X-Auth-Token": token,
    }


def resolve_chat_mode(config: dict, provider: str) -> Tuple[str, str, str]:
    token = str(config.get("auth_token") or "").strip()
    if token:
        return "account", "", "linked account"

    provider_name = str(provider or "").strip().lower()
    if provider_name in DIRECT_KEY_PROVIDERS:
        key, source = local_direct_key(config, provider_name)
        if key:
            return "direct", key, source
        raise RuntimeError(
            f"Not logged in and no local {provider_name.upper()} key is configured. "
            f"Run `zerothink login` or `zerothink key set {provider_name}`."
        )

    raise RuntimeError(
        f"Not logged in. Provider `{provider_name}` needs a linked ZeroThink account. "
        "Run `zerothink login` or switch to Groq/NVIDIA and configure a local key."
    )


def is_auth_error_message(message: str) -> bool:
    text = str(message or "").strip().lower()
    return (
        "403" in text
        and (
            "auth required" in text
            or "auth or supported direct key mode required" in text
            or "not authorized" in text
            or "forbidden" in text
        )
    )


def is_provider_fallback_message(message: str) -> bool:
    text = str(message or "").strip().lower()
    if not text:
        return False
    indicators = (
        "api error (400)",
        "api error (401)",
        "api error (429)",
        "api error (500)",
        "provider error",
        "api key missing",
        "failed to unmarshal json",
        "failed to decode json body",
        "invalid api key",
        "invalid authentication",
        "unsupported model",
        "model not found",
        "service unavailable",
    )
    return any(token in text for token in indicators)


def is_runtime_identity_question(prompt: str) -> bool:
    text = " ".join(str(prompt or "").strip().lower().split())
    if not text or len(text) > 240:
        return False
    exact_phrases = (
        "what api are you using",
        "which api are you using",
        "what api is this using",
        "which api is this using",
        "what model are you using",
        "which model are you using",
        "what model is this using",
        "which model is this using",
        "what provider are you using",
        "which provider are you using",
        "what provider is this using",
        "which provider is this using",
        "what backend are you using",
        "which backend are you using",
        "what llm are you using",
        "which llm are you using",
        "what engine are you using",
        "which engine are you using",
        "what route are you using",
        "which route are you using",
        "what are you running on",
        "what am i connected to",
        "are you using groq",
        "are you using nvidia",
        "are you using openai",
        "are you using gemini",
        "are you using xai",
    )
    if any(phrase in text for phrase in exact_phrases):
        return True
    if any(token in text for token in ("api", "model", "provider", "backend", "llm", "engine", "route")):
        if any(token in text for token in ("using", "running", "connected", "connected to", "right now", "currently")):
            return True
    return False


def build_runtime_route_info(
    config: dict,
    provider: str,
    model: str,
    requested_mode: str,
    requested_source: str = "",
    actual_mode: str = "",
    actual_source: str = "",
    fallback_from: str = "",
) -> dict:
    provider_name = str(provider or "").strip().lower() or "groq"
    model_name = normalize_model(provider_name, str(model or PROVIDERS[provider_name]["default_model"]))
    base_url = str(PROVIDERS.get(provider_name, {}).get("base_url") or "")
    parsed = urllib.parse.urlparse(base_url) if "://" in base_url else None
    api_host = parsed.netloc if parsed and parsed.netloc else base_url
    effective_mode = (actual_mode or requested_mode or "account").strip().lower()
    effective_source = (actual_source or requested_source or ("linked account" if effective_mode == "account" else "local key")).strip()
    return {
        "provider": provider_name,
        "model": model_name,
        "api_host": api_host,
        "access_mode": access_mode_from(config),
        "requested_mode": str(requested_mode or "").strip().lower(),
        "requested_source": str(requested_source or "").strip(),
        "actual_mode": effective_mode,
        "actual_source": effective_source,
        "fallback_from": str(fallback_from or "").strip().lower(),
        "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
    }


def remember_runtime_route(route: dict) -> dict:
    LAST_ROUTE_INFO.clear()
    for key, value in (route or {}).items():
        LAST_ROUTE_INFO[str(key)] = str(value)
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    ensure_private_permissions(CONFIG_DIR)
    temp_path = ROUTE_FILE.with_suffix(".json.tmp")
    temp_path.write_text(json.dumps(LAST_ROUTE_INFO, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    temp_path.replace(ROUTE_FILE)
    ensure_private_permissions(ROUTE_FILE)
    return dict(LAST_ROUTE_INFO)


def current_runtime_route(config: dict, provider: str, model: str, requested_mode: str, requested_source: str = "") -> dict:
    provider_name = str(provider or "").strip().lower() or "groq"
    model_name = normalize_model(provider_name, str(model or PROVIDERS[provider_name]["default_model"]))
    if LAST_ROUTE_INFO and LAST_ROUTE_INFO.get("provider") == provider_name and LAST_ROUTE_INFO.get("model") == model_name:
        return dict(LAST_ROUTE_INFO)
    if ROUTE_FILE.exists():
        try:
            saved = json.loads(ROUTE_FILE.read_text(encoding="utf-8"))
            if isinstance(saved, dict) and saved.get("provider") == provider_name and saved.get("model") == model_name:
                LAST_ROUTE_INFO.clear()
                for key, value in saved.items():
                    LAST_ROUTE_INFO[str(key)] = str(value)
                return dict(LAST_ROUTE_INFO)
        except Exception:
            pass
    return build_runtime_route_info(config, provider_name, model_name, requested_mode, requested_source=requested_source)


def format_runtime_route(route: dict) -> str:
    provider = str(route.get("provider") or "groq")
    model = str(route.get("model") or "")
    api_host = str(route.get("api_host") or "")
    actual_mode = str(route.get("actual_mode") or "")
    actual_source = str(route.get("actual_source") or "")
    fallback_from = str(route.get("fallback_from") or "")
    lines = [
        f"Current CLI route: provider `{provider}`, model `{model}`.",
    ]
    if api_host:
        lines.append(f"API host: `{api_host}`.")
    if actual_mode == "direct":
        lines.append(f"Auth lane: local machine key ({actual_source}).")
    elif actual_mode == "account":
        lines.append("Auth lane: linked ZeroThink account token.")
    if fallback_from and fallback_from != actual_mode:
        lines.append(
            f"The linked `{provider}` lane failed earlier in this session, so the CLI fell back to your local `{provider.upper()}` key."
        )
    if provider == "groq" and model.startswith("openai/"):
        lines.append(
            "The `openai/...` model label here is the Groq model ID. This route is using Groq's OpenAI-compatible API, not OpenAI's own API."
        )
    return "\n".join(lines)


def is_cli_self_identity_question(prompt: str) -> bool:
    text = " ".join(str(prompt or "").strip().lower().split())
    if not text or len(text) > 260:
        return False
    phrases = (
        "who are you",
        "what are you",
        "identify yourself",
        "what is zerothink cli",
        "what is agent zero",
        "are you zerothink cli",
        "are you agent zero",
        "what do i call you",
    )
    return any(phrase in text for phrase in phrases)


def is_cli_capability_question(prompt: str) -> bool:
    text = " ".join(str(prompt or "").strip().lower().split())
    if not text or len(text) > 320:
        return False
    phrases = (
        "what can you do",
        "what are your capabilities",
        "what can zerothink cli do",
        "what can agent zero do",
        "what tools do you have",
        "what commands do you have",
        "what can you access",
        "what are you able to do",
        "how can you help",
        "what do you do",
    )
    return any(phrase in text for phrase in phrases)


def format_agent_zero_identity(route: dict) -> str:
    provider = str(route.get("provider") or "groq")
    model = str(route.get("model") or "")
    actual_mode = str(route.get("actual_mode") or "")
    lines = [
        "I’m ZeroThink CLI aka Agent Zero, the terminal operator and coding client for ZeroThink.",
        f"Current route: provider `{provider}`, model `{model}`.",
    ]
    if actual_mode == "account":
        lines.append("This session is using the linked ZeroThink account lane.")
    elif actual_mode == "direct":
        lines.append("This session is using local direct-key mode on this machine.")
    lines.append("Type `/capabilities`, `/status`, `/runtime`, or `/help commands` for the live terminal map.")
    return "\n".join(lines)


def format_agent_zero_capabilities(route: dict) -> str:
    actual_mode = str(route.get("actual_mode") or "")
    lines = [
        "I’m ZeroThink CLI aka Agent Zero. Here’s what I can do in this terminal:",
        "- chat through the current provider/model route",
        "- inspect, read, write, append, replace, copy, move, and remove local files",
        "- scan repos, attach files/folders/zips, and handle large code or text context",
        "- create polished static site starters and playable no-dependency browser games",
        "- create simple local PDF reports from text or files",
        "- maintain a local Zero Library index so search/context packing costs no model tokens",
        "- analyze DNA/RNA sequences locally for research-only GC, motif, ORF, translation, and reverse-complement checks",
        "- encrypt/decrypt files and encrypted folder bundles with the local Z-Pepper envelope",
        "- run local shell commands",
        "- search the web locally, browse pages, and attach page context",
        "- use MCP servers and Playwright browser automation",
        "- manage local plans, checkpoints, exports, sessions, and saved agents",
        "- SSH into saved servers, run compact health/file checks, transfer files, and read or edit remote files",
        "- run bounded autopilot and named worker agents like zero1, zero2, and zero3",
    ]
    if actual_mode == "direct":
        lines.append("- direct-key mode is active, so I do not use ZeroThink account memory/history/Pro account state")
    else:
        lines.append("- linked-account mode is active, so I can use the normal ZeroThink account lane")
    lines.append("Useful commands: `/about`, `/capabilities`, `/status`, `/runtime`, `/help commands`.")
    return "\n".join(lines)


def local_agent_zero_reply(
    config: dict,
    provider_name: str,
    model_name: str,
    requested_mode: str,
    requested_source: str,
    prompt: str,
) -> str:
    if not prompt:
        return ""
    route = current_runtime_route(config, provider_name, model_name, requested_mode, requested_source=requested_source)
    if is_cli_capability_question(prompt):
        return format_agent_zero_capabilities(route)
    if is_cli_self_identity_question(prompt):
        return format_agent_zero_identity(route)
    return ""


def chat_error_message(result: dict) -> str:
    if not isinstance(result, dict):
        return ""
    status = str(result.get("status") or "").strip().lower()
    reply = str(result.get("reply") or result.get("message") or result.get("error") or "").strip()
    lowered = reply.lower()
    if status in {"error", "failed", "fail"}:
        return reply or "Unknown chat error."
    if lowered.startswith("request failed:") or lowered.startswith("api error") or "provider error" in lowered:
        return reply
    return ""


def path_completion_candidates(fragment: str, cwd: pathlib.Path) -> List[str]:
    fragment = fragment or ""
    expanded = os.path.expanduser(fragment)
    slash_hint = fragment.endswith(("/", "\\"))

    if fragment in {"", "."}:
        search_dir = cwd
        prefix = ""
        lead = ""
    else:
        raw_path = pathlib.Path(expanded)
        if raw_path.is_absolute():
            if slash_hint:
                search_dir = raw_path
                prefix = ""
            else:
                search_dir = raw_path.parent
                prefix = raw_path.name
            lead = str(search_dir)
            if lead and not lead.endswith(("\\", "/")):
                lead += os.sep
        else:
            rel_path = pathlib.Path(fragment)
            if slash_hint:
                search_dir = (cwd / rel_path).resolve()
                prefix = ""
                lead = fragment
            else:
                rel_parent = rel_path.parent
                search_dir = (cwd / rel_parent).resolve()
                prefix = rel_path.name
                lead = "" if str(rel_parent) in {"", "."} else str(rel_parent).replace("\\", "/") + "/"

    try:
        if not search_dir.exists() or not search_dir.is_dir():
            return []
        matches = []
        for item in sorted(search_dir.iterdir(), key=lambda value: (not value.is_dir(), value.name.lower())):
            if prefix and not item.name.lower().startswith(prefix.lower()):
                continue
            display = lead + item.name
            if item.is_dir():
                display += "/"
            matches.append(display)
        return matches[:200]
    except Exception:
        return []


class ZeroThinkCompleter(Completer):
    def __init__(self, current_dir_getter, provider_getter):
        self.current_dir_getter = current_dir_getter
        self.provider_getter = provider_getter

    def _yield_matches(self, current: str, options: List[str]):
        seen = set()
        needle = (current or "").lower()
        for option in options:
            if option in seen:
                continue
            if needle and not option.lower().startswith(needle):
                continue
            seen.add(option)
            yield Completion(option, start_position=-len(current), display=option)

    def get_completions(self, document, complete_event):
        text = document.text_before_cursor
        stripped = text.lstrip()
        if not stripped.startswith("/"):
            return

        body = stripped[1:]
        trailing_space = stripped.endswith(" ")
        current_dir = pathlib.Path(self.current_dir_getter()).resolve()
        custom_path, custom_commands = load_custom_commands(current_dir, load_config())
        parts = split_repl_command_line(body)

        if not parts:
            yield from self._yield_matches("", REPL_COMMANDS + sorted(custom_commands.keys()))
            return

        if len(parts) == 1 and not trailing_space:
            yield from self._yield_matches(parts[0], REPL_COMMANDS + sorted(custom_commands.keys()))
            return

        cmd = parts[0].lower()
        args = parts[1:]
        current = "" if trailing_space else (args[-1] if args else "")
        arg_index = len(args) if trailing_space else max(0, len(args) - 1)

        if cmd == "help":
            yield from self._yield_matches(current, sorted(HELP_TOPICS.keys()))
            return
        if cmd == "provider":
            yield from self._yield_matches(current, sorted(PROVIDERS.keys()))
            return
        if cmd == "model":
            provider = str(self.provider_getter() or "groq").lower()
            models = MODEL_CATALOG.get(provider, [])
            yield from self._yield_matches(current, models)
            return
        if cmd == "models":
            yield from self._yield_matches(current, sorted(PROVIDERS.keys()))
            return
        if cmd in {"zero", "web"}:
            yield from self._yield_matches(current, BOOLEAN_COMPLETIONS)
            return
        if cmd == "key":
            if arg_index == 0:
                yield from self._yield_matches(current, ["status", "set", "clear"])
                return
            if arg_index == 1 and args and args[0].lower() in {"set", "clear"}:
                yield from self._yield_matches(current, list(DIRECT_KEY_PROVIDERS))
                return
        if cmd == "steer":
            yield from self._yield_matches(current, ["status", "auto", "plan", "confirm"])
            return
        if cmd == "stop":
            yield from self._yield_matches(current, [item["name"] for item in list_saved_agents()])
            return
        if cmd == "context":
            yield from self._yield_matches(current, ["status", "show", "on", "off", "reload"])
            return
        if cmd in {"library", "lib"}:
            if arg_index == 0:
                yield from self._yield_matches(current, ["status", "add", "remove", "index", "files", "search", "context"])
                return
            if arg_index == 1 and args and args[0].lower() in {"add", "remove"}:
                yield from self._yield_matches(current, path_completion_candidates(current, pathlib.Path(self.current_dir_getter()).resolve()))
                return
            return
        if cmd == "dna":
            if arg_index == 0:
                yield from self._yield_matches(current, ["analyze"])
                return
            if arg_index == 1 and args and args[0].lower() == "analyze":
                yield from self._yield_matches(current, path_completion_candidates(current, pathlib.Path(self.current_dir_getter()).resolve()))
                return
        if cmd == "plugins":
            return
        if cmd == "resume":
            yield from self._yield_matches(current, [item["name"] for item in list_saved_sessions()])
            return
        if cmd == "custom":
            yield from self._yield_matches(current, ["status", "show"])
            return
        if cmd == "agent":
            if arg_index == 0:
                yield from self._yield_matches(current, ["list", "show", "log", "rm", "create", "step", "run"])
                return
            if arg_index == 1 and args and args[0].lower() in {"show", "log", "rm", "step", "run"}:
                yield from self._yield_matches(current, [item["name"] for item in list_saved_agents()])
                return
        if cmd == "mcp":
            config = load_config()
            names = sorted(get_mcp_servers(config).keys())
            if arg_index == 0:
                yield from self._yield_matches(current, ["list", "add", "add-playwright", "show", "rm", "tools", "call"])
                return
            if arg_index == 1 and args:
                first = args[0].lower()
                if first in {"show", "rm", "tools", "call"}:
                    yield from self._yield_matches(current, names)
                    return
            return
        if cmd in {"playwright", "pw"}:
            if arg_index == 0:
                yield from self._yield_matches(current, ["status", "tools", "open", "call"])
            return
        if cmd == "ssh":
            if arg_index == 0:
                yield from self._yield_matches(current, sorted(get_server_profiles(load_config()).keys()))
            return
        if cmd == "scp":
            if arg_index == 0:
                yield from self._yield_matches(current, ["put", "get"])
                return
            if arg_index == 1:
                yield from self._yield_matches(current, sorted(get_server_profiles(load_config()).keys()))
                return
            if arg_index == 2 and args and args[0].lower() == "put":
                yield from self._yield_matches(current, path_completion_candidates(current, pathlib.Path(self.current_dir_getter()).resolve()))
            if arg_index == 3 and args and args[0].lower() == "get":
                yield from self._yield_matches(current, path_completion_candidates(current, pathlib.Path(self.current_dir_getter()).resolve()))
            return
        if cmd == "remote":
            remote_actions = ["pwd", "ls", "tree", "scan", "doctor", "check", "read", "search", "mkdir", "rm", "write", "append", "replace", "exec"]
            if arg_index == 0:
                yield from self._yield_matches(current, remote_actions)
                return
            if arg_index == 1:
                yield from self._yield_matches(current, sorted(get_server_profiles(load_config()).keys()))
                return
            return
        if cmd == "access":
            if arg_index == 0:
                yield from self._yield_matches(current, ["status", "machine", "folder"])
                return
            if arg_index == 1 and args and args[0].lower() == "folder":
                yield from self._yield_matches(current, path_completion_candidates(current, pathlib.Path(self.current_dir_getter()).resolve()))
                return
        if cmd == "zip":
            if arg_index == 0:
                yield from self._yield_matches(current, ["list", "extract", "create"])
                return
            yield from self._yield_matches(current, path_completion_candidates(current, pathlib.Path(self.current_dir_getter()).resolve()))
            return
        if cmd == "search-web":
            return
        if cmd in {"autopilot", "team"}:
            return
        if cmd == "attach-page":
            return
        if cmd == "page":
            return
        if cmd == "pages":
            return
        if cmd in {"attach", "detach", "cd", "ls", "tree", "scan", "read", "search", "workspace", "export"}:
            yield from self._yield_matches(current, path_completion_candidates(current, pathlib.Path(self.current_dir_getter()).resolve()))
            return
        if cmd in {"attach-url", "detach-url", "fetch", "browse"}:
            return


def build_repl_prompt_session(current_dir_getter, provider_getter):
    if not PROMPT_TOOLKIT_AVAILABLE or not is_tty_session():
        return None
    try:
        CONFIG_DIR.mkdir(parents=True, exist_ok=True)
        return PromptSession(
            history=FileHistory(str(HISTORY_FILE)),
            completer=ZeroThinkCompleter(current_dir_getter, provider_getter),
            complete_while_typing=True,
        )
    except Exception:
        return None


def read_repl_line(session, prompt_text: str) -> str:
    if session is not None:
        return session.prompt(prompt_text)
    return input(prompt_text)


def resolve_repl_command_name(raw: str) -> str:
    candidate = (raw or "").strip().lower()
    if not candidate:
        return ""
    if candidate in REPL_COMMANDS:
        return candidate
    aliases = {
        "start": "guide",
        "begin": "guide",
        "about": "about",
        "whoareyou": "about",
        "identity": "about",
        "capability": "capabilities",
        "capabilities": "capabilities",
        "caps": "capabilities",
        "tools": "capabilities",
        "me": "whoami",
        "who": "whoami",
        "ctx": "context",
        "save": "checkpoint",
        "load": "resume",
        "task": "plan",
        "tasks": "plan",
        "auto": "autopilot",
        "agents": "agent",
        "websearch": "search-web",
        "searchweb": "search-web",
        "browser": "browse",
        "open": "page",
        "pageshow": "page",
    }
    if candidate in aliases:
        return aliases[candidate]
    matches = [item for item in REPL_COMMANDS if item.startswith(candidate)]
    if len(matches) == 1:
        return matches[0]
    return candidate


def split_repl_command_line(body: str) -> List[str]:
    body = (body or "").strip()
    if not body:
        return []

    raw_cmd, has_sep, raw_tail = body.partition(" ")
    cmd = resolve_repl_command_name(raw_cmd.lower())
    if not has_sep:
        return [cmd] if cmd else []

    tail = raw_tail.strip()
    if not tail:
        return [cmd]

    if cmd == "access":
        first, has_rest, rest = tail.partition(" ")
        first = first.strip()
        if first.lower() == "folder" and has_rest and rest.strip():
            return [cmd, "folder", rest.strip()]

    if cmd in RAW_TAIL_REPL_COMMANDS:
        return [cmd, tail]

    if cmd in PATH_NUMERIC_TAIL_REPL_COMMANDS:
        max_numeric = PATH_NUMERIC_TAIL_REPL_COMMANDS[cmd]
        numbers: List[str] = []
        path_part = tail
        while max_numeric > 0:
            candidate_path, sep, maybe_number = path_part.rpartition(" ")
            if not sep or not maybe_number.strip().isdigit():
                break
            numbers.insert(0, maybe_number.strip())
            path_part = candidate_path.strip()
            max_numeric -= 1
        if path_part:
            return [cmd, path_part, *numbers]

    try:
        parts = shlex.split(body)
    except ValueError:
        parts = body.split()
    if not parts:
        return []
    resolved = resolve_repl_command_name(parts[0].lower())
    return [resolved, *parts[1:]]


def api_cli(config: dict, action: str, payload: Optional[dict] = None, auth: bool = False) -> dict:
    body = {"action": action}
    if payload:
        body.update(payload)
    headers = {"Content-Type": "application/json"}
    if auth:
        headers = auth_headers(config)
    base_url = base_url_from(config)
    last_error: Optional[Exception] = None
    for endpoint in ("/api/cli", "/api_cli.php"):
        try:
            return http_json(f"{base_url}{endpoint}", body, headers)
        except RuntimeError as exc:
            last_error = exc
            if endpoint == "/api/cli" and "API error (404)" in str(exc):
                continue
            raise
    if last_error:
        raise last_error
    raise RuntimeError("CLI API endpoint is unavailable.")


def agent_request(config: dict, payload: dict, mode: str = "account", direct_key: str = "") -> dict:
    request_payload = dict(payload)
    if mode == "direct":
        request_payload["direct_mode"] = True
        request_payload["key"] = direct_key
        return http_json(f"{base_url_from(config)}/api_agent.php", request_payload, {"Content-Type": "application/json"})
    return http_json(f"{base_url_from(config)}/api_agent.php", request_payload, auth_headers(config))


def extract_openai_text(data: dict) -> str:
    content = data.get("choices", [{}])[0].get("message", {}).get("content", "")
    if isinstance(content, list):
        parts = []
        for item in content:
            if isinstance(item, str):
                parts.append(item)
            elif isinstance(item, dict) and item.get("text"):
                parts.append(str(item["text"]))
        return "\n".join(parts).strip()
    return str(content).strip()


def extract_gemini_text(data: dict) -> str:
    parts = data.get("candidates", [{}])[0].get("content", {}).get("parts", [])
    chunks = []
    for part in parts:
        if isinstance(part, dict) and part.get("text"):
            chunks.append(str(part["text"]))
    return "\n".join(chunks).strip()


def build_outline(content: str) -> str:
    lines = content.splitlines()
    hits = []
    patterns = [
        ("class ", "class"),
        ("interface ", "interface"),
        ("trait ", "trait"),
        ("enum ", "enum"),
        ("function ", "function"),
        ("def ", "def"),
        ("fn ", "fn"),
        ("struct ", "struct"),
        ("impl ", "impl"),
    ]
    for idx, line in enumerate(lines, start=1):
        text = line.strip()
        if not text:
            continue
        lowered = text.lower()
        for prefix, label in patterns:
            if lowered.startswith(prefix):
                name = text.split("(", 1)[0].split("{", 1)[0]
                hits.append(f"L{idx}  {label} {name[len(prefix):].strip()}")
                break
        if len(hits) >= 120:
            break
    return "\n".join(hits) if hits else "No obvious symbols detected. Treat as raw text, config, or log content."


def pack_file_context(path: pathlib.Path) -> str:
    raw = path.read_text(encoding="utf-8", errors="replace").replace("\x00", "")
    outline = build_outline(raw)
    chars = len(raw)
    lines = raw.count("\n") + 1
    header = (
        "FILE PACK\n"
        f"Name: {path.name}\n"
        f"Chars: {chars:,}\n"
        f"Lines: {lines:,}\n"
        "Structure Index:\n"
        f"{outline}\n"
    )

    if chars <= 260000:
        return f"{header}[FULL FILE BELOW]\n{raw}\n[END FILE]"

    head = raw[:95000]
    quarter_start = max(0, int(chars * 0.25) - 20000)
    quarter = raw[quarter_start : quarter_start + 40000]
    middle_start = max(0, int(chars * 0.5) - 27500)
    middle = raw[middle_start : middle_start + 55000]
    three_quarter_start = max(0, int(chars * 0.75) - 20000)
    three_quarter = raw[three_quarter_start : three_quarter_start + 40000]
    tail = raw[-95000:]
    return (
        f"{header}[LARGE FILE PACKED FOR MODEL WINDOW]\n"
        f"[HEAD]\n{head}\n"
        f"[QUARTER]\n{quarter}\n"
        f"[MIDDLE]\n{middle}\n"
        f"[THREE QUARTER]\n{three_quarter}\n"
        f"[TAIL]\n{tail}\n[END FILE]"
    )


def is_text_file(path: pathlib.Path) -> bool:
    if path.suffix.lower() in TEXT_EXTENSIONS:
        return True
    return path.name.lower() in {"dockerfile", "makefile"}


def pack_directory(root: pathlib.Path) -> str:
    files = [path for path in root.rglob("*") if path.is_file()]
    manifest = [f"DIRECTORY PACK\nRoot: {root.name}\nFiles: {len(files):,}\nTree Preview:"]
    samples = []
    for path in sorted(files)[:240]:
        rel = path.relative_to(root).as_posix()
        manifest.append(f"- {rel} ({path.stat().st_size:,} bytes)")
        if len(samples) < DIRECTORY_SAMPLE_LIMIT and is_text_file(path):
            samples.append(path)
    chunks = ["\n".join(manifest)]
    for sample in samples:
        rel = sample.relative_to(root).as_posix()
        chunks.append(pack_file_context(sample.with_name(sample.name)))
        chunks[-1] = chunks[-1].replace(f"Name: {sample.name}", f"Name: {root.name}/{rel}", 1)
    return "\n\n".join(chunks)


def list_archive_members(path: pathlib.Path) -> List[Tuple[str, int]]:
    items: List[Tuple[str, int]] = []
    lower = path.name.lower()
    if lower.endswith(".zip"):
        with zipfile.ZipFile(path) as zf:
            for info in zf.infolist():
                if not info.is_dir():
                    items.append((info.filename, info.file_size))
    else:
        mode = "r:gz" if lower.endswith(".tar.gz") or lower.endswith(".tgz") else "r:"
        with tarfile.open(path, mode) as tf:
            for member in tf.getmembers():
                if member.isfile():
                    items.append((member.name, member.size))
    return items


def pack_archive(path: pathlib.Path) -> str:
    members = list_archive_members(path)
    manifest = [f"ARCHIVE PACK\nName: {path.name}\nMembers: {len(members):,}\nContents Preview:"]
    for name, size in members[:260]:
        manifest.append(f"- {name} ({size:,} bytes)")

    chunks = ["\n".join(manifest)]
    samples = 0
    lower = path.name.lower()
    if lower.endswith(".zip"):
        with zipfile.ZipFile(path) as zf:
            for info in zf.infolist():
                if info.is_dir():
                    continue
                member = pathlib.PurePosixPath(info.filename)
                if member.suffix.lower() not in TEXT_EXTENSIONS and member.name.lower() not in {"dockerfile", "makefile"}:
                    continue
                if samples >= ARCHIVE_SAMPLE_LIMIT:
                    break
                try:
                    text = zf.read(info.filename).decode("utf-8", errors="replace")
                except Exception:
                    continue
                chunks.append(pack_file_context_from_text(f"{path.name}::{member.as_posix()}", text))
                samples += 1
    else:
        mode = "r:gz" if lower.endswith(".tar.gz") or lower.endswith(".tgz") else "r:"
        with tarfile.open(path, mode) as tf:
            for member in tf.getmembers():
                if not member.isfile():
                    continue
                rel = pathlib.PurePosixPath(member.name)
                if rel.suffix.lower() not in TEXT_EXTENSIONS and rel.name.lower() not in {"dockerfile", "makefile"}:
                    continue
                if samples >= ARCHIVE_SAMPLE_LIMIT:
                    break
                extracted = tf.extractfile(member)
                if extracted is None:
                    continue
                try:
                    text = extracted.read().decode("utf-8", errors="replace")
                except Exception:
                    continue
                chunks.append(pack_file_context_from_text(f"{path.name}::{rel.as_posix()}", text))
                samples += 1
    if samples == 0:
        chunks.append("No text/code members were sampled from this archive. Ask ZeroThink to reason from the manifest only.")
    return "\n\n".join(chunks)


def pack_file_context_from_text(name: str, text: str) -> str:
    text = text.replace("\x00", "")
    outline = build_outline(text)
    chars = len(text)
    lines = text.count("\n") + 1
    header = (
        "FILE PACK\n"
        f"Name: {name}\n"
        f"Chars: {chars:,}\n"
        f"Lines: {lines:,}\n"
        "Structure Index:\n"
        f"{outline}\n"
    )
    if chars <= 260000:
        return f"{header}[FULL FILE BELOW]\n{text}\n[END FILE]"
    head = text[:95000]
    quarter_start = max(0, int(chars * 0.25) - 20000)
    quarter = text[quarter_start : quarter_start + 40000]
    middle_start = max(0, int(chars * 0.5) - 27500)
    middle = text[middle_start : middle_start + 55000]
    three_quarter_start = max(0, int(chars * 0.75) - 20000)
    three_quarter = text[three_quarter_start : three_quarter_start + 40000]
    tail = text[-95000:]
    return (
        f"{header}[LARGE FILE PACKED FOR MODEL WINDOW]\n"
        f"[HEAD]\n{head}\n"
        f"[QUARTER]\n{quarter}\n"
        f"[MIDDLE]\n{middle}\n"
        f"[THREE QUARTER]\n{three_quarter}\n"
        f"[TAIL]\n{tail}\n[END FILE]"
    )


def build_file_contexts(paths: List[str]) -> str:
    chunks = []
    for raw in paths:
        path = pathlib.Path(raw).expanduser().resolve()
        if not path.exists():
            raise RuntimeError(f"File not found: {raw}")
        lower = path.name.lower()
        if path.is_dir():
            chunks.append(pack_directory(path))
        elif lower.endswith(".zip") or lower.endswith(".tar.gz") or lower.endswith(".tgz") or lower.endswith(".tar"):
            chunks.append(pack_archive(path))
        elif is_text_file(path):
            chunks.append(pack_file_context(path))
        else:
            raise RuntimeError(f"Unsupported attachment type: {path.name}")
    return "\n\n==== NEXT ATTACHMENT ====\n\n".join(chunks)


def library_paths_from_config(config: Optional[dict] = None) -> List[pathlib.Path]:
    current = config if isinstance(config, dict) else load_config()
    seen = set()
    paths: List[pathlib.Path] = []
    for raw in list(current.get("library_paths") or []):
        text = str(raw or "").strip()
        if not text:
            continue
        try:
            path = pathlib.Path(text).expanduser().resolve()
        except Exception:
            continue
        key = str(path).lower()
        if key not in seen:
            seen.add(key)
            paths.append(path)
    try:
        if LIBRARY_DIR.exists():
            key = str(LIBRARY_DIR.resolve()).lower()
            if key not in seen:
                paths.append(LIBRARY_DIR.resolve())
    except Exception:
        pass
    return paths


def save_library_paths(paths: List[pathlib.Path]) -> None:
    config = load_config()
    clean: List[str] = []
    seen = set()
    for path in paths:
        try:
            resolved = pathlib.Path(path).expanduser().resolve()
        except Exception:
            continue
        key = str(resolved).lower()
        if key in seen:
            continue
        seen.add(key)
        clean.append(str(resolved))
    config["library_paths"] = clean
    save_config(config)


def library_tokenize(text: str) -> Dict[str, int]:
    stop = {
        "the", "and", "for", "with", "that", "this", "from", "into", "your", "you",
        "are", "was", "were", "will", "can", "not", "have", "has", "use", "using",
        "file", "code", "then", "than", "been", "they", "them", "what", "when",
    }
    tokens: Dict[str, int] = {}
    for token in re.findall(r"[a-zA-Z0-9_]{2,}", str(text or "").lower()):
        if token not in stop:
            tokens[token] = tokens.get(token, 0) + 1
    return tokens


def library_scan_files(paths: Optional[List[pathlib.Path]] = None) -> List[pathlib.Path]:
    roots = paths if paths is not None else library_paths_from_config()
    files: List[pathlib.Path] = []
    seen = set()
    skip_dirs = {".git", ".hg", ".svn", "__pycache__", "node_modules", ".venv", "venv", ".runtime"}
    for root in roots:
        if not root.exists():
            continue
        candidates = [root] if root.is_file() else root.rglob("*")
        for path in candidates:
            try:
                if path.is_dir():
                    continue
                if any(part in skip_dirs for part in path.parts):
                    continue
                if not is_text_file(path):
                    continue
                size = path.stat().st_size
                if size <= 0 or size > MAX_LIBRARY_FILE_BYTES:
                    continue
                key = str(path.resolve()).lower()
                if key in seen:
                    continue
                seen.add(key)
                files.append(path.resolve())
                if len(files) >= MAX_LIBRARY_FILES:
                    return files
            except Exception:
                continue
    return files


def library_build_index(force: bool = False) -> dict:
    if not force and LIBRARY_INDEX_FILE.exists():
        try:
            if time.time() - LIBRARY_INDEX_FILE.stat().st_mtime < 300:
                with open(LIBRARY_INDEX_FILE, "r", encoding="utf-8") as handle:
                    cached = json.load(handle)
                if isinstance(cached, dict):
                    return cached
        except Exception:
            pass
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    ensure_private_permissions(CONFIG_DIR)
    items = []
    for path in library_scan_files():
        try:
            text = path.read_text(encoding="utf-8", errors="replace").replace("\x00", "")
            sample = text[:300000]
            tokens = library_tokenize(sample)
            top_terms = sorted(tokens, key=lambda key: tokens[key], reverse=True)[:24]
            items.append(
                {
                    "path": str(path),
                    "name": path.name,
                    "size": path.stat().st_size,
                    "mtime": int(path.stat().st_mtime),
                    "lines": text.count("\n") + 1,
                    "sha256": hashlib.sha256(path.read_bytes()).hexdigest(),
                    "top_terms": top_terms,
                    "outline": build_outline(sample),
                }
            )
        except Exception:
            continue
    index = {
        "version": 1,
        "generated_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "paths": [str(path) for path in library_paths_from_config()],
        "count": len(items),
        "items": items,
    }
    with open(LIBRARY_INDEX_FILE, "w", encoding="utf-8") as handle:
        json.dump(index, handle, indent=2, ensure_ascii=False)
    ensure_private_permissions(LIBRARY_INDEX_FILE)
    return index


def library_best_snippet(tokens: Dict[str, int], text: str, radius: int = 4) -> str:
    lines = str(text or "").replace("\x00", "").splitlines()
    best_line = 0
    best_score = -1
    for i, line in enumerate(lines):
        lower = line.lower()
        score = sum(1 + min(3, weight) for token, weight in tokens.items() if token in lower)
        if score > best_score:
            best_score = score
            best_line = i
    start = max(0, best_line - radius)
    end = min(len(lines), best_line + radius + 1)
    return "\n".join(f"{i + 1:>5} | {lines[i]}" for i in range(start, end))


def library_search(query: str, limit: int = 8) -> List[dict]:
    tokens = library_tokenize(query)
    if not tokens:
        return []
    index = library_build_index(force=False)
    hits: List[dict] = []
    for item in list(index.get("items") or []):
        path = pathlib.Path(str(item.get("path") or ""))
        if not path.exists() or not is_text_file(path):
            continue
        try:
            text = path.read_text(encoding="utf-8", errors="replace").replace("\x00", "")
        except Exception:
            continue
        hay = (str(path) + "\n" + str(item.get("outline") or "") + "\n" + text[:260000]).lower()
        score = 0
        for token, weight in tokens.items():
            hits_for_token = hay.count(token.lower())
            if hits_for_token:
                score += min(12, hits_for_token) * (1 + min(3, weight))
        if score <= 0:
            continue
        found = dict(item)
        found["score"] = score
        found["snippet"] = library_best_snippet(tokens, text)
        hits.append(found)
    hits.sort(key=lambda item: (-int(item.get("score") or 0), str(item.get("path") or "")))
    return hits[: max(1, min(20, int(limit or 8)))]


def library_context_pack(query: str, max_chars: int = MAX_LIBRARY_CONTEXT_CHARS) -> str:
    hits = library_search(query, limit=8)
    if not hits:
        return ""
    out = [
        "[ZERO LIBRARY CONTEXT]",
        "Deterministic local search results. Search/indexing used no model tokens. Use only these snippets unless the operator attaches more files.",
    ]
    for idx, hit in enumerate(hits, start=1):
        out.append("")
        out.append(f"[LIBRARY HIT {idx}] {hit.get('path')}")
        out.append(f"score={hit.get('score')} lines={hit.get('lines')} sha256={str(hit.get('sha256') or '')[:16]}")
        outline = str(hit.get("outline") or "").strip()
        if outline:
            out.append("Outline:")
            out.append(outline[:1600])
        out.append("Snippet:")
        out.append(str(hit.get("snippet") or ""))
        if len("\n".join(out)) >= max_chars:
            out.append("[ZERO LIBRARY CONTEXT TRUNCATED]")
            break
    return "\n".join(out)[: max(2000, int(max_chars))]


def should_use_local_library(prompt: str) -> bool:
    text = str(prompt or "").lower()
    return bool(
        "@library" in text
        or "zero library" in text
        or "use library" in text
        or "my library" in text
        or "knowledge base" in text
        or re.search(r"\b(dna|rna|genome|sequence|codon|orf|fasta|fastq)\b", text)
    )


def library_status() -> None:
    index = library_build_index(force=False)
    print("Zero Library:")
    print(f"  indexed files: {index.get('count', 0)}")
    print(f"  index file: {LIBRARY_INDEX_FILE}")
    print("  registered paths:")
    for path in library_paths_from_config():
        marker = "ok" if path.exists() else "missing"
        print(f"    - [{marker}] {path}")
    if not library_paths_from_config():
        print("    (none yet; run `zerothink library add .`)")


def library_add_path(raw_path: str, current_dir: Optional[pathlib.Path] = None) -> None:
    target = resolve_cli_path(raw_path or ".", current_dir)
    if not target.exists():
        raise RuntimeError(f"Library path does not exist: {target}")
    paths = library_paths_from_config()
    if str(target).lower() not in {str(path).lower() for path in paths}:
        paths.append(target)
        save_library_paths(paths)
    index = library_build_index(force=True)
    print(f"Added library path: {target}")
    print(f"Indexed files: {index.get('count', 0)}")


def library_remove_path(raw_path: str, current_dir: Optional[pathlib.Path] = None) -> None:
    target = resolve_cli_path(raw_path or ".", current_dir)
    paths = [path for path in library_paths_from_config() if str(path).lower() != str(target).lower()]
    save_library_paths(paths)
    index = library_build_index(force=True)
    print(f"Removed library path: {target}")
    print(f"Indexed files: {index.get('count', 0)}")


def library_print_files() -> None:
    index = library_build_index(force=False)
    for item in list(index.get("items") or []):
        print(f"- {item.get('path')} ({format_bytes(int(item.get('size') or 0))}, {item.get('lines')} lines)")


def library_print_search(query: str) -> None:
    hits = library_search(query, limit=10)
    if not hits:
        print("No matching Zero Library snippets.")
        return
    for idx, hit in enumerate(hits, start=1):
        print(f"{idx}. {hit.get('path')}  score={hit.get('score')}  lines={hit.get('lines')}")
        print(textwrap.indent(str(hit.get("snippet") or "").strip(), "   "))
        print("")


DNA_CODON_TABLE = {
    "TTT": "F", "TTC": "F", "TTA": "L", "TTG": "L", "TCT": "S", "TCC": "S", "TCA": "S", "TCG": "S",
    "TAT": "Y", "TAC": "Y", "TAA": "*", "TAG": "*", "TGT": "C", "TGC": "C", "TGA": "*", "TGG": "W",
    "CTT": "L", "CTC": "L", "CTA": "L", "CTG": "L", "CCT": "P", "CCC": "P", "CCA": "P", "CCG": "P",
    "CAT": "H", "CAC": "H", "CAA": "Q", "CAG": "Q", "CGT": "R", "CGC": "R", "CGA": "R", "CGG": "R",
    "ATT": "I", "ATC": "I", "ATA": "I", "ATG": "M", "ACT": "T", "ACC": "T", "ACA": "T", "ACG": "T",
    "AAT": "N", "AAC": "N", "AAA": "K", "AAG": "K", "AGT": "S", "AGC": "S", "AGA": "R", "AGG": "R",
    "GTT": "V", "GTC": "V", "GTA": "V", "GTG": "V", "GCT": "A", "GCC": "A", "GCA": "A", "GCG": "A",
    "GAT": "D", "GAC": "D", "GAA": "E", "GAG": "E", "GGT": "G", "GGC": "G", "GGA": "G", "GGG": "G",
}


def dna_clean(sequence: str) -> str:
    return re.sub(r"[^ACGTUN]", "", str(sequence or "").upper()).replace("U", "T")


def dna_extract_sequence(text: str) -> str:
    path = pathlib.Path(str(text or "")).expanduser()
    if path.exists() and path.is_file() and is_text_file(path):
        text = path.read_text(encoding="utf-8", errors="replace")
    candidates = re.findall(r"[ACGTUNacgtun\s\r\n]{8,}", str(text or ""))
    best = ""
    for candidate in candidates:
        cleaned = dna_clean(candidate)
        if len(cleaned) > len(best):
            best = cleaned
    return best or dna_clean(str(text or ""))


def dna_reverse_complement(seq: str) -> str:
    return seq.translate(str.maketrans("ACGTN", "TGCAN"))[::-1]


def dna_translate(seq: str, frame: int = 0) -> str:
    aa = []
    for i in range(frame, len(seq) - 2, 3):
        aa.append(DNA_CODON_TABLE.get(seq[i : i + 3], "X"))
        if len(aa) >= 300:
            aa.append("...")
            break
    return "".join(aa)


def dna_orfs(seq: str) -> List[dict]:
    orfs: List[dict] = []
    stops = {"TAA", "TAG", "TGA"}
    for frame in range(3):
        for i in range(frame, len(seq) - 2, 3):
            if seq[i : i + 3] != "ATG":
                continue
            for j in range(i + 3, len(seq) - 2, 3):
                if seq[j : j + 3] in stops:
                    orfs.append({"frame": frame + 1, "start": i + 1, "end": j + 3, "codons": (j + 3 - i) // 3})
                    break
    return sorted(orfs, key=lambda item: (-int(item["codons"]), int(item["start"])))[:12]


def dna_analyze(raw: str) -> dict:
    seq = dna_extract_sequence(raw)
    if len(seq) < 8:
        raise RuntimeError("Paste a DNA/RNA sequence of at least 8 bases, or pass a FASTA/text file.")
    counts = {base: seq.count(base) for base in "ACGTN"}
    length = len(seq)
    return {
        "length": length,
        "counts": counts,
        "gc_percent": round(((counts["G"] + counts["C"]) / length) * 100, 2),
        "at_percent": round(((counts["A"] + counts["T"]) / length) * 100, 2),
        "motifs": {
            "start_ATG": seq.count("ATG"),
            "stop_TAA": seq.count("TAA"),
            "stop_TAG": seq.count("TAG"),
            "stop_TGA": seq.count("TGA"),
            "cpg": seq.count("CG"),
            "poly_A_6": len(re.findall(r"A{6,}", seq)),
            "poly_T_6": len(re.findall(r"T{6,}", seq)),
        },
        "orfs": dna_orfs(seq),
        "frames": {"+1": dna_translate(seq, 0), "+2": dna_translate(seq, 1), "+3": dna_translate(seq, 2)},
        "reverse_complement_preview": dna_reverse_complement(seq)[:240] + ("..." if len(seq) > 240 else ""),
        "sequence_preview": seq[:240] + ("..." if len(seq) > 240 else ""),
        "disclaimer": "Research utility only. This is not medical, diagnostic, ancestry, or clinical interpretation.",
    }


def dna_markdown(analysis: dict) -> str:
    lines = [
        "Zero DNA Analysis",
        "",
        f"Research boundary: {analysis['disclaimer']}",
        "",
        f"- Length: {analysis['length']:,} bases",
        f"- GC: {analysis['gc_percent']}%",
        f"- AT: {analysis['at_percent']}%",
        "- Counts: " + ", ".join(f"{base}={analysis['counts'][base]}" for base in "ACGTN"),
        "",
        "Motifs:",
    ]
    for name, count in analysis["motifs"].items():
        lines.append(f"- {name}: {count}")
    lines.append("")
    lines.append("Longest ORFs:")
    if analysis["orfs"]:
        for orf in analysis["orfs"]:
            lines.append(f"- Frame +{orf['frame']}: bases {orf['start']}-{orf['end']} ({orf['codons']} codons)")
    else:
        lines.append("- No complete ATG...stop ORF found in the forward frames.")
    lines.append("")
    lines.append("Translation preview:")
    for frame, aa in analysis["frames"].items():
        lines.append(f"- Frame {frame}: {aa}")
    lines.append("")
    lines.append(f"Reverse complement preview: {analysis['reverse_complement_preview']}")
    return "\n".join(lines)


def resolve_cli_path(value: Optional[str], cwd: Optional[pathlib.Path] = None) -> pathlib.Path:
    base = cwd or pathlib.Path.cwd()
    if not value:
        return base.resolve()
    path = pathlib.Path(value).expanduser()
    if not path.is_absolute():
        path = (base / path)
    return path.resolve()


def format_bytes(size: int) -> str:
    units = ["B", "KB", "MB", "GB", "TB"]
    value = float(size)
    unit = units[0]
    for unit in units:
        if value < 1024 or unit == units[-1]:
            break
        value /= 1024.0
    if unit == "B":
        return f"{int(value)} {unit}"
    return f"{value:.1f} {unit}"


def should_skip_operator_path(path: pathlib.Path, root: Optional[pathlib.Path] = None) -> bool:
    try:
        parts = path.relative_to(root).parts if root else path.parts[-1:]
    except Exception:
        parts = path.parts[-1:]
    return any(str(part).lower() in OPERATOR_SKIP_DIRS for part in parts)


def print_path_listing(path: pathlib.Path, show_all: bool = False) -> None:
    path = path.resolve()
    if not path.exists():
        raise RuntimeError(f"Path not found: {path}")
    if path.is_file():
        stat = path.stat()
        print(f"FILE  {format_bytes(stat.st_size):>8}  {path.name}")
        return

    entries = sorted(
        path.iterdir(),
        key=lambda item: (not item.is_dir(), item.name.lower()),
    )
    count = 0
    for entry in entries:
        if not show_all and entry.name.startswith("."):
            continue
        try:
            size = "-" if entry.is_dir() else format_bytes(entry.stat().st_size)
        except OSError:
            size = "?"
        kind = "DIR " if entry.is_dir() else "FILE"
        print(f"{kind} {size:>8}  {entry.name}")
        count += 1
    if count == 0:
        print("(empty)")


def print_tree(path: pathlib.Path, max_depth: int = 3, show_all: bool = False, limit: int = 300) -> None:
    path = path.resolve()
    if not path.exists():
        raise RuntimeError(f"Path not found: {path}")
    print(path)
    emitted = 0

    def walk(node: pathlib.Path, prefix: str, depth: int) -> None:
        nonlocal emitted
        if depth > max_depth or emitted >= limit or not node.is_dir():
            return
        try:
            entries = sorted(node.iterdir(), key=lambda item: (not item.is_dir(), item.name.lower()))
        except OSError:
            return
        if not show_all:
            entries = [
                item for item in entries
                if not item.name.startswith(".") and not should_skip_operator_path(item, path)
            ]
        for index, entry in enumerate(entries):
            if emitted >= limit:
                break
            branch = "└── " if index == len(entries) - 1 else "├── "
            label = entry.name + ("/" if entry.is_dir() else "")
            print(prefix + branch + label)
            emitted += 1
            extension = "    " if index == len(entries) - 1 else "│   "
            if entry.is_dir():
                walk(entry, prefix + extension, depth + 1)

    walk(path, "", 1)
    if emitted >= limit:
        print("...tree truncated...")


def read_text_preview(path: pathlib.Path, start: int = 1, lines: int = MAX_READ_LINES) -> None:
    path = path.resolve()
    if not path.exists() or not path.is_file():
        raise RuntimeError(f"File not found: {path}")
    raw = path.read_text(encoding="utf-8", errors="replace").replace("\x00", "")
    all_lines = raw.splitlines()
    start = max(1, int(start))
    lines = max(1, min(MAX_READ_LINES, int(lines)))
    begin = start - 1
    end = min(len(all_lines), begin + lines)
    if begin >= len(all_lines):
        raise RuntimeError(f"Start line {start} is beyond end of file ({len(all_lines)} lines).")
    for idx in range(begin, end):
        print(f"{idx + 1:>5} | {all_lines[idx]}")
    if end < len(all_lines):
        print(f"...truncated after line {end} of {len(all_lines)}...")


def search_workspace(pattern: str, root: pathlib.Path, ignore_case: bool = False) -> None:
    root = root.resolve()
    if not root.exists():
        raise RuntimeError(f"Path not found: {root}")
    flags = re.IGNORECASE if ignore_case else 0
    regex = re.compile(pattern, flags)
    hits = 0
    if root.is_file():
        files = [root]
    else:
        files = []
        for dirpath, dirnames, filenames in os.walk(root):
            current = pathlib.Path(dirpath)
            dirnames[:] = [
                name for name in dirnames
                if not name.startswith(".") and name.lower() not in OPERATOR_SKIP_DIRS
            ]
            if should_skip_operator_path(current, root):
                continue
            files.extend(current / name for name in filenames)
    for file in files:
        if file.suffix.lower() in OPERATOR_SKIP_FILE_SUFFIXES:
            continue
        try:
            if file.stat().st_size > MAX_SEARCH_FILE_BYTES:
                continue
            text = file.read_text(encoding="utf-8", errors="replace")
        except Exception:
            continue
        for number, line in enumerate(text.splitlines(), start=1):
            if regex.search(line):
                rel = file.relative_to(root) if root.is_dir() else file.name
                print(f"{rel}:{number}: {line.strip()}")
                hits += 1
                if hits >= MAX_SEARCH_HITS:
                    print("...search truncated...")
                    return
    if hits == 0:
        print("No matches.")


def print_workspace_scan(path: pathlib.Path) -> None:
    path = path.resolve()
    if not path.exists():
        raise RuntimeError(f"Path not found: {path}")
    if path.is_file():
        print(f"File: {path.name}")
        print(f"Size: {format_bytes(path.stat().st_size)}")
        print(f"Type: {'text' if is_text_file(path) else 'binary/other'}")
        return

    dirs = 0
    files = 0
    total_size = 0
    ext_counts: Dict[str, int] = {}
    samples: List[str] = []
    for item in path.rglob("*"):
        try:
            if should_skip_operator_path(item, path):
                continue
            if item.is_dir():
                dirs += 1
                continue
            if item.is_file():
                files += 1
                total_size += item.stat().st_size
                ext = item.suffix.lower() or "[no ext]"
                ext_counts[ext] = ext_counts.get(ext, 0) + 1
                if len(samples) < 18:
                    samples.append(item.relative_to(path).as_posix())
                if files >= MAX_SCAN_FILES:
                    break
        except OSError:
            continue

    print(f"Workspace: {path}")
    print(f"Directories: {dirs:,}")
    print(f"Files scanned: {files:,}")
    print(f"Approx size: {format_bytes(total_size)}")
    git_dir = path / ".git"
    if git_dir.exists():
        print("Git: yes")
    if ext_counts:
        print("\nTop file types:")
        for ext, count in sorted(ext_counts.items(), key=lambda item: (-item[1], item[0]))[:12]:
            print(f"  {ext:<12} {count:>6}")
    if samples:
        print("\nSample paths:")
        for sample in samples:
            print(f"  - {sample}")
    if files >= MAX_SCAN_FILES:
        print("\n...scan truncated for speed...")


def run_local_command(command: str, cwd: Optional[pathlib.Path] = None, timeout: int = 120) -> int:
    cwd = (cwd or pathlib.Path.cwd()).resolve()
    timeout = max(5, min(900, int(timeout)))
    if os.name == "nt":
        argv = ["powershell", "-NoProfile", "-Command", command]
    else:
        shell = shutil.which("bash") or shutil.which("sh")
        if not shell:
            raise RuntimeError("No shell found to run commands.")
        argv = [shell, "-lc", command]
    completed = subprocess.run(
        argv,
        cwd=str(cwd),
        capture_output=True,
        text=True,
        timeout=timeout,
        errors="replace",
    )
    stdout = completed.stdout[-MAX_EXEC_OUTPUT:]
    stderr = completed.stderr[-MAX_EXEC_OUTPUT:]
    if stdout.strip():
        print(stdout.rstrip())
    if stderr.strip():
        print(stderr.rstrip(), file=sys.stderr)
    print(f"\n[exit {completed.returncode}]")
    return completed.returncode


def require_program(name: str) -> str:
    path = shutil.which(name)
    if not path:
        raise RuntimeError(f"Required program was not found on PATH: {name}")
    return path


def get_server_profile(config: dict, name: str) -> dict:
    profiles = get_server_profiles(config)
    profile = profiles.get(name)
    if not profile:
        raise RuntimeError(f"Unknown server profile: {name}")
    if not profile.get("host"):
        raise RuntimeError(f"Server profile `{name}` is missing a host.")
    return profile


def resolve_server_password(profile: dict) -> str:
    env_name = str(profile.get("password_env") or "").strip()
    if env_name:
        env_value = str(os.getenv(env_name) or "").strip()
        if env_value:
            return env_value
    return str(profile.get("password") or "").strip()


def server_auth_label(profile: dict) -> str:
    identity = str(profile.get("identity") or "").strip()
    password = resolve_server_password(profile)
    if identity and password:
        return "key+password"
    if identity:
        return "key"
    if password:
        return "password"
    return "agent/default"


def build_ssh_target(profile: dict) -> str:
    user = str(profile.get("user") or "").strip()
    host = str(profile.get("host") or "").strip()
    return f"{user}@{host}" if user else host


def build_ssh_argv(profile: dict, program: str = "ssh") -> List[str]:
    argv = [require_program(program)]
    port = int(profile.get("port") or 22)
    identity = str(profile.get("identity") or "").strip()
    if port and port != 22:
        argv.extend(["-p", str(port)])
    if identity:
        argv.extend(["-i", identity])
    argv.append(build_ssh_target(profile))
    return argv


def should_use_paramiko(profile: dict, program: str) -> bool:
    return bool(resolve_server_password(profile)) or not bool(shutil.which(program))


def connect_paramiko(profile: dict, timeout: int = 30):
    if not PARAMIKO_AVAILABLE:
        raise RuntimeError("Paramiko is not available on this machine, so password-based remote sessions are unavailable.")
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    kwargs = {
        "hostname": str(profile.get("host") or "").strip(),
        "port": int(profile.get("port") or 22),
        "username": str(profile.get("user") or "").strip() or None,
        "timeout": timeout,
        "banner_timeout": timeout,
        "auth_timeout": timeout,
        "look_for_keys": True,
        "allow_agent": True,
    }
    identity = str(profile.get("identity") or "").strip()
    if identity:
        kwargs["key_filename"] = identity
    password = resolve_server_password(profile)
    if password:
        kwargs["password"] = password
    client.connect(**kwargs)
    return client


def normalize_remote_posix(path: str) -> str:
    raw = str(path or "").replace("\\", "/")
    is_absolute = raw.startswith("/")
    parts: List[str] = []
    for part in raw.split("/"):
        if part in {"", "."}:
            continue
        if part == "..":
            if parts and parts[-1] != "..":
                parts.pop()
            elif not is_absolute:
                parts.append(part)
            continue
        parts.append(part)
    if is_absolute:
        return "/" + "/".join(parts) if parts else "/"
    return "/".join(parts) if parts else "."


def resolve_remote_path(profile: dict, value: Optional[str] = None) -> str:
    raw = str(value or ".").strip() or "."
    candidate = pathlib.PurePosixPath(raw.replace("\\", "/"))
    if candidate.is_absolute():
        return normalize_remote_posix(str(candidate))
    base = str(profile.get("cwd") or "").strip() or "."
    joined = str(pathlib.PurePosixPath(base) / candidate)
    return normalize_remote_posix(joined)


def remote_parent_path(path: str) -> str:
    pure = pathlib.PurePosixPath(path)
    parent = str(pure.parent)
    return normalize_remote_posix(parent or ".")


def remote_basename(path: str) -> str:
    return pathlib.PurePosixPath(path).name


def remote_is_dir(sftp, path: str) -> bool:
    try:
        return stat.S_ISDIR(sftp.stat(path).st_mode)
    except Exception:
        return False


def remote_is_file(sftp, path: str) -> bool:
    try:
        return stat.S_ISREG(sftp.stat(path).st_mode)
    except Exception:
        return False


def ensure_remote_dir(sftp, path: str) -> None:
    target = normalize_remote_posix(path)
    if target in {".", "/"}:
        return
    pending: List[str] = []
    probe = target
    while probe not in {".", "/"}:
        try:
            if remote_is_dir(sftp, probe):
                break
        except Exception:
            pass
        pending.append(probe)
        probe = remote_parent_path(probe)
    for item in reversed(pending):
        try:
            sftp.mkdir(item)
        except IOError:
            if not remote_is_dir(sftp, item):
                raise


def open_remote_session(profile: dict, timeout: int = 30):
    client = connect_paramiko(profile, timeout=timeout)
    sftp = client.open_sftp()
    return client, sftp


def run_remote_command(profile: dict, command: str, timeout: int = 180) -> int:
    timeout = max(5, min(3600, int(timeout)))
    remote_command = command.strip()
    remote_cwd = str(profile.get("cwd") or "").strip()
    if remote_cwd:
        remote_command = f"cd {shlex.quote(remote_cwd)} && {remote_command}"
    if should_use_paramiko(profile, "ssh"):
        client = connect_paramiko(profile, timeout=min(timeout, 60))
        try:
            stdin, stdout, stderr = client.exec_command(remote_command, timeout=timeout)
            out = stdout.read().decode("utf-8", errors="replace")
            err = stderr.read().decode("utf-8", errors="replace")
            code = stdout.channel.recv_exit_status()
            if out.strip():
                print(out.rstrip())
            if err.strip():
                print(err.rstrip(), file=sys.stderr)
            print(f"\n[exit {code}]")
            return int(code)
        finally:
            client.close()
    argv = build_ssh_argv(profile, "ssh") + [remote_command]
    completed = subprocess.run(
        argv,
        capture_output=True,
        text=True,
        timeout=timeout,
        errors="replace",
    )
    if completed.stdout.strip():
        print(completed.stdout.rstrip())
    if completed.stderr.strip():
        print(completed.stderr.rstrip(), file=sys.stderr)
    print(f"\n[exit {completed.returncode}]")
    return completed.returncode


def scp_transfer(profile: dict, source: str, target: str, download: bool = False, timeout: int = 1800) -> int:
    timeout = max(10, min(7200, int(timeout)))
    if should_use_paramiko(profile, "scp"):
        client, sftp = open_remote_session(profile, timeout=min(timeout, 60))
        try:
            if download:
                local_target = pathlib.Path(target).expanduser().resolve()
                local_target.parent.mkdir(parents=True, exist_ok=True)
                sftp.get(source, str(local_target))
                print(f"Downloaded {source} -> {local_target}")
            else:
                local_source = pathlib.Path(source).expanduser().resolve()
                if not local_source.exists() or not local_source.is_file():
                    raise RuntimeError(f"Local file not found: {local_source}")
                remote_target = resolve_remote_path(profile, target)
                ensure_remote_dir(sftp, remote_parent_path(remote_target))
                sftp.put(str(local_source), remote_target)
                print(f"Uploaded {local_source} -> {remote_target}")
            print("\n[exit 0]")
            return 0
        finally:
            sftp.close()
            client.close()
    argv = [require_program("scp")]
    port = int(profile.get("port") or 22)
    identity = str(profile.get("identity") or "").strip()
    if port and port != 22:
        argv.extend(["-P", str(port)])
    if identity:
        argv.extend(["-i", identity])
    remote_target = build_ssh_target(profile)
    if download:
        argv.extend([f"{remote_target}:{source}", target])
    else:
        argv.extend([source, f"{remote_target}:{target}"])
    completed = subprocess.run(
        argv,
        capture_output=True,
        text=True,
        timeout=timeout,
        errors="replace",
    )
    if completed.stdout.strip():
        print(completed.stdout.rstrip())
    if completed.stderr.strip():
        print(completed.stderr.rstrip(), file=sys.stderr)
    print(f"\n[exit {completed.returncode}]")
    return completed.returncode


def print_server_profiles(config: dict) -> None:
    profiles = get_server_profiles(config)
    if not profiles:
        print("No saved server profiles.")
        return
    for name in sorted(profiles.keys()):
        profile = profiles[name]
        user = str(profile.get("user") or "").strip()
        host = str(profile.get("host") or "").strip()
        port = int(profile.get("port") or 22)
        cwd = str(profile.get("cwd") or "").strip() or "-"
        identity = str(profile.get("identity") or "").strip() or "-"
        password_env = str(profile.get("password_env") or "").strip() or "-"
        target = f"{user}@{host}" if user else host
        auth = server_auth_label(profile)
        print(f"{name:<18} {target:<32} port={port:<5} auth={auth:<14} cwd={cwd}")
        if identity != "-":
            print(f"{'':<18} identity={identity}")
        if password_env != "-":
            print(f"{'':<18} password_env={password_env}")


def list_remote_dir(sftp, path: str):
    return sorted(sftp.listdir_attr(path), key=lambda item: (not stat.S_ISDIR(item.st_mode), item.filename.lower()))


def remote_ls(profile: dict, path: str = ".") -> None:
    client, sftp = open_remote_session(profile)
    try:
        target = resolve_remote_path(profile, path)
        if remote_is_file(sftp, target):
            stat_info = sftp.stat(target)
            print(f"FILE  {format_bytes(int(stat_info.st_size)):>8}  {remote_basename(target)}")
            return
        entries = list_remote_dir(sftp, target)
        if not entries:
            print("(empty)")
            return
        for entry in entries:
            kind = "DIR " if stat.S_ISDIR(entry.st_mode) else "FILE"
            size = "-" if stat.S_ISDIR(entry.st_mode) else format_bytes(int(entry.st_size))
            print(f"{kind} {size:>8}  {entry.filename}")
    finally:
        sftp.close()
        client.close()


def remote_tree(profile: dict, path: str = ".", max_depth: int = 3, limit: int = 300) -> None:
    client, sftp = open_remote_session(profile)
    try:
        target = resolve_remote_path(profile, path)
        print(target)
        emitted = 0

        def walk(node: str, prefix: str, depth: int) -> None:
            nonlocal emitted
            if depth > max_depth or emitted >= limit or not remote_is_dir(sftp, node):
                return
            entries = list_remote_dir(sftp, node)
            entries = [entry for entry in entries if entry.filename.lower() not in OPERATOR_SKIP_DIRS and not entry.filename.startswith(".")]
            for index, entry in enumerate(entries):
                if emitted >= limit:
                    break
                branch = "└── " if index == len(entries) - 1 else "├── "
                is_dir = stat.S_ISDIR(entry.st_mode)
                label = entry.filename + ("/" if is_dir else "")
                print(prefix + branch + label)
                emitted += 1
                extension = "    " if index == len(entries) - 1 else "│   "
                if is_dir:
                    walk(normalize_remote_posix(f"{node}/{entry.filename}"), prefix + extension, depth + 1)

        walk(target, "", 1)
        if emitted >= limit:
            print("...tree truncated...")
    finally:
        sftp.close()
        client.close()


def remote_read_text(profile: dict, path: str, start: int = 1, lines: int = MAX_READ_LINES) -> None:
    client, sftp = open_remote_session(profile)
    try:
        target = resolve_remote_path(profile, path)
        if not remote_is_file(sftp, target):
            raise RuntimeError(f"Remote file not found: {target}")
        with sftp.open(target, "rb") as handle:
            raw = handle.read().decode("utf-8", errors="replace").replace("\x00", "")
        all_lines = raw.splitlines()
        start = max(1, int(start))
        lines = max(1, min(MAX_READ_LINES, int(lines)))
        begin = start - 1
        end = min(len(all_lines), begin + lines)
        if begin >= len(all_lines):
            raise RuntimeError(f"Start line {start} is beyond end of file ({len(all_lines)} lines).")
        for idx in range(begin, end):
            print(f"{idx + 1:>5} | {all_lines[idx]}")
        if end < len(all_lines):
            print(f"...truncated after line {end} of {len(all_lines)}...")
    finally:
        sftp.close()
        client.close()


def remote_write_text(profile: dict, path: str, text: str, append: bool = False) -> None:
    client, sftp = open_remote_session(profile)
    try:
        target = resolve_remote_path(profile, path)
        ensure_remote_dir(sftp, remote_parent_path(target))
        mode = "ab" if append else "wb"
        with sftp.open(target, mode) as handle:
            handle.write(text.encode("utf-8"))
        print(f"{'Appended' if append else 'Wrote'} {target}")
    finally:
        sftp.close()
        client.close()


def remote_replace_text(profile: dict, path: str, old: str, new: str, replace_all: bool = False) -> None:
    client, sftp = open_remote_session(profile)
    try:
        target = resolve_remote_path(profile, path)
        if not remote_is_file(sftp, target):
            raise RuntimeError(f"Remote file not found: {target}")
        with sftp.open(target, "rb") as handle:
            text = handle.read().decode("utf-8", errors="replace")
        count = text.count(old)
        if count == 0:
            raise RuntimeError("Pattern not found.")
        limit = count if replace_all else 1
        updated = text.replace(old, new, limit)
        with sftp.open(target, "wb") as handle:
            handle.write(updated.encode("utf-8"))
        print(f"Updated {target} ({'all' if replace_all else 'first'} match).")
    finally:
        sftp.close()
        client.close()


def remote_mkdir(profile: dict, path: str) -> None:
    client, sftp = open_remote_session(profile)
    try:
        target = resolve_remote_path(profile, path)
        ensure_remote_dir(sftp, target)
        print(f"Created {target}")
    finally:
        sftp.close()
        client.close()


def remote_rm(profile: dict, path: str, recursive: bool = False) -> None:
    client, sftp = open_remote_session(profile)
    try:
        target = resolve_remote_path(profile, path)
        if remote_is_dir(sftp, target):
            if not recursive:
                raise RuntimeError("Refusing to remove a remote directory without --recursive.")
            stack = [target]
            order: List[str] = []
            while stack:
                node = stack.pop()
                order.append(node)
                for entry in list_remote_dir(sftp, node):
                    child = normalize_remote_posix(f"{node}/{entry.filename}")
                    if stat.S_ISDIR(entry.st_mode):
                        stack.append(child)
                    else:
                        sftp.remove(child)
            for node in reversed(order):
                sftp.rmdir(node)
        elif remote_is_file(sftp, target):
            sftp.remove(target)
        else:
            raise RuntimeError(f"Remote path not found: {target}")
        print(f"Removed {target}")
    finally:
        sftp.close()
        client.close()


def remote_search(profile: dict, pattern: str, path: str = ".", ignore_case: bool = False) -> None:
    client, sftp = open_remote_session(profile)
    try:
        root = resolve_remote_path(profile, path)
        flags = re.IGNORECASE if ignore_case else 0
        regex = re.compile(pattern, flags)
        hits = 0
        queue = [root]
        while queue:
            node = queue.pop(0)
            if remote_is_dir(sftp, node):
                for entry in list_remote_dir(sftp, node):
                    if entry.filename.startswith(".") or entry.filename.lower() in OPERATOR_SKIP_DIRS:
                        continue
                    queue.append(normalize_remote_posix(f"{node}/{entry.filename}"))
                continue
            if not remote_is_file(sftp, node):
                continue
            suffix = pathlib.PurePosixPath(node).suffix.lower()
            if suffix in OPERATOR_SKIP_FILE_SUFFIXES:
                continue
            try:
                with sftp.open(node, "rb") as handle:
                    text = handle.read(512000).decode("utf-8", errors="replace")
            except Exception:
                continue
            for number, line in enumerate(text.splitlines(), start=1):
                if regex.search(line):
                    rel = node[len(root):].lstrip("/") if root != node and node.startswith(root) else remote_basename(node)
                    print(f"{rel}:{number}: {line.strip()}")
                    hits += 1
                    if hits >= MAX_SEARCH_HITS:
                        print("...search truncated...")
                        return
        if hits == 0:
            print("No matches.")
    finally:
        sftp.close()
        client.close()


def remote_scan(profile: dict, path: str = ".") -> None:
    client, sftp = open_remote_session(profile)
    try:
        root = resolve_remote_path(profile, path)
        if remote_is_file(sftp, root):
            info = sftp.stat(root)
            print(f"File: {remote_basename(root)}")
            print(f"Size: {format_bytes(int(info.st_size))}")
            return
        dirs = 0
        files = 0
        total_size = 0
        ext_counts: Dict[str, int] = {}
        samples: List[str] = []
        queue = [root]
        while queue and files < MAX_SCAN_FILES:
            node = queue.pop(0)
            if not remote_is_dir(sftp, node):
                continue
            dirs += 1
            try:
                entries = list_remote_dir(sftp, node)
            except Exception:
                continue
            for entry in entries:
                if entry.filename.startswith(".") or entry.filename.lower() in OPERATOR_SKIP_DIRS:
                    continue
                child = normalize_remote_posix(f"{node}/{entry.filename}")
                if stat.S_ISDIR(entry.st_mode):
                    queue.append(child)
                    continue
                files += 1
                total_size += int(entry.st_size)
                ext = pathlib.PurePosixPath(entry.filename).suffix.lower() or "[no ext]"
                ext_counts[ext] = ext_counts.get(ext, 0) + 1
                if len(samples) < 18:
                    rel = child[len(root):].lstrip("/") if child.startswith(root) else child
                    samples.append(rel)
                if files >= MAX_SCAN_FILES:
                    break
        print(f"Remote workspace: {root}")
        print(f"Directories: {max(0, dirs - 1):,}")
        print(f"Files scanned: {files:,}")
        print(f"Approx size: {format_bytes(total_size)}")
        if ext_counts:
            print("\nTop file types:")
            for ext, count in sorted(ext_counts.items(), key=lambda item: (-item[1], item[0]))[:12]:
                print(f"  {ext:<12} {count:>6}")
        if samples:
            print("\nSample paths:")
            for sample in samples:
                print(f"  - {sample}")
        if files >= MAX_SCAN_FILES:
            print("\n...scan truncated for speed...")
    finally:
        sftp.close()
        client.close()


def remote_health(profile: dict, path: str = ".") -> int:
    target = resolve_remote_path(profile, path)
    quoted = shlex.quote(target)
    command = (
        "echo '== identity ==' ; "
        "hostname 2>/dev/null || true ; "
        "whoami 2>/dev/null || true ; "
        "pwd ; "
        "(date -Is 2>/dev/null || date) ; "
        "echo ; echo '== uptime ==' ; "
        "uptime 2>/dev/null || true ; "
        "echo ; echo '== disk ==' ; "
        "df -h . 2>/dev/null || df -h 2>/dev/null || true ; "
        "echo ; echo '== memory ==' ; "
        "free -h 2>/dev/null || vm_stat 2>/dev/null || true ; "
        "echo ; echo '== target path ==' ; "
        f"if cd {quoted} 2>/dev/null; then pwd; ls -la | sed -n '1,80p'; "
        "echo ; echo '== recent files ==' ; "
        "find . -maxdepth 2 \\( -name .git -o -name node_modules -o -name vendor -o -name backups -o -name cache -o -name tmp \\) -prune -o -type f -printf '%TY-%Tm-%Td %TH:%TM %p\\n' 2>/dev/null | sort -r | sed -n '1,40p' || true ; "
        "echo ; echo '== git status ==' ; "
        "git status --short --branch 2>/dev/null || true ; "
        f"else echo 'Path unavailable: {target}'; fi ; "
        "echo ; echo '== failed services ==' ; "
        "if command -v systemctl >/dev/null 2>&1; then systemctl --failed --no-pager 2>/dev/null | sed -n '1,80p' || true; fi"
    )
    return run_remote_command(profile, command, timeout=240)


def write_text_file(path: pathlib.Path, text: str, append: bool = False) -> None:
    path = path.resolve()
    path.parent.mkdir(parents=True, exist_ok=True)
    mode = "a" if append else "w"
    with path.open(mode, encoding="utf-8", newline="") as handle:
        handle.write(text)
    print(f"{'Appended' if append else 'Wrote'} {path}")


def replace_in_file(path: pathlib.Path, old: str, new: str, replace_all: bool = False) -> None:
    path = path.resolve()
    text = path.read_text(encoding="utf-8", errors="replace")
    count = text.count(old)
    if count == 0:
        raise RuntimeError("Pattern not found.")
    limit = count if replace_all else 1
    updated = text.replace(old, new, limit)
    write_text_portable(path, updated)
    print(f"Updated {path} ({'all' if replace_all else 'first'} match).")


def _crypto_b64(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).decode("ascii").rstrip("=")


def _crypto_unb64(value: str) -> bytes:
    value = str(value or "").strip()
    value += "=" * (-len(value) % 4)
    return base64.urlsafe_b64decode(value.encode("ascii"))


def zpepper_hex_from_pattern(pattern: str) -> str:
    cleaned = re.sub(r"\s+", "", str(pattern or "")).upper()
    acc = 7045
    for ch in cleaned:
        acc = ((acc * 131) ^ ord(ch)) & 0xFFFFFFFF
    return f"{acc:08x}"


def zpepper_hint(pattern: str) -> str:
    pepper = zpepper_hex_from_pattern(pattern)
    return hashlib.sha256(pepper.encode("ascii")).hexdigest()[:16]


def crypto_status(config: Optional[dict] = None) -> None:
    config = config or load_config()
    pattern = str(config.get("crypto_zpepper_pattern") or "")
    print("ZeroThink Z-Pepper crypto:")
    print(f"  envelope: {CRYPTO_MAGIC.decode('ascii', errors='ignore').rstrip(chr(0))}")
    print(f"  default iterations: {CRYPTO_DEFAULT_ITERATIONS:,}")
    if pattern:
        print("  saved local zpepper: yes")
        print(f"  zpepper hint: {zpepper_hint(pattern)}")
    else:
        print("  saved local zpepper: no")
    print("  commands: zerothink crypto encrypt FILE | decrypt FILE.ztz | pack OUT.ztz PATH...")


def set_crypto_zpepper(pattern: str = "") -> None:
    if not pattern:
        pattern = getpass.getpass("Z-Pepper pattern (hidden, not your file passphrase): ")
    pattern = str(pattern or "")
    if not pattern.strip():
        raise RuntimeError("Z-Pepper pattern cannot be empty.")
    config = load_config()
    config["crypto_zpepper_pattern"] = pattern
    save_config(config)
    print("Saved local Z-Pepper pattern.")
    print(f"Hint: {zpepper_hint(pattern)}")


def clear_crypto_zpepper() -> None:
    config = load_config()
    config.pop("crypto_zpepper_pattern", None)
    save_config(config)
    print("Cleared saved local Z-Pepper pattern.")


def resolve_crypto_pattern(config: dict, pattern: str = "", zpepper_env: str = "") -> str:
    explicit = str(pattern or "")
    if explicit:
        return explicit
    env_name = str(zpepper_env or "").strip()
    if env_name:
        return os.getenv(env_name, "")
    return str(config.get("crypto_zpepper_pattern") or "")


def resolve_crypto_passphrase(passphrase: str = "", pass_env: str = "", confirm: bool = False) -> str:
    if passphrase:
        return str(passphrase)
    env_name = str(pass_env or "").strip()
    if env_name:
        value = os.getenv(env_name, "")
        if not value:
            raise RuntimeError(f"Environment variable is empty or missing: {env_name}")
        return value
    first = getpass.getpass("File passphrase: ")
    if confirm:
        second = getpass.getpass("Confirm passphrase: ")
        if first != second:
            raise RuntimeError("Passphrases did not match.")
    if not first:
        raise RuntimeError("Passphrase cannot be empty.")
    return first


def derive_crypto_keys(passphrase: str, pattern: str, salt: bytes, iterations: int) -> Tuple[bytes, bytes]:
    if not passphrase:
        raise RuntimeError("Passphrase cannot be empty.")
    iterations = max(CRYPTO_MIN_ITERATIONS, int(iterations or CRYPTO_DEFAULT_ITERATIONS))
    pepper = zpepper_hex_from_pattern(pattern)
    material = passphrase.encode("utf-8") + b"\0ZERO-ZPEPPER\0" + pepper.encode("ascii")
    derived = hashlib.pbkdf2_hmac("sha256", material, salt, iterations, dklen=64)
    return derived[:32], derived[32:]


def crypto_stream_xor(data: bytes, key: bytes, nonce: bytes) -> bytes:
    output = bytearray(len(data))
    offset = 0
    counter = 0
    while offset < len(data):
        block = hmac.new(key, nonce + counter.to_bytes(8, "big"), hashlib.sha256).digest()
        take = min(len(block), len(data) - offset)
        for i in range(take):
            output[offset + i] = data[offset + i] ^ block[i]
        offset += take
        counter += 1
    return bytes(output)


def crypto_header_bytes(header: dict) -> bytes:
    return json.dumps(header, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode("utf-8")


def crypto_encrypt_bytes(
    payload: bytes,
    passphrase: str,
    pattern: str,
    original_name: str,
    kind: str = "file",
    iterations: int = CRYPTO_DEFAULT_ITERATIONS,
    compress: bool = True,
) -> bytes:
    if not isinstance(payload, (bytes, bytearray)):
        raise RuntimeError("Crypto payload must be bytes.")
    iterations = max(CRYPTO_MIN_ITERATIONS, int(iterations or CRYPTO_DEFAULT_ITERATIONS))
    salt = secrets.token_bytes(16)
    nonce = secrets.token_bytes(16)
    plaintext = zlib.compress(bytes(payload), level=9) if compress else bytes(payload)
    enc_key, mac_key = derive_crypto_keys(passphrase, pattern, salt, iterations)
    cipher = crypto_stream_xor(plaintext, enc_key, nonce)
    header = {
        "format": "ZeroThink Z-Pepper Envelope",
        "version": 1,
        "kind": kind,
        "alg": "HMAC-SHA256-STREAM+HMAC-SHA256",
        "kdf": {"name": "PBKDF2-HMAC-SHA256", "iterations": iterations, "salt": _crypto_b64(salt)},
        "nonce": _crypto_b64(nonce),
        "compressed": bool(compress),
        "original_name": original_name,
        "plain_sha256": hashlib.sha256(bytes(payload)).hexdigest(),
        "zpepper_hint": zpepper_hint(pattern),
        "created_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
    }
    header_raw = crypto_header_bytes(header)
    tag = hmac.new(mac_key, CRYPTO_MAGIC + header_raw + cipher, hashlib.sha256).digest()
    return CRYPTO_MAGIC + len(header_raw).to_bytes(4, "big") + header_raw + cipher + tag


def crypto_parse_envelope(raw: bytes) -> Tuple[dict, bytes, bytes, bytes]:
    if not raw.startswith(CRYPTO_MAGIC):
        raise RuntimeError("Not a ZeroThink Z-Pepper encrypted file.")
    cursor = len(CRYPTO_MAGIC)
    if len(raw) < cursor + 4 + 32:
        raise RuntimeError("Encrypted file is truncated.")
    header_len = int.from_bytes(raw[cursor:cursor + 4], "big")
    cursor += 4
    if header_len <= 0 or len(raw) < cursor + header_len + 32:
        raise RuntimeError("Encrypted file header is invalid.")
    header_raw = raw[cursor:cursor + header_len]
    cursor += header_len
    body = raw[cursor:]
    cipher, tag = body[:-32], body[-32:]
    try:
        header = json.loads(header_raw.decode("utf-8"))
    except Exception as exc:
        raise RuntimeError(f"Encrypted file header could not be parsed: {exc}") from exc
    return header, header_raw, cipher, tag


def crypto_read_envelope(path: pathlib.Path) -> Tuple[dict, bytes, bytes, bytes]:
    return crypto_parse_envelope(path.read_bytes())


def crypto_decrypt_bytes(envelope: bytes, passphrase: str, pattern: str) -> Tuple[bytes, dict]:
    header, header_raw, cipher, tag = crypto_parse_envelope(envelope)
    kdf = dict(header.get("kdf") or {})
    salt = _crypto_unb64(str(kdf.get("salt") or ""))
    iterations = int(kdf.get("iterations") or CRYPTO_DEFAULT_ITERATIONS)
    nonce = _crypto_unb64(str(header.get("nonce") or ""))
    enc_key, mac_key = derive_crypto_keys(passphrase, pattern, salt, iterations)
    expected = hmac.new(mac_key, CRYPTO_MAGIC + header_raw + cipher, hashlib.sha256).digest()
    if not hmac.compare_digest(expected, tag):
        raise RuntimeError("Decrypt failed: passphrase/Z-Pepper mismatch or file was modified.")
    plaintext = crypto_stream_xor(cipher, enc_key, nonce)
    if bool(header.get("compressed")):
        plaintext = zlib.decompress(plaintext)
    expected_hash = str(header.get("plain_sha256") or "")
    if expected_hash and hashlib.sha256(plaintext).hexdigest() != expected_hash:
        raise RuntimeError("Decrypt failed: plaintext checksum mismatch.")
    return plaintext, header


def default_encrypted_output(path: pathlib.Path, output: Optional[pathlib.Path] = None) -> pathlib.Path:
    return (output or path.with_name(path.name + CRYPTO_EXTENSION)).expanduser().resolve()


def default_decrypted_output(path: pathlib.Path, header: dict, output: Optional[pathlib.Path] = None) -> pathlib.Path:
    if output:
        return output.expanduser().resolve()
    original = str(header.get("original_name") or "").strip()
    if path.name.endswith(CRYPTO_EXTENSION):
        return path.with_name(path.name[:-len(CRYPTO_EXTENSION)]).resolve()
    if original:
        return path.with_name(original).resolve()
    return path.with_name(path.name + ".decrypted").resolve()


def ensure_crypto_output_path(path: pathlib.Path, force: bool = False) -> None:
    if path.exists() and not force:
        raise RuntimeError(f"Output already exists: {path} (use --force to overwrite)")
    path.parent.mkdir(parents=True, exist_ok=True)


def crypto_encrypt_file(
    source: pathlib.Path,
    output: Optional[pathlib.Path],
    passphrase: str,
    pattern: str,
    iterations: int = CRYPTO_DEFAULT_ITERATIONS,
    compress: bool = True,
    force: bool = False,
) -> pathlib.Path:
    source = source.expanduser().resolve()
    if not source.exists() or not source.is_file():
        raise RuntimeError(f"File not found: {source}")
    target = default_encrypted_output(source, output)
    ensure_crypto_output_path(target, force=force)
    envelope = crypto_encrypt_bytes(source.read_bytes(), passphrase, pattern, source.name, kind="file", iterations=iterations, compress=compress)
    target.write_bytes(envelope)
    print(f"Encrypted {source} -> {target}")
    print(f"Z-Pepper hint: {zpepper_hint(pattern)}")
    return target


def crypto_encrypt_path(
    source: pathlib.Path,
    output: Optional[pathlib.Path],
    passphrase: str,
    pattern: str,
    iterations: int = CRYPTO_DEFAULT_ITERATIONS,
    compress: bool = True,
    force: bool = False,
) -> pathlib.Path:
    source = source.expanduser().resolve()
    if source.is_dir():
        target = default_encrypted_output(source, output)
        print("Folder detected; creating an encrypted Z-Pepper bundle.")
        return crypto_pack_paths(target, [source], passphrase, pattern, iterations=iterations, force=force)
    return crypto_encrypt_file(source, output, passphrase, pattern, iterations=iterations, compress=compress, force=force)


def crypto_decrypt_file(
    source: pathlib.Path,
    output: Optional[pathlib.Path],
    passphrase: str,
    pattern: str,
    force: bool = False,
) -> pathlib.Path:
    source = source.expanduser().resolve()
    if not source.exists() or not source.is_file():
        raise RuntimeError(f"Encrypted file not found: {source}")
    plaintext, header = crypto_decrypt_bytes(source.read_bytes(), passphrase, pattern)
    target = default_decrypted_output(source, header, output)
    ensure_crypto_output_path(target, force=force)
    target.write_bytes(plaintext)
    print(f"Decrypted {source} -> {target}")
    return target


def crypto_decrypt_path(
    source: pathlib.Path,
    output: Optional[pathlib.Path],
    passphrase: str,
    pattern: str,
    force: bool = False,
) -> pathlib.Path:
    source = source.expanduser().resolve()
    header, _header_raw, _cipher, _tag = crypto_read_envelope(source)
    if str(header.get("kind") or "") == "bundle":
        dest = output.expanduser().resolve() if output else source.with_name(source.name[:-len(CRYPTO_EXTENSION)] if source.name.endswith(CRYPTO_EXTENSION) else source.stem).resolve()
        print("Encrypted bundle detected; unpacking to a folder.")
        return crypto_unpack_bundle(source, dest, passphrase, pattern, force=force)
    return crypto_decrypt_file(source, output, passphrase, pattern, force=force)


def crypto_inspect_file(path: pathlib.Path) -> None:
    path = path.expanduser().resolve()
    header, _header_raw, cipher, tag = crypto_read_envelope(path)
    print(f"File: {path}")
    print(f"Kind: {header.get('kind')}")
    print(f"Format: {header.get('format')} v{header.get('version')}")
    print(f"Algorithm: {header.get('alg')}")
    print(f"Original name: {header.get('original_name') or ''}")
    print(f"Created UTC: {header.get('created_utc') or ''}")
    print(f"Compressed: {bool(header.get('compressed'))}")
    print(f"Cipher bytes: {len(cipher):,}")
    print(f"Auth tag bytes: {len(tag)}")
    print(f"Z-Pepper hint: {header.get('zpepper_hint') or ''}")
    kdf = dict(header.get("kdf") or {})
    print(f"KDF: {kdf.get('name')} / {int(kdf.get('iterations') or 0):,} iterations")
    print(f"Plain SHA256: {header.get('plain_sha256') or ''}")


def crypto_zip_bytes(sources: List[pathlib.Path]) -> bytes:
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for source in sources:
            source = source.expanduser().resolve()
            if not source.exists():
                raise RuntimeError(f"Path not found: {source}")
            if source.is_dir():
                for file in source.rglob("*"):
                    if file.is_file():
                        zf.write(file, file.relative_to(source.parent).as_posix())
            else:
                zf.write(source, source.name)
    return buffer.getvalue()


def crypto_extract_zip_bytes(data: bytes, dest: pathlib.Path) -> None:
    dest = dest.expanduser().resolve()
    dest.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(io.BytesIO(data)) as zf:
        for info in zf.infolist():
            if info.is_dir():
                continue
            member_path = (dest / pathlib.PurePosixPath(info.filename)).resolve()
            if dest not in [member_path, *member_path.parents]:
                raise RuntimeError(f"Unsafe encrypted bundle entry blocked: {info.filename}")
            member_path.parent.mkdir(parents=True, exist_ok=True)
            with zf.open(info) as src, member_path.open("wb") as dst:
                shutil.copyfileobj(src, dst)


def crypto_pack_paths(
    output: pathlib.Path,
    sources: List[pathlib.Path],
    passphrase: str,
    pattern: str,
    iterations: int = CRYPTO_DEFAULT_ITERATIONS,
    force: bool = False,
) -> pathlib.Path:
    if not sources:
        raise RuntimeError("crypto pack requires at least one source path.")
    output = output.expanduser().resolve()
    ensure_crypto_output_path(output, force=force)
    zip_bytes = crypto_zip_bytes(sources)
    envelope = crypto_encrypt_bytes(zip_bytes, passphrase, pattern, output.name, kind="bundle", iterations=iterations, compress=False)
    output.write_bytes(envelope)
    print(f"Packed and encrypted {len(sources)} source(s) -> {output}")
    print(f"Z-Pepper hint: {zpepper_hint(pattern)}")
    return output


def crypto_unpack_bundle(
    source: pathlib.Path,
    dest: pathlib.Path,
    passphrase: str,
    pattern: str,
    force: bool = False,
) -> pathlib.Path:
    source = source.expanduser().resolve()
    dest = dest.expanduser().resolve()
    if dest.exists() and not dest.is_dir():
        raise RuntimeError(f"Destination exists and is not a directory: {dest}")
    if dest.exists() and any(dest.iterdir()) and not force:
        raise RuntimeError(f"Destination is not empty: {dest} (use --force to allow extraction)")
    plaintext, header = crypto_decrypt_bytes(source.read_bytes(), passphrase, pattern)
    if str(header.get("kind") or "") != "bundle":
        raise RuntimeError("Encrypted file is not a bundle. Use `crypto decrypt` for normal files.")
    crypto_extract_zip_bytes(plaintext, dest)
    print(f"Unpacked encrypted bundle {source} -> {dest}")
    return dest


def safe_project_slug(value: str) -> str:
    slug = re.sub(r"[^A-Za-z0-9._-]+", "-", str(value or "").strip()).strip("-._")
    return slug or "zero-site"


def write_text_portable(path: pathlib.Path, text: str) -> None:
    path = path.expanduser().resolve()
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        handle.write(text)


def pdf_escape_text(value: str) -> str:
    return str(value or "").replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")


def pdf_wrap_text(value: str, width: int = 92) -> List[str]:
    lines: List[str] = []
    for paragraph in str(value or "").replace("\r", "").split("\n"):
        cleaned = paragraph.expandtabs(4).rstrip()
        if not cleaned:
            lines.append("")
            continue
        wrapped = textwrap.wrap(cleaned, width=width, replace_whitespace=False, drop_whitespace=True)
        lines.extend(wrapped or [""])
    return lines or [""]


def create_text_pdf(output: pathlib.Path, title: str, text: str, footer: str = "Generated by ZeroThink CLI") -> pathlib.Path:
    output = output.expanduser().resolve()
    if output.suffix.lower() != ".pdf":
        output = output.with_suffix(".pdf")
    output.parent.mkdir(parents=True, exist_ok=True)
    display_title = str(title or output.stem or "ZeroThink PDF").strip()[:120]
    body_lines = pdf_wrap_text(text or display_title)
    page_width = 612
    page_height = 792
    margin = 54
    body_size = 11
    leading = 15
    first_capacity = 38
    later_capacity = 43
    chunks: List[List[str]] = []
    remaining = list(body_lines)
    chunks.append(remaining[:first_capacity])
    remaining = remaining[first_capacity:]
    while remaining:
        chunks.append(remaining[:later_capacity])
        remaining = remaining[later_capacity:]
    page_count = max(1, len(chunks))
    page_streams: List[bytes] = []
    for index, chunk in enumerate(chunks):
        stream: List[str] = []
        y = page_height - margin
        if index == 0:
            stream.extend(["BT", "/F1 18 Tf", f"{margin} {y} Td", f"({pdf_escape_text(display_title)}) Tj", "ET"])
            y -= 34
        stream.extend(["BT", f"/F1 {body_size} Tf", f"{margin} {y} Td"])
        for line in chunk:
            stream.append(f"({pdf_escape_text(line)}) Tj")
            stream.append(f"0 -{leading} Td")
        stream.append("ET")
        stream.extend(
            [
                "BT",
                "/F1 9 Tf",
                f"{margin} 34 Td",
                f"({pdf_escape_text(footer)} - page {index + 1} of {page_count}) Tj",
                "ET",
            ]
        )
        page_streams.append("\n".join(stream).encode("latin-1", errors="replace"))

    font_id = 3 + page_count * 2
    object_count = font_id
    objects: Dict[int, bytes] = {}
    kids = " ".join(f"{3 + index * 2} 0 R" for index in range(page_count))
    objects[1] = b"<< /Type /Catalog /Pages 2 0 R >>"
    objects[2] = f"<< /Type /Pages /Kids [{kids}] /Count {page_count} >>".encode("ascii")
    for index, stream in enumerate(page_streams):
        page_id = 3 + index * 2
        content_id = page_id + 1
        objects[page_id] = (
            f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {page_width} {page_height}] "
            f"/Resources << /Font << /F1 {font_id} 0 R >> >> /Contents {content_id} 0 R >>"
        ).encode("ascii")
        objects[content_id] = b"<< /Length " + str(len(stream)).encode("ascii") + b" >>\nstream\n" + stream + b"\nendstream"
    objects[font_id] = b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"

    chunks_bytes: List[bytes] = [b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n"]
    offsets = [0] * (object_count + 1)
    cursor = len(chunks_bytes[0])
    for object_id in range(1, object_count + 1):
        offsets[object_id] = cursor
        entry = f"{object_id} 0 obj\n".encode("ascii") + objects[object_id] + b"\nendobj\n"
        chunks_bytes.append(entry)
        cursor += len(entry)
    xref_offset = cursor
    xref = [f"xref\n0 {object_count + 1}\n", "0000000000 65535 f \n"]
    for object_id in range(1, object_count + 1):
        xref.append(f"{offsets[object_id]:010d} 00000 n \n")
    trailer = (
        "".join(xref)
        + f"trailer\n<< /Size {object_count + 1} /Root 1 0 R >>\nstartxref\n{xref_offset}\n%%EOF\n"
    ).encode("ascii")
    chunks_bytes.append(trailer)
    output.write_bytes(b"".join(chunks_bytes))
    print(f"Created PDF {output}")
    return output


def site_scaffold(root: pathlib.Path, title: str = "", theme: str = "zero", force: bool = False) -> pathlib.Path:
    root = root.expanduser().resolve()
    project_name = safe_project_slug(title or root.name)
    display_name = (title or project_name).replace("-", " ").replace("_", " ").strip().title()
    if root.exists() and not root.is_dir():
        raise RuntimeError(f"Destination exists and is not a directory: {root}")
    if root.exists() and any(root.iterdir()) and not force:
        raise RuntimeError(f"Destination is not empty: {root} (use --force to scaffold anyway)")
    public = root / "public"
    src = root / "src"
    assets = root / "assets"
    for folder in (public, src, assets):
        folder.mkdir(parents=True, exist_ok=True)
    palette = {
        "zero": ("#04140a", "#00ff66", "#d8ffe6", "#0c2b18"),
        "clean": ("#f6f1e8", "#143d2b", "#111816", "#e1d5c2"),
        "luxury": ("#080604", "#d6aa4f", "#fff6df", "#25190b"),
        "cyber": ("#050714", "#00e5ff", "#e6fbff", "#121a3a"),
    }.get(str(theme or "zero").lower(), ("#04140a", "#00ff66", "#d8ffe6", "#0c2b18"))
    bg, accent, text, panel = palette
    files = {
        root / "README.md": f"# {display_name}\n\nGenerated by ZeroThink CLI aka Agent Zero.\n\n## Start\n\nOpen `public/index.html` directly, or serve the folder with any static web server.\n",
        root / ".gitignore": "node_modules/\ndist/\n.env\n*.log\n.DS_Store\nThumbs.db\n",
        root / "package.json": json.dumps(
            {
                "name": project_name.lower(),
                "version": "0.1.0",
                "private": True,
                "scripts": {"start": "python -m http.server 8080 -d public"},
            },
            indent=2,
        ) + "\n",
        public / "index.html": f"""<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{html.escape(display_name)}</title>
  <meta name="description" content="{html.escape(display_name)} website generated by ZeroThink CLI aka Agent Zero.">
  <link rel="stylesheet" href="../src/styles.css">
</head>
<body>
  <main class="shell">
    <nav class="nav">
      <strong>{html.escape(display_name)}</strong>
      <a href="#contact">Contact</a>
    </nav>
    <section class="hero">
      <p class="eyebrow">Built with Agent Zero</p>
      <h1>{html.escape(display_name)} that feels sharp, fast, and alive.</h1>
      <p class="lede">A focused starter page with strong spacing, mobile polish, and enough structure to become a real website instead of a blank template.</p>
      <div class="actions">
        <a class="button primary" href="#contact">Start Now</a>
        <a class="button ghost" href="#features">Explore</a>
      </div>
    </section>
    <section id="features" class="grid">
      <article><span>01</span><h2>Clear offer</h2><p>Say what you do, who it helps, and why it matters in seconds.</p></article>
      <article><span>02</span><h2>Trust layer</h2><p>Add testimonials, proof, screenshots, or a simple results story here.</p></article>
      <article><span>03</span><h2>Conversion path</h2><p>Guide people toward one clean next step without clutter.</p></article>
    </section>
    <section id="contact" class="panel">
      <h2>Ready to make this real?</h2>
      <p>Edit the copy, add your images, then ask Agent Zero to expand it into pages, forms, or a full app.</p>
    </section>
  </main>
  <script src="../src/main.js"></script>
</body>
</html>
""",
        src / "styles.css": f""":root {{
  --bg: {bg};
  --accent: {accent};
  --text: {text};
  --panel: {panel};
  --muted: color-mix(in srgb, var(--text) 68%, transparent);
}}
* {{ box-sizing: border-box; }}
body {{
  margin: 0;
  min-height: 100vh;
  color: var(--text);
  font-family: Georgia, 'Times New Roman', serif;
  background:
    radial-gradient(circle at 20% 10%, color-mix(in srgb, var(--accent) 28%, transparent), transparent 28rem),
    radial-gradient(circle at 80% 0%, color-mix(in srgb, var(--accent) 16%, transparent), transparent 22rem),
    var(--bg);
}}
a {{ color: inherit; }}
.shell {{ width: min(1120px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 64px; }}
.nav {{ display: flex; align-items: center; justify-content: space-between; padding: 18px 0; }}
.nav strong {{ letter-spacing: .08em; text-transform: uppercase; }}
.nav a {{ text-decoration: none; color: var(--muted); }}
.hero {{ padding: clamp(64px, 12vw, 150px) 0 56px; max-width: 900px; }}
.eyebrow {{ color: var(--accent); letter-spacing: .16em; text-transform: uppercase; font: 700 13px/1.3 ui-monospace, monospace; }}
h1 {{ font-size: clamp(48px, 8vw, 110px); line-height: .9; margin: 0 0 24px; letter-spacing: -.06em; }}
.lede {{ max-width: 720px; color: var(--muted); font-size: clamp(18px, 2.3vw, 25px); line-height: 1.45; }}
.actions {{ display: flex; flex-wrap: wrap; gap: 14px; margin-top: 32px; }}
.button {{ padding: 14px 20px; border: 1px solid color-mix(in srgb, var(--accent) 70%, transparent); border-radius: 999px; text-decoration: none; }}
.primary {{ background: var(--accent); color: var(--bg); font-weight: 800; }}
.ghost {{ color: var(--accent); }}
.grid {{ display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 30px; }}
article, .panel {{ border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent); background: color-mix(in srgb, var(--panel) 70%, transparent); border-radius: 28px; padding: 26px; box-shadow: 0 24px 90px rgba(0,0,0,.24); }}
article span {{ color: var(--accent); font: 800 13px/1 ui-monospace, monospace; }}
article h2, .panel h2 {{ margin: 14px 0 10px; }}
article p, .panel p {{ color: var(--muted); line-height: 1.6; }}
.panel {{ margin-top: 18px; }}
@media (max-width: 760px) {{
  .grid {{ grid-template-columns: 1fr; }}
  .nav {{ align-items: flex-start; gap: 12px; }}
  .hero {{ padding-top: 54px; }}
}}
""",
        src / "main.js": "document.documentElement.classList.add('ready');\n",
    }
    for path, content in files.items():
        if path.exists() and not force:
            raise RuntimeError(f"Refusing to overwrite existing file: {path}")
        write_text_portable(path, content)
    print(f"Created website scaffold at {root}")
    print("Next: edit public/index.html and src/styles.css, or ask Agent Zero to extend it.")
    return root


def unique_project_path(root: pathlib.Path) -> pathlib.Path:
    root = root.expanduser().resolve()
    if not root.exists():
        return root
    parent = root.parent
    stem = root.name
    for index in range(2, 100):
        candidate = parent / f"{stem}-{index}"
        if not candidate.exists():
            return candidate.resolve()
    raise RuntimeError(f"Could not find a free project folder near {root}")


def extract_latest_user_request(task: str) -> str:
    raw = str(task or "").strip()
    marker = "Latest user request:"
    if marker in raw:
        return raw.split(marker, 1)[1].strip()
    return raw


def looks_like_small_game_request(task: str) -> bool:
    text = " ".join(extract_latest_user_request(task).lower().split())
    if not text or "game" not in text:
        return False
    if any(token in text for token in ("fix ", "debug ", "review ", "audit ", "improve ", "refactor ", "existing ")):
        return False
    return bool(
        re.search(r"\b(make|create|build|scaffold|generate|set up|setup)\b", text)
        and any(token in text for token in ("small game", "simple game", "mini game", "browser game", "arcade game", "game project", "playable game"))
    )


def infer_game_title(task: str) -> str:
    text = extract_latest_user_request(task)
    patterns = (
        r"(?:called|named|call it|name it)\s+['\"]?([A-Za-z0-9][A-Za-z0-9 _.-]{1,48})",
        r"(?:game\s+called|game\s+named)\s+['\"]?([A-Za-z0-9][A-Za-z0-9 _.-]{1,48})",
    )
    for pattern in patterns:
        match = re.search(pattern, text, flags=re.IGNORECASE)
        if match:
            title = re.sub(r"[^A-Za-z0-9 _.-]+", "", match.group(1)).strip(" ._-")
            if title:
                return title[:48]
    return "ZeroThink Mini Game"


def game_scaffold(root: pathlib.Path, title: str = "", force: bool = False) -> pathlib.Path:
    root = root.expanduser().resolve()
    display_name = (title or root.name or "ZeroThink Mini Game").replace("-", " ").replace("_", " ").strip().title()
    project_name = safe_project_slug(title or root.name or "zerothink-mini-game").lower()
    if root.exists() and not root.is_dir():
        raise RuntimeError(f"Destination exists and is not a directory: {root}")
    if root.exists() and any(root.iterdir()) and not force:
        raise RuntimeError(f"Destination is not empty: {root} (use --force to scaffold anyway)")
    root.mkdir(parents=True, exist_ok=True)
    escaped_title = html.escape(display_name)
    files = {
        root / "README.md": f"""# {display_name}

Generated by ZeroThink CLI aka Agent Zero.

## Play

Open `index.html` in a browser, or run:

```bash
python -m http.server 8080
```

Then visit `http://localhost:8080`.

Controls: arrow keys or WASD to move, collect green cores, avoid red spikes, press Space to restart.
""",
        root / ".gitignore": "node_modules/\ndist/\n.env\n*.log\n.DS_Store\nThumbs.db\n",
        root / "package.json": json.dumps(
            {
                "name": project_name,
                "version": "0.1.0",
                "private": True,
                "scripts": {"start": "python -m http.server 8080"},
            },
            indent=2,
        ) + "\n",
        root / "index.html": f"""<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{escaped_title}</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="game-shell">
    <section class="hud" aria-label="Game status">
      <div>
        <p class="eyebrow">Agent Zero Arcade</p>
        <h1>{escaped_title}</h1>
      </div>
      <div class="stats">
        <span>Score <strong id="score">0</strong></span>
        <span>Lives <strong id="lives">3</strong></span>
        <span>Time <strong id="time">60</strong></span>
      </div>
    </section>
    <canvas id="game" width="960" height="540" aria-label="{escaped_title} game board"></canvas>
    <section class="controls">
      <button id="start" type="button">Start</button>
      <p>Move with WASD or arrow keys. Collect green cores. Dodge red spikes. Space restarts.</p>
    </section>
  </main>
  <script src="game.js"></script>
</body>
</html>
""",
        root / "style.css": """:root {
  color-scheme: dark;
  --bg: #080b10;
  --panel: #111821;
  --line: #223040;
  --text: #eef6ff;
  --muted: #a7b5c4;
  --accent: #43f28f;
  --danger: #ff4d68;
  --gold: #ffd166;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100vh;
  display: grid;
  place-items: center;
  background:
    radial-gradient(circle at 18% 12%, rgba(67, 242, 143, .16), transparent 24rem),
    radial-gradient(circle at 82% 0%, rgba(255, 209, 102, .12), transparent 20rem),
    var(--bg);
  color: var(--text);
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
.game-shell {
  width: min(1040px, calc(100% - 28px));
  padding: 22px 0;
}
.hud, .controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.eyebrow {
  margin: 0 0 6px;
  color: var(--accent);
  font: 800 12px/1 ui-monospace, SFMono-Regular, Menlo, monospace;
  letter-spacing: .14em;
  text-transform: uppercase;
}
h1 { margin: 0; font-size: clamp(28px, 5vw, 52px); line-height: 1; }
.stats {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: flex-end;
}
.stats span, button {
  border: 1px solid var(--line);
  background: rgba(17, 24, 33, .86);
  border-radius: 8px;
  padding: 10px 12px;
}
.stats strong { color: var(--gold); }
canvas {
  width: 100%;
  aspect-ratio: 16 / 9;
  display: block;
  margin: 18px 0;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: #0b1118;
  box-shadow: 0 24px 80px rgba(0, 0, 0, .36);
}
button {
  cursor: pointer;
  color: #041008;
  background: var(--accent);
  border-color: transparent;
  font-weight: 900;
}
.controls p { margin: 0; color: var(--muted); line-height: 1.5; text-align: right; }
@media (max-width: 720px) {
  .hud, .controls { align-items: flex-start; flex-direction: column; }
  .stats { justify-content: flex-start; }
  .controls p { text-align: left; }
}
""",
        root / "game.js": """const canvas = document.querySelector("#game");
const ctx = canvas.getContext("2d");
const scoreEl = document.querySelector("#score");
const livesEl = document.querySelector("#lives");
const timeEl = document.querySelector("#time");
const startBtn = document.querySelector("#start");

const keys = new Set();
let running = false;
let lastTime = 0;
let spawnTimer = 0;
let score = 0;
let lives = 3;
let timeLeft = 60;

const player = { x: 80, y: 240, size: 28, speed: 310 };
let cores = [];
let spikes = [];

function reset() {
  running = true;
  lastTime = performance.now();
  spawnTimer = 0;
  score = 0;
  lives = 3;
  timeLeft = 60;
  player.x = 80;
  player.y = canvas.height / 2 - player.size / 2;
  cores = [{ x: 520, y: 220, size: 18, vx: -120 }];
  spikes = [{ x: 820, y: 260, size: 30, vx: -170 }];
  updateHud();
  requestAnimationFrame(loop);
}

function updateHud() {
  scoreEl.textContent = score;
  livesEl.textContent = lives;
  timeEl.textContent = Math.max(0, Math.ceil(timeLeft));
}

function rectsTouch(a, b) {
  return a.x < b.x + b.size && a.x + a.size > b.x && a.y < b.y + b.size && a.y + a.size > b.y;
}

function spawnThing() {
  const y = 42 + Math.random() * (canvas.height - 84);
  if (Math.random() < 0.58) {
    cores.push({ x: canvas.width + 30, y, size: 18, vx: -150 - score * 2 });
  } else {
    spikes.push({ x: canvas.width + 30, y, size: 30, vx: -190 - score * 2 });
  }
}

function movePlayer(dt) {
  let dx = 0;
  let dy = 0;
  if (keys.has("arrowleft") || keys.has("a")) dx -= 1;
  if (keys.has("arrowright") || keys.has("d")) dx += 1;
  if (keys.has("arrowup") || keys.has("w")) dy -= 1;
  if (keys.has("arrowdown") || keys.has("s")) dy += 1;
  if (dx && dy) {
    dx *= Math.SQRT1_2;
    dy *= Math.SQRT1_2;
  }
  player.x = Math.max(0, Math.min(canvas.width - player.size, player.x + dx * player.speed * dt));
  player.y = Math.max(0, Math.min(canvas.height - player.size, player.y + dy * player.speed * dt));
}

function updateThings(list, dt) {
  for (const item of list) item.x += item.vx * dt;
  return list.filter(item => item.x + item.size > -20);
}

function drawGrid() {
  ctx.strokeStyle = "rgba(255,255,255,.045)";
  ctx.lineWidth = 1;
  for (let x = 0; x < canvas.width; x += 48) {
    ctx.beginPath();
    ctx.moveTo(x, 0);
    ctx.lineTo(x, canvas.height);
    ctx.stroke();
  }
  for (let y = 0; y < canvas.height; y += 48) {
    ctx.beginPath();
    ctx.moveTo(0, y);
    ctx.lineTo(canvas.width, y);
    ctx.stroke();
  }
}

function draw() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  drawGrid();
  ctx.fillStyle = "#43f28f";
  ctx.fillRect(player.x, player.y, player.size, player.size);
  ctx.fillStyle = "#ffd166";
  for (const core of cores) {
    ctx.beginPath();
    ctx.arc(core.x + core.size / 2, core.y + core.size / 2, core.size / 2, 0, Math.PI * 2);
    ctx.fill();
  }
  ctx.fillStyle = "#ff4d68";
  for (const spike of spikes) {
    ctx.beginPath();
    ctx.moveTo(spike.x + spike.size / 2, spike.y);
    ctx.lineTo(spike.x + spike.size, spike.y + spike.size);
    ctx.lineTo(spike.x, spike.y + spike.size);
    ctx.closePath();
    ctx.fill();
  }
  if (!running) {
    ctx.fillStyle = "rgba(8,11,16,.72)";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = "#eef6ff";
    ctx.font = "700 42px system-ui";
    ctx.textAlign = "center";
    ctx.fillText(lives <= 0 || timeLeft <= 0 ? `Final score: ${score}` : "Press Start", canvas.width / 2, canvas.height / 2);
    ctx.font = "500 20px system-ui";
    ctx.fillText("Space restarts", canvas.width / 2, canvas.height / 2 + 42);
  }
}

function loop(now) {
  if (!running) {
    draw();
    return;
  }
  const dt = Math.min(0.033, (now - lastTime) / 1000);
  lastTime = now;
  timeLeft -= dt;
  spawnTimer -= dt;
  if (spawnTimer <= 0) {
    spawnThing();
    spawnTimer = Math.max(0.35, 1.05 - score * 0.012);
  }
  movePlayer(dt);
  cores = updateThings(cores, dt);
  spikes = updateThings(spikes, dt);
  cores = cores.filter(core => {
    if (rectsTouch(player, core)) {
      score += 10;
      return false;
    }
    return true;
  });
  spikes = spikes.filter(spike => {
    if (rectsTouch(player, spike)) {
      lives -= 1;
      player.x = 80;
      player.y = canvas.height / 2 - player.size / 2;
      return false;
    }
    return true;
  });
  if (lives <= 0 || timeLeft <= 0) running = false;
  updateHud();
  draw();
  requestAnimationFrame(loop);
}

document.addEventListener("keydown", event => {
  keys.add(event.key.toLowerCase());
  if (event.code === "Space") reset();
});
document.addEventListener("keyup", event => keys.delete(event.key.toLowerCase()));
startBtn.addEventListener("click", reset);
draw();
""",
    }
    for path, content in files.items():
        if path.exists() and not force:
            raise RuntimeError(f"Refusing to overwrite existing file: {path}")
        write_text_portable(path, content)
    print(f"Created playable browser game at {root}")
    print("Open index.html, or run `python -m http.server 8080` from the project folder.")
    return root


def create_local_dir(path: pathlib.Path) -> None:
    path = path.resolve()
    path.mkdir(parents=True, exist_ok=True)
    print(f"Created {path}")


def remove_local_path(path: pathlib.Path, recursive: bool = False) -> None:
    path = path.resolve()
    if not path.exists():
        raise RuntimeError(f"Path not found: {path}")
    if path.is_dir():
        if not recursive:
            raise RuntimeError("Refusing to remove a directory without recursive=true.")
        shutil.rmtree(path)
    else:
        path.unlink()
    print(f"Removed {path}")


def copy_local_path(src: pathlib.Path, dst: pathlib.Path) -> None:
    src = src.resolve()
    dst = dst.resolve()
    if src.is_dir():
        shutil.copytree(src, dst, dirs_exist_ok=True)
    else:
        dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dst)
    print(f"Copied {src} -> {dst}")


def move_local_path(src: pathlib.Path, dst: pathlib.Path) -> None:
    src = src.resolve()
    dst = dst.resolve()
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.move(str(src), str(dst))
    print(f"Moved {src} -> {dst}")


def image_to_data_url(path: pathlib.Path) -> str:
    suffix = path.suffix.lower()
    if suffix not in IMAGE_EXTENSIONS:
        raise RuntimeError(f"Unsupported image type: {path.name}")
    mime = {
        ".jpg": "image/jpeg",
        ".jpeg": "image/jpeg",
        ".png": "image/png",
        ".webp": "image/webp",
        ".gif": "image/gif",
    }[suffix]
    encoded = base64.b64encode(path.read_bytes()).decode("ascii")
    return f"data:{mime};base64,{encoded}"


def login_flow(label: str = "", no_browser: bool = False) -> int:
    config = load_config()
    started = api_cli(
        config,
        "device_start",
        {
            "label": label or f"{platform.node() or platform.system()} CLI",
            "platform": platform.platform(),
            "hostname": platform.node(),
            "version": VERSION,
        },
        auth=False,
    )
    verification_url = started["verification_url"]
    user_code = started["user_code"]
    interval = max(2, int(started.get("interval", 3)))
    expires_in = max(60, int(started.get("expires_in", 900)))

    print_banner()
    print(f"User code: {user_code}")
    print(f"Open: {verification_url}")
    print("This uses the same ZeroThink Google login and the same vault keys as the website.")
    print("Steps:")
    print("  1. Open the browser page above.")
    print("  2. Sign in with your normal ZeroThink Google account.")
    print("  3. Approve the device link.")
    print("  4. Return here and wait for confirmation.\n")
    if not no_browser:
        try:
            webbrowser.open(verification_url)
        except Exception:
            pass

    started_at = time.time()
    while time.time() - started_at < expires_in + 5:
        time.sleep(interval)
        try:
            poll = api_cli(config, "device_poll", {"device_code": started["device_code"]}, auth=False)
        except RuntimeError as exc:
            if "authorization pending" in str(exc).lower():
                continue
            raise
        if poll.get("status") == "success":
            config["auth_token"] = poll["access_token"]
            config["auth_expires_at"] = poll.get("expires_at", "")
            config["user_email"] = (poll.get("user") or {}).get("email", "")
            save_config(config)
            print("Linked successfully.\n")
            try:
                show_account_summary(api_cli(config, "me", auth=True).get("user", {}))
            except Exception as exc:
                print(f"Linked token saved, but the live account check was rejected: {exc}")
                print("If linked mode still looks blocked, run `zerothink logout` then `zerothink login` again,")
                print("or use `zerothink key set groq` / `zerothink key set nvidia` for direct-key mode.")
            print("")
            print("Recommended next steps:")
            print("  zerothink access folder .")
            print("  zerothink help quickstart")
            print("  zerothink")
            return 0
    raise RuntimeError("Login expired. Run `zerothink login` again.")


def run_first_run_tutorial(force: bool = False, startup: bool = False) -> int:
    if not is_tty_session():
        raise RuntimeError("The ZeroThink tutorial needs an interactive terminal.")

    config = load_config()
    if tutorial_completed(config) and not force:
        return 0

    print_banner()
    print("FIRST-RUN GUIDE // beginner setup\n")
    print("ZeroThink has two main lanes:")
    print("  1. Linked account mode: same ZeroThink web account, same Vault-backed provider keys, and normal account features.")
    print("  2. Direct key mode: Groq or NVIDIA only, using a local or environment key on this machine, with no account memory/history/web/Pro features.")
    print("")
    describe_account_and_key_state(config)
    print("")

    if not prompt_yes_no("Run the guided setup now?", default=True):
        mark_tutorial_complete(config)
        print("Skipped. Run `zerothink tutorial` anytime if you want the guided setup later.")
        print("")
        return 0

    setup_options: List[Tuple[str, str]] = []
    if str(config.get("auth_token") or "").strip() or any(local_direct_key(config, name)[0] for name in DIRECT_KEY_PROVIDERS):
        setup_options.append(("keep", "Keep the current local setup and just review the basics"))
    setup_options.extend([
        ("account", "Link the same ZeroThink web account used in the browser (recommended full mode)"),
        ("groq", "Use no-Google direct mode with a local Groq key"),
        ("nvidia", "Use no-Google direct mode with a local NVIDIA key"),
        ("skip", "Skip setup for now and open the CLI as-is"),
    ])
    lane = prompt_choice("Choose your setup lane.", setup_options, default=1)

    if lane == "account":
        print("")
        print("Linked account mode opens a browser so this machine can pair with your ZeroThink account.")
        print("That lane gives you the same Vault-backed provider keys and account identity as the website.")
        print("")
        try:
            result = login_flow(label="", no_browser=False)
        except Exception as exc:
            print(str(exc))
            result = 1
        if result != 0:
            print("Login was not completed. You can rerun `zerothink login` later.")
        config = load_config()
    elif lane in DIRECT_KEY_PROVIDERS:
        provider_name = lane
        config = set_provider_defaults(config, provider_name)
        existing_key, source = local_direct_key(config, provider_name)
        print("")
        print(f"{provider_name.upper()} direct mode keeps the key on this machine only.")
        print("It does not connect this CLI session to ZeroThink account memory, saved history, web search, or Pro-only account features.")
        if existing_key and source.startswith("env:"):
            print(f"A {provider_name.upper()} key is already available via {source}.")
            direct_key_choice = prompt_choice(
                "How do you want to use it?",
                [
                    ("env", f"Use the detected {source} key without saving anything locally"),
                    ("save", f"Save a different {provider_name.upper()} key into {CONFIG_FILE}"),
                    ("keep", "Keep the current config as-is"),
                ],
                default=1,
            )
            if direct_key_choice == "save":
                set_direct_key(provider_name, "")
        elif existing_key:
            print(f"A local {provider_name.upper()} key is already saved.")
            direct_key_choice = prompt_choice(
                "How do you want to proceed?",
                [
                    ("keep", "Keep the saved key"),
                    ("replace", f"Replace the saved {provider_name.upper()} key"),
                ],
                default=1,
            )
            if direct_key_choice == "replace":
                set_direct_key(provider_name, "")
        else:
            print(f"No {provider_name.upper()} key was detected yet, so the guide will ask for one now.")
            set_direct_key(provider_name, "")
        config = load_config()
        config = set_provider_defaults(config, provider_name)
        save_config(config)
    elif lane == "skip":
        print("")
        print("Skipping account/key changes for now.")
    else:
        print("")
        print("Keeping the current local setup.")

    print("")
    access_choice = prompt_choice(
        "Choose how much of this machine the CLI should touch.",
        [
            ("folder", "Folder-only mode (recommended): lock file actions to one workspace root"),
            ("machine", "Machine mode: allow broader shell, SSH, SCP, and local operator work"),
            ("keep", "Keep the current access setting"),
        ],
        default=1,
    )
    if access_choice == "folder":
        target = prompt_workspace_for_folder_mode(pathlib.Path.cwd())
        config = set_access_mode("folder", workspace=target)
        print(f"Folder-only mode is active at {target}.")
    elif access_choice == "machine":
        config = set_access_mode("machine")
        print("Machine mode is active.")
    else:
        config = load_config()

    config = mark_tutorial_complete(config)
    print_tutorial_summary(config)
    if startup:
        print("Opening the interactive prompt now.\n")
    else:
        print("Run `zerothink` to open the interactive prompt, or `/tutorial` inside the REPL to review this later.\n")
    return 0


def show_account_summary(user: dict) -> None:
    print(f"Account: {user.get('email', '(unknown)')}")
    print(f"Role: {user.get('role', 'user')} | Plan: {user.get('plan', 'free')} | Pro: {'yes' if user.get('is_pro') else 'no'}")
    keys = user.get("provider_keys") or {}
    if keys:
        print("Vault: " + ", ".join(f"{name}={'yes' if ready else 'no'}" for name, ready in keys.items()))


def list_models(selector: Optional[str] = None) -> None:
    selected = str(selector or "").strip().lower()
    if selected in {"coding", "code"}:
        print("Recommended coding models:\n")
        for name in MODEL_CATALOG.keys():
            coding = list(MODEL_CODING_PICKS.get(name) or [])
            if not coding:
                continue
            default_model = str(PROVIDERS.get(name, {}).get("default_model") or "")
            autopilot_model = str(AUTOPILOT_DEFAULT_MODELS.get(name) or "")
            print(f"[{name}]")
            if default_model:
                print(f"  default: {default_model}")
            if autopilot_model:
                print(f"  autopilot: {autopilot_model}")
            for item in coding:
                note = str(MODEL_NOTES.get(name, {}).get(item) or "").strip()
                suffix = f"  # {note}" if note else ""
                print(f"  - {item}{suffix}")
            print("")
        print("Tip: run `zerothink models groq` or `/models groq` for the full curated provider list.")
        return

    providers = [selected] if selected else list(MODEL_CATALOG.keys())
    for name in providers:
        models = MODEL_CATALOG.get(name)
        if not models:
            print(f"Unknown provider: {name}")
            continue
        default_model = str(PROVIDERS.get(name, {}).get("default_model") or "")
        autopilot_model = str(AUTOPILOT_DEFAULT_MODELS.get(name) or "")
        coding = list(MODEL_CODING_PICKS.get(name) or [])
        print(f"\n[{name}]")
        if default_model:
            print(f"  default: {default_model}")
        if autopilot_model:
            print(f"  autopilot: {autopilot_model}")
        if coding:
            print("  coding picks:")
            for item in coding:
                note = str(MODEL_NOTES.get(name, {}).get(item) or "").strip()
                suffix = f"  # {note}" if note else ""
                print(f"    - {item}{suffix}")
        print("  curated models:")
        for item in models:
            note = str(MODEL_NOTES.get(name, {}).get(item) or "").strip()
            suffix = f"  # {note}" if note else ""
            print(f"    - {item}{suffix}")


def show_config(key: Optional[str] = None, value: Optional[str] = None) -> None:
    config = load_config()
    if key and value is not None:
        if key == "zero_mode":
            config[key] = False
            save_config(config)
            print("CLI trace mode is retired and remains off. Use the web app for full ZeroThink reasoning.")
            return
        if key in {"use_web"}:
            config[key] = boolish(value)
        elif key == "provider":
            config[key] = value.strip().lower()
        elif key in {"model", "base_url"}:
            config[key] = value.strip()
        else:
            config[key] = value
        save_config(config)
        print(f"Updated {key}.")
        return
    if key:
        print(config.get(key, ""))
        return
    safe = dict(config)
    safe["direct_keys"] = {name: mask_secret(secret) for name, secret in normalize_direct_keys(config).items()}
    if safe.get("auth_token"):
        safe["auth_token"] = safe["auth_token"][:12] + "..."
    print(json.dumps(safe, indent=2, ensure_ascii=False))


def doctor() -> None:
    config = load_config()
    print_banner()
    print(f"Config file: {'present' if CONFIG_FILE.exists() else 'missing'} -> {CONFIG_FILE}")
    print(f"Logged in: {'yes' if str(config.get('auth_token') or '').strip() else 'no'}")
    print(f"Default provider: {config.get('provider')}")
    print(f"Default model: {config.get('model')}")
    print("CLI trace mode: off (retired in terminal)")
    print(f"Web search: {'on' if boolish(config.get('use_web', False)) else 'off'}")
    print(f"Project context: {'on' if project_context_enabled(config) else 'off'}")
    show_access_status(config)
    print_direct_key_status(config)
    current_dir = active_working_dir(config)
    context_path, _context_text = read_project_context(current_dir, config)
    print(f"Project context file: {context_path if context_path else 'none'}")
    custom_path, custom_commands = load_custom_commands(current_dir, config)
    print(f"Custom commands: {len(custom_commands)}" + (f" via {custom_path}" if custom_path else ""))
    print(f"Saved local sessions: {len(list_saved_sessions())}")
    print(f"Saved local agents: {len(list_saved_agents())}")
    try:
        show_account_summary(api_cli(config, "me", auth=True).get("user", {}))
    except Exception as exc:
        print(f"Auth/API check failed: {exc}")
    print("\nNeed a beginner walkthrough? Run: zerothink help quickstart")


def run_chat(
    prompt: str,
    provider: Optional[str],
    model: Optional[str],
    files: List[str],
    urls: Optional[List[str]],
    image: Optional[str],
    zero_override,
    web_override,
    transcript: Optional[List[dict]] = None,
    context_path: Optional[pathlib.Path] = None,
    context_text: str = "",
    current_dir_override: Optional[pathlib.Path] = None,
) -> dict:
    config = load_config()
    current_dir = (current_dir_override or active_working_dir(config)).expanduser().resolve()
    provider_name = (provider or config.get("provider") or "groq").strip().lower()
    if provider_name not in PROVIDERS:
        provider_name = "groq"
    model_name = normalize_model(provider_name, model or config.get("model") or PROVIDERS[provider_name]["default_model"])
    mode, direct_key, mode_source = resolve_chat_mode(config, provider_name)
    zero_enabled = False
    web_enabled = boolish(config.get("use_web", False)) if web_override is None else web_override
    if mode == "direct":
        zero_enabled = False
        web_enabled = False
    if is_runtime_identity_question(prompt):
        route = current_runtime_route(config, provider_name, model_name, mode, requested_source=mode_source)
        return {
            "status": "success",
            "provider": provider_name,
            "model": model_name,
            "direct_mode": route.get("actual_mode") == "direct",
            "thought": "",
            "reply": format_runtime_route(route),
        }
    local_identity_reply = local_agent_zero_reply(config, provider_name, model_name, mode, mode_source, prompt)
    if local_identity_reply:
        route = current_runtime_route(config, provider_name, model_name, mode, requested_source=mode_source)
        return {
            "status": "success",
            "provider": provider_name,
            "model": model_name,
            "direct_mode": route.get("actual_mode") == "direct",
            "thought": "",
            "reply": local_identity_reply,
        }
    payload = {
        "prompt": build_local_session_prompt(
            prompt,
            transcript=transcript,
            context_path=context_path,
            context_text=context_text,
            current_dir=current_dir,
            config=config,
            provider_name=provider_name,
            model_name=model_name,
            mode_name=mode,
            mode_source=mode_source,
            attachments=files,
            url_attachments=list(urls or []),
        ),
        "engine": provider_name,
        "model": model_name,
        "zero_mode": zero_enabled,
        "use_web": web_enabled,
    }
    file_context_parts = []
    if files:
        file_context_parts.append(build_file_contexts(files))
    if urls:
        file_context_parts.append(build_url_contexts(urls))
    if should_use_local_library(prompt):
        library_pack = library_context_pack(prompt, MAX_LIBRARY_CONTEXT_CHARS)
        if library_pack:
            file_context_parts.append(library_pack)
    if file_context_parts:
        payload["file_context"] = "\n\n==== NEXT CONTEXT BLOCK ====\n\n".join(file_context_parts)
    if image:
        payload["image"] = image_to_data_url(pathlib.Path(image).expanduser().resolve())

    def perform_request(request_mode: str, request_key: str, request_source: str, fallback_from: str = "") -> dict:
        result = agent_request(config, payload, mode=request_mode, direct_key=request_key)
        message = chat_error_message(result)
        if message:
            raise RuntimeError(message)
        route = build_runtime_route_info(
            config,
            provider_name,
            model_name,
            mode,
            requested_source=mode_source,
            actual_mode=request_mode,
            actual_source=request_source,
            fallback_from=fallback_from,
        )
        remember_runtime_route(route)
        result["runtime_route"] = route
        return result

    try:
        return perform_request(mode, direct_key, mode_source)
    except RuntimeError as exc:
        if (
            mode == "account"
            and provider_name in DIRECT_KEY_PROVIDERS
            and (is_auth_error_message(str(exc)) or is_provider_fallback_message(str(exc)))
        ):
            fallback_key, fallback_source = local_direct_key(config, provider_name)
            if fallback_key:
                print(
                    f"[notice] Linked account {provider_name.upper()} lane failed. Falling back to local {provider_name.upper()} key from {fallback_source}.",
                    file=sys.stderr,
                )
                payload["zero_mode"] = False
                payload["use_web"] = False
                return perform_request("direct", fallback_key, fallback_source, fallback_from=mode)
        raise


def chat_once(args: argparse.Namespace) -> int:
    prompt = " ".join(args.prompt or []).strip()
    if prompt == "-" or (not prompt and not sys.stdin.isatty()):
        prompt = sys.stdin.read().strip()
    if not prompt:
        raise RuntimeError("No prompt supplied.")
    config = load_config()
    file_args = [str(resolve_scoped_path(item, config)) for item in (args.file or [])]
    if len(file_args) > MAX_ATTACHMENTS:
        raise RuntimeError(f"Too many file attachments. Limit: {MAX_ATTACHMENTS}")
    image_path = str(resolve_scoped_path(args.image, config)) if args.image else None
    current_dir = active_working_dir(config)
    context_path, context_text = read_project_context(current_dir, config)
    result = run_chat(
        prompt,
        args.provider,
        args.model,
        file_args,
        args.url or [],
        image_path,
        True if args.zero_on else (False if args.zero_off else None),
        True if args.use_web else (False if args.no_web else None),
        transcript=[],
        context_path=context_path,
        context_text=context_text,
        current_dir_override=current_dir,
    )
    print_agent_result(result)
    return 0


def build_review_prompt(target: str, focus: str = "") -> str:
    extra = f"\nFocus: {focus.strip()}" if str(focus or "").strip() else ""
    return (
        "Perform a code-review style analysis with findings first. "
        "Prioritize bugs, risks, regressions, security issues, missing tests, and operational hazards. "
        "Keep the summary brief after findings."
        f"\nTarget: {target.strip()}{extra}"
    )


ACTION_REQUEST_PREFIXES = (
    "make ",
    "generate ",
    "produce ",
    "create ",
    "build ",
    "scaffold ",
    "set up ",
    "setup ",
    "spin up ",
    "start a new ",
    "start an ",
    "add ",
    "fix ",
    "update ",
    "edit ",
    "change ",
    "install ",
    "configure ",
    "deploy ",
    "rename ",
    "move ",
    "copy ",
    "delete ",
    "remove ",
    "clean ",
    "scan ",
    "audit ",
    "check ",
    "open ",
    "browse ",
    "search ",
    "read ",
    "write ",
    "append ",
    "replace ",
    "run ",
    "execute ",
    "download ",
    "extract ",
    "zip ",
    "unzip ",
    "log into ",
    "login to ",
    "connect to ",
    "ssh ",
)

ACTION_REQUEST_PATTERNS = (
    r"^(can|could|would|will)\s+you\s+(make|generate|produce|create|build|scaffold|set up|setup|add|fix|update|edit|install|configure|deploy|rename|move|copy|delete|remove|scan|audit|open|search|read|write|run|download|extract|zip|unzip|log in|login|connect)\b",
    r"^please\s+(make|generate|produce|create|build|scaffold|set up|setup|add|fix|update|edit|install|configure|deploy|rename|move|copy|delete|remove|scan|audit|open|search|read|write|run|download|extract|zip|unzip|log in|login|connect)\b",
    r"^i need you to\s+(make|generate|produce|create|build|scaffold|set up|setup|add|fix|update|edit|install|configure|deploy|rename|move|copy|delete|remove|scan|audit|open|search|read|write|run|download|extract|zip|unzip|log in|login|connect)\b",
)

QUESTION_ONLY_PREFIXES = (
    "who ",
    "what ",
    "when ",
    "where ",
    "why ",
    "how ",
    "explain ",
    "summarize ",
    "describe ",
    "tell me ",
)

ACTION_FOLLOWUP_PREFIXES = (
    "yes",
    "yep",
    "yeah",
    "go ahead",
    "do it",
    "carry on",
    "continue",
    "call it ",
    "name it ",
    "use ",
    "put it ",
    "create it ",
)


def transcript_has_pending_action_context(transcript: Optional[List[dict]]) -> bool:
    recent = list(transcript or [])[-6:]
    if not recent:
        return False
    blob = "\n".join(str(item.get("content") or "").lower() for item in recent)
    markers = (
        "project folder",
        "scaffold",
        "starter files",
        "folder name",
        "target location",
        "initial structure",
        "once i have your preferences",
        "to proceed",
        "commands executed",
        "git init",
        "package.json",
    )
    return any(marker in blob for marker in markers)


def should_route_to_autopilot(prompt: str, transcript: Optional[List[dict]] = None) -> bool:
    lowered = " ".join(str(prompt or "").strip().lower().split())
    if not lowered:
        return False
    if is_runtime_identity_question(lowered) or is_cli_self_identity_question(lowered) or is_cli_capability_question(lowered):
        return False
    if any(lowered.startswith(prefix) for prefix in ACTION_REQUEST_PREFIXES):
        return True
    if any(re.match(pattern, lowered) for pattern in ACTION_REQUEST_PATTERNS):
        return True
    if lowered.endswith("?") and any(lowered.startswith(prefix) for prefix in QUESTION_ONLY_PREFIXES):
        return False
    if transcript_has_pending_action_context(transcript) and any(lowered.startswith(prefix) for prefix in ACTION_FOLLOWUP_PREFIXES):
        return True
    if any(token in lowered for token in (" folder ", " scaffold", " repo", " repository", " project ", " file ", " directory ", " server ", " ssh ", " zip ", " archive ", " game ")) and not lowered.endswith("?"):
        return True
    return False


def build_repl_autopilot_task(
    prompt: str,
    transcript: Optional[List[dict]],
    current_dir: pathlib.Path,
    attachments: List[str],
    url_attachments: List[str],
) -> str:
    sections = [
        "Interactive execution brief:",
        "Carry out the user's request using the CLI tools yourself.",
        "Do not tell the user to run commands if the CLI can do the work directly.",
        "Use the current working directory unless the recent context clearly says otherwise.",
        f"Current working directory: {current_dir}",
    ]
    if attachments:
        sections.append("Attached local paths:\n- " + "\n- ".join(attachments[:8]))
    if url_attachments:
        sections.append("Attached URLs:\n- " + "\n- ".join(url_attachments[:8]))
    recent = format_transcript_for_context(list(transcript or [])[-6:])
    if recent:
        sections.append("Recent interactive context:\n" + recent)
    lowered = str(prompt or "").strip().lower()
    if any(token in lowered for token in ("scaffold", "starter files", "web scaffold", "website scaffold", "app skeleton", "site skeleton", "forum scaffold")):
        sections.append(
            "Scaffold default:\n"
            "Only when the user explicitly asked for a scaffold or starter files, default to creating "
            "src/, public/, assets/, README.md, .gitignore, package.json, public/index.html, src/main.js, "
            "src/style.css, and initialize git in the project root."
        )
    if "game" in lowered and any(token in lowered for token in ("make", "create", "build", "generate", "scaffold", "simple", "small", "mini", "browser")):
        sections.append(
            "Game scaffold default:\n"
            "For a small/simple/mini browser game request, use the built-in local_scaffold_game action. "
            "It creates a runnable HTML/CSS/JS game with no external dependencies; do not inspect the whole workspace first."
        )
    if "pdf" in lowered and any(token in lowered for token in ("make", "create", "build", "generate", "export", "save")):
        sections.append(
            "PDF default:\n"
            "For a simple PDF/report request, use local_pdf_create. Keep it text/report focused and save the PDF in the current workspace unless the user names a path."
        )
    if "server" in lowered or "ssh" in lowered or "remote" in lowered:
        sections.append(
            "Server operator default:\n"
            "Use saved server profiles only. For broad requests like checking a server, logging in, or checking files, start with remote_health. "
            "If no profile is available, explain `zerothink server add NAME HOST --user USER --cwd PATH` instead of inventing credentials."
        )
    if any(token in lowered for token in ("project folder", "new folder", "workspace folder", "repo folder")) and "scaffold" not in lowered:
        sections.append(
            "Scope guard:\n"
            "If the user asked for a folder or workspace only, create the folder and only the directly requested files. "
            "Do not scaffold a full website, app, forum, or starter tree unless they explicitly ask for that."
        )
    sections.append("Latest user request:\n" + prompt.strip())
    return "\n\n".join(sections).strip()


def format_pending_action_preview(prompt: str, current_dir: pathlib.Path, steering_mode: str, attachments: List[str], url_attachments: List[str]) -> str:
    lines = [
        f"Steering mode: {steering_mode}",
        f"Current dir: {current_dir}",
        "Agent Zero staged this task and has not executed it yet.",
        "",
        "Request:",
        prompt.strip(),
    ]
    if attachments:
        lines.extend(["", "Attached local paths:", *[f"- {item}" for item in attachments[:8]]])
    if url_attachments:
        lines.extend(["", "Attached URLs:", *[f"- {item}" for item in url_attachments[:8]]])
    lowered = str(prompt or "").strip().lower()
    if any(token in lowered for token in ("project folder", "new folder", "workspace folder", "repo folder")) and "scaffold" not in lowered:
        lines.extend(
            [
                "",
                "Scope guard:",
                "- This looks like a folder/workspace request, so Agent Zero will not scaffold a full site unless you ask for starter files or a scaffold explicitly.",
            ]
        )
    lines.extend(
        [
            "",
            "Next:",
            "- /go to execute it now",
            "- /stop to discard this staged task",
            "- /steer auto to go back to execute-first mode",
        ]
    )
    return "\n".join(lines).strip()


def normalize_agent_plan(items) -> List[str]:
    plan: List[str] = []
    for raw in list(items or []):
        text = " ".join(str(raw or "").strip().split())
        if not text:
            continue
        if text not in plan:
            plan.append(text[:180])
        if len(plan) >= 6:
            break
    return plan


def normalize_agent_progress(text: str) -> str:
    return " ".join(str(text or "").strip().split())[:240]


def review_once(args: argparse.Namespace) -> int:
    config = load_config()
    files: List[str] = []
    urls: List[str] = list(args.url or [])
    target_raw = str(args.target).strip()
    resolved_target = None
    try:
        resolved_target = str(resolve_scoped_path(target_raw, config))
        files.append(resolved_target)
    except Exception:
        resolved_target = target_raw
    current_dir = active_working_dir(config)
    context_path, context_text = read_project_context(current_dir, config)
    result = run_chat(
        build_review_prompt(resolved_target or target_raw, args.focus or ""),
        args.provider,
        args.model,
        files,
        urls,
        None,
        None,
        None,
        transcript=[],
        context_path=context_path,
        context_text=context_text,
        current_dir_override=current_dir,
    )
    print_agent_result(result)
    return 0


def research_is_sensitive_name(name: str) -> bool:
    lowered = str(name or "").strip().lower()
    if lowered in RESEARCH_SENSITIVE_NAMES:
        return True
    if lowered.endswith((".pem", ".key", ".p12", ".pfx", ".kdbx")):
        return True
    normalized = re.sub(r"[^a-z0-9]+", "_", lowered)
    return any(token in normalized for token in RESEARCH_SENSITIVE_TOKENS)


def research_is_supported_name(name: str) -> bool:
    pure = pathlib.PurePosixPath(str(name or ""))
    return (
        pure.suffix.lower() in TEXT_EXTENSIONS
        or pure.suffix.lower() in {".pdf", ".docx", ".rtf"}
        or pure.name.lower() in {"dockerfile", "makefile"}
    )


def research_text_from_bytes(name: str, data: bytes) -> Tuple[str, str]:
    suffix = pathlib.PurePosixPath(name).suffix.lower()
    if suffix == ".docx":
        try:
            with zipfile.ZipFile(io.BytesIO(data)) as archive:
                xml = archive.read("word/document.xml").decode("utf-8", errors="replace")
            xml = re.sub(r"</w:p[^>]*>", "\n", xml, flags=re.I)
            xml = re.sub(r"<w:tab[^>]*/>", "\t", xml, flags=re.I)
            text = html.unescape(re.sub(r"<[^>]+>", "", xml))
            return re.sub(r"\n{3,}", "\n\n", text).strip(), "docx"
        except Exception as exc:
            return "", f"docx unreadable: {exc}"
    if suffix == ".pdf":
        try:
            from pypdf import PdfReader  # type: ignore
            reader = PdfReader(io.BytesIO(data))
            text = "\n\n".join((page.extract_text() or "") for page in reader.pages[:80])
            return text.strip(), f"pdf ({len(reader.pages)} pages)"
        except Exception:
            return "", "PDF inventoried only; install pypdf for local text extraction"
    try:
        return data.decode("utf-8", errors="replace").replace("\x00", "").strip(), "text"
    except Exception as exc:
        return "", f"decode failed: {exc}"


def research_excerpt(name: str, text: str, label: str) -> str:
    cleaned = str(text or "").replace("\x00", "").strip()
    if len(cleaned) > MAX_RESEARCH_FILE_CHARS:
        head = cleaned[: int(MAX_RESEARCH_FILE_CHARS * 0.62)]
        tail = cleaned[-int(MAX_RESEARCH_FILE_CHARS * 0.38) :]
        cleaned = f"{head}\n\n[... excerpt shortened ...]\n\n{tail}"
    return (
        "RESEARCH SOURCE\n"
        f"Location: {label}\n"
        f"Name: {name}\n"
        f"Extracted chars: {len(cleaned):,}\n"
        "[SOURCE EXCERPT]\n"
        f"{cleaned}\n"
        "[END SOURCE EXCERPT]"
    )


def collect_local_research_sources(
    raw_paths: List[str],
    config: dict,
    include_sensitive: bool = False,
    max_files: int = MAX_RESEARCH_FILES,
) -> Tuple[List[dict], str]:
    inventory: List[dict] = []
    excerpts: List[str] = []
    total_chars = 0
    seen = set()
    candidates: List[pathlib.Path] = []

    for raw in raw_paths:
        root = resolve_scoped_path(raw, config)
        if not root.exists():
            inventory.append({"location": f"local:{raw}", "status": "missing", "size": 0})
            continue
        if root.is_file():
            candidates.append(root)
            continue
        try:
            for path in root.rglob("*"):
                if len(candidates) >= max_files * 4:
                    break
                if path.is_dir():
                    continue
                try:
                    rel_parts = path.relative_to(root).parts
                except Exception:
                    rel_parts = path.parts
                if any(part.lower() in RESEARCH_SKIP_DIRS for part in rel_parts):
                    continue
                candidates.append(path)
        except OSError as exc:
            inventory.append({"location": f"local:{root}", "status": f"scan failed: {exc}", "size": 0})

    for path in candidates:
        if len(inventory) >= max_files:
            break
        try:
            resolved = path.resolve()
            key = str(resolved).lower()
            if key in seen:
                continue
            seen.add(key)
            size = int(resolved.stat().st_size)
            label = f"local:{resolved}"
            if not research_is_supported_name(resolved.name):
                continue
            if not include_sensitive and research_is_sensitive_name(resolved.name):
                inventory.append({"location": label, "status": "excluded: sensitive filename", "size": size})
                continue
            if size <= 0:
                inventory.append({"location": label, "status": "empty", "size": size})
                continue
            if size > MAX_LIBRARY_FILE_BYTES:
                inventory.append({"location": label, "status": "inventoried only: file too large", "size": size})
                continue
            data = resolved.read_bytes()
            text, status = research_text_from_bytes(resolved.name, data)
            if text and total_chars < MAX_RESEARCH_CONTEXT_CHARS:
                excerpt = research_excerpt(resolved.name, text, label)
                remaining = MAX_RESEARCH_CONTEXT_CHARS - total_chars
                excerpt = excerpt[:remaining]
                excerpts.append(excerpt)
                total_chars += len(excerpt)
                inventory.append({"location": label, "status": f"included: {status}", "size": size})
            else:
                inventory.append({"location": label, "status": status or "inventoried only", "size": size})
        except Exception as exc:
            inventory.append({"location": f"local:{path}", "status": f"read failed: {exc}", "size": 0})

    return inventory, "\n\n==== NEXT RESEARCH SOURCE ====\n\n".join(excerpts)


def collect_remote_research_sources(
    profile_name: str,
    raw_paths: List[str],
    config: dict,
    include_sensitive: bool = False,
    max_files: int = MAX_RESEARCH_FILES,
) -> Tuple[List[dict], str]:
    require_machine_mode(config)
    profile = get_server_profile(config, profile_name)
    inventory: List[dict] = []
    excerpts: List[str] = []
    total_chars = 0
    seen = set()
    client, sftp = open_remote_session(profile)
    try:
        queue = [resolve_remote_path(profile, item) for item in (raw_paths or ["."])]
        candidates: List[str] = []
        while queue and len(candidates) < max_files * 4:
            node = queue.pop(0)
            if remote_is_file(sftp, node):
                candidates.append(node)
                continue
            if not remote_is_dir(sftp, node):
                inventory.append({"location": f"remote:{profile_name}:{node}", "status": "missing", "size": 0})
                continue
            try:
                entries = list_remote_dir(sftp, node)
            except Exception as exc:
                inventory.append({"location": f"remote:{profile_name}:{node}", "status": f"scan failed: {exc}", "size": 0})
                continue
            for entry in entries:
                child = normalize_remote_posix(f"{node}/{entry.filename}")
                if stat.S_ISDIR(entry.st_mode):
                    if entry.filename.lower() not in RESEARCH_SKIP_DIRS:
                        queue.append(child)
                elif stat.S_ISREG(entry.st_mode):
                    candidates.append(child)
                if len(candidates) >= max_files * 4:
                    break

        for remote_path in candidates:
            if len(inventory) >= max_files:
                break
            key = remote_path.lower()
            if key in seen:
                continue
            seen.add(key)
            name = remote_basename(remote_path)
            if not research_is_supported_name(name):
                continue
            try:
                info = sftp.stat(remote_path)
                size = int(info.st_size)
                label = f"remote:{profile_name}:{remote_path}"
                if not include_sensitive and research_is_sensitive_name(name):
                    inventory.append({"location": label, "status": "excluded: sensitive filename", "size": size})
                    continue
                if size <= 0:
                    inventory.append({"location": label, "status": "empty", "size": size})
                    continue
                if size > MAX_LIBRARY_FILE_BYTES:
                    inventory.append({"location": label, "status": "inventoried only: file too large", "size": size})
                    continue
                with sftp.open(remote_path, "rb") as handle:
                    data = handle.read(MAX_LIBRARY_FILE_BYTES + 1)
                text, status = research_text_from_bytes(name, data)
                if text and total_chars < MAX_RESEARCH_CONTEXT_CHARS:
                    excerpt = research_excerpt(name, text, label)
                    remaining = MAX_RESEARCH_CONTEXT_CHARS - total_chars
                    excerpt = excerpt[:remaining]
                    excerpts.append(excerpt)
                    total_chars += len(excerpt)
                    inventory.append({"location": label, "status": f"included: {status}", "size": size})
                else:
                    inventory.append({"location": label, "status": status or "inventoried only", "size": size})
            except Exception as exc:
                inventory.append({"location": f"remote:{profile_name}:{remote_path}", "status": f"read failed: {exc}", "size": 0})
    finally:
        sftp.close()
        client.close()
    return inventory, "\n\n==== NEXT RESEARCH SOURCE ====\n\n".join(excerpts)


def format_research_inventory(inventory: List[dict]) -> str:
    if not inventory:
        return "- No local or remote source files were supplied."
    lines = []
    for item in inventory:
        size = int(item.get("size") or 0)
        size_label = format_bytes(size) if size else "-"
        lines.append(f"- {item.get('location')} | {size_label} | {item.get('status')}")
    return "\n".join(lines)


def build_research_prompt(args: argparse.Namespace, inventory: List[dict]) -> str:
    kind = str(getattr(args, "research_action", "paper") or "paper").lower()
    topic = str(getattr(args, "topic", "") or "").strip()
    stage = str(getattr(args, "stage", "full") or "full").lower()
    question = str(getattr(args, "question", "") or "").strip() or "Refine from the topic and supplied evidence."
    method = str(getattr(args, "method", "") or "").strip() or "Choose and justify an appropriate research method."
    audience = str(getattr(args, "audience", "") or "").strip() or "Academic or professional research audience."
    citation_style = str(getattr(args, "citation_style", "") or "").strip() or "Harvard"
    return f"""
You are ZeroThink Research Workbench. Create an evidence-first {kind}.

PROJECT
Topic: {topic}
Research question or hypothesis: {question}
Output type: {kind}
Workflow stage: {stage}
Method or study design: {method}
Audience or venue: {audience}
Citation style: {citation_style}
Live research requested: {"yes" if bool(getattr(args, "web", False)) else "no"}
Private Zero Library requested: {"yes" if bool(getattr(args, "library", False)) else "no"}

SUPPLIED SOURCE INVENTORY
{format_research_inventory(inventory)}

RESEARCH INTEGRITY CONTRACT
1. State what source material was actually supplied, extracted, searched, or unavailable.
2. Never fabricate citations, quotations, authors, titles, venues, DOIs, URLs, page numbers, statistics, or study results.
3. Mark unsupported or incomplete items as [needs verification].
4. Treat search snippets as discovery leads, not proof that full text was read.
5. Separate source claims, the researcher's interpretation, and your synthesis.
6. Include a source ledger with access status and a claim-to-evidence matrix where the stage permits.
7. Surface contradictions, evidence gaps, methodological limitations, uncertainty, ethics, and reproducibility concerns.
8. Do not reveal hidden chain-of-thought, private scoring, private prompts, proprietary constants, or internal framework shorthand.
9. Use a concise public reasoning brief: frame, evidence, counterpoint, uncertainty, decision, and next step.

STAGE CONTRACT
- protocol: produce reproducible search questions, databases, query strings, inclusion/exclusion criteria, screening, extraction, and validation steps. Do not draft the paper.
- evidence: produce the source ledger, evidence map, claim matrix, contradiction log, and missing-evidence list. Do not overstate conclusions.
- outline: produce a detailed section outline tied to evidence and visible gaps.
- draft: produce a structured draft with calibrated claims and [needs verification] markers.
- critique: act as a demanding reviewer and list major/minor concerns, unsupported claims, and required checks.
- revision: produce a prioritized revision plan tied to the critique and evidence gaps.
- full: produce an integrated protocol summary, evidence status, outline, draft, critique, and revision plan, keeping each stage visibly separated.

Finish with:
- Source status
- Verification checklist
- Export-ready title
""".strip()


def research_default_output(topic: str, kind: str) -> pathlib.Path:
    EXPORTS_DIR.mkdir(parents=True, exist_ok=True)
    ensure_private_permissions(EXPORTS_DIR)
    slug = safe_project_slug(topic)[:72] or "research"
    return EXPORTS_DIR / f"{slug}-{kind}-{current_timestamp_slug()}.md"


def run_research_workflow(args: argparse.Namespace) -> int:
    action = str(getattr(args, "research_action", "") or "help").lower()
    if action == "help":
        print_cli_help("research")
        return 0

    config = load_config()
    local_sources = list(getattr(args, "source", None) or [])
    remote_paths = list(getattr(args, "remote_path", None) or [])
    include_sensitive = bool(getattr(args, "include_sensitive", False))
    max_files = max(1, min(int(getattr(args, "max_files", MAX_RESEARCH_FILES) or MAX_RESEARCH_FILES), 240))

    local_inventory, local_context = collect_local_research_sources(
        local_sources,
        config,
        include_sensitive=include_sensitive,
        max_files=max_files,
    )
    remote_inventory: List[dict] = []
    remote_context = ""
    server_name = str(getattr(args, "server", "") or "").strip()
    if server_name:
        remote_inventory, remote_context = collect_remote_research_sources(
            server_name,
            remote_paths or ["."],
            config,
            include_sensitive=include_sensitive,
            max_files=max_files,
        )

    inventory = local_inventory + remote_inventory
    if action == "scan":
        print("Research source scan")
        print(format_research_inventory(inventory))
        print("")
        print(f"Included excerpt characters: {len(local_context) + len(remote_context):,}")
        print("Sensitive-looking files and common dependency folders are excluded by default.")
        return 0

    topic = str(getattr(args, "topic", "") or "").strip()
    if not topic:
        raise RuntimeError('Usage: zerothink research paper "TOPIC" [--source PATH] [--server PROFILE --remote-path PATH]')

    prompt = build_research_prompt(args, inventory)
    if bool(getattr(args, "dry_run", False)):
        print("Research workflow dry run")
        print(f"Type: {action}")
        print(f"Topic: {topic}")
        print(f"Stage: {getattr(args, 'stage', 'full')}")
        print("")
        print("Source inventory:")
        print(format_research_inventory(inventory))
        print("")
        print("No model call was made.")
        return 0

    source_context = "\n\n==== NEXT RESEARCH SOURCE ====\n\n".join(
        block for block in (local_context, remote_context) if block
    )
    if bool(getattr(args, "library", False)):
        library_pack = library_context_pack(topic, MAX_LIBRARY_CONTEXT_CHARS)
        if library_pack:
            source_context = "\n\n==== PRIVATE ZERO LIBRARY EVIDENCE ====\n\n".join(
                block for block in (source_context, library_pack) if block
            )

    temp_path: Optional[pathlib.Path] = None
    files: List[str] = []
    try:
        if source_context:
            temp_root = CONFIG_DIR / "research"
            temp_root.mkdir(parents=True, exist_ok=True)
            ensure_private_permissions(temp_root)
            temp_path = temp_root / f"source-pack-{current_timestamp_slug()}-{secrets.token_hex(3)}.md"
            write_text_portable(temp_path, source_context)
            ensure_private_permissions(temp_path)
            files.append(str(temp_path))

        current_dir = active_working_dir(config)
        context_path, context_text = read_project_context(current_dir, config)
        result = run_chat(
            prompt,
            getattr(args, "provider", None),
            getattr(args, "model", None),
            files,
            [],
            None,
            None,
            True if bool(getattr(args, "web", False)) else False,
            transcript=[],
            context_path=context_path,
            context_text=context_text,
            current_dir_override=current_dir,
        )
        if bool(getattr(args, "web", False)) and bool(result.get("direct_mode", False)):
            print(
                "[notice] Hosted web research is unavailable in local direct-key mode. "
                "The output uses supplied files and local context only.",
                file=sys.stderr,
            )
        reply = safe_console_text(str(result.get("reply", "") or "").strip())
        if not reply:
            raise RuntimeError("The selected model returned no research output.")

        output_raw = str(getattr(args, "output", "") or "").strip()
        output_path = resolve_scoped_path(output_raw, config) if output_raw else research_default_output(topic, action)
        output_path.parent.mkdir(parents=True, exist_ok=True)
        route_provider = str(result.get("provider") or getattr(args, "provider", "") or config.get("provider") or "")
        route_model = str(result.get("model") or getattr(args, "model", "") or config.get("model") or "")
        document = (
            f"# {topic}\n\n"
            f"- Created: {time.strftime('%Y-%m-%d %H:%M:%S')}\n"
            f"- Workflow: {action}\n"
            f"- Stage: {getattr(args, 'stage', 'full')}\n"
            f"- Citation style: {getattr(args, 'citation_style', 'Harvard')}\n"
            f"- Provider: {route_provider}\n"
            f"- Model: {route_model}\n\n"
            "## Supplied Source Inventory\n\n"
            f"{format_research_inventory(inventory)}\n\n"
            "## Research Output\n\n"
            f"{reply}\n"
        )
        write_text_portable(output_path, document)
        ensure_private_permissions(output_path)
        print(reply)
        print("")
        print(f"Saved research output: {output_path}")
        return 0
    finally:
        if temp_path and temp_path.exists():
            try:
                temp_path.unlink()
            except OSError:
                pass


def print_agent_result(result: dict) -> Tuple[str, str]:
    reply_text = safe_console_text(str(result.get("reply", "")).strip())
    thought = ""
    print("")
    print(reply_text)
    print("")
    return reply_text, thought


def append_transcript_turn(transcript: List[dict], user_text: str, reply_text: str, thought: str = "") -> None:
    now = time.strftime("%Y-%m-%d %H:%M:%S")
    transcript.append({"role": "user", "content": user_text.strip(), "timestamp": now})
    transcript.append({"role": "assistant", "content": reply_text, "thought": thought, "timestamp": now})


def safe_agent_name(name: Optional[str]) -> str:
    raw = str(name or "").strip()
    cleaned = re.sub(r"[^a-zA-Z0-9._-]+", "-", raw).strip(".-_").lower()
    return cleaned or f"zero-{current_timestamp_slug()}"


def agent_path(name: str) -> pathlib.Path:
    return AGENTS_DIR / f"{safe_agent_name(name)}.json"


def list_saved_agents() -> List[dict]:
    if not AGENTS_DIR.exists():
        return []
    items: List[dict] = []
    for path in sorted(AGENTS_DIR.glob("*.json"), key=lambda item: item.stat().st_mtime, reverse=True):
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
            if not isinstance(data, dict):
                continue
            items.append(
                {
                    "name": str(data.get("name") or path.stem),
                    "status": str(data.get("status") or "idle"),
                    "role": str(data.get("role") or "general"),
                    "provider": str(data.get("provider") or ""),
                    "model": str(data.get("model") or ""),
                    "steps": int(data.get("step_count") or 0),
                    "updated_at": str(data.get("updated_at") or ""),
                    "path": path,
                }
            )
        except Exception:
            continue
    return items


def load_saved_agent(name: str) -> dict:
    path = agent_path(name)
    if not path.exists():
        raise RuntimeError(f"Saved agent not found: {safe_agent_name(name)}")
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        raise RuntimeError(f"Could not read saved agent: {exc}") from exc
    if not isinstance(data, dict):
        raise RuntimeError("Saved agent file is invalid.")
    data["name"] = str(data.get("name") or path.stem)
    data["children"] = [str(item) for item in list(data.get("children") or []) if str(item).strip()]
    data["log"] = list(data.get("log") or [])
    data["attachments"] = [str(item) for item in list(data.get("attachments") or [])]
    data["url_attachments"] = [str(item) for item in list(data.get("url_attachments") or [])]
    data["briefing"] = str(data.get("briefing") or "").strip()
    data["artifacts"] = list(data.get("artifacts") or [])
    data["step_count"] = int(data.get("step_count") or 0)
    data["max_steps"] = int(data.get("max_steps") or MAX_AGENT_STEPS)
    data["worker_limit"] = int(data.get("worker_limit") or MAX_AGENT_CHILDREN)
    data["depth"] = int(data.get("depth") or 0)
    data["stop_requested"] = boolish(data.get("stop_requested", False))
    data["stop_note"] = str(data.get("stop_note") or "").strip()
    data["working_plan"] = normalize_agent_plan(data.get("working_plan") or [])
    data["progress_note"] = normalize_agent_progress(data.get("progress_note") or "")
    return data


def save_agent_state(agent: dict) -> pathlib.Path:
    AGENTS_DIR.mkdir(parents=True, exist_ok=True)
    ensure_private_permissions(AGENTS_DIR)
    path = agent_path(str(agent.get("name") or ""))
    agent["updated_at"] = time.strftime("%Y-%m-%d %H:%M:%S")
    temp_path = path.with_suffix(".json.tmp")
    temp_path.write_text(json.dumps(agent, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    temp_path.replace(path)
    ensure_private_permissions(path)
    return path


def request_agent_stop(name: str, note: str = "Stopped by operator.") -> dict:
    agent = load_saved_agent(name)
    if str(agent.get("status") or "").strip().lower() in {"done", "error", "stopped"}:
        return agent
    agent["stop_requested"] = True
    agent["stop_note"] = str(note or "Stopped by operator.").strip()
    if str(agent.get("status") or "").strip().lower() not in {"running", "done", "error", "stopped"}:
        agent["status"] = "stopping"
    agent_log = list(agent.get("log") or [])
    agent_log.append(
        {
            "step": agent.get("step_count") or 0,
            "action": "stop_requested",
            "reason": agent["stop_note"],
            "payload": {},
            "observation": "Operator requested a stop.",
            "child": "",
            "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
        }
    )
    agent["log"] = trim_agent_log(agent_log)
    save_agent_state(agent)
    return agent


def latest_stoppable_agent_name() -> str:
    for item in list_saved_agents():
        status = str(item.get("status") or "").strip().lower()
        if status not in {"done", "error", "stopped"}:
            return str(item.get("name") or "")
    return ""


def remove_saved_agent(name: str) -> None:
    path = agent_path(name)
    if not path.exists():
        raise RuntimeError(f"Saved agent not found: {safe_agent_name(name)}")
    path.unlink()


def next_zero_agent_name() -> str:
    existing = {item["name"] for item in list_saved_agents()}
    for idx in range(1, 1000):
        candidate = f"zero{idx}"
        if candidate not in existing:
            return candidate
    return f"zero-{current_timestamp_slug()}"


def summarize_agent_log(log: List[dict], limit: int = 8) -> str:
    if not log:
        return "(no previous steps)"
    blocks: List[str] = []
    for item in log[-limit:]:
        action = str(item.get("action") or "unknown")
        note = str(item.get("reason") or "").strip()
        observation = str(item.get("observation") or "").strip()
        blocks.append(
            f"Step {item.get('step', '?')}: action={action}"
            + (f" // {note}" if note else "")
            + (
                "\nObservation:\n"
                + observation[:MAX_AGENT_SUMMARY_OBSERVATION_CHARS]
                + ("...\n" if len(observation) > MAX_AGENT_SUMMARY_OBSERVATION_CHARS else "")
                if observation
                else ""
            )
        )
    return "\n\n".join(blocks)


def compact_agent_payload(value, depth: int = 0):
    if depth >= 3:
        return "...truncated..."
    if isinstance(value, dict):
        return {str(key): compact_agent_payload(item, depth + 1) for key, item in value.items()}
    if isinstance(value, list):
        clipped = [compact_agent_payload(item, depth + 1) for item in value[:12]]
        if len(value) > 12:
            clipped.append("...truncated...")
        return clipped
    text = str(value)
    if len(text) > 500:
        return text[:500] + "...truncated..."
    return value


def trim_agent_log(log: List[dict]) -> List[dict]:
    items = list(log)
    removed = 0
    while len(json.dumps(items, ensure_ascii=False)) > MAX_AGENT_LOG_CHARS and len(items) > 1:
        items.pop(0)
        removed += 1
    if removed:
        marker = {
            "step": "…",
            "action": "trim",
            "reason": f"Older log entries trimmed to keep the saved agent under {MAX_AGENT_LOG_CHARS} chars.",
            "observation": f"Trimmed {removed} earlier step(s).",
            "child": "",
            "payload": {},
            "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
        }
        items.insert(0, marker)
        while len(json.dumps(items, ensure_ascii=False)) > MAX_AGENT_LOG_CHARS and len(items) > 1:
            items.pop(1)
    return items


def print_saved_agents() -> None:
    items = list_saved_agents()
    if not items:
        print("No saved agents.")
        return
    for item in items:
        print(
            f"{item['name']:<12} status={item['status']:<8} steps={item['steps']:<3} "
            f"role={item['role']:<10} provider={item['provider']:<8} updated={item['updated_at']}"
        )


def print_agent_summary(agent: dict) -> None:
    print(f"Name: {agent.get('name')}")
    print(f"Status: {agent.get('status')}")
    print(f"Role: {agent.get('role')}")
    print(f"Task: {agent.get('task')}")
    print(f"Provider: {agent.get('provider')} / {agent.get('model')}")
    print(f"Dir: {agent.get('current_dir')}")
    print(f"Steps: {agent.get('step_count')} / {agent.get('max_steps')}")
    print(f"Workers: {len(agent.get('children') or [])} / {agent.get('worker_limit')}")
    progress_note = str(agent.get("progress_note") or "").strip()
    if progress_note:
        print(f"Progress: {safe_console_text(progress_note)}")
    working_plan = normalize_agent_plan(agent.get("working_plan") or [])
    if working_plan:
        print("Working plan:")
        for item in working_plan:
            print(f"- {safe_console_text(item)}")
    print(f"Created: {agent.get('created_at')}")
    print(f"Updated: {agent.get('updated_at')}")
    final = str(agent.get("final_reply") or "").strip()
    if final:
        print("\nFinal:")
        print(safe_console_text(final))


def print_agent_log(agent: dict) -> None:
    log = list(agent.get("log") or [])
    if not log:
        print("No agent steps yet.")
        return
    for item in log:
        print(f"[step {item.get('step')}] {item.get('action')}")
        reason = str(item.get("reason") or "").strip()
        if reason:
            print(f"Reason: {reason}")
        progress = str(item.get("progress") or "").strip()
        if progress:
            print(f"Progress: {safe_console_text(progress)}")
        plan = normalize_agent_plan(item.get("plan") or [])
        if plan:
            print("Plan:")
            for entry in plan:
                print(f"- {safe_console_text(entry)}")
        payload = item.get("payload")
        if isinstance(payload, dict) and payload:
            print("Payload:")
            print(safe_console_text(json.dumps(payload, indent=2, ensure_ascii=False)))
        observation = str(item.get("observation") or "").strip()
        if observation:
            print("Observation:")
            print(safe_console_text(observation))
        child = str(item.get("child") or "").strip()
        if child:
            print(f"Child: {child}")
        print("")


def capture_text_output(func, *args, **kwargs) -> str:
    stdout = io.StringIO()
    stderr = io.StringIO()
    with contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stderr):
        func(*args, **kwargs)
    text = stdout.getvalue()
    err = stderr.getvalue()
    combined = text + (("\n" + err) if err else "")
    combined = combined.strip()
    if len(combined) > MAX_AGENT_OBSERVATION_CHARS:
        combined = combined[:MAX_AGENT_OBSERVATION_CHARS] + "\n...truncated..."
    return combined or "(no output)"


def extract_json_object(text: str) -> dict:
    raw = str(text or "").strip()
    if not raw:
        raise RuntimeError("Model returned an empty autopilot response.")
    fence = re.search(r"```(?:json)?\s*(\{.*?\})\s*```", raw, re.DOTALL | re.IGNORECASE)
    candidates = [fence.group(1)] if fence else []
    candidates.append(raw)
    start = raw.find("{")
    end = raw.rfind("}")
    if start != -1 and end != -1 and end > start:
        candidates.append(raw[start : end + 1])
    for candidate in candidates:
        try:
            parsed = json.loads(candidate)
            if isinstance(parsed, dict):
                return parsed
        except Exception:
            continue
    raise RuntimeError("Could not parse autopilot JSON from the model reply.")


def call_agent_model(prompt: str, provider: str, model: str, cwd: pathlib.Path) -> str:
    config = load_config()
    context_path, context_text = read_project_context(cwd, config)
    provider_name = str(provider or config.get("provider") or "groq").strip().lower()
    if provider_name not in PROVIDERS:
        provider_name = "groq"
    primary_model = normalize_model(provider_name, str(model or config.get("model") or PROVIDERS[provider_name]["default_model"]))
    candidates = [primary_model]
    for fallback in AUTOPILOT_MODEL_FALLBACKS.get(provider_name, []):
        candidate = normalize_model(provider_name, fallback)
        if candidate not in candidates:
            candidates.append(candidate)
    last_error: Optional[Exception] = None
    for candidate in candidates:
        try:
            result = run_chat(
                prompt,
                provider_name,
                candidate,
                [],
                [],
                None,
                None,
                False,
                transcript=[],
                context_path=context_path,
                context_text=context_text,
                current_dir_override=cwd,
            )
            reply = str(result.get("reply") or "").strip()
            lowered = reply.lower()
            if not reply or lowered.startswith("request failed:") or "provider error" in lowered or lowered.startswith("api error"):
                raise RuntimeError(reply or f"{provider_name}:{candidate} returned an empty autopilot reply.")
            return reply
        except Exception as exc:
            last_error = exc
            continue
    if last_error:
        raise last_error
    raise RuntimeError("Autopilot model call failed.")


def build_autopilot_prompt(agent: dict) -> str:
    config = load_config()
    cwd = pathlib.Path(str(agent.get("current_dir") or pathlib.Path.cwd())).expanduser().resolve()
    server_names = ", ".join(sorted(get_server_profiles(config).keys())) or "(none)"
    mcp_names = ", ".join(sorted(get_mcp_servers(config).keys())) or "(none)"
    child_budget = max(0, int(agent.get("worker_limit") or MAX_AGENT_CHILDREN) - len(list(agent.get("children") or [])))
    attachments = [str(item).strip() for item in list(agent.get("attachments") or []) if str(item).strip()]
    url_attachments = [str(item).strip() for item in list(agent.get("url_attachments") or []) if str(item).strip()]
    briefing = str(agent.get("briefing") or "").strip()
    working_plan = normalize_agent_plan(agent.get("working_plan") or [])
    progress_note = normalize_agent_progress(agent.get("progress_note") or "")
    return textwrap.dedent(
        f"""
        You are ZeroThink Autopilot, a self-driven coding and operator agent running inside the ZeroThink CLI.
        Your job is to make steady progress on the task by choosing exactly one next action as strict JSON.
        You are responsible for executing the task, not merely describing commands or asking the user to do the work.
        For safe non-destructive setup work like scaffolding a project, creating files, organizing folders, scanning code, or preparing a repo, take action directly and choose sensible defaults.
        Never claim a command ran or a file exists unless you used an action that actually produced that observation.
        Think privately in this order: orient -> inspect -> plan -> act -> verify -> report.
        Keep a compact working plan in sync with reality so the next step stays deliberate instead of drifting.

        Current agent:
        - name: {agent.get('name')}
        - role: {agent.get('role')}
        - task: {agent.get('task')}
        - cwd: {cwd}
        - platform: {platform.system()}
        - shell: {"powershell" if os.name == "nt" else "posix"}
        - provider: {agent.get('provider')}
        - model: {agent.get('model')}
        - step: {agent.get('step_count')} of {agent.get('max_steps')}
        - child budget left: {child_budget}
        - available server profiles: {server_names}
        - available MCP servers: {mcp_names}
        - local attachments: {", ".join(attachments[:8]) if attachments else "(none)"}
        - URL attachments: {", ".join(url_attachments[:8]) if url_attachments else "(none)"}
        - current progress note: {progress_note or "(none)"}
        - current working plan: {" | ".join(working_plan) if working_plan else "(none)"}

        Available actions:
        - done: {{"action":"done","summary":"what was completed","final":"what the user should know","progress":"short progress note","plan":["optional remaining or follow-up items"]}}
        - local_ls: {{"action":"local_ls","path":"."}}
        - local_tree: {{"action":"local_tree","path":".","depth":2,"limit":80}}
        - local_scan: {{"action":"local_scan","path":"."}}
        - local_read: {{"action":"local_read","path":"file","start":1,"lines":120}}
        - local_search: {{"action":"local_search","pattern":"TODO","path":".","ignore_case":true}}
        - local_mkdir: {{"action":"local_mkdir","path":"new-folder"}}
        - local_scaffold_game: {{"action":"local_scaffold_game","path":"zerothink-mini-game","title":"ZeroThink Mini Game"}}
        - local_rm: {{"action":"local_rm","path":"file-or-dir","recursive":false}}
        - local_cp: {{"action":"local_cp","src":"from","dst":"to"}}
        - local_mv: {{"action":"local_mv","src":"from","dst":"to"}}
        - local_exec: {{"action":"local_exec","command":"git status","cwd":"optional","timeout":120}}
        - local_write: {{"action":"local_write","path":"file","text":"new content"}}
        - local_append: {{"action":"local_append","path":"file","text":"more text"}}
        - local_replace: {{"action":"local_replace","path":"file","old":"from","new":"to","all":false}}
        - local_zip_list: {{"action":"local_zip_list","path":"archive.zip"}}
        - local_zip_extract: {{"action":"local_zip_extract","path":"archive.zip","dest":"folder"}}
        - local_zip_create: {{"action":"local_zip_create","output":"bundle.zip","sources":["path1","path2"]}}
        - local_pdf_create: {{"action":"local_pdf_create","path":"report.pdf","title":"Report title","text":"report body","source":"optional-source.md"}}
        - local_encrypt: {{"action":"local_encrypt","path":"file","output":"optional.ztz","pattern":"optional zpepper pattern"}}
        - local_decrypt: {{"action":"local_decrypt","path":"file.ztz","output":"optional","pattern":"optional zpepper pattern"}}
        - local_crypto_inspect: {{"action":"local_crypto_inspect","path":"file.ztz"}}
        - local_crypto_pack: {{"action":"local_crypto_pack","output":"bundle.ztz","sources":["path1","path2"],"pattern":"optional zpepper pattern"}}
        - web_search: {{"action":"web_search","query":"search terms"}}
        - web_open: {{"action":"web_open","url":"https://example.com"}}
        - remote_ls: {{"action":"remote_ls","server":"name","path":"."}}
        - remote_tree: {{"action":"remote_tree","server":"name","path":".","depth":2,"limit":80}}
        - remote_scan: {{"action":"remote_scan","server":"name","path":"."}}
        - remote_read: {{"action":"remote_read","server":"name","path":"file","start":1,"lines":120}}
        - remote_search: {{"action":"remote_search","server":"name","pattern":"TODO","path":".","ignore_case":true}}
        - remote_health: {{"action":"remote_health","server":"name","path":"."}}
        - remote_mkdir: {{"action":"remote_mkdir","server":"name","path":"folder"}}
        - remote_rm: {{"action":"remote_rm","server":"name","path":"target","recursive":false}}
        - remote_exec: {{"action":"remote_exec","server":"name","command":"pwd && ls","timeout":180}}
        - remote_write: {{"action":"remote_write","server":"name","path":"file","text":"new content"}}
        - remote_append: {{"action":"remote_append","server":"name","path":"file","text":"more text"}}
        - remote_replace: {{"action":"remote_replace","server":"name","path":"file","old":"from","new":"to","all":false}}
        - playwright_open: {{"action":"playwright_open","url":"https://example.com","profile":"optional"}}
        - playwright_call: {{"action":"playwright_call","tool":"browser_snapshot","arguments":{{}},"profile":"optional"}}
        - mcp_tools: {{"action":"mcp_tools","server":"playwright"}}
        - mcp_call: {{"action":"mcp_call","server":"playwright","tool":"browser_snapshot","arguments":{{}}}}
        - spawn: {{"action":"spawn","task":"side task","role":"reviewer","name":"optional"}}

        Rules:
        - Return JSON only. No markdown fences. No prose before or after.
        - Pick the single best next action.
        - Include `reason` with the concrete reasoning for this next action.
        - Include `progress` as one short line describing where the task stands now.
        - Include `plan` as a compact list of 1-5 current steps when it helps keep the task aligned.
        - Use spawn only when a side task can help materially and child budget remains.
        - Prefer compact inspection before writing or replacing files: local_ls, local_scan, local_search, or local_read beats broad local_tree.
        - Avoid broad local_tree at Desktop, home, repo roots, or production roots unless the user specifically asked for a tree; if you use it, keep depth <= 2.
        - Prefer native file actions like local_mkdir/local_write/local_cp/local_mv/local_rm over shell commands when they are sufficient.
        - Do not use local_exec for routine file or folder creation when local actions can do the job directly.
        - For "create/make/export a PDF", use local_pdf_create. If the user names a source file, set source and keep text compact.
        - For "login/connect/check a server" or "check files on server", use a saved server profile and start with remote_health unless a narrower remote_read/remote_search is clearly better.
        - Never invent server credentials, hostnames, passwords, or profile names. If no saved server profile exists, explain the exact `zerothink server add ...` command needed.
        - For "make/create/build a small/simple/mini browser game", use local_scaffold_game instead of asking questions or dumping a directory tree.
        - If the user only asked for a folder, workspace, or repo directory, create that folder only and do not silently scaffold a whole website or app.
        - For an explicit new project scaffold, create the folder and starter files directly instead of asking follow-up questions unless a destructive choice is unclear.
        - Only for an explicit web/app scaffold request, default to: src/, public/, assets/, README.md, .gitignore, package.json, public/index.html, src/main.js, src/style.css, and git init in the project root unless the user says otherwise.
        - If an optional tool like git is unavailable, finish the rest of the task and mention that limitation in the final summary instead of pretending it worked.
        - Use local_encrypt/local_decrypt/local_crypto_pack for explicit encryption or password-protection requests. local_encrypt accepts files or folders; folders become encrypted bundles. The CLI will ask the operator for the passphrase when needed; do not invent or log passphrases.
        - If you must use local_exec on Windows, emit a single PowerShell-compatible command and avoid bash-only separators like &&.
        - Use Playwright or MCP only in machine mode and only when that tool is actually needed.
        - If you write or modify code, try to verify the affected area before using `done`.
        - If the task is complete, use done.

        Recent interactive context:
        {briefing[:2000] if briefing else "(none)"}

        Recent agent log:
        {summarize_agent_log(list(agent.get("log") or []))}
        """
    ).strip()


def create_agent_state(
    task: str,
    name: Optional[str] = None,
    role: str = "general",
    provider: Optional[str] = None,
    model: Optional[str] = None,
    current_dir: Optional[pathlib.Path] = None,
    max_steps: int = MAX_AGENT_STEPS,
    worker_limit: int = MAX_AGENT_CHILDREN,
    parent: str = "",
    depth: int = 0,
    attachments: Optional[List[str]] = None,
    url_attachments: Optional[List[str]] = None,
    briefing: str = "",
) -> dict:
    config = load_config()
    provider_name = str(provider or config.get("provider") or "groq").strip().lower()
    if provider_name not in PROVIDERS:
        provider_name = "groq"
    requested_model = str(model or "").strip()
    base_model = requested_model or AUTOPILOT_DEFAULT_MODELS.get(provider_name) or str(config.get("model") or PROVIDERS[provider_name]["default_model"])
    model_name = normalize_model(provider_name, base_model)
    target_name = safe_agent_name(name or next_zero_agent_name())
    path = agent_path(target_name)
    if path.exists():
        raise RuntimeError(f"Agent already exists: {target_name}")
    cwd = (current_dir or active_working_dir(config)).expanduser().resolve()
    agent = {
        "version": 1,
        "name": target_name,
        "role": role or "general",
        "task": str(task or "").strip(),
        "provider": provider_name,
        "model": model_name,
        "status": "idle",
        "current_dir": str(cwd),
        "created_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "updated_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "step_count": 0,
        "max_steps": max(1, min(int(max_steps), 48)),
        "worker_limit": max(0, min(int(worker_limit), 8)),
        "parent": str(parent or "").strip(),
        "children": [],
        "depth": max(0, depth),
        "attachments": [str(item) for item in list(attachments or []) if str(item).strip()],
        "url_attachments": [str(item) for item in list(url_attachments or []) if str(item).strip()],
        "briefing": str(briefing or "").strip(),
        "artifacts": [],
        "log": [],
        "working_plan": [],
        "progress_note": "",
        "stop_requested": False,
        "stop_note": "",
        "final_reply": "",
    }
    save_agent_state(agent)
    return agent


def record_agent_artifact(agent: dict, kind: str, path: str, note: str = "") -> None:
    cleaned_path = str(path or "").strip()
    if not cleaned_path:
        return
    artifacts = list(agent.get("artifacts") or [])
    entry = {
        "kind": str(kind or "artifact").strip() or "artifact",
        "path": cleaned_path,
        "note": str(note or "").strip(),
        "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
    }
    last = artifacts[-1] if artifacts else {}
    if (
        str(last.get("kind") or "") == entry["kind"]
        and str(last.get("path") or "") == entry["path"]
        and str(last.get("note") or "") == entry["note"]
    ):
        return
    artifacts.append(entry)
    agent["artifacts"] = artifacts[-24:]


def format_agent_artifact(entry: dict) -> str:
    kind = str(entry.get("kind") or "artifact").strip().lower()
    path = safe_console_text(str(entry.get("path") or "").strip())
    note = safe_console_text(str(entry.get("note") or "").strip())
    labels = {
        "created_dir": "Created directory",
        "wrote_file": "Wrote file",
        "appended_file": "Appended file",
        "updated_file": "Updated file",
        "created_pdf": "Created PDF",
        "created_game": "Created playable game",
        "removed_path": "Removed path",
        "copied_path": "Copied to",
        "moved_path": "Moved to",
        "created_archive": "Created archive",
        "extracted_archive": "Extracted archive to",
        "remote_created_dir": "Created remote directory",
        "remote_removed_path": "Removed remote path",
        "remote_wrote_file": "Wrote remote file",
        "remote_appended_file": "Appended remote file",
        "remote_updated_file": "Updated remote file",
    }
    label = labels.get(kind, kind.replace("_", " ").strip().capitalize() or "Artifact")
    return f"{label}: {path}" + (f" ({note})" if note else "")


def is_generic_agent_final(text: str) -> bool:
    lowered = str(text or "").strip().lower()
    if not lowered:
        return True
    generic_values = {
        "completed",
        "completed.",
        "done",
        "done.",
        "finished",
        "finished.",
        "task complete",
        "task complete.",
        "completed successfully",
        "completed successfully.",
    }
    if lowered in generic_values:
        return True
    if len(lowered) <= 40 and any(token in lowered for token in ("complete", "done", "finished")):
        return True
    return False


def synthesize_agent_final_reply(agent: dict) -> str:
    status = str(agent.get("status") or "").strip().lower()
    final_reply = str(agent.get("final_reply") or "").strip()
    artifacts = list(agent.get("artifacts") or [])
    last_observations = [
        safe_console_text(str(item.get("observation") or "").strip())
        for item in reversed(list(agent.get("log") or []))
        if str(item.get("observation") or "").strip() and str(item.get("action") or "") != "done"
    ]
    lines: List[str] = []
    if final_reply and not is_generic_agent_final(final_reply):
        lines.append(safe_console_text(final_reply))
    elif status == "done":
        lines.append("Agent Zero completed the task.")
    elif status == "error":
        lines.append("Agent Zero hit an error before finishing the task.")
    elif status == "stopped":
        lines.append("Agent Zero stopped after using the current step budget.")
    else:
        lines.append(f"Agent Zero status: {status or 'unknown'}.")
    if artifacts:
        lines.append("")
        lines.append("Touched paths:")
        for item in artifacts[-10:]:
            lines.append(f"- {format_agent_artifact(item)}")
    if status in {"error", "stopped", "running"} and last_observations:
        lines.append("")
        lines.append("Last result:")
        lines.append(last_observations[0])
    elif not artifacts and last_observations:
        lines.append("")
        lines.append("Last result:")
        lines.append(last_observations[0])
    return "\n".join(lines).strip()


def fallback_autopilot_action(agent: dict, bad_reply: str) -> dict:
    raw = str(bad_reply or "").strip()
    lowered = raw.lower()
    step_count = int(agent.get("step_count") or 0)
    if any(token in lowered for token in ("done", "completed", "complete", "finished")):
        summary = raw.splitlines()[0].strip() if raw else "Autopilot marked the task complete."
        return {
            "action": "done",
            "summary": summary[:240] or "Autopilot marked the task complete.",
            "final": raw[:1200] or summary or "Completed.",
            "reason": "Fallback action after invalid autopilot JSON.",
        }
    file_match = re.search(r"([A-Za-z0-9_./\\\\-]+\.[A-Za-z0-9]{1,8})", raw)
    if file_match:
        return {
            "action": "local_read",
            "path": file_match.group(1),
            "start": 1,
            "lines": 120,
            "reason": "Fallback action after invalid autopilot JSON: inspect the most likely file reference first.",
        }
    if "search" in lowered and "web" in lowered:
        return {
            "action": "web_search",
            "query": str(agent.get("task") or "relevant documentation"),
            "reason": "Fallback action after invalid autopilot JSON: run a web search for the task.",
        }
    if step_count <= 0:
        return {
            "action": "local_ls",
            "path": ".",
            "reason": "Fallback action after invalid autopilot JSON: inspect the workspace root compactly first.",
        }
    return {
        "action": "done",
        "summary": "Autopilot stopped after invalid model JSON.",
        "final": (raw[:1200] + ("\n...truncated..." if len(raw) > 1200 else "")) or "Autopilot stopped after invalid model JSON.",
        "reason": "Fallback action after repeated invalid autopilot JSON.",
    }


def run_agent_repair(agent: dict, bad_reply: str) -> dict:
    cwd = pathlib.Path(str(agent.get("current_dir") or pathlib.Path.cwd())).expanduser().resolve()
    prompt = textwrap.dedent(
        f"""
        Convert the following autopilot reply into a valid JSON object only.
        Keep the same intended action if possible.
        If the reply is vague or unusable, prefer:
        {{"action":"local_ls","path":".","reason":"inspect the workspace root compactly first"}}

        Bad reply:
        {bad_reply}
        """
    ).strip()
    repaired = call_agent_model(prompt, str(agent.get("provider") or "groq"), str(agent.get("model") or ""), cwd)
    try:
        return extract_json_object(repaired)
    except Exception:
        return fallback_autopilot_action(agent, bad_reply)


def execute_agent_action(agent: dict, action: dict) -> Tuple[str, Optional[str]]:
    config = load_config()
    current_dir = pathlib.Path(str(agent.get("current_dir") or pathlib.Path.cwd())).expanduser().resolve()
    kind = str(action.get("action") or "").strip().lower()
    child_name: Optional[str] = None
    if kind == "done":
        summary = str(action.get("summary") or "").strip()
        final = str(action.get("final") or summary or "Completed.")
        agent["status"] = "done"
        agent["final_reply"] = final
        return summary or final, None
    if kind == "local_ls":
        target = resolve_scoped_path(str(action.get("path") or "."), config, current_dir)
        return capture_text_output(print_path_listing, target), None
    if kind == "local_tree":
        target = resolve_scoped_path(str(action.get("path") or "."), config, current_dir)
        depth = max(1, min(int(action.get("depth") or 3), 8))
        limit = max(20, min(int(action.get("limit") or 120), 300))
        return capture_text_output(print_tree, target, max_depth=depth, limit=limit), None
    if kind == "local_scan":
        target = resolve_scoped_path(str(action.get("path") or "."), config, current_dir)
        return capture_text_output(print_workspace_scan, target), None
    if kind == "local_read":
        target = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        start = int(action.get("start") or 1)
        lines = int(action.get("lines") or MAX_READ_LINES)
        return capture_text_output(read_text_preview, target, start=start, lines=lines), None
    if kind == "local_search":
        target = resolve_scoped_path(str(action.get("path") or "."), config, current_dir)
        return capture_text_output(search_workspace, str(action.get("pattern") or ""), target, ignore_case=bool(action.get("ignore_case"))), None
    if kind == "local_mkdir":
        target = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        observation = capture_text_output(create_local_dir, target)
        record_agent_artifact(agent, "created_dir", str(target.resolve()))
        return observation, None
    if kind == "local_scaffold_game":
        title = str(action.get("title") or infer_game_title(str(agent.get("task") or ""))).strip()
        default_name = safe_project_slug(title or "zerothink-mini-game").lower()
        raw_path = str(action.get("path") or default_name).strip() or default_name
        target = resolve_scoped_path(raw_path, config, current_dir)
        force = bool(action.get("force"))
        if not force:
            target = unique_project_path(target)
        observation = capture_text_output(game_scaffold, target, title=title, force=force)
        record_agent_artifact(agent, "created_game", str(target.resolve()))
        return observation, None
    if kind == "local_rm":
        target = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        observation = capture_text_output(remove_local_path, target, recursive=bool(action.get("recursive")))
        record_agent_artifact(agent, "removed_path", str(target.resolve()))
        return observation, None
    if kind == "local_cp":
        src = resolve_scoped_path(str(action.get("src") or ""), config, current_dir)
        dst = resolve_scoped_path(str(action.get("dst") or ""), config, current_dir)
        observation = capture_text_output(copy_local_path, src, dst)
        record_agent_artifact(agent, "copied_path", str(dst.resolve()), note=f"from {src.resolve()}")
        return observation, None
    if kind == "local_mv":
        src = resolve_scoped_path(str(action.get("src") or ""), config, current_dir)
        dst = resolve_scoped_path(str(action.get("dst") or ""), config, current_dir)
        observation = capture_text_output(move_local_path, src, dst)
        record_agent_artifact(agent, "moved_path", str(dst.resolve()), note=f"from {src.resolve()}")
        return observation, None
    if kind == "local_exec":
        require_machine_mode(config)
        cwd = resolve_scoped_path(str(action.get("cwd") or "."), config, current_dir)
        timeout = max(5, min(900, int(action.get("timeout") or 120)))
        return capture_text_output(run_local_command, str(action.get("command") or ""), cwd=cwd, timeout=timeout), None
    if kind == "local_write":
        target = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        observation = capture_text_output(write_text_file, target, str(action.get("text") or ""), append=False)
        record_agent_artifact(agent, "wrote_file", str(target.resolve()))
        return observation, None
    if kind == "local_append":
        target = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        observation = capture_text_output(write_text_file, target, str(action.get("text") or ""), append=True)
        record_agent_artifact(agent, "appended_file", str(target.resolve()))
        return observation, None
    if kind == "local_replace":
        target = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        observation = capture_text_output(replace_in_file, target, str(action.get("old") or ""), str(action.get("new") or ""), replace_all=bool(action.get("all")))
        record_agent_artifact(agent, "updated_file", str(target.resolve()))
        return observation, None
    if kind == "local_zip_list":
        target = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        return capture_text_output(zip_list, target), None
    if kind == "local_zip_extract":
        archive = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        dest = resolve_scoped_path(str(action.get("dest") or archive.stem), config, current_dir)
        observation = capture_text_output(zip_extract, archive, dest)
        record_agent_artifact(agent, "extracted_archive", str(dest.resolve()), note=f"from {archive.resolve()}")
        return observation, None
    if kind == "local_zip_create":
        output = resolve_scoped_path(str(action.get("output") or ""), config, current_dir)
        raw_sources = action.get("sources")
        if isinstance(raw_sources, list) and raw_sources:
            sources = [resolve_scoped_path(str(item), config, current_dir) for item in raw_sources if str(item).strip()]
        else:
            single_source = str(action.get("path") or "").strip()
            sources = [resolve_scoped_path(single_source, config, current_dir)] if single_source else []
        if not sources:
            raise RuntimeError("local_zip_create requires at least one source path.")
        observation = capture_text_output(zip_create, output, sources)
        record_agent_artifact(agent, "created_archive", str(output.resolve()))
        return observation, None
    if kind == "local_pdf_create":
        raw_output = str(action.get("path") or action.get("output") or "zerothink-report.pdf").strip()
        output = resolve_scoped_path(raw_output, config, current_dir)
        title = str(action.get("title") or output.stem or "ZeroThink Report").strip()
        body = str(action.get("text") or action.get("body") or "").strip()
        source_raw = str(action.get("source") or "").strip()
        if source_raw:
            source = resolve_scoped_path(source_raw, config, current_dir)
            try:
                source_text = source.read_text(encoding="utf-8", errors="replace")
            except Exception as exc:
                raise RuntimeError(f"Could not read PDF source file: {exc}") from exc
            body = (body + "\n\n" if body else "") + source_text
        if not body:
            body = title
        observation = capture_text_output(create_text_pdf, output, title, body)
        record_agent_artifact(agent, "created_pdf", str(output.with_suffix(".pdf").resolve()))
        return observation, None
    if kind == "local_encrypt":
        source = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        output_raw = str(action.get("output") or "").strip()
        output = resolve_scoped_path(output_raw, config, current_dir) if output_raw else None
        pattern = resolve_crypto_pattern(config, str(action.get("pattern") or ""))
        passphrase = resolve_crypto_passphrase(confirm=True)
        observation = capture_text_output(crypto_encrypt_path, source, output, passphrase, pattern)
        record_agent_artifact(agent, "encrypted_file", str((output or default_encrypted_output(source)).resolve()))
        return observation, None
    if kind == "local_decrypt":
        source = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        output_raw = str(action.get("output") or "").strip()
        output = resolve_scoped_path(output_raw, config, current_dir) if output_raw else None
        pattern = resolve_crypto_pattern(config, str(action.get("pattern") or ""))
        passphrase = resolve_crypto_passphrase(confirm=False)
        observation = capture_text_output(crypto_decrypt_path, source, output, passphrase, pattern)
        record_agent_artifact(agent, "decrypted_file", str((output or source.with_suffix("")).resolve()))
        return observation, None
    if kind == "local_crypto_inspect":
        target = resolve_scoped_path(str(action.get("path") or ""), config, current_dir)
        return capture_text_output(crypto_inspect_file, target), None
    if kind == "local_crypto_pack":
        output = resolve_scoped_path(str(action.get("output") or ""), config, current_dir)
        raw_sources = action.get("sources")
        if not isinstance(raw_sources, list) or not raw_sources:
            raise RuntimeError("local_crypto_pack requires sources.")
        sources = [resolve_scoped_path(str(item), config, current_dir) for item in raw_sources if str(item).strip()]
        pattern = resolve_crypto_pattern(config, str(action.get("pattern") or ""))
        passphrase = resolve_crypto_passphrase(confirm=True)
        observation = capture_text_output(crypto_pack_paths, output, sources, passphrase, pattern)
        record_agent_artifact(agent, "encrypted_bundle", str(output.resolve()))
        return observation, None
    if kind == "web_search":
        return capture_text_output(print_browser_pages, safe_search_web(str(action.get("query") or ""))), None
    if kind == "web_open":
        return capture_text_output(print_browser_page, fetch_browser_page(str(action.get("url") or ""))), None
    if kind == "playwright_open":
        require_machine_mode(config)
        profile = ensure_playwright_profile(config, str(action.get("profile") or "playwright"))
        result = run_playwright_open(
            profile,
            str(action.get("url") or ""),
            timeout=max(30, min(600, int(action.get("timeout") or profile.get("call_timeout") or MCP_CALL_TIMEOUT))),
        )
        return capture_text_output(print_mcp_call_result, result), None
    if kind == "playwright_call":
        require_machine_mode(config)
        profile = ensure_playwright_profile(config, str(action.get("profile") or "playwright"))
        arguments = action.get("arguments") if isinstance(action.get("arguments"), dict) else {}
        result = run_mcp_stdio(
            profile,
            "call",
            tool_name=str(action.get("tool") or "").strip(),
            arguments=arguments,
            timeout=max(30, min(600, int(action.get("timeout") or profile.get("call_timeout") or MCP_CALL_TIMEOUT))),
        )
        return capture_text_output(print_mcp_call_result, result), None
    if kind == "mcp_tools":
        require_machine_mode(config)
        profile = get_mcp_profile(config, str(action.get("server") or "").strip() or "playwright")
        return capture_text_output(print_mcp_tools, profile), None
    if kind == "mcp_call":
        require_machine_mode(config)
        profile = get_mcp_profile(config, str(action.get("server") or "").strip() or "playwright")
        arguments = action.get("arguments") if isinstance(action.get("arguments"), dict) else {}
        result = run_mcp_stdio(
            profile,
            "call",
            tool_name=str(action.get("tool") or "").strip(),
            arguments=arguments,
            timeout=max(30, min(600, int(action.get("timeout") or profile.get("call_timeout") or MCP_CALL_TIMEOUT))),
        )
        return capture_text_output(print_mcp_call_result, result), None
    if kind.startswith("remote_"):
        require_machine_mode(config)
        server = str(action.get("server") or "").strip()
        profile = get_server_profile(config, server)
        if kind == "remote_ls":
            return capture_text_output(remote_ls, profile, str(action.get("path") or ".")), None
        if kind == "remote_tree":
            depth = max(1, min(int(action.get("depth") or 3), 8))
            limit = max(20, min(int(action.get("limit") or 120), 300))
            return capture_text_output(remote_tree, profile, str(action.get("path") or "."), max_depth=depth, limit=limit), None
        if kind == "remote_scan":
            return capture_text_output(remote_scan, profile, str(action.get("path") or ".")), None
        if kind == "remote_health":
            return capture_text_output(remote_health, profile, str(action.get("path") or ".")), None
        if kind == "remote_read":
            start = int(action.get("start") or 1)
            lines = int(action.get("lines") or MAX_READ_LINES)
            return capture_text_output(remote_read_text, profile, str(action.get("path") or ""), start=start, lines=lines), None
        if kind == "remote_search":
            return capture_text_output(remote_search, profile, str(action.get("pattern") or ""), str(action.get("path") or "."), ignore_case=bool(action.get("ignore_case"))), None
        if kind == "remote_mkdir":
            target = resolve_remote_path(profile, str(action.get("path") or ""))
            observation = capture_text_output(remote_mkdir, profile, str(action.get("path") or ""))
            record_agent_artifact(agent, "remote_created_dir", f"{server}:{target}")
            return observation, None
        if kind == "remote_rm":
            target = resolve_remote_path(profile, str(action.get("path") or ""))
            observation = capture_text_output(remote_rm, profile, str(action.get("path") or ""), recursive=bool(action.get("recursive")))
            record_agent_artifact(agent, "remote_removed_path", f"{server}:{target}")
            return observation, None
        if kind == "remote_exec":
            timeout = max(5, min(3600, int(action.get("timeout") or 180)))
            return capture_text_output(run_remote_command, profile, str(action.get("command") or ""), timeout=timeout), None
        if kind == "remote_write":
            target = resolve_remote_path(profile, str(action.get("path") or ""))
            observation = capture_text_output(remote_write_text, profile, str(action.get("path") or ""), str(action.get("text") or ""), append=False)
            record_agent_artifact(agent, "remote_wrote_file", f"{server}:{target}")
            return observation, None
        if kind == "remote_append":
            target = resolve_remote_path(profile, str(action.get("path") or ""))
            observation = capture_text_output(remote_write_text, profile, str(action.get("path") or ""), str(action.get("text") or ""), append=True)
            record_agent_artifact(agent, "remote_appended_file", f"{server}:{target}")
            return observation, None
        if kind == "remote_replace":
            target = resolve_remote_path(profile, str(action.get("path") or ""))
            observation = capture_text_output(remote_replace_text, profile, str(action.get("path") or ""), str(action.get("old") or ""), str(action.get("new") or ""), replace_all=bool(action.get("all")))
            record_agent_artifact(agent, "remote_updated_file", f"{server}:{target}")
            return observation, None
        raise RuntimeError(f"Unsupported remote action: {kind}")
    if kind == "spawn":
        if int(agent.get("depth") or 0) >= MAX_AGENT_DEPTH:
            raise RuntimeError("Maximum agent depth reached.")
        if len(list(agent.get("children") or [])) >= int(agent.get("worker_limit") or MAX_AGENT_CHILDREN):
            raise RuntimeError("No child worker slots remain for this agent.")
        child = create_agent_state(
            str(action.get("task") or "").strip(),
            name=str(action.get("name") or "").strip() or next_zero_agent_name(),
            role=str(action.get("role") or "worker").strip() or "worker",
            provider=str(agent.get("provider") or ""),
            model=str(agent.get("model") or ""),
            current_dir=current_dir,
            max_steps=max(2, min(6, int(action.get("steps") or 4))),
            worker_limit=max(0, min(2, int(agent.get("worker_limit") or MAX_AGENT_CHILDREN))),
            parent=str(agent.get("name") or ""),
            depth=int(agent.get("depth") or 0) + 1,
            attachments=list(agent.get("attachments") or []),
            url_attachments=list(agent.get("url_attachments") or []),
            briefing=str(agent.get("briefing") or ""),
        )
        child_name = str(child.get("name") or "")
        children = list(agent.get("children") or [])
        children.append(child_name)
        agent["children"] = children
        child_result = run_agent_until_stop(child_name, max_steps=int(child.get("max_steps") or 4), silent=False)
        summary = str(child_result.get("final_reply") or child_result.get("status") or "child finished")
        return f"Spawned {child_name}. Child result:\n{summary}", child_name
    raise RuntimeError(f"Unsupported autopilot action: {kind}")


def run_builtin_autopilot_shortcut(agent: dict, silent: bool = False) -> Optional[dict]:
    task = str(agent.get("task") or "")
    if not looks_like_small_game_request(task):
        return None
    title = infer_game_title(task)
    action = {
        "action": "local_scaffold_game",
        "path": safe_project_slug(title).lower(),
        "title": title,
        "reason": "The request is a simple playable game scaffold, so Agent Zero can create the files directly.",
        "progress": "Creating a small browser game project with no external dependencies.",
        "plan": ["Create game files", "Record the project path", "Report how to run it"],
    }
    if not silent:
        print(f"[{agent.get('name')}] step {int(agent.get('step_count') or 0) + 1}/{agent.get('max_steps')} -> {action['action']}")
        print(f"reason: {action['reason']}")
        print(f"progress: {action['progress']}")
    observation, child_name = execute_agent_action(agent, action)
    agent["step_count"] = int(agent.get("step_count") or 0) + 1
    agent["status"] = "done"
    agent["progress_note"] = str(action["progress"])
    agent["working_plan"] = list(action["plan"])
    touched = list(agent.get("artifacts") or [])
    game_path = str(touched[-1].get("path") or "") if touched else ""
    agent["final_reply"] = (
        "Created a playable small browser game."
        + (f"\n\nProject: {game_path}" if game_path else "")
        + "\n\nRun it by opening `index.html`, or run `python -m http.server 8080` inside the project folder."
    )
    log_entry = {
        "step": agent["step_count"],
        "action": action["action"],
        "reason": action["reason"],
        "progress": action["progress"],
        "plan": list(action["plan"]),
        "payload": compact_agent_payload(action),
        "observation": observation,
        "child": child_name or "",
        "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
    }
    agent_log = list(agent.get("log") or [])
    agent_log.append(log_entry)
    agent["log"] = trim_agent_log(agent_log)
    save_agent_state(agent)
    if not silent:
        print(safe_console_text(observation))
        print("")
    return agent


def run_agent_step(name: str, silent: bool = False) -> dict:
    agent = load_saved_agent(name)
    if agent.get("status") == "done":
        return agent
    if boolish(agent.get("stop_requested", False)):
        agent["status"] = "stopped"
        if not str(agent.get("final_reply") or "").strip():
            agent["final_reply"] = str(agent.get("stop_note") or "Stopped by operator.").strip()
        save_agent_state(agent)
        return agent
    if int(agent.get("step_count") or 0) >= int(agent.get("max_steps") or MAX_AGENT_STEPS):
        agent["status"] = "stopped"
        save_agent_state(agent)
        return agent
    agent["status"] = "running"
    save_agent_state(agent)
    cwd = pathlib.Path(str(agent.get("current_dir") or pathlib.Path.cwd())).expanduser().resolve()
    try:
        prompt = build_autopilot_prompt(agent)
        reply = call_agent_model(prompt, str(agent.get("provider") or "groq"), str(agent.get("model") or ""), cwd)
        try:
            action = extract_json_object(reply)
        except Exception:
            action = run_agent_repair(agent, reply)
        reason = str(action.get("reason") or "").strip()
        progress = normalize_agent_progress(action.get("progress") or "")
        working_plan = normalize_agent_plan(action.get("plan") or [])
        if not silent:
            print(f"[{agent.get('name')}] step {int(agent.get('step_count') or 0) + 1}/{agent.get('max_steps')} -> {action.get('action')}")
            if reason:
                print(f"reason: {reason}")
            if progress:
                print(f"progress: {safe_console_text(progress)}")
        observation, child_name = execute_agent_action(agent, action)
        agent["step_count"] = int(agent.get("step_count") or 0) + 1
        if progress:
            agent["progress_note"] = progress
        if working_plan:
            agent["working_plan"] = working_plan
        log_entry = {
            "step": agent["step_count"],
            "action": str(action.get("action") or ""),
            "reason": reason,
            "progress": progress,
            "plan": working_plan,
            "payload": compact_agent_payload(action),
            "observation": observation,
            "child": child_name or "",
            "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
        }
        agent_log = list(agent.get("log") or [])
        agent_log.append(log_entry)
        agent["log"] = trim_agent_log(agent_log)
        if str(action.get("action") or "").strip().lower() == "done":
            agent["status"] = "done"
        save_agent_state(agent)
        if not silent:
            print(safe_console_text(observation))
            print("")
        return agent
    except Exception as exc:
        agent["status"] = "error"
        agent["step_count"] = int(agent.get("step_count") or 0) + 1
        agent_log = list(agent.get("log") or [])
        agent_log.append(
            {
                "step": agent["step_count"],
                "action": "error",
                "reason": "Autopilot step failed",
                "payload": {},
                "observation": str(exc),
                "child": "",
                "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
            }
        )
        agent["log"] = trim_agent_log(agent_log)
        save_agent_state(agent)
        if not silent:
            print(str(exc))
            print("")
        return agent


def run_agent_until_stop(name: str, max_steps: Optional[int] = None, silent: bool = False) -> dict:
    agent = load_saved_agent(name)
    budget = max_steps if max_steps is not None else int(agent.get("max_steps") or MAX_AGENT_STEPS)
    budget = max(1, budget)
    for _ in range(budget):
        agent = load_saved_agent(name)
        if agent.get("status") in {"done", "error"}:
            break
        if boolish(agent.get("stop_requested", False)):
            agent["status"] = "stopped"
            if not str(agent.get("final_reply") or "").strip():
                agent["final_reply"] = str(agent.get("stop_note") or "Stopped by operator.").strip()
            save_agent_state(agent)
            break
        if int(agent.get("step_count") or 0) >= int(agent.get("max_steps") or MAX_AGENT_STEPS):
            agent["status"] = "stopped"
            save_agent_state(agent)
            break
        try:
            agent = run_agent_step(name, silent=silent)
        except KeyboardInterrupt:
            stopped = request_agent_stop(name, "Stopped by operator from the current terminal.")
            stopped["status"] = "stopped"
            if not str(stopped.get("final_reply") or "").strip():
                stopped["final_reply"] = str(stopped.get("stop_note") or "Stopped by operator.").strip()
            save_agent_state(stopped)
            if not silent:
                print("\nStop requested. Agent halted.\n")
            return load_saved_agent(name)
        if agent.get("status") in {"done", "error"}:
            break
    final_agent = load_saved_agent(name)
    if (
        final_agent.get("status") not in {"done", "error", "stopped"}
        and int(final_agent.get("step_count") or 0) >= int(final_agent.get("max_steps") or MAX_AGENT_STEPS)
    ):
        final_agent["status"] = "stopped"
        save_agent_state(final_agent)
        final_agent = load_saved_agent(name)
    final_text = synthesize_agent_final_reply(final_agent)
    if final_text and final_text != str(final_agent.get("final_reply") or "").strip():
        final_agent["final_reply"] = final_text
        save_agent_state(final_agent)
    return final_agent


def start_autopilot(
    task: str,
    name: Optional[str] = None,
    provider: Optional[str] = None,
    model: Optional[str] = None,
    cwd: Optional[pathlib.Path] = None,
    steps: int = MAX_AGENT_STEPS,
    workers: int = MAX_AGENT_CHILDREN,
    role: str = "autopilot",
    attachments: Optional[List[str]] = None,
    url_attachments: Optional[List[str]] = None,
    briefing: str = "",
) -> dict:
    agent = create_agent_state(
        task=task,
        name=name,
        role=role,
        provider=provider,
        model=model,
        current_dir=cwd,
        max_steps=steps,
        worker_limit=workers,
        attachments=attachments,
        url_attachments=url_attachments,
        briefing=briefing,
    )
    shortcut = run_builtin_autopilot_shortcut(agent, silent=False)
    if shortcut is not None:
        return shortcut
    return run_agent_until_stop(str(agent.get("name") or ""), max_steps=steps, silent=False)


def logout() -> int:
    config = load_config()
    try:
        api_cli(config, "revoke", auth=True)
    except Exception:
        pass
    config["auth_token"] = ""
    config["auth_expires_at"] = ""
    config["user_email"] = ""
    save_config(config)
    print("Logged out.")
    return 0


def clear_screen() -> None:
    os.system("cls" if os.name == "nt" else "clear")


def show_session_status(
    config: dict,
    provider: str,
    model: str,
    zero_mode: bool,
    use_web: bool,
    attachments: List[str],
    plan_items: Optional[List[dict]],
    url_attachments: Optional[List[str]],
    browser_pages: Optional[List[dict]],
    current_dir: pathlib.Path,
    transcript: Optional[List[dict]] = None,
) -> None:
    access_mode = access_mode_from(config)
    try:
        status_mode, _status_key, status_source = resolve_chat_mode(config, provider)
    except Exception:
        status_mode, status_source = ("account" if str(config.get("auth_token") or "").strip() else "direct"), ""
    route = current_runtime_route(config, provider, model, status_mode, requested_source=status_source)
    print(f"Provider: {provider}")
    print(f"Model: {model}")
    api_host = str(route.get("api_host") or "")
    if api_host:
        print(f"API host: {api_host}")
    actual_mode = str(route.get("actual_mode") or "")
    actual_source = str(route.get("actual_source") or "")
    if actual_mode == "direct":
        print(f"Auth lane: local key ({actual_source})")
    elif actual_mode == "account":
        print("Auth lane: linked account")
    fallback_from = str(route.get("fallback_from") or "")
    if fallback_from and fallback_from != actual_mode:
        print(f"Last route fallback: {fallback_from} -> {actual_mode}")
    print("CLI trace mode: off")
    print(f"Web search: {'on' if use_web else 'off'}")
    print(f"Access: {access_mode}")
    print(f"Current dir: {current_dir}")
    workspace_root = workspace_root_from_config(config)
    if workspace_root:
        print(f"Workspace root: {workspace_root}")
    print(f"Project context: {'on' if project_context_enabled(config) else 'off'}")
    context_path, _context_text = read_project_context(current_dir, config)
    if context_path:
        print(f"Context file: {context_path}")
    print(f"Attachments: {len(attachments)}")
    if attachments:
        for item in attachments[:8]:
            print(f"  - {item}")
        if len(attachments) > 8:
            print("  ...more attachments omitted...")
    print(f"URL attachments: {len(url_attachments or [])}")
    if url_attachments:
        for item in list(url_attachments)[:6]:
            print(f"  - {item}")
        if len(url_attachments) > 6:
            print("  ...more URL attachments omitted...")
    browser_count = len(normalize_browser_pages(browser_pages))
    if browser_count:
        print(f"Browser pages: {browser_count}")
        for item in normalize_browser_pages(browser_pages)[:4]:
            print(f"  - {safe_console_text(str(item.get('title') or item.get('url') or '').strip())}")
        if browser_count > 4:
            print("  ...more browser pages omitted...")
    else:
        print("Browser pages: 0")
    plan_count = len(normalize_plan_items(plan_items))
    if plan_count:
        plan_done = sum(1 for item in normalize_plan_items(plan_items) if item.get("done"))
        print(f"Plan board: {plan_count - plan_done} open / {plan_done} done")
    else:
        print("Plan board: empty")
    print(f"Local session turns: {len(transcript or [])}")


def self_update(config: Optional[dict] = None) -> int:
    config = config or load_config()
    url = f"{base_url_from(config)}/cli-assets/zerothink.py"
    hash_url = f"{url}.sha256"
    target = pathlib.Path(__file__).resolve()
    backup = target.with_suffix(".py.bak")
    req = urllib.request.Request(url, headers={"User-Agent": f"ZeroThinkCLI/{VERSION}"}, method="GET")
    with urllib.request.urlopen(req, timeout=240) as response:
        raw = response.read()
    hash_req = urllib.request.Request(hash_url, headers={"User-Agent": f"ZeroThinkCLI/{VERSION}"}, method="GET")
    with urllib.request.urlopen(hash_req, timeout=60) as response:
        expected_hash = response.read().decode("utf-8", errors="replace").strip().split()[0]
    if not re.fullmatch(r"[a-fA-F0-9]{64}", expected_hash):
        raise RuntimeError(f"Update hash from {hash_url} is invalid.")
    actual_hash = hashlib.sha256(raw).hexdigest()
    if actual_hash.lower() != expected_hash.lower():
        raise RuntimeError("Update hash verification failed. Refusing to overwrite the local CLI.")
    content = raw.decode("utf-8", errors="replace")
    if "ZERO THINK CLI" not in content and "ZeroThink CLI" not in content:
        raise RuntimeError("Downloaded update does not look like a valid ZeroThink CLI script.")
    if target.exists():
        shutil.copy2(target, backup)
    write_text_portable(target, content)
    print(f"Updated CLI script from {url}")
    print(f"Path: {target}")
    print("Restart the CLI to load the new version.")
    return 0


def zip_list(path: pathlib.Path) -> None:
    for name, size in list_archive_members(path):
        print(f"{size:>10,}  {name}")


def zip_extract(path: pathlib.Path, dest: pathlib.Path) -> None:
    dest.mkdir(parents=True, exist_ok=True)
    lower = path.name.lower()
    if lower.endswith(".zip"):
        with zipfile.ZipFile(path) as zf:
            for info in zf.infolist():
                if info.is_dir():
                    continue
                member_path = (dest / pathlib.PurePosixPath(info.filename)).resolve()
                if dest.resolve() not in [member_path, *member_path.parents]:
                    raise RuntimeError(f"Unsafe archive entry blocked: {info.filename}")
                member_path.parent.mkdir(parents=True, exist_ok=True)
                with zf.open(info) as src, member_path.open("wb") as dst:
                    shutil.copyfileobj(src, dst)
    else:
        mode = "r:gz" if lower.endswith(".tar.gz") or lower.endswith(".tgz") else "r:"
        with tarfile.open(path, mode) as tf:
            for member in tf.getmembers():
                if not member.isfile():
                    continue
                member_path = (dest / pathlib.PurePosixPath(member.name)).resolve()
                if dest.resolve() not in [member_path, *member_path.parents]:
                    raise RuntimeError(f"Unsafe archive entry blocked: {member.name}")
                member_path.parent.mkdir(parents=True, exist_ok=True)
                extracted = tf.extractfile(member)
                if extracted is None:
                    continue
                with extracted, member_path.open("wb") as dst:
                    shutil.copyfileobj(extracted, dst)


def zip_create(output: pathlib.Path, sources: List[pathlib.Path]) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for source in sources:
            source = source.resolve()
            if source.is_dir():
                for file in source.rglob("*"):
                    if file.is_file():
                        zf.write(file, file.relative_to(source.parent).as_posix())
            else:
                zf.write(source, source.name)


def repl() -> int:
    config = load_config()
    provider = str(config.get("provider", "groq")).lower()
    if provider not in PROVIDERS:
        provider = "groq"
    model = normalize_model(provider, str(config.get("model", PROVIDERS[provider]["default_model"])))
    zero_mode = boolish(config.get("zero_mode", False))
    use_web = boolish(config.get("use_web", False))
    attachments: List[str] = []
    url_attachments: List[str] = []
    browser_pages: List[dict] = []
    plan_items: List[dict] = []
    transcript: List[dict] = []
    pending_action_task = ""
    pending_action_preview = ""
    last_agent_name = ""
    workspace_root = workspace_root_from_config(config)
    if access_mode_from(config) == "folder":
        if workspace_root is None:
            workspace_root = pathlib.Path.cwd().resolve()
            config["workspace_root"] = str(workspace_root)
            save_config(config)
        current_dir = workspace_root
        os.chdir(str(current_dir))
    else:
        if workspace_root and workspace_root.exists() and workspace_root.is_dir():
            current_dir = workspace_root.resolve()
            os.chdir(str(current_dir))
        else:
            current_dir = pathlib.Path.cwd().resolve()
    session = build_repl_prompt_session(lambda: current_dir, lambda: provider)

    print_banner()
    if str(config.get("auth_token") or "").strip():
        print("Linked-account mode active. Use /login if this machine is not linked yet.")
    else:
        key_ready, key_source = local_direct_key(config, provider)
        if key_ready:
            print(f"Direct key mode active for {provider.upper()} via {key_source}.")
        else:
            print("No linked account token found. Use /login or /key set groq|nvidia.")
    print("New here? Type /about, /guide, /tutorial, or /help quickstart.")
    print("Need examples? Type /help examples.")
    if session is not None:
        print("Autocomplete is on. Try TAB after `/`, `/he`, `/provider`, `/model`, or a path command.")
    if project_context_enabled(config):
        context_path, _context_text = read_project_context(current_dir, config)
        if context_path:
            print(f"Project context loaded from {context_path.name}. Use /context show to inspect it.")
    custom_path, custom_commands = load_custom_commands(current_dir, config)
    if custom_path and custom_commands:
        print(f"Custom command pack loaded from {custom_path.name}. Use /custom show to inspect it.")
    print(f"Steering mode: {steering_mode_from(config)}. Use /steer auto|plan|confirm.")
    print("Type /help for commands.\n")

    while True:
        try:
            line = read_repl_line(session, "ZERO> ").strip()
        except (EOFError, KeyboardInterrupt):
            print("\nSession closed.")
            return 0

        if not line:
            continue

        lowered_line = line.lower()
        if lowered_line.startswith("zerothink "):
            line = "/" + line[len("zerothink "):].strip()
        elif lowered_line.startswith("zt "):
            line = "/" + line[len("zt "):].strip()

        if line == "/":
            print_cli_help("commands", interactive=True)
            continue

        if line.startswith("!"):
            try:
                require_machine_mode(load_config())
                run_local_command(line[1:].strip(), cwd=current_dir)
            except Exception as exc:
                print(str(exc))
            continue

        config = load_config()
        custom_path, custom_commands = load_custom_commands(current_dir, config)

        if line.lower() in {"login", "log in", "link account", "connect account", "connect zerothink", "zerothink login"}:
            try:
                login_flow()
            except Exception as exc:
                print(str(exc))
            continue

        if line.startswith("/"):
            parts = split_repl_command_line(line[1:])
            cmd = resolve_repl_command_name(parts[0].lower()) if parts else ""
            args = parts[1:]
            if cmd in {"quit", "exit"}:
                print("Session closed.")
                return 0
            if cmd == "help":
                topic = args[0] if args else "commands"
                print_cli_help(topic, interactive=True)
                continue
            if cmd == "about":
                config = load_config()
                try:
                    runtime_mode, _runtime_key, runtime_source = resolve_chat_mode(config, provider)
                except Exception:
                    runtime_mode, runtime_source = ("account" if str(config.get("auth_token") or "").strip() else "direct"), ""
                print("")
                print(format_agent_zero_identity(current_runtime_route(config, provider, model, runtime_mode, requested_source=runtime_source)))
                print("")
                continue
            if cmd == "capabilities":
                config = load_config()
                try:
                    runtime_mode, _runtime_key, runtime_source = resolve_chat_mode(config, provider)
                except Exception:
                    runtime_mode, runtime_source = ("account" if str(config.get("auth_token") or "").strip() else "direct"), ""
                print("")
                print(format_agent_zero_capabilities(current_runtime_route(config, provider, model, runtime_mode, requested_source=runtime_source)))
                print("")
                continue
            if cmd == "skills":
                print("")
                print_skill_catalog()
                print("")
                continue
            if cmd in {"guide", "start"}:
                print_cli_help("quickstart", interactive=True)
                continue
            if cmd == "tutorial":
                try:
                    run_first_run_tutorial(force=True, startup=False)
                    config = load_config()
                    provider = str(config.get("provider", "groq")).lower()
                    if provider not in PROVIDERS:
                        provider = "groq"
                    model = normalize_model(provider, str(config.get("model", PROVIDERS[provider]["default_model"])))
                    zero_mode = boolish(config.get("zero_mode", False))
                    use_web = boolish(config.get("use_web", False))
                    if access_mode_from(config) == "folder":
                        workspace_root = workspace_root_from_config(config)
                        if workspace_root:
                            current_dir = workspace_root
                            os.chdir(str(current_dir))
                    else:
                        current_dir = pathlib.Path.cwd().resolve()
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "tips":
                print_cli_help("examples", interactive=True)
                continue
            if cmd == "login":
                login_flow()
                continue
            if cmd == "logout":
                logout()
                continue
            if cmd == "status":
                show_session_status(load_config(), provider, model, zero_mode, use_web, attachments, plan_items, url_attachments, browser_pages, current_dir, transcript=transcript)
                continue
            if cmd == "runtime":
                config = load_config()
                try:
                    runtime_mode, _runtime_key, runtime_source = resolve_chat_mode(config, provider)
                except Exception:
                    runtime_mode, runtime_source = ("account" if str(config.get("auth_token") or "").strip() else "direct"), ""
                print("")
                print(format_runtime_route(current_runtime_route(config, provider, model, runtime_mode, requested_source=runtime_source)))
                print("")
                continue
            if cmd == "steer":
                config = load_config()
                raw_subcmd = str(args[0]).strip().lower() if args else "status"
                if not args or (args and str(args[0]).strip().lower() == "status"):
                    print(f"Steering mode: {steering_mode_from(config)}")
                    print("Modes: auto, plan, confirm")
                    if pending_action_task:
                        print("")
                        print("There is one staged task waiting. Use /go to run it or /stop to discard it.")
                    continue
                config["steering_mode"] = normalize_steering_mode(raw_subcmd)
                save_config(config)
                print(f"Steering mode set to {config['steering_mode']}")
                continue
            if cmd == "go":
                if not pending_action_task:
                    print("No staged task is waiting. Ask for a task first or use /steer plan to stage one.")
                    continue
                try:
                    print("[agent-zero] executing staged task with the local tool stack...\n")
                    agent = start_autopilot(
                        task=pending_action_task,
                        name=None,
                        provider=provider,
                        model=model,
                        cwd=current_dir,
                        steps=16,
                        workers=3,
                        role="controller",
                        attachments=attachments,
                        url_attachments=url_attachments,
                        briefing=format_transcript_for_context(list(transcript)[-6:]),
                    )
                    last_agent_name = str(agent.get("name") or "")
                    reply_text = safe_console_text(str(agent.get("final_reply") or agent.get("status") or "").strip())
                    thought = ""
                    print("Final:")
                    print(reply_text)
                    print("")
                    append_transcript_turn(transcript, pending_action_preview or pending_action_task, reply_text, thought)
                except Exception as exc:
                    print(str(exc))
                pending_action_task = ""
                pending_action_preview = ""
                continue
            if cmd == "stop":
                if pending_action_task:
                    pending_action_task = ""
                    pending_action_preview = ""
                    print("Discarded the staged task.")
                    continue
                target_name = str(args[0] if args else "").strip() or last_agent_name or latest_stoppable_agent_name()
                if not target_name:
                    print("Nothing staged and no active saved agent was found to stop.")
                    continue
                try:
                    agent = request_agent_stop(target_name)
                    print(f"Stop requested for `{agent.get('name')}`.")
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "clear":
                clear_screen()
                continue
            if cmd == "doctor":
                doctor()
                continue
            if cmd == "update":
                try:
                    self_update(load_config())
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd in {"library", "lib"}:
                subcmd = args[0].lower() if args else "status"
                try:
                    if subcmd == "status":
                        library_status()
                        continue
                    if subcmd == "add" and len(args) >= 2:
                        library_add_path(args[1], current_dir)
                        continue
                    if subcmd in {"remove", "rm"} and len(args) >= 2:
                        library_remove_path(args[1], current_dir)
                        continue
                    if subcmd in {"index", "reindex"}:
                        index = library_build_index(force=True)
                        print(f"Rebuilt Zero Library index: {index.get('count', 0)} file(s).")
                        continue
                    if subcmd == "files":
                        library_print_files()
                        continue
                    if subcmd == "search" and len(args) >= 2:
                        library_print_search(" ".join(args[1:]))
                        continue
                    if subcmd == "context" and len(args) >= 2:
                        print(library_context_pack(" ".join(args[1:])) or "No Zero Library context found.")
                        continue
                except Exception as exc:
                    print(str(exc))
                    continue
                print("Usage: /library status|add PATH|remove PATH|index|files|search QUERY|context QUERY")
                continue
            if cmd == "dna":
                subcmd = args[0].lower() if args else "analyze"
                raw_value = " ".join(args[1:] if subcmd == "analyze" else args).strip()
                if subcmd == "analyze" and raw_value:
                    try:
                        print(dna_markdown(dna_analyze(str(resolve_cli_path(raw_value, current_dir)) if pathlib.Path(raw_value).expanduser().exists() else raw_value)))
                    except Exception as exc:
                        print(str(exc))
                    continue
                print("Usage: /dna analyze SEQUENCE_OR_FILE")
                continue
            if cmd == "key":
                subcmd = args[0].lower() if args else "status"
                try:
                    if subcmd == "status":
                        print_direct_key_status(load_config())
                        continue
                    if subcmd == "set" and len(args) >= 2:
                        set_direct_key(args[1], args[2] if len(args) >= 3 else "")
                        continue
                    if subcmd == "clear" and len(args) >= 2:
                        clear_direct_key(args[1])
                        continue
                except Exception as exc:
                    print(str(exc))
                    continue
                print("Usage: /key status | /key set groq [KEY] | /key clear groq")
                continue
            if cmd in {"whoami", "me"}:
                try:
                    show_account_summary(api_cli(load_config(), "me", auth=True).get("user", {}))
                except Exception as exc:
                    print(f"Identity check failed: {exc}")
                    print_direct_key_status(load_config())
                continue
            if cmd == "history":
                if not transcript:
                    print("No local session turns yet.")
                    continue
                for item in transcript[-MAX_HISTORY_PREVIEW:]:
                    role = str(item.get("role") or "assistant").upper()
                    content = str(item.get("content") or "").strip().replace("\r", "")
                    preview = content.splitlines()[0][:140] if content else "(empty)"
                    print(f"{role}: {preview}")
                continue
            if cmd == "checkpoint":
                try:
                    saved = save_session_checkpoint(
                        args[0] if args else "",
                        transcript,
                        provider,
                        model,
                        zero_mode,
                        use_web,
                        current_dir,
                        attachments,
                        plan_items=plan_items,
                        url_attachments=url_attachments,
                        browser_pages=browser_pages,
                    )
                    print(f"Saved local checkpoint to {saved}")
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "resume":
                if not args:
                    print_saved_sessions()
                    continue
                try:
                    saved = load_saved_session(args[0])
                    transcript = list(saved.get("transcript") or [])
                    provider = str(saved.get("provider") or provider).lower()
                    if provider not in PROVIDERS:
                        provider = "groq"
                    model = normalize_model(provider, str(saved.get("model") or model))
                    zero_mode = False
                    use_web = bool(saved.get("use_web", use_web))
                    restored_attachments = []
                    for item in list(saved.get("attachments") or []):
                        try:
                            restored_attachments.append(str(resolve_scoped_path(item, load_config(), current_dir)))
                        except Exception:
                            continue
                    attachments = restored_attachments
                    plan_items = normalize_plan_items(saved.get("plan_items"))
                    url_attachments = [str(item).strip() for item in list(saved.get("url_attachments") or []) if str(item).strip()]
                    browser_pages = normalize_browser_pages(saved.get("browser_pages"))
                    saved_dir = str(saved.get("current_dir") or "").strip()
                    if saved_dir:
                        try:
                            target_dir = resolve_scoped_path(saved_dir, load_config(), current_dir)
                            if target_dir.exists() and target_dir.is_dir():
                                current_dir = target_dir
                                os.chdir(str(current_dir))
                        except Exception:
                            pass
                    print(
                        f"Resumed local session `{saved.get('name')}` "
                        f"with {len(transcript)} turns on {provider}/{model}."
                    )
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "export":
                try:
                    target = resolve_scoped_path(args[0], load_config(), current_dir) if args else None
                    exported = export_session_markdown(
                        transcript,
                        provider,
                        model,
                        zero_mode,
                        use_web,
                        current_dir,
                        attachments,
                        plan_items=plan_items,
                        url_attachments=url_attachments,
                        browser_pages=browser_pages,
                        output_path=target,
                    )
                    print(f"Exported local session to {exported}")
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "new":
                transcript = []
                attachments = []
                url_attachments = []
                browser_pages = []
                plan_items = []
                pending_action_task = ""
                pending_action_preview = ""
                print("Cleared the current local session and attachments.")
                continue
            if cmd == "context":
                subcmd = args[0].lower() if args else "status"
                try:
                    config = load_config()
                    if subcmd in {"status", "reload"}:
                        print_context_status(current_dir, config, show_preview=False)
                        continue
                    if subcmd == "show":
                        print_context_status(current_dir, config, show_preview=True)
                        continue
                    if subcmd in {"on", "off"}:
                        config["project_context_enabled"] = subcmd == "on"
                        save_config(config)
                        print_context_status(current_dir, config, show_preview=False)
                        continue
                except Exception as exc:
                    print(str(exc))
                    continue
                print("Usage: /context status | /context show | /context on | /context off")
                continue
            if cmd == "custom":
                print_custom_command_status(current_dir, load_config(), show_detail=(args and args[0].lower() == "show"))
                continue
            if cmd == "plugins":
                print("Plugin layer:")
                print_custom_command_status(current_dir, load_config(), show_detail=False)
                print("")
                print_mcp_servers(load_config())
                continue
            if cmd == "files":
                if not attachments and not url_attachments:
                    print("No attachments.")
                    continue
                if attachments:
                    print("Local attachments:")
                    print("\n".join(f"- {item}" for item in attachments))
                if url_attachments:
                    if attachments:
                        print("")
                    print("URL attachments:")
                    print("\n".join(f"- {item}" for item in url_attachments))
                continue
            if cmd in {"plan", "todo"}:
                try:
                    plan_items = handle_plan_action(plan_items, args)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "search-web" and args:
                try:
                    query = " ".join(args).strip()
                    results = safe_search_web(query)
                    browser_pages = normalize_browser_pages(results + browser_pages)
                    print(f"Stored {len(results)} search results for: {query}")
                    print_browser_pages(results)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "pages":
                print_browser_pages(browser_pages)
                continue
            if cmd == "page" and args:
                try:
                    index = int(args[0])
                    if index < 1 or index > len(browser_pages):
                        raise RuntimeError(f"Page index out of range. Current size: {len(browser_pages)}")
                    item = dict(browser_pages[index - 1])
                    if not str(item.get("text") or "").strip():
                        item = fetch_browser_page(str(item.get("url") or ""), str(item.get("title") or ""), str(item.get("snippet") or ""))
                        browser_pages = upsert_browser_page(browser_pages, item)
                    print_browser_page(item, index=index)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "attach-page" and args:
                try:
                    index = int(args[0])
                    if index < 1 or index > len(browser_pages):
                        raise RuntimeError(f"Page index out of range. Current size: {len(browser_pages)}")
                    url = str(browser_pages[index - 1].get("url") or "").strip()
                    if not url:
                        raise RuntimeError("That page does not have a usable URL.")
                    if url not in url_attachments:
                        if len(url_attachments) >= MAX_URL_ATTACHMENTS:
                            raise RuntimeError(f"Too many URL attachments. Limit: {MAX_URL_ATTACHMENTS}")
                        url_attachments.append(url)
                    print(f"Attached page {index}: {url}")
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "clear-pages":
                browser_pages = []
                print("Local browser pages cleared.")
                continue
            if cmd == "browse" and args:
                try:
                    page = fetch_browser_page(args[0])
                    browser_pages = upsert_browser_page(browser_pages, page)
                    print_browser_page(page, index=1)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "fetch" and args:
                try:
                    print(fetch_url_text(args[0]))
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "attach-url" and args:
                url = str(args[0]).strip()
                if url not in url_attachments:
                    if len(url_attachments) >= MAX_URL_ATTACHMENTS:
                        print(f"Too many URL attachments. Limit: {MAX_URL_ATTACHMENTS}")
                        continue
                    url_attachments.append(url)
                print(f"Attached URL {url}")
                continue
            if cmd == "detach-url":
                if not args or args[0].lower() == "all":
                    url_attachments = []
                    print("URL attachments cleared.")
                else:
                    needle = str(args[0]).strip()
                    url_attachments = [item for item in url_attachments if item != needle]
                    print(f"Detached URL {needle}")
                continue
            if cmd == "review" and args:
                target = args[0]
                review_files: List[str] = []
                try:
                    review_files.append(str(resolve_scoped_path(target, load_config(), current_dir)))
                except Exception:
                    pass
                try:
                    context_path, context_text = read_project_context(current_dir, load_config())
                    result = run_chat(
                        build_review_prompt(target, " ".join(args[1:])),
                        provider,
                        model,
                        review_files,
                        url_attachments,
                        None,
                        zero_mode,
                        use_web,
                        transcript=transcript,
                        context_path=context_path,
                        context_text=context_text,
                        current_dir_override=current_dir,
                    )
                    reply_text, thought = print_agent_result(result)
                    append_transcript_turn(transcript, f"/review {' '.join(args)}", reply_text, thought)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "pdf":
                try:
                    if len(args) < 2 or args[0].lower() != "create":
                        print("Usage: /pdf create OUT.pdf [--title TITLE] [--text TEXT] [--file PATH]")
                        continue
                    output_arg = args[1]
                    title = ""
                    body = ""
                    source_file = ""
                    i = 2
                    while i < len(args):
                        token = args[i]
                        if token == "--title" and i + 1 < len(args):
                            title = args[i + 1]
                            i += 2
                            continue
                        if token == "--text" and i + 1 < len(args):
                            body = args[i + 1]
                            i += 2
                            continue
                        if token == "--file" and i + 1 < len(args):
                            source_file = args[i + 1]
                            i += 2
                            continue
                        body = (body + " " if body else "") + token
                        i += 1
                    config_now = load_config()
                    output = resolve_scoped_path(output_arg, config_now, current_dir)
                    if source_file:
                        source = resolve_scoped_path(source_file, config_now, current_dir)
                        body = (body + "\n\n" if body else "") + source.read_text(encoding="utf-8", errors="replace")
                    if not body:
                        print("Usage: /pdf create OUT.pdf --text TEXT or --file PATH")
                        continue
                    create_text_pdf(output, title or output.stem, body)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd in {"autopilot", "team"} and args:
                try:
                    task = " ".join(args).strip()
                    agent = start_autopilot(
                        task=task,
                        name=None,
                        provider=provider,
                        model=model,
                        cwd=current_dir,
                        steps=8,
                        workers=3,
                        role="controller",
                        attachments=attachments,
                        url_attachments=url_attachments,
                        briefing=format_transcript_for_context(list(transcript)[-6:]),
                    )
                    last_agent_name = str(agent.get("name") or "")
                    print("Final:")
                    print(safe_console_text(str(agent.get("final_reply") or agent.get("status") or "").strip()))
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "agent":
                try:
                    if not args or args[0].lower() == "list":
                        print_saved_agents()
                        continue
                    sub = args[0].lower()
                    if sub == "show" and len(args) >= 2:
                        print_agent_summary(load_saved_agent(args[1]))
                        continue
                    if sub == "log" and len(args) >= 2:
                        print_agent_log(load_saved_agent(args[1]))
                        continue
                    if sub == "rm" and len(args) >= 2:
                        remove_saved_agent(args[1])
                        print(f"Removed agent `{safe_agent_name(args[1])}`.")
                        continue
                    if sub == "create" and len(args) >= 3:
                        task = " ".join(args[2:]).strip()
                        agent = create_agent_state(
                            task=task,
                            name=args[1],
                            role="general",
                            provider=provider,
                            model=model,
                            current_dir=current_dir,
                            max_steps=8,
                            worker_limit=2,
                        )
                        print(f"Created agent `{agent.get('name')}`.")
                        continue
                    if sub == "step" and len(args) >= 2:
                        run_agent_step(args[1], silent=False)
                        last_agent_name = str(args[1]).strip()
                        continue
                    if sub == "run" and len(args) >= 2:
                        final_agent = run_agent_until_stop(args[1], max_steps=8, silent=False)
                        last_agent_name = str(final_agent.get("name") or args[1]).strip()
                        print("Final:")
                        print(safe_console_text(str(final_agent.get("final_reply") or final_agent.get("status") or "").strip()))
                        continue
                    if sub == "stop" and len(args) >= 2:
                        stopped = request_agent_stop(args[1])
                        print(f"Stop requested for `{stopped.get('name')}`.")
                        continue
                    print("Usage: /agent list | /agent show NAME | /agent log NAME | /agent rm NAME | /agent create NAME TASK | /agent step NAME | /agent run NAME | /agent stop NAME")
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "access":
                if not args or args[0].lower() == "status":
                    show_access_status(load_config())
                    continue
                if args[0].lower() == "machine":
                    config = set_access_mode("machine")
                    print("Access mode set to machine.")
                    show_access_status(config)
                    continue
                if args[0].lower() == "folder":
                    target = resolve_cli_path(args[1], current_dir) if len(args) >= 2 else current_dir
                    if not target.exists() or not target.is_dir():
                        print(f"Directory not found: {target}")
                        continue
                    config = set_access_mode("folder", workspace=target)
                    current_dir = target.resolve()
                    os.chdir(str(current_dir))
                    print("Access mode set to folder.")
                    show_access_status(config)
                    continue
                print("Usage: /access status | /access machine | /access folder [PATH]")
                continue
            if cmd == "workspace":
                if not args:
                    root = workspace_root_from_config(load_config())
                    print(root if root else "No workspace root is set.")
                    continue
                target = resolve_cli_path(args[0], current_dir)
                if not target.exists() or not target.is_dir():
                    print(f"Directory not found: {target}")
                    continue
                config = load_config()
                config["workspace_root"] = str(target.resolve())
                save_config(config)
                if access_mode_from(config) == "folder":
                    current_dir = target.resolve()
                    os.chdir(str(current_dir))
                print(f"Workspace root set to {target.resolve()}")
                continue
            if cmd == "server":
                try:
                    config_now = load_config()
                    if not args or args[0].lower() == "list":
                        print_server_profiles(config_now)
                        continue
                    sub = args[0].lower()
                    if sub == "show" and len(args) >= 2:
                        profile = dict(get_server_profile(config_now, args[1]))
                        if profile.get("password"):
                            profile["password"] = mask_secret(str(profile.get("password") or ""))
                        print(json.dumps(profile, indent=2, ensure_ascii=False))
                        continue
                    if sub in {"check", "doctor"} and len(args) >= 2:
                        require_machine_mode(config_now)
                        profile = get_server_profile(config_now, args[1])
                        remote_health(profile, args[2] if len(args) >= 3 else ".")
                        continue
                    print("Usage: /server list | /server show NAME | /server check NAME [PATH]")
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "servers":
                print_server_profiles(load_config())
                continue
            if cmd in {"playwright", "pw"}:
                try:
                    config = load_config()
                    require_machine_mode(config)
                    if not args or args[0].lower() in {"help", "status"}:
                        profile = ensure_playwright_profile(config)
                        print("Playwright MCP is ready.")
                        print(format_mcp_command(profile))
                        print("Usage: /playwright open URL | /playwright tools | /playwright call TOOL key=value")
                        continue
                    sub = args[0].lower()
                    profile = ensure_playwright_profile(config)
                    if sub == "open" and len(args) >= 2:
                        result = run_playwright_open(profile, args[1])
                        print_mcp_call_result(result)
                        continue
                    if sub == "tools":
                        print_mcp_tools(profile)
                        continue
                    if sub == "call" and len(args) >= 2:
                        tool = args[1]
                        payload = parse_key_value_args(args[2:]) if len(args) > 2 else {}
                        result = run_mcp_stdio(profile, "call", tool_name=tool, arguments=payload)
                        print_mcp_call_result(result)
                        continue
                    print("Usage: /playwright open URL | /playwright tools | /playwright call TOOL key=value")
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "mcp":
                try:
                    config = load_config()
                    require_machine_mode(config)
                    if not args or args[0].lower() == "list":
                        print_mcp_servers(config)
                        continue
                    sub = args[0].lower()
                    if sub == "add-playwright":
                        browser_name = ""
                        headless = False
                        isolated = False
                        name = "playwright"
                        for token in args[1:]:
                            lowered = str(token).strip().lower()
                            if lowered == "headless":
                                headless = True
                            elif lowered == "isolated":
                                isolated = True
                            elif lowered.startswith("browser="):
                                browser_name = lowered.split("=", 1)[1].strip()
                            elif lowered.startswith("name="):
                                name = token.split("=", 1)[1].strip() or name
                        servers = get_mcp_servers(config)
                        servers[name] = build_playwright_mcp_profile(headless=headless, browser_name=browser_name, isolated=isolated)
                        save_mcp_servers(config, servers)
                        print(f"Saved Playwright MCP profile `{name}`.")
                        print(format_mcp_command(servers[name]))
                        continue
                    if sub == "tools" and len(args) >= 2:
                        print_mcp_tools(get_mcp_profile(config, args[1]))
                        continue
                    if sub == "call" and len(args) >= 3:
                        name = args[1]
                        tool = args[2]
                        payload = parse_key_value_args(args[3:]) if len(args) > 3 else {}
                        result = run_mcp_stdio(get_mcp_profile(config, name), "call", tool_name=tool, arguments=payload)
                        print_mcp_call_result(result)
                        continue
                    print("Usage: /mcp list | /mcp add-playwright [headless] [browser=chrome] | /mcp tools NAME | /mcp call NAME TOOL key=value")
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "ssh" and len(args) >= 2:
                try:
                    config = load_config()
                    require_machine_mode(config)
                    profile = get_server_profile(config, args[0])
                    run_remote_command(profile, " ".join(args[1:]))
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "scp" and len(args) >= 4:
                try:
                    config = load_config()
                    require_machine_mode(config)
                    profile = get_server_profile(config, args[1])
                    if args[0].lower() == "put":
                        local_path = str(resolve_scoped_path(args[2], config, current_dir))
                        scp_transfer(profile, local_path, args[3], download=False)
                    elif args[0].lower() == "get":
                        local_path = str(resolve_scoped_path(args[3], config, current_dir))
                        scp_transfer(profile, args[2], local_path, download=True)
                    else:
                        print("Usage: /scp put NAME LOCAL REMOTE | /scp get NAME REMOTE LOCAL")
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "remote":
                try:
                    config = load_config()
                    require_machine_mode(config)
                    if not args:
                        print("Usage: /remote pwd|ls|tree|scan|doctor|check|read|search|mkdir|rm|write|append|replace|exec NAME ...")
                        continue
                    sub = args[0].lower()
                    if sub not in {"pwd", "ls", "tree", "scan", "doctor", "check", "read", "search", "mkdir", "rm", "write", "append", "replace", "exec"}:
                        print("Usage: /remote pwd|ls|tree|scan|doctor|check|read|search|mkdir|rm|write|append|replace|exec NAME ...")
                        continue
                    if len(args) < 2:
                        print(f"Usage: /remote {sub} NAME ...")
                        continue
                    profile = get_server_profile(config, args[1])
                    if sub == "pwd":
                        print(resolve_remote_path(profile, "."))
                        continue
                    if sub == "ls":
                        remote_ls(profile, args[2] if len(args) >= 3 else ".")
                        continue
                    if sub == "tree":
                        target = args[2] if len(args) >= 3 else "."
                        depth = 3
                        if len(args) >= 4 and args[3].isdigit():
                            depth = int(args[3])
                        remote_tree(profile, target, max_depth=max(1, min(depth, 8)))
                        continue
                    if sub == "scan":
                        remote_scan(profile, args[2] if len(args) >= 3 else ".")
                        continue
                    if sub in {"doctor", "check"}:
                        remote_health(profile, args[2] if len(args) >= 3 else ".")
                        continue
                    if sub == "read":
                        if len(args) < 3:
                            print("Usage: /remote read NAME PATH [START] [LINES]")
                            continue
                        start = int(args[3]) if len(args) >= 4 and args[3].isdigit() else 1
                        length = int(args[4]) if len(args) >= 5 and args[4].isdigit() else MAX_READ_LINES
                        remote_read_text(profile, args[2], start=start, lines=length)
                        continue
                    if sub == "search":
                        if len(args) < 3:
                            print("Usage: /remote search NAME REGEX [PATH]")
                            continue
                        remote_search(profile, args[2], args[3] if len(args) >= 4 else ".", ignore_case=False)
                        continue
                    if sub == "mkdir":
                        if len(args) < 3:
                            print("Usage: /remote mkdir NAME PATH")
                            continue
                        remote_mkdir(profile, args[2])
                        continue
                    if sub == "rm":
                        if len(args) < 3:
                            print("Usage: /remote rm NAME PATH [recursive]")
                            continue
                        recursive = len(args) >= 4 and boolish(args[3])
                        remote_rm(profile, args[2], recursive=recursive)
                        continue
                    if sub in {"write", "append"}:
                        if len(args) < 4:
                            print(f"Usage: /remote {sub} NAME PATH TEXT")
                            continue
                        remote_write_text(profile, args[2], " ".join(args[3:]), append=(sub == "append"))
                        continue
                    if sub == "replace":
                        if len(args) < 5:
                            print("Usage: /remote replace NAME PATH OLD NEW [all]")
                            continue
                        replace_all = len(args) >= 6 and boolish(args[5])
                        remote_replace_text(profile, args[2], args[3], args[4], replace_all=replace_all)
                        continue
                    if sub == "exec":
                        if len(args) < 3:
                            print("Usage: /remote exec NAME COMMAND")
                            continue
                        run_remote_command(profile, " ".join(args[2:]))
                        continue
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "pwd":
                print(current_dir)
                continue
            if cmd == "cd" and args:
                target = resolve_scoped_path(args[0], load_config(), current_dir)
                if not target.exists() or not target.is_dir():
                    print(f"Directory not found: {target}")
                    continue
                current_dir = target
                os.chdir(str(current_dir))
                print(current_dir)
                continue
            if cmd == "ls":
                target = resolve_scoped_path(args[0], load_config(), current_dir) if args else current_dir
                try:
                    print_path_listing(target)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "tree":
                target = resolve_scoped_path(args[0], load_config(), current_dir) if args else current_dir
                depth = 3
                if len(args) >= 2:
                    try:
                        depth = int(args[1])
                    except ValueError:
                        pass
                try:
                    print_tree(target, max_depth=max(1, min(depth, 8)))
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "scan":
                target = resolve_scoped_path(args[0], load_config(), current_dir) if args else current_dir
                try:
                    print_workspace_scan(target)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "read" and args:
                target = resolve_scoped_path(args[0], load_config(), current_dir)
                start = int(args[1]) if len(args) >= 2 and args[1].isdigit() else 1
                length = int(args[2]) if len(args) >= 3 and args[2].isdigit() else MAX_READ_LINES
                try:
                    read_text_preview(target, start=start, lines=length)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "search" and args:
                pattern = args[0]
                target = resolve_scoped_path(args[1], load_config(), current_dir) if len(args) >= 2 else current_dir
                try:
                    search_workspace(pattern, target, ignore_case=False)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "exec" and args:
                try:
                    require_machine_mode(load_config())
                    run_local_command(" ".join(args), cwd=current_dir)
                except Exception as exc:
                    print(str(exc))
                continue
            if cmd == "attach" and args:
                path = str(resolve_scoped_path(args[0], load_config(), current_dir))
                if path not in attachments:
                    if len(attachments) >= MAX_ATTACHMENTS:
                        print(f"Too many local attachments. Limit: {MAX_ATTACHMENTS}")
                        continue
                    attachments.append(path)
                print(f"Attached {path}")
                continue
            if cmd == "detach":
                if not args or args[0].lower() == "all":
                    attachments.clear()
                    print("Attachments cleared.")
                else:
                    needle = str(resolve_scoped_path(args[0], load_config(), current_dir))
                    attachments = [item for item in attachments if item != needle]
                    print(f"Detached {needle}")
                continue
            if cmd == "provider" and args:
                candidate = args[0].strip().lower()
                if candidate in PROVIDERS:
                    provider = candidate
                    model = normalize_model(provider, PROVIDERS[provider]["default_model"])
                    print(f"Provider set to {provider}")
                else:
                    print(f"Unknown provider: {candidate}")
                continue
            if cmd == "model" and args:
                model = normalize_model(provider, args[0])
                print(f"Model set to {model}")
                continue
            if cmd == "models":
                list_models(args[0].lower() if args else provider)
                continue
            if cmd == "zero" and args:
                zero_mode = False
                print("CLI trace mode is retired and stays off. Use the web app or Studio for full ZeroThink reasoning.")
                continue
            if cmd == "web" and args:
                use_web = boolish(args[0])
                print(f"Web search: {'on' if use_web else 'off'}")
                continue
            if cmd == "site":
                if len(args) >= 2 and args[0].lower() == "new":
                    config_now = load_config()
                    theme = "zero"
                    title = ""
                    clean_args = []
                    i = 1
                    while i < len(args):
                        if args[i] == "--theme" and i + 1 < len(args):
                            theme = args[i + 1]
                            i += 2
                            continue
                        if args[i] == "--title" and i + 1 < len(args):
                            title = args[i + 1]
                            i += 2
                            continue
                        clean_args.append(args[i])
                        i += 1
                    if not clean_args:
                        print("Usage: /site new PATH [--title TITLE] [--theme zero|clean|luxury|cyber]")
                        continue
                    site_scaffold(resolve_scoped_path(clean_args[0], config_now, current_dir), title=title or pathlib.Path(clean_args[0]).name, theme=theme)
                    continue
                print("Usage: /site new PATH [--title TITLE] [--theme zero|clean|luxury|cyber]")
                continue
            if cmd == "game":
                if len(args) >= 2 and args[0].lower() == "new":
                    config_now = load_config()
                    title = ""
                    force = False
                    clean_args = []
                    i = 1
                    while i < len(args):
                        if args[i] == "--title" and i + 1 < len(args):
                            title = args[i + 1]
                            i += 2
                            continue
                        if args[i] == "--force":
                            force = True
                            i += 1
                            continue
                        clean_args.append(args[i])
                        i += 1
                    if not clean_args:
                        print("Usage: /game new PATH [--title TITLE] [--force]")
                        continue
                    game_scaffold(resolve_scoped_path(clean_args[0], config_now, current_dir), title=title or pathlib.Path(clean_args[0]).name, force=force)
                    continue
                print("Usage: /game new PATH [--title TITLE] [--force]")
                continue
            if cmd in {"crypto", "encrypt", "decrypt", "lock", "unlock", "protect"}:
                config_now = load_config()
                try:
                    if cmd in {"encrypt", "lock", "protect"}:
                        if not args:
                            print("Usage: /encrypt FILE_OR_FOLDER [OUT.ztz]")
                            continue
                        source = resolve_scoped_path(args[0], config_now, current_dir)
                        output = resolve_scoped_path(args[1], config_now, current_dir) if len(args) >= 2 else None
                        pattern = resolve_crypto_pattern(config_now)
                        passphrase = resolve_crypto_passphrase(confirm=True)
                        crypto_encrypt_path(source, output, passphrase, pattern)
                        continue
                    if cmd in {"decrypt", "unlock"}:
                        if not args:
                            print("Usage: /decrypt FILE.ztz [OUT_OR_DEST]")
                            continue
                        source = resolve_scoped_path(args[0], config_now, current_dir)
                        output = resolve_scoped_path(args[1], config_now, current_dir) if len(args) >= 2 else None
                        pattern = resolve_crypto_pattern(config_now)
                        passphrase = resolve_crypto_passphrase(confirm=False)
                        crypto_decrypt_path(source, output, passphrase, pattern)
                        continue
                    sub = args[0].lower() if args else "status"
                    if sub == "status":
                        crypto_status(config_now)
                        continue
                    if sub == "zpepper":
                        action = args[1].lower() if len(args) >= 2 else "status"
                        if action == "status":
                            crypto_status(config_now)
                        elif action == "set":
                            set_crypto_zpepper(" ".join(args[2:]).strip())
                        elif action == "clear":
                            clear_crypto_zpepper()
                        else:
                            print("Usage: /crypto zpepper status|set|clear")
                        continue
                    if sub == "inspect" and len(args) >= 2:
                        crypto_inspect_file(resolve_scoped_path(args[1], config_now, current_dir))
                        continue
                    if sub == "encrypt" and len(args) >= 2:
                        source = resolve_scoped_path(args[1], config_now, current_dir)
                        output = resolve_scoped_path(args[2], config_now, current_dir) if len(args) >= 3 else None
                        pattern = resolve_crypto_pattern(config_now)
                        passphrase = resolve_crypto_passphrase(confirm=True)
                        crypto_encrypt_path(source, output, passphrase, pattern)
                        continue
                    if sub == "decrypt" and len(args) >= 2:
                        source = resolve_scoped_path(args[1], config_now, current_dir)
                        output = resolve_scoped_path(args[2], config_now, current_dir) if len(args) >= 3 else None
                        pattern = resolve_crypto_pattern(config_now)
                        passphrase = resolve_crypto_passphrase(confirm=False)
                        crypto_decrypt_path(source, output, passphrase, pattern)
                        continue
                    if sub == "pack" and len(args) >= 3:
                        output = resolve_scoped_path(args[1], config_now, current_dir)
                        sources = [resolve_scoped_path(item, config_now, current_dir) for item in args[2:]]
                        pattern = resolve_crypto_pattern(config_now)
                        passphrase = resolve_crypto_passphrase(confirm=True)
                        crypto_pack_paths(output, sources, passphrase, pattern)
                        continue
                    if sub == "unpack" and len(args) >= 3:
                        source = resolve_scoped_path(args[1], config_now, current_dir)
                        dest = resolve_scoped_path(args[2], config_now, current_dir)
                        pattern = resolve_crypto_pattern(config_now)
                        passphrase = resolve_crypto_passphrase(confirm=False)
                        crypto_unpack_bundle(source, dest, passphrase, pattern)
                        continue
                    print("Usage: /crypto status | zpepper set|clear | inspect FILE | encrypt FILE_OR_FOLDER [OUT] | decrypt FILE.ztz [OUT_OR_DEST] | pack OUT.ztz PATH... | unpack FILE.ztz DEST")
                except RuntimeError as exc:
                    print(str(exc))
                continue
            if cmd == "zip" and args:
                sub = args[0].lower()
                if sub == "list" and len(args) >= 2:
                    zip_list(resolve_scoped_path(args[1], load_config(), current_dir))
                    continue
                if sub == "extract" and len(args) >= 2:
                    archive = resolve_scoped_path(args[1], load_config(), current_dir)
                    dest = resolve_scoped_path(args[2], load_config(), current_dir) if len(args) >= 3 else resolve_scoped_path(archive.stem, load_config(), current_dir)
                    zip_extract(archive, dest)
                    print(f"Extracted to {dest}")
                    continue
                if sub == "create" and len(args) >= 3:
                    output = resolve_scoped_path(args[1], load_config(), current_dir)
                    sources = [resolve_scoped_path(item, load_config(), current_dir) for item in args[2:]]
                    zip_create(output, sources)
                    print(f"Created {output}")
                    continue
                print("Usage: /zip list ARCHIVE | /zip extract ARCHIVE [DEST] | /zip create OUT.zip path1 [path2]")
                continue
            custom_name = resolve_custom_command_name(cmd, custom_commands)
            if custom_name in custom_commands:
                spec = custom_commands[custom_name]
                try:
                    if spec.get("shell"):
                        require_machine_mode(load_config())
                        shell_command = render_custom_template(str(spec.get("shell") or ""), args, current_dir)
                        run_local_command(shell_command, cwd=current_dir)
                    else:
                        context_path, context_text = read_project_context(current_dir, load_config())
                        custom_provider = str(spec.get("provider") or provider).strip().lower() or provider
                        if custom_provider not in PROVIDERS:
                            custom_provider = provider
                        custom_model = normalize_model(custom_provider, str(spec.get("model") or model))
                        prompt_text = render_custom_template(str(spec.get("prompt") or ""), args, current_dir)
                        result = run_chat(
                            prompt_text,
                            custom_provider,
                            custom_model,
                            attachments,
                            url_attachments,
                            None,
                            zero_mode,
                            use_web,
                            transcript=transcript,
                            context_path=context_path,
                            context_text=context_text,
                            current_dir_override=current_dir,
                        )
                        reply_text, thought = print_agent_result(result)
                        append_transcript_turn(transcript, f"/{custom_name} {' '.join(args)}".strip(), reply_text, thought)
                except Exception as exc:
                    print(str(exc))
                continue
            print("Unknown command. Type /help, try a shorter prefix like `/he`, or press TAB after `/` to explore commands.")
            continue

        if pending_action_task and line.strip().lower() in {"yes", "y", "yep", "yeah", "go ahead", "do it", "carry on", "continue"}:
            try:
                print("[agent-zero] executing staged task with the local tool stack...\n")
                agent = start_autopilot(
                    task=pending_action_task,
                    name=None,
                    provider=provider,
                    model=model,
                    cwd=current_dir,
                    steps=16,
                    workers=3,
                    role="controller",
                    attachments=attachments,
                    url_attachments=url_attachments,
                    briefing=format_transcript_for_context(list(transcript)[-6:]),
                )
                last_agent_name = str(agent.get("name") or "")
                reply_text = safe_console_text(str(agent.get("final_reply") or agent.get("status") or "").strip())
                thought = ""
                print("Final:")
                print(reply_text)
                print("")
                append_transcript_turn(transcript, pending_action_preview or pending_action_task, reply_text, thought)
            except Exception as exc:
                print(str(exc))
            pending_action_task = ""
            pending_action_preview = ""
            continue

        if should_route_to_autopilot(line, transcript):
            steering_mode = steering_mode_from(load_config())
            staged_task = build_repl_autopilot_task(line, transcript, current_dir, attachments, url_attachments)
            if steering_mode in {"plan", "confirm"}:
                pending_action_task = staged_task
                pending_action_preview = line
                print("")
                print(format_pending_action_preview(line, current_dir, steering_mode, attachments, url_attachments))
                print("")
                continue
            try:
                print("[agent-zero] executing task with the local tool stack...\n")
                agent = start_autopilot(
                    task=staged_task,
                    name=None,
                    provider=provider,
                    model=model,
                    cwd=current_dir,
                    steps=16,
                    workers=3,
                    role="controller",
                    attachments=attachments,
                    url_attachments=url_attachments,
                    briefing=format_transcript_for_context(list(transcript)[-6:]),
                )
                last_agent_name = str(agent.get("name") or "")
                reply_text = safe_console_text(str(agent.get("final_reply") or agent.get("status") or "").strip())
                thought = ""
                print("Final:")
                print(reply_text)
                print("")
                append_transcript_turn(transcript, line, reply_text, thought)
            except Exception as exc:
                print(str(exc))
            continue

        try:
            config = load_config()
            context_path, context_text = read_project_context(current_dir, config)
            result = run_chat(
                line,
                provider,
                model,
                attachments,
                url_attachments,
                None,
                zero_mode,
                use_web,
                transcript=transcript,
                context_path=context_path,
                context_text=context_text,
                current_dir_override=current_dir,
            )
        except Exception as exc:
            print(str(exc))
            continue
        reply_text, thought = print_agent_result(result)
        append_transcript_turn(transcript, line, reply_text, thought)


def freewebpanel_redact_text(value: str) -> str:
    return FREEWEBPANEL_SECRET_RE.sub(
        lambda match: f"{match.group(1)}{match.group(2)}{match.group(3)}[REDACTED]{match.group(5)}",
        str(value or ""),
    )


def freewebpanel_resolve_root(path_value: Optional[str] = None) -> pathlib.Path:
    if path_value:
        return pathlib.Path(path_value).expanduser().resolve()
    for candidate in FREEWEBPANEL_PATH_CANDIDATES:
        path = pathlib.Path(candidate)
        if path.exists():
            return path.resolve()
    return pathlib.Path.cwd().resolve()


def freewebpanel_command_path(*names: str) -> str:
    for name in names:
        found = shutil.which(name)
        if found:
            return found
        candidate = pathlib.Path(name).expanduser()
        if candidate.is_file():
            return str(candidate.resolve())
    return ""


def freewebpanel_service_status(service: str) -> str:
    systemctl = shutil.which("systemctl")
    if not systemctl or os.name == "nt":
        return "unavailable"
    try:
        result = subprocess.run(
            [systemctl, "is-active", service],
            capture_output=True,
            text=True,
            timeout=12,
            errors="replace",
        )
    except Exception:
        return "unknown"
    status = (result.stdout or result.stderr or "").strip().lower()
    return status or ("active" if result.returncode == 0 else "inactive")


def freewebpanel_program_version(path: str, flag: str = "--version") -> str:
    if not path:
        return ""
    try:
        result = subprocess.run(
            [path, flag],
            capture_output=True,
            text=True,
            timeout=12,
            errors="replace",
        )
    except Exception:
        return ""
    output = (result.stdout or result.stderr or "").strip().splitlines()
    return output[0][:240] if output else ""


def freewebpanel_backup_status() -> dict:
    roots = [CONFIG_DIR / "freewebpanel-backups"]
    roots.extend(pathlib.Path(path) for path in FREEWEBPANEL_BACKUP_CANDIDATES)
    archives: List[pathlib.Path] = []
    for root in roots:
        if not root.exists() or not root.is_dir():
            continue
        try:
            archives.extend(path for path in root.glob("*.tar.gz") if path.is_file())
        except OSError:
            continue
    archives.sort(key=lambda path: path.stat().st_mtime if path.exists() else 0, reverse=True)
    return {
        "count": len(archives),
        "latest": str(archives[0]) if archives else "",
        "roots": [str(root) for root in roots if root.exists()],
    }


def freewebpanel_config_files(limit: int = 80) -> List[pathlib.Path]:
    found: List[pathlib.Path] = []
    allowed = {".conf", ".ini", ".json", ".yaml", ".yml", ".toml", ".env", ".php"}
    for raw in FREEWEBPANEL_CONFIG_CANDIDATES:
        root = pathlib.Path(raw)
        if not root.exists():
            continue
        if root.is_file():
            found.append(root)
            continue
        try:
            for path in root.rglob("*"):
                if len(found) >= limit:
                    return found
                if path.is_file() and (path.suffix.lower() in allowed or path.name.startswith(".")):
                    found.append(path)
        except OSError:
            continue
    return found


def freewebpanel_log_files(limit: int = 40) -> List[pathlib.Path]:
    found: List[pathlib.Path] = []
    seen = set()
    for pattern in FREEWEBPANEL_LOG_PATTERNS:
        raw_path = pathlib.Path(pattern)
        parent = raw_path.parent
        if not parent.exists():
            continue
        try:
            matches = parent.glob(raw_path.name)
            for path in matches:
                key = str(path)
                if path.is_file() and key not in seen:
                    found.append(path)
                    seen.add(key)
                    if len(found) >= limit:
                        return found
        except OSError:
            continue
    return found


def freewebpanel_tail(path: pathlib.Path, lines: int = 80, redact: bool = True) -> str:
    lines = max(1, min(int(lines), 500))
    try:
        with path.open("r", encoding="utf-8", errors="replace") as handle:
            content = handle.readlines()[-lines:]
    except OSError as exc:
        return f"[unreadable: {exc}]"
    text = "".join(content).rstrip()
    return freewebpanel_redact_text(text) if redact else text


def freewebpanel_permission_findings(root: pathlib.Path, limit: int = 250) -> List[dict]:
    findings: List[dict] = []
    if not root.exists():
        return [{"level": "error", "path": str(root), "message": "Path does not exist."}]
    if os.name == "nt":
        return [
            {
                "level": "info",
                "path": str(root),
                "message": "POSIX ownership and world-writable checks are available when this command runs on the Linux server.",
            }
        ]
    scanned = 0
    paths = [root]
    if root.is_dir():
        try:
            paths.extend(root.rglob("*"))
        except OSError:
            pass
    for path in paths:
        if scanned >= limit:
            break
        scanned += 1
        try:
            mode = stat.S_IMODE(path.stat().st_mode)
        except OSError:
            continue
        if mode & stat.S_IWOTH:
            findings.append(
                {
                    "level": "warn",
                    "path": str(path),
                    "mode": oct(mode),
                    "message": "World-writable path.",
                }
            )
    if not findings:
        findings.append(
            {
                "level": "ok",
                "path": str(root),
                "message": f"No world-writable entries found in the first {scanned} paths checked.",
            }
        )
    return findings


def freewebpanel_status_payload(args: argparse.Namespace) -> dict:
    root = freewebpanel_resolve_root(getattr(args, "path", None))
    disk_root = root if root.exists() else pathlib.Path.cwd().resolve()
    usage = shutil.disk_usage(disk_root)
    commands = {
        "apache": freewebpanel_command_path("apache2", "httpd"),
        "nginx": freewebpanel_command_path("nginx"),
        "php": freewebpanel_command_path("php"),
        "php_fpm": freewebpanel_command_path("php-fpm", "php8.4-fpm", "php8.3-fpm", "php8.2-fpm"),
        "mysql": freewebpanel_command_path("mariadb", "mysql"),
        "certbot": freewebpanel_command_path("certbot"),
        "openssl": freewebpanel_command_path("openssl"),
        "tar": freewebpanel_command_path("tar"),
        "freewebpanel": freewebpanel_command_path("freewebpanel", "/usr/local/bin/freewebpanel", "/opt/freewebpanel/bin/freewebpanel"),
    }
    services = {
        "nginx": freewebpanel_service_status("nginx"),
        "apache2": freewebpanel_service_status("apache2"),
        "httpd": freewebpanel_service_status("httpd"),
        "php-fpm": freewebpanel_service_status("php-fpm"),
        "mariadb": freewebpanel_service_status("mariadb"),
        "mysql": freewebpanel_service_status("mysql"),
        "freewebpanel": freewebpanel_service_status("freewebpanel"),
        "thcz-api": freewebpanel_service_status("thcz-api"),
    }
    config_files = freewebpanel_config_files()
    logs = freewebpanel_log_files()
    backup_status = freewebpanel_backup_status()
    detected_hostname = platform.node().strip().lower()
    requested_domain = str(getattr(args, "domain", "") or "").strip().lower()
    domain = requested_domain or (detected_hostname if "." in detected_hostname else "")
    ssl_paths = []
    if domain:
        for path in (
            pathlib.Path("/etc/letsencrypt/live") / domain / "fullchain.pem",
            pathlib.Path("/etc/letsencrypt/live") / domain / "privkey.pem",
        ):
            if path.exists():
                ssl_paths.append(str(path))
    required_paths = {
        "target": root.exists(),
        "target_readable": os.access(root, os.R_OK) if root.exists() else False,
        "target_writable": os.access(root, os.W_OK) if root.exists() else False,
        "htaccess": (root / ".htaccess").exists() if root.is_dir() else False,
        "config_detected": bool(config_files),
        "logs_detected": bool(logs),
    }
    installed = bool(commands["freewebpanel"]) or any(
        pathlib.Path(path).exists()
        for path in FREEWEBPANEL_PATH_CANDIDATES
        if path != "/var/www/html"
    )
    warnings = []
    if not root.exists():
        warnings.append(f"Selected FreeWebPanel path does not exist: {root}")
    if not commands["freewebpanel"]:
        warnings.append("FreeWebPanel executable was not found on PATH or in the standard install locations.")
    if not commands["php"]:
        warnings.append("PHP CLI was not found.")
    if not commands["apache"] and not commands["nginx"]:
        warnings.append("Neither Apache nor nginx was found.")
    if usage.free and usage.total and (usage.free / usage.total) < 0.1:
        warnings.append("Less than 10% disk space is free.")
    if installed and not config_files:
        warnings.append("A FreeWebPanel install was detected, but no known configuration files were found.")
    if domain and not ssl_paths:
        warnings.append(f"No Let's Encrypt certificate files were detected for {domain}.")
    return {
        "mode": str(getattr(args, "server_mode", "auto") or "auto"),
        "target": str(root),
        "target_exists": root.exists(),
        "installation": {
            "detected": installed,
            "status": "installed" if installed else "not detected",
            "executable": commands["freewebpanel"],
        },
        "platform": {
            "system": platform.system(),
            "release": platform.release(),
            "machine": platform.machine(),
            "python": platform.python_version(),
        },
        "disk": {
            "total_bytes": usage.total,
            "used_bytes": usage.used,
            "free_bytes": usage.free,
            "total": format_bytes(usage.total),
            "used": format_bytes(usage.used),
            "free": format_bytes(usage.free),
        },
        "commands": commands,
        "versions": {
            "freewebpanel": freewebpanel_program_version(commands["freewebpanel"]),
            "php": freewebpanel_program_version(commands["php"]),
            "nginx": freewebpanel_program_version(commands["nginx"], "-v"),
            "apache": freewebpanel_program_version(commands["apache"], "-v"),
            "mysql": freewebpanel_program_version(commands["mysql"]),
        },
        "services": services,
        "config_files": [str(path) for path in config_files],
        "log_files": [str(path) for path in logs],
        "domain": domain,
        "ssl_files": ssl_paths,
        "required_paths": required_paths,
        "backup": backup_status,
        "warnings": warnings,
    }


def freewebpanel_print_payload(payload: dict, quiet: bool = False, json_mode: bool = False) -> None:
    if json_mode:
        print(json.dumps(payload, indent=2, ensure_ascii=False))
        return
    if quiet:
        print("ok" if not payload.get("warnings") else "warning")
        return
    print("FreeWebPanel server report")
    print(f"[INFO] Target: {payload.get('target')}")
    installation = payload.get("installation") or {}
    install_label = "OK" if installation.get("detected") else "WARN"
    print(f"[{install_label}] Installation: {installation.get('status')}")
    platform_info = payload.get("platform") or {}
    print(f"[INFO] Platform: {platform_info.get('system')} {platform_info.get('release')} ({platform_info.get('machine')})")
    disk = payload.get("disk") or {}
    print(f"[INFO] Disk: {disk.get('used')} used / {disk.get('total')} total / {disk.get('free')} free")
    print("[INFO] Services:")
    for name, status in (payload.get("services") or {}).items():
        label = "OK" if status == "active" else "INFO" if status == "unavailable" else "WARN"
        print(f"  [{label}] {name}: {status}")
    print("[INFO] Programs:")
    for name, path in (payload.get("commands") or {}).items():
        label = "OK" if path else "WARN"
        print(f"  [{label}] {name}: {path or 'not found'}")
    config_files = payload.get("config_files") or []
    log_files = payload.get("log_files") or []
    print(f"[INFO] Config files found: {len(config_files)}")
    print(f"[INFO] Log files found: {len(log_files)}")
    backup = payload.get("backup") or {}
    print(f"[INFO] Backups found: {backup.get('count', 0)}" + (f" (latest: {backup.get('latest')})" if backup.get("latest") else ""))
    if payload.get("domain"):
        print(f"[INFO] Domain: {payload.get('domain')}")
    for name, version in (payload.get("versions") or {}).items():
        if version:
            print(f"[OK] {name} version: {version}")
    for name, passed in (payload.get("required_paths") or {}).items():
        print(f"[{'OK' if passed else 'WARN'}] {name.replace('_', ' ')}")
    for warning in payload.get("warnings") or []:
        print(f"[WARN] {warning}")


def freewebpanel_print_help() -> None:
    print(
        textwrap.dedent(
            """
            ZeroThink FreeWebPanel mode
            FreeWebPanel mode: install, manage, inspect, backup, repair, update, and operate a FreeWebPanel server.

            Usage:
              zerothink freewebpanel ACTION [options]
              zerothink fwp ACTION [options]

            Read-only actions:
              status, check, health, doctor, config, domains, logs,
              permissions, apache, php, ssl, explain

            Backup and change planning:
              backup, install, repair, update, deploy

            Examples:
              zerothink fwp status
              zerothink fwp doctor --verbose
              zerothink fwp health --json
              zerothink fwp domains --domain example.com
              zerothink fwp logs --lines 100 --redact
              zerothink fwp permissions --path /var/www/example.com
              zerothink fwp backup --path /opt/freewebpanel --dry-run
              zerothink fwp repair --backup-first --dry-run
              zerothink fwp update --backup-first --dry-run
              zerothink fwp deploy --path ./release --domain example.com --dry-run

            Safety:
              Change-oriented actions print a plan by default. Use --yes only after
              reviewing the plan. Use --backup-first before repair, update, or deploy.

            Ecosystem:
              ZeroThink CLI is the local server intelligence and operations layer in the
              TalkToAI / ZeroThink ecosystem. FreeWebPanel mode is not only an installer;
              it remains useful after installation for daily admin work, diagnostics,
              update planning, migration checks, and redacted evidence reports.
            """
        ).strip()
    )


def freewebpanel_backup(args: argparse.Namespace) -> int:
    root = freewebpanel_resolve_root(getattr(args, "path", None))
    output_root = pathlib.Path(getattr(args, "output", "") or (CONFIG_DIR / "freewebpanel-backups")).expanduser().resolve()
    archive = output_root / f"freewebpanel-backup-{current_timestamp_slug()}.tar.gz"
    sources = [root] if bool(getattr(args, "include_sites", True)) else []
    if bool(getattr(args, "include_config", False)):
        sources.extend(pathlib.Path(path) for path in FREEWEBPANEL_CONFIG_CANDIDATES if pathlib.Path(path).exists())
    unique_sources = []
    seen = set()
    for source in sources:
        key = str(source)
        if source.exists() and key not in seen:
            unique_sources.append(source)
            seen.add(key)
    for source in unique_sources:
        if source.is_dir():
            try:
                archive.relative_to(source.resolve())
            except ValueError:
                continue
            raise RuntimeError(
                f"Backup output must be outside the source tree. Output {archive} is inside {source}."
            )
    if bool(getattr(args, "dry_run", False)) or not bool(getattr(args, "yes", False)):
        print("[PLAN] FreeWebPanel backup")
        print(f"[PLAN] Output: {archive}")
        for source in unique_sources:
            print(f"[PLAN] Include: {source}")
        if bool(getattr(args, "include_db", False)):
            print("[PLAN] Database dump requested. Database credentials are not read or printed by this command.")
        if not bool(getattr(args, "dry_run", False)):
            print("[SAFE] No archive was written. Re-run with --yes after reviewing the paths.")
        return 0
    if not unique_sources:
        raise RuntimeError("No existing FreeWebPanel paths were found to back up.")
    output_root.mkdir(parents=True, exist_ok=True)
    with tarfile.open(archive, "w:gz") as bundle:
        for source in unique_sources:
            arcname = source.name or "root"
            bundle.add(source, arcname=arcname, recursive=True)
    print(f"[OK] Backup created: {archive}")
    if bool(getattr(args, "include_db", False)):
        print("[WARN] Database contents were not dumped automatically. Add a provider-approved dump separately.")
    return 0


def freewebpanel_change_plan(action: str, args: argparse.Namespace) -> List[str]:
    mode = str(getattr(args, "server_mode", "auto") or "auto")
    domain = str(getattr(args, "domain", "") or "").strip()
    target = freewebpanel_resolve_root(getattr(args, "path", None))
    plans = {
        "install": [
            f"Run the FreeWebPanel preflight for server mode: {mode}.",
            "Verify supported OS, root access, ports, DNS, disk, RAM, mail hostname, and existing panel conflicts.",
            "Install the official FreeWebPanel bundle and enable its API, web, database, mail, SSL, update, firewall, and backup lanes.",
            "Run post-install health checks before opening the panel to customers.",
        ],
        "repair": [
            "Capture a backup and current service/config evidence.",
            "Validate nginx/Apache, PHP-FPM, MariaDB, mail, DNS, SSL, firewall, and FreeWebPanel API state.",
            "Apply only the repair actions selected by the operator.",
            "Restart affected services and run health checks.",
        ],
        "update": [
            "Capture a backup and release metadata.",
            "Fetch and verify the current stable FreeWebPanel release.",
            "Stage the new UI, API, helpers, units, and installer metadata.",
            "Validate configuration and roll back automatically if validation fails.",
        ],
        "deploy": [
            f"Validate the release path: {target}.",
            f"Resolve the target domain: {domain or '[domain required]'} and its document root.",
            "Back up the current release, deploy atomically, restore ownership, and run syntax checks.",
            "Run HTTPS and application smoke checks, then keep a rollback pointer.",
        ],
    }
    return plans.get(action, [])


def freewebpanel_installer_url(args: argparse.Namespace) -> str:
    mode = str(getattr(args, "server_mode", "auto") or "auto")
    replacement = str(getattr(args, "replace_panel", "") or "").strip().lower()
    if replacement or mode in {"cwp", "cwp-pro", "directadmin", "cpanel"}:
        return FREEWEBPANEL_INSTALLER_URLS["ubuntu"]
    return FREEWEBPANEL_INSTALLER_URLS.get(mode, FREEWEBPANEL_INSTALLER_URLS["auto"])


def freewebpanel_installer_arguments(args: argparse.Namespace) -> List[str]:
    mode = str(getattr(args, "server_mode", "auto") or "auto")
    hostname = str(getattr(args, "hostname", "") or getattr(args, "domain", "") or "").strip()
    email = str(getattr(args, "email", "") or "").strip()
    if not hostname:
        raise RuntimeError("FreeWebPanel install requires --hostname (or --domain).")
    if not email:
        raise RuntimeError("FreeWebPanel install requires --email for certificate notices.")
    arguments = [
        "--hostname",
        hostname,
        "--email",
        email,
        "--ssh-port",
        str(max(1, min(int(getattr(args, "ssh_port", 22) or 22), 65535))),
        "--server-profile",
        str(getattr(args, "server_profile", "provider") or "provider"),
    ]
    if bool(getattr(args, "preflight_only", False)):
        arguments.append("--preflight-only")
    replacement = str(getattr(args, "replace_panel", "") or "").strip().lower()
    if not replacement and mode in {"cwp", "cwp-pro", "directadmin", "cpanel"}:
        replacement = "cwp" if mode == "cwp-pro" else mode
    if replacement:
        if mode in {"rhel-family", "alma", "rocky", "cwp", "cwp-pro", "directadmin", "cpanel"}:
            arguments.append("--allow-rhel-family")
        if mode in {"openzero-aios", "zeromint"}:
            arguments.append("--allow-openzero-aios")
        arguments.extend(["--replace-panel", replacement])
        confirmation = str(getattr(args, "confirm_panel_replace", "") or "").strip()
        if confirmation:
            arguments.extend(["--confirm-panel-replace", confirmation])
    return arguments


def freewebpanel_print_install_command(args: argparse.Namespace) -> None:
    url = freewebpanel_installer_url(args)
    try:
        arguments = freewebpanel_installer_arguments(args)
    except RuntimeError:
        hostname = str(getattr(args, "hostname", "") or getattr(args, "domain", "") or "panel.example.com")
        email = str(getattr(args, "email", "") or "admin@example.com")
        arguments = [
            "--hostname",
            hostname,
            "--email",
            email,
            "--ssh-port",
            str(getattr(args, "ssh_port", 22) or 22),
            "--server-profile",
            str(getattr(args, "server_profile", "provider") or "provider"),
        ]
        if bool(getattr(args, "preflight_only", False)):
            arguments.append("--preflight-only")
    rendered = " ".join(shlex.quote(item) for item in arguments)
    print(f"[PLAN] Installer: {url}")
    print(f"[PLAN] Equivalent command: curl -fsSL {shlex.quote(url)} | sudo bash -s -- {rendered}")


def freewebpanel_run_installer(args: argparse.Namespace) -> int:
    if os.name == "nt":
        raise RuntimeError("FreeWebPanel installation must run on the target Linux server.")
    if hasattr(os, "geteuid") and os.geteuid() != 0:
        raise RuntimeError("FreeWebPanel installation requires root privileges. Run ZeroThink through sudo on the target server.")
    url = freewebpanel_installer_url(args)
    arguments = freewebpanel_installer_arguments(args)
    existing = freewebpanel_command_path("freewebpanel", "/usr/local/bin/freewebpanel", "/opt/freewebpanel/bin/freewebpanel")
    existing = existing or next(
        (
            path
            for path in FREEWEBPANEL_PATH_CANDIDATES
            if path != "/var/www/html" and pathlib.Path(path).exists()
        ),
        "",
    )
    if existing and "--preflight-only" not in arguments and not bool(getattr(args, "backup_first", False)):
        raise RuntimeError(
            f"An existing FreeWebPanel installation was detected at {existing}. Re-run with --backup-first or use --preflight-only."
        )
    replacement = ""
    if "--replace-panel" in arguments:
        replacement = arguments[arguments.index("--replace-panel") + 1]
    if replacement and "--preflight-only" not in arguments:
        confirmation = str(getattr(args, "confirm_panel_replace", "") or "").strip()
        if confirmation != "BACKUP_READY":
            raise RuntimeError("Panel takeover requires --confirm-panel-replace BACKUP_READY after verified off-server backups.")
    request = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            installer = response.read(4 * 1024 * 1024 + 1)
    except Exception as exc:
        raise RuntimeError(f"Unable to download the official FreeWebPanel installer: {exc}") from exc
    if len(installer) > 4 * 1024 * 1024:
        raise RuntimeError("Refusing an unexpectedly large FreeWebPanel installer response.")
    if not installer.startswith(b"#!/usr/bin/env bash") and not installer.startswith(b"#!/bin/bash"):
        raise RuntimeError("Downloaded FreeWebPanel installer did not have the expected Bash header.")
    digest = hashlib.sha256(installer).hexdigest()
    print(f"[OK] Downloaded official installer: {url}")
    print(f"[OK] Installer SHA-256: {digest}")
    temp_path = ""
    try:
        with tempfile.NamedTemporaryFile(prefix="freewebpanel-install-", suffix=".sh", delete=False) as handle:
            handle.write(installer)
            temp_path = handle.name
        os.chmod(temp_path, 0o700)
        completed = subprocess.run(
            [freewebpanel_command_path("bash") or "/bin/bash", temp_path, *arguments],
            timeout=3600,
        )
        return int(completed.returncode)
    finally:
        if temp_path:
            try:
                pathlib.Path(temp_path).unlink()
            except OSError:
                pass


def freewebpanel_handle_change(action: str, args: argparse.Namespace) -> int:
    if action in {"repair", "update", "deploy"} and bool(getattr(args, "no_backup", False)):
        raise RuntimeError(f"FreeWebPanel {action} requires a backup. --no-backup is not permitted for this write operation.")
    if action in {"repair", "update", "deploy"}:
        args.backup_first = True
    print(f"[PLAN] FreeWebPanel {action}")
    for index, item in enumerate(freewebpanel_change_plan(action, args), start=1):
        print(f"  {index}. {item}")
    if action == "install":
        freewebpanel_print_install_command(args)
    if bool(getattr(args, "backup_first", False)):
        print("[PLAN] Backup-first is enabled.")
    if bool(getattr(args, "dry_run", False)):
        print("[DRY-RUN] No server changes were made.")
        return 0
    if not bool(getattr(args, "yes", False)):
        print("[SAFE] No server changes were made. Re-run with --yes after reviewing the plan.")
        return 0
    if bool(getattr(args, "backup_first", False)):
        backup_args = argparse.Namespace(**vars(args))
        backup_args.dry_run = False
        backup_args.yes = True
        backup_args.include_config = True
        backup_args.include_db = False
        backup_args.output = getattr(args, "output", "")
        freewebpanel_backup(backup_args)
    if action == "install":
        return freewebpanel_run_installer(args)
    executable = freewebpanel_command_path("freewebpanel", "/usr/local/bin/freewebpanel", "/opt/freewebpanel/bin/freewebpanel")
    if not executable:
        raise RuntimeError(
            "The FreeWebPanel executable was not found. The plan was preserved, but no unverified installer or repair script was executed."
        )
    command = f"{shlex.quote(executable)} {shlex.quote(action)}"
    if getattr(args, "server_mode", None):
        command += f" --server-mode {shlex.quote(str(args.server_mode))}"
    if getattr(args, "domain", None):
        command += f" --domain {shlex.quote(str(args.domain))}"
    if getattr(args, "path", None):
        command += f" --path {shlex.quote(str(args.path))}"
    command += " --non-interactive"
    return run_local_command(command, cwd=pathlib.Path.cwd(), timeout=900)


def freewebpanel_show_configs(args: argparse.Namespace) -> int:
    files = freewebpanel_config_files()
    payload = {"config_files": [str(path) for path in files]}
    if bool(getattr(args, "json", False)):
        print(json.dumps(payload, indent=2, ensure_ascii=False))
        return 0
    print(f"FreeWebPanel config files: {len(files)}")
    for path in files:
        print(f"  - {path}")
        if bool(getattr(args, "verbose", False)):
            preview = freewebpanel_tail(path, lines=min(int(getattr(args, "lines", 80)), 80), redact=bool(getattr(args, "redact", True)))
            if preview:
                print(textwrap.indent(preview, "      "))
    return 0


def freewebpanel_show_logs(args: argparse.Namespace) -> int:
    requested = str(getattr(args, "log_path", "") or getattr(args, "path", "") or "").strip()
    files = freewebpanel_log_files()
    if requested:
        requested_path = pathlib.Path(requested).expanduser().resolve()
        if requested_path.is_dir():
            try:
                files = [path for path in requested_path.glob("*.log") if path.is_file()]
            except OSError:
                files = []
        else:
            files = [requested_path]
    if bool(getattr(args, "json", False)):
        payload = []
        for path in files:
            payload.append(
                {
                    "path": str(path),
                    "tail": freewebpanel_tail(path, getattr(args, "lines", 80), bool(getattr(args, "redact", True))),
                }
            )
        print(json.dumps(payload, indent=2, ensure_ascii=False))
        return 0
    if not files:
        print("[WARN] No known FreeWebPanel, web, PHP, or SSL log files were found.")
        return 0
    for path in files:
        print(f"\n==> {path} <==")
        print(freewebpanel_tail(path, getattr(args, "lines", 80), bool(getattr(args, "redact", True))) or "[empty]")
    return 0


def freewebpanel_show_domains(args: argparse.Namespace) -> int:
    domain = str(getattr(args, "domain", "") or "").strip().lower()
    roots = [pathlib.Path("/etc/nginx/sites-enabled"), pathlib.Path("/etc/apache2/sites-enabled"), pathlib.Path("/etc/httpd/conf.d")]
    matches = []
    for root in roots:
        if not root.exists():
            continue
        try:
            for path in root.iterdir():
                if not path.is_file() and not path.is_symlink():
                    continue
                if not domain or domain in path.name.lower():
                    matches.append(str(path))
                    continue
                try:
                    body = path.read_text(encoding="utf-8", errors="replace")
                except OSError:
                    continue
                if domain in body.lower():
                    matches.append(str(path))
        except OSError:
            continue
    payload = {
        "domain": domain,
        "config_matches": matches,
        "ecosystem_note": "FreeWebPanel mode reports domain evidence without publishing private infrastructure examples.",
        "example": "zerothink fwp domains --domain panel.example.com",
    }
    if bool(getattr(args, "json", False)):
        print(json.dumps(payload, indent=2, ensure_ascii=False))
    else:
        print(f"Domain filter: {domain or 'all visible vhost files'}")
        if matches:
            for match in matches:
                print(f"  [OK] {match}")
        else:
            print("  [WARN] No matching enabled nginx/Apache vhost files were found.")
        print(f"[INFO] {payload['ecosystem_note']}")
        print(f"[INFO] Example: {payload['example']}")
    return 0


def handle_freewebpanel_command(args: argparse.Namespace) -> int:
    action = str(getattr(args, "freewebpanel_action", "") or "help").lower()
    if action in {"help", "explain"}:
        freewebpanel_print_help()
        return 0
    if action in {"status", "check", "health", "doctor"}:
        payload = freewebpanel_status_payload(args)
        if action in {"check", "health", "doctor"}:
            payload["permission_findings"] = freewebpanel_permission_findings(
                freewebpanel_resolve_root(getattr(args, "path", None)),
                limit=80 if action in {"check", "health"} else 250,
            )
        if action == "doctor":
            payload["manual_review"] = [
                "Confirm the panel hostname and nameserver A records are DNS-only and point to this server.",
                "Review enabled vhosts before changing Apache or nginx configuration.",
                "Verify an off-server backup before repair, update, takeover, or deployment.",
                "Run the official installer preflight before package changes on a used server.",
            ]
        freewebpanel_print_payload(payload, quiet=bool(getattr(args, "quiet", False)), json_mode=bool(getattr(args, "json", False)))
        if action in {"check", "health", "doctor"} and not bool(getattr(args, "json", False)):
            for finding in payload.get("permission_findings") or []:
                print(f"[{str(finding.get('level') or 'info').upper()}] {finding.get('path')}: {finding.get('message')}")
        if action == "doctor" and not bool(getattr(args, "json", False)):
            for item in payload.get("manual_review") or []:
                print(f"[MANUAL REVIEW] {item}")
        return 0
    if action == "backup":
        return freewebpanel_backup(args)
    if action in {"install", "repair", "update", "deploy"}:
        return freewebpanel_handle_change(action, args)
    if action == "config":
        if bool(getattr(args, "show_redacted", False)):
            args.verbose = True
            args.redact = True
        return freewebpanel_show_configs(args)
    if action == "logs":
        return freewebpanel_show_logs(args)
    if action == "domains":
        return freewebpanel_show_domains(args)
    if action == "permissions":
        findings = freewebpanel_permission_findings(freewebpanel_resolve_root(getattr(args, "path", None)))
        if bool(getattr(args, "json", False)):
            print(json.dumps(findings, indent=2, ensure_ascii=False))
        else:
            for finding in findings:
                print(f"[{str(finding.get('level') or 'info').upper()}] {finding.get('path')}: {finding.get('message')}")
        return 0
    if action in {"apache", "php", "ssl"}:
        payload = freewebpanel_status_payload(args)
        focused = {
            "apache": {
                "apache": payload["commands"].get("apache"),
                "nginx": payload["commands"].get("nginx"),
                "apache_version": payload["versions"].get("apache"),
                "nginx_version": payload["versions"].get("nginx"),
                "htaccess": payload["required_paths"].get("htaccess"),
                "vhost_hints": [
                    path
                    for path in (
                        "/etc/apache2/sites-enabled",
                        "/etc/httpd/conf.d",
                        "/etc/nginx/sites-enabled",
                    )
                    if pathlib.Path(path).exists()
                ],
                "services": {name: value for name, value in payload["services"].items() if name in {"apache2", "httpd", "nginx"}},
            },
            "php": {
                "php": payload["commands"].get("php"),
                "php_fpm": payload["commands"].get("php_fpm"),
                "version": payload["versions"].get("php"),
                "services": {name: value for name, value in payload["services"].items() if "php" in name},
            },
            "ssl": {
                "domain": payload.get("domain"),
                "certbot": payload["commands"].get("certbot"),
                "openssl": payload["commands"].get("openssl"),
                "certificate_files": payload.get("ssl_files"),
                "logs": [path for path in payload["log_files"] if "letsencrypt" in path.lower()],
            },
        }[action]
        if bool(getattr(args, "json", False)):
            print(json.dumps(focused, indent=2, ensure_ascii=False))
        else:
            print(f"FreeWebPanel {action} report")
            for key, value in focused.items():
                print(f"[INFO] {key}: {value}")
        return 0
    raise RuntimeError("Usage: zerothink freewebpanel help|install|status|check|health|doctor|backup|repair|update|config|domains|logs|permissions|apache|php|ssl|deploy|explain")


def add_freewebpanel_common_arguments(parser: argparse.ArgumentParser) -> None:
    parser.add_argument("--dry-run", action="store_true", help="Show the plan without changing the server")
    parser.add_argument("--backup-first", action="store_true", help="Create a backup before an approved change")
    parser.add_argument("--no-backup", action="store_true", help="Explicitly disable backup planning")
    parser.add_argument("--path", help="FreeWebPanel, website, release, or document-root path")
    parser.add_argument("--domain", help="Domain to inspect or deploy")
    parser.add_argument("--server-mode", choices=FREEWEBPANEL_SERVER_MODES, default="auto", help="Server or takeover mode")
    parser.add_argument("--non-interactive", action="store_true", help="Do not prompt for input")
    parser.add_argument("--json", action="store_true", help="Print machine-readable JSON")
    parser.add_argument("--verbose", action="store_true", help="Show more findings")
    parser.add_argument("--quiet", action="store_true", help="Print only a compact result")
    parser.add_argument("--redact", dest="redact", action="store_true", default=True, help="Redact common secret values")
    parser.add_argument("--no-redact", dest="redact", action="store_false", help="Disable redaction for a trusted local terminal")
    parser.add_argument("--yes", action="store_true", help="Approve an operation after reviewing its plan")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="zerothink",
        description="ZeroThink terminal client",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=textwrap.dedent(
            """
            Beginner flow:
              zerothink
              or
              zerothink tutorial
              zerothink help quickstart
              zerothink login
              or
              zerothink key set groq
              zerothink access folder .
              zerothink

            Common examples:
              zerothink chat --file app.py "explain this file"
              zerothink status
              zerothink search "nginx php-fpm tuning"
              zerothink browse https://nginx.org/en/docs/
              zerothink fs scan .
              zerothink key status
              zerothink context show
              zerothink session list
              zerothink server list
              zerothink fwp status
              zerothink fwp doctor --verbose
              zerothink help examples
            """
        ).strip(),
    )
    sub = parser.add_subparsers(dest="command")

    sub.add_parser("init", help="Alias for login")
    help_parser = sub.add_parser("help", help="Show beginner help and examples")
    help_parser.add_argument("topic", nargs="?", help="overview, tutorial, quickstart, repl, sessions, plan, context, fetch, browser, research, mcp, custom, access, files, remote, freewebpanel, agents, crypto, library, dna, models, examples, commands")
    sub.add_parser("tutorial", help="Run the interactive first-run guide again")
    login = sub.add_parser("login", help="Link this machine to your ZeroThink account")
    login.add_argument("--label", help="Friendly label for this machine")
    login.add_argument("--no-browser", action="store_true", help="Do not open the browser automatically")
    sub.add_parser("logout", help="Revoke the local CLI session")
    sub.add_parser("me", help="Show the linked ZeroThink account")
    sub.add_parser("whoami", help="Alias for me")
    sub.add_parser("about", help="Explain what ZeroThink CLI aka Agent Zero is")
    sub.add_parser("capabilities", help="List the main things Agent Zero can do in this terminal")
    sub.add_parser("skills", help="List practical Agent Zero skills")
    sub.add_parser("status", help="Show a local readiness and session summary")
    steer_parser = sub.add_parser("steer", help="Show or set the interactive steering mode")
    steer_parser.add_argument("mode", nargs="?", choices=["status", "auto", "plan", "confirm"], default="status")
    stop_parser = sub.add_parser("stop", help="Request stop for a running saved agent")
    stop_parser.add_argument("name", nargs="?", help="Agent name (defaults to the latest active agent)")

    key_parser = sub.add_parser("key", help="Manage local direct keys for Groq or NVIDIA")
    key_sub = key_parser.add_subparsers(dest="key_action")
    key_sub.add_parser("status", help="Show local direct-key readiness")
    key_set = key_sub.add_parser("set", help="Save a local direct key")
    key_set.add_argument("provider", choices=list(DIRECT_KEY_PROVIDERS))
    key_set.add_argument("value", nargs="?", help="API key value (omit to enter securely)")
    key_clear = key_sub.add_parser("clear", help="Clear a saved local direct key")
    key_clear.add_argument("provider", choices=list(DIRECT_KEY_PROVIDERS))

    models_parser = sub.add_parser("models", help="List available curated models or coding picks")
    models_parser.add_argument("provider", nargs="?", help='Optional provider name or "coding"')

    config_parser = sub.add_parser("config", help="Show or set local defaults")
    config_parser.add_argument("key", nargs="?")
    config_parser.add_argument("value", nargs="?")

    context_parser = sub.add_parser("context", help="Inspect or toggle project context file support")
    context_parser.add_argument("action", nargs="?", choices=["status", "show", "on", "off"], default="status")

    library_parser = sub.add_parser("library", help="Manage the local no-token Zero Library index")
    library_sub = library_parser.add_subparsers(dest="library_action")
    library_sub.add_parser("status", help="Show library paths and index status")
    library_add = library_sub.add_parser("add", help="Register a file or folder for local library indexing")
    library_add.add_argument("path", help="File or folder path")
    library_remove = library_sub.add_parser("remove", aliases=["rm"], help="Remove a registered library path")
    library_remove.add_argument("path", help="Registered path to remove")
    library_sub.add_parser("index", aliases=["reindex"], help="Rebuild the local library index")
    library_sub.add_parser("files", help="List indexed files")
    library_search_parser = library_sub.add_parser("search", help="Search library files without a model call")
    library_search_parser.add_argument("query", nargs=argparse.REMAINDER, help="Search query")
    library_context_parser = library_sub.add_parser("context", help="Print a compact evidence pack for a query")
    library_context_parser.add_argument("query", nargs=argparse.REMAINDER, help="Context query")

    dna_parser = sub.add_parser("dna", help="Run local research-only DNA/RNA analysis")
    dna_sub = dna_parser.add_subparsers(dest="dna_action")
    dna_analyze = dna_sub.add_parser("analyze", help="Analyze a sequence string or FASTA/text file")
    dna_analyze.add_argument("input", nargs=argparse.REMAINDER, help="Sequence or file path")

    custom_parser = sub.add_parser("custom", help="Inspect local custom command packs")
    custom_parser.add_argument("action", nargs="?", choices=["status", "show"], default="status")
    sub.add_parser("plugins", help="Show the local plugin layers: custom packs and MCP servers")

    autopilot_parser = sub.add_parser("autopilot", help="Run a self-driven local agent over the CLI tool stack")
    autopilot_parser.add_argument("task", nargs="+", help="Autopilot task")
    autopilot_parser.add_argument("--name", help="Optional agent name, default zero1/zero2 style")
    autopilot_parser.add_argument("--provider", help="Provider override")
    autopilot_parser.add_argument("--model", help="Model override")
    autopilot_parser.add_argument("--cwd", help="Working directory override")
    autopilot_parser.add_argument("--steps", type=int, default=8, help="How many controller steps to run")
    autopilot_parser.add_argument("--workers", type=int, default=3, help="Maximum child workers the controller can spawn")

    agent_parser = sub.add_parser("agent", help="Manage named local agents and subagents")
    agent_sub = agent_parser.add_subparsers(dest="agent_action")
    agent_sub.add_parser("list", help="List saved agents")
    agent_show = agent_sub.add_parser("show", help="Show one agent summary")
    agent_show.add_argument("name", help="Agent name")
    agent_log = agent_sub.add_parser("log", help="Show the full agent log")
    agent_log.add_argument("name", help="Agent name")
    agent_rm = agent_sub.add_parser("rm", help="Remove a saved agent")
    agent_rm.add_argument("name", help="Agent name")
    agent_create = agent_sub.add_parser("create", help="Create a named agent without running it yet")
    agent_create.add_argument("name", help="Agent name")
    agent_create.add_argument("task", nargs="+", help="Agent task")
    agent_create.add_argument("--role", default="general", help="Agent role")
    agent_create.add_argument("--provider", help="Provider override")
    agent_create.add_argument("--model", help="Model override")
    agent_create.add_argument("--cwd", help="Working directory override")
    agent_create.add_argument("--steps", type=int, default=8, help="Maximum steps for this agent")
    agent_create.add_argument("--workers", type=int, default=2, help="Maximum child workers")
    agent_step = agent_sub.add_parser("step", help="Run one step for a saved agent")
    agent_step.add_argument("name", help="Agent name")
    agent_run = agent_sub.add_parser("run", help="Run a saved agent for multiple steps")
    agent_run.add_argument("name", help="Agent name")
    agent_run.add_argument("--steps", type=int, default=8, help="How many steps to run now")
    agent_stop = agent_sub.add_parser("stop", help="Request stop for a running saved agent")
    agent_stop.add_argument("name", help="Agent name")

    session_parser = sub.add_parser("session", help="Manage local CLI checkpoints")
    session_sub = session_parser.add_subparsers(dest="session_action")
    session_sub.add_parser("list", help="List local saved sessions")
    session_show = session_sub.add_parser("show", help="Show summary for one saved session")
    session_show.add_argument("name", help="Session name")
    session_export = session_sub.add_parser("export", help="Export one saved session to Markdown")
    session_export.add_argument("name", help="Session name")
    session_export.add_argument("path", nargs="?", help="Optional output file path")
    session_rm = session_sub.add_parser("rm", help="Delete one saved session")
    session_rm.add_argument("name", help="Session name")

    access_parser = sub.add_parser("access", help="Show or set access mode")
    access_parser.add_argument("mode", nargs="?", choices=["status", "machine", "folder"], default="status")
    access_parser.add_argument("path", nargs="?", help="Workspace root when using folder mode")

    workspace_parser = sub.add_parser("workspace", help="Show or set the workspace root")
    workspace_parser.add_argument("path", nargs="?", help="Folder path to store as the workspace root")

    freewebpanel_parser = sub.add_parser(
        "freewebpanel",
        aliases=["fwp"],
        help="FreeWebPanel mode: install, manage, inspect, backup, repair, update, and operate a FreeWebPanel server",
    )
    freewebpanel_sub = freewebpanel_parser.add_subparsers(dest="freewebpanel_action")
    for action in FREEWEBPANEL_COMMANDS:
        action_parser = freewebpanel_sub.add_parser(action, help=f"FreeWebPanel {action}")
        add_freewebpanel_common_arguments(action_parser)
        if action in {"config", "logs"}:
            action_parser.add_argument("--lines", type=int, default=80, help="Number of lines to preview")
        if action == "config":
            action_parser.add_argument("--show-redacted", action="store_true", help="Show a redacted configuration preview")
        if action == "logs":
            action_parser.add_argument("--log-path", help="Read one explicit log file")
        if action in {"backup", "install", "repair", "update", "deploy"}:
            action_parser.add_argument("--output", help="Backup or staged output directory")
        if action == "install":
            action_parser.add_argument("--hostname", help="Panel hostname already pointed to this server")
            action_parser.add_argument("--email", help="Owner email for certificate notices")
            action_parser.add_argument("--ssh-port", type=int, default=22, help="SSH port that must remain reachable")
            action_parser.add_argument("--server-profile", choices=["micro", "small", "provider"], default="provider", help="FreeWebPanel server profile")
            action_parser.add_argument("--preflight-only", action="store_true", help="Run the official installer preflight without package changes")
            action_parser.add_argument("--replace-panel", choices=["cpanel", "cwp", "directadmin", "plesk", "cyberpanel", "hestia", "webmin", "virtualmin", "ispconfig", "aapanel", "generic"], help="Existing panel takeover lane")
            action_parser.add_argument("--confirm-panel-replace", help="Use BACKUP_READY only after verified off-server backups")
        if action == "backup":
            action_parser.add_argument("--include-config", action="store_true", help="Include known FreeWebPanel config directories")
            action_parser.add_argument("--include-sites", action="store_true", default=True, help="Include the selected site or panel path")
            action_parser.add_argument("--no-sites", dest="include_sites", action="store_false", help="Exclude the selected site or panel path")
            action_parser.add_argument("--include-db", action="store_true", help="Record that a separate provider-approved database dump is required")

    server_parser = sub.add_parser("server", help="Manage saved SSH server profiles")
    server_sub = server_parser.add_subparsers(dest="server_action")

    server_add = server_sub.add_parser("add", help="Add or update a server profile")
    server_add.add_argument("name", help="Profile name")
    server_add.add_argument("host", help="Server host or IP")
    server_add.add_argument("--user", help="SSH username")
    server_add.add_argument("--port", type=int, default=22, help="SSH port")
    server_add.add_argument("--cwd", help="Remote working directory")
    server_add.add_argument("--identity", help="SSH identity/private key path")
    server_add.add_argument("--password", help="SSH password to save locally")
    server_add.add_argument("--password-env", help="Environment variable that holds the SSH password")
    server_add.add_argument("--ask-pass", action="store_true", help="Prompt securely for the SSH password now")

    server_sub.add_parser("list", help="List server profiles")
    server_show = server_sub.add_parser("show", help="Show one server profile")
    server_show.add_argument("name", help="Profile name")
    server_rm = server_sub.add_parser("rm", help="Remove a server profile")
    server_rm.add_argument("name", help="Profile name")
    for action_name in ("check", "doctor"):
        server_check = server_sub.add_parser(action_name, help="Run a compact health and file check on a saved server")
        server_check.add_argument("name", help="Profile name")
        server_check.add_argument("path", nargs="?", default=".", help="Remote path to inspect")

    mcp_parser = sub.add_parser("mcp", help="Manage local MCP stdio servers and tools")
    mcp_sub = mcp_parser.add_subparsers(dest="mcp_action")
    mcp_sub.add_parser("list", help="List saved MCP servers")
    mcp_add = mcp_sub.add_parser("add", help="Add a generic stdio MCP server")
    mcp_add.add_argument("name", help="Profile name")
    mcp_add.add_argument("program", help="Program to run, such as npx")
    mcp_add.add_argument("args", nargs=argparse.REMAINDER, help="Arguments to pass to the MCP server command")
    mcp_add.add_argument("--cwd", help="Optional working directory")
    mcp_add.add_argument("--startup-timeout", type=int, default=MCP_STARTUP_TIMEOUT, help="Startup/tools timeout in seconds")
    mcp_add.add_argument("--call-timeout", type=int, default=MCP_CALL_TIMEOUT, help="Tool call timeout in seconds")
    mcp_show = mcp_sub.add_parser("show", help="Show one MCP server profile")
    mcp_show.add_argument("name", help="Profile name")
    mcp_rm = mcp_sub.add_parser("rm", help="Remove one MCP server profile")
    mcp_rm.add_argument("name", help="Profile name")
    mcp_tools = mcp_sub.add_parser("tools", help="List tools exposed by an MCP server")
    mcp_tools.add_argument("name", help="Profile name")
    mcp_call = mcp_sub.add_parser("call", help="Call one MCP tool")
    mcp_call.add_argument("name", help="Profile name")
    mcp_call.add_argument("tool", help="Tool name")
    mcp_call.add_argument("--arg", action="append", default=[], help="Tool argument as key=value")
    mcp_call.add_argument("--json", help="Raw JSON object for tool arguments")
    mcp_pw = mcp_sub.add_parser("add-playwright", help="Add the official Playwright MCP server profile")
    mcp_pw.add_argument("--name", default="playwright", help="Profile name")
    mcp_pw.add_argument("--headless", action="store_true", help="Run Playwright MCP headless")
    mcp_pw.add_argument("--browser", choices=["chrome", "firefox", "webkit", "msedge"], help="Browser channel")
    mcp_pw.add_argument("--isolated", action="store_true", help="Use isolated browser mode")
    mcp_pw.add_argument("--startup-timeout", type=int, default=180, help="Startup/tools timeout in seconds")
    mcp_pw.add_argument("--call-timeout", type=int, default=240, help="Tool call timeout in seconds")

    playwright_parser = sub.add_parser("playwright", help="Use the built-in Playwright MCP browser lane")
    playwright_sub = playwright_parser.add_subparsers(dest="playwright_action")
    playwright_sub.add_parser("status", help="Show the current Playwright MCP profile")
    playwright_tools = playwright_sub.add_parser("tools", help="List Playwright MCP browser tools")
    playwright_tools.add_argument("--profile", default="playwright", help="MCP profile name")
    playwright_open = playwright_sub.add_parser("open", help="Open a URL through Playwright MCP")
    playwright_open.add_argument("url", help="http:// or https:// URL")
    playwright_open.add_argument("--profile", default="playwright", help="MCP profile name")
    playwright_call = playwright_sub.add_parser("call", help="Call one raw Playwright MCP tool")
    playwright_call.add_argument("tool", help="Tool name such as browser_snapshot")
    playwright_call.add_argument("--profile", default="playwright", help="MCP profile name")
    playwright_call.add_argument("--arg", action="append", default=[], help="Tool argument as key=value")
    playwright_call.add_argument("--json", help="Raw JSON object for tool arguments")

    ssh_parser = sub.add_parser("ssh", help="Run a command on a saved server profile")
    ssh_parser.add_argument("name", help="Server profile name")
    ssh_parser.add_argument("ssh_command", nargs=argparse.REMAINDER, help="Remote shell command")
    ssh_parser.add_argument("--timeout", type=int, default=180, help="Timeout in seconds")

    scp_parser = sub.add_parser("scp", help="Transfer files with a saved server profile")
    scp_parser.add_argument("direction", choices=["put", "get"])
    scp_parser.add_argument("name", help="Server profile name")
    scp_parser.add_argument("source", help="Local path or remote path")
    scp_parser.add_argument("target", help="Remote path or local path")
    scp_parser.add_argument("--timeout", type=int, default=1800, help="Timeout in seconds")

    remote_parser = sub.add_parser("remote", help="Remote file and workspace operations on a saved server")
    remote_sub = remote_parser.add_subparsers(dest="remote_action")

    remote_pwd = remote_sub.add_parser("pwd", help="Print the effective remote working directory")
    remote_pwd.add_argument("name", help="Server profile name")

    remote_ls = remote_sub.add_parser("ls", help="List a remote file or directory")
    remote_ls.add_argument("name", help="Server profile name")
    remote_ls.add_argument("path", nargs="?", default=".", help="Remote path")

    remote_tree = remote_sub.add_parser("tree", help="Render a remote directory tree")
    remote_tree.add_argument("name", help="Server profile name")
    remote_tree.add_argument("path", nargs="?", default=".", help="Remote path")
    remote_tree.add_argument("--depth", type=int, default=3, help="Max tree depth")

    remote_scan = remote_sub.add_parser("scan", help="Summarize a remote workspace")
    remote_scan.add_argument("name", help="Server profile name")
    remote_scan.add_argument("path", nargs="?", default=".", help="Remote path")

    for action_name in ("doctor", "check"):
        remote_doctor = remote_sub.add_parser(action_name, help="Run a compact remote server and file health check")
        remote_doctor.add_argument("name", help="Server profile name")
        remote_doctor.add_argument("path", nargs="?", default=".", help="Remote path")

    remote_read = remote_sub.add_parser("read", help="Read a remote text file with line numbers")
    remote_read.add_argument("name", help="Server profile name")
    remote_read.add_argument("path", help="Remote file path")
    remote_read.add_argument("--start", type=int, default=1, help="Starting line number")
    remote_read.add_argument("--lines", type=int, default=MAX_READ_LINES, help="How many lines to show")

    remote_search = remote_sub.add_parser("search", help="Search text in a remote file or directory")
    remote_search.add_argument("name", help="Server profile name")
    remote_search.add_argument("pattern", help="Regex pattern")
    remote_search.add_argument("path", nargs="?", default=".", help="Remote path")
    remote_search.add_argument("--ignore-case", action="store_true", help="Case-insensitive search")

    remote_mkdir = remote_sub.add_parser("mkdir", help="Create a remote directory")
    remote_mkdir.add_argument("name", help="Server profile name")
    remote_mkdir.add_argument("path", help="Remote directory path")

    remote_rm = remote_sub.add_parser("rm", help="Delete a remote file or directory")
    remote_rm.add_argument("name", help="Server profile name")
    remote_rm.add_argument("path", help="Remote path")
    remote_rm.add_argument("--recursive", action="store_true", help="Allow recursive directory delete")

    remote_write = remote_sub.add_parser("write", help="Write text to a remote file")
    remote_write.add_argument("name", help="Server profile name")
    remote_write.add_argument("path", help="Remote file path")
    remote_write.add_argument("--text", help="Text to write")
    remote_write.add_argument("--stdin", action="store_true", help="Read text from stdin")

    remote_append = remote_sub.add_parser("append", help="Append text to a remote file")
    remote_append.add_argument("name", help="Server profile name")
    remote_append.add_argument("path", help="Remote file path")
    remote_append.add_argument("--text", help="Text to append")
    remote_append.add_argument("--stdin", action="store_true", help="Read text from stdin")

    remote_replace = remote_sub.add_parser("replace", help="Replace text inside a remote file")
    remote_replace.add_argument("name", help="Server profile name")
    remote_replace.add_argument("path", help="Remote file path")
    remote_replace.add_argument("old", help="Find text")
    remote_replace.add_argument("new", help="Replacement text")
    remote_replace.add_argument("--all", action="store_true", help="Replace all matches")

    remote_exec = remote_sub.add_parser("exec", help="Run a remote shell command")
    remote_exec.add_argument("name", help="Server profile name")
    remote_exec.add_argument("remote_command", nargs=argparse.REMAINDER, help="Remote shell command")
    remote_exec.add_argument("--timeout", type=int, default=180, help="Timeout in seconds")

    sub.add_parser("doctor", help="Show local CLI readiness")
    sub.add_parser("update", help="Download the latest ZeroThink CLI script to this machine")
    sub.add_parser("runtime", help="Print the exact current CLI provider/model/API route")

    fetch_parser = sub.add_parser("fetch", help="Fetch a URL and print cleaned text")
    fetch_parser.add_argument("url", help="http:// or https:// URL")

    search_parser = sub.add_parser("search", help="Search the web locally and print numbered results")
    search_parser.add_argument("query", nargs=argparse.REMAINDER, help="Search query")

    browse_parser = sub.add_parser("browse", help="Fetch a page locally and print cleaned text")
    browse_parser.add_argument("url", help="http:// or https:// URL")

    chat = sub.add_parser("chat", help="Run a one-shot chat prompt")
    chat.add_argument("prompt", nargs=argparse.REMAINDER, help="Prompt text")
    chat.add_argument("--provider", help="Provider override")
    chat.add_argument("--model", help="Model override")
    chat.add_argument("--file", action="append", help="Attach a file, folder, or archive")
    chat.add_argument("--url", action="append", help="Fetch and attach a URL as extra context")
    chat.add_argument("--image", help="Attach an image")
    chat.add_argument("--use-web", action="store_true")
    chat.add_argument("--no-web", action="store_true")
    chat.add_argument("--zero-on", action="store_true", help="Deprecated in CLI; ignored")
    chat.add_argument("--zero-off", action="store_true", help="Deprecated in CLI; ignored")

    research_parser = sub.add_parser(
        "research",
        help="Create evidence-first papers, articles, surveys, reviews, and proposals from local or saved-server files",
    )
    research_sub = research_parser.add_subparsers(dest="research_action")
    research_sub.add_parser("help", help="Show the research workflow guide")
    research_scan = research_sub.add_parser("scan", help="Inventory research sources without calling a model")
    research_scan.add_argument("--source", action="append", default=[], help="Local file or folder; repeat as needed")
    research_scan.add_argument("--server", help="Saved SSH server profile")
    research_scan.add_argument("--remote-path", action="append", default=[], help="Remote file or folder; repeat as needed")
    research_scan.add_argument("--max-files", type=int, default=MAX_RESEARCH_FILES, help="Maximum source files to inspect")
    research_scan.add_argument("--include-sensitive", action="store_true", help="Include sensitive-looking filenames in a trusted terminal")
    for research_kind in RESEARCH_KINDS:
        research_kind_parser = research_sub.add_parser(research_kind, help=f"Create an evidence-first research {research_kind}")
        research_kind_parser.add_argument("topic", help="Research topic or working title")
        research_kind_parser.add_argument("--source", action="append", default=[], help="Local file or folder; repeat as needed")
        research_kind_parser.add_argument("--server", help="Saved SSH server profile")
        research_kind_parser.add_argument("--remote-path", action="append", default=[], help="Remote file or folder; repeat as needed")
        research_kind_parser.add_argument("--stage", choices=RESEARCH_STAGES, default="full", help="Workflow stage to produce")
        research_kind_parser.add_argument("--question", help="Exact research question or hypothesis")
        research_kind_parser.add_argument("--method", help="Method or study design")
        research_kind_parser.add_argument("--audience", help="Audience, venue, journal, module, or decision-maker")
        research_kind_parser.add_argument("--citation-style", default="Harvard", help="Citation style, such as Harvard, APA 7, or IEEE")
        research_kind_parser.add_argument("--provider", help="Provider override")
        research_kind_parser.add_argument("--model", help="Model override")
        research_kind_parser.add_argument("--web", action="store_true", help="Enable live research when the current route supports it")
        research_kind_parser.add_argument("--library", action="store_true", help="Attach a compact private Zero Library evidence pack")
        research_kind_parser.add_argument("--output", help="Markdown output path")
        research_kind_parser.add_argument("--dry-run", action="store_true", help="Inspect the source plan without calling a model")
        research_kind_parser.add_argument("--max-files", type=int, default=MAX_RESEARCH_FILES, help="Maximum source files to inspect")
        research_kind_parser.add_argument("--include-sensitive", action="store_true", help="Include sensitive-looking filenames in a trusted terminal")

    review = sub.add_parser("review", help="Run a findings-first code review prompt for a path or target")
    review.add_argument("target", help="Path, URL label, or review target")
    review.add_argument("--focus", help="Optional review focus area")
    review.add_argument("--provider", help="Provider override")
    review.add_argument("--model", help="Model override")
    review.add_argument("--url", action="append", help="Attach one or more URLs as extra context")

    pdf_parser = sub.add_parser("pdf", help="Create simple local PDF reports")
    pdf_sub = pdf_parser.add_subparsers(dest="pdf_action")
    pdf_create = pdf_sub.add_parser("create", help="Create a text PDF from --text or --file")
    pdf_create.add_argument("output", help="Output PDF path")
    pdf_create.add_argument("--title", default="", help="PDF title")
    pdf_create.add_argument("--text", default="", help="Text to place in the PDF")
    pdf_create.add_argument("--file", help="Read PDF text from a local source file")

    exec_parser = sub.add_parser("exec", help="Run a local shell command")
    exec_parser.add_argument("exec_command", nargs=argparse.REMAINDER, help="Command to run")
    exec_parser.add_argument("--cwd", help="Working directory")
    exec_parser.add_argument("--timeout", type=int, default=120, help="Timeout in seconds")

    fs_parser = sub.add_parser("fs", help="Local file and workspace operations")
    fs_sub = fs_parser.add_subparsers(dest="fs_action")

    fs_pwd = fs_sub.add_parser("pwd", help="Print current working directory")
    fs_pwd.add_argument("path", nargs="?", help="Optional path to normalize and print")

    fs_ls = fs_sub.add_parser("ls", help="List a file or directory")
    fs_ls.add_argument("path", nargs="?", default=".", help="Path to inspect")
    fs_ls.add_argument("--all", action="store_true", help="Show dotfiles too")

    fs_tree = fs_sub.add_parser("tree", help="Render a directory tree")
    fs_tree.add_argument("path", nargs="?", default=".", help="Path to inspect")
    fs_tree.add_argument("--depth", type=int, default=3, help="Max tree depth")
    fs_tree.add_argument("--all", action="store_true", help="Show dotfiles too")

    fs_scan = fs_sub.add_parser("scan", help="Summarize a workspace")
    fs_scan.add_argument("path", nargs="?", default=".", help="Workspace path")

    fs_read = fs_sub.add_parser("read", help="Read a text file with line numbers")
    fs_read.add_argument("path", help="File path")
    fs_read.add_argument("--start", type=int, default=1, help="Starting line number")
    fs_read.add_argument("--lines", type=int, default=MAX_READ_LINES, help="How many lines to show")

    fs_search = fs_sub.add_parser("search", help="Search text in a file or directory")
    fs_search.add_argument("pattern", help="Regex pattern")
    fs_search.add_argument("path", nargs="?", default=".", help="Path to search")
    fs_search.add_argument("--ignore-case", action="store_true", help="Case-insensitive search")

    fs_mkdir = fs_sub.add_parser("mkdir", help="Create a directory")
    fs_mkdir.add_argument("path", help="Directory path")

    fs_rm = fs_sub.add_parser("rm", help="Delete a file or directory")
    fs_rm.add_argument("path", help="Path to remove")
    fs_rm.add_argument("--recursive", action="store_true", help="Allow recursive directory delete")

    fs_cp = fs_sub.add_parser("cp", help="Copy a file or directory")
    fs_cp.add_argument("src", help="Source path")
    fs_cp.add_argument("dst", help="Destination path")

    fs_mv = fs_sub.add_parser("mv", help="Move or rename a file or directory")
    fs_mv.add_argument("src", help="Source path")
    fs_mv.add_argument("dst", help="Destination path")

    fs_write = fs_sub.add_parser("write", help="Write text to a file")
    fs_write.add_argument("path", help="File path")
    fs_write.add_argument("--text", help="Text to write")
    fs_write.add_argument("--stdin", action="store_true", help="Read text from stdin")

    fs_append = fs_sub.add_parser("append", help="Append text to a file")
    fs_append.add_argument("path", help="File path")
    fs_append.add_argument("--text", help="Text to append")
    fs_append.add_argument("--stdin", action="store_true", help="Read text from stdin")

    fs_replace = fs_sub.add_parser("replace", help="Replace text inside a file")
    fs_replace.add_argument("path", help="File path")
    fs_replace.add_argument("old", help="Find text")
    fs_replace.add_argument("new", help="Replacement text")
    fs_replace.add_argument("--all", action="store_true", help="Replace all matches")

    crypto_parser = sub.add_parser("crypto", help="Encrypt/decrypt local files with the Z-Pepper envelope")
    crypto_sub = crypto_parser.add_subparsers(dest="crypto_action")
    crypto_sub.add_parser("status", help="Show local crypto and Z-Pepper status")

    crypto_z = crypto_sub.add_parser("zpepper", help="Manage the saved local Z-Pepper pattern")
    crypto_z_sub = crypto_z.add_subparsers(dest="zpepper_action")
    crypto_z_sub.add_parser("status", help="Show saved Z-Pepper status")
    crypto_z_set = crypto_z_sub.add_parser("set", help="Save a local Z-Pepper pattern")
    crypto_z_set.add_argument("pattern", nargs="?", help="Pattern value (omit to enter hidden)")
    crypto_z_sub.add_parser("clear", help="Clear the saved local Z-Pepper pattern")

    crypto_enc = crypto_sub.add_parser("encrypt", aliases=["lock", "protect"], help="Encrypt/password-protect one local file or folder")
    crypto_enc.add_argument("path", help="File or folder to encrypt")
    crypto_enc.add_argument("output", nargs="?", help="Optional output path")
    crypto_enc.add_argument("--pattern", "--zpepper", dest="pattern", default="", help="Z-Pepper pattern for the second layer")
    crypto_enc.add_argument("--zpepper-env", default="", help="Environment variable containing the Z-Pepper pattern")
    crypto_enc.add_argument("--passphrase", default="", help="Passphrase value; prefer prompt or --pass-env")
    crypto_enc.add_argument("--pass-env", default="", help="Environment variable containing the passphrase")
    crypto_enc.add_argument("--iterations", type=int, default=CRYPTO_DEFAULT_ITERATIONS, help="PBKDF2 iterations")
    crypto_enc.add_argument("--no-compress", action="store_true", help="Do not compress before encryption")
    crypto_enc.add_argument("--force", action="store_true", help="Overwrite output if it exists")

    crypto_dec = crypto_sub.add_parser("decrypt", aliases=["unlock"], help="Decrypt one local encrypted file or unpack an encrypted folder bundle")
    crypto_dec.add_argument("path", help="Encrypted .ztz file")
    crypto_dec.add_argument("output", nargs="?", help="Optional output file path or bundle destination folder")
    crypto_dec.add_argument("--pattern", "--zpepper", dest="pattern", default="", help="Z-Pepper pattern used during encryption")
    crypto_dec.add_argument("--zpepper-env", default="", help="Environment variable containing the Z-Pepper pattern")
    crypto_dec.add_argument("--passphrase", default="", help="Passphrase value; prefer prompt or --pass-env")
    crypto_dec.add_argument("--pass-env", default="", help="Environment variable containing the passphrase")
    crypto_dec.add_argument("--force", action="store_true", help="Overwrite output if it exists")

    crypto_inspect = crypto_sub.add_parser("inspect", help="Inspect an encrypted file header without decrypting")
    crypto_inspect.add_argument("path", help="Encrypted .ztz file")

    crypto_pack = crypto_sub.add_parser("pack", help="Create an encrypted bundle from files/folders")
    crypto_pack.add_argument("output", help="Output .ztz bundle")
    crypto_pack.add_argument("sources", nargs="+", help="Files or folders to include")
    crypto_pack.add_argument("--pattern", "--zpepper", dest="pattern", default="", help="Z-Pepper pattern for the second layer")
    crypto_pack.add_argument("--zpepper-env", default="", help="Environment variable containing the Z-Pepper pattern")
    crypto_pack.add_argument("--passphrase", default="", help="Passphrase value; prefer prompt or --pass-env")
    crypto_pack.add_argument("--pass-env", default="", help="Environment variable containing the passphrase")
    crypto_pack.add_argument("--iterations", type=int, default=CRYPTO_DEFAULT_ITERATIONS, help="PBKDF2 iterations")
    crypto_pack.add_argument("--force", action="store_true", help="Overwrite output if it exists")

    crypto_unpack = crypto_sub.add_parser("unpack", help="Decrypt and extract an encrypted bundle")
    crypto_unpack.add_argument("path", help="Encrypted bundle")
    crypto_unpack.add_argument("dest", help="Destination folder")
    crypto_unpack.add_argument("--pattern", "--zpepper", dest="pattern", default="", help="Z-Pepper pattern used during encryption")
    crypto_unpack.add_argument("--zpepper-env", default="", help="Environment variable containing the Z-Pepper pattern")
    crypto_unpack.add_argument("--passphrase", default="", help="Passphrase value; prefer prompt or --pass-env")
    crypto_unpack.add_argument("--pass-env", default="", help="Environment variable containing the passphrase")
    crypto_unpack.add_argument("--force", action="store_true", help="Allow extraction into a non-empty folder")

    site_parser = sub.add_parser("site", help="Create a polished static website starter")
    site_sub = site_parser.add_subparsers(dest="site_action")
    site_new = site_sub.add_parser("new", help="Scaffold a responsive static website")
    site_new.add_argument("path", help="Destination folder")
    site_new.add_argument("--title", default="", help="Display title")
    site_new.add_argument("--theme", choices=["zero", "clean", "luxury", "cyber"], default="zero", help="Visual direction")
    site_new.add_argument("--force", action="store_true", help="Allow writing into a non-empty folder")

    game_parser = sub.add_parser("game", help="Create a playable browser game starter")
    game_sub = game_parser.add_subparsers(dest="game_action")
    game_new = game_sub.add_parser("new", help="Scaffold a small HTML/CSS/JS game")
    game_new.add_argument("path", help="Destination folder")
    game_new.add_argument("--title", default="", help="Display title")
    game_new.add_argument("--force", action="store_true", help="Allow writing into a non-empty folder")

    zip_parser = sub.add_parser("zip", help="Inspect or create archives")
    zip_parser.add_argument("zip_action", choices=["list", "extract", "create"])
    zip_parser.add_argument("archive", nargs="?")
    zip_parser.add_argument("rest", nargs="*")

    sub.add_parser("version", help="Print version")
    return parser


def main() -> int:
    try:
        parser = build_parser()
        args = parser.parse_args()

        if args.command == "init":
            return login_flow()
        if args.command == "help":
            print_cli_help(args.topic)
            return 0
        if args.command == "tutorial":
            return run_first_run_tutorial(force=True, startup=False)
        if args.command == "login":
            return login_flow(label=args.label or "", no_browser=args.no_browser)
        if args.command == "logout":
            return logout()
        if args.command in {"me", "whoami"}:
            config = load_config()
            try:
                show_account_summary(api_cli(config, "me", auth=True).get("user", {}))
            except Exception as exc:
                print(f"Identity check failed: {exc}")
                print_direct_key_status(config)
            return 0
        if args.command in {"about", "capabilities"}:
            config = load_config()
            provider = str(config.get("provider", "groq")).lower()
            if provider not in PROVIDERS:
                provider = "groq"
            model = normalize_model(provider, str(config.get("model", PROVIDERS[provider]["default_model"])))
            try:
                runtime_mode, _runtime_key, runtime_source = resolve_chat_mode(config, provider)
            except Exception:
                runtime_mode, runtime_source = ("account" if str(config.get("auth_token") or "").strip() else "direct"), ""
            route = current_runtime_route(config, provider, model, runtime_mode, requested_source=runtime_source)
            print(format_agent_zero_identity(route) if args.command == "about" else format_agent_zero_capabilities(route))
            return 0
        if args.command == "skills":
            print_skill_catalog()
            return 0
        if args.command == "status":
            config = load_config()
            provider = str(config.get("provider", "groq")).lower()
            if provider not in PROVIDERS:
                provider = "groq"
            model = normalize_model(provider, str(config.get("model", PROVIDERS[provider]["default_model"])))
            zero_mode = boolish(config.get("zero_mode", False))
            use_web = boolish(config.get("use_web", False))
            current_dir = active_working_dir(config)
            show_session_status(config, provider, model, zero_mode, use_web, [], [], [], [], current_dir, transcript=[])
            return 0
        if args.command == "steer":
            config = load_config()
            if args.mode == "status":
                print(f"Steering mode: {steering_mode_from(config)}")
                print("Modes: auto, plan, confirm")
                return 0
            config["steering_mode"] = normalize_steering_mode(args.mode)
            save_config(config)
            print(f"Steering mode set to {config['steering_mode']}")
            return 0
        if args.command == "stop":
            target_name = str(args.name or "").strip() or latest_stoppable_agent_name()
            if not target_name:
                raise RuntimeError("No active saved agent was found to stop.")
            agent = request_agent_stop(target_name)
            print(f"Stop requested for `{agent.get('name')}`.")
            return 0
        if args.command == "runtime":
            config = load_config()
            provider = str(config.get("provider", "groq")).lower()
            if provider not in PROVIDERS:
                provider = "groq"
            model = normalize_model(provider, str(config.get("model", PROVIDERS[provider]["default_model"])))
            try:
                runtime_mode, _runtime_key, runtime_source = resolve_chat_mode(config, provider)
            except Exception:
                runtime_mode, runtime_source = ("account" if str(config.get("auth_token") or "").strip() else "direct"), ""
            print(format_runtime_route(current_runtime_route(config, provider, model, runtime_mode, requested_source=runtime_source)))
            return 0
        if args.command == "key":
            if args.key_action in {None, "status"}:
                print_direct_key_status(load_config())
                return 0
            if args.key_action == "set":
                set_direct_key(args.provider, args.value or "")
                return 0
            if args.key_action == "clear":
                clear_direct_key(args.provider)
                return 0
            raise RuntimeError("Usage: zerothink key status | zerothink key set groq | zerothink key clear groq")
        if args.command == "models":
            list_models(args.provider.lower() if args.provider else None)
            return 0
        if args.command == "config":
            show_config(args.key, args.value)
            return 0
        if args.command == "context":
            config = load_config()
            current_dir = active_working_dir(config)
            if args.action == "status":
                print_context_status(current_dir, config, show_preview=False)
                return 0
            if args.action == "show":
                print_context_status(current_dir, config, show_preview=True)
                return 0
            config["project_context_enabled"] = args.action == "on"
            save_config(config)
            print_context_status(current_dir, config, show_preview=False)
            return 0
        if args.command == "library":
            action = args.library_action or "status"
            if action == "status":
                library_status()
                return 0
            if action == "add":
                library_add_path(args.path)
                return 0
            if action in {"remove", "rm"}:
                library_remove_path(args.path)
                return 0
            if action in {"index", "reindex"}:
                index = library_build_index(force=True)
                print(f"Rebuilt Zero Library index: {index.get('count', 0)} file(s).")
                return 0
            if action == "files":
                library_print_files()
                return 0
            if action == "search":
                query = " ".join(args.query or []).strip()
                if not query:
                    raise RuntimeError("Usage: zerothink library search QUERY")
                library_print_search(query)
                return 0
            if action == "context":
                query = " ".join(args.query or []).strip()
                if not query:
                    raise RuntimeError("Usage: zerothink library context QUERY")
                print(library_context_pack(query) or "No Zero Library context found.")
                return 0
            raise RuntimeError("Usage: zerothink library status|add|remove|index|files|search|context ...")
        if args.command == "dna":
            action = args.dna_action or "analyze"
            if action == "analyze":
                raw_value = " ".join(args.input or []).strip()
                if not raw_value:
                    raise RuntimeError("Usage: zerothink dna analyze SEQUENCE_OR_FILE")
                path_candidate = pathlib.Path(raw_value).expanduser()
                if path_candidate.exists():
                    raw_value = str(path_candidate.resolve())
                print(dna_markdown(dna_analyze(raw_value)))
                return 0
            raise RuntimeError("Usage: zerothink dna analyze SEQUENCE_OR_FILE")
        if args.command == "custom":
            config = load_config()
            current_dir = active_working_dir(config)
            print_custom_command_status(current_dir, config, show_detail=(args.action == "show"))
            return 0
        if args.command == "plugins":
            config = load_config()
            current_dir = active_working_dir(config)
            print("Plugin layer:")
            print_custom_command_status(current_dir, config, show_detail=False)
            print("")
            print_mcp_servers(config)
            return 0
        if args.command == "autopilot":
            task = " ".join(args.task or []).strip()
            if not task:
                raise RuntimeError("Usage: zerothink autopilot \"task\"")
            cwd = resolve_scoped_path(args.cwd, load_config()) if getattr(args, "cwd", None) else active_working_dir(load_config())
            agent = start_autopilot(
                task=task,
                name=args.name,
                provider=args.provider,
                model=args.model,
                cwd=cwd,
                steps=max(1, min(int(args.steps or 8), 48)),
                workers=max(0, min(int(args.workers or 3), 8)),
                role="controller",
            )
            print("Final:")
            print(safe_console_text(str(agent.get("final_reply") or agent.get("status") or "").strip()))
            return 0
        if args.command == "agent":
            action = args.agent_action or "list"
            if action == "list":
                print_saved_agents()
                return 0
            if action == "show":
                print_agent_summary(load_saved_agent(args.name))
                return 0
            if action == "log":
                print_agent_log(load_saved_agent(args.name))
                return 0
            if action == "rm":
                remove_saved_agent(args.name)
                print(f"Removed agent `{safe_agent_name(args.name)}`.")
                return 0
            if action == "create":
                task = " ".join(args.task or []).strip()
                if not task:
                    raise RuntimeError("Usage: zerothink agent create NAME \"task\"")
                cwd = resolve_scoped_path(args.cwd, load_config()) if getattr(args, "cwd", None) else active_working_dir(load_config())
                agent = create_agent_state(
                    task=task,
                    name=args.name,
                    role=args.role,
                    provider=args.provider,
                    model=args.model,
                    current_dir=cwd,
                    max_steps=max(1, min(int(args.steps or 8), 48)),
                    worker_limit=max(0, min(int(args.workers or 2), 8)),
                )
                print(f"Created agent `{agent.get('name')}`.")
                print_agent_summary(agent)
                return 0
            if action == "step":
                agent = run_agent_step(args.name, silent=False)
                print_agent_summary(agent)
                return 0
            if action == "run":
                agent = run_agent_until_stop(args.name, max_steps=max(1, min(int(args.steps or 8), 48)), silent=False)
                print("Final:")
                print(safe_console_text(str(agent.get("final_reply") or agent.get("status") or "").strip()))
                return 0
            if action == "stop":
                agent = request_agent_stop(args.name)
                print(f"Stop requested for `{agent.get('name')}`.")
                return 0
            raise RuntimeError("Usage: zerothink agent list|show|log|rm|create|step|run|stop ...")
        if args.command == "session":
            action = args.session_action or "list"
            if action == "list":
                print_saved_sessions()
                return 0
            if action == "show":
                saved = load_saved_session(args.name)
                print(f"Name: {saved.get('name')}")
                print(f"Saved at: {saved.get('saved_at')}")
                print(f"Provider: {saved.get('provider')}")
                print(f"Model: {saved.get('model')}")
                print(f"Turns: {len(saved.get('transcript') or [])}")
                print(f"Plan items: {len(saved.get('plan_items') or [])}")
                print(f"Browser pages: {len(saved.get('browser_pages') or [])}")
                print(f"Dir: {saved.get('current_dir') or ''}")
                return 0
            if action == "export":
                saved = load_saved_session(args.name)
                output = pathlib.Path(args.path).expanduser().resolve() if args.path else None
                exported = export_session_markdown(
                    list(saved.get("transcript") or []),
                    str(saved.get("provider") or ""),
                    str(saved.get("model") or ""),
                    False,
                    bool(saved.get("use_web", False)),
                    pathlib.Path(str(saved.get("current_dir") or pathlib.Path.cwd())).expanduser().resolve(),
                    [str(item) for item in list(saved.get("attachments") or [])],
                    plan_items=normalize_plan_items(saved.get("plan_items")),
                    url_attachments=[str(item) for item in list(saved.get("url_attachments") or [])],
                    browser_pages=normalize_browser_pages(saved.get("browser_pages")),
                    output_path=output,
                )
                print(f"Exported to {exported}")
                return 0
            if action == "rm":
                remove_saved_session(args.name)
                print(f"Removed session `{safe_session_name(args.name)}`.")
                return 0
            raise RuntimeError("Usage: zerothink session list|show|export|rm ...")
        if args.command == "access":
            if args.mode == "status":
                show_access_status(load_config())
                return 0
            if args.mode == "machine":
                config = set_access_mode("machine")
                print("Access mode set to machine.")
                show_access_status(config)
                return 0
            if args.mode == "folder":
                target = resolve_cli_path(args.path or ".")
                if not target.exists() or not target.is_dir():
                    raise RuntimeError(f"Directory not found: {target}")
                config = set_access_mode("folder", workspace=target)
                print(f"Access mode set to folder at {target.resolve()}")
                return 0
        if args.command == "workspace":
            config = load_config()
            if not args.path:
                root = workspace_root_from_config(config)
                print(root if root else "")
                return 0
            target = resolve_cli_path(args.path)
            if not target.exists() or not target.is_dir():
                raise RuntimeError(f"Directory not found: {target}")
            config["workspace_root"] = str(target.resolve())
            save_config(config)
            print(f"Workspace root set to {target.resolve()}")
            return 0
        if args.command in {"freewebpanel", "fwp"}:
            if bool(getattr(args, "backup_first", False)) and bool(getattr(args, "no_backup", False)):
                raise RuntimeError("Choose either --backup-first or --no-backup, not both.")
            return handle_freewebpanel_command(args)
        if args.command == "server":
            config = load_config()
            profiles = get_server_profiles(config)
            if args.server_action == "add":
                password = str(args.password or "").strip()
                if getattr(args, "ask_pass", False):
                    password = getpass.getpass("SSH password: ")
                profiles[args.name] = {
                    "host": args.host,
                    "user": args.user or "",
                    "port": args.port or 22,
                    "cwd": args.cwd or "",
                    "identity": str(pathlib.Path(args.identity).expanduser().resolve()) if args.identity else "",
                    "password": password,
                    "password_env": str(args.password_env or "").strip(),
                }
                save_server_profiles(config, profiles)
                print(f"Saved server profile `{args.name}`.")
                return 0
            if args.server_action == "list":
                print_server_profiles(config)
                return 0
            if args.server_action == "show":
                profile = dict(get_server_profile(config, args.name))
                if profile.get("password"):
                    profile["password"] = mask_secret(str(profile.get("password") or ""))
                print(json.dumps(profile, indent=2, ensure_ascii=False))
                return 0
            if args.server_action == "rm":
                if args.name not in profiles:
                    raise RuntimeError(f"Unknown server profile: {args.name}")
                del profiles[args.name]
                save_server_profiles(config, profiles)
                print(f"Removed server profile `{args.name}`.")
                return 0
            if args.server_action in {"check", "doctor"}:
                require_machine_mode(config)
                return remote_health(get_server_profile(config, args.name), args.path)
            raise RuntimeError("Usage: zerothink server add|list|show|rm|check|doctor ...")
        if args.command == "mcp":
            config = load_config()
            require_machine_mode(config)
            servers = get_mcp_servers(config)
            action = args.mcp_action or "list"
            if action == "list":
                print_mcp_servers(config)
                return 0
            if action == "add":
                servers[args.name] = {
                    "command": str(args.program).strip(),
                    "args": [str(item) for item in list(args.args or []) if str(item).strip()],
                    "cwd": str(args.cwd or "").strip(),
                    "transport": "stdio",
                    "description": "Generic stdio MCP server",
                    "env": {},
                    "startup_timeout": max(10, int(args.startup_timeout or MCP_STARTUP_TIMEOUT)),
                    "call_timeout": max(10, int(args.call_timeout or MCP_CALL_TIMEOUT)),
                }
                save_mcp_servers(config, servers)
                print(f"Saved MCP server `{args.name}`.")
                print(format_mcp_command(servers[args.name]))
                return 0
            if action == "add-playwright":
                servers[args.name] = build_playwright_mcp_profile(
                    headless=bool(args.headless),
                    browser_name=str(args.browser or "").strip(),
                    isolated=bool(args.isolated),
                )
                servers[args.name]["startup_timeout"] = max(10, int(args.startup_timeout or 180))
                servers[args.name]["call_timeout"] = max(10, int(args.call_timeout or 240))
                save_mcp_servers(config, servers)
                print(f"Saved Playwright MCP profile `{args.name}`.")
                print(format_mcp_command(servers[args.name]))
                return 0
            if action == "show":
                print(json.dumps(get_mcp_profile(config, args.name), indent=2, ensure_ascii=False))
                return 0
            if action == "rm":
                if args.name not in servers:
                    raise RuntimeError(f"Unknown MCP server: {args.name}")
                del servers[args.name]
                save_mcp_servers(config, servers)
                print(f"Removed MCP server `{args.name}`.")
                return 0
            if action == "tools":
                print_mcp_tools(get_mcp_profile(config, args.name))
                return 0
            if action == "call":
                payload = {}
                if args.json:
                    try:
                        payload = json.loads(args.json)
                    except Exception as exc:
                        raise RuntimeError(f"Invalid JSON for --json: {exc}") from exc
                    if not isinstance(payload, dict):
                        raise RuntimeError("--json must decode to an object.")
                if args.arg:
                    payload.update(parse_key_value_args(args.arg))
                result = run_mcp_stdio(get_mcp_profile(config, args.name), "call", tool_name=args.tool, arguments=payload)
                print_mcp_call_result(result)
                return 0
            raise RuntimeError("Usage: zerothink mcp list|add|add-playwright|show|rm|tools|call ...")
        if args.command == "playwright":
            config = load_config()
            require_machine_mode(config)
            action = args.playwright_action or "status"
            profile = ensure_playwright_profile(config, getattr(args, "profile", "playwright"))
            if action == "status":
                print(json.dumps(profile, indent=2, ensure_ascii=False))
                return 0
            if action == "tools":
                print_mcp_tools(profile)
                return 0
            if action == "open":
                print_mcp_call_result(run_playwright_open(profile, args.url))
                return 0
            if action == "call":
                payload = {}
                if args.json:
                    try:
                        payload = json.loads(args.json)
                    except Exception as exc:
                        raise RuntimeError(f"Invalid JSON for --json: {exc}") from exc
                    if not isinstance(payload, dict):
                        raise RuntimeError("--json must decode to an object.")
                if args.arg:
                    payload.update(parse_key_value_args(args.arg))
                print_mcp_call_result(run_mcp_stdio(profile, "call", tool_name=args.tool, arguments=payload))
                return 0
            raise RuntimeError("Usage: zerothink playwright status|tools|open|call ...")
        if args.command == "ssh":
            if not args.ssh_command:
                raise RuntimeError("Usage: zerothink ssh NAME COMMAND")
            config = load_config()
            require_machine_mode(config)
            profile = get_server_profile(config, args.name)
            return run_remote_command(profile, " ".join(args.ssh_command), timeout=args.timeout)
        if args.command == "scp":
            config = load_config()
            require_machine_mode(config)
            profile = get_server_profile(config, args.name)
            if args.direction == "put":
                source = str(resolve_scoped_path(args.source, config))
                return scp_transfer(profile, source, args.target, download=False, timeout=args.timeout)
            target = str(resolve_scoped_path(args.target, config))
            return scp_transfer(profile, args.source, target, download=True, timeout=args.timeout)
        if args.command == "remote":
            config = load_config()
            require_machine_mode(config)
            action = args.remote_action
            if not action:
                raise RuntimeError("Usage: zerothink remote ACTION NAME ...")
            profile = get_server_profile(config, args.name)
            if action == "pwd":
                print(resolve_remote_path(profile, "."))
                return 0
            if action == "ls":
                remote_ls(profile, args.path)
                return 0
            if action == "tree":
                remote_tree(profile, args.path, max_depth=max(1, min(int(args.depth), 8)))
                return 0
            if action == "scan":
                remote_scan(profile, args.path)
                return 0
            if action in {"doctor", "check"}:
                return remote_health(profile, args.path)
            if action == "read":
                remote_read_text(profile, args.path, start=args.start, lines=args.lines)
                return 0
            if action == "search":
                remote_search(profile, args.pattern, args.path, ignore_case=args.ignore_case)
                return 0
            if action == "mkdir":
                remote_mkdir(profile, args.path)
                return 0
            if action == "rm":
                remote_rm(profile, args.path, recursive=args.recursive)
                return 0
            if action in {"write", "append"}:
                text = ""
                if args.stdin:
                    text = sys.stdin.read()
                elif args.text is not None:
                    text = args.text
                else:
                    raise RuntimeError(f"Usage: zerothink remote {action} NAME PATH --text \"...\" or --stdin")
                remote_write_text(profile, args.path, text, append=(action == "append"))
                return 0
            if action == "replace":
                remote_replace_text(profile, args.path, args.old, args.new, replace_all=args.all)
                return 0
            if action == "exec":
                if not args.remote_command:
                    raise RuntimeError("Usage: zerothink remote exec NAME COMMAND")
                return run_remote_command(profile, " ".join(args.remote_command), timeout=args.timeout)
            raise RuntimeError("Usage: zerothink remote pwd|ls|tree|scan|doctor|check|read|search|mkdir|rm|write|append|replace|exec ...")
        if args.command == "doctor":
            doctor()
            return 0
        if args.command == "update":
            return self_update(load_config())
        if args.command == "fetch":
            print(fetch_url_text(args.url))
            return 0
        if args.command == "search":
            query = " ".join(args.query or []).strip()
            if not query:
                raise RuntimeError("Usage: zerothink search QUERY")
            print_browser_pages(safe_search_web(query))
            return 0
        if args.command == "browse":
            print_browser_page(fetch_browser_page(args.url))
            return 0
        if args.command == "chat":
            return chat_once(args)
        if args.command == "research":
            return run_research_workflow(args)
        if args.command == "review":
            return review_once(args)
        if args.command == "pdf":
            action = args.pdf_action or "create"
            if action == "create":
                config = load_config()
                output = resolve_scoped_path(args.output, config)
                body = str(args.text or "")
                if args.file:
                    source = resolve_scoped_path(args.file, config)
                    try:
                        file_text = source.read_text(encoding="utf-8", errors="replace")
                    except Exception as exc:
                        raise RuntimeError(f"Could not read PDF source file: {exc}") from exc
                    body = (body + "\n\n" if body else "") + file_text
                if not body:
                    raise RuntimeError("Usage: zerothink pdf create OUT.pdf --text \"...\" or --file notes.md")
                create_text_pdf(output, args.title or output.stem, body)
                return 0
            raise RuntimeError("Usage: zerothink pdf create OUT.pdf --text \"...\" or --file notes.md")
        if args.command == "exec":
            if not args.exec_command:
                raise RuntimeError("Usage: zerothink exec COMMAND")
            config = load_config()
            require_machine_mode(config)
            cwd = resolve_scoped_path(args.cwd, config) if args.cwd else pathlib.Path.cwd().resolve()
            return run_local_command(" ".join(args.exec_command), cwd=cwd, timeout=args.timeout)
        if args.command == "fs":
            action = args.fs_action
            if not action:
                raise RuntimeError("Usage: zerothink fs ACTION ...")
            config = load_config()
            if action == "pwd":
                print(resolve_scoped_path(args.path, config) if args.path else (workspace_root_from_config(config) if access_mode_from(config) == "folder" and workspace_root_from_config(config) else pathlib.Path.cwd().resolve()))
                return 0
            if action == "ls":
                print_path_listing(resolve_scoped_path(args.path, config), show_all=args.all)
                return 0
            if action == "tree":
                print_tree(resolve_scoped_path(args.path, config), max_depth=max(1, min(int(args.depth), 8)), show_all=args.all)
                return 0
            if action == "scan":
                print_workspace_scan(resolve_scoped_path(args.path, config))
                return 0
            if action == "read":
                read_text_preview(resolve_scoped_path(args.path, config), start=args.start, lines=args.lines)
                return 0
            if action == "search":
                search_workspace(args.pattern, resolve_scoped_path(args.path, config), ignore_case=args.ignore_case)
                return 0
            if action == "mkdir":
                path = resolve_scoped_path(args.path, config)
                path.mkdir(parents=True, exist_ok=True)
                print(f"Created {path}")
                return 0
            if action == "rm":
                path = resolve_scoped_path(args.path, config)
                if not path.exists():
                    raise RuntimeError(f"Path not found: {path}")
                if path.is_dir():
                    if not args.recursive:
                        raise RuntimeError("Refusing to remove a directory without --recursive.")
                    shutil.rmtree(path)
                else:
                    path.unlink()
                print(f"Removed {path}")
                return 0
            if action == "cp":
                src = resolve_scoped_path(args.src, config)
                dst = resolve_scoped_path(args.dst, config)
                if src.is_dir():
                    shutil.copytree(src, dst, dirs_exist_ok=True)
                else:
                    dst.parent.mkdir(parents=True, exist_ok=True)
                    shutil.copy2(src, dst)
                print(f"Copied {src} -> {dst}")
                return 0
            if action == "mv":
                src = resolve_scoped_path(args.src, config)
                dst = resolve_scoped_path(args.dst, config)
                dst.parent.mkdir(parents=True, exist_ok=True)
                shutil.move(str(src), str(dst))
                print(f"Moved {src} -> {dst}")
                return 0
            if action in {"write", "append"}:
                text = ""
                if args.stdin:
                    text = sys.stdin.read()
                elif args.text is not None:
                    text = args.text
                else:
                    raise RuntimeError(f"Usage: zerothink fs {action} PATH --text \"...\" or --stdin")
                write_text_file(resolve_scoped_path(args.path, config), text, append=(action == "append"))
                return 0
            if action == "replace":
                replace_in_file(resolve_scoped_path(args.path, config), args.old, args.new, replace_all=args.all)
                return 0
        if args.command == "crypto":
            config = load_config()
            action = args.crypto_action or "status"
            if action == "status":
                crypto_status(config)
                return 0
            if action == "zpepper":
                zaction = args.zpepper_action or "status"
                if zaction == "status":
                    crypto_status(config)
                    return 0
                if zaction == "set":
                    set_crypto_zpepper(args.pattern or "")
                    return 0
                if zaction == "clear":
                    clear_crypto_zpepper()
                    return 0
                raise RuntimeError("Usage: zerothink crypto zpepper status|set|clear")
            if action == "inspect":
                crypto_inspect_file(resolve_scoped_path(args.path, config))
                return 0
            if action in {"encrypt", "lock", "protect"}:
                source = resolve_scoped_path(args.path, config)
                output = resolve_scoped_path(args.output, config) if args.output else None
                pattern = resolve_crypto_pattern(config, args.pattern, args.zpepper_env)
                passphrase = resolve_crypto_passphrase(args.passphrase, args.pass_env, confirm=not bool(args.passphrase or args.pass_env))
                crypto_encrypt_path(
                    source,
                    output,
                    passphrase,
                    pattern,
                    iterations=args.iterations,
                    compress=not bool(args.no_compress),
                    force=bool(args.force),
                )
                return 0
            if action in {"decrypt", "unlock"}:
                source = resolve_scoped_path(args.path, config)
                output = resolve_scoped_path(args.output, config) if args.output else None
                pattern = resolve_crypto_pattern(config, args.pattern, args.zpepper_env)
                passphrase = resolve_crypto_passphrase(args.passphrase, args.pass_env, confirm=False)
                crypto_decrypt_path(source, output, passphrase, pattern, force=bool(args.force))
                return 0
            if action == "pack":
                output = resolve_scoped_path(args.output, config)
                sources = [resolve_scoped_path(item, config) for item in list(args.sources or [])]
                pattern = resolve_crypto_pattern(config, args.pattern, args.zpepper_env)
                passphrase = resolve_crypto_passphrase(args.passphrase, args.pass_env, confirm=not bool(args.passphrase or args.pass_env))
                crypto_pack_paths(output, sources, passphrase, pattern, iterations=args.iterations, force=bool(args.force))
                return 0
            if action == "unpack":
                source = resolve_scoped_path(args.path, config)
                dest = resolve_scoped_path(args.dest, config)
                pattern = resolve_crypto_pattern(config, args.pattern, args.zpepper_env)
                passphrase = resolve_crypto_passphrase(args.passphrase, args.pass_env, confirm=False)
                crypto_unpack_bundle(source, dest, passphrase, pattern, force=bool(args.force))
                return 0
            raise RuntimeError("Usage: zerothink crypto status|zpepper|encrypt|decrypt|inspect|pack|unpack ...")
        if args.command == "site":
            action = args.site_action or "new"
            if action == "new":
                config = load_config()
                site_scaffold(resolve_scoped_path(args.path, config), title=args.title or pathlib.Path(args.path).name, theme=args.theme, force=bool(args.force))
                return 0
            raise RuntimeError("Usage: zerothink site new PATH [--title TITLE] [--theme zero|clean|luxury|cyber]")
        if args.command == "game":
            action = args.game_action or "new"
            if action == "new":
                config = load_config()
                game_scaffold(resolve_scoped_path(args.path, config), title=args.title or pathlib.Path(args.path).name, force=bool(args.force))
                return 0
            raise RuntimeError("Usage: zerothink game new PATH [--title TITLE] [--force]")
        if args.command == "zip":
            config = load_config()
            if args.zip_action == "list":
                if not args.archive:
                    raise RuntimeError("Usage: zerothink zip list ARCHIVE")
                zip_list(resolve_scoped_path(args.archive, config))
                return 0
            if args.zip_action == "extract":
                if not args.archive:
                    raise RuntimeError("Usage: zerothink zip extract ARCHIVE [DEST]")
                archive = resolve_scoped_path(args.archive, config)
                dest = resolve_scoped_path(args.rest[0], config) if args.rest else resolve_scoped_path(archive.stem, config)
                zip_extract(archive, dest)
                print(f"Extracted to {dest}")
                return 0
            if args.zip_action == "create":
                if not args.archive or not args.rest:
                    raise RuntimeError("Usage: zerothink zip create OUT.zip path1 [path2 ...]")
                output = resolve_scoped_path(args.archive, config)
                sources = [resolve_scoped_path(item, config) for item in args.rest]
                zip_create(output, sources)
                print(f"Created {output}")
                return 0
        if args.command == "version":
            print(VERSION)
            return 0

        config = load_config()
        if is_tty_session() and not tutorial_completed(config):
            run_first_run_tutorial(force=False, startup=True)
        return repl()
    except RuntimeError as exc:
        print(str(exc), file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
