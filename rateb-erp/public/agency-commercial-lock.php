<?php
/**
 * Production lock: Control Panel suspended/inactive agencies must not use ERP.
 * Lives under public/ so fast deploy + opcache-bust always pick it up (app/Core can lag).
 */
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;
}
if (defined('RATEB_HEALTH_PROBE') && RATEB_HEALTH_PROBE) {
    return;
}
if (defined('RATEB_SKIP_AGENCY_COMMERCIAL_LOCK') && RATEB_SKIP_AGENCY_COMMERCIAL_LOCK) {
    return;
}

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
if (stripos($uri, '/logout') !== false) {
    return;
}

$agencyId = defined('RATEB_ERP_AGENCY_ID') ? (int) RATEB_ERP_AGENCY_ID : 0;
$host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
if (strpos($host, ':') !== false) {
    $host = explode(':', $host, 2)[0];
}
if ($agencyId < 1 && $host === 'admin.rateb.sa') {
    $agencyId = 34;
}

$blocked = defined('RATEB_ERP_COMMERCIAL_SUSPENDED') && RATEB_ERP_COMMERCIAL_SUSPENDED;

if (!$blocked && $agencyId > 0) {
    $lookupFile = dirname(__DIR__, 2) . '/config/env/agency_lookup.php';
    if (is_file($lookupFile)) {
        require_once $lookupFile;
    }
    $conn = function_exists('rateb_agency_lookup_connection') ? rateb_agency_lookup_connection() : null;
    if ($conn instanceof mysqli) {
        $sql = 'SELECT COALESCE(is_active, 1) AS is_active';
        $hasSusp = @$conn->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
        if ($hasSusp && $hasSusp->num_rows > 0) {
            $sql .= ', COALESCE(is_suspended, 0) AS is_suspended';
        } else {
            $sql .= ', 0 AS is_suspended';
        }
        $sql .= ' FROM control_agencies WHERE id = ? LIMIT 1';
        $st = $conn->prepare($sql);
        if ($st) {
            $st->bind_param('i', $agencyId);
            $st->execute();
            $res = $st->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $st->close();
            if (is_array($row)) {
                $blocked = ((int) ($row['is_suspended'] ?? 0) === 1)
                    || ((int) ($row['is_active'] ?? 1) === 0);
            }
        }
        $conn->close();
    }
}

if (!$blocked && class_exists(\Rateb\App\Core\Database::class)) {
    try {
        $pdo = \Rateb\App\Core\Database::connection();
        $st = $pdo->query('SELECT status FROM rateb_companies ORDER BY id ASC LIMIT 1');
        $companyStatus = strtolower(trim((string) ($st ? $st->fetchColumn() : '')));
        if ($companyStatus === 'suspended') {
            $blocked = true;
        }
    } catch (Throwable $e) {
        // ignore — control_agencies is the authority
    }
}

if (!$blocked) {
    return;
}

http_response_code(403);
$accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
$wantsJson = strpos($uri, '/api/') !== false
    || strpos($accept, 'application/json') !== false
    || isset($_SERVER['HTTP_X_CSRF_TOKEN']);
if ($wantsJson) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'This agency is suspended. ERP is blocked until Control Panel unsuspends it.',
        'code' => 'AGENCY_SUSPENDED',
    ]);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
$safeId = htmlspecialchars((string) $agencyId, ENT_QUOTES, 'UTF-8');
echo '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>الوكالة معلّقة</title>';
echo '<style>body{font-family:Tahoma,Arial,sans-serif;background:#111827;color:#f9fafb;margin:0}.box{max-width:40rem;margin:12vh auto;padding:2rem;background:#1f2937;border-radius:12px}h1{margin:0 0 .75rem;font-size:1.4rem}p{line-height:1.6;color:#d1d5db}.en{direction:ltr;text-align:left;margin-top:1.5rem;border-top:1px solid #374151;padding-top:1.25rem}</style>';
echo '</head><body><main class="box"><h1>هذه الوكالة معلّقة</h1>';
echo '<p>تم إيقاف نظام رتب ERP. سجّل الدخول غير مسموح حتى يتم إلغاء التعليق من إدارة الوكالات.</p>';
echo '<p>المعرف: ' . $safeId . '</p>';
echo '<div class="en"><h1>This agency is suspended</h1><p>RATEB ERP login and operations are blocked until the agency is unsuspended in Manage Agencies.</p></div>';
echo '</main></body></html>';
exit;
