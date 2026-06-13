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
    echo '<!-- GTM ' . Rateb\App\Core\View::escape($analytics['google_tag_manager_id']) . ' -->';
}
if (!empty($analytics['custom_head_code'])) {
    echo $analytics['custom_head_code'];
}
