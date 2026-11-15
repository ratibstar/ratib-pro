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
        $class = 'btn ' . ($action['class'] ?? 'quiet');
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

function admin_table_open(): void
{
    echo '<div class="table-wrap"><table>';
}

function admin_table_close(): void
{
    echo '</table></div>';
}

function admin_table_empty_row(int $cols, string $message): void
{
    echo '<tr><td colspan="' . (int) $cols . '"><p class="empty-state" style="margin:8px 0;border:0;padding:16px">' . admin_h($message) . '</p></td></tr>';
}

/** @param 'ok'|'bad'|'' $type */
function admin_flash_box(string $code, string $type = ''): void
{
    if ($code === '') {
        return;
    }
    $cls = 'flash';
    if ($type === 'ok') {
        $cls .= ' ok';
    } elseif ($type === 'bad') {
        $cls .= ' bad';
    }
    echo '<p class="' . admin_h($cls) . '">' . admin_h(admin_flash($code)) . '</p>';
}

function admin_th_actions(): string
{
    return admin_t('Actions', 'التحكم');
}

function admin_nav_glyph(string $href): string
{
    $path = parse_url($href, PHP_URL_PATH) ?: $href;
  return match ($path) {
        '/admin/', '/admin/index.php' => '▣',
        '/admin/users.php' => '◎',
        '/admin/hr-overview.php' => '◈',
        '/admin/finance.php' => '◆',
        '/admin/finance-customer.php' => '◆',
        '/admin/payments.php' => '◇',
        '/admin/campaigns.php' => '▤',
        '/admin/media.php' => '▥',
        '/admin/subscriptions.php' => '▧',
        '/admin/plans.php' => '▨',
        '/admin/usage.php' => '▩',
        '/admin/audit.php' => '▢',
        '/admin/settings.php' => '⚙',
        '/admin/staff.php' => '▶',
        '/admin/roles.php' => '⬡',
        default => '•',
    };
}

function admin_subscription_status_label(string $status): string
{
    return match ($status) {
        'active' => admin_t('Active', 'نشط'),
        'replaced' => admin_t('Replaced', 'مستبدل'),
        'cancelled', 'canceled' => admin_t('Cancelled', 'ملغى'),
        default => $status,
    };
}

function admin_campaign_status_label(string $status): string
{
    return match ($status) {
        'draft' => admin_t('Draft', 'مسودة'),
        'processing' => admin_t('Processing', 'قيد المعالجة'),
        'ready' => admin_t('Ready', 'جاهزة'),
        'failed' => admin_t('Failed', 'فاشلة'),
        default => $status,
    };
}

function admin_payment_status_label(string $status): string
{
    return match ($status) {
        'paid' => admin_t('Paid', 'مدفوع'),
        'pending' => admin_t('Pending', 'معلّق'),
        'failed' => admin_t('Failed', 'فاشل'),
        default => $status,
    };
}

function admin_pill_for_status(string $status): string
{
    $pill = match ($status) {
        'active', 'ready', 'completed', 'approved', 'applied' => 'ok',
        'failed', 'rejected', 'inactive', 'disabled' => 'bad',
        'pending', 'processing', 'draft' => 'warn',
        default => '',
    };
    return $pill !== '' ? ' pill ' . $pill : ' pill';
}