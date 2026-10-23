<?php
/** Public reference metadata only; no user DNA and no costly database scan per request. */
function zl_dna_reference_catalog() {
    $base = dirname(__DIR__) . '/data/packs/';
    $path = $base . 'ancestry_catalog.json';
    $database = $base . 'ancestry_core.sqlite';
    if (!is_readable($path) || !is_readable($database)) {
        return ['schema_version' => 1, 'integrity_status' => 'unavailable', 'panels' => [], 'limitations' => ['Reference inventory is unavailable.']];
    }
    $catalog = json_decode((string) file_get_contents($path), true);
    if (!is_array($catalog) || ($catalog['schema_version'] ?? null) !== 1) {
        return ['schema_version' => 1, 'integrity_status' => 'unavailable', 'panels' => []];
    }
    clearstatcache(true, $database);
    $same = (int) ($catalog['database_size_bytes'] ?? -1) === (int) filesize($database)
        && (int) ($catalog['database_mtime'] ?? -1) === (int) filemtime($database);
    $catalog['integrity_status'] = $same ? 'verified_snapshot' : 'stale_snapshot';
    if (!$same) {
        $catalog['limitations'][] = 'The active pack changed after this inventory; refresh the inventory before using these counts.';
    }
    return $catalog;
}
