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

function startControlLayout($pageTitle = 'Control Panel', $additionalCSS = [], $additionalJS = [], array $layoutOptions = []) {
    global $apiBase, $ctrl;
    $standalone = !empty($layoutOptions['standalone']);
    $additionalCSS = is_array($additionalCSS) ? $additionalCSS : [];
    $additionalJS = is_array($additionalJS) ? $additionalJS : [];
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
    // Public Ratib root for shared assets (css/js at site root, not /control-panel).
    $ratibPublic = function_exists('control_ratib_pro_public_base_url')
        ? control_ratib_pro_public_base_url()
        : preg_replace('#/control-panel$#', '', $fullBase);
    $ratibPublic = rtrim((string) $ratibPublic, '/');
    $GLOBALS['control_layout_standalone'] = $standalone;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        if ($standalone) {
            echo htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
        } else {
            echo htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') . ' | RATEB — Recruitment Automation &amp; Telemetry Enterprise Base';
        }
    ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/control/system.css'); ?>?v=<?php echo time(); ?>">
    <?php if (!$standalone): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($ratibPublic . '/css/global-ai-action.css?v=' . time(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php foreach ($additionalCSS as $css):
        $css = (string)$css;
        $cssAbs = (bool)preg_match('#^(https?://|/)#i', $css);
        $cssHref = $cssAbs ? $css : asset($css);
        $cssVer = $cssAbs ? '' : ('?v=' . time());
        ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssHref . $cssVer); ?>">
    <?php endforeach; ?>
</head>
<body class="control-system-body control-layout-no-header<?php echo $standalone ? ' control-layout-standalone' : ''; ?>">
    <div id="control-config" data-api-base="<?php echo htmlspecialchars($apiBase); ?>"></div>
    <?php
    $coreAiUrl = rtrim($fullBase, '/') . '/coreai/index.php';
    // Main Ratib Pro JSON API lives at site /api, not /control-panel/api
    $ratibPublic = function_exists('control_ratib_pro_public_base_url') ? control_ratib_pro_public_base_url() : $fullBase;
    $ratibApiBase = rtrim($ratibPublic !== '' ? $ratibPublic : $fullBase, '/') . '/api';
    ?>
    <?php $controlHrApiBase = rtrim($fullBase, '/') . '/api/control/hr'; ?>
    <div id="app-config" data-base-url="<?php echo htmlspecialchars($fullBase, ENT_QUOTES, 'UTF-8'); ?>" data-api-base="<?php echo htmlspecialchars($ratibApiBase, ENT_QUOTES, 'UTF-8'); ?>" data-control="1" data-control-api-path="<?php echo htmlspecialchars($fullBase . '/api/control', ENT_QUOTES, 'UTF-8'); ?>" data-control-hr-api-base="<?php echo htmlspecialchars($controlHrApiBase, ENT_QUOTES, 'UTF-8'); ?>" class="hidden"></div>
    <?php
    $ratibGlobalAiRunUrl = htmlspecialchars(
        rtrim($ratibApiBase, '/') . '/workers/global-ai-run.php',
        ENT_QUOTES,
        'UTF-8'
    );
    ?>
    <script id="ratib-global-ai-fetch-v7">
    (function(){if(window.__ratibGlobalAiFetchV7)return;window.__ratibGlobalAiFetchV7=1;var RUN_URL='<?php echo $ratibGlobalAiRunUrl; ?>';var orig=window.fetch;window.fetch=function(url,opts){var u=typeof url==='string'?url:(url&&url.url)||'';if(u.indexOf('worker-onboarding')!==-1){url=typeof url==='string'?RUN_URL:(typeof Request!=='undefined'?new Request(RUN_URL,url):RUN_URL);}return orig.call(this,url,opts);};})();
    </script>
    <script src="<?php echo asset('js/control/app-config-init.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/frame-guard.js'); ?>?v=<?php echo time(); ?>"></script>
    <div class="control-layout<?php echo $standalone ? ' control-layout-standalone-shell' : ''; ?>">
        <?php if (!$standalone): ?>
        <?php include __DIR__ . '/sidebar.php'; ?>
        <main class="control-content">
            <div class="content-header">
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
                <?php if (isset($pageTitle) && $pageTitle): ?><h2><?php echo htmlspecialchars($pageTitle); ?></h2><?php endif; ?>
                <div class="content-header-toolbar">
                <a href="<?php echo htmlspecialchars($coreAiUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-coreai" target="_blank" rel="noopener noreferrer" aria-label="Open CoreAI in a new tab">
                    <i class="fas fa-robot" aria-hidden="true"></i>
                    <span>CoreAI</span>
                </a>
                <?php echo \App\UI\GlobalAIButton::renderButton($fullBase, 'header'); ?>
                </div>
                <div class="header-alerts" id="headerAlerts" data-permission="control_support_chats,view_control_support">
                    <button type="button" class="header-alert-btn" id="supportAlertsBtn" aria-label="Support alerts" title="Support alerts">
                        <i class="fas fa-bell"></i>
                        <span class="badge-count header-alert-badge d-none" id="supportAlertsBadge">0</span>
                    </button>
                    <div class="header-alert-dropdown d-none" id="supportAlertsDropdown">
                        <div class="header-alert-title">Support Alerts</div>
                        <div class="header-alert-list" id="supportAlertsList">
                            <div class="header-alert-empty">No unread chats.</div>
                        </div>
                        <a href="<?php echo pageUrl('control/support-chats.php'); ?>" class="header-alert-footer">Open Support Chats</a>
                    </div>
                </div>
            </div>
            <?php echo \App\UI\GlobalAIButton::renderModalAndScript($fullBase); ?>
        <?php else: ?>
        <main class="control-content control-content-standalone-full">
        <?php endif; ?>
        <div class="module-content">
<?php
}

function endControlLayout($additionalJS = []) {
    if (!function_exists('control_request_origin_base')) {
        require_once __DIR__ . '/request-url.php';
    }
    $fullBase = control_request_origin_base();
    $standaloneEnd = !empty($GLOBALS['control_layout_standalone']);
    ?>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('js/permissions.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo asset('js/control/system.js'); ?>?v=<?php echo time(); ?>"></script>
    <?php if (!$standaloneEnd): ?>
    <script src="<?php echo asset('js/control/header-support-alerts.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo htmlspecialchars(rtrim((string) (function_exists('control_ratib_pro_public_base_url') ? control_ratib_pro_public_base_url() : preg_replace('#/control-panel$#', '', $fullBase)), '/') . '/js/utils/global-ai-action.js?v=' . time(), ENT_QUOTES, 'UTF-8'); ?>"></script>
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
        ?>
    <script src="<?php echo htmlspecialchars($jsSrc . $jsVer); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
<?php
}
