<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Observability;

use Ratib\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;

/**
 * Non-blocking facade for provider event logging.
 * Never throws to preserve adapter/orchestration flow.
 */
final class ProviderEventBus
{
    private static ?ProviderEventLogger $logger = null;
    private static bool $initFailed = false;

    /**
     * @param array<string, mixed> $context
     */
    public static function log(string $providerType, string $providerCode, string $eventName, array $context = []): void
    {
        try {
            $logger = self::logger();
            if ($logger === null) {
                return;
            }
            $logger->log($providerType, $providerCode, $eventName, $context);
        } catch (\Throwable $e) {
            // Intentionally swallowed: observability must be additive, never blocking.
        }
    }

    public static function logger(): ?ProviderEventLogger
    {
        if (self::$logger instanceof ProviderEventLogger) {
            return self::$logger;
        }
        if (self::$initFailed) {
            return null;
        }
        try {
            $pdo = DatabaseConnectionFactory::createPdo();
            self::$logger = new ProviderEventLogger($pdo);
        } catch (\Throwable $e) {
            self::$initFailed = true;
            return null;
        }

        return self::$logger;
    }
}
