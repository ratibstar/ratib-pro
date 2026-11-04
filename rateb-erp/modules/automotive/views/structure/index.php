<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$models = $models ?? [];
$generations = $generations ?? [];
$engines = $engines ?? [];
$transmissions = $transmissions ?? [];
$diagrams = $diagrams ?? [];
$canManage = !empty($can_manage);
$localeAr = function_exists('rateb_locale') && rateb_locale() === 'ar';
$label = static function (array $row) use ($localeAr): string {
    return $localeAr ? (string) $row['name_ar'] : (string) $row['name_en'];
};
?>
<h1 class="h4 mb-3"><?php echo View::escape((string) ($title ?? __('auto_epc_structure'))); ?></h1>
<div class="row g-3">
<?php
$blocks = [
    ['models', $models, 'auto_epc_models', 'reference/structure/models'],
    ['generations', $generations, 'auto_epc_generations', 'reference/structure/generations'],
    ['engines', $engines, 'auto_epc_engines', 'reference/structure/engines'],
    ['transmissions', $transmissions, 'auto_epc_transmissions', 'reference/structure/transmissions'],
    ['diagrams', $diagrams, 'auto_epc_diagrams', 'reference/structure/diagrams'],
];
foreach ($blocks as [$key, $rows, $heading, $action]):
?>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h2 class="h6"><?php echo View::escape(__($heading)); ?></h2>
        <?php if ($rows === []): ?>
            <p class="text-muted"><?php echo View::escape(__('auto_epc_empty')); ?></p>
        <?php else: ?>
            <ul><?php foreach ($rows as $row): ?><li><?php echo View::escape($key === 'diagrams' ? (string) $row['assembly_code'] : $label($row)); ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($canManage): ?>
        <form method="post" action="<?php echo rateb_app_url('automotive/' . $action); ?>">
            <?php echo rateb_csrf_field(); ?>
            <?php if ($key === 'models'): ?>
                <input class="form-control mb-2" name="vehicle_brand_id" required placeholder="<?php echo View::escape(__('auto_epc_record_id')); ?>">
            <?php elseif ($key === 'generations'): ?>
                <input class="form-control mb-2" name="model_id" required placeholder="<?php echo View::escape(__('auto_epc_record_id')); ?>">
                <input class="form-control mb-2" name="market_id" required placeholder="<?php echo View::escape(__('auto_epc_markets')); ?>">
                <input class="form-control mb-2" name="year_from" required placeholder="<?php echo View::escape(__('auto_epc_year_from')); ?>">
                <input class="form-control mb-2" name="year_to" required placeholder="<?php echo View::escape(__('auto_epc_year_to')); ?>">
            <?php elseif ($key === 'diagrams'): ?>
                <input class="form-control mb-2" name="system_code" required placeholder="<?php echo View::escape(__('auto_epc_system')); ?>">
                <input class="form-control mb-2" name="assembly_code" required placeholder="<?php echo View::escape(__('auto_epc_assembly')); ?>">
                <input class="form-control mb-2" name="version_no" required placeholder="<?php echo View::escape(__('auto_epc_version')); ?>">
                <textarea class="form-control mb-2" name="svg_text" placeholder="<?php echo View::escape(__('auto_epc_svg')); ?>"></textarea>
            <?php else: ?>
                <input class="form-control mb-2" name="code" required placeholder="<?php echo View::escape(__('auto_epc_code')); ?>">
            <?php endif; ?>
            <?php if ($key !== 'diagrams'): ?>
                <input class="form-control mb-2" name="name_en" required placeholder="<?php echo View::escape(__('auto_epc_name_en')); ?>">
                <input class="form-control mb-2" name="name_ar" required placeholder="<?php echo View::escape(__('auto_epc_name_ar')); ?>">
            <?php endif; ?>
            <button class="btn btn-primary btn-sm" type="submit"><?php echo View::escape(__('save')); ?></button>
        </form>
        <?php endif; ?>
    </div></div></div>
<?php endforeach; ?>
</div>
