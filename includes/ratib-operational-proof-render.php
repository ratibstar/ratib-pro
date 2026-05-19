<?php
/**
 * Renders operational proof block (diagrams, screenshots, workflows).
 */
declare(strict_types=1);

$ratibOpProofDataPath = __DIR__ . '/ratib-operational-proof-data.php';
if (is_file($ratibOpProofDataPath)) {
    require_once $ratibOpProofDataPath;
}

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

if (!function_exists('ratib_op_more_item_hidden')) {
    function ratib_op_more_item_hidden(bool $compact, int $index, int $limit): bool
    {
        return $compact && $index >= $limit;
    }
}

if (!function_exists('ratib_op_more_item_class')) {
    function ratib_op_more_item_class(bool $compact, int $index, int $limit): string
    {
        return ratib_op_more_item_hidden($compact, $index, $limit) ? ' ratib-op-item--more' : '';
    }
}

if (!function_exists('ratib_op_more_item_attr')) {
    /** hidden attribute so collapsed items stay hidden even if CSS is cached */
    function ratib_op_more_item_attr(bool $compact, int $index, int $limit): string
    {
        return ratib_op_more_item_hidden($compact, $index, $limit) ? ' hidden' : '';
    }
}

if (!function_exists('ratib_op_more_button')) {
    function ratib_op_more_button(bool $show): string
    {
        if (!$show) {
            return '';
        }

        return '<button type="button" class="ratib-op-more-btn" data-ratib-op-more aria-expanded="false">'
            . '<span class="ratib-op-more-btn__more">More</span>'
            . '<span class="ratib-op-more-btn__less" hidden>Less</span>'
            . '</button>';
    }
}

if (!function_exists('ratib_op_gallery_thumb')) {
    /**
     * Clickable screenshot/diagram — opens lightbox with prev/next (data-ratib-gallery-open).
     */
    function ratib_op_gallery_thumb(string $src, string $alt, string $caption, string $badgeLabel, string $loading = 'lazy'): string
    {
        if ($src === '') {
            return '';
        }
        $viewLabel = trim($caption) !== '' ? trim($caption) : ($alt !== '' ? $alt : 'Image');
        $aria = 'View larger: ' . $viewLabel;

        return '<button type="button" class="ratib-op-gallery-thumb" data-ratib-gallery-open data-full-src="'
            . ratib_op_h($src) . '" data-caption="' . ratib_op_h($caption) . '" aria-label="' . ratib_op_h($aria) . '">'
            . ratib_op_sample_badge($badgeLabel)
            . '<img src="' . ratib_op_h($src) . '" alt="' . ratib_op_h($alt) . '" loading="' . ratib_op_h($loading) . '" decoding="async">'
            . '</button>';
    }
}

if (!function_exists('ratib_operational_proof_render')) {
    /**
     * @param array<string, mixed>|null $copy Optional section overrides (from CMS)
     * @param array{government?:bool,diagrams?:bool,screenshots?:bool,workflows?:bool,compact?:bool,compact_limits?:array<string,int>} $show
     */
    function ratib_operational_proof_render(string $baseUrl, ?array $copy = null, array $show = []): void
    {
        if (!function_exists('ratib_operational_proof_config')) {
            return;
        }
        $cfg = ratib_operational_proof_config($baseUrl);
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
        <section class="ratib-op-proof<?php echo $compact ? ' ratib-op-proof--compact' : ''; ?>" id="operational-proof" aria-labelledby="ratib-op-proof-title" data-ratib-marketing-depth="deep">
            <div class="ratib-op-proof__inner ratib-about-container">
                <header class="ratib-op-proof__head">
                    <p class="ratib-op-proof__eyebrow"><?php echo ratib_op_h((string) ($sec['eyebrow'] ?? '')); ?></p>
                    <h2 id="ratib-op-proof-title" class="ratib-op-proof__title"><?php echo ratib_op_h((string) ($sec['title'] ?? '')); ?></h2>
                    <p class="ratib-op-proof__sub<?php echo $compact ? ' ratib-op-text--clamp-2' : ''; ?>"><?php echo ratib_op_h((string) ($sec['sub'] ?? '')); ?></p>
                    <?php if ($disclaimer !== '') { ?>
                    <p class="ratib-op-proof__disclaimer ratib-mono<?php echo $compact ? ' ratib-op-text--clamp-2' : ''; ?>"><?php echo ratib_op_h($disclaimer); ?></p>
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
                <div class="ratib-op-proof__block ratib-op-gov<?php echo $govHasMore ? ' ratib-op-collapsible' : ''; ?>" id="<?php echo ratib_op_h($govId); ?>" aria-labelledby="ratib-op-gov-title"<?php echo $govHasMore ? ' data-ratib-op-collapsible' : ''; ?>>
                    <header class="ratib-op-gov__head">
                        <p class="ratib-op-gov__eyebrow"><?php echo ratib_op_h((string) ($gov['eyebrow'] ?? 'Government & labor oversight')); ?></p>
                        <h3 id="ratib-op-gov-title" class="ratib-op-proof__block-title ratib-op-gov__title"><?php echo ratib_op_h((string) ($gov['title'] ?? '')); ?></h3>
                        <p class="ratib-op-gov__lead<?php echo $compact ? ' ratib-op-text--clamp-2' : ''; ?>"><?php echo ratib_op_h((string) ($gov['lead'] ?? '')); ?></p>
                        <?php if (!empty($govPoints)) { ?>
                        <ul class="ratib-op-gov__points">
                            <?php foreach ($govPoints as $pi => $point) { ?>
                            <li class="<?php echo trim(ratib_op_more_item_class($compact, (int) $pi, $limits['gov_points'])); ?>"<?php echo ratib_op_more_item_attr($compact, (int) $pi, $limits['gov_points']); ?>><?php echo ratib_op_h((string) $point); ?></li>
                            <?php } ?>
                        </ul>
                        <?php } ?>
                        <p class="ratib-op-proof__block-note"><?php echo ratib_op_sample_badge($govNote); ?></p>
                    </header>
                    <div class="ratib-op-gov__grid">
                        <?php foreach ($govShots as $gi => $s) {
                            $badge = (string) ($s['label'] ?? 'Sample operational data');
                            $featured = !empty($s['featured']);
                            ?>
                        <figure class="ratib-op-screen-card<?php echo $featured ? ' ratib-op-screen-card--featured' : ''; ?><?php echo ratib_op_more_item_class($compact, (int) $gi, $limits['gov_shots']); ?>"<?php echo ratib_op_more_item_attr($compact, (int) $gi, $limits['gov_shots']); ?>>
                            <div class="ratib-op-screen-card__frame">
                                <?php echo ratib_op_gallery_thumb(
                                    (string) ($s['src'] ?? ''),
                                    (string) ($s['alt'] ?? ''),
                                    (string) ($s['title'] ?? ''),
                                    $badge,
                                    $featured ? 'eager' : 'lazy'
                                ); ?>
                            </div>
                            <figcaption>
                                <strong><?php echo ratib_op_h((string) ($s['title'] ?? '')); ?></strong>
                                <?php if (!empty($s['caption'])) { ?>
                                <span class="<?php echo $compact ? 'ratib-op-text--clamp-1' : ''; ?>"><?php echo ratib_op_h((string) $s['caption']); ?></span>
                                <?php } ?>
                            </figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                    <?php echo ratib_op_more_button($govHasMore); ?>
                </div>
                <?php } ?>

                <?php if ($showDiagrams && !empty($diagrams)) {
                    $diagramHasMore = $compact && count($diagrams) > $limits['diagrams'];
                    ?>
                <div class="ratib-op-proof__block<?php echo $diagramHasMore ? ' ratib-op-collapsible' : ''; ?>"<?php echo $diagramHasMore ? ' data-ratib-op-collapsible' : ''; ?>>
                    <h3 class="ratib-op-proof__block-title">Reference diagrams</h3>
                    <p class="ratib-op-proof__block-note"><?php echo ratib_op_sample_badge('Illustrative diagrams · not live system output'); ?></p>
                    <div class="ratib-op-diagram-grid">
                        <?php foreach ($diagrams as $di => $d) { ?>
                        <figure class="ratib-op-diagram-card<?php echo ratib_op_more_item_class($compact, (int) $di, $limits['diagrams']); ?>"<?php echo ratib_op_more_item_attr($compact, (int) $di, $limits['diagrams']); ?>>
                            <div class="ratib-op-diagram-card__frame">
                                <?php echo ratib_op_gallery_thumb(
                                    (string) ($d['src'] ?? ''),
                                    (string) ($d['title'] ?? ''),
                                    trim((string) ($d['title'] ?? '') . ' — ' . (string) ($d['caption'] ?? '')),
                                    'Illustrative diagram',
                                    'lazy'
                                ); ?>
                            </div>
                            <figcaption>
                                <strong><?php echo ratib_op_h((string) ($d['title'] ?? '')); ?></strong>
                                <span class="<?php echo $compact ? 'ratib-op-text--clamp-1' : ''; ?>"><?php echo ratib_op_h((string) ($d['caption'] ?? '')); ?></span>
                            </figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                    <?php echo ratib_op_more_button($diagramHasMore); ?>
                </div>
                <?php } ?>

                <?php if ($showScreenshots && !empty($screenshots)) {
                    $screenHasMore = $compact && count($screenshots) > $limits['screenshots'];
                    ?>
                <div class="ratib-op-proof__block<?php echo $screenHasMore ? ' ratib-op-collapsible' : ''; ?>"<?php echo $screenHasMore ? ' data-ratib-op-collapsible' : ''; ?>>
                    <h3 class="ratib-op-proof__block-title">Platform screens (sanitized)</h3>
                    <p class="ratib-op-proof__block-note"><?php echo ratib_op_sample_badge('Sample operational data'); ?> — names and figures are fictional.</p>
                    <div class="ratib-op-screen-grid">
                        <?php foreach ($screenshots as $si => $s) {
                            $badge = (string) ($s['label'] ?? 'Illustrative interface');
                            ?>
                        <figure class="ratib-op-screen-card<?php echo ratib_op_more_item_class($compact, (int) $si, $limits['screenshots']); ?>"<?php echo ratib_op_more_item_attr($compact, (int) $si, $limits['screenshots']); ?>>
                            <div class="ratib-op-screen-card__frame">
                                <?php echo ratib_op_gallery_thumb(
                                    (string) ($s['src'] ?? ''),
                                    (string) ($s['alt'] ?? ''),
                                    (string) ($s['title'] ?? ''),
                                    $badge,
                                    'lazy'
                                ); ?>
                            </div>
                            <figcaption><?php echo ratib_op_h((string) ($s['title'] ?? '')); ?></figcaption>
                        </figure>
                        <?php } ?>
                    </div>
                    <?php echo ratib_op_more_button($screenHasMore); ?>
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
                <div class="ratib-op-proof__block<?php echo $workflowHasMore ? ' ratib-op-collapsible' : ''; ?>"<?php echo $workflowHasMore ? ' data-ratib-op-collapsible' : ''; ?>>
                    <h3 class="ratib-op-proof__block-title">Workflow walkthroughs</h3>
                    <p class="ratib-op-proof__block-note">Typical operator paths—your corridor policies may add or remove steps.</p>
                    <div class="ratib-op-workflow-grid">
                        <?php foreach ($workflows as $wi => $w) {
                            $steps = is_array($w['steps'] ?? null) ? $w['steps'] : [];
                            $wfMore = $compact && (int) $wi >= $limits['workflows'];
                            ?>
                        <article class="ratib-op-workflow<?php echo $wfMore ? ' ratib-op-item--more' : ''; ?>" id="<?php echo ratib_op_h((string) ($w['id'] ?? '')); ?>"<?php echo $wfMore ? ' hidden' : ''; ?>>
                            <header class="ratib-op-workflow__head">
                                <span class="ratib-op-workflow__icon" aria-hidden="true"><i class="fas <?php echo ratib_op_h((string) ($w['icon'] ?? 'fa-circle')); ?>"></i></span>
                                <h4><?php echo ratib_op_h((string) ($w['title'] ?? '')); ?></h4>
                            </header>
                            <ol class="ratib-op-workflow__steps">
                                <?php foreach ($steps as $sti => $step) {
                                    $stepHidden = $compact && (((int) $wi === 0 && (int) $sti >= $limits['workflow_steps']) || (int) $wi > 0);
                                    ?>
                                <li<?php echo $stepHidden ? ' class="ratib-op-item--more" hidden' : ''; ?>><?php echo ratib_op_h((string) $step); ?></li>
                                <?php } ?>
                            </ol>
                            <p class="ratib-op-workflow__outcome<?php echo $compact ? ' ratib-op-text--clamp-2' : ''; ?>"><span class="ratib-mono">Outcome</span> <?php echo ratib_op_h((string) ($w['outcome'] ?? '')); ?></p>
                        </article>
                        <?php } ?>
                    </div>
                    <?php echo ratib_op_more_button($workflowHasMore); ?>
                </div>
                <?php } ?>
            </div>
        </section>
        <?php
    }
}
