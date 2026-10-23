Implement raw DNA parser, quality engine, and normalization.

Support:
- MyHeritage-style raw text
- 23andMe-style raw text
- Ancestry-style raw text
- generic rsid/chrom/position/genotype format

Requirements:
- Detect vendor style.
- Parse rsid, chromosome, position, genotype.
- Normalize rsID casing and whitespace.
- Normalize chromosome labels.
- Normalize genotype strings.
- Preserve unknown/no-call status.
- Count markers, duplicate rsIDs, no-calls, autosomal/X/Y/MT coverage.
- Guess genome build only when evidence exists.
- Add optional liftover layer using data/sources/ucsc/hg19ToHg38.over.chain.gz if installed.
- Do not silently transform uncertain positions without recording the transform.
- Never log raw genotype rows.
- Use synthetic fixture files only in tests.

Return code changes and tests.
