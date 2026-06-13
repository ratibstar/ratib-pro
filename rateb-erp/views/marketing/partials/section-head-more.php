<?php
/** @var string $sectionTitle */
/** @var string $moreUrl */
/** @var string|null $moreLabel */
$moreLabel = $moreLabel ?? __('cms_more');
?>
<div class="rateb-mkt-section-head">
    <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape($sectionTitle); ?></h2>
    <a href="<?php echo $moreUrl; ?>" class="rateb-mkt-more-link">
        <span class="rateb-mkt-more-icon" aria-hidden="true"><i class="fas fa-circle-plus"></i></span>
        <span><?php echo Rateb\App\Core\View::escape($moreLabel); ?></span>
    </a>
</div>
