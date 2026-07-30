<?php
declare(strict_types=1);

/** @var array{title:string,icon:string,tone:string,desc:string} $sectionMeta */
$sectionMeta = $sectionMeta ?? ['title' => 'agent_apps_section', 'icon' => 'fa-cube', 'tone' => 'navy', 'desc' => ''];
$tone = (string) ($sectionMeta['tone'] ?? 'navy');
$hubTitle = (string) ($hubTitle ?? __((string) $sectionMeta['title']));
$hubBody = (string) ($hubBody ?? '');
$hubCta = (string) ($hubCta ?? __('view'));
$hubUrl = (string) ($hubUrl ?? '#');
$hubSecondaryCta = (string) ($hubSecondaryCta ?? '');
$hubSecondaryUrl = (string) ($hubSecondaryUrl ?? '');
?>
<div class="raa" data-raa="hub">
    <header class="raa-hero raa-hero--compact">
        <div class="raa-hero__copy">
            <p class="raa-hero__eyebrow"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?></p>
            <h1 class="raa-hero__title">
                <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($sectionMeta['icon'] ?? 'fa-cube')); ?>"></i>
                <?php echo Rateb\App\Core\View::escape($hubTitle); ?>
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
                <h2 class="raa-empty__title"><?php echo Rateb\App\Core\View::escape($hubTitle); ?></h2>
                <p class="raa-empty__text"><?php echo Rateb\App\Core\View::escape($hubBody); ?></p>
                <div class="raa-empty__actions">
                    <a class="raa-card__btn" href="<?php echo Rateb\App\Core\View::escape($hubUrl); ?>" data-rateb-href="<?php echo Rateb\App\Core\View::escape($hubUrl); ?>" data-rateb-soft-nav="1">
                        <?php echo Rateb\App\Core\View::escape($hubCta); ?>
                    </a>
                    <?php if ($hubSecondaryCta !== '' && $hubSecondaryUrl !== '') { ?>
                    <a class="raa-card__btn raa-card__btn--ghost" href="<?php echo Rateb\App\Core\View::escape($hubSecondaryUrl); ?>" data-rateb-href="<?php echo Rateb\App\Core\View::escape($hubSecondaryUrl); ?>" data-rateb-soft-nav="1">
                        <?php echo Rateb\App\Core\View::escape($hubSecondaryCta); ?>
                    </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
