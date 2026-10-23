-- ZeroThink DNA Lab local data-pack schema
-- Use this in SQLite or adapt to DuckDB.

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

CREATE TABLE IF NOT EXISTS clinvar_variants (
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
  source_version TEXT
);
CREATE INDEX IF NOT EXISTS idx_clinvar_rsid ON clinvar_variants(rsid);
CREATE INDEX IF NOT EXISTS idx_clinvar_gene ON clinvar_variants(gene_symbol);

CREATE TABLE IF NOT EXISTS gwas_associations (
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

CREATE TABLE IF NOT EXISTS pharmgkb_clinical_annotations (
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
CREATE INDEX IF NOT EXISTS idx_pgkb_rsid ON pharmgkb_clinical_annotations(rsid);
CREATE INDEX IF NOT EXISTS idx_pgkb_gene ON pharmgkb_clinical_annotations(gene);

CREATE TABLE IF NOT EXISTS pharmgkb_clinical_variants (
  variant TEXT,
  rsid TEXT,
  gene TEXT,
  chemical TEXT,
  evidence_level TEXT,
  source_version TEXT
);
CREATE INDEX IF NOT EXISTS idx_pgkb_cv_rsid ON pharmgkb_clinical_variants(rsid);

CREATE TABLE IF NOT EXISTS ancestry_reference_markers (
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

CREATE TABLE IF NOT EXISTS ancient_samples (
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
CREATE INDEX IF NOT EXISTS idx_ancient_samples_country ON ancient_samples(modern_country);
CREATE INDEX IF NOT EXISTS idx_ancient_samples_culture ON ancient_samples(culture);

CREATE TABLE IF NOT EXISTS ancient_sample_genotypes (
  sample_id TEXT NOT NULL,
  rsid TEXT NOT NULL,
  genotype TEXT NOT NULL,
  marker_weight REAL DEFAULT 1.0,
  source_version TEXT,
  PRIMARY KEY(sample_id, rsid)
);
CREATE INDEX IF NOT EXISTS idx_ancient_genotypes_rsid ON ancient_sample_genotypes(rsid);

CREATE TABLE IF NOT EXISTS lineage_public_people (
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

CREATE TABLE IF NOT EXISTS lineage_edges (
  child_id TEXT NOT NULL,
  parent_id TEXT NOT NULL,
  edge_type TEXT NOT NULL,
  source_url TEXT,
  confidence REAL DEFAULT 0.5,
  source_count INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_lineage_child ON lineage_edges(child_id);
CREATE INDEX IF NOT EXISTS idx_lineage_parent ON lineage_edges(parent_id);
