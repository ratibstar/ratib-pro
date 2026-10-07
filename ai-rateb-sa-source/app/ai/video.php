<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../media/library.php';
require_once __DIR__ . '/../video/engine.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();
session_write_close();

$campaign = media_owned_campaign((int) ($_POST['campaign_id'] ?? 0));
$arabic = ui_language() === 'ar';
$outputs = db()->prepare('SELECT output_type, content, approval_status FROM campaign_outputs WHERE campaign_id = ?');
$outputs->execute([(int) $campaign['id']]);
$strategy = null;
$copy = [];
foreach ($outputs as $row) {
    if ((string) $row['output_type'] === 'strategy' && $strategy === null) {
        $strategy = $row;
    }
    if (in_array((string) $row['output_type'], rateb_copy_types(), true)) {
        $copy[] = $row;
    }
}
$images = db()->prepare("SELECT * FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = 'image' ORDER BY (source = 'ai') DESC, id DESC");
$images->execute([(int) $campaign['id'], (int) $campaign['user_id']]);
$result = rateb_video_start(db(), $campaign, $strategy ?: [], $copy, $images->fetchAll());
$payload = [
    'ok' => $result['ok'],
    'job_id' => $result['job_id'],
    'error' => $result['ok'] ? '' : rateb_video_message($result['code'], $arabic),
];
header('Content-Type: application/json; charset=utf-8');
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
