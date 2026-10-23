# ZeroThink DNA Lab v2 - Database and Library Research Notes

## Health / medical research sources

### ClinVar
Use for health evidence cards. Store by rsID, gene, condition, clinical significance, review status, assembly/build.

Downloads:
- variant_summary.txt.gz
- ClinVar GRCh37 VCF
- ClinVar GRCh38 VCF

### GWAS Catalog
Use as research-only trait associations. Do not treat GWAS associations as diagnosis.

Download:
- full associations TSV

### PharmGKB / CPIC / ClinPGx
Use for pharmacogenomics evidence context. Do not output medication action instructions.

Downloads:
- clinicalAnnotations.zip
- clinicalAnnotations_LOE1-2.zip
- clinicalVariants.zip
- guidelineAnnotations.json.zip
- genes.zip
- variants.zip
- drugs.zip

### HPO + Mondo
Use for naming and normalization, not direct variant interpretation.

Downloads:
- hp.obo
- mondo.obo

### Optional health context
- Open Targets Platform: gene/disease/target evidence context.
- SNPedia: optional only; noncommercial/share-alike license restrictions; disable by default if building commercial service.

## Ancestry / ethnicity signal sources

### 1000 Genomes
Use as first real reference panel. It has five super-populations and 26 more specific populations. Start with broad super-pop frequencies, then build subpopulation panels from sample genotypes.

Starter files:
- integrated_call_samples_v3.20130502.ALL.panel
- chromosome 22 VCF test file

Production:
- autosomes chr1-22
- prune markers
- calibrate ZDNA-MAP scores
- store reduced marker frequencies in ancestry_core.sqlite

### HGDP via IGSR
Use as expanded reference for population diversity beyond 1000G. Builder-only; not runtime remote.

### SGDP
Use only if reuse terms fit. Strong diversity reference, but heavier and more complex.

### gnomAD / NCBI ALFA
Use as allele frequency context. Do not use frequency databases alone as identity classifiers.

## Ancient grave / public ancient DNA sources

### AADR / public ancient genotypes
Use as the first serious ancient-sample source. Build a reduced runtime pack from public EIGENSTRAT-style sample metadata and genotypes.

Runtime table:
- ancient_samples: public label, site, modern country, region, culture, date, sex, lat/lon, source version
- ancient_sample_genotypes: sample_id, rsID, normalized genotype, marker weight

Output rule:
- Say "closest public ancient sample/site affinity lead."
- Show marker overlap, confidence, site/culture/date, and map point.
- Do not say descent from a grave, king, soldier, tribe, religion, culture, or named ancient person.

### AmtDB / ancient mtDNA context
Use as an optional companion for ancient mitochondrial/site/culture context. It can support maternal-line research when enough mtDNA markers exist, but it does not prove whole ancestry or identity.

## Haplogroup sources

### yhaplo
Use for Y-DNA haplogroup clues if enough Y-chromosome SNPs exist in the raw file.

### HaploGrep / PhyloTree
Use for mtDNA haplogroup clues if enough mitochondrial markers exist.

### MITOMAP
Use for mitochondrial variant context.

## Royal / lineage sources

### GEDCOM
User-provided GEDCOM is the main evidence path for family-tree claims.

### Wikidata
Use a local filtered public graph of people, parent relationships, dates, titles, and QIDs. It is not proof by itself. It is a route finder and evidence ledger source.

## Libraries / tools

### Python
- pandas / numpy / scipy / scikit-learn for scoring, calibration, tables
- sqlite3 / DuckDB for local packs
- cyvcf2 / pysam for VCF and Tabix
- obonet for HPO/Mondo
- networkx for lineage graph paths
- rapidfuzz for fuzzy name/place matching

### CLI tools worth allowing
- bcftools for VCF filtering/querying
- tabix/bgzip for indexed genomic files
- plink/plink2 for reference panel conversion, QC, LD pruning, PCA/admixture-like prep
- CrossMap or UCSC liftOver for build conversion

## Main build rule

Make downloadable sources builder-only. Runtime should query reduced local SQLite/DuckDB packs.
