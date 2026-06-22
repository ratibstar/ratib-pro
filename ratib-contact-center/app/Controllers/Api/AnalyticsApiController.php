<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\Analytics\AnalyticsAggregationService;
use Ratib\ContactCenter\App\Application\Services\Analytics\DashboardBuilder;
use Ratib\ContactCenter\App\Application\Services\Analytics\KpiEngine;
use Ratib\ContactCenter\App\Application\Services\Knowledge\KnowledgeBaseService;
use Ratib\ContactCenter\App\Application\Services\Knowledge\KnowledgeSuggestionService;
use Ratib\ContactCenter\App\Application\Services\Qa\QaCoachingService;
use Ratib\ContactCenter\App\Application\Services\Qa\QaEvaluationService;
use Ratib\ContactCenter\App\Application\Services\Qa\QaScoringService;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Application\Services\Recordings\RecordingService;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;

final class AnalyticsApiController
{
    public function __construct(
        private readonly DashboardBuilder $dashboard = new DashboardBuilder(),
        private readonly KpiEngine $kpis = new KpiEngine(),
        private readonly AnalyticsAggregationService $aggregation = new AnalyticsAggregationService(),
        private readonly RecordingService $recordings = new RecordingService(),
        private readonly QaEvaluationService $qa = new QaEvaluationService(),
        private readonly QaScoringService $qaScoring = new QaScoringService(),
        private readonly QaCoachingService $qaCoaching = new QaCoachingService(),
        private readonly KnowledgeBaseService $kb = new KnowledgeBaseService(),
        private readonly KnowledgeSuggestionService $kbSuggest = new KnowledgeSuggestionService()
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            RealtimeOrchestrator::boot();
            $action = (string) ($_GET['action'] ?? '');
            $input = array_merge($this->parseJsonBody(), $_GET);
            echo json_encode($this->handleAction($action, $input), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    public function handleAction(string $action, array $input): array
    {
        $tenantId = $this->resolveTenantId($input);
        TenantContext::set($tenantId);
        $userId = AuthContext::userId();

        return match ($action) {
            'executive_dashboard' => $this->gate('rcc.command.view', fn () => $this->ok($this->dashboard->executive($tenantId))),
            'kpis' => $this->gate('rcc.analytics.view', fn () => $this->ok(['kpis' => $this->kpis->evaluate($tenantId)])),
            'aggregate_daily' => $this->gate('rcc.analytics.admin', fn () => $this->ok([
                'metrics' => $this->aggregation->aggregateDaily($tenantId, isset($input['date']) ? (string) $input['date'] : null),
            ])),
            'widgets' => $this->gate('rcc.analytics.view', fn () => $this->ok([
                'widgets' => $this->dashboard->widgets($tenantId, (string) ($input['dashboard_key'] ?? 'executive')),
            ])),
            'recording_search' => $this->gate('rcc.recordings.view', fn () => $this->ok([
                'recordings' => $this->recordings->search(
                    $tenantId,
                    isset($input['q']) ? (string) $input['q'] : null,
                    isset($input['contact_id']) ? (int) $input['contact_id'] : null
                ),
            ])),
            'recording_get' => $this->gate('rcc.recordings.view', fn () => $this->ok([
                'recording' => $this->recordings->find($tenantId, (int) ($input['recording_id'] ?? 0)),
            ])),
            'qa_forms' => $this->gate('rcc.qa.view', fn () => $this->ok(['forms' => $this->qa->listForms($tenantId)])),
            'qa_review_create' => $this->gate('rcc.qa.evaluate', fn () => $this->ok([
                'review' => $this->qa->createReview($tenantId, $input, $userId),
            ])),
            'qa_review_score' => $this->gate('rcc.qa.evaluate', fn () => $this->ok([
                'review' => $this->qaScoring->submitScores($tenantId, (int) ($input['review_id'] ?? 0), is_array($input['scores'] ?? null) ? $input['scores'] : [], $userId),
            ])),
            'qa_coaching' => $this->gate('rcc.qa.coach', function () use ($tenantId, $input, $userId) {
                $this->qaCoaching->saveCoachingNotes($tenantId, (int) ($input['review_id'] ?? 0), (string) ($input['notes'] ?? ''), $userId);
                return $this->ok(['saved' => true]);
            }),
            'kb_search' => $this->gate('rcc.kb.view', fn () => $this->ok([
                'articles' => $this->kb->search($tenantId, (string) ($input['q'] ?? '')),
            ])),
            'kb_suggest' => $this->gate('rcc.kb.view', fn () => $this->ok([
                'articles' => isset($input['conversation_id'])
                    ? $this->kbSuggest->suggestForConversation($tenantId, (int) $input['conversation_id'])
                    : $this->kbSuggest->suggestForQuery($tenantId, (string) ($input['q'] ?? '')),
            ])),
            'kb_save' => $this->gate('rcc.kb.author', fn () => $this->ok([
                'article' => $this->kb->saveArticle($tenantId, $input, $userId),
            ])),
            default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
        };
    }

    /** @return array<string, mixed> */
    private function gate(string $perm, callable $fn): array
    {
        if (!AuthContext::can($perm)) {
            return ['ok' => false, 'error' => 'Permission denied: ' . $perm];
        }
        return $fn();
    }

    /** @param array<string, mixed> $input */
    private function resolveTenantId(array $input): int
    {
        AuthContext::requirePermission('rcc.access');
        $tenantId = AuthContext::tenantId();
        if (AuthContext::can('rcc.tenants.manage')) {
            $requested = (int) ($input['tenant_id'] ?? 0);
            if ($requested > 0) {
                return $requested;
            }
        }
        return $tenantId;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function ok(array $data): array
    {
        return ['ok' => true] + $data;
    }

    /** @return array<string, mixed> */
    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
