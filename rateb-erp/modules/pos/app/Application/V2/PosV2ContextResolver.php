<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\DTO\V2\Context\PosV2BranchContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2ShiftContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2TerminalContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CashierPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2LocalePortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PermissionsPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosContextPortInterface;
use Rateb\App\Pos\Services\V2\PosV2FeatureFlagService;
use RuntimeException;

/**
 * Resolves immutable V2 register and request contexts (single resolution path).
 */
final class PosV2ContextResolver
{
    public function __construct(
        private readonly PosV2PosContextPortInterface $posContext,
        private readonly PosV2CashierPortInterface $cashier,
        private readonly PosV2LocalePortInterface $locale,
        private readonly PosV2PermissionsPortInterface $permissions,
        private readonly PosV2FeatureFlagService $featureFlags,
    ) {
    }

    public function resolveRegisterContext(): PosV2RegisterContext
    {
        $this->posContext->bootstrapTenant();

        $cashierUserId = $this->cashier->userId();
        $metadata = $this->posContext->getRegisterMetadata();
        $companyId = (int) ($metadata['company_id'] ?? 0);

        if ($companyId > 0 && $cashierUserId > 0) {
            $this->posContext->syncRegisterFromOpenShift($companyId, $cashierUserId);
            $metadata = $this->posContext->getRegisterMetadata();
        }

        if ($companyId < 1) {
            throw new RuntimeException('POS V2 register context requires a valid company scope.');
        }

        $featureFlagContext = PosV2RequestScope::ensure()->resolveFeatureFlagContext();
        if ($featureFlagContext === null) {
            throw new RuntimeException('POS V2 feature flag context could not be resolved.');
        }

        $resolvedFlags = $this->featureFlags->resolve($featureFlagContext);

        $terminal = $this->mapTerminal($metadata['terminal'] ?? null);
        $shift = $this->mapShift($metadata['shift'] ?? null);
        $branch = $this->mapBranch($metadata['branch'] ?? null);

        return new PosV2RegisterContext(
            companyId: $companyId,
            branchId: (int) ($metadata['branch_id'] ?? ($branch?->id ?? 0)),
            warehouseId: (int) ($metadata['warehouse_id'] ?? ($terminal?->warehouseId ?? 0)),
            sessionId: (int) ($metadata['session_id'] ?? 0),
            terminal: $terminal,
            shift: $shift,
            branch: $branch,
            cashier: new PosV2CashierContext(
                userId: $cashierUserId,
                displayName: $this->cashier->displayName(),
            ),
            locale: $this->locale->locale(),
            timezone: $this->locale->timezone(),
            currency: $this->locale->currency(),
            rtl: $this->locale->isRtl(),
            featureFlags: new PosV2FeatureFlagsContext(
                enabled: $resolvedFlags->enabled,
                profile: $resolvedFlags->profile,
                scanMode: $resolvedFlags->scanMode,
                offline: $resolvedFlags->offline,
                cardTerminal: $resolvedFlags->cardTerminal,
            ),
            permissions: $this->permissions->resolveForUser($cashierUserId),
            registerReady: (bool) ($metadata['register_ready'] ?? false),
        );
    }

    public function resolveRequestContext(string $channel, string $httpMethod, string $requestPath): PosV2RequestContext
    {
        return new PosV2RequestContext(
            httpMethod: strtoupper($httpMethod),
            requestPath: $requestPath,
            channel: $channel,
            register: $this->resolveRegisterContext(),
        );
    }

    /**
     * @param array{id: int, code: string, name: string, warehouse_id: int}|null $raw
     */
    private function mapTerminal(?array $raw): ?PosV2TerminalContext
    {
        if ($raw === null) {
            return null;
        }

        return new PosV2TerminalContext(
            id: (int) $raw['id'],
            code: (string) $raw['code'],
            name: (string) $raw['name'],
            warehouseId: (int) $raw['warehouse_id'],
        );
    }

    /**
     * @param array{id: int, shift_no: string, status: string}|null $raw
     */
    private function mapShift(?array $raw): ?PosV2ShiftContext
    {
        if ($raw === null) {
            return null;
        }

        return new PosV2ShiftContext(
            id: (int) $raw['id'],
            shiftNo: (string) $raw['shift_no'],
            status: (string) $raw['status'],
        );
    }

    /**
     * @param array{id: int, name: string}|null $raw
     */
    private function mapBranch(?array $raw): ?PosV2BranchContext
    {
        if ($raw === null) {
            return null;
        }

        return new PosV2BranchContext(
            id: (int) $raw['id'],
            name: (string) $raw['name'],
        );
    }
}
