#!/usr/bin/env python3
"""
ZeroThink DNA Lab v2 - ancestry reference builder.

Creates:
  data/packs/ancestry_core.sqlite

MVP mode:
  --chr22-test-only imports chromosome 22 from 1000 Genomes Phase 3 to prove pipeline.

Production mode:
  supply --vcf for each 1000G/HGDP/SGDP VCF you want to ingest. Use a reduced marker list
  rather than importing every variant into runtime packs.

Important: reference panels are evidence tools. They are not identity truth machines.
"""
from __future__ import annotations

import argparse
import csv
import gzip
import hashlib
import os
import re
import sqlite3
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable

try:
    from cyvcf2 import VCF  # type: ignore
except Exception:  # pragma: no cover
    VCF = None

PACK_VERSION = "0.2.1"
IMPORTER_VERSION = "zerothink-ancestry-builder-0.2.1"

POP_LABELS = {
    "AFR": "African super-population",
    "AMR": "Admixed Americas super-population",
    "EAS": "East Asian super-population",
    "EUR": "European super-population",
    "SAS": "South Asian super-population",
    "ACB": "African Caribbean in Barbados",
    "ASW": "African Ancestry in Southwest USA",
    "ESN": "Esan in Nigeria",
    "GWD": "Gambian in Western Divisions, The Gambia",
    "LWK": "Luhya in Webuye, Kenya",
    "MSL": "Mende in Sierra Leone",
    "YRI": "Yoruba in Ibadan, Nigeria",
    "CLM": "Colombian in Medellin",
    "MXL": "Mexican ancestry in Los Angeles",
    "PEL": "Peruvian in Lima, Peru",
    "PUR": "Puerto Rican in Puerto Rico",
    "CDX": "Chinese Dai in Xishuangbanna, China",
    "CHB": "Han Chinese in Beijing, China",
    "CHS": "Southern Han Chinese",
    "JPT": "Japanese in Tokyo, Japan",
    "KHV": "Kinh Vietnamese in Ho Chi Minh City, Vietnam",
    "CEU": "Utah residents with Northern and Western European ancestry",
    "FIN": "Finnish in Finland",
    "GBR": "British in England and Scotland",
    "IBS": "Iberian populations in Spain",
    "TSI": "Toscani in Italy",
    "BEB": "Bengali in Bangladesh",
    "GIH": "Gujarati Indian in Houston",
    "ITU": "Indian Telugu in the UK",
    "PJL": "Punjabi in Lahore, Pakistan",
    "STU": "Sri Lankan Tamil in the UK",
    "ASJ": "Ashkenazi Jewish diaspora frequency panel",
    "NFE": "Non-Finnish European frequency control panel",
    "MID": "Middle Eastern frequency control panel",
    "AMI": "Amish frequency control panel",
    "OTH": "Other ancestry frequency control panel",
    "SEF": "Sephardi Jewish diaspora research panel",
    "MIZ": "Mizrahi Jewish diaspora research panel",
    "LEV": "Levant / Eastern Mediterranean research panel",
    "MAG": "Maghreb / North Africa research panel",
    "IRN": "Iran / Persian research panel",
}

GNOMAD_SUPER = {
    "ASJ": "DIASPORA",
    "NFE": "EUR",
    "FIN": "EUR",
    "MID": "WAS",
    "AMI": "EUR",
    "AFR": "AFR",
    "AMR": "AMR",
    "EAS": "EAS",
    "SAS": "SAS",
    "OTH": "OTHER",
}


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def sha256_path(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


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

        CREATE TABLE IF NOT EXISTS pack_meta (
          key TEXT PRIMARY KEY,
          value TEXT NOT NULL
        );

        DROP TABLE IF EXISTS ancestry_reference_markers;
        CREATE TABLE ancestry_reference_markers (
          rsid TEXT NOT NULL,
          chrom TEXT,
          pos INTEGER,
          build TEXT,
          ref TEXT,
          alt TEXT,
          panel_name TEXT NOT NULL,
          population_code TEXT NOT NULL,
          population_label TEXT,
          super_population TEXT,
          allele_frequency REAL,
          marker_weight REAL DEFAULT 1.0,
          source_version TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_ancestry_marker_rsid ON ancestry_reference_markers(rsid);
        CREATE INDEX IF NOT EXISTS idx_ancestry_marker_pop ON ancestry_reference_markers(population_code);
        """
    )
    conn.commit()


def record_meta(conn: sqlite3.Connection, key: str, value: object) -> None:
    conn.execute(
        "INSERT OR REPLACE INTO pack_meta (key, value) VALUES (?, ?)",
        (key, str(value)),
    )
    conn.commit()


def parse_panel(panel_path: Path) -> tuple[dict[str, str], dict[str, str]]:
    """Return sample->population and sample->super_population."""
    sample_pop: dict[str, str] = {}
    sample_super: dict[str, str] = {}
    if not panel_path.exists():
        return sample_pop, sample_super
    with panel_path.open("rt", encoding="utf-8", errors="replace") as f:
        reader = csv.DictReader(f, delimiter="\t")
        # expected columns: sample, pop, super_pop, gender
        for row in reader:
            sample = row.get("sample") or row.get("Sample") or row.get("SAMPLE")
            pop = row.get("pop") or row.get("population") or row.get("POP")
            super_pop = row.get("super_pop") or row.get("super population") or row.get("SUPER_POP")
            if sample and pop:
                sample_pop[sample] = pop
            if sample and super_pop:
                sample_super[sample] = super_pop
    return sample_pop, sample_super


def record_source(conn: sqlite3.Connection, *, source_id: str, name: str, url: str, path: Path, row_count: int, license_note: str | None = None) -> None:
    conn.execute(
        """
        INSERT OR REPLACE INTO data_sources
        (id, source_name, source_url, version_or_release, downloaded_at, file_hash_sha256,
         license_note, imported_row_count, pack_name, pack_version, local_path, importer_version)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """,
        (
            source_id,
            name,
            url,
            "current_download",
            now_iso(),
            sha256_path(path) if path.exists() else "0" * 64,
            license_note or "IGSR/1000 Genomes open data; cite source publications; respect data reuse statements.",
            row_count,
            "ancestry_core",
            PACK_VERSION,
            str(path),
            IMPORTER_VERSION,
        ),
    )
    conn.commit()


def info_dict(info: str) -> dict[str, str]:
    parsed: dict[str, str] = {}
    for part in info.split(";"):
        if not part:
            continue
        if "=" in part:
            key, value = part.split("=", 1)
            parsed[key] = value
        else:
            parsed[part] = "1"
    return parsed


def first_float(value: object) -> float | None:
    if value is None:
        return None
    text = str(value).split(",", 1)[0].strip()
    try:
        return float(text)
    except Exception:
        return None


def rsid_from_id(value: str) -> str:
    match = re.search(r"rs\d+", value or "", flags=re.I)
    return match.group(0) if match else ""


def marker_key(chrom: object, pos: object, raw_id: str = "") -> str:
    rsid = rsid_from_id(raw_id)
    if rsid:
        return rsid.lower()
    chrom_s = str(chrom).replace("chr", "").strip()
    pos_s = str(pos).strip()
    if chrom_s and pos_s.isdigit():
        return f"{chrom_s}:{pos_s}"
    return ""


def infer_build_from_path(path: Path, default: str = "GRCh38") -> str:
    name = path.name.lower()
    if "grch37" in name or "hg19" in name:
        return "GRCh37"
    if "grch38" in name or "hg38" in name:
        return "GRCh38"
    return default


def parse_pop_vcf_arg(value: str) -> tuple[str, Path]:
    """Parse POP=/path/file.vcf.gz or POP:/path/file.vcf.gz."""
    text = value.strip()
    for sep in ("=", ":"):
        if sep in text:
            pop, raw_path = text.split(sep, 1)
            return pop.strip().upper(), Path(raw_path.strip())
    raise ValueError(f"expected POP=/path.vcf.gz for --gnomad-pop-vcf, got: {value}")


def gt_alt_counts(sample_field: str) -> tuple[int, int] | None:
    gt = sample_field.split(":", 1)[0]
    if "." in gt:
        return None
    alleles = re.split(r"[\/|]", gt)
    if not alleles:
        return None
    alt = 0
    called = 0
    for allele in alleles:
        if allele == "":
            return None
        if allele not in {"0", "1"}:
            return None
        called += 1
        if allele == "1":
            alt += 1
    return alt, called


def import_1000g_vcf_pure_python(
    conn: sqlite3.Connection,
    vcf_path: Path,
    *,
    panel_path: Path | None = None,
    build: str = "GRCh37",
    optimized_runtime: bool = False,
    max_superpop_variants: int | None = None,
    min_superpop_delta: float = 0.12,
    marker_hash_mod: int = 1,
) -> int:
    """Dependency-free fallback for CWP servers where cyvcf2 is not installed.

    The INFO fields provide broad super-population frequencies. For clearer user
    language, a deterministic chr22 subset also computes frequencies for modern
    1000G reference population labels from the sample genotypes.
    """
    if not vcf_path.exists():
        return 0

    sample_pop: dict[str, str] = {}
    sample_super: dict[str, str] = {}
    if panel_path and panel_path.exists():
        sample_pop, sample_super = parse_panel(panel_path)

    max_subpop_variants = int(os.environ.get("ZDNA_MAX_SUBPOP_VARIANTS", "25000"))
    subpop_mod = max(1, int(os.environ.get("ZDNA_SUBPOP_HASH_MOD", "40")))
    subpop_variants = 0
    superpop_variants = 0
    sample_pops: list[str | None] = []
    sample_supers: list[str | None] = []
    count = 0
    batch = []
    progress_every = max(0, int(os.environ.get("ZDNA_PROGRESS_EVERY", "250000") or "0"))
    records_seen = 0

    def add_row(row: tuple[object, ...]) -> None:
        nonlocal count, batch
        batch.append(row)
        if len(batch) >= 10000:
            conn.executemany("INSERT INTO ancestry_reference_markers VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", batch)
            count += len(batch)
            batch.clear()

    def optimized_caps_reached() -> bool:
        if not optimized_runtime or max_superpop_variants is None:
            return False
        if superpop_variants < max_superpop_variants:
            return False
        return (not sample_pops) or subpop_variants >= max_subpop_variants

    with gzip.open(vcf_path, "rt", encoding="utf-8", errors="replace") as f:
        for line in f:
            if line.startswith("##"):
                continue
            if line.startswith("#CHROM"):
                header = line.rstrip("\n").split("\t")
                samples = header[9:]
                sample_pops = [sample_pop.get(sample) for sample in samples]
                sample_supers = [sample_super.get(sample) for sample in samples]
                continue
            if line.startswith("#"):
                continue

            cols = line.rstrip("\n").split("\t")
            if len(cols) < 8:
                continue
            records_seen += 1
            if progress_every and records_seen % progress_every == 0:
                print(
                    "progress "
                    f"{vcf_path.name}: records={records_seen:,} "
                    f"rows={count + len(batch):,} "
                    f"superpop_markers={superpop_variants:,} "
                    f"population_markers={subpop_variants:,}",
                    flush=True,
                )
            chrom, pos, raw_id, ref, alt, _qual, _filter, raw_info = cols[:8]
            rsid = marker_key(chrom, pos, raw_id)
            if not rsid or "," in alt:
                continue

            info = info_dict(raw_info)
            pop_afs: dict[str, float] = {}
            for pop in ("AFR", "AMR", "EAS", "EUR", "SAS"):
                af_f = first_float(info.get(f"{pop}_AF"))
                if af_f is None or af_f <= 0.01 or af_f >= 0.99:
                    continue
                pop_afs[pop] = af_f

            marker_hash = int(hashlib.sha256(rsid.encode("utf-8")).hexdigest()[:8], 16)
            selected_superpop = bool(pop_afs)
            if optimized_runtime:
                selected_superpop = False
                if len(pop_afs) >= 2:
                    delta = max(pop_afs.values()) - min(pop_afs.values())
                    if delta >= min_superpop_delta and marker_hash % max(1, marker_hash_mod) == 0:
                        if max_superpop_variants is None or superpop_variants < max_superpop_variants:
                            selected_superpop = True
                            superpop_variants += 1

            if selected_superpop:
                for pop, af_f in pop_afs.items():
                    add_row((
                        rsid,
                        chrom.replace("chr", ""),
                        int(pos),
                        build,
                        ref,
                        alt,
                        "1000G_Phase3_optimized_superpop" if optimized_runtime else "1000G_Phase3_superpop",
                        pop,
                        POP_LABELS.get(pop, pop),
                        pop,
                        af_f,
                        1.25 if optimized_runtime else 1.0,
                        "1000G Phase3 optimized runtime panel" if optimized_runtime else "1000G Phase3 current_download",
                    ))

            if not sample_pops or subpop_variants >= max_subpop_variants:
                if optimized_caps_reached():
                    print(
                        f"optimized caps reached for {vcf_path.name}; "
                        f"records={records_seen:,} rows={count + len(batch):,}",
                        flush=True,
                    )
                    break
                continue
            # In optimized mode, compute detailed population frequencies only for
            # the selected high-signal super-population markers. In starter mode,
            # spread them across the chromosome with a deterministic hash.
            if optimized_runtime:
                if not selected_superpop:
                    continue
            elif marker_hash % subpop_mod != 0:
                continue

            pop_alt: defaultdict[str, int] = defaultdict(int)
            pop_total: defaultdict[str, int] = defaultdict(int)
            super_for_pop: dict[str, str] = {}
            for sample_field, pop, super_pop in zip(cols[9:], sample_pops, sample_supers):
                if not pop:
                    continue
                counts = gt_alt_counts(sample_field)
                if counts is None:
                    continue
                alt_count, called = counts
                pop_alt[pop] += alt_count
                pop_total[pop] += called
                if super_pop:
                    super_for_pop[pop] = super_pop
            if not pop_total:
                continue
            subpop_variants += 1
            for pop, total_alleles in pop_total.items():
                if total_alleles < 40:
                    continue
                af_f = pop_alt[pop] / total_alleles
                if af_f <= 0.01 or af_f >= 0.99:
                    continue
                super_pop = super_for_pop.get(pop, "")
                add_row((
                    rsid,
                    chrom.replace("chr", ""),
                    int(pos),
                    build,
                    ref,
                    alt,
                    "1000G_Phase3_optimized_population" if optimized_runtime else "1000G_Phase3_population_subset",
                    pop,
                    POP_LABELS.get(pop, pop),
                    super_pop,
                    af_f,
                    1.35 if optimized_runtime else 1.1,
                    "1000G Phase3 optimized runtime panel" if optimized_runtime else "1000G Phase3 current_download chr22 deterministic subset",
                ))
            if optimized_caps_reached():
                print(
                    f"optimized caps reached for {vcf_path.name}; "
                    f"records={records_seen:,} rows={count + len(batch):,}",
                    flush=True,
                )
                break

    if batch:
        conn.executemany("INSERT INTO ancestry_reference_markers VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", batch)
        count += len(batch)
    conn.commit()
    return count


def import_1000g_vcf_superpop_info(
    conn: sqlite3.Connection,
    vcf_path: Path,
    build: str = "GRCh37",
    panel_path: Path | None = None,
    optimized_runtime: bool = False,
    max_superpop_variants: int | None = None,
    min_superpop_delta: float = 0.12,
    marker_hash_mod: int = 1,
) -> int:
    """Import 1000G ancestry frequencies, using cyvcf2 when available."""
    if VCF is None or optimized_runtime:
        print("using pure-Python optimized reference parser" if optimized_runtime else "cyvcf2 not installed; using pure-Python reference parser")
        return import_1000g_vcf_pure_python(
            conn,
            vcf_path,
            panel_path=panel_path,
            build=build,
            optimized_runtime=optimized_runtime,
            max_superpop_variants=max_superpop_variants,
            min_superpop_delta=min_superpop_delta,
            marker_hash_mod=marker_hash_mod,
        )
    if not vcf_path.exists():
        return 0
    count = 0
    batch = []
    for variant in VCF(str(vcf_path)):
        rsid = marker_key(variant.CHROM, variant.POS, variant.ID or "")
        if not rsid:
            continue
        # Skip multi-alt until the scorer handles it properly.
        if len(variant.ALT or []) != 1:
            continue
        info = variant.INFO
        for pop in ("AFR", "AMR", "EAS", "EUR", "SAS"):
            af = info.get(f"{pop}_AF")
            if af is None:
                continue
            try:
                af_f = float(af)
            except Exception:
                continue
            # skip monomorphic-ish markers and very rare markers for starter panel
            if af_f <= 0.01 or af_f >= 0.99:
                continue
            batch.append((
                rsid,
                str(variant.CHROM).replace("chr", ""),
                int(variant.POS),
                build,
                variant.REF,
                variant.ALT[0],
                "1000G_Phase3_superpop",
                pop,
                POP_LABELS.get(pop, pop),
                pop,
                af_f,
                1.0,
                "1000G Phase3 current_download",
            ))
        if len(batch) >= 10000:
            conn.executemany("INSERT INTO ancestry_reference_markers VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", batch)
            count += len(batch)
            batch.clear()
    if batch:
        conn.executemany("INSERT INTO ancestry_reference_markers VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", batch)
        count += len(batch)
    conn.commit()
    return count


def import_gnomad_sites_vcf(
    conn: sqlite3.Connection,
    vcf_path: Path,
    *,
    populations: Iterable[str],
    build: str = "GRCh38",
    max_variants: int = 60000,
    min_delta: float = 0.08,
    marker_hash_mod: int = 1,
    single_population: str | None = None,
) -> int:
    """Import compact gnomAD-style population frequency context.

    This importer is designed for specialist frequency lanes such as ASJ
    (Ashkenazi Jewish diaspora), NFE, MID, AFR, EAS, SAS and AMR controls.
    It does not turn frequency context into identity proof; it only gives the
    runtime scorer real local rows to compare against when the data pack exists.
    """
    if not vcf_path.exists():
        return 0

    single_pop = single_population.strip().upper() if single_population else ""
    requested = [single_pop] if single_pop else [p.strip().upper() for p in populations if str(p).strip()]
    requested = requested or ["ASJ", "NFE", "FIN", "MID", "AFR", "AMR", "EAS", "SAS"]
    count = 0
    selected_variants = 0
    batch = []

    def af_from_info(parsed: dict[str, str], pop: str) -> float | None:
        candidates = [
            f"AF_{pop.lower()}",
            f"AF_{pop.upper()}",
            f"AF-{pop.lower()}",
            f"AF-{pop.upper()}",
        ]
        if single_pop:
            candidates.append("AF")
        for key in candidates:
            value = parsed.get(key)
            af = first_float(value)
            if af is not None:
                return af
        return None

    with gzip.open(vcf_path, "rt", encoding="utf-8", errors="replace") as f:
        for line in f:
            if line.startswith("#"):
                continue
            cols = line.rstrip("\n").split("\t")
            if len(cols) < 8:
                continue
            chrom, pos, raw_id, ref, alt, _qual, _filter, raw_info = cols[:8]
            rsid = marker_key(chrom, pos, raw_id)
            if not rsid or "," in alt or len(str(alt)) > 1:
                continue
            parsed = info_dict(raw_info)
            pop_afs: dict[str, float] = {}
            for pop in requested:
                af = af_from_info(parsed, pop)
                if af is None or af <= 0.01 or af >= 0.99:
                    continue
                pop_afs[pop] = af
            if not pop_afs:
                continue
            if "ASJ" in pop_afs and len(pop_afs) > 1:
                controls = [v for k, v in pop_afs.items() if k != "ASJ"]
                delta = abs(pop_afs["ASJ"] - (sum(controls) / max(1, len(controls))))
            else:
                delta = max(pop_afs.values()) - min(pop_afs.values()) if len(pop_afs) >= 2 else min_delta
            marker_hash = int(hashlib.sha256(rsid.encode("utf-8")).hexdigest()[:8], 16)
            if delta < min_delta or marker_hash % max(1, marker_hash_mod) != 0:
                continue
            selected_variants += 1
            for pop, af in pop_afs.items():
                code = pop.upper()
                batch.append((
                    rsid,
                    chrom.replace("chr", ""),
                    int(pos),
                    build,
                    ref,
                    alt,
                    "gnomAD_specialist_frequency",
                    code,
                    POP_LABELS.get(code, f"gnomAD {code} frequency panel"),
                    GNOMAD_SUPER.get(code, code),
                    af,
                    1.65 if code == "ASJ" else 1.15,
                    "gnomAD specialist population frequency context",
                ))
            if len(batch) >= 10000:
                conn.executemany("INSERT INTO ancestry_reference_markers VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", batch)
                count += len(batch)
                batch.clear()
            if max_variants > 0 and selected_variants >= max_variants:
                break
    if batch:
        conn.executemany("INSERT INTO ancestry_reference_markers VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)", batch)
        count += len(batch)
    conn.commit()
    return count


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument("--chr22-test-only", action="store_true")
    ap.add_argument("--optimized-runtime", action="store_true", help="Build a compact, high-signal runtime ancestry panel instead of importing every marker.")
    ap.add_argument("--chromosomes", default="", help="Comma/range list for metadata only, e.g. 1-22 or 1,2,22.")
    ap.add_argument("--max-superpop-variants-per-vcf", type=int, default=45000)
    ap.add_argument("--max-subpop-variants-per-vcf", type=int, default=9000)
    ap.add_argument("--min-superpop-delta", type=float, default=0.12)
    ap.add_argument("--marker-hash-mod", type=int, default=1)
    ap.add_argument("--vcf", action="append", default=[], help="Additional 1000G/HGDP-style VCF path")
    ap.add_argument("--gnomad-vcf", action="append", default=[], help="Optional gnomAD sites VCF path for ASJ/NFE/MID/etc frequency context")
    ap.add_argument("--gnomad-pop-vcf", action="append", default=[], help="Optional population-specific frequency VCF as POP=/path/file.vcf.gz. Uses INFO/AF for that population.")
    ap.add_argument("--gnomad-populations", default="asj,nfe,fin,mid,afr,amr,eas,sas", help="Comma-separated gnomAD population frequency suffixes")
    ap.add_argument("--max-gnomad-variants-per-vcf", type=int, default=60000)
    args = ap.parse_args()

    root = Path(args.root).resolve()
    src = root / "data" / "sources"
    db_path = root / "data" / "packs" / "ancestry_core.sqlite"
    conn = connect(db_path)
    create_schema(conn)

    vcf_paths: list[Path] = []
    if args.chr22_test_only:
        vcf_paths.append(src / "1000g" / "ALL.chr22.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz")
    vcf_paths.extend(Path(x) for x in args.vcf)

    total = 0
    panel_path = src / "1000g" / "integrated_call_samples_v3.20130502.ALL.panel"
    record_meta(conn, "pack_version", PACK_VERSION)
    record_meta(conn, "importer_version", IMPORTER_VERSION)
    record_meta(conn, "build_mode", "optimized_runtime" if args.optimized_runtime else ("chr22_test" if args.chr22_test_only else "custom_vcf"))
    record_meta(conn, "chromosomes", args.chromosomes or ("22" if args.chr22_test_only else "custom"))
    record_meta(conn, "min_superpop_delta", args.min_superpop_delta)
    record_meta(conn, "marker_hash_mod", max(1, args.marker_hash_mod))
    for p in vcf_paths:
        if not p.exists():
            print(f"missing VCF: {p}")
            continue
        print(f"starting ancestry import: {p}", flush=True)
        os.environ["ZDNA_MAX_SUBPOP_VARIANTS"] = str(max(0, args.max_subpop_variants_per_vcf))
        rows = import_1000g_vcf_superpop_info(
            conn,
            p,
            panel_path=panel_path,
            optimized_runtime=args.optimized_runtime,
            max_superpop_variants=args.max_superpop_variants_per_vcf,
            min_superpop_delta=args.min_superpop_delta,
            marker_hash_mod=max(1, args.marker_hash_mod),
        )
        total += rows
        record_source(
            conn,
            source_id=f"1000g_{p.name}",
            name=f"1000 Genomes reference markers from {p.name}",
            url="https://ftp.1000genomes.ebi.ac.uk/vol1/ftp/release/20130502/",
            path=p,
            row_count=rows,
        )
        print(f"imported {rows} ancestry frequency rows from {p.name}")

    gnomad_pops = [part.strip() for part in str(args.gnomad_populations).split(",") if part.strip()]
    for p in [Path(x) for x in args.gnomad_vcf]:
        if not p.exists():
            print(f"missing gnomAD VCF: {p}")
            continue
        print(f"starting gnomAD specialist import: {p}", flush=True)
        rows = import_gnomad_sites_vcf(
            conn,
            p,
            populations=gnomad_pops,
            build=infer_build_from_path(p),
            max_variants=max(0, args.max_gnomad_variants_per_vcf),
            min_delta=max(0.0, args.min_superpop_delta * 0.65),
            marker_hash_mod=max(1, args.marker_hash_mod),
        )
        total += rows
        record_source(
            conn,
            source_id=f"gnomad_{p.name}",
            name=f"gnomAD specialist population frequency markers from {p.name}",
            url="https://gnomad.broadinstitute.org/downloads",
            path=p,
            row_count=rows,
            license_note="gnomAD public data; cite gnomAD and respect current data-use terms. Frequency context only, not identity proof.",
        )
        print(f"imported {rows} gnomAD specialist frequency rows from {p.name}")

    for raw in args.gnomad_pop_vcf:
        try:
            pop, p = parse_pop_vcf_arg(raw)
        except ValueError as exc:
            print(str(exc))
            continue
        if not p.exists():
            print(f"missing gnomAD population VCF: {p}")
            continue
        print(f"starting gnomAD specialist population import: {pop} from {p}", flush=True)
        rows = import_gnomad_sites_vcf(
            conn,
            p,
            populations=[pop],
            build=infer_build_from_path(p),
            max_variants=max(0, args.max_gnomad_variants_per_vcf),
            min_delta=0.0,
            marker_hash_mod=max(1, args.marker_hash_mod),
            single_population=pop,
        )
        total += rows
        record_source(
            conn,
            source_id=f"gnomad_{pop}_{p.name}",
            name=f"gnomAD {pop} specialist population frequency markers from {p.name}",
            url="https://gnomad.broadinstitute.org/downloads",
            path=p,
            row_count=rows,
            license_note="gnomAD/TogoVar public frequency data; cite source projects and respect current data-use terms. Frequency context only, not identity proof.",
        )
        print(f"imported {rows} gnomAD {pop} specialist frequency rows from {p.name}")

    conn.close()
    print(f"built {db_path} with {total} rows")
    if args.optimized_runtime:
        print("Optimized runtime ancestry panel built. This is compact and real-reference based, but still probabilistic.")
    else:
        print("Note: chr22-only is for testing. Production should build across autosomes and optionally HGDP/SGDP.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
