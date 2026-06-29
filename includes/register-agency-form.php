<?php
/**
 * Bilingual Register Your Agency form (EN + AR).
 * Expects variables from register-agency-vars.php and optional $ratebHome CMS strings.
 */
declare(strict_types=1);

$ratebHome = is_array($ratebHome ?? null) ? $ratebHome : [];
$planHint = strip_tags($ratebHome['home.register.form.plan_hint'] ?? 'Select <strong>Gold (Business)</strong> or <strong>Platinum (Enterprise)</strong> to see the payment summary.', '<strong>');
$paymentPlaceholder = strip_tags($ratebHome['home.register.payment_placeholder'] ?? '<strong>Pricing summary</strong> — Select Business (Gold) or Enterprise (Platinum) at the top of this form.', '<strong>');
$paymentSummaryTitle = $ratebHome['home.register.payment_summary.title'] ?? 'Payment Summary';
$paymentSummaryFooter = $ratebHome['home.register.payment_summary.footer'] ?? 'Submit your request below. We will contact you about payment after review.';
?>
<section class="register-section rateb-register-wrap rateb-register-agency-page" id="register">
    <div class="rateb-info">
        <h2 class="rateb-bilingual-heading">
            <span class="label-en"><i class="fas fa-info-circle me-2 register-info-icon"></i>Why RATEB?</span>
            <span class="label-ar" dir="rtl" lang="ar">لماذا راتب؟</span>
        </h2>
        <p class="rateb-bilingual-sub">
            <span class="label-en">Enterprise workforce program infrastructure for sending agencies.</span>
            <span class="label-ar" dir="rtl" lang="ar">بنية تحتية لمشاريع القوى العاملة لوكالات الإرسال.</span>
        </p>
        <ul class="checklist">
            <li><i class="fas fa-check-circle"></i><span>Recruitment orchestration / تنسيق التوظيف</span></li>
            <li><i class="fas fa-check-circle"></i><span>Branded agency portal / بوابة وكالة بعلامتك</span></li>
            <li><i class="fas fa-check-circle"></i><span>Worker-sending countries / دول إرسال العمالة</span></li>
            <li><i class="fas fa-check-circle"></i><span>Contracts &amp; compliance / العقود والامتثال</span></li>
            <li><i class="fas fa-check-circle"></i><span>Document tracking / تتبع المستندات</span></li>
            <li><i class="fas fa-check-circle"></i><span>Reporting &amp; analytics / التقارير والتحليلات</span></li>
        </ul>
    </div>
    <div class="form-card">
        <h1 class="rateb-bilingual-title">
            <span class="label-en"><i class="fas fa-building me-2"></i>Register Your Agency</span>
            <span class="label-ar" dir="rtl" lang="ar"><i class="fas fa-building ms-2"></i>تسجيل وكالتك</span>
        </h1>
        <p class="subtitle rateb-bilingual-sub">
            <span class="label-en">Request <?php echo htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8'); ?> plan access. We will review and contact you.</span>
            <span class="label-ar" dir="rtl" lang="ar">طلب باقة <?php echo htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8'); ?> — سنراجع طلبك ونتواصل معك.</span>
        </p>
        <div class="mb-3">
            <label class="form-label rateb-bilingual-label">
                <span class="label-en">Choose Plan</span>
                <span class="label-ar" dir="rtl" lang="ar">اختر الباقة</span>
            </label>
            <p class="small mb-2 form-plan-hint"><i class="fas fa-info-circle me-1"></i><?php echo $planHint; ?></p>
            <div class="d-flex gap-2 flex-wrap mb-2">
                <button type="button" class="btn plan-btn-form plan-btn-pro" data-plan="pro" data-amount="" data-years="1"><i class="fas fa-star me-1"></i> Pro</button>
                <button type="button" class="btn plan-btn-form plan-btn-gold" data-plan="gold" data-amount="<?php echo (float) $goldTestPriceYear1; ?>" data-years="1"><i class="fas fa-crown me-1"></i> Gold <span class="promo-old">$<?php echo number_format((float) $goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float) $goldTestPriceYear1, 0); ?></span></button>
                <button type="button" class="btn plan-btn-form plan-btn-platinum" data-plan="platinum" data-amount="<?php echo (float) $platinumTestPriceYear1; ?>" data-years="1"><i class="fas fa-gem me-1"></i> Platinum <span class="promo-old">$<?php echo number_format((float) $platinumListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float) $platinumTestPriceYear1, 0); ?></span></button>
            </div>
            <div id="formYearButtonsWrap" class="mb-2 <?php echo ($plan !== 'pro' && $planAmount) ? '' : 'is-hidden'; ?>">
                <label class="form-label rateb-bilingual-label form-duration-label">
                    <span class="label-en">Duration</span>
                    <span class="label-ar" dir="rtl" lang="ar">المدة</span>
                </label>
                <div class="d-flex gap-2 flex-wrap" id="formYearButtons">
                    <button type="button" class="form-year-btn" data-years="0" data-price-gold="<?php echo (float) $goldTestPriceMonth; ?>" data-price-platinum="<?php echo (float) $platinumTestPriceMonth; ?>">Monthly / شهري<br><span class="form-year-price"><span class="promo-old">$<?php echo number_format((float) $goldListPriceMonth, 2); ?></span> <span class="promo-new">$<?php echo number_format((float) $goldTestPriceMonth, 2); ?></span></span></button>
                    <button type="button" class="form-year-btn" data-years="1" data-price-gold="<?php echo (float) $goldTestPriceYear1; ?>" data-price-platinum="<?php echo (float) $platinumTestPriceYear1; ?>">1 yr / سنة<br><span class="form-year-price"><span class="promo-old">$<?php echo number_format((float) $goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float) $goldTestPriceYear1, 0); ?></span></span></button>
                </div>
            </div>
        </div>
        <div id="successMsg" class="alert alert-success success-msg mb-3 is-hidden" role="alert"><i class="fas fa-check-circle me-2"></i><span id="successText"></span></div>
        <form id="regForm" dir="ltr" data-after-save-url="<?php echo htmlspecialchars($ratebAfterRegisterUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="plan" id="inputPlan" value="<?php echo htmlspecialchars($plan, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="plan_amount" id="inputPlanAmount" value="<?php echo $planAmount !== null ? (float) $planAmount : ''; ?>">
            <input type="hidden" name="years" id="inputYears" value="<?php echo $years !== null ? (int) $years : ''; ?>" data-allow-zero="1">
            <input type="hidden" name="payment_method" value="register">
            <div class="hp hp-field"><input type="text" id="hp" name="website_url" tabindex="-1" autocomplete="off"></div>
            <div class="mb-3">
                <label class="form-label rateb-bilingual-label"><span class="label-en">Agency Name *</span><span class="label-ar" dir="rtl" lang="ar">اسم الوكالة *</span></label>
                <input type="text" class="form-control" name="agency_name" required maxlength="255" placeholder="Your agency or company name / اسم الوكالة">
            </div>
            <div class="mb-3">
                <label class="form-label rateb-bilingual-label"><span class="label-en">Agency ID</span><span class="label-ar" dir="rtl" lang="ar">معرّف الوكالة</span></label>
                <input type="text" class="form-control" name="agency_id" maxlength="64" placeholder="License or registration number / رقم الترخيص">
            </div>
            <div class="mb-3">
                <label class="form-label rateb-bilingual-label"><span class="label-en">Country *</span><span class="label-ar" dir="rtl" lang="ar">الدولة *</span></label>
                <select class="form-control<?php echo $ratebCountryIsLocked ? ' is-locked-country' : ''; ?>" name="<?php echo $ratebCountryIsLocked ? 'country_visible' : 'country'; ?>" id="countrySelect" required <?php echo $ratebCountryIsLocked ? 'disabled' : ''; ?>>
                    <option value="">-- Select Country / اختر الدولة --</option>
                    <?php foreach ($countries as $c): ?>
                    <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($ratebCountryIsLocked && $ratebLockedCountryName === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($ratebCountryIsLocked): ?>
                <input type="hidden" name="country" value="<?php echo htmlspecialchars($ratebLockedCountryName, ENT_QUOTES, 'UTF-8'); ?>">
                <p class="small mt-2 mb-0 form-plan-hint"><i class="fas fa-lock me-1"></i>Country is set by your portal / الدولة محددة من بوابتك</p>
                <?php endif; ?>
            </div>
            <div class="mb-3 is-hidden" id="otherCountryWrap">
                <label class="form-label rateb-bilingual-label"><span class="label-en">Specify country</span><span class="label-ar" dir="rtl" lang="ar">حدد الدولة</span></label>
                <input type="text" class="form-control" name="country_other" id="countryOther" maxlength="255" placeholder="Enter country name / أدخل اسم الدولة">
            </div>
            <div class="mb-3">
                <label class="form-label rateb-bilingual-label"><span class="label-en">Contact Email *</span><span class="label-ar" dir="rtl" lang="ar">البريد الإلكتروني *</span></label>
                <input type="email" class="form-control" name="contact_email" required maxlength="255" placeholder="you@example.com">
            </div>
            <div class="mb-3">
                <label class="form-label rateb-bilingual-label"><span class="label-en">Contact Phone *</span><span class="label-ar" dir="rtl" lang="ar">رقم الهاتف *</span></label>
                <input type="text" class="form-control" name="contact_phone" required maxlength="64" placeholder="+966xxxxxxxxx">
            </div>
            <div class="mb-3">
                <label class="form-label rateb-bilingual-label"><span class="label-en">Desired Site URL (optional)</span><span class="label-ar" dir="rtl" lang="ar">رابط الموقع المطلوب (اختياري)</span></label>
                <input type="url" class="form-control" name="desired_site_url" maxlength="512" placeholder="https://your-agency.rateb.sa">
            </div>
            <div class="mb-4">
                <label class="form-label rateb-bilingual-label"><span class="label-en">Notes</span><span class="label-ar" dir="rtl" lang="ar">ملاحظات</span></label>
                <textarea class="form-control" name="notes" rows="3" maxlength="2000" placeholder="Tell us about your agency / أخبرنا عن وكالتك"></textarea>
            </div>
            <div id="paymentBlockPlaceholder" class="mb-4 <?php echo ($plan !== 'pro' && $planAmount) ? 'is-hidden' : ''; ?>">
                <div class="payment-placeholder-box">
                    <i class="fas fa-receipt me-2 payment-placeholder-icon"></i><?php echo $paymentPlaceholder; ?>
                </div>
            </div>
            <div id="paymentBlockWrap" class="payment-block-wrap mb-4 <?php echo ($plan !== 'pro' && $planAmount) ? '' : 'is-hidden'; ?>">
                <div class="mb-4 payment-summary-box payment-summary-panel">
                    <h4 class="payment-summary-title rateb-bilingual-label">
                        <span class="label-en"><i class="fas fa-receipt me-2"></i><?php echo htmlspecialchars($paymentSummaryTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="label-ar" dir="rtl" lang="ar">ملخص الدفع</span>
                    </h4>
                    <?php
                    $__payableSubtotal = $planAmount ? (float) $planAmount : 0.0;
                    $__listSubtotal = $__payableSubtotal * 2;
                    $__discountAmount = $__listSubtotal - $__payableSubtotal;
                    ?>
                    <div class="payment-summary-row">
                        <span class="payment-summary-muted">List Price / السعر الأصلي</span>
                        <span class="payment-summary-value" id="paymentSummaryListPrice">$<?php echo number_format($__listSubtotal, 2); ?></span>
                    </div>
                    <div class="payment-summary-row">
                        <span class="payment-summary-muted">Discount (50%) / الخصم</span>
                        <span class="payment-summary-value" id="paymentSummaryDiscount">-$<?php echo number_format($__discountAmount, 2); ?></span>
                    </div>
                    <div class="payment-summary-row">
                        <span class="payment-summary-muted" id="paymentSummaryLabel"><?php echo htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8'); ?> Plan</span>
                        <span class="payment-summary-value" id="paymentSummarySubtotal">$<?php echo $planAmount ? number_format((float) $planAmount, 2) : '0.00'; ?></span>
                    </div>
                    <div class="payment-summary-row">
                        <span class="payment-summary-muted">Tax (15%) / الضريبة</span>
                        <span class="payment-summary-value" id="paymentSummaryTax">$<?php echo $planAmount ? number_format($planAmount * 0.15, 2) : '0.00'; ?></span>
                    </div>
                    <div class="payment-summary-total-row">
                        <span>Total / الإجمالي</span>
                        <span id="paymentSummaryTotal"><?php echo htmlspecialchars($ratebDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?> <?php echo $planAmount ? number_format(((float) $planAmount * 1.15 * (float) $ratebDisplayUsdRate), 2) : '0.00'; ?></span>
                    </div>
                </div>
                <p class="small mb-0 payment-summary-footnote"><i class="fas fa-file-invoice me-2 payment-summary-footnote-icon"></i><?php echo htmlspecialchars($paymentSummaryFooter, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <button type="submit" class="btn btn-primary btn-submit" id="btnSubmit">
                <i class="fas fa-paper-plane me-2"></i>
                <span class="label-en">Submit Request</span>
                <span class="label-ar" dir="rtl" lang="ar">إرسال الطلب</span>
            </button>
        </form>
    </div>
</section>
