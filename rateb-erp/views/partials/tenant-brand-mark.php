<?php
$variant = (string) ($variant ?? 'sidebar');
$logoUrl = function_exists('rateb_erp_brand_logo_url') ? rateb_erp_brand_logo_url() : '';
$label = function_exists('rateb_erp_brand_display_name') ? rateb_erp_brand_display_name() : __('rateb_erp');
$esc = static fn (string $v): string => Rateb\App\Core\View::escape($v);
if ($logoUrl !== '') { ?>
<span class="rateb-brand-logo-wrap rateb-brand-logo-wrap--<?php echo $esc($variant); ?>">
    <img class="rateb-brand-logo" src="<?php echo $esc($logoUrl); ?>" alt="<?php echo $esc($label); ?>">
</span>
<?php if ($variant === 'auth') { ?>
<h2 class="visually-hidden"><?php echo $esc($label); ?></h2>
<?php } ?>
<?php } elseif ($variant === 'auth') { ?>
<i class="fas fa-hospital fa-2x text-primary mb-2"></i>
<h2 class="h4"><?php echo $esc($label); ?></h2>
<?php } else { ?>
<i class="fas fa-hospital"></i>
<span><?php echo $esc($label); ?></span>
<?php } ?>
