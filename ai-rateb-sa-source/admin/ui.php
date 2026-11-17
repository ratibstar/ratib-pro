<?php
declare(strict_types=1);

/**
 * Shared admin UI helpers (presentation only).
 */

/** Permission-filtered quick links (mirrors sidebar) for every admin screen. */
function admin_global_quick_nav(array $actor): void
{
    if (!function_exists('admin_nav_items') || !function_exists('admin_actor_has')) {
        return;
    }
    $actions = [];
    $seen = [];
    foreach (admin_nav_items() as $item) {
        if (!admin_actor_has($actor, $item['permission'])) {
            continue;
        }
        $href = (string) $item['href'];
        if (isset($seen[$href])) {
            continue;
        }
        $seen[$href] = true;
        $here = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH) ?: '/admin';
        $on = admin_nav_on($here, $href);
        $actions[] = [
            'href' => $href,
            'label' => admin_t((string) $item['en'], (string) $item['ar']),
            'class' => $on ? 'primary' : 'quiet',
        ];
    }
    $actions[] = [
        'href' => admin_studio_url(),
        'label' => admin_t('Customer studio', 'استوديو العملاء'),
        'class' => 'quiet',
    ];
    $actions[] = [
        'href' => admin_public_site_url(),
        'label' => admin_t('Public site', 'الموقع العام'),
        'class' => 'quiet',
        'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
    ];
    echo '<nav class="global-quick-nav" aria-label="' . admin_h(admin_t('Console navigation', 'تنقل وحدة التحكم')) . '">';
    admin_toolbar($actions);
    echo '</nav>';
}

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
        '/admin', '/admin/', '/admin/index.php' => '▣',
        '/admin/users.php' => '◎',
        '/admin/hr-overview.php' => '◈',
        '/admin/finance.php' => '◆',
        '/admin/finance-export.php' => '▣',
        '/admin/finance-customer.php' => '◆',
        '/admin/hr-customer.php' => '◈',
        '/admin/payments.php' => '◇',
        '/admin/refunds.php' => '◇',
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

/**
 * Standard list controls: add, edit, delete, bulk.
 *
 * @param array{
 *   screen:string,
 *   can_manage?:bool,
 *   form_id?:string,
 *   add_href?:string|null,
 *   add_enabled?:bool,
 *   bulk_actions?:list<array{value:string,label:string}>,
 *   links?:list<array{href:string,label:string,class?:string}>
 * } $opts
 */
function admin_crud_toolbar(array $opts): void
{
    $screen = (string) $opts['screen'];
    $minimal = (bool) ($opts['minimal'] ?? false);
    $formId = (string) ($opts['form_id'] ?? ('admin-bulk-' . $screen));

    echo '<div class="page-toolbar crud-toolbar" data-admin-crud-toolbar data-admin-form="' . admin_h($formId) . '">';
    foreach ($opts['links'] ?? [] as $link) {
        $class = 'btn ' . ($link['class'] ?? 'quiet');
        $extra = '';
        foreach ($link['attrs'] ?? [] as $k => $v) {
            $extra .= ' ' . admin_h((string) $k) . '="' . admin_h((string) $v) . '"';
        }
        echo '<a class="' . admin_h(trim($class)) . '" href="' . admin_h((string) $link['href']) . '"' . $extra . '>' . admin_h((string) $link['label']) . '</a>';
    }
    if ($minimal) {
        echo '</div>';
        return;
    }

    $canManage = (bool) ($opts['can_manage'] ?? false);
    $addHref = $opts['add_href'] ?? null;
    $addEnabled = $canManage && ($opts['add_enabled'] ?? true) && is_string($addHref) && $addHref !== '';
    $bulkActions = $opts['bulk_actions'] ?? [];
    if ($bulkActions === [] && function_exists('admin_bulk_actions_for_screen')) {
        $bulkActions = admin_bulk_actions_for_screen($screen);
    }
    global $adminActor;
    if (
        $bulkActions !== []
        && function_exists('admin_actor_is_owner')
        && !admin_actor_is_owner(is_array($adminActor ?? null) ? $adminActor : [])
    ) {
        $bulkActions = array_values(array_filter(
            $bulkActions,
            static fn (array $item): bool => (string) ($item['value'] ?? '') !== 'delete'
        ));
    }
    $hasBulk = $canManage && $bulkActions !== [];

    echo '<span class="crud-group">';
    if ($addEnabled) {
        echo '<a class="btn primary" href="' . admin_h($addHref) . '">' . admin_h(admin_t('Add', 'إضافة')) . '</a>';
    } else {
        echo '<span class="btn disabled" title="' . admin_h(admin_t('Not available on this screen', 'غير متاح في هذه الشاشة')) . '">' . admin_h(admin_t('Add', 'إضافة')) . '</span>';
    }
    echo '<button type="button" class="btn quiet" data-admin-crud="edit" disabled>' . admin_h(admin_t('Edit', 'تعديل')) . '</button>';
    echo '<button type="button" class="btn danger quiet" data-admin-crud="delete" disabled>' . admin_h(admin_t('Delete', 'حذف')) . '</button>';
    if ($hasBulk) {
        echo '<select class="bulk-action" data-admin-bulk-picker aria-label="' . admin_h(admin_t('Bulk action', 'إجراء جماعي')) . '">';
        echo '<option value="">' . admin_h(admin_t('Bulk…', 'جماعي…')) . '</option>';
        foreach ($bulkActions as $bulk) {
            echo '<option value="' . admin_h((string) $bulk['value']) . '">' . admin_h((string) $bulk['label']) . '</option>';
        }
        echo '</select>';
        echo '<button type="button" class="btn primary" data-admin-bulk-run form="' . admin_h($formId) . '">' . admin_h(admin_t('Apply bulk', 'تنفيذ جماعي')) . '</button>';
    } else {
        echo '<span class="btn disabled">' . admin_h(admin_t('Bulk', 'جماعي')) . '</span>';
    }
    echo '</span></div>';
}

function admin_bulk_form_open(string $screen, string $returnPath): string
{
    $id = 'admin-bulk-' . preg_replace('/[^a-z0-9_-]/', '', $screen);
    echo '<form id="' . admin_h($id) . '" class="admin-bulk-form" method="post" action="/admin/bulk.php" data-admin-list>';
    echo '<input type="hidden" name="csrf_token" value="' . admin_h(csrf_token()) . '">';
    echo '<input type="hidden" name="screen" value="' . admin_h($screen) . '">';
    echo '<input type="hidden" name="return" value="' . admin_h($returnPath) . '">';
    echo '<input type="hidden" name="bulk_action" value="" data-admin-bulk-action-field>';
    echo '<input type="hidden" name="bulk_reason" value="bulk" data-admin-bulk-reason-field>';
    return $id;
}

function admin_bulk_form_close(): void
{
    echo '</form>';
}

function admin_th_select(bool $enabled): void
{
    if (!$enabled) {
        echo '<th class="sel-col" aria-hidden="true"></th>';
        return;
    }
    echo '<th class="sel-col"><input type="checkbox" data-admin-select-all aria-label="' . admin_h(admin_t('Select all', 'تحديد الكل')) . '"></th>';
}

function admin_td_select(int|string $id, string $editHref, bool $enabled): void
{
    if (!$enabled) {
        echo '<td class="sel-col"></td>';
        return;
    }
    echo '<td class="sel-col"><input type="checkbox" name="ids[]" value="' . admin_h((string) $id) . '" data-admin-row-select data-edit-href="' . admin_h($editHref) . '"></td>';
}

function admin_crud_row_actions(string $editHref, string $viewHref, bool $canManage, int|string $rowId, bool $rowDelete = true): void
{
    echo '<div class="table-actions">';
    if ($viewHref !== '' && $viewHref !== $editHref) {
        echo '<a class="btn small quiet" href="' . admin_h($viewHref) . '">' . admin_h(admin_t('View', 'عرض')) . '</a>';
    }
    echo '<a class="btn small primary" href="' . admin_h($editHref) . '">' . admin_h(admin_t('Edit', 'تعديل')) . '</a>';
    if ($canManage && $rowDelete) {
        echo '<button type="button" class="btn small danger" data-admin-row-delete data-row-id="' . admin_h((string) $rowId) . '">' . admin_h(admin_t('Delete', 'حذف')) . '</button>';
    }
    echo '</div>';
}

function admin_bulk_flash_from_query(): void
{
    $code = (string) ($_GET['bulk'] ?? '');
    if ($code === '') {
        return;
    }
    $n = (int) ($_GET['n'] ?? 0);
    $msg = match ($code) {
        'applied' => admin_t('Bulk update applied to {n} row(s).', 'طُبّق التحديث الجماعي على {n} صف.') ,
        'reason' => admin_t('Enter a reason (3+ chars) for bulk changes.', 'اكتب سببًا (٣+ أحرف) للتغيير الجماعي.'),
        'owner_only' => admin_t('Only the owner can run this finance delete.', 'المالك فقط ينفّذ هذا الحذف المالي.'),
        'missing' => admin_t('No eligible rows were updated.', 'لم يُحدَّث أي صف مؤهل.'),
        default => admin_t('Bulk action was not completed.', 'لم يكتمل الإجراء الجماعي.'),
    };
    $msg = str_replace('{n}', (string) $n, $msg);
    $type = $code === 'applied' ? 'ok' : 'bad';
    echo '<p class="flash ' . admin_h($type) . '">' . admin_h($msg) . '</p>';
}