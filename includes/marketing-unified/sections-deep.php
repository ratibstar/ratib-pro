<?php
/** Deep marketing sections (platform → API) */

/** Ported from pages/home.php (unified marketing at /). Do not edit pages/home.php. */
?>
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

        