#!/usr/bin/env python3
"""
ZeroThink DNA Lab v2 - health/pharmacogenomics local pack builder.

Creates:
  data/packs/health_core.sqlite

Imports:
  - ClinVar variant_summary.txt.gz
  - GWAS Catalog full TSV
  - PharmGKB clinicalAnnotations.zip (annotations, allele text, evidence, history)
  - PharmGKB clinicalVariants.zip

ClinVar matching is deliberately allele-specific.  An rsID is only a locus
identifier and MUST NOT be treated as evidence that a user's observed allele is
the ClinVar-classified allele.  Exact matching requires the same assembly,
chromosome, VCF-normalized position, reference allele, and alternate allele.

The first thirteen ``clinvar_variants`` columns are retained for compatibility
with older ZeroThink readers.  Allele/build/provenance columns are appended and
the pack metadata records the matching contract explicitly.
"""
from __future__ import annotations

import argparse
import csv
import gzip
import hashlib
import json
import os
import re
import shutil
import sqlite3
import sys
import zipfile
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath
from typing import Optional

IMPORTER_VERSION = "zerothink-health-core-builder-0.3.1"
PACK_VERSION = "0.3.0"
SCHEMA_VERSION = "health-core-schema-0.3.0"
CLINVAR_SOURCE_ID = "clinvar_variant_summary"
GWAS_SOURCE_ID = "gwas_catalog_associations"
GWAS_SOURCE_RELEASE = "v1.0.2"
GWAS_SOURCE_URL = (
    "https://www.ebi.ac.uk/gwas/api/search/downloads/associations/v1.0.2"
)
CLINPGX_ANNOTATIONS_SOURCE_URL = (
    "https://api.clinpgx.org/v1/download/file/data/clinicalAnnotations.zip"
)
CLINPGX_VARIANTS_SOURCE_URL = (
    "https://api.clinpgx.org/v1/download/file/data/clinicalVariants.zip"
)
CLINPGX_SOURCE_VERSION = "ClinPGx/PharmGKB current download"
CLINPGX_LICENSE_NOTE = (
    "ClinPGx/PharmGKB data licensed under CC BY-SA 4.0 "
    "(https://creativecommons.org/licenses/by-sa/4.0/); adapted by ZeroThink "
    "into the health_core SQLite pack. Cite ClinPGx/PharmGKB."
)
MAX_MATCH_ALLELE_LENGTH = 1000

CLINVAR_MATCH_CONTRACT = (
    "Exact reference-forward match only: assembly + chromosome + PositionVCF + "
    "ReferenceAlleleVCF + AlternateAlleleVCF. rsID alone is never sufficient."
)


@dataclass(frozen=True)
class ClinVarRelease:
    value: str
    evidence: str
    vcf_headers: tuple[dict[str, str], ...] = ()


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

        CREATE TABLE IF NOT EXISTS pack_metadata (
          key TEXT PRIMARY KEY,
          value TEXT NOT NULL
        );

        DROP VIEW IF EXISTS clinvar_exact_alleles;
        DROP TABLE IF EXISTS clinvar_variants;
        CREATE TABLE clinvar_variants (
          variation_id TEXT,
          rsid TEXT,
          chrom TEXT,
          pos_grch37 INTEGER,
          pos_grch38 INTEGER,
          gene_symbol TEXT,
          clinical_significance TEXT,
          condition_name TEXT,
          review_status TEXT,
          accession TEXT,
          allele_id TEXT,
          variant_type TEXT,
          source_version TEXT,
          assembly TEXT,
          position_vcf INTEGER,
          reference_allele TEXT,
          alternate_allele TEXT,
          source_reference_allele TEXT,
          source_alternate_allele TEXT,
          allele_match_key TEXT,
          allele_match_status TEXT NOT NULL,
          allele_matchable INTEGER NOT NULL DEFAULT 0 CHECK (allele_matchable IN (0, 1)),
          is_simple_snv INTEGER NOT NULL DEFAULT 0 CHECK (is_simple_snv IN (0, 1)),
          source_format TEXT NOT NULL,
          source_release TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_clinvar_rsid ON clinvar_variants(rsid);
        CREATE INDEX IF NOT EXISTS idx_clinvar_gene ON clinvar_variants(gene_symbol);
        CREATE INDEX IF NOT EXISTS idx_clinvar_exact_locus
          ON clinvar_variants(assembly, chrom, position_vcf)
          WHERE allele_matchable = 1;
        CREATE VIEW clinvar_exact_alleles AS
          SELECT
            variation_id, allele_id, rsid, assembly, chrom, position_vcf,
            reference_allele, alternate_allele, allele_match_key,
            is_simple_snv, gene_symbol, clinical_significance, condition_name,
            review_status, accession, variant_type, source_version,
            source_release
          FROM clinvar_variants
          WHERE allele_matchable = 1;

        DROP TABLE IF EXISTS gwas_associations;
        CREATE TABLE gwas_associations (
          rsid TEXT,
          trait TEXT,
          mapped_trait TEXT,
          p_value TEXT,
          odds_ratio TEXT,
          beta TEXT,
          pubmed_id TEXT,
          study_accession TEXT,
          ancestry_initial TEXT,
          ancestry_replication TEXT,
          source_version TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_gwas_rsid ON gwas_associations(rsid);
        CREATE INDEX IF NOT EXISTS idx_gwas_trait ON gwas_associations(mapped_trait);

        DROP TABLE IF EXISTS pharmgkb_clinical_annotations;
        CREATE TABLE pharmgkb_clinical_annotations (
          annotation_id TEXT,
          variant TEXT,
          rsid TEXT,
          gene TEXT,
          chemical TEXT,
          phenotype TEXT,
          evidence_level TEXT,
          annotation_text TEXT,
          source_version TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_pgkb_ann_rsid ON pharmgkb_clinical_annotations(rsid);
        CREATE INDEX IF NOT EXISTS idx_pgkb_ann_gene ON pharmgkb_clinical_annotations(gene);

        DROP TABLE IF EXISTS pharmgkb_allele_annotations;
        CREATE TABLE pharmgkb_allele_annotations (
          annotation_id TEXT,
          genotype_allele TEXT,
          annotation_text TEXT,
          allele_function TEXT,
          source_version TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_pgkb_allele_annotation_id
          ON pharmgkb_allele_annotations(annotation_id);

        DROP TABLE IF EXISTS pharmgkb_evidence;
        CREATE TABLE pharmgkb_evidence (
          annotation_id TEXT,
          evidence_id TEXT,
          evidence_type TEXT,
          evidence_url TEXT,
          pubmed_id TEXT,
          summary TEXT,
          score TEXT,
          source_version TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_pgkb_evidence_annotation_id
          ON pharmgkb_evidence(annotation_id);
        CREATE INDEX IF NOT EXISTS idx_pgkb_evidence_pubmed_id
          ON pharmgkb_evidence(pubmed_id);

        DROP TABLE IF EXISTS pharmgkb_history;
        CREATE TABLE pharmgkb_history (
          annotation_id TEXT,
          event_date TEXT,
          event_type TEXT,
          comment TEXT,
          source_version TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_pgkb_history_annotation_id
          ON pharmgkb_history(annotation_id);

        DROP TABLE IF EXISTS pharmgkb_clinical_variants;
        CREATE TABLE pharmgkb_clinical_variants (
          variant TEXT,
          rsid TEXT,
          gene TEXT,
          chemical TEXT,
          evidence_level TEXT,
          source_version TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_pgkb_cv_rsid ON pharmgkb_clinical_variants(rsid);
        """
    )
    conn.commit()


def write_pack_metadata(conn: sqlite3.Connection, values: dict[str, str]) -> None:
    conn.executemany(
        "INSERT OR REPLACE INTO pack_metadata(key, value) VALUES (?, ?)",
        sorted(values.items()),
    )
    conn.commit()


def normalize_release_date(value: str) -> str:
    value = value.strip()
    if re.fullmatch(r"\d{8}", value):
        return f"{value[:4]}-{value[4:6]}-{value[6:]}"
    return value


def read_clinvar_vcf_header(path: Path) -> dict[str, str]:
    """Read only the provenance lines needed from a gzipped ClinVar VCF."""
    header: dict[str, str] = {"path": str(path)}
    with gzip.open(path, "rt", encoding="utf-8", errors="replace") as fh:
        for line in fh:
            if line.startswith("##fileDate="):
                header["file_date"] = normalize_release_date(line.split("=", 1)[1].strip())
            elif line.startswith("##reference="):
                header["reference"] = line.split("=", 1)[1].strip()
            elif line.startswith("#CHROM"):
                break
    return header


def discover_clinvar_release(
    clinvar_dir: Path,
    variant_summary_path: Path,
    explicit_release: str = "",
) -> ClinVarRelease:
    """Resolve a reproducible ClinVar release without inventing a date.

    ``variant_summary.txt.gz`` has no release header.  The downloader fetches it
    alongside assembly-specific VCFs, so matching VCF ``##fileDate`` values are
    accepted as *inferred sibling-header* evidence.  If those headers are absent,
    the summary's content hash is used as the immutable version identifier.
    Conflicting VCF dates or filename/header assemblies fail closed.
    """
    if explicit_release.strip():
        return ClinVarRelease(normalize_release_date(explicit_release), "explicit_cli")

    headers: list[dict[str, str]] = []
    if clinvar_dir.exists():
        for path in sorted(clinvar_dir.iterdir(), key=lambda item: item.name.lower()):
            lower_name = path.name.lower()
            if lower_name not in {"clinvar_grch37.vcf.gz", "clinvar_grch38.vcf.gz"}:
                continue
            header = read_clinvar_vcf_header(path)
            expected = "GRCh37" if "grch37" in lower_name else "GRCh38"
            observed = header.get("reference", "")
            if observed and observed.lower() != expected.lower():
                raise RuntimeError(
                    f"ClinVar VCF assembly mismatch: {path.name} declares {observed}, expected {expected}"
                )
            header["expected_reference"] = expected
            headers.append(header)

    dated = {item.get("file_date", "") for item in headers if item.get("file_date")}
    if len(dated) > 1:
        raise RuntimeError(f"ClinVar VCF release dates disagree: {sorted(dated)}")
    if dated:
        return ClinVarRelease(
            value=next(iter(dated)),
            evidence="inferred_from_sibling_vcf_headers",
            vcf_headers=tuple(headers),
        )

    if not variant_summary_path.exists():
        raise FileNotFoundError(f"required ClinVar source is missing: {variant_summary_path}")
    digest = sha256_path(variant_summary_path)
    return ClinVarRelease(
        value=f"sha256:{digest[:16]}",
        evidence="variant_summary_content_hash",
        vcf_headers=tuple(headers),
    )


def record_source(
    conn: sqlite3.Connection,
    *,
    source_id: str,
    source_name: str,
    source_url: str,
    local_path: Path,
    license_note: str,
    row_count: int,
    pack_name: str,
    version: str = "current_download",
) -> None:
    conn.execute(
        """
        INSERT OR REPLACE INTO data_sources
        (id, source_name, source_url, version_or_release, downloaded_at, file_hash_sha256,
         license_note, imported_row_count, pack_name, pack_version, local_path, importer_version)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """,
        (
            source_id,
            source_name,
            source_url,
            version,
            now_iso(),
            sha256_path(local_path),
            license_note,
            row_count,
            pack_name,
            PACK_VERSION,
            str(local_path),
            IMPORTER_VERSION,
        ),
    )
    conn.commit()


def safe_get(row: dict[str, str], *names: str) -> str:
    for name in names:
        if name in row and row[name] is not None:
            return row[name]
    # Case-insensitive fallback
    lower = {k.lower(): v for k, v in row.items()}
    for name in names:
        v = lower.get(name.lower())
        if v is not None:
            return v
    return ""


def to_int_or_none(value: str) -> Optional[int]:
    if not value or value in {"na", "NA", "-"}:
        return None
    try:
        return int(value)
    except ValueError:
        return None


def normalize_assembly(value: str) -> str:
    aliases = {
        "grch37": "GRCh37",
        "hg19": "GRCh37",
        "grch38": "GRCh38",
        "hg38": "GRCh38",
    }
    stripped = value.strip()
    return aliases.get(stripped.lower(), stripped)


def normalize_chromosome(value: str) -> str:
    value = value.strip()
    if value.lower().startswith("chr"):
        value = value[3:]
    value = value.upper()
    return "MT" if value in {"M", "MTDNA"} else value


def normalize_rsid(value: str) -> str:
    value = value.strip().lower()
    if value in {"", "-", "-1", "na", "n/a", "none", "null"}:
        return ""
    if value.startswith("rs"):
        numeric = value[2:]
    else:
        numeric = value
    return f"rs{numeric}" if numeric.isdigit() and int(numeric) > 0 else ""


def normalize_vcf_allele(value: str) -> Optional[str]:
    value = value.strip().upper()
    if value.lower() in {"", "-", "na", "n/a", "none", "null", "not provided"}:
        return None
    return value


def clinvar_allele_semantics(
    *,
    assembly: str,
    chrom: str,
    position_vcf: Optional[int],
    reference_allele: Optional[str],
    alternate_allele: Optional[str],
) -> tuple[str, int, int, Optional[str]]:
    """Return status, matchable, simple-SNV, and canonical exact-match key."""
    if assembly not in {"GRCh37", "GRCh38"}:
        return "unsupported_assembly", 0, 0, None
    if not chrom:
        return "missing_chromosome", 0, 0, None
    if position_vcf is None or position_vcf <= 0:
        return "invalid_vcf_position", 0, 0, None
    if reference_allele is None or alternate_allele is None:
        return "missing_vcf_allele", 0, 0, None
    if reference_allele == alternate_allele:
        return "reference_equals_alternate", 0, 0, None
    if not re.fullmatch(r"[ACGT]+", reference_allele) or not re.fullmatch(
        r"[ACGT]+", alternate_allele
    ):
        return "symbolic_or_ambiguous_allele", 0, 0, None
    if max(len(reference_allele), len(alternate_allele)) > MAX_MATCH_ALLELE_LENGTH:
        return "allele_too_long_for_runtime_match", 0, 0, None

    simple_snv = int(len(reference_allele) == 1 and len(alternate_allele) == 1)
    status = "exact_snv" if simple_snv else "exact_sequence"
    key = f"{assembly}:{chrom}:{position_vcf}:{reference_allele}>{alternate_allele}"
    return status, 1, simple_snv, key


def import_clinvar_variant_summary(
    conn: sqlite3.Connection,
    path: Path,
    *,
    source_release: str,
) -> int:
    if not path.exists():
        print(f"skip missing ClinVar: {path}", file=sys.stderr)
        return 0
    count = 0
    batch = []
    with gzip.open(path, "rt", encoding="utf-8", errors="replace") as f:
        reader = csv.DictReader(f, delimiter="\t")
        required_columns = {
            "Assembly",
            "Chromosome",
            "VariationID",
            "PositionVCF",
            "ReferenceAlleleVCF",
            "AlternateAlleleVCF",
        }
        missing_columns = required_columns.difference(reader.fieldnames or [])
        if missing_columns:
            raise RuntimeError(
                "ClinVar variant_summary schema is missing exact-allele columns: "
                + ", ".join(sorted(missing_columns))
            )
        for row in reader:
            rsid = normalize_rsid(safe_get(row, "RS# (dbSNP)", "RS#"))
            assembly = normalize_assembly(safe_get(row, "Assembly"))
            chrom = normalize_chromosome(safe_get(row, "Chromosome", "ChromosomeAccession"))

            # Preserve the historical Start-based columns for older readers.
            # Exact allele matching always uses the separate VCF-normalized fields.
            source_position = to_int_or_none(safe_get(row, "Start"))
            if source_position is not None and source_position <= 0:
                source_position = None
            pos37 = source_position if assembly == "GRCh37" else None
            pos38 = source_position if assembly == "GRCh38" else None

            position_vcf = to_int_or_none(safe_get(row, "PositionVCF"))
            exact_ref = normalize_vcf_allele(safe_get(row, "ReferenceAlleleVCF"))
            exact_alt = normalize_vcf_allele(safe_get(row, "AlternateAlleleVCF"))
            match_status, matchable, simple_snv, match_key = clinvar_allele_semantics(
                assembly=assembly,
                chrom=chrom,
                position_vcf=position_vcf,
                reference_allele=exact_ref,
                alternate_allele=exact_alt,
            )
            batch.append(
                (
                    safe_get(row, "VariationID"),
                    rsid,
                    chrom,
                    pos37,
                    pos38,
                    safe_get(row, "GeneSymbol"),
                    safe_get(row, "ClinicalSignificance"),
                    safe_get(row, "PhenotypeList", "Name"),
                    safe_get(row, "ReviewStatus"),
                    safe_get(row, "RCVaccession", "Accession"),
                    safe_get(row, "AlleleID"),
                    safe_get(row, "Type"),
                    f"ClinVar {source_release}",
                    assembly,
                    position_vcf,
                    exact_ref,
                    exact_alt,
                    safe_get(row, "ReferenceAllele"),
                    safe_get(row, "AlternateAllele"),
                    match_key,
                    match_status,
                    matchable,
                    simple_snv,
                    "variant_summary.txt.gz",
                    source_release,
                )
            )
            if len(batch) >= 5000:
                conn.executemany(
                    """
                    INSERT INTO clinvar_variants (
                      variation_id, rsid, chrom, pos_grch37, pos_grch38,
                      gene_symbol, clinical_significance, condition_name,
                      review_status, accession, allele_id, variant_type,
                      source_version, assembly, position_vcf, reference_allele,
                      alternate_allele, source_reference_allele,
                      source_alternate_allele, allele_match_key,
                      allele_match_status, allele_matchable, is_simple_snv,
                      source_format, source_release
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    """,
                    batch,
                )
                count += len(batch)
                batch.clear()
        if batch:
            conn.executemany(
                """
                INSERT INTO clinvar_variants (
                  variation_id, rsid, chrom, pos_grch37, pos_grch38,
                  gene_symbol, clinical_significance, condition_name,
                  review_status, accession, allele_id, variant_type,
                  source_version, assembly, position_vcf, reference_allele,
                  alternate_allele, source_reference_allele,
                  source_alternate_allele, allele_match_key,
                  allele_match_status, allele_matchable, is_simple_snv,
                  source_format, source_release
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                """,
                batch,
            )
            count += len(batch)
    conn.commit()
    return count


def import_gwas(conn: sqlite3.Connection, path: Path) -> int:
    if not path.exists():
        print(f"skip missing GWAS: {path}", file=sys.stderr)
        return 0
    count = 0
    batch = []
    with path.open("rt", encoding="utf-8", errors="replace") as f:
        reader = csv.DictReader(f, delimiter="\t")
        for row in reader:
            snps = safe_get(row, "SNPS", "SNP_ID_CURRENT")
            # Keep one row per rsID token where possible; do not explode huge multi-SNP rows too much.
            rsids = [x.strip() for x in snps.replace(";", ",").split(",") if x.strip().lower().startswith("rs")]
            if not rsids:
                rsids = [snps.strip()] if snps.strip() else []
            for rsid in rsids[:10]:
                batch.append(
                    (
                        rsid.lower(),
                        safe_get(row, "DISEASE/TRAIT"),
                        safe_get(row, "MAPPED_TRAIT"),
                        safe_get(row, "P-VALUE"),
                        safe_get(row, "OR or BETA", "OR_OR_BETA"),
                        safe_get(row, "BETA"),
                        safe_get(row, "PUBMEDID"),
                        safe_get(row, "STUDY ACCESSION", "STUDY_ACCESSION"),
                        safe_get(row, "INITIAL SAMPLE SIZE", "INITIAL_SAMPLE_SIZE"),
                        safe_get(row, "REPLICATION SAMPLE SIZE", "REPLICATION_SAMPLE_SIZE"),
                        f"GWAS Catalog associations {GWAS_SOURCE_RELEASE}",
                    )
                )
            if len(batch) >= 5000:
                conn.executemany("INSERT INTO gwas_associations VALUES (?,?,?,?,?,?,?,?,?,?,?)", batch)
                count += len(batch)
                batch.clear()
        if batch:
            conn.executemany("INSERT INTO gwas_associations VALUES (?,?,?,?,?,?,?,?,?,?,?)", batch)
            count += len(batch)
    conn.commit()
    return count


def first_tsv_in_zip(
    path: Path, preferred_names: tuple[str, ...] = ()
) -> Optional[str]:
    """Select a data table without mistaking archive documentation for data.

    PharmGKB archives commonly put ``LICENSE.txt`` before the actual TSV. The
    former implementation returned that first text file and silently imported
    license prose as rows. Prefer a caller-supplied canonical basename, then a
    TSV, and use a non-documentation TXT only as a compatibility fallback.
    """

    with zipfile.ZipFile(path) as zf:
        members = []
        for info in zf.infolist():
            if info.is_dir():
                continue
            archive_path = PurePosixPath(info.filename.replace("\\", "/"))
            if any(part.casefold() == "__macosx" for part in archive_path.parts):
                continue
            members.append((info.filename, archive_path.name.casefold()))

        for preferred in preferred_names:
            wanted = PurePosixPath(preferred).name.casefold()
            for name, basename in members:
                if basename == wanted:
                    return name

        for name, basename in members:
            if basename.endswith(".tsv"):
                return name

        documentation_prefixes = ("license", "readme", "created")
        for name, basename in members:
            if not basename.endswith(".txt"):
                continue
            stem = basename[:-4]
            if stem.startswith(documentation_prefixes):
                continue
            return name
    return None


def import_pharmgkb_annotations(conn: sqlite3.Connection, path: Path) -> int:
    if not path.exists():
        print(f"skip missing PharmGKB annotations: {path}", file=sys.stderr)
        return 0
    member = first_tsv_in_zip(path, ("clinical_annotations.tsv",))
    if not member:
        print(f"skip PharmGKB zip without TSV: {path}", file=sys.stderr)
        return 0
    evidence_summary_by_annotation: dict[str, str] = {}
    evidence_member = first_tsv_in_zip(path, ("clinical_ann_evidence.tsv",))
    if evidence_member:
        with zipfile.ZipFile(path) as zf, zf.open(evidence_member) as raw:
            text = (line.decode("utf-8", errors="replace") for line in raw)
            for evidence_row in csv.DictReader(text, delimiter="\t"):
                annotation_id = safe_get(
                    evidence_row, "Clinical Annotation ID", "Annotation ID"
                )
                summary = safe_get(evidence_row, "Summary", "Annotation Text")
                if annotation_id and summary and annotation_id not in evidence_summary_by_annotation:
                    evidence_summary_by_annotation[annotation_id] = summary

    count = 0
    batch = []
    with zipfile.ZipFile(path) as zf, zf.open(member) as raw:
        text = (line.decode("utf-8", errors="replace") for line in raw)
        reader = csv.DictReader(text, delimiter="\t")
        for row in reader:
            variant = safe_get(row, "Variant", "variant", "Variant/Haplotypes")
            rsid = ""
            for token in variant.replace(";", " ").replace(",", " ").split():
                if token.lower().startswith("rs"):
                    rsid = token.lower()
                    break
            annotation_id = safe_get(
                row, "Clinical Annotation ID", "Annotation ID", "id"
            )
            batch.append(
                (
                    annotation_id,
                    variant,
                    rsid,
                    safe_get(row, "Gene", "Genes"),
                    safe_get(row, "Chemical", "Drug", "Chemicals", "Drug(s)"),
                    safe_get(
                        row,
                        "Phenotype",
                        "Phenotypes",
                        "Phenotype(s)",
                        "Phenotype Category",
                    ),
                    safe_get(row, "Level of Evidence", "Evidence Level"),
                    safe_get(row, "Annotation Text", "Summary")
                    or evidence_summary_by_annotation.get(annotation_id, ""),
                    CLINPGX_SOURCE_VERSION,
                )
            )
            if len(batch) >= 2000:
                conn.executemany("INSERT INTO pharmgkb_clinical_annotations VALUES (?,?,?,?,?,?,?,?,?)", batch)
                count += len(batch)
                batch.clear()
        if batch:
            conn.executemany("INSERT INTO pharmgkb_clinical_annotations VALUES (?,?,?,?,?,?,?,?,?)", batch)
            count += len(batch)
    conn.commit()
    return count


def import_pharmgkb_allele_annotations(conn: sqlite3.Connection, path: Path) -> int:
    if not path.exists():
        return 0
    member = first_tsv_in_zip(path, ("clinical_ann_alleles.tsv",))
    if not member:
        return 0
    count = 0
    batch = []
    with zipfile.ZipFile(path) as zf, zf.open(member) as raw:
        text = (line.decode("utf-8", errors="replace") for line in raw)
        reader = csv.DictReader(text, delimiter="\t")
        for row in reader:
            batch.append(
                (
                    safe_get(row, "Clinical Annotation ID", "Annotation ID"),
                    safe_get(row, "Genotype/Allele", "Genotype", "Allele"),
                    safe_get(row, "Annotation Text", "Summary"),
                    safe_get(row, "Allele Function", "Function"),
                    CLINPGX_SOURCE_VERSION,
                )
            )
            if len(batch) >= 2000:
                conn.executemany(
                    "INSERT INTO pharmgkb_allele_annotations VALUES (?,?,?,?,?)", batch
                )
                count += len(batch)
                batch.clear()
        if batch:
            conn.executemany(
                "INSERT INTO pharmgkb_allele_annotations VALUES (?,?,?,?,?)", batch
            )
            count += len(batch)
    conn.commit()
    return count


def import_pharmgkb_evidence(conn: sqlite3.Connection, path: Path) -> int:
    if not path.exists():
        return 0
    member = first_tsv_in_zip(path, ("clinical_ann_evidence.tsv",))
    if not member:
        return 0
    count = 0
    batch = []
    with zipfile.ZipFile(path) as zf, zf.open(member) as raw:
        text = (line.decode("utf-8", errors="replace") for line in raw)
        reader = csv.DictReader(text, delimiter="\t")
        for row in reader:
            batch.append(
                (
                    safe_get(row, "Clinical Annotation ID", "Annotation ID"),
                    safe_get(row, "Evidence ID"),
                    safe_get(row, "Evidence Type"),
                    safe_get(row, "Evidence URL", "URL"),
                    safe_get(row, "PMID", "PubMed ID"),
                    safe_get(row, "Summary", "Annotation Text"),
                    safe_get(row, "Score"),
                    CLINPGX_SOURCE_VERSION,
                )
            )
            if len(batch) >= 2000:
                conn.executemany(
                    "INSERT INTO pharmgkb_evidence VALUES (?,?,?,?,?,?,?,?)", batch
                )
                count += len(batch)
                batch.clear()
        if batch:
            conn.executemany(
                "INSERT INTO pharmgkb_evidence VALUES (?,?,?,?,?,?,?,?)", batch
            )
            count += len(batch)
    conn.commit()
    return count


def import_pharmgkb_history(conn: sqlite3.Connection, path: Path) -> int:
    if not path.exists():
        return 0
    member = first_tsv_in_zip(path, ("clinical_ann_history.tsv",))
    if not member:
        return 0
    count = 0
    batch = []
    with zipfile.ZipFile(path) as zf, zf.open(member) as raw:
        text = (line.decode("utf-8", errors="replace") for line in raw)
        reader = csv.DictReader(text, delimiter="\t")
        for row in reader:
            batch.append(
                (
                    safe_get(row, "Clinical Annotation ID", "Annotation ID"),
                    safe_get(row, "Date (YYYY-MM-DD)", "Date"),
                    safe_get(row, "Type", "Event Type"),
                    safe_get(row, "Comment", "Summary"),
                    CLINPGX_SOURCE_VERSION,
                )
            )
            if len(batch) >= 2000:
                conn.executemany(
                    "INSERT INTO pharmgkb_history VALUES (?,?,?,?,?)", batch
                )
                count += len(batch)
                batch.clear()
        if batch:
            conn.executemany("INSERT INTO pharmgkb_history VALUES (?,?,?,?,?)", batch)
            count += len(batch)
    conn.commit()
    return count


def import_pharmgkb_clinical_variants(conn: sqlite3.Connection, path: Path) -> int:
    if not path.exists():
        print(f"skip missing PharmGKB clinical variants: {path}", file=sys.stderr)
        return 0
    member = first_tsv_in_zip(path, ("clinicalVariants.tsv",))
    if not member:
        print(f"skip PharmGKB clinical variants zip without TSV: {path}", file=sys.stderr)
        return 0
    count = 0
    batch = []
    with zipfile.ZipFile(path) as zf, zf.open(member) as raw:
        text = (line.decode("utf-8", errors="replace") for line in raw)
        reader = csv.DictReader(text, delimiter="\t")
        for row in reader:
            variant = safe_get(row, "Variant", "variant")
            rsid = ""
            for token in variant.replace(";", " ").replace(",", " ").split():
                if token.lower().startswith("rs"):
                    rsid = token.lower()
                    break
            batch.append(
                (
                    variant,
                    rsid,
                    safe_get(row, "Gene", "Genes"),
                    safe_get(row, "Chemical", "Drug", "Chemicals"),
                    safe_get(row, "Level of Evidence", "Evidence Level"),
                    CLINPGX_SOURCE_VERSION,
                )
            )
            if len(batch) >= 2000:
                conn.executemany("INSERT INTO pharmgkb_clinical_variants VALUES (?,?,?,?,?,?)", batch)
                count += len(batch)
                batch.clear()
        if batch:
            conn.executemany("INSERT INTO pharmgkb_clinical_variants VALUES (?,?,?,?,?,?)", batch)
            count += len(batch)
    conn.commit()
    return count


DATA_TABLES = (
    "clinvar_variants",
    "gwas_associations",
    "pharmgkb_clinical_annotations",
    "pharmgkb_allele_annotations",
    "pharmgkb_evidence",
    "pharmgkb_history",
    "pharmgkb_clinical_variants",
)

TABLE_SOURCE_IDS = {
    "clinvar_variants": CLINVAR_SOURCE_ID,
    "gwas_associations": GWAS_SOURCE_ID,
    "pharmgkb_clinical_annotations": "pharmgkb_clinical_annotations",
    "pharmgkb_allele_annotations": "pharmgkb_allele_annotations",
    "pharmgkb_evidence": "pharmgkb_evidence",
    "pharmgkb_history": "pharmgkb_history",
    "pharmgkb_clinical_variants": "pharmgkb_clinical_variants",
}


def verify_import_rowid_contiguity(conn: sqlite3.Connection) -> None:
    """Prove the fresh append-only import has contiguous rowids per source."""
    for table, source_id in TABLE_SOURCE_IDS.items():
        recorded_row = conn.execute(
            "SELECT imported_row_count FROM data_sources WHERE id = ? LIMIT 1",
            (source_id,),
        ).fetchone()
        if recorded_row is None:
            continue
        recorded = int(recorded_row[0])
        high_water = int(
            conn.execute(f'SELECT COALESCE(MAX(rowid), 0) FROM "{table}"').fetchone()[0]
        )
        if high_water != recorded:
            raise RuntimeError(
                f"{table} rowid high-water {high_water} does not match imported count {recorded}"
            )
    write_pack_metadata(conn, {"rowid_contiguity_verified": "true"})


def table_row_counts(db_path: Path) -> dict[str, int]:
    if not db_path.exists():
        return {}
    conn = sqlite3.connect(str(db_path))
    try:
        available = {
            row[0]
            for row in conn.execute("SELECT name FROM sqlite_master WHERE type = 'table'")
        }
        counts: dict[str, int] = {}
        has_sources = "data_sources" in available
        contiguity_verified = False
        if "pack_metadata" in available:
            marker = conn.execute(
                "SELECT value FROM pack_metadata WHERE key = 'rowid_contiguity_verified' LIMIT 1"
            ).fetchone()
            contiguity_verified = marker is not None and str(marker[0]).lower() == "true"
        for table in DATA_TABLES:
            if table not in available:
                continue

            # Builder-created pack tables are append-only and receive contiguous
            # rowids. When the physical high-water mark independently agrees
            # with the recorded importer count, that is an exact O(log n)
            # count and avoids a multi-gigabyte full scan during regression
            # checks. Any disagreement or legacy schema falls back to COUNT(*).
            recorded = None
            if has_sources and contiguity_verified:
                row = conn.execute(
                    "SELECT imported_row_count FROM data_sources WHERE id = ? LIMIT 1",
                    (TABLE_SOURCE_IDS[table],),
                ).fetchone()
                if row is not None:
                    recorded = int(row[0])
            high_water = int(
                conn.execute(f'SELECT COALESCE(MAX(rowid), 0) FROM "{table}"').fetchone()[0]
            )
            if recorded is not None and recorded >= 0 and high_water == recorded:
                counts[table] = recorded
            else:
                counts[table] = int(
                    conn.execute(f'SELECT COUNT(*) FROM "{table}"').fetchone()[0]
                )
        return counts
    finally:
        conn.close()


def clinvar_quality_profile(conn: sqlite3.Connection) -> dict[str, object]:
    row_count = int(conn.execute("SELECT COUNT(*) FROM clinvar_variants").fetchone()[0])
    matchable = int(
        conn.execute(
            "SELECT COUNT(*) FROM clinvar_variants WHERE allele_matchable = 1"
        ).fetchone()[0]
    )
    simple_snvs = int(
        conn.execute(
            "SELECT COUNT(*) FROM clinvar_variants WHERE is_simple_snv = 1"
        ).fetchone()[0]
    )
    statuses = {
        str(status): int(count)
        for status, count in conn.execute(
            """
            SELECT allele_match_status, COUNT(*)
            FROM clinvar_variants
            GROUP BY allele_match_status
            ORDER BY allele_match_status
            """
        )
    }
    matchable_by_assembly = {
        str(assembly): int(count)
        for assembly, count in conn.execute(
            """
            SELECT assembly, COUNT(*)
            FROM clinvar_variants
            WHERE allele_matchable = 1
            GROUP BY assembly
            ORDER BY assembly
            """
        )
    }
    invalid_matchable = int(
        conn.execute(
            """
            SELECT COUNT(*) FROM clinvar_variants
            WHERE allele_matchable = 1
              AND (
                assembly NOT IN ('GRCh37', 'GRCh38')
                OR chrom IS NULL OR chrom = ''
                OR position_vcf IS NULL OR position_vcf <= 0
                OR reference_allele IS NULL OR alternate_allele IS NULL
                OR allele_match_key IS NULL OR allele_match_key = ''
              )
            """
        ).fetchone()[0]
    )
    return {
        "rows": row_count,
        "allele_matchable_rows": matchable,
        "simple_snv_rows": simple_snvs,
        "invalid_matchable_rows": invalid_matchable,
        "matchable_rows_by_assembly": matchable_by_assembly,
        "match_status_counts": statuses,
    }


def clinpgx_quality_profile(conn: sqlite3.Connection) -> dict[str, int]:
    counts = {
        table: int(conn.execute(f'SELECT COUNT(*) FROM "{table}"').fetchone()[0])
        for table in (
            "pharmgkb_clinical_annotations",
            "pharmgkb_allele_annotations",
            "pharmgkb_evidence",
            "pharmgkb_history",
            "pharmgkb_clinical_variants",
        )
    }
    counts["clinical_annotations_with_text"] = int(
        conn.execute(
            """
            SELECT COUNT(*) FROM pharmgkb_clinical_annotations
            WHERE annotation_text IS NOT NULL AND TRIM(annotation_text) <> ''
            """
        ).fetchone()[0]
    )
    counts["allele_annotations_with_text"] = int(
        conn.execute(
            """
            SELECT COUNT(*) FROM pharmgkb_allele_annotations
            WHERE annotation_text IS NOT NULL AND TRIM(annotation_text) <> ''
            """
        ).fetchone()[0]
    )
    counts["evidence_rows_with_summary"] = int(
        conn.execute(
            """
            SELECT COUNT(*) FROM pharmgkb_evidence
            WHERE summary IS NOT NULL AND TRIM(summary) <> ''
            """
        ).fetchone()[0]
    )
    return counts


def validate_pack(conn: sqlite3.Connection) -> dict[str, object]:
    integrity = str(conn.execute("PRAGMA integrity_check").fetchone()[0])
    if integrity.lower() != "ok":
        raise RuntimeError(f"SQLite integrity check failed: {integrity}")

    profile = clinvar_quality_profile(conn)
    if int(profile["rows"]) <= 0:
        raise RuntimeError("ClinVar import produced zero rows; refusing to install pack")
    if int(profile["allele_matchable_rows"]) <= 0:
        raise RuntimeError(
            "ClinVar import produced no allele-specific rows; source schema may have drifted"
        )
    matchable_by_assembly = profile["matchable_rows_by_assembly"]
    assert isinstance(matchable_by_assembly, dict)
    missing_builds = [
        assembly
        for assembly in ("GRCh37", "GRCh38")
        if int(matchable_by_assembly.get(assembly, 0)) <= 0
    ]
    if missing_builds:
        raise RuntimeError(
            "ClinVar import has no exact allele rows for required assemblies: "
            + ", ".join(missing_builds)
        )
    if int(profile["invalid_matchable_rows"]) != 0:
        raise RuntimeError(
            f"ClinVar import marked {profile['invalid_matchable_rows']} incomplete rows as matchable"
        )
    return profile


def assert_no_row_count_regression(
    existing_path: Path,
    candidate_path: Path,
    *,
    allow_regression: bool,
) -> None:
    if allow_regression or not existing_path.exists():
        return
    before = table_row_counts(existing_path)
    after = table_row_counts(candidate_path)
    regressions = {
        table: {"before": old_count, "after": after.get(table, 0)}
        for table, old_count in before.items()
        if old_count > after.get(table, 0)
    }
    if regressions:
        raise RuntimeError(
            "candidate health pack has fewer rows than the installed pack; "
            "refusing replacement without --allow-row-count-regression: "
            + json.dumps(regressions, sort_keys=True)
        )


def backup_existing_pack(db_path: Path, root: Path) -> Optional[Path]:
    if not db_path.exists():
        return None
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S%fZ")
    backup_dir = root / "data" / "cache" / "health_backups"
    backup_dir.mkdir(parents=True, exist_ok=True)
    backup_path = backup_dir / f"health_core.sqlite.{stamp}.bak"
    source = sqlite3.connect(str(db_path))
    destination = sqlite3.connect(str(backup_path))
    try:
        source.backup(destination)
        destination.commit()
        integrity = str(destination.execute("PRAGMA integrity_check").fetchone()[0])
        if integrity.lower() != "ok":
            raise RuntimeError(f"health pack backup failed integrity check: {integrity}")
    finally:
        destination.close()
        source.close()
    shutil.copystat(db_path, backup_path)
    return backup_path


def assert_safe_sqlite_sidecars(db_path: Path) -> None:
    """Refuse replacement while the installed SQLite pack has active sidecars."""
    active = []
    for suffix in ("-wal", "-shm"):
        sidecar = Path(str(db_path) + suffix)
        if sidecar.exists() and sidecar.stat().st_size > 0:
            active.append({"path": str(sidecar), "bytes": sidecar.stat().st_size})
    if active:
        raise RuntimeError(
            "installed health pack has active SQLite WAL/SHM sidecars; "
            "refusing atomic replacement until writers are stopped and the pack is checkpointed: "
            + json.dumps(active, sort_keys=True)
        )


def preserve_installed_file_metadata(installed: Path, candidate: Path) -> None:
    if not installed.exists():
        return
    installed_stat = installed.stat()
    os.chmod(candidate, installed_stat.st_mode)
    if hasattr(os, "chown"):
        try:
            os.chown(candidate, installed_stat.st_uid, installed_stat.st_gid)
        except PermissionError:
            # Non-root local builders can still replace files they own; mode is preserved.
            pass


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=".")
    ap.add_argument(
        "--clinvar-release",
        default="",
        help=(
            "Explicit ClinVar release label. By default this is inferred from matching "
            "GRCh37/GRCh38 VCF ##fileDate headers, with a content-hash fallback."
        ),
    )
    ap.add_argument(
        "--allow-row-count-regression",
        action="store_true",
        help="Explicitly allow a candidate pack to contain fewer rows than the installed pack.",
    )
    args = ap.parse_args()
    root = Path(args.root).resolve()
    src = root / "data" / "sources"
    db_path = root / "data" / "packs" / "health_core.sqlite"
    clinvar_path = src / "clinvar" / "variant_summary.txt.gz"
    clinvar_release = discover_clinvar_release(
        src / "clinvar",
        clinvar_path,
        args.clinvar_release,
    )

    candidate_path = db_path.with_name(db_path.name + ".building")
    for transient in (
        candidate_path,
        Path(str(candidate_path) + "-wal"),
        Path(str(candidate_path) + "-shm"),
    ):
        transient.unlink(missing_ok=True)

    conn = connect(candidate_path)
    create_schema(conn)
    write_pack_metadata(
        conn,
        {
            "schema_version": SCHEMA_VERSION,
            "pack_version": PACK_VERSION,
            "importer_version": IMPORTER_VERSION,
            "clinvar_match_contract": CLINVAR_MATCH_CONTRACT,
            "clinvar_rsid_alone_is_match": "false",
            "clinvar_source_release": clinvar_release.value,
            "clinvar_source_release_evidence": clinvar_release.evidence,
            "clinvar_vcf_header_evidence": json.dumps(
                clinvar_release.vcf_headers, sort_keys=True
            ),
        },
    )

    sources = [
        (
            "clinvar_variant_summary",
            "ClinVar Variant Summary",
            "https://ftp.ncbi.nlm.nih.gov/pub/clinvar/tab_delimited/variant_summary.txt.gz",
            clinvar_path,
            "NCBI public data; cite ClinVar/NCBI; verify current use terms.",
            import_clinvar_variant_summary,
        ),
        (
            GWAS_SOURCE_ID,
            "NHGRI-EBI GWAS Catalog associations",
            GWAS_SOURCE_URL,
            src / "gwas" / "gwas_catalog_associations.tsv",
            "EMBL-EBI/GWAS Catalog terms; cite GWAS Catalog and source studies.",
            import_gwas,
        ),
        (
            "pharmgkb_clinical_annotations",
            "ClinPGx/PharmGKB clinical annotations",
            CLINPGX_ANNOTATIONS_SOURCE_URL,
            src / "pharmgkb" / "clinicalAnnotations.zip",
            CLINPGX_LICENSE_NOTE,
            import_pharmgkb_annotations,
        ),
        (
            "pharmgkb_allele_annotations",
            "ClinPGx/PharmGKB allele annotations",
            CLINPGX_ANNOTATIONS_SOURCE_URL,
            src / "pharmgkb" / "clinicalAnnotations.zip",
            CLINPGX_LICENSE_NOTE,
            import_pharmgkb_allele_annotations,
        ),
        (
            "pharmgkb_evidence",
            "ClinPGx/PharmGKB annotation evidence",
            CLINPGX_ANNOTATIONS_SOURCE_URL,
            src / "pharmgkb" / "clinicalAnnotations.zip",
            CLINPGX_LICENSE_NOTE,
            import_pharmgkb_evidence,
        ),
        (
            "pharmgkb_history",
            "ClinPGx/PharmGKB annotation history",
            CLINPGX_ANNOTATIONS_SOURCE_URL,
            src / "pharmgkb" / "clinicalAnnotations.zip",
            CLINPGX_LICENSE_NOTE,
            import_pharmgkb_history,
        ),
        (
            "pharmgkb_clinical_variants",
            "ClinPGx/PharmGKB clinical variants",
            CLINPGX_VARIANTS_SOURCE_URL,
            src / "pharmgkb" / "clinicalVariants.zip",
            CLINPGX_LICENSE_NOTE,
            import_pharmgkb_clinical_variants,
        ),
    ]

    manifest = []
    try:
        for sid, name, url, path, license_note, fn in sources:
            if sid == CLINVAR_SOURCE_ID:
                rows = fn(conn, path, source_release=clinvar_release.value)
                version = clinvar_release.value
            elif sid == GWAS_SOURCE_ID:
                rows = fn(conn, path)
                version = GWAS_SOURCE_RELEASE
            else:
                rows = fn(conn, path)
                version = "current_download"
            if path.exists():
                record_source(
                    conn,
                    source_id=sid,
                    source_name=name,
                    source_url=url,
                    local_path=path,
                    license_note=license_note,
                    row_count=rows,
                    pack_name="health_core",
                    version=version,
                )
                manifest.append(
                    {
                        "source_id": sid,
                        "rows": rows,
                        "path": str(path),
                        "version_or_release": version,
                    }
                )
                print(f"imported {rows:>8} rows from {name}")

        # The summary file contains the imported exact VCF fields but no release
        # header. Preserve the hashed sibling VCFs used to infer that release.
        for header in clinvar_release.vcf_headers:
            vcf_path = Path(header["path"])
            assembly = header.get("expected_reference", header.get("reference", "unknown"))
            source_id = f"clinvar_{assembly.lower()}_vcf_release_evidence"
            source_url = (
                f"https://ftp.ncbi.nlm.nih.gov/pub/clinvar/vcf_{assembly}/clinvar.vcf.gz"
            )
            record_source(
                conn,
                source_id=source_id,
                source_name=f"ClinVar {assembly} VCF release evidence",
                source_url=source_url,
                local_path=vcf_path,
                license_note="NCBI public data; release-header evidence for the ClinVar summary import.",
                row_count=0,
                pack_name="health_core",
                version=clinvar_release.value,
            )
            manifest.append(
                {
                    "source_id": source_id,
                    "rows": 0,
                    "path": str(vcf_path),
                    "version_or_release": clinvar_release.value,
                    "role": "release_header_evidence",
                    "declared_reference": header.get("reference", ""),
                    "declared_file_date": header.get("file_date", ""),
                }
            )

        quality_profile = validate_pack(conn)
        clinpgx_profile = clinpgx_quality_profile(conn)
        if int(clinpgx_profile["pharmgkb_clinical_annotations"]) > 0:
            missing_companion_tables = [
                table
                for table in (
                    "pharmgkb_allele_annotations",
                    "pharmgkb_evidence",
                    "pharmgkb_history",
                )
                if int(clinpgx_profile[table]) <= 0
            ]
            if missing_companion_tables:
                raise RuntimeError(
                    "ClinPGx clinical annotations were imported without required "
                    "companion detail tables: " + ", ".join(missing_companion_tables)
                )
            if int(clinpgx_profile["clinical_annotations_with_text"]) <= 0:
                raise RuntimeError(
                    "ClinPGx annotations contain no evidence summaries; source schema may have drifted"
                )
        verify_import_rowid_contiguity(conn)
        conn.execute("PRAGMA wal_checkpoint(TRUNCATE)")
        conn.execute("PRAGMA journal_mode=DELETE")
        conn.close()
    except Exception:
        conn.close()
        raise

    assert_no_row_count_regression(
        db_path,
        candidate_path,
        allow_regression=args.allow_row_count_regression,
    )
    assert_safe_sqlite_sidecars(db_path)
    backup_path = backup_existing_pack(db_path, root)
    preserve_installed_file_metadata(db_path, candidate_path)

    manifest_payload = {
        "built_at": now_iso(),
        "pack_version": PACK_VERSION,
        "schema_version": SCHEMA_VERSION,
        "importer_version": IMPORTER_VERSION,
        "clinvar_match_contract": CLINVAR_MATCH_CONTRACT,
        "clinvar_release": {
            "value": clinvar_release.value,
            "evidence": clinvar_release.evidence,
            "vcf_headers": clinvar_release.vcf_headers,
        },
        "quality": {
            "clinvar": quality_profile,
            "clinpgx": clinpgx_profile,
        },
        "sources": manifest,
    }
    manifest_path = root / "data" / "packs" / "health_core_manifest.json"
    manifest_candidate = manifest_path.with_name(manifest_path.name + ".building")
    manifest_candidate.write_text(
        json.dumps(manifest_payload, indent=2),
        encoding="utf-8",
    )

    os.replace(candidate_path, db_path)
    os.replace(manifest_candidate, manifest_path)
    if backup_path is not None:
        print(f"backed up previous pack to {backup_path}")
    print(f"built {db_path}")
    print(f"ClinVar release: {clinvar_release.value} ({clinvar_release.evidence})")
    print(f"ClinVar allele quality: {json.dumps(quality_profile, sort_keys=True)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
