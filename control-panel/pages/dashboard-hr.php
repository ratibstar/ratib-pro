<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/dashboard-hr.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/dashboard-hr.php`.
 */
/**
 * Stub: HR dashboard is in Ratib Pro. Open Ratib Pro with agency selected for full HR.
 */
require_once __DIR__ . '/../includes/config.php';
if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
$agencyName = $_SESSION['control_agency_name'] ?? 'your agency';
$ratibUrl = defined('RATIB_PRO_URL') ? RATIB_PRO_URL : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Command Center – Control Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-dark text-light p-4">
    <div class="container">
        <h5><i class="fas fa-user-tie me-2"></i>HR Command Center</h5>
        <p class="text-muted">Full HR is available in Ratib Pro. Select your agency there to manage workers, contracts, and HR settings.</p>
        <?php if ($ratibUrl): ?>
        <a href="<?php echo htmlspecialchars($ratibUrl); ?>?control=1&agency_id=<?php echo (int)($_SESSION['control_agency_id'] ?? 0); ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open Ratib Pro – HR <i class="fas fa-external-link-alt ms-1"></i></a>
        <?php else: ?>
        <p class="small text-muted">Set <code>RATIB_PRO_URL</code> in config/env.php to enable the link.</p>
        <?php endif; ?>
    </div>
</body>
</html>
