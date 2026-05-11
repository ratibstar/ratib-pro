<?php
/**
 * Central place for adapter/action telemetry without failing user flows.
 */
declare(strict_types=1);

final class Ratib_ClientDashboard_ObservabilityHub
{
    /** @var list<array<string, mixed>> */
    private $adapterEvents = [];

    /** @var list<array<string, mixed>> */
    private $actionEvents = [];

    /** @var array<string, bool> */
    private $degraded = [];

    public function recordAdapter(string $name, bool $ok, ?string $message = null, array $meta = []): void
    {
        $this->adapterEvents[] = [
            'adapter' => $name,
            'ok' => $ok,
            'message' => $message,
            'meta' => $meta,
            'at' => gmdate('c'),
        ];
        if (!$ok) {
            $this->degraded['adapter:' . $name] = true;
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function recordAction(string $action, bool $ok, array $meta = []): void
    {
        $this->actionEvents[] = [
            'action' => $action,
            'ok' => $ok,
            'meta' => $meta,
            'at' => gmdate('c'),
        ];
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
        ];
    }
}
