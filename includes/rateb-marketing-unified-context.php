<?php
declare(strict_types=1);

/**
 * Build legacy /home CMS + checkout context for unified marketing (rateb-erp at /).
 * Does not render HTML. Requires project-root includes/config.php (Pro DB + site content).
 *
 * @return array<string, mixed>|null
 */
function rateb_marketing_unified_context(): ?array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $root = dirname(__DIR__);
    $config = $root . '/includes/config.php';
    if (!is_file($config)) {
        return null;
    }

    require_once $config;
    require_once $root . '/includes/rateb-public-base-url.php';
    require_once $root . '/includes/site-content.php';
    require_once $root . '/includes/site-content-home-data.php';
    require_once $root . '/includes/rateb-public-marketing-density.php';

    if (!function_exists('rateb_ngenius_env')) {
        $envPhp = $root . '/config/env.php';
        if (is_file($envPhp)) {
            require_once $envPhp;
        }
    }

    $ratebCheckoutCurrency = 'SAR';
    $ratebUsdToSar = 3.75;
    if (function_exists('rateb_ngenius_env')) {
        $ratebCheckoutCurrency = strtoupper(trim((string) rateb_ngenius_env('NGENIUS_CHECKOUT_CURRENCY', 'SAR'))) ?: 'SAR';
        $ratebUsdToSar = (float) rateb_ngenius_env('NGENIUS_USD_TO_SAR', '3.75');
    }
    if (!is_finite($ratebUsdToSar) || $ratebUsdToSar <= 0) {
        $ratebUsdToSar = 3.75;
    }
    $ratebDisplayCheckoutCurrency = $ratebCheckoutCurrency;
    $ratebDisplayNgeniusLabel = ($ratebCheckoutCurrency === 'SAR') ? 'N-Genius KSA' : 'N-Genius';
    $ratebDisplayUsdRate = $ratebUsdToSar;

    $baseUrl = rateb_public_site_base_url();
    $ratebDomainsIframeSrc = $baseUrl . '/modules/infrastructure-marketplace/Views/marketplace/index.php?focus=domains&embed=1#infra-domain-search';

    $openRegister = isset($_GET['open']) && trim((string) ($_GET['open'] ?? '')) === 'register';
    $planRaw = isset($_GET['plan']) ? trim((string) $_GET['plan']) : '';
    $plan = $planRaw !== '' ? $planRaw : ($openRegister ? 'gold' : 'pro');
    if ($plan === '') {
        $plan = 'pro';
    }

    $goldTestPriceYear1 = 5.0;
    $goldTestPriceMonth = 4.5;
    $platinumTestPriceYear1 = 800.0;
    $platinumTestPriceMonth = 67.0;
    $goldListPriceYear1 = $goldTestPriceYear1 * 2;
    $goldListPriceMonth = $goldTestPriceMonth * 2;
    $platinumListPriceYear1 = $platinumTestPriceYear1 * 2;
    $platinumListPriceMonth = $platinumTestPriceMonth * 2;

    $amount = isset($_GET['amount']) ? (float) $_GET['amount'] : null;
    $years = isset($_GET['years']) ? (int) $_GET['years'] : null;
    if ($years !== null && $years !== 0 && $years !== 1) {
        $years = 1;
    }

    $plans = [
        'gold' => ['label' => 'Gold', 'amount' => $goldTestPriceYear1],
        'platinum' => ['label' => 'Platinum', 'amount' => $platinumTestPriceYear1],
        'pro' => ['label' => 'Pro', 'amount' => null],
    ];
    $planLabel = $plans[$plan]['label'] ?? ucfirst($plan);
    $planAmount = ($amount !== null) ? $amount : null;
    if ($planAmount === null && isset($plans[$plan])) {
        if (($plan === 'gold' || $plan === 'platinum') && $years !== null) {
            $y = (int) $years;
            if ($y === 0) {
                $planAmount = $plan === 'gold' ? $goldTestPriceMonth : $platinumTestPriceMonth;
            } elseif ($y === 1) {
                $planAmount = $plan === 'gold' ? $goldTestPriceYear1 : $platinumTestPriceYear1;
            } else {
                $planAmount = $plans[$plan]['amount'] ?? null;
            }
        } else {
            $planAmount = $plans[$plan]['amount'] ?? null;
        }
    }

    $countries = ['Bangladesh', 'Uganda', 'Kenya', 'Sri Lanka', 'Philippines', 'Indonesia', 'Ethiopia', 'Nigeria', 'Rwanda', 'Thailand', 'Nepal', 'Other countries sending workers'];
    $ratebLockedCountryName = '';
    $ratebCountryIsLocked = ($ratebLockedCountryName !== '');

    $ratebHome = rateb_site_content_home_flat(false);
    $sanitize = $root . '/includes/rateb-site-content-rebrand-sanitize.php';
    if (is_file($sanitize)) {
        require_once $sanitize;
        if (function_exists('rateb_site_content_rebrand_sanitize_flat') && function_exists('rateb_site_content_defaults_home')) {
            $ratebHome = rateb_site_content_rebrand_sanitize_flat($ratebHome, rateb_site_content_defaults_home());
        }
    }

    $ratebPricingStarterLines = function_exists('rateb_site_content_home_nl_lines')
        ? rateb_site_content_home_nl_lines($ratebHome['home.pricing.starter.features'] ?? '')
        : [];
    $ratebPricingGoldLines = function_exists('rateb_site_content_home_nl_lines')
        ? rateb_site_content_home_nl_lines($ratebHome['home.pricing.gold.features'] ?? '')
        : [];
    $ratebPricingPlatinumLines = function_exists('rateb_site_content_home_nl_lines')
        ? rateb_site_content_home_nl_lines($ratebHome['home.pricing.platinum.features'] ?? '')
        : [];

    // Media slots (program + video) — same rules as nav bootstrap.
    $ratebProgSlotsOut = [];
    if (function_exists('rateb_site_content_home_program_slots_from_flat')) {
        foreach (rateb_site_content_home_program_slots_from_flat($ratebHome) as $slot) {
            $stored = trim((string) ($slot['src'] ?? ''));
            if ($stored === '') {
                continue;
            }
            $imgSrc = rateb_site_content_asset_url($baseUrl, $stored, '', '');
            if (trim($imgSrc) === '') {
                continue;
            }
            $ratebProgSlotsOut[] = [
                'src' => $imgSrc,
                'alt' => (string) ($slot['alt'] ?? ''),
                'caption' => (string) ($slot['caption'] ?? ''),
            ];
        }
    }

    $ratebVideoSources = [];
    $videoExists = false;
    if (function_exists('rateb_site_content_home_video_src_strings_from_flat') && function_exists('rateb_site_content_home_resolve_video_display_url')) {
        foreach (rateb_site_content_home_video_src_strings_from_flat($ratebHome) as $vs) {
            $vs = trim((string) $vs);
            if ($vs === '') {
                continue;
            }
            $u = rateb_site_content_home_resolve_video_display_url($vs, $baseUrl);
            if ($u !== '') {
                $ratebVideoSources[] = [
                    'url' => $u,
                    'is_image' => function_exists('rateb_site_content_media_stored_is_image')
                        && rateb_site_content_media_stored_is_image($vs),
                ];
            }
        }
    }
    if ($ratebVideoSources !== []) {
        $videoExists = true;
    }
    $ratebVideoSlotsRawCheck = trim((string) ($ratebHome['home.video.slots_json'] ?? ''));
    $ratebVideoClearedInCms = false;
    if ($ratebVideoSlotsRawCheck !== '') {
        $decoded = json_decode($ratebVideoSlotsRawCheck, true);
        $ratebVideoClearedInCms = is_array($decoded) && count($decoded) === 0;
    }
    $ratebShowHomeVideoBand = !empty($ratebVideoSources) || (!$videoExists && !$ratebVideoClearedInCms);

    $ratebHomeAnchor = static function (string $hash): string {
        if (function_exists('rateb_public_marketing_home_anchor')) {
            return rateb_public_marketing_home_anchor($hash);
        }
        return $hash !== '' && $hash[0] === '#' ? $hash : '#' . ltrim($hash, '#');
    };
    $ratebRegisterHref = $ratebHomeAnchor('#register');
    $ratebWalkthroughHref = is_file($root . '/includes/rateb-architecture-sections.php')
        ? rtrim($baseUrl, '/') . '/architecture/'
        : $ratebHomeAnchor('#enterprise-infrastructure');

    if (!function_exists('rateb_enterprise_mailto')) {
        function rateb_enterprise_mailto(string $subject): string
        {
            return 'mailto:info@rateb.sa?subject=' . rawurlencode($subject);
        }
    }

    $entInclude = $root . '/includes/rateb-enterprise-trust-home.php';
    if (is_file($entInclude)) {
        require_once $entInclude;
    } elseif (!function_exists('rateb_enterprise_trust_render_home')) {
        function rateb_enterprise_trust_render_home(array $ratebHome, string $baseUrl): void
        {
        }
    }

    $opInclude = $root . '/includes/rateb-operational-proof-render.php';
    $ratebOpProofAvailable = is_file($opInclude);
    if ($ratebOpProofAvailable) {
        require_once $opInclude;
    } elseif (!function_exists('rateb_operational_proof_render')) {
        function rateb_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void
        {
        }
    }

    $ratebHomeBootstrap = [
        'checkoutCurrency' => $ratebCheckoutCurrency,
        'displayCheckoutCurrency' => $ratebDisplayCheckoutCurrency,
        'displayNgeniusLabel' => $ratebDisplayNgeniusLabel,
        'displayUsdRate' => (float) $ratebDisplayUsdRate,
        'usdToSar' => (float) $ratebUsdToSar,
        'openRegister' => $openRegister,
        'initialPlan' => $plan,
        'initialAmount' => $planAmount !== null ? (float) $planAmount : null,
        'initialYears' => $years !== null ? (int) $years : 1,
        'goldMonth' => (float) $goldTestPriceMonth,
        'goldYear1' => (float) $goldTestPriceYear1,
        'platinumMonth' => (float) $platinumTestPriceMonth,
        'platinumYear1' => (float) ($plans['platinum']['amount'] ?? $platinumTestPriceYear1),
    ];

    $cached = [
        'baseUrl' => $baseUrl,
        'ratebHome' => $ratebHome,
        'ratebDomainsIframeSrc' => $ratebDomainsIframeSrc,
        'openRegister' => $openRegister,
        'plan' => $plan,
        'planLabel' => $planLabel,
        'planAmount' => $planAmount,
        'years' => $years,
        'plans' => $plans,
        'countries' => $countries,
        'ratebCountryIsLocked' => $ratebCountryIsLocked,
        'ratebLockedCountryName' => $ratebLockedCountryName,
        'goldTestPriceYear1' => $goldTestPriceYear1,
        'goldTestPriceMonth' => $goldTestPriceMonth,
        'platinumTestPriceYear1' => $platinumTestPriceYear1,
        'platinumTestPriceMonth' => $platinumTestPriceMonth,
        'goldListPriceYear1' => $goldListPriceYear1,
        'goldListPriceMonth' => $goldListPriceMonth,
        'platinumListPriceYear1' => $platinumListPriceYear1,
        'platinumListPriceMonth' => $platinumListPriceMonth,
        'ratebPricingStarterLines' => $ratebPricingStarterLines,
        'ratebPricingGoldLines' => $ratebPricingGoldLines,
        'ratebPricingPlatinumLines' => $ratebPricingPlatinumLines,
        'ratebProgSlotsOut' => $ratebProgSlotsOut,
        'ratebVideoSources' => $ratebVideoSources,
        'videoExists' => $videoExists,
        'ratebVideoClearedInCms' => $ratebVideoClearedInCms,
        'ratebShowHomeVideoBand' => $ratebShowHomeVideoBand,
        'ratebRegisterHref' => $ratebRegisterHref,
        'ratebWalkthroughHref' => $ratebWalkthroughHref,
        'ratebCheckoutCurrency' => $ratebCheckoutCurrency,
        'ratebDisplayCheckoutCurrency' => $ratebDisplayCheckoutCurrency,
        'ratebDisplayNgeniusLabel' => $ratebDisplayNgeniusLabel,
        'ratebDisplayUsdRate' => $ratebDisplayUsdRate,
        'ratebUsdToSar' => $ratebUsdToSar,
        'ratebOpProofAvailable' => $ratebOpProofAvailable,
        'ratebHomeBootstrap' => $ratebHomeBootstrap,
        'sectionsPath' => $root . '/includes/marketing-unified/sections-body.php',
        'sectionsDeepPath' => $root . '/includes/marketing-unified/sections-deep.php',
        'sectionsCommercePath' => $root . '/includes/marketing-unified/sections-commerce.php',
        'heroExtrasPath' => $root . '/includes/marketing-unified/hero-extras.php',
        'galleryMarkupPath' => $root . '/includes/rateb-gallery-lightbox-markup.php',
    ];

    return $cached;
}

if (!function_exists('rateb_marketing_unified_render')) {
    /** @param 'full'|'commerce' $mode */
    function rateb_marketing_unified_render(string $mode = 'full'): void
    {
        $ctx = rateb_marketing_unified_context();
        if ($ctx === null) {
            return;
        }
        extract($ctx, EXTR_SKIP);
        echo '<div class="rateb-mkt-legacy rateb-saas-home" data-rateb-unified-home="1">';
        if ($mode === 'full' && is_file($heroExtrasPath)) {
            require $heroExtrasPath;
        }
        if ($mode === 'full' && is_file($sectionsDeepPath)) {
            require $sectionsDeepPath;
        } elseif ($mode === 'full' && is_file($sectionsPath)) {
            require $sectionsPath;
        }
        if (is_file($sectionsCommercePath)) {
            require $sectionsCommercePath;
        }
        echo '</div>';
        echo '<script type="application/json" id="rateb-home-bootstrap">';
        echo json_encode($ratebHomeBootstrap, JSON_UNESCAPED_SLASHES);
        echo '</script>';
        $origin = rtrim($baseUrl, '/');
        echo '<script>window.RATEB_BASE_URL=' . json_encode($origin, JSON_UNESCAPED_SLASHES) . ';</script>';
        echo '<script src="' . htmlspecialchars($origin . '/js/pages/home-page.js?v=unified-home', ENT_QUOTES, 'UTF-8') . '"></script>';
        echo '<script src="' . htmlspecialchars($origin . '/js/payment.js?v=unified-home', ENT_QUOTES, 'UTF-8') . '"></script>';
        $lb = $origin . '/js/pages/rateb-gallery-lightbox.js';
        if (is_file(dirname(__DIR__) . '/js/pages/rateb-gallery-lightbox.js')) {
            echo '<script src="' . htmlspecialchars($lb . '?v=unified-home', ENT_QUOTES, 'UTF-8') . '"></script>';
        }
        if ($mode === 'full' && is_file($galleryMarkupPath)) {
            require $galleryMarkupPath;
        }
    }
}
