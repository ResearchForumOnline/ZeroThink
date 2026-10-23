Add final safety and regression tests.

Required tests:
- parser handles MyHeritage-style synthetic fixture
- parser handles 23andMe-style synthetic fixture
- parser handles Ancestry-style synthetic fixture
- raw quality counts duplicates/no-calls/coverage
- evidence card schema requires confidence and limitations
- health cards always include research-only warning
- ancestry wording avoids identity claims
- royal wording avoids proof claims
- raw genotype rows are not logged
- DNA Agent input excludes raw genotype rows
- data-pack manifest records sha256 and row count
- UI disables report buttons until file parsed

Run all tests and return exact commands/results.
