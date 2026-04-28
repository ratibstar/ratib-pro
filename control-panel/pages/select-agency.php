<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/select-agency.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/select-agency.php`.
 */
/**
 * Control Panel: Select agency
 */
require_once __DIR__ . '/../includes/config.php';
if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
$allowed = getAllowedCountrySlugs();
$hasAccess = ($allowed === null) || !empty($allowed);
if (!$hasAccess) {
    http_response_code(403);
    die('Access denied.');
}
unset($_SESSION['control_use_own_program']);

$ctrl = $GLOBALS['control_conn'] ?? null;
if ($ctrl === null) die('Control panel database unavailable.');

if (isset($_GET['agency_id']) && ctype_digit($_GET['agency_id'])) {
    $agencyId = (int)$_GET['agency_id'];
    $hasCountryCol = ($ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'")->num_rows > 0);
    $selCols = $hasCountryCol ? "id, name, country_id" : "id, name";
    $stmt = $ctrl->prepare("SELECT $selCols FROM control_agencies WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("i", $agencyId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $_SESSION['control_agency_id'] = (int)$row['id'];
        $_SESSION['control_agency_name'] = $row['name'];
        $_SESSION['control_country_id'] = null;
        $_SESSION['control_country_name'] = null;
        if (isset($row['country_id']) && (int)$row['country_id'] > 0) {
            $cStmt = $ctrl->prepare("SELECT id, name FROM control_countries WHERE id = ? AND is_active = 1 LIMIT 1");
            if ($cStmt) {
                $cStmt->bind_param("i", $row['country_id']);
                $cStmt->execute();
                $cRes = $cStmt->get_result();
                if ($cRes && $cRes->num_rows > 0) {
                    $cRow = $cRes->fetch_assoc();
                    $_SESSION['control_country_id'] = (int)$cRow['id'];
                    $_SESSION['control_country_name'] = $cRow['name'];
                }
                $cStmt->close();
            }
        }
        $_SESSION['user_id'] = 0;
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = 'Control:' . ($_SESSION['control_username'] ?? '');
        $_SESSION['role_id'] = 1;
        $_SESSION['user_permissions'] = [];
        $stmt->close();
        header('Location: ' . pageUrl('control/dashboard.php'));
        exit;
    }
    $stmt->close();
}

$countryId = isset($_GET['country_id']) && ctype_digit($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
$countryName = null;
$agencies = [];
$hasCountryId = false;
try {
    $cols = $ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
    $hasCountryId = $cols && $cols->num_rows > 0;
} catch (Throwable $e) { /* ignore */ }

if ($hasCountryId && $countryId > 0) {
    $stmt = $ctrl->prepare("SELECT id, name, slug FROM control_countries WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("i", $countryId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $countryRow = $res->fetch_assoc();
        $countryName = $countryRow['name'];
        $countrySlug = $countryRow['slug'] ?? '';
        $stmt->close();
        if (!hasControlCountryAccess($countrySlug)) {
            header('Location: ' . pageUrl('select-country.php'));
            exit;
        }
    }
}

if ($hasCountryId && $countryId > 0) {
    $stmt = $ctrl->prepare("SELECT id, name, slug, site_url FROM control_agencies WHERE country_id = ? AND is_active = 1 ORDER BY sort_order ASC, name ASC");
    $stmt->bind_param("i", $countryId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) $agencies[] = $row;
        $stmt->close();
    }
} else {
    $res = $ctrl->query("SELECT id, name, slug, site_url FROM control_agencies WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) $agencies[] = $row;
        $res->close();
    }
}

if ($hasCountryId && $countryId === 0) {
    $chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
    if ($chk && $chk->num_rows > 0) {
        header('Location: ' . pageUrl('select-country.php'));
        exit;
    }
}

require_once __DIR__ . '/../includes/control/layout-wrapper.php';
$pageTitle = $countryName ? 'Agencies in ' . htmlspecialchars($countryName) : 'Select Agency';
startControlLayout($pageTitle, ['css/control/select-agency.css'], []);
?>
<div class="module-section">
    <?php if ($countryId > 0): ?>
    <div class="select-agency-back-wrap">
        <a href="<?php echo pageUrl('select-country.php'); ?>" class="back-button"><i class="fas fa-arrow-left"></i><span>Back to Countries</span></a>
    </div>
    <?php endif; ?>
    <p class="text-muted mb-4">Choose an agency to manage.</p>
    <?php if (empty($agencies)): ?>
        <div class="alert alert-warning">No agencies configured.</div>
    <?php else: ?>
        <div class="agency-grid">
            <?php foreach ($agencies as $a): ?>
                <a href="?agency_id=<?php echo (int)$a['id']; ?>" class="agency-card">
                    <h3><?php echo htmlspecialchars($a['name']); ?></h3>
                    <div class="slug"><?php echo htmlspecialchars($a['slug']); ?></div>
                    <?php if (!empty($a['site_url'])): ?><div class="url"><i class="fas fa-link me-1"></i><?php echo htmlspecialchars($a['site_url']); ?></div><?php endif; ?>
                    <div class="select-agency-hint"><i class="fas fa-arrow-right"></i> Select Agency</div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endControlLayout([]); ?>
