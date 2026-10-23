<?php

/**
 * Security boundary for provider endpoints that receive bearer credentials.
 *
 * Provider origins are server-owned configuration. Request payloads must never
 * be able to choose the host that receives a saved or service API key.
 */
function zt_media_seedance_allowed_bases(): array {
    return [
        'https://ark.ap-southeast.bytepluses.com/api/v3',
    ];
}

function zt_media_seedance_base(array $request = []): string {
    foreach (['seedance_base', 'seedance_url', 'base_url', 'api_base'] as $request_key) {
        if (array_key_exists($request_key, $request) && trim((string) $request[$request_key]) !== '') {
            throw new InvalidArgumentException('Client-selected SeedDance endpoints are not accepted.');
        }
    }

    $base = trim((string) getenv('SEEDANCE_API_BASE'));
    if ($base === '' && function_exists('site_setting')) {
        $base = trim((string) site_setting('service_seedance_api_base', ''));
    }
    if ($base === '') {
        $base = zt_media_seedance_allowed_bases()[0];
    }
    $base = rtrim($base, '/');

    if (!in_array($base, zt_media_seedance_allowed_bases(), true)) {
        throw new RuntimeException('The server SeedDance endpoint is not on the approved provider allowlist.');
    }

    return $base;
}

function zt_media_seedance_tasks_url(array $request = []): string {
    return zt_media_seedance_base($request) . '/contents/generations/tasks';
}

function zt_media_seedance_task_url(array $request, string $task_id): string {
    $task_id = trim($task_id);
    if ($task_id === '' || strlen($task_id) > 256 || preg_match('/[\x00-\x1F\x7F]/', $task_id)) {
        throw new InvalidArgumentException('Invalid SeedDance task id.');
    }

    return zt_media_seedance_tasks_url($request) . '/' . rawurlencode($task_id);
}

