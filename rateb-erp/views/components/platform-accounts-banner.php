<?php
/**
 * Banner when managing platform SA / platform staff (no active company context).
 */
$usersBase = function_exists('rateb_app_route')
    ? rateb_url(rateb_app_route('users'))
    : rateb_url('admin/users');
$link = static function (string $scope) use ($usersBase): string {
    if (function_exists('rateb_url_query')) {
        return rateb_url_query($usersBase, ['scope' => $scope]);
    }

    return $usersBase . (str_contains($usersBase, '?') ? '&' : '?') . 'scope=' . rawurlencode($scope);
};
$opsCompanyId = function_exists('rateb_resolve_ops_company_id') ? (int) rateb_resolve_ops_company_id() : 0;
?>
<div class="rateb-card mb-3 rateb-platform-accounts-banner" data-rateb-platform-accounts="1">
    <div class="rateb-card-body py-3">
        <div class="alert alert-secondary py-2 mb-3 mb-md-2">
            <i class="fas fa-shield-halved me-1"></i>
            <strong><?php echo __('platform_accounts_mode_title'); ?></strong>
            — <?php echo __('platform_accounts_mode_lead'); ?>
        </div>
        <p class="text-muted small mb-2"><?php echo __('platform_accounts_mode_help'); ?></p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?php echo Rateb\App\Core\View::escape($link('platform')); ?>" data-rateb-full-nav="1">
                <i class="fas fa-user-shield me-1"></i><?php echo __('users_scope_platform'); ?>
            </a>
            <a class="btn btn-sm btn-outline-primary" href="<?php echo Rateb\App\Core\View::escape($link('staff')); ?>" data-rateb-full-nav="1">
                <i class="fas fa-user-lock me-1"></i><?php echo __('users_scope_staff'); ?>
            </a>
            <?php if ($opsCompanyId > 0) { ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo Rateb\App\Core\View::escape($link('company')); ?>" data-rateb-full-nav="1">
                <i class="fas fa-building me-1"></i><?php echo __('users_scope_company'); ?>
            </a>
            <?php } ?>
        </div>
    </div>
</div>
