<?php
declare(strict_types=1);

require_once __DIR__ . '/../plans.php';

function rateb_package_media_path(string $root, int $campaignId, string $storedName): ?string
{
    if ($campaignId < 1 || preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif|mp4|webm|mp3|wav|ogg|m4a)$/', $storedName) !== 1) {
        return null;
    }
    $base = realpath($root);
    $dir = $base === false ? false : realpath($base . DIRECTORY_SEPARATOR . $campaignId);
    $full = $dir === false ? false : realpath($dir . DIRECTORY_SEPARATOR . $storedName);
    if ($base === false || $dir === false || $full === false || !is_file($full)) {
        return null;
    }
    if (!str_starts_with($dir, $base . DIRECTORY_SEPARATOR) || dirname($full) !== $dir) {
        return null;
    }
    return $full;
}

/** @return array<string,string> */
function rateb_package_labels(string $language): array
{
    return $language === 'en'
        ? [
            'positioning' => 'Positioning',
            'core_message' => 'Core message',
            'audience' => 'Audience',
            'channels' => 'Suggested channels',
            'tone' => 'Tone',
            'creative_direction' => 'Creative direction',
            'call_to_action' => 'Call to action',
            'headline' => 'Headline',
            'ad_copy' => 'Ad copy',
            'short_ad' => 'Short ad',
            'social_posts' => 'Social posts',
            'whatsapp' => 'WhatsApp message',
            'product_description' => 'Product description',
        ]
        : [
            'positioning' => 'التموضع',
            'core_message' => 'الرسالة الأساسية',
            'audience' => 'الجمهور',
            'channels' => 'القنوات المقترحة',
            'tone' => 'النبرة',
            'creative_direction' => 'الاتجاه الإبداعي',
            'call_to_action' => 'الدعوة إلى الإجراء',
            'headline' => 'العنوان',
            'ad_copy' => 'نص الإعلان',
            'short_ad' => 'إعلان قصير',
            'social_posts' => 'منشورات التواصل',
            'whatsapp' => 'رسالة واتساب',
            'product_description' => 'وصف المنتج',
        ];
}

function rateb_package_text(mixed $value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return trim($value);
        }
        $value = $decoded;
    }
    if (!is_array($value)) {
        return is_scalar($value) ? trim((string) $value) : '';
    }
    if (isset($value['ar'], $value['en']) && is_string($value['ar']) && is_string($value['en'])) {
        return trim(implode("\n\n", array_filter([trim($value['ar']), trim($value['en'])], static fn (string $part): bool => $part !== '')));
    }
    $lines = [];
    foreach ($value as $key => $item) {
        $text = rateb_package_text($item);
        if ($text === '') {
            continue;
        }
        $lines[] = is_string($key) ? $key . ': ' . $text : $text;
    }
    return implode("\n\n", $lines);
}

/**
 * @param array<int,array<string,mixed>> $outputs newest first
 * @return array{strategy:string,copy:string}
 */
function rateb_package_documents(array $outputs, string $language): array
{
    $labels = rateb_package_labels($language);
    $strategyRow = null;
    $copy = [];
    foreach ($outputs as $row) {
        $type = (string) ($row['output_type'] ?? '');
        if ($type === 'strategy') {
            $strategyRow ??= $row;
        } elseif (in_array($type, rateb_copy_types(), true) && !isset($copy[$type])) {
            $copy[$type] = $row;
        }
    }
    $strategy = '';
    if ($strategyRow !== null && (string) ($strategyRow['approval_status'] ?? '') === 'approved') {
        $document = rateb_strategy_document((string) ($strategyRow['content'] ?? '')) ?? [];
        $sections = [];
        foreach (rateb_strategy_keys() as $key) {
            $text = rateb_package_text($document[$key] ?? '');
            if ($text !== '') {
                $sections[] = $labels[$key] . "\n" . $text;
            }
        }
        $strategy = implode("\n\n", $sections);
    }
    $copySections = [];
    foreach (rateb_copy_types() as $type) {
        if (!isset($copy[$type]) || (string) ($copy[$type]['approval_status'] ?? '') !== 'approved') {
            continue;
        }
        $text = rateb_package_text((string) ($copy[$type]['content'] ?? ''));
        if ($text !== '') {
            $copySections[] = ($labels[$type] ?? $type) . "\n" . $text;
        }
    }
    return ['strategy' => $strategy, 'copy' => implode("\n\n", $copySections)];
}

function rateb_package_max_bytes(): int
{
    return 1073741824;
}

/**
 * @param array<string,mixed> $campaign
 * @param array<int,array<string,mixed>> $outputs newest first
 * @param array<int,array<string,mixed>> $media
 * @return array{entries:array<int,array<string,mixed>>,manifest:array<string,mixed>}
 */
function rateb_package_build(array $campaign, int $viewerId, array $outputs, array $media, int $approvedVideoMediaId, string $language, string $root, int $now, ?int $maxBytes = null): array
{
    $language = $language === 'en' ? 'en' : 'ar';
    $campaignId = (int) ($campaign['id'] ?? 0);
    $userId = (int) ($campaign['user_id'] ?? 0);
    if ($campaignId < 1 || $userId < 1 || $viewerId !== $userId) {
        throw new RuntimeException('package_forbidden');
    }
    $budget = $maxBytes ?? rateb_package_max_bytes();
    $entries = [];
    $files = [];
    $skipped = [];
    $documents = rateb_package_documents($outputs, $language);
    foreach (['strategy' => 'strategy.txt', 'copy' => 'copy.txt'] as $key => $name) {
        if ($documents[$key] === '') {
            continue;
        }
        $data = $documents[$key] . "\n";
        $budget -= strlen($data);
        $entries[] = ['name' => $name, 'data' => $data];
        $files[] = ['path' => $name, 'kind' => $key, 'source' => 'ai', 'approval' => 'approved', 'bytes' => strlen($data)];
    }
    $counters = ['image' => 0, 'audio' => 0, 'video' => 0];
    $folders = ['image' => 'images', 'audio' => 'audio', 'video' => 'video'];
    foreach ($media as $row) {
        $kind = (string) ($row['kind'] ?? '');
        if (!isset($folders[$kind]) || (int) ($row['campaign_id'] ?? 0) !== $campaignId || (int) ($row['user_id'] ?? 0) !== $userId) {
            continue;
        }
        $source = (string) ($row['source'] ?? '') === 'ai' ? 'ai' : 'upload';
        if ($kind === 'video' && $source === 'ai' && (int) ($row['id'] ?? 0) !== $approvedVideoMediaId) {
            continue;
        }
        $storedName = (string) ($row['stored_name'] ?? '');
        $path = rateb_package_media_path($root, $campaignId, $storedName);
        if ($path === null) {
            $skipped[] = ['media_id' => (int) ($row['id'] ?? 0), 'kind' => $kind, 'reason' => 'missing'];
            continue;
        }
        $bytes = filesize($path);
        if ($bytes === false || $bytes > $budget) {
            $skipped[] = ['media_id' => (int) ($row['id'] ?? 0), 'kind' => $kind, 'reason' => 'too_large'];
            continue;
        }
        $budget -= $bytes;
        $counters[$kind]++;
        $extension = (string) pathinfo($storedName, PATHINFO_EXTENSION);
        $name = sprintf('%s/%02d-%s.%s', $folders[$kind], $counters[$kind], $source, $extension);
        $entries[] = ['name' => $name, 'path' => $path];
        $approval = match (true) {
            $kind === 'video' && $source === 'ai' => 'approved',
            $source === 'upload' => 'uploaded',
            default => 'no_approval_step',
        };
        $files[] = ['path' => $name, 'kind' => $kind, 'source' => $source, 'approval' => $approval, 'bytes' => (int) $bytes];
    }
    $manifest = [
        'campaign_id' => $campaignId,
        'title' => (string) ($campaign['title'] ?? ''),
        'language' => $language,
        'created_at' => gmdate('c', $now),
        'files' => $files,
        'skipped' => $skipped,
    ];
    $entries[] = ['name' => 'manifest.json', 'data' => (string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n"];
    return ['entries' => $entries, 'manifest' => $manifest];
}

/**
 * @param array<int,array<string,mixed>> $entries
 * @return array<int,array<string,mixed>>
 */
function rateb_zip_prepare(array $entries): array
{
    $prepared = [];
    $offset = 0;
    foreach ($entries as $entry) {
        $name = (string) $entry['name'];
        if (isset($entry['path'])) {
            $size = @filesize((string) $entry['path']);
            $crc = @hash_file('crc32b', (string) $entry['path']);
        } else {
            $size = strlen((string) $entry['data']);
            $crc = hash('crc32b', (string) $entry['data']);
        }
        if ($size === false || $crc === false || $size > 0xFFFFFFFF) {
            throw new RuntimeException('package_file');
        }
        $prepared[] = $entry + ['size' => (int) $size, 'crc' => (int) hexdec($crc), 'offset' => $offset];
        $offset += 30 + strlen($name) + (int) $size;
        if ($offset > 0xFFFFFFFF) {
            throw new RuntimeException('package_size');
        }
    }
    return $prepared;
}

/** @param array<int,array<string,mixed>> $prepared */
function rateb_zip_length(array $prepared): int
{
    $length = 22;
    foreach ($prepared as $entry) {
        $length += 76 + 2 * strlen((string) $entry['name']) + (int) $entry['size'];
    }
    return $length;
}

/**
 * Stored (uncompressed) ZIP so media is streamed without loading it into memory.
 *
 * @param array<int,array<string,mixed>> $prepared
 * @param callable(string):void $write
 */
function rateb_zip_stream(array $prepared, callable $write, int $now): void
{
    $time = getdate($now);
    $dosTime = ($time['hours'] << 11) | ($time['minutes'] << 5) | intdiv($time['seconds'], 2);
    $dosDate = ((max(1980, $time['year']) - 1980) << 9) | ($time['mon'] << 5) | $time['mday'];
    $central = '';
    foreach ($prepared as $entry) {
        $name = (string) $entry['name'];
        $common = pack('vvvvvVVVv', 20, 0x0800, 0, $dosTime, $dosDate, $entry['crc'], $entry['size'], $entry['size'], strlen($name));
        $write("PK\x03\x04" . $common . pack('v', 0) . $name);
        if (isset($entry['path'])) {
            $handle = @fopen((string) $entry['path'], 'rb');
            if ($handle === false) {
                throw new RuntimeException('package_file');
            }
            $remaining = (int) $entry['size'];
            $crc = hash_init('crc32b');
            while ($remaining > 0) {
                $chunk = fread($handle, min(1048576, $remaining));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $remaining -= strlen($chunk);
                hash_update($crc, $chunk);
                $write($chunk);
            }
            $extra = fread($handle, 1);
            fclose($handle);
            // The header already promised this size and checksum, so a changed file must abort the archive.
            if ($remaining !== 0 || ($extra !== false && $extra !== '') || (int) hexdec(hash_final($crc)) !== (int) $entry['crc']) {
                throw new RuntimeException('package_changed');
            }
        } else {
            $write((string) $entry['data']);
        }
        $central .= "PK\x01\x02" . pack('v', 20) . $common . pack('vvvvVV', 0, 0, 0, 0, 0, $entry['offset']) . $name;
    }
    $end = $prepared === [] ? 0 : (int) $prepared[count($prepared) - 1]['offset'] + 30 + strlen((string) $prepared[count($prepared) - 1]['name']) + (int) $prepared[count($prepared) - 1]['size'];
    $write($central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($prepared), count($prepared), strlen($central), $end, 0));
}
