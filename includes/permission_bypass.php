<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/permission_bypass.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/permission_bypass.php`.
 */
// Temporary permission bypass for API testing
function checkApiPermission($permission) {
    // Allow all permissions for now
    return true;
}

function checkApiActionPermission($permission) {
    // Allow all permissions for now
    return true;
}

function checkPermission($permission, $return_json = false) {
    // Allow all permissions for now
    return true;
}
?>