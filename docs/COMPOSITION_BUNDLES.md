# Pinned composition-reference bundle compatibility

The PHP composition loader accepts two exact reviewed manifests. The operator selects the installation directory with `ZEROTHINK_COMPOSITION_BUNDLE`; that environment setting cannot add a trusted manifest or enable an unsupported genome build.

Reference distributions are separate from this application checkout: see the
[research-datasets catalogue](https://github.com/ResearchForumOnline/research-datasets)
and [datasets-2026.10.23 release](https://github.com/ResearchForumOnline/research-datasets/releases/tag/datasets-2026.10.23).
The composition pack ID is `composition-grch37-2026.09.05`; the separate ancient
pack is `ancient-origins-grch37-2026.09.05`. Use the bundled provenance, licence,
installation and checksum records rather than treating a filename as verification.

| Profile | Manifest SHA-256 | Supported builds | Runtime |
| --- | --- | --- | --- |
| Original `bigsnpr-runtime-v1` | `94a2a30e47d94cb7e53fc47d02a4bfb6432ec3416f572427ad07bec114592b18` | GRCh37 and GRCh38 | Original pinned runtime, or an explicitly configured operator interpreter |
| `zerothink-composition-reference-v1`, public GRCh37 release | `5330a197b6b0203cbb985ebfd50bf68ae1fbcd8f1dc190933329d27db33d2747` | **GRCh37 only** | Explicit operator-installed Python required; no interpreter is bundled |

The public manifest requires exactly eight files: `model.json`, `worker.py`, `weights37.npz`, `marker_index37.sqlite`, `SOURCE_MANIFEST.json` and the three listed licence texts. Each file's exact byte length and SHA-256 are checked. The worker remains the reviewed original, SHA-256 `d859f5603c921a15eabcad4cecd5449460d55b02691bf556f5857252be5b079c`. The original manifest still requires all its original files, including both genome-build assets and runtime files; accepting the smaller profile does not relax the original profile.

Unknown manifests, incomplete or changed files, noncanonical paths, scoped paths escaping their installation root and unsupported builds fail closed. Manifest/model JSON reads and file counts are bounded. A matching schema name alone confers no trust. Validation is repeated rather than retaining a stale per-path success after file changes.

The unchanged model metadata contains a historical GRCh38 marker count. The public profile filters catalogue counts to the actual installed manifest's **GRCh37** support. A confirmed GRCh38 input is rejected before collecting model genotype rows; there is no alternate-build lookup or automatic liftover. The current model remains a research reference fit, without consumer ancestry calibration or identity/country/medical claims.

## Operator Python and resource boundary

On a Linux installation, the operator may configure an absolute path to a Python executable with the required NumPy and `quadprog` dependencies:

```sh
export ZEROTHINK_COMPOSITION_BUNDLE=/opt/zerothink-reference/composition-grch37-2026.09.05
export ZEROTHINK_COMPOSITION_PYTHON=/opt/zerothink-composition-env/bin/python
```

These are installation examples, not auto-run setup commands. The new scoped bundle requires the explicit interpreter setting. It does not search `PATH`, download a runtime, install dependencies, run inference during discovery or use a cloud provider. For the legacy profile, leaving the setting unset retains its original `runtime/bin/python` path. An explicit virtual-environment invocation path is preserved after checking its target, so resolving a Python symlink does not accidentally bypass that environment's `pyvenv.cfg`.

Use the `README.md` shipped with the reviewed reference release for its dependency versions, installation and provenance details. The application source alone does not include the reference archive or a prepared Python environment.

Both profiles require Linux, executable `/usr/bin/timeout` and `/usr/bin/prlimit`, PHP `proc_open` and PHP SQLite3. The worker command retains the 30-second wall timeout, two-second termination grace, 25-second CPU limit and 805,306,368-byte address-space limit. It remains a shell-free argument array, including paths containing spaces. Existing private workspace permissions, sanitized child environment, output-size/pipe limits, result validation and cleanup remain in place. Input validation/coverage and upstream consent boundaries are unchanged.

**Windows remains unavailable for composition execution.** Configuring a Windows Python executable does not bypass missing Linux process/resource controls. Source verification can still identify an installed pinned pack on Windows, and the catalogue distinguishes `source_installed=true` / `local_runtime_unavailable` from an absent or untrusted source. A source installation does not establish a working inference runtime.

## Focused validation

Run synthetic guard tests without a model installation:

```sh
php web/tests/composition_bundle.php
```

Optionally pass the public bundle directory and original manifest file for non-executing source validation:

```sh
php web/tests/composition_bundle.php /path/to/public-grch37-bundle /path/to/original-manifest.json
```

The recorded Windows run passed **46 checks**: actual new manifest and eight file hashes, scoped model metadata, original manifest/profile requirements, unknown pins, unsafe paths, malformed metadata, changed bytes, supported-build gates, preserved resource-command arguments, and source/runtime status separation. The original manifest was checked against its 1,477 recorded file entries, but the complete original runtime was not available on this workstation; no full legacy installation was executed. No worker, inference, private DNA, automatic download or expensive model computation ran. This is loader compatibility evidence, not numerical or scientific calibration.
