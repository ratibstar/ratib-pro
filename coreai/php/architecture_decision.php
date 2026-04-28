<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * AI Architectural Decision Engine endpoint.
 * نقطة واجهة محرك القرار المعماري للذكاء الاصطناعي.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = coreai_require_auth(true);
coreai_enforce_subscription_or_exit($currentUser, 'architecture_decision_request', 'architecture_decision', false);

$rawInput = file_get_contents('php://input');
$payload = [];
if ($rawInput !== false && trim($rawInput) !== '') {
    $decoded = json_decode($rawInput, true);
    if (!is_array($decoded)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $payload = $decoded;
}

/**
 * Load persistent intelligence then evaluate architecture decisions.
 * تحميل الذكاء الدائم ثم تقييم القرارات المعمارية.
 */
$intelligence = coreai_load_project_intelligence_memory();
$decision = coreai_run_architectural_decision_engine($intelligence, $payload);

/**
 * Persist decision with reason/context/expected outcome.
 * حفظ القرار مع السبب والسياق والنتيجة المتوقعة.
 */
$decisionId = coreai_store_architecture_decision([
    'reason' => (string)($decision['reasoning_explanation']['summary'] ?? 'Architecture optimization recommendation.'),
    'context' => [
        'request_payload' => $payload,
        'context_signals' => $decision['reasoning_explanation']['context_signals'] ?? [],
    ],
    'expected_outcome' => [
        'recommended_architecture_changes' => $decision['recommended_architecture_changes'] ?? [],
        'optimized_recommendations' => $decision['optimized_recommendations'] ?? [],
        'historical_confidence_score' => $decision['historical_confidence_score'] ?? 0,
        'matched_past_patterns' => $decision['matched_past_patterns'] ?? [],
        'avoided_risk_patterns' => $decision['avoided_risk_patterns'] ?? [],
        'tradeoff_policy' => $decision['reasoning_explanation']['performance_vs_safety_policy'] ?? '',
    ],
]);

echo json_encode(
    [
        'ok' => true,
        'decision_id' => $decisionId,
        'recommended_architecture_changes' => $decision['recommended_architecture_changes'],
        'optimized_recommendations' => $decision['optimized_recommendations'] ?? [],
        'historical_confidence_score' => $decision['historical_confidence_score'] ?? 0,
        'matched_past_patterns' => $decision['matched_past_patterns'] ?? [],
        'avoided_risk_patterns' => $decision['avoided_risk_patterns'] ?? [],
        'reasoning_explanation' => $decision['reasoning_explanation'],
    ],
    JSON_UNESCAPED_UNICODE
);

coreai_track_usage('architecture_decision_request', [
    'recommendation_count' => count((array)($decision['optimized_recommendations'] ?? [])),
    'confidence' => (int)($decision['historical_confidence_score'] ?? 0),
]);
