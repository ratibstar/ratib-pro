<?php
declare(strict_types=1);
/** @var string|null $builderHtml */
/** @var bool $isPreview */
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-blocks.css'), ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!empty($isPreview)) { ?>
<div class="wb-preview-banner"><?php echo htmlspecialchars(__('website_preview_banner') ?: 'Preview — not published', ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
<div class="wb-page">
    <?php echo $builderHtml ?? ''; ?>
</div>
