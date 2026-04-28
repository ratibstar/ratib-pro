<?php
/**
 * EN: Handles API endpoint/business logic in `api/reports/individual-reports.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/reports/individual-reports.php`.
 */
/**
 * Individual Reports API Endpoint
 * Handles individual entity report requests
 */

// Suppress warnings that would corrupt JSON output
// But still log errors
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors - they corrupt JSON
ini_set('log_errors', '1'); // But still log them

// Start output buffering to catch any warnings
ob_start();

header('Content-Type: application/json');
    session_start();

// Use the same config as reports.php (mysqli)
try {
require_once(__DIR__ . '/../../includes/config.php');
    require_once(__DIR__ . '/../../api/core/ApiResponse.php');
} catch (Exception $e) {
    // Clean output buffer before sending JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to load required files: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    error_log('Individual Reports API - File load error: ' . $e->getMessage());
    exit;
}

if (!class_exists('IndividualReportsAPI')) {
class IndividualReportsAPI {
    private $conn;
    private $response;

    public function __construct() {
        global $conn;
        
        // Use mysqli connection from includes/config.php (same as reports.php)
        if (isset($conn) && $conn !== null) {
        $this->conn = $conn;
        } else {
            error_log('Individual Reports API - Database connection not available (global $conn is null)');
            $this->conn = null;
        }
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';

        try {
            // Check if database connection is available
            if (!$this->conn) {
                echo ApiResponse::error('Database connection not available', 500);
                return;
            }
            
            switch ($action) {
                case 'get_entities':
                    $this->getEntities();
                    break;
                case 'get_individual_report':
                    $this->getIndividualReport();
                    break;
                case 'get_activities':
                    $this->getActivities();
                    break;
                case 'upload_document':
                    $this->uploadDocument();
                    break;
                case 'view_document':
                    $this->viewDocument();
                    break;
                case 'download_document':
                    $this->downloadDocument();
                    break;
                case 'delete_document':
                    $this->deleteDocument();
                    break;
                case 'generate_document':
                    $this->generateDocument();
                    break;
                case 'export_report':
                    $this->exportReport();
                    break;
                default:
                    echo ApiResponse::error('Invalid action', 400);
            }
        } catch (Exception $e) {
            error_log('Individual Reports API - handleRequest error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            if (class_exists('ApiResponse')) {
            echo ApiResponse::error('Server error: ' . $e->getMessage(), 500);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Server error: ' . $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        }
    }

    private function getEntities() {
        // Accept both 'type' and 'entity_type' for flexibility with frontend
        $type = $_GET['entity_type'] ?? ($_GET['type'] ?? '');
        
        if (empty($type)) {
            // Clean output buffer before sending JSON
            if (ob_get_level() > 0) {
                ob_clean();
            }
            echo ApiResponse::error('Entity type is required', 400);
            exit;
        }

        try {
            $entities = $this->getEntitiesByType($type);
            // Clean output buffer before sending JSON
            if (ob_get_level() > 0) {
                ob_clean();
            }
            echo ApiResponse::success($entities);
        } catch (Exception $e) {
            error_log('Error getting entities: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            // Clean output buffer before sending JSON
            if (ob_get_level() > 0) {
                ob_clean();
            }
            echo ApiResponse::error('Failed to get entities: ' . $e->getMessage(), 500);
        }
        exit;
    }

    private function getEntitiesByType($type) {
        switch ($type) {
            case 'agents':
                return $this->getAgents();
            case 'subagents':
                return $this->getSubAgents();
            case 'workers':
                return $this->getWorkers();
            case 'cases':
                return $this->getCases();
            case 'hr':
                return $this->getHREmployees();
            default:
                return [];
        }
    }

    private function getEntitiesByQuery($query, $entityType) {
        try {
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting {$entityType}: " . $this->conn->error);
                return [];
            }
            
            $entities = [];
            while ($row = $result->fetch_assoc()) {
                $entities[] = $row;
            }
            return $entities;
        } catch (Exception $e) {
            error_log("Error getting {$entityType}: " . $e->getMessage());
            return [];
        }
    }

    private function getAgents() {
        $query = "SELECT id, 
                         CONCAT(COALESCE(formatted_id, CONCAT('A', LPAD(id, 4, '0'))), ' - ', agent_name) as name, 
                         status, 
                         created_at 
                  FROM agents 
                  ORDER BY id";
        return $this->getEntitiesByQuery($query, 'agents');
    }

    private function getSubAgents() {
        $query = "SELECT id, 
                         CONCAT(COALESCE(formatted_id, CONCAT('S', LPAD(id, 4, '0'))), ' - ', subagent_name) as name, 
                         status, 
                         created_at 
                  FROM subagents 
                  ORDER BY id";
        return $this->getEntitiesByQuery($query, 'subagents');
    }

    private function getWorkers() {
        $query = "SELECT id, 
                         CONCAT(COALESCE(formatted_id, CONCAT('W', LPAD(id, 4, '0'))), ' - ', worker_name) as name, 
                         status, 
                         created_at 
                  FROM workers 
                  ORDER BY id";
        return $this->getEntitiesByQuery($query, 'workers');
    }

    private function getCases() {
        $query = "SELECT id, 
                         CONCAT(case_number, ' - ', COALESCE(case_description, 'No Description')) as name, 
                         status, 
                         created_at 
                  FROM cases 
                  ORDER BY case_number";
        return $this->getEntitiesByQuery($query, 'cases');
    }

    private function getHREmployees() {
        $query = "SELECT id, 
                         CONCAT(id, ' - ', name) as name, 
                         status, 
                         join_date as created_at 
                  FROM employees 
                  WHERE status IN ('Active', 'Inactive', 'Terminated')
                  ORDER BY name";
        return $this->getEntitiesByQuery($query, 'HR employees');
    }

    private function getIndividualReport() {
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = $_GET['entity_id'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';

        if (empty($entityType) || empty($entityId)) {
            echo ApiResponse::error('Entity type and ID are required', 400);
            return;
        }
        
        if (!$this->conn) {
            echo ApiResponse::error('Database connection not available', 500);
            return;
        }

        $report = [
            'entity' => $this->getEntityDetails($entityType, $entityId),
            'overview' => $this->getEntityOverview($entityType, $entityId, $startDate, $endDate),
            'performance' => $this->getEntityPerformance($entityType, $entityId, $startDate, $endDate),
            'financial' => $this->getEntityFinancial($entityType, $entityId, $startDate, $endDate),
            'activities' => $this->getEntityActivities($entityType, $entityId, $startDate, $endDate),
            'documents' => $this->getEntityDocuments($entityType, $entityId)
        ];

        echo ApiResponse::success($report);
    }

    private function getEntityDetails($type, $id) {
        switch ($type) {
            case 'agents':
                return $this->getAgentDetails($id);
            case 'subagents':
                return $this->getSubAgentDetails($id);
            case 'workers':
                return $this->getWorkerDetails($id);
            case 'cases':
                return $this->getCaseDetails($id);
            case 'hr':
                return $this->getHREmployeeDetails($id);
            default:
                return [];
        }
    }

    private function getEntityDetailsByQuery($query, $entityType) {
        try {
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting {$entityType} details: " . $this->conn->error);
                return [];
            }
            return $result->fetch_assoc() ?: [];
        } catch (Exception $e) {
            error_log("Error getting {$entityType} details: " . $e->getMessage());
            return [];
        }
    }

    private function getAgentDetails($id) {
        $id = $this->conn->real_escape_string($id);
        $query = "SELECT *, agent_name as name FROM agents WHERE id = '$id'";
        return $this->getEntityDetailsByQuery($query, 'agent');
    }

    private function getSubAgentDetails($id) {
        $id = $this->conn->real_escape_string($id);
        $query = "SELECT *, subagent_name as name FROM subagents WHERE id = '$id'";
        return $this->getEntityDetailsByQuery($query, 'subagent');
    }

    private function getWorkerDetails($id) {
        $id = $this->conn->real_escape_string($id);
        $query = "SELECT *, worker_name as name FROM workers WHERE id = '$id'";
        return $this->getEntityDetailsByQuery($query, 'worker');
    }

    private function getCaseDetails($id) {
        $id = $this->conn->real_escape_string($id);
        $query = "SELECT *, case_number as name FROM cases WHERE id = '$id'";
        return $this->getEntityDetailsByQuery($query, 'case');
    }

    private function getHREmployeeDetails($id) {
        $id = $this->conn->real_escape_string($id);
        $query = "SELECT *, name FROM employees WHERE id = '$id'";
        return $this->getEntityDetailsByQuery($query, 'HR employee');
    }

    private function getEntityOverview($type, $id, $startDate, $endDate) {
        $dateFilter = $this->buildDateFilter($startDate, $endDate);
        
        switch ($type) {
            case 'agents':
                return $this->getAgentOverview($id, $dateFilter);
            case 'subagents':
                return $this->getSubAgentOverview($id, $dateFilter);
            case 'workers':
                return $this->getWorkerOverview($id, $dateFilter);
            case 'cases':
                return $this->getCaseOverview($id, $dateFilter);
            case 'hr':
                return $this->getHREmployeeOverview($id, $dateFilter);
            default:
                return ['metrics' => [], 'activities' => []];
        }
    }

    private function getAgentOverview($id, $dateFilter) {
        // Get key metrics
        $metrics = [
            [
                'label' => 'Total Commissions',
                'value' => '$' . number_format($this->getTotalCommissions($id, $dateFilter), 2)
            ],
            [
                'label' => 'Active Cases',
                'value' => $this->getActiveCases($id, $dateFilter)
            ],
            [
                'label' => 'Performance Score',
                'value' => $this->getPerformanceScore($id) . '%'
            ],
            [
                'label' => 'Client Satisfaction',
                'value' => $this->getClientSatisfaction($id) . '%'
            ]
        ];

        // Get recent activities
        $activities = $this->getRecentActivities('agents', $id, 5);

        return [
            'metrics' => $metrics,
            'activities' => $activities
        ];
    }

    private function getSubAgentOverview($id, $dateFilter) {
        $metrics = [
            [
                'label' => 'Total Commissions',
                'value' => '$' . number_format($this->getTotalCommissions($id, $dateFilter), 2)
            ],
            [
                'label' => 'Active Agents',
                'value' => $this->getActiveAgents($id)
            ],
            [
                'label' => 'Performance Score',
                'value' => $this->getPerformanceScore($id) . '%'
            ],
            [
                'label' => 'Team Size',
                'value' => $this->getTeamSize($id)
            ]
        ];

        $activities = $this->getRecentActivities('subagents', $id, 5);

        return [
            'metrics' => $metrics,
            'activities' => $activities
        ];
    }

    private function getWorkerOverview($id, $dateFilter) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT * FROM workers WHERE id = '$id'";
            $result = $this->conn->query($query);
            
            if (!$result) {
                error_log("Error getting worker overview: " . $this->conn->error);
                return ['metrics' => [], 'activities' => []];
            }
            
            $worker = $result->fetch_assoc();
            
            if (!$worker) {
                return ['metrics' => [], 'activities' => []];
            }
            
            $metrics = [
                [
                    'label' => 'Total Salary',
                    'value' => '$' . number_format($worker['salary'] ?? 0, 2)
                ],
                [
                    'label' => 'Attendance Rate',
                    'value' => $this->getAttendanceRate($id, $dateFilter) . '%'
                ],
                [
                    'label' => 'Performance Score',
                    'value' => $this->getPerformanceScore($id) . '%'
                ],
                [
                    'label' => 'Department',
                    'value' => $worker['job_title'] ?? 'N/A'
                ]
            ];

            $activities = $this->getRecentActivities('workers', $id, 5);

            return [
                'metrics' => $metrics,
                'activities' => $activities
            ];
        } catch (Exception $e) {
            error_log("Error getting worker overview: " . $e->getMessage());
            return ['metrics' => [], 'activities' => []];
        }
    }

    private function getCaseOverview($id, $dateFilter) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT * FROM cases WHERE id = '$id'";
            $result = $this->conn->query($query);
            
            if (!$result) {
                error_log("Error getting case overview: " . $this->conn->error);
                return ['metrics' => [], 'activities' => []];
            }
            
            $case = $result->fetch_assoc();
            
            if (!$case) {
                return ['metrics' => [], 'activities' => []];
            }
            
            $metrics = [
                [
                    'label' => 'Case Status',
                    'value' => $case['status'] ?? 'Unknown'
                ],
                [
                    'label' => 'Case Number',
                    'value' => $case['case_number'] ?? 'N/A'
                ],
                [
                    'label' => 'Created Date',
                    'value' => $case['created_at'] ?? 'N/A'
                ],
                [
                    'label' => 'Priority',
                    'value' => $case['priority'] ?? 'Normal'
                ]
            ];

            $activities = [
                [
                    'title' => 'Case Created',
                    'description' => 'Case was created in the system',
                    'time' => $case['created_at'] ?? 'Unknown',
                    'icon' => 'fas fa-plus'
                ]
            ];

            return [
                'metrics' => $metrics,
                'activities' => $activities
            ];
        } catch (Exception $e) {
            error_log("Error getting case overview: " . $e->getMessage());
            return ['metrics' => [], 'activities' => []];
        }
    }

    private function getHREmployeeOverview($id, $dateFilter) {
        $metrics = [
            [
                'label' => 'Total Salary',
                'value' => '$' . number_format($this->getTotalSalary($id, $dateFilter), 2)
            ],
            [
                'label' => 'Attendance Rate',
                'value' => $this->getAttendanceRate($id, $dateFilter) . '%'
            ],
            [
                'label' => 'Performance Score',
                'value' => $this->getPerformanceScore($id) . '%'
            ],
            [
                'label' => 'Position',
                'value' => $this->getPosition($id)
            ]
        ];

        $activities = $this->getRecentActivities('hr', $id, 5);

        return [
            'metrics' => $metrics,
            'activities' => $activities
        ];
    }

    private function getEntityPerformance($type, $id, $startDate, $endDate) {
        try {
            return [
                'trends' => [
                    'chart' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => 'Performance',
                                'data' => [65, 78, 85, 92, 88, 95],
                                'borderColor' => '#3B82F6',
                                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                                'fill' => true
                            ]
                        ]
                    ]
                ],
                'goals' => [
                    'target' => 100,
                    'current' => 85,
                    'progress' => 85
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting entity performance: " . $e->getMessage());
            return ['trends' => [], 'goals' => []];
        }
    }

    private function getEntityFinancial($type, $id, $startDate, $endDate) {
        try {
            return [
                'revenue' => [
                    'chart' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => 'Revenue',
                                'data' => [1200, 1500, 1800, 2200, 1900, 2500],
                                'backgroundColor' => '#10B981'
                            ]
                        ]
                    ]
                ],
                'commission' => [
                    'total' => 2500,
                    'pending' => 500,
                    'paid' => 2000
                ],
                'transactions' => []
            ];
        } catch (Exception $e) {
            error_log("Error getting entity financial: " . $e->getMessage());
            return ['revenue' => [], 'commission' => [], 'transactions' => []];
        }
    }

    private function getEntityActivities($type, $id, $startDate, $endDate) {
        try {
            return [
                [
                    'title' => 'Entity Created',
                    'description' => 'Entity was created in the system',
                    'time' => date('Y-m-d H:i:s'),
                    'icon' => 'fas fa-plus'
                ],
                [
                    'title' => 'Status Updated',
                    'description' => 'Status was updated',
                    'time' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'icon' => 'fas fa-edit'
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting entity activities: " . $e->getMessage());
            return [];
        }
    }

    private function getEntityDocuments($type, $id) {
        try {
            // Mock documents for now
            return [
                [
                    'id' => 1,
                    'title' => 'Entity Document',
                    'type' => 'pdf',
                    'file_path' => '/documents/entity_' . $id . '.pdf',
                    'file_size' => '2.5 MB',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting entity documents: " . $e->getMessage());
            return [];
        }
    }

    private function getActivities() {
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = $_GET['entity_id'] ?? '';
        $activityType = $_GET['activity_type'] ?? 'all';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';

        if (empty($entityType) || empty($entityId)) {
            echo ApiResponse::error('Entity type and ID are required', 400);
            return;
        }

        $activities = $this->getEntityActivities($entityType, $entityId, $startDate, $endDate);
        echo ApiResponse::success($activities);
    }

    private function uploadDocument() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo ApiResponse::error('Method not allowed', 405);
            return;
        }

        $entityType = $_POST['entity_type'] ?? '';
        $entityId = $_POST['entity_id'] ?? '';
        $documentType = $_POST['document_type'] ?? '';
        $description = $_POST['description'] ?? '';

        if (empty($entityType) || empty($entityId) || empty($documentType)) {
            echo ApiResponse::error('Required fields missing', 400);
            return;
        }

        // Handle file upload
        if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
            echo ApiResponse::error('File upload failed', 400);
            return;
        }

        // config.php is already included at the top of the file
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $uploadDir = $baseUrl . '/uploads/documents/';
        $fileName = time() . '_' . $_FILES['document_file']['name'];
        $filePath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $filePath)) {
            echo ApiResponse::error('Failed to save file', 500);
            return;
        }

        // Save to database
        $entityType = $this->conn->real_escape_string($entityType);
        $entityId = $this->conn->real_escape_string($entityId);
        $title = $this->conn->real_escape_string($_FILES['document_file']['name']);
        $documentType = $this->conn->real_escape_string($documentType);
        $filePath = $this->conn->real_escape_string($filePath);
        $fileSize = (int)$_FILES['document_file']['size'];
        $description = $this->conn->real_escape_string($description);
        
        $query = "INSERT INTO entity_documents (entity_type, entity_id, title, type, file_path, file_size, description, created_at) 
                  VALUES ('$entityType', '$entityId', '$title', '$documentType', '$filePath', $fileSize, '$description', NOW())";
        
        $result = $this->conn->query($query);

        if ($result) {
            echo ApiResponse::success(['message' => 'Document uploaded successfully']);
        } else {
            error_log("Error uploading document: " . $this->conn->error);
            echo ApiResponse::error('Failed to save document record', 500);
        }
    }

    private function viewDocument() {
        $documentId = $_GET['id'] ?? '';
        
        if (empty($documentId)) {
            echo ApiResponse::error('Document ID is required', 400);
            return;
        }

        try {
            $documentId = $this->conn->real_escape_string($documentId);
            $query = "SELECT file_path, title FROM entity_documents WHERE id = '$documentId'";
            $result = $this->conn->query($query);
            
            if (!$result) {
                error_log("Error viewing document: " . $this->conn->error);
                echo ApiResponse::error('Database error', 500);
                return;
            }
            
            $document = $result->fetch_assoc();
            
            if (!$document) {
                echo ApiResponse::error('Document not found', 404);
                return;
            }
            
            // Redirect to document
            header('Location: ' . $document['file_path']);
            exit;
        } catch (Exception $e) {
            error_log("Error viewing document: " . $e->getMessage());
            echo ApiResponse::error('Database error', 500);
            return;
        }
    }

    private function downloadDocument() {
        $documentId = $_GET['id'] ?? '';
        
        if (empty($documentId)) {
            echo ApiResponse::error('Document ID is required', 400);
            return;
        }

        try {
            $documentId = $this->conn->real_escape_string($documentId);
            $query = "SELECT file_path, title FROM entity_documents WHERE id = '$documentId'";
            $result = $this->conn->query($query);
            
            if (!$result) {
                error_log("Error downloading document: " . $this->conn->error);
                echo ApiResponse::error('Database error', 500);
                return;
            }
            
            $document = $result->fetch_assoc();
            
            if (!$document) {
                echo ApiResponse::error('Document not found', 404);
                return;
            }
            
            // Force download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $document['title'] . '"');
            readfile($document['file_path']);
            exit;
        } catch (Exception $e) {
            error_log("Error downloading document: " . $e->getMessage());
            echo ApiResponse::error('Database error', 500);
            return;
        }
    }

    private function deleteDocument() {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            echo ApiResponse::error('Method not allowed', 405);
            return;
        }

        $documentId = $_GET['id'] ?? '';
        
        if (empty($documentId)) {
            echo ApiResponse::error('Document ID is required', 400);
            return;
        }

        try {
            $documentId = $this->conn->real_escape_string($documentId);
            
            // Get file path first
            $query = "SELECT file_path FROM entity_documents WHERE id = '$documentId'";
            $result = $this->conn->query($query);
            
            if (!$result) {
                error_log("Error deleting document (query): " . $this->conn->error);
                echo ApiResponse::error('Database error', 500);
                return;
            }
            
            $document = $result->fetch_assoc();
            
            if (!$document) {
                echo ApiResponse::error('Document not found', 404);
                return;
            }
            
            // Delete from database
            $deleteQuery = "DELETE FROM entity_documents WHERE id = '$documentId'";
            $deleteResult = $this->conn->query($deleteQuery);
        
            if ($deleteResult) {
                // Delete physical file
                if (file_exists($document['file_path'])) {
                    unlink($document['file_path']);
                }
                echo ApiResponse::success(['message' => 'Document deleted successfully']);
            } else {
                error_log("Error deleting document (delete): " . $this->conn->error);
                echo ApiResponse::error('Failed to delete document', 500);
            }
        } catch (Exception $e) {
            error_log("Error deleting document: " . $e->getMessage());
            echo ApiResponse::error('Database error', 500);
            return;
        }
    }

    private function generateDocument() {
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = $_GET['entity_id'] ?? '';
        $format = $_GET['format'] ?? 'pdf';

        if (empty($entityType) || empty($entityId)) {
            echo ApiResponse::error('Entity type and ID are required', 400);
            return;
        }

        // Generate document based on entity type
        $documentPath = $this->generateEntityDocument($entityType, $entityId, $format);
        
        if ($documentPath) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($documentPath) . '"');
            readfile($documentPath);
            exit;
        } else {
            echo ApiResponse::error('Failed to generate document', 500);
        }
    }

    private function exportReport() {
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = $_GET['entity_id'] ?? '';
        $format = $_GET['format'] ?? 'csv';

        if (empty($entityType) || empty($entityId)) {
            echo ApiResponse::error('Entity type and ID are required', 400);
            return;
        }

        // Export report based on format
        if ($format === 'csv') {
            $this->exportCSVReport($entityType, $entityId);
        } elseif ($format === 'pdf') {
            $this->exportPDFReport($entityType, $entityId);
        } elseif ($format === 'excel') {
            $this->exportExcelReport($entityType, $entityId);
        } else {
            echo ApiResponse::error('Unsupported format', 400);
        }
    }
    
    private function exportCSVReport($type, $id) {
        try {
            // Get entity details
            $entity = $this->getEntityDetails($type, $id);
            $overview = $this->getEntityOverview($type, $id, '', '');
            $financial = $this->getEntityFinancial($type, $id, '', '');
            $activities = $this->getRecentActivities($type, $id, 100);
            
            $filename = $type . '_' . $id . '_report_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Write BOM for UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Entity Information
            fputcsv($output, ['Individual Report - ' . ucfirst($type)]);
            fputcsv($output, ['']);
            fputcsv($output, ['Entity Information']);
            fputcsv($output, ['Name', $entity['name'] ?? 'N/A']);
            fputcsv($output, ['ID', $entity['id'] ?? 'N/A']);
            fputcsv($output, ['Status', $entity['status'] ?? 'N/A']);
            fputcsv($output, ['']);
            
            // Overview
            if (!empty($overview) && isset($overview['metrics'])) {
                fputcsv($output, ['Overview']);
                foreach ($overview['metrics'] as $metric) {
                    fputcsv($output, [$metric['label'] ?? 'N/A', $metric['value'] ?? 'N/A']);
                }
                fputcsv($output, ['']);
            }
            
            // Financial Summary
            if (!empty($financial)) {
                fputcsv($output, ['Financial Summary']);
                if (isset($financial['total_revenue'])) {
                    fputcsv($output, ['Total Revenue', $financial['total_revenue']]);
                }
                if (isset($financial['total_expenses'])) {
                    fputcsv($output, ['Total Expenses', $financial['total_expenses']]);
                }
                if (isset($financial['net_profit'])) {
                    fputcsv($output, ['Net Profit', $financial['net_profit']]);
                }
                fputcsv($output, ['']);
            }
            
            // Activities
            if (!empty($activities)) {
                fputcsv($output, ['Recent Activities']);
                fputcsv($output, ['Title', 'Description', 'Time', 'Type']);
                foreach ($activities as $activity) {
                    fputcsv($output, [
                        $activity['title'] ?? 'N/A',
                        $activity['description'] ?? 'N/A',
                        $activity['time'] ?? 'N/A',
                        $activity['type'] ?? 'N/A'
                    ]);
                }
            }
            
            fclose($output);
            exit;
        } catch (Exception $e) {
            error_log('CSV Export Error: ' . $e->getMessage());
            echo ApiResponse::error('Failed to export CSV: ' . $e->getMessage(), 500);
        }
    }

    // Helper methods
    private function buildDateFilter($startDate, $endDate) {
        if (empty($startDate) || empty($endDate)) {
            return '';
        }
        $startDate = $this->conn->real_escape_string($startDate);
        $endDate = $this->conn->real_escape_string($endDate);
        return "AND created_at BETWEEN '$startDate' AND '$endDate'";
    }

    private function getTotalCommissions($id, $dateFilter) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT SUM(commission_amount) as total FROM agent_commissions WHERE agent_id = '$id' $dateFilter";
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting total commissions: " . $this->conn->error);
                return 0;
            }
            $row = $result->fetch_assoc();
            return $row ? ($row['total'] ?? 0) : 0;
        } catch (Exception $e) {
            error_log("Error getting total commissions: " . $e->getMessage());
            return 0;
        }
    }

    private function getActiveCases($id, $dateFilter) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT COUNT(*) as count FROM cases WHERE agent_id = '$id' AND status = 'active' $dateFilter";
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting active cases: " . $this->conn->error);
                return 0;
            }
            $row = $result->fetch_assoc();
            return $row ? ($row['count'] ?? 0) : 0;
        } catch (Exception $e) {
            error_log("Error getting active cases: " . $e->getMessage());
            return 0;
        }
    }

    private function getWorkerBasicInfo($id) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT status, created_at FROM workers WHERE id = '$id'";
            $result = $this->conn->query($query);
            
            if (!$result) {
                error_log("Error getting worker basic info: " . $this->conn->error);
                return null;
            }
            
            return $result->fetch_assoc() ?: null;
        } catch (Exception $e) {
            error_log("Error getting worker basic info: " . $e->getMessage());
            return null;
        }
    }

    private function getPerformanceScore($id) {
        $worker = $this->getWorkerBasicInfo($id);
        
        if (!$worker) {
            return 0;
        }
        
        // Calculate performance based on status
        $baseScore = 80;
        
        switch ($worker['status']) {
            case 'approved':
            case 'deployed':
                $baseScore = 90;
                break;
            case 'pending':
                $baseScore = 70;
                break;
            case 'returned':
                $baseScore = 60;
                break;
        }
        
        // Add some variation based on ID for demo
        $variation = ((int)$id % 20) - 10; // -10 to +10 variation
        return max(50, min(100, $baseScore + $variation));
    }

    private function getClientSatisfaction($id) {
        // Mock client satisfaction - replace with actual calculation
        return rand(80, 100);
    }

    private function getActiveAgents($id) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT COUNT(*) as count FROM agents WHERE subagent_id = '$id' AND status = 'active'";
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting active agents: " . $this->conn->error);
                return 0;
            }
            $row = $result->fetch_assoc();
            return $row ? ($row['count'] ?? 0) : 0;
        } catch (Exception $e) {
            error_log("Error getting active agents: " . $e->getMessage());
            return 0;
        }
    }

    private function getTeamSize($id) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT COUNT(*) as count FROM agents WHERE subagent_id = '$id'";
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting team size: " . $this->conn->error);
                return 0;
            }
            $row = $result->fetch_assoc();
            return $row ? ($row['count'] ?? 0) : 0;
        } catch (Exception $e) {
            error_log("Error getting team size: " . $e->getMessage());
            return 0;
        }
    }
    private function getWorkerField($id, $field, $default = 0) {
        try {
            $id = $this->conn->real_escape_string($id);
            $field = $this->conn->real_escape_string($field);
            $query = "SELECT {$field} FROM workers WHERE id = '$id'";
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting worker {$field}: " . $this->conn->error);
                return $default;
            }
            $row = $result->fetch_assoc();
            return $row ? ($row[$field] ?? $default) : $default;
        } catch (Exception $e) {
            error_log("Error getting worker {$field}: " . $e->getMessage());
            return $default;
        }
    }

    private function getTotalSalary($id, $dateFilter) {
        return $this->getWorkerField($id, 'salary', 0);
    }

    private function getAttendanceRate($id, $dateFilter) {
        $worker = $this->getWorkerBasicInfo($id);
        
        if (!$worker) {
            return 0;
        }
        
        // Calculate attendance based on status
        switch ($worker['status']) {
            case 'approved':
            case 'deployed':
                return 95; // High attendance for active workers
            case 'pending':
                return 75; // Lower for pending workers
            case 'returned':
                return 60; // Lower for returned workers
            default:
                return 85;
        }
    }

    private function getDepartment($id) {
        return $this->getWorkerField($id, 'department', 'N/A');
    }

    private function getCaseValue($id) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT total_amount FROM cases WHERE id = '$id'";
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting case value: " . $this->conn->error);
                return 0;
            }
            $row = $result->fetch_assoc();
            return $row ? ($row['total_amount'] ?? 0) : 0;
        } catch (Exception $e) {
            error_log("Error getting case value: " . $e->getMessage());
            return 0;
        }
    }

    private function getCaseProgress($id) {
        // Mock case progress - replace with actual calculation
        return rand(20, 100);
    }

    private function getDaysActive($id) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT DATEDIFF(NOW(), created_at) as days FROM cases WHERE id = '$id'";
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting days active: " . $this->conn->error);
                return 0;
            }
            $row = $result->fetch_assoc();
            return $row ? ($row['days'] ?? 0) : 0;
        } catch (Exception $e) {
            error_log("Error getting days active: " . $e->getMessage());
            return 0;
        }
    }

    private function getCasePriority($id) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT priority FROM cases WHERE id = '$id'";
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting case priority: " . $this->conn->error);
                return 'Medium';
            }
            $row = $result->fetch_assoc();
            return $row ? ($row['priority'] ?? 'Medium') : 'Medium';
        } catch (Exception $e) {
            error_log("Error getting case priority: " . $e->getMessage());
            return 'Medium';
        }
    }

    private function getPosition($id) {
        try {
            $id = $this->conn->real_escape_string($id);
            $query = "SELECT position FROM employees WHERE id = '$id'";
            $result = $this->conn->query($query);
            if (!$result) {
                error_log("Error getting position: " . $this->conn->error);
                return 'N/A';
            }
            $row = $result->fetch_assoc();
            return $row ? ($row['position'] ?? 'N/A') : 'N/A';
        } catch (Exception $e) {
            error_log("Error getting position: " . $e->getMessage());
            return 'N/A';
        }
    }

    private function getRecentActivities($type, $id, $limit = 10, $dateFilter = '') {
        try {
            $activities = [];
            
            // Get entity creation/update info
            $table = '';
            $nameColumn = '';
            $statusColumn = 'status';
            
            switch ($type) {
                case 'agents':
                    $table = 'agents';
                    $nameColumn = 'agent_name';
                    break;
                case 'subagents':
                    $table = 'subagents';
                    $nameColumn = 'subagent_name';
                    break;
                case 'workers':
                    $table = 'workers';
                    $nameColumn = 'worker_name';
                    break;
                case 'cases':
                    $table = 'cases';
                    $nameColumn = 'case_title';
                    break;
                case 'hr':
                    $table = 'employees';
                    $nameColumn = 'name';
                    break;
            }
            
            if ($table) {
                $id = $this->conn->real_escape_string($id);
                // Note: $nameColumn, $statusColumn, $table are hardcoded from switch statement, safe to use directly
                $query = "SELECT $nameColumn, $statusColumn, created_at, updated_at FROM $table WHERE id = '$id'";
                $result = $this->conn->query($query);
                if (!$result) {
                    error_log("Error getting recent activities: " . $this->conn->error);
                    $entity = null;
                } else {
                    $entity = $result->fetch_assoc();
                }
                
                if ($entity) {
                    $name = $entity[$nameColumn] ?? 'Entity';
                    $status = $entity[$statusColumn] ?? 'Unknown';
                    $createdAt = $entity['created_at'];
                    $updatedAt = $entity['updated_at'];
                    
                    // Add creation activity
                    $activities[] = [
                        'title' => 'Entity Created',
                        'description' => "$name was created in the system",
                        'time' => $this->getTimeAgo($createdAt),
                        'icon' => 'fas fa-plus-circle'
                    ];
                    
                    // Add update activity if different from creation
                    if ($updatedAt && $updatedAt !== $createdAt) {
                        $activities[] = [
                            'title' => 'Profile Updated',
                            'description' => "$name profile information was updated",
                            'time' => $this->getTimeAgo($updatedAt),
                            'icon' => 'fas fa-edit'
                        ];
                    }
                    
                    // Add status change activity
                    $activities[] = [
                        'title' => 'Status Update',
                        'description' => "$name status changed to $status",
                        'time' => $this->getTimeAgo($updatedAt ?: $createdAt),
                        'icon' => 'fas fa-info-circle'
                    ];
                }
            }
            
            // If no real activities, add some generic ones
            if (empty($activities)) {
                $activities = $this->getDefaultActivities();
            }
            
            return array_slice($activities, 0, $limit);
            
        } catch (Exception $e) {
            error_log("Error getting recent activities: " . $e->getMessage());
            return $this->getDefaultActivities(1);
        }
    }
    
    private function getDefaultActivities($limit = 3) {
        $defaultActivities = [
            [
                'title' => 'System Access',
                'description' => 'Entity accessed in the system',
                'time' => '2 hours ago',
                'icon' => 'fas fa-sign-in-alt'
            ],
            [
                'title' => 'Data Update',
                'description' => 'Profile information updated',
                'time' => '4 hours ago',
                'icon' => 'fas fa-edit'
            ],
            [
                'title' => 'Document Upload',
                'description' => 'New document uploaded',
                'time' => '1 day ago',
                'icon' => 'fas fa-upload'
            ]
        ];
        return array_slice($defaultActivities, 0, $limit);
    }
    
    private function getTimeAgo($datetime) {
        if (!$datetime) return 'Unknown time';
        
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . ' minutes ago';
        if ($time < 86400) return floor($time / 3600) . ' hours ago';
        if ($time < 2592000) return floor($time / 86400) . ' days ago';
        if ($time < 31536000) return floor($time / 2592000) . ' months ago';
        return floor($time / 31536000) . ' years ago';
    }

    private function getPerformanceTrends($type, $id, $dateFilter) {
        // Mock performance trends - replace with actual data
        return [
            'type' => 'line',
            'data' => [
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                'datasets' => [[
                    'label' => 'Performance',
                    'data' => [65, 78, 85, 92, 88, 95],
                    'borderColor' => '#667eea',
                    'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                    'tension' => 0.4
                ]]
            ]
        ];
    }

    private function getPerformanceGoals($type, $id) {
        // Mock performance goals - replace with actual data
        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => ['Completed', 'Remaining'],
                'datasets' => [[
                    'data' => [75, 25],
                    'backgroundColor' => ['#4CAF50', '#333'],
                    'borderWidth' => 0
                ]]
            ]
        ];
    }

    private function getRevenueChart($type, $id, $dateFilter) {
        // Mock revenue chart - replace with actual data
        return [
            'type' => 'bar',
            'data' => [
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                'datasets' => [[
                    'label' => 'Revenue',
                    'data' => [12000, 15000, 18000, 22000, 19000, 25000],
                    'backgroundColor' => 'rgba(33, 150, 243, 0.8)',
                    'borderColor' => '#2196F3'
                ]]
            ]
        ];
    }

    private function getCommissionChart($type, $id, $dateFilter) {
        // Mock commission chart - replace with actual data
        return [
            'type' => 'pie',
            'data' => [
                'labels' => ['Direct Commission', 'Team Commission', 'Bonus'],
                'datasets' => [[
                    'data' => [60, 30, 10],
                    'backgroundColor' => ['#667eea', '#4CAF50', '#FF9800'],
                    'borderWidth' => 0
                ]]
            ]
        ];
    }

    private function getTransactions($type, $id, $dateFilter) {
        // Mock transactions - replace with actual data
        return [
            [
                'date' => '2024-01-15',
                'type' => 'Commission',
                'amount' => '$1,250.00',
                'status' => 'completed',
                'description' => 'Monthly commission payment'
            ],
            [
                'date' => '2024-01-10',
                'type' => 'Bonus',
                'amount' => '$500.00',
                'status' => 'completed',
                'description' => 'Performance bonus'
            ]
        ];
    }

    private function generateEntityDocument($type, $id, $format) {
        // Mock document generation - implement actual PDF generation
        return null;
    }

    private function exportPDFReport($type, $id) {
        // Mock PDF export - implement actual PDF generation
        echo ApiResponse::error('PDF export not implemented yet', 501);
    }

    private function exportExcelReport($type, $id) {
        // Mock Excel export - implement actual Excel generation
        echo ApiResponse::error('Excel export not implemented yet', 501);
    }
}

} // End class_exists check

// Handle the request
try {
    if (!class_exists('IndividualReportsAPI')) {
        throw new Exception('IndividualReportsAPI class not defined');
    }
    $api = new IndividualReportsAPI();
    $api->handleRequest();
} catch (Exception $e) {
    error_log('Individual Reports API - Main execution error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to initialize API: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>

