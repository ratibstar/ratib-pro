<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/bulletproof_overlay.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/bulletproof_overlay.php`.
 */
// BULLETPROOF OVERLAY MODAL - Simple and guaranteed to work
// Ensure permissions.php is loaded
if (!function_exists('hasPermission')) {
    require_once __DIR__ . '/permissions.php';
}

function showBulletproofModal($requiredPermission) {
    $userId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 0;
    $roleId = isset($_SESSION["role_id"]) ? $_SESSION["role_id"] : 0;
    
    echo "<div id=\"bulletproof-overlay\" class=\"final-overlay\">";
    echo "<div class=\"final-overlay-box\">";
    echo "<div class=\"final-overlay-icon\">⚠️</div>";
    echo "<div class=\"final-overlay-title\">Access Denied</div>";
    echo "<div class=\"final-overlay-text\">You do not have permission to access this page.</div>";
    echo "<div class=\"final-overlay-requirement\">Required permission: <strong>$requiredPermission</strong></div>";
    echo "<div class=\"final-overlay-user-info\">";
    echo "<strong>User ID:</strong> $userId<br>";
    echo "<strong>Role ID:</strong> $roleId<br>";
    echo "<strong>Status:</strong> BLOCKED<br>";
    echo "</div>";
    echo "<div class=\"final-overlay-contact-text\">Please contact your administrator if you believe this is an error.</div>";
    echo "<button class=\"final-overlay-btn\" data-overlay-close=\"bulletproof-overlay\">OK</button>";
    echo "</div>";
    echo "</div>";
}

// SIMPLE PERMISSION CHECK
function checkPermissionBulletproof($requiredPermission) {
    // Use centralized permission check function
    if (!hasPermission($requiredPermission)) {
        showBulletproofModal($requiredPermission);
        return false;
    }
    
    return true;
}
?>