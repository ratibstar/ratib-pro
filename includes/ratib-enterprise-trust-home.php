<?php
/**
 * Enterprise trust layer — home page visual block (topology, layers, flows).
 *
 * @param array<string, mixed> $ratibHome CMS home strings
 * @param string $baseUrl Site root without trailing slash
 */
declare(strict_types=1);

if (!function_exists('ratib_ent_h')) {
    function ratib_ent_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ratib_enterprise_mailto')) {
    function ratib_enterprise_mailto(string $subject): string
    {
        return 'mailto:info@out.ratib.sa?subject=' . rawurlencode($subject);
    }
}

if (!function_exists('ratib_enterprise_trust_render_home')) {
    function ratib_enterprise_trust_render_home(array $ratibHome, string $baseUrl): void
    {
        $root = rtrim($baseUrl, '/');
        $layers = [
            ['L7', 'Experience', 'Consoles & partner surfaces'],
            ['L6', 'Orchestration', 'Stage graphs & workflow engine'],
            ['L5', 'Telemetry', 'Field signals & escalation'],
            ['L4', 'Business modules', 'Recruitment · deployment · docs'],
            ['L3', 'Governance', 'RBAC · policy · audit'],
            ['L2', 'Commercial', 'Ledger · AR/AP · registration'],
            ['L1', 'Data', 'Control plane · tenant DBs'],
        ];
        ?>
        <section class="ratib-section ratib-ent-trust" id="enterprise-infrastructure" aria-labelledby="ent-trust-title">
            <div class="ratib-container">
                <header class="ratib-section__head ratib-ent-trust__head">
                    <p class="ratib-eyebrow ratib-eyebrow--enterprise"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.eyebrow'] ?? 'Infrastructure posture')); ?></p>
                    <h2 id="ent-trust-title" class="ratib-section__title"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.title'] ?? '')); ?></h2>
                    <p class="ratib-section__sub"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.sub'] ?? '')); ?></p>
                </header>

                <div class="ratib-ent-indicators" role="list" aria-label="Operational trust indicators">
                    <?php for ($bi = 1; $bi <= 6; $bi++) {
                        $badge = trim((string) ($ratibHome['home.ent.badge.' . $bi] ?? ''));
                        if ($badge === '') {
                            continue;
                        }
                        ?>
                    <span class="ratib-ent-indicator" role="listitem"><i class="fas fa-circle-check" aria-hidden="true"></i> <?php echo ratib_ent_h($badge); ?></span>
                    <?php } ?>
                </div>

                <div class="ratib-ent-grid ratib-ent-grid--primary">
                    <div class="ratib-ent-panel ratib-ent-panel--layers">
                        <h3 class="ratib-ent-panel__title ratib-mono-ops"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.layers.title'] ?? 'Layered control plane')); ?></h3>
                        <p class="ratib-ent-panel__sub"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.layers.sub'] ?? '')); ?></p>
                        <ol class="ratib-ent-layer-stack" aria-label="Control plane layers">
                            <?php foreach ($layers as $layer) { ?>
                            <li class="ratib-ent-layer">
                                <span class="ratib-ent-layer__order ratib-mono-ops"><?php echo ratib_ent_h($layer[0]); ?></span>
                                <span class="ratib-ent-layer__name"><?php echo ratib_ent_h($layer[1]); ?></span>
                                <span class="ratib-ent-layer__desc"><?php echo ratib_ent_h($layer[2]); ?></span>
                            </li>
                            <?php } ?>
                        </ol>
                        <a class="ratib-ent-panel__link" href="<?php echo ratib_ent_h($root . '/architecture/'); ?>"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.layers.link'] ?? 'Full architecture →')); ?></a>
                    </div>

                    <div class="ratib-ent-panel ratib-ent-panel--flow">
                        <h3 class="ratib-ent-panel__title ratib-mono-ops"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.flow.title'] ?? 'Orchestration flow')); ?></h3>
                        <p class="ratib-ent-panel__sub"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.flow.sub'] ?? '')); ?></p>
                        <div class="ratib-ent-orchestration" aria-label="Lifecycle orchestration sequence">
                            <?php
                            $flowSteps = [
                                ['emit', 'Event emit', 'Stage transition committed'],
                                ['policy', 'Policy gate', 'Governance evaluates'],
                                ['route', 'Fabric route', 'Webhooks · SSE · modules'],
                                ['persist', 'Tenant persist', 'Isolated datastore write'],
                            ];
                            foreach ($flowSteps as $fi => $fs) {
                                if ($fi > 0) {
                                    echo '<span class="ratib-ent-orchestration__arrow" aria-hidden="true">↓</span>';
                                }
                                ?>
                            <div class="ratib-ent-orchestration__step">
                                <span class="ratib-mono-ops"><?php echo ratib_ent_h($fs[0]); ?></span>
                                <strong><?php echo ratib_ent_h($fs[1]); ?></strong>
                                <span><?php echo ratib_ent_h($fs[2]); ?></span>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="ratib-ent-grid ratib-ent-grid--diagrams">
                    <div class="ratib-ent-panel ratib-ent-panel--topology">
                        <h3 class="ratib-ent-panel__title ratib-mono-ops"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.topology.title'] ?? 'Deployment topology')); ?></h3>
                        <div class="ratib-ent-topology" aria-hidden="true">
                            <div class="ratib-ent-topology__row"><span>Public edge</span></div>
                            <div class="ratib-ent-topology__v"></div>
                            <div class="ratib-ent-topology__row ratib-ent-topology__row--split">
                                <span>Agency workspace</span><span>Partner portals</span>
                            </div>
                            <div class="ratib-ent-topology__v"></div>
                            <div class="ratib-ent-topology__row"><span>API gateway</span></div>
                            <div class="ratib-ent-topology__v ratib-ent-topology__v--core"></div>
                            <div class="ratib-ent-topology__row ratib-ent-topology__row--core"><span>Orchestration core</span></div>
                            <div class="ratib-ent-topology__v"></div>
                            <div class="ratib-ent-topology__row ratib-ent-topology__row--data">
                                <span>Tenant DB</span><span>Tenant DB</span><span>Tenant DB</span>
                            </div>
                        </div>
                        <p class="ratib-ent-panel__note"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.topology.note'] ?? '')); ?></p>
                    </div>

                    <div class="ratib-ent-panel ratib-ent-panel--governance">
                        <h3 class="ratib-ent-panel__title ratib-mono-ops"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.gov.title'] ?? 'Governance plane')); ?></h3>
                        <ul class="ratib-ent-gov-list">
                            <?php for ($gi = 1; $gi <= 4; $gi++) {
                                $gt = (string) ($ratibHome['home.ent.gov.' . $gi . '.title'] ?? '');
                                $gb = (string) ($ratibHome['home.ent.gov.' . $gi . '.body'] ?? '');
                                if ($gt === '' && $gb === '') {
                                    continue;
                                }
                                ?>
                            <li>
                                <span class="ratib-ent-gov-list__k ratib-mono-ops"><?php echo ratib_ent_h($gt); ?></span>
                                <span class="ratib-ent-gov-list__v"><?php echo ratib_ent_h($gb); ?></span>
                            </li>
                            <?php } ?>
                        </ul>
                        <a class="ratib-ent-panel__link" href="<?php echo ratib_ent_h($root . '/security-compliance/'); ?>"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.gov.link'] ?? 'Security & compliance →')); ?></a>
                    </div>
                </div>

                <div class="ratib-ent-panel ratib-ent-panel--telemetry">
                    <h3 class="ratib-ent-panel__title ratib-mono-ops"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.telemetry.title'] ?? 'Telemetry intelligence path')); ?></h3>
                    <div class="ratib-ent-telemetry-flow" aria-label="Telemetry processing path">
                        <?php
                        $tel = ['Field capture', 'Offline sync', 'Anti-spoof check', 'Geofence', 'Escalation queue'];
                        foreach ($tel as $ti => $tlabel) {
                            if ($ti > 0) {
                                echo '<span class="ratib-ent-telemetry-flow__sep" aria-hidden="true">→</span>';
                            }
                            echo '<span class="ratib-ent-telemetry-flow__node">' . ratib_ent_h($tlabel) . '</span>';
                        }
                        ?>
                    </div>
                    <p class="ratib-ent-panel__note"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.telemetry.note'] ?? '')); ?></p>
                </div>

                <div class="ratib-ent-audit-block">
                    <div class="ratib-ent-audit-block__copy">
                        <h3 class="ratib-mono-ops"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.audit.title'] ?? 'Audit-oriented operations')); ?></h3>
                        <p><?php echo ratib_ent_h((string) ($ratibHome['home.ent.audit.body'] ?? '')); ?></p>
                    </div>
                    <ul class="ratib-ent-audit-block__list ratib-mono-ops">
                        <li>correlation_id · stage_commit</li>
                        <li>policy_version · actor_attribution</li>
                        <li>replay_safe · idempotent_writes</li>
                    </ul>
                </div>

                <div class="ratib-ent-cta-bar" aria-label="Enterprise contact actions">
                    <a href="<?php echo ratib_ent_h(ratib_enterprise_mailto('RATIB — Request Enterprise Demo')); ?>" class="ratib-btn ratib-btn--primary"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.cta.demo'] ?? 'Request Enterprise Demo')); ?></a>
                    <a href="<?php echo ratib_ent_h($root . '/architecture/'); ?>" class="ratib-btn ratib-btn--outline"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.cta.architecture'] ?? 'Review Architecture')); ?></a>
                    <a href="<?php echo ratib_ent_h(ratib_enterprise_mailto('RATIB — Contact Solutions Team')); ?>" class="ratib-btn ratib-btn--outline"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.cta.solutions'] ?? 'Contact Solutions Team')); ?></a>
                    <a href="<?php echo ratib_ent_h(ratib_enterprise_mailto('RATIB — Request Security Brief')); ?>" class="ratib-btn ratib-btn--ghost"><?php echo ratib_ent_h((string) ($ratibHome['home.ent.cta.security'] ?? 'Request Security Brief')); ?></a>
                </div>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('ratib_enterprise_trust_render_hero_strip')) {
    function ratib_enterprise_trust_render_hero_strip(array $ratibHome): void
    {
        ?>
        <div class="ratib-ent-hero-strip" role="list" aria-label="Platform posture">
            <?php for ($hi = 1; $hi <= 4; $hi++) {
                $label = trim((string) ($ratibHome['home.ent.hero_strip.' . $hi] ?? ''));
                if ($label === '') {
                    continue;
                }
                ?>
            <span class="ratib-ent-hero-strip__item" role="listitem"><span class="ratib-mono-tag">ok</span> <?php echo ratib_ent_h($label); ?></span>
            <?php } ?>
        </div>
        <?php
    }
}
