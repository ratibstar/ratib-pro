<?php
/**
 * Renders procurement & legal enterprise page sections.
 */
declare(strict_types=1);

if (!function_exists('ratib_proc_h')) {
    function ratib_proc_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ratib_procurement_legal_render_sections')) {
    function ratib_procurement_legal_render_sections(array $proc): void
    {
        ratib_proc_render_hero($proc['hero'] ?? []);
        ratib_proc_render_identity($proc['identity'] ?? []);
        ratib_proc_render_engagement($proc['engagement'] ?? []);
        ratib_proc_render_security_governance($proc['security_governance'] ?? []);
        ratib_proc_render_data_boundaries($proc['data_boundaries'] ?? []);
        ratib_proc_render_legal_notes($proc['legal_notes'] ?? []);
        ratib_proc_render_requests($proc['requests'] ?? []);
        ratib_proc_render_contact($proc['contact'] ?? []);
    }
}

if (!function_exists('ratib_proc_render_hero')) {
    /** @param array<string, mixed> $hero */
    function ratib_proc_render_hero(array $hero): void
    {
        ?>
        <section class="ratib-proc-hero" id="top" aria-labelledby="proc-hero-title">
            <div class="ratib-about-container">
                <div class="ratib-proc-hero__inner" data-ratib-reveal>
                    <p class="ratib-about-page-label"><?php echo ratib_proc_h((string) ($hero['eyebrow'] ?? '')); ?></p>
                    <h1 id="proc-hero-title" class="ratib-proc-hero__title"><?php echo ratib_proc_h((string) ($hero['title'] ?? '')); ?></h1>
                    <p class="ratib-proc-hero__lead"><?php echo ratib_proc_h((string) ($hero['lead'] ?? '')); ?></p>
                    <?php if (!empty($hero['notice'])) { ?>
                    <p class="ratib-proc-notice ratib-mono"><?php echo ratib_proc_h((string) $hero['notice']); ?></p>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_proc_render_identity')) {
    /** @param array<string, mixed> $section */
    function ratib_proc_render_identity(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'company-identity');
        ?>
        <section class="ratib-about-section ratib-proc-section" id="<?php echo ratib_proc_h($id); ?>" aria-labelledby="proc-identity-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-identity-title" class="ratib-about-title"><?php echo ratib_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-proc-identity-grid">
                    <?php foreach ($section['fields'] ?? [] as $i => $field) {
                        $href = (string) ($field['href'] ?? '');
                        ?>
                    <div class="ratib-proc-identity-row ratib-about-glass" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 35); ?>">
                        <span class="ratib-proc-identity-row__icon" aria-hidden="true"><i class="fas <?php echo ratib_proc_h((string) ($field['icon'] ?? 'fa-circle')); ?>"></i></span>
                        <div class="ratib-proc-identity-row__body">
                            <dt class="ratib-mono"><?php echo ratib_proc_h((string) ($field['label'] ?? '')); ?></dt>
                            <dd>
                                <?php if ($href !== '') { ?>
                                <a href="<?php echo ratib_proc_h($href); ?>"<?php echo str_starts_with($href, 'http') ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo ratib_proc_h((string) ($field['value'] ?? '')); ?></a>
                                <?php } else { ?>
                                <?php echo ratib_proc_h((string) ($field['value'] ?? '')); ?>
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

if (!function_exists('ratib_proc_render_engagement')) {
    /** @param array<string, mixed> $section */
    function ratib_proc_render_engagement(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'enterprise-engagement');
        ?>
        <section class="ratib-about-section ratib-proc-section ratib-proc-section--engagement" id="<?php echo ratib_proc_h($id); ?>" aria-labelledby="proc-engagement-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-engagement-title" class="ratib-about-title"><?php echo ratib_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <ol class="ratib-proc-process">
                    <?php foreach ($section['steps'] ?? [] as $i => $step) { ?>
                    <li class="ratib-proc-process__step ratib-about-glass" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 45); ?>">
                        <span class="ratib-proc-process__num ratib-mono"><?php echo str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); ?></span>
                        <span class="ratib-proc-process__icon" aria-hidden="true"><i class="fas <?php echo ratib_proc_h((string) ($step['icon'] ?? 'fa-circle')); ?>"></i></span>
                        <div class="ratib-proc-process__copy">
                            <h3><?php echo ratib_proc_h((string) ($step['title'] ?? '')); ?></h3>
                            <p><?php echo ratib_proc_h((string) ($step['body'] ?? '')); ?></p>
                        </div>
                    </li>
                    <?php } ?>
                </ol>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_proc_render_security_governance')) {
    /** @param array<string, mixed> $section */
    function ratib_proc_render_security_governance(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'security-governance');
        ?>
        <section class="ratib-about-section ratib-proc-section ratib-proc-section--security" id="<?php echo ratib_proc_h($id); ?>" aria-labelledby="proc-security-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-security-title" class="ratib-about-title"><?php echo ratib_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <p class="ratib-proc-summary ratib-about-glass" data-ratib-reveal><?php echo ratib_proc_h((string) ($section['summary'] ?? '')); ?></p>
                <div class="ratib-proc-ref-links">
                    <?php foreach ($section['links'] ?? [] as $i => $link) { ?>
                    <a href="<?php echo ratib_proc_h((string) ($link['href'] ?? '#')); ?>" class="ratib-proc-ref-link ratib-about-glass" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 50); ?>">
                        <span class="ratib-proc-ref-link__icon" aria-hidden="true"><i class="fas <?php echo ratib_proc_h((string) ($link['icon'] ?? 'fa-arrow-right')); ?>"></i></span>
                        <span class="ratib-proc-ref-link__copy">
                            <strong><?php echo ratib_proc_h((string) ($link['title'] ?? '')); ?></strong>
                            <span><?php echo ratib_proc_h((string) ($link['desc'] ?? '')); ?></span>
                        </span>
                        <span class="ratib-proc-ref-link__arrow" aria-hidden="true">→</span>
                    </a>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_proc_render_data_boundaries')) {
    /** @param array<string, mixed> $section */
    function ratib_proc_render_data_boundaries(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'data-tenant-boundaries');
        ?>
        <section class="ratib-about-section ratib-proc-section" id="<?php echo ratib_proc_h($id); ?>" aria-labelledby="proc-boundaries-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-boundaries-title" class="ratib-about-title"><?php echo ratib_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-proc-boundary-diagram" data-ratib-reveal aria-hidden="true">
                    <div class="ratib-proc-boundary-diagram__plane">Control plane</div>
                    <div class="ratib-proc-boundary-diagram__line"></div>
                    <div class="ratib-proc-boundary-diagram__tenants">
                        <span>Tenant datastore</span>
                        <span>Tenant datastore</span>
                        <span>Tenant datastore</span>
                    </div>
                </div>
                <div class="ratib-about-feature-grid ratib-about-feature-grid--2">
                    <?php foreach ($section['points'] ?? [] as $i => $pt) { ?>
                    <article class="ratib-about-glass ratib-about-feature" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 40); ?>">
                        <h3><?php echo ratib_proc_h((string) ($pt['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_proc_h((string) ($pt['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_proc_render_legal_notes')) {
    /** @param array<string, mixed> $section */
    function ratib_proc_render_legal_notes(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'legal-operational-notes');
        ?>
        <section class="ratib-about-section ratib-proc-section ratib-proc-section--legal" id="<?php echo ratib_proc_h($id); ?>" aria-labelledby="proc-legal-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-legal-title" class="ratib-about-title"><?php echo ratib_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <ul class="ratib-proc-legal-list">
                    <?php foreach ($section['items'] ?? [] as $i => $item) { ?>
                    <li class="ratib-proc-legal-list__item ratib-about-glass" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 40); ?>">
                        <h3><?php echo ratib_proc_h((string) ($item['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_proc_h((string) ($item['body'] ?? '')); ?></p>
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_proc_render_requests')) {
    /** @param array<string, mixed> $section */
    function ratib_proc_render_requests(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'procurement-requests');
        ?>
        <section class="ratib-about-section ratib-proc-section ratib-proc-section--requests" id="<?php echo ratib_proc_h($id); ?>" aria-labelledby="proc-requests-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_proc_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-requests-title" class="ratib-about-title"><?php echo ratib_proc_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_proc_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-proc-cta-grid">
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
                    <article class="ratib-about-glass ratib-proc-cta-card" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 55); ?>">
                        <span class="ratib-proc-cta-card__icon" aria-hidden="true"><i class="fas <?php echo ratib_proc_h((string) ($cta['icon'] ?? 'fa-envelope')); ?>"></i></span>
                        <h3><?php echo ratib_proc_h((string) ($cta['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_proc_h((string) ($cta['body'] ?? '')); ?></p>
                        <a href="<?php echo ratib_proc_h((string) ($cta['href'] ?? '#')); ?>" class="<?php echo ratib_proc_h($btnClass); ?>"><?php echo ratib_proc_h((string) ($cta['title'] ?? 'Contact')); ?></a>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_proc_render_contact')) {
    /** @param array<string, mixed> $contact */
    function ratib_proc_render_contact(array $contact): void
    {
        if ($contact === []) {
            return;
        }
        $id = (string) ($contact['id'] ?? 'contact-escalation');
        $email = (string) ($contact['email'] ?? 'info@out.ratib.sa');
        ?>
        <section class="ratib-about-section ratib-proc-section ratib-proc-section--contact" id="<?php echo ratib_proc_h($id); ?>" aria-labelledby="proc-contact-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_proc_h((string) ($contact['eyebrow'] ?? '')); ?></p>
                    <h2 id="proc-contact-title" class="ratib-about-title"><?php echo ratib_proc_h((string) ($contact['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_proc_h((string) ($contact['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-proc-contact-card ratib-about-glass" data-ratib-reveal>
                    <div class="ratib-proc-contact-card__primary">
                        <a href="mailto:<?php echo ratib_proc_h($email); ?>" class="ratib-proc-contact-email"><?php echo ratib_proc_h($email); ?></a>
                        <p class="ratib-mono ratib-proc-contact-card__hq"><?php echo ratib_proc_h((string) ($contact['hq'] ?? '')); ?></p>
                    </div>
                    <ul class="ratib-proc-contact-card__channels">
                        <li>
                            <a href="mailto:<?php echo ratib_proc_h($email); ?>"><i class="fas fa-envelope" aria-hidden="true"></i> Email escalation</a>
                        </li>
                        <?php if (!empty($contact['phone_href'])) { ?>
                        <li>
                            <a href="<?php echo ratib_proc_h((string) $contact['phone_href']); ?>" dir="ltr"><i class="fas fa-phone" aria-hidden="true"></i> <?php echo ratib_proc_h((string) ($contact['phone'] ?? '')); ?></a>
                        </li>
                        <?php } ?>
                        <?php if (!empty($contact['whatsapp'])) { ?>
                        <li>
                            <a href="<?php echo ratib_proc_h((string) $contact['whatsapp']); ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp (business)</a>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        </section>
        <?php
    }
}
