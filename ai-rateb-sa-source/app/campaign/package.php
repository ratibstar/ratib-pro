<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../media/library.php';
require_once __DIR__ . '/../video/engine.php';
require_once __DIR__ . '/package-lib.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

$viewerId = (int) $_SESSION['user_id'];
$campaign = media_owned_campaign((int) ($_GET['id'] ?? 0));
$campaignId = (int) $campaign['id'];
$balance = db()->prepare('SELECT credits_balance FROM users WHERE id = ?');
$balance->execute([$viewerId]);
$bill = rateb_campaign_credit_bill(db(), (int) $campaign['user_id'], $campaignId);
if ((int) $balance->fetchColumn() < 1 && $bill['credits'] > 0) {
    header('Location: /campaign.php?id=' . $campaignId . '#ready');
    exit;
}
$userId = (int) $campaign['user_id'];
session_write_close();

$outputs = db()->prepare('SELECT o.output_type, o.content, o.approval_status FROM campaign_outputs o INNER JOIN campaigns c ON c.id = o.campaign_id WHERE o.campaign_id = ? AND c.user_id = ? ORDER BY o.id DESC');
$outputs->execute([$campaignId, $userId]);
$media = db()->prepare("SELECT id, campaign_id, user_id, kind, source, stored_name FROM campaign_media WHERE campaign_id = ? AND user_id = ? AND kind IN ('image', 'audio', 'video') ORDER BY id ASC");
$media->execute([$campaignId, $userId]);
$approvedVideo = rateb_video_approved(db(), $campaignId, $userId);
$language = (string) (rateb_output_language(db(), $campaign)['speech'] ?? 'ar') === 'en' ? 'en' : 'ar';
$now = time();

try {
    $package = rateb_package_build(
        $campaign,
        $viewerId,
        $outputs->fetchAll(),
        $media->fetchAll(),
        $approvedVideo ? (int) $approvedVideo['output_media_id'] : 0,
        $language,
        (string) MEDIA_ROOT,
        $now
    );
    $prepared = rateb_zip_prepare($package['entries']);
} catch (RuntimeException $error) {
    $forbidden = $error->getMessage() === 'package_forbidden';
    http_response_code($forbidden ? 404 : 500);
    if ($forbidden) {
        exit(ui_language() === 'ar' ? 'الحملة غير موجودة.' : 'Campaign not found.');
    }
    exit(ui_language() === 'ar' ? 'تعذر تجهيز ملف الحملة.' : 'The campaign package could not be prepared.');
}

@set_time_limit(300);
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/zip');
header('Content-Length: ' . (string) rateb_zip_length($prepared));
header('Cache-Control: private, no-store');
header('Content-Disposition: attachment; filename="rateb-campaign-' . $campaignId . '.zip"');
try {
    rateb_zip_stream($prepared, static function (string $bytes): void {
        echo $bytes;
        flush();
    }, $now);
} catch (RuntimeException $error) {
    // Headers are sent; stopping before the central directory leaves an archive no client will accept.
    error_log('rateb package ' . $campaignId . ': ' . $error->getMessage());
}
exit;
