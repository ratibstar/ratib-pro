<?php
declare(strict_types=1);

use Rateb\App\Core\View;
?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3"><?php echo View::escape((string) ($title ?? __('marketplace_providers'))); ?></h1>
    <?php if (!empty($phase_note)): ?>
        <div class="alert alert-info"><?php echo View::escape((string) $phase_note); ?></div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th><?php echo View::escape(__('code')); ?></th><th><?php echo View::escape(__('name')); ?></th><th><?php echo View::escape(__('status')); ?></th></tr></thead>
            <tbody>
            <?php if (($items ?? []) === []): ?>
                <tr><td colspan="3" class="text-muted"><?php echo View::escape(__('no_records')); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
