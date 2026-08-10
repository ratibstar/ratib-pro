<?php
if (!empty($rbacScope) && function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
    Rateb\App\Core\View::partial('rbac-scope-tabs', [
        'rbacScope' => $rbacScope,
        'rbacBaseUrl' => $rbacBaseUrl ?? rateb_url($routePrefix ?? 'admin/roles'),
        'rbacOpsCompanyId' => (int) ($rbacOpsCompanyId ?? 0),
    ]);
}
if (!empty($listHelp)) { ?>
<div class="alert alert-secondary py-2 small mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape((string) $listHelp); ?>
</div>
<?php }
Rateb\App\Core\View::partial('crud-index', get_defined_vars());
?>
