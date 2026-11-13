<?php
declare(strict_types=1);

/**
 * Shared admin UI helpers (presentation only).
 */

/** @param list<array{href?:string,label:string,class?:string,submit?:bool,form?:string,attrs?:array<string,string>}> $actions */
function admin_toolbar(array $actions): void
{
    if ($actions === []) {
        return;
    }
    echo '<div class="page-toolbar">';
    foreach ($actions as $action) {
        $class = 'btn ' . ($action['class'] ?? '');
        $label = (string) $action['label'];
        if (!empty($action['submit'])) {
            $form = (string) ($action['form'] ?? '');
            echo '<button type="submit" class="' . admin_h(trim($class)) . '"' . ($form !== '' ? ' form="' . admin_h($form) . '"' : '') . '>' . admin_h($label) . '</button>';
            continue;
        }
        $href = (string) ($action['href'] ?? '#');
        $extra = '';
        foreach ($action['attrs'] ?? [] as $k => $v) {
            $extra .= ' ' . admin_h((string) $k) . '="' . admin_h((string) $v) . '"';
        }
        echo '<a class="' . admin_h(trim($class)) . '" href="' . admin_h($href) . '"' . $extra . '>' . admin_h($label) . '</a>';
    }
    echo '</div>';
}

function admin_panel_open(string $title, ?string $hint = null): void
{
    echo '<article class="panel"><header class="panel-head"><h2>' . admin_h($title) . '</h2>';
    if ($hint !== null && $hint !== '') {
        echo '<p class="panel-hint">' . admin_h($hint) . '</p>';
    }
    echo '</header><div class="panel-body">';
}

function admin_panel_close(): void
{
    echo '</div></article>';
}

function admin_th_actions(): string
{
    return admin_t('Actions', 'التحكم');
}
