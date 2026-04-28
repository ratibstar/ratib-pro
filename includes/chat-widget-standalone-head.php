<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/chat-widget-standalone-head.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/chat-widget-standalone-head.php`.
 */
/**
 * Chat widget styles for standalone auth-style pages (not using includes/footer.php).
 * Preconditions: config loaded, asset() available.
 */
if (!function_exists('asset')) {
    return;
}
$chatCssV = file_exists(__DIR__ . '/../css/chat-widget.css')
    ? filemtime(__DIR__ . '/../css/chat-widget.css')
    : time();
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(asset('css/chat-widget.css')); ?>?v=<?php echo (int) $chatCssV; ?>">
