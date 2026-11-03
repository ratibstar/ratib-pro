<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$phaseNote = (string) ($phase_note ?? '');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($title ?? __('automotive_dashboard'))); ?></h1>
</div>
<?php if ($phaseNote !== ''): ?>
<div class="alert alert-info"><?php echo View::escape($phaseNote); ?></div>
<?php endif; ?>
<div class="d-flex gap-2">
    <a class="btn btn-primary" href="<?php echo rateb_app_url('automotive/parts'); ?>"><?php echo View::escape(__('auto_parts')); ?></a>
    <a class="btn btn-outline-primary" href="<?php echo rateb_app_url('automotive/vehicles'); ?>"><?php echo View::escape(__('auto_vehicles')); ?></a>
</div>
