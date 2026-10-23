# Full ZeroThink restoration, 23 October 2026

The earlier public repository contained ZeroThink Local 1.0: a new small Node
implementation of selected research concepts. It did not contain the original
PHP application or DNA Lab. The original application is now restored in `web/`.
The Node CLI stays compatible at the root; its previous README is retained in
`docs/LOCAL_ENGINE.md` and its historical provenance remains in `PROVENANCE.json`.

Restoration inputs were the original full application, the later July 2026 audited
runtime and frozen security fixes, followed in order by the September lab,
origins, discovery, ancestry, customer, country and final DNA v1.7.1 releases.
File hashes in `web/SOURCE_SHA256.json` identify this public source snapshot.

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
