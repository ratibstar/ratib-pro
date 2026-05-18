<?php
/**
 * Renders security & compliance trust center sections.
 */
declare(strict_types=1);

if (!function_exists('ratib_trust_h')) {
    function ratib_trust_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ratib_security_compliance_render_sections')) {
    function ratib_security_compliance_render_sections(array $trust, string $baseUrl): void
    {
        $hero = $trust['hero'] ?? [];
        $sections = $trust['sections'] ?? [];
        $procurement = $trust['procurement'] ?? [];
        $disclaimer = (string) ($trust['disclaimer'] ?? '');
        $contactWa = (string) (($trust['contact']['whatsapp'] ?? '') ?: 'https://wa.me/966599863868');
        ?>
        <section class="ratib-trust-hero" id="top" aria-labelledby="trust-hero-title">
            <div class="ratib-about-container">
                <div class="ratib-trust-hero__grid">
                    <div class="ratib-trust-hero__copy" data-ratib-reveal>
                        <p class="ratib-about-page-label"><?php echo ratib_trust_h((string) ($hero['eyebrow'] ?? '')); ?></p>
                        <h1 id="trust-hero-title" class="ratib-trust-hero__title"><?php echo ratib_trust_h((string) ($hero['title'] ?? '')); ?></h1>
                        <p class="ratib-trust-hero__lead"><?php echo ratib_trust_h((string) ($hero['lead'] ?? '')); ?></p>
                        <?php if ($disclaimer !== '') { ?>
                        <p class="ratib-trust-disclaimer ratib-mono"><?php echo ratib_trust_h($disclaimer); ?></p>
                        <?php } ?>
                    </div>
                    <div class="ratib-trust-hero__aside" data-ratib-reveal data-ratib-delay="80">
                        <div class="ratib-trust-status-strip" aria-label="Security posture indicators">
                            <?php foreach ($hero['chips'] ?? [] as $chip) { ?>
                            <span class="ratib-about-chip ratib-about-chip--<?php echo !empty($chip['ok']) ? 'ok' : 'muted'; ?>"><?php echo ratib_trust_h((string) ($chip['label'] ?? '')); ?></span>
                            <?php } ?>
                        </div>
                        <div class="ratib-trust-hero__panel ratib-about-glass">
                            <p class="ratib-trust-hero__panel-kicker ratib-mono">procurement posture</p>
                            <ul class="ratib-trust-hero__panel-list">
                                <li>Architecture documentation available on request</li>
                                <li>Tenant isolation &amp; governance walkthroughs</li>
                                <li>No implied SOC 2 / ISO claims on this page</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php
        ratib_trust_render_feature_section($sections['security_overview'] ?? [], 'ratib-trust-section--security');
        ratib_trust_render_feature_section($sections['compliance_governance'] ?? [], 'ratib-trust-section--governance');
        ratib_trust_render_isolation_section($sections['data_isolation'] ?? []);
        ratib_trust_render_feature_section($sections['authentication'] ?? [], 'ratib-trust-section--auth', 'ratib-about-feature-grid--2');
        ratib_trust_render_feature_section($sections['reliability'] ?? [], 'ratib-trust-section--reliability');
        ratib_trust_render_feature_section($sections['infrastructure'] ?? [], 'ratib-trust-section--infra', 'ratib-about-feature-grid--3');
        ratib_trust_render_procurement_section($procurement, $contactWa);
    }
}

if (!function_exists('ratib_trust_render_feature_section')) {
    /**
     * @param array<string, mixed> $section
     */
    function ratib_trust_render_feature_section(array $section, string $extraClass = '', string $gridClass = 'ratib-about-feature-grid--3'): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? '');
        $titleId = $id !== '' ? 'trust-' . $id . '-title' : 'trust-section-title';
        ?>
        <section class="ratib-about-section ratib-trust-section <?php echo ratib_trust_h($extraClass); ?>"<?php echo $id !== '' ? ' id="' . ratib_trust_h($id) . '"' : ''; ?> aria-labelledby="<?php echo ratib_trust_h($titleId); ?>">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_trust_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="<?php echo ratib_trust_h($titleId); ?>" class="ratib-about-title"><?php echo ratib_trust_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_trust_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-about-feature-grid <?php echo ratib_trust_h($gridClass); ?>">
                    <?php foreach ($section['items'] ?? [] as $i => $item) { ?>
                    <article class="ratib-about-glass ratib-about-feature" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 40); ?>">
                        <span class="ratib-about-feature__icon ratib-about-feature__icon--cyan"><i class="fas <?php echo ratib_trust_h((string) ($item['icon'] ?? 'fa-circle')); ?>" aria-hidden="true"></i></span>
                        <h3><?php echo ratib_trust_h((string) ($item['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_trust_h((string) ($item['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_trust_render_isolation_section')) {
    /**
     * @param array<string, mixed> $section
     */
    function ratib_trust_render_isolation_section(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'data-isolation');
        ?>
        <section class="ratib-about-section ratib-trust-section ratib-trust-section--isolation" id="<?php echo ratib_trust_h($id); ?>" aria-labelledby="trust-data-isolation-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_trust_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="trust-data-isolation-title" class="ratib-about-title"><?php echo ratib_trust_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_trust_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-trust-isolation">
                    <div class="ratib-trust-isolation__diagram" data-ratib-reveal aria-hidden="true">
                        <div class="ratib-trust-isolation__tier ratib-trust-isolation__tier--control">
                            <span class="ratib-mono">control plane</span>
                        </div>
                        <div class="ratib-trust-isolation__connector" aria-hidden="true"></div>
                        <div class="ratib-trust-isolation__tenants">
                            <div class="ratib-trust-isolation__tenant">Tenant A</div>
                            <div class="ratib-trust-isolation__tenant">Tenant B</div>
                            <div class="ratib-trust-isolation__tenant">Tenant C</div>
                        </div>
                    </div>
                    <div class="ratib-trust-isolation__cards">
                        <?php foreach ($section['layers'] ?? [] as $i => $layer) { ?>
                        <article class="ratib-about-glass ratib-trust-isolation__card" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 50); ?>">
                            <h3><?php echo ratib_trust_h((string) ($layer['label'] ?? '')); ?></h3>
                            <p><?php echo ratib_trust_h((string) ($layer['body'] ?? '')); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_trust_render_procurement_section')) {
    /**
     * @param array<string, mixed> $section
     */
    function ratib_trust_render_procurement_section(array $section, string $contactWa): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'procurement');
        ?>
        <section class="ratib-about-section ratib-trust-section ratib-trust-section--procurement" id="<?php echo ratib_trust_h($id); ?>" aria-labelledby="trust-procurement-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_trust_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="trust-procurement-title" class="ratib-about-title"><?php echo ratib_trust_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_trust_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-trust-procurement">
                    <?php foreach ($section['ctas'] ?? [] as $i => $cta) {
                        $variant = (string) ($cta['variant'] ?? 'outline');
                        $btnClass = 'ratib-about-btn';
                        if ($variant === 'primary') {
                            $btnClass .= ' ratib-about-btn--primary';
                        } elseif ($variant === 'ghost') {
                            $btnClass .= ' ratib-about-btn--ghost';
                        } else {
                            $btnClass .= ' ratib-about-btn--outline';
                        }
                        ?>
                    <article class="ratib-about-glass ratib-trust-procurement__card" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 60); ?>">
                        <span class="ratib-trust-procurement__icon" aria-hidden="true"><i class="fas <?php echo ratib_trust_h((string) ($cta['icon'] ?? 'fa-envelope')); ?>"></i></span>
                        <h3><?php echo ratib_trust_h((string) ($cta['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_trust_h((string) ($cta['body'] ?? '')); ?></p>
                        <a href="<?php echo ratib_trust_h((string) ($cta['href'] ?? '#')); ?>" class="<?php echo ratib_trust_h($btnClass); ?>"><?php echo ratib_trust_h((string) ($cta['title'] ?? 'Contact')); ?></a>
                    </article>
                    <?php } ?>
                </div>
                <p class="ratib-trust-procurement__wa ratib-mono" data-ratib-reveal>
                    Prefer live discussion? <a href="<?php echo ratib_trust_h($contactWa); ?>" target="_blank" rel="noopener noreferrer">WhatsApp enterprise line</a>
                </p>
            </div>
        </section>
        <?php
    }
}
