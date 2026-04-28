<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/simple_modal.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/simple_modal.php`.
 */
// SIMPLE WORKING MODAL
function showModal($permission) {
    $userId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 0;
    $roleId = isset($_SESSION["role_id"]) ? $_SESSION["role_id"] : 0;
    $dashboardUrl = function_exists('ratib_country_dashboard_url')
        ? ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0))
        : "../pages/dashboard.php";
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Access Denied</title>";
    if (!function_exists('asset')) {
        require_once __DIR__ . '/config.php';
    }
    echo '<link rel="stylesheet" href="' . asset('css/modal.css') . '">';
    echo "</head><body>";
    echo "<div class=\"modal\">";
    echo "<div class=\"icon\">⚠️</div>";
    echo "<div class=\"title\">Access Denied</div>";
    echo "<div class=\"message\">You do not have permission to access this page.</div>";
    echo "<div class=\"permission\">Required permission: <strong>$permission</strong></div>";
    echo "<div class=\"info\">";
    echo "<strong>User ID:</strong> $userId<br>";
    echo "<strong>Role ID:</strong> $roleId<br>";
    echo "<strong>Status:</strong> BLOCKED<br>";
    echo "</div>";
    echo "<div class=\"message\">Please contact your administrator if you believe this is an error.</div>";
    echo "<a href=\"" . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . "\" class=\"btn\">OK</a>";
    echo "</div>";
    echo "</body></html>";
    exit();
}

// SIMPLE PERMISSION CHECK
function checkPermission($permission) {
    session_start();
    require_once "../includes/config.php";
    
    $userId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 0;
    $roleId = isset($_SESSION["role_id"]) ? $_SESSION["role_id"] : 0;
    
    // Admin has all permissions
    if ($roleId == 1) {
        return true;
    }
    
    // Check granular permissions
    $stmt = $conn->prepare("
        SELECT p.permission_key 
        FROM permissions p 
        INNER JOIN role_permissions rp ON p.permission_id = rp.permission_id 
        INNER JOIN users u ON rp.role_id = u.role_id 
        WHERE u.user_id = ? AND p.permission_key = ?
    ");
    $stmt->bind_param("is", $userId, $permission);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasPermission = $result->num_rows > 0;
    $stmt->close();
    
    if (!$hasPermission) {
        showModal($permission);
    }
    
    return true;
}
?>