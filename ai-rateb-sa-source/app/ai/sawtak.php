<?php
declare(strict_types=1);

function rateb_sawtak_configured(): bool
{
    return trim((string) getenv('SAWTAK_API_KEY')) !== '';
}

/** @param array<string,mixed> $page @return array{voices:array<int,array<string,mixed>>,next:?string,more:bool} */
function rateb_sawtak_page_voices(array $page): array
{
    $voices = [];
    foreach ($page['data'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $voice = rateb_sawtak_voice($row);
        if ($voice['id'] !== '') {
            $voices[] = $voice;
        }
    }
    $more = ($page['has_more'] ?? false) === true;
    $cursor = isset($page['next_cursor']) && is_string($page['next_cursor']) && $page['next_cursor'] !== ''
        ? $page['next_cursor']
        : null;
    return ['voices' => $voices, 'next' => $more ? $cursor : null, 'more' => $more];
}

/** @param array<int,array<string,mixed>> $pages */
function rateb_sawtak_merge(array $pages): array
{
    $byId = [];
    foreach ($pages as $page) {
        foreach ($page as $voice) {
            $id = (string) ($voice['id'] ?? '');
            if ($id === '' || isset($byId[$id])) {
                continue;
            }
            $byId[$id] = $voice;
        }
    }
    return array_values($byId);
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function rateb_sawtak_voice(array $row): array
{
    $labels = is_array($row['labels'] ?? null) ? $row['labels'] : [];
    $sharing = is_array($row['sharing'] ?? null) ? $row['sharing'] : [];
    return [
        'id' => is_string($row['id'] ?? null) ? $row['id'] : '',
        'name' => is_string($row['name'] ?? null) ? $row['name'] : '',
        'status' => is_string($row['status'] ?? null) ? $row['status'] : '',
        'dialect' => is_string($labels['dialect'] ?? null) ? $labels['dialect'] : null,
        'gender' => is_string($labels['gender'] ?? null) ? $labels['gender'] : null,
        'age' => is_string($labels['age'] ?? null) ? $labels['age'] : null,
        'use_case' => is_string($labels['use_case'] ?? null) ? $labels['use_case'] : null,
        'preview_url' => is_string($row['preview_url'] ?? null) ? $row['preview_url'] : null,
        'sharing_status' => is_string($sharing['status'] ?? null) ? $sharing['status'] : null,
        'liked_by_count' => isset($sharing['liked_by_count']) ? (int) $sharing['liked_by_count'] : null,
        'description' => is_string($row['description'] ?? null) ? $row['description'] : null,
        'preview_text' => is_string($row['preview_text'] ?? null) ? $row['preview_text'] : null,
        'continuation_status' => is_string($row['continuation_status'] ?? null) ? $row['continuation_status'] : null,
        'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
    ];
}

/** @param callable(string):array<string,mixed> $fetch @return array{ok:bool,code:string,voices:array<int,array<string,mixed>>,pages:int,complete:bool} */
function rateb_sawtak_collect(callable $fetch): array
{
    $pages = [];
    $cursor = null;
    $complete = true;
    for ($i = 0; $i < 100; $i++) {
        $query = 'sharing_status=public&limit=100&sort=newest';
        if ($cursor !== null) {
            $query .= '&after=' . rawurlencode($cursor);
        }
        $page = $fetch($query);
        if (!is_array($page) || (($page['object'] ?? '') !== 'list' && !isset($page['data']))) {
            return ['ok' => false, 'code' => 'catalog', 'voices' => [], 'pages' => $i, 'complete' => false];
        }
        $parsed = rateb_sawtak_page_voices($page);
        $pages[] = $parsed['voices'];
        if (!$parsed['more']) {
            break;
        }
        if ($parsed['next'] === null || $parsed['next'] === $cursor) {
            $complete = false;
            break;
        }
        $cursor = $parsed['next'];
    }
    return ['ok' => true, 'code' => '', 'voices' => rateb_sawtak_merge($pages), 'pages' => count($pages), 'complete' => $complete];
}

function rateb_sawtak_cache_path(): string
{
    $root = defined('MEDIA_ROOT') ? MEDIA_ROOT : sys_get_temp_dir();
    return rtrim($root, '/\\') . '/sawtak-voices.json';
}

function rateb_sawtak_public_catalog(): array
{
    $path = __DIR__ . '/sawtak-public.json';
    if (!is_file($path)) {
        return [];
    }
    $rows = json_decode((string) file_get_contents($path), true);
    return is_array($rows) ? $rows : [];
}

/** @return array{ok:bool,code:string,voices:array<int,array<string,mixed>>,pages:int,complete:bool} */
function rateb_sawtak_catalog(bool $refresh = false): array
{
    if (!rateb_sawtak_configured()) {
        $voices = rateb_sawtak_public_catalog();
        return [
            'ok' => $voices !== [],
            'code' => $voices === [] ? 'not_configured' : '',
            'voices' => $voices,
            'pages' => 1,
            'complete' => $voices !== [],
        ];
    }
    $path = rateb_sawtak_cache_path();
    if (!$refresh && is_file($path) && filemtime($path) > time() - 3600) {
        $cached = json_decode((string) file_get_contents($path), true);
        if (is_array($cached) && isset($cached['voices']) && is_array($cached['voices'])) {
            return [
                'ok' => true,
                'code' => '',
                'voices' => $cached['voices'],
                'pages' => (int) ($cached['pages'] ?? 0),
                'complete' => ($cached['complete'] ?? false) === true,
            ];
        }
    }
    $key = trim((string) getenv('SAWTAK_API_KEY'));
    $result = rateb_sawtak_collect(static function (string $query) use ($key): array {
        $handle = curl_init('https://api.sawtakarabi.ai/v1/voices?' . $query);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Accept: application/json'],
        ]);
        $body = curl_exec($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($code === 401 || $code === 403) {
            throw new RuntimeException('auth');
        }
        if (!is_string($body) || $code < 200 || $code >= 300) {
            throw new RuntimeException('catalog');
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    });
    if ($result['ok']) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        file_put_contents($path, json_encode($result, JSON_UNESCAPED_UNICODE));
    }
    return $result;
}

function rateb_sawtak_find(string $id): ?array
{
    if ($id === '') {
        return null;
    }
    $catalog = rateb_sawtak_catalog();
    if (!$catalog['ok']) {
        return null;
    }
    foreach ($catalog['voices'] as $voice) {
        if ((string) $voice['id'] === $id && (string) ($voice['sharing_status'] ?? 'public') === 'public') {
            return $voice;
        }
    }
    return null;
}
