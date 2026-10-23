#!/usr/bin/env python3
"""
Build ZeroThink ancient grave/sample runtime pack.

This is a private builder. It accepts either:
  1) a long-form CSV with sample metadata plus rsID/genotype rows, or
  2) an AADR/EIGENSTRAT-style prefix containing .snp, .geno, .ind and optional .anno.

Runtime output:
  data/packs/ancient_graves.sqlite

The web app reports genetic affinity to public ancient samples/sites only. It must
not claim descent from a grave, king, soldier, tribe, culture, religion, or named
ancient person.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import os
import pathlib
import sqlite3
import sys
import time
from typing import Dict, Iterable, List, Optional, Set, Tuple


DNA = {"A", "C", "G", "T"}


def now_iso() -> str:
    return time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())


def sha256_path(path: pathlib.Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def norm_text(value: object) -> str:
    return str(value or "").strip()


def norm_rsid(value: object) -> str:
    v = norm_text(value).lower()
    return v if v.startswith("rs") else ""


def norm_genotype(value: object) -> str:
    gt = "".join(ch for ch in norm_text(value).upper() if ch in DNA)
    if len(gt) == 1:
        gt *= 2
    if len(gt) >= 2:
        return "".join(sorted(gt[:2]))
    return ""


def pick(row: Dict[str, str], *names: str) -> str:
    lowered = {k.lower().strip(): v for k, v in row.items()}
    for name in names:
        key = name.lower().strip()
        if key in lowered and norm_text(lowered[key]) != "":
            return norm_text(lowered[key])
    for name in names:
        key = name.lower().strip()
        if key == "":
            continue
        for row_key, value in lowered.items():
            if key in row_key and norm_text(value) != "":
                return norm_text(value)
    return ""


def init_db(path: pathlib.Path) -> sqlite3.Connection:
    path.parent.mkdir(parents=True, exist_ok=True)
    if path.exists():
        path.unlink()
    con = sqlite3.connect(str(path))
    cur = con.cursor()
    cur.executescript(
        """
        PRAGMA journal_mode=OFF;
        PRAGMA synchronous=OFF;
        CREATE TABLE pack_meta (
          key TEXT PRIMARY KEY,
          value TEXT
        );
        CREATE TABLE ancient_samples (
          sample_id TEXT PRIMARY KEY,
          public_label TEXT,
          site_name TEXT,
          modern_country TEXT,
          region TEXT,
          culture TEXT,
          date_label TEXT,
          date_bp TEXT,
          sex TEXT,
          latitude REAL,
          longitude REAL,
          source_name TEXT,
          source_version TEXT,
          source_url TEXT,
          sample_quality TEXT
        );
        CREATE TABLE ancient_sample_genotypes (
          sample_id TEXT NOT NULL,
          rsid TEXT NOT NULL,
          genotype TEXT NOT NULL,
          marker_weight REAL DEFAULT 1.0,
          source_version TEXT,
          PRIMARY KEY(sample_id, rsid)
        );
        CREATE INDEX idx_ancient_genotypes_rsid ON ancient_sample_genotypes(rsid);
        CREATE INDEX idx_ancient_samples_country ON ancient_samples(modern_country);
        CREATE INDEX idx_ancient_samples_culture ON ancient_samples(culture);
        """
    )
    return con


def put_meta(con: sqlite3.Connection, **items: object) -> None:
    con.executemany(
        "INSERT OR REPLACE INTO pack_meta(key,value) VALUES(?,?)",
        [(k, str(v)) for k, v in items.items()],
    )


def to_float(value: object) -> Optional[float]:
    try:
        text = norm_text(value)
        return float(text) if text != "" else None
    except Exception:
        return None


def to_int_loose(value: object) -> int:
    text = norm_text(value).replace(",", "")
    digits = "".join(ch for ch in text if ch.isdigit())
    if digits == "":
        return 0
    try:
        return int(digits)
    except Exception:
        return 0


def ancient_priority_terms(text: str, terms: Set[str]) -> int:
    lower = text.lower()
    return sum(1 for term in terms if term and term in lower)


def insert_sample(cur: sqlite3.Cursor, meta: Dict[str, object]) -> None:
    cur.execute(
        """
        INSERT OR IGNORE INTO ancient_samples(
          sample_id, public_label, site_name, modern_country, region, culture,
          date_label, date_bp, sex, latitude, longitude, source_name,
          source_version, source_url, sample_quality
        ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        """,
        (
            norm_text(meta.get("sample_id")),
            norm_text(meta.get("public_label")),
            norm_text(meta.get("site_name")),
            norm_text(meta.get("modern_country")),
            norm_text(meta.get("region")),
            norm_text(meta.get("culture")),
            norm_text(meta.get("date_label")),
            norm_text(meta.get("date_bp")),
            norm_text(meta.get("sex")),
            to_float(meta.get("latitude")),
            to_float(meta.get("longitude")),
            norm_text(meta.get("source_name")) or "public ancient DNA source",
            norm_text(meta.get("source_version")) or "local ancient pack",
            norm_text(meta.get("source_url")),
            norm_text(meta.get("sample_quality")),
        ),
    )


def build_from_csv(con: sqlite3.Connection, csv_path: pathlib.Path, max_rows: int) -> Tuple[int, int]:
    cur = con.cursor()
    samples = set()
    genotypes = 0
    with csv_path.open("r", encoding="utf-8-sig", newline="") as fh:
        reader = csv.DictReader(fh)
        for i, row in enumerate(reader):
            if i >= max_rows:
                break
            sample_id = pick(row, "sample_id", "id", "sample", "iid")
            rsid = norm_rsid(pick(row, "rsid", "snp", "snp_id", "variant"))
            genotype = norm_genotype(pick(row, "genotype", "gt", "call"))
            if not sample_id or not rsid or not genotype:
                continue
            meta = {
                "sample_id": sample_id,
                "public_label": pick(row, "public_label", "label", "name") or sample_id,
                "site_name": pick(row, "site_name", "site", "locality"),
                "modern_country": pick(row, "modern_country", "country"),
                "region": pick(row, "region", "area"),
                "culture": pick(row, "culture", "group", "group_id"),
                "date_label": pick(row, "date_label", "date", "period"),
                "date_bp": pick(row, "date_bp", "bp", "date_mean_bp"),
                "sex": pick(row, "sex"),
                "latitude": pick(row, "latitude", "lat"),
                "longitude": pick(row, "longitude", "lon", "long"),
                "source_name": pick(row, "source_name") or "custom ancient CSV",
                "source_version": pick(row, "source_version") or csv_path.name,
                "source_url": pick(row, "source_url"),
                "sample_quality": pick(row, "sample_quality", "coverage", "quality"),
            }
            insert_sample(cur, meta)
            cur.execute(
                "INSERT OR IGNORE INTO ancient_sample_genotypes(sample_id,rsid,genotype,marker_weight,source_version) VALUES(?,?,?,?,?)",
                (
                    sample_id,
                    rsid,
                    genotype,
                    max(0.1, min(5.0, to_float(pick(row, "marker_weight", "weight")) or 1.0)),
                    meta["source_version"],
                ),
            )
            samples.add(sample_id)
            genotypes += cur.rowcount
            if genotypes and genotypes % 100000 == 0:
                con.commit()
    con.commit()
    return len(samples), genotypes


def read_aadr_anno(path: pathlib.Path) -> Dict[str, Dict[str, str]]:
    if not path.exists():
        return {}
    with path.open("r", encoding="utf-8", errors="replace", newline="") as fh:
        sample = fh.readline()
        fh.seek(0)
        dialect = csv.excel_tab if "\t" in sample else csv.excel
        reader = csv.DictReader(fh, dialect=dialect)
        out = {}
        for row in reader:
            sid = pick(row, "Instance ID", "Sample ID", "I12438", "Master ID", "sample_id", "id")
            if not sid and row:
                sid = norm_text(next(iter(row.values())))
            if sid:
                out[sid] = row
        return out


def read_aadr_ind(
    path: pathlib.Path,
    anno: Dict[str, Dict[str, str]],
    max_samples: int,
    include_modern: bool = False,
    priority_terms: Optional[Set[str]] = None,
    max_samples_per_country: int = 80,
    max_samples_per_culture: int = 60,
    min_autosomal_targets: int = 0,
) -> Tuple[List[str], Dict[str, Dict[str, object]]]:
    priority_terms = priority_terms or set()
    candidates: List[Tuple[float, str, Dict[str, object]]] = []
    with path.open("r", encoding="utf-8", errors="replace") as fh:
        for ind_index, line in enumerate(fh):
            parts = line.split()
            if not parts:
                continue
            sid = parts[0]
            row = anno.get(sid, {})
            group = parts[2] if len(parts) > 2 else ""
            date_label = pick(row, "Date", "Date range", "Full Date")
            date_bp = pick(row, "Date mean in BP", "Mean Date BP", "Date BP")
            if not include_modern:
                haystack = " ".join([group, date_label, date_bp, pick(row, "Data source", "Group ID", "Culture")]).lower()
                if "modern" in haystack or "present" in haystack or (row and not date_label and not date_bp):
                    continue
            sample_quality = pick(row, "SNPs hit on autosomal targets", "Coverage", "Molecular coverage")
            autosomal_targets = to_int_loose(sample_quality)
            if min_autosomal_targets > 0 and autosomal_targets and autosomal_targets < min_autosomal_targets:
                continue
            meta = {
                "sample_id": sid,
                "_ind_index": ind_index,
                "public_label": pick(row, "Master ID", "Publication ID", "Instance ID", "Sample ID") or sid,
                "site_name": pick(row, "Locality", "Site", "Archaeological Site"),
                "modern_country": pick(row, "Political Entity", "Country", "Present-day Country", "Country ISO"),
                "region": pick(row, "Region", "Political Entity", "Geographic region"),
                "culture": pick(row, "Group ID", "Culture", "Genetic ID") or group,
                "date_label": date_label,
                "date_bp": date_bp,
                "sex": pick(row, "Sex", "Reported Sex") or (parts[1] if len(parts) > 1 else ""),
                "latitude": pick(row, "Lat.", "Lat", "Latitude"),
                "longitude": pick(row, "Long.", "Lon", "Longitude"),
                "source_name": "AADR public ancient DNA",
                "source_version": pick(row, "Version", "Publication", "Data source") or "AADR/EIGENSTRAT import",
                "source_url": "https://dataverse.harvard.edu/dataset.xhtml?persistentId=doi:10.7910/DVN/FFIDCW",
                "sample_quality": sample_quality,
            }
            haystack = " ".join(str(meta.get(key, "")) for key in ("public_label", "site_name", "modern_country", "region", "culture", "date_label"))
            priority_hits = ancient_priority_terms(haystack, priority_terms)
            score = priority_hits * 100000.0 + min(90000, autosomal_targets) + (5000 if date_bp or date_label else 0)
            candidates.append((score, sid, meta))
    candidates.sort(key=lambda item: item[0], reverse=True)

    sample_ids: List[str] = []
    sample_meta: Dict[str, Dict[str, object]] = {}
    country_counts: Dict[str, int] = {}
    culture_counts: Dict[str, int] = {}
    deferred: List[Tuple[float, str, Dict[str, object]]] = []
    for score, sid, meta in candidates:
        country = norm_text(meta.get("modern_country")).lower() or "unknown"
        culture = norm_text(meta.get("culture")).lower() or "unknown"
        if max_samples_per_country > 0 and country_counts.get(country, 0) >= max_samples_per_country:
            deferred.append((score, sid, meta))
            continue
        if max_samples_per_culture > 0 and culture_counts.get(culture, 0) >= max_samples_per_culture:
            deferred.append((score, sid, meta))
            continue
        sample_ids.append(sid)
        sample_meta[sid] = meta
        country_counts[country] = country_counts.get(country, 0) + 1
        culture_counts[culture] = culture_counts.get(culture, 0) + 1
        if len(sample_ids) >= max_samples:
            return sample_ids, sample_meta
    for score, sid, meta in deferred:
        if sid in sample_meta:
            continue
        sample_ids.append(sid)
        sample_meta[sid] = meta
        if len(sample_ids) >= max_samples:
            break
    return sample_ids, sample_meta


def iter_aadr_snps(path: pathlib.Path, max_snps: int) -> Iterable[Tuple[int, str, str, str]]:
    with path.open("r", encoding="utf-8", errors="replace") as fh:
        used = 0
        for index, line in enumerate(fh):
            parts = line.split()
            if len(parts) < 6:
                continue
            rsid = norm_rsid(parts[0])
            a1 = norm_text(parts[4]).upper()
            a2 = norm_text(parts[5]).upper()
            if not rsid or a1 not in DNA or a2 not in DNA:
                continue
            yield index, rsid, a1, a2
            used += 1
            if used >= max_snps:
                break


def build_from_aadr(
    con: sqlite3.Connection,
    prefix: pathlib.Path,
    max_samples: int,
    max_snps: int,
    max_markers_per_sample: int,
    include_modern: bool = False,
    priority_terms: Optional[Set[str]] = None,
    max_samples_per_country: int = 80,
    max_samples_per_culture: int = 60,
    min_autosomal_targets: int = 0,
) -> Tuple[int, int]:
    base = str(prefix)
    snp_path = pathlib.Path(base + ".snp")
    geno_path = pathlib.Path(base + ".geno")
    ind_path = pathlib.Path(base + ".ind")
    anno_path = pathlib.Path(base + ".anno")
    missing = [str(p) for p in (snp_path, geno_path, ind_path) if not p.exists()]
    if missing:
        raise SystemExit("Missing AADR/EIGENSTRAT files: " + ", ".join(missing))

    anno = read_aadr_anno(anno_path)
    sample_ids, sample_meta = read_aadr_ind(
        ind_path,
        anno,
        max_samples,
        include_modern=include_modern,
        priority_terms=priority_terms,
        max_samples_per_country=max_samples_per_country,
        max_samples_per_culture=max_samples_per_culture,
        min_autosomal_targets=min_autosomal_targets,
    )
    if not sample_ids:
        raise SystemExit("No samples found in .ind file")

    cur = con.cursor()
    for sid in sample_ids:
        insert_sample(cur, sample_meta[sid])
    con.commit()

    snp_lookup = {idx: (rsid, a1, a2) for idx, rsid, a1, a2 in iter_aadr_snps(snp_path, max_snps)}
    last_snp_index = max(snp_lookup) if snp_lookup else -1
    sample_counts = {sid: 0 for sid in sample_ids}
    genotypes = 0
    sample_slots = [(sid, int(sample_meta[sid].get("_ind_index", idx))) for idx, sid in enumerate(sample_ids)]
    with geno_path.open("r", encoding="utf-8", errors="replace") as fh:
        for line_index, line in enumerate(fh):
            if last_snp_index >= 0 and line_index > last_snp_index:
                break
            if line_index not in snp_lookup:
                continue
            rsid, a1, a2 = snp_lookup[line_index]
            calls = "".join(line.split())
            if not calls:
                continue
            rows = []
            for sid, sample_index in sample_slots:
                if sample_index >= len(calls):
                    continue
                if sample_counts[sid] >= max_markers_per_sample:
                    continue
                digit = calls[sample_index]
                if digit == "9":
                    continue
                if digit == "0":
                    gt = a1 + a1
                elif digit == "1":
                    gt = a1 + a2
                elif digit == "2":
                    gt = a2 + a2
                else:
                    continue
                rows.append((sid, rsid, norm_genotype(gt), 1.0, "AADR/EIGENSTRAT import"))
                sample_counts[sid] += 1
            if rows:
                cur.executemany(
                    "INSERT OR IGNORE INTO ancient_sample_genotypes(sample_id,rsid,genotype,marker_weight,source_version) VALUES(?,?,?,?,?)",
                    rows,
                )
                genotypes += cur.rowcount
            if genotypes and genotypes % 250000 == 0:
                con.commit()
            if all(count >= max_markers_per_sample for count in sample_counts.values()):
                break
    con.commit()
    return len(sample_ids), genotypes


def main() -> int:
    parser = argparse.ArgumentParser(description="Build ZeroThink ancient grave/sample DNA runtime pack")
    parser.add_argument("--root", default=os.getcwd(), help="ZeroThink site root")
    parser.add_argument("--output", default="", help="Output SQLite path")
    parser.add_argument("--csv", default="", help="Long-form ancient sample CSV")
    parser.add_argument("--aadr-prefix", default="", help="AADR/EIGENSTRAT prefix without .snp/.geno/.ind suffix")
    parser.add_argument("--max-rows", type=int, default=2_000_000)
    parser.add_argument("--max-samples", type=int, default=450)
    parser.add_argument("--max-snps", type=int, default=120_000)
    parser.add_argument("--max-markers-per-sample", type=int, default=2500)
    parser.add_argument("--priority-terms", default="", help="Comma-separated country/culture/site terms to prioritize while keeping diversity caps.")
    parser.add_argument("--max-samples-per-country", type=int, default=80)
    parser.add_argument("--max-samples-per-culture", type=int, default=60)
    parser.add_argument("--min-autosomal-targets", type=int, default=0)
    parser.add_argument("--include-modern", action="store_true", help="Include present-day/modern AADR samples. Default is ancient-only.")
    args = parser.parse_args()

    root = pathlib.Path(args.root).resolve()
    out = pathlib.Path(args.output).resolve() if args.output else root / "data" / "packs" / "ancient_graves.sqlite"
    con = init_db(out)
    put_meta(
        con,
        pack_name="ancient_graves",
        pack_version="0.1.0",
        built_at=now_iso(),
        boundary="public ancient sample/site affinity only; not descent proof",
        source_mode="csv" if args.csv else ("aadr" if args.aadr_prefix else "empty"),
    )

    sample_count = 0
    genotype_count = 0
    sources: List[str] = []
    if args.csv:
        csv_path = pathlib.Path(args.csv).resolve()
        s, g = build_from_csv(con, csv_path, args.max_rows)
        sample_count += s
        genotype_count += g
        sources.append(f"csv:{csv_path.name}:{sha256_path(csv_path)}")
    if args.aadr_prefix:
        prefix = pathlib.Path(args.aadr_prefix).resolve()
        terms = {term.strip().lower() for term in args.priority_terms.split(",") if term.strip()}
        s, g = build_from_aadr(
            con,
            prefix,
            args.max_samples,
            args.max_snps,
            args.max_markers_per_sample,
            include_modern=args.include_modern,
            priority_terms=terms,
            max_samples_per_country=args.max_samples_per_country,
            max_samples_per_culture=args.max_samples_per_culture,
            min_autosomal_targets=args.min_autosomal_targets,
        )
        sample_count += s
        genotype_count += g
        for suffix in (".snp", ".geno", ".ind", ".anno"):
            p = pathlib.Path(str(prefix) + suffix)
            if p.exists():
                sources.append(f"{suffix}:{p.name}:{sha256_path(p)}")

    put_meta(con, sample_count=sample_count, genotype_count=genotype_count, sources="|".join(sources))
    con.commit()
    cur = con.cursor()
    cur.execute("ANALYZE")
    con.commit()
    cur.execute("VACUUM")
    con.close()

    print(
        f"built ancient_graves.sqlite: samples={sample_count:,} genotypes={genotype_count:,} path={out}",
        flush=True,
    )
    if sample_count <= 0 or genotype_count <= 0:
        print("warning: ancient pack is empty; provide --csv or --aadr-prefix to build real matches", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
