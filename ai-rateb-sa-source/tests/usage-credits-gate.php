<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/plans.php';

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};

$assert(rateb_usage_credit_feature('image') === 'ai_image', 'image maps to ai_image credits');
$assert(rateb_usage_credit_feature('voice') === 'saudi_voice', 'voice maps to saudi_voice credits');
$assert(rateb_usage_credit_feature('video') === 'video_storyboard', 'video maps to video_storyboard credits');
$assert(rateb_usage_credit_feature('campaign_text') === '', 'text uses its own deduct path');
$assert(rateb_get_feature_cost('ai_image') === 10, 'image costs 10 credits');
$assert(rateb_get_feature_cost('saudi_voice') === 15, 'voice costs 15 credits');
$assert(rateb_get_feature_cost('video_storyboard') === 20, 'video costs 20 credits');
$summary = rateb_credits_spend_summary(60);
$assert($summary['images'] === 6 && $summary['videos'] === 3 && $summary['voices'] === 4, '60 points buys 6 images / 3 videos / 4 voices');
$assert(rateb_credits_spend_summary(9)['images'] === 0, 'under 10 points cannot buy an image');

$src = (string) file_get_contents($root . '/app/plans.php');
$start = strpos($src, 'function rateb_usage_begin');
$end = strpos($src, 'function rateb_usage_finish', $start === false ? 0 : $start);
$body = ($start !== false && $end !== false) ? substr($src, $start, $end - $start) : '';
$assert($body !== '', 'usage_begin body found');
$assert(!str_contains($body, "operation === 'image'"), 'usage_begin no longer branches on image monthly cap');
$assert(!str_contains($body, 'rateb_video_is_free($plan) ? 2'), 'usage_begin no longer uses free video monthly cap');
$assert(str_contains($body, 'rateb_reservation_charge'), 'usage_begin charges credits on open reservation');
$assert(str_contains($body, "'credits'"), 'usage_begin returns credits when balance is low');

$assert(str_contains($src, "'credits' =>") && str_contains($src, 'رصيد النقاط غير كافٍ'), 'credits error copy exists');

$approve = (string) file_get_contents($root . '/app/media/approve.php');
$assert(str_contains($approve, 'ai_image_refund'), 'image approve nets refunds before charging again');

$video = (string) file_get_contents($root . '/app/video/engine.php');
$assert(!str_contains($video, 'rateb_check_and_deduct_credits((int) $campaign[\'user_id\'], \'video_storyboard\''), 'video completion does not double-charge');

exit($fail > 0 ? 1 : 0);
