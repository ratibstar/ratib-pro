<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/documents.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/documents.php`.
 */
if (isset($_GET['control']) && (string)$_GET['control'] === '1') {
    session_name('ratib_control');
}
require_once __DIR__ . '/hr-api-bootstrap.inc.php';
// Disable error display to prevent HTML output in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load response.php - try root Utils first, then api/utils
$responsePath = __DIR__ . '/../../Utils/response.php';
if (!file_exists($responsePath)) {
    $responsePath = __DIR__ . '/../../api/utils/response.php';
}
if (!file_exists($responsePath)) {
    error_log('ERROR: response.php not found. Tried: ' . __DIR__ . '/../../Utils/response.php and ' . __DIR__ . '/../../api/utils/response.php');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server configuration error: response.php not found']);
    exit;
}
require_once $responsePath;

require_once __DIR__ . '/hr-connection.php';

// Set headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Resolve stored file_path (may be full URL or path) to filesystem path
function resolveHrDocumentFilePath($storedPath, $fileName = '') {
    $basename = $fileName ?: basename($storedPath ?: '');
    if (empty($basename)) return '';

    // 1. Project root from script location - most reliable across hosts
    $projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    if (!$projectRoot) {
        $projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, __DIR__ . '/../../'), DIRECTORY_SEPARATOR);
    }
    $uploadsBase = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents';

    // 2. Try: project_root/uploads/documents/filename (primary)
    $primary = $uploadsBase . DIRECTORY_SEPARATOR . $basename;
    if (file_exists($primary)) return $primary;

    // 3. Try: DOCUMENT_ROOT + path from DB
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $pathPart = $storedPath;
    if (preg_match('#^https?://[^/]+(/.+)$#', $storedPath ?? '', $m)) $pathPart = $m[1];
    elseif (empty($pathPart) || $pathPart[0] !== '/') $pathPart = '/uploads/documents/' . $basename;
    $byDocRoot = $docRoot . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pathPart);
    if (file_exists($byDocRoot)) return $byDocRoot;

    // 4. Scan uploads/documents for file (handles encoding / subdirs)
    if (is_dir($uploadsBase)) {
        $found = findFileInDir($uploadsBase, $basename);
        if ($found) return $found;
    }

    return $primary; // Return best guess for error msg
}

// Recursively find file by name (handles subdirs, encoding differences)
function findFileInDir($dir, $targetName) {
    if (!is_dir($dir)) return null;
    $items = @scandir($dir);
    if (!$items) return null;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) {
            $found = findFileInDir($full, $targetName);
            if ($found) return $found;
        } elseif ($item === $targetName) {
            return $full;
        }
    }
    return null;
}

try {
    $conn = hr_api_get_connection();

    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    switch ($action) {
        case 'list':
            // Check if table exists (PDO MySQL: do not rely on rowCount() for SHOW TABLES)
            $tableCheck = $conn->query("SHOW TABLES LIKE 'hr_documents'");
            $hrDocsTableExists = ($tableCheck !== false && $tableCheck->fetch(PDO::FETCH_NUM) !== false);
            if (!$hrDocsTableExists) {
                sendResponse([
                    'success' => true,
                    'data' => [],
                    'pagination' => [
                        'total' => 0,
                        'page' => 1,
                        'limit' => 5,
                        'pages' => 0
                    ]
                ]);
                break;
            }
            
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 5; // Max 100 per page
            $type = $_GET['type'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($type)) {
                $whereConditions[] = "document_type = :type";
                $params[':type'] = $type;
            }
            
            if (!empty($status)) {
                $whereConditions[] = "status = :status";
                $params[':status'] = $status;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM hr_documents $whereClause";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Check if employee columns exist in the table
            $checkColumns = "SHOW COLUMNS FROM hr_documents LIKE 'employee_id'";
            $checkStmt = $conn->prepare($checkColumns);
            $checkStmt->execute();
            $hasEmployeeColumns = ($checkStmt->fetch(PDO::FETCH_NUM) !== false);
            
            $lim = (int) $limit;
            $off = (int) $offset;
            if ($hasEmployeeColumns) {
                // Get documents with employee information
                $query = "SELECT d.*, COALESCE(e.name, d.employee_name, 'N/A') as employee_name 
                          FROM hr_documents d 
                          LEFT JOIN employees e ON d.employee_id = e.id 
                          $whereClause 
                          ORDER BY d.id DESC LIMIT {$lim} OFFSET {$off}";
            } else {
                // Show employee name from uploaded_by field
                $query = "SELECT d.*, 
                          CASE 
                            WHEN d.uploaded_by IS NOT NULL AND d.uploaded_by != 'System' AND d.uploaded_by != 'Unknown Employee' THEN d.uploaded_by
                            ELSE 'N/A'
                          END as employee_name 
                          FROM hr_documents d 
                          $whereClause 
                          ORDER BY d.id DESC LIMIT {$lim} OFFSET {$off}";
            }
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'data' => $documents,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'add':
            // checkApiPermission('hr_add');
            
            // Handle file upload
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_upload'])) {
                $input = $_POST;
                
                
                // Check if employee columns exist first
                $checkColumns = "SHOW COLUMNS FROM hr_documents LIKE 'employee_id'";
                $checkStmt = $conn->prepare($checkColumns);
                $checkStmt->execute();
                $hasEmployeeColumns = ($checkStmt->fetch(PDO::FETCH_NUM) !== false);
                
                // Always try to get employee information if employee_id is provided
                $employee = ['name' => null];
                if (!empty($input['employee_id'])) {
                    $empQuery = "SELECT name FROM employees WHERE id = :id";
                    $empStmt = $conn->prepare($empQuery);
                    $empStmt->bindParam(':id', $input['employee_id'], PDO::PARAM_INT);
                    $empStmt->execute();
                    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
                    
                    
                    if (!$employee) {
                        sendResponse(['success' => false, 'message' => 'Employee not found'], 404);
                    }
                }
                
                // Validate required fields based on table structure
                if ($hasEmployeeColumns) {
                    $requiredFields = ['employee_id', 'title', 'document_type', 'department', 'issue_date', 'document_number'];
                } else {
                    $requiredFields = ['title', 'document_type', 'department', 'issue_date', 'document_number'];
                }
                
                foreach ($requiredFields as $field) {
                    if (empty($input[$field])) {
                        sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
                    }
                }
                
                // Handle file upload - use project root (same as resolveHrDocumentFilePath)
                require_once __DIR__ . '/../../includes/config.php';
                $baseUrl = defined('BASE_URL') ? BASE_URL : '';
                $projectRoot = realpath(__DIR__ . '/../../') ?: rtrim(str_replace('\\', '/', __DIR__ . '/../../'), '/');
                $uploadPath = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR;
                $uploadDir = $baseUrl . '/uploads/documents/';
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                $file = $_FILES['file_upload'];
                
                // Validate file size (10MB limit)
                $maxSize = 10 * 1024 * 1024; // 10MB
                if ($file['size'] > $maxSize) {
                    sendResponse(['success' => false, 'message' => 'File size exceeds 10MB limit'], 400);
                }
                
                // Validate file type
                $allowedMimeTypes = [
                    'application/pdf',
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/plain', 'text/csv'
                ];
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    sendResponse(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, Images, Word, Excel, Text'], 400);
                }
                
                // Sanitize file name
                $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
                $originalName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
                $originalName = substr($originalName, 0, 100); // Limit length
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'bin';
                
                // Validate extension matches MIME type
                $extMimeMap = [
                    'pdf' => 'application/pdf',
                    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                    'gif' => 'image/gif', 'webp' => 'image/webp',
                    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'txt' => 'text/plain', 'csv' => 'text/csv'
                ];
                
                if (isset($extMimeMap[$ext]) && $extMimeMap[$ext] !== $mimeType) {
                    sendResponse(['success' => false, 'message' => 'File extension does not match file type'], 400);
                }
                
                $safeFileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $filePath = $uploadPath . $safeFileName;
                $fileUrl = $uploadDir . $safeFileName;
                $fileName = $safeFileName;
                
                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    sendResponse(['success' => false, 'message' => 'File upload failed'], 500);
                }
            } else {
                sendResponse(['success' => false, 'message' => 'No file uploaded'], 400);
            }
            
            // Check if employee columns exist
            $checkColumns = "SHOW COLUMNS FROM hr_documents LIKE 'employee_id'";
            $checkStmt = $conn->prepare($checkColumns);
            $checkStmt->execute();
            $hasEmployeeColumns = ($checkStmt->fetch(PDO::FETCH_NUM) !== false);
            
            // Start transaction for atomic operation
            $conn->beginTransaction();
            
            try {
                // Generate record ID with DO0001 format
                require_once __DIR__ . '/../core/formatted-id-helper.php';
                try {
                    $recordId = generateHRDocumentId($conn);
                } catch (Exception $e) {
                    error_log("Error generating HR document ID: " . $e->getMessage());
                    // Fallback to simple ID generation (DO prefix for Documents)
                    $recordId = 'DO' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
                }
                
                if ($hasEmployeeColumns) {
                // Insert with employee information
                $query = "INSERT INTO hr_documents (
                    record_id, employee_id, employee_name, title, document_type, department, issue_date, expiry_date, document_number,
                    description, file_name, file_path, file_size, mime_type, uploaded_by, status,
                    created_at, updated_at
                ) VALUES (
                    :record_id, :employee_id, :employee_name, :title, :document_type, :department, :issue_date, :expiry_date, :document_number,
                    :description, :file_name, :file_path, :file_size, :mime_type, :uploaded_by, :status,
                    NOW(), NOW()
                )";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':record_id', $recordId);
                $stmt->bindParam(':employee_id', $input['employee_id'], PDO::PARAM_INT);
                $stmt->bindParam(':employee_name', $employee['name']);
            } else {
                // Insert without employee information (backward compatibility)
                $query = "INSERT INTO hr_documents (
                    record_id, title, document_type, department, issue_date, expiry_date, document_number,
                    description, file_name, file_path, file_size, mime_type, uploaded_by, status,
                    created_at, updated_at
                ) VALUES (
                    :record_id, :title, :document_type, :department, :issue_date, :expiry_date, :document_number,
                    :description, :file_name, :file_path, :file_size, :mime_type, :uploaded_by, :status,
                    NOW(), NOW()
                )";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':record_id', $recordId);
            }
            
            $stmt->bindParam(':title', $input['title']);
            $stmt->bindParam(':document_type', $input['document_type']);
            $stmt->bindParam(':department', $input['department']);
            $stmt->bindParam(':issue_date', $input['issue_date']);
            $stmt->bindParam(':expiry_date', $input['expiry_date']);
            $stmt->bindParam(':document_number', $input['document_number']);
            $stmt->bindParam(':description', $input['description']);
            $stmt->bindParam(':file_name', $fileName);
            $stmt->bindParam(':file_path', $fileUrl);
            
            // Use actual file size from uploaded file (more reliable than $_FILES['size'])
            $fileSize = filesize($filePath);
            if ($fileSize === false) {
                $conn->rollBack();
                @unlink($filePath);
                sendResponse(['success' => false, 'message' => 'Failed to get file size'], 500);
            }
            
            // Use detected MIME type (already validated above), not client-provided type
            // $mimeType is already set from finfo_file() above
            $stmt->bindParam(':file_size', $fileSize, PDO::PARAM_INT);
            $stmt->bindParam(':mime_type', $mimeType);
            // Use employee name as uploaded_by if no employee columns exist
            $uploadedBy = $hasEmployeeColumns ? ($input['uploaded_by'] ?? 'System') : ($employee['name'] ?? 'Unknown Employee');
            $status = $input['status'] ?? 'active';
            $stmt->bindParam(':uploaded_by', $uploadedBy);
            $stmt->bindParam(':status', $status);
            
            $result = $stmt->execute();
            
            if ($result) {
                $insertId = $conn->lastInsertId();
                
                // Get created document for history
                $stmt = $conn->prepare("SELECT * FROM hr_documents WHERE id = ?");
                $stmt->execute([$insertId]);
                $newDocument = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log history
                    if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                        require_once __DIR__ . '/../core/global-history-helper.php';
                        @logGlobalHistory('hr_documents', $insertId, 'create', 'hr', null, $newDocument);
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    sendResponse([
                        'success' => true,
                        'message' => 'Document uploaded successfully',
                        'data' => ['id' => $insertId]
                    ]);
                } else {
                    $conn->rollBack();
                    // Delete uploaded file if database insert failed
                    if (isset($filePath) && file_exists($filePath)) {
                        @unlink($filePath);
                    }
                    sendResponse(['success' => false, 'message' => 'Failed to save document record'], 500);
                }
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                // Delete uploaded file if database insert failed
                if (isset($filePath) && file_exists($filePath)) {
                    @unlink($filePath);
                }
                error_log("HR Document upload transaction error: " . $e->getMessage());
                sendResponse(['success' => false, 'message' => 'Failed to upload document: ' . $e->getMessage()], 500);
            }
            break;
            
        case 'update':
            // checkApiPermission('hr_edit');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Document ID is required'], 400);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            // Get old data for history
            $stmt = $conn->prepare("SELECT * FROM hr_documents WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $oldDocument = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldDocument) {
                sendResponse(['success' => false, 'message' => 'Document not found'], 404);
            }
            
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = ['employee_id', 'title', 'document_type', 'department', 'issue_date', 'expiry_date', 'document_number', 'description', 'status'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $input[$field];
                }
            }
            if (isset($input['employee_id']) && !empty($input['employee_id'])) {
                $empStmt = $conn->prepare("SELECT name FROM employees WHERE id = :id");
                $empStmt->bindParam(':id', $input['employee_id'], PDO::PARAM_INT);
                $empStmt->execute();
                $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
                if ($emp) {
                    $updateFields[] = "employee_name = :employee_name";
                    $params[":employee_name"] = $emp['name'];
                }
            }
            
            if (empty($updateFields)) {
                sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
            }
            
            $updateFields[] = "updated_at = NOW()";
            
            $query = "UPDATE hr_documents SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            // Get updated document for history
            $stmt = $conn->prepare("SELECT * FROM hr_documents WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $updatedDocument = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('hr_documents', $id, 'update', 'hr', $oldDocument, $updatedDocument);
            }
            
            sendResponse(['success' => true, 'message' => 'Document updated successfully']);
            break;
            
        case 'get':
            // checkApiPermission('hr_view');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Document ID is required'], 400);
            }
            
            // Check if employee columns exist
            $checkColumns = "SHOW COLUMNS FROM hr_documents LIKE 'employee_id'";
            $checkStmt = $conn->prepare($checkColumns);
            $checkStmt->execute();
            $hasEmployeeColumns = ($checkStmt->fetch(PDO::FETCH_NUM) !== false);
            
            if ($hasEmployeeColumns) {
                $query = "SELECT d.*, COALESCE(e.name, d.employee_name, 'N/A') as employee_name 
                          FROM hr_documents d 
                          LEFT JOIN employees e ON d.employee_id = e.id 
                          WHERE d.id = :id";
            } else {
                $query = "SELECT d.*, 
                          CASE 
                            WHEN d.uploaded_by IS NOT NULL AND d.uploaded_by != 'System' AND d.uploaded_by != 'Unknown Employee' THEN d.uploaded_by
                            ELSE 'N/A'
                          END as employee_name 
                          FROM hr_documents d 
                          WHERE d.id = :id";
            }
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($document) {
                sendResponse([
                    'success' => true,
                    'data' => $document
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Document not found'], 404);
            }
            break;
            
        case 'view':
            // checkApiPermission('hr_view');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Document ID is required'], 400);
            }
            
            $query = "SELECT file_path, file_name, mime_type FROM hr_documents WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$document) {
                sendResponse(['success' => false, 'message' => 'Document not found'], 404);
            }
            
            $filePath = resolveHrDocumentFilePath($document['file_path'] ?? '', $document['file_name'] ?? '');
            
            // Debug: ?debug=1 returns path info (remove in production)
            if (!empty($_GET['debug'])) {
                $projRoot = realpath(__DIR__ . '/../../');
                sendResponse([
                    'file_name' => $document['file_name'],
                    'file_path_db' => $document['file_path'] ?? null,
                    'resolved_path' => $filePath,
                    'exists' => file_exists($filePath),
                    'project_root' => $projRoot,
                    'uploads_dir' => $projRoot ? $projRoot . '/uploads/documents' : null,
                    'uploads_exists' => $projRoot ? is_dir($projRoot . '/uploads/documents') : false,
                ]);
            }
            
            if (file_exists($filePath)) {
                // Check if browser supports inline display for this file type
                $mimeType = $document['mime_type'];
                $canDisplayInline = strpos($mimeType, 'image/') === 0 || 
                                    strpos($mimeType, 'application/pdf') === 0 ||
                                    strpos($mimeType, 'text/') === 0;
                
                if ($canDisplayInline) {
                    header('Content-Type: ' . $mimeType);
                    header('Content-Disposition: inline; filename="' . $document['file_name'] . '"');
                } else {
                    // For files that can't be displayed inline, download them
                    header('Content-Type: ' . $mimeType);
                    header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
                }
                
                // Set cache headers to prevent issues
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                readfile($filePath);
                exit;
            } else {
                sendResponse(['success' => false, 'message' => 'File not found'], 404);
            }
            break;
            
        case 'download':
            // checkApiPermission('hr_view');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Document ID is required'], 400);
            }
            
            $query = "SELECT file_path, file_name, mime_type FROM hr_documents WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$document) {
                sendResponse(['success' => false, 'message' => 'Document not found'], 404);
            }
            
            $filePath = resolveHrDocumentFilePath($document['file_path'] ?? '', $document['file_name'] ?? '');
            
            if (file_exists($filePath)) {
                // Get file info
                $fileInfo = pathinfo($document['file_name']);
                $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
                
                // Force download with proper headers - suppress Windows from trying to open
                header('Content-Description: File Transfer');
                header('Content-Type: ' . $document['mime_type']);
                header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: no-cache');
                header('X-Content-Type-Options: nosniff');
                header('Content-Length: ' . filesize($filePath));
                
                // Disable output buffering to prevent issues
                if (ob_get_length()) {
                    ob_clean();
                }
                flush();
                
                readfile($filePath);
                exit;
            } else {
                sendResponse(['success' => false, 'message' => 'File not found'], 404);
            }
            break;
            
        case 'delete':
            // checkApiPermission('hr_delete');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Document ID is required'], 400);
            }
            
            // Get deleted data for history (before deletion)
            $query = "SELECT * FROM hr_documents WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $deletedDocument = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deletedDocument) {
                sendResponse(['success' => false, 'message' => 'Document not found'], 404);
            }
            
            // Delete from database
            $query = "DELETE FROM hr_documents WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Delete file if it exists
            if ($deletedDocument && ($deletedDocument['file_path'] || $deletedDocument['file_name'])) {
                $filePath = resolveHrDocumentFilePath($deletedDocument['file_path'] ?? '', $deletedDocument['file_name'] ?? '');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('hr_documents', $id, 'delete', 'hr', $deletedDocument, null);
            }
            
            sendResponse(['success' => true, 'message' => 'Document deleted successfully']);
            break;
            
        case 'bulk-delete':
            // checkApiPermission('hr_delete');
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
                sendResponse(['success' => false, 'message' => 'No document IDs provided'], 400);
            }
            
            // Validate and sanitize IDs - must be integers
            $validIds = [];
            foreach ($input['ids'] as $id) {
                $id = intval($id);
                if ($id > 0) {
                    $validIds[] = $id;
                }
            }
            
            if (empty($validIds)) {
                sendResponse(['success' => false, 'message' => 'No valid IDs provided'], 400);
            }
            
            // Limit bulk operations to prevent abuse
            if (count($validIds) > 100) {
                sendResponse(['success' => false, 'message' => 'Too many IDs (max 100)'], 400);
            }
            
            $ids = $validIds;
            $deletedCount = 0;
            $notFoundCount = 0;
            
            foreach ($ids as $docId) {
                // Get file path and name before deleting
                $query = "SELECT file_path, file_name FROM hr_documents WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $docId, PDO::PARAM_INT);
                $stmt->execute();
                $document = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($document) {
                    // Delete from database
                    $query = "DELETE FROM hr_documents WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $docId, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $deletedCount++;
                    
                    // Delete file if it exists
                    if ($document['file_path'] || $document['file_name']) {
                        $filePath = resolveHrDocumentFilePath($document['file_path'] ?? '', $document['file_name'] ?? '');
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                } else {
                    $notFoundCount++;
                }
            }
            
            $message = "{$deletedCount} document(s) deleted successfully";
            if ($notFoundCount > 0) {
                $message .= ", {$notFoundCount} not found";
            }
            
            sendResponse(['success' => true, 'message' => $message]);
            break;
            
        case 'bulk-update':
            // checkApiPermission('hr_edit');
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
                sendResponse(['success' => false, 'message' => 'No document IDs provided'], 400);
            }
            
            // Validate status value
            $allowedStatuses = ['active', 'inactive', 'archived', 'expired'];
            if (!isset($input['status']) || !in_array($input['status'], $allowedStatuses)) {
                sendResponse(['success' => false, 'message' => 'Invalid or missing status value'], 400);
            }
            
            // Validate and sanitize IDs - must be integers
            $validIds = [];
            foreach ($input['ids'] as $id) {
                $id = intval($id);
                if ($id > 0) {
                    $validIds[] = $id;
                }
            }
            
            if (empty($validIds)) {
                sendResponse(['success' => false, 'message' => 'No valid IDs provided'], 400);
            }
            
            // Limit bulk operations to prevent abuse
            if (count($validIds) > 100) {
                sendResponse(['success' => false, 'message' => 'Too many IDs (max 100)'], 400);
            }
            
            $status = $input['status'];
            
            // Update all documents with the new status
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            $query = "UPDATE hr_documents SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $params = array_merge([$status], $validIds);
            $stmt->execute($params);
            
            $updatedCount = $stmt->rowCount();
            
            if ($updatedCount > 0) {
                sendResponse([
                    'success' => true,
                    'message' => "{$updatedCount} document(s) updated successfully"
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'No documents were updated'], 404);
            }
            break;
            
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log("HR Documents API Error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An error occurred while processing your request'], 500);
} catch (Error $e) {
    error_log("HR Documents API Fatal Error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
}
?>
