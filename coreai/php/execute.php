<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Safe execution endpoint for approved actions.
 * نقطة تنفيذ آمنة للإجراءات المعتمدة من المستخدم.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = coreai_require_auth(true);
coreai_enforce_subscription_or_exit($currentUser, 'execute_request', 'ai_execution', false);

$rawInput = file_get_contents('php://input');
if ($rawInput === false || trim($rawInput) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$decisionId = trim((string)($payload['decisionId'] ?? ''));
$changeReason = trim((string)($payload['reasonForChange'] ?? ''));

$actions = $payload['actions'] ?? [];
if (!is_array($actions) || $actions === []) {
    http_response_code(400);
    echo json_encode(['error' => 'No approved actions provided.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$approvalConfirmed = ($payload['approvalConfirmed'] ?? false) === true;
if (!$approvalConfirmed) {
    http_response_code(400);
    echo json_encode(['error' => 'Execution blocked: explicit UI approval is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Reject unsafe requests before any file-system mutation.
 * رفض أي طلب غير آمن قبل أي تعديل على الملفات.
 */
$groups = $payload['actionGroups'] ?? [];
if (!is_array($groups) || $groups === []) {
    // Backward-compatible single group wrapping.
    // التوافق مع النسخة السابقة عبر تغليف الإجراءات في مجموعة واحدة.
    $groups = [
        [
            'id' => 'default-group',
            'actions' => $actions,
        ],
    ];
}

$validation = coreai_validate_action_groups($groups);
if (($validation['ok'] ?? false) !== true) {
    http_response_code(400);
    echo json_encode(['error' => $validation['error'] ?? 'Unsafe actions request.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Build predicted baseline before execution for validation.
 * بناء خط أساس متوقع قبل التنفيذ لأغراض التحقق.
 */
$predictedPlan = coreai_build_execution_plan($groups);
$predictedByGroup = [];
foreach (($predictedPlan['groups'] ?? []) as $groupPlan) {
    if (!is_array($groupPlan)) {
        continue;
    }
    $gid = (string)($groupPlan['group_id'] ?? '');
    if ($gid !== '') {
        $predictedByGroup[$gid] = $groupPlan;
    }
}

/**
 * Execute approved coordinated groups with rollback safety.
 * تنفيذ المجموعات المعتمدة بشكل منسق مع أمان التراجع.
 */
$beforeArchitectureMemory = coreai_load_project_intelligence_memory();
$result = coreai_execute_action_groups($groups);

/**
 * Refresh persistent project intelligence after filesystem changes.
 * تحديث ذاكرة ذكاء المشروع الدائمة بعد تغييرات نظام الملفات.
 */
coreai_build_project_intelligence_memory();
$afterArchitectureMemory = coreai_load_project_intelligence_memory();
$postExecutionPlan = coreai_build_execution_plan($groups);
$postByGroup = [];
foreach (($postExecutionPlan['groups'] ?? []) as $gp) {
    if (!is_array($gp)) {
        continue;
    }
    $gid = (string)($gp['group_id'] ?? '');
    if ($gid !== '') {
        $postByGroup[$gid] = $gp;
    }
}

/**
 * Validate predicted vs actual state and persist deviations.
 * التحقق من مقارنة الحالة المتوقعة بالحالة الفعلية وحفظ الانحرافات.
 */
foreach (($result['groups'] ?? []) as &$executedGroup) {
    if (!is_array($executedGroup)) {
        continue;
    }
    $gid = (string)($executedGroup['group_id'] ?? '');
    $predicted = $predictedByGroup[$gid] ?? [];
    $actualAffected = [];
    foreach (($executedGroup['results'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $target = trim((string)($row['target'] ?? ''));
        if ($target !== '') {
            $actualAffected[] = $target;
        }
    }
    $actualAffected = array_values(array_unique($actualAffected));
    $actualState = coreai_collect_actual_system_state($actualAffected);
    $validationReport = coreai_validate_prediction_accuracy($predicted, $actualState);

    $executedGroup['prediction_accuracy_score'] = $validationReport['prediction_accuracy_score'];
    $executedGroup['deviation_report'] = $validationReport['deviation_report'];

    coreai_store_prediction_validation([
        'timestamp' => gmdate(DATE_ATOM),
        'group_id' => $gid,
        'prediction_accuracy_score' => $validationReport['prediction_accuracy_score'],
        'deviation_report' => $validationReport['deviation_report'],
    ]);

    /**
     * Feed learning memory with architectural decision outcomes.
     * تغذية ذاكرة التعلم بنتائج القرارات المعمارية.
     */
    $predictedMeta = is_array($predicted) ? $predicted : [];
    coreai_update_architecture_learning_memory([
        'group_id' => $gid,
        'ok' => (bool)($executedGroup['ok'] ?? false),
        'rolled_back' => (bool)($executedGroup['rolled_back'] ?? false),
        'operations' => $predictedMeta['operations'] ?? [],
        'risk_category' => $predictedMeta['risk_category'] ?? 'unknown',
        'semantic_risk_score' => $predictedMeta['semantic_risk_score'] ?? 0,
        'semantic_risk_factors' => $predictedMeta['semantic_risk_factors'] ?? [],
        'strategy_mode_used' => $predictedMeta['strategy_mode'] ?? ($predictedPlan['impact_summary']['strategy_mode'] ?? 'safety-first'),
        'reason_for_change' => $changeReason !== '' ? $changeReason : ($decisionId !== '' ? 'decision_execution' : 'execution_update'),
        'prediction_accuracy_score' => $validationReport['prediction_accuracy_score'],
    ]);
}
unset($executedGroup);

/**
 * Attach actual execution outcome to prior architecture decision.
 * إرفاق النتيجة الفعلية للتنفيذ بالقرار المعماري السابق.
 */
if ($decisionId !== '') {
    $groupSummaries = [];
    foreach (($result['groups'] ?? []) as $g) {
        if (!is_array($g)) {
            continue;
        }
        $groupSummaries[] = [
            'group_id' => (string)($g['group_id'] ?? ''),
            'ok' => (bool)($g['ok'] ?? false),
            'rolled_back' => (bool)($g['rolled_back'] ?? false),
            'summary' => (string)($g['summary'] ?? ''),
            'prediction_accuracy_score' => (int)($g['prediction_accuracy_score'] ?? 0),
        ];
    }
    coreai_update_architecture_decision_actual_outcome($decisionId, [
        'executed_at' => gmdate(DATE_ATOM),
        'execution_ok' => (bool)($result['ok'] ?? false),
        'summary' => (string)($result['summary'] ?? ''),
        'groups' => $groupSummaries,
    ]);
}

/**
 * Track architecture evolution timeline after each execution.
 * تتبع الخط الزمني لتطور المعمارية بعد كل تنفيذ.
 */
foreach (($result['groups'] ?? []) as $executedGroup) {
    if (!is_array($executedGroup) || (($executedGroup['ok'] ?? false) !== true)) {
        continue;
    }
    $gid = (string)($executedGroup['group_id'] ?? '');
    $pre = $predictedByGroup[$gid] ?? [];
    $post = $postByGroup[$gid] ?? [];
    $affectedModules = [];
    foreach (($executedGroup['results'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $target = trim((string)($row['target'] ?? ''));
        if ($target !== '') {
            $affectedModules[] = $target;
        }
    }

    coreai_append_evolution_timeline_entry(
        $beforeArchitectureMemory,
        $afterArchitectureMemory,
        [
            'change_id' => $gid !== '' ? ('change-' . $gid . '-' . gmdate('YmdHis')) : '',
            'reason_for_change' => $changeReason !== '' ? $changeReason : ($decisionId !== '' ? 'decision_execution' : 'execution_update'),
            'strategy_mode_used' => (string)($pre['strategy_mode'] ?? $postExecutionPlan['impact_summary']['strategy_mode'] ?? 'safety-first'),
            'decision_id' => $decisionId,
            'execution_summary' => (string)($executedGroup['summary'] ?? $result['summary'] ?? ''),
            'affected_modules' => array_values(array_unique($affectedModules)),
            'risk_score_before' => (int)($pre['risk_score'] ?? 0),
            'risk_score_after' => (int)($post['risk_score'] ?? 0),
            'stability_score_before' => (int)($pre['stability_score'] ?? 0),
            'stability_score_after' => (int)($post['stability_score'] ?? 0),
        ]
    );
}

echo json_encode(
    [
        'ok' => $result['ok'],
        'summary' => $result['summary'],
        'groups' => $result['groups'],
    ],
    JSON_UNESCAPED_UNICODE
);

coreai_track_usage('execute_request', [
    'groups' => count((array)($result['groups'] ?? [])),
    'ok' => (bool)($result['ok'] ?? false),
]);
