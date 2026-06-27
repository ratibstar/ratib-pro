<?php
/** Pending HR item — approve/reject only in admin oversight. */
$status = (string) ($status ?? '');
if ($status !== 'pending') {
    return;
}
$oversightUrl = function_exists('rateb_url') ? rateb_url('admin/oversight/approvals') : '#';
$canOpenOversight = function_exists('rateb_is_super_admin') && rateb_is_super_admin();
?>
<?php if ($canOpenOversight) { ?>
<a href="<?php echo Rateb\App\Core\View::escape($oversightUrl); ?>" class="badge bg-warning text-dark text-decoration-none"><?php echo __('awaiting_oversight_approval'); ?></a>
<?php } else { ?>
<span class="badge bg-warning text-dark"><?php echo __('awaiting_oversight_approval'); ?></span>
<?php } ?>
