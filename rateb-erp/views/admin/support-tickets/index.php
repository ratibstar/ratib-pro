<?php
if (!empty($listHelp)) { ?>
<div class="alert alert-secondary py-2 small mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape((string) $listHelp); ?>
</div>
<?php }
Rateb\App\Core\View::partial('crud-index', get_defined_vars());
?>
