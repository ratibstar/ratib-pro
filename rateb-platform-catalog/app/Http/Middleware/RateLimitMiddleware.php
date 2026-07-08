<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Middleware;

use Rateb\PlatformCatalog\Application\Support\PlatformIdentityResolver;
use Rateb\PlatformCatalog\Core\Response;
use Rateb\PlatformCatalog\Infrastructure\Redis\RedisClient;
use Rateb\PlatformCatalog\Infrastructure\Redis\RedisConfig;
use Rateb\PlatformCatalog\Support\Request;

final class RateLimitMiddleware
{
    private ?RedisClient $redis = null;

    public function __construct(
        private readonly PlatformIdentityResolver $identityResolver
    ) {
    }

    public function handle(string $method, string $path): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if (!str_starts_with($path, '/catalog/')) {
            return true;
        }

        $config = $this->loadConfig();
        $limit = $this->resolveLimit($method, $path, $config);
        $key = $this->resolveKey($path);
        $window = 60;
        $now = microtime(true);
        $bucket = 'ratelimit:' . $key;

        try {
            $redis = $this->redis();
            $member = $now . ':' . bin2hex(random_bytes(4));
            $redis->zadd($bucket, $now, $member);
            $redis->zremrangebyscore($bucket, 0, $now - $window);

            $count = count($redis->zrangebyscore($bucket, $now - $window, $now, 10000));
            $remaining = max(0, $limit - $count);
            $reset = (int) ceil($now + $window);

            header('X-RateLimit-Limit: ' . $limit);
            header('X-RateLimit-Remaining: ' . $remaining);
            header('X-RateLimit-Reset: ' . $reset);

            if ($count > $limit) {
                header('Retry-After: ' . $window);
                Response::json([
                    'data' => null,
                    'meta' => [],
                    'errors' => [['message' => 'Too many requests']],
                ], 429);

                return false;
            }
        } catch (\Throwable) {
            return true;
        }

        return true;
    }

    /**
     * @param array<string, int|bool> $config
     */
    private function resolveLimit(string $method, string $path, array $config): int
    {
        if (str_starts_with($path, '/catalog/bulk/')) {
            return (int) ($config['RATE_LIMIT_BULK_CONCURRENT'] ?? 10);
        }

        if (str_starts_with($path, '/catalog/media/') || str_starts_with($path, '/catalog/files/')) {
            return (int) ($config['RATE_LIMIT_MEDIA_PER_MIN'] ?? 1000);
        }

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return (int) ($config['RATE_LIMIT_API_WRITE_PER_MIN'] ?? 60);
        }

        return (int) ($config['RATE_LIMIT_API_READ_PER_MIN'] ?? 300);
    }

    private function resolveKey(string $path): string
    {
        $actorId = $this->identityResolver->resolveActorId();
        if ($actorId !== null) {
            return 'user:' . $actorId;
        }

        $apiKey = Request::header('X-Api-Key') ?? Request::header('Authorization') ?? '';
        if ($apiKey !== '') {
            return 'api:' . hash('sha256', $apiKey);
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (str_starts_with($path, '/catalog/media/') || str_starts_with($path, '/catalog/files/')) {
            return 'ip:' . $ip;
        }

        return 'ip:' . $ip . ':' . $path;
    }

  private function redis(): RedisClient
    {
        if ($this->redis === null) {
            $this->redis = new RedisClient(RedisConfig::fromEnvironment());
        }

        return $this->redis;
    }

    private function isEnabled(): bool
    {
        $config = $this->loadConfig();

        return (bool) ($config['RATE_LIMIT_ENABLED'] ?? true);
    }

    /**
     * @return array<string, int|bool>
     */
    private function loadConfig(): array
    {
        $path = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/config/ratelimit.php' : dirname(__DIR__, 3) . '/config/ratelimit.php';

        return is_file($path) ? require $path : [];
    }
}
