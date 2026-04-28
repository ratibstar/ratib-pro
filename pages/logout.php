<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/logout.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/logout.php`.
 */
require_once '../includes/config.php';
require_once __DIR__ . '/../admin/core/EventBus.php';

$countryId = isset($_SESSION['country_id']) ? (int)$_SESSION['country_id'] : 0;
$agencyId = isset($_SESSION['agency_id']) ? (int)$_SESSION['agency_id'] : 0;
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

emitEvent('AUTH_LOGOUT', 'info', 'User logout', [
    'tenant_id' => $countryId > 0 ? $countryId : null,
    'user_id' => $userId > 0 ? $userId : null,
    'source' => 'auth',
    'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'pages/logout.php'),
    'mode' => 'session',
]);

// Control bridge sometimes has agency_id but missing country_id — resolve so redirect can use slug + agency_id
if ($countryId <= 0 && $agencyId > 0) {
    $lookup = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
    $db = $lookup ?: ($GLOBALS['conn'] ?? null);
    if ($db instanceof mysqli) {
        $chk = @$db->query("SHOW TABLES LIKE 'control_agencies'");
        if ($chk && $chk->num_rows > 0) {
            $stmt = @$db->prepare('SELECT country_id FROM control_agencies WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $agencyId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0 && ($row = $res->fetch_assoc())) {
                    $countryId = (int)($row['country_id'] ?? 0);
                }
                $stmt->close();
            }
        }
    }
}

if (($countryId > 0 || $agencyId > 0) && function_exists('ratib_set_login_context_cookies')) {
    ratib_set_login_context_cookies($countryId, $agencyId);
}

// Control panel and Ratib Pro can share the same session cookie. Never destroy the whole
// session when control_* keys exist; only clear Ratib keys so control panel stays logged in.
$hasControlSessionKeys = !empty($_SESSION['control_logged_in'])
    || isset($_SESSION['control_username'])
    || isset($_SESSION['control_admin_id'])
    || isset($_SESSION['control_country_id'])
    || isset($_SESSION['control_agency_id']);

if ($hasControlSessionKeys && session_status() === PHP_SESSION_ACTIVE) {
    foreach (array_keys($_SESSION) as $k) {
        if (!is_string($k) || strncmp($k, 'control_', 8) !== 0) {
            unset($_SESSION[$k]);
        }
    }
} else {
    session_unset();
    session_destroy();
    session_start();
}

// Redirect to country-specific login (e.g. /kenya/login) when known, so user sees single-country page
$redirectUrl = pageUrl('login.php') . '?message=logged_out';
if ($countryId > 0) {
    $slug = null;
    $lookup = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
    $db = $lookup ?: ($GLOBALS['conn'] ?? null);
    if ($db instanceof mysqli) {
        $chk = @$db->query("SHOW TABLES LIKE 'control_countries'");
        if ($chk && $chk->num_rows > 0) {
            $stmt = @$db->prepare("SELECT slug FROM control_countries WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $countryId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $slug = trim($row['slug'] ?? '');
                }
                $stmt->close();
            }
        }
    }
    if ($slug !== null && $slug !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'out.ratib.sa';
        $base = rtrim((defined('BASE_URL') ? BASE_URL : ''), '/');
        $redirectUrl = $scheme . '://' . $host . ($base ? $base . '/' : '/') . $slug . '/login?message=logged_out';
        if ($agencyId > 0) {
            $redirectUrl .= '&agency_id=' . $agencyId;
        }
    } else {
        $redirectUrl .= '&country_id=' . $countryId;
        if ($agencyId > 0) {
            $redirectUrl .= '&agency_id=' . $agencyId;
        }
    }
}
header('Location: ' . $redirectUrl);
exit(); 