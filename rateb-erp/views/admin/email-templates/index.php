<?php
/** @var string|null $listHelp */
if (!empty($listHelp)) { ?>
<div class="alert alert-info rateb-ar-text mb-3" role="status">
    <i class="fas fa-info-circle ms-1"></i>
    <?php echo Rateb\App\Core\View::escape($listHelp); ?>
</div>
<?php }
Rateb\App\Core\View::partial('crud-index', get_defined_vars());
