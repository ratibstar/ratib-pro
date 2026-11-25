<?php
declare(strict_types=1);

/**
 * Automatic free-only online video failover: Agnes → Replicate → Runware.
 * Never selects Runway. Never falls back to local rendering.
 */
final class FreeOnlineVideoManager implements RatebVideoProvider
{
    /** @var array<string,RatebVideoProvider> */
    private array $adapters;

    public function __construct()
    {
        $this->adapters = [
            'agnes' => new AgnesVideoProvider(),
            'replicate' => new ReplicateVideoProvider(),
            'runware' => new RunwareVideoProvider(),
        ];
    }

    public function name(): string
    {
        return 'free_chain';
    }

    public function configured(): bool
    {
        return $this->firstEligible() !== null;
    }

    public function hasFreeAvailable(): bool
    {
        return $this->configured();
    }

    /** @return list<array<string,mixed>> */
    public function statuses(bool $probe = false): array
    {
        return rateb_video_free_provider_statuses($probe);
    }

    public function generateFromImage(string $imageDataUri, string $prompt, int $seconds, string $ratio): array
    {
        return $this->generateWithFailover('image', $imageDataUri, $prompt, $seconds, $ratio);
    }

    public function generateFromText(string $prompt, int $seconds, string $ratio): array
    {
        return $this->generateWithFailover('text', '', $prompt, $seconds, $ratio);
    }

    public function generateFromVideo(string $videoUri, string $prompt, int $seconds, string $ratio): array
    {
        throw new RuntimeException('rejected_model');
    }

    public function getTaskStatus(string $taskId): array
    {
        [$provider, $raw] = $this->splitTaskId($taskId);
        $adapter = $this->adapters[$provider] ?? null;
        if (!$adapter instanceof RatebVideoProvider) {
            return ['status' => 'failed', 'url' => '', 'error' => 'provider'];
        }
        return $adapter->getTaskStatus($raw);
    }

    public function downloadResult(string $url): string
    {
        // Try each adapter allowlist by attempting download; first success wins.
        $errors = [];
        foreach ($this->adapters as $name => $adapter) {
            try {
                return $adapter->downloadResult($url);
            } catch (RuntimeException $e) {
                $errors[$name] = $e->getMessage();
            }
        }
        throw new RuntimeException('rejected');
    }

    /** @return array{task_id:string} */
    private function generateWithFailover(string $mode, string $imageDataUri, string $prompt, int $seconds, string $ratio): array
    {
        $tried = [];
        foreach (rateb_video_free_provider_order() as $name) {
            $status = rateb_video_free_provider_status($name, false);
            if (!$status['eligible']) {
                rateb_video_free_audit_write([
                    'event' => 'skip',
                    'provider' => $name,
                    'reason' => $status['exhausted'] ? 'exhausted' : ($status['configured'] ? 'not_free_verified' : 'not_configured'),
                    'quota_status' => $status['quota_status'],
                ]);
                continue;
            }
            $adapter = $this->adapters[$name] ?? null;
            if (!$adapter instanceof RatebVideoProvider) {
                continue;
            }
            $tried[] = $name;
            try {
                $created = $mode === 'text'
                    ? $adapter->generateFromText($prompt, $seconds, $ratio)
                    : $adapter->generateFromImage($imageDataUri, $prompt, $seconds, $ratio);
                $rawId = (string) ($created['task_id'] ?? '');
                if ($rawId === '') {
                    throw new RuntimeException('provider');
                }
                rateb_video_free_audit_write([
                    'event' => 'accepted',
                    'provider' => $name,
                    'mode' => $mode,
                    'seconds' => $seconds,
                    'prompt' => $prompt,
                    'task_prefix' => $name,
                ]);
                return ['task_id' => $name . ':' . $rawId];
            } catch (RuntimeException $e) {
                $code = $e->getMessage();
                rateb_video_free_audit_write([
                    'event' => 'failure',
                    'provider' => $name,
                    'reason' => $code,
                    'mode' => $mode,
                ]);
                if (rateb_video_free_error_is_quota($code)) {
                    rateb_video_free_audit_mark_exhausted($name, $code);
                }
                // Failover to next eligible provider; never retry exhausted.
                continue;
            }
        }
        rateb_video_free_audit_write([
            'event' => 'chain_exhausted',
            'tried' => $tried,
            'reason' => 'no_free_video_provider',
        ]);
        throw new RuntimeException('no_free_video_provider');
    }

    private function firstEligible(): ?string
    {
        foreach (rateb_video_free_provider_order() as $name) {
            $status = rateb_video_free_provider_status($name, false);
            if ($status['eligible']) {
                return $name;
            }
        }
        return null;
    }

    /** @return array{0:string,1:string} */
    private function splitTaskId(string $taskId): array
    {
        $pos = strpos($taskId, ':');
        if ($pos === false) {
            return ['', $taskId];
        }
        return [substr($taskId, 0, $pos), substr($taskId, $pos + 1)];
    }
}
