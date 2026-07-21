<?php
declare(strict_types=1);

/** @var string $activeSection */
/** @var string $sectionKey */
/** @var array{title:string,icon:string,tone:string,desc:string} $sectionMeta */
/** @var list<array<string,mixed>> $stats */
/** @var bool $canManage */
/** @var string $mobileAppsUrl */
$activeSection = (string) ($activeSection ?? '');
$sectionKey = (string) ($sectionKey ?? '');
$sectionMeta = $sectionMeta ?? ['title' => 'agent_apps_section', 'icon' => 'fa-cube', 'tone' => 'blue', 'desc' => ''];
$stats = $stats ?? [];
$canManage = !empty($canManage);
$tone = (string) ($sectionMeta['tone'] ?? 'blue');
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/agent-apps.css'); ?>">
<div class="raa" data-raa="section">
    <?php require __DIR__ . '/_side-nav.php'; ?>
    <div class="raa-main">
        <header class="raa-hero raa-hero--compact">
            <div>
                <p class="raa-hero__eyebrow"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?></p>
                <h1 class="raa-hero__title">
                    <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($sectionMeta['icon'] ?? 'fa-cube')); ?> me-2"></i>
                    <?php echo Rateb\App\Core\View::escape(__((string) $sectionMeta['title'])); ?>
                </h1>
                <p class="raa-hero__lead"><?php echo Rateb\App\Core\View::escape(__((string) ($sectionMeta['desc'] ?? ''))); ?></p>
            </div>
            <a class="raa-hero__cta raa-hero__cta--ghost" href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-soft-nav="1">
                <i class="fas fa-arrow-right"></i>
                <?php echo Rateb\App\Core\View::escape(__('agent_apps_back_dashboard')); ?>
            </a>
        </header>

        <div class="raa-panel" data-tone="<?php echo Rateb\App\Core\View::escape($tone); ?>">
            <div class="raa-panel__body">
                <div class="raa-empty">
                    <div class="raa-empty__icon" aria-hidden="true">
                        <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($sectionMeta['icon'] ?? 'fa-cube')); ?>"></i>
                    </div>
                    <h2 class="raa-empty__title"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section_ready_title')); ?></h2>
                    <p class="raa-empty__text"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section_ready_body')); ?></p>
                    <div class="raa-empty__actions">
                        <?php if ($sectionKey === 'settings') { ?>
                        <a class="raa-card__btn" href="<?php echo Rateb\App\Core\View::escape($mobileAppsUrl ?? rateb_url('admin/mobile-apps')); ?>" data-rateb-href="<?php echo Rateb\App\Core\View::escape($mobileAppsUrl ?? rateb_url('admin/mobile-apps')); ?>" data-rateb-soft-nav="1">
                            <?php echo Rateb\App\Core\View::escape(__('agent_apps_manage_branding')); ?>
                        </a>
                        <?php } ?>
                        <a class="raa-card__btn raa-card__btn--ghost" href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-soft-nav="1">
                            <?php echo Rateb\App\Core\View::escape(__('agent_apps_back_dashboard')); ?>
                        </a>
                    </div>
                    <?php if ($canManage) { ?>
                    <p class="raa-empty__hint text-muted small mb-0"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section_manage_hint')); ?></p>
                    <?php } ?>
                </div>
            </div>
        </div>

        <section class="raa-stats raa-stats--compact" aria-label="<?php echo Rateb\App\Core\View::escape(__('agent_apps_quick_stats')); ?>">
            <div class="raa-stats__grid">
                <?php foreach (array_slice($stats, 0, 4) as $stat) { ?>
                <article class="raa-stat" data-tone="<?php echo Rateb\App\Core\View::escape((string) ($stat['tone'] ?? 'blue')); ?>">
                    <div class="raa-stat__icon" aria-hidden="true">
                        <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($stat['icon'] ?? 'fa-chart-simple')); ?>"></i>
                    </div>
                    <div>
                        <div class="raa-stat__label"><?php echo Rateb\App\Core\View::escape((string) ($stat['label'] ?? '')); ?></div>
                        <div class="raa-stat__value rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($stat['value'] ?? '0')); ?></div>
                    </div>
                </article>
                <?php } ?>
            </div>
        </section>
    </div>
</div>
