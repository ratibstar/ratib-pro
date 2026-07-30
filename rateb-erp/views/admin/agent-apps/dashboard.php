<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $modules */
/** @var list<array<string,mixed>> $stats */
$modules = $modules ?? [];
$stats = $stats ?? [];
?>
<div class="raa" data-raa="dashboard">
    <header class="raa-hero">
        <div class="raa-hero__copy">
            <h1 class="raa-hero__title"><?php echo Rateb\App\Core\View::escape(__('agent_apps_dashboard_title')); ?></h1>
            <p class="raa-hero__lead"><?php echo Rateb\App\Core\View::escape(__('agent_apps_dashboard_intro')); ?></p>
        </div>
        <a class="raa-hero__cta" href="<?php echo rateb_url('admin/mobile-apps'); ?>" data-rateb-href="<?php echo rateb_url('admin/mobile-apps'); ?>" data-rateb-soft-nav="1">
            <i class="fas fa-mobile-alt"></i>
            <?php echo Rateb\App\Core\View::escape(__('agent_apps_manage_branding')); ?>
        </a>
    </header>

    <section class="raa-modules" aria-label="<?php echo Rateb\App\Core\View::escape(__('agent_apps_modules')); ?>">
        <div class="raa-modules__grid">
            <?php foreach ($modules as $mod) {
                $tone = (string) ($mod['tone'] ?? 'blue');
                ?>
            <article class="raa-card<?php echo empty($mod['live']) ? ' raa-card--soon' : ''; ?>" data-tone="<?php echo Rateb\App\Core\View::escape($tone); ?>">
                <div class="raa-card__icon" aria-hidden="true">
                    <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($mod['icon'] ?? 'fa-cube')); ?>"></i>
                </div>
                <div class="raa-card__head">
                    <h2 class="raa-card__title"><?php echo Rateb\App\Core\View::escape((string) ($mod['title'] ?? '')); ?></h2>
                    <?php if (!empty($mod['live'])) { ?>
                    <span class="raa-badge raa-badge--live"><?php echo Rateb\App\Core\View::escape(__('agent_apps_live_badge')); ?></span>
                    <?php } else { ?>
                    <span class="raa-badge raa-badge--soon"><?php echo Rateb\App\Core\View::escape(__('agent_apps_coming_soon_badge')); ?></span>
                    <?php } ?>
                </div>
                <p class="raa-card__desc"><?php echo Rateb\App\Core\View::escape((string) ($mod['desc'] ?? '')); ?></p>
                <a class="raa-card__btn<?php echo empty($mod['live']) ? ' raa-card__btn--ghost' : ''; ?>"
                   href="<?php echo Rateb\App\Core\View::escape((string) ($mod['url'] ?? '#')); ?>"
                   data-rateb-href="<?php echo Rateb\App\Core\View::escape((string) ($mod['url'] ?? '#')); ?>"
                   data-rateb-soft-nav="1">
                    <?php echo Rateb\App\Core\View::escape((string) ($mod['cta'] ?? __('view'))); ?>
                </a>
            </article>
            <?php } ?>
        </div>
    </section>

    <section class="raa-stats" aria-label="<?php echo Rateb\App\Core\View::escape(__('agent_apps_quick_stats')); ?>">
        <h2 class="raa-stats__title"><?php echo Rateb\App\Core\View::escape(__('agent_apps_quick_stats')); ?></h2>
        <div class="raa-stats__grid">
            <?php foreach ($stats as $stat) { ?>
            <article class="raa-stat" data-tone="<?php echo Rateb\App\Core\View::escape((string) ($stat['tone'] ?? 'blue')); ?>">
                <div class="raa-stat__icon" aria-hidden="true">
                    <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($stat['icon'] ?? 'fa-chart-simple')); ?>"></i>
                </div>
                <div class="raa-stat__copy">
                    <div class="raa-stat__label"><?php echo Rateb\App\Core\View::escape((string) ($stat['label'] ?? '')); ?></div>
                    <div class="raa-stat__value rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($stat['value'] ?? '0')); ?></div>
                </div>
            </article>
            <?php } ?>
        </div>
    </section>
</div>
