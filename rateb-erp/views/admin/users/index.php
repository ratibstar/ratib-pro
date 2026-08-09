<?php
/** @var string|null $listHelp */
if (!empty($listHelp)) { ?>
<div class="alert alert-info py-2 mb-3" role="status">
    <?php echo Rateb\App\Core\View::escape($listHelp); ?>
</div>
<?php }
Rateb\App\Core\View::partial('crud-index', get_defined_vars());
?>
