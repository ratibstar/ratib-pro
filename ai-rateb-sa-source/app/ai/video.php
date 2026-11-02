<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../media/library.php';
require_once __DIR__ . '/../video/engine.php';

header('Content-Type: application/json; charset=utf-8');

$ratebVideoJson = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

if (empty($_SESSION['user_id'])) {
    $ratebVideoJson(401, ['ok' => false, 'job_id' => 0, 'error' => ui_language() === 'ar' ? 'انتهت الجلسة. حدّث الصفحة وسجّل الدخول ثم أعد المحاولة.' : 'The session expired. Refresh the page, sign in, and try again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $ratebVideoJson(405, ['ok' => false, 'job_id' => 0, 'error' => 'Method not allowed']);
}
$csrf = (string) ($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
$expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $csrf)) {
    $ratebVideoJson(419, ['ok' => false, 'job_id' => 0, 'error' => ui_language() === 'ar' ? 'انتهت صلاحية الصفحة. حدّثها ثم أعد المحاولة.' : 'The page expired. Refresh it and try again.']);
}
$userId = (int) $_SESSION['user_id'];
session_write_close();

$campaignStmt = db()->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
$campaignStmt->execute([(int) ($_POST['campaign_id'] ?? 0), $userId]);
$campaign = $campaignStmt->fetch();
if (!$campaign) {
    $ratebVideoJson(404, ['ok' => false, 'job_id' => 0, 'error' => ui_language() === 'ar' ? 'الحملة غير موجودة.' : 'Campaign not found.']);
}
$arabic = ui_language() === 'ar';
$outputs = db()->prepare('SELECT output_type, content, approval_status FROM campaign_outputs WHERE campaign_id = ? ORDER BY id DESC');
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
$images = db()->prepare("SELECT * FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind = 'image' ORDER BY (source = 'ai') ASC, id DESC");
$images->execute([(int) $campaign['id'], (int) $campaign['user_id']]);
$seconds = (int) ($_POST['seconds'] ?? 5);
try {
    $direction = trim(mb_substr((string) ($_POST['video_brief'] ?? ''), 0, 400));
    $result = rateb_video_start(db(), $campaign, $strategy ?: [], $copy, $images->fetchAll(), $seconds, $direction);
} catch (Throwable $error) {
    $code = $error->getMessage();
    $known = ['not_configured', 'auth', 'rejected', 'rejected_image', 'rejected_model', 'rejected_credit', 'timeout', 'provider', 'invalid', 'missing_image', 'missing_copy', 'storage', 'watermark', 'face_protection', 'credits', 'package'];
    $ratebVideoJson(500, [
        'ok' => false,
        'job_id' => 0,
        'error' => rateb_video_message(in_array($code, $known, true) ? $code : 'provider', $arabic),
    ]);
}
$payload = [
    'ok' => $result['ok'],
    'job_id' => $result['job_id'],
    'error' => $result['ok'] ? '' : rateb_video_message($result['code'], $arabic),
];
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
