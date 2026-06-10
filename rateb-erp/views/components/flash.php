<?php
$success = Rateb\App\Core\SessionManager::flash('success');
$error = Rateb\App\Core\SessionManager::flash('error');
?>
<?php if ($success) { ?>
<div class="alert alert-success rateb-flash alert-dismissible fade show" role="alert">
    <?php echo Rateb\App\Core\View::escape($success); ?>
    <button type="button" class="btn-close" aria-label="Close"></button>
</div>
<?php } ?>
<?php if ($error) { ?>
<div class="alert alert-danger rateb-flash alert-dismissible fade show" role="alert">
    <?php echo Rateb\App\Core\View::escape($error); ?>
    <button type="button" class="btn-close" aria-label="Close"></button>
</div>
<?php } ?>
