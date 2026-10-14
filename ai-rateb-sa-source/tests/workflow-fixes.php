<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/plans.php';
require_once __DIR__ . '/../app/video/engine.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$now = 1_700_000_000;
check(rateb_image_job_active($now - 200, $now), 'image job stays active past the old 180 second window');
check(rateb_image_job_active($now - rateb_image_job_ttl(), $now), 'image job stays active through the server lifetime');
check(!rateb_image_job_active($now - rateb_image_job_ttl() - 1, $now), 'image job expires after the server lifetime');
check(!rateb_image_job_active(0, $now), 'image job without a start time is not active');

check(rateb_video_stage_complete(true, false), 'approved generated video completes the stage');
check(rateb_video_stage_complete(false, true), 'uploaded video completes the stage');
check(!rateb_video_stage_complete(false, false), 'unapproved generated video does not complete the stage');

$english = rateb_voice_script_from_rows([
    ['output_type' => 'headline', 'approval_status' => 'approved', 'content' => 'A short English offer for the shop'],
    ['output_type' => 'ad_copy', 'approval_status' => 'draft', 'content' => 'مسودة عربية لا تُقرأ'],
], 'en');
check($english === 'A short English offer for the shop', 'approved English headline is the spoken script');
check(!rateb_voice_text_is_arabic($english), 'English script is not sent to the Arabic speech model');

$arabic = rateb_voice_script_from_rows([
    ['output_type' => 'headline', 'approval_status' => 'approved', 'content' => json_encode(['ar' => 'عرض اليوم في جدة', 'en' => 'Today in Jeddah'], JSON_UNESCAPED_UNICODE)],
    ['output_type' => 'call_to_action', 'approval_status' => 'approved', 'content' => json_encode(['ar' => 'اطلب الآن', 'en' => 'Order now'], JSON_UNESCAPED_UNICODE)],
], 'ar');
check(str_contains($arabic, 'عرض اليوم في جدة') && str_contains($arabic, 'اطلب الآن'), 'Arabic speech uses the approved Arabic copy');
check(!str_contains($arabic, 'Jeddah') && !str_contains($arabic, 'Order now'), 'Arabic speech does not mix in the English copy');
check(rateb_voice_text_is_arabic($arabic), 'Arabic approved copy can be spoken');

$campaign = ['product_name' => 'تمر مجروش'];
$strategy = ['creative_direction' => 'إضاءة دافئة وصورة قريبة', 'core_message' => 'طعم الضيافة'];
$brand = ['brand_name' => 'بيت التمر', 'tone' => 'دافئ', 'primary_color' => '#0f766e', 'secondary_color' => '#111827', 'logo_stored' => 'secret-logo.png'];
$prompt = rateb_visual_prompt(
    array_merge(['Advertising photograph'], rateb_visual_lines($campaign, $strategy, 'اطلب التمر اليوم', $brand, 'ar')),
    'No written text and no logo.',
    400
);
check(mb_strlen($prompt) <= 400, 'image prompt stays within the provider limit');
check(str_contains($prompt, 'تمر مجروش'), 'image prompt keeps the product');
check(str_contains($prompt, 'إضاءة دافئة'), 'image prompt keeps the creative direction');
check(str_contains($prompt, 'اطلب التمر اليوم'), 'image prompt keeps the approved copy');
check(str_contains($prompt, 'بيت التمر') && str_contains($prompt, '#0f766e'), 'image prompt keeps saved brand name and palette');
check(!str_contains($prompt, 'secret-logo.png') && !str_contains($prompt, 'logo applied'), 'image prompt does not apply or claim the logo file');
check(!str_contains($prompt, 'الرياض'), 'image prompt does not invent a location');

$bare = rateb_visual_prompt(
    array_merge(['Advertising photograph'], rateb_visual_lines(['product_name' => 'Dates'], ['creative_direction' => 'Warm close-up'], 'Order today', [], 'en')),
    'No written text and no logo.',
    400
);
check(!str_contains($bare, 'Palette') && !str_contains($bare, 'Brand:'), 'missing brand kit adds no brand facts');

$scenes = rateb_video_storyboard(
    ['id' => 3, 'product_name' => 'تمر مجروش'],
    ['creative_direction' => 'إضاءة دافئة'],
    ['اطلب التمر اليوم'],
    [['id' => 8]],
    5,
    $brand
);
check(count($scenes) === 1 && str_contains($scenes[0]['prompt'], 'إضاءة دافئة'), 'video prompt keeps the approved creative direction');
check(str_contains($scenes[0]['prompt'], 'تمر مجروش') && str_contains($scenes[0]['prompt'], '#0f766e'), 'video prompt keeps the product and saved palette');
check(str_contains($scenes[0]['prompt'], 'no text') && !str_contains($scenes[0]['prompt'], 'secret-logo.png'), 'video prompt asks for no rendered text and does not attach the logo');
check(mb_strlen($scenes[0]['prompt']) <= 400, 'video prompt stays within the provider limit');

$now = 1_700_000_000;
$userId = 15;
$campaignId = 7;
$jobId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
$active = rateb_image_job_record($jobId, $userId, $campaignId, 41, $now);
$sameSession = rateb_image_jobs_decide([$jobId => $active], $userId, $campaignId, $jobId, $now + 200);
check($sameSession['action'] === 'resume' && (int) $sameSession['usage_id'] === 41 && $sameSession['finish'] === [], 'same-session image poll resumes the existing reservation');
check(rateb_image_job_allows_new_reservation($sameSession) === false, 'same-session resume does not allow a second reservation');

$ledger = [];
$usageIds = [41, 42, 43, 44];
foreach ($usageIds as $offset => $usageId) {
    $id = sprintf('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa%d', $offset + 1);
    $ledger = rateb_image_jobs_attach($ledger, rateb_image_job_record($id, $userId, 70 + $offset, $usageId, $now + $offset));
}
$session = rateb_image_session_trim($ledger, 3);
check(count($session) === 3 && !isset($session['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1']), 'session list trims to the newest three jobs');
check(count($ledger) === 4 && isset($ledger['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1']), 'trimming the session list does not drop ledger jobs');
$trimmed = rateb_image_jobs_decide($ledger, $userId, 70, '', $now + 10);
check($trimmed['action'] === 'resume' && (int) $trimmed['usage_id'] === 41 && $trimmed['finish'] === [], 'a job removed from the session list still resumes its reservation');

$jobDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rateb-image-job-test-' . getmypid();
putenv('RATEB_IMAGE_JOB_DIR=' . $jobDir);
rateb_image_jobs_transaction($userId, static function (array $jobs) use ($active): array {
    return ['jobs' => rateb_image_jobs_attach($jobs, $active), 'result' => null];
});
$reloaded = rateb_image_jobs_transaction($userId, static function (array $jobs): array {
    return ['jobs' => $jobs, 'result' => $jobs];
});
$lostSession = rateb_image_jobs_decide($reloaded, $userId, $campaignId, '', $now + 200);
check($lostSession['action'] === 'resume' && (int) $lostSession['usage_id'] === 41 && $lostSession['finish'] === [], 'lost session reloads the unfinished job and its reservation');
check(rateb_image_job_allows_new_reservation($lostSession) === false, 'lost-session recovery does not allow a second reservation');

$expired = rateb_image_job_record($jobId, $userId, $campaignId, 88, $now - rateb_image_job_ttl() - 1);
$firstExpiry = rateb_image_jobs_decide([$jobId => $expired], $userId, $campaignId, $jobId, $now);
$secondExpiry = rateb_image_jobs_decide($firstExpiry['jobs'], $userId, $campaignId, $jobId, $now);
check($firstExpiry['action'] === 'expire' && $firstExpiry['finish'] === [88] && $firstExpiry['jobs'] === [], 'an expired image job finalizes its reservation');
check($secondExpiry['finish'] === [] && rateb_image_job_allows_new_reservation($secondExpiry) === false, 'a second look at an expired job does not finalize it again or reserve another');
$afterExpiry = rateb_image_jobs_decide($firstExpiry['jobs'], $userId, $campaignId, '', $now);
check($afterExpiry['action'] === 'begin' && $afterExpiry['finish'] === [], 'a new image is allowed only after the expired reservation is gone');

$claimed = rateb_image_jobs_claim([], $userId, $campaignId, '', $now, 70);
$duplicate = rateb_image_jobs_claim($claimed['jobs'], $userId, $campaignId, '', $now, 71);
check($claimed['action'] === 'begin' && (int) $claimed['usage_id'] === 70, 'the first image claim records one reservation');
check($duplicate['action'] === 'wait' && (int) $duplicate['usage_id'] === 70 && rateb_image_job_allows_new_reservation($duplicate) === false, 'a second claim waits on the unfinished reservation');
check(!isset($duplicate['jobs']['usage-71']), 'a second claim does not store another reservation');
$freshClaim = rateb_image_job_record('', $userId, $campaignId, 55, $now - 60, 'submitting');
$stillSubmitting = rateb_image_jobs_decide(['usage-55' => $freshClaim], $userId, $campaignId, '', $now);
check($stillSubmitting['action'] === 'wait' && $stillSubmitting['finish'] === [], 'a submit still inside the grace period is not marked failed');
$staleClaim = rateb_image_job_record('', $userId, $campaignId, 55, $now - 61, 'submitting');
$stale = rateb_image_jobs_decide(['usage-55' => $staleClaim], $userId, $campaignId, '', $now);
$staleAgain = rateb_image_jobs_decide($stale['jobs'], $userId, $campaignId, '', $now);
check($stale['action'] === 'begin' && $stale['finish'] === [55], 'a submit that cannot still be running is finalized once');
check($staleAgain['finish'] === [] && $staleAgain['action'] === 'begin', 'the stale submit is not finalized twice');
rateb_image_jobs_transaction($userId, static function (array $jobs) use ($claimed): array {
    return ['jobs' => $claimed['jobs'], 'result' => null];
});
$storedClaim = rateb_image_jobs_transaction($userId, static function (array $jobs): array {
    return ['jobs' => $jobs, 'result' => $jobs];
});
$otherBrowser = rateb_image_jobs_decide($storedClaim, $userId, $campaignId, '', $now);
check($otherBrowser['action'] === 'wait' && (int) $otherBrowser['usage_id'] === 70 && $otherBrowser['finish'] === [], 'another browser waits on the stored unfinished reservation');
check(rateb_image_job_allows_new_reservation($otherBrowser) === false, 'another browser is not allowed to reserve the same unfinished image');

$files = glob($jobDir . DIRECTORY_SEPARATOR . '*') ?: [];
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
if (is_dir($jobDir)) {
    rmdir($jobDir);
}
putenv('RATEB_IMAGE_JOB_DIR');

$arabicCopy = rateb_video_copy_text('اطلب التمر اليوم من بيت التمر', 'ar');
$englishCopy = rateb_video_copy_text('Order the dates from the house today', 'en');
check($arabicCopy === 'اطلب التمر اليوم من بيت التمر', 'Arabic video copy stays plain text');
check($englishCopy === 'Order the dates from the house today', 'English video copy stays plain text');
$bilingual = json_encode(['ar' => 'عرض اليوم في جدة', 'en' => 'Today only in Jeddah'], JSON_UNESCAPED_UNICODE);
check(rateb_video_copy_text($bilingual, 'ar') === 'عرض اليوم في جدة', 'Arabic campaign language selects the Arabic copy field');
check(rateb_video_copy_text($bilingual, 'en') === 'Today only in Jeddah', 'English campaign language selects the English copy field');
check(rateb_video_copy_text('{"ar":"عرض', 'ar') === '', 'malformed JSON is not copied into the video prompt');
$longCopy = str_repeat('A', 181);
$limited = rateb_video_copy_text($longCopy, 'en');
check(mb_strlen($limited) === 180 && $limited === str_repeat('A', 180), 'video copy is limited to 180 characters after it is plain text');
$approvedParts = rateb_video_copy_parts([
    ['approval_status' => 'draft', 'content' => 'مسودة لا تُستخدم'],
    ['approval_status' => 'approved', 'content' => $bilingual],
], 'ar');
check($approvedParts === ['عرض اليوم في جدة'], 'only approved copy is normalized for the video');
$jsonScenes = rateb_video_storyboard(
    ['id' => 3, 'product_name' => 'تمر مجروش'],
    ['creative_direction' => 'إضاءة دافئة'],
    [$bilingual],
    [['id' => 8]],
    5,
    [],
    'ar'
);
check(count($jsonScenes) === 1 && str_contains($jsonScenes[0]['prompt'], 'عرض اليوم في جدة'), 'video prompt uses the Arabic field from bilingual copy');
check(!str_contains($jsonScenes[0]['prompt'], '{') && !str_contains($jsonScenes[0]['prompt'], 'Jeddah') && !str_contains($jsonScenes[0]['purpose'], '{'), 'video prompt does not contain raw bilingual JSON');

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);
