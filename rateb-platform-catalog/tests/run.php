<?php

declare(strict_types=1);

if (!defined('RATEB_CATALOG_TESTING')) {
    define('RATEB_CATALOG_TESTING', true);
}

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Invalid catalog root\n");
    exit(1);
}

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\PlatformCatalog\Core\Bootstrap::initMinimal($root);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Rateb\\PlatformCatalog\\Tests\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    $file = $root . '/tests/' . $relative;
    if (is_file($file)) {
        require_once $file;
    }
});

require_once $root . '/tests/Support/ConfigurablePolicyGuard.php';
require $root . '/tests/Support/SessionRbacPolicyGuardFactory.php';
require_once $root . '/tests/Support/StubSearchIndexReadRepository.php';

$corePassed = 0;
$coreFailures = 0;
$coreSkipped = 0;
$adapterPassed = 0;
$adapterFailures = 0;
$adapterSkipped = 0;
$catalogTestContext = 'core';

/** @param callable(): void $test */
function catalog_test(string $name, callable $test): void
{
    global $coreFailures, $corePassed, $coreSkipped;
    global $adapterFailures, $adapterPassed, $adapterSkipped, $catalogTestContext;

    ob_start();
    try {
        $test();
        $output = ob_get_clean();
        if ($output !== false && $output !== '') {
            echo $output;
        }

        if ($catalogTestContext === 'adapter') {
            if ($output !== false && str_contains($output, '[SKIP]')) {
                $adapterSkipped++;

                return;
            }

            echo "[PASS] {$name}\n";
            $adapterPassed++;

            return;
        }

        if ($output !== false && str_contains($output, '[SKIP]')) {
            $coreSkipped++;

            return;
        }

        echo "[PASS] {$name}\n";
        $corePassed++;
    } catch (Throwable $e) {
        $output = ob_get_clean();
        if ($output !== false && $output !== '') {
            echo $output;
        }

        echo "[FAIL] {$name} — {$e->getMessage()}\n";
        if ($catalogTestContext === 'adapter') {
            $adapterFailures++;
        } else {
            $coreFailures++;
        }
    }
}

function catalog_assert_true(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function catalog_assert_same(mixed $expected, mixed $actual, string $message = 'Values are not identical'): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
    }
}

function catalog_assert_false(bool $condition, string $message = 'Expected false'): void
{
    if ($condition) {
        throw new RuntimeException($message);
    }
}

require $root . '/tests/Unit/Core/ContainerTest.php';
require $root . '/tests/Unit/Core/RouterTest.php';
require $root . '/tests/Unit/Events/EventDispatcherTest.php';
require $root . '/tests/Unit/Support/RequestTest.php';
require $root . '/tests/Unit/Services/HealthServiceTest.php';
require $root . '/tests/Unit/Services/Sprint2EnterpriseTest.php';
require $root . '/tests/Unit/Migrations/MigrationRunnerTest.php';
require $root . '/tests/Unit/Mappers/TaxonomyMapperTest.php';
require $root . '/tests/Unit/Services/TaxonomyServiceTest.php';
require $root . '/tests/Unit/Mappers/FamilyAttributeMapperTest.php';
require $root . '/tests/Unit/Support/LocaleMetaBuilderTest.php';
require $root . '/tests/Unit/Services/FamilyAttributeServiceTest.php';
require $root . '/tests/Unit/Mappers/ProductMapperTest.php';
require $root . '/tests/Unit/Services/ProductServiceTest.php';
require $root . '/tests/Unit/Validators/BundleCircularReferenceValidatorTest.php';
require $root . '/tests/Unit/Mappers/ProductRelationshipMapperTest.php';
require $root . '/tests/Unit/Services/ProductRelationshipServiceTest.php';
require $root . '/tests/Unit/Support/MediaStorageKeyBuilderTest.php';
require $root . '/tests/Unit/Storage/LocalStorageAdapterTest.php';
require $root . '/tests/Unit/Storage/SignedUrlTest.php';
require $root . '/tests/Unit/Storage/S3CompatibleAdapterTest.php';
require $root . '/tests/Unit/Storage/StorageMimeResolverTest.php';
require $root . '/tests/Unit/Validators/UploadValidatorTest.php';
require $root . '/tests/Unit/Middleware/IdempotencyMiddlewareTest.php';
require $root . '/tests/Unit/Middleware/IdempotencyRemediationTest.php';
require $root . '/tests/Unit/Mappers/MediaMapperTest.php';
require $root . '/tests/Unit/Services/MediaServiceTest.php';
require $root . '/tests/Unit/Services/VideoServiceTest.php';
require $root . '/tests/Unit/Support/ArabicNormalizerTest.php';
require $root . '/tests/Unit/Queue/RetryPolicyTest.php';
require $root . '/tests/Unit/Search/MeilisearchAdapterTest.php';
require $root . '/tests/Unit/Search/DatabaseSearchAdapterTest.php';
require $root . '/tests/Unit/Search/SearchAdapterFactoryTest.php';
require $root . '/tests/Unit/Repositories/MysqlSearchIndexReadRepositoryTest.php';
require $root . '/tests/Unit/Services/SearchQueryServiceTest.php';
require $root . '/tests/Unit/Services/SearchIndexerServiceTest.php';
require $root . '/tests/Unit/Queue/QueueAdapterTest.php';
require $root . '/tests/Unit/Events/SearchEventWiringTest.php';
require $root . '/tests/Integration/bootstrap.php';
require $root . '/tests/Unit/Enterprise/Phase28EnterpriseVerificationTest.php';
require $root . '/tests/Unit/Services/WorkflowPhase28Test.php';
require $root . '/tests/Unit/Services/WorkflowHistoryServiceTest.php';
require $root . '/tests/Unit/Services/ProductSeoServiceTest.php';
require $root . '/tests/Unit/Services/RbacAdminServiceTest.php';
require $root . '/tests/Unit/Services/ScheduledPublishServiceTest.php';
require $root . '/tests/Unit/Security/GatewayTrustTest.php';
require $root . '/tests/Unit/Security/SystemUserRbacProtectionTest.php';
require $root . '/tests/Integration/Phase28EnterpriseTest.php';
require $root . '/tests/Integration/ProductSeoIntegrationTest.php';
require $root . '/tests/Integration/ProductSnapshotRestoreIntegrationTest.php';
require $root . '/tests/Integration/ScheduledPublishIntegrationTest.php';
require $root . '/tests/Integration/SearchQueueIntegrationTest.php';
require $root . '/tests/Integration/IdempotencyIntegrationTest.php';
require $root . '/tests/Integration/DatabaseSearchIntegrationTest.php';

$adapterSuiteRequested = in_array(
    strtolower((string) (getenv('CATALOG_ADAPTER_TESTS') ?: '')),
    ['meilisearch', 's3'],
    true
);
$adapterSuiteExecuted = false;

if ($adapterSuiteRequested) {
    $adapterSuiteExecuted = true;
    $catalogTestContext = 'adapter';
    require $root . '/tests/Integration/Adapters/bootstrap.php';

    if (catalog_adapter_tests_enabled('meilisearch')) {
        require $root . '/tests/Integration/Adapters/Meilisearch/MeilisearchAdapterIntegrationTest.php';
    }

    if (catalog_adapter_tests_enabled('s3')) {
        require $root . '/tests/Integration/Adapters/S3/S3CompatibleAdapterIntegrationTest.php';
    }
}

echo PHP_EOL;
echo 'Core Tests:' . PHP_EOL;
echo "{$corePassed} PASS" . PHP_EOL;
echo "{$coreFailures} FAIL" . PHP_EOL;
echo "{$coreSkipped} SKIP" . PHP_EOL;
echo PHP_EOL;
echo 'Optional Adapter Tests:' . PHP_EOL;

if (!$adapterSuiteExecuted) {
    echo 'Not Executed' . PHP_EOL;
} else {
    echo "{$adapterPassed} PASS" . PHP_EOL;
    echo "{$adapterFailures} FAIL" . PHP_EOL;
    echo "{$adapterSkipped} SKIP" . PHP_EOL;
}

$exitCode = ($coreFailures + $adapterFailures) > 0 ? 1 : 0;
exit($exitCode);
