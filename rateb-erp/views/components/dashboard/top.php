<?php
/** @var string $title */
/** @var string $subtitle */
/** @var array<int, array<string, mixed>> $actions */
$actions = $actions ?? [];
?>
<header class="rdx-top">
    <div class="rdx-top-text">
        <h1><?php echo Rateb\App\Core\View::escape($title); ?></h1>
        <?php if (!empty($subtitle)) { ?>
        <p><?php echo $subtitle; ?></p>
        <?php } ?>
    </div>
    <?php if ($actions !== []) { ?>
    <nav class="rdx-actions" aria-label="<?php echo __('quick_shortcuts'); ?>">
        <?php foreach ($actions as $act) {
            $cls = 'rdx-btn';
            if (!empty($act['primary'])) {
                $cls .= ' rdx-btn--primary';
            } elseif (!empty($act['ghost'])) {
                $cls .= ' rdx-btn--ghost';
            }
            $icon = !empty($act['icon']) ? '<i class="fas ' . Rateb\App\Core\View::escape((string) $act['icon']) . '"></i> ' : '';
            if (!empty($act['form'])) {
                ?>
        <form method="post" action="<?php echo Rateb\App\Core\View::escape((string) $act['href']); ?>" class="d-inline m-0">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape((string) ($act['csrf'] ?? '')); ?>">
            <button type="submit" class="<?php echo $cls; ?>"><?php echo $icon . Rateb\App\Core\View::escape((string) $act['label']); ?></button>
        </form>
                <?php
                continue;
            }
            ?>
        <a href="<?php echo Rateb\App\Core\View::escape((string) $act['href']); ?>" class="<?php echo $cls; ?>"><?php echo $icon . Rateb\App\Core\View::escape((string) $act['label']); ?></a>
        <?php } ?>
    </nav>
    <?php } ?>
</header>
