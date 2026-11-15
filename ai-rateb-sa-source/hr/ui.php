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

function hr_table_open(): void
{
    echo '<div class="table-wrap"><table>';
}

function hr_table_close(): void
{
    echo '</table></div>';
}

function hr_table_empty_row(int $cols, string $message): void
{
    echo '<tr><td colspan="' . (int) $cols . '"><p class="empty-state" style="margin:8px 0;border:0;padding:16px">' . hr_h($message) . '</p></td></tr>';
}

function hr_th_actions(): string
{
    return hr_t('Actions', 'التحكم');
}

function hr_employee_status_label(string $status): string
{
    return match ($status) {
        'active' => hr_t('Active', 'نشط'),
        'inactive' => hr_t('Inactive', 'غير نشط'),
        'terminated' => hr_t('Terminated', 'منتهي الخدمة'),
        default => $status,
    };
}

function hr_leave_type_label(string $type): string
{
    return match ($type) {
        'annual' => hr_t('Annual', 'سنوية'),
        'sick' => hr_t('Sick', 'مرضية'),
        'unpaid' => hr_t('Unpaid', 'بدون راتب'),
        default => $type,
    };
}

function hr_leave_status_label(string $status): string
{
    return match ($status) {
        'pending' => hr_t('Pending', 'معلّق'),
        'approved' => hr_t('Approved', 'موافق'),
        'rejected' => hr_t('Rejected', 'مرفوض'),
        default => $status,
    };
}

function hr_payroll_status_label(string $status): string
{
    return match ($status) {
        'draft' => hr_t('Draft', 'مسودة'),
        'final' => hr_t('Final', 'نهائي'),
        'paid' => hr_t('Paid', 'مُسدَّد'),
        default => $status,
    };
}

function hr_pill_class(string $status): string
{
    return match ($status) {
        'active', 'approved' => 'ok',
        'rejected', 'terminated', 'inactive' => 'bad',
        'pending' => 'warn',
        default => '',
    };
}

/** @param 'ok'|'bad'|'' $type */
function hr_flash_box(string $code, string $type = 'ok'): void
{
    if ($code === '') {
        return;
    }
    $cls = 'flash';
    if ($type === 'bad' || $code === 'invalid' || $code === 'forbidden') {
        $cls .= ' bad';
    } else {
        $cls .= ' ok';
    }
    echo '<p class="' . hr_h($cls) . '">' . hr_h(hr_flash($code)) . '</p>';
}