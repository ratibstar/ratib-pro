<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control/layout-wrapper.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control/layout-wrapper.php`.
 */
/**
 * Control Panel Layout Wrapper - Standalone
 * Usage: require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
 *        startControlLayout($pageTitle, $additionalCSS, $additionalJS, $layoutOptions);
 *
 * $layoutOptions:
 * - standalone (bool): no sidebar, content header, CoreAI, support alerts, or Global AI — full-width module only.
 */
require_once __DIR__ . '/../../../app/UI/GlobalAIButton.php';

if (!function_exists('control_render_global_ai_header_button')) {
    function control_render_global_ai_header_button(string $baseUrl): string
    {
        if (method_exists(\App\UI\GlobalAIButton::class, 'renderButton')) {
            return \App\UI\GlobalAIButton::renderButton($baseUrl, 'header');
        }

        return '';
    }
}

if (!function_exists('control_render_global_ai_modal')) {
    function control_render_global_ai_modal(string $baseUrl): string
    {
        if (method_exists(\App\UI\GlobalAIButton::class, 'renderModalAndScript')) {
            return \App\UI\GlobalAIButton::renderModalAndScript($baseUrl);
        }
        if (method_exists(\App\UI\GlobalAIButton::class, 'render')) {
            return \App\UI\GlobalAIButton::render($baseUrl);
        }

        return '';
    }
}

function startControlLayout($pageTitle = 'Control Panel', $additionalCSS = [], $additionalJS = [], array $layoutOptions = []) {
    global $apiBase, $ctrl;
    $standalone = !empty($layoutOptions['standalone']);
    $additionalCSS = is_array($additionalCSS) ? $additionalCSS : [];
    $additionalJS = is_array($additionalJS) ? $additionalJS : [];
    $pageTitle = function_exists('cp_translate_page_title') ? cp_translate_page_title((string) $pageTitle) : (string) $pageTitle;
    $htmlLang = function_exists('cp_html_lang') ? cp_html_lang() : 'en';
    $htmlDir = function_exists('cp_html_dir') ? cp_html_dir() : 'ltr';
    if (!isset($ctrl)) $ctrl = $GLOBALS['control_conn'] ?? null;
    if (!function_exists('control_request_origin_base')) {
        require_once __DIR__ . '/request-url.php';
    }
    $fullBase = control_request_origin_base();

    if (!isset($apiBase)) {
        if (!function_exists('control_control_api_base_url')) {
            require_once __DIR__ . '/request-url.php';
        }
        $apiBase = control_control_api_base_url();
    }
    // Public RATEB root for shared assets (css/js at site root, not /control-panel).
    $ratebPublic = function_exists('control_rateb_pro_public_base_url')
        ? control_rateb_pro_public_base_url()
        : preg_replace('#/control-panel$#', '', $fullBase);
    $ratebPublic = rtrim((string) $ratebPublic, '/');
    $GLOBALS['control_layout_standalone'] = $standalone;
    ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars($htmlDir, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        if ($standalone) {
            echo htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
        } else {
            echo htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') . ' | ' . htmlspecialchars(function_exists('cp_t') ? cp_t('meta.brand_suffix') : 'RATEB', ENT_QUOTES, 'UTF-8');
        }
    ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/system.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/control/rtl.css'); ?>?v=<?php echo time(); ?>"><?php if (!$standalone): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($ratebPublic . '/css/global-ai-action.css?v=' . time(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($htmlDir === 'rtl'): ?>
    <style id="cp-rtl-layout-fix">
    .control-layout{display:flex!important;flex-direction:row!important;direction:ltr!important}
    .control-layout.cp-layout-rtl>.control-content{order:1!important;flex:1 1 auto!important;min-width:0!important;direction:rtl;padding:2rem 4px 2rem 2rem!important}
    .control-layout.cp-layout-rtl>.control-sidebar{order:2!important;flex:0 0 280px!important;width:280px!important;min-width:280px!important;direction:rtl;text-align:right}
    .control-content .stat-card{direction:ltr!important;flex-direction:row!important;align-items:flex-start!important;gap:1.25rem!important;padding:1.5rem!important}
    .control-content .stat-card .stat-icon{order:2!important}
    .control-content .stat-card .stat-content{order:1!important;direction:rtl!important;text-align:right!important;flex:1!important;min-width:0!important}
    .control-sidebar .sidebar-item{border-left:none!important;border-right:3px solid transparent!important}
    .control-sidebar .sidebar-item.active{border-left:none!important;border-right:3px solid var(--control-accent)!important;box-shadow:inset -4px 0 0 var(--control-accent)!important;background:linear-gradient(270deg,rgba(102,126,234,.2),rgba(118,75,162,.2))!important}
    .content-header{flex-direction:row-reverse!important}
    </style>
    <?php endif; ?>
    <?php endif; ?>
    <?php foreach ($additionalCSS as $css):
        $css = (string)$css;
        $cssAbs = (bool)preg_match('#^(https?://|/)#i', $css);
        $cssHref = $cssAbs ? $css : asset($css);
        $cssVer = $cssAbs ? '' : ('?v=' . time());
        $cssDir = $cssAbs ? ' dir="ltr"' : '';
        ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssHref . $cssVer); ?>"<?php echo $cssDir; ?>>
    <?php endforeach; ?>
</head>
<body class="control-system-body control-layout-no-header<?php echo $standalone ? ' control-layout-standalone' : ''; ?><?php echo $htmlDir === 'rtl' ? ' cp-rtl' : ''; ?>">
    <div id="control-config" data-cp-no-i18n data-api-base="<?php echo htmlspecialchars($apiBase); ?>"></div>
    <?php
    $coreAiUrl = rtrim($fullBase, '/') . '/coreai/index.php';
    // Main RATEB Pro JSON API lives at site /api, not /control-panel/api
    $ratebPublic = function_exists('control_rateb_pro_public_base_url') ? control_rateb_pro_public_base_url() : $fullBase;
    $ratebApiBase = rtrim($ratebPublic !== '' ? $ratebPublic : $fullBase, '/') . '/api';
    ?>
    <?php $controlHrApiBase = rtrim($fullBase, '/') . '/api/control/hr'; ?>
    <div id="app-config" data-base-url="<?php echo htmlspecialchars($fullBase, ENT_QUOTES, 'UTF-8'); ?>" data-api-base="<?php echo htmlspecialchars($ratebApiBase, ENT_QUOTES, 'UTF-8'); ?>" data-control="1" data-control-api-path="<?php echo htmlspecialchars($fullBase . '/api/control', ENT_QUOTES, 'UTF-8'); ?>" data-control-hr-api-base="<?php echo htmlspecialchars($controlHrApiBase, ENT_QUOTES, 'UTF-8'); ?>" class="hidden"></div>
    <?php
    $ratebGlobalAiRunUrl = htmlspecialchars(
        rtrim($ratebApiBase, '/') . '/workers/global-ai-run.php',
        ENT_QUOTES,
        'UTF-8'
    );
    ?>
    <script id="rateb-global-ai-fetch-v7">
    (function(){if(window.__ratebGlobalAiFetchV7)return;window.__ratebGlobalAiFetchV7=1;var RUN_URL='<?php echo $ratebGlobalAiRunUrl; ?>';var orig=window.fetch;window.fetch=function(url,opts){var u=typeof url==='string'?url:(url&&url.url)||'';if(u.indexOf('worker-onboarding')!==-1){url=typeof url==='string'?RUN_URL:(typeof Request!=='undefined'?new Request(RUN_URL,url):RUN_URL);}return orig.call(this,url,opts);};})();
    </script>
    <script src="<?php echo asset('js/control/app-config-init.js'); ?>?v=<?php echo time(); ?>"></script>
    <?php if (function_exists('cp_i18n_inline_script')) { echo cp_i18n_inline_script(); } ?>
    <script src="<?php echo asset('js/control/i18n.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/frame-guard.js'); ?>?v=<?php echo time(); ?>"></script>
    <div class="control-layout<?php echo $standalone ? ' control-layout-standalone-shell' : ''; ?><?php echo $htmlDir === 'rtl' ? ' cp-layout-rtl' : ''; ?>">
        <?php if (!$standalone): ?>
        <?php if (control_sidebar_before_main()) { control_render_sidebar(); } ?>
        <main class="control-content">
            <div class="content-header">
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="<?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('layout.toggle_sidebar') : 'Toggle sidebar', ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-bars"></i></button>
                <?php if (isset($pageTitle) && $pageTitle): ?><h2><?php echo htmlspecialchars($pageTitle); ?></h2><?php endif; ?>
                <div class="content-header-actions">
                <div class="content-header-toolbar">
                <?php include __DIR__ . '/lang-switcher.php'; ?>
                <a href="<?php echo htmlspecialchars($coreAiUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-coreai" target="_blank" rel="noopener noreferrer" aria-label="<?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('layout.open_coreai') : 'Open CoreAI', ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-robot" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('layout.coreai') : 'CoreAI', ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <?php echo control_render_global_ai_header_button($fullBase); ?>
                <span class="user-info"><?php echo htmlspecialchars((string) ($_SESSION['control_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                <a href="<?php echo pageUrl('logout.php'); ?>?control=1" class="btn-logout"><?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('nav.logout') : 'Logout', ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
                <div class="header-alerts" id="headerAlerts" data-permission="control_support_chats,view_control_support">
                    <button type="button" class="header-alert-btn" id="supportAlertsBtn" aria-label="<?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('layout.support_alerts') : 'Support alerts', ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('layout.support_alerts') : 'Support alerts', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-bell"></i>
                        <span class="badge-count header-alert-badge d-none" id="supportAlertsBadge">0</span>
                    </button>
                    <div class="header-alert-dropdown d-none" id="supportAlertsDropdown">
                        <div class="header-alert-title"><?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('layout.support_alerts_title') : 'Support Alerts', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="header-alert-list" id="supportAlertsList">
                            <div class="header-alert-empty"><?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('layout.no_unread_chats') : 'No unread chats.', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <a href="<?php echo pageUrl('control/support-chats.php'); ?>" class="header-alert-footer"><?php echo htmlspecialchars(function_exists('cp_t') ? cp_t('layout.open_support_chats') : 'Open Support Chats', ENT_QUOTES, 'UTF-8'); ?></a>
                    </div>
                </div>
                </div>
            </div>
            <?php echo control_render_global_ai_modal($fullBase); ?>
        <?php else: ?>
        <main class="control-content control-content-standalone-full">
        <?php endif; ?>
        <div class="module-content<?php echo function_exists('cp_is_rtl') && cp_is_rtl() ? ' cp-module-rtl' : ''; ?>"<?php echo function_exists('cp_is_rtl') && cp_is_rtl() ? ' translate="no" data-cp-no-i18n="1"' : ''; ?>>
<!--CP_MODULE_START-->
<?php
}

function endControlLayout($additionalJS = []) {
    if (!function_exists('control_request_origin_base')) {
        require_once __DIR__ . '/request-url.php';
    }
    $fullBase = control_request_origin_base();
    $standaloneEnd = !empty($GLOBALS['control_layout_standalone']);
    ?>
<!--CP_MODULE_END-->
            </div>
        </main>
        <?php if (!$standaloneEnd && !control_sidebar_before_main()) { control_render_sidebar(); } ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/system.js'); ?>?v=<?php echo time(); ?>"></script>
    <?php if (!$standaloneEnd): ?>
    <script src="<?php echo asset('js/control/header-support-alerts.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo htmlspecialchars(rtrim((string) (function_exists('control_rateb_pro_public_base_url') ? control_rateb_pro_public_base_url() : preg_replace('#/control-panel$#', '', $fullBase)), '/') . '/js/utils/global-ai-action.js?v=' . time(), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php
    $globalAiRunPatch = dirname(__DIR__, 3) . '/includes/global_ai_run_patch.php';
    if (is_file($globalAiRunPatch)) {
        include $globalAiRunPatch;
    }
    ?>
    <?php endif; ?>
    <?php foreach ((array)$additionalJS as $js):
        $js = (string)$js;
        $jsAbs = (bool)preg_match('#^(https?://|/)#i', $js);
        $jsSrc = $jsAbs ? $js : asset($js);
        $jsVer = $jsAbs ? '' : ('?v=' . time());
        $jsDir = $jsAbs ? ' dir="ltr"' : '';
        ?>
    <script src="<?php echo htmlspecialchars($jsSrc . $jsVer); ?>"<?php echo $jsDir; ?>></script>
    <?php endforeach; ?>
</body>
</html>
<?php
}
