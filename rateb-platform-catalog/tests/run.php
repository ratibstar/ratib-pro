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

$passed = 0;
$failures = 0;
$skipped = 0;

/** @param callable(): void $test */
function catalog_test(string $name, callable $test): void
{
    global $failures, $passed, $skipped;

    ob_start();
    try {
        $test();
        $output = ob_get_clean();
        if ($output !== false && $output !== '') {
            echo $output;
        }

        if ($output !== false && str_contains($output, '[SKIP]')) {
            $skipped++;

            return;
        }

        echo "[PASS] {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        $output = ob_get_clean();
        if ($output !== false && $output !== '') {
            echo $output;
        }

        echo "[FAIL] {$name} — {$e->getMessage()}\n";
        $failures++;
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
require $root . '/tests/Unit/Mappers/MediaMapperTest.php';
require $root . '/tests/Unit/Services/MediaServiceTest.php';
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
require $root . '/tests/Integration/DatabaseSearchIntegrationTest.php';

echo PHP_EOL . "Passed: {$passed}, Failed: {$failures}, Skipped: {$skipped}" . PHP_EOL;
exit($failures > 0 ? 1 : 0);
