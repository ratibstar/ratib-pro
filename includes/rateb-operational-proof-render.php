<?php
/**
 * Renders operational proof block (diagrams, screenshots, workflows).
 */
declare(strict_types=1);

$ratebOpProofDataPath = __DIR__ . '/rateb-operational-proof-data.php';
if (is_file($ratebOpProofDataPath)) {
    require_once $ratebOpProofDataPath;
}

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

if (!function_exists('rateb_op_more_item_hidden')) {
    function rateb_op_more_item_hidden(bool $compact, int $index, int $limit): bool
    {
        return $compact && $index >= $limit;
    }
}

if (!function_exists('rateb_op_more_item_class')) {
    function rateb_op_more_item_class(bool $compact, int $index, int $limit): string
    {
        return rateb_op_more_item_hidden($compact, $index, $limit) ? ' rateb-op-item--more' : '';
    }
}

if (!function_exists('rateb_op_more_item_attr')) {
    /** hidden attribute so collapsed items stay hidden even if CSS is cached */
    function rateb_op_more_item_attr(bool $compact, int $index, int $limit): string
    {
        return rateb_op_more_item_hidden($compact, $index, $limit) ? ' hidden' : '';
    }
}

if (!function_exists('rateb_op_more_button')) {
    function rateb_op_more_button(bool $show): string
    {
        if (!$show) {
            return '';
        }

        return '<button type="button" class="rateb-op-more-btn" data-rateb-op-more aria-expanded="false">'
            . '<span class="rateb-op-more-btn__more">More</span>'
            . '<span class="rateb-op-more-btn__less" hidden>Less</span>'
            . '</button>';
    }
}

if (!function_exists('rateb_op_gallery_thumb')) {
    /**
     * Clickable screenshot/diagram — opens lightbox with prev/next (data-rateb-gallery-open).
     */
    function rateb_op_gallery_thumb(string $src, string $alt, string $caption, string $badgeLabel, string $loading = 'lazy'): string
    {
        if ($src === '') {
            return '';
        }
        $viewLabel = trim($caption) !== '' ? trim($caption) : ($alt !== '' ? $alt : 'Image');
        $aria = 'View larger: ' . $viewLabel;

        return '<button type="button" class="rateb-op-gallery-thumb" data-rateb-gallery-open data-full-src="'
            . rateb_op_h($src) . '" data-caption="' . rateb_op_h($caption) . '" aria-label="' . rateb_op_h($aria) . '">'
            . rateb_op_sample_badge($badgeLabel)
            . '<img src="' . rateb_op_h($src) . '" alt="' . rateb_op_h($alt) . '" loading="' . rateb_op_h($loading) . '" decoding="async">'
            . '</button>';
    }
}

if (!function_exists('rateb_operational_proof_render')) {
    /**
     * @param array<string, mixed>|null $copy Optional section overrides (from CMS)
     * @param array{government?:bool,diagrams?:bool,screenshots?:bool,workflows?:bool,compact?:bool,compact_limits?:array<string,int>} $show
     */
    function rateb_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void
    {
        if (!function_exists('rateb_operational_proof_config')) {
            return;
        }
        $cfg = rateb_operational_proof_config($baseUrl);
        $sec = $cfg['section'];
        if (is_array($copy)) {
            $sec = array_merge($sec, $copy);
        }
        $showGovernment = $show['government'] ?? true;
        $showDiagrams = $show['diagrams'] ?? true;
        $showScreenshots = $show['screenshots'] ?? true;
        $showWorkflows = $show['workflows'] ?? true;
        $compact = !empty($show['compact']);
        $limits = [
            'gov_shots' => 1,
            'gov_points' => 0,
            'diagrams' => 1,
            'screenshots' => 1,
            'workflows' => 1,
            'workflow_steps' => 1,
        ];
        if (!empty($show['compact_limits']) && is_array($show['compact_limits'])) {
            $limits = array_merge($limits, $show['compact_limits']);
        }
        $disclaimer = (string) ($cfg['disclaimer'] ?? '');
        $gov = is_array($cfg['government'] ?? null) ? $cfg['government'] : [];
        $govShots = is_array($gov['screenshots'] ?? null) ? $gov['screenshots'] : [];
        $govPoints = is_array($gov['points'] ?? null) ? $gov['points'] : [];
        $diagrams = is_array($cfg['diagrams'] ?? null) ? $cfg['diagrams'] : [];
        $screenshots = is_array($cfg['screenshots'] ?? null) ? $cfg['screenshots'] : [];
        $workflows = is_array($cfg['workflows'] ?? null) ? $cfg['workflows'] : [];
        ?>
        <section class="rateb-op-proof<?php echo $compact ? ' rateb-op-proof--compact' : ''; ?>" id="operational-proof" aria-labelledby="rateb-op-proof-title" data-rateb-marketing-depth="deep">
            <div class="rateb-op-proof__inner rateb-about-container">
                <header class="rateb-op-proof__head">
                    <p class="rateb-op-proof__eyebrow"><?php echo rateb_op_h((string) ($sec['eyebrow'] ?? '')); ?></p>
                    <h2 id="rateb-op-proof-title" class="rateb-op-proof__title"><?php echo rateb_op_h((string) ($sec['title'] ?? '')); ?></h2>
                    <p class="rateb-op-proof__sub<?php echo $compact ? ' rateb-op-text--clamp-2' : ''; ?>"><?php echo rateb_op_h((string) ($sec['sub'] ?? '')); ?></p>
                    <?php if ($disclaimer !== '') { ?>
                    <p class="rateb-op-proof__disclaimer rateb-mono<?php echo $compact ? ' rateb-op-text--clamp-2' : ''; ?>"><?php echo rateb_op_h($disclaimer); ?></p>
                    <?php } ?>
                </header>

                <?php if ($showGovernment && !empty($govShots)) {
                    $govId = (string) ($gov['id'] ?? 'government-oversight');
                    $govNote = (string) ($gov['note'] ?? 'Sample operational data');
                    $govHasMore = $compact && (
                        count($govShots) > $limits['gov_shots']
                        || count($govPoints) > $limits['gov_points']
                    );
                    ?>
                <div class="rateb-op-proof__block rateb-op-gov<?php echo $govHasMore ? ' rateb-op-collapsible' : ''; ?>" id="<?php echo rateb_op_h($govId); ?>" aria-labelledby="rateb-op-gov-title"<?php echo $govHasMore ? ' data-rateb-op-collapsible' : ''; ?>>
                    <header class="rateb-op-gov__head">
                        <p class="rateb-op-gov__eyebrow"><?php echo rateb_op_h((string) ($gov['eyebrow'] ?? 'Government & labor oversight')); ?></p>
                        <h3 id="rateb-op-gov-title" class="rateb-op-proof__block-title rateb-op-gov__title"><?php echo rateb_op_h((string) ($gov['title'] ?? '')); ?></h3>
                        <p class="rateb-op-gov__lead<?php echo $compact ? ' rateb-op-text--clamp-2' : ''; ?>"><?php echo rateb_op_h((string) ($gov['lead'] ?? '')); ?></p>
                        <?php if (!empty($govPoints)) { ?>
                        <ul class="rateb-op-gov__points">
                            <?php foreach ($govPoints as $pi => $point) { ?>
                            <li class="<?php echo trim(rateb_op_more_item_class($compact, (int) $pi, $limits['gov_points'])); ?>"<?php echo rateb_op_more_item_attr($compact, (int) $pi, $limits['gov_points']); ?>><?php echo rateb_op_h((string) $point); ?></li>
                            <?php } ?>
                        </ul>
                        <?php } ?>
                        <p class="rateb-op-proof__block-note"><?php echo rateb_op_sample_badge($govNote); ?></p>
                    </header>
                    <div class="rateb-op-gov__grid">
                        <?php foreach ($govShots as $gi => $s) {
                            $badge = (string) ($s['label'] ?? 'Sample operational data');
                            $featured = !empty($s['featured']);
                            ?>
                        <figure class="rateb-op-screen-card<?php echo $featured ? ' rateb-op-screen-card--featured' : ''; ?><?php echo rateb_op_more_item_class($compact, (int) $gi, $limits['gov_shots']); ?>"<?php echo rateb_op_more_item_attr($compact, (int) $gi, $limits['gov_shots']); ?>>
                            <div class="rateb-op-screen-card__frame">
                                <?php echo rateb_op_gallery_thumb(
                                    (string) ($s['src'] ?? ''),
                                    (string) ($s['alt'] ?? ''),
                                    (string) ($s['title'] ?? ''),
                                    $badge,
                                    $featured ? 'eager' : 'lazy'
                                ); ?>
                            </div>
                            <figcaption>
                                <strong><?php echo rateb_op_h((string) ($s['title'] ?? '')); ?></strong>
                                <?php if (!empty($s['caption'])) { ?>
                                <span class="<?php echo $compact ? 'rateb-op-text--clamp-1' : ''; ?>"><?php echo rateb_op_h((string) $s['caption']); ?></span>
                                <?php } ?>
                            </figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                    <?php echo rateb_op_more_button($govHasMore); ?>
                </div>
                <?php } ?>

                <?php if ($showDiagrams && !empty($diagrams)) {
                    $diagramHasMore = $compact && count($diagrams) > $limits['diagrams'];
                    ?>
                <div class="rateb-op-proof__block<?php echo $diagramHasMore ? ' rateb-op-collapsible' : ''; ?>"<?php echo $diagramHasMore ? ' data-rateb-op-collapsible' : ''; ?>>
                    <h3 class="rateb-op-proof__block-title">Reference diagrams</h3>
                    <p class="rateb-op-proof__block-note"><?php echo rateb_op_sample_badge('Illustrative diagrams · not live system output'); ?></p>
                    <div class="rateb-op-diagram-grid">
                        <?php foreach ($diagrams as $di => $d) { ?>
                        <figure class="rateb-op-diagram-card<?php echo rateb_op_more_item_class($compact, (int) $di, $limits['diagrams']); ?>"<?php echo rateb_op_more_item_attr($compact, (int) $di, $limits['diagrams']); ?>>
                            <div class="rateb-op-diagram-card__frame">
                                <?php echo rateb_op_gallery_thumb(
                                    (string) ($d['src'] ?? ''),
                                    (string) ($d['title'] ?? ''),
                                    trim((string) ($d['title'] ?? '') . ' — ' . (string) ($d['caption'] ?? '')),
                                    'Illustrative diagram',
                                    'lazy'
                                ); ?>
                            </div>
                            <figcaption>
                                <strong><?php echo rateb_op_h((string) ($d['title'] ?? '')); ?></strong>
                                <span class="<?php echo $compact ? 'rateb-op-text--clamp-1' : ''; ?>"><?php echo rateb_op_h((string) ($d['caption'] ?? '')); ?></span>
                            </figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                    <?php echo rateb_op_more_button($diagramHasMore); ?>
                </div>
                <?php } ?>

                <?php if ($showScreenshots && !empty($screenshots)) {
                    $screenHasMore = $compact && count($screenshots) > $limits['screenshots'];
                    ?>
                <div class="rateb-op-proof__block<?php echo $screenHasMore ? ' rateb-op-collapsible' : ''; ?>"<?php echo $screenHasMore ? ' data-rateb-op-collapsible' : ''; ?>>
                    <h3 class="rateb-op-proof__block-title">Platform screens (sanitized)</h3>
                    <p class="rateb-op-proof__block-note"><?php echo rateb_op_sample_badge('Sample operational data'); ?> — names and figures are fictional.</p>
                    <div class="rateb-op-screen-grid">
                        <?php foreach ($screenshots as $si => $s) {
                            $badge = (string) ($s['label'] ?? 'Illustrative interface');
                            ?>
                        <figure class="rateb-op-screen-card<?php echo rateb_op_more_item_class($compact, (int) $si, $limits['screenshots']); ?>"<?php echo rateb_op_more_item_attr($compact, (int) $si, $limits['screenshots']); ?>>
                            <div class="rateb-op-screen-card__frame">
                                <?php echo rateb_op_gallery_thumb(
                                    (string) ($s['src'] ?? ''),
                                    (string) ($s['alt'] ?? ''),
                                    (string) ($s['title'] ?? ''),
                                    $badge,
                                    'lazy'
                                ); ?>
                            </div>
                            <figcaption><?php echo rateb_op_h((string) ($s['title'] ?? '')); ?></figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                    <?php echo rateb_op_more_button($screenHasMore); ?>
                </div>
                <?php } ?>

                <?php if ($showWorkflows && !empty($workflows)) {
                    $workflowHasMore = false;
                    if ($compact) {
                        foreach ($workflows as $wi => $w) {
                            $steps = is_array($w['steps'] ?? null) ? $w['steps'] : [];
                            if ($wi >= $limits['workflows'] || count($steps) > $limits['workflow_steps']) {
                                $workflowHasMore = true;
                                break;
                            }
                        }
                    }
                    ?>
                <div class="rateb-op-proof__block<?php echo $workflowHasMore ? ' rateb-op-collapsible' : ''; ?>"<?php echo $workflowHasMore ? ' data-rateb-op-collapsible' : ''; ?>>
                    <h3 class="rateb-op-proof__block-title">Workflow walkthroughs</h3>
                    <p class="rateb-op-proof__block-note">Typical operator paths—your corridor policies may add or remove steps.</p>
                    <div class="rateb-op-workflow-grid">
                        <?php foreach ($workflows as $wi => $w) {
                            $steps = is_array($w['steps'] ?? null) ? $w['steps'] : [];
                            $wfMore = $compact && (int) $wi >= $limits['workflows'];
                            ?>
                        <article class="rateb-op-workflow<?php echo $wfMore ? ' rateb-op-item--more' : ''; ?>" id="<?php echo rateb_op_h((string) ($w['id'] ?? '')); ?>"<?php echo $wfMore ? ' hidden' : ''; ?>>
                            <header class="rateb-op-workflow__head">
                                <span class="rateb-op-workflow__icon" aria-hidden="true"><i class="fas <?php echo rateb_op_h((string) ($w['icon'] ?? 'fa-circle')); ?>"></i></span>
                                <h4><?php echo rateb_op_h((string) ($w['title'] ?? '')); ?></h4>
                            </header>
                            <ol class="rateb-op-workflow__steps">
                                <?php foreach ($steps as $sti => $step) {
                                    $stepHidden = $compact && (((int) $wi === 0 && (int) $sti >= $limits['workflow_steps']) || (int) $wi > 0);
                                    ?>
                                <li<?php echo $stepHidden ? ' class="rateb-op-item--more" hidden' : ''; ?>><?php echo rateb_op_h((string) $step); ?></li>
                                <?php } ?>
                            </ol>
                            <p class="rateb-op-workflow__outcome<?php echo $compact ? ' rateb-op-text--clamp-2' : ''; ?>"><span class="rateb-mono">Outcome</span> <?php echo rateb_op_h((string) ($w['outcome'] ?? '')); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                    <?php echo rateb_op_more_button($workflowHasMore); ?>
                </div>
                <?php } ?>
            </div>
        </section>
        <?php
    }
}
