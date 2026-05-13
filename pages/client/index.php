<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../modules/client-dashboard/bootstrap.php';
if (ratib_client_dashboard_can_access()) {
    header('Location: ' . pageUrl('client/dashboard.php'));
    exit;
}
if (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
$controlMode = (!empty($_GET['control']) && (string) $_GET['control'] === '1') || !empty($_SESSION['control_logged_in']);
if ($controlMode) {
    $controlQuery = ['control' => '1'];
    $agencyId = (int) ($_GET['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
    if ($agencyId > 0) {
        $controlQuery['agency_id'] = (string) $agencyId;
    }
    header('Location: ' . rtrim((string) getBaseUrl(), '/') . '/control-panel/pages/control/dashboard.php?' . http_build_query($controlQuery));
    exit;
}
header('Location: ' . pageUrl('dashboard.php'));
exit;
