<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/followups.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/followups.php`.
 */
/**
 * Follow-ups API
 * CRUD operations for accounting follow-ups/tasks
 */

require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_followups'");
    if ($tableCheck->num_rows === 0) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Follow-ups table not found. Please run setup first.']);
        exit;
    }
    
    switch ($method) {
        case 'GET':
            // List follow-ups
            $action = $_GET['action'] ?? 'list';
            $followupId = isset($_GET['id']) ? intval($_GET['id']) : null;
            
            if ($followupId) {
                // Get single follow-up
                $stmt = $conn->prepare("
                    SELECT f.*, 
                           u1.username as created_by_name,
                           u2.username as assigned_to_name
                    FROM accounting_followups f
                    LEFT JOIN users u1 ON f.created_by = u1.user_id
                    LEFT JOIN users u2 ON f.assigned_to = u2.user_id
                    WHERE f.id = ?
                ");
                $stmt->bind_param('i', $followupId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    echo json_encode(['success' => true, 'followup' => $row]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Follow-up not found']);
                }
            } else {
                // List follow-ups with filters
                $status = $_GET['status'] ?? '';
                $priority = $_GET['priority'] ?? '';
                $relatedType = $_GET['related_type'] ?? '';
                $relatedId = isset($_GET['related_id']) ? intval($_GET['related_id']) : null;
                $assignedTo = isset($_GET['assigned_to']) ? intval($_GET['assigned_to']) : null;
                $dueDateFrom = $_GET['due_date_from'] ?? '';
                $dueDateTo = $_GET['due_date_to'] ?? '';
                
                $where = [];
                $params = [];
                $types = '';
                
                if ($status) {
                    $where[] = "f.status = ?";
                    $params[] = $status;
                    $types .= 's';
                }
                if ($priority) {
                    $where[] = "f.priority = ?";
                    $params[] = $priority;
                    $types .= 's';
                }
                if ($relatedType) {
                    $where[] = "f.related_type = ?";
                    $params[] = $relatedType;
                    $types .= 's';
                }
                if ($relatedId) {
                    $where[] = "f.related_id = ?";
                    $params[] = $relatedId;
                    $types .= 'i';
                }
                if ($assignedTo) {
                    $where[] = "(f.assigned_to = ? OR f.assigned_to IS NULL)";
                    $params[] = $assignedTo;
                    $types .= 'i';
                }
                if ($dueDateFrom) {
                    $where[] = "f.due_date >= ?";
                    $params[] = $dueDateFrom;
                    $types .= 's';
                }
                if ($dueDateTo) {
                    $where[] = "f.due_date <= ?";
                    $params[] = $dueDateTo;
                    $types .= 's';
                }
                
                $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                
                $query = "
                    SELECT f.*, 
                           u1.username as created_by_name,
                           u2.username as assigned_to_name
                    FROM accounting_followups f
                    LEFT JOIN users u1 ON f.created_by = u1.user_id
                    LEFT JOIN users u2 ON f.assigned_to = u2.user_id
                    $whereClause
                    ORDER BY 
                        CASE f.priority 
                            WHEN 'urgent' THEN 1 
                            WHEN 'high' THEN 2 
                            WHEN 'medium' THEN 3 
                            WHEN 'low' THEN 4 
                        END,
                        f.due_date ASC,
                        f.created_at DESC
                ";
                
                $stmt = $conn->prepare($query);
                if (count($params) > 0) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                
                $followups = [];
                while ($row = $result->fetch_assoc()) {
                    $followups[] = $row;
                }
                
                echo json_encode(['success' => true, 'followups' => $followups, 'count' => count($followups)]);
            }
            break;
            
        case 'POST':
            // Create follow-up
            $data = json_decode(file_get_contents('php://input'), true);
            
            $title = $data['title'] ?? '';
            $description = $data['description'] ?? '';
            $relatedType = $data['related_type'] ?? '';
            $relatedId = isset($data['related_id']) ? intval($data['related_id']) : 0;
            $dueDate = $data['due_date'] ?? null;
            $priority = $data['priority'] ?? 'medium';
            $status = $data['status'] ?? 'pending';
            $assignedTo = isset($data['assigned_to']) ? intval($data['assigned_to']) : null;
            $reminderDate = $data['reminder_date'] ?? null;
            $notes = $data['notes'] ?? '';
            
            if (empty($title) || empty($relatedType)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Title and related type are required']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO accounting_followups 
                (title, description, related_type, related_id, due_date, priority, status, assigned_to, created_by, reminder_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param('sssisssiiss', 
                $title, $description, $relatedType, $relatedId, $dueDate, 
                $priority, $status, $assignedTo, $userId, $reminderDate, $notes
            );
            
            if ($stmt->execute()) {
                $followupId = $conn->insert_id;
                echo json_encode([
                    'success' => true, 
                    'message' => 'Follow-up created successfully',
                    'id' => $followupId
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error creating follow-up: ' . $conn->error]);
            }
            break;
            
        case 'PUT':
            // Update follow-up
            $data = json_decode(file_get_contents('php://input'), true);
            $followupId = isset($data['id']) ? intval($data['id']) : 0;
            
            if (!$followupId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Follow-up ID is required']);
                exit;
            }
            
            // Check if follow-up exists
            $checkStmt = $conn->prepare("SELECT id FROM accounting_followups WHERE id = ?");
            $checkStmt->bind_param('i', $followupId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Follow-up not found']);
                exit;
            }
            
            $updates = [];
            $params = [];
            $types = '';
            
            if (isset($data['title'])) {
                $updates[] = "title = ?";
                $params[] = $data['title'];
                $types .= 's';
            }
            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = $data['description'];
                $types .= 's';
            }
            if (isset($data['due_date'])) {
                $updates[] = "due_date = ?";
                $params[] = $data['due_date'];
                $types .= 's';
            }
            if (isset($data['priority'])) {
                $updates[] = "priority = ?";
                $params[] = $data['priority'];
                $types .= 's';
            }
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
                $types .= 's';
                // If marking as completed, set completed_at
                if ($data['status'] === 'completed') {
                    $updates[] = "completed_at = CURRENT_TIMESTAMP";
                }
            }
            if (isset($data['assigned_to'])) {
                $updates[] = "assigned_to = ?";
                $params[] = $data['assigned_to'] ? intval($data['assigned_to']) : null;
                $types .= 'i';
            }
            if (isset($data['reminder_date'])) {
                $updates[] = "reminder_date = ?";
                $params[] = $data['reminder_date'];
                $types .= 's';
            }
            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $data['notes'];
                $types .= 's';
            }
            
            if (count($updates) === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit;
            }
            
            $params[] = $followupId;
            $types .= 'i';
            
            $query = "UPDATE accounting_followups SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Follow-up updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error updating follow-up: ' . $conn->error]);
            }
            break;
            
        case 'DELETE':
            // Delete follow-up
            $followupId = isset($_GET['id']) ? intval($_GET['id']) : 0;
            
            if (!$followupId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Follow-up ID is required']);
                exit;
            }
            
            $stmt = $conn->prepare("DELETE FROM accounting_followups WHERE id = ?");
            $stmt->bind_param('i', $followupId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Follow-up deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error deleting follow-up: ' . $conn->error]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

