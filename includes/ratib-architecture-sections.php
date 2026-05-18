<?php
/**
 * Renders enterprise architecture page sections.
 */
declare(strict_types=1);

if (!function_exists('ratib_arch_h')) {
    function ratib_arch_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ratib_architecture_render_sections')) {
    function ratib_architecture_render_sections(array $arch, string $baseUrl): void
    {
        ratib_arch_render_hero($arch['hero'] ?? []);
        ratib_arch_render_overview($arch['overview'] ?? []);
        ratib_arch_render_layers($arch['layers'] ?? []);
        ratib_arch_render_isolation($arch['isolation'] ?? []);
        ratib_arch_render_events($arch['events'] ?? []);
        ratib_arch_render_capability_section($arch['telemetry'] ?? [], 'ratib-arch-section--telemetry');
        ratib_arch_render_capability_section($arch['finance'] ?? [], 'ratib-arch-section--finance', 'ratib-about-feature-grid--2');
        ratib_arch_render_capability_section($arch['governance'] ?? [], 'ratib-arch-section--governance');
        ratib_arch_render_deployment($arch['deployment'] ?? []);
        ratib_arch_render_briefing($arch['briefing'] ?? []);
    }
}

if (!function_exists('ratib_arch_render_hero')) {
    /** @param array<string, mixed> $hero */
    function ratib_arch_render_hero(array $hero): void
    {
        ?>
        <section class="ratib-arch-hero" id="top" aria-labelledby="arch-hero-title">
            <div class="ratib-about-container">
                <div class="ratib-arch-hero__grid">
                    <div class="ratib-arch-hero__copy" data-ratib-reveal>
                        <p class="ratib-about-page-label"><?php echo ratib_arch_h((string) ($hero['eyebrow'] ?? '')); ?></p>
                        <h1 id="arch-hero-title" class="ratib-arch-hero__title"><?php echo ratib_arch_h((string) ($hero['title'] ?? '')); ?></h1>
                        <p class="ratib-arch-hero__lead"><?php echo ratib_arch_h((string) ($hero['lead'] ?? '')); ?></p>
                    </div>
                    <aside class="ratib-arch-hero__stack" data-ratib-reveal data-ratib-delay="60" aria-label="Platform layer stack preview">
                        <p class="ratib-arch-hero__stack-kicker ratib-mono"><?php echo ratib_arch_h((string) ($hero['diagram_label'] ?? 'stack')); ?></p>
                        <ol class="ratib-arch-stack-preview" reversed>
                            <?php foreach ($hero['stack_preview'] ?? [] as $layer) { ?>
                            <li><span class="ratib-mono"><?php echo ratib_arch_h((string) $layer); ?></span></li>
                            <?php } ?>
                        </ol>
                    </aside>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_arch_render_overview')) {
    /** @param array<string, mixed> $section */
    function ratib_arch_render_overview(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'architecture-overview');
        ?>
        <section class="ratib-about-section ratib-arch-section ratib-arch-section--overview" id="<?php echo ratib_arch_h($id); ?>" aria-labelledby="arch-overview-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-overview-title" class="ratib-about-title"><?php echo ratib_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-arch-overview-grid">
                    <?php foreach ($section['points'] ?? [] as $i => $pt) { ?>
                    <article class="ratib-about-glass ratib-arch-overview-card" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 45); ?>">
                        <h3 class="ratib-mono"><?php echo ratib_arch_h((string) ($pt['label'] ?? '')); ?></h3>
                        <p><?php echo ratib_arch_h((string) ($pt['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_arch_render_layers')) {
    /** @param array<string, mixed> $section */
    function ratib_arch_render_layers(array $section): void
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
        <section class="ratib-about-section ratib-arch-section ratib-arch-section--layers" id="<?php echo ratib_arch_h($id); ?>" aria-labelledby="arch-layers-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-layers-title" class="ratib-about-title"><?php echo ratib_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-arch-layer-stack">
                    <?php foreach ($items as $i => $layer) { ?>
                    <article class="ratib-arch-layer-card ratib-about-glass" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 35); ?>" id="layer-<?php echo ratib_arch_h((string) ($layer['key'] ?? 'layer')); ?>">
                        <div class="ratib-arch-layer-card__rail">
                            <span class="ratib-arch-layer-card__order ratib-mono">L<?php echo ratib_arch_h((string) ($layer['order'] ?? '')); ?></span>
                            <span class="ratib-arch-layer-card__icon" aria-hidden="true"><i class="fas <?php echo ratib_arch_h((string) ($layer['icon'] ?? 'fa-layer-group')); ?>"></i></span>
                        </div>
                        <div class="ratib-arch-layer-card__body">
                            <h3><?php echo ratib_arch_h((string) ($layer['title'] ?? '')); ?></h3>
                            <dl class="ratib-arch-layer-dl">
                                <div>
                                    <dt class="ratib-mono">Responsibilities</dt>
                                    <dd><?php echo ratib_arch_h((string) ($layer['responsibilities'] ?? '')); ?></dd>
                                </div>
                                <div>
                                    <dt class="ratib-mono">Operational role</dt>
                                    <dd><?php echo ratib_arch_h((string) ($layer['operational_role'] ?? '')); ?></dd>
                                </div>
                                <div>
                                    <dt class="ratib-mono">Boundaries</dt>
                                    <dd><?php echo ratib_arch_h((string) ($layer['boundaries'] ?? '')); ?></dd>
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

if (!function_exists('ratib_arch_render_isolation')) {
    /** @param array<string, mixed> $section */
    function ratib_arch_render_isolation(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'multi-tenant-isolation');
        ?>
        <section class="ratib-about-section ratib-arch-section ratib-arch-section--isolation" id="<?php echo ratib_arch_h($id); ?>" aria-labelledby="arch-isolation-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-isolation-title" class="ratib-about-title"><?php echo ratib_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-arch-topology ratib-arch-topology--isolation">
                    <div class="ratib-arch-topology__diagram" data-ratib-reveal aria-hidden="true">
                        <div class="ratib-arch-topology__core">Orchestration core</div>
                        <div class="ratib-arch-topology__spokes">
                            <span>Tenant A DB</span>
                            <span>Tenant B DB</span>
                            <span>Tenant C DB</span>
                        </div>
                        <p class="ratib-arch-topology__caption ratib-mono">shared core · isolated datastores</p>
                    </div>
                    <div class="ratib-arch-pillar-grid">
                        <?php foreach ($section['pillars'] ?? [] as $i => $p) { ?>
                        <article class="ratib-about-glass ratib-arch-pillar" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 50); ?>">
                            <span class="ratib-arch-pillar__icon" aria-hidden="true"><i class="fas <?php echo ratib_arch_h((string) ($p['icon'] ?? 'fa-circle')); ?>"></i></span>
                            <h3><?php echo ratib_arch_h((string) ($p['title'] ?? '')); ?></h3>
                            <p><?php echo ratib_arch_h((string) ($p['body'] ?? '')); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_arch_render_events')) {
    /** @param array<string, mixed> $section */
    function ratib_arch_render_events(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'event-driven');
        ?>
        <section class="ratib-about-section ratib-arch-section ratib-arch-section--events" id="<?php echo ratib_arch_h($id); ?>" aria-labelledby="arch-events-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-events-title" class="ratib-about-title"><?php echo ratib_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-arch-flow" data-ratib-reveal aria-label="Event processing flow">
                    <?php foreach ($section['flow'] ?? [] as $i => $step) {
                        if ($i > 0) {
                            echo '<span class="ratib-arch-flow__arrow" aria-hidden="true">→</span>';
                        }
                        ?>
                    <div class="ratib-arch-flow__step">
                        <span class="ratib-arch-flow__step-label ratib-mono"><?php echo ratib_arch_h((string) ($step['step'] ?? '')); ?></span>
                        <span class="ratib-arch-flow__step-detail"><?php echo ratib_arch_h((string) ($step['detail'] ?? '')); ?></span>
                    </div>
                    <?php } ?>
                </div>
                <div class="ratib-about-feature-grid ratib-about-feature-grid--3 ratib-arch-cap-grid">
                    <?php foreach ($section['capabilities'] ?? [] as $i => $item) { ?>
                    <article class="ratib-about-glass ratib-about-feature" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 40); ?>">
                        <span class="ratib-about-feature__icon ratib-about-feature__icon--cyan"><i class="fas <?php echo ratib_arch_h((string) ($item['icon'] ?? 'fa-circle')); ?>" aria-hidden="true"></i></span>
                        <h3><?php echo ratib_arch_h((string) ($item['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_arch_h((string) ($item['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_arch_render_capability_section')) {
    /**
     * @param array<string, mixed> $section
     */
    function ratib_arch_render_capability_section(array $section, string $extraClass = '', string $gridClass = 'ratib-about-feature-grid--3'): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? '');
        $titleId = $id !== '' ? 'arch-' . str_replace('-', '_', $id) . '-title' : 'arch-cap-title';
        ?>
        <section class="ratib-about-section ratib-arch-section <?php echo ratib_arch_h($extraClass); ?>"<?php echo $id !== '' ? ' id="' . ratib_arch_h($id) . '"' : ''; ?> aria-labelledby="<?php echo ratib_arch_h($titleId); ?>">
            <div class="ratib-about-container">
                <header class="ratib-about-head" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="<?php echo ratib_arch_h($titleId); ?>" class="ratib-about-title"><?php echo ratib_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-about-feature-grid <?php echo ratib_arch_h($gridClass); ?>">
                    <?php foreach ($section['items'] ?? [] as $i => $item) { ?>
                    <article class="ratib-about-glass ratib-about-feature" data-ratib-reveal data-ratib-delay="<?php echo (int) ($i * 40); ?>">
                        <span class="ratib-about-feature__icon ratib-about-feature__icon--cyan"><i class="fas <?php echo ratib_arch_h((string) ($item['icon'] ?? 'fa-circle')); ?>" aria-hidden="true"></i></span>
                        <h3><?php echo ratib_arch_h((string) ($item['title'] ?? '')); ?></h3>
                        <p><?php echo ratib_arch_h((string) ($item['body'] ?? '')); ?></p>
                    </article>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_arch_render_deployment')) {
    /** @param array<string, mixed> $section */
    function ratib_arch_render_deployment(array $section): void
    {
        if ($section === []) {
            return;
        }
        $id = (string) ($section['id'] ?? 'deployment-model');
        ?>
        <section class="ratib-about-section ratib-arch-section ratib-arch-section--deployment" id="<?php echo ratib_arch_h($id); ?>" aria-labelledby="arch-deployment-title">
            <div class="ratib-about-container">
                <header class="ratib-about-head ratib-about-head--center" data-ratib-reveal>
                    <p class="ratib-about-eyebrow"><?php echo ratib_arch_h((string) ($section['eyebrow'] ?? '')); ?></p>
                    <h2 id="arch-deployment-title" class="ratib-about-title"><?php echo ratib_arch_h((string) ($section['title'] ?? '')); ?></h2>
                    <p class="ratib-about-sub"><?php echo ratib_arch_h((string) ($section['sub'] ?? '')); ?></p>
                </header>
                <div class="ratib-arch-deploy-topology" data-ratib-reveal>
                    <div class="ratib-arch-deploy-row ratib-arch-deploy-row--edge">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') !== 'edge') {
                                continue;
                            }
                            ratib_arch_render_deploy_node($node);
                        } ?>
                    </div>
                    <div class="ratib-arch-deploy-connector" aria-hidden="true"></div>
                    <div class="ratib-arch-deploy-row ratib-arch-deploy-row--clients">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') !== 'client') {
                                continue;
                            }
                            ratib_arch_render_deploy_node($node);
                        } ?>
                    </div>
                    <div class="ratib-arch-deploy-connector" aria-hidden="true"></div>
                    <div class="ratib-arch-deploy-row ratib-arch-deploy-row--gateway">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') === 'gateway') {
                                ratib_arch_render_deploy_node($node);
                            }
                        } ?>
                    </div>
                    <div class="ratib-arch-deploy-connector ratib-arch-deploy-connector--thick" aria-hidden="true"></div>
                    <div class="ratib-arch-deploy-row ratib-arch-deploy-row--core">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') === 'core') {
                                ratib_arch_render_deploy_node($node, true);
                            }
                        } ?>
                    </div>
                    <div class="ratib-arch-deploy-connector" aria-hidden="true"></div>
                    <div class="ratib-arch-deploy-row ratib-arch-deploy-row--data">
                        <?php foreach ($section['nodes'] ?? [] as $node) {
                            if (($node['tier'] ?? '') === 'data') {
                                ratib_arch_render_deploy_node($node);
                            }
                        } ?>
                    </div>
                </div>
                <div class="ratib-arch-deploy-legend ratib-mono" data-ratib-reveal>
                    <?php foreach ($section['nodes'] ?? [] as $node) { ?>
                    <div class="ratib-arch-deploy-legend__item">
                        <strong><?php echo ratib_arch_h((string) ($node['label'] ?? '')); ?></strong>
                        <span><?php echo ratib_arch_h((string) ($node['body'] ?? '')); ?></span>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_arch_render_deploy_node')) {
    /** @param array<string, mixed> $node */
    function ratib_arch_render_deploy_node(array $node, bool $emphasis = false): void
    {
        $class = 'ratib-arch-deploy-node';
        if ($emphasis) {
            $class .= ' ratib-arch-deploy-node--core';
        }
        $class .= ' ratib-arch-deploy-node--' . preg_replace('/[^a-z0-9_-]/', '', (string) ($node['tier'] ?? 'node'));
        ?>
        <div class="<?php echo ratib_arch_h($class); ?>" id="deploy-<?php echo ratib_arch_h((string) ($node['id'] ?? 'node')); ?>">
            <span class="ratib-arch-deploy-node__label"><?php echo ratib_arch_h((string) ($node['label'] ?? '')); ?></span>
        </div>
        <?php
    }
}

if (!function_exists('ratib_arch_render_briefing')) {
    /** @param array<string, mixed> $briefing */
    function ratib_arch_render_briefing(array $briefing): void
    {
        if ($briefing === []) {
            return;
        }
        ?>
        <section class="ratib-about-section ratib-arch-section ratib-arch-section--briefing" aria-labelledby="arch-briefing-title">
            <div class="ratib-about-container">
                <div class="ratib-about-glass ratib-arch-briefing" data-ratib-reveal>
                    <div class="ratib-arch-briefing__copy">
                        <h2 id="arch-briefing-title"><?php echo ratib_arch_h((string) ($briefing['title'] ?? '')); ?></h2>
                        <p><?php echo ratib_arch_h((string) ($briefing['body'] ?? '')); ?></p>
                    </div>
                    <div class="ratib-arch-briefing__actions">
                        <a href="<?php echo ratib_arch_h((string) ($briefing['href'] ?? '#')); ?>" class="ratib-about-btn ratib-about-btn--primary">Request architecture review</a>
                        <?php if (!empty($briefing['secondary_href'])) { ?>
                        <a href="<?php echo ratib_arch_h((string) $briefing['secondary_href']); ?>" class="ratib-about-btn ratib-about-btn--outline"><?php echo ratib_arch_h((string) ($briefing['secondary_label'] ?? 'Related')); ?></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}
