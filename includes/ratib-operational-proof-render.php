<?php
/**
 * Renders operational proof block (diagrams, screenshots, workflows).
 */
declare(strict_types=1);

require_once __DIR__ . '/ratib-operational-proof-data.php';

if (!function_exists('ratib_op_h')) {
    function ratib_op_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ratib_op_sample_badge')) {
    function ratib_op_sample_badge(string $text = 'Illustrative interface'): string
    {
        return '<span class="ratib-op-sample">' . ratib_op_h($text) . '</span>';
    }
}

if (!function_exists('ratib_operational_proof_render')) {
    /**
     * @param array<string, mixed>|null $copy Optional section overrides (from CMS)
     * @param array{diagrams?:bool,screenshots?:bool,workflows?:bool} $show
     */
    function ratib_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void
    {
        $cfg = ratib_operational_proof_config($baseUrl);
        $sec = $cfg['section'];
        if (is_array($copy)) {
            $sec = array_merge($sec, $copy);
        }
        $showDiagrams = $show['diagrams'] ?? true;
        $showScreenshots = $show['screenshots'] ?? true;
        $showWorkflows = $show['workflows'] ?? true;
        $disclaimer = (string) ($cfg['disclaimer'] ?? '');
        ?>
        <section class="ratib-op-proof" id="operational-proof" aria-labelledby="ratib-op-proof-title">
            <div class="ratib-op-proof__inner ratib-about-container">
                <header class="ratib-op-proof__head">
                    <p class="ratib-op-proof__eyebrow"><?php echo ratib_op_h((string) ($sec['eyebrow'] ?? '')); ?></p>
                    <h2 id="ratib-op-proof-title" class="ratib-op-proof__title"><?php echo ratib_op_h((string) ($sec['title'] ?? '')); ?></h2>
                    <p class="ratib-op-proof__sub"><?php echo ratib_op_h((string) ($sec['sub'] ?? '')); ?></p>
                    <?php if ($disclaimer !== '') { ?>
                    <p class="ratib-op-proof__disclaimer ratib-mono"><?php echo ratib_op_h($disclaimer); ?></p>
                    <?php } ?>
                </header>

                <?php if ($showDiagrams && !empty($cfg['diagrams'])) { ?>
                <div class="ratib-op-proof__block">
                    <h3 class="ratib-op-proof__block-title">Reference diagrams</h3>
                    <p class="ratib-op-proof__block-note"><?php echo ratib_op_sample_badge('Illustrative diagrams · not live system output'); ?></p>
                    <div class="ratib-op-diagram-grid">
                        <?php foreach ($cfg['diagrams'] as $d) { ?>
                        <figure class="ratib-op-diagram-card">
                            <div class="ratib-op-diagram-card__frame">
                                <img src="<?php echo ratib_op_h((string) ($d['src'] ?? '')); ?>" alt="<?php echo ratib_op_h((string) ($d['title'] ?? '')); ?>" width="640" height="400" loading="lazy" decoding="async">
                            </div>
                            <figcaption>
                                <strong><?php echo ratib_op_h((string) ($d['title'] ?? '')); ?></strong>
                                <span><?php echo ratib_op_h((string) ($d['caption'] ?? '')); ?></span>
                            </figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

                <?php if ($showScreenshots && !empty($cfg['screenshots'])) { ?>
                <div class="ratib-op-proof__block">
                    <h3 class="ratib-op-proof__block-title">Platform screens (sanitized)</h3>
                    <p class="ratib-op-proof__block-note"><?php echo ratib_op_sample_badge('Sample operational data'); ?> — names and figures are fictional.</p>
                    <div class="ratib-op-screen-grid">
                        <?php foreach ($cfg['screenshots'] as $s) {
                            $badge = (string) ($s['label'] ?? 'Illustrative interface');
                            ?>
                        <figure class="ratib-op-screen-card">
                            <div class="ratib-op-screen-card__frame">
                                <?php echo ratib_op_sample_badge($badge); ?>
                                <img src="<?php echo ratib_op_h((string) ($s['src'] ?? '')); ?>" alt="<?php echo ratib_op_h((string) ($s['alt'] ?? '')); ?>" loading="lazy" decoding="async">
                            </div>
                            <figcaption><?php echo ratib_op_h((string) ($s['title'] ?? '')); ?></figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                </div>
                <?php } ?>

                <?php if ($showWorkflows && !empty($cfg['workflows'])) { ?>
                <div class="ratib-op-proof__block">
                    <h3 class="ratib-op-proof__block-title">Workflow walkthroughs</h3>
                    <p class="ratib-op-proof__block-note">Typical operator paths—your corridor policies may add or remove steps.</p>
                    <div class="ratib-op-workflow-grid">
                        <?php foreach ($cfg['workflows'] as $w) { ?>
                        <article class="ratib-op-workflow" id="<?php echo ratib_op_h((string) ($w['id'] ?? '')); ?>">
                            <header class="ratib-op-workflow__head">
                                <span class="ratib-op-workflow__icon" aria-hidden="true"><i class="fas <?php echo ratib_op_h((string) ($w['icon'] ?? 'fa-circle')); ?>"></i></span>
                                <h4><?php echo ratib_op_h((string) ($w['title'] ?? '')); ?></h4>
                            </header>
                            <ol class="ratib-op-workflow__steps">
                                <?php foreach ($w['steps'] ?? [] as $step) { ?>
                                <li><?php echo ratib_op_h((string) $step); ?></li>
                                <?php } ?>
                            </ol>
                            <p class="ratib-op-workflow__outcome"><span class="ratib-mono">Outcome</span> <?php echo ratib_op_h((string) ($w['outcome'] ?? '')); ?></p>
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
