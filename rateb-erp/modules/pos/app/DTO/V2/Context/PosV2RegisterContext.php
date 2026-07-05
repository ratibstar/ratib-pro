<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Context;

/**
 * Immutable register runtime context — single source of truth for V2 controllers.
 */
final readonly class PosV2RegisterContext
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public int $companyId,
        public int $branchId,
        public int $warehouseId,
        public int $sessionId,
        public ?PosV2TerminalContext $terminal,
        public ?PosV2ShiftContext $shift,
        public ?PosV2BranchContext $branch,
        public PosV2CashierContext $cashier,
        public string $locale,
        public string $timezone,
        public string $currency,
        public bool $rtl,
        public PosV2FeatureFlagsContext $featureFlags,
        public array $permissions,
        public bool $registerReady,
    ) {
    }

    public function profile(): string
    {
        return $this->featureFlags->profile;
    }

    public function v2Enabled(): bool
    {
        return $this->featureFlags->enabled;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'warehouse_id' => $this->warehouseId,
            'session_id' => $this->sessionId,
            'terminal' => $this->terminal?->toArray(),
            'shift' => $this->shift?->toArray(),
            'branch' => $this->branch?->toArray(),
            'cashier' => $this->cashier->toArray(),
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'rtl' => $this->rtl,
            'feature_flags' => $this->featureFlags->toArray(),
            'permissions' => $this->permissions,
            'register_ready' => $this->registerReady,
            'profile' => $this->profile(),
        ];
    }
}
