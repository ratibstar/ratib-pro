<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Release\Deployment;

final class ReleaseGateEvaluator
{
    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function evaluate(array $report, bool $strict = false): array
    {
        $status = (string) ($report['status'] ?? 'FAIL');
        $fail = (int) (($report['matrix']['FAIL'] ?? 0));
        $warn = (int) (($report['matrix']['WARN'] ?? 0));

        $queue = (array) (($report['sections']['queue_worker']['summary'] ?? []));
        $staleWorkers = (int) ($queue['stale_workers'] ?? 0);
        $deadLetter = (int) ($queue['dead_letter'] ?? 0);
        $depth = (int) ($queue['depth'] ?? 0);

        $reasons = [];
        if ($status === 'FAIL' || $fail > 0) {
            $reasons[] = 'Prelaunch status FAIL.';
        }
        if ($staleWorkers > 0) {
            $reasons[] = 'Stale worker heartbeats detected.';
        }
        if ($deadLetter > 100) {
            $reasons[] = 'Dead-letter threshold exceeded.';
        }
        if ($depth > 3000) {
            $reasons[] = 'Queue saturation risk.';
        }
        if ($strict && $warn > 0) {
            $reasons[] = 'Strict mode blocks WARN statuses.';
        }

        return [
            'pass' => $reasons === [],
            'reasons' => $reasons,
            'strict' => $strict,
        ];
    }
}

