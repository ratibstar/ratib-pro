<?php
/** Ported from pages/home.php (unified marketing at /). Do not edit pages/home.php. */
        <section class="rateb-section rateb-trust" id="platform">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.platform.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.platform.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-trust__grid">
                    <?php
                    $ratebTrustIcons = ['fa-user-shield', 'fa-clock-rotate-left', 'fa-lock', 'fa-stopwatch', 'fa-clipboard-check', 'fa-server'];
                    for ($ti = 1; $ti <= 6; $ti++) {
                        $ic = $ratebTrustIcons[$ti - 1] ?? 'fa-circle';
                        ?>
                    <article class="rateb-trust-card"><div class="rateb-trust-card__icon"><i class="fas <?php echo htmlspecialchars($ic, ENT_QUOTES, 'UTF-8'); ?>"></i></div><h3><?php echo htmlspecialchars($ratebHome['home.trust.' . $ti . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><p><?php echo htmlspecialchars($ratebHome['home.trust.' . $ti . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <?php if (!function_exists('rateb_public_marketing_should_render_deep') || true) { ?>
        <?php rateb_enterprise_trust_render_home($ratebHome, $baseUrl); ?>

        <?php if ($ratebOpProofAvailable) {
            rateb_operational_proof_render($baseUrl, [
                'eyebrow' => (string) ($ratebHome['home.op_proof.eyebrow'] ?? 'Operational proof'),
                'title' => (string) ($ratebHome['home.op_proof.title'] ?? ''),
                'sub' => (string) ($ratebHome['home.op_proof.sub'] ?? ''),
            ]);
        } ?>

        <section class="rateb-section rateb-domains-embed" id="domains" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow">Domains</p>
                    <h2 class="rateb-section__title">Find a domain</h2>
                    <p class="rateb-section__sub">Search availability and browse catalog offers when providers are active.</p>
                </header>
                <div class="rateb-home-domains-embed">
                    <iframe
                        class="rateb-home-domains-embed__frame"
                        title="Domain availability search and marketplace catalog"
                        src="<?php echo htmlspecialchars($ratebDomainsIframeSrc, ENT_QUOTES, 'UTF-8'); ?>"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                    ></iframe>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-how" id="how-it-works" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.how.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.how.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.how.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <ol class="rateb-how__steps" aria-label="Deployment sequence">
                    <?php for ($hi = 1; $hi <= 7; $hi++) {
                        $hn = str_pad((string) $hi, 2, '0', STR_PAD_LEFT); ?>
                    <li class="rateb-how__step"><span class="rateb-how__n" aria-hidden="true"><?php echo $hn; ?></span><strong class="rateb-how__title"><?php echo htmlspecialchars($ratebHome['home.how.step.' . $hi . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong><span class="rateb-how__desc"><?php echo htmlspecialchars($ratebHome['home.how.step.' . $hi . '.desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></li>
                    <?php } ?>
                </ol>
            </div>
        </section>

        <section class="rateb-section" id="features">
            <div class="rateb-container">
                <header class="rateb-section__head rateb-section__head--left">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.features.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.features.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub rateb-section__sub--inline"><?php echo htmlspecialchars($ratebHome['home.features.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-feature-grid">
                    <?php
                    $ratebFeatureIcons = ['fa-gears', 'fa-id-badge', 'fa-shuffle', 'fa-location-dot', 'fa-globe', 'fa-file-signature', 'fa-coins', 'fa-receipt', 'fa-route', 'fa-bell', 'fa-chart-pie', 'fa-plug'];
                    for ($fi = 1; $fi <= 12; $fi++) {
                        $fic = $ratebFeatureIcons[$fi - 1] ?? 'fa-circle';
                        ?>
                    <article class="rateb-feature-card rateb-feature-card--tone<?php echo (int) $fi; ?>"><div class="rateb-feature-card__icon"><i class="fas <?php echo htmlspecialchars($fic, ENT_QUOTES, 'UTF-8'); ?>"></i></div><h3><?php echo htmlspecialchars($ratebHome['home.features.' . $fi . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><p><?php echo htmlspecialchars($ratebHome['home.features.' . $fi . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-pipeline-section" id="tracking" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.pipeline.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.pipeline.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.pipeline.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-pipeline" role="list">
                    <div class="rateb-pipeline__track" aria-hidden="true"></div>
                    <?php
                    $ratebPipeState = ['rateb-pipeline__item--complete', 'rateb-pipeline__item--complete', 'rateb-pipeline__item--active', '', '', '', '', ''];
                    for ($pi = 1; $pi <= 8; $pi++) {
                        $pcls = trim('rateb-pipeline__item ' . ($ratebPipeState[$pi - 1] ?? ''));
                        ?>
                    <div class="<?php echo htmlspecialchars($pcls, ENT_QUOTES, 'UTF-8'); ?>" role="listitem"><span class="rateb-pipeline__dot"></span><span class="rateb-pipeline__label"><?php echo htmlspecialchars($ratebHome['home.pipeline.step.' . $pi . '.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-pipeline__meta"><?php echo htmlspecialchars($ratebHome['home.pipeline.step.' . $pi . '.meta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-ai-section" id="solutions" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head rateb-section__head--left">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.solutions.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.solutions.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.solutions.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-ai-grid rateb-use-grid">
                    <article class="rateb-ai-card rateb-ai-card--wide rateb-use-card rateb-use-card--wide">
                        <h3><?php echo htmlspecialchars($ratebHome['home.solutions.1.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($ratebHome['home.solutions.1.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="rateb-ai-visual rateb-use-visual">
                            <div class="rateb-ai-row"><span class="rateb-pill"><?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.1b'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="rateb-ai-row"><span class="rateb-pill rateb-pill--accent"><?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.2'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.2b'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="rateb-ai-row"><span class="rateb-pill"><?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.3'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratebHome['home.solutions.1.demo_row.3b'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </article>
                    <?php for ($si = 2; $si <= 6; $si++) { ?>
                    <article class="rateb-ai-card rateb-use-card">
                        <h3><?php echo htmlspecialchars($ratebHome['home.solutions.' . $si . '.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($ratebHome['home.solutions.' . $si . '.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-eco" id="agencies" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.agencies.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.agencies.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.agencies.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-eco__viz" aria-hidden="true">
                    <div class="rateb-eco__core">
                        <span class="rateb-eco__core-label"><?php echo htmlspecialchars($ratebHome['home.agencies.core.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="rateb-eco__core-sub"><?php echo htmlspecialchars($ratebHome['home.agencies.core.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="rateb-eco__spokes">
                        <?php for ($ei = 1; $ei <= 3; $ei++) { ?>
                        <div class="rateb-eco__spoke"><span><?php echo htmlspecialchars($ratebHome['home.agencies.spoke.' . $ei . '.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><small><?php echo htmlspecialchars($ratebHome['home.agencies.spoke.' . $ei . '.small'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></div>
                        <?php } ?>
                        <div class="rateb-eco__spoke rateb-eco__spoke--accent"><span><?php echo htmlspecialchars($ratebHome['home.agencies.spoke.4.label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><small><?php echo htmlspecialchars($ratebHome['home.agencies.spoke.4.small'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-analytics" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head rateb-section__head--left">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.analytics.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.analytics.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.analytics.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (trim((string) ($ratebHome['home.analytics.sample_tag'] ?? '')) !== '') { ?>
                    <p class="rateb-sample-data-tag"><?php echo htmlspecialchars($ratebHome['home.analytics.sample_tag'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php } ?>
                </header>
                <div class="rateb-analytics__grid">
                    <article class="rateb-analytics-card"><p class="rateb-analytics-card__stamp rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.analytics.1.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratebHome['home.analytics.1.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="rateb-metric"><span class="rateb-metric__val"><?php echo htmlspecialchars($ratebHome['home.analytics.1.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-metric__chart rateb-metric__chart--line" aria-hidden="true"></span></div><span class="rateb-analytics__illus"><?php echo htmlspecialchars($ratebHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratebHome['home.analytics.1.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="rateb-analytics-card"><p class="rateb-analytics-card__stamp rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.analytics.2.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratebHome['home.analytics.2.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="rateb-metric"><span class="rateb-metric__val"><?php echo htmlspecialchars($ratebHome['home.analytics.2.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-metric__chart rateb-metric__chart--bars" aria-hidden="true"></span></div><span class="rateb-analytics__illus"><?php echo htmlspecialchars($ratebHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratebHome['home.analytics.2.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="rateb-analytics-card"><p class="rateb-analytics-card__stamp rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.analytics.3.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratebHome['home.analytics.3.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="rateb-metric"><span class="rateb-metric__val"><?php echo htmlspecialchars($ratebHome['home.analytics.3.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-metric__note"><?php echo htmlspecialchars($ratebHome['home.analytics.3.note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div><span class="rateb-analytics__illus"><?php echo htmlspecialchars($ratebHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratebHome['home.analytics.3.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                    <article class="rateb-analytics-card"><p class="rateb-analytics-card__stamp rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.analytics.4.stamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><h3><?php echo htmlspecialchars($ratebHome['home.analytics.4.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3><div class="rateb-metric"><span class="rateb-metric__val"><?php echo htmlspecialchars($ratebHome['home.analytics.4.metric'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-metric__note"><?php echo htmlspecialchars($ratebHome['home.analytics.4.note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div><span class="rateb-analytics__illus"><?php echo htmlspecialchars($ratebHome['home.analytics.illus'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><p><?php echo htmlspecialchars($ratebHome['home.analytics.4.body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p></article>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-ops-visibility" id="operational" data-rateb-marketing-depth="deep">
            <div class="rateb-container">
                <header class="rateb-section__head">
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.ops.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-section__title"><?php echo htmlspecialchars($ratebHome['home.ops.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-section__sub"><?php echo htmlspecialchars($ratebHome['home.ops.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="rateb-ops__disclaimer rateb-mono-ops"><?php echo htmlspecialchars($ratebHome['home.ops.disclaimer'] ?? 'Illustrative interface · sample operational data only', ENT_QUOTES, 'UTF-8'); ?></p>
                </header>
                <div class="rateb-ops__layout">
                    <div class="rateb-ops__panel rateb-ops__panel--preview">
                        <div class="rateb-ops__panel-bar">
                            <span class="rateb-mono-tag">sample.ops.panel</span>
                            <span class="rateb-pill rateb-pill--subtle">Sample operational data</span>
                        </div>
                        <div class="rateb-ops__preview-grid">
                            <div class="rateb-ops__mini">
                                <span class="rateb-ops__mini-label">Workflow health</span>
                                <span class="rateb-ops__mini-val rateb-ops__mini-val--ok">Healthy</span>
                                <span class="rateb-ops__mini-sub">0 breached SLA · <span class="rateb-live-sync-age">2m</span> since last reconcile</span>
                            </div>
                            <div class="rateb-ops__mini">
                                <span class="rateb-ops__mini-label">Throughput (24h)</span>
                                <span class="rateb-ops__mini-val">412</span>
                                <span class="rateb-ops__mini-sub">stage transitions committed</span>
                            </div>
                            <div class="rateb-ops__mini">
                                <span class="rateb-ops__mini-label">Automation</span>
                                <span class="rateb-ops__mini-val">3</span>
                                <span class="rateb-ops__mini-sub">workflows auto-resolved <span class="rateb-mono-tag">rolling 1h</span></span>
                            </div>
                            <div class="rateb-ops__mini">
                                <span class="rateb-ops__mini-label">Tracking stability</span>
                                <span class="rateb-ops__mini-val rateb-ops__mini-val--ok">Stable</span>
                                <span class="rateb-ops__mini-sub">Tracking and checkpoint signals within variance</span>
                            </div>
                            <div class="rateb-ops__mini rateb-ops__mini--wide">
                                <span class="rateb-ops__mini-label">Document verification</span>
                                <span class="rateb-ops__mini-val">Queue depth 12</span>
                                <span class="rateb-ops__mini-sub">KYC · medical · embassy bundles · median review 14m</span>
                            </div>
                            <div class="rateb-ops__sparkline" aria-hidden="true">
                                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                            </div>
                        </div>
                    </div>
                    <div class="rateb-ops__events">
                        <div class="rateb-ops__events-head"><span class="rateb-mono-tag">event_log · tail</span><span class="rateb-pill rateb-pill--subtle">sample stream</span></div>
                        <ul class="rateb-ops__event-list">
                            <li><time class="rateb-ops__time rateb-live-clock" datetime="">--:--:--</time><span class="rateb-ops__evt">PIPELINE_MEDICAL_CLEAR · worker · shard A</span></li>
                            <li><time class="rateb-ops__time">—</time><span class="rateb-ops__evt">INV_EMIT · correlation id · FIN connector OK</span></li>
                            <li><time class="rateb-ops__time">—</time><span class="rateb-ops__evt">GEO_FENCE_MATCH · RUH corridor</span></li>
                            <li><time class="rateb-ops__time">—</time><span class="rateb-ops__evt">SLA_WATCH · no breach · policy CL-2024</span></li>
                        </ul>
                    </div>
                </div>
                <div class="rateb-trust-band">
                    <?php for ($oi = 1; $oi <= 6; $oi++) { ?>
                    <div class="rateb-trust-band__item"><span class="rateb-trust-band__k"><?php echo htmlspecialchars($ratebHome['home.ops.band.' . $oi . '.k'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="rateb-trust-band__v"><?php echo htmlspecialchars($ratebHome['home.ops.band.' . $oi . '.v'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="rateb-section rateb-api-strip" id="api" data-rateb-marketing-depth="deep">
            <div class="rateb-container rateb-api-strip__inner">
                <div>
                    <p class="rateb-eyebrow"><?php echo htmlspecialchars($ratebHome['home.api.eyebrow'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <h2 class="rateb-api-strip__title"><?php echo htmlspecialchars($ratebHome['home.api.title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="rateb-api-strip__sub"><?php echo htmlspecialchars($ratebHome['home.api.sub'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <a href="<?php echo htmlspecialchars(rateb_enterprise_mailto('RATEB — Contact Solutions Team'), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-btn rateb-btn--outline"><?php echo htmlspecialchars($ratebHome['home.api.cta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </section>
        <?php } ?>

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
    
