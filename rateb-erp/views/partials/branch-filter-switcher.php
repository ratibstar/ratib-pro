<?php declare(strict_types=1);
$branches = $branches ?? [];
$activeFilter = (int) ($activeFilter ?? 0);
if ($branches === [] || !function_exists('rateb_branch_access_all') || !rateb_branch_access_all()) {
    return;
}
$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
$query = $_GET ?? [];
unset($query['active_branch_id'], $query['branch_filter']);
?>
<form method="get" action="<?php echo Rateb\App\Core\View::escape($currentPath); ?>" class="d-flex align-items-center gap-2 flex-wrap">
    <?php foreach ($query as $k => $v) {
        if (is_array($v)) {
            continue;
        } ?>
    <input type="hidden" name="<?php echo Rateb\App\Core\View::escape((string) $k); ?>" value="<?php echo Rateb\App\Core\View::escape((string) $v); ?>">
    <?php } ?>
    <label class="form-label mb-0 small text-muted"><?php echo __('branch_filter'); ?>:</label>
    <select name="active_branch_id" class="form-select form-select-sm" style="width:auto;min-width:12rem" onchange="this.form.submit()">
        <option value="all" <?php echo $activeFilter < 1 ? 'selected' : ''; ?>><?php echo __('branch_filter_all'); ?></option>
        <?php foreach ($branches as $b) { ?>
        <option value="<?php echo (int) ($b['id'] ?? 0); ?>" <?php echo $activeFilter === (int) ($b['id'] ?? 0) ? 'selected' : ''; ?>><?php echo Rateb\App\Core\View::escape((string) ($b['name'] ?? '')); ?></option>
        <?php } ?>
    </select>
</form>
