<?php
declare(strict_types=1);

/** @param list<array{href?:string,label:string,class?:string}> $actions */
function hr_toolbar(array $actions): void
{
    if ($actions === []) {
        return;
    }
    echo '<div class="page-toolbar">';
    foreach ($actions as $action) {
        $class = 'btn ' . ($action['class'] ?? 'quiet');
        echo '<a class="' . hr_h(trim($class)) . '" href="' . hr_h((string) ($action['href'] ?? '#')) . '">' . hr_h((string) $action['label']) . '</a>';
    }
    echo '</div>';
}

function hr_panel_open(string $title, ?string $hint = null): void
{
    echo '<article class="panel"><header class="panel-head"><h2>' . hr_h($title) . '</h2>';
    if ($hint !== null && $hint !== '') {
        echo '<p class="panel-hint">' . hr_h($hint) . '</p>';
    }
    echo '</header><div class="panel-body">';
}

function hr_panel_close(): void
{
    echo '</div></article>';
}

function hr_th_actions(): string
{
    return hr_t('Actions', 'التحكم');
}
