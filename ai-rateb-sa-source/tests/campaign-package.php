<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/campaign/package-lib.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

/** @return array<string,string> */
function read_zip(string $zip): array
{
    $end = strrpos($zip, "PK\x05\x06");
    if ($end === false) {
        throw new RuntimeException('no end record');
    }
    $eocd = unpack('vdisk/vstart/vcount/vtotal/Vsize/Voffset', substr($zip, $end + 4, 16));
    $files = [];
    $pos = (int) $eocd['offset'];
    for ($i = 0; $i < (int) $eocd['total']; $i++) {
        if (substr($zip, $pos, 4) !== "PK\x01\x02") {
            throw new RuntimeException('bad central entry');
        }
        $c = unpack('vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset', substr($zip, $pos + 4, 42));
        $name = substr($zip, $pos + 46, (int) $c['name']);
        $local = (int) $c['offset'];
        if (substr($zip, $local, 4) !== "PK\x03\x04") {
            throw new RuntimeException('bad local entry');
        }
        $l = unpack('vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vname/vextra', substr($zip, $local + 4, 26));
        $data = substr($zip, $local + 30 + (int) $l['name'] + (int) $l['extra'], (int) $l['csize']);
        if ((int) $c['method'] !== 0 || hexdec(hash('crc32b', $data)) !== (int) $c['crc'] || (int) $l['crc'] !== (int) $c['crc']) {
            throw new RuntimeException('crc mismatch for ' . $name);
        }
        $files[$name] = $data;
        $pos += 46 + (int) $c['name'] + (int) $c['extra'] + (int) $c['comment'];
    }
    return $files;
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rateb-package-test-' . getmypid();
$campaignDir = $root . DIRECTORY_SEPARATOR . '7';
$otherDir = $root . DIRECTORY_SEPARATOR . '8';
mkdir($campaignDir, 0700, true);
mkdir($otherDir, 0700, true);
$image = str_repeat('a', 32) . '.png';
$upload = str_repeat('b', 32) . '.jpg';
$voice = str_repeat('c', 32) . '.wav';
$draftVideo = str_repeat('d', 32) . '.mp4';
$approvedVideo = str_repeat('e', 32) . '.mp4';
$uploadedVideo = str_repeat('f', 32) . '.mp4';
$foreign = str_repeat('9', 32) . '.png';
foreach ([$image => 'IMG', $upload => 'UPL', $voice => 'RIFFVOICE', $draftVideo => 'DRAFT', $approvedVideo => 'APPROVED', $uploadedVideo => 'UPLOADEDVIDEO'] as $name => $bytes) {
    file_put_contents($campaignDir . DIRECTORY_SEPARATOR . $name, $bytes);
}
file_put_contents($otherDir . DIRECTORY_SEPARATOR . $foreign, 'FOREIGN');

check(rateb_package_media_path($root, 7, $image) !== null, 'a stored campaign file resolves');
check(rateb_package_media_path($root, 7, '../8/' . $foreign) === null, 'a parent-directory path is rejected');
check(rateb_package_media_path($root, 7, $foreign) === null, 'a file from another campaign folder is not found');
check(rateb_package_media_path($root, 7, 'evil.php') === null, 'an unexpected stored name is rejected');
check(rateb_package_media_path($root, 7, str_repeat('0', 32) . '.png') === null, 'a missing file is skipped');
check(rateb_package_media_path($root, 0, $image) === null, 'an invalid campaign id is rejected');

$campaign = ['id' => 7, 'user_id' => 15, 'title' => 'حملة التمر'];
$strategy = [];
foreach (rateb_strategy_keys() as $key) {
    $strategy[$key] = ['ar' => 'قيمة ' . $key, 'en' => 'Value ' . $key];
}
$outputs = [
    ['output_type' => 'headline', 'approval_status' => 'approved', 'content' => json_encode(['ar' => 'عرض اليوم', 'en' => 'Today'], JSON_UNESCAPED_UNICODE)],
    ['output_type' => 'ad_copy', 'approval_status' => 'draft', 'content' => 'مسودة لا تُصدَّر'],
    ['output_type' => 'social_posts', 'approval_status' => 'approved', 'content' => json_encode(['instagram' => 'منشور إنستغرام'], JSON_UNESCAPED_UNICODE)],
    ['output_type' => 'strategy', 'approval_status' => 'approved', 'content' => json_encode($strategy, JSON_UNESCAPED_UNICODE)],
    ['output_type' => 'headline', 'approval_status' => 'approved', 'content' => 'عنوان قديم'],
];
$media = [
    ['id' => 1, 'campaign_id' => 7, 'user_id' => 15, 'kind' => 'image', 'source' => 'ai', 'stored_name' => $image],
    ['id' => 2, 'campaign_id' => 7, 'user_id' => 15, 'kind' => 'image', 'source' => 'upload', 'stored_name' => $upload],
    ['id' => 3, 'campaign_id' => 7, 'user_id' => 15, 'kind' => 'audio', 'source' => 'ai', 'stored_name' => $voice],
    ['id' => 4, 'campaign_id' => 7, 'user_id' => 15, 'kind' => 'video', 'source' => 'ai', 'stored_name' => $draftVideo],
    ['id' => 5, 'campaign_id' => 7, 'user_id' => 15, 'kind' => 'video', 'source' => 'ai', 'stored_name' => $approvedVideo],
    ['id' => 6, 'campaign_id' => 7, 'user_id' => 15, 'kind' => 'video', 'source' => 'upload', 'stored_name' => $uploadedVideo],
    ['id' => 7, 'campaign_id' => 8, 'user_id' => 15, 'kind' => 'image', 'source' => 'ai', 'stored_name' => $foreign],
    ['id' => 8, 'campaign_id' => 7, 'user_id' => 99, 'kind' => 'image', 'source' => 'ai', 'stored_name' => $image],
    ['id' => 9, 'campaign_id' => 7, 'user_id' => 15, 'kind' => 'image', 'source' => 'ai', 'stored_name' => str_repeat('0', 32) . '.png'],
    ['id' => 10, 'campaign_id' => 7, 'user_id' => 15, 'kind' => 'image', 'source' => 'ai', 'stored_name' => '../8/' . $foreign],
];
foreach ([99, 0] as $viewer) {
    $refused = false;
    try {
        rateb_package_build($campaign, $viewer, $outputs, $media, 5, 'ar', $root, 1_700_000_000);
    } catch (RuntimeException $error) {
        $refused = $error->getMessage() === 'package_forbidden';
    }
    check($refused, 'a viewer who does not own the campaign gets no package (viewer ' . $viewer . ')');
}
$foreignVideo = rateb_package_build($campaign, 15, [], [
    ['id' => 20, 'campaign_id' => 7, 'user_id' => 99, 'kind' => 'video', 'source' => 'ai', 'stored_name' => $approvedVideo],
], 20, 'ar', $root, 1_700_000_000);
check(array_column($foreignVideo['entries'], 'name') === ['manifest.json'], 'an approved video id that belongs to another user is not packaged');

$package = rateb_package_build($campaign, 15, $outputs, $media, 5, 'ar', $root, 1_700_000_000);
$names = array_column($package['entries'], 'name');
check($names === ['strategy.txt', 'copy.txt', 'images/01-ai.png', 'images/02-upload.jpg', 'audio/01-ai.wav', 'video/01-ai.mp4', 'video/02-upload.mp4', 'manifest.json'], 'package lists only owned, existing, approved files');

$stream = '';
$prepared = rateb_zip_prepare($package['entries']);
rateb_zip_stream($prepared, static function (string $bytes) use (&$stream): void {
    $stream .= $bytes;
}, 1_700_000_000);
check(strlen($stream) === rateb_zip_length($prepared), 'declared length matches the streamed archive');
$files = [];
try {
    $files = read_zip($stream);
    check(true, 'archive structure and checksums are valid');
} catch (RuntimeException $error) {
    check(false, 'archive structure and checksums are valid: ' . $error->getMessage());
}
check(($files['video/01-ai.mp4'] ?? '') === 'APPROVED', 'the approved generated video is packaged');
check(!in_array('DRAFT', $files, true) && !in_array('FOREIGN', $files, true), 'draft video and other campaign files are excluded');
check(($files['video/02-upload.mp4'] ?? '') === 'UPLOADEDVIDEO', 'uploaded video is packaged');
check(str_contains($files['copy.txt'] ?? '', 'عرض اليوم') && !str_contains($files['copy.txt'] ?? '', 'عنوان قديم'), 'copy uses the newest approved headline');
check(!str_contains($files['copy.txt'] ?? '', 'مسودة'), 'draft copy is excluded');
check(str_contains($files['copy.txt'] ?? '', 'منشور إنستغرام') && !str_contains($files['copy.txt'] ?? '', '{'), 'social posts are flattened to text');
check(str_contains($files['strategy.txt'] ?? '', 'الرسالة الأساسية') && !str_contains($files['strategy.txt'] ?? '', 'Core message'), 'strategy headings follow the campaign language');
$manifest = json_decode($files['manifest.json'] ?? '', true);
check(is_array($manifest) && count($manifest['files']) === 7 && (int) $manifest['campaign_id'] === 7, 'manifest lists every packaged file');
$approvals = array_column($manifest['files'] ?? [], 'approval', 'path');
check(($approvals['images/01-ai.png'] ?? '') === 'no_approval_step' && ($approvals['video/01-ai.mp4'] ?? '') === 'approved' && ($approvals['images/02-upload.jpg'] ?? '') === 'uploaded', 'manifest states the approval status of each asset');

$draftOutputs = [
    ['output_type' => 'strategy', 'approval_status' => 'draft', 'content' => json_encode($strategy, JSON_UNESCAPED_UNICODE)],
    ['output_type' => 'headline', 'approval_status' => 'draft', 'content' => 'مسودة'],
];
$draftPackage = rateb_package_build($campaign, 15, $draftOutputs, [], 0, 'en', $root, 1_700_000_000);
check(array_column($draftPackage['entries'], 'name') === ['manifest.json'], 'a campaign with only drafts exports just the manifest');

$skippedReasons = array_column($manifest['skipped'] ?? [], 'reason', 'media_id');
check(($skippedReasons[9] ?? '') === 'missing' && ($skippedReasons[10] ?? '') === 'missing', 'missing and unsafe files are reported as skipped in the manifest');
check(!isset($skippedReasons[7]) && !isset($skippedReasons[8]), 'assets of another campaign or user are not even listed');

$small = rateb_package_build($campaign, 15, [], $media, 5, 'ar', $root, 1_700_000_000, 6);
$smallNames = array_column($small['entries'], 'name');
$smallSkipped = array_column($small['manifest']['skipped'], 'reason', 'media_id');
check($smallNames === ['images/01-ai.png', 'images/02-upload.jpg', 'manifest.json'] && ($smallSkipped[3] ?? '') === 'too_large' && ($smallSkipped[5] ?? '') === 'too_large', 'files past the package size budget are skipped and reported');

$streamFails = static function (array $prepared): string {
    $sink = '';
    try {
        rateb_zip_stream($prepared, static function (string $bytes) use (&$sink): void {
            $sink .= $bytes;
        }, 1_700_000_000);
    } catch (RuntimeException $error) {
        return $error->getMessage() . '|' . (str_contains($sink, "PK\x05\x06") ? 'closed' : 'open');
    }
    return 'ok';
};
$volatile = $campaignDir . DIRECTORY_SEPARATOR . str_repeat('1', 32) . '.png';
file_put_contents($volatile, 'STABLE');
$plan = rateb_zip_prepare([['name' => 'images/01-ai.png', 'path' => $volatile]]);
file_put_contents($volatile, 'CHANGED');
check($streamFails($plan) === 'package_changed|open', 'a file that grows after preparation aborts the archive before the central directory');
file_put_contents($volatile, 'STAB');
check($streamFails($plan) === 'package_changed|open', 'a file that shrinks after preparation aborts the archive');
file_put_contents($volatile, 'STABLX');
check($streamFails($plan) === 'package_changed|open', 'a same-size file with different bytes fails its checksum');
unlink($volatile);
check($streamFails($plan) === 'package_file|open', 'a file deleted after preparation aborts the archive');
$prepareFailed = false;
try {
    rateb_zip_prepare([['name' => 'gone.png', 'path' => $volatile]]);
} catch (RuntimeException $error) {
    $prepareFailed = $error->getMessage() === 'package_file';
}
check($prepareFailed, 'preparing a missing file fails before any bytes are sent');

foreach ([$campaignDir, $otherDir] as $dir) {
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($dir);
}
rmdir($root);

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);
