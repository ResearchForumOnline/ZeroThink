-- Suggested app-side tables for ZeroThink DNA Lab.
-- Adapt to your existing database/migration system.

CREATE TABLE IF NOT EXISTS dna_uploads (
  id TEXT PRIMARY KEY,
  user_id TEXT,
  vendor_guess TEXT,
  genome_build_guess TEXT,
  marker_count INTEGER,
  quality_score REAL,
  quality_band TEXT,
  raw_file_hash TEXT,
  raw_storage_opt_in BOOLEAN DEFAULT FALSE,
  created_at TEXT,
  deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS dna_normalized_variants (
  upload_id TEXT NOT NULL,
  rsid TEXT NOT NULL,
  chrom TEXT,
  pos INTEGER,
  build TEXT,
  genotype TEXT,
  allele_a TEXT,
  allele_b TEXT,
  call_quality TEXT
);
CREATE INDEX IF NOT EXISTS idx_upload_rsid ON dna_normalized_variants(upload_id, rsid);

CREATE TABLE IF NOT EXISTS analysis_runs (
  id TEXT PRIMARY KEY,
  upload_id TEXT NOT NULL,
  analysis_type TEXT NOT NULL,
  status TEXT NOT NULL,
  engine_version TEXT,
  data_pack_versions TEXT,
  created_at TEXT,
  completed_at TEXT
);

CREATE TABLE IF NOT EXISTS evidence_cards (
  id TEXT PRIMARY KEY,
  analysis_run_id TEXT NOT NULL,
  title TEXT NOT NULL,
  claim_type TEXT NOT NULL,
  claim_label TEXT NOT NULL,
  evidence_level TEXT NOT NULL,
  confidence_score REAL NOT NULL,
  confidence_band TEXT NOT NULL,
  method TEXT NOT NULL,
  source_name TEXT,
  source_version TEXT,
  matched_marker_count INTEGER DEFAULT 0,
  explanation TEXT NOT NULL,
  limitations TEXT NOT NULL,
  next_steps TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS lineage_claims (
  id TEXT PRIMARY KEY,
  user_id TEXT,
  upload_id TEXT,
  family_side TEXT,
  claim_text TEXT,
  surnames_json TEXT,
  locations_json TEXT,
  date_ranges_json TEXT,
  conclusion_band TEXT,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS privacy_events (
  id TEXT PRIMARY KEY,
  user_id TEXT,
  upload_id TEXT,
  event_type TEXT NOT NULL,
  event_details TEXT,
  created_at TEXT NOT NULL
);
