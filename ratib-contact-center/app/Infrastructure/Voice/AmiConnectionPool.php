<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Voice;

/**
 * Singleton AMI connection for PBX command execution from web requests.
 */
final class AmiConnectionPool
{
    private static ?AmiClient $client = null;

    public static function client(): AmiClient
    {
        if (self::$client === null || !self::$client->isConnected()) {
            self::$client = new AmiClient();
            self::$client->connect();
        }
        return self::$client;
    }

    public static function reset(): void
    {
        if (self::$client !== null) {
            self::$client->disconnect();
            self::$client = null;
        }
    }

    /** @param callable(AmiClient): void $callback */
    public static function withClient(callable $callback): void
    {
        $client = self::client();
        try {
            $callback($client);
        } catch (\Throwable $e) {
            self::reset();
            throw $e;
        }
    }
}
