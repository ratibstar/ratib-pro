<?php
declare(strict_types=1);

/** @var list<array{label:string,url:?string}> $crumbs */
?>
<nav class="hc-breadcrumb" aria-label="<?php echo Rateb\App\Core\View::escape(__('help_breadcrumb')); ?>">
    <ol>
        <?php foreach ($crumbs as $i => $crumb) {
            $isLast = $i === array_key_last($crumbs);
            $label = (string) ($crumb['label'] ?? '');
            $url = $crumb['url'] ?? null;
            ?>
        <li>
            <?php if (!$isLast && is_string($url) && $url !== '') { ?>
            <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Rateb\App\Core\View::escape($label); ?></a>
            <?php } else { ?>
            <span aria-current="page"><?php echo Rateb\App\Core\View::escape($label); ?></span>
            <?php } ?>
        </li>
        <?php } ?>
    </ol>
</nav>
