<?php
/**
 * Partner portal — read-only worker CV payload (same fields as staff CV preview), session scoped.
 */
ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyWorkerDocSharesController.php';

function partnerPortalCvJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function ratib_partner_portal_strip_worker_for_cv(array $row): array
{
    foreach (
        [
            'agent_id',
            'subagent_id',
            'biometric_id',
            'created_by',
            'updated_by',
        ] as $k
    ) {
        unset($row[$k]);
    }

    return $row;
}

/**
 * Make relative photo URLs absolute for the partner browser.
 */
function ratib_partner_portal_resolve_photo_url(?string $url): string
{
    if ($url === null || trim($url) === '') {
        return '';
    }
    $u = trim($url);
    if (preg_match('#^https?://#i', $u) || strncmp($u, 'data:', 5) === 0) {
        return $u;
    }
    $base = defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '';
    if ($base === '') {
        return $u;
    }

    return $base . '/' . ltrim($u, '/');
}

try {
    if (!function_exists('ratib_partner_portal_session_is_valid') || !ratib_partner_portal_session_is_valid()) {
        partnerPortalCvJson(['success' => false, 'message' => 'Partner portal session required'], 401);
    }

    $wid = (int) ($_GET['worker_id'] ?? 0);
    if ($wid <= 0) {
        partnerPortalCvJson(['success' => false, 'message' => 'worker_id is required'], 400);
    }

    $aid = ratib_partner_portal_agency_id();
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);
    $ctl = new PartnerAgencyWorkerDocSharesController($conn);

    if (!$ctl->partnerPortalCanViewWorkerCv($aid, $wid)) {
        partnerPortalCvJson(['success' => false, 'message' => 'This worker is not available on your portal.'], 403);
    }

    $worker = $ctl->fetchWorkerRow($wid);
    if (!$worker) {
        partnerPortalCvJson(['success' => false, 'message' => 'Worker not found'], 404);
    }

    $worker = ratib_partner_portal_strip_worker_for_cv($worker);
    if (isset($worker['personal_photo_url'])) {
        $worker['personal_photo_url'] = ratib_partner_portal_resolve_photo_url((string) $worker['personal_photo_url']);
    }

    $company = defined('APP_NAME') ? (string) APP_NAME : 'Ratib Program';

    partnerPortalCvJson([
        'success' => true,
        'data' => [
            'worker' => $worker,
            'company_display_name' => $company,
        ],
    ]);
} catch (Throwable $e) {
    error_log('partner-portal-worker-cv-data: ' . $e->getMessage());
    partnerPortalCvJson(['success' => false, 'message' => 'Internal server error'], 500);
}
