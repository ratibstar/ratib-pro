<?php
/**
 * Renders enterprise architecture page sections.
 */
declare(strict_types=1);

if (!function_exists('rateb_arch_h')) {
    function rateb_arch_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rateb_architecture_render_sections')) {
    function rateb_architecture_render_sections(array $arch, string $baseUrl): void
    {
        rateb_arch_render_hero($arch['hero'] ?? []);
        rateb_arch_render_overview($arch['overview'] ?? []);
        rateb_arch_render_layers($arch['layers'] ?? []);
        rateb_arch_render_isolation($arch['isolation'] ?? []);
        rateb_arch_render_events($arch['events'] ?? []);
        rateb_arch_render_capability_section($arch['telemetry'] ?? [], 'rateb-arch-section--telemetry');
        rateb_arch_render_capability_section($arch['finance'] ?? [], 'rateb-arch-section--finance', 'rateb-about-feature-grid--2');
        rateb_arch_render_capability_section($arch['governance'] ?? [], 'rateb-arch-section--governance');
        rateb_arch_render_deployment($arch['deployment'] ?? []);
        rateb_arch_render_briefing($arch['briefing'] ?? []);
    }
}

if (!function_exists('rateb_arch_render_hero')) {
    /** @param array<string, mixed> $hero */
    function rateb_arch_render_hero(array $hero): void
    {
        ?>
        <section class="rateb-arch-hero" id="top" aria-labelledby="arch-hero-title">
            <div class="rateb-about-container">
                <div class="rateb-arch-hero__grid">
                    <div class="rateb-arch-hero__copy" data-rateb-reveal>
                        <p class="rateb-about-page-label"><?php echo rateb_arch_h((string) ($hero['eyebrow'] ?? '')); ?></p>
                        <h1 id="arch-hero-title" class="rateb-arch-hero__title"><?php echo rateb_arch_h((string) ($hero['title'] ?? '')); ?></h1>
                        <p class="rateb-arch-hero__lead"><?php echo rateb_arch_h((string) ($hero['lead'] ?? '')); ?></p>
                    </div>
                    <aside class="rateb-arch-hero__stack" data-rateb-reveal data-rateb-delay="60" aria-label="Platform layer stack preview">
                        <p class="rateb-arch-hero__stack-kicker rateb-mono"><?php echo rateb_arch_h((string) ($hero['diagram_label'] ?? 'stack')); ?></p>
                        <ol class="rateb-arch-stack-preview" reversed>
                            <?php foreach ($hero['stack_preview'] ?? [] as $layer) { ?>
                            <li><span class="rateb-mono"><?php echo rateb_arch_h((string) $layer); ?></span></li>
                            <?php } ?>
                        </ol>
                    </aside>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_arch_render_overview')) {
    /** @param array<string, mixed> $section */
    function rateb_arch_render_overview(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'architecture-overview');
        ?>
        <section class="rateb-about-section rateb-arch-section rateb-arch-section--overview" id="<?php echo rateb_arch_h($id); ?>" aria-labelledby="arch-overview-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-overview-title" class="rateb-about-title"><?php echo rateb_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-arch-overview-grid">
                    <?php foreach ($section['points'] ?? [] as $i => $pt) { ?>
                    <article class="rateb-about-glass rateb-arch-overview-card" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 45); ?>">
                        <h3 class="rateb-mono"><?php echo rateb_arch_h((string) ($pt['label'] ?? '')); ?></h3>
                        <p><?php echo rateb_arch_h((string) ($pt['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_arch_render_layers')) {
    /** @param array<string, mixed> $section */
    function rateb_arch_render_layers(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'layered-control-plane');
        $items = $section['items'] ?? [];
        usort($items, static function ($a, $b) {
            return ((int) ($b['order'] ?? 0)) <=> ((int) ($a['order'] ?? 0));
        });
        ?>
        <section class="rateb-about-section rateb-arch-section rateb-arch-section--layers" id="<?php echo rateb_arch_h($id); ?>" aria-labelledby="arch-layers-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-layers-title" class="rateb-about-title"><?php echo rateb_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-arch-layer-stack">
                    <?php foreach ($items as $i => $layer) { ?>
                    <article class="rateb-arch-layer-card rateb-about-glass" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 35); ?>" id="layer-<?php echo rateb_arch_h((string) ($layer['key'] ?? 'layer')); ?>">
                        <div class="rateb-arch-layer-card__rail">
                            <span class="rateb-arch-layer-card__order rateb-mono">L<?php echo rateb_arch_h((string) ($layer['order'] ?? '')); ?></span>
                            <span class="rateb-arch-layer-card__icon" aria-hidden="true"><i class="fas <?php echo rateb_arch_h((string) ($layer['icon'] ?? 'fa-layer-group')); ?>"></i></span>
                        </div>
                        <div class="rateb-arch-layer-card__body">
                            <h3><?php echo rateb_arch_h((string) ($layer['title'] ?? '')); ?></h3>
                            <dl class="rateb-arch-layer-dl">
                                <div>
                                    <dt class="rateb-mono">Responsibilities</dt>
                                    <dd><?php echo rateb_arch_h((string) ($layer['responsibilities'] ?? '')); ?></dd>
                                </div>
                                <div>
                                    <dt class="rateb-mono">Operational role</dt>
                                    <dd><?php echo rateb_arch_h((string) ($layer['operational_role'] ?? '')); ?></dd>
                                </div>
                                <div>
                                    <dt class="rateb-mono">Boundaries</dt>
                                    <dd><?php echo rateb_arch_h((string) ($layer['boundaries'] ?? '')); ?></dd>
                                </div>
                            </dl>
                        </div>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_arch_render_isolation')) {
    /** @param array<string, mixed> $section */
    function rateb_arch_render_isolation(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'multi-tenant-isolation');
        ?>
        <section class="rateb-about-section rateb-arch-section rateb-arch-section--isolation" id="<?php echo rateb_arch_h($id); ?>" aria-labelledby="arch-isolation-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head rateb-about-head--center" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-isolation-title" class="rateb-about-title"><?php echo rateb_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-arch-topology rateb-arch-topology--isolation">
                    <div class="rateb-arch-topology__diagram" data-rateb-reveal aria-hidden="true">
                        <div class="rateb-arch-topology__core">Orchestration core</div>
                        <div class="rateb-arch-topology__spokes">
                            <span>Tenant A DB</span>
                            <span>Tenant B DB</span>
                            <span>Tenant C DB</span>
                        </div>
                        <p class="rateb-arch-topology__caption rateb-mono">shared core · isolated datastores</p>
                    </div>
                    <div class="rateb-arch-pillar-grid">
                        <?php foreach ($section['pillars'] ?? [] as $i => $p) { ?>
                        <article class="rateb-about-glass rateb-arch-pillar" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 50); ?>">
                            <span class="rateb-arch-pillar__icon" aria-hidden="true"><i class="fas <?php echo rateb_arch_h((string) ($p['icon'] ?? 'fa-circle')); ?>"></i></span>
                            <h3><?php echo rateb_arch_h((string) ($p['title'] ?? '')); ?></h3>
                            <p><?php echo rateb_arch_h((string) ($p['body'] ?? '')); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_arch_render_events')) {
    /** @param array<string, mixed> $section */
    function rateb_arch_render_events(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'event-driven');
        ?>
        <section class="rateb-about-section rateb-arch-section rateb-arch-section--events" id="<?php echo rateb_arch_h($id); ?>" aria-labelledby="arch-events-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-events-title" class="rateb-about-title"><?php echo rateb_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-arch-flow" data-rateb-reveal aria-label="Event processing flow">
                    <?php foreach ($section['flow'] ?? [] as $i => $step) {
                        if ($i > 0) {
                            echo '<span class="rateb-arch-flow__arrow" aria-hidden="true">→</span>';
                        }
                        ?>
                    <div class="rateb-arch-flow__step">
                        <span class="rateb-arch-flow__step-label rateb-mono"><?php echo rateb_arch_h((string) ($step['step'] ?? '')); ?></span>
                        <span class="rateb-arch-flow__step-detail"><?php echo rateb_arch_h((string) ($step['detail'] ?? '')); ?></span>
                    </div>
                    <?php } ?>
                </div>
                <div class="rateb-about-feature-grid rateb-about-feature-grid--3 rateb-arch-cap-grid">
                    <?php foreach ($section['capabilities'] ?? [] as $i => $item) { ?>
                    <article class="rateb-about-glass rateb-about-feature" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 40); ?>">
                        <span class="rateb-about-feature__icon rateb-about-feature__icon--cyan"><i class="fas <?php echo rateb_arch_h((string) ($item['icon'] ?? 'fa-circle')); ?>" aria-hidden="true"></i></span>
                        <h3><?php echo rateb_arch_h((string) ($item['title'] ?? '')); ?></h3>
                        <p><?php echo rateb_arch_h((string) ($item['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_arch_render_capability_section')) {
    /**
     * @param array<string, mixed> $section
     */
    function rateb_arch_render_capability_section(array $section, string $extraClass = '', string $gridClass = 'rateb-about-feature-grid--3'): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? '');
        $titleId = $id !== '' ? 'arch-' . str_replace('-', '_', $id) . '-title' : 'arch-cap-title';
        ?>
        <section class="rateb-about-section rateb-arch-section <?php echo rateb_arch_h($extraClass); ?>"<?php echo $id !== '' ? ' id="' . rateb_arch_h($id) . '"' : ''; ?> aria-labelledby="<?php echo rateb_arch_h($titleId); ?>">
            <div class="rateb-about-container">
                <header class="rateb-about-head" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="<?php echo rateb_arch_h($titleId); ?>" class="rateb-about-title"><?php echo rateb_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-about-feature-grid <?php echo rateb_arch_h($gridClass); ?>">
                    <?php foreach ($section['items'] ?? [] as $i => $item) { ?>
                    <article class="rateb-about-glass rateb-about-feature" data-rateb-reveal data-rateb-delay="<?php echo (int) ($i * 40); ?>">
                        <span class="rateb-about-feature__icon rateb-about-feature__icon--cyan"><i class="fas <?php echo rateb_arch_h((string) ($item['icon'] ?? 'fa-circle')); ?>" aria-hidden="true"></i></span>
                        <h3><?php echo rateb_arch_h((string) ($item['title'] ?? '')); ?></h3>
                        <p><?php echo rateb_arch_h((string) ($item['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_arch_render_deployment')) {
    /** @param array<string, mixed> $section */
    function rateb_arch_render_deployment(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'deployment-model');
        ?>
        <section class="rateb-about-section rateb-arch-section rateb-arch-section--deployment" id="<?php echo rateb_arch_h($id); ?>" aria-labelledby="arch-deployment-title">
            <div class="rateb-about-container">
                <header class="rateb-about-head rateb-about-head--center" data-rateb-reveal>
                    <p class="rateb-about-eyebrow"><?php echo rateb_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-deployment-title" class="rateb-about-title"><?php echo rateb_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="rateb-about-sub"><?php echo rateb_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="rateb-arch-deploy-topology" data-rateb-reveal>
                    <div class="rateb-arch-deploy-row rateb-arch-deploy-row--edge">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') !== 'edge') {
                                continue;
                            }
                            rateb_arch_render_deploy_node($node);
                        } ?>
                    </div>
                    <div class="rateb-arch-deploy-connector" aria-hidden="true"></div>
                    <div class="rateb-arch-deploy-row rateb-arch-deploy-row--clients">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') !== 'client') {
                                continue;
                            }
                            rateb_arch_render_deploy_node($node);
                        } ?>
                    </div>
                    <div class="rateb-arch-deploy-connector" aria-hidden="true"></div>
                    <div class="rateb-arch-deploy-row rateb-arch-deploy-row--gateway">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') === 'gateway') {
                                rateb_arch_render_deploy_node($node);
                            }
                        } ?>
                    </div>
                    <div class="rateb-arch-deploy-connector rateb-arch-deploy-connector--thick" aria-hidden="true"></div>
                    <div class="rateb-arch-deploy-row rateb-arch-deploy-row--core">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') === 'core') {
                                rateb_arch_render_deploy_node($node, true);
                            }
                        } ?>
                    </div>
                    <div class="rateb-arch-deploy-connector" aria-hidden="true"></div>
                    <div class="rateb-arch-deploy-row rateb-arch-deploy-row--data">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') === 'data') {
                                rateb_arch_render_deploy_node($node);
                            }
                        } ?>
                    </div>
                </div>
                <div class="rateb-arch-deploy-legend rateb-mono" data-rateb-reveal>
                    <?php foreach ($section['nodes'] ?? [] as $node) { ?>
                    <div class="rateb-arch-deploy-legend__item">
                        <strong><?php echo rateb_arch_h((string) ($node['label'] ?? '')); ?></strong>
                        <span><?php echo rateb_arch_h((string) ($node['body'] ?? '')); ?></span>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_arch_render_deploy_node')) {
    /** @param array<string, mixed> $node */
    function rateb_arch_render_deploy_node(array $node, bool $emphasis = false): void
    {
        $class = 'rateb-arch-deploy-node';
        if ($emphasis) {
            $class .= ' rateb-arch-deploy-node--core';
        }
        $class .= ' rateb-arch-deploy-node--' . preg_replace('/[^a-z0-9_-]/', '', (string) ($node['tier'] ?? 'node'));
        ?>
        <div class="<?php echo rateb_arch_h($class); ?>" id="deploy-<?php echo rateb_arch_h((string) ($node['id'] ?? 'node')); ?>">
            <span class="rateb-arch-deploy-node__label"><?php echo rateb_arch_h((string) ($node['label'] ?? '')); ?></span>
        </div>
        <?php
    }
}

if (!function_exists('rateb_arch_render_briefing')) {
    /** @param array<string, mixed> $briefing */
    function rateb_arch_render_briefing(array $briefing): void
    {
        if ($briefing === []) {
            return;
        }
        ?>
        <section class="rateb-about-section rateb-arch-section rateb-arch-section--briefing" aria-labelledby="arch-briefing-title">
            <div class="rateb-about-container">
                <div class="rateb-about-glass rateb-arch-briefing" data-rateb-reveal>
                    <div class="rateb-arch-briefing__copy">
                        <h2 id="arch-briefing-title"><?php echo rateb_arch_h((string) ($briefing['title'] ?? '')); ?></h2>
                        <p><?php echo rateb_arch_h((string) ($briefing['body'] ?? '')); ?></p>
                    </div>
                    <div class="rateb-arch-briefing__actions">
                        <a href="<?php echo rateb_arch_h((string) ($briefing['href'] ?? '#')); ?>" class="rateb-about-btn rateb-about-btn--primary">Request architecture review</a>
                        <?php if (!empty($briefing['secondary_href'])) { ?>
                        <a href="<?php echo rateb_arch_h((string) $briefing['secondary_href']); ?>" class="rateb-about-btn rateb-about-btn--outline"><?php echo rateb_arch_h((string) ($briefing['secondary_label'] ?? 'Related')); ?></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}
