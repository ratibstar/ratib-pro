<?php
declare(strict_types=1);

/** @var array<string, mixed> $context */
/** @var list<array{url:string,title:string,hint:string}> $links */
/** @var bool $canDemoSetup */
$context = is_array($context ?? null) ? $context : [];
$links = is_array($links ?? null) ? $links : [];
$canDemoSetup = (bool) ($canDemoSetup ?? false);
$terminal = is_array($context['terminal'] ?? null) ? $context['terminal'] : [];
$shift = is_array($context['shift'] ?? null) ? $context['shift'] : null;
$branch = is_array($context['branch'] ?? null) ? $context['branch'] : [];
$warehouse = is_array($context['warehouse'] ?? null) ? $context['warehouse'] : [];
$locale = (string) ($locale ?? rateb_locale());
$flashSuccess = (string) ($flashSuccess ?? '');
$flashError = (string) ($flashError ?? '');

$terminalLabel = trim((string) ($terminal['name'] ?? $terminal['code'] ?? ''));
$branchLabel = trim((string) ($branch['name'] ?? $branch['name_ar'] ?? ''));
$warehouseLabel = trim((string) ($warehouse['name'] ?? $warehouse['code'] ?? ''));
$shiftLabel = $shift ? (string) ($shift['shift_no'] ?? '—') : __('pos_shift_not_open');
$shiftStatus = $shift ? (string) ($shift['status'] ?? '') : '';
?>
<div class="rateb-pos-page rateb-pos-settings">
    <?php if ($flashSuccess !== ''): ?>
        <div class="rateb-pos-settings__alert rateb-pos-settings__alert--ok" role="status"><?php echo \Rateb\App\Pos\Support\PosView::escape($flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError !== ''): ?>
        <div class="rateb-pos-settings__alert rateb-pos-settings__alert--err" role="alert"><?php echo \Rateb\App\Pos\Support\PosView::escape($flashError); ?></div>
    <?php endif; ?>

    <section class="rateb-pos-settings__card">
        <h2 class="rateb-pos-settings__h"><?php echo __('pos_settings_session_title'); ?></h2>
        <p class="rateb-pos-settings__hint"><?php echo __('pos_settings_session_hint'); ?></p>
        <dl class="rateb-pos-settings__grid">
            <div>
                <dt><?php echo __('pos_context_branch'); ?></dt>
                <dd><?php echo \Rateb\App\Pos\Support\PosView::escape($branchLabel !== '' ? $branchLabel : '—'); ?></dd>
            </div>
            <div>
                <dt><?php echo __('pos_context_terminal'); ?></dt>
                <dd><?php echo \Rateb\App\Pos\Support\PosView::escape($terminalLabel !== '' ? $terminalLabel : '—'); ?></dd>
            </div>
            <div>
                <dt><?php echo __('pos_context_warehouse'); ?></dt>
                <dd><?php echo \Rateb\App\Pos\Support\PosView::escape($warehouseLabel !== '' ? $warehouseLabel : '—'); ?></dd>
            </div>
            <div>
                <dt><?php echo __('pos_context_shift'); ?></dt>
                <dd>
                    <?php echo \Rateb\App\Pos\Support\PosView::escape($shiftLabel); ?>
                    <?php if ($shiftStatus !== ''): ?>
                        <span class="rateb-pos-settings__pill"><?php echo \Rateb\App\Pos\Support\PosView::escape($shiftStatus); ?></span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt><?php echo __('language'); ?></dt>
                <dd><?php echo $locale === 'ar' ? 'العربية' : 'English'; ?></dd>
            </div>
        </dl>
    </section>

    <section class="rateb-pos-settings__card">
        <h2 class="rateb-pos-settings__h"><?php echo __('pos_settings_display_title'); ?></h2>
        <p class="rateb-pos-settings__hint"><?php echo __('pos_settings_display_hint'); ?></p>
        <div class="rateb-pos-settings__row">
            <div class="rateb-pos__lang" role="group" aria-label="<?php echo __('language'); ?>">
                <a href="<?php echo \Rateb\App\Pos\Support\PosView::escape(rateb_locale_switch_url('en')); ?>" class="rateb-pos__lang-btn<?php echo $locale === 'en' ? ' is-active' : ''; ?>" data-locale="en" lang="en">EN</a>
                <a href="<?php echo \Rateb\App\Pos\Support\PosView::escape(rateb_locale_switch_url('ar')); ?>" class="rateb-pos__lang-btn<?php echo $locale === 'ar' ? ' is-active' : ''; ?>" data-locale="ar" lang="ar">ع</a>
            </div>
            <div class="rateb-pos__theme" role="group" aria-label="<?php echo __('pos_theme_dark'); ?>">
                <button type="button" class="rateb-pos__theme-btn" data-theme-choice="light" aria-pressed="false" title="<?php echo __('pos_theme_light'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                </button>
                <button type="button" class="rateb-pos__theme-btn" data-theme-choice="dark" aria-pressed="true" title="<?php echo __('pos_theme_dark'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </div>
    </section>

    <?php if ($links !== []): ?>
    <section class="rateb-pos-settings__card">
        <h2 class="rateb-pos-settings__h"><?php echo __('pos_settings_links_title'); ?></h2>
        <p class="rateb-pos-settings__hint"><?php echo __('pos_settings_links_hint'); ?></p>
        <div class="rateb-pos-settings__links">
            <?php foreach ($links as $link): ?>
                <a class="rateb-pos-settings__link" href="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($link['url'] ?? '#')); ?>">
                    <strong><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($link['title'] ?? '')); ?></strong>
                    <span><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($link['hint'] ?? '')); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($canDemoSetup): ?>
    <section class="rateb-pos-settings__card rateb-pos-settings__card--muted">
        <h2 class="rateb-pos-settings__h"><?php echo __('pos_demo_setup_title'); ?></h2>
        <p class="rateb-pos-settings__hint"><?php echo __('pos_demo_setup_hint'); ?></p>
        <form method="post" action="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($demoSetupUrl ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($csrf ?? '')); ?>">
            <button type="submit" class="rateb-pos-settings__btn">
                <?php echo __('pos_demo_setup_action'); ?>
            </button>
        </form>
    </section>
    <?php endif; ?>
</div>
