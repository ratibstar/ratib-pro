<?php
/** @var array<int, array<string, mixed>> $roles */
/** @var array<int, array{href:string,label:string,icon:string}> $quickLinks */
$userName = (string) ($userName ?? '');
$roles = $roles ?? [];
$quickLinks = $quickLinks ?? [];
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape(__('dashboard')); ?></div>
    <div class="rateb-card-body">
        <div class="alert alert-info py-2 mb-3" role="status">
            <i class="fas fa-user-shield me-1"></i>
            <?php echo Rateb\App\Core\View::escape(__('users_type_platform_staff')); ?>
            <?php if ($userName !== '') { ?>
            — <?php echo Rateb\App\Core\View::escape($userName); ?>
            <?php } ?>
        </div>
        <p class="text-muted mb-3"><?php echo Rateb\App\Core\View::escape(__('platform_staff_home_intro')); ?></p>

        <h3 class="h6 mb-2"><?php echo __('assign_roles'); ?></h3>
        <?php if ($roles === []) { ?>
        <p class="text-muted"><?php echo __('no_data'); ?></p>
        <?php } else { ?>
        <ul class="list-unstyled mb-4">
            <?php foreach ($roles as $role) { ?>
            <li class="mb-1">
                <i class="fas fa-user-tag me-1 text-muted"></i>
                <strong><?php echo Rateb\App\Core\View::escape(function_exists('rateb_role_label') ? rateb_role_label($role) : (string) ($role['name'] ?? '')); ?></strong>
                <small class="text-muted">(<?php echo Rateb\App\Core\View::escape((string) ($role['slug'] ?? '')); ?>)</small>
            </li>
            <?php } ?>
        </ul>
        <?php } ?>

        <?php if ($quickLinks !== []) { ?>
        <h3 class="h6 mb-2"><?php echo __('platform_staff_quick_links'); ?></h3>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($quickLinks as $link) { ?>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo Rateb\App\Core\View::escape($link['href']); ?>" data-rateb-full-nav="1">
                <i class="fas <?php echo Rateb\App\Core\View::escape($link['icon']); ?> me-1"></i>
                <?php echo Rateb\App\Core\View::escape($link['label']); ?>
            </a>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
</div>
