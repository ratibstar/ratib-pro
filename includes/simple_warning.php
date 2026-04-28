<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/simple_warning.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/simple_warning.php`.
 */
// Simple Warning System - Same warning for ALL pages
function showWarning($permission) {
    // Include warning CSS
    if (!function_exists('asset')) {
        require_once __DIR__ . '/config.php';
    }
    echo '<link rel="stylesheet" href="' . asset('css/warning.css') . '">';
    
    echo "<div class=\"warning-overlay\">
        <div class=\"warning-modal\">
            <div class=\"warning-icon\">⚠️</div>
            <div class=\"warning-title\">Access Denied</div>
            <div class=\"warning-message\">You do not have permission to access this page.</div>
            <div class=\"permission-info\">Required permission: <strong>$permission</strong></div>
            <div class=\"warning-message\">Please contact your administrator if you believe this is an error.</div>
            <button class=\"warning-button\" data-warning-close>OK</button>
        </div>
    </div>";
}

// Check if user has permission
function checkPermission($permission) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $roleId = isset($_SESSION["role_id"]) ? $_SESSION["role_id"] : 0;
    
    // Role ID 3 = NO ACCESS (shows warning)
    if ($roleId == 3) {
        return false;
    }
    
    // Role ID 1 = ALL ACCESS (shows content)
    if ($roleId == 1) {
        return true;
    }
    
    // Default = NO ACCESS
    return false;
}
?>