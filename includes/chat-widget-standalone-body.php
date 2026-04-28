<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/chat-widget-standalone-body.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/chat-widget-standalone-body.php`.
 */
/**
 * Chat widget markup + scripts for standalone pages under /pages/ (login, forgot-password, etc.).
 * Optional: set $chatWidgetPlaceholder (string) before including.
 * Preconditions: config loaded, asset() available.
 */
if (!function_exists('asset')) {
    return;
}
$chatPlaceholder = isset($chatWidgetPlaceholder) && is_string($chatWidgetPlaceholder) && $chatWidgetPlaceholder !== ''
    ? $chatWidgetPlaceholder
    : 'Ask about login, Workers… or: I need to talk to support';
$chatPlaceholderAttr = htmlspecialchars($chatPlaceholder, ENT_QUOTES, 'UTF-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$dir = dirname($sn);
$appRoot = preg_replace('#/pages$#', '', $dir);
if ($appRoot === $dir) {
    $appRoot = '';
}
$ratibChatBase = rtrim($scheme . '://' . $host . ($appRoot === '/' ? '' : $appRoot), '/');

$builtinPath = __DIR__ . '/../js/help-center/help-center-builtin-content.js';
$chatJsPath = __DIR__ . '/../js/chat-widget.js';
$builtinV = file_exists($builtinPath) ? filemtime($builtinPath) : time();
$chatJsV = file_exists($chatJsPath) ? filemtime($chatJsPath) : time();
?>
<button type="button" class="chat-widget-button" id="chatWidgetButton" aria-label="Open chat support">
    <i class="fas fa-comments"></i>
</button>
<div class="chat-widget-container" id="chatWidgetContainer">
    <div class="chat-widget-header">
        <div class="chat-widget-header-info">
            <div class="chat-widget-header-avatar" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i></div>
            <div class="chat-widget-header-text">
                <h3>Ratib Assistant</h3>
                <p class="online">Help guides &amp; live support</p>
            </div>
        </div>
        <div class="chat-widget-header-actions">
            <button type="button" class="chat-widget-clear" id="chatWidgetClear" aria-label="Clear conversation" title="Clear assistant chat">
                <i class="fas fa-trash-alt"></i>
            </button>
            <button type="button" class="chat-widget-close" id="chatWidgetClose" aria-label="Close chat">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="chat-widget-messages" id="chatWidgetMessages"></div>
    <div class="chat-widget-input-area">
        <div class="chat-widget-input-wrapper">
            <textarea class="chat-widget-input" id="chatWidgetInput" rows="1" placeholder="<?php echo $chatPlaceholderAttr; ?>" data-chat-widget-placeholder="<?php echo $chatPlaceholderAttr; ?>"></textarea>
            <button type="button" class="chat-widget-send" id="chatWidgetSend" aria-label="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>
<script>window.RATIB_BASE_URL = <?php echo json_encode($ratibChatBase); ?>;</script>
<script src="<?php echo htmlspecialchars(asset('js/help-center/help-center-builtin-content.js')); ?>?v=<?php echo (int) $builtinV; ?>"></script>
<script src="<?php echo htmlspecialchars(asset('js/chat-widget.js')); ?>?v=<?php echo (int) $chatJsV; ?>"></script>
