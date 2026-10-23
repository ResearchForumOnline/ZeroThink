# Third-party and source notices

The repository Apache-2.0 grant covers the author's original project code. It does
not replace a third-party license or license datasets, APIs, provider models,
university marks or dependencies on somebody else's behalf.

- The reference-model `composition_worker.py` and
  `evaluate_bigsnpr_composition.py` state GPL-3.0-or-later and cite
  Florian Prive (2022), DOI `10.1093/bioinformatics/btac348`. Its `near_pd` adaptation
  credits Martin Maechler (2007-2019) and Jens Oehlschlaegel (2007), originally
  GPL-2.0-or-later. These files are distributed under GPL-3.0-or-later; see
  `scripts/reference-models/COPYING.GPL-3.0`. The restored
  `bigsnpr_additional_checks.py` and `validate_composition_bundle.py` import this
  GPL evaluator and are also marked GPL-3.0-or-later. Respect the GPL terms for
  these combined evaluation programs and any derivatives. The application invokes
  the composition worker as a separate Python process. Neither that arrangement
  nor this repository's general license relicenses the GPL tools as Apache-2.0.
- bigsnpr/Matrix implementation source is not vendored here. The source builder
  records its upstream revision, aggregate-data license and reference DOI.
  Separately downloaded frequency/loading datasets retain their source terms.
- GrafAnc and its model files retain their upstream terms. Model binaries and
  downloaded datasets are not in Git.
- World-atlas map files include the retained MIT license under `assets/maps`;
  map-source attributions are retained with relevant assets. Other geographic
  source material retains the source's stated terms.
- Composer dependencies, optional Python packages, Font Awesome and external API
  providers retain their licenses and service terms. Install dependencies yourself
  rather than copying a previous deployment's vendor directory.
- University affiliation or endorsement is not granted by this source release.
  The university logo from an older deployment is excluded from this package.

Synthetic JSON/raw text fixtures exercise software contracts. They are not
participant DNA, personal results, independent consumer validation or a reference
database. Deployment logs, screenshots, private reports, account records and
downloaded reference packs are excluded.
