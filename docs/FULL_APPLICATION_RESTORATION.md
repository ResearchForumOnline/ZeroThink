# ZeroThink source restoration and completeness audit, 23 October 2026

The earlier public repository contained ZeroThink Local 1.0: a new small Node
implementation of selected research concepts. It did not contain the original
PHP application or DNA Lab. The original application is now restored in `web/`.
The Node CLI stays compatible at the root; its previous README is retained in
`docs/LOCAL_ENGINE.md` and its historical provenance remains in `PROVENANCE.json`.

Restoration inputs were the original full application, the later July 2026 audited
runtime and frozen security fixes, followed in order by the September lab,
origins, discovery, ancestry, customer, country and final DNA v1.7.1 releases.
File hashes in `web/SOURCE_SHA256.json` identify this public source snapshot.

## Follow-up completeness audit

The final clean-snapshot exporter also caught a nested runtime directory,
`web/app/data`, that the initial root-only privacy filter missed. It contained
eight stored PDF artifact metadata records (title, generated filename, timestamps,
provider/model, size and numeric user ID), plus an empty neural-cache JSON and
directory protection files. No PDF bodies or genotype/credential fields were in
those eight records. They are runtime state, not application source, and are
removed from the current tree and fresh source archive. The guard now rejects
runtime/data directories at any depth. Original local source/data were preserved.
Removing a file from the current tree does not remove its older Git history or
previous release archives; those require a separate published-history cleanup.

The first restored release contained the main application and modern DNA engine,
but its extension/folder filters omitted surrounding authored research tools,
the root PWA manifest, a referenced Quantum Zero presentation video and an
extensionless reference-pack installer. Calling that snapshot the complete
project without qualifying those omissions was too broad. The follow-up release
restores reviewed source and assets and documents the remaining exclusions.

The audit compared the original `ZeroThink` application, audited/frozen July
releases, cumulative September DNA stages, deployment copies and older Desktop
archives. It compared module paths, hashes, callable inventories and static asset
references rather than equating folder/archive size with application source.
The earlier restored snapshot already contained 179 PHP files, 46,053 PHP lines,
18,265 Python lines and the 12,309-line original Python CLI. These are baseline
counts before the follow-up additions, not scientific validation metrics.

An older January TAR held 23,834 files and 107,673,600 bytes. After excluding
dependencies and private/runtime paths, its application code was 127 files and
344,388 bytes. It had no modern DNA Lab module. Its older web shells were not
substituted for the much larger audited July/September application.

The source map in the main README now leads with the original application.
The old Local 1.0 NOTICE and lengthy companion-first presentation have been
corrected so readers do not mistake that small Node companion for the full lab.

See [application coverage](SOURCE_COVERAGE_APP.md) and the
[DNA research-tool inventory](../web/docs/DNA_RESEARCH_TOOLS.md) for restored file
provenance, source hashes, modifications needed for portable configuration and
deliberate exclusions. Dependencies, downloaded datasets and model bundles
remain separate even when they account for most of an old installation's size.

Publication excludes original database exports, all user/account records, private
DNA and medical files, local credentials/configuration, uploaded files, runtime
vaults/memory, logs, private source investigations, vendor trees, downloaded
datasets, model binaries and one-off unsafe maintenance entrypoints. Local source
folders and production data were preserved. `web/sql/application-schema.sql`
contains schema definitions only, not a database dump.

Publication improvements include a database-free loopback DNA UI and offline DNA
CLI, explicit external/offload blocking in those modes, environment configuration
examples, removal of historical owner-email/user-ID privilege shortcuts, honest
missing-pack report wording, current parser compatibility, tests and a source-only
privacy guard. The full hosted account/payment application is included as source;
it was not deployed or claimed to have passed a public hosted-service audit.
