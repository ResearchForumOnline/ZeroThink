Implement health research cards and ancestry/genetic-affinity cards using local packs and existing ZDNA-MAP math.

Health engine:
- Use health_core.sqlite.
- Match variants by rsID and genotype where possible.
- ClinVar = clinical-significance evidence.
- GWAS Catalog = research-only trait association evidence.
- PharmGKB/CPIC = pharmacogenomics research/guideline context, no treatment instructions.
- Every health card says: Research support only. Not diagnosis or treatment advice.

Ancestry engine:
- Locate existing ZDNA-MAP/special math module.
- Preserve it and wrap behind typed interface.
- Use ancestry_core.sqlite where installed.
- If a reference panel is missing, say "reference panel missing" instead of guessing.
- Output broad/focused signals only as evidence language.
- Include matched marker count, confidence band, top competing signals, source versions.

Banned wording tests:
- "you are Jewish"
- "you are Chinese"
- "you are English"
- "you are Native American"
- "you have this disease"
- "you should take"
- "you should stop"

Return code changes and tests.
