<?php
/**
 * Agency client assignments — deployments grouped by destination country.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.inc.php';
require_once __DIR__ . '/../partnerships/PartnerAgencyController.php';

try {
    $claims = rateb_mobile_require_auth('agency');
    $pdo = rateb_mobile_pdo();

    if (($claims['typ'] ?? '') !== 'partner') {
        rateb_mobile_json(['success' => false, 'message' => 'Partner agency account required'], 403);
    }

    $agencyId = (int) ($claims['sub'] ?? 0);
    if ($agencyId <= 0) {
        rateb_mobile_json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $deployments = (new PartnerAgencyController($pdo))->workersByAgency($agencyId);

    $byCountry = [];
    foreach ($deployments as $row) {
        $country = trim((string) ($row['country'] ?? ''));
        if ($country === '') {
            $country = 'Unassigned';
        }
        if (!isset($byCountry[$country])) {
            $byCountry[$country] = [
                'client_name' => $country,
                'workers_count' => 0,
                'deployed' => 0,
                'processing' => 0,
                'issue' => 0,
            ];
        }
        $byCountry[$country]['workers_count']++;
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status === 'deployed') {
            $byCountry[$country]['deployed']++;
        } elseif ($status === 'processing') {
            $byCountry[$country]['processing']++;
        } elseif ($status === 'issue') {
            $byCountry[$country]['issue']++;
        }
    }

    $assignments = [];
    foreach ($byCountry as $entry) {
        $parts = [];
        if ($entry['deployed'] > 0) {
            $parts[] = $entry['deployed'] . ' deployed';
        }
        if ($entry['processing'] > 0) {
            $parts[] = $entry['processing'] . ' processing';
        }
        if ($entry['issue'] > 0) {
            $parts[] = $entry['issue'] . ' issue';
        }
        $subtitle = $parts !== []
            ? implode(' · ', $parts)
            : $entry['workers_count'] . ' workers';

        $assignments[] = [
            'client_name' => (string) $entry['client_name'],
            'workers_count' => (int) $entry['workers_count'],
            'subtitle' => $subtitle,
            'deployed' => (int) $entry['deployed'],
            'processing' => (int) $entry['processing'],
        ];
    }

    usort($assignments, static function (array $a, array $b): int {
        return ($b['workers_count'] ?? 0) <=> ($a['workers_count'] ?? 0);
    });

    $activeAssignments = 0;
    foreach ($assignments as $a) {
        if (($a['workers_count'] ?? 0) > 0) {
            $activeAssignments++;
        }
    }

    rateb_mobile_json([
        'success' => true,
        'data' => [
            'assignments' => $assignments,
            'stats' => [
                'total_clients' => count($assignments),
                'total_workers' => count($deployments),
                'active_assignments' => $activeAssignments,
            ],
        ],
    ]);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'Assignments unavailable'], 500);
}
