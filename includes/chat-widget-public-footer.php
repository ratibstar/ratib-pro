<?php
/**
 * Public marketing pages — floating chat (CMS knowledge + live support → control panel).
 *
 * Expects $baseUrl (or sets from request). Optional: $ratebHome (CMS), $chatWidgetPlaceholder.
 * Optional: $ratebPublicChatSkipCss = true when chat-widget.css is already in <head>.
 */
declare(strict_types=1);

if (!function_exists('asset')) {
    return;
}

require_once __DIR__ . '/rateb-public-chat-kb.php';

$ratebPublicChatBase = '';
if (isset($baseUrl) && is_string($baseUrl) && $baseUrl !== '') {
    $ratebPublicChatBase = rtrim($baseUrl, '/');
} elseif (isset($ratebChatBase) && is_string($ratebChatBase) && $ratebChatBase !== '') {
    $ratebPublicChatBase = rtrim($ratebChatBase, '/');
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($sn);
    $appRoot = preg_replace('#/pages$#', '', $dir);
    if ($appRoot === $dir) {
        $appRoot = '';
    }
    $ratebPublicChatBase = rtrim($scheme . '://' . $host . ($appRoot === '/' ? '' : $appRoot), '/');
}

$ratebHomeForChat = (isset($ratebHome) && is_array($ratebHome)) ? $ratebHome : [];
$ratebPublicChatKb = rateb_public_chat_kb_entries($ratebPublicChatBase, $ratebHomeForChat);

$chatTitle = trim((string) ($ratebHomeForChat['home.chat.title'] ?? 'RATEB Assistant'));
$chatSubtitle = trim((string) ($ratebHomeForChat['home.chat.subtitle'] ?? 'Public site help & live support'));
$chatPlaceholder = (isset($chatWidgetPlaceholder) && is_string($chatWidgetPlaceholder) && $chatWidgetPlaceholder !== '')
    ? $chatWidgetPlaceholder
    : 'Ask about register, domains, pricing… or: I need to talk to support';
$chatPlaceholderAttr = htmlspecialchars($chatPlaceholder, ENT_QUOTES, 'UTF-8');
$chatTitleAttr = htmlspecialchars($chatTitle, ENT_QUOTES, 'UTF-8');
$chatSubtitleAttr = htmlspecialchars($chatSubtitle, ENT_QUOTES, 'UTF-8');

$skipCss = !empty($ratebPublicChatSkipCss);
if (!$skipCss) {
    $chatCssV = is_file(__DIR__ . '/../css/chat-widget.css') ? filemtime(__DIR__ . '/../css/chat-widget.css') : time();
    echo '<link rel="stylesheet" href="' . htmlspecialchars(asset('css/chat-widget.css'), ENT_QUOTES, 'UTF-8') . '?v=' . (int) $chatCssV . '">' . "\n";
}

$builtinPath = __DIR__ . '/../js/help-center/help-center-builtin-content.js';
$chatJsPath = __DIR__ . '/../js/chat-widget.js';
$builtinV = is_file($builtinPath) ? filemtime($builtinPath) : time();
$chatJsV = is_file($chatJsPath) ? filemtime($chatJsPath) : time();
?>
<button type="button" class="chat-widget-button" id="chatWidgetButton" aria-label="Open chat support">
    <i class="fas fa-comments"></i>
</button>
<div class="chat-widget-container" id="chatWidgetContainer">
    <div class="chat-widget-header" data-chat-header-lock="1">
        <div class="chat-widget-header-info">
            <div class="chat-widget-header-avatar" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i></div>
            <div class="chat-widget-header-text">
                <h3><?php echo $chatTitleAttr; ?></h3>
                <p class="online"><?php echo $chatSubtitleAttr; ?></p>
            </div>
        </div>
        <div class="chat-widget-header-actions">
            <button type="button" class="chat-widget-clear" id="chatWidgetClear" aria-label="Clear conversation">
                <i class="fas fa-trash-alt" aria-hidden="true"></i>
            </button>
            <button type="button" class="chat-widget-close" id="chatWidgetClose" aria-label="Close chat">
                <i class="fas fa-times" aria-hidden="true"></i>
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
<script>window.RATEB_BASE_URL = <?php echo json_encode($ratebPublicChatBase, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
<script>window.RATEB_CHAT_CONTEXT = 'public';</script>
<script>window.RATEB_PUBLIC_CHAT_KB = <?php echo json_encode($ratebPublicChatKb, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?php echo htmlspecialchars(asset('js/help-center/help-center-builtin-content.js')); ?>?v=<?php echo (int) $builtinV; ?>"></script>
<script src="<?php echo htmlspecialchars(asset('js/chat-widget.js')); ?>?v=<?php echo (int) $chatJsV; ?>"></script>
