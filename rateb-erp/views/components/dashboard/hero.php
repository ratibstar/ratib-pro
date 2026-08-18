<?php
/** @var string $title */
/** @var string $subtitle */
/** @var string $tag */
/** @var array<int, array<string, mixed>> $actions */
$actions = $actions ?? [];
$tag = $tag ?? __('dashboard');
?>
<header class="cm-bar">
    <div class="cm-bar__ctx">
        <span class="cm-bar__tag"><?php echo Rateb\App\Core\View::escape($tag); ?></span>
        <h1><?php echo Rateb\App\Core\View::escape($title); ?></h1>
        <?php if (!empty($subtitle)) { ?>
        <p class="cm-bar__sub"><?php echo $subtitle; ?></p>
        <?php } ?>
    </div>
    <?php if ($actions !== []) { ?>
    <nav class="cm-bar__acts" aria-label="<?php echo __('quick_shortcuts'); ?>">
        <?php foreach ($actions as $act) {
            $cls = 'cm-btn';
            if (!empty($act['primary'])) {
                $cls .= ' cm-btn--main';
            }
            $icon = !empty($act['icon']) ? '<i class="fas ' . Rateb\App\Core\View::escape((string) $act['icon']) . '"></i>' : '';
            $label = Rateb\App\Core\View::escape((string) ($act['label'] ?? ''));
            if (!empty($act['form_get'])) {
                $fields = is_array($act['fields'] ?? null) ? $act['fields'] : [];
                ?>
        <form method="get" action="<?php echo Rateb\App\Core\View::escape((string) $act['href']); ?>" class="d-inline m-0 rateb-pos-register-open" data-pos-open-register="1">
            <?php foreach ($fields as $fieldName => $fieldValue) { ?>
            <input type="hidden" name="<?php echo Rateb\App\Core\View::escape((string) $fieldName); ?>" value="<?php echo Rateb\App\Core\View::escape((string) $fieldValue); ?>">
            <?php } ?>
            <button type="submit" class="<?php echo $cls; ?>"><?php echo $icon; ?><span class="cm-btn__lbl"><?php echo $label; ?></span></button>
        </form>
                <?php
                continue;
            }
            if (!empty($act['form'])) {
                ?>
        <form method="post" action="<?php echo Rateb\App\Core\View::escape((string) $act['href']); ?>" class="d-inline m-0">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape((string) ($act['csrf'] ?? '')); ?>">
            <button type="submit" class="<?php echo $cls; ?>"><?php echo $icon; ?><span class="cm-btn__lbl"><?php echo $label; ?></span></button>
        </form>
                <?php
                continue;
            }
            ?>
        <a href="<?php echo Rateb\App\Core\View::escape((string) $act['href']); ?>" class="<?php echo $cls; ?>" title="<?php echo $label; ?>"><?php echo $icon; ?><span class="cm-btn__lbl"><?php echo $label; ?></span></a>
        <?php } ?>
    </nav>
    <?php } ?>
</header>
