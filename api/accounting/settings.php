<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/settings.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/settings.php`.
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

// Check permissions
if ($method === 'GET') {
    enforceApiPermission('accounts', 'view');
} elseif ($method === 'POST' || $method === 'PUT') {
    enforceApiPermission('accounts', 'edit');
} elseif ($method === 'DELETE') {
    enforceApiPermission('accounts', 'delete');
}

try {
    // Check if accounting_settings table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_settings'");
    if ($tableCheck->num_rows === 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS accounting_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                setting_type ENUM('text', 'number', 'boolean', 'json', 'date') DEFAULT 'text',
                description TEXT,
                category VARCHAR(50) DEFAULT 'general',
                is_system BOOLEAN DEFAULT FALSE,
                is_required BOOLEAN DEFAULT FALSE,
                display_order INT DEFAULT 0,
                updated_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_setting_key (setting_key),
                FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Insert default settings
        $defaultSettings = [
            ['default_tax_rate', '15', 'number', 'Default tax rate percentage', 'tax', FALSE, FALSE, 1],
            ['tax_calculation_method', 'inclusive', 'text', 'Tax calculation method (inclusive/exclusive)', 'tax', FALSE, FALSE, 2],
            ['default_currency', 'SAR', 'text', 'Default currency code', 'currency', FALSE, FALSE, 1],
            ['fiscal_year_start', date('Y') . '-01-01', 'date', 'Fiscal year start date', 'fiscal', FALSE, FALSE, 1],
            ['fiscal_year_end', date('Y') . '-12-31', 'date', 'Fiscal year end date', 'fiscal', FALSE, FALSE, 2],
            ['number_format', 'standard', 'text', 'Number format style', 'formatting', FALSE, FALSE, 1],
            ['decimal_places', '2', 'number', 'Number of decimal places', 'formatting', FALSE, FALSE, 2],
            ['thousand_separator', 'comma', 'text', 'Thousand separator style', 'formatting', FALSE, FALSE, 3],
            ['accounting_method', 'accrual', 'text', 'Accounting method (accrual/cash)', 'general', FALSE, FALSE, 1],
            ['auto_numbering', 'enabled', 'text', 'Auto numbering for entries', 'general', FALSE, FALSE, 2],
            ['require_approval', 'disabled', 'text', 'Require approval for entries', 'approval', FALSE, FALSE, 1],
            ['approval_threshold', '0', 'number', 'Approval threshold amount', 'approval', FALSE, FALSE, 2]
        ];

        $stmt = $conn->prepare("
            INSERT INTO accounting_settings (setting_key, setting_value, setting_type, description, category, is_system, is_required, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($defaultSettings as $setting) {
            $stmt->bind_param('sssssiii', $setting[0], $setting[1], $setting[2], $setting[3], $setting[4], $setting[5], $setting[6], $setting[7]);
            $stmt->execute();
        }
        $stmt->close();
    } else {
        // Check if new columns exist and add them if missing
        $columnsCheck = $conn->query("SHOW COLUMNS FROM accounting_settings LIKE 'category'");
        if ($columnsCheck->num_rows === 0) {
            // Add new columns
            $conn->query("ALTER TABLE accounting_settings
                ADD COLUMN category VARCHAR(50) DEFAULT 'general' AFTER description,
                ADD COLUMN is_system BOOLEAN DEFAULT FALSE AFTER category,
                ADD COLUMN is_required BOOLEAN DEFAULT FALSE AFTER is_system,
                ADD COLUMN display_order INT DEFAULT 0 AFTER is_required,
                ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER display_order,
                ADD INDEX idx_category (category),
                ADD INDEX idx_setting_key (setting_key)
            ");
        }
    }

    if ($method === 'GET') {
        $settingKey = isset($_GET['key']) ? $_GET['key'] : null;

        if ($settingKey) {
            // Get single setting
            $stmt = $conn->prepare("SELECT * FROM accounting_settings WHERE setting_key = ?");
            $stmt->bind_param('s', $settingKey);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Parse value based on type
                $value = $row['setting_value'];
                if ($row['setting_type'] === 'json' && $value) {
                    $value = json_decode($value, true);
                } elseif ($row['setting_type'] === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($row['setting_type'] === 'number') {
                    $value = is_numeric($value) ? floatval($value) : 0;
                }

                echo json_encode([
                    'success' => true,
                    'setting' => [
                        'key' => $row['setting_key'],
                        'value' => $value,
                        'type' => $row['setting_type'],
                        'description' => $row['description']
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Setting not found'
                ]);
            }
        } else {
            // Get all settings
            $category = isset($_GET['category']) ? $_GET['category'] : null;
            $orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'display_order, setting_key';

            if ($category) {
                $stmt = $conn->prepare("SELECT * FROM accounting_settings WHERE category = ? ORDER BY " . $orderBy);
                $stmt->bind_param('s', $category);
            } else {
                $stmt = $conn->prepare("SELECT * FROM accounting_settings ORDER BY " . $orderBy);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $settings = [];
            while ($row = $result->fetch_assoc()) {
                $value = $row['setting_value'];
                if ($row['setting_type'] === 'json' && $value) {
                    $value = json_decode($value, true);
                } elseif ($row['setting_type'] === 'boolean') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($row['setting_type'] === 'number') {
                    $value = is_numeric($value) ? floatval($value) : 0;
                }

                $settings[] = [
                    'key' => $row['setting_key'],
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'description' => $row['description'],
                    'category' => $row['category'] ?? 'general',
                    'is_system' => isset($row['is_system']) ? (bool)$row['is_system'] : false,
                    'is_required' => isset($row['is_required']) ? (bool)$row['is_required'] : false,
                    'display_order' => isset($row['display_order']) ? (int)$row['display_order'] : 0,
                    'updated_at' => $row['updated_at'] ?? null,
                    'created_at' => $row['created_at'] ?? null
                ];
            }

            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
        }
    } elseif ($method === 'POST' || $method === 'PUT') {
        // Create or update setting
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['key']) || !isset($data['value'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Setting key and value are required'
            ]);
            exit;
        }

        $settingKey = $data['key'];
        $settingValue = $data['value'];
        $settingType = $data['type'] ?? 'text';
        $description = $data['description'] ?? null;
        $category = $data['category'] ?? 'general';
        $displayOrder = isset($data['display_order']) ? (int)$data['display_order'] : 0;
        $userId = $_SESSION['user_id'];

        // Prevent updating system settings unless user has admin permissions
        $checkStmt = $conn->prepare("SELECT is_system FROM accounting_settings WHERE setting_key = ?");
        $checkStmt->bind_param('s', $settingKey);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        if ($existing && isset($existing['is_system']) && $existing['is_system']) {
            // Require edit accounting settings to modify system settings
            if (!hasPermission('edit_accounting_settings')) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'System settings cannot be modified'
                ]);
                exit;
            }
        }
        $checkStmt->close();

        // Convert value to string based on type
        if ($settingType === 'json' && is_array($settingValue)) {
            $settingValue = json_encode($settingValue);
        } elseif ($settingType === 'boolean') {
            $settingValue = $settingValue ? '1' : '0';
        } else {
            $settingValue = (string)$settingValue;
        }

        // Check if setting exists
        $stmt = $conn->prepare("SELECT id FROM accounting_settings WHERE setting_key = ?");
        $stmt->bind_param('s', $settingKey);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();

        // Check if category column exists
        $categoryColumnExists = false;
        $columnsCheck = $conn->query("SHOW COLUMNS FROM accounting_settings LIKE 'category'");
        if ($columnsCheck && $columnsCheck->num_rows > 0) {
            $categoryColumnExists = true;
        }

        if ($exists) {
            // Update existing setting
            if ($categoryColumnExists) {
                $stmt = $conn->prepare("
                    UPDATE accounting_settings
                    SET setting_value = ?, setting_type = ?, description = ?, category = ?, display_order = ?, updated_by = ?, updated_at = NOW()
                    WHERE setting_key = ?
                ");
                $stmt->bind_param('ssssiis', $settingValue, $settingType, $description, $category, $displayOrder, $userId, $settingKey);
            } else {
                $stmt = $conn->prepare("
                    UPDATE accounting_settings
                    SET setting_value = ?, setting_type = ?, description = ?, updated_by = ?, updated_at = NOW()
                    WHERE setting_key = ?
                ");
                $stmt->bind_param('sssis', $settingValue, $settingType, $description, $userId, $settingKey);
            }
        } else {
            // Insert new setting
            if ($categoryColumnExists) {
                $stmt = $conn->prepare("
                    INSERT INTO accounting_settings (setting_key, setting_value, setting_type, description, category, display_order, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('sssssii', $settingKey, $settingValue, $settingType, $description, $category, $displayOrder, $userId);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO accounting_settings (setting_key, setting_value, setting_type, description, updated_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('ssssi', $settingKey, $settingValue, $settingType, $description, $userId);
            }
        }

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => $exists ? 'Setting updated successfully' : 'Setting created successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error saving setting: ' . $stmt->error
            ]);
        }
    } elseif ($method === 'DELETE') {
        $settingKey = isset($_GET['key']) ? $_GET['key'] : null;

        if (!$settingKey) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Setting key is required'
            ]);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM accounting_settings WHERE setting_key = ?");
        $stmt->bind_param('s', $settingKey);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Setting deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting setting: ' . $stmt->error
            ]);
        }
    }

} catch (Exception $e) {
    error_log('Accounting settings error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

