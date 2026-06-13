<div class="text-center py-4">
    <i class="fas fa-qrcode fa-3x text-muted mb-3"></i>
    <h5><?php echo __('scan_not_found'); ?></h5>
    <p class="font-monospace text-muted"><?php echo Rateb\App\Core\View::escape((string) ($code ?? '')); ?></p>
    <a href="<?php echo rateb_url('login'); ?>" class="btn btn-outline-secondary btn-sm mt-2"><?php echo __('login'); ?></a>
</div>
