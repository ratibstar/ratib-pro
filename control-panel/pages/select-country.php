<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/select-country.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/select-country.php`.
 */
/**
 * Control Panel: Select country
 */
require_once __DIR__ . '/../includes/config.php';
unset($_SESSION['control_use_own_program']);
if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
$allowed = getAllowedCountrySlugs();
$hasAccess = ($allowed === null) || !empty($allowed);
if (!$hasAccess) {
    http_response_code(403);
    die(cp_t('common.access_denied'));
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if ($ctrl === null) die(cp_t('common.db_unavailable'));
$GLOBALS['ctrl'] = $ctrl;

$hasCountries = false;
try {
    $chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
    $hasCountries = $chk && $chk->num_rows > 0;
} catch (Throwable $e) { /* ignore */ }

if (!$hasCountries) {
    header('Location: ' . pageUrl('select-agency.php'));
    exit;
}

$countries = [];
$stmt = $ctrl->query("SELECT id, name, slug FROM control_countries WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
if ($stmt) {
    $allowedSlugs = getAllowedCountrySlugs();
    while ($row = $stmt->fetch_assoc()) {
        if ($allowedSlugs === null || in_array($row['slug'], $allowedSlugs, true)) {
            $countries[] = $row;
        }
    }
    $stmt->close();
}

require_once __DIR__ . '/../includes/control/layout-wrapper.php';
startControlLayout('Select Country', [], []);
?>
<div class="module-section">
    <p class="text-muted mb-4"><?php echo htmlspecialchars(cp_t('select_country.intro'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if (empty($countries)): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars(cp_t('select_country.no_countries'), ENT_QUOTES, 'UTF-8'); ?></div>
        <a href="<?php echo pageUrl('select-agency.php'); ?>" class="back-button"><i class="fas fa-arrow-left"></i><span><?php echo htmlspecialchars(cp_t('select_country.view_all_agencies'), ENT_QUOTES, 'UTF-8'); ?></span></a>
    <?php else: ?>
        <div class="country-grid">
            <?php
            $ratebBase = rtrim((string) (defined('SITE_URL') ? SITE_URL : ''), '/');
            if ($ratebBase === '' && isset($_SERVER['HTTP_HOST'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $ratebBase = $scheme . '://' . $_SERVER['HTTP_HOST'];
            }
            foreach ($countries as $c):
                $cardUrl = $ratebBase . '/' . $c['slug'] . '/login';
            ?>
                <a href="<?php echo htmlspecialchars($cardUrl); ?>" class="country-card" target="_blank" rel="noopener noreferrer">
                    <h3><?php echo htmlspecialchars($c['name']); ?></h3>
                    <div class="slug"><?php echo htmlspecialchars($c['slug']); ?></div>
                    <div class="hint"><i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars(cp_t('select_country.open_platform_login'), ENT_QUOTES, 'UTF-8'); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
if (file_exists(__DIR__ . '/../includes/control_pending_reg_alert.php')) {
    require_once __DIR__ . '/../includes/control_pending_reg_alert.php';
}
endControlLayout([]);
?>
