<?php
/** Commerce: pricing + register + final CTA */
?>
<section class="pricing-section rateb-pricing-saas" id="programs">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.pricing.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.pricing.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.pricing.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="pricing-row pricing-row--three">
            <div class="price-card price-card-starter">
                <span class="card-badge card-badge--muted"><?php echo htmlspecialchars($ratebHome['home.pricing.starter.badge'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-plan"><?php echo htmlspecialchars($ratebHome['home.pricing.starter.plan'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="card-subtitle"><?php echo htmlspecialchars($ratebHome['home.pricing.starter.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <p class="card-price-saas"><?php echo htmlspecialchars($ratebHome['home.pricing.starter.price_line'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <?php foreach ($ratebPricingStarterLines as $ratebLine) { ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($ratebLine, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php } ?>
                </ul>
                <a href="<?php echo htmlspecialchars($ratebRegisterHref, ENT_QUOTES, 'UTF-8'); ?>" class="btn-register btn-register-starter js-open-register" data-register-plan="pro" data-register-amount="" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratebHome['home.pricing.starter.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
            <div class="price-card gold price-card--featured">
                <span class="card-badge"><?php echo htmlspecialchars($ratebHome['home.pricing.gold.badge'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-plan"><?php echo htmlspecialchars($ratebHome['home.pricing.gold.plan_word'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="card-plan-note">list $<?php echo number_format((float)$goldListPriceYear1, 0); ?></span></div>
                <div class="card-subtitle"><?php echo htmlspecialchars($ratebHome['home.pricing.gold.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="plan-year-wrap">
                    <div class="plan-year-buttons">
                        <button type="button" class="year-btn gold-year-btn year-btn-card year-btn-neutral" data-years="0" data-price="<?php echo (float)$goldTestPriceMonth; ?>">Monthly<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$goldListPriceMonth, 2); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceMonth, 2); ?></span></span></button>
                        <button type="button" class="year-btn gold-year-btn year-btn-card year-btn-gold-active active" data-years="1" data-price="<?php echo (float)$goldTestPriceYear1; ?>">1 Year<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
                <p class="card-price-old" id="goldOldPrice">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></p>
                <p class="card-price" id="goldPrice">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?> <span id="goldPriceLabel">for 1 year</span></p>
                <span class="card-discount"><?php echo htmlspecialchars($ratebHome['home.pricing.gold.discount_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <?php foreach ($ratebPricingGoldLines as $ratebLine) { ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($ratebLine, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php } ?>
                </ul>
                <a href="<?php echo htmlspecialchars($ratebRegisterHref, ENT_QUOTES, 'UTF-8'); ?>" id="goldRegisterBtn" class="btn-register js-open-register" data-register-plan="gold" data-register-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratebHome['home.pricing.gold.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
            <div class="price-card platinum">
                <span class="card-badge"><?php echo htmlspecialchars($ratebHome['home.pricing.platinum.badge'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-plan"><?php echo htmlspecialchars($ratebHome['home.pricing.platinum.plan_word'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="card-plan-note">list $<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span></div>
                <div class="card-subtitle"><?php echo htmlspecialchars($ratebHome['home.pricing.platinum.subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="plan-year-wrap">
                    <div class="plan-year-buttons">
                        <button type="button" class="year-btn platinum-year-btn year-btn-card year-btn-neutral" data-years="0" data-price="<?php echo (float)$platinumTestPriceMonth; ?>">Monthly<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$platinumListPriceMonth, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceMonth, 0); ?></span></span></button>
                        <button type="button" class="year-btn platinum-year-btn year-btn-card year-btn-platinum-active active" data-years="1" data-price="<?php echo (float)$platinumTestPriceYear1; ?>">1 Year<br><span class="year-price-small"><span class="promo-old">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
                <p class="card-price-old" id="platinumOldPrice">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></p>
                <p class="card-price" id="platinumPrice">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?> <span id="platinumPriceLabel">for 1 year</span></p>
                <span class="card-discount"><?php echo htmlspecialchars($ratebHome['home.pricing.platinum.discount_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <div class="card-divider"></div>
                <ul class="card-features">
                    <?php foreach ($ratebPricingPlatinumLines as $ratebLine) { ?>
                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($ratebLine, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php } ?>
                </ul>
                <a href="<?php echo htmlspecialchars($ratebRegisterHref, ENT_QUOTES, 'UTF-8'); ?>" id="platinumRegisterBtn" class="btn-register js-open-register" data-register-plan="platinum" data-register-amount="<?php echo (float)($plans['platinum']['amount'] ?? $platinumTestPriceYear1); ?>" data-register-years="1"><i class="fas fa-arrow-right me-2"></i> <?php echo htmlspecialchars($ratebHome['home.pricing.platinum.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </div>
            </div>
        </section>

        <section class="register-section<?php echo $openRegister ? '' : ' register-section-hidden'; ?> rateb-register-wrap" id="register">
        <div class="rateb-info">
            <h2><i class="fas fa-info-circle me-2 register-info-icon"></i><?php echo htmlspecialchars($ratebHome['home.register.info.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><?php echo htmlspecialchars($ratebHome['home.register.info.intro'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <ul class="checklist">
                <?php for ($ci = 1; $ci <= 7; $ci++) { ?>
                <li><i class="fas fa-check-circle"></i><span><?php echo strip_tags($ratebHome['home.register.check.' . $ci] ?? '', '<strong>'); ?></span></li>
                <?php } ?>
            </ul>
        </div>
        <div class="form-card">
            <h1><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($ratebHome['home.register.form.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="subtitle">Request <?php echo htmlspecialchars($planLabel); ?> plan access<?php if ($planAmount): ?> — $<?php echo number_format($planAmount); ?><?php if ($years !== null): ?><?php if ((int)$years === 0): ?> per month<?php elseif ((int)$years > 0): ?> for <?php echo (int)$years; ?> year<?php echo (int)$years > 1 ? 's' : ''; ?><?php else: ?> setup<?php endif; ?><?php else: ?> setup<?php endif; ?><?php endif; ?>. We will review and contact you.</p>
            <div class="mb-3">
                <label class="form-label">Choose Plan</label>
                <p class="small mb-2 form-plan-hint"><i class="fas fa-info-circle me-1"></i><?php echo strip_tags($ratebHome['home.register.form.plan_hint'] ?? '', '<strong>'); ?></p>
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <button type="button" class="btn plan-btn-form plan-btn-pro" data-plan="pro" data-amount="" data-years="1"><i class="fas fa-star me-1"></i> Pro</button>
                    <button type="button" class="btn plan-btn-form plan-btn-gold" data-plan="gold" data-amount="<?php echo (float)$goldTestPriceYear1; ?>" data-years="1"><i class="fas fa-crown me-1"></i> Gold <span class="promo-old">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?></span></button>
                    <button type="button" class="btn plan-btn-form plan-btn-platinum" data-plan="platinum" data-amount="<?php echo (float)$platinumTestPriceYear1; ?>" data-years="1"><i class="fas fa-gem me-1"></i> Platinum <span class="promo-old">$<?php echo number_format((float)$platinumListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$platinumTestPriceYear1, 0); ?></span></button>
                </div>
                <div id="formYearButtonsWrap" class="mb-2 <?php echo ($plan !== 'pro' && $planAmount) ? '' : 'is-hidden'; ?>">
                    <label class="form-label form-duration-label">Duration</label>
                    <div class="d-flex gap-2 flex-wrap" id="formYearButtons">
                        <button type="button" class="form-year-btn" data-years="0" data-price-gold="<?php echo (float)$goldTestPriceMonth; ?>" data-price-platinum="<?php echo (float)$platinumTestPriceMonth; ?>">Monthly<br><span class="form-year-price"><span class="promo-old">$<?php echo number_format((float)$goldListPriceMonth, 2); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceMonth, 2); ?></span></span></button>
                        <button type="button" class="form-year-btn" data-years="1" data-price-gold="<?php echo (float)$goldTestPriceYear1; ?>" data-price-platinum="<?php echo (float)$platinumTestPriceYear1; ?>">1 yr<br><span class="form-year-price"><span class="promo-old">$<?php echo number_format((float)$goldListPriceYear1, 0); ?></span> <span class="promo-new">$<?php echo number_format((float)$goldTestPriceYear1, 0); ?></span></span></button>
                    </div>
                </div>
            </div>
            <div id="successMsg" class="alert alert-success success-msg mb-3 is-hidden" role="alert"><i class="fas fa-check-circle me-2"></i><span id="successText"></span></div>
            <form id="regForm" dir="ltr">
                <input type="hidden" name="plan" id="inputPlan" value="<?php echo htmlspecialchars($plan); ?>">
                <input type="hidden" name="plan_amount" id="inputPlanAmount" value="<?php echo $planAmount !== null ? (float)$planAmount : ''; ?>">
                <input type="hidden" name="years" id="inputYears" value="<?php echo $years !== null ? (int)$years : ''; ?>" data-allow-zero="1">
                <input type="hidden" name="payment_method" value="register">
                <div class="hp hp-field"><input type="text" id="hp" name="website_url" tabindex="-1" autocomplete="off"></div>
                <div class="mb-3"><label class="form-label">Agency Name *</label><input type="text" class="form-control" name="agency_name" required maxlength="255" placeholder="Your agency or company name"></div>
                <div class="mb-3"><label class="form-label">Agency ID</label><input type="text" class="form-control" name="agency_id" maxlength="64" placeholder="e.g. registration or license number"></div>
                <div class="mb-3">
                    <label class="form-label">Country *</label>
                    <select class="form-control<?php echo $ratebCountryIsLocked ? ' is-locked-country' : ''; ?>" name="<?php echo $ratebCountryIsLocked ? 'country_visible' : 'country'; ?>" id="countrySelect" required <?php echo $ratebCountryIsLocked ? 'disabled' : ''; ?>>
                        <option value="">-- Select Country --</option>
                        <?php foreach ($countries as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($ratebCountryIsLocked && $ratebLockedCountryName === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($ratebCountryIsLocked): ?>
                    <input type="hidden" name="country" value="<?php echo htmlspecialchars($ratebLockedCountryName, ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="small mt-2 mb-0 form-plan-hint"><i class="fas fa-lock me-1"></i>Country is set by your portal.</p>
                    <?php endif; ?>
                </div>
                <div class="mb-3 is-hidden" id="otherCountryWrap"><label class="form-label">Specify country</label><input type="text" class="form-control" name="country_other" id="countryOther" maxlength="255" placeholder="Enter country name"></div>
                <div class="mb-3"><label class="form-label">Contact Email *</label><input type="email" class="form-control" name="contact_email" required maxlength="255" placeholder="you@example.com"></div>
                <div class="mb-3"><label class="form-label">Contact Phone *</label><input type="text" class="form-control" name="contact_phone" required maxlength="64" placeholder="+1234567890"></div>
                <div class="mb-3"><label class="form-label">Desired Site URL (optional)</label><input type="url" class="form-control" name="desired_site_url" maxlength="512" placeholder="https://your-agency.rateb.sa"></div>
                <div class="mb-4"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3" maxlength="2000" placeholder="Tell us about your agency or requirements..."></textarea></div>
                
                <!-- When Pro selected: hint to choose Gold/Platinum for pricing summary -->
                <div id="paymentBlockPlaceholder" class="mb-4 <?php echo ($plan !== 'pro' && $planAmount) ? 'is-hidden' : ''; ?>">
                    <div class="payment-placeholder-box">
                        <i class="fas fa-receipt me-2 payment-placeholder-icon"></i><?php echo strip_tags($ratebHome['home.register.payment_placeholder'] ?? '', '<strong>'); ?>
                    </div>
                </div>
                <!-- Payment block: always in DOM; shown only for Gold/Platinum (JS toggles visibility) -->
                <div id="paymentBlockWrap" class="payment-block-wrap mb-4 <?php echo ($plan !== 'pro' && $planAmount) ? '' : 'is-hidden'; ?>">
                    <!-- Payment Summary -->
                    <div class="mb-4 payment-summary-box payment-summary-panel">
                        <h4 class="payment-summary-title"><i class="fas fa-receipt me-2"></i><?php echo htmlspecialchars($ratebHome['home.register.payment_summary.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                        <?php
                        $__payableSubtotal = $planAmount ? (float)$planAmount : 0.0;
                        $__listSubtotal = $__payableSubtotal * 2;
                        $__discountAmount = $__listSubtotal - $__payableSubtotal;
                        ?>
                        <div class="payment-summary-row">
                            <span class="payment-summary-muted">List Price</span>
                            <span class="payment-summary-value" id="paymentSummaryListPrice">$<?php echo number_format($__listSubtotal, 2); ?></span>
                        </div>
                        <div class="payment-summary-row">
                            <span class="payment-summary-muted">Discount (50%)</span>
                            <span class="payment-summary-value" id="paymentSummaryDiscount">-$<?php echo number_format($__discountAmount, 2); ?></span>
                        </div>
                        <div class="payment-summary-row">
                            <span class="payment-summary-muted" id="paymentSummaryLabel"><?php echo htmlspecialchars($planLabel); ?> Plan (<?php echo ($years !== null && (int)$years === 0) ? 'monthly' : ((int)($years !== null ? $years : 1)) . ' year' . (((int)($years !== null ? $years : 1)) > 1 ? 's' : ''); ?>)</span>
                            <span class="payment-summary-value" id="paymentSummarySubtotal">$<?php echo $planAmount ? number_format((float)$planAmount, 2) : '0.00'; ?></span>
                        </div>
                        <div class="payment-summary-row">
                            <span class="payment-summary-muted">Tax (15%)</span>
                            <span class="payment-summary-value" id="paymentSummaryTax">$<?php echo $planAmount ? number_format($planAmount * 0.15, 2) : '0.00'; ?></span>
                        </div>
                        <div class="payment-summary-total-row">
                            <span>Total</span>
                            <span id="paymentSummaryTotal"><?php echo htmlspecialchars($ratebDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?> <?php echo $planAmount ? number_format(((float)$planAmount * 1.15 * (float)$ratebDisplayUsdRate), 2) : '0.00'; ?></span>
                        </div>
                        <?php
                        $__showNgeniusNote = ($plan !== 'pro' && $planAmount);
                        if ($__showNgeniusNote) {
                            $__usdTotal = (float) $planAmount * 1.15;
                            $__gatewayCurrency = strtoupper(trim((string) $ratebCheckoutCurrency));
                            if ($__gatewayCurrency === '') {
                                $__gatewayCurrency = 'SAR';
                            }
                            $__gatewayRate = ($__gatewayCurrency === 'SAR') ? (float) $ratebUsdToSar : 1.0;
                            if (!is_finite($__gatewayRate) || $__gatewayRate <= 0) {
                                $__gatewayRate = ($__gatewayCurrency === 'SAR') ? 3.75 : 1.0;
                            }
                            $__gatewayTotal = round($__usdTotal * $__gatewayRate, 2);
                            $__displayTotal = round($__usdTotal * $ratebDisplayUsdRate, 2);
                            ?>
                        <p class="small mb-0 mt-2 rateb-ngenius-currency-note">Card checkout is charged in <strong><?php echo htmlspecialchars($ratebDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?></strong>: <strong class="rateb-ngenius-sar-total"><?php echo htmlspecialchars($ratebDisplayCheckoutCurrency, ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format($__displayTotal, 2); ?></strong> <span class="rateb-ngenius-rate-note">(USD × <?php echo htmlspecialchars(number_format($ratebDisplayUsdRate, 2), ENT_QUOTES, 'UTF-8'); ?>)</span>.</p>
                        <?php if ($ratebDisplayCheckoutCurrency !== $__gatewayCurrency): ?>
                        <p class="small text-muted mb-0 mt-1 rateb-ngenius-currency-note">You will complete payment in <?php echo htmlspecialchars($__gatewayCurrency, ENT_QUOTES, 'UTF-8'); ?>.</p>
                        <?php endif; ?>
                        <?php } ?>
                    </div>
                    <p class="small mb-0 payment-summary-footnote"><i class="fas fa-file-invoice me-2 payment-summary-footnote-icon"></i><?php echo htmlspecialchars($ratebHome['home.register.payment_summary.footer'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                
                <button type="submit" class="btn btn-primary btn-submit" id="btnSubmit"><i class="fas fa-paper-plane me-2"></i><?php echo htmlspecialchars($ratebHome['home.register.submit'] ?? '', ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
    </section>

        <section class="rateb-final-cta rateb-final-cta--enterprise" id="contact" aria-labelledby="rateb-final-cta-title">
            <div class="rateb-final-cta__bg" aria-hidden="true"></div>
            <div class="rateb-container rateb-final-cta__inner">
                <h2 id="rateb-final-cta-title" class="rateb-final-cta__title"><?php echo htmlspecialchars($ratebHome['home.final_cta.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="rateb-final-cta__sub"><?php echo htmlspecialchars($ratebHome['home.final_cta.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="rateb-final-cta__actions">
                    <a href="<?php echo htmlspecialchars(rateb_enterprise_mailto('RATEB — Request Enterprise Demo'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--primary rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.final_cta.btn_primary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="<?php echo htmlspecialchars($ratebWalkthroughHref, ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--outline rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.final_cta.btn_secondary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="<?php echo htmlspecialchars(rateb_enterprise_mailto('RATEB — Contact Solutions Team'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--outline rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.final_cta.btn_tertiary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                    <a href="<?php echo htmlspecialchars(rateb_enterprise_mailto('RATEB — Request Security Brief'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--ghost rateb-btn--lg"><?php echo htmlspecialchars($ratebHome['home.final_cta.btn_quaternary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            </div>
        </section>
    
