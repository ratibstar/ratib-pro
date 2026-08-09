<?php
/** @var string|null $listHelp */
/** @var bool $showUsersScopeTabs */
/** @var string $usersScope */
/** @var int $usersScopeCompanyId */
$scope = (string) ($usersScope ?? 'company');
$baseList = rateb_url($routePrefix ?? 'admin/users');
if (!empty($showUsersScopeTabs)) { ?>
<div class="btn-group btn-group-sm mb-3" role="group" aria-label="<?php echo Rateb\App\Core\View::escape(__('users_scope_label')); ?>">
    <?php if ((int) ($usersScopeCompanyId ?? 0) > 0) { ?>
    <a class="btn btn-outline-primary<?php echo $scope === 'company' ? ' active' : ''; ?>"
       href="<?php echo Rateb\App\Core\View::escape($baseList . '?scope=company'); ?>"
       data-rateb-full-nav="1"><?php echo __('users_scope_company'); ?></a>
    <?php } ?>
    <a class="btn btn-outline-primary<?php echo $scope === 'platform' ? ' active' : ''; ?>"
       href="<?php echo Rateb\App\Core\View::escape($baseList . '?scope=platform'); ?>"
       data-rateb-full-nav="1"><?php echo __('users_scope_platform'); ?></a>
    <a class="btn btn-outline-primary<?php echo $scope === 'all' ? ' active' : ''; ?>"
       href="<?php echo Rateb\App\Core\View::escape($baseList . '?scope=all'); ?>"
       data-rateb-full-nav="1"><?php echo __('users_scope_all'); ?></a>
</div>
<?php }
if (!empty($listHelp)) { ?>
<div class="alert alert-info py-2 mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape($listHelp); ?>
</div>
<?php }
Rateb\App\Core\View::partial('crud-index', get_defined_vars());
?>
