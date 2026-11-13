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
$list = $pdo->prepare("SELECT c.id, c.title, c.status, c.created_at, u.email, b.campaign_language, b.resolved_language FROM campaigns c INNER JOIN users u ON u.id = c.user_id LEFT JOIN campaign_briefs b ON b.campaign_id = c.id $sqlWhere ORDER BY c.id DESC LIMIT " . admin_per_page() . ' OFFSET ' . admin_offset($page));
$list->execute($params);
$rows = $list->fetchAll();

admin_start(admin_t('Campaigns', 'الحملات'));
echo '<form class="search" method="get"><input name="q" value="' . admin_h($q) . '" placeholder="' . admin_h(admin_t('Title or owner email', 'العنوان أو بريد المالك')) . '">';
echo '<select name="status"><option value="">' . admin_h(admin_t('Any status', 'كل الحالات')) . '</option>';
foreach (campaign_status_values() as $value) {
    echo '<option value="' . admin_h($value) . '"' . ($status === $value ? ' selected' : '') . '>' . admin_h($value) . '</option>';
}
echo '</select><button type="submit">' . admin_h(admin_t('Search', 'بحث')) . '</button></form>';
echo '<table><tr><th>' . admin_h(admin_t('Title', 'العنوان')) . '</th><th>' . admin_h(admin_t('Owner', 'المالك')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Language', 'اللغة')) . '</th><th>' . admin_h(admin_t('Created', 'التاريخ')) . '</th></tr>';
foreach ($rows as $row) {
    $language = (string) ($row['campaign_language'] ?? '');
    if ((string) ($row['resolved_language'] ?? '') !== '') {
        $language .= ' → ' . (string) $row['resolved_language'];
    }
    echo '<tr><td><a href="/admin/campaign.php?id=' . (int) $row['id'] . '">' . admin_h((string) $row['title']) . '</a></td><td>' . admin_h((string) $row['email']) . '</td><td><span class="pill">' . admin_h((string) $row['status']) . '</span></td><td>' . admin_h($language) . '</td><td>' . admin_h((string) $row['created_at']) . '</td></tr>';
}
echo '</table>';
$base = '/admin/campaigns.php?q=' . rawurlencode($q) . '&status=' . rawurlencode($status) . '&page=';
admin_pager($page, $total, $base);
admin_end();
