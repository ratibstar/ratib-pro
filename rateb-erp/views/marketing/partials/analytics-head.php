<?php
/** @var array<string, mixed>|null $analytics */
if (empty($analytics)) {
    return;
}
if (!empty($analytics['google_analytics_id'])) {
    $ga = Rateb\App\Core\View::escape($analytics['google_analytics_id']);
    echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $ga . '"></script>';
    echo '<script src="' . rateb_asset('js/cms-analytics.js') . '" data-ga-id="' . $ga . '"></script>';
}
if (!empty($analytics['google_tag_manager_id'])) {
    $gtm = Rateb\App\Core\View::escape($analytics['google_tag_manager_id']);
    echo '<script src="' . rateb_asset('js/cms-gtm.js') . '" data-gtm-id="' . $gtm . '"></script>';
}
if (!empty($analytics['meta_pixel_id'])) {
    echo '<!-- Meta Pixel ' . Rateb\App\Core\View::escape($analytics['meta_pixel_id']) . ' -->';
}
if (!empty($analytics['tiktok_pixel_id'])) {
    echo '<!-- TikTok Pixel ' . Rateb\App\Core\View::escape($analytics['tiktok_pixel_id']) . ' -->';
}
if (!empty($analytics['custom_head_code'])) {
    echo $analytics['custom_head_code'];
}
