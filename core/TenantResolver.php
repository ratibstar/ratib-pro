<?php
/**
 * EN: Handles core framework/runtime behavior in `core/TenantResolver.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/TenantResolver.php`.
 */
/**
 * TenantResolver - Subdomain-based tenant detection
 *
 * Load BEFORE any DB interaction.
 * Extracts subdomain (e.g. sa from sa.out.ratib.sa), validates against countries table.
 *
 * Usage: require_once __DIR__ . '/core/TenantResolver.php';
 */
if (defined('TENANT_RESOLVED')) {
    return;
}

// Must have DB constants (load config/env first)
if (!defined('DB_HOST') || !defined('DB_NAME')) {
    die('TenantResolver: Load config/env before TenantResolver.');
}

/**
 * Allow root domain during migration (out.ratib.sa without subdomain)
 * Set to false when subdomains are deployed.
 */
define('TENANT_ALLOW_ROOT_DOMAIN', getenv('TENANT_ALLOW_ROOT_DOMAIN') === '1' || getenv('TENANT_ALLOW_ROOT_DOMAIN') === 'true');

/**
 * Base domain for tenant subdomains (e.g. out.ratib.sa)
 * Subdomains: sa.out.ratib.sa, ae.out.ratib.sa
 */
define('TENANT_BASE_DOMAIN', getenv('TENANT_BASE_DOMAIN') ?: 'out.ratib.sa');

// EN: Read and normalize requested host before any tenant lookup.
// AR: قراءة وتوحيد اسم النطاق المطلوب قبل أي بحث عن المستأجر.
$host = $_SERVER['HTTP_HOST'] ?? '';
$host = strtolower(trim($host));

// Extract subdomain: sa.out.ratib.sa -> sa
$baseDomain = TENANT_BASE_DOMAIN;
$isBaseDomain = ($host === $baseDomain || $host === 'www.' . $baseDomain);

// EN: Decide tenant code source: migration fallback on base domain, otherwise subdomain.
// AR: تحديد مصدر كود المستأجر: وضع الترحيل للنطاق الأساسي أو استخدام النطاق الفرعي.
if ($isBaseDomain) {
    if (!TENANT_ALLOW_ROOT_DOMAIN) {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        die('<h1>Access Denied</h1><p>Tenant subdomain required. Use e.g. <strong>sa.' . htmlspecialchars($baseDomain) . '</strong></p>');
    }
    // Migration mode: use first active country as default tenant
    $defaultCode = 'sa';
    $subdomain = $defaultCode;
} else {
    $parts = explode('.', $host);
    $subdomain = $parts[0] ?? '';
    if (count($parts) < 2 || $subdomain === 'www') {
        $subdomain = '';
    }
}

if (empty($subdomain)) {
    http_response_code(400);
    header('Content-Type: text/html; charset=UTF-8');
    die('<h1>Invalid Request</h1><p>Could not determine tenant from host: ' . htmlspecialchars($host) . '</p>');
}

// EN: Validate tenant identity against countries table to prevent unknown domains.
// AR: التحقق من هوية المستأجر من جدول الدول لمنع النطاقات غير المعروفة.
// Validate tenant against database
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : 3306) . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log('TenantResolver: DB connection failed - ' . $e->getMessage());
    http_response_code(500);
    die('TenantResolver: Database unavailable.');
}

$stmt = $pdo->prepare("SELECT id, name, code, domain, status, subscription_plan, subscription_expiry FROM countries WHERE code = ? AND status IN ('active','suspended','inactive') LIMIT 1");
$stmt->execute([$subdomain]);
$tenant = $stmt->fetch();

if (!$tenant) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    die('<h1>Tenant Not Found</h1><p>Invalid subdomain: <strong>' . htmlspecialchars($subdomain) . '</strong></p><p>Valid tenants: sa, ae, eg, bd, jo, kw, bh, om, qa, iq, ye, pk</p>');
}

// EN: Enforce operational status/subscription before exposing tenant application.
// AR: فرض حالة التشغيل والاشتراك قبل السماح بالدخول لتطبيق المستأجر.
// Subscription check
$today = date('Y-m-d');
if ($tenant['status'] === 'suspended' || $tenant['status'] === 'inactive') {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Tenant Suspended</h1><p>This tenant is currently ' . htmlspecialchars($tenant['status']) . '. Contact administrator.</p>';
    exit;
}
if (!empty($tenant['subscription_expiry']) && $tenant['subscription_expiry'] < $today) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h1>Subscription Expired</h1><p>Subscription expired on ' . htmlspecialchars($tenant['subscription_expiry']) . '. Contact administrator to renew.</p>';
    exit;
}

define('TENANT_ID', (int) $tenant['id']);
define('TENANT_CODE', $tenant['code']);
define('TENANT_NAME', $tenant['name']);
define('TENANT_DOMAIN', $tenant['domain']);
define('TENANT_RESOLVED', true);
