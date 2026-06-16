<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Cli;

use RATEB\InfrastructureMarketplace\Audit\Deployment\DeploymentAuditReporter;
use RATEB\InfrastructureMarketplace\Health\PrelaunchHealthService;
use RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory;
use RATEB\InfrastructureMarketplace\Release\Deployment\ReleaseGateEvaluator;
use RATEB\InfrastructureMarketplace\Release\Deployment\RollbackChecklistGenerator;

final class InfrastructureLaunchVerifier
{
    /**
     * @param list<string> $argv
     */
    public static function main(array $argv): int
    {
        require_once dirname(__DIR__) . '/bootstrap.php';

        $opts = self::parseArgs($argv);
        $pdo = DatabaseConnectionFactory::createPdo();
        $report = (new PrelaunchHealthService($pdo))->run();
        $gate = (new ReleaseGateEvaluator())->evaluate($report, (bool) $opts['strict']);

        $releaseId = (string) ($opts['release'] ?? getenv('RATEB_INFRA_RELEASE_ID') ?: ('manual-' . date('Ymd-His')));
        $env = (string) ($opts['environment'] ?? getenv('RATEB_INFRA_RELEASE_ENV') ?: 'production');
        (new DeploymentAuditReporter($pdo))->record($releaseId, $env, $report);

        if ((bool) $opts['json']) {
            echo json_encode([
                'release_id' => $releaseId,
                'environment' => $env,
                'filters' => [
                    'tenant' => $opts['tenant'],
                    'provider' => $opts['provider'],
                    'dry_run_flag' => $opts['dry-run'],
                ],
                'report' => $report,
                'gate' => $gate,
                'rollback_checklist' => (new RollbackChecklistGenerator())->generate($releaseId),
            ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } else {
            self::printHuman($releaseId, $env, $report, $gate, $opts);
        }

        return $gate['pass'] ? 0 : 2;
    }

    /**
     * @param list<string> $argv
     * @return array<string, mixed>
     */
    private static function parseArgs(array $argv): array
    {
        $opts = [
            'json' => false,
            'strict' => false,
            'tenant' => null,
            'provider' => null,
            'dry-run' => false,
            'release' => null,
            'environment' => null,
        ];
        foreach ($argv as $arg) {
            if ($arg === '--json') {
                $opts['json'] = true;
            } elseif ($arg === '--strict') {
                $opts['strict'] = true;
            } elseif ($arg === '--dry-run') {
                $opts['dry-run'] = true;
            } elseif (str_starts_with($arg, '--tenant=')) {
                $opts['tenant'] = (int) substr($arg, 9);
            } elseif (str_starts_with($arg, '--provider=')) {
                $opts['provider'] = substr($arg, 11);
            } elseif (str_starts_with($arg, '--release=')) {
                $opts['release'] = substr($arg, 10);
            } elseif (str_starts_with($arg, '--environment=')) {
                $opts['environment'] = substr($arg, 14);
            }
        }
        return $opts;
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $gate
     */
    private static function printHuman(string $releaseId, string $env, array $report, array $gate, array $opts): void
    {
        echo 'Infrastructure Launch Verifier' . PHP_EOL;
        echo 'Release: ' . $releaseId . ' | Env: ' . $env . PHP_EOL;
        echo 'Filters: tenant=' . (string) ($opts['tenant'] ?? 'all')
            . ', provider=' . (string) ($opts['provider'] ?? 'all')
            . ', dry-run=' . ((bool) ($opts['dry-run'] ?? false) ? 'true' : 'false') . PHP_EOL;
        echo 'Status: ' . (string) ($report['status'] ?? 'UNKNOWN') . ' | Score: ' . (int) ($report['score'] ?? 0) . PHP_EOL;
        echo 'PASS/WARN/FAIL: '
            . (int) (($report['matrix']['PASS'] ?? 0)) . '/'
            . (int) (($report['matrix']['WARN'] ?? 0)) . '/'
            . (int) (($report['matrix']['FAIL'] ?? 0)) . PHP_EOL;
        echo PHP_EOL . 'Recommendations:' . PHP_EOL;
        foreach ((array) ($report['recommendations'] ?? []) as $line) {
            echo '- ' . (string) $line . PHP_EOL;
        }
        if (!$gate['pass']) {
            echo PHP_EOL . 'Gate blockers:' . PHP_EOL;
            foreach ((array) ($gate['reasons'] ?? []) as $reason) {
                echo '- ' . (string) $reason . PHP_EOL;
            }
        }
    }
}

if (PHP_SAPI === 'cli' && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    exit(InfrastructureLaunchVerifier::main(array_slice($argv, 1)));
}

