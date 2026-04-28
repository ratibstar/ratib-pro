<?php
/**
 * EN: Handles API endpoint/business logic in `api/dashboard/stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/dashboard/stats.php`.
 */
// EN: Buffer output so endpoint always returns clean JSON payload.
// AR: تخزين المخرجات مؤقتاً لضمان إرجاع JSON نظيف دائماً.
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';

// EN: Session guard to prevent dashboard stats leakage to unauthenticated requests.
// AR: حماية الجلسة لمنع كشف إحصاءات اللوحة للطلبات غير المصادق عليها.
// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ob_clean();
    echo ApiResponse::error('Unauthorized');
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stats = [
        'unread_notifications' => 0,
        'last_login_time' => 'Never'
    ];
    
    // EN: Fetch lightweight counters first for fast dashboard render.
    // AR: جلب العدادات الخفيفة أولاً لتسريع عرض لوحة التحكم.
    // Get unread notifications count - use contact_notifications table (matches dashboard.php)
    try {
        // Check if contact_notifications table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'contact_notifications'");
        if ($tableCheck && $tableCheck->rowCount() > 0) {
            // Count unread notifications (status != 'read' means unread)
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contact_notifications WHERE status != 'read'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['unread_notifications'] = (int)($result['count'] ?? 0);
        }
    } catch (Exception $e) {
        error_log("Error fetching unread notifications: " . $e->getMessage());
    }
    
    // EN: Resolve last login with audit-first strategy then fallback to users table.
    // AR: تحديد آخر تسجيل دخول عبر سجلات النشاط أولاً ثم الرجوع لجدول المستخدمين.
    // Get last login time: prefer activity_logs 'login' action, fallback to users.last_login
    try {
        $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        if ($userId <= 0) {
            $stats['last_login_time'] = 'Never';
        } else {
        $stmt = $conn->prepare("
            SELECT created_at 
            FROM activity_logs 
            WHERE user_id = ? AND action = 'login' 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['created_at'])) {
            $stats['last_login_time'] = date('M d, Y H:i', strtotime($result['created_at']));
        } else {
            $stmt = $conn->prepare("SELECT last_login FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['last_login_time'] = ($userRow && !empty($userRow['last_login']))
                ? date('M d, Y H:i', strtotime($userRow['last_login']))
                : 'Never';
        }
        }
    } catch (Exception $e) {
        error_log("Error fetching last login time: " . $e->getMessage());
    }
    
    ob_clean();
    echo ApiResponse::success($stats);
    exit;
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    ob_clean();
    echo ApiResponse::error($e->getMessage());
    exit;
}
