<?php
declare(strict_types=1);
use Rateb\App\Core\View;
$row = $row ?? [];
$dir = function_exists('rateb_is_rtl') && rateb_is_rtl() ? 'rtl' : 'ltr';
?>
<div dir="<?php echo View::escape($dir); ?>">
<h1 class="h4 mb-2"><?php echo View::escape((string) ($row['model_year'] ?? '') . ' ' . (string) ($row['make_name'] ?? '') . ' ' . (string) ($row['model_name'] ?? '')); ?></h1>
<p class="text-muted"><?php echo View::escape(__('auto_us_scope')); ?> <?php echo View::escape((string) ($row['market_scope'] ?? 'US')); ?></p>
<div class="card"><div class="table-responsive"><table class="table mb-0"><tbody>
<?php
$fields = [
    'auto_us_source' => (string) ($row['provider_code'] ?? '') . ' ' . (string) ($row['epa_id'] ?? ''),
    'auto_us_class' => (string) ($row['vclass'] ?? ''),
    'auto_us_fuel' => (string) ($row['fuel_type1'] ?? ''),
    'auto_us_cylinders' => (string) ($row['cylinders'] ?? ''),
    'auto_us_displ' => (string) ($row['displ'] ?? ''),
    'auto_us_trany' => (string) ($row['trany'] ?? ''),
    'auto_us_drive' => (string) ($row['drive'] ?? ''),
    'auto_us_mpg' => (string) ($row['city_mpg'] ?? '') . ' / ' . (string) ($row['highway_mpg'] ?? '') . ' / ' . (string) ($row['comb_mpg'] ?? '') . ' MPG',
    'auto_open_retrieved' => (string) ($row['retrieved_on'] ?? ''),
    'auto_us_license' => (string) ($row['license_evidence'] ?? ''),
];
foreach ($fields as $label => $value): ?>
<tr><th><?php echo View::escape(__($label)); ?></th><td><?php echo View::escape($value); ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<p class="mt-3"><?php echo View::escape((string) ($row['attribution'] ?? '')); ?></p>
</div>
