<?php
/**
 * Renders operational proof block (diagrams, screenshots, workflows).
 */
declare(strict_types=1);

require_once __DIR__ . '/rateb-operational-proof-data.php';

if (!function_exists('rateb_op_h')) {
    function rateb_op_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rateb_op_sample_badge')) {
    function rateb_op_sample_badge(string $text = 'Illustrative interface'): string
    {
        return '<span class="rateb-op-sample">' . rateb_op_h($text) . '</span>';
    }
}

if (!function_exists('rateb_operational_proof_render')) {
    /**
     * @param array<string, mixed>|null $copy Optional section overrides (from CMS)
     * @param array{diagrams?:bool,screenshots?:bool,workflows?:bool} $show
     */
    function rateb_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void
    {
        $cfg = rateb_operational_proof_config($baseUrl);
        $sec = $cfg['section'];
        if (is_array($copy)) {
            $sec = array_merge($sec, $copy);
        }
        $showDiagrams = $show['diagrams'] ?? true;
        $showScreenshots = $show['screenshots'] ?? true;
        $showWorkflows = $show['workflows'] ?? true;
        $disclaimer = (string) ($cfg['disclaimer'] ?? '');
        ?>
        <section class="rateb-op-proof" id="operational-proof" aria-labelledby="rateb-op-proof-title">
            <div class="rateb-op-proof__inner rateb-about-container">
                <header class="rateb-op-proof__head">
                    <p class="rateb-op-proof__eyebrow"><?php echo rateb_op_h((string) ($sec['eyebrow'] ?? '')); ?></p>
                    <h2 id="rateb-op-proof-title" class="rateb-op-proof__title"><?php echo rateb_op_h((string) ($sec['title'] ?? '')); ?></h2>
                    <p class="rateb-op-proof__sub"><?php echo rateb_op_h((string) ($sec['sub'] ?? '')); ?></p>
                    <?php if ($disclaimer !== '') { ?>
                    <p class="rateb-op-proof__disclaimer rateb-mono"><?php echo rateb_op_h($disclaimer); ?></p>
                    <?php } ?>
                </header>

                <?php if ($showDiagrams && !empty($cfg['diagrams'])) { ?>
                <div class="rateb-op-proof__block">
                    <h3 class="rateb-op-proof__block-title">Reference diagrams</h3>
                    <p class="rateb-op-proof__block-note"><?php echo rateb_op_sample_badge('Illustrative diagrams · not live system output'); ?></p>
                    <div class="rateb-op-diagram-grid">
                        <?php foreach ($cfg['diagrams'] as $d) { ?>
                        <figure class="rateb-op-diagram-card">
                            <div class="rateb-op-diagram-card__frame">
                                <img src="<?php echo rateb_op_h((string) ($d['src'] ?? '')); ?>" alt="<?php echo rateb_op_h((string) ($d['title'] ?? '')); ?>" width="640" height="400" loading="lazy" decoding="async">
                            </div>
                            <figcaption>
                                <strong><?php echo rateb_op_h((string) ($d['title'] ?? '')); ?></strong>
                                <span><?php echo rateb_op_h((string) ($d['caption'] ?? '')); ?></span>
                            </figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

                <?php if ($showScreenshots && !empty($cfg['screenshots'])) { ?>
                <div class="rateb-op-proof__block">
                    <h3 class="rateb-op-proof__block-title">Platform screens (sanitized)</h3>
                    <p class="rateb-op-proof__block-note"><?php echo rateb_op_sample_badge('Sample operational data'); ?> — names and figures are fictional.</p>
                    <div class="rateb-op-screen-grid">
                        <?php foreach ($cfg['screenshots'] as $s) {
                            $badge = (string) ($s['label'] ?? 'Illustrative interface');
                            ?>
                        <figure class="rateb-op-screen-card">
                            <div class="rateb-op-screen-card__frame">
                                <?php echo rateb_op_sample_badge($badge); ?>
                                <img src="<?php echo rateb_op_h((string) ($s['src'] ?? '')); ?>" alt="<?php echo rateb_op_h((string) ($s['alt'] ?? '')); ?>" loading="lazy" decoding="async">
                            </div>
                            <figcaption><?php echo rateb_op_h((string) ($s['title'] ?? '')); ?></figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

                <?php if ($showWorkflows && !empty($cfg['workflows'])) { ?>
                <div class="rateb-op-proof__block">
                    <h3 class="rateb-op-proof__block-title">Workflow walkthroughs</h3>
                    <p class="rateb-op-proof__block-note">Typical operator paths—your corridor policies may add or remove steps.</p>
                    <div class="rateb-op-workflow-grid">
                        <?php foreach ($cfg['workflows'] as $w) { ?>
                        <article class="rateb-op-workflow" id="<?php echo rateb_op_h((string) ($w['id'] ?? '')); ?>">
                            <header class="rateb-op-workflow__head">
                                <span class="rateb-op-workflow__icon" aria-hidden="true"><i class="fas <?php echo rateb_op_h((string) ($w['icon'] ?? 'fa-circle')); ?>"></i></span>
                                <h4><?php echo rateb_op_h((string) ($w['title'] ?? '')); ?></h4>
                            </header>
                            <ol class="rateb-op-workflow__steps">
                                <?php foreach ($w['steps'] ?? [] as $step) { ?>
                                <li><?php echo rateb_op_h((string) $step); ?></li>
                                <?php } ?>
                            </ol>
                            <p class="rateb-op-workflow__outcome"><span class="rateb-mono">Outcome</span> <?php echo rateb_op_h((string) ($w['outcome'] ?? '')); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </section>
        <?php
    }
}
