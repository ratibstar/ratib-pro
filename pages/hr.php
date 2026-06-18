<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/hr.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/hr.php`.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permissions.php';

// Stay on RATEB Pro when ?control=1&agency_id= is present (sidebar SSO); do not bounce to control-panel copies.

rateb_staff_page_require_session();
rateb_staff_page_require_permission('view_hr_dashboard');

$pageTitle = "HR Management System";
$pageCss = [
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css',
    asset('css/hr.css') . '?v=' . filemtime(__DIR__ . '/../css/hr.css'),
];
$pageJs = [asset('js/hr.js'), asset('js/hr-forms.js')];
$pageJsFooter = [
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
    asset('js/hr/hr-page-init.js'),
    asset('js/utils/currencies-utils.js'),
    asset('js/countries-cities.js'),
    asset('js/hr/countries-cities-handler.js'),
    asset('js/hr/hr-page.js'),
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/hr-dashboard-body.php';
require_once __DIR__ . '/../includes/footer.php';
