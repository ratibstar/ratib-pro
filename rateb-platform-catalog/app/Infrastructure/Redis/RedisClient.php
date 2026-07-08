<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Redis;

final class RedisClient implements RedisConnectionInterface
{
    private ?\Redis $extensionClient = null;

    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly RedisConfig $config
    ) {
    }

    public function ping(): bool
    {
        if ($this->isTestingWithoutRedis()) {
            return true;
        }

        try {
            $response = $this->command(['PING']);

            return $response === 'PONG' || $response === true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function get(string $key): ?string
    {
        $value = $this->command(['GET', $this->prefix($key)]);

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value, ?int $ttlSeconds = null): void
    {
        if ($ttlSeconds !== null && $ttlSeconds > 0) {
            $this->command(['SET', $this->prefix($key), $value, 'EX', (string) $ttlSeconds]);

            return;
        }

        $this->command(['SET', $this->prefix($key), $value]);
    }

    public function del(string $key): void
    {
        $this->command(['DEL', $this->prefix($key)]);
    }

    public function incr(string $key): int
    {
        $value = $this->command(['INCR', $this->prefix($key)]);

        return is_int($value) ? $value : (int) $value;
    }

    public function expire(string $key, int $ttlSeconds): void
    {
        $this->command(['EXPIRE', $this->prefix($key), (string) $ttlSeconds]);
    }

    public function lpush(string $key, string $value): void
    {
        $this->command(['LPUSH', $this->prefix($key), $value]);
    }

    public function rpop(string $key): ?string
    {
        $value = $this->command(['RPOP', $this->prefix($key)]);

        return is_string($value) ? $value : null;
    }

    public function zadd(string $key, float $score, string $member): void
    {
        $this->command(['ZADD', $this->prefix($key), (string) $score, $member]);
    }

    /**
     * @return list<string>
     */
    public function zrangebyscore(string $key, float $min, float $max, int $limit = 1): array
    {
        $response = $this->command([
            'ZRANGEBYSCORE',
            $this->prefix($key),
            (string) $min,
            (string) $max,
            'LIMIT',
            '0',
            (string) $limit,
        ]);

        if (!is_array($response)) {
            return [];
        }

        return array_values(array_filter($response, static fn ($v): bool => is_string($v)));
    }

    public function zrem(string $key, string $member): void
    {
        $this->command(['ZREM', $this->prefix($key), $member]);
    }

    public function zremrangebyscore(string $key, float $min, float $max): void
    {
        $this->command(['ZREMRANGEBYSCORE', $this->prefix($key), (string) $min, (string) $max]);
    }

  /**
     * @param list<string|int> $args
     */
    private function command(array $args): mixed
    {
        if (class_exists(\Redis::class)) {
            return $this->commandViaExtension($args);
        }

        return $this->commandViaSocket($args);
    }

    /**
     * @param list<string|int> $args
     */
    private function commandViaExtension(array $args): mixed
    {
        $client = $this->extensionClient ?? $this->connectExtension();
        $command = array_shift($args);
        if (!is_string($command)) {
            throw new \RuntimeException('Invalid Redis command');
        }

        return $client->rawCommand($command, ...array_map(static fn ($arg): string => (string) $arg, $args));
    }

    private function connectExtension(): \Redis
    {
        $client = new \Redis();
        $connected = $client->connect(
            $this->config->host,
            $this->config->port,
            $this->config->timeout
        );
        if (!$connected) {
            throw new \RuntimeException('Unable to connect to Redis');
        }
        if ($this->config->password !== null) {
            $client->auth($this->config->password);
        }
        if ($this->config->database > 0) {
            $client->select($this->config->database);
        }
        if ($this->config->prefix !== '') {
            $client->setOption(\Redis::OPT_PREFIX, $this->config->prefix);
        }
        $this->extensionClient = $client;

        return $client;
    }

    /**
     * @param list<string|int> $args
     */
    private function commandViaSocket(array $args): mixed
    {
        $socket = $this->socket ?? $this->connectSocket();
        $payload = '*' . count($args) . "\r\n";
        foreach ($args as $arg) {
            $string = (string) $arg;
            $payload .= '$' . strlen($string) . "\r\n" . $string . "\r\n";
        }
        if (fwrite($socket, $payload) === false) {
            throw new \RuntimeException('Redis write failed');
        }

        return $this->readResponse($socket);
    }

    /** @return resource */
    private function connectSocket()
    {
        $socket = @stream_socket_client(
            'tcp://' . $this->config->host . ':' . $this->config->port,
            $errno,
            $errstr,
            $this->config->timeout
        );
        if ($socket === false) {
            throw new \RuntimeException('Unable to connect to Redis: ' . $errstr);
        }
        stream_set_timeout($socket, (int) ceil($this->config->timeout));
        if ($this->config->password !== null) {
            $this->commandViaSocket(['AUTH', $this->config->password]);
        }
        if ($this->config->database > 0) {
            $this->commandViaSocket(['SELECT', (string) $this->config->database]);
        }
        $this->socket = $socket;

        return $socket;
    }

    /** @param resource $socket */
    private function readResponse($socket): mixed
    {
        $line = fgets($socket);
        if ($line === false || $line === '') {
            throw new \RuntimeException('Redis read failed');
        }
        $type = $line[0];
        $payload = substr($line, 1, -2);

        return match ($type) {
            '+' => $payload,
            '-' => throw new \RuntimeException('Redis error: ' . $payload),
            ':' => (int) $payload,
            '$' => $this->readBulk($socket, (int) $payload),
            '*' => $this->readArray($socket, (int) $payload),
            default => throw new \RuntimeException('Unknown Redis response'),
        };
    }

    /** @param resource $socket */
    private function readBulk($socket, int $length): ?string
    {
        if ($length < 0) {
            return null;
        }
        $data = '';
        while (strlen($data) < $length + 2) {
            $chunk = fread($socket, $length + 2 - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Redis bulk read failed');
            }
            $data .= $chunk;
        }

        return substr($data, 0, $length);
    }

    /**
     * @param resource $socket
     * @return list<mixed>
     */
    private function readArray($socket, int $count): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->readResponse($socket);
        }

        return $items;
    }

    private function prefix(string $key): string
    {
        if ($this->extensionClient !== null || class_exists(\Redis::class)) {
            return $key;
        }

        return $this->config->prefix . $key;
    }

    private function isTestingWithoutRedis(): bool
    {
        return defined('RATEB_CATALOG_TESTING') && RATEB_CATALOG_TESTING
            && getenv('CATALOG_REDIS_TESTS') !== '1';
    }
}
