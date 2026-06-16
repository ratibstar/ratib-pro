<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Reports;

use RATEB\InfrastructureMarketplace\Infrastructure\SchemaHelpers;

/**
 * Phase 1 — system audit & auto-detection for infrastructure-marketplace (+ notes for client-dashboard).
 *
 * Classifies subsystems: EXISTS_COMPLETE | EXISTS_PARTIAL | MISSING | LEGACY_CONFLICT | NEEDS_REFACTOR.
 * Outputs machine-readable JSON and HTML (no DB writes).
 *
 * CLI: `php modules/infrastructure-marketplace/Reports/ArchitectureAuditReport.php`
 *       [--json=path] [--html=path]   (default: writes next to this file)
 *
 * Programmatic:
 *   $r = ArchitectureAuditReport::build(null);
 *   file_put_contents('audit.json', ArchitectureAuditReport::toJson($r));
 */
final class ArchitectureAuditReport
{
    public const STATUS_EXISTS_COMPLETE = 'EXISTS_COMPLETE';
    public const STATUS_EXISTS_PARTIAL = 'EXISTS_PARTIAL';
    public const STATUS_MISSING = 'MISSING';
    public const STATUS_LEGACY_CONFLICT = 'LEGACY_CONFLICT';
    public const STATUS_NEEDS_REFACTOR = 'NEEDS_REFACTOR';

    private const MODULE_ROOT = __DIR__ . '/..';

    /**
     * @return array<string, mixed>
     */
    public static function build(?\PDO $pdo = null): array
    {
        $root = self::MODULE_ROOT;
        $clientRoot = dirname($root, 2) . '/client-dashboard';

        $subsystems = self::evaluateSubsystems($root, $clientRoot, $pdo);

        $reused = self::deriveReused($subsystems);
        $newInMission = [
            [
                'id' => 'architecture_audit_report',
                'description' => 'Phase 1 — automated subsystem classification + JSON/HTML export',
                'path' => 'modules/infrastructure-marketplace/Reports/ArchitectureAuditReport.php',
            ],
        ];
        $missingEnv = self::detectMissingEnv();
        $missingWorkers = self::detectMissingWorkers($subsystems, $pdo);
        $activationChecklist = self::activationChecklist($subsystems, $missingEnv, $missingWorkers);
        $prodReadiness = self::productionReadinessScores($subsystems, $missingEnv, $missingWorkers);
        $backwardCompat = self::backwardCompatibilityNotes();
        $rolloutRisk = self::rolloutRiskAnalysis($subsystems, $prodReadiness);
        $migrationSafety = self::migrationSafetyReport($pdo);

        return [
            'schema_version' => '1.0',
            'generated_at' => gmdate('c'),
            'php_version' => PHP_VERSION,
            'module_root' => realpath($root) ?: $root,
            '1_architecture_audit' => $subsystems,
            '2_reused_systems' => $reused,
            '3_newly_created_systems' => $newInMission,
            '4_missing_environment_variables' => $missingEnv,
            '5_missing_workers_services' => $missingWorkers,
            '6_runtime_activation_checklist' => $activationChecklist,
            '7_production_readiness' => $prodReadiness,
            '8_backward_compatibility_verification' => $backwardCompat,
            '9_rollout_risk_analysis' => $rolloutRisk,
            '10_migration_safety_report' => $migrationSafety,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function toJson(array $report): string
    {
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json !== false ? $json : '{"error":"json_encode_failed"}';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function toHtml(array $report): string
    {
        $title = 'Infrastructure marketplace — architecture audit';
        $esc = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $rows = '';
        foreach ($report['1_architecture_audit'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $st = (string) ($row['status'] ?? '');
            $cls = 'st-' . strtolower(str_replace('_', '-', $st));
            $ev = $row['evidence'] ?? [];
            $evList = is_array($ev) && $ev !== [] ? '<ul class="evidence"><li>' . $esc(implode('</li><li>', array_map('strval', $ev))) . '</li></ul>' : '—';
            $gaps = $row['gaps'] ?? [];
            $gapList = is_array($gaps) && $gaps !== [] ? '<ul class="gaps"><li>' . $esc(implode('</li><li>', array_map('strval', $gaps))) . '</li></ul>' : '—';
            $rows .= '<tr class="' . $esc($cls) . '">';
            $rows .= '<td><code>' . $esc((string) ($row['id'] ?? '')) . '</code></td>';
            $rows .= '<td>' . $esc((string) ($row['name'] ?? '')) . '</td>';
            $rows .= '<td><span class="badge">' . $esc($st) . '</span></td>';
            $rows .= '<td>' . $evList . '</td>';
            $rows .= '<td>' . $gapList . '</td>';
            $rows .= '</tr>';
        }

        $sections = '';
        foreach ([
            '2_reused_systems' => 'Reused systems',
            '3_newly_created_systems' => 'Newly created (this mission)',
            '4_missing_environment_variables' => 'Missing / unset env (warnings)',
            '5_missing_workers_services' => 'Missing workers / services',
            '6_runtime_activation_checklist' => 'Runtime activation checklist',
            '7_production_readiness' => 'Production readiness',
            '8_backward_compatibility_verification' => 'Backward compatibility',
            '9_rollout_risk_analysis' => 'Rollout risk',
            '10_migration_safety_report' => 'Migration safety',
        ] as $key => $heading) {
            $sections .= '<h2>' . $esc($heading) . '</h2><pre class="json-block">' . $esc(self::toJson([$key => $report[$key] ?? []])) . '</pre>';
        }

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $esc($title) . '</title><style>
body{font-family:system-ui,Segoe UI,sans-serif;margin:1.25rem;background:#0f172a;color:#e2e8f0;line-height:1.45}
h1{font-size:1.35rem;color:#f8fafc}
h2{font-size:1.05rem;margin-top:1.75rem;color:#cbd5e1;border-bottom:1px solid #334155;padding-bottom:.35rem}
table{width:100%;border-collapse:collapse;margin:1rem 0;font-size:.88rem}
th,td{border:1px solid #334155;padding:.45rem .5rem;vertical-align:top}
th{background:#1e293b;text-align:left}
.badge{font-weight:600;font-size:.75rem}
.st-exists-complete .badge{color:#86efac}
.st-exists-partial .badge{color:#fde047}
.st-missing .badge{color:#fca5a5}
.st-needs-refactor .badge{color:#fdba74}
.st-legacy-conflict .badge{color:#f472b6}
ul.evidence,ul.gaps{margin:0;padding-left:1.1rem}
.json-block{background:#020617;border:1px solid #334155;padding:.75rem;border-radius:8px;overflow:auto;max-height:320px;font-size:.78rem}
.meta{color:#94a3b8;font-size:.85rem;margin-bottom:1rem}
</style></head><body>'
            . '<h1>' . $esc($title) . '</h1>'
            . '<p class="meta">Generated: ' . $esc((string) ($report['generated_at'] ?? '')) . ' · PHP ' . $esc((string) ($report['php_version'] ?? '')) . '</p>'
            . '<h2>Subsystem matrix</h2><table><thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Evidence</th><th>Gaps / next steps</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . $sections
            . '</body></html>';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function evaluateSubsystems(string $root, string $clientRoot, ?\PDO $pdo): array
    {
        $f = static function (string $rel): bool {
            return is_file(self::MODULE_ROOT . '/' . $rel);
        };
        $d = static function (string $rel): bool {
            return is_dir(self::MODULE_ROOT . '/' . $rel);
        };
        $cf = static function (string $abs): bool {
            return is_file($abs);
        };

        $table = static function (?\PDO $pdo, string $t): ?bool {
            if ($pdo === null) {
                return null;
            }
            try {
                return SchemaHelpers::tableExists($pdo, $t);
            } catch (\Throwable $e) {
                return null;
            }
        };

        $rows = [];

        $add = static function (array $row) use (&$rows): void {
            $rows[] = $row;
        };

        $catalogRepo = $f('Catalog/CatalogRepository.php');
        $catalogTable = $table($pdo, 'rateb_infra_catalog_items');
        $productsTable = $table($pdo, 'rateb_infra_products');
        $plansTable = $table($pdo, 'rateb_infra_plans');
        $catalogSt = self::STATUS_EXISTS_PARTIAL;
        $catalogGaps = [
            'Commerce tables rateb_infra_products / rateb_infra_plans / rateb_infra_plan_features / rateb_infra_pricing not in baseline schema — add via additive migrations when ready.',
        ];
        if ($productsTable === true && $plansTable === true) {
            $catalogSt = self::STATUS_EXISTS_COMPLETE;
            $catalogGaps = [];
        } elseif (!$catalogRepo) {
            $catalogSt = self::STATUS_MISSING;
            $catalogGaps = ['CatalogRepository missing.'];
        }
        $catalogEvidence = array_values(array_filter([
            $catalogRepo ? 'Catalog/CatalogRepository.php' : null,
            $catalogTable === true ? 'DB: rateb_infra_catalog_items' : null,
            $catalogTable === null && $pdo === null ? 'DB: skipped (no PDO)' : null,
        ]));
        $add([
            'id' => 'product_catalog',
            'name' => 'Product catalog',
            'status' => $catalogSt,
            'evidence' => $catalogEvidence,
            'gaps' => $catalogGaps,
        ]);

        $add([
            'id' => 'hosting_plans',
            'name' => 'Hosting plans (commerce SKUs)',
            'status' => $plansTable === true ? self::STATUS_EXISTS_COMPLETE : self::STATUS_MISSING,
            'evidence' => $f('Catalog/Pricing/PricingEngine.php') ? ['Catalog/Pricing/PricingEngine.php'] : [],
            'gaps' => $plansTable === true ? [] : ['Add rateb_infra_plans (+ migrations) or extend catalog_items with plan metadata without breaking APIs.'],
        ]);

        $add([
            'id' => 'billing_mappings',
            'name' => 'Billing mappings / settlement hooks',
            'status' => $f('Billing/InfrastructureBillingSynchronizer.php') && $f('Billing/Listeners/ProvisionAfterSettlementListener.php')
                ? self::STATUS_EXISTS_PARTIAL
                : self::STATUS_MISSING,
            'evidence' => array_values(array_filter([
                $f('Billing/InfrastructureBillingSynchronizer.php') ? 'Billing sync' : null,
                $f('Billing/BillingHookRegistry.php') ? 'BillingHookRegistry' : null,
            ])),
            'gaps' => ['Verify N-Genius / control billing integration end-to-end per tenant.'],
        ]);

        $add([
            'id' => 'provisioning_workflows',
            'name' => 'Provisioning workflows',
            'status' => $f('Provisioning/Execution/ProvisioningExecutionEngine.php') ? self::STATUS_EXISTS_COMPLETE : self::STATUS_MISSING,
            'evidence' => ['ProvisioningExecutionEngine.php', 'OperationalSafetyGuard.php'],
            'gaps' => [],
        ]);

        $add([
            'id' => 'worker_runtime',
            'name' => 'Worker runtime',
            'status' => $f('Workers/InfrastructureProvisioningWorker.php') ? self::STATUS_EXISTS_COMPLETE : self::STATUS_MISSING,
            'evidence' => ['Workers/InfrastructureProvisioningWorker.php', 'Workers/bootstrap.php'],
            'gaps' => ['No separate QueueSupervisor.php / WorkerHeartbeat.php classes (heartbeat SQL embedded in worker).'],
        ]);

        $add([
            'id' => 'queue_consumers',
            'name' => 'Queue consumers (database / sync)',
            'status' => $f('Provisioning/Queue/DatabaseQueueDispatcher.php') ? self::STATUS_EXISTS_COMPLETE : self::STATUS_MISSING,
            'evidence' => ['DatabaseQueueDispatcher.php', 'SyncQueueDispatcher.php'],
            'gaps' => [],
        ]);

        $servicesTable = $table($pdo, 'rateb_infra_services');
        $add([
            'id' => 'tenant_resources',
            'name' => 'Tenant resource ownership',
            'status' => $servicesTable === true ? self::STATUS_EXISTS_PARTIAL : ($servicesTable === false ? self::STATUS_MISSING : self::STATUS_EXISTS_PARTIAL),
            'evidence' => array_values(array_filter([
                $f('Provisioning/Execution/OrphanResourceReconciler.php') ? 'OrphanResourceReconciler' : null,
                $servicesTable === true ? 'DB: rateb_infra_services' : null,
            ])),
            'gaps' => [
                'rateb_tenant_resources table + TenantResourceManager.php not present — extend additively alongside rateb_infra_services.',
            ],
        ]);

        $add([
            'id' => 'website_builder',
            'name' => 'Website builder',
            'status' => $d('Websites') ? self::STATUS_EXISTS_PARTIAL : self::STATUS_MISSING,
            'evidence' => $d('Websites') ? ['Websites/'] : [],
            'gaps' => ['Create Websites/ + AIBuilder/ modules per roadmap; integrate via existing orchestrator.'],
        ]);

        $add([
            'id' => 'email_providers',
            'name' => 'Email platform (Workspace / M365 / Zoho)',
            'status' => $d('Email') ? self::STATUS_EXISTS_PARTIAL : self::STATUS_MISSING,
            'evidence' => [],
            'gaps' => ['Add Email/ provider abstraction; no breaking changes to registrar/DNS APIs.'],
        ]);

        $add([
            'id' => 'customer_product_dashboard',
            'name' => 'Customer product dashboard (client-dashboard)',
            'status' => $cf($clientRoot . '/bootstrap.php') ? self::STATUS_EXISTS_PARTIAL : self::STATUS_MISSING,
            'evidence' => array_values(array_filter([
                $cf($clientRoot . '/Adapters/InfrastructureAdapter.php') ? 'client-dashboard/InfrastructureAdapter' : null,
                $cf($clientRoot . '/Data/SnapshotBuilder.php') ? 'SnapshotBuilder' : null,
            ])),
            'gaps' => ['Optional widgets (My Domains, My Hosting, …) — add as additive snapshot keys with graceful fallback.'],
        ]);

        $add([
            'id' => 'domain_lifecycle',
            'name' => 'Domain lifecycle management',
            'status' => $f('Registrars/Lifecycle/DomainLifecycleManager.php') ? self::STATUS_EXISTS_PARTIAL : self::STATUS_MISSING,
            'evidence' => ['DomainLifecycleManager.php', 'NamecheapRegistrarAdapter.php'],
            'gaps' => ['Renewal scheduler / redemption / transfer tracking: extend DomainLifecycleManager; avoid new routes for existing APIs.'],
        ]);

        $add([
            'id' => 'dns_orchestration',
            'name' => 'DNS orchestration',
            'status' => $f('DNS/Orchestration/DnsOrchestrationService.php') ? self::STATUS_EXISTS_PARTIAL : self::STATUS_MISSING,
            'evidence' => ['DnsOrchestrationService.php', 'CloudflareDnsAdapter.php'],
            'gaps' => ['Zone onboarding + propagation verification: extend DnsOrchestrationService behind feature flags.'],
        ]);

        $add([
            'id' => 'ssl_lifecycle',
            'name' => 'SSL lifecycle',
            'status' => $f('SSL/Lifecycle/CertificateLifecycleManager.php') ? self::STATUS_EXISTS_PARTIAL : self::STATUS_MISSING,
            'evidence' => ['CertificateLifecycleManager.php', 'SslExpirationMonitor.php'],
            'gaps' => ['Renewal automation + reconciliation: extend existing managers.'],
        ]);

        $add([
            'id' => 'provider_registry',
            'name' => 'Provider registry',
            'status' => $f('Services/ProviderRegistry.php') ? self::STATUS_EXISTS_COMPLETE : self::STATUS_MISSING,
            'evidence' => ['ProviderRegistry.php', 'ProviderActivationRegistry.php'],
            'gaps' => [],
        ]);

        $add([
            'id' => 'commerce_activation',
            'name' => 'Commerce activation layer',
            'status' => $f('Providers/Activation/ProviderActivationRegistry.php') ? self::STATUS_EXISTS_COMPLETE : self::STATUS_MISSING,
            'evidence' => ['ProviderActivationRegistry.php'],
            'gaps' => [],
        ]);

        $states = $f('Provisioning/Lifecycle/ProvisioningState.php');
        $sm = $f('Provisioning/StateMachine/ProvisioningStateMachine.php');
        $validator = $f('Provisioning/Lifecycle/StateTransitionValidator.php');
        $expectedCommerceStates = ['ORDERED', 'PAYMENT_PENDING', 'PAYMENT_CONFIRMED', 'VALIDATING', 'PROVISIONING', 'DNS_SETUP', 'SSL_ISSUED'];
        $currentStates = $states ? 'PENDING,QUEUED,RUNNING,RETRYING,WAITING_EXTERNAL,COMPLETED,FAILED,DEAD_LETTER,RECONCILING,CANCELLED' : '';
        $add([
            'id' => 'provisioning_state_machine',
            'name' => 'Provisioning state machine',
            'status' => $sm && $validator ? self::STATUS_NEEDS_REFACTOR : self::STATUS_EXISTS_PARTIAL,
            'evidence' => array_values(array_filter([$sm ? 'ProvisioningStateMachine.php' : null, $states ? 'ProvisioningState.php' : null])),
            'gaps' => [
                'Current job states are queue-oriented (' . $currentStates . ').',
                'Commerce-oriented states (' . implode(', ', $expectedCommerceStates) . ', …) should be mapped additively (aliases or super-state) without renaming DB enum columns in use.',
            ],
        ]);

        $add([
            'id' => 'resource_ownership_tracking',
            'name' => 'Resource ownership tracking',
            'status' => $servicesTable === true ? self::STATUS_EXISTS_PARTIAL : self::STATUS_MISSING,
            'evidence' => $servicesTable === true ? ['rateb_infra_services'] : [],
            'gaps' => ['Align with proposed rateb_tenant_resources for cross-product ownership.'],
        ]);

        $add([
            'id' => 'feature_flags',
            'name' => 'Feature flags / runtime controls',
            'status' => $f('Config/RuntimeOverrideStore.php') && $f('Config/ModuleConfig.php') ? self::STATUS_EXISTS_COMPLETE : self::STATUS_EXISTS_PARTIAL,
            'evidence' => ['ModuleConfig.php', 'RuntimeOverrideStore.php'],
            'gaps' => [],
        ]);

        $add([
            'id' => 'capability_system',
            'name' => 'Capability discovery',
            'status' => $f('Providers/Capabilities/CapabilityDiscoveryService.php') ? self::STATUS_EXISTS_COMPLETE : self::STATUS_MISSING,
            'evidence' => ['CapabilityDiscoveryService.php'],
            'gaps' => [],
        ]);

        $add([
            'id' => 'dry_run_observability_audit',
            'name' => 'Dry-run, observability, audit, correlation_id, tenant isolation',
            'status' => self::STATUS_EXISTS_PARTIAL,
            'evidence' => [
                'dry_run: ModuleConfig + ProvisioningExecutionEngine',
                'correlation_id: ProvisioningJob + job repository',
                'audit: InfrastructureAuditLogger, rateb_infra_audit_entries',
                'observability: InfrastructureMetrics, InfrastructureEventEmitter',
                'tenant: TenantContext, TenantIsolationCompliance',
            ],
            'gaps' => ['Ensure every new subsystem uses same patterns; add trace_id propagation where missing.'],
        ]);

        $add([
            'id' => 'hosting_cpanel',
            'name' => 'Hosting automation (cPanel/WHM)',
            'status' => $f('Hosting/Adapters/CpanelWhmAdapter.php') ? self::STATUS_EXISTS_PARTIAL : self::STATUS_MISSING,
            'evidence' => ['CpanelWhmAdapter.php (create/suspend/unsuspend/terminate/listPackages/usageMetrics)'],
            'gaps' => ['Env uses RATEB_INFRA_CPANEL_BASE_URL / USERNAME / API_TOKEN (not *_URL alone). Extend quota sync if product requires it.'],
        ]);

        $add([
            'id' => 'governance_client_dashboard',
            'name' => 'Governance overlay (client-dashboard)',
            'status' => $cf($clientRoot . '/Governance/GovernanceFacade.php') ? self::STATUS_EXISTS_COMPLETE : self::STATUS_MISSING,
            'evidence' => ['GovernanceFacade.php', 'Policy/PolicyEngine.php'],
            'gaps' => ['Wire new infra traces into GovernanceFacade without breaking snapshot contracts.'],
        ]);

        $add([
            'id' => 'auto_repair',
            'name' => 'Safe auto-repair',
            'status' => self::STATUS_MISSING,
            'evidence' => [],
            'gaps' => ['Explicitly out of scope for silent auto-mutation; use read-only audit + operator-approved repair jobs.'],
        ]);

        $add([
            'id' => 'schema_docs_naming',
            'name' => 'Schema documentation vs tables',
            'status' => $f('Database/README.schema-notes.txt') ? self::STATUS_LEGACY_CONFLICT : self::STATUS_MISSING,
            'evidence' => ['Database/README.schema-notes.txt mentions rateb_infra_catalog_item (singular); code uses rateb_infra_catalog_items.'],
            'gaps' => ['Align documentation with MigrationVerifier list; do not rename live tables.'],
        ]);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $subsystems
     * @return list<string>
     */
    private static function deriveReused(array $subsystems): array
    {
        $out = [];
        foreach ($subsystems as $s) {
            if (!is_array($s)) {
                continue;
            }
            $st = (string) ($s['status'] ?? '');
            if ($st === self::STATUS_EXISTS_COMPLETE || $st === self::STATUS_EXISTS_PARTIAL) {
                $out[] = (string) ($s['id'] ?? '') . ':' . $st;
            }
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    private static function detectMissingEnv(): array
    {
        $checks = [
            'RATEB_INFRA_MARKETPLACE_ENABLED' => 'Module master flag',
            'RATEB_INFRA_CPANEL_BASE_URL' => 'cPanel/WHM API base (also check runtime override)',
            'RATEB_INFRA_CPANEL_USERNAME' => 'cPanel user',
            'RATEB_INFRA_CPANEL_API_TOKEN' => 'cPanel API token',
        ];
        $missing = [];
        foreach ($checks as $key => $label) {
            $v = getenv($key);
            if ($key === 'RATEB_INFRA_MARKETPLACE_ENABLED') {
                continue;
            }
            if (!is_string($v) || trim($v) === '') {
                $missing[] = $key . ' (' . $label . ') unset or empty';
            }
        }
        return $missing;
    }

    /**
     * @param list<array<string, mixed>> $subsystems
     * @return list<string>
     */
    private static function detectMissingWorkers(array $subsystems, ?\PDO $pdo): array
    {
        $notes = [];
        $notes[] = 'Run `php modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php` (or systemd) when queue driver is database/redis.';
        if ($pdo !== null) {
            try {
                if (!SchemaHelpers::tableExists($pdo, 'rateb_infra_worker_heartbeats')) {
                    $notes[] = 'Table rateb_infra_worker_heartbeats missing — worker health not persisted.';
                }
            } catch (\Throwable $e) {
                $notes[] = 'Could not verify worker_heartbeats table: ' . $e->getMessage();
            }
        }
        foreach ($subsystems as $s) {
            if (is_array($s) && ($s['id'] ?? '') === 'worker_runtime') {
                if (($s['status'] ?? '') === self::STATUS_MISSING) {
                    $notes[] = 'InfrastructureProvisioningWorker.php not found — critical.';
                }
            }
        }
        return $notes;
    }

    /**
     * @param list<array<string, mixed>> $subsystems
     * @param list<string> $missingEnv
     * @param list<string> $missingWorkers
     * @return list<array{step:string,done:bool,notes:string}>
     */
    private static function activationChecklist(array $subsystems, array $missingEnv, array $missingWorkers): array
    {
        return [
            ['step' => 'Enable module + dry-run pilot', 'done' => false, 'notes' => 'Use control panel Infrastructure → Control; confirm RATEB_INFRA_MARKETPLACE_ENABLED.'],
            ['step' => 'Apply SQL migrations (control DB)', 'done' => false, 'notes' => 'See modules/infrastructure-marketplace Migrations bundle used by your DBA.'],
            ['step' => 'Configure providers + activations', 'done' => false, 'notes' => 'Providers tab; Namecheap/Cloudflare/LE credentials.'],
            ['step' => 'Start queue worker', 'done' => false, 'notes' => implode(' ', $missingWorkers)],
            ['step' => 'Resolve env gaps', 'done' => count($missingEnv) === 0, 'notes' => implode('; ', $missingEnv) ?: 'Core env looks present (non-exhaustive).'],
            ['step' => 'Verify prelaunch health API', 'done' => false, 'notes' => '/api/infrastructure-marketplace/prelaunch-health.php'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $subsystems
     * @return array<string, mixed>
     */
    private static function productionReadinessScores(array $subsystems, array $missingEnv, array $missingWorkers): array
    {
        $weights = [self::STATUS_EXISTS_COMPLETE => 1.0, self::STATUS_EXISTS_PARTIAL => 0.55, self::STATUS_NEEDS_REFACTOR => 0.45, self::STATUS_MISSING => 0.0, self::STATUS_LEGACY_CONFLICT => 0.2];
        $n = 0;
        $score = 0.0;
        foreach ($subsystems as $s) {
            if (!is_array($s)) {
                continue;
            }
            $n++;
            $score += $weights[(string) ($s['status'] ?? '')] ?? 0.3;
        }
        $readiness = $n > 0 ? round(100 * $score / $n, 1) : 0.0;
        $risk = round(100 - $readiness - min(20, count($missingEnv) * 3) - min(15, max(0, count($missingWorkers) - 1) * 2), 1);
        $risk = max(0.0, min(100.0, $risk));

        return [
            'readiness_score_0_100' => $readiness,
            'operational_risk_score_0_100' => $risk,
            'deployment_warnings' => array_merge(
                count($missingEnv) > 0 ? ['Missing recommended env vars — see section 4.'] : [],
                ['Commerce SKUs (products/plans tables) not standardised yet — see product_catalog / hosting_plans rows.']
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function backwardCompatibilityNotes(): array
    {
        return [
            'api_routes' => 'Do not rename files under api/infrastructure-marketplace/; additive query/body keys only.',
            'payload_fields' => 'ProvisioningJob + order payloads retain existing JSON keys; extend with optional nested objects.',
            'database' => 'New tables must be additive; avoid ALTER breaking NOT NULL without defaults.',
            'client_dashboard' => 'Snapshot payloads remain backward compatible when new keys are optional.',
        ];
    }

    /**
     * @param list<array<string, mixed>> $subsystems
     * @param array<string, mixed> $prodReadiness
     * @return array<string, mixed>
     */
    private static function rolloutRiskAnalysis(array $subsystems, array $prodReadiness): array
    {
        $high = [];
        foreach ($subsystems as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (($s['status'] ?? '') === self::STATUS_MISSING && in_array($s['id'] ?? '', ['worker_runtime', 'queue_consumers', 'provider_registry'], true)) {
                $high[] = 'Critical subsystem missing: ' . (string) ($s['id'] ?? '');
            }
        }
        return [
            'overall_risk_score' => $prodReadiness['operational_risk_score_0_100'] ?? null,
            'high_risk_items' => $high,
            'mitigation' => 'Stage with dry-run + tenant allowlist; enable workers last; monitor queue depth.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function migrationSafetyReport(?\PDO $pdo): array
    {
        if ($pdo === null) {
            return ['status' => 'SKIPPED', 'reason' => 'No PDO — run CLI with control-panel config loaded or set RATEB_INFRA_DB_DSN.'];
        }
        $required = [
            'rateb_infra_provisioning_jobs',
            'rateb_infra_job_logs',
            'rateb_infra_orders',
            'rateb_infra_catalog_items',
            'rateb_infra_provider_activations',
        ];
        $present = [];
        $missing = [];
        foreach ($required as $t) {
            try {
                if (SchemaHelpers::tableExists($pdo, $t)) {
                    $present[] = $t;
                } else {
                    $missing[] = $t;
                }
            } catch (\Throwable $e) {
                $missing[] = $t . ' (check failed)';
            }
        }
        return [
            'status' => $missing === [] ? 'OK' : 'INCOMPLETE',
            'tables_present' => $present,
            'tables_missing' => $missing,
            'note' => 'Prefer idempotent migrations; never drop tenant data in auto-repair.',
        ];
    }
}

if (PHP_SAPI === 'cli') {
    $norm = static function (string $p): string {
        return strtolower(str_replace('\\', '/', $p));
    };
    $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($script !== '' && $norm($script) === $norm(__FILE__)) {
    $opts = getopt('', ['json::', 'html::']);
    $base = __DIR__;
    $jsonPath = is_string($opts['json'] ?? null) && $opts['json'] !== '' ? $opts['json'] : $base . '/architecture-audit-report.json';
    $htmlPath = is_string($opts['html'] ?? null) && $opts['html'] !== '' ? $opts['html'] : $base . '/architecture-audit-report.html';

    require_once dirname(__DIR__) . '/bootstrap.php';

    $pdo = null;
    try {
        if (class_exists(\RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory::class)) {
            $pdo = \RATEB\InfrastructureMarketplace\Infrastructure\DatabaseConnectionFactory::createPdo();
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, "Note: running without DB (" . $e->getMessage() . ")\n");
    }

    $report = ArchitectureAuditReport::build($pdo);
    file_put_contents($jsonPath, ArchitectureAuditReport::toJson($report));
    file_put_contents($htmlPath, ArchitectureAuditReport::toHtml($report));
    fwrite(STDOUT, "Wrote:\n  {$jsonPath}\n  {$htmlPath}\n");
    }
}
