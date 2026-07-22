<?php
declare(strict_types=1);

/**
 * RATEB Contact Center — Customer Self-Service Portal (Phase 11).
 */
define('RCC_SKIP_ORCHESTRATOR_BOOT', true);

try {
    require_once dirname(__DIR__, 2) . '/bootstrap.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('RCC bootstrap failed: ' . $e->getMessage());
}

use Ratib\ContactCenter\App\Application\Services\SaaS\WhiteLabelService;

$tenantId = (int) ($_GET['tenant_id'] ?? 0);
$branding = [];
try {
    $whiteLabel = new WhiteLabelService();
    if ($tenantId < 1) {
        $tenantId = $whiteLabel->resolveTenantByDomain((string) ($_SERVER['HTTP_HOST'] ?? ''));
    }
    if ($tenantId < 1) {
        $tenantId = 1;
    }
    $branding = $whiteLabel->branding($tenantId);
} catch (Throwable $e) {
    error_log('[RCC Portal] ' . $e->getMessage());
    $tenantId = $tenantId > 0 ? $tenantId : 1;
}

$companyName = $branding['company_name'] ?? 'Customer Portal';
$logo = $branding['logo_url'] ?? '';
$primary = $branding['primary_color'] ?? '#2563eb';
$locale = $_GET['lang'] ?? 'ar';
$isAr = $locale === 'ar';

$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$assetBase = preg_replace('#/portal$#', '', rtrim($scriptDir, '/'));
if ($assetBase === '') {
    $assetBase = '/ratib-contact-center/public';
}
$cssUrl = $assetBase . '/asset.php?k=portal-css';
$jsUrl = $assetBase . '/asset.php?k=portal-js';
$apiBase = $assetBase . '/api/v1/customer-portal.php';
?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo $isAr ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <style>:root { --rcc-primary: <?php echo htmlspecialchars($primary, ENT_QUOTES, 'UTF-8'); ?>; }</style>
</head>
<body>
<div class="rcc-portal" id="rcc-portal" data-tenant="<?php echo $tenantId; ?>" data-api="<?php echo htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8'); ?>">
    <header class="rcc-portal__header">
        <?php if ($logo !== '') { ?><img src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt=""><?php } ?>
        <h1><?php echo htmlspecialchars($isAr ? ($branding['company_name_ar'] ?? $companyName) : $companyName, ENT_QUOTES, 'UTF-8'); ?></h1>
    </header>
    <section id="rcc-portal-login" class="rcc-portal__login">
        <h2><?php echo $isAr ? 'تسجيل الدخول' : 'Sign in'; ?></h2>
        <form id="rcc-portal-login-form">
            <label><?php echo $isAr ? 'البريد الإلكتروني' : 'Email'; ?>
                <input type="email" name="email" required autocomplete="username" dir="ltr">
            </label>
            <label><?php echo $isAr ? 'كلمة المرور' : 'Password'; ?>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit"><?php echo $isAr ? 'دخول' : 'Login'; ?></button>
        </form>
        <p id="rcc-portal-login-error" class="rcc-portal__error" role="alert"></p>
    </section>
    <section id="rcc-portal-app" hidden>
        <nav id="rcc-portal-nav" class="rcc-portal__nav"></nav>
        <div id="rcc-portal-panel" class="rcc-portal__panel"></div>
    </section>
</div>
<script src="<?php echo htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
