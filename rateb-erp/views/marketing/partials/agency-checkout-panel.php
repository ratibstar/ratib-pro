<?php
/**
 * Agency registration checkout (control_registration_requests) — shown inline on pricing.
 *
 * @var string $mktCheckoutPlan ERP plan slug from query (starter|professional|enterprise)
 * @var int $mktCheckoutYears
 */
declare(strict_types=1);

$erpPlanSlug = isset($mktCheckoutPlan) ? strtolower(trim((string) $mktCheckoutPlan)) : 'professional';
if ($erpPlanSlug === '') {
    $erpPlanSlug = 'professional';
}
$checkoutPlan = rateb_erp_plan_to_checkout_slug($erpPlanSlug);
$checkoutYears = isset($mktCheckoutYears) ? (int) $mktCheckoutYears : 1;
if ($checkoutYears !== 0 && $checkoutYears !== 1) {
    $checkoutYears = 1;
}

$goldYear = 5.0;
$goldMonth = 4.5;
$platYear = 800.0;
$platMonth = 67.0;
$planAmount = null;
if ($checkoutPlan === 'gold') {
    $planAmount = $checkoutYears === 0 ? $goldMonth : $goldYear;
} elseif ($checkoutPlan === 'platinum') {
    $planAmount = $checkoutYears === 0 ? $platMonth : $platYear;
}

$agencyCountriesFile = dirname(RATEB_ROOT, 1) . '/includes/agency-registration-countries.php';
if (is_file($agencyCountriesFile)) {
    require_once $agencyCountriesFile;
}
$countries = function_exists('rateb_agency_registration_countries')
    ? rateb_agency_registration_countries()
    : [];
$countryOtherValue = function_exists('rateb_agency_registration_country_other_value')
    ? rateb_agency_registration_country_other_value()
    : 'Other countries sending workers';
$isRtl = function_exists('rateb_is_rtl') && rateb_is_rtl();
?>
<div id="ratebMktAgencyRegister" class="rateb-mkt-agency-register d-none" hidden>
    <div class="rateb-mkt-agency-register__card">
        <button type="button" class="btn btn-link rateb-mkt-agency-register__back px-0" id="ratebMktBackToPricing">
            <i class="fas fa-arrow-<?php echo $isRtl ? 'right' : 'left'; ?> me-1"></i><?php echo __('cms_agency_register_back'); ?>
        </button>
        <h2 class="h4 mb-1"><i class="fas fa-building me-2"></i><?php echo __('cms_agency_register_title'); ?></h2>
        <p class="text-muted small mb-4"><?php echo __('cms_agency_register_intro'); ?></p>
        <div id="ratebMktRegisterSuccess" class="alert alert-success d-none" hidden role="alert">
            <i class="fas fa-check-circle me-2"></i><span data-success-text><?php echo __('cms_agency_register_success'); ?></span>
        </div>
        <form id="regForm" dir="<?php echo $isRtl ? 'rtl' : 'ltr'; ?>">
            <input type="hidden" name="plan" id="inputPlan" value="<?php echo htmlspecialchars($checkoutPlan, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="plan_amount" id="inputPlanAmount" value="<?php echo $planAmount !== null ? (float) $planAmount : ''; ?>">
            <input type="hidden" name="years" id="inputYears" value="<?php echo (int) $checkoutYears; ?>" data-allow-zero="1">
            <input type="hidden" name="payment_method" value="register">
            <div class="hp hp-field" aria-hidden="true"><input type="text" name="website_url" tabindex="-1" autocomplete="off"></div>
            <div class="mb-3">
                <label class="form-label"><?php echo __('cms_agency_name'); ?> *</label>
                <input type="text" class="form-control" name="agency_name" required maxlength="255">
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo __('cms_agency_id'); ?></label>
                <input type="text" class="form-control" name="agency_id" maxlength="64">
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo __('cms_agency_country'); ?> *</label>
                <select class="form-select" name="country" id="countrySelect" required data-other-value="<?php echo htmlspecialchars($countryOtherValue, ENT_QUOTES, 'UTF-8'); ?>">
                    <option value=""><?php echo __('cms_agency_country_select'); ?></option>
                    <?php foreach ($countries as $c) { ?>
                    <option value="<?php echo htmlspecialchars($c['value'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="mb-3 d-none" id="otherCountryWrap">
                <label class="form-label"><?php echo __('cms_agency_country_other'); ?></label>
                <input type="text" class="form-control" name="country_other" id="countryOther" maxlength="255">
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo __('cms_agency_email'); ?> *</label>
                <input type="email" class="form-control" name="contact_email" required maxlength="255">
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo __('cms_agency_phone'); ?> *</label>
                <input type="text" class="form-control" name="contact_phone" required maxlength="64">
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo __('cms_agency_site_url'); ?></label>
                <input type="url" class="form-control" name="desired_site_url" maxlength="512" placeholder="https://your-agency.rateb.sa">
            </div>
            <div class="mb-4">
                <label class="form-label"><?php echo __('cms_agency_notes'); ?></label>
                <textarea class="form-control" name="notes" rows="3" maxlength="2000"></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100" id="btnSubmit">
                <i class="fas fa-paper-plane me-2"></i><?php echo __('cms_agency_register_submit'); ?>
            </button>
        </form>
    </div>
</div>
