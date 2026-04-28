<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/pages/control/support-chats.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/pages/control/support-chats.php`.
 */
/**
 * Control Panel: Support Chats
 * Unified layout with sidebar - loads content from old page
 */
require_once __DIR__ . '/../../includes/config.php';

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_SUPPORT, 'view_control_support');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    die('Control panel database unavailable.');
}

// Use unified layout
require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Support Chats', ['css/control/support-chats.css'], []);

// Absolute iframe URL to avoid path resolution issues
$iframeParams = array_intersect_key($_GET, array_flip(['status', 'page', 'limit', 'country_id']));
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$base = rtrim(getBaseUrl() ?: '', '/');
$iframePath = ($base ? $base . '/' : '') . 'pages/control-support-chats.php';
$iframeUrl = $scheme . '://' . $host . '/' . ltrim($iframePath, '/') . '?embedded=1' . (!empty($iframeParams) ? '&' . http_build_query($iframeParams) : '');
?>

<div class="support-chats-wrap">
    <iframe src="<?php echo htmlspecialchars($iframeUrl); ?>" 
            class="support-chats-frame"
            id="chatsFrame"
            frameborder="0"
            scrolling="yes"></iframe>
</div>

<?php endControlLayout(['js/control/support-chats-embed.js']); ?>
