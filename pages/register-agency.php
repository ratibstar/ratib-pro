<?php
/**
 * Standalone bilingual agency registration page (EN + AR).
 * Replaces pages/home?open=register for checkout and control-panel client registration links.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/rateb-public-base-url.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('X-LiteSpeed-Cache-Control: no-cache', false);
}

require_once __DIR__ . '/../includes/register-agency-vars.php';

$baseUrl = rateb_public_site_base_url();
$ratebAfterRegisterUrl = rateb_registration_requests_queue_url($baseUrl);

require_once __DIR__ . '/../includes/site-content.php';
require_once __DIR__ . '/../includes/rateb-home-public-nav-bootstrap.php';

$ratebHomeBootstrap = [
    'checkoutCurrency' => $ratebCheckoutCurrency,
    'displayCheckoutCurrency' => $ratebDisplayCheckoutCurrency,
    'displayNgeniusLabel' => $ratebDisplayNgeniusLabel,
    'displayUsdRate' => (float) $ratebDisplayUsdRate,
    'usdToSar' => (float) $ratebUsdToSar,
    'openRegister' => true,
    'initialPlan' => $plan,
    'initialAmount' => $planAmount !== null ? (float) $planAmount : null,
    'initialYears' => $years !== null ? (int) $years : 1,
    'goldMonth' => (float) $goldTestPriceMonth,
    'goldYear1' => (float) $goldTestPriceYear1,
    'platinumMonth' => (float) $platinumTestPriceMonth,
    'platinumYear1' => (float) $platinumTestPriceYear1,
];

$ratebHomeJsPath = __DIR__ . '/../js/pages/home-page.js';
clearstatcache(true, $ratebHomeJsPath);
$ratebHomeJsQ = (string) (int) (@filemtime($ratebHomeJsPath) ?: time());
$ratebRegCssPath = __DIR__ . '/../css/pages/register-agency.css';
clearstatcache(true, $ratebRegCssPath);
$ratebRegCssQ = (string) (int) (@filemtime($ratebRegCssPath) ?: time());
$ratebPaymentJsVer = (int) (@filemtime(dirname(__DIR__) . '/js/payment.js') ?: time());
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register Your Agency | تسجيل وكالتك — RATEB</title>
    <meta name="description" content="Register your workforce agency with RATEB — bilingual registration form. تسجيل وكالة العمالة مع راتب.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/home-public.css?v=<?php echo htmlspecialchars($ratebHomePublicCssQuery ?? $ratebRegCssQ, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUrl); ?>/css/pages/register-agency.css?v=<?php echo htmlspecialchars($ratebRegCssQ, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="rateb-saas-home rateb-register-agency-body">
<div class="rateb-saas-bg" aria-hidden="true"><div class="rateb-saas-bg__gradient"></div></div>
<?php require __DIR__ . '/../includes/rateb-home-public-chrome-top.php'; ?>
<main class="rateb-register-agency-main" id="main">
    <div class="rateb-container rateb-register-agency-container">
        <?php require __DIR__ . '/../includes/register-agency-form.php'; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/rateb-home-public-footer.php'; ?>
<script type="application/json" id="rateb-home-bootstrap"><?php echo json_encode($ratebHomeBootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script>window.RATEB_BASE_URL = <?php echo json_encode($baseUrl); ?>;</script>
<script>window.RATEB_AFTER_REGISTER_URL = <?php echo json_encode($ratebAfterRegisterUrl); ?>;</script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/pages/home-page.js?v=<?php echo htmlspecialchars($ratebHomeJsQ, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars($baseUrl); ?>/js/payment.js?v=<?php echo $ratebPaymentJsVer; ?>"></script>
</body>
</html>
