<?php
/**
 * EN: Handles API endpoint/business logic in `api/settings/handler.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/settings/handler.php`.
 */
// EN: API response headers tuned for JSON payloads and short-lived caching.
// AR: ترويسات API مهيأة لحمولات JSON مع تخزين مؤقت قصير.
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

// Enable output buffering
ob_start('ob_gzhandler');

require_once __DIR__ . '/../../config/database.php';

// Error reporting (Production: log only, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Request started: " . print_r($_POST, true));

// EN: Service class centralizing CRUD operations for settings_items table.
// AR: فئة خدمية توحّد عمليات CRUD لجدول settings_items.
class SettingsHandler {
    private $conn;
    private $stmt = [];
    
    public function __construct($conn) {
        $this->conn = $conn;
        // Prepare statements once
        $this->prepareStatements();
    }
    
    private function prepareStatements() {
        $this->stmt['getData'] = $this->conn->prepare(
            "SELECT * FROM settings_items USE INDEX (idx_category_type) 
             WHERE category = ? AND type = ? 
             ORDER BY id DESC LIMIT 1000"
        );
    }
    
    public function getData($category, $type) {
        try {
            if (!$this->stmt['getData']->execute([$category, $type])) {
                throw new Exception("Query failed");
            }
            
            $result = $this->stmt['getData']->get_result();
            $data = [];
            
            while ($row = $result->fetch_assoc()) {
                $row['data'] = json_decode($row['data'], true);
                $data[] = $row;
            }
            
            return ['success' => true, 'data' => $data];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // EN: Add flow splits normalized core fields and flexible JSON extra payload.
    // AR: مسار الإضافة يقسم الحقول الأساسية المعيارية عن البيانات الإضافية المرنة بصيغة JSON.
    public function addItem($category, $type, $data) {
        try {
            error_log("Adding item - Category: $category, Type: $type, Data: " . print_r($data, true));
            
            // Remove action from data
            unset($data['action']);
            
            // Split data into main fields and extra data
            $mainData = [
                'category' => $category,
                'type' => $type,
                'name_en' => $data['name_en'] ?? '',
                'name_ar' => $data['name_ar'] ?? ''
            ];
            
            // Put remaining fields in data JSON
            unset($data['name_en'], $data['name_ar'], $data['category'], $data['type']);
            $extraData = json_encode($data);
            
            error_log("Main data: " . print_r($mainData, true));
            error_log("Extra data: " . $extraData);
            
            $sql = "INSERT INTO settings_items (category, type, name_en, name_ar, data) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Database error: " . $this->conn->error);
            }
            
            $stmt->bind_param("sssss", 
                $mainData['category'],
                $mainData['type'],
                $mainData['name_en'],
                $mainData['name_ar'],
                $extraData
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to add item: " . $stmt->error);
            }
            
            $newId = $this->conn->insert_id;
            
            // Log history
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath) && $newId) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    // Get created item
                    $getStmt = $this->conn->prepare("SELECT * FROM settings_items WHERE id = ?");
                    $getStmt->bind_param('i', $newId);
                    $getStmt->execute();
                    $result = $getStmt->get_result();
                    $newItem = $result->fetch_assoc();
                    if ($newItem) {
                        @logGlobalHistory('settings_items', $newId, 'create', 'settings', null, $newItem);
                    }
                }
            }
            
            return ['success' => true, 'message' => 'Item added successfully'];
            
        } catch (Exception $e) {
            error_log("Error in addItem: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getItem($category, $type, $id) {
        try {
            $sql = "SELECT * FROM settings_items WHERE category = ? AND type = ? AND id = ?";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Database error: " . $this->conn->error);
            }
            
            $stmt->bind_param("ssi", $category, $type, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Item not found");
            }
            
            $row = $result->fetch_assoc();
            $row['data'] = json_decode($row['data'], true);
            
            return ['success' => true, 'data' => $row];
            
        } catch (Exception $e) {
            error_log("Error in getItem: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // EN: Update flow preserves audit history by capturing before/after snapshots.
    // AR: مسار التعديل يحافظ على سجل المراجعة عبر حفظ حالة قبل/بعد التغيير.
    public function editItem($category, $type, $id, $data) {
        try {
            // Remove action, category, type, id from data
            unset($data['action'], $data['category'], $data['type'], $data['id']);
            
            // Split data into main fields and extra data
            $mainData = [
                'name_en' => $data['name_en'] ?? '',
                'name_ar' => $data['name_ar'] ?? ''
            ];
            
            // Put remaining fields in data JSON
            unset($data['name_en'], $data['name_ar']);
            $extraData = json_encode($data);
            
            $sql = "UPDATE settings_items SET name_en = ?, name_ar = ?, data = ? WHERE id = ? AND category = ? AND type = ?";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Database error: " . $this->conn->error);
            }
            
            $stmt->bind_param("ssssss", 
                $mainData['name_en'],
                $mainData['name_ar'],
                $extraData,
                $id,
                $category,
                $type
            );
            
            // Get old data for history
            $oldStmt = $this->conn->prepare("SELECT * FROM settings_items WHERE id = ? AND category = ? AND type = ?");
            $oldStmt->bind_param("iss", $id, $category, $type);
            $oldStmt->execute();
            $oldResult = $oldStmt->get_result();
            $oldItem = $oldResult->fetch_assoc();
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update item: " . $stmt->error);
            }
            
            // Get updated item for history
            $newStmt = $this->conn->prepare("SELECT * FROM settings_items WHERE id = ? AND category = ? AND type = ?");
            $newStmt->bind_param("iss", $id, $category, $type);
            $newStmt->execute();
            $newResult = $newStmt->get_result();
            $newItem = $newResult->fetch_assoc();
            
            // Log history
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath) && $oldItem && $newItem) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    @logGlobalHistory('settings_items', $id, 'update', 'settings', $oldItem, $newItem);
                }
            }
            
            return ['success' => true, 'message' => 'Item updated successfully'];
            
        } catch (Exception $e) {
            error_log("Error in editItem: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function deleteItem($category, $type, $id) {
        try {
            $sql = "DELETE FROM settings_items WHERE id = ? AND category = ? AND type = ?";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Database error: " . $this->conn->error);
            }
            
            // Get old data for history
            $oldStmt = $this->conn->prepare("SELECT * FROM settings_items WHERE id = ? AND category = ? AND type = ?");
            $oldStmt->bind_param("iss", $id, $category, $type);
            $oldStmt->execute();
            $oldResult = $oldStmt->get_result();
            $oldItem = $oldResult->fetch_assoc();
            
            $stmt->bind_param("iss", $id, $category, $type);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete item: " . $stmt->error);
            }
            
            // Log history
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath) && $oldItem) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    @logGlobalHistory('settings_items', $id, 'delete', 'settings', $oldItem, null);
                }
            }
            
            return ['success' => true, 'message' => 'Item deleted successfully'];
            
        } catch (Exception $e) {
            error_log("Error in deleteItem: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// EN: Request router validates action + required args then dispatches to handler methods.
// AR: موجّه الطلبات يتحقق من الإجراء والمعاملات المطلوبة ثم يوجه للدوال المناسبة.
// Handle API requests
try {
    $handler = new SettingsHandler($conn);
    
    if (!isset($_POST['action'])) {
        throw new Exception('Action is required');
    }
    
    $action = $_POST['action'];
    $category = $_POST['category'] ?? '';
    $type = $_POST['type'] ?? '';
    
    error_log("Received request - Action: $action, Category: $category, Type: $type");
    
    switch ($action) {
        case 'get_data':
            if (empty($category) || empty($type)) {
                throw new Exception('Category and type are required');
            }
            $response = $handler->getData($category, $type);
            break;
            
        case 'get_item':
            if (empty($category) || empty($type) || empty($_POST['id'])) {
                throw new Exception('Category, type and id are required');
            }
            $response = $handler->getItem($category, $type, $_POST['id']);
            break;
            
        case 'add_item':
            if (empty($category) || empty($type)) {
                throw new Exception('Category and type are required');
            }
            $response = $handler->addItem($category, $type, $_POST);
            break;
            
        case 'edit_item':
            if (empty($category) || empty($type) || empty($_POST['id'])) {
                throw new Exception('Category, type and id are required');
            }
            $response = $handler->editItem($category, $type, $_POST['id'], $_POST);
            break;
            
        case 'delete_item':
            if (empty($category) || empty($type) || empty($_POST['id'])) {
                throw new Exception('Category, type and id are required');
            }
            $response = $handler->deleteItem($category, $type, $_POST['id']);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Flush output buffer
ob_end_flush();