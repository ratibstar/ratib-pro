<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$id = (int) ($_POST['campaign_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$owned = db()->prepare('SELECT id FROM campaigns WHERE id = ? AND user_id = ?');
$owned->execute([$id, $userId]);
if (!$owned->fetch()) {
    http_response_code(404);
    exit(ui_language() === 'ar' ? 'الحملة غير موجودة.' : 'Campaign not found.');
}

$kind = (string) ($_POST['kind'] ?? '');
if ($kind === 'strategy') {
    $row = db()->prepare("SELECT id, content FROM campaign_outputs WHERE campaign_id = ? AND output_type = 'strategy' ORDER BY id DESC LIMIT 1");
    $row->execute([$id]);
    $strategy = $row->fetch();
    if (!$strategy || rateb_strategy_document((string) $strategy['content']) === null) {
        http_response_code(409);
        exit(ui_language() === 'ar' ? 'لا توجد استراتيجية لاعتمادها.' : 'There is no strategy to approve.');
    }
    db()->prepare("UPDATE campaign_outputs SET approval_status = 'approved' WHERE id = ? AND campaign_id = ?")->execute([(int) $strategy['id'], $id]);
    header('Location: /campaign.php?id=' . $id . '#strategy');
    exit;
}

$outputId = (int) ($_POST['output_id'] ?? 0);
$row = db()->prepare('SELECT o.id, o.output_type FROM campaign_outputs o INNER JOIN campaigns c ON c.id = o.campaign_id WHERE o.id = ? AND o.campaign_id = ? AND c.user_id = ?');
$row->execute([$outputId, $id, $userId]);
$output = $row->fetch();
if (!$output || !in_array((string) $output['output_type'], rateb_copy_types(), true)) {
    http_response_code(404);
    exit(ui_language() === 'ar' ? 'النص غير موجود.' : 'The copy was not found.');
}
db()->prepare("UPDATE campaign_outputs SET approval_status = 'approved' WHERE id = ? AND campaign_id = ?")->execute([$outputId, $id]);
header('Location: /campaign.php?id=' . $id . '#copy');
exit;
