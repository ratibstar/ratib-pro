<?php
declare(strict_types=1);

namespace App\Core;

final class PolicyEngine
{
    /** @return array<string, mixed> */
    public function forMode(string $mode): array
    {
        if ($mode === ModeResolver::GOVERNMENT_MODE) {
            return [
                'mode' => ModeResolver::GOVERNMENT_MODE,
                'strict_workflow' => true,
                'soft_validation' => false,
                'force_event_logging' => true,
                'immutable_audit' => true,
                'anti_spoofing' => true,
                'anomaly_detection' => true,
                'max_retries_override' => null,
            ];
        }

        return [
            'mode' => ModeResolver::COMMERCIAL_MODE,
            'strict_workflow' => false,
            'soft_validation' => true,
            'force_event_logging' => true,
            'immutable_audit' => true,
            'anti_spoofing' => false,
            'anomaly_detection' => false,
            'max_retries_override' => 1,
        ];
    }
}
