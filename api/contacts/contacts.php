<?php
/**
 * EN: Handles API endpoint/business logic in `api/contacts/contacts.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/contacts/contacts.php`.
 */
/**
 * Contacts API Endpoint
 * Handles all contact-related CRUD operations
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once(__DIR__ . '/../core/Database.php');
require_once(__DIR__ . '/../core/ApiResponse.php');

class ContactsAPI {
    private $db;
    private $response;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->response = new ApiResponse();
        } catch (Exception $e) {
            error_log('ContactsAPI constructor error: ' . $e->getMessage());
            $this->db = null;
            $this->response = new ApiResponse();
        }
    }




    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';

        try {
            switch ($action) {
                case 'get_contacts':
                    $this->getContacts();
                    break;
                case 'get_contact':
                    $this->getContact();
                    break;
                case 'create_contact':
                    $this->createContact();
                    break;
                case 'update_contact':
                    $this->updateContact();
                    break;
                case 'delete_contact':
                    $this->deleteContact();
                    break;
                case 'bulk_delete_contacts':
                    $this->bulkDeleteContacts();
                    break;
                case 'get_communications':
                    $this->getCommunications();
                    break;
                case 'add_communication':
                    $this->addCommunication();
                    break;
                case 'search_contacts':
                    $this->searchContacts();
                    break;
                case 'get_companies':
                    $this->getCompanies();
                    break;
                case 'search_companies':
                    $this->searchCompanies();
                    break;
                case 'export_contacts':
                    $this->exportContacts();
                    break;
                case 'import_contacts':
                    $this->importContacts();
                    break;
                case 'get_recent_communications':
                    $this->getRecentCommunications();
                    break;
                case 'get_contacts_for_communication':
                    $this->getContactsForCommunication();
                    break;
                case 'get_communication':
                    $this->getCommunication();
                    break;
                case 'test_connection':
                    $this->testConnection();
                    break;
                default:
                    ob_clean();
                    echo ApiResponse::error('Invalid action', 400);
                    exit();
            }
        } catch (Exception $e) {
            error_log('Contacts API error: ' . $e->getMessage());
            ob_clean();
            echo ApiResponse::error('Server error: ' . $e->getMessage(), 500);
            exit();
        } catch (Throwable $e) {
            error_log('Contacts API fatal error: ' . $e->getMessage());
            ob_clean();
            echo ApiResponse::error('Server error: ' . $e->getMessage(), 500);
            exit();
        }
    }

    private function getContacts() {
        if (!$this->db) {
            ob_clean();
            echo ApiResponse::error('Database not initialized', 500);
            exit();
        }
        
        try {
            $conn = $this->db->getConnection();
        } catch (Exception $e) {
            error_log('Contacts API getContacts database error: ' . $e->getMessage());
            ob_clean();
            echo ApiResponse::error('Database connection failed: ' . $e->getMessage(), 500);
            exit();
        }
        
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 10);
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';
        $status = $_GET['status'] ?? '';

        $whereConditions = [];
        $params = [];

        // Always exclude soft-deleted contacts
        $whereConditions[] = "(contacts.is_deleted = 0 OR contacts.is_deleted IS NULL)";

        if (!empty($search)) {
            $whereConditions[] = "(contacts.name LIKE ? OR contacts.email LIKE ? OR contacts.phone LIKE ? OR contacts.company LIKE ?)";
            $searchTerm = "%{$search}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (!empty($type)) {
            $whereConditions[] = "contacts.contact_type = ?";
            $params[] = $type;
        }

        if (!empty($status)) {
            $whereConditions[] = "contacts.status = ?";
            $params[] = $status;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM contacts {$whereClause}";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get contacts - replace contacts. with c. for the JOIN query
        $joinWhereClause = str_replace('contacts.', 'c.', $whereClause);
        $query = "SELECT c.*, u.username as created_by_name,
                         (SELECT MAX(communication_date) FROM contact_communications cc WHERE cc.contact_id = c.id) as last_contact_date
                  FROM contacts c 
                  LEFT JOIN users u ON c.created_by = u.user_id 
                  {$joinWhereClause} 
                  ORDER BY c.created_at DESC, c.id DESC 
                  LIMIT {$limit} OFFSET {$offset}";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = [
            'contacts' => $contacts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $limit)
            ]
        ];

        ob_clean();
        echo ApiResponse::success($response);
        exit();
    }

    private function getContact() {
        $contactId = $_GET['id'] ?? '';
        if (empty($contactId)) {
            ob_clean();
            echo ApiResponse::error('Contact ID is required', 400);
            exit();
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $query = "SELECT c.*, u.username as created_by_name 
                  FROM contacts c 
                  LEFT JOIN users u ON c.created_by = u.user_id 
                  WHERE c.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$contactId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contact) {
            ob_clean();
            echo ApiResponse::error('Contact not found', 404);
            exit();
        }

        ob_clean();
        echo ApiResponse::success($contact);
        exit();
    }

    private function createContact() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_clean();
            echo ApiResponse::error('Method not allowed', 405);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            ob_clean();
            echo ApiResponse::error('Invalid JSON input', 400);
            exit();
        }

        $requiredFields = ['name'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                ob_clean();
                echo ApiResponse::error("Field '{$field}' is required", 400);
                exit();
            }
        }

        try {
            $conn = $this->db->getConnection();
            if (!$conn) {
                ob_clean();
                echo ApiResponse::error('Database connection failed', 500);
                exit();
            }
            
            // Check if contacts table exists
            $checkTable = $conn->query("SHOW TABLES LIKE 'contacts'");
            if ($checkTable->rowCount() == 0) {
                ob_clean();
                echo ApiResponse::error('Contacts table does not exist', 500);
                exit();
            }

            // Generate unique contact_id
            $contactId = 'C' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Ensure contact_id is unique
            $checkQuery = "SELECT id FROM contacts WHERE contact_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->execute([$contactId]);
            
            while ($checkStmt->fetch()) {
                $contactId = 'C' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $checkStmt->execute([$contactId]);
            }

            // Simplified query with only essential fields
            $query = "INSERT INTO contacts (contact_id, name, email, phone, city, country, contact_type, status, notes, created_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            $result = $stmt->execute([
                $contactId,
                $input['name'],
                $input['email'] ?? null,
                $input['phone'] ?? null,
                $input['city'] ?? null,
                $input['country'] ?? null,
                $input['contact_type'] ?? 'customer',
                $input['status'] ?? 'active',
                $input['notes'] ?? null,
                $_SESSION['user_id'] ?? 1
            ]);

            if ($result) {
                $newId = $conn->lastInsertId();
                
                // Get created contact for history
                $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
                $stmt->execute([$newId]);
                $newContact = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log history
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        logGlobalHistory('contacts', $newId, 'create', 'contacts', null, $newContact);
                    }
                }
                
                ob_clean();
                echo ApiResponse::success(['id' => $newId, 'contact_id' => $contactId, 'message' => 'Contact created successfully']);
                exit();
            } else {
                ob_clean();
                echo ApiResponse::error('Failed to create contact', 500);
                exit();
            }
        } catch (Exception $e) {
            error_log('Contact creation error: ' . $e->getMessage());
            ob_clean();
            echo ApiResponse::error('Failed to create contact: ' . $e->getMessage(), 500);
            exit();
        }
    }

    private function updateContact() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_clean();
            echo ApiResponse::error('Method not allowed', 405);
            exit();
        }

        $contactId = $_GET['id'] ?? '';
        if (empty($contactId)) {
            ob_clean();
            echo ApiResponse::error('Contact ID is required', 400);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            ob_clean();
            echo ApiResponse::error('Invalid JSON input', 400);
            exit();
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $updateFields = [];
        $params = [];

        $allowedFields = ['name', 'email', 'secondary_email', 'website', 'phone', 'secondary_phone', 'company', 'industry', 'position', 'department', 'company_size', 'address', 'city', 'country', 'postal_code', 'timezone', 'contact_type', 'lead_source', 'priority', 'birth_date', 'status', 'notes'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $input[$field];
            }
        }

        if (empty($updateFields)) {
            ob_clean();
            echo ApiResponse::error('No valid fields to update', 400);
            exit();
        }

        $params[] = $contactId;
        $query = "UPDATE contacts SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        try {
            // Get old data for history
            $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
            $stmt->execute([$contactId]);
            $oldContact = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldContact) {
                ob_clean();
                echo ApiResponse::error('Contact not found', 404);
                exit();
            }
            
            $stmt = $conn->prepare($query);
            $result = $stmt->execute($params);

            if ($result) {
                // Get updated contact for history
                $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
                $stmt->execute([$contactId]);
                $updatedContact = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log history
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        logGlobalHistory('contacts', $contactId, 'update', 'contacts', $oldContact, $updatedContact);
                    }
                }
                
                ob_clean();
                echo ApiResponse::success(['message' => 'Contact updated successfully']);
                exit();
            } else {
                ob_clean();
                echo ApiResponse::error('Failed to update contact', 500);
                exit();
            }
        } catch (Exception $e) {
            error_log('Contact update error: ' . $e->getMessage());
            ob_clean();
            echo ApiResponse::error('Failed to update contact: ' . $e->getMessage(), 500);
            exit();
        }
    }

    private function deleteContact() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_clean();
            echo ApiResponse::error('Method not allowed', 405);
            exit();
        }

        $contactId = $_GET['id'] ?? '';
        if (empty($contactId)) {
            ob_clean();
            echo ApiResponse::error('Contact ID is required', 400);
            exit();
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        // Get deleted data for history (before deletion)
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->execute([$contactId]);
        $deletedContact = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deletedContact) {
            ob_clean();
            echo ApiResponse::error('Contact not found', 404);
            exit();
        }
        
        // Soft delete: mark as deleted instead of actually deleting
        $query = "UPDATE contacts SET is_deleted = 1, deleted_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $result = $stmt->execute([$contactId]);

        if ($result) {
            // Log history
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    logGlobalHistory('contacts', $contactId, 'delete', 'contacts', $deletedContact, null);
                }
            }
            
            ob_clean();
            echo ApiResponse::success(['message' => 'Contact deleted successfully']);
            exit();
        } else {
            ob_clean();
            echo ApiResponse::error('Failed to delete contact', 500);
            exit();
        }
    }

    private function bulkDeleteContacts() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_clean();
            echo ApiResponse::error('Method not allowed', 405);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['contact_ids']) || !is_array($input['contact_ids'])) {
            ob_clean();
            echo ApiResponse::error('Contact IDs array is required', 400);
            exit();
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $contactIds = array_filter(array_map('intval', $input['contact_ids']));
        if (empty($contactIds)) {
            ob_clean();
            echo ApiResponse::error('No valid contact IDs provided', 400);
            exit();
        }

        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        
        // Get deleted data for history (before deletion)
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE id IN ($placeholders)");
        $stmt->execute($contactIds);
        $deletedContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Soft delete: mark as deleted instead of actually deleting
        $query = "UPDATE contacts SET is_deleted = 1, deleted_at = NOW() WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($query);
        $result = $stmt->execute($contactIds);

        if ($result) {
            // Log history for each deleted contact
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    foreach ($deletedContacts as $contact) {
                        logGlobalHistory('contacts', $contact['id'], 'delete', 'contacts', $contact, null);
                    }
                }
            }
            
            $deletedCount = $stmt->rowCount();
            ob_clean();
            echo ApiResponse::success(['message' => "{$deletedCount} contact(s) deleted successfully", 'deleted_count' => $deletedCount]);
            exit();
        } else {
            ob_clean();
            echo ApiResponse::error('Failed to delete contacts', 500);
            exit();
        }
    }

    private function getCommunications() {
        $contactId = $_GET['contact_id'] ?? '';
        if (empty($contactId)) {
            ob_clean();
            echo ApiResponse::error('Contact ID is required', 400);
            exit();
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $query = "SELECT cc.*, u.username as created_by_name 
                  FROM contact_communications cc 
                  LEFT JOIN users u ON cc.created_by = u.user_id 
                  WHERE cc.contact_id = ? 
                  ORDER BY cc.communication_date DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$contactId]);
        $communications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        echo ApiResponse::success($communications);
        exit();
    }

    private function addCommunication() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_clean();
            echo ApiResponse::error('Method not allowed', 405);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            ob_clean();
            echo ApiResponse::error('Invalid JSON input', 400);
            exit();
        }

        $requiredFields = ['contact_id', 'communication_type', 'subject', 'message'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                ob_clean();
                echo ApiResponse::error("Field '{$field}' is required", 400);
                exit();
            }
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        try {
            // Check which columns exist in the table
            $columnsQuery = $conn->query("SHOW COLUMNS FROM contact_communications");
            $existingColumns = [];
            while ($row = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
                $existingColumns[] = $row['Field'];
            }
            
            $hasChannel = in_array('channel', $existingColumns);
            $hasDuration = in_array('duration', $existingColumns);
            $hasCommunicationDate = in_array('communication_date', $existingColumns);
            
            // Build the query dynamically based on available columns
            $columns = ['contact_id', 'communication_type', 'direction', 'priority'];
            $values = ['?', '?', '?', '?'];
            $params = [
                $input['contact_id'],
                $input['communication_type'],
                $input['direction'] ?? 'outbound',
                $input['priority'] ?? 'medium'
            ];
            
            if ($hasCommunicationDate) {
                $columns[] = 'communication_date';
                $values[] = '?';
                $params[] = !empty($input['communication_date']) ? $input['communication_date'] : date('Y-m-d H:i:s');
            }
            
            if ($hasChannel) {
                $columns[] = 'channel';
                $values[] = '?';
                $params[] = $input['channel'] ?? 'direct';
            }
            
            if ($hasDuration) {
                $columns[] = 'duration';
                $values[] = '?';
                $params[] = $input['duration'] ?? null;
            }
            
            // Add required columns
            $columns[] = 'subject';
            $columns[] = 'message';
            $values[] = '?';
            $values[] = '?';
            $params[] = $input['subject'];
            $params[] = $input['message'];
            
            // Add optional columns
            $optionalColumns = ['outcome', 'next_action', 'follow_up_date'];
            foreach ($optionalColumns as $col) {
                $columns[] = $col;
                $values[] = '?';
                $params[] = $input[$col] ?? null;
            }
            
            // Add created_by and status
            $columns[] = 'created_by';
            $columns[] = 'status';
            $values[] = '?';
            $values[] = '?';
            $params[] = $_SESSION['user_id'] ?? 1;
            $params[] = $input['status'] ?? 'sent';
            
            $query = "INSERT INTO contact_communications (" . implode(', ', $columns) . ") 
                      VALUES (" . implode(', ', $values) . ")";
            
            $stmt = $conn->prepare($query);
            $result = $stmt->execute($params);

            if ($result) {
                $communicationId = $conn->lastInsertId();
                ob_clean();
                echo ApiResponse::success(['id' => $communicationId, 'message' => 'Communication added successfully']);
                exit();
            } else {
                ob_clean();
                echo ApiResponse::error('Failed to add communication', 500);
                exit();
            }
        } catch (Exception $e) {
            error_log('Communication save error: ' . $e->getMessage());
            ob_clean();
            echo ApiResponse::error('Failed to add communication: ' . $e->getMessage(), 500);
            exit();
        }
    }

    private function searchContacts() {
        $search = $_GET['q'] ?? '';
        if (empty($search)) {
            ob_clean();
            echo ApiResponse::error('Search query is required', 400);
            exit();
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $searchTerm = "%{$search}%";
        $query = "SELECT id, contact_id, name, email, phone, company, contact_type, status 
                  FROM contacts 
                  WHERE (name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?) 
                  AND status = 'active' 
                  AND (is_deleted = 0 OR is_deleted IS NULL)
                  ORDER BY name ASC 
                  LIMIT 20";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        echo ApiResponse::success($contacts);
        exit();
    }

    private function getCompanies() {
        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $query = "SELECT DISTINCT company FROM contacts WHERE company IS NOT NULL AND company != '' AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY company ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $companies = $stmt->fetchAll(PDO::FETCH_COLUMN);

        ob_clean();
        echo ApiResponse::success($companies);
        exit();
    }

    private function searchCompanies() {
        $search = $_GET['q'] ?? '';
        if (empty($search)) {
            ob_clean();
            echo ApiResponse::error('Search query is required', 400);
            exit();
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $searchTerm = "%{$search}%";
        $query = "SELECT DISTINCT company FROM contacts WHERE company LIKE ? AND company IS NOT NULL AND company != '' AND (is_deleted = 0 OR is_deleted IS NULL) ORDER BY company ASC LIMIT 10";
        $stmt = $conn->prepare($query);
        $stmt->execute([$searchTerm]);
        $companies = $stmt->fetchAll(PDO::FETCH_COLUMN);

        ob_clean();
        echo ApiResponse::success($companies);
        exit();
    }

    private function exportContacts() {
        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $filters = $this->getFilters();
        $whereConditions = [];
        $params = [];

        // Always exclude soft-deleted contacts
        $whereConditions[] = "(is_deleted = 0 OR is_deleted IS NULL)";

        if (!empty($filters['search'])) {
            $whereConditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (!empty($filters['type'])) {
            $whereConditions[] = "contact_type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT contact_id, name, email, phone, company, position, address, city, country, contact_type, status, notes, created_at 
                  FROM contacts {$whereClause} 
                  ORDER BY created_at DESC, id DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Clean output buffer before sending CSV headers
        ob_clean();
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="contacts_export_' . date('Y-m-d_H-i-s') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Contact ID', 'Name', 'Email', 'Phone', 'Company', 'Position', 
            'Address', 'City', 'Country', 'Type', 'Status', 'Notes', 'Created At'
        ]);

        // CSV data
        foreach ($contacts as $contact) {
            fputcsv($output, [
                $contact['contact_id'],
                $contact['name'],
                $contact['email'],
                $contact['phone'],
                $contact['company'],
                $contact['position'],
                $contact['address'],
                $contact['city'],
                $contact['country'],
                $contact['contact_type'],
                $contact['status'],
                $contact['notes'],
                $contact['created_at']
            ]);
        }

        fclose($output);
        exit();
    }

    private function importContacts() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ob_clean();
            echo ApiResponse::error('Method not allowed', 405);
            exit();
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo ApiResponse::error('No file uploaded or upload error', 400);
            exit();
        }

        $file = $_FILES['file'];
        $csvData = array_map('str_getcsv', file($file['tmp_name']));
        
        if (empty($csvData) || count($csvData) < 2) {
            ob_clean();
            echo ApiResponse::error('Invalid CSV file', 400);
            exit();
        }

        $headers = array_shift($csvData); // Remove header row
        $imported = 0;
        $errors = [];

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        foreach ($csvData as $row) {
            if (count($row) < 2) continue; // Skip invalid rows

            $data = array_combine($headers, $row);
            
            // Validate required fields
            if (empty($data['Name'])) {
                $errors[] = "Row " . ($imported + 1) . ": Name is required";
                continue;
            }

            try {
                $query = "INSERT INTO contacts (name, email, phone, company, position, address, city, country, contact_type, status, notes, created_by) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($query);
                $result = $stmt->execute([
                    $data['Name'] ?? '',
                    $data['Email'] ?? null,
                    $data['Phone'] ?? null,
                    $data['Company'] ?? null,
                    $data['Position'] ?? null,
                    $data['Address'] ?? null,
                    $data['City'] ?? null,
                    $data['Country'] ?? null,
                    $data['Type'] ?? 'other',
                    $data['Status'] ?? 'active',
                    $data['Notes'] ?? null,
                    $_SESSION['user_id'] ?? 1
                ]);

                if ($result) {
                    $imported++;
                }
            } catch (Exception $e) {
                $errors[] = "Row " . ($imported + 1) . ": " . $e->getMessage();
            }
        }

        $response = [
            'imported' => $imported,
            'errors' => $errors
        ];

        ob_clean();
        echo ApiResponse::success($response);
        exit();
    }

    private function getRecentCommunications() {
        if (!$this->db) {
            ob_clean();
            echo ApiResponse::error('Database not initialized', 500);
            exit();
        }
        
        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $limit = (int)($_GET['limit'] ?? 10);
        $query = "SELECT cc.*, c.name as contact_name, c.company as contact_company, c.contact_type, u.username as created_by_name
                  FROM contact_communications cc 
                  LEFT JOIN contacts c ON cc.contact_id = c.id 
                  LEFT JOIN users u ON cc.created_by = u.user_id 
                  WHERE (c.is_deleted = 0 OR c.is_deleted IS NULL)
                  ORDER BY cc.communication_date DESC, cc.id DESC 
                  LIMIT {$limit}";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $communications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        echo ApiResponse::success($communications);
        exit();
    }

    private function getContactsForCommunication() {
        if (!$this->db) {
            ob_clean();
            echo ApiResponse::error('Database not initialized', 500);
            exit();
        }
        
        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $query = "SELECT id, name, company, contact_type, email, phone 
                  FROM contacts 
                  WHERE status = 'active' 
                  AND (is_deleted = 0 OR is_deleted IS NULL)
                  ORDER BY name ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_clean();
        echo ApiResponse::success($contacts);
        exit();
    }

    private function getCommunication() {
        $communicationId = $_GET['id'] ?? '';
        if (empty($communicationId)) {
            ob_clean();
            echo ApiResponse::error('Communication ID is required', 400);
            exit();
        }

        $conn = $this->db->getConnection();
        if (!$conn) {
            ob_clean();
            echo ApiResponse::error('Database connection failed', 500);
            exit();
        }

        $query = "SELECT cc.*, c.name as contact_name, c.company as contact_company, c.contact_type, u.username as created_by_name
                  FROM contact_communications cc 
                  LEFT JOIN contacts c ON cc.contact_id = c.id 
                  LEFT JOIN users u ON cc.created_by = u.user_id 
                  WHERE cc.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$communicationId]);
        $communication = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$communication) {
            ob_clean();
            echo ApiResponse::error('Communication not found', 404);
            exit();
        }

        ob_clean();
        echo ApiResponse::success($communication);
        exit();
    }

    private function getFilters() {
        return [
            'search' => $_GET['search'] ?? '',
            'type' => $_GET['type'] ?? '',
            'status' => $_GET['status'] ?? ''
        ];
    }

    private function testConnection() {
        try {
            $conn = $this->db->getConnection();
            if (!$conn) {
                ob_clean();
                echo ApiResponse::error('Database connection failed', 500);
                exit();
            }
            
            // Check if contacts table exists
            $checkTable = $conn->query("SHOW TABLES LIKE 'contacts'");
            $tableExists = $checkTable->rowCount() > 0;
            
            // Check if contact_communications table exists
            $checkCommTable = $conn->query("SHOW TABLES LIKE 'contact_communications'");
            $commTableExists = $checkCommTable->rowCount() > 0;
            
            $response = [
                'database_connected' => true,
                'contacts_table_exists' => $tableExists,
                'communications_table_exists' => $commTableExists,
                'session_user_id' => $_SESSION['user_id'] ?? 'not_set',
                'php_version' => PHP_VERSION,
                'pdo_available' => extension_loaded('pdo'),
                'pdo_mysql_available' => extension_loaded('pdo_mysql')
            ];
            
            ob_clean();
            echo ApiResponse::success($response);
            exit();
        } catch (Exception $e) {
            ob_clean();
            echo ApiResponse::error('Test failed: ' . $e->getMessage(), 500);
            exit();
        }
    }

}

// Initialize and handle request
$api = new ContactsAPI();
$api->handleRequest();
exit();
