<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

admin_require_permission('campaigns.view');

$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$page = admin_page();
$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(c.title LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\')";
    $like = admin_like(mb_substr($q, 0, 120));
    $params[] = $like;
    $params[] = $like;
}
if (in_array($status, campaign_status_values(), true)) {
    $where[] = 'c.status = ?';
    $params[] = $status;
}
$sqlWhere = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));
$count = $pdo->prepare("SELECT COUNT(*) FROM campaigns c INNER JOIN users u ON u.id = c.user_id $sqlWhere");
$count->execute($params);
$total = (int) $count->fetchColumn();
$page = admin_enforce_page_in_range('/admin/campaigns.php', $page, $total);
$list = $pdo->prepare("SELECT c.id, c.title, c.status, c.created_at, u.email, b.campaign_language, b.resolved_language FROM campaigns c INNER JOIN users u ON u.id = c.user_id LEFT JOIN campaign_briefs b ON b.campaign_id = c.id $sqlWhere ORDER BY c.id DESC LIMIT " . admin_per_page() . ' OFFSET ' . admin_offset($page));
$list->execute($params);
$rows = $list->fetchAll();

$canManage = admin_actor_has($adminActor, 'campaigns.manage');
$pageReturn = '/admin/campaigns.php?q=' . rawurlencode($q) . '&status=' . rawurlencode($status) . '&page=' . $page;
admin_start(admin_t('Campaigns', 'الحملات'));
admin_customers_nav_toolbar($adminActor);
admin_panel_open(admin_t('All campaigns', 'كل الحملات'), admin_t('Open a row to inspect content and media.', 'افتح الصف لمراجعة المحتوى والوسائط.'));
echo '<form class="search" method="get"><input name="q" value="' . admin_h($q) . '" placeholder="' . admin_h(admin_t('Title or owner email', 'العنوان أو بريد المالك')) . '">';
echo '<select name="status"><option value="">' . admin_h(admin_t('Any status', 'كل الحالات')) . '</option>';
foreach (campaign_status_values() as $value) {
    $label = admin_campaign_status_label($value);
    echo '<option value="' . admin_h($value) . '"' . ($status === $value ? ' selected' : '') . '>' . admin_h($label) . '</option>';
}
echo '</select><button type="submit" class="btn primary">' . admin_h(admin_t('Search', 'بحث')) . '</button></form>';
admin_bulk_flash_from_query();
$formId = admin_bulk_form_open('campaigns', $pageReturn);
admin_crud_toolbar([
    'screen' => 'campaigns',
    'can_manage' => $canManage,
    'form_id' => $formId,
    'add_enabled' => false,
]);
admin_table_open();
echo '<tr>';
admin_th_select($canManage);
echo '<th>' . admin_h(admin_t('Title', 'العنوان')) . '</th><th>' . admin_h(admin_t('Owner', 'المالك')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Language', 'اللغة')) . '</th><th>' . admin_h(admin_t('Created', 'التاريخ')) . '</th><th>' . admin_h(admin_th_actions()) . '</th></tr>';
foreach ($rows as $row) {
    $language = (string) ($row['campaign_language'] ?? '');
    if ((string) ($row['resolved_language'] ?? '') !== '') {
        $language .= ' → ' . (string) $row['resolved_language'];
    }
    $st = (string) $row['status'];
    $cid = (int) $row['id'];
    $editHref = '/admin/campaign.php?id=' . $cid;
    echo '<tr>';
    admin_td_select($cid, $editHref, $canManage);
    echo '<td>' . admin_h((string) $row['title']) . '</td><td>' . admin_h((string) $row['email']) . '</td>';
    echo '<td><span class="pill' . admin_pill_for_status($st) . '">' . admin_h(admin_campaign_status_label($st)) . '</span></td>';
    echo '<td>' . admin_h($language) . '</td><td>' . admin_h((string) $row['created_at']) . '</td><td>';
    admin_crud_row_actions($editHref, $editHref, $canManage, $cid);
    echo '</td></tr>';
}
if ($rows === []) {
    admin_table_empty_row($canManage ? 7 : 6, admin_t('No campaigns match your filters.', 'لا حملات تطابق التصفية.'));
}
admin_table_close();
$base = '/admin/campaigns.php?q=' . rawurlencode($q) . '&status=' . rawurlencode($status) . '&page=';
admin_pager($page, $total, $base);
admin_panel_close();
admin_bulk_form_close();
admin_end();