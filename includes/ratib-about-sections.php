<?php
/**
 * Renders enterprise company profile sections for pages/about.php.
 *
 * @var array<string,mixed> $about
 * @var string $baseUrl
 */
declare(strict_types=1);

if (!function_exists('ratib_about_h')) {
    function ratib_about_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ratib_about_render_company_dossier')) {
    function ratib_about_render_company_dossier(array $company, string $baseUrl): void
    {
        $wa = (string) ($company['whatsapp'] ?? 'https://wa.me/966599863868');
        ?>
        <section class="ratib-company-dossier" id="company-profile" aria-labelledby="company-profile-title">
            <div class="ratib-about-container">
                <header class="ratib-company-dossier__head" data-ratib-reveal>
                    <p class="ratib-about-page-label">Full company profile</p>
                    <h1 id="company-profile-title" class="ratib-company-dossier__title"><?php echo ratib_about_h((string) ($company['trade_name'] ?? 'Ratib Company')); ?></h1>
                    <p class="ratib-company-dossier__legal"><?php echo ratib_about_h((string) ($company['legal_name'] ?? '')); ?></p>
                    <p class="ratib-company-dossier__tagline"><?php echo ratib_about_h((string) ($company['tagline'] ?? '')); ?></p>
                </header>

                <div class="ratib-company-dossier__grid">
                    <aside class="ratib-company-dossier__card ratib-company-dossier__card--identity" data-ratib-reveal>
                        <div class="ratib-company-dossier__logo" aria-hidden="true">R</div>
                        <h2 class="ratib-company-dossier__card-title">Company identity</h2>
                        <dl class="ratib-company-dossier__dl">
                            <div><dt>Founded</dt><dd><?php echo ratib_about_h((string) ($company['founded'] ?? '')); ?></dd></div>
                            <div><dt>Headquarters</dt><dd><?php echo ratib_about_h((string) ($company['hq'] ?? '')); ?></dd></div>
                            <div><dt>Industry</dt><dd><?php echo ratib_about_h((string) ($company['industry'] ?? '')); ?></dd></div>
                            <div><dt><?php echo ratib_about_h((string) ($company['cr_label'] ?? 'CR')); ?></dt><dd><?php echo ratib_about_h((string) ($company['cr_value'] ?? '')); ?></dd></div>
                            <div><dt><?php echo ratib_about_h((string) ($company['vat_label'] ?? 'VAT')); ?></dt><dd><?php echo ratib_about_h((string) ($company['vat_value'] ?? '')); ?></dd></div>
                            <div><dt>Team size</dt><dd><?php echo ratib_about_h((string) ($company['employees_band'] ?? '')); ?></dd></div>
                        </dl>
                    </aside>

                    <div class="ratib-company-dossier__card ratib-company-dossier__card--contact" data-ratib-reveal data-ratib-delay="60">
                        <h2 class="ratib-company-dossier__card-title">Contact &amp; web</h2>
                        <dl class="ratib-company-dossier__dl">
                            <div><dt>Phone</dt><dd><a href="tel:+966599863868"><?php echo ratib_about_h((string) ($company['phone'] ?? '')); ?></a></dd></div>
                            <div><dt>Email</dt><dd><a href="mailto:<?php echo ratib_about_h((string) ($company['email'] ?? '')); ?>"><?php echo ratib_about_h((string) ($company['email'] ?? '')); ?></a></dd></div>
                            <div><dt>Website</dt><dd><a href="<?php echo ratib_about_h((string) ($company['website'] ?? $baseUrl)); ?>"><?php echo ratib_about_h((string) ($company['website'] ?? $baseUrl)); ?></a></dd></div>
                            <div><dt>Address</dt><dd><?php echo ratib_about_h((string) ($company['address'] ?? '')); ?></dd></div>
                            <div><dt>WhatsApp</dt><dd><a href="<?php echo ratib_about_h($wa); ?>" target="_blank" rel="noopener noreferrer">Live business line</a></dd></div>
                        </dl>
                        <div class="ratib-company-dossier__actions">
                            <a href="<?php echo ratib_about_h($wa); ?>" target="_blank" rel="noopener noreferrer" class="ratib-about-btn ratib-about-btn--primary">Contact company</a>
                            <a href="<?php echo ratib_about_h(ratib_public_marketing_home_url($baseUrl)); ?>" class="ratib-about-btn ratib-about-btn--outline">Marketing site</a>
                        </div>
                    </div>

                    <article class="ratib-company-dossier__card ratib-company-dossier__card--wide" data-ratib-reveal data-ratib-delay="90">
                        <h2 class="ratib-company-dossier__card-title">About the company</h2>
                        <p class="ratib-company-dossier__text"><?php echo ratib_about_h((string) ($company['summary'] ?? '')); ?></p>
                        <h3 class="ratib-company-dossier__sub">Mission</h3>
                        <p class="ratib-company-dossier__text"><?php echo ratib_about_h((string) ($company['mission'] ?? '')); ?></p>
                        <h3 class="ratib-company-dossier__sub">Vision</h3>
                        <p class="ratib-company-dossier__text"><?php echo ratib_about_h((string) ($company['vision'] ?? '')); ?></p>
                        <h3 class="ratib-company-dossier__sub">Markets &amp; corridors</h3>
                        <p class="ratib-company-dossier__text"><?php echo ratib_about_h((string) ($company['markets'] ?? '')); ?></p>
                    </article>

                    <article class="ratib-company-dossier__card ratib-company-dossier__card--wide" data-ratib-reveal data-ratib-delay="120">
                        <h2 class="ratib-company-dossier__card-title">Services &amp; capabilities</h2>
                        <ul class="ratib-company-dossier__services">
                            <?php foreach ($company['services'] ?? [] as $svc) { ?>
                            <li><?php echo ratib_about_h((string) $svc); ?></li>
                            <?php } ?>
                        </ul>
                    </article>

                    <div class="ratib-company-dossier__stats" data-ratib-reveal data-ratib-delay="150">
                        <?php foreach ($company['highlights'] ?? [] as $h) { ?>
                        <div class="ratib-company-dossier__stat">
                            <span class="ratib-company-dossier__stat-label"><?php echo ratib_about_h((string) ($h['label'] ?? '')); ?></span>
                            <span class="ratib-company-dossier__stat-value"><?php echo ratib_about_h((string) ($h['value'] ?? '')); ?></span>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_about_render_sections')) {
    function ratib_about_render_sections(array $about, string $baseUrl): void
    {
        $shots = $about['screenshots'] ?? [];
        $homeRegister = $baseUrl . '/pages/home.php?open=register&plan=gold&years=1';
        $contactWa = 'https://wa.me/966599863868';
        $company = $about['company'] ?? [];
        if ($company !== []) {
            ratib_about_render_company_dossier($company, $baseUrl);
        }
        ?>
        <section class="ratib-about-hero" id="platform-overview" aria-labelledby="about-hero-title">
            <div class="ratib-about-container ratib-about-hero__grid">
                <div class="ratib-about-hero__copy" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Platform overview</p>
                    <h2 id="about-hero-title" class="ratib-about-hero__title">RATIB <span class="ratib-about-gradient">control plane</span></h2>
                    <p class="ratib-about-hero__lead">How sending-country agencies and host-market programs run recruitment, telemetry, compliance, and finance on one multi-tenant stack.</p>
                    <div class="ratib-about-hero__actions">
                        <a href="<?php echo ratib_about_h($contactWa); ?>" target="_blank" rel="noopener noreferrer" class="ratib-about-btn ratib-about-btn--primary ratib-about-btn--lg">Request Platform Demo</a>
                        <a href="<?php echo ratib_about_h($homeRegister); ?>" class="ratib-about-btn ratib-about-btn--outline ratib-about-btn--lg">Launch Agency Workspace</a>
                    </div>
                    <div class="ratib-about-status-strip" aria-label="System status">
                        <?php foreach ($about['status_chips'] ?? [] as $chip) { ?>
                        <span class="ratib-about-chip ratib-about-chip--<?php echo !empty($chip['ok']) ? 'ok' : 'muted'; ?>"><?php echo ratib_about_h((string) ($chip['label'] ?? '')); ?></span>
                        <?php } ?>
                    </div>
                </div>
                <div class="ratib-about-hero__visual" data-ratib-reveal data-ratib-delay="120">
                    <div class="ratib-about-metrics-row" aria-hidden="true">
                        <?php foreach ($about['hero_metrics'] ?? [] as $m) { ?>
                        <div class="ratib-about-metric ratib-about-metric--<?php echo ratib_about_h((string) ($m['tone'] ?? 'blue')); ?>">
                            <span class="ratib-about-metric__label"><?php echo ratib_about_h((string) ($m['label'] ?? '')); ?></span>
                            <span class="ratib-about-metric__value" data-ratib-count="<?php echo ratib_about_h(preg_replace('/[^0-9.]/', '', (string) ($m['value'] ?? ''))); ?>"><?php echo ratib_about_h((string) ($m['value'] ?? '')); ?></span>
                            <span class="ratib-about-metric__delta"><?php echo ratib_about_h((string) ($m['delta'] ?? '')); ?></span>
                        </div>
                        <?php } ?>
                    </div>
                    <figure class="ratib-about-shot ratib-about-shot--hero">
                        <img src="<?php echo ratib_about_h((string) ($shots['hero']['src'] ?? '')); ?>" alt="<?php echo ratib_about_h((string) ($shots['hero']['alt'] ?? '')); ?>" width="1280" height="720" loading="eager" decoding="async">
                        <figcaption class="ratib-about-shot__cap ratib-mono">Operations control plane · production surface</figcaption>
                    </figure>
                </div>
            </div>
            <div class="ratib-about-hero__glow" aria-hidden="true"></div>
        </section>

        <section class="ratib-about-section" id="what-is-ratib" aria-labelledby="about-what-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Category definition</p>
                    <h2 id="about-what-title" class="ratib-about-title">What RATIB is — and what it is not</h2>
                    <p class="ratib-about-sub">RATIB is workforce program infrastructure for regulated, high-volume international recruitment—not a lightweight CRM.</p>
                </header>
                <div class="ratib-about-compare">
                    <div class="ratib-about-glass ratib-about-compare__col ratib-about-compare__col--no" data-ratib-reveal>
                        <h3 class="ratib-about-compare__heading"><i class="fas fa-xmark" aria-hidden="true"></i> Not this</h3>
                        <ul class="ratib-about-list">
                            <?php foreach ($about['not_crm'] ?? [] as $line) { ?>
                            <li><?php echo ratib_about_h((string) $line); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                    <div class="ratib-about-glass ratib-about-compare__col ratib-about-compare__col--yes" data-ratib-reveal data-ratib-delay="80">
                        <h3 class="ratib-about-compare__heading"><i class="fas fa-check" aria-hidden="true"></i> This is RATIB</h3>
                        <ul class="ratib-about-list ratib-about-list--check">
                            <?php foreach ($about['is_infrastructure'] ?? [] as $line) { ?>
                            <li><?php echo ratib_about_h((string) $line); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="ratib-about-section ratib-about-section--arch" id="architecture" aria-labelledby="about-arch-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Platform architecture</p>
                    <h2 id="about-arch-title" class="ratib-about-title">Layered control plane</h2>
                    <p class="ratib-about-sub">Seven infrastructure layers—from experience surfaces to isolated program datastores.</p>
                </header>
                <div class="ratib-about-arch">
                    <div class="ratib-about-arch__viz" data-ratib-reveal aria-hidden="true">
                        <svg class="ratib-about-arch__svg" viewBox="0 0 400 520" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="ratibArchGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.9"/>
                                    <stop offset="100%" stop-color="#8B5CF6" stop-opacity="0.9"/>
                                </linearGradient>
                            </defs>
                            <?php
                            $layers = $about['architecture_layers'] ?? [];
                            $y = 24;
                            foreach ($layers as $i => $layer) {
                                $ly = $y + $i * 68;
                                ?>
                            <g class="ratib-about-arch__node" data-layer="<?php echo ratib_about_h((string) ($layer['id'] ?? '')); ?>">
                                <rect x="40" y="<?php echo $ly; ?>" width="320" height="52" rx="10" fill="rgba(17,24,39,0.85)" stroke="url(#ratibArchGrad)" stroke-width="1.2" opacity="0.95"/>
                                <circle cx="62" cy="<?php echo $ly + 26; ?>" r="4" fill="#3B82F6"><animate attributeName="opacity" values="0.4;1;0.4" dur="2.4s" repeatCount="indefinite" begin="<?php echo $i * 0.3; ?>s"/></circle>
                                <text x="78" y="<?php echo $ly + 30; ?>" fill="#e2e8f0" font-size="13" font-family="Inter, sans-serif" font-weight="600"><?php echo ratib_about_h((string) ($layer['title'] ?? '')); ?></text>
                                <?php if ($i < count($layers) - 1) { ?>
                                <line x1="200" y1="<?php echo $ly + 52; ?>" x2="200" y2="<?php echo $ly + 68; ?>" stroke="rgba(59,130,246,0.45)" stroke-width="1.5" stroke-dasharray="4 3"/>
                                <?php } ?>
                            </g>
                            <?php } ?>
                        </svg>
                    </div>
                    <div class="ratib-about-arch__stack">
                        <?php foreach ($about['architecture_layers'] ?? [] as $i => $layer) { ?>
                        <article class="ratib-about-glass ratib-about-arch__card" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 40); ?>" data-layer-card="<?php echo ratib_about_h((string) ($layer['id'] ?? '')); ?>">
                            <span class="ratib-about-arch__icon"><i class="fas <?php echo ratib_about_h((string) ($layer['icon'] ?? 'fa-circle')); ?>" aria-hidden="true"></i></span>
                            <div>
                                <h3><?php echo ratib_about_h((string) ($layer['title'] ?? '')); ?></h3>
                                <p><?php echo ratib_about_h((string) ($layer['body'] ?? '')); ?></p>
                            </div>
                        </article>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="ratib-about-section" id="operations" aria-labelledby="about-ops-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Agency workspace</p>
                    <h2 id="about-ops-title" class="ratib-about-title">Operations control plane</h2>
                    <p class="ratib-about-sub">Dense operational surfaces your teams run daily—agents, workforce records, finance, HR, and partner programs.</p>
                </header>
                <div class="ratib-about-split">
                    <div class="ratib-about-split__media" data-ratib-reveal>
                        <figure class="ratib-about-shot">
                            <img src="<?php echo ratib_about_h((string) ($shots['ops']['src'] ?? '')); ?>" alt="<?php echo ratib_about_h((string) ($shots['ops']['alt'] ?? '')); ?>" width="1200" height="675" loading="lazy" decoding="async">
                        </figure>
                    </div>
                    <div class="ratib-about-split__content">
                        <div class="ratib-about-feature-grid ratib-about-feature-grid--compact">
                            <?php foreach ($about['ops_modules'] ?? [] as $i => $mod) { ?>
                            <article class="ratib-about-glass ratib-about-feature" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 50); ?>">
                                <span class="ratib-about-feature__icon"><i class="fas <?php echo ratib_about_h((string) ($mod['icon'] ?? '')); ?>" aria-hidden="true"></i></span>
                                <h3><?php echo ratib_about_h((string) ($mod['title'] ?? '')); ?></h3>
                                <p><?php echo ratib_about_h((string) ($mod['body'] ?? '')); ?></p>
                            </article>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="ratib-about-shot-row" data-ratib-reveal>
                    <figure class="ratib-about-shot ratib-about-shot--third">
                        <img src="<?php echo ratib_about_h((string) ($shots['workers']['src'] ?? '')); ?>" alt="<?php echo ratib_about_h((string) ($shots['workers']['alt'] ?? '')); ?>" loading="lazy" decoding="async">
                        <figcaption>Workforce system of record</figcaption>
                    </figure>
                    <figure class="ratib-about-shot ratib-about-shot--third">
                        <img src="<?php echo ratib_about_h((string) ($shots['pipeline']['src'] ?? '')); ?>" alt="<?php echo ratib_about_h((string) ($shots['pipeline']['alt'] ?? '')); ?>" loading="lazy" decoding="async">
                        <figcaption>Orchestration pipeline</figcaption>
                    </figure>
                    <figure class="ratib-about-shot ratib-about-shot--third">
                        <img src="<?php echo ratib_about_h((string) ($shots['control']['src'] ?? '')); ?>" alt="<?php echo ratib_about_h((string) ($shots['control']['alt'] ?? '')); ?>" loading="lazy" decoding="async">
                        <figcaption>Platform control plane</figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <section class="ratib-about-section ratib-about-section--telemetry" id="telemetry" aria-labelledby="about-tel-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Critical capability</p>
                    <h2 id="about-tel-title" class="ratib-about-title">Workforce telemetry intelligence</h2>
                    <p class="ratib-about-sub">Not GPS tracking—a geospatial operations layer with intelligence at ingest, offline resilience, and sovereign-grade audit trails.</p>
                </header>
                <div class="ratib-about-split ratib-about-split--reverse">
                    <div class="ratib-about-split__content">
                        <div class="ratib-about-feature-grid">
                            <?php foreach ($about['telemetry_features'] ?? [] as $i => $f) { ?>
                            <article class="ratib-about-glass ratib-about-feature" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 45); ?>">
                                <span class="ratib-about-feature__icon ratib-about-feature__icon--cyan"><i class="fas <?php echo ratib_about_h((string) ($f['icon'] ?? '')); ?>" aria-hidden="true"></i></span>
                                <h3><?php echo ratib_about_h((string) ($f['title'] ?? '')); ?></h3>
                                <p><?php echo ratib_about_h((string) ($f['body'] ?? '')); ?></p>
                            </article>
                            <?php } ?>
                        </div>
                        <div class="ratib-about-event-stream" data-ratib-reveal aria-label="Sample telemetry events">
                            <div class="ratib-about-event ratib-about-event--ok"><span class="ratib-mono">WORKER_LOCATION_UPDATE</span> · geofence match · RUH corridor</div>
                            <div class="ratib-about-event ratib-about-event--warn"><span class="ratib-mono">WORKER_IDLE_ALERT</span> · SLA watch · 38h window</div>
                            <div class="ratib-about-event ratib-about-event--info"><span class="ratib-mono">WORKER_OFFLINE</span> · batch queued · sync pending</div>
                        </div>
                    </div>
                    <div class="ratib-about-split__media" data-ratib-reveal data-ratib-delay="100">
                        <figure class="ratib-about-shot ratib-about-shot--glow">
                            <img src="<?php echo ratib_about_h((string) ($shots['telemetry']['src'] ?? '')); ?>" alt="<?php echo ratib_about_h((string) ($shots['telemetry']['alt'] ?? '')); ?>" loading="lazy" decoding="async">
                            <figcaption class="ratib-about-shot__cap ratib-mono">Geospatial operations console</figcaption>
                        </figure>
                    </div>
                </div>
            </div>
        </section>

        <section class="ratib-about-section" id="governance" aria-labelledby="about-gov-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Sovereign-grade</p>
                    <h2 id="about-gov-title" class="ratib-about-title">Compliance &amp; governance</h2>
                    <p class="ratib-about-sub">Audit-ready by design—immutable history, government oversight, and tenant isolation for regulated programs.</p>
                </header>
                <div class="ratib-about-feature-grid ratib-about-feature-grid--3">
                    <?php foreach ($about['governance_features'] ?? [] as $i => $f) { ?>
                    <article class="ratib-about-glass ratib-about-feature" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 40); ?>">
                        <span class="ratib-about-feature__icon ratib-about-feature__icon--violet"><i class="fas <?php echo ratib_about_h((string) ($f['icon'] ?? '')); ?>" aria-hidden="true"></i></span>
                        <h3><?php echo ratib_about_h((string) ($f['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_about_h((string) ($f['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="ratib-about-section" id="finance" aria-labelledby="about-fin-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Controllers &amp; CFOs</p>
                    <h2 id="about-fin-title" class="ratib-about-title">Operational finance infrastructure</h2>
                    <p class="ratib-about-sub">Finance-grade ledger subsystem—not invoicing bolted onto a CRM.</p>
                </header>
                <div class="ratib-about-split">
                    <div class="ratib-about-split__media" data-ratib-reveal>
                        <figure class="ratib-about-shot">
                            <img src="<?php echo ratib_about_h((string) ($shots['accounting']['src'] ?? '')); ?>" alt="<?php echo ratib_about_h((string) ($shots['accounting']['alt'] ?? '')); ?>" loading="lazy" decoding="async">
                        </figure>
                    </div>
                    <div class="ratib-about-feature-grid ratib-about-feature-grid--2">
                        <?php foreach ($about['finance_features'] ?? [] as $i => $f) { ?>
                        <article class="ratib-about-glass ratib-about-feature" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 45); ?>">
                            <span class="ratib-about-feature__icon ratib-about-feature__icon--green"><i class="fas <?php echo ratib_about_h((string) ($f['icon'] ?? '')); ?>" aria-hidden="true"></i></span>
                            <h3><?php echo ratib_about_h((string) ($f['title'] ?? '')); ?></h3>
                            <p><?php echo ratib_about_h((string) ($f['body'] ?? '')); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="ratib-about-section ratib-about-section--corridors" id="corridors" aria-labelledby="about-cor-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Multi-corridor fabric</p>
                    <h2 id="about-cor-title" class="ratib-about-title">Multi-country operations</h2>
                    <p class="ratib-about-sub">Worker-sending markets on one orchestration core—with country-scoped governance and expanding corridors.</p>
                </header>
                <div class="ratib-about-corridors" data-ratib-reveal>
                    <div class="ratib-about-corridors__map" aria-hidden="true">
                        <svg viewBox="0 0 800 400" class="ratib-about-map-svg">
                            <defs><radialGradient id="mapGlow" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#3B82F6" stop-opacity="0.25"/><stop offset="100%" stop-color="transparent"/></radialGradient></defs>
                            <rect width="800" height="400" fill="url(#mapGlow)"/>
                            <ellipse cx="520" cy="120" rx="280" ry="140" fill="none" stroke="rgba(59,130,246,0.2)" stroke-width="1"/>
                            <ellipse cx="480" cy="200" rx="200" ry="100" fill="none" stroke="rgba(139,92,246,0.15)" stroke-width="1"/>
                            <?php
                            $nodes = [
                                ['cx' => 180, 'cy' => 140, 'label' => 'PH'],
                                ['cx' => 220, 'cy' => 170, 'label' => 'BD'],
                                ['cx' => 250, 'cy' => 220, 'label' => 'ID'],
                                ['cx' => 340, 'cy' => 260, 'label' => 'ET'],
                                ['cx' => 380, 'cy' => 240, 'label' => 'KE'],
                                ['cx' => 400, 'cy' => 220, 'label' => 'UG'],
                                ['cx' => 300, 'cy' => 200, 'label' => 'NG'],
                                ['cx' => 360, 'cy' => 280, 'label' => 'RW'],
                                ['cx' => 620, 'cy' => 160, 'label' => 'KSA', 'host' => true],
                            ];
                            foreach ($nodes as $n) {
                                $fill = !empty($n['host']) ? '#F97316' : '#3B82F6';
                                ?>
                            <g class="ratib-about-map-node">
                                <circle cx="<?php echo (int) $n['cx']; ?>" cy="<?php echo (int) $n['cy']; ?>" r="14" fill="<?php echo $fill; ?>" opacity="0.9"/>
                                <text x="<?php echo (int) $n['cx']; ?>" y="<?php echo (int) $n['cy'] + 4; ?>" text-anchor="middle" fill="#fff" font-size="9" font-weight="700" font-family="JetBrains Mono, monospace"><?php echo ratib_about_h((string) $n['label']); ?></text>
                            </g>
                            <?php } ?>
                        </svg>
                    </div>
                    <ul class="ratib-about-corridors__list">
                        <?php foreach ($about['corridors'] ?? [] as $c) { ?>
                        <li class="ratib-about-corridor-chip"><span class="ratib-mono"><?php echo ratib_about_h((string) ($c['code'] ?? '')); ?></span> <?php echo ratib_about_h((string) ($c['name'] ?? '')); ?></li>
                        <?php } ?>
                        <li class="ratib-about-corridor-chip ratib-about-corridor-chip--host"><span class="ratib-mono">KSA</span> Host-market programs · Riyadh HQ</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="ratib-about-section" id="trust" aria-labelledby="about-trust-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Enterprise trust</p>
                    <h2 id="about-trust-title" class="ratib-about-title">Infrastructure you can defend in procurement</h2>
                </header>
                <div class="ratib-about-trust-grid">
                    <?php foreach ($about['trust_items'] ?? [] as $i => $t) { ?>
                    <article class="ratib-about-glass ratib-about-trust" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 35); ?>">
                        <i class="fas <?php echo ratib_about_h((string) ($t['icon'] ?? '')); ?>" aria-hidden="true"></i>
                        <h3><?php echo ratib_about_h((string) ($t['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_about_h((string) ($t['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="ratib-about-section" id="partners" aria-labelledby="about-part-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Host-market ecosystem</p>
                    <h2 id="about-part-title" class="ratib-about-title">Partner collaboration surface</h2>
                    <p class="ratib-about-sub">Deployments, documents, and statements—without email chaos.</p>
                </header>
                <div class="ratib-about-split">
                    <div class="ratib-about-feature-grid ratib-about-feature-grid--2">
                        <?php foreach ($about['partner_features'] ?? [] as $i => $f) { ?>
                        <article class="ratib-about-glass ratib-about-feature" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 50); ?>">
                            <span class="ratib-about-feature__icon"><i class="fas <?php echo ratib_about_h((string) ($f['icon'] ?? '')); ?>" aria-hidden="true"></i></span>
                            <h3><?php echo ratib_about_h((string) ($f['title'] ?? '')); ?></h3>
                            <p><?php echo ratib_about_h((string) ($f['body'] ?? '')); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                    <figure class="ratib-about-shot" data-ratib-reveal data-ratib-delay="80">
                        <img src="<?php echo ratib_about_h((string) ($shots['partners']['src'] ?? '')); ?>" alt="<?php echo ratib_about_h((string) ($shots['partners']['alt'] ?? '')); ?>" loading="lazy" decoding="async">
                    </figure>
                </div>
            </div>
        </section>

        <section class="ratib-about-section ratib-about-section--services" id="platform-services" aria-labelledby="about-svc-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow">Secondary · digital edge</p>
                    <h2 id="about-svc-title" class="ratib-about-title">Platform services</h2>
                    <p class="ratib-about-sub">Domains, SSL, and provisioning for branded agency edges—adjacent to the recruitment control plane, not the lead story.</p>
                </header>
                <div class="ratib-about-feature-grid ratib-about-feature-grid--4">
                    <?php foreach ($about['platform_services'] ?? [] as $i => $f) { ?>
                    <article class="ratib-about-glass ratib-about-feature ratib-about-feature--muted" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 40); ?>">
                        <span class="ratib-about-feature__icon"><i class="fas <?php echo ratib_about_h((string) ($f['icon'] ?? '')); ?>" aria-hidden="true"></i></span>
                        <h3><?php echo ratib_about_h((string) ($f['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_about_h((string) ($f['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>

        <section class="ratib-about-cta" id="contact-cta" aria-labelledby="about-cta-title">
            <div class="ratib-about-container ratib-about-cta__inner" data-ratib-reveal>
                <h2 id="about-cta-title" class="ratib-about-cta__title">Operate corridors, not spreadsheets.</h2>
                <p class="ratib-about-cta__sub">Deploy production-grade workforce program infrastructure for your agency or country program.</p>
                <div class="ratib-about-cta__actions">
                    <a href="<?php echo ratib_about_h($contactWa); ?>" target="_blank" rel="noopener noreferrer" class="ratib-about-btn ratib-about-btn--primary ratib-about-btn--xl">Request Enterprise Demo</a>
                    <a href="<?php echo ratib_about_h($homeRegister); ?>" class="ratib-about-btn ratib-about-btn--outline ratib-about-btn--xl">Deploy Agency Workspace</a>
                    <a href="mailto:ratibstar@gmail.com" class="ratib-about-btn ratib-about-btn--ghost">Talk to Solutions Team</a>
                </div>
                <p class="ratib-about-cta__legal ratib-mono">Ratib Software Foundation for Information Technology · Riyadh, Saudi Arabia · out.ratib.sa</p>
            </div>
        </section>
        <?php
    }
}
