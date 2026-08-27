<?php
if (!empty($listHelp)) { ?>
<div class="alert alert-secondary py-2 small mb-3" role="status" data-rateb-uncached-page="1">
    <?php echo Rateb\App\Core\View::escape((string) $listHelp); ?>
</div>
<?php }
Rateb\App\Core\View::partial('crud-index', get_defined_vars());

$pollUrl = function_exists('rateb_url') ? rateb_url('admin/api/support-ticket-alerts') : '';
?>
<div data-support-tickets-index="1" hidden></div>
<script>
window.__RATEB_SUPPORT_TICKETS_INDEX__ = 1;
window.__RATEB_SUPPORT_TICKETS_POLL__ = <?php echo json_encode($pollUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo Rateb\App\Core\View::escape(rateb_asset('js/support-ticket-table-live.js')); ?>" defer></script>
