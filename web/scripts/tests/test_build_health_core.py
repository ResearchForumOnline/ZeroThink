from __future__ import annotations

import gzip
import importlib.util
import json
import sqlite3
import subprocess
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "build_health_core.py"
SPEC = importlib.util.spec_from_file_location("build_health_core", SCRIPT)
assert SPEC is not None and SPEC.loader is not None
BUILDER = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = BUILDER
SPEC.loader.exec_module(BUILDER)


SUMMARY_COLUMNS = [
    "#AlleleID",
    "Type",
    "Name",
    "GeneSymbol",
    "ClinicalSignificance",
    "RS# (dbSNP)",
    "RCVaccession",
    "PhenotypeList",
    "Assembly",
    "Chromosome",
    "Start",
    "ReferenceAllele",
    "AlternateAllele",
    "ReviewStatus",
    "VariationID",
    "PositionVCF",
    "ReferenceAlleleVCF",
    "AlternateAlleleVCF",
]


def write_summary(path: Path) -> None:
    rows = [
        [
            "101",
            "single nucleotide variant",
            "NM_TEST:c.1G>A",
            "GENE1",
            "Pathogenic",
            "123",
            "RCV000001",
            "Synthetic condition one",
            "GRCh38",
            "chr1",
            "100",
            "G",
            "A",
            "reviewed by expert panel",
            "1001",
            "100",
            "G",
            "A",
        ],
        [
            "101",
            "single nucleotide variant",
            "NM_TEST:c.1G>A",
            "GENE1",
            "Pathogenic",
            "123",
            "RCV000001",
            "Synthetic condition one",
            "GRCh37",
            "1",
            "90",
            "G",
            "A",
            "reviewed by expert panel",
            "1001",
            "90",
            "G",
            "A",
        ],
        [
            "202",
            "Deletion",
            "NM_TEST:c.20del",
            "GENE2",
            "Likely pathogenic",
            "456",
            "RCV000002",
            "Synthetic condition two",
            "GRCh38",
            "2",
            "200",
            "AT",
            "A",
            "criteria provided, multiple submitters, no conflicts",
            "2002",
            "200",
            "AT",
            "A",
        ],
        [
            "303",
            "copy number gain",
            "synthetic symbolic variant",
            "GENE3",
            "Uncertain significance",
            "789",
            "RCV000003",
            "Synthetic condition three",
            "GRCh38",
            "3",
            "300",
            "na",
            "na",
            "criteria provided, single submitter",
            "3003",
            "300",
            "N",
            "<DUP>",
        ],
        [
            "404",
            "single nucleotide variant",
            "synthetic row without dbSNP",
            "GENE4",
            "Benign",
            "-1",
            "RCV000004",
            "Synthetic condition four",
            "GRCh38",
            "4",
            "400",
            "C",
            "T",
            "criteria provided, single submitter",
            "4004",
            "400",
            "na",
            "na",
        ],
        [
            "505",
            "single nucleotide variant",
            "synthetic old assembly",
            "GENE5",
            "Benign",
            "999",
            "RCV000005",
            "Synthetic condition five",
            "NCBI36",
            "5",
            "500",
            "A",
            "C",
            "criteria provided, single submitter",
            "5005",
            "500",
            "A",
            "C",
        ],
    ]
    path.parent.mkdir(parents=True, exist_ok=True)
    with gzip.open(path, "wt", encoding="utf-8", newline="") as fh:
        fh.write("\t".join(SUMMARY_COLUMNS) + "\n")
        for row in rows:
            fh.write("\t".join(row) + "\n")


def write_vcf_header(path: Path, assembly: str, file_date: str = "20260503") -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with gzip.open(path, "wt", encoding="utf-8") as fh:
        fh.write("##fileformat=VCFv4.1\n")
        fh.write(f"##fileDate={file_date}\n")
        fh.write(f"##reference={assembly}\n")
        fh.write("#CHROM\tPOS\tID\tREF\tALT\tQUAL\tFILTER\tINFO\n")


def write_gwas(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        "\t".join(
            [
                "SNPS",
                "DISEASE/TRAIT",
                "MAPPED_TRAIT",
                "P-VALUE",
                "OR or BETA",
                "BETA",
                "PUBMEDID",
                "STUDY ACCESSION",
                "INITIAL SAMPLE SIZE",
                "REPLICATION SAMPLE SIZE",
            ]
        )
        + "\n"
        + "\t".join(
            [
                "rs321",
                "Synthetic trait",
                "synthetic measurement",
                "1e-9",
                "1.2",
                "",
                "12345678",
                "GCST000001",
                "100 cases, 100 controls",
                "50 cases, 50 controls",
            ]
        )
        + "\n",
        encoding="utf-8",
    )


def write_pharmgkb_sources(root: Path) -> None:
    source_dir = root / "data" / "sources" / "pharmgkb"
    source_dir.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(source_dir / "clinicalAnnotations.zip", "w") as zf:
        zf.writestr("LICENSE.txt", "CC BY-SA 4.0\n")
        zf.writestr(
            "clinical_annotations.tsv",
            "Clinical Annotation ID\tVariant/Haplotypes\tGene\tDrug(s)\t"
            "Phenotype(s)\tLevel of Evidence\n"
            "CA-MAIN\trs654\tCYP2C9\twarfarin\tdose\t1A\n",
        )
        zf.writestr(
            "clinical_ann_alleles.tsv",
            "Clinical Annotation ID\tGenotype/Allele\tAnnotation Text\tAllele Function\n"
            "CA-MAIN\tAG\tSynthetic allele-specific text\tdecreased function\n",
        )
        zf.writestr(
            "clinical_ann_evidence.tsv",
            "Clinical Annotation ID\tEvidence ID\tEvidence Type\tEvidence URL\t"
            "PMID\tSummary\tScore\n"
            "CA-MAIN\tEV-1\tVariant Annotation\thttps://example.test/evidence\t"
            "12345678\tSynthetic evidence summary\t1.5\n",
        )
        zf.writestr(
            "clinical_ann_history.tsv",
            "Clinical Annotation ID\tDate (YYYY-MM-DD)\tType\tComment\n"
            "CA-MAIN\t2026-01-01\tUpdate\tSynthetic curation update\n",
        )
    with zipfile.ZipFile(source_dir / "clinicalVariants.zip", "w") as zf:
        zf.writestr("LICENSE.txt", "CC BY-SA 4.0\n")
        zf.writestr(
            "clinicalVariants.tsv",
            "Variant\tGene\tChemicals\tLevel of Evidence\n"
            "rs987\tVKORC1\twarfarin\t1A\n",
        )


class HealthCoreBuilderTests(unittest.TestCase):
    def test_synthetic_build_is_allele_specific_and_backward_compatible(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            clinvar = root / "data" / "sources" / "clinvar"
            write_summary(clinvar / "variant_summary.txt.gz")
            write_vcf_header(clinvar / "clinvar_GRCh37.vcf.gz", "GRCh37")
            write_vcf_header(clinvar / "clinvar_GRCh38.vcf.gz", "GRCh38")
            write_gwas(
                root
                / "data"
                / "sources"
                / "gwas"
                / "gwas_catalog_associations.tsv"
            )
            write_pharmgkb_sources(root)

            completed = subprocess.run(
                [sys.executable, str(SCRIPT), "--root", str(root)],
                check=False,
                capture_output=True,
                text=True,
            )
            self.assertEqual(completed.returncode, 0, completed.stderr)

            db_path = root / "data" / "packs" / "health_core.sqlite"
            conn = sqlite3.connect(str(db_path))
            conn.row_factory = sqlite3.Row
            try:
                columns = [
                    row[1]
                    for row in conn.execute("PRAGMA table_info(clinvar_variants)")
                ]
                self.assertEqual(
                    columns[:13],
                    [
                        "variation_id",
                        "rsid",
                        "chrom",
                        "pos_grch37",
                        "pos_grch38",
                        "gene_symbol",
                        "clinical_significance",
                        "condition_name",
                        "review_status",
                        "accession",
                        "allele_id",
                        "variant_type",
                        "source_version",
                    ],
                )

                grch38_snv = conn.execute(
                    """
                    SELECT * FROM clinvar_variants
                    WHERE variation_id = '1001' AND assembly = 'GRCh38'
                    """
                ).fetchone()
                assert grch38_snv is not None
                self.assertEqual(grch38_snv["rsid"], "rs123")
                self.assertEqual(grch38_snv["chrom"], "1")
                self.assertEqual(grch38_snv["condition_name"], "Synthetic condition one")
                self.assertEqual(grch38_snv["position_vcf"], 100)
                self.assertEqual(grch38_snv["reference_allele"], "G")
                self.assertEqual(grch38_snv["alternate_allele"], "A")
                self.assertEqual(grch38_snv["allele_match_status"], "exact_snv")
                self.assertEqual(grch38_snv["allele_matchable"], 1)
                self.assertEqual(grch38_snv["is_simple_snv"], 1)
                self.assertEqual(grch38_snv["allele_match_key"], "GRCh38:1:100:G>A")
                self.assertEqual(grch38_snv["source_release"], "2026-05-03")

                indel = conn.execute(
                    "SELECT * FROM clinvar_variants WHERE variation_id = '2002'"
                ).fetchone()
                assert indel is not None
                self.assertEqual(indel["allele_match_status"], "exact_sequence")
                self.assertEqual(indel["allele_matchable"], 1)
                self.assertEqual(indel["is_simple_snv"], 0)

                symbolic = conn.execute(
                    "SELECT * FROM clinvar_variants WHERE variation_id = '3003'"
                ).fetchone()
                assert symbolic is not None
                self.assertEqual(
                    symbolic["allele_match_status"], "symbolic_or_ambiguous_allele"
                )
                self.assertEqual(symbolic["allele_matchable"], 0)
                self.assertIsNone(symbolic["allele_match_key"])

                no_rsid = conn.execute(
                    "SELECT * FROM clinvar_variants WHERE variation_id = '4004'"
                ).fetchone()
                assert no_rsid is not None
                self.assertEqual(no_rsid["rsid"], "")
                self.assertEqual(no_rsid["allele_match_status"], "missing_vcf_allele")

                old_build = conn.execute(
                    "SELECT * FROM clinvar_variants WHERE variation_id = '5005'"
                ).fetchone()
                assert old_build is not None
                self.assertEqual(old_build["allele_match_status"], "unsupported_assembly")

                exact_view_ids = {
                    row[0]
                    for row in conn.execute(
                        "SELECT variation_id FROM clinvar_exact_alleles"
                    )
                }
                self.assertEqual(exact_view_ids, {"1001", "2002"})

                metadata = dict(conn.execute("SELECT key, value FROM pack_metadata"))
                self.assertEqual(metadata["schema_version"], "health-core-schema-0.3.0")
                self.assertEqual(metadata["rowid_contiguity_verified"], "true")
                self.assertEqual(metadata["clinvar_rsid_alone_is_match"], "false")
                self.assertIn("rsID alone is never sufficient", metadata["clinvar_match_contract"])
                self.assertEqual(metadata["clinvar_source_release"], "2026-05-03")

                gwas = conn.execute(
                    "SELECT * FROM gwas_associations WHERE rsid = 'rs321'"
                ).fetchone()
                assert gwas is not None
                self.assertEqual(
                    gwas["source_version"], "GWAS Catalog associations v1.0.2"
                )
                gwas_source = conn.execute(
                    """
                    SELECT source_url, version_or_release, imported_row_count
                    FROM data_sources WHERE id = 'gwas_catalog_associations'
                    """
                ).fetchone()
                assert gwas_source is not None
                self.assertEqual(gwas_source["source_url"], BUILDER.GWAS_SOURCE_URL)
                self.assertEqual(gwas_source["version_or_release"], "v1.0.2")
                self.assertEqual(gwas_source["imported_row_count"], 1)

                pharm_sources = {
                    row["id"]: row
                    for row in conn.execute(
                        """
                        SELECT id, source_name, source_url, license_note,
                               imported_row_count
                        FROM data_sources WHERE id LIKE 'pharmgkb_%'
                        """
                    )
                }
                expected_pharm_urls = {
                    "pharmgkb_clinical_annotations": BUILDER.CLINPGX_ANNOTATIONS_SOURCE_URL,
                    "pharmgkb_allele_annotations": BUILDER.CLINPGX_ANNOTATIONS_SOURCE_URL,
                    "pharmgkb_evidence": BUILDER.CLINPGX_ANNOTATIONS_SOURCE_URL,
                    "pharmgkb_history": BUILDER.CLINPGX_ANNOTATIONS_SOURCE_URL,
                    "pharmgkb_clinical_variants": BUILDER.CLINPGX_VARIANTS_SOURCE_URL,
                }
                self.assertEqual(set(pharm_sources), set(expected_pharm_urls))
                for source_id, expected_url in expected_pharm_urls.items():
                    source = pharm_sources[source_id]
                    self.assertTrue(
                        source["source_name"].startswith("ClinPGx/PharmGKB")
                    )
                    self.assertEqual(source["source_url"], expected_url)
                    self.assertIn("CC BY-SA 4.0", source["license_note"])
                    self.assertIn("adapted by ZeroThink", source["license_note"])
                    self.assertEqual(source["imported_row_count"], 1)
            finally:
                conn.close()

            manifest = json.loads(
                (root / "data" / "packs" / "health_core_manifest.json").read_text(
                    encoding="utf-8"
                )
            )
            self.assertEqual(manifest["quality"]["clinvar"]["rows"], 6)
            self.assertEqual(
                manifest["quality"]["clinpgx"]["pharmgkb_allele_annotations"], 1
            )
            self.assertEqual(
                manifest["quality"]["clinpgx"]["pharmgkb_evidence"], 1
            )
            self.assertEqual(
                manifest["quality"]["clinvar"]["allele_matchable_rows"], 3
            )
            self.assertEqual(
                manifest["clinvar_release"]["evidence"],
                "inferred_from_sibling_vcf_headers",
            )
            self.assertEqual(
                {
                    source["source_id"]
                    for source in manifest["sources"]
                    if source.get("role") == "release_header_evidence"
                },
                {
                    "clinvar_grch37_vcf_release_evidence",
                    "clinvar_grch38_vcf_release_evidence",
                },
            )

            rebuilt = subprocess.run(
                [sys.executable, str(SCRIPT), "--root", str(root)],
                check=False,
                capture_output=True,
                text=True,
            )
            self.assertEqual(rebuilt.returncode, 0, rebuilt.stderr)
            backups = list(
                (root / "data" / "cache" / "health_backups").glob(
                    "health_core.sqlite.*.bak"
                )
            )
            self.assertEqual(len(backups), 1)
            backup_conn = sqlite3.connect(str(backups[0]))
            try:
                self.assertEqual(
                    backup_conn.execute("PRAGMA integrity_check").fetchone()[0], "ok"
                )
                self.assertEqual(
                    backup_conn.execute(
                        "SELECT COUNT(*) FROM clinvar_variants"
                    ).fetchone()[0],
                    6,
                )
            finally:
                backup_conn.close()

    def test_conflicting_vcf_release_dates_fail_closed(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            summary = root / "variant_summary.txt.gz"
            write_summary(summary)
            write_vcf_header(root / "clinvar_GRCh37.vcf.gz", "GRCh37", "20260503")
            write_vcf_header(root / "clinvar_GRCh38.vcf.gz", "GRCh38", "20260510")

            with self.assertRaisesRegex(RuntimeError, "release dates disagree"):
                BUILDER.discover_clinvar_release(root, summary)

    def test_missing_exact_allele_source_columns_fail_closed(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            source = Path(tmp) / "variant_summary.txt.gz"
            with gzip.open(source, "wt", encoding="utf-8") as fh:
                fh.write("Assembly\tChromosome\tVariationID\n")
                fh.write("GRCh38\t1\t1\n")
            conn = sqlite3.connect(":memory:")
            try:
                BUILDER.create_schema(conn)
                with self.assertRaisesRegex(RuntimeError, "missing exact-allele columns"):
                    BUILDER.import_clinvar_variant_summary(
                        conn, source, source_release="synthetic"
                    )
            finally:
                conn.close()

    def test_row_count_regression_requires_explicit_override(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            existing = root / "existing.sqlite"
            candidate = root / "candidate.sqlite"
            for path, rows in ((existing, 2), (candidate, 1)):
                conn = sqlite3.connect(str(path))
                conn.execute("CREATE TABLE clinvar_variants (id INTEGER)")
                conn.executemany(
                    "INSERT INTO clinvar_variants VALUES (?)", [(i,) for i in range(rows)]
                )
                conn.commit()
                conn.close()

            with self.assertRaisesRegex(RuntimeError, "fewer rows"):
                BUILDER.assert_no_row_count_regression(
                    existing, candidate, allow_regression=False
                )
            BUILDER.assert_no_row_count_regression(
                existing, candidate, allow_regression=True
            )

    def test_very_long_sequence_is_preserved_but_not_runtime_matchable(self) -> None:
        status, matchable, simple_snv, key = BUILDER.clinvar_allele_semantics(
            assembly="GRCh38",
            chrom="1",
            position_vcf=123,
            reference_allele="A" * (BUILDER.MAX_MATCH_ALLELE_LENGTH + 1),
            alternate_allele="T",
        )
        self.assertEqual(status, "allele_too_long_for_runtime_match")
        self.assertEqual((matchable, simple_snv, key), (0, 0, None))

    def test_pharmgkb_importers_prefer_canonical_tables_over_decoys(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            annotations = root / "clinicalAnnotations.zip"
            with zipfile.ZipFile(annotations, "w") as zf:
                zf.writestr("LICENSE.txt", "license prose\nline two\n")
                zf.writestr(
                    "unrelated.tsv",
                    "Clinical Annotation ID\tVariant/Haplotypes\tGene\n"
                    "DECOY\trs999\tWRONG\n",
                )
                zf.writestr(
                    "release/clinical_annotations.tsv",
                    "Clinical Annotation ID\tVariant/Haplotypes\tGene\tDrug(s)\t"
                    "Phenotype(s)\tLevel of Evidence\tAnnotation Text\n"
                    "CA1\trs123\tCYP2C19\tclopidogrel\tresponse\t1A\t"
                    "Synthetic annotation\n",
                )

            variants = root / "clinicalVariants.zip"
            with zipfile.ZipFile(variants, "w") as zf:
                zf.writestr("README.txt", "documentation\n")
                zf.writestr(
                    "fallback.tsv",
                    "Variant\tGene\tChemicals\tLevel of Evidence\n"
                    "rs998\tWRONG\tdecoy\t4\n",
                )
                zf.writestr(
                    "__MACOSX/clinicalVariants.tsv",
                    "Variant\tGene\nrs997\tMACOSX\n",
                )
                zf.writestr(
                    "data/clinicalVariants.tsv",
                    "Variant\tGene\tChemicals\tLevel of Evidence\n"
                    "rs456\tCYP2D6\tcodeine\t1A\n",
                )

            conn = sqlite3.connect(":memory:")
            conn.row_factory = sqlite3.Row
            try:
                BUILDER.create_schema(conn)
                self.assertEqual(
                    BUILDER.import_pharmgkb_annotations(conn, annotations), 1
                )
                self.assertEqual(
                    BUILDER.import_pharmgkb_clinical_variants(conn, variants), 1
                )

                annotation = conn.execute(
                    "SELECT * FROM pharmgkb_clinical_annotations"
                ).fetchone()
                assert annotation is not None
                self.assertEqual(annotation["annotation_id"], "CA1")
                self.assertEqual(annotation["rsid"], "rs123")
                self.assertEqual(annotation["chemical"], "clopidogrel")
                self.assertEqual(annotation["phenotype"], "response")
                self.assertEqual(
                    annotation["source_version"], BUILDER.CLINPGX_SOURCE_VERSION
                )

                variant = conn.execute(
                    "SELECT * FROM pharmgkb_clinical_variants"
                ).fetchone()
                assert variant is not None
                self.assertEqual(variant["rsid"], "rs456")
                self.assertEqual(variant["gene"], "CYP2D6")
                self.assertEqual(variant["chemical"], "codeine")
                self.assertEqual(
                    variant["source_version"], BUILDER.CLINPGX_SOURCE_VERSION
                )
            finally:
                conn.close()

    def test_zip_selection_prioritizes_tsv_and_uses_only_safe_txt_fallbacks(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)

            tsv_archive = root / "tsv-priority.zip"
            with zipfile.ZipFile(tsv_archive, "w") as zf:
                zf.writestr("LICENSE.txt", "license\n")
                zf.writestr("payload.txt", "fallback\n")
                zf.writestr("tables/data.tsv", "header\n")
            self.assertEqual(
                BUILDER.first_tsv_in_zip(tsv_archive, ("missing.tsv",)),
                "tables/data.tsv",
            )

            txt_archive = root / "txt-fallback.zip"
            with zipfile.ZipFile(txt_archive, "w") as zf:
                zf.writestr("LICENSE.txt", "license\n")
                zf.writestr("docs/README.txt", "readme\n")
                zf.writestr("CREATED_2026.txt", "created\n")
                zf.writestr("data/export.txt", "header\n")
            self.assertEqual(
                BUILDER.first_tsv_in_zip(txt_archive), "data/export.txt"
            )

            documentation_only = root / "documentation-only.zip"
            with zipfile.ZipFile(documentation_only, "w") as zf:
                zf.writestr("LICENSE.txt", "license\n")
                zf.writestr("README-FIRST.txt", "readme\n")
                zf.writestr("notes/CREATED.txt", "created\n")
            self.assertIsNone(BUILDER.first_tsv_in_zip(documentation_only))


if __name__ == "__main__":
    unittest.main()
