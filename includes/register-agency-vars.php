<?php
/**
 * Shared registration form variables (plans, currency, countries).
 * Used by pages/register-agency.php and legacy home.php register section.
 */
declare(strict_types=1);

if (!function_exists('rateb_ngenius_env')) {
    require_once __DIR__ . '/../config/env.php';
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
$ratebDefaultUsdRates = [
    'USD' => 1.00,
    'SAR' => 3.75,
    'BDT' => 117.50,
    'IDR' => 16000.00,
    'ETB' => 57.00,
    'PHP' => 57.00,
    'KES' => 129.00,
    'UGX' => 3800.00,
    'NGN' => 1450.00,
    'RWF' => 1300.00,
    'LKR' => 300.00,
    'NPR' => 133.00,
    'THB' => 36.00,
];
$countryCurrencyByCode = [
    'BD' => 'BDT', 'ET' => 'ETB', 'PH' => 'PHP', 'KE' => 'KES', 'ID' => 'IDR',
    'UG' => 'UGX', 'NG' => 'NGN', 'RW' => 'RWF', 'LK' => 'LKR', 'NP' => 'NPR', 'TH' => 'THB',
];
$countryCurrencyByName = [
    'BANGLADESH' => 'BDT', 'ETHIOPIA' => 'ETB', 'PHILIPPINES' => 'PHP', 'KENYA' => 'KES',
    'INDONESIA' => 'IDR', 'UGANDA' => 'UGX', 'NIGERIA' => 'NGN', 'RWANDA' => 'RWF',
    'SRI LANKA' => 'LKR', 'NEPAL' => 'NPR', 'THAILAND' => 'THB',
];
$countryCurrencyBySlug = [
    'bangladesh' => 'BDT', 'ethiopia' => 'ETB', 'philippines' => 'PHP', 'kenya' => 'KES',
    'indonesia' => 'IDR', 'uganda' => 'UGX', 'nigeria' => 'NGN', 'rwanda' => 'RWF',
    'sri-lanka' => 'LKR', 'srilanka' => 'LKR', 'nepal' => 'NPR', 'thailand' => 'THB',
];
$countryNameByCode = [
    'BD' => 'Bangladesh', 'UG' => 'Uganda', 'KE' => 'Kenya', 'LK' => 'Sri Lanka',
    'PH' => 'Philippines', 'ID' => 'Indonesia', 'ET' => 'Ethiopia', 'NG' => 'Nigeria',
    'RW' => 'Rwanda', 'TH' => 'Thailand', 'NP' => 'Nepal',
];
$countryNameBySlug = [
    'bangladesh' => 'Bangladesh', 'uganda' => 'Uganda', 'kenya' => 'Kenya',
    'sri-lanka' => 'Sri Lanka', 'srilanka' => 'Sri Lanka', 'philippines' => 'Philippines',
    'indonesia' => 'Indonesia', 'ethiopia' => 'Ethiopia', 'nigeria' => 'Nigeria',
    'rwanda' => 'Rwanda', 'thailand' => 'Thailand', 'nepal' => 'Nepal',
];

$countryCodeRaw = strtoupper(trim((string) ($_GET['country_code'] ?? ($_SESSION['country_code'] ?? ''))));
$countryNameRaw = strtoupper(trim((string) ($_GET['country_name'] ?? $_GET['country'] ?? ($_SESSION['country_name'] ?? ''))));
$countrySlugRaw = strtolower(trim((string) ($_GET['country_slug'] ?? '')));
if ($countrySlugRaw === '') {
    $ref = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref !== '') {
        $refPath = (string) parse_url($ref, PHP_URL_PATH);
        $refPath = trim($refPath, '/');
        if ($refPath !== '') {
            $firstSeg = strtolower((string) strtok($refPath, '/'));
            if ($firstSeg !== '' && isset($countryCurrencyBySlug[$firstSeg])) {
                $countrySlugRaw = $firstSeg;
            }
        }
    }
}
if ($countryCodeRaw !== '' && isset($countryCurrencyByCode[$countryCodeRaw])) {
    $ratebDisplayCheckoutCurrency = $countryCurrencyByCode[$countryCodeRaw];
} elseif ($countryNameRaw !== '' && isset($countryCurrencyByName[$countryNameRaw])) {
    $ratebDisplayCheckoutCurrency = $countryCurrencyByName[$countryNameRaw];
} elseif ($countrySlugRaw !== '' && isset($countryCurrencyBySlug[$countrySlugRaw])) {
    $ratebDisplayCheckoutCurrency = $countryCurrencyBySlug[$countrySlugRaw];
}
if ($ratebDisplayCheckoutCurrency !== 'SAR') {
    $ratebDisplayNgeniusLabel = 'N-Genius ' . $ratebDisplayCheckoutCurrency;
}
$ratebDisplayUsdRate = $ratebDefaultUsdRates[$ratebDisplayCheckoutCurrency] ?? 1.00;
$ratebDisplayRateKey = 'NGENIUS_USD_TO_' . preg_replace('/[^A-Z]/', '', $ratebDisplayCheckoutCurrency);
$ratebDisplayUsdRateEnv = (float) rateb_ngenius_env($ratebDisplayRateKey, (string) $ratebDisplayUsdRate);
if (is_finite($ratebDisplayUsdRateEnv) && $ratebDisplayUsdRateEnv > 0) {
    $ratebDisplayUsdRate = $ratebDisplayUsdRateEnv;
}
$ratebLockedCountryName = '';
if ($countryCodeRaw !== '' && isset($countryNameByCode[$countryCodeRaw])) {
    $ratebLockedCountryName = $countryNameByCode[$countryCodeRaw];
} elseif ($countrySlugRaw !== '' && isset($countryNameBySlug[$countrySlugRaw])) {
    $ratebLockedCountryName = $countryNameBySlug[$countrySlugRaw];
} elseif ($countryNameRaw !== '') {
    $countryNameTitle = ucwords(strtolower($countryNameRaw));
    if ($countryNameTitle !== '') {
        $ratebLockedCountryName = $countryNameTitle;
    }
}

$planRaw = isset($_GET['plan']) ? trim((string) $_GET['plan']) : '';
$plan = $planRaw !== '' ? $planRaw : 'gold';
if ($plan === '') {
    $plan = 'gold';
}
$goldTestPriceYear1 = 5;
$goldTestPriceMonth = 4.5;
$platinumTestPriceYear1 = 800;
$platinumTestPriceMonth = 67;
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
$countries = [
    'Bangladesh', 'Uganda', 'Kenya', 'Sri Lanka', 'Philippines', 'Indonesia',
    'Ethiopia', 'Nigeria', 'Rwanda', 'Thailand', 'Nepal', 'Other countries sending workers',
];
$ratebCountryIsLocked = ($ratebLockedCountryName !== '');
if ($ratebCountryIsLocked && !in_array($ratebLockedCountryName, $countries, true)) {
    array_unshift($countries, $ratebLockedCountryName);
}
