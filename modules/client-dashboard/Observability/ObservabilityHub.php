<?php
/**
 * Central place for adapter/action telemetry without failing user flows.
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_ObservabilityHub
{
    /** @var list<array<string, mixed>> */
    private $adapterEvents = [];

    /** @var list<array<string, mixed>> */
    private $actionEvents = [];

    /** @var array<string, bool> */
    private $degraded = [];

    /** @var string */
    private $correlationId = '';

    /** @var string */
    private $traceId = '';

    /** @var list<array<string, mixed>> */
    private $adapterTimingsMs = [];

    public function __construct()
    {
        $this->traceId = bin2hex(random_bytes(8));
    }

    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function traceId(): string
    {
        return $this->traceId;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function recordAdapter(string $name, bool $ok, ?string $message = null, array $meta = []): void
    {
        $row = [
            'adapter' => $name,
            'ok' => $ok,
            'message' => $message,
            'meta' => $meta,
            'at' => gmdate('c'),
        ];
        if ($this->correlationId !== '') {
            $row['correlation_id'] = $this->correlationId;
        }
        $this->adapterEvents[] = $row;
        if (!$ok) {
            $this->degraded['adapter:' . $name] = true;
        }
    }

    public function recordAdapterTiming(string $name, float $elapsedMs): void
    {
        $this->adapterTimingsMs[] = [
            'adapter' => $name,
            'ms' => round($elapsedMs, 3),
            'at' => gmdate('c'),
            'correlation_id' => $this->correlationId,
        ];
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function recordAction(string $action, bool $ok, array $meta = []): void
    {
        $row = [
            'action' => $action,
            'ok' => $ok,
            'meta' => $meta,
            'at' => gmdate('c'),
        ];
        if ($this->correlationId !== '') {
            $row['correlation_id'] = $this->correlationId;
        }
        $this->actionEvents[] = $row;
    }

    public function markDegraded(string $key, bool $on = true): void
    {
        if ($on) {
            $this->degraded[$key] = true;
        } else {
            unset($this->degraded[$key]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotSlice(): array
    {
        return [
            'degraded_flags' => array_keys($this->degraded),
            'adapter_events_tail' => array_slice($this->adapterEvents, -12),
            'recent_actions_tail' => array_slice($this->actionEvents, -8),
            'trace_id' => $this->traceId,
            'correlation_id' => $this->correlationId,
            'adapter_timings_ms_tail' => array_slice($this->adapterTimingsMs, -10),
        ];
    }
}
