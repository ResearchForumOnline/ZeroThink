#!/usr/bin/env bash
set -euo pipefail

# ZeroThink DNA Lab v2 - source downloader
# Run from repo root. Downloads are intentionally NOT committed to git.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="${ROOT:-$(pwd)}"
TOOL_DIR="${DNA_TOOL_DIR:-$SCRIPT_DIR}"
SRC="$ROOT/data/sources"
CACHE="$ROOT/data/cache"
PACKS="$ROOT/data/packs"
LINEAGE_LIMIT="${LINEAGE_LIMIT:-5000}"

pick_python() {
  if [[ -n "${PYTHON_BIN:-}" ]]; then
    return
  fi
  for candidate in python3.12 python3.11 python3.10 python3.9 python3; do
    if command -v "$candidate" >/dev/null 2>&1; then
      PYTHON_BIN="$candidate"
      return
    fi
  done
  echo "Missing required command: python3.9 or newer" >&2
  exit 1
}

pick_python

if ! "$PYTHON_BIN" - <<'PY' >/dev/null
import sys
raise SystemExit(0 if sys.version_info >= (3, 8) else 1)
PY
then
  echo "ZeroThink DNA data-pack builder requires Python 3.8+. Found: $("$PYTHON_BIN" --version 2>&1)" >&2
  exit 1
fi

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
  cat <<EOF
ZeroThink DNA data-pack builder

Run from the ZeroThink site root:
  ROOT=/home/talkto/zerothink.talktoai.org bash /home/talkto/.private_dna_tools/scripts/update_dna_data_packs.sh

Builds local SQLite packs under:
  data/packs/health_core.sqlite
  data/packs/ancestry_core.sqlite
  data/packs/lineage_core.sqlite
  data/packs/ancient_graves.sqlite   (optional)

Optimized ancestry reference panel:
  ZDNA_BUILD_OPTIMIZED_ANCESTRY=1 ZDNA_AUTOSOME_CHROMS=1-22 bash ...

Optional specialist Jewish-diaspora frequency lane:
  ZDNA_GNOMAD_VCFS=/path/to/gnomad.sites.vcf.gz bash ...
  Imports ASJ/NFE/MID/AFR/AMR/EAS/SAS frequency context into ancestry_core.sqlite.
  ZDNA_GNOMAD_POP_VCFS=ASJ=/path/asj.vcf.gz,NFE=/path/nfe.vcf.gz bash ...
  Uses compact population-specific VCFs where INFO/AF is the population frequency.
  ZDNA_BUILD_SPECIALIST_JEWISH=1 bash ...
  Fetches a compact ASJ + control frequency starter pack for chromosomes 1,2,22.

Lineage graph size:
  LINEAGE_LIMIT=5000 by default. Increase carefully for production builds.

Ancient grave/sample matching:
  ZDNA_BUILD_ANCIENT_GRAVES=1 ZDNA_AADR_PREFIX=/path/to/aadr_dataset_prefix bash ...
  ZDNA_BUILD_ANCIENT_GRAVES=1 ZDNA_ANCIENT_CSV=/path/to/ancient_long_rows.csv bash ...
  AADR mode is ancient-only by default. The pack reports public sample/site affinity only, not descent proof.

Python runtime:
  $PYTHON_BIN
EOF
  exit 0
fi

if [[ "${1:-}" == "--check" ]]; then
  "$PYTHON_BIN" - <<'PY'
import csv, gzip, json, sqlite3, urllib.request, zipfile
print("python_stdlib_ok")
PY
  exit 0
fi

mkdir -p \
  "$SRC/clinvar" \
  "$SRC/gwas" \
  "$SRC/pharmgkb" \
  "$SRC/hpo" \
  "$SRC/mondo" \
  "$SRC/ucsc" \
  "$SRC/1000g" \
  "$SRC/gnomad" \
  "$SRC/aadr" \
  "$SRC/amtdb" \
  "$SRC/mitomap" \
  "$CACHE" \
  "$PACKS"

fetch() {
  local url="$1"
  local out="$2"
  if [ -s "$out" ] && [[ "$out" == *.gz ]]; then
    if ! gzip -t "$out" >/dev/null 2>&1; then
      echo "bad gzip, redownloading: $out"
      rm -f "$out"
    fi
  fi
  if [ -s "$out" ]; then
    echo "exists: $out"
  else
    echo "download: $url"
    rm -f "$out.tmp"
    if ! curl -fL --retry 3 --retry-delay 3 -o "$out.tmp" "$url"; then
      rm -f "$out.tmp"
      return 1
    fi
    if [[ "$out" == *.gz ]] && ! gzip -t "$out.tmp" >/dev/null 2>&1; then
      echo "downloaded file is not a valid gzip: $url" >&2
      rm -f "$out.tmp"
      return 1
    fi
    mv "$out.tmp" "$out"
  fi
  "$PYTHON_BIN" - <<PY
import hashlib, pathlib
p = pathlib.Path("$out")
h = hashlib.sha256()
with p.open('rb') as f:
    for chunk in iter(lambda: f.read(1024*1024), b''):
        h.update(chunk)
print(f"sha256 {p}: {h.hexdigest()}")
PY
}

fetch_optional() {
  local url="$1"
  local out="$2"
  if ! fetch "$url" "$out"; then
    echo "optional source unavailable, continuing: $url"
    rm -f "$out" "$out.tmp"
  fi
}

# Health / clinical evidence core
fetch "https://ftp.ncbi.nlm.nih.gov/pub/clinvar/tab_delimited/variant_summary.txt.gz" "$SRC/clinvar/variant_summary.txt.gz"
fetch "https://ftp.ncbi.nlm.nih.gov/pub/clinvar/vcf_GRCh37/clinvar.vcf.gz" "$SRC/clinvar/clinvar_GRCh37.vcf.gz"
fetch "https://ftp.ncbi.nlm.nih.gov/pub/clinvar/vcf_GRCh37/clinvar.vcf.gz.tbi" "$SRC/clinvar/clinvar_GRCh37.vcf.gz.tbi"
fetch "https://ftp.ncbi.nlm.nih.gov/pub/clinvar/vcf_GRCh38/clinvar.vcf.gz" "$SRC/clinvar/clinvar_GRCh38.vcf.gz"
fetch "https://ftp.ncbi.nlm.nih.gov/pub/clinvar/vcf_GRCh38/clinvar.vcf.gz.tbi" "$SRC/clinvar/clinvar_GRCh38.vcf.gz.tbi"
fetch_optional "https://www.ebi.ac.uk/gwas/labs/api/search/downloads/full" "$SRC/gwas/gwas_catalog_associations.tsv"

# Pharmacogenomics
fetch "https://api.pharmgkb.org/v1/download/file/data/clinicalAnnotations.zip" "$SRC/pharmgkb/clinicalAnnotations.zip"
fetch "https://api.pharmgkb.org/v1/download/file/data/clinicalAnnotations_LOE1-2.zip" "$SRC/pharmgkb/clinicalAnnotations_LOE1-2.zip"
fetch "https://api.pharmgkb.org/v1/download/file/data/clinicalVariants.zip" "$SRC/pharmgkb/clinicalVariants.zip"
fetch "https://api.pharmgkb.org/v1/download/file/data/guidelineAnnotations.json.zip" "$SRC/pharmgkb/guidelineAnnotations.json.zip"
fetch "https://api.pharmgkb.org/v1/download/file/data/genes.zip" "$SRC/pharmgkb/genes.zip"
fetch "https://api.pharmgkb.org/v1/download/file/data/variants.zip" "$SRC/pharmgkb/variants.zip"
fetch "https://api.pharmgkb.org/v1/download/file/data/drugs.zip" "$SRC/pharmgkb/drugs.zip"

# Ontologies
fetch "https://purl.obolibrary.org/obo/hp.obo" "$SRC/hpo/hp.obo"
fetch "https://purl.obolibrary.org/obo/mondo.obo" "$SRC/mondo/mondo.obo"

# Genome build normalization
fetch "https://hgdownload.soe.ucsc.edu/goldenPath/hg19/liftOver/hg19ToHg38.over.chain.gz" "$SRC/ucsc/hg19ToHg38.over.chain.gz"

# 1000 Genomes: small sample metadata + chr22 test only.
# Full autosomes are large. Build chr22 first, then use scripts/build_ancestry_reference.py for larger panels.
fetch "https://ftp.1000genomes.ebi.ac.uk/vol1/ftp/release/20130502/integrated_call_samples_v3.20130502.ALL.panel" "$SRC/1000g/integrated_call_samples_v3.20130502.ALL.panel"
fetch "https://ftp.1000genomes.ebi.ac.uk/vol1/ftp/release/20130502/ALL.chr22.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz" "$SRC/1000g/ALL.chr22.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz"
fetch "https://ftp.1000genomes.ebi.ac.uk/vol1/ftp/release/20130502/ALL.chr22.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz.tbi" "$SRC/1000g/ALL.chr22.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz.tbi"

expand_chroms() {
  local spec="${1:-22}"
  local out=()
  local part start end i
  IFS=',' read -ra parts <<< "$spec"
  for part in "${parts[@]}"; do
    if [[ "$part" == *-* ]]; then
      start="${part%-*}"
      end="${part#*-}"
      for ((i=start; i<=end; i++)); do out+=("$i"); done
    elif [[ -n "$part" ]]; then
      out+=("$part")
    fi
  done
  printf '%s\n' "${out[@]}"
}

ANCESTRY_OUTPUT_ROOT="$CACHE/ancestry_build_$(date +%Y%m%d_%H%M%S)_$$"
mkdir -p "$ANCESTRY_OUTPUT_ROOT/data/sources/1000g" "$ANCESTRY_OUTPUT_ROOT/data/packs"
ln -sf "$SRC/1000g/integrated_call_samples_v3.20130502.ALL.panel" \
  "$ANCESTRY_OUTPUT_ROOT/data/sources/1000g/integrated_call_samples_v3.20130502.ALL.panel"
ANCESTRY_BUILD_ARGS=(--root "$ANCESTRY_OUTPUT_ROOT" --chr22-test-only)
if [[ "${ZDNA_BUILD_OPTIMIZED_ANCESTRY:-0}" == "1" ]]; then
  AUTOSOME_CHROMS="${ZDNA_AUTOSOME_CHROMS:-1-22}"
  ANCESTRY_BUILD_ARGS=(
    --root "$ANCESTRY_OUTPUT_ROOT"
    --optimized-runtime
    --chromosomes "$AUTOSOME_CHROMS"
    --max-superpop-variants-per-vcf "${ZDNA_MAX_SUPERPOP_VARIANTS_PER_VCF:-18000}"
    --max-subpop-variants-per-vcf "${ZDNA_MAX_SUBPOP_VARIANTS_PER_VCF:-4500}"
    --min-superpop-delta "${ZDNA_MIN_SUPERPOP_DELTA:-0.14}"
    --marker-hash-mod "${ZDNA_MARKER_HASH_MOD:-1}"
  )
  echo "Building optimized ancestry runtime panel for chromosomes: $AUTOSOME_CHROMS"
  while IFS= read -r chrom; do
    [[ -z "$chrom" ]] && continue
    vcf="$SRC/1000g/ALL.chr${chrom}.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz"
    tbi="$vcf.tbi"
    fetch "https://ftp.1000genomes.ebi.ac.uk/vol1/ftp/release/20130502/ALL.chr${chrom}.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz" "$vcf"
    fetch "https://ftp.1000genomes.ebi.ac.uk/vol1/ftp/release/20130502/ALL.chr${chrom}.phase3_shapeit2_mvncall_integrated_v5b.20130502.genotypes.vcf.gz.tbi" "$tbi"
    ANCESTRY_BUILD_ARGS+=(--vcf "$vcf")
  done < <(expand_chroms "$AUTOSOME_CHROMS")
fi

if [[ -n "${ZDNA_GNOMAD_VCFS:-}" ]]; then
  IFS=',' read -ra gnomad_vcfs <<< "$ZDNA_GNOMAD_VCFS"
  for gvcf in "${gnomad_vcfs[@]}"; do
    [[ -z "$gvcf" ]] && continue
    ANCESTRY_BUILD_ARGS+=(
      --gnomad-vcf "$gvcf"
      --gnomad-populations "${ZDNA_GNOMAD_POPULATIONS:-asj,nfe,fin,mid,afr,amr,eas,sas}"
      --max-gnomad-variants-per-vcf "${ZDNA_MAX_GNOMAD_VARIANTS_PER_VCF:-60000}"
    )
  done
fi

if [[ -n "${ZDNA_GNOMAD_POP_VCFS:-}" ]]; then
  IFS=',' read -ra gnomad_pop_vcfs <<< "$ZDNA_GNOMAD_POP_VCFS"
  for spec in "${gnomad_pop_vcfs[@]}"; do
    [[ -z "$spec" ]] && continue
    ANCESTRY_BUILD_ARGS+=(
      --gnomad-pop-vcf "$spec"
      --max-gnomad-variants-per-vcf "${ZDNA_MAX_GNOMAD_VARIANTS_PER_VCF:-60000}"
    )
  done
fi

if [[ "${ZDNA_BUILD_SPECIALIST_JEWISH:-0}" == "1" ]]; then
  GNOMAD_RELEASE="${ZDNA_GNOMAD_TOGOVAR_RELEASE:-20241203}"
  GNOMAD_SPECIALIST_POPS="${ZDNA_GNOMAD_SPECIALIST_POPS:-asj,nfe,fin,afr,amr,eas,sas,oth}"
  GNOMAD_SPECIALIST_CHROMS="${ZDNA_GNOMAD_SPECIALIST_CHROMS:-1,2,22}"
  echo "Fetching compact ASJ + control specialist frequency starter pack: pops=$GNOMAD_SPECIALIST_POPS chroms=$GNOMAD_SPECIALIST_CHROMS"
  IFS=',' read -ra pop_parts <<< "$GNOMAD_SPECIALIST_POPS"
  IFS=',' read -ra chrom_parts <<< "$GNOMAD_SPECIALIST_CHROMS"
  for pop in "${pop_parts[@]}"; do
    pop="$(echo "$pop" | tr '[:upper:]' '[:lower:]' | xargs)"
    [[ -z "$pop" ]] && continue
    for chrom in "${chrom_parts[@]}"; do
      chrom="$(echo "$chrom" | xargs)"
      [[ -z "$chrom" ]] && continue
      pop_upper="$(echo "$pop" | tr '[:lower:]' '[:upper:]')"
      out="$SRC/gnomad/gnomad_exomes_v2.1.1.${pop}.chr${chrom}.grch37.vcf.gz"
      url="https://grch37.togovar.org/downloads/release/${GNOMAD_RELEASE}/grch37/frequency/vcf/gnomad_exomes/${pop}/gnomad_exomes_v2.1.1.${pop}.chr${chrom}.grch37.vcf.gz"
      if fetch_optional "$url" "$out"; then
        if [ -s "$out" ]; then
          ANCESTRY_BUILD_ARGS+=(
            --gnomad-pop-vcf "${pop_upper}=$out"
            --max-gnomad-variants-per-vcf "${ZDNA_MAX_GNOMAD_VARIANTS_PER_VCF:-60000}"
          )
        fi
      fi
    done
  done
fi

# Mitochondrial optional context
fetch_optional "https://mitomap.org/cgi-bin/polymorphisms.cgi?format=vcf" "$SRC/mitomap/mitomap_polymorphisms.vcf"
fetch_optional "https://mitomap.org/cgi-bin/disease.cgi?format=vcf" "$SRC/mitomap/mitomap_disease.vcf"

"$PYTHON_BIN" "$TOOL_DIR/build_health_core.py" --root "$ROOT"
"$PYTHON_BIN" "$TOOL_DIR/build_ancestry_reference.py" "${ANCESTRY_BUILD_ARGS[@]}"
LIVE_ANCESTRY_DB="$PACKS/ancestry_core.sqlite"
BUILT_ANCESTRY_DB="$ANCESTRY_OUTPUT_ROOT/data/packs/ancestry_core.sqlite"
"$PYTHON_BIN" - "$BUILT_ANCESTRY_DB" <<'PY'
import pathlib, sqlite3, sys
db = pathlib.Path(sys.argv[1])
if not db.exists():
    raise SystemExit(f"ancestry build did not create {db}")
con = sqlite3.connect(str(db))
cur = con.cursor()
cur.execute("select count(*) from ancestry_reference_markers")
rows = cur.fetchone()[0]
cur.execute("pragma wal_checkpoint(TRUNCATE)")
con.close()
print(f"validated ancestry temp pack: {rows:,} rows")
if rows < 1000:
    raise SystemExit("ancestry pack has too few rows; refusing to install")
PY
mkdir -p "$CACHE/ancestry_backups"
if [ -e "$LIVE_ANCESTRY_DB" ]; then
  cp -a "$LIVE_ANCESTRY_DB" "$CACHE/ancestry_backups/ancestry_core.sqlite.$(date +%Y%m%d_%H%M%S).bak"
fi
rm -f "$LIVE_ANCESTRY_DB-wal" "$LIVE_ANCESTRY_DB-shm"
install -m 0644 "$BUILT_ANCESTRY_DB" "$LIVE_ANCESTRY_DB"
chown --reference="$ROOT" "$LIVE_ANCESTRY_DB" 2>/dev/null || true
"$PYTHON_BIN" - "$LIVE_ANCESTRY_DB" <<'PY'
import pathlib, sqlite3, sys
db = pathlib.Path(sys.argv[1])
con = sqlite3.connect(str(db))
cur = con.cursor()
cur.execute("select count(*) from ancestry_reference_markers")
rows = cur.fetchone()[0]
cur.execute("pragma wal_checkpoint(TRUNCATE)")
con.close()
print(f"installed ancestry runtime pack: {rows:,} rows")
if rows < 1000:
    raise SystemExit("installed ancestry pack failed validation")
PY
if [[ "${ZDNA_BUILD_OPTIMIZED_ANCESTRY:-0}" == "1" ]]; then
  echo "Optimized ancestry build was atomic. Temp root kept for audit: $ANCESTRY_OUTPUT_ROOT"
else
  echo "Ancestry build was atomic. Temp root kept for audit: $ANCESTRY_OUTPUT_ROOT"
fi
"$PYTHON_BIN" "$TOOL_DIR/fetch_wikidata_lineage_subset.py" --root "$ROOT" --limit "$LINEAGE_LIMIT"

if [[ "${ZDNA_BUILD_ANCIENT_GRAVES:-0}" == "1" ]]; then
  if [[ -z "${ZDNA_AADR_PREFIX:-}" && -z "${ZDNA_ANCIENT_CSV:-}" ]]; then
    echo "ZDNA_BUILD_ANCIENT_GRAVES=1 requires ZDNA_AADR_PREFIX or ZDNA_ANCIENT_CSV" >&2
    exit 1
  fi
  ANCIENT_ARGS=(--root "$ROOT")
  if [[ -n "${ZDNA_AADR_PREFIX:-}" ]]; then
    ANCIENT_ARGS+=(--aadr-prefix "$ZDNA_AADR_PREFIX")
  fi
  if [[ -n "${ZDNA_ANCIENT_CSV:-}" ]]; then
    ANCIENT_ARGS+=(--csv "$ZDNA_ANCIENT_CSV")
  fi
  ANCIENT_ARGS+=(
    --max-samples "${ZDNA_ANCIENT_MAX_SAMPLES:-450}"
    --max-markers-per-sample "${ZDNA_ANCIENT_MAX_MARKERS_PER_SAMPLE:-2500}"
    --max-snps "${ZDNA_ANCIENT_MAX_SNPS:-120000}"
    --priority-terms "${ZDNA_ANCIENT_PRIORITY_TERMS:-ireland,irish,england,britain,british,scotland,wales,celtic,anglo-saxon,levant,israel,palestine,jordan,lebanon,syria,canaanite,egypt,nile,nubia,iran,persia,pakistan,punjab,india,indus,steppe,khazar,caucasus,scythian,sarmatian,turkic,mediterranean,rome,italy,iberia,spain,greece}"
    --max-samples-per-country "${ZDNA_ANCIENT_MAX_SAMPLES_PER_COUNTRY:-90}"
    --max-samples-per-culture "${ZDNA_ANCIENT_MAX_SAMPLES_PER_CULTURE:-70}"
    --min-autosomal-targets "${ZDNA_ANCIENT_MIN_AUTOSOMAL_TARGETS:-0}"
  )
  "$PYTHON_BIN" "$TOOL_DIR/build_ancient_grave_reference.py" "${ANCIENT_ARGS[@]}"
fi

echo "Done. Local packs should be in: $PACKS"
