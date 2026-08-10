<?php
/** @var string $rbacScope platform|company */
/** @var string $rbacBaseUrl */
/** @var int $rbacOpsCompanyId */
$scope = (string) ($rbacScope ?? 'platform');
$base = (string) ($rbacBaseUrl ?? '');
$opsId = (int) ($rbacOpsCompanyId ?? 0);
$isSa = function_exists('rateb_is_super_admin') && rateb_is_super_admin();
if (!$isSa || $base === '') {
    return;
}
$platformUrl = function_exists('rateb_url_query')
    ? rateb_url_query($base, ['scope' => 'platform'])
    : ($base . (str_contains($base, '?') ? '&' : '?') . 'scope=platform');
$companyUrl = function_exists('rateb_url_query')
    ? rateb_url_query($base, ['scope' => 'company'])
    : ($base . (str_contains($base, '?') ? '&' : '?') . 'scope=company');
?>
<div class="btn-group btn-group-sm mb-3" role="group" aria-label="<?php echo Rateb\App\Core\View::escape(__('rbac_scope_label')); ?>">
    <a class="btn btn-outline-primary<?php echo $scope === 'platform' ? ' active' : ''; ?>"
       href="<?php echo Rateb\App\Core\View::escape($platformUrl); ?>"
       data-rateb-full-nav="1"><?php echo __('rbac_scope_platform'); ?></a>
    <a class="btn btn-outline-primary<?php echo $scope === 'company' ? ' active' : ''; ?><?php echo $opsId < 1 ? ' disabled' : ''; ?>"
       href="<?php echo $opsId > 0 ? Rateb\App\Core\View::escape($companyUrl) : '#'; ?>"
       data-rateb-full-nav="1"
       <?php echo $opsId < 1 ? ' aria-disabled="true" tabindex="-1"' : ''; ?>><?php echo __('rbac_scope_company'); ?></a>
</div>
<?php if ($scope === 'platform') { ?>
<div class="alert alert-info py-2 small mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape(__('rbac_scope_platform_help')); ?>
</div>
<?php } elseif ($opsId < 1) { ?>
<div class="alert alert-warning py-2 small mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape(__('rbac_scope_company_need_ops')); ?>
</div>
<?php } else { ?>
<div class="alert alert-secondary py-2 small mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape(__('rbac_scope_company_help')); ?>
</div>
<?php } ?>
