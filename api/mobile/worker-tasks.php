<?php
/**
 * Worker tasks — document and deployment action items.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.inc.php';

try {
    $claims = rateb_mobile_require_auth('worker');
    $pdo = rateb_mobile_pdo();
    $profile = rateb_mobile_staff_profile($pdo, $claims);
    $worker = rateb_mobile_resolve_worker($pdo, $claims);

    $tasks = [];

    if ($worker !== null) {
        $workerId = (int) $worker['id'];
        $workerName = (string) ($worker['worker_name'] ?? 'Worker');
        $status = strtolower((string) ($worker['status'] ?? 'pending'));

        $requiredDocs = [
            'passport' => 'Upload passport copy',
            'visa' => 'Complete visa documentation',
            'medical' => 'Submit medical certificate',
        ];

        foreach ($requiredDocs as $docKey => $title) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT id FROM worker_documents WHERE worker_id = ? AND document_type = ? LIMIT 1'
                );
                $stmt->execute([$workerId, $docKey]);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    $tasks[] = [
                        'id' => 'doc-' . $docKey,
                        'title' => $title,
                        'subtitle' => 'Required document',
                        'due_label' => 'Pending',
                        'status' => 'pending',
                        'category' => 'document',
                    ];
                }
            } catch (Throwable $docErr) {
                // Table may not exist — add one generic doc task.
                if ($docKey === 'passport') {
                    $tasks[] = [
                        'id' => 'doc-passport',
                        'title' => 'Complete onboarding documents',
                        'subtitle' => $workerName,
                        'due_label' => 'This week',
                        'status' => 'pending',
                        'category' => 'document',
                    ];
                }
            }
        }

        if ($status === 'pending') {
            $tasks[] = [
                'id' => 'profile-verify',
                'title' => 'Verify contact information',
                'subtitle' => 'Keep your profile up to date',
                'due_label' => 'This week',
                'status' => 'pending',
                'category' => 'profile',
            ];
        }

        try {
            $deployStmt = $pdo->prepare(
                "SELECT wd.id, wd.status, wd.country, wd.job_title, wd.contract_end
                 FROM worker_deployments wd
                 WHERE wd.worker_id = ?
                 AND wd.status IN ('processing', 'issue')
                 ORDER BY wd.id DESC
                 LIMIT 10"
            );
            $deployStmt->execute([$workerId]);
            foreach ($deployStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $country = trim((string) ($row['country'] ?? ''));
                $job = trim((string) ($row['job_title'] ?? ''));
                $label = $country !== '' ? $country : 'Deployment';
                if ($job !== '') {
                    $label .= ' · ' . $job;
                }
                $tasks[] = [
                    'id' => 'deployment-' . (int) $row['id'],
                    'title' => 'Deployment in progress',
                    'subtitle' => $label,
                    'due_label' => rateb_mobile_humanize_status((string) ($row['status'] ?? 'processing')),
                    'status' => (string) ($row['status'] ?? 'processing'),
                    'category' => 'deployment',
                ];
            }
        } catch (Throwable $deployErr) {
            // ignore
        }
    } else {
        $tasks[] = [
            'id' => 'link-worker',
            'title' => 'Link your worker profile',
            'subtitle' => 'Ask HR to match your account email with your worker record',
            'due_label' => 'Action needed',
            'status' => 'pending',
            'category' => 'profile',
        ];
        $tasks[] = [
            'id' => 'update-contact',
            'title' => 'Update contact information',
            'subtitle' => (string) ($profile['email'] ?? ''),
            'due_label' => 'This week',
            'status' => 'pending',
            'category' => 'profile',
        ];
    }

    rateb_mobile_json([
        'success' => true,
        'data' => [
            'tasks' => $tasks,
            'total' => count($tasks),
        ],
    ]);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'Tasks unavailable'], 500);
}
