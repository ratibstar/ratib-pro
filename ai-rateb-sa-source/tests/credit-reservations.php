<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/plans.php';
require_once __DIR__ . '/support/fake-ledger.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$user = 15;
$campaign = 7;

$db = new FakeLedger();
$db->balances[$user] = 25;
$image = $db->reserve($user, $campaign, 'image');
check(rateb_reservation_charge($db, $image, $user, 'ai_image', $campaign) === 'ok', 'an open reservation can be charged');
check($db->balances[$user] === 15 && $db->events[$image]['credits_deducted'] === 10, 'the charge is taken once and recorded on the reservation');
check(count($db->rows('ai_image')) === 1, 'the charge is logged as an ai_image event');
check(rateb_reservation_charge($db, $image, $user, 'ai_image', $campaign) === 'reservation' && $db->balances[$user] === 15, 'the same reservation cannot be charged twice');
check(rateb_reservation_settle($db, $image, $user, 'failed', 'ai_image') === true && $db->balances[$user] === 25, 'a failed reservation refunds its recorded charge');
check(rateb_reservation_settle($db, $image, $user, 'failed', 'ai_image') === false && $db->balances[$user] === 25, 'a second failure report does not refund again');
check(count($db->rows('ai_image_refund')) === 1, 'exactly one refund event is written');
check(rateb_reservation_charge($db, $image, $user, 'ai_image', $campaign) === 'reservation', 'a closed reservation cannot be charged again');

$db = new FakeLedger();
$db->balances[$user] = 5;
$poor = $db->reserve($user, $campaign, 'image');
check(rateb_reservation_charge($db, $poor, $user, 'ai_image', $campaign) === 'credits', 'a low balance is reported as a credit stop');
check($db->balances[$user] === 5 && $db->events[$poor]['credits_deducted'] === 0 && $db->rows('ai_image') === [], 'a refused charge leaves the balance and reservation untouched');
check(rateb_reservation_settle($db, $poor, $user, 'failed', 'ai_image') === false && $db->balances[$user] === 5, 'an uncharged reservation is never refunded');

$db = new FakeLedger();
$db->balances[$user] = 30;
$done = $db->reserve($user, $campaign, 'voice');
rateb_reservation_charge($db, $done, $user, 'saudi_voice', $campaign);
check(rateb_reservation_settle($db, $done, $user, 'completed', 'saudi_voice') === false && $db->balances[$user] === 15, 'a completed voice keeps its charge');
check(rateb_reservation_settle($db, $done, $user, 'failed', 'saudi_voice') === false && $db->balances[$user] === 15, 'a late failure after completion does not refund');
$other = $db->reserve($user, $campaign, 'voice');
rateb_reservation_charge($db, $other, $user, 'saudi_voice', $campaign);
check(rateb_reservation_settle($db, $other, 99, 'failed', 'saudi_voice') === false && $db->events[$other]['status'] === 'reserved' && $db->balances[$user] === 0, 'another user cannot settle or refund this reservation');

$db = new FakeLedger();
$db->balances[$user] = 20;
$broken = $db->reserve($user, $campaign, 'image');
$db->failOn = "VALUES (?, ?, ?, 1, 'completed', ?, ?)";
$threw = false;
try {
    rateb_reservation_charge($db, $broken, $user, 'ai_image', $campaign);
} catch (PDOException $error) {
    $threw = true;
}
check($threw && $db->balances[$user] === 20 && $db->events[$broken]['credits_deducted'] === 0, 'a failed charge write rolls back the debit and the reservation mark');
$db->failOn = '';
rateb_reservation_charge($db, $broken, $user, 'ai_image', $campaign);
$db->failOn = "VALUES (?, ?, ?, 1, 'completed', ?, 0)";
$threw = false;
try {
    rateb_reservation_settle($db, $broken, $user, 'failed', 'ai_image');
} catch (PDOException $error) {
    $threw = true;
}
check($threw && $db->events[$broken]['status'] === 'reserved' && $db->balances[$user] === 10, 'a failed refund write keeps the reservation open and the balance unchanged');
$db->failOn = '';
check(rateb_reservation_settle($db, $broken, $user, 'failed', 'ai_image') === true && $db->balances[$user] === 20, 'retrying after a failed refund write refunds exactly once');
check(rateb_reservation_settle($db, $broken, $user, 'failed', 'ai_image') === false && $db->balances[$user] === 20, 'the retried refund is not repeated');

$db = new FakeLedger();
$db->balances[$user] = 40;
$maxAge = rateb_image_reservation_max_age();
$lost = $db->reserve($user, $campaign, 'image', $maxAge + 5);
$db->events[$lost]['credits_deducted'] = 10;
$young = $db->reserve($user, $campaign, 'image', $maxAge - 5);
$db->events[$young]['credits_deducted'] = 10;
$free = $db->reserve($user, $campaign, 'image', $maxAge + 5);
$voiceOld = $db->reserve($user, $campaign, 'voice', $maxAge + 5);
$db->events[$voiceOld]['credits_deducted'] = 15;
check(rateb_reservation_sweep($db, $user, 'image', 'ai_image', $maxAge) === 1 && $db->balances[$user] === 50, 'a charged image reservation lost after provider acceptance is refunded once it is stale');
check($db->events[$young]['status'] === 'reserved' && $db->events[$free]['status'] === 'reserved' && $db->events[$voiceOld]['status'] === 'reserved', 'the sweep leaves running, uncharged, and other-feature reservations alone');
check(rateb_reservation_sweep($db, $user, 'image', 'ai_image', $maxAge) === 0 && $db->balances[$user] === 50, 'a second sweep refunds nothing');
check(rateb_reservation_settle($db, $lost, $user, 'completed', 'ai_image') === false && $db->balances[$user] === 50, 'a job that completes after the sweep is not charged again');

$db = new FakeLedger();
$db->balances[$user] = 10;
$now = 1_700_000_000;
$jobId = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc1';
$usage = $db->reserve($user, $campaign, 'image');
rateb_reservation_charge($db, $usage, $user, 'ai_image', $campaign);
$ledger = rateb_image_jobs_attach([], rateb_image_job_record($jobId, $user, $campaign, $usage, $now - rateb_image_job_ttl() - 1, 'pending'));
$expired = rateb_image_jobs_decide($ledger, $user, $campaign, $jobId, $now);
foreach ($expired['finish'] as $id) {
    rateb_reservation_settle($db, (int) $id, $user, 'failed', 'ai_image');
}
check($expired['action'] === 'expire' && $db->balances[$user] === 10, 'an expired image job refunds its charge');
$again = rateb_image_jobs_decide($ledger, $user, $campaign, $jobId, $now);
foreach ($again['finish'] as $id) {
    rateb_reservation_settle($db, (int) $id, $user, 'failed', 'ai_image');
}
$late = rateb_image_jobs_take($ledger, $jobId, 0);
foreach ($late['removed'] as $job) {
    rateb_reservation_settle($db, (int) $job['usage_id'], $user, 'failed', 'ai_image');
}
check($db->balances[$user] === 10 && count($db->rows('ai_image_refund')) === 1, 'replaying the expiry or a late release of the expired job does not refund twice');

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);
