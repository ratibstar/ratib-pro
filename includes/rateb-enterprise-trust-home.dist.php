<?php
/**
 * Enterprise trust layer — home page visual block (topology, layers, flows).
 *
 * @param array<string, mixed> $ratebHome CMS home strings
 * @param string $baseUrl Site root without trailing slash
 */
declare(strict_types=1);

if (!function_exists('rateb_ent_h')) {
    function rateb_ent_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rateb_enterprise_mailto')) {
    function rateb_enterprise_mailto(string $subject): string
    {
        return 'mailto:info@rateb.sa?subject=' . rawurlencode($subject);
    }
}

if (!function_exists('rateb_enterprise_trust_render_home')) {
    function rateb_enterprise_trust_render_home(array $ratebHome, string $baseUrl): void
    {
        $root = rtrim($baseUrl, '/');
        $layers = [
            ['L7', 'Experience', 'Consoles & partner surfaces'],
            ['L6', 'Orchestration', 'Stage graphs & workflow engine'],
            ['L5', 'Telemetry', 'Field signals & escalation'],
            ['L4', 'Business modules', 'Recruitment · deployment · docs'],
            ['L3', 'Governance', 'RBAC · policy · audit'],
            ['L2', 'Commercial', 'Ledger · AR/AP · registration'],
            ['L1', 'Data', 'Platform config · tenant DBs'],
        ];
        ?>
        <section class="rateb-section rateb-ent-trust" id="enterprise-infrastructure" aria-labelledby="ent-trust-title">
            <div class="rateb-container">
                <header class="rateb-section__head rateb-ent-trust__head">
                    <p class="rateb-eyebrow rateb-eyebrow--enterprise"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.eyebrow'] ?? 'Infrastructure posture')); ?></p>
                    <h2 id="ent-trust-title" class="rateb-section__title"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.title'] ?? '')); ?></h2>
                    <p class="rateb-section__sub"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.sub'] ?? '')); ?></p>
                </header>

                <div class="rateb-ent-indicators" role="list" aria-label="Operational trust indicators">
                    <?php for ($bi = 1; $bi <= 6; $bi++) {
                        $badge = trim((string) ($ratebHome['home.ent.badge.' . $bi] ?? ''));
                        if ($badge === '') {
                            continue;
                        }
                        ?>
                    <span class="rateb-ent-indicator" role="listitem"><i class="fas fa-circle-check" aria-hidden="true"></i> <?php echo rateb_ent_h($badge); ?></span>
                    <?php } ?>
                </div>

                <div class="rateb-ent-grid rateb-ent-grid--primary">
                    <div class="rateb-ent-panel rateb-ent-panel--layers">
                        <h3 class="rateb-ent-panel__title rateb-mono-ops"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.layers.title'] ?? 'Platform layers')); ?></h3>
                        <p class="rateb-ent-panel__sub"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.layers.sub'] ?? '')); ?></p>
                        <ol class="rateb-ent-layer-stack" aria-label="Platform layers">
                            <?php foreach ($layers as $layer) { ?>
                            <li class="rateb-ent-layer">
                                <span class="rateb-ent-layer__order rateb-mono-ops"><?php echo rateb_ent_h($layer[0]); ?></span>
                                <span class="rateb-ent-layer__name"><?php echo rateb_ent_h($layer[1]); ?></span>
                                <span class="rateb-ent-layer__desc"><?php echo rateb_ent_h($layer[2]); ?></span>
                            </li>
                            <?php } ?>
                        </ol>
                        <a class="rateb-ent-panel__link" href="<?php echo rateb_ent_h($root . '/architecture/'); ?>"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.layers.link'] ?? 'Full architecture →')); ?></a>
                    </div>

                    <div class="rateb-ent-panel rateb-ent-panel--flow">
                        <h3 class="rateb-ent-panel__title rateb-mono-ops"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.flow.title'] ?? 'Orchestration flow')); ?></h3>
                        <p class="rateb-ent-panel__sub"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.flow.sub'] ?? '')); ?></p>
                        <div class="rateb-ent-orchestration" aria-label="Lifecycle orchestration sequence">
                            <?php
                            $flowSteps = [
                                ['emit', 'Event emit', 'Stage transition committed'],
                                ['policy', 'Policy gate', 'Governance evaluates'],
                                ['route', 'Fabric route', 'Webhooks · SSE · modules'],
                                ['persist', 'Tenant persist', 'Isolated datastore write'],
                            ];
                            foreach ($flowSteps as $fi => $fs) {
                                if ($fi > 0) {
                                    echo '<span class="rateb-ent-orchestration__arrow" aria-hidden="true">↓</span>';
                                }
                                ?>
                            <div class="rateb-ent-orchestration__step">
                                <span class="rateb-mono-ops"><?php echo rateb_ent_h($fs[0]); ?></span>
                                <strong><?php echo rateb_ent_h($fs[1]); ?></strong>
                                <span><?php echo rateb_ent_h($fs[2]); ?></span>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="rateb-ent-grid rateb-ent-grid--diagrams">
                    <div class="rateb-ent-panel rateb-ent-panel--topology">
                        <h3 class="rateb-ent-panel__title rateb-mono-ops"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.topology.title'] ?? 'Deployment topology')); ?></h3>
                        <div class="rateb-ent-topology" aria-hidden="true">
                            <div class="rateb-ent-topology__row"><span>Public edge</span></div>
                            <div class="rateb-ent-topology__v"></div>
                            <div class="rateb-ent-topology__row rateb-ent-topology__row--split">
                                <span>Agency workspace</span><span>Partner portals</span>
                            </div>
                            <div class="rateb-ent-topology__v"></div>
                            <div class="rateb-ent-topology__row"><span>API gateway</span></div>
                            <div class="rateb-ent-topology__v rateb-ent-topology__v--core"></div>
                            <div class="rateb-ent-topology__row rateb-ent-topology__row--core"><span>Orchestration core</span></div>
                            <div class="rateb-ent-topology__v"></div>
                            <div class="rateb-ent-topology__row rateb-ent-topology__row--data">
                                <span>Tenant DB</span><span>Tenant DB</span><span>Tenant DB</span>
                            </div>
                        </div>
                        <p class="rateb-ent-panel__note"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.topology.note'] ?? '')); ?></p>
                    </div>

                    <div class="rateb-ent-panel rateb-ent-panel--governance">
                        <h3 class="rateb-ent-panel__title rateb-mono-ops"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.gov.title'] ?? 'Governance plane')); ?></h3>
                        <ul class="rateb-ent-gov-list">
                            <?php for ($gi = 1; $gi <= 4; $gi++) {
                                $gt = (string) ($ratebHome['home.ent.gov.' . $gi . '.title'] ?? '');
                                $gb = (string) ($ratebHome['home.ent.gov.' . $gi . '.body'] ?? '');
                                if ($gt === '' && $gb === '') {
                                    continue;
                                }
                                ?>
                            <li>
                                <span class="rateb-ent-gov-list__k rateb-mono-ops"><?php echo rateb_ent_h($gt); ?></span>
                                <span class="rateb-ent-gov-list__v"><?php echo rateb_ent_h($gb); ?></span>
                            </li>
                            <?php } ?>
                        </ul>
                        <a class="rateb-ent-panel__link" href="<?php echo rateb_ent_h($root . '/security-compliance/'); ?>"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.gov.link'] ?? 'Security & compliance →')); ?></a>
                    </div>
                </div>

                <div class="rateb-ent-panel rateb-ent-panel--telemetry">
                    <h3 class="rateb-ent-panel__title rateb-mono-ops"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.telemetry.title'] ?? 'Field operations path')); ?></h3>
                    <div class="rateb-ent-telemetry-flow" aria-label="Telemetry processing path">
                        <?php
                        $tel = ['Field capture', 'Offline sync', 'Signal check', 'Geofence', 'Escalation queue'];
                        foreach ($tel as $ti => $tlabel) {
                            if ($ti > 0) {
                                echo '<span class="rateb-ent-telemetry-flow__sep" aria-hidden="true">→</span>';
                            }
                            echo '<span class="rateb-ent-telemetry-flow__node">' . rateb_ent_h($tlabel) . '</span>';
                        }
                        ?>
                    </div>
                    <p class="rateb-ent-panel__note"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.telemetry.note'] ?? '')); ?></p>
                </div>

                <div class="rateb-ent-audit-block">
                    <div class="rateb-ent-audit-block__copy">
                        <h3 class="rateb-mono-ops"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.audit.title'] ?? 'Audit-oriented operations')); ?></h3>
                        <p><?php echo rateb_ent_h((string) ($ratebHome['home.ent.audit.body'] ?? '')); ?></p>
                    </div>
                    <ul class="rateb-ent-audit-block__list rateb-mono-ops">
                        <li>correlation_id · stage_commit</li>
                        <li>policy_version · actor_attribution</li>
                        <li>replay_safe · idempotent_writes</li>
                    </ul>
                </div>

                <div class="rateb-ent-cta-bar" aria-label="Enterprise contact actions">
                    <a href="<?php echo rateb_ent_h(rateb_enterprise_mailto('RATEB — Request Enterprise Demo')); ?>" class="rateb-btn rateb-btn--primary"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.cta.demo'] ?? 'Request Enterprise Demo')); ?></a>
                    <a href="<?php echo rateb_ent_h($root . '/architecture/'); ?>" class="rateb-btn rateb-btn--outline"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.cta.architecture'] ?? 'Review Architecture')); ?></a>
                    <a href="<?php echo rateb_ent_h(rateb_enterprise_mailto('RATEB — Contact Solutions Team')); ?>" class="rateb-btn rateb-btn--outline"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.cta.solutions'] ?? 'Contact Solutions Team')); ?></a>
                    <a href="<?php echo rateb_ent_h(rateb_enterprise_mailto('RATEB — Request Security Brief')); ?>" class="rateb-btn rateb-btn--ghost"><?php echo rateb_ent_h((string) ($ratebHome['home.ent.cta.security'] ?? 'Request Security Brief')); ?></a>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('rateb_enterprise_trust_render_hero_strip')) {
    function rateb_enterprise_trust_render_hero_strip(array $ratebHome): void
    {
        ?>
        <div class="rateb-ent-hero-strip" role="list" aria-label="Platform posture">
            <?php for ($hi = 1; $hi <= 4; $hi++) {
                $label = trim((string) ($ratebHome['home.ent.hero_strip.' . $hi] ?? ''));
                if ($label === '') {
                    continue;
                }
                ?>
            <span class="rateb-ent-hero-strip__item" role="listitem"><span class="rateb-mono-tag">ok</span> <?php echo rateb_ent_h($label); ?></span>
            <?php } ?>
        </div>
        <?php
    }
}
