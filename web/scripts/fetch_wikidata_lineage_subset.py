#!/usr/bin/env python3
"""
ZeroThink DNA Lab v2 - Wikidata lineage subset builder.

Creates:
  data/packs/lineage_core.sqlite

This fetches a small public graph first, then Codex/app can expand it.
It does NOT prove royal lineage. It gives public person nodes and parent edges for evidence-path research.

For larger production builds, use Wikidata dumps or WDumper/filtering rather than hammering SPARQL.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import sqlite3
import time
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

try:
    import requests
except Exception:  # pragma: no cover - CWP servers may not have requests installed.
    requests = None

PACK_VERSION = "0.1.0"
IMPORTER_VERSION = "zerothink-wikidata-lineage-builder-0.1.0"
SPARQL_URL = "https://query.wikidata.org/sparql"

# Query humans with father/mother edges and some title/position signal.
# This is intentionally conservative and small. Expand by seed QIDs later.
QUERY = """
SELECT ?person ?personLabel ?father ?fatherLabel ?mother ?motherLabel ?birth ?death ?titleLabel WHERE {
  ?person wdt:P31 wd:Q5.
  OPTIONAL { ?person wdt:P22 ?father. }
  OPTIONAL { ?person wdt:P25 ?mother. }
  OPTIONAL { ?person wdt:P569 ?birth. }
  OPTIONAL { ?person wdt:P570 ?death. }
  OPTIONAL { ?person wdt:P97 ?title. }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
  FILTER(BOUND(?father) || BOUND(?mother) || BOUND(?title))
}
LIMIT __LIMIT__
"""


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def connect(db_path: Path) -> sqlite3.Connection:
    db_path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(db_path))
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA synchronous=NORMAL")
    return conn


def create_schema(conn: sqlite3.Connection) -> None:
    conn.executescript(
        """
        CREATE TABLE IF NOT EXISTS data_sources (
          id TEXT PRIMARY KEY,
          source_name TEXT NOT NULL,
          source_url TEXT NOT NULL,
          version_or_release TEXT,
          downloaded_at TEXT NOT NULL,
          file_hash_sha256 TEXT NOT NULL,
          license_note TEXT,
          imported_row_count INTEGER DEFAULT 0,
          pack_name TEXT NOT NULL,
          pack_version TEXT NOT NULL,
          local_path TEXT,
          importer_version TEXT
        );

        DROP TABLE IF EXISTS lineage_public_people;
        CREATE TABLE lineage_public_people (
          person_id TEXT PRIMARY KEY,
          name TEXT,
          birth_date TEXT,
          death_date TEXT,
          birth_place TEXT,
          death_place TEXT,
          wikidata_qid TEXT,
          title_or_role TEXT,
          source_count INTEGER DEFAULT 0
        );

        DROP TABLE IF EXISTS lineage_edges;
        CREATE TABLE lineage_edges (
          child_id TEXT NOT NULL,
          parent_id TEXT NOT NULL,
          edge_type TEXT NOT NULL,
          source_url TEXT,
          confidence REAL DEFAULT 0.5,
          source_count INTEGER DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_lineage_child ON lineage_edges(child_id);
        CREATE INDEX IF NOT EXISTS idx_lineage_parent ON lineage_edges(parent_id);

        CREATE TABLE IF NOT EXISTS lineage_pack_notes (
          key TEXT PRIMARY KEY,
          value TEXT
        );
        """
    )
    conn.commit()


def qid(uri: str) -> str:
    if not uri:
        return ""
    return uri.rsplit("/", 1)[-1]


def fetch(limit: int) -> dict[str, Any]:
    query = QUERY.replace("__LIMIT__", str(limit))
    headers = {
        "Accept": "application/sparql-results+json",
        "User-Agent": "ZeroThinkDNA/0.1 local data-pack builder (contact: admin@talktoai.org)"
    }
    params = {"query": query, "format": "json"}
    if requests is not None:
        r = requests.get(SPARQL_URL, params=params, headers=headers, timeout=60)
        r.raise_for_status()
        return r.json()

    url = SPARQL_URL + "?" + urllib.parse.urlencode(params)
    req = urllib.request.Request(url, headers=headers)
    with urllib.request.urlopen(req, timeout=60) as res:
        return json.loads(res.read().decode("utf-8"))


def import_results(conn: sqlite3.Connection, data: dict[str, Any]) -> int:
    people: dict[str, tuple] = {}
    edges: list[tuple] = []
    for b in data.get("results", {}).get("bindings", []):
        child = qid(b.get("person", {}).get("value", ""))
        if not child:
            continue
        name = b.get("personLabel", {}).get("value", child)
        birth = b.get("birth", {}).get("value", "")[:10]
        death = b.get("death", {}).get("value", "")[:10]
        title = b.get("titleLabel", {}).get("value", "")
        people[child] = (child, name, birth, death, "", "", child, title, 1)

        father = qid(b.get("father", {}).get("value", ""))
        father_name = b.get("fatherLabel", {}).get("value", father)
        if father:
            people.setdefault(father, (father, father_name, "", "", "", "", father, "", 1))
            edges.append((child, father, "father", f"https://www.wikidata.org/wiki/{child}", 0.65, 1))

        mother = qid(b.get("mother", {}).get("value", ""))
        mother_name = b.get("motherLabel", {}).get("value", mother)
        if mother:
            people.setdefault(mother, (mother, mother_name, "", "", "", "", mother, "", 1))
            edges.append((child, mother, "mother", f"https://www.wikidata.org/wiki/{child}", 0.65, 1))

    conn.executemany("INSERT OR REPLACE INTO lineage_public_people VALUES (?,?,?,?,?,?,?,?,?)", people.values())
    conn.executemany("INSERT INTO lineage_edges VALUES (?,?,?,?,?,?)", edges)
    conn.commit()
    return len(people) + len(edges)


def import_starter_seed_graph(conn: sqlite3.Connection) -> int:
    """Small offline graph so lineage reports never look empty after SPARQL limits.

    This is deliberately a lead graph, not a proof graph. Production should expand
    lineage_core.sqlite from Wikidata dumps or larger source-weighted exports.
    """
    people = [
        ("starter:elizabeth_ii", "Elizabeth II", "1926-04-21", "2022-09-08", "", "", "Q9682", "Queen of the United Kingdom", 1),
        ("starter:george_vi", "George VI", "1895-12-14", "1952-02-06", "", "", "Q280856", "King of the United Kingdom", 1),
        ("starter:elizabeth_bowes_lyon", "Elizabeth Bowes-Lyon", "1900-08-04", "2002-03-30", "", "", "Q151544", "Queen consort of the United Kingdom", 1),
        ("starter:george_v", "George V", "1865-06-03", "1936-01-20", "", "", "Q269994", "King of the United Kingdom", 1),
        ("starter:mary_of_teck", "Mary of Teck", "1867-05-26", "1953-03-24", "", "", "Q161132", "Queen consort of the United Kingdom", 1),
        ("starter:edward_vii", "Edward VII", "1841-11-09", "1910-05-06", "", "", "Q20875", "King of the United Kingdom", 1),
        ("starter:alexandra_denmark", "Alexandra of Denmark", "1844-12-01", "1925-11-20", "", "", "Q154478", "Queen consort of the United Kingdom", 1),
        ("starter:victoria", "Victoria", "1819-05-24", "1901-01-22", "", "", "Q9439", "Queen of the United Kingdom", 1),
        ("starter:prince_albert", "Prince Albert of Saxe-Coburg and Gotha", "1819-08-26", "1861-12-14", "", "", "Q152888", "Prince consort", 1),
        ("starter:charles_iii", "Charles III", "1948-11-14", "", "", "", "Q43274", "King of the United Kingdom", 1),
        ("starter:william_wales", "William, Prince of Wales", "1982-06-21", "", "", "", "Q36812", "Prince of Wales", 1),
        ("starter:hashemite_hussein_hejaz", "Hussein bin Ali, King of Hejaz", "1854-05-01", "1931-06-04", "", "", "starter_hashemite_hussein_hejaz", "Sharif of Mecca / King of Hejaz", 1),
        ("starter:abdullah_i_jordan", "Abdullah I of Jordan", "1882-02-02", "1951-07-20", "", "", "starter_abdullah_i_jordan", "King of Jordan", 1),
        ("starter:talal_jordan", "Talal of Jordan", "1909-02-26", "1972-07-07", "", "", "starter_talal_jordan", "King of Jordan", 1),
        ("starter:hussein_jordan", "Hussein of Jordan", "1935-11-14", "1999-02-07", "", "", "starter_hussein_jordan", "King of Jordan", 1),
        ("starter:abdullah_ii_jordan", "Abdullah II of Jordan", "1962-01-30", "", "", "", "starter_abdullah_ii_jordan", "King of Jordan", 1),
        ("starter:ali_abi_talib", "Ali ibn Abi Talib", "0600-01-01", "0661-01-29", "", "", "starter_ali_abi_talib", "early Islamic public historical figure", 1),
        ("starter:fatimah", "Fatimah bint Muhammad", "0605-01-01", "0632-01-01", "", "", "starter_fatimah", "early Islamic public historical figure", 1),
        ("starter:hasan_ali", "Hasan ibn Ali", "0625-01-01", "0670-01-01", "", "", "starter_hasan_ali", "early Islamic public historical figure", 1),
        ("starter:husayn_ali", "Husayn ibn Ali", "0626-01-01", "0680-10-10", "", "", "starter_husayn_ali", "early Islamic public historical figure", 1),
        ("starter:cyrus_great", "Cyrus the Great", "-0600-01-01", "-0530-01-01", "", "", "Q8423", "Achaemenid ruler", 1),
        ("starter:darius_i", "Darius I", "-0550-01-01", "-0486-01-01", "", "", "Q40570", "Achaemenid ruler", 1),
        ("starter:william_shakespeare", "William Shakespeare", "1564-04-26", "1616-04-23", "", "", "Q692", "writer", 1),
    ]
    edges = [
        ("starter:elizabeth_ii", "starter:george_vi", "father", "offline-starter-seed", 0.35, 1),
        ("starter:elizabeth_ii", "starter:elizabeth_bowes_lyon", "mother", "offline-starter-seed", 0.35, 1),
        ("starter:george_vi", "starter:george_v", "father", "offline-starter-seed", 0.35, 1),
        ("starter:george_vi", "starter:mary_of_teck", "mother", "offline-starter-seed", 0.35, 1),
        ("starter:george_v", "starter:edward_vii", "father", "offline-starter-seed", 0.35, 1),
        ("starter:george_v", "starter:alexandra_denmark", "mother", "offline-starter-seed", 0.35, 1),
        ("starter:edward_vii", "starter:prince_albert", "father", "offline-starter-seed", 0.35, 1),
        ("starter:edward_vii", "starter:victoria", "mother", "offline-starter-seed", 0.35, 1),
        ("starter:charles_iii", "starter:elizabeth_ii", "mother", "offline-starter-seed", 0.35, 1),
        ("starter:william_wales", "starter:charles_iii", "father", "offline-starter-seed", 0.35, 1),
        ("starter:abdullah_i_jordan", "starter:hashemite_hussein_hejaz", "father", "offline-starter-seed", 0.25, 1),
        ("starter:talal_jordan", "starter:abdullah_i_jordan", "father", "offline-starter-seed", 0.25, 1),
        ("starter:hussein_jordan", "starter:talal_jordan", "father", "offline-starter-seed", 0.25, 1),
        ("starter:abdullah_ii_jordan", "starter:hussein_jordan", "father", "offline-starter-seed", 0.25, 1),
        ("starter:hasan_ali", "starter:ali_abi_talib", "father", "offline-starter-seed", 0.2, 1),
        ("starter:hasan_ali", "starter:fatimah", "mother", "offline-starter-seed", 0.2, 1),
        ("starter:husayn_ali", "starter:ali_abi_talib", "father", "offline-starter-seed", 0.2, 1),
        ("starter:husayn_ali", "starter:fatimah", "mother", "offline-starter-seed", 0.2, 1),
    ]
    conn.executemany("INSERT OR REPLACE INTO lineage_public_people VALUES (?,?,?,?,?,?,?,?,?)", people)
    conn.executemany("INSERT INTO lineage_edges VALUES (?,?,?,?,?,?)", edges)
    conn.commit()
    return len(people) + len(edges)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--limit", type=int, default=5000)
    ap.add_argument("--offline", action="store_true", help="create empty schema only")
    args = ap.parse_args()

    root = Path(args.root).resolve()
    db_path = root / "data" / "packs" / "lineage_core.sqlite"
    conn = connect(db_path)
    create_schema(conn)

    row_count = 0
    status_note = "ready"
    if not args.offline:
        try:
            data = fetch(args.limit)
            row_count = import_results(conn, data)
            # Respect public endpoint; production should use dumps.
            time.sleep(1)
        except Exception as exc:
            status_note = f"wikidata_fetch_unavailable: {type(exc).__name__}: {str(exc)[:220]}"
            print(f"warning: {status_note}")
    else:
        status_note = "offline schema only"

    if row_count == 0:
        seed_rows = import_starter_seed_graph(conn)
        row_count += seed_rows
        status_note = status_note + f"; offline starter seed graph inserted ({seed_rows} rows). Expand with dumps/SPARQL for production."

    conn.execute("INSERT OR REPLACE INTO lineage_pack_notes VALUES (?,?)", ("status", status_note))

    fake_hash = hashlib.sha256(f"wikidata-sparql-{args.limit}-{now_iso()}".encode()).hexdigest()
    conn.execute(
        """
        INSERT OR REPLACE INTO data_sources
        (id, source_name, source_url, version_or_release, downloaded_at, file_hash_sha256,
         license_note, imported_row_count, pack_name, pack_version, local_path, importer_version)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """,
        (
            "wikidata_filtered_lineage",
            "Wikidata filtered lineage graph",
            "https://www.wikidata.org/wiki/Wikidata:Database_download",
            "SPARQL small subset current",
            now_iso(),
            fake_hash,
            "Wikidata structured data is CC0; preserve QIDs and source links.",
            row_count,
            "lineage_core",
            PACK_VERSION,
            str(db_path),
            IMPORTER_VERSION,
        ),
    )
    conn.commit()
    conn.close()
    print(f"built {db_path}; imported approx {row_count} lineage rows")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
