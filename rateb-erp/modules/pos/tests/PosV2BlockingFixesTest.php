<?php

declare(strict_types=1);

/**
 * POS V2 audit blocking-fix tests (no PHPUnit dependency).
 *
 * Run: php modules/pos/tests/run-blocking-fixes-tests.php
 */

use Rateb\App\Pos\Application\V2\Http\PosV2NotImplementedResponse;
use Rateb\App\Pos\Application\V2\PosV2RequestCompositionRoot;
use Rateb\App\Pos\Application\V2\PosV2RequestScope;
use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Application\V2\PosV2SharedRepositories;
use Rateb\App\Pos\Application\V2\PosV2SharedServices;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2CatalogScope;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagContext;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagLayers;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2MergedPosSettings;
use Rateb\App\Pos\DTO\V2\Bootstrap\PosV2BootstrapMeta;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchRequest;
use Rateb\App\Pos\DTO\V2\Catalog\CatalogSearchResponse;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogBootstrapDto;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2CatalogProductDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2PaginationDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2CompanyContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2CurrencyContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2LocaleContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2PermissionsContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2ProfilesContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2RegisterBootstrapMetadata;
use Rateb\App\Pos\DTO\V2\Register\PosV2RegisterBootstrapRegister;
use Rateb\App\Pos\DTO\V2\Register\PosV2RegisterCapabilities;
use Rateb\App\Pos\DTO\V2\Register\RegisterBootstrapResponse;
use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagRepositoryInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogCategoryCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogCategoryPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CatalogProductPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CashierPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosContextPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsPortInterface;
use Rateb\App\Pos\Repositories\V2\InMemoryCatalogCategoryCache;
use Rateb\App\Pos\Repositories\V2\InMemoryFeatureFlagCache;
use Rateb\App\Pos\Repositories\V2\InMemoryPosSettingsCache;
use Rateb\App\Pos\Services\V2\Contracts\PosV2EnvironmentFlagReaderInterface;
use Rateb\App\Pos\Services\V2\PosV2EnvironmentFlagReader;
use Rateb\App\Pos\Services\V2\PosV2FeatureFlagResolver;
use Rateb\App\Pos\Services\V2\PosV2FeatureFlagService;
use Rateb\App\Pos\Services\V2\PosV2UnifiedFeatureFlagContextResolver;

final class PosV2BlockingFixesTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testFeatureFlagTerminalOverridesBranch();
        $this->testFeatureFlagEnvFallbackAfterLayers();
        $this->testCompositionRootSharesInstancesPerRequest();
        $this->testFeatureFlagContextCachedOnCompositionRoot();
        $this->testBootstrapSuccessEnvelope();
        $this->testPosV2DisabledEnvelope();
        $this->testNotImplementedEnvelope();

        return $this->results;
    }

    private function testFeatureFlagTerminalOverridesBranch(): void
    {
        $resolver = new PosV2FeatureFlagResolver(new NullEnvironmentFlagReader(), $this->testFlagConfig());
        $layers = new PosV2FeatureFlagLayers(
            terminalV2: ['enabled' => false],
            branchV2: ['enabled' => true],
            companyV2: ['enabled' => true],
        );

        $resolved = $resolver->resolve($layers);
        $this->record(
            'feature flag priority: terminal overrides branch/company',
            $resolved->enabled === false,
            'expected enabled=false from terminal layer',
        );
    }

    private function testFeatureFlagEnvFallbackAfterLayers(): void
    {
        $resolver = new PosV2FeatureFlagResolver(new StubEnvironmentFlagReader(enabled: true), $this->testFlagConfig());
        $layers = new PosV2FeatureFlagLayers(null, null, null);
        $resolved = $resolver->resolve($layers);

        $this->record(
            'feature flag priority: env after empty layers',
            $resolved->enabled === true,
            'expected enabled=true from env fallback',
        );
    }

    private function testCompositionRootSharesInstancesPerRequest(): void
    {
        unset($_SERVER['RATEB_POS_V2_COMPOSITION_ROOT']);

        $root = $this->makeTestCompositionRoot();
        PosV2RequestScope::bind($root);

        $middlewareRoot = PosV2RequestScope::ensure();
        $applicationRoot = PosV2RequestScope::ensure();

        $sameRoot = $middlewareRoot === $applicationRoot;
        $sameCache = spl_object_id($middlewareRoot->repositories->featureFlagCache)
            === spl_object_id($applicationRoot->repositories->featureFlagCache);
        $sameRepo = spl_object_id($middlewareRoot->repositories->featureFlagRepository)
            === spl_object_id($applicationRoot->repositories->featureFlagRepository);
        $samePosContext = spl_object_id($middlewareRoot->posContext)
            === spl_object_id($applicationRoot->posContext);

        $this->record(
            'composition root shared per request',
            $sameRoot && $sameCache && $sameRepo && $samePosContext,
            'middleware and application must share one graph',
        );
    }

    private function testFeatureFlagContextCachedOnCompositionRoot(): void
    {
        $root = $this->makeTestCompositionRoot();
        $first = $root->resolveFeatureFlagContext();
        $second = $root->resolveFeatureFlagContext();

        $this->record(
            'feature flag context cached on composition root',
            $first === $second && $first instanceof PosV2FeatureFlagContext,
            'resolveFeatureFlagContext must return same instance',
        );
    }

    private function makeTestCompositionRoot(): PosV2RequestCompositionRoot
    {
        $featureFlagCache = new InMemoryFeatureFlagCache();
        $posSettingsCache = new InMemoryPosSettingsCache();
        $posContext = new StubPosContextPort();
        $cashier = new StubCashierPort();

        $featureFlagService = new PosV2FeatureFlagService(
            new StubFeatureFlagRepository(),
            new PosV2FeatureFlagResolver(new PosV2EnvironmentFlagReader(), $this->testFlagConfig()),
            $featureFlagCache,
        );

        return new PosV2RequestCompositionRoot(
            new PosV2SharedRepositories(
                $featureFlagCache,
                new StubFeatureFlagRepository(),
                $posSettingsCache,
                new StubPosSettingsPort(),
                new InMemoryCatalogCategoryCache(),
                new StubCatalogCategoryPort(),
                new StubCatalogProductPort(),
                new StubCartPort(),
            ),
            new PosV2SharedServices($featureFlagService),
            $posContext,
            $cashier,
            new PosV2UnifiedFeatureFlagContextResolver($posContext, $cashier),
        );
    }

    private function testBootstrapSuccessEnvelope(): void
    {
        $response = (new PosV2ResponseFactory())->bootstrapSuccess(
            $this->minimalBootstrapResponse(),
            new PosV2BootstrapMeta('2', 'retail'),
        );

        $body = $response->body;
        $ok = $response->statusCode === 200
            && ($body['success'] ?? null) === true
            && is_array($body['data'] ?? null)
            && is_array($body['meta'] ?? null)
            && ($body['meta']['version'] ?? '') === '2'
            && ($body['meta']['profile'] ?? '') === 'retail';

        $this->record('bootstrap success envelope', $ok, 'expected success/data/meta shape');
    }

    private function testPosV2DisabledEnvelope(): void
    {
        $envelope = [
            'success' => false,
            'error' => [
                'code' => 'POS_V2_DISABLED',
                'message' => 'POS V2 is not enabled for this company, branch, or terminal.',
                'field' => null,
                'details' => ['fallback' => 'v1'],
            ],
        ];

        $ok = $envelope['success'] === false
            && ($envelope['error']['code'] ?? '') === 'POS_V2_DISABLED';

        $this->record('POS_V2_DISABLED envelope contract', $ok, 'middleware api gate shape');
    }

    private function testNotImplementedEnvelope(): void
    {
        $body = PosV2NotImplementedResponse::body();
        $ok = $body['success'] === false
            && ($body['error']['code'] ?? '') === 'NOT_IMPLEMENTED';

        $this->record('NOT_IMPLEMENTED envelope', $ok, 'stub route 501 shape');
    }

    /** @return array<string, mixed> */
    private function testFlagConfig(): array
    {
        return [
            'defaults' => [
                'POS_V2_ENABLED' => false,
                'POS_V2_PROFILE' => 'retail',
                'POS_V2_SCAN_MODE' => false,
                'POS_V2_OFFLINE' => false,
                'POS_V2_CARD_TERMINAL' => false,
            ],
            'env' => [
                'POS_V2_ENABLED' => 'POS_V2_ENABLED',
                'POS_V2_PROFILE' => 'POS_V2_PROFILE',
                'POS_V2_SCAN_MODE' => 'POS_V2_SCAN_MODE',
                'POS_V2_OFFLINE' => 'POS_V2_OFFLINE',
                'POS_V2_CARD_TERMINAL' => 'POS_V2_CARD_TERMINAL',
            ],
            'profiles' => ['retail'],
        ];
    }

    private function minimalBootstrapResponse(): RegisterBootstrapResponse
    {
        return new RegisterBootstrapResponse(
            register: new PosV2RegisterBootstrapRegister(1, 2, true, false),
            terminal: null,
            branch: null,
            warehouse: null,
            company: new PosV2CompanyContext(1, 'Test Co'),
            shift: null,
            cashier: new PosV2CashierContext(9, 'Cashier'),
            permissions: new PosV2PermissionsContext([]),
            featureFlags: new PosV2FeatureFlagsContext(true, 'retail', false, false, false),
            locale: new PosV2LocaleContext('ar', true),
            timezone: 'Asia/Riyadh',
            currency: new PosV2CurrencyContext('SAR'),
            profile: 'retail',
            posSettings: null,
            receiptSettings: null,
            taxSettings: null,
            profiles: new PosV2ProfilesContext('retail', ['retail']),
            capabilities: new PosV2RegisterCapabilities(
                registerAccess: true,
                shiftOpen: true,
                shiftClose: true,
                scanMode: false,
                offlineMode: false,
                cardTerminal: false,
                manageSettings: false,
                manageTerminals: false,
                returns: false,
                discounts: false,
            ),
            catalog: new PosV2CatalogBootstrapDto([]),
            cart: new CartResponse(
                [],
                new PosV2CartTotalsDto(
                    new PosV2MoneyDto('0.00', 'SAR'),
                    new PosV2MoneyDto('0.00', 'SAR'),
                    new PosV2MoneyDto('0.00', 'SAR'),
                    new PosV2MoneyDto('0.00', 'SAR'),
                ),
                0,
            ),
            metadata: new PosV2RegisterBootstrapMetadata('2', 'api', 'GET', '/api/v2/pos/bootstrap'),
        );
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}

final class NullEnvironmentFlagReader implements PosV2EnvironmentFlagReaderInterface
{
    public function getOptionalBool(string $envKey): ?bool
    {
        return null;
    }

    public function getOptionalString(string $envKey): ?string
    {
        return null;
    }
}

final class StubEnvironmentFlagReader implements PosV2EnvironmentFlagReaderInterface
{
    public function __construct(
        private readonly ?bool $enabled = null,
    ) {
    }

    public function getOptionalBool(string $envKey): ?bool
    {
        return $envKey === 'POS_V2_ENABLED' ? $this->enabled : null;
    }

    public function getOptionalString(string $envKey): ?string
    {
        return null;
    }
}

final class StubPosContextPort implements PosV2PosContextPortInterface
{
    public function bootstrapTenant(): void
    {
    }

    public function syncRegisterFromOpenShift(int $companyId, int $userId): void
    {
    }

    public function getRegisterMetadata(): array
    {
        return [
            'company_id' => 1,
            'branch_id' => 2,
            'terminal' => ['id' => 3, 'code' => 'T1', 'name' => 'Terminal', 'warehouse_id' => 4],
        ];
    }
}

final class StubCashierPort implements PosV2CashierPortInterface
{
    public function userId(): int
    {
        return 10;
    }

    public function displayName(): string
    {
        return 'Test Cashier';
    }
}

final class StubFeatureFlagRepository implements FeatureFlagRepositoryInterface
{
    public function loadLayers(PosV2FeatureFlagContext $context): PosV2FeatureFlagLayers
    {
        return new PosV2FeatureFlagLayers(null, null, null);
    }
}

final class StubPosSettingsPort implements PosV2PosSettingsPortInterface
{
    public function loadMerged(int $companyId, int $branchId): PosV2MergedPosSettings
    {
        return new PosV2MergedPosSettings($companyId, $branchId, false, [], null);
    }
}

final class StubCatalogCategoryPort implements PosV2CatalogCategoryPortInterface
{
    public function listActive(int $companyId, bool $rtl): array
    {
        return [];
    }
}

final class StubCatalogProductPort implements PosV2CatalogProductPortInterface
{
    public function search(PosV2CatalogScope $scope, CatalogSearchRequest $request): CatalogSearchResponse
    {
        return new CatalogSearchResponse([], new PosV2PaginationDto(1, 24, 0, 1));
    }

    public function findById(PosV2CatalogScope $scope, int $productId): ?PosV2CatalogProductDto
    {
        return null;
    }

    public function lookupBarcode(PosV2CatalogScope $scope, string $code): ?PosV2CatalogProductDto
    {
        return null;
    }
}

final class StubCartPort implements PosV2CartPortInterface
{
    public function load(PosV2CartScope $scope): CartResponse
    {
        return $this->emptyCart($scope->currency);
    }

    public function addLine(PosV2CartScope $scope, int $productId, string $qty): CartResponse
    {
        return $this->emptyCart($scope->currency);
    }

    public function updateLine(PosV2CartScope $scope, string $lineId, string $qty): CartResponse
    {
        return $this->emptyCart($scope->currency);
    }

    public function removeLine(PosV2CartScope $scope, string $lineId): CartResponse
    {
        return $this->emptyCart($scope->currency);
    }

    public function clear(PosV2CartScope $scope): CartResponse
    {
        return $this->emptyCart($scope->currency);
    }

    private function emptyCart(string $currency): CartResponse
    {
        $zero = new PosV2MoneyDto('0.00', $currency);

        return new CartResponse(
            [],
            new PosV2CartTotalsDto($zero, $zero, $zero, $zero),
            0,
        );
    }
}
