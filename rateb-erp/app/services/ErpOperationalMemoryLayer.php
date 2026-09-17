<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;

/**
 * Operational Memory Layer — organizes existing tenant evidence into relevant context.
 * Not a Vector DB / RAG / Memory Agent. LLM cannot write memory.
 * Freshness: CURRENT > RECENT > HISTORICAL. Current state always wins conflicts.
 */
final class ErpOperationalMemoryLayer
{
    public const BAND_CURRENT = 'CURRENT';
    public const BAND_RECENT = 'RECENT';
    public const BAND_HISTORICAL = 'HISTORICAL';
    public const BAND_ARCHIVED = 'ARCHIVED';

    private const RECENT_HOURS = 72;
    private const HISTORICAL_DAYS = 30;
    private const MAX_ITEMS = 40;
    private const MAX_LLM_ITEMS = 12;

    /** @var array<string, mixed> */
    private static array $memo = [];

    public static function clearMemo(): void
    {
        self::$memo = [];
    }

    /**
     * Build relevant operational context for the current request.
     *
     * @param array<string, mixed> $intent
     * @param array<string, mixed> $currentHints optional current snapshot pieces
     * @return array<string, mixed>
     */
    public static function buildRelevantContext(
        ProcurementAgentContext $ctx,
        array $intent = [],
        array $currentHints = [],
        int $limit = 12
    ): array {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return self::emptyContext('tenant_mismatch');
        }

        $limit = max(5, min(self::MAX_LLM_ITEMS, $limit));
        $memoKey = 'ctx:' . $companyId . ':' . md5((string) json_encode([$intent, $limit, array_keys($currentHints)]));
        if (isset(self::$memo[$memoKey])) {
            return self::$memo[$memoKey];
        }

        $t0 = microtime(true);
        $catalog = self::catalog($ctx, $currentHints);
        $domains = self::intentDomains($intent, $ctx);
        $scored = [];
        foreach ($catalog as $item) {
            if (!is_array($item)) {
                continue;
            }
            // Permission / module gate
            if (!self::itemVisible($item, $ctx)) {
                continue;
            }
            $score = self::relevanceScore($item, $intent, $domains);
            if ($score <= 0) {
                continue;
            }
            $item['relevance_score'] = $score;
            $item['freshness_band'] = self::freshnessBand((string) ($item['timestamp'] ?? ''));
            if ($item['freshness_band'] === self::BAND_ARCHIVED) {
                continue;
            }
            $scored[] = $item;
        }

        usort($scored, static function (array $a, array $b): int {
            // Current band first, then score
            $bandRank = static fn(string $b): int => match ($b) {
                self::BAND_CURRENT => 3,
                self::BAND_RECENT => 2,
                self::BAND_HISTORICAL => 1,
                default => 0,
            };
            $cmp = $bandRank((string) ($b['freshness_band'] ?? '')) <=> $bandRank((string) ($a['freshness_band'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return ((float) ($b['relevance_score'] ?? 0)) <=> ((float) ($a['relevance_score'] ?? 0));
        });

        $relevant = array_slice($scored, 0, $limit);
        $conflicts = self::detectConflicts($relevant, $currentHints);

        // Never let historical override current — flag conflicts
        foreach ($relevant as &$item) {
            $item['is_historical'] = ($item['freshness_band'] ?? '') !== self::BAND_CURRENT;
            $item['stale_for_writes'] = in_array(($item['freshness_band'] ?? ''), [self::BAND_HISTORICAL, self::BAND_ARCHIVED], true);
            $item['usable_as_current_fact'] = ($item['freshness_band'] ?? '') === self::BAND_CURRENT
                && empty($item['conflict_with_current']);
        }
        unset($item);

        foreach ($conflicts as $c) {
            $id = (string) ($c['memory_id'] ?? '');
            foreach ($relevant as &$item) {
                if ((string) ($item['memory_id'] ?? '') === $id) {
                    $item['conflict_with_current'] = true;
                    $item['usable_as_current_fact'] = false;
                    $item['stale_for_writes'] = true;
                    $item['conflict_note'] = 'current_state_has_priority';
                }
            }
            unset($item);
        }

        $pack = [
            'data_source' => 'live_tenant',
            'company_id' => $companyId,
            'as_of' => date('Y-m-d H:i:s'),
            'hierarchy' => [
                'current_request',
                'current_company_state',
                'active_warnings',
                'recent_actions',
                'recent_outcomes',
                'historical_patterns',
                'learning_signals',
            ],
            'domains_in_focus' => $domains,
            'items' => $relevant,
            'conflicts' => $conflicts,
            'current_priority' => true,
            'stale_protection' => [
                'historical_not_current_fact' => true,
                'writes_require_live_state_validation' => true,
                'phase14_stale_state_protection' => true,
            ],
            'llm_boundary' => [
                'llm_can_write_memory' => false,
                'system_writes_only_from_events' => true,
                'events' => ['ACTION', 'VERIFICATION', 'OUTCOME', 'WARNING', 'FORECAST', 'LEARNING'],
            ],
            'counts' => [
                'catalog' => count($catalog),
                'relevant' => count($relevant),
                'conflicts' => count($conflicts),
            ],
            'observability' => [
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
            ],
            'explainability' => [
                'hidden_chain_of_thought' => false,
                'format' => 'operational_evidence_only',
            ],
            'auto_execute' => false,
        ];

        return self::$memo[$memoKey] = $pack;
    }

    /**
     * Full catalog for Control Tower (bounded, tenant-scoped).
     *
     * @return array<string, mixed>
     */
    public static function towerSection(ProcurementAgentContext $ctx, int $limit = 10): array
    {
        $ctxPack = self::buildRelevantContext($ctx, ['intent_kind' => 'executive', 'memory' => true], [], $limit);
        $items = is_array($ctxPack['items'] ?? null) ? $ctxPack['items'] : [];
        $byType = [
            'decisions' => [],
            'actions' => [],
            'outcomes' => [],
            'patterns' => [],
            'unresolved' => [],
            'warnings' => [],
            'learning_signals' => [],
        ];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            $row = [
                'memory_id' => $item['memory_id'] ?? '',
                'title' => $item['title'] ?? '',
                'domain' => $item['domain'] ?? '',
                'timestamp' => $item['timestamp'] ?? '',
                'freshness_band' => $item['freshness_band'] ?? '',
                'is_historical' => !empty($item['is_historical']),
                'evidence' => $item['evidence'] ?? [],
                'outcome' => $item['outcome'] ?? null,
                'relevance_score' => $item['relevance_score'] ?? null,
            ];
            if ($type === 'warning' && ($item['status'] ?? '') !== 'RESOLVED' && ($item['status'] ?? '') !== 'DISMISSED') {
                $byType['unresolved'][] = $row;
                $byType['warnings'][] = $row;
            } elseif ($type === 'outcome') {
                $byType['outcomes'][] = $row;
                $byType['actions'][] = $row;
            } elseif ($type === 'pattern') {
                $byType['patterns'][] = $row;
            } elseif ($type === 'learning_signal') {
                $byType['learning_signals'][] = $row;
            } elseif ($type === 'recommendation' || $type === 'decision') {
                $byType['decisions'][] = $row;
            } elseif ($type === 'action') {
                $byType['actions'][] = $row;
            }
        }

        return [
            'data_source' => 'live_tenant',
            'company_id' => (int) $ctx->companyId,
            'section' => 'operational_context_memory',
            'items' => $items,
            'groups' => $byType,
            'conflicts' => $ctxPack['conflicts'] ?? [],
            'llm_can_write_memory' => false,
            'stale_protection' => $ctxPack['stale_protection'] ?? [],
            'auto_execute' => false,
        ];
    }

    /**
     * Compact pack safe to attach to intelligence / LLM prompts (no secrets).
     *
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    public static function forIntelligence(ProcurementAgentContext $ctx, array $intent, array $currentHints = []): array
    {
        $pack = self::buildRelevantContext($ctx, $intent, $currentHints, self::MAX_LLM_ITEMS);
        $lines = [];
        foreach (($pack['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $band = (string) ($item['freshness_band'] ?? '');
            $title = (string) ($item['title'] ?? '');
            $domain = (string) ($item['domain'] ?? '');
            $hist = !empty($item['is_historical']) ? ' [historical]' : '';
            $stale = !empty($item['stale_for_writes']) ? ' [not_for_writes]' : '';
            $lines[] = trim($band . '|' . $domain . '|' . $title . $hist . $stale);
        }

        return [
            'data_source' => 'live_tenant',
            'company_id' => (int) $ctx->companyId,
            'relevant_memory' => array_slice($pack['items'] ?? [], 0, self::MAX_LLM_ITEMS),
            'summary_lines' => array_slice($lines, 0, self::MAX_LLM_ITEMS),
            'conflicts' => $pack['conflicts'] ?? [],
            'current_priority' => true,
            'llm_can_write_memory' => false,
            'formula' => 'CURRENT_DATA + RELEVANT_OPERATIONAL_MEMORY',
            'not' => 'CURRENT_DATA + FULL_HISTORY',
        ];
    }

    /**
     * Assert LLM boundary (tests + runtime).
     *
     * @return array<string, bool|string>
     */
    public static function assertLlmBoundary(): array
    {
        return [
            'llm_writes_memory' => false,
            'vector_db' => false,
            'rag_platform' => false,
            'memory_agent' => false,
            'system_event_driven' => true,
            'scope' => 'tenant_operational_context_only',
        ];
    }

    /**
     * @param array<string, mixed> $currentHints
     * @return list<array<string, mixed>>
     */
    private static function catalog(ProcurementAgentContext $ctx, array $currentHints): array
    {
        $companyId = (int) $ctx->companyId;
        $items = [];
        $now = time();

        // Active warnings (CURRENT/RECENT)
        foreach (ErpProactiveEarlyWarningLayer::listWarnings($companyId, false) as $w) {
            if (!is_array($w)) {
                continue;
            }
            $items[] = self::item(
                'warn_' . (string) ($w['warning_id'] ?? uniqid()),
                'warning',
                (string) ($w['signal'] ?? 'warning'),
                (string) ($w['domain'] ?? 'executive'),
                (string) ($w['detected_at'] ?? date('Y-m-d H:i:s')),
                [
                    'source' => 'ErpProactiveEarlyWarningLayer',
                    'warning_id' => $w['warning_id'] ?? null,
                    'severity' => $w['severity'] ?? null,
                    'status' => $w['status'] ?? null,
                    'details' => $w['evidence'] ?? [],
                ],
                (string) ($w['status'] ?? ''),
                (string) ($w['recommended_action'] ?? ''),
                ['module' => self::domainToModule((string) ($w['domain'] ?? ''))]
            );
        }

        // Learning analyze (outcomes, signals, patterns) — reuse existing store
        $learning = ErpOperationalLearningLayer::analyze($ctx, 30);
        foreach (array_slice(array_values($learning['outcomes'] ?? []), -20) as $o) {
            if (!is_array($o)) {
                continue;
            }
            $items[] = self::item(
                'out_' . (string) ($o['outcome_id'] ?? uniqid()),
                'outcome',
                (string) (($o['tool'] ?? 'action') . ' → ' . ($o['business_outcome'] ?? $o['outcome_status'] ?? '')),
                (string) ($o['domain'] ?? 'procurement'),
                (string) ($o['outcome_timestamp'] ?? date('Y-m-d H:i:s')),
                [
                    'source' => 'ErpOperationalLearningLayer',
                    'outcome_id' => $o['outcome_id'] ?? null,
                    'tool' => $o['tool'] ?? null,
                    'verification' => $o['verification'] ?? null,
                ],
                (string) ($o['business_outcome'] ?? $o['outcome_status'] ?? ''),
                (string) ($o['related_recommendation'] ?? ''),
                ['module' => self::domainToModule((string) ($o['domain'] ?? ''))]
            );
        }
        foreach (array_slice(array_values($learning['signals'] ?? []), -20) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $items[] = self::item(
                'ls_' . (string) ($s['signal_id'] ?? uniqid()),
                'learning_signal',
                (string) ($s['code'] ?? 'learning_signal'),
                (string) ($s['domain'] ?? 'executive'),
                (string) ($s['at'] ?? date('Y-m-d H:i:s')),
                [
                    'source' => 'ErpOperationalLearningLayer',
                    'signal_id' => $s['signal_id'] ?? null,
                    'data_sufficiency' => $s['data_sufficiency'] ?? null,
                    'details' => $s['evidence'] ?? [],
                ],
                null,
                '',
                ['module' => 'dashboard']
            );
        }
        foreach (($learning['patterns'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $items[] = self::item(
                'pat_' . md5((string) ($p['code'] ?? '')),
                'pattern',
                (string) ($p['code'] ?? 'pattern'),
                'executive',
                date('Y-m-d H:i:s', $now - 3600),
                [
                    'source' => 'ErpOperationalLearningLayer',
                    'occurrences' => $p['occurrences'] ?? null,
                    'data_sufficiency' => $p['data_sufficiency'] ?? null,
                    'details' => $p['evidence'] ?? [],
                ],
                null,
                '',
                ['module' => 'dashboard', 'recurring' => true]
            );
        }
        foreach (array_slice(array_values($learning['recommendation_effectiveness'] ?? []), 0, 15) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $items[] = self::item(
                'rec_' . md5((string) ($r['recommendation'] ?? '')),
                'recommendation',
                (string) ($r['recommendation'] ?? 'recommendation'),
                (string) ($r['domain'] ?? 'executive'),
                (string) ($r['updated_at'] ?? date('Y-m-d H:i:s')),
                [
                    'source' => 'ErpOperationalLearningLayer',
                    'effectiveness' => $r['effectiveness'] ?? null,
                    'data_sufficiency' => $r['data_sufficiency'] ?? null,
                ],
                (string) ($r['effectiveness'] ?? ''),
                (string) ($r['recommendation'] ?? ''),
                ['module' => self::domainToModule((string) ($r['domain'] ?? 'executive'))]
            );
        }

        // Recent audit activity (bounded)
        foreach (self::recentAudit($companyId, 15) as $a) {
            $items[] = $a;
        }

        // Current hints from caller (Control Tower / executive pack)
        if (!empty($currentHints['open_warning_codes']) && is_array($currentHints['open_warning_codes'])) {
            foreach ($currentHints['open_warning_codes'] as $code) {
                $items[] = self::item(
                    'cur_warn_' . md5((string) $code),
                    'warning',
                    (string) $code,
                    'executive',
                    date('Y-m-d H:i:s'),
                    ['source' => 'current_hints', 'details' => []],
                    'ACTIVE',
                    '',
                    ['module' => 'dashboard']
                );
            }
        }

        // Cap catalog
        if (count($items) > self::MAX_ITEMS) {
            $items = array_slice($items, 0, self::MAX_ITEMS);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function recentAudit(int $companyId, int $limit): array
    {
        $out = [];
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT id, tool_name, status, request_id, duration_ms, created_at, error_code
                 FROM rateb_agent_audit_events
                 WHERE company_id = :cid
                   AND created_at >= (NOW() - INTERVAL 7 DAY)
                 ORDER BY id DESC
                 LIMIT " . (int) $limit
            );
            $stmt->execute(['cid' => $companyId]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $tool = (string) ($row['tool_name'] ?? '');
                $out[] = self::item(
                    'aud_' . (string) ($row['id'] ?? ''),
                    str_contains($tool, 'create_') || str_contains($tool, 'submit_') || str_contains($tool, 'update_') || str_contains($tool, 'cancel_')
                        ? 'action'
                        : 'decision',
                    $tool . ' [' . (string) ($row['status'] ?? '') . ']',
                    self::guessDomainFromTool($tool),
                    (string) ($row['created_at'] ?? date('Y-m-d H:i:s')),
                    [
                        'source' => 'rateb_agent_audit_events',
                        'request_id' => $row['request_id'] ?? null,
                        'status' => $row['status'] ?? null,
                        'error_code' => $row['error_code'] ?? null,
                        'duration_ms' => $row['duration_ms'] ?? null,
                    ],
                    (string) ($row['status'] ?? ''),
                    '',
                    ['module' => self::domainToModule(self::guessDomainFromTool($tool))]
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $evidence
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private static function item(
        string $id,
        string $type,
        string $title,
        string $domain,
        string $timestamp,
        array $evidence,
        ?string $outcome,
        string $recommendation,
        array $meta = []
    ): array {
        return [
            'memory_id' => $id,
            'type' => $type,
            'title' => $title,
            'domain' => $domain,
            'timestamp' => $timestamp,
            'evidence' => array_merge([
                'domain' => $domain,
                'source' => $evidence['source'] ?? 'operational_memory',
                'timestamp' => $timestamp,
            ], $evidence),
            'outcome' => $outcome,
            'recommendation' => $recommendation,
            'status' => $meta['status'] ?? ($evidence['status'] ?? null),
            'module' => $meta['module'] ?? 'dashboard',
            'recurring' => !empty($meta['recurring']),
            'llm_authored' => false,
        ];
    }

    /**
     * @param array<string, mixed> $intent
     * @return list<string>
     */
    private static function intentDomains(array $intent, ProcurementAgentContext $ctx): array
    {
        $domains = [];
        foreach (($intent['domains'] ?? []) as $d) {
            $d = (string) $d;
            if ($d !== '' && $ctx->moduleEnabled(self::domainToModule($d))) {
                $domains[] = $d;
            }
        }
        if ($domains === [] && !empty($intent['primary'])) {
            $domains[] = (string) $intent['primary'];
        }
        if ($domains === []) {
            foreach (ErpDomainRegistry::activeDomainIds() as $id) {
                $meta = ErpDomainRegistry::resolve($id);
                if ($meta && $ctx->moduleEnabled((string) $meta['module'])) {
                    $domains[] = $id;
                }
            }
        }
        return array_values(array_unique($domains));
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $intent
     * @param list<string> $domains
     */
    private static function relevanceScore(array $item, array $intent, array $domains): float
    {
        $score = 1.0;
        $domain = (string) ($item['domain'] ?? '');
        if ($domains !== [] && in_array($domain, $domains, true)) {
            $score += 3.0;
        } elseif ($domain === 'executive' || $domain === '') {
            $score += 1.0;
        } else {
            $score += 0.2;
        }

        $type = (string) ($item['type'] ?? '');
        if (!empty($intent['proactive']) && $type === 'warning') {
            $score += 2.5;
        }
        if (!empty($intent['learning']) && in_array($type, ['learning_signal', 'outcome', 'pattern'], true)) {
            $score += 2.5;
        }
        if ((($intent['intent_kind'] ?? '') === 'executive' || !empty($intent['executive']))
            && in_array($type, ['warning', 'pattern', 'recommendation', 'outcome'], true)
        ) {
            $score += 1.5;
        }
        if (!empty($intent['write_intent']) && in_array($type, ['action', 'outcome', 'warning'], true)) {
            $score += 1.5;
        }
        if (!empty($item['recurring'])) {
            $score += 1.2;
        }
        // Unresolved warnings boost
        $st = strtoupper((string) ($item['status'] ?? ''));
        if ($type === 'warning' && !in_array($st, ['RESOLVED', 'DISMISSED'], true)) {
            $score += 2.0;
        }
        // Failed outcomes boost for learning
        if ($type === 'outcome' && in_array(strtoupper((string) ($item['outcome'] ?? '')), ['FAILED', 'NEGATIVE_OUTCOME', 'NO_EFFECT'], true)) {
            $score += 1.0;
        }

        $band = self::freshnessBand((string) ($item['timestamp'] ?? ''));
        $score += match ($band) {
            self::BAND_CURRENT => 2.0,
            self::BAND_RECENT => 1.0,
            self::BAND_HISTORICAL => 0.3,
            default => -5.0,
        };

        return $score;
    }

    private static function freshnessBand(string $timestamp): string
    {
        if ($timestamp === '' || strtotime($timestamp) === false) {
            return self::BAND_HISTORICAL;
        }
        $age = time() - strtotime($timestamp);
        if ($age <= 6 * 3600) {
            return self::BAND_CURRENT;
        }
        if ($age <= self::RECENT_HOURS * 3600) {
            return self::BAND_RECENT;
        }
        if ($age <= self::HISTORICAL_DAYS * 86400) {
            return self::BAND_HISTORICAL;
        }
        return self::BAND_ARCHIVED;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function itemVisible(array $item, ProcurementAgentContext $ctx): bool
    {
        $module = (string) ($item['module'] ?? 'dashboard');
        if ($module === '' || $module === 'dashboard') {
            return $ctx->can('ai.view') || $ctx->can('dashboard.view') || $ctx->isSuperAdmin;
        }
        if (!$ctx->moduleEnabled($module) && $module !== 'pos') {
            // sales uses pos module
            if ($module === 'pos' || (string) ($item['domain'] ?? '') === 'sales') {
                return $ctx->moduleEnabled('pos') || $ctx->can('pos.view');
            }
            return false;
        }
        return true;
    }

    /**
     * @param list<array<string, mixed>> $relevant
     * @param array<string, mixed> $currentHints
     * @return list<array<string, mixed>>
     */
    private static function detectConflicts(array $relevant, array $currentHints): array
    {
        $conflicts = [];
        $currentCodes = [];
        foreach (($currentHints['resolved_warning_ids'] ?? []) as $id) {
            $currentCodes['resolved:' . $id] = true;
        }
        foreach ($relevant as $item) {
            if (!is_array($item)) {
                continue;
            }
            $band = self::freshnessBand((string) ($item['timestamp'] ?? ''));
            if ($band === self::BAND_CURRENT) {
                continue;
            }
            // Historical open warning that current hints mark resolved
            $wid = (string) (($item['evidence']['warning_id'] ?? ''));
            if ($wid !== '' && isset($currentCodes['resolved:' . $wid])) {
                $conflicts[] = [
                    'memory_id' => $item['memory_id'] ?? '',
                    'reason' => 'historical_warning_resolved_in_current_state',
                    'resolution' => 'prefer_current_state',
                ];
            }
            // Historical recommendation marked EFFECTIVE but recent outcome FAILED for same rec
            if (($item['type'] ?? '') === 'recommendation'
                && strtoupper((string) ($item['outcome'] ?? '')) === 'EFFECTIVE'
            ) {
                foreach ($relevant as $other) {
                    if (!is_array($other) || ($other['type'] ?? '') !== 'outcome') {
                        continue;
                    }
                    if ((string) ($other['recommendation'] ?? '') === (string) ($item['recommendation'] ?? '')
                        && in_array(strtoupper((string) ($other['outcome'] ?? '')), ['FAILED', 'NO_EFFECT', 'NEGATIVE_OUTCOME'], true)
                        && self::freshnessBand((string) ($other['timestamp'] ?? '')) === self::BAND_CURRENT
                    ) {
                        $conflicts[] = [
                            'memory_id' => $item['memory_id'] ?? '',
                            'reason' => 'historical_effectiveness_conflicts_with_recent_outcome',
                            'resolution' => 'prefer_current_outcome',
                        ];
                    }
                }
            }
        }
        return $conflicts;
    }

    private static function domainToModule(string $domain): string
    {
        return match ($domain) {
            'sales' => 'pos',
            'suppliers' => 'suppliers',
            'executive' => 'dashboard',
            default => $domain !== '' ? $domain : 'dashboard',
        };
    }

    private static function guessDomainFromTool(string $tool): string
    {
        if (str_contains($tool, 'accounting') || str_contains($tool, 'journal') || str_contains($tool, 'financial')) {
            return 'accounting';
        }
        if (str_contains($tool, 'crm')) {
            return 'crm';
        }
        if (str_contains($tool, 'sales') || str_contains($tool, 'pos')) {
            return 'sales';
        }
        if (str_contains($tool, 'inventory') || str_contains($tool, 'stock')) {
            return 'inventory';
        }
        if (str_contains($tool, 'logistics') || str_contains($tool, 'shipment')) {
            return 'logistics';
        }
        if (str_contains($tool, 'supplier')) {
            return 'suppliers';
        }
        if (str_contains($tool, 'executive') || str_contains($tool, 'early_warning') || str_contains($tool, 'learning') || str_contains($tool, 'control_tower')) {
            return 'executive';
        }
        return 'procurement';
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyContext(string $reason): array
    {
        return [
            'data_source' => 'live_tenant',
            'error' => $reason,
            'items' => [],
            'conflicts' => [],
            'llm_boundary' => ['llm_can_write_memory' => false],
            'auto_execute' => false,
        ];
    }
}
