<?php

declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Phase 10 — Lightweight CRM observability (timing + failure logs via AuditService).
 */
final class CrmObservability
{
    /**
     * @template T
     * @param callable():T $fn
     * @return array{result:T,ms:float,ok:bool,error:?string}
     */
    public static function timed(string $label, callable $fn, ?string $entityType = null, ?int $entityId = null): array
    {
        $start = microtime(true);
        try {
            $result = $fn();
            $ms = round((microtime(true) - $start) * 1000, 2);
            self::logTiming($label, $ms, true, null, $entityType, $entityId);

            return ['result' => $result, 'ms' => $ms, 'ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            $ms = round((microtime(true) - $start) * 1000, 2);
            self::logTiming($label, $ms, false, $e->getMessage(), $entityType, $entityId);
            throw $e;
        }
    }

    public static function logTiming(
        string $label,
        float $ms,
        bool $ok,
        ?string $error = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): void {
        if (!class_exists(AuditService::class)) {
            return;
        }
        try {
            (new AuditService())->log('crm.observability.timing', $entityType ?? 'crm', $entityId, [
                'label' => $label,
                'ms' => $ms,
                'ok' => $ok,
                'error' => $error,
            ]);
        } catch (\Throwable $e) {
            // never break request path
        }
    }

    public static function logFailure(string $label, \Throwable $e, ?string $entityType = null, ?int $entityId = null): void
    {
        if (!class_exists(AuditService::class)) {
            return;
        }
        try {
            (new AuditService())->log('crm.observability.failure', $entityType ?? 'crm', $entityId, [
                'label' => $label,
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
        } catch (\Throwable $ignored) {
        }
    }
}
