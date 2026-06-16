<?php
/**
 * Renders procurement & legal enterprise page sections.
 */
declare(strict_types=1);

if (!function_exists('rateb_proc_h')) {
    function rateb_proc_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rateb_procurement_legal_render_sections')) {
    function rateb_procurement_legal_render_sections(array $proc): void
    {
        rateb_proc_render_hero($proc['hero'] ?? []);
        rateb_proc_render_identity($proc['identity'] ?? []);
        rateb_proc_render_engagement($proc['engagement'] ?? []);
        rateb_proc_render_security_governance($proc['security_governance'] ?? []);
        rateb_proc_render_data_boundaries($proc['data_boundaries'] ?? []);
        rateb_proc_render_legal_notes($proc['legal_notes'] ?? []);
        rateb_proc_render_requests($proc['requests'] ?? []);
        rateb_proc_render_contact($proc['contact'] ?? []);
    }
}

if (!function_exists('rateb_proc_render_hero')) {
    /** @param array<string, mixed> $hero */
    function rateb_proc_render_hero(array $hero): void
    {
        ?>
        <section class="rateb-proc-hero" id="top" aria-labelledby="proc-hero-title">
            <div class="rateb-about-container">
                <div class="rateb-proc-hero__inner" data-rateb-reveal>
                    <p class="rateb-about-page-label"><?php echo rateb_proc_h((string) ($hero['eyebrow'] ?? '')); ?></p>
                    <h1 id="proc-hero-title" class="rateb-proc-hero__title"><?php echo rateb_proc_h((string) ($hero['title'] ?? '')); ?></h1>
                    <p class="rateb-proc-hero__lead"><?php echo rateb_proc_h((string) ($hero['lead'] ?? '')); ?></p>
                    <?php if (!empty($hero['notice'])) { ?>
                    <p class="rateb-proc-notice rateb-mono"><?php echo rateb_proc_h((string) $hero['notice']); ?></p>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_proc_render_identity')) {
    /** @param array<string, mixed> $section */
    function rateb_proc_render_identity(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'company-identity');
        ?>
        <section class="rateb-about-section rateb-proc-section" id="<?php echo rateb_proc_h($id); ?>" aria-labelledby="proc-identity-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-identity-title" class="rateb-about-title"><?php echo rateb_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-proc-identity-grid">
                    <?php foreach ($section['fields'] ?? [] as $i => $field) {
                        $href = (string) ($field['href'] ?? '');
                        ?>
                    <div class="rateb-proc-identity-row rateb-about-glass" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 35); ?>">
                        <span class="rateb-proc-identity-row__icon" aria-hidden="true"><i class="fas <?php echo rateb_proc_h((string) ($field['icon'] ?? 'fa-circle')); ?>"></i></span>
                        <div class="rateb-proc-identity-row__body">
                            <dt class="rateb-mono"><?php echo rateb_proc_h((string) ($field['label'] ?? '')); ?></dt>
                            <dd>
                                <?php if ($href !== '') { ?>
                                <a href="<?php echo rateb_proc_h($href); ?>"<?php echo str_starts_with($href, 'http') ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo rateb_proc_h((string) ($field['value'] ?? '')); ?></a>
                                <?php } else { ?>
                                <?php echo rateb_proc_h((string) ($field['value'] ?? '')); ?>
                                <?php } ?>
                            </dd>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_proc_render_engagement')) {
    /** @param array<string, mixed> $section */
    function rateb_proc_render_engagement(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'enterprise-engagement');
        ?>
        <section class="rateb-about-section rateb-proc-section rateb-proc-section--engagement" id="<?php echo rateb_proc_h($id); ?>" aria-labelledby="proc-engagement-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-engagement-title" class="rateb-about-title"><?php echo rateb_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <ol class="rateb-proc-process">
                    <?php foreach ($section['steps'] ?? [] as $i => $step) { ?>
                    <li class="rateb-proc-process__step rateb-about-glass" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 45); ?>">
                        <span class="rateb-proc-process__num rateb-mono"><?php echo str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); ?></span>
                        <span class="rateb-proc-process__icon" aria-hidden="true"><i class="fas <?php echo rateb_proc_h((string) ($step['icon'] ?? 'fa-circle')); ?>"></i></span>
                        <div class="rateb-proc-process__copy">
                            <h3><?php echo rateb_proc_h((string) ($step['title'] ?? '')); ?></h3>
                            <p><?php echo rateb_proc_h((string) ($step['body'] ?? '')); ?></p>
                        </div>
                    </li>
                    <?php } ?>
                </ol>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_proc_render_security_governance')) {
    /** @param array<string, mixed> $section */
    function rateb_proc_render_security_governance(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'security-governance');
        ?>
        <section class="rateb-about-section rateb-proc-section rateb-proc-section--security" id="<?php echo rateb_proc_h($id); ?>" aria-labelledby="proc-security-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-security-title" class="rateb-about-title"><?php echo rateb_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <p class="rateb-proc-summary rateb-about-glass" data-rateb-reveal><?php echo rateb_proc_h((string) ($section['summary'] ?? '')); ?></p>
                <div class="rateb-proc-ref-links">
                    <?php foreach ($section['links'] ?? [] as $i => $link) { ?>
                    <a href="<?php echo rateb_proc_h((string) ($link['href'] ?? '#')); ?>" class="rateb-proc-ref-link rateb-about-glass" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 50); ?>">
                        <span class="rateb-proc-ref-link__icon" aria-hidden="true"><i class="fas <?php echo rateb_proc_h((string) ($link['icon'] ?? 'fa-arrow-right')); ?>"></i></span>
                        <span class="rateb-proc-ref-link__copy">
                            <strong><?php echo rateb_proc_h((string) ($link['title'] ?? '')); ?></strong>
                            <span><?php echo rateb_proc_h((string) ($link['desc'] ?? '')); ?></span>
                        </span>
                        <span class="rateb-proc-ref-link__arrow" aria-hidden="true">→</span>
                    </a>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_proc_render_data_boundaries')) {
    /** @param array<string, mixed> $section */
    function rateb_proc_render_data_boundaries(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'data-tenant-boundaries');
        ?>
        <section class="rateb-about-section rateb-proc-section" id="<?php echo rateb_proc_h($id); ?>" aria-labelledby="proc-boundaries-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head rateb-about-head--center" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-boundaries-title" class="rateb-about-title"><?php echo rateb_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-proc-boundary-diagram" data-rateb-reveal aria-hidden="true">
                    <div class="rateb-proc-boundary-diagram__plane">Platform core</div>
                    <div class="rateb-proc-boundary-diagram__line"></div>
                    <div class="rateb-proc-boundary-diagram__tenants">
                        <span>Tenant datastore</span>
                        <span>Tenant datastore</span>
                        <span>Tenant datastore</span>
                    </div>
                </div>
                <div class="rateb-about-feature-grid rateb-about-feature-grid--2">
                    <?php foreach ($section['points'] ?? [] as $i => $pt) { ?>
                    <article class="rateb-about-glass rateb-about-feature" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 40); ?>">
                        <h3><?php echo rateb_proc_h((string) ($pt['title'] ?? '')); ?></h3>
                        <p><?php echo rateb_proc_h((string) ($pt['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_proc_render_legal_notes')) {
    /** @param array<string, mixed> $section */
    function rateb_proc_render_legal_notes(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'legal-operational-notes');
        ?>
        <section class="rateb-about-section rateb-proc-section rateb-proc-section--legal" id="<?php echo rateb_proc_h($id); ?>" aria-labelledby="proc-legal-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-legal-title" class="rateb-about-title"><?php echo rateb_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <ul class="rateb-proc-legal-list">
                    <?php foreach ($section['items'] ?? [] as $i => $item) { ?>
                    <li class="rateb-proc-legal-list__item rateb-about-glass" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 40); ?>">
                        <h3><?php echo rateb_proc_h((string) ($item['title'] ?? '')); ?></h3>
                        <p><?php echo rateb_proc_h((string) ($item['body'] ?? '')); ?></p>
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_proc_render_requests')) {
    /** @param array<string, mixed> $section */
    function rateb_proc_render_requests(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'procurement-requests');
        ?>
        <section class="rateb-about-section rateb-proc-section rateb-proc-section--requests" id="<?php echo rateb_proc_h($id); ?>" aria-labelledby="proc-requests-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head rateb-about-head--center" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-requests-title" class="rateb-about-title"><?php echo rateb_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-proc-cta-grid">
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
                    <article class="rateb-about-glass rateb-proc-cta-card" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 55); ?>">
                        <span class="rateb-proc-cta-card__icon" aria-hidden="true"><i class="fas <?php echo rateb_proc_h((string) ($cta['icon'] ?? 'fa-envelope')); ?>"></i></span>
                        <h3><?php echo rateb_proc_h((string) ($cta['title'] ?? '')); ?></h3>
                        <p><?php echo rateb_proc_h((string) ($cta['body'] ?? '')); ?></p>
                        <a href="<?php echo rateb_proc_h((string) ($cta['href'] ?? '#')); ?>" class="<?php echo rateb_proc_h($btnClass); ?>"><?php echo rateb_proc_h((string) ($cta['title'] ?? 'Contact')); ?></a>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_proc_render_contact')) {
    /** @param array<string, mixed> $contact */
    function rateb_proc_render_contact(array $contact): void
    {
        if ($contact === []) {
            return;
        }
        $id = (string) ($contact['id'] ?? 'contact-escalation');
        $email = (string) ($contact['email'] ?? 'info@rateb.sa');
        ?>
        <section class="rateb-about-section rateb-proc-section rateb-proc-section--contact" id="<?php echo rateb_proc_h($id); ?>" aria-labelledby="proc-contact-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head rateb-about-head--center" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_proc_h((string) ($contact['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-contact-title" class="rateb-about-title"><?php echo rateb_proc_h((string) ($contact['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_proc_h((string) ($contact['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-proc-contact-card rateb-about-glass" data-rateb-reveal>
                    <div class="rateb-proc-contact-card__primary">
                        <a href="mailto:<?php echo rateb_proc_h($email); ?>" class="rateb-proc-contact-email"><?php echo rateb_proc_h($email); ?></a>
                        <p class="rateb-mono rateb-proc-contact-card__hq"><?php echo rateb_proc_h((string) ($contact['hq'] ?? '')); ?></p>
                    </div>
                    <ul class="rateb-proc-contact-card__channels">
                        <li>
                            <a href="mailto:<?php echo rateb_proc_h($email); ?>"><i class="fas fa-envelope" aria-hidden="true"></i> Email escalation</a>
                        </li>
                        <?php if (!empty($contact['phone_href'])) { ?>
                        <li>
                            <a href="<?php echo rateb_proc_h((string) $contact['phone_href']); ?>" dir="ltr"><i class="fas fa-phone" aria-hidden="true"></i> <?php echo rateb_proc_h((string) ($contact['phone'] ?? '')); ?></a>
                        </li>
                        <?php } ?>
                        <?php if (!empty($contact['whatsapp'])) { ?>
                        <li>
                            <a href="<?php echo rateb_proc_h((string) $contact['whatsapp']); ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp (business)</a>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        </section>
        <?php
    }
}
