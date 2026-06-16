<?php
/**
 * Renders security & compliance trust center sections.
 */
declare(strict_types=1);

if (!function_exists('rateb_trust_h')) {
    function rateb_trust_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rateb_security_compliance_render_sections')) {
    function rateb_security_compliance_render_sections(array $trust, string $baseUrl): void
    {
        $hero = $trust['hero'] ?? [];
        $sections = $trust['sections'] ?? [];
        $procurement = $trust['procurement'] ?? [];
        $disclaimer = (string) ($trust['disclaimer'] ?? '');
        $contactWa = (string) (($trust['contact']['whatsapp'] ?? '') ?: 'https://wa.me/966599863868');
        ?>
        <section class="rateb-trust-hero" id="top" aria-labelledby="trust-hero-title">
            <div class="rateb-about-container">
                <div class="rateb-trust-hero__grid">
                    <div class="rateb-trust-hero__copy" data-rateb-reveal>
                        <p class="rateb-about-page-label"><?php echo rateb_trust_h((string) ($hero['eyebrow'] ?? '')); ?></p>
                        <h1 id="trust-hero-title" class="rateb-trust-hero__title"><?php echo rateb_trust_h((string) ($hero['title'] ?? '')); ?></h1>
                        <p class="rateb-trust-hero__lead"><?php echo rateb_trust_h((string) ($hero['lead'] ?? '')); ?></p>
                        <?php if ($disclaimer !== '') { ?>
                        <p class="rateb-trust-disclaimer rateb-mono"><?php echo rateb_trust_h($disclaimer); ?></p>
                        <?php } ?>
                    </div>
                    <div class="rateb-trust-hero__aside" data-rateb-reveal data-rateb-delay="80">
                        <div class="rateb-trust-status-strip" aria-label="Security posture indicators">
                            <?php foreach ($hero['chips'] ?? [] as $chip) { ?>
                            <span class="rateb-about-chip rateb-about-chip--<?php echo !empty($chip['ok']) ? 'ok' : 'muted'; ?>"><?php echo rateb_trust_h((string) ($chip['label'] ?? '')); ?></span>
                            <?php } ?>
                        </div>
                        <div class="rateb-trust-hero__panel rateb-about-glass">
                            <p class="rateb-trust-hero__panel-kicker rateb-mono">procurement posture</p>
                            <ul class="rateb-trust-hero__panel-list">
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
        rateb_trust_render_feature_section($sections['security_overview'] ?? [], 'rateb-trust-section--security');
        rateb_trust_render_feature_section($sections['compliance_governance'] ?? [], 'rateb-trust-section--governance');
        rateb_trust_render_isolation_section($sections['data_isolation'] ?? []);
        rateb_trust_render_feature_section($sections['authentication'] ?? [], 'rateb-trust-section--auth', 'rateb-about-feature-grid--2');
        rateb_trust_render_feature_section($sections['reliability'] ?? [], 'rateb-trust-section--reliability');
        rateb_trust_render_feature_section($sections['infrastructure'] ?? [], 'rateb-trust-section--infra', 'rateb-about-feature-grid--3');
        rateb_trust_render_procurement_section($procurement, $contactWa);
    }
}

if (!function_exists('rateb_trust_render_feature_section')) {
    /**
     * @param array<string, mixed> $section
     */
    function rateb_trust_render_feature_section(array $section, string $extraClass = '', string $gridClass = 'rateb-about-feature-grid--3'): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? '');
        $titleId = $id !== '' ? 'trust-' . $id . '-title' : 'trust-section-title';
        ?>
        <section class="rateb-about-section rateb-trust-section <?php echo rateb_trust_h($extraClass); ?>"<?php echo $id !== '' ? ' id="' . rateb_trust_h($id) . '"' : ''; ?> aria-labelledby="<?php echo rateb_trust_h($titleId); ?>">
            <div class="rateb-about-container">
                <header class="rateb-about-head" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_trust_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="<?php echo rateb_trust_h($titleId); ?>" class="rateb-about-title"><?php echo rateb_trust_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_trust_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-about-feature-grid <?php echo rateb_trust_h($gridClass); ?>">
                    <?php foreach ($section['items'] ?? [] as $i => $item) { ?>
                    <article class="rateb-about-glass rateb-about-feature" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 40); ?>">
                        <span class="rateb-about-feature__icon rateb-about-feature__icon--cyan"><i class="fas <?php echo rateb_trust_h((string) ($item['icon'] ?? 'fa-circle')); ?>" aria-hidden="true"></i></span>
                        <h3><?php echo rateb_trust_h((string) ($item['title'] ?? '')); ?></h3>
                        <p><?php echo rateb_trust_h((string) ($item['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_trust_render_isolation_section')) {
    /**
     * @param array<string, mixed> $section
     */
    function rateb_trust_render_isolation_section(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'data-isolation');
        ?>
        <section class="rateb-about-section rateb-trust-section rateb-trust-section--isolation" id="<?php echo rateb_trust_h($id); ?>" aria-labelledby="trust-data-isolation-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head rateb-about-head--center" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_trust_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="trust-data-isolation-title" class="rateb-about-title"><?php echo rateb_trust_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_trust_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-trust-isolation">
                    <div class="rateb-trust-isolation__diagram" data-rateb-reveal aria-hidden="true">
                        <div class="rateb-trust-isolation__tier rateb-trust-isolation__tier--control">
                            <span class="rateb-mono">platform core</span>
                        </div>
                        <div class="rateb-trust-isolation__connector" aria-hidden="true"></div>
                        <div class="rateb-trust-isolation__tenants">
                            <div class="rateb-trust-isolation__tenant">Tenant A</div>
                            <div class="rateb-trust-isolation__tenant">Tenant B</div>
                            <div class="rateb-trust-isolation__tenant">Tenant C</div>
                        </div>
                    </div>
                    <div class="rateb-trust-isolation__cards">
                        <?php foreach ($section['layers'] ?? [] as $i => $layer) { ?>
                        <article class="rateb-about-glass rateb-trust-isolation__card" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 50); ?>">
                            <h3><?php echo rateb_trust_h((string) ($layer['label'] ?? '')); ?></h3>
                            <p><?php echo rateb_trust_h((string) ($layer['body'] ?? '')); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_trust_render_procurement_section')) {
    /**
     * @param array<string, mixed> $section
     */
    function rateb_trust_render_procurement_section(array $section, string $contactWa): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'procurement');
        ?>
        <section class="rateb-about-section rateb-trust-section rateb-trust-section--procurement" id="<?php echo rateb_trust_h($id); ?>" aria-labelledby="trust-procurement-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head rateb-about-head--center" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_trust_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="trust-procurement-title" class="rateb-about-title"><?php echo rateb_trust_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_trust_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-trust-procurement">
                    <?php foreach ($section['ctas'] ?? [] as $i => $cta) {
                        $variant = (string) ($cta['variant'] ?? 'outline');
                        $btnClass = 'rateb-about-btn';
                        if ($variant === 'primary') {
                            $btnClass .= ' rateb-about-btn--primary';
                        } elseif ($variant === 'ghost') {
                            $btnClass .= ' rateb-about-btn--ghost';
                        } else {
                            $btnClass .= ' rateb-about-btn--outline';
                        }
                        ?>
                    <article class="rateb-about-glass rateb-trust-procurement__card" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 60); ?>">
                        <span class="rateb-trust-procurement__icon" aria-hidden="true"><i class="fas <?php echo rateb_trust_h((string) ($cta['icon'] ?? 'fa-envelope')); ?>"></i></span>
                        <h3><?php echo rateb_trust_h((string) ($cta['title'] ?? '')); ?></h3>
                        <p><?php echo rateb_trust_h((string) ($cta['body'] ?? '')); ?></p>
                        <a href="<?php echo rateb_trust_h((string) ($cta['href'] ?? '#')); ?>" class="<?php echo rateb_trust_h($btnClass); ?>"><?php echo rateb_trust_h((string) ($cta['title'] ?? 'Contact')); ?></a>
                    </article>
                    <?php } ?>
                </div>
                <p class="rateb-trust-procurement__wa rateb-mono" data-rateb-reveal>
                    Prefer live discussion? <a href="<?php echo rateb_trust_h($contactWa); ?>" target="_blank" rel="noopener noreferrer">WhatsApp enterprise line</a>
                </p>
            </div>
        </section>
        <?php
    }
}
