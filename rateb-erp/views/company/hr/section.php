<?php
$sectionKey = (string) ($sectionKey ?? '');
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => $sectionKey]);
?>
<div class="rateb-card">
    <div class="rateb-card-body text-center py-5">
        <i class="fas fa-users-gear fa-3x text-muted mb-3"></i>
        <h5 class="mb-2"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h5>
        <p class="text-muted mb-0"><?php echo __('hr_coming_soon'); ?></p>
    </div>
</div>
