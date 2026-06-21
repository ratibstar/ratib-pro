<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR;

use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrSessionStatus;

/**
 * Result of executing a single IVR node.
 */
final class NodeExecutionResult
{
    public function __construct(
        public readonly bool $continueExecution,
        public readonly ?int $nextNodeId,
        public readonly IvrSessionStatus $sessionStatus,
        public readonly int $retryCount,
        /** @var array<string, mixed> */
        public readonly array $statePatch = [],
        public readonly bool $awaitInput = false
    ) {
    }

    /** @param array<string, mixed> $statePatch */
    public static function advance(?int $nextNodeId, array $statePatch = []): self
    {
        return new self(true, $nextNodeId, IvrSessionStatus::Active, 0, $statePatch, false);
    }

    /** @param array<string, mixed> $statePatch */
    public static function waitForInput(array $statePatch = [], int $retryCount = 0): self
    {
        return new self(false, null, IvrSessionStatus::WaitingInput, $retryCount, $statePatch, true);
    }

    public static function complete(array $statePatch = []): self
    {
        return new self(false, null, IvrSessionStatus::Completed, 0, $statePatch, false);
    }

    public static function fail(array $statePatch = []): self
    {
        return new self(false, null, IvrSessionStatus::Failed, 0, $statePatch, false);
    }

    public static function timedOut(?int $fallbackNodeId, int $retryCount, array $statePatch = []): self
    {
        if ($fallbackNodeId !== null && $fallbackNodeId > 0) {
            return new self(true, $fallbackNodeId, IvrSessionStatus::Active, 0, $statePatch, false);
        }
        return new self(false, null, IvrSessionStatus::Timeout, $retryCount, $statePatch, false);
    }
}
