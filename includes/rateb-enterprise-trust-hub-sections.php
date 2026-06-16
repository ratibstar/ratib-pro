<?php
declare(strict_types=1);

if (!function_exists('rateb_eth_h')) {
    function rateb_eth_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rateb_enterprise_trust_hub_render')) {
    function rateb_enterprise_trust_hub_render(array $cfg, string $baseUrl): void
    {
        $root = rtrim($baseUrl, '/');
        ?>
        <section class="rateb-eth-hero" id="top">
            <div class="rateb-about-container">
                <p class="rateb-eyebrow rateb-eyebrow--enterprise"><?php echo rateb_eth_h((string) ($cfg['hero']['eyebrow'] ?? '')); ?></p>
                <h1 class="rateb-eth-hero__title"><?php echo rateb_eth_h((string) ($cfg['hero']['title'] ?? '')); ?></h1>
                <p class="rateb-eth-hero__lead"><?php echo rateb_eth_h((string) ($cfg['hero']['lead'] ?? '')); ?></p>
                <div class="rateb-eth-hero__chips">
                    <span class="rateb-telemetry-chip"><i class="fas fa-lock" aria-hidden="true"></i> TLS 1.3</span>
                    <span class="rateb-telemetry-chip"><i class="fas fa-users-gear" aria-hidden="true"></i> RBAC</span>
                    <span class="rateb-telemetry-chip"><i class="fas fa-database" aria-hidden="true"></i> Tenant isolation</span>
                    <span class="rateb-telemetry-chip"><i class="fas fa-route" aria-hidden="true"></i> Audit trails</span>
                </div>
            </div>
        </section>

        <?php foreach (($cfg['pillars'] ?? []) as $pillar) {
            if (!is_array($pillar)) {
                continue;
            }
            $id = (string) ($pillar['id'] ?? '');
            ?>
        <section class="rateb-eth-pillar rateb-glass-panel" id="<?php echo rateb_eth_h($id); ?>">
            <div class="rateb-about-container">
                <header class="rateb-eth-pillar__head">
                    <span class="rateb-eth-pillar__icon" aria-hidden="true"><i class="fas <?php echo rateb_eth_h((string) ($pillar['icon'] ?? 'fa-circle')); ?>"></i></span>
                    <div>
                        <h2 class="rateb-eth-pillar__title"><?php echo rateb_eth_h((string) ($pillar['title'] ?? '')); ?></h2>
                        <p class="rateb-eth-pillar__lead"><?php echo rateb_eth_h((string) ($pillar['lead'] ?? '')); ?></p>
                    </div>
                </header>
                <ul class="rateb-eth-pillar__list">
                    <?php foreach (($pillar['points'] ?? []) as $pt) { ?>
                    <li><?php echo rateb_eth_h((string) $pt); ?></li>
                    <?php } ?>
                </ul>
                <?php if (!empty($pillar['href'])) { ?>
                <p class="rateb-eth-pillar__link"><a href="<?php echo rateb_eth_h((string) $pillar['href']); ?>"><?php echo rateb_eth_h((string) ($pillar['href_label'] ?? 'Learn more →')); ?></a></p>
                <?php } ?>
            </div>
        </section>
        <?php } ?>

        <section class="rateb-eth-cta">
            <div class="rateb-about-container rateb-glass-panel">
                <h2 class="rateb-eth-cta__title">Procurement &amp; technical review</h2>
                <p class="rateb-eth-cta__sub">Request briefs, walkthroughs, or downloadable enterprise packs.</p>
                <div class="rateb-eth-cta__actions">
                    <?php foreach (($cfg['cta'] ?? []) as $cta) {
                        if (!is_array($cta)) {
                            continue;
                        }
                        if (!empty($cta['href'])) { ?>
                    <a class="rateb-btn rateb-btn--secondary" href="<?php echo rateb_eth_h((string) $cta['href']); ?>"><?php echo rateb_eth_h((string) ($cta['label'] ?? '')); ?></a>
                        <?php } else {
                            $subj = (string) ($cta['subject'] ?? 'RATEB — Enterprise inquiry');
                            ?>
                    <a class="rateb-btn rateb-btn--primary" href="mailto:info@rateb.sa?subject=<?php echo rawurlencode($subj); ?>"><?php echo rateb_eth_h((string) ($cta['label'] ?? '')); ?></a>
                        <?php }
                    } ?>
                </div>
            </div>
        </section>
        <?php
    }
}
