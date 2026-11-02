<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/plans.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$free = ['code' => 'free', 'price_sar' => 0, 'is_active' => 1, 'image_limit' => 15, 'video_limit' => 3, 'video_max_seconds' => 8, 'period_end' => '2026-12-01 00:00:00'];
$starter = ['code' => 'starter', 'price_sar' => 79, 'is_active' => 1, 'image_limit' => 100, 'video_limit' => 10, 'video_max_seconds' => 10, 'period_end' => '2026-12-01 00:00:00'];
$expired = $starter;
$expired['period_end'] = '2020-01-01 00:00:00';
$inactive = $starter;
$inactive['is_active'] = 0;
$restricted = $starter;
$restricted['image_limit'] = 0;
$restricted['video_limit'] = 0;

$now = strtotime('2026-06-01 00:00:00');
check(rateb_subscription_current($free, $now) && rateb_subscription_current($starter, $now), 'free and paid plans inside their period stay current');
check(!rateb_subscription_current($expired, $now), 'an elapsed period is not current');
check(!rateb_subscription_current($inactive, $now), 'an inactive plan is not current');
check(!rateb_plan_feature_locked($free, 'ai_image') && !rateb_plan_feature_locked($free, 'video_storyboard') && !rateb_plan_feature_locked($free, 'saudi_voice'), 'the free plan keeps image, short video, and voice available');
check(!rateb_plan_feature_locked($starter, 'ai_image') && !rateb_plan_feature_locked($starter, 'video_storyboard'), 'a paid plan with limits is not feature locked');
check(rateb_plan_feature_locked($inactive, 'text_campaign'), 'an inactive plan is locked');
check(rateb_plan_feature_locked($restricted, 'ai_image') && rateb_plan_feature_locked($restricted, 'video_storyboard'), 'a plan with a zero image or video limit locks that feature');
check(rateb_video_duration_code($free, 10) === '' && rateb_video_duration_code($free, 15) === 'video_paid', 'free still allows 10 seconds and refuses 15');

echo $failures === 0 ? "OK\n" : "FAILED {$failures}\n";
exit($failures === 0 ? 0 : 1);
