# ZeroThink DNA Lab v2 - Data Pack Plan

## Must-have first pack

1. ClinVar variant_summary + VCF GRCh37/GRCh38
2. GWAS Catalog associations
3. PharmGKB clinical annotations / clinical variants / guideline annotations
4. HPO + Mondo
5. UCSC hg19→hg38 chain
6. 1000 Genomes sample panel + chr22 test file
7. Wikidata filtered lineage graph
8. Optional AADR/ancient sample pack for grave/site affinity scans

This first pack lets you build:

- raw DNA quality report
- variant health research cards
- pharmacogenomics research cards without drug instructions
- broad ancestry signal proof of pipeline
- ancient grave/site affinity scan when ancient_graves.sqlite is installed
- royal/lineage clue scaffold
- source/version/evidence ledger

## Medium second pack

1. Full 1000 Genomes autosomes
2. HGDP via IGSR
3. gnomAD-lite or NCBI ALFA frequency context
4. yhaplo for Y-DNA haplogroup support
5. HaploGrep / MITOMAP for mtDNA support

## Advanced third pack

1. SGDP if licensing/reuse fits your app
2. AADR ancient DNA reference layer, labelled as ancient-reference similarity only
3. Open Targets gene/disease context
4. SNPedia only if noncommercial/share-alike restrictions are acceptable

## Runtime rule

Do not use full source files at runtime. Build reduced SQLite/DuckDB packs.

Runtime should query:

```txt
data/packs/health_core.sqlite
data/packs/ancestry_core.sqlite
data/packs/lineage_core.sqlite
data/packs/ancient_graves.sqlite
```

Source files stay in:

```txt
data/sources/
```

## Evidence rule

Every card must say:

- what was checked
- what matched
- what did not match
- how many markers were used
- source database and version
- confidence band
- limitations
- next step

## Ancient grave matching rule

Ancient grave matching is public ancient-sample/site affinity only. It can say:

- closest public ancient sample leads
- grave site / culture cluster leads
- overlap marker count
- similarity and confidence band
- modern country/site map context

It must not say:

- the user descends from this grave or person
- the user is a king, soldier, tribe, religion, culture, or named ancient identity
- the result is proof of royal, noble, dynastic, or tribal lineage

Build with:

```bash
ZDNA_BUILD_ANCIENT_GRAVES=1 ZDNA_AADR_PREFIX=/path/to/aadr_dataset_prefix bash scripts/update_dna_data_packs.sh
ZDNA_BUILD_ANCIENT_GRAVES=1 ZDNA_ANCIENT_CSV=/path/to/ancient_long_rows.csv bash scripts/update_dna_data_packs.sh
```
