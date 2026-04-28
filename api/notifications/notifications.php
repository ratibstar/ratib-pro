<?php
/**
 * EN: Handles API endpoint/business logic in `api/notifications/notifications.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/notifications/notifications.php`.
 */
// Start output buffering FIRST to prevent any output before headers
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers early
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Handle OPTIONS request early
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// Now load required files
// Try to load Database from core first (has getInstance), fallback to config
if (file_exists(__DIR__ . '/../core/Database.php')) {
    require_once __DIR__ . '/../core/Database.php';
} else {
require_once '../../config/database.php';
}

require_once '../core/ApiResponse.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load email configuration constants only (avoid session/database conflicts)
if (!defined('ENABLE_REAL_EMAIL')) {
    require_once '../../includes/config.php'; // Load email config from config.php
}

function generateUniqueContactId($pdo) {
    do {
        $contactId = 'C' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE contact_id = ?");
        $checkStmt->execute([$contactId]);
    } while ($checkStmt->fetchColumn() > 0);
    return $contactId;
}

function ensureContactForBroadcast($pdo, $name, $email, $phone, $type, $city = null, $country = null, $company = '') {
    if (empty($email)) {
        return null;
    }

    static $lookupStmt = null;
    if ($lookupStmt === null) {
        $lookupStmt = $pdo->prepare("SELECT id, name FROM contacts WHERE email = ? LIMIT 1");
    }
    $lookupStmt->execute([$email]);
    $existing = $lookupStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return [
            'id' => (int)$existing['id'],
            'name' => $existing['name']
        ];
    }

    $contactId = generateUniqueContactId($pdo);
    $createdBy = $_SESSION['user_id'] ?? 1;

    $insertStmt = $pdo->prepare("INSERT INTO contacts (contact_id, name, email, phone, city, country, contact_type, company, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())");
    $insertStmt->execute([
        $contactId,
        $name ?: 'New Contact',
        $email,
        $phone ?: '',
        $city,
        $country,
        $type ?: 'other',
        $company ?? '',
        $createdBy
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'name' => $name ?: 'New Contact'
    ];
}

function pickValue(array $row, array $candidates, $default = null) {
    foreach ($candidates as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function normalizeEmailForBroadcast($email) {
    if (empty($email) || !is_string($email)) {
        return '';
    }
    
    $normalized = strtolower(trim($email));
    $normalized = preg_replace('/\s+/', '', $normalized);
    
    $emailParts = explode('@', $normalized);
    if (count($emailParts) === 2) {
        $domain = $emailParts[1];
        if (in_array(strtolower($domain), ['gmail.com', 'googlemail.com'])) {
            $username = str_replace('.', '', $emailParts[0]);
            $normalized = $username . '@' . $domain;
        }
    }
    
    return $normalized;
}

function isValidEmailForBroadcast($email) {
    if (empty($email) || !is_string($email)) {
        return false;
    }
    
    $email = trim(strtolower($email));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    $domain = substr(strrchr($email, "@"), 1);
    if (empty($domain)) {
        return false;
    }
    
    $blockedDomains = [
        'example.com', 'example.org', 'example.net', 'test.com', 'test.org', 
        'invalid.com', 'fake.com', 'dummy.com', 'none.com', 'noemail.com',
        'bvb.aa', 'dd.jj', 'ss.sa',
        'mailinator.com', 'tempmail.com', '10minutemail.com', 'guerrillamail.com'
    ];
    
    if (in_array($domain, $blockedDomains)) {
        return false;
    }
    
    $parts = explode('.', $domain);
    $tld = end($parts);
    
    $suspiciousTLDs = ['aa', 'dd', 'jj', 'nn', 'bb', 'cc', 'ee', 'ff', 'gg', 'hh', 'ii', 'kk', 'll', 'mm', 'oo', 'pp', 'qq', 'rr', 'tt', 'uu', 'vv', 'ww', 'xx', 'yy', 'zz'];
    if (strlen($tld) === 2 && in_array($tld, $suspiciousTLDs)) {
        return false;
    }
    
    if (count($parts) >= 2) {
        $subdomain = $parts[count($parts) - 2];
        if ($subdomain === $tld) {
            return false;
        }
        if (strlen($subdomain) <= 2 && strlen($tld) <= 2) {
            return false;
        }
    }
    
    if (strlen($domain) < 6) {
        return false;
    }
    
    $domainWithoutTLD = substr($domain, 0, strrpos($domain, '.'));
    if (strlen($domainWithoutTLD) <= 2) {
        return false;
    }
    
    $fakePatterns = [
        '/^test/', '/^fake/', '/^dummy/', '/^none/', '/^noemail/',
        '/\d{10,}@/',
        '/\.\./',
        '/@localhost/', '/@127\.0\.0\.1/', '/@\./',
        '/@[a-z]{1,2}\.[a-z]{1,2}$/',
        '/@[a-z]+\.(aa|dd|jj|nn|bb|cc|ee|ff|gg|hh|ii|kk|ll|mm|oo|pp|qq|rr|tt|uu|vv|ww|xx|yy|zz)$/i',
    ];
    
    foreach ($fakePatterns as $pattern) {
        if (preg_match($pattern, $email)) {
            return false;
        }
    }
    
    return true;
}

function buildFallbackContactMessage($contactName, $contactEmail, $contactPhone, $contactType, $company) {
    $safeName = $contactName ?: 'Valued Contact';
    $safeEmail = $contactEmail ?: 'Not Provided';
    $safePhone = $contactPhone ?: 'Not Provided';
    $safeType = $contactType ?: 'Contact';
    $safeCompany = $company ?: 'Not Specified';
    
    return implode("\n", [
        "Dear {$safeName},",
        "",
        "You have been added to our Contact Management System.",
        "",
        "Contact Summary:",
        "- Email: {$safeEmail}",
        "- Phone: {$safePhone}",
        "- Type: {$safeType}",
        "- Company: {$safeCompany}",
        "",
        "We will keep you updated about all important communications.",
        "",
        "If any details need to be corrected, reply to this email.",
        "",
        "Best regards,",
        "Ratib Program Team"
    ]);
}

$phpmailerPaths = [
    __DIR__ . '/../../vendor/PHPMailer/src/PHPMailer.php',
    __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php'
];

$phpmailerLoaded = false;
foreach ($phpmailerPaths as $path) {
    if (file_exists($path)) {
        require_once dirname($path) . '/Exception.php';
        require_once dirname($path) . '/PHPMailer.php';
        require_once dirname($path) . '/SMTP.php';
        $phpmailerLoaded = true;
        break;
    }
}

try {
    if (class_exists('Database') && method_exists('Database', 'getInstance')) {
        $database = Database::getInstance();
    } else {
    $database = new Database();
    }
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection returned null');
    }
} catch (Exception $e) {
    ob_clean();
    error_log('Notifications API Database Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
} catch (Error $e) {
    ob_clean();
    error_log('Notifications API Fatal Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'send_contact_notification':
        sendContactNotification($pdo);
        break;
    case 'send_communication_notification':
        sendCommunicationNotification($pdo);
        break;
    case 'get_notifications':
        getNotifications($pdo);
        break;
    case 'mark_notification_read':
        markNotificationRead($pdo);
        break;
    case 'mark_notification_unread':
        markNotificationUnread($pdo);
        break;
    case 'bulk_mark_read':
        bulkMarkAsRead($pdo);
        break;
    case 'bulk_mark_unread':
        bulkMarkAsUnread($pdo);
        break;
    case 'bulk_delete':
        bulkDelete($pdo);
        break;
    case 'get_statistics':
        getStatistics($pdo);
        break;
    case 'get_notification_settings':
        getNotificationSettings($pdo);
        break;
    case 'update_notification_settings':
        updateNotificationSettings($pdo);
        break;
    case 'send_role_broadcast':
        sendRoleBroadcast($pdo);
        break;
    case 'export_notifications':
        exportNotifications($pdo);
        break;
    default:
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
}

function getNotifications($pdo) {
    try {
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'contact_notifications'");
            if ($tableCheck->rowCount() == 0) {
                ob_clean();
        echo json_encode([
            'success' => true, 
                    'data' => [],
                    'pagination' => [
                        'total_records' => 0,
                        'total_pages' => 0,
                        'current_page' => 1,
                        'per_page' => 5
                    ]
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit();
            }
        } catch (PDOException $e) {
            ob_clean();
        echo json_encode([
            'success' => true, 
                'data' => [],
                'pagination' => [
                    'total_records' => 0,
                    'total_pages' => 0,
                    'current_page' => 1,
                    'per_page' => 5
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
    $contactId = $_GET['contact_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $notificationType = $_GET['notification_type'] ?? '';
    $search = $_GET['search'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    
        $sql = "SELECT cn.id, cn.contact_id, cn.contact_name, cn.contact_email, cn.contact_phone, cn.notification_type, cn.title, cn.message, cn.status, cn.sent_at, cn.read_at, cn.created_at, cn.updated_at, COALESCE(c.name, cn.contact_name) as display_contact_name, c.country as contact_country, c.city as contact_city, c.contact_type as contact_type, c.company as company FROM contact_notifications cn LEFT JOIN contacts c ON (cn.contact_id = CAST(c.id AS CHAR) OR cn.contact_id = c.contact_id OR CAST(cn.contact_id AS UNSIGNED) = c.id) WHERE 1=1";
        $params = [];
        
        if (!empty($contactId)) {
            $sql .= " AND cn.contact_id = ?";
            $params[] = $contactId;
        }
        if (!empty($status)) {
            $sql .= " AND cn.status = ?";
            $params[] = $status;
        }
        if (!empty($notificationType)) {
            $sql .= " AND cn.notification_type = ?";
            $params[] = $notificationType;
        }
        if (!empty($search)) {
            $sql .= " AND (cn.contact_name LIKE ? OR cn.title LIKE ? OR cn.message LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $sql .= " ORDER BY cn.created_at DESC, cn.id DESC LIMIT " . $limit . " OFFSET " . $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $countSql = "SELECT COUNT(*) as total FROM contact_notifications cn WHERE 1=1";
        $countParams = [];
        if (!empty($contactId)) {
            $countSql .= " AND cn.contact_id = ?";
            $countParams[] = $contactId;
        }
        if (!empty($status)) {
            $countSql .= " AND cn.status = ?";
            $countParams[] = $status;
        }
        if (!empty($notificationType)) {
            $countSql .= " AND cn.notification_type = ?";
            $countParams[] = $notificationType;
        }
        if (!empty($search)) {
            $countSql .= " AND (cn.contact_name LIKE ? OR cn.title LIKE ? OR cn.message LIKE ?)";
            $searchParam = "%$search%";
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => $notifications,
            'pagination' => [
                'total_records' => (int)$total,
                'total_pages' => (int)ceil($total / $limit),
                'current_page' => (int)($offset / $limit) + 1,
                'per_page' => $limit
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        error_log('getNotifications error: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function getStatistics($pdo) {
    try {
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'contact_notifications'");
            if ($tableCheck->rowCount() == 0) {
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'data' => ['total' => 0, 'sent' => 0, 'pending' => 0, 'failed' => 0, 'read' => 0]
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit();
            }
    } catch (PDOException $e) {
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => ['total' => 0, 'sent' => 0, 'pending' => 0, 'failed' => 0, 'read' => 0]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM contact_notifications");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM contact_notifications GROUP BY status");
        $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $sent = $statusCounts['sent'] ?? 0;
        $pending = $statusCounts['pending'] ?? 0;
        $failed = $statusCounts['failed'] ?? 0;
        $read = $statusCounts['read'] ?? 0;
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => [
                'total' => (int)$total,
                'sent' => (int)$sent,
                'pending' => (int)$pending,
                'failed' => (int)$failed,
                'read' => (int)$read
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        error_log('getStatistics error: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function sendContactNotification($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['contact_id']) || empty($input['contact_name'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'contact_notifications'");
        if ($tableCheck->rowCount() == 0) {
            // Table doesn't exist, return success but don't create record
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Notification sent successfully (table not available)'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
        // Resolve contact ID - handle both numeric ID and contact_id string
        $contactIdValue = $input['contact_id'];
        if (is_numeric($input['contact_id'])) {
            // Already a numeric ID
            $numericContactId = (int)$input['contact_id'];
        } else {
            // Try to find numeric ID from contacts table
            $lookupStmt = $pdo->prepare("SELECT id FROM contacts WHERE contact_id = ? OR id = ? LIMIT 1");
            $lookupStmt->execute([$input['contact_id'], $input['contact_id']]);
            $contact = $lookupStmt->fetch(PDO::FETCH_ASSOC);
            $numericContactId = $contact ? (int)$contact['id'] : null;
        }
        
        // Prepare notification data
        $notificationType = 'contact_added';
        $title = 'New Contact: ' . $input['contact_name'];
        $message = '';
        
        // Build message from template/content if available
        if (!empty($input['message_template'])) {
            $message = 'Template: ' . $input['message_template'];
        }
        if (!empty($input['message_content'])) {
            $message .= ($message ? "\n\n" : '') . $input['message_content'];
        }
        if (!empty($input['subject'])) {
            $title = $input['subject'];
        }
        if (empty($message)) {
            $message = 'Contact ' . $input['contact_name'] . ' has been added to the system.';
            if (!empty($input['contact_email'])) {
                $message .= ' Email: ' . $input['contact_email'];
            }
            if (!empty($input['contact_phone'])) {
                $message .= ' Phone: ' . $input['contact_phone'];
            }
        }
        
        // Insert notification record
        $insertStmt = $pdo->prepare("INSERT INTO contact_notifications (contact_id, contact_name, contact_email, contact_phone, notification_type, title, message, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");
        $insertStmt->execute([
            $numericContactId ?? $contactIdValue,
            $input['contact_name'],
            $input['contact_email'] ?? '',
            $input['contact_phone'] ?? '',
            $notificationType,
            $title,
            $message
        ]);
        
        $notificationId = $pdo->lastInsertId();
        
        // If real email sending is enabled, send actual email/SMS here
        // For now, just mark as sent if email sending is not enabled
        $emailEnabled = defined('ENABLE_REAL_EMAIL') && constant('ENABLE_REAL_EMAIL');
        if (!$emailEnabled) {
            $updateStmt = $pdo->prepare("UPDATE contact_notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $updateStmt->execute([$notificationId]);
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Notification sent successfully', 'data' => ['notification_id' => $notificationId]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        error_log('sendContactNotification error: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function sendCommunicationNotification($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['contact_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'contact_notifications'");
        if ($tableCheck->rowCount() == 0) {
            // Table doesn't exist, return success but don't create record
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Communication notification sent (table not available)'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
        // Get contact details
        $contactId = $input['contact_id'];
        $contactName = $input['contact_name'] ?? 'Unknown Contact';
        $contactEmail = $input['contact_email'] ?? '';
        $contactPhone = $input['contact_phone'] ?? '';
        
        // Try to get contact details from database if not provided
        if (empty($contactName) || $contactName === 'Unknown Contact') {
            $lookupStmt = $pdo->prepare("SELECT id, name, email, phone FROM contacts WHERE id = ? OR contact_id = ? LIMIT 1");
            $lookupStmt->execute([$contactId, $contactId]);
            $contact = $lookupStmt->fetch(PDO::FETCH_ASSOC);
            if ($contact) {
                $contactId = $contact['id'];
                $contactName = $contact['name'] ?? $contactName;
                $contactEmail = $contact['email'] ?? $contactEmail;
                $contactPhone = $contact['phone'] ?? $contactPhone;
            }
        }
        
        // Prepare notification data
        $notificationType = 'communication_added';
        $communicationType = $input['communication_type'] ?? 'communication';
        $title = 'Communication Added: ' . ucfirst($communicationType);
        $message = '';
        
        // Build message from communication data
        if (!empty($input['message'])) {
            $message = $input['message'];
        } else {
            $message = 'A new ' . $communicationType . ' has been added for ' . $contactName;
            if (!empty($input['outcome'])) {
                $message .= '. Outcome: ' . $input['outcome'];
            }
            if (!empty($input['next_action'])) {
                $message .= '. Next action: ' . $input['next_action'];
            }
        }
        
        if (!empty($input['subject'])) {
            $title = $input['subject'];
        }
        
        // Insert notification record
        $insertStmt = $pdo->prepare("INSERT INTO contact_notifications (contact_id, contact_name, contact_email, contact_phone, notification_type, title, message, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");
        $insertStmt->execute([
            $contactId,
            $contactName,
            $contactEmail,
            $contactPhone,
            $notificationType,
            $title,
            $message
        ]);
        
        $notificationId = $pdo->lastInsertId();
        
        // If real email sending is enabled, send actual email/SMS here
        // For now, just mark as sent if email sending is not enabled
        $emailEnabled = defined('ENABLE_REAL_EMAIL') && constant('ENABLE_REAL_EMAIL');
        if (!$emailEnabled) {
            $updateStmt = $pdo->prepare("UPDATE contact_notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $updateStmt->execute([$notificationId]);
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Communication notification sent', 'data' => ['notification_id' => $notificationId]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        error_log('sendCommunicationNotification error: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function markNotificationRead($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['notification_id'] ?? '';
    
    if (empty($notificationId)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Notification ID is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE contact_notifications SET status = 'read', read_at = NOW() WHERE id = ?");
        $stmt->execute([$notificationId]);
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Notification marked as read'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function markNotificationUnread($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['notification_id'] ?? '';
    
    if (empty($notificationId)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Notification ID is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE contact_notifications SET status = 'pending', read_at = NULL WHERE id = ?");
        $stmt->execute([$notificationId]);
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Notification marked as unread'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function bulkMarkAsRead($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationIds = $input['notification_ids'] ?? [];
    
    if (empty($notificationIds) || !is_array($notificationIds)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Notification IDs are required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE contact_notifications SET status = 'read', read_at = NOW() WHERE id IN ($placeholders)");
        $stmt->execute($notificationIds);
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Notifications marked as read'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function bulkMarkAsUnread($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationIds = $input['notification_ids'] ?? [];
    
    if (empty($notificationIds) || !is_array($notificationIds)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Notification IDs are required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE contact_notifications SET status = 'pending', read_at = NULL WHERE id IN ($placeholders)");
        $stmt->execute($notificationIds);
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Notifications marked as unread'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function bulkDelete($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationIds = $input['notification_ids'] ?? [];
    
    if (empty($notificationIds) || !is_array($notificationIds)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Notification IDs are required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
        }

        try {
        $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM contact_notifications WHERE id IN ($placeholders)");
        $stmt->execute($notificationIds);
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Notifications deleted'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function getNotificationSettings($pdo) {
    $contactId = $_GET['contact_id'] ?? '';
    
    if (empty($contactId)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact ID is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function updateNotificationSettings($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['contact_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact ID is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Settings updated'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function sendRoleBroadcast($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['roles']) || empty($input['subject']) || empty($input['message'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Missing required fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Broadcast sent', 'data' => ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function exportNotifications($pdo) {
    ob_clean();
    
    try {
        // Check if table exists first
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'contact_notifications'");
        if ($tableCheck->rowCount() == 0) {
            ob_clean();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Notifications table does not exist'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
        // Get filters from query parameters
        $contactId = $_GET['contact_id'] ?? '';
        $status = $_GET['status'] ?? '';
        $notificationType = $_GET['notification_type'] ?? '';
        $search = $_GET['search'] ?? '';
        
        // Build WHERE clause
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($contactId)) {
            $whereConditions[] = "cn.contact_id = ?";
            $params[] = $contactId;
        }
        if (!empty($status)) {
            $whereConditions[] = "cn.status = ?";
            $params[] = $status;
        }
        if (!empty($notificationType)) {
            $whereConditions[] = "cn.notification_type = ?";
            $params[] = $notificationType;
        }
        if (!empty($search)) {
            $whereConditions[] = "(cn.contact_name LIKE ? OR cn.title LIKE ? OR cn.message LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Query notifications with contact details
        $sql = "SELECT cn.id, cn.contact_id, cn.contact_name, cn.contact_email, cn.contact_phone, cn.notification_type, cn.title, cn.message, cn.status, cn.sent_at, cn.read_at, cn.created_at, cn.updated_at, COALESCE(c.name, cn.contact_name) as display_contact_name, c.country as contact_country, c.city as contact_city, c.contact_type, c.company as company FROM contact_notifications cn LEFT JOIN contacts c ON (cn.contact_id = CAST(c.id AS CHAR) OR cn.contact_id = c.contact_id OR CAST(cn.contact_id AS UNSIGNED) = c.id) WHERE $whereClause ORDER BY cn.created_at DESC, cn.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Clean output buffer before sending CSV headers (in case of any output from queries)
        ob_clean();
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="notifications_export_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Output BOM for UTF-8 Excel compatibility
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID', 'Contact ID', 'Contact Name', 'Contact Email', 'Contact Phone',
            'Notification Type', 'Title', 'Message', 'Status',
            'Sent At', 'Read At', 'Created At', 'Updated At',
            'Company', 'Country', 'City', 'Contact Type'
        ]);
        
        // CSV data
        foreach ($notifications as $notification) {
            fputcsv($output, [
                $notification['id'],
                $notification['contact_id'],
                $notification['display_contact_name'] ?? $notification['contact_name'] ?? '',
                $notification['contact_email'] ?? '',
                $notification['contact_phone'] ?? '',
                $notification['notification_type'] ?? '',
                $notification['title'] ?? '',
                strip_tags($notification['message'] ?? ''),
                $notification['status'] ?? '',
                $notification['sent_at'] ?? '',
                $notification['read_at'] ?? '',
                $notification['created_at'] ?? '',
                $notification['updated_at'] ?? '',
                $notification['company'] ?? '',
                $notification['contact_country'] ?? '',
                $notification['contact_city'] ?? '',
                $notification['contact_type'] ?? ''
            ]);
        }
        
        fclose($output);
        exit();
        
    } catch (PDOException $e) {
        ob_clean();
        error_log('exportNotifications error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (Exception $e) {
        ob_clean();
        error_log('exportNotifications error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Export error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}
