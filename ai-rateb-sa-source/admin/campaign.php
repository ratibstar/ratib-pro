<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/media/library.php';

admin_require_permission('campaigns.view');

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT c.*, u.email, u.name AS owner_name FROM campaigns c INNER JOIN users u ON u.id = c.user_id WHERE c.id = ?');
$stmt->execute([$id]);
$campaign = $stmt->fetch();
admin_start(admin_t('Campaign', 'حملة'));
if (!$campaign) {
    echo '<p class="note">' . admin_h(admin_t('Campaign not found.', 'الحملة غير موجودة.')) . '</p>';
    admin_end();
    exit;
}
admin_toolbar([
    ['href' => '/admin/campaigns.php', 'label' => admin_t('All campaigns', 'كل الحملات'), 'class' => 'quiet'],
    ['href' => '/admin/user.php?id=' . (int) $campaign['user_id'], 'label' => admin_t('Owner account', 'حساب المالك'), 'class' => 'quiet'],
]);
echo '<article class="card"><h2>' . admin_h((string) $campaign['title']) . '</h2>';
echo '<p>' . admin_h(admin_t('Owner', 'المالك')) . ': ' . admin_h((string) $campaign['owner_name']) . ' · ' . admin_h((string) $campaign['email']) . '</p>';
echo '<p>' . admin_h(admin_t('Status', 'الحالة')) . ': <span class="pill">' . admin_h((string) $campaign['status']) . '</span></p>';
echo '<p>' . admin_h(admin_t('Created', 'التاريخ')) . ': ' . admin_h((string) $campaign['created_at']) . '</p>';
echo '<p>' . admin_h((string) ($campaign['product_name'] ?? '')) . '</p>';
echo '<p>' . admin_h((string) ($campaign['description'] ?? '')) . '</p></article>';

$brief = $pdo->prepare('SELECT * FROM campaign_briefs WHERE campaign_id = ?');
$brief->execute([$id]);
$briefRow = $brief->fetch();
echo '<h2>' . admin_h(admin_t('Brief', 'الملخص')) . '</h2>';
if ($briefRow) {
    echo '<article class="card">';
    foreach (['product', 'audience', 'objective', 'location', 'campaign_language', 'resolved_language', 'brand_tone', 'core_message', 'channels'] as $field) {
        echo '<p><strong>' . admin_h($field) . '</strong> ' . admin_h((string) ($briefRow[$field] ?? '')) . '</p>';
    }
    echo '</article>';
} else {
    echo '<p class="note">' . admin_h(admin_t('No brief.', 'لا يوجد ملخص.')) . '</p>';
}

$outputs = $pdo->prepare('SELECT output_type, approval_status, content, created_at FROM campaign_outputs WHERE campaign_id = ? ORDER BY id DESC');
$outputs->execute([$id]);
echo '<h2>' . admin_h(admin_t('Generated text', 'النص المولّد')) . '</h2>';
foreach ($outputs as $row) {
    echo '<article class="card"><p><span class="pill">' . admin_h((string) $row['output_type']) . '</span> <span class="pill">' . admin_h((string) $row['approval_status']) . '</span></p><pre>' . admin_h((string) $row['content']) . '</pre></article>';
}

$media = $pdo->prepare('SELECT id, kind, source, mime, bytes, original_name, created_at FROM campaign_media WHERE campaign_id = ? ORDER BY id DESC');
$media->execute([$id]);
echo '<h2>' . admin_h(admin_t('Media', 'الوسائط')) . '</h2><div class="media-grid">';
foreach ($media as $row) {
    $src = '/admin/media.php?id=' . (int) $row['id'];
    echo '<article class="card"><p>' . admin_h((string) $row['kind']) . ' · ' . admin_h((string) $row['source']) . '</p>';
    if ((string) $row['kind'] === 'image') {
        echo '<img src="' . admin_h($src) . '" alt="">';
    } elseif ((string) $row['kind'] === 'video') {
        echo '<video controls src="' . admin_h($src) . '"></video>';
    } elseif ((string) $row['kind'] === 'audio') {
        echo '<audio controls src="' . admin_h($src) . '"></audio>';
    }
    echo '<p><a href="' . admin_h($src) . '&amp;download=1">' . admin_h((string) $row['original_name']) . '</a></p></article>';
}
echo '</div>';

$jobs = $pdo->prepare('SELECT id, status, provider, error_code, approval_status, output_media_id, created_at, updated_at FROM video_jobs WHERE campaign_id = ? ORDER BY id DESC');
$jobs->execute([$id]);
echo '<h2>' . admin_h(admin_t('Video jobs', 'مهام الفيديو')) . '</h2><table><tr><th>ID</th><th>' . admin_h(admin_t('Provider', 'المزوّد')) . '</th><th>' . admin_h(admin_t('Status', 'الحالة')) . '</th><th>' . admin_h(admin_t('Error', 'الخطأ')) . '</th><th>' . admin_h(admin_t('Approval', 'الاعتماد')) . '</th></tr>';
foreach ($jobs as $row) {
    $pill = (string) $row['status'] === 'failed' ? 'bad' : ((string) $row['status'] === 'completed' ? 'ok' : 'warn');
    echo '<tr><td>' . (int) $row['id'] . '</td><td>' . admin_h((string) $row['provider']) . '</td><td><span class="pill ' . $pill . '">' . admin_h((string) $row['status']) . '</span></td><td>' . admin_h((string) $row['error_code']) . '</td><td>' . admin_h((string) $row['approval_status']) . '</td></tr>';
}
admin_table_close();
admin_end();
