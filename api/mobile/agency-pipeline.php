<?php
/**
 * Agency recruitment pipeline — deployments + CVs grouped by stage.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.inc.php';
require_once __DIR__ . '/../partnerships/PartnerAgencyController.php';
require_once __DIR__ . '/../partnerships/PartnerAgencyCvsController.php';
require_once __DIR__ . '/../partnerships/DeploymentController.php';

try {
    $claims = rateb_mobile_require_auth('agency');
    $pdo = rateb_mobile_pdo();

    if (($claims['typ'] ?? '') !== 'partner') {
        rateb_mobile_json(['success' => false, 'message' => 'Partner agency account required'], 403);
    }

    $agencyId = rateb_mobile_resolve_agency_id($claims);
    if ($agencyId === null) {
        rateb_mobile_json(['success' => false, 'message' => 'Partner agency account required'], 403);
    }

    // Tenant scope: pipeline data is limited to deployments/CVs for this agency id from JWT.
    $deployments = (new PartnerAgencyController($pdo))->workersByAgency($agencyId);
    $cvs = (new PartnerAgencyCvsController($pdo))->listForAgency($agencyId);

    $stageDefs = [
        ['name' => 'Processing', 'status_key' => 'processing', 'count' => 0],
        ['name' => 'Deployed', 'status_key' => 'deployed', 'count' => 0],
        ['name' => 'Issues', 'status_key' => 'issue', 'count' => 0],
        ['name' => 'Returned', 'status_key' => 'returned', 'count' => 0],
        ['name' => 'Transferred', 'status_key' => 'transferred', 'count' => 0],
    ];
    $countsByKey = [];
    foreach ($stageDefs as $def) {
        $countsByKey[$def['status_key']] = 0;
    }

    foreach ($deployments as $row) {
        $key = strtolower(trim((string) ($row['status'] ?? 'processing')));
        if (!isset($countsByKey[$key])) {
            $key = 'processing';
        }
        $countsByKey[$key]++;
    }

    $stages = [];
    $cvCount = count($cvs);
    if ($cvCount > 0) {
        $stages[] = [
            'name' => 'CV Pool',
            'count' => $cvCount,
            'status_key' => 'cvs',
        ];
    }

    foreach ($stageDefs as $def) {
        $count = (int) ($countsByKey[$def['status_key']] ?? 0);
        if ($count <= 0) {
            continue;
        }
        $stages[] = [
            'name' => $def['name'],
            'count' => $count,
            'status_key' => $def['status_key'],
        ];
    }

    if ($stages === []) {
        $stages = [
            ['name' => 'Sourcing', 'count' => 0, 'status_key' => 'sourcing'],
            ['name' => 'Screening', 'count' => 0, 'status_key' => 'screening'],
            ['name' => 'Deployment', 'count' => 0, 'status_key' => 'deployment'],
        ];
    }

    $deployedCount = (int) ($countsByKey['deployed'] ?? 0);
    $processingCount = (int) ($countsByKey['processing'] ?? 0);
    $totalCandidates = count($deployments) + $cvCount;

    rateb_mobile_json([
        'success' => true,
        'data' => [
            'stages' => $stages,
            'stats' => [
                'total_candidates' => $totalCandidates,
                'cvs' => $cvCount,
                'deployments' => count($deployments),
                'deployed' => $deployedCount,
                'processing' => $processingCount,
            ],
        ],
    ]);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'Pipeline unavailable'], 500);
}
