<?php
declare(strict_types=1);

namespace RatebEndpointTest;

/*
 * Test doubles for running app/ai/image.php and app/ai/voice.php offline. The endpoint
 * source is evaluated in this namespace, so these functions shadow the session, storage,
 * and provider HTTP calls only. Credit, usage, and image-job functions stay the real
 * global implementations from app/plans.php.
 */

const CURLOPT_POST = 47;
const CURLOPT_RETURNTRANSFER = 19913;
const CURLOPT_HTTPHEADER = 10023;
const CURLOPT_POSTFIELDS = 10015;
const CURLOPT_TIMEOUT = 13;
const CURLOPT_CONNECTTIMEOUT = 78;
const CURLOPT_CUSTOMREQUEST = 10036;
const CURLOPT_FOLLOWLOCATION = 52;
const CURLOPT_MAXREDIRS = 68;
const CURLOPT_PROTOCOLS = 181;
const CURLPROTO_HTTPS = 2;
const CURLINFO_HTTP_CODE = 2097154;
const CURLINFO_EFFECTIVE_URL = 1048577;

final class Harness
{
    public static \FakeLedger $db;
    /** @var array<string,mixed> */
    public static array $scenario = [];
    /** @var array<int,array<string,mixed>> */
    public static array $calls = [];
    /** @var array<int,array<string,mixed>> */
    public static array $unexpected = [];
    /** @var array<int,bool> */
    private static array $used = [];
    public static int $stores = 0;

    public static function userId(): int
    {
        return (int) (self::$scenario['session']['user_id'] ?? 0);
    }

    /** @return array{http:int,body:string,effective:string}|null */
    public static function respond(string $method, string $url): ?array
    {
        foreach (self::$scenario['provider'] ?? [] as $index => $reply) {
            if (isset(self::$used[$index]) || ($reply['method'] ?? 'GET') !== $method || !str_starts_with($url, (string) $reply['url'])) {
                continue;
            }
            self::$used[$index] = true;
            $body = isset($reply['body_b64']) ? (string) base64_decode((string) $reply['body_b64'], true) : (string) ($reply['body'] ?? '');
            return ['http' => (int) $reply['http'], 'body' => $body, 'effective' => (string) ($reply['effective'] ?? $url)];
        }
        return null;
    }
}

final class CurlHandle
{
    public string $method = 'GET';
    public int $http = 0;
    public string $effective = '';

    public function __construct(public string $url)
    {
        $this->effective = $url;
    }
}

function db(): \PDO
{
    return Harness::$db;
}

function require_login(): void
{
}

function check_csrf(): void
{
}

function ui_language(): string
{
    return 'en';
}

function session_write_close(): bool
{
    return true;
}

function header(string $header, bool $replace = true, int $code = 0): void
{
}

function ai_config(): array
{
    $extra = Harness::$scenario['ai'] ?? [];
    return array_merge([
        'horde_api_key' => 'test-horde-key',
        'sawtak_api_key' => 'test-sawtak-key',
        'image_provider' => 'horde',
        'cloudflare_account_id' => '',
        'cloudflare_api_token' => '',
        'image_model' => '@cf/black-forest-labs/flux-1-schnell',
    ], is_array($extra) ? $extra : []);
}

function media_owned_campaign(int $campaignId): array
{
    $campaign = Harness::$scenario['campaign'];
    if ((int) $campaign['id'] !== $campaignId) {
        throw new \RuntimeException('test campaign mismatch');
    }
    return $campaign;
}

function media_store_bytes(mixed ...$args): array
{
    if (!empty(Harness::$scenario['storage_fails'])) {
        throw new \RuntimeException('test storage failure');
    }
    Harness::$stores++;
    return ['id' => Harness::$stores];
}

function rateb_output_language(\PDO $pdo, array $campaign): array
{
    return ['speech' => (string) (Harness::$scenario['speech'] ?? 'ar'), 'text' => 'ar'];
}

function rateb_voice_script(\PDO $pdo, int $campaignId, string $speech): string
{
    return (string) (Harness::$scenario['voice_script'] ?? '');
}

function rateb_sawtak_find(string $id): ?array
{
    return $id === 'test-voice' ? ['id' => 'test-voice', 'status' => 'ready'] : null;
}

function rateb_watermark_required(\PDO $pdo, int $userId): bool
{
    return false;
}

function curl_init(?string $url = null): CurlHandle
{
    return new CurlHandle((string) $url);
}

function curl_setopt(CurlHandle $handle, int $option, mixed $value): bool
{
    if (($option === CURLOPT_POST && $value) || ($option === CURLOPT_CUSTOMREQUEST && $value === 'POST')) {
        $handle->method = 'POST';
    }
    return true;
}

function curl_setopt_array(CurlHandle $handle, array $options): bool
{
    foreach ($options as $option => $value) {
        curl_setopt($handle, (int) $option, $value);
    }
    return true;
}

function curl_exec(CurlHandle $handle): string|false
{
    $userId = Harness::userId();
    $call = ['method' => $handle->method, 'url' => $handle->url, 'balance' => Harness::$db->balances[$userId] ?? null];
    $reply = Harness::respond($handle->method, $handle->url);
    if ($reply === null) {
        Harness::$unexpected[] = $call;
        return false;
    }
    Harness::$calls[] = $call;
    $handle->http = $reply['http'];
    $handle->effective = $reply['effective'];
    return $reply['body'];
}

function curl_getinfo(CurlHandle $handle, int $option): mixed
{
    return match ($option) {
        CURLINFO_HTTP_CODE => $handle->http,
        CURLINFO_EFFECTIVE_URL => $handle->effective,
        default => null,
    };
}

function curl_close(CurlHandle $handle): void
{
}
