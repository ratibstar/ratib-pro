<?php
/**
 * EN: Handles API endpoint/business logic in `api/contacts/simple_contacts.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/contacts/simple_contacts.php`.
 */
/**
 * Simple Contacts API - Guaranteed to work!
 */

// Suppress any output that might interfere with JSON response
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load application config (DB constants, email settings, session handling)
require_once __DIR__ . '/../../includes/config.php';

// Try to load PHPMailer if available
$phpmailerPaths = [
    __DIR__ . '/../../vendor/PHPMailer/src/PHPMailer.php',
    __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php'
];

foreach ($phpmailerPaths as $path) {
    if (file_exists($path)) {
        require_once dirname($path) . '/Exception.php';
        require_once dirname($path) . '/PHPMailer.php';
        require_once dirname($path) . '/SMTP.php';
        break;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Clear any output buffer
ob_clean();

// Database connection using config constants
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * Retrieve JSON body once and cache it for reuse.
 */
function getJsonInput() {
    static $jsonInput = null;
    
    if ($jsonInput !== null) {
        return $jsonInput;
    }
    
    $rawInput = file_get_contents('php://input');
    if ($rawInput !== false && trim($rawInput) !== '') {
        $decoded = json_decode($rawInput, true);
        $jsonInput = is_array($decoded) ? $decoded : [];
    } else {
        $jsonInput = [];
    }
    
    return $jsonInput;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (!$action) {
    $jsonInput = getJsonInput();
    if (isset($jsonInput['action'])) {
        $action = $jsonInput['action'];
    }
}
// Action logging disabled for production

try {
    switch ($action) {
        case 'get_contacts':
            getContacts($pdo);
            break;
        case 'get_contact':
            getContact($pdo);
            break;
        case 'create_contact':
            createContact($pdo);
            break;
        case 'update_contact':
            updateContact($pdo);
            break;
        case 'delete_contact':
            deleteContact($pdo);
            break;
        case 'add_communication':
            addCommunication($pdo);
            break;
        case 'edit_communication':
            editCommunication($pdo);
            break;
        case 'delete_communication':
            deleteCommunication($pdo);
            break;
        case 'bulk_delete_communications':
            bulkDeleteCommunications($pdo);
            break;
        case 'bulk_change_status_communications':
            bulkChangeStatusCommunications($pdo);
            break;
        case 'bulk_update_communications':
            bulkUpdateCommunications($pdo);
            break;
        case 'get_recent_communications':
            getRecentCommunications($pdo);
            break;
        case 'get_communication':
            getCommunication($pdo);
            break;
        case 'send_message':
            sendMessage($pdo);
            break;
        case 'get_all_contacts':
            getAllContactsFromAllSources($pdo);
            break;
        case 'export_communications':
            exportCommunications($pdo);
            break;
        case 'get_sent_messages':
            getSentMessages($pdo);
            break;
        case 'test':
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'API is working!', 'tables_exist' => checkTables($pdo)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
    }
} catch (Exception $e) {
    // Clean any output buffer before sending error response
    ob_clean();
    error_log('Simple Contacts API Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function getAllContactsFromAllSources($pdo) {
    $allContacts = [];
    $seenIds = [];
    $seenNamesBySource = []; // Track seen names per source type to avoid duplicate names
    
    try {
        // Get agents - try both possible table structures (PRIORITY: Load agents first to avoid duplicates)
        try {
            $stmt = $pdo->query("SELECT id, agent_name as name, email, contact_number as phone, city, 'agent' as source_type, 'agent' as contact_type FROM agents");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $contactId = 'agent_' . $row['id'];
                // Only add if we haven't seen this ID before
                if (!isset($seenIds[$contactId])) {
                $allContacts[] = [
                        'id' => $contactId,
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                        'city' => $row['city'] ?? '',
                        'country' => '', // Agents table doesn't have country
                    'company' => 'Agent',
                    'source_type' => 'Agent',
                        'contact_type' => 'agent',
                    'source_id' => $row['id']
                ];
                    $seenIds[$contactId] = true;
                }
            }
        } catch (PDOException $e) {
            // Try alternative table structure
            try {
                $stmt = $pdo->query("SELECT agent_id as id, full_name as name, email, phone, city, 'agent' as source_type, 'agent' as contact_type FROM agents");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $contactId = 'agent_' . $row['id'];
                    // Only add if we haven't seen this ID before
                    if (!isset($seenIds[$contactId])) {
                    $allContacts[] = [
                            'id' => $contactId,
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                            'city' => $row['city'] ?? '',
                            'country' => '',
                        'company' => 'Agent',
                        'source_type' => 'Agent',
                            'contact_type' => 'agent',
                        'source_id' => $row['id']
                    ];
                        $seenIds[$contactId] = true;
                    }
                }
            } catch (PDOException $e2) {
                // Table doesn't exist or has different structure
            }
        }
        
        // Get subagents - try both possible table structures
        try {
            $stmt = $pdo->query("SELECT id, subagent_name as name, email, contact_number as phone, city, 'subagent' as source_type, 'subagent' as contact_type FROM subagents");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $contactId = 'subagent_' . $row['id'];
                // Only add if we haven't seen this ID before AND this name hasn't been seen as a SubAgent
                if (!isset($seenIds[$contactId])) {
                    if (!isset($seenNamesBySource['SubAgent'][$row['name']])) {
                $allContacts[] = [
                            'id' => $contactId,
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                            'city' => $row['city'] ?? '',
                            'country' => '', // SubAgents table doesn't have country
                    'company' => 'SubAgent',
                    'source_type' => 'SubAgent',
                            'contact_type' => 'subagent',
                    'source_id' => $row['id']
                ];
                        $seenIds[$contactId] = true;
                        $seenNamesBySource['SubAgent'][$row['name']] = true;
                    }
                }
            }
        } catch (PDOException $e) {
            // Try alternative table structure
            try {
                $stmt = $pdo->query("SELECT subagent_id as id, full_name as name, email, phone, city, 'subagent' as source_type, 'subagent' as contact_type FROM subagents");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $contactId = 'subagent_' . $row['id'];
                    // Only add if we haven't seen this ID before AND this name hasn't been seen as a SubAgent
                    if (!isset($seenIds[$contactId])) {
                        if (!isset($seenNamesBySource['SubAgent'][$row['name']])) {
                    $allContacts[] = [
                                'id' => $contactId,
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                                'city' => $row['city'] ?? '',
                                'country' => '',
                        'company' => 'SubAgent',
                        'source_type' => 'SubAgent',
                                'contact_type' => 'subagent',
                        'source_id' => $row['id']
                    ];
                            $seenIds[$contactId] = true;
                            $seenNamesBySource['SubAgent'][$row['name']] = true;
                        }
                    }
                }
            } catch (PDOException $e2) {
                // Table doesn't exist or has different structure
            }
        }
        
        // Get workers - try both possible table structures
        try {
            $stmt = $pdo->query("SELECT id, worker_name as name, contact_number as phone, email, city, country, 'worker' as source_type, 'worker' as contact_type FROM workers");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $contactId = 'worker_' . $row['id'];
                // Only add if we haven't seen this ID before
                if (!isset($seenIds[$contactId])) {
                $allContacts[] = [
                        'id' => $contactId,
                    'name' => $row['name'],
                        'email' => $row['email'] ?? '',
                    'phone' => $row['phone'],
                        'city' => $row['city'] ?? '',
                        'country' => $row['country'] ?? '',
                    'company' => 'Worker',
                    'source_type' => 'Worker',
                        'contact_type' => 'worker',
                    'source_id' => $row['id']
                ];
                    $seenIds[$contactId] = true;
                }
            }
        } catch (PDOException $e) {
            // Try alternative table structure
            try {
                $stmt = $pdo->query("SELECT worker_id as id, full_name as name, phone, email, city, country, 'worker' as source_type, 'worker' as contact_type FROM workers");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $contactId = 'worker_' . $row['id'];
                    // Only add if we haven't seen this ID before
                    if (!isset($seenIds[$contactId])) {
                    $allContacts[] = [
                            'id' => $contactId,
                        'name' => $row['name'],
                            'email' => $row['email'] ?? '',
                        'phone' => $row['phone'],
                            'city' => $row['city'] ?? '',
                            'country' => $row['country'] ?? '',
                        'company' => 'Worker',
                        'source_type' => 'Worker',
                            'contact_type' => 'worker',
                        'source_id' => $row['id']
                    ];
                        $seenIds[$contactId] = true;
                    }
                }
            } catch (PDOException $e2) {
                // Table doesn't exist or has different structure
            }
        }
        
        // Get HR contacts - try multiple possible table structures
        try {
            // Try hr_employees table first
            $stmt = $pdo->query("SELECT id, employee_name as name, email, phone, city, country, 'hr' as source_type FROM hr_employees WHERE employee_name IS NOT NULL AND employee_name != ''");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $contactId = 'hr_' . $row['id'];
                // Only add if we haven't seen this ID before
                if (!isset($seenIds[$contactId])) {
                $allContacts[] = [
                        'id' => $contactId,
                    'name' => $row['name'],
                    'email' => $row['email'] ?? '',
                    'phone' => $row['phone'] ?? '',
                        'city' => $row['city'] ?? '',
                        'country' => $row['country'] ?? '',
                    'company' => 'HR',
                    'source_type' => 'HR',
                        'contact_type' => 'hr',
                    'source_id' => $row['id']
                ];
                    $seenIds[$contactId] = true;
                }
            }
        } catch (PDOException $e) {
            try {
                // Try hr_documents table
                $stmt = $pdo->query("SELECT DISTINCT employee_id as id, employee_name as name, 'hr' as source_type FROM hr_documents WHERE employee_name IS NOT NULL AND employee_name != ''");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $contactId = 'hr_' . $row['id'];
                    // Only add if we haven't seen this ID before
                    if (!isset($seenIds[$contactId])) {
                    $allContacts[] = [
                            'id' => $contactId,
                        'name' => $row['name'],
                        'email' => '',
                        'phone' => '',
                            'city' => '',
                            'country' => '',
                        'company' => 'HR',
                        'source_type' => 'HR',
                            'contact_type' => 'hr',
                        'source_id' => $row['id']
                    ];
                        $seenIds[$contactId] = true;
                    }
                }
            } catch (PDOException $e2) {
                try {
                    // Try employees table
                    $stmt = $pdo->query("SELECT id, name, email, phone, city, 'hr' as source_type FROM employees WHERE name IS NOT NULL AND name != ''");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $contactId = 'hr_' . $row['id'];
                        // Only add if we haven't seen this ID before
                        if (!isset($seenIds[$contactId])) {
                        $allContacts[] = [
                                'id' => $contactId,
                            'name' => $row['name'],
                            'email' => $row['email'] ?? '',
                            'phone' => $row['phone'] ?? '',
                                'city' => $row['city'] ?? '',
                                'country' => '', // employees table doesn't have country
                            'company' => 'HR',
                            'source_type' => 'HR',
                                'contact_type' => 'hr',
                            'source_id' => $row['id']
                        ];
                            $seenIds[$contactId] = true;
                        }
                    }
                } catch (PDOException $e3) {
                    // No HR tables found
                }
            }
        }
        
        // Get contacts from contacts table (load LAST to avoid duplicates)
        try {
            $stmt = $pdo->query("SELECT id, name, email, phone, company, contact_type, city, country, 'contact' as source_type FROM contacts WHERE status = 'active'");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Skip contacts that have a contact_type matching agents/subagents/workers/hr (already loaded from their tables)
                if (in_array($row['contact_type'], ['agent', 'subagent', 'worker', 'hr'])) {
                    continue;
                }
                
                // Add contact from contacts table
                $allContacts[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'company' => $row['company'],
                    'contact_type' => $row['contact_type'] ?? '',
                    'city' => $row['city'] ?? '',
                    'country' => $row['country'] ?? '',
                    'source_type' => 'Contact',
                    'source_id' => $row['id']
                ];
            }
        } catch (PDOException $e) {
            // contacts table might not exist
        }
        
        // Sort by creation date (newest first), then by name
        usort($allContacts, function($a, $b) {
            // First sort by creation date (newest first)
            $dateA = $a['created_at'] ?? $a['updated_at'] ?? '1970-01-01';
            $dateB = $b['created_at'] ?? $b['updated_at'] ?? '1970-01-01';
            
            if ($dateA != $dateB) {
                return strtotime($dateB) - strtotime($dateA); // Newest first
            }
            
            // If dates are the same, sort by name
            return strcmp($a['name'], $b['name']);
        });
        
        ob_clean();
        echo json_encode(['success' => true, 'data' => $allContacts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function checkTables($pdo) {
    $tables = [];
    $result = $pdo->query("SHOW TABLES LIKE 'contacts'");
    $tables['contacts'] = $result->rowCount() > 0;
    
    $result = $pdo->query("SHOW TABLES LIKE 'contact_communications'");
    $tables['communications'] = $result->rowCount() > 0;
    
    return $tables;
}

function getContacts($pdo) {
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    // Get all contacts from all sources (same as getAllContactsFromAllSources)
    $allContacts = [];
    
    try {
        // Get contacts from contacts table
        $stmt = $pdo->query("SELECT id, contact_id, name, email, phone, city, country, contact_type, status, 'Contact' as source_type, created_at, updated_at FROM contacts");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $allContacts[] = $row;
        }
        
        // Get agents
        try {
            $stmt = $pdo->query("SELECT id, agent_name as name, email, contact_number as phone, city, 'Agent' as source_type, 'agent' as contact_type, 'active' as status, created_at, updated_at FROM agents");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['city'] = $row['city'] ?? '';
                $row['country'] = ''; // Agents table doesn't have country
                $allContacts[] = $row;
            }
        } catch (PDOException $e) {
            // Try alternative table structure
            try {
                $stmt = $pdo->query("SELECT agent_id as id, full_name as name, email, phone, city, 'Agent' as source_type, 'agent' as contact_type, 'active' as status, created_at, updated_at FROM agents");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['city'] = $row['city'] ?? '';
                    $row['country'] = '';
                    $allContacts[] = $row;
                }
            } catch (PDOException $e2) {
                // Table doesn't exist
            }
        }
        
        // Get subagents
        try {
            $stmt = $pdo->query("SELECT id, subagent_name as name, email, contact_number as phone, city, 'SubAgent' as source_type, 'subagent' as contact_type, 'active' as status, created_at, updated_at FROM subagents");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['city'] = $row['city'] ?? '';
                $row['country'] = ''; // SubAgents table doesn't have country
                $allContacts[] = $row;
            }
        } catch (PDOException $e) {
            // Try alternative table structure
            try {
                $stmt = $pdo->query("SELECT subagent_id as id, full_name as name, email, phone, city, 'SubAgent' as source_type, 'subagent' as contact_type, 'active' as status, created_at, updated_at FROM subagents");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['city'] = $row['city'] ?? '';
                    $row['country'] = '';
                    $allContacts[] = $row;
                }
            } catch (PDOException $e2) {
                // Table doesn't exist
            }
        }
        
        // Get workers
        try {
            $stmt = $pdo->query("SELECT id, worker_name as name, contact_number as phone, email, city, country, 'Worker' as source_type, 'worker' as contact_type, 'active' as status, created_at, updated_at FROM workers");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['email'] = $row['email'] ?? ''; // Workers might not have email
                $row['city'] = $row['city'] ?? '';
                $row['country'] = $row['country'] ?? '';
                $allContacts[] = $row;
            }
        } catch (PDOException $e) {
            // Try alternative table structure
            try {
                $stmt = $pdo->query("SELECT worker_id as id, full_name as name, phone, email, city, country, 'Worker' as source_type, 'worker' as contact_type, 'active' as status, created_at, updated_at FROM workers");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $row['email'] = $row['email'] ?? ''; // Workers might not have email
                    $row['city'] = $row['city'] ?? '';
                    $row['country'] = $row['country'] ?? '';
                    $allContacts[] = $row;
                }
            } catch (PDOException $e2) {
                // Table doesn't exist
            }
        }
        
        // Apply filters
        if ($search) {
            $allContacts = array_filter($allContacts, function($contact) use ($search) {
                return stripos($contact['name'], $search) !== false || 
                       stripos($contact['email'], $search) !== false || 
                       stripos($contact['phone'], $search) !== false || 
                       stripos($contact['company'], $search) !== false;
            });
        }
        
        if ($type) {
            $allContacts = array_filter($allContacts, function($contact) use ($type) {
                return $contact['contact_type'] === $type || $contact['source_type'] === $type;
            });
        }
        
        if ($status) {
            $allContacts = array_filter($allContacts, function($contact) use ($status) {
                return $contact['status'] === $status;
            });
        }
        
        // Sort by creation date (newest first), then by name
        usort($allContacts, function($a, $b) {
            // First sort by creation date (newest first)
            $dateA = $a['created_at'] ?? $a['updated_at'] ?? '1970-01-01';
            $dateB = $b['created_at'] ?? $b['updated_at'] ?? '1970-01-01';
            
            if ($dateA != $dateB) {
                return strtotime($dateB) - strtotime($dateA); // Newest first
            }
            
            // If dates are the same, sort by name
            return strcmp($a['name'], $b['name']);
        });
        
        // Apply pagination
        $totalRecords = count($allContacts);
        $totalPages = ceil($totalRecords / $limit);
        $contacts = array_slice($allContacts, $offset, $limit);
        
        
        // Add last_contact_date to each contact
        foreach ($contacts as &$contact) {
            try {
                $stmt = $pdo->prepare("SELECT MAX(communication_date) as last_contact_date FROM contact_communications WHERE contact_id = ?");
                $stmt->execute([$contact['id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $contact['last_contact_date'] = $result['last_contact_date'];
            } catch (PDOException $e) {
                $contact['last_contact_date'] = null;
            }
        }
        
        $pagination = [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit
        ];
        
        ob_clean();
        echo json_encode([
            'success' => true, 
            'data' => [
                'contacts' => $contacts,
                'pagination' => $pagination
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function createContact($pdo) {
    $input = getJsonInput();
    
    if (!$input || empty($input['name'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Name is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    // Generate unique contact_id
    $contactId = 'C' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Ensure contact_id is unique
    $checkStmt = $pdo->prepare("SELECT id FROM contacts WHERE contact_id = ?");
    $checkStmt->execute([$contactId]);
    
    while ($checkStmt->fetch()) {
        $contactId = 'CON' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $checkStmt->execute([$contactId]);
    }
    
    // Insert with only the essential fields
    $sql = "INSERT INTO contacts (contact_id, name, email, phone, city, country, contact_type, status, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
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
        $newId = $pdo->lastInsertId();
        
        // Get created contact for history
        $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
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
        echo json_encode(['success' => true, 'message' => 'Contact created successfully', 'data' => ['id' => $newId, 'contact_id' => $contactId]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to create contact'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function updateContact($pdo) {
    $contactId = $_GET['id'] ?? '';
    $input = getJsonInput();
    
    if (!$contactId || !$input) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact ID and data required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    // Handle composite IDs (e.g., "subagent_56", "agent_1", "worker_5")
    if (strpos($contactId, '_') !== false) {
        $parts = explode('_', $contactId);
        $sourceTable = $parts[0];
        $sourceId = $parts[1];
        
        // Update the source table (subagents, agents, workers)
        $updateFields = [];
        $params = [];
        
        switch ($sourceTable) {
            case 'subagent':
                if (isset($input['name'])) {
                    $updateFields[] = "subagent_name = ?";
                    $params[] = $input['name'];
                }
                if (isset($input['email'])) {
                    $updateFields[] = "email = ?";
                    $params[] = $input['email'];
                }
                if (isset($input['phone'])) {
                    $updateFields[] = "contact_number = ?";
                    $params[] = $input['phone'];
                }
                if (isset($input['city'])) {
                    $updateFields[] = "city = ?";
                    $params[] = $input['city'];
                }
                if (isset($input['address'])) {
                    $updateFields[] = "address = ?";
                    $params[] = $input['address'];
                }
                
                if (!empty($updateFields)) {
                    // Get old data for history
                    $oldStmt = $pdo->prepare("SELECT * FROM subagents WHERE id = ?");
                    $oldStmt->execute([$sourceId]);
                    $oldSubagent = $oldStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $params[] = $sourceId;
                    $sql = "UPDATE subagents SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute($params);
                    
                    if ($result) {
                        // Get updated data for history
                        $newStmt = $pdo->prepare("SELECT * FROM subagents WHERE id = ?");
                        $newStmt->execute([$sourceId]);
                        $newSubagent = $newStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Log history
                        $helperPath = __DIR__ . '/../core/global-history-helper.php';
                        if (file_exists($helperPath) && $oldSubagent && $newSubagent) {
                            require_once $helperPath;
                            if (function_exists('logGlobalHistory')) {
                                @logGlobalHistory('subagents', $sourceId, 'update', 'subagents', $oldSubagent, $newSubagent);
                            }
                        }
                        
                        ob_clean();
                        echo json_encode(['success' => true, 'message' => 'Contact updated successfully'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit();
                    }
                }
                break;
                
            case 'agent':
                if (isset($input['name'])) {
                    $updateFields[] = "agent_name = ?";
                    $params[] = $input['name'];
                }
                if (isset($input['email'])) {
                    $updateFields[] = "email = ?";
                    $params[] = $input['email'];
                }
                if (isset($input['phone'])) {
                    $updateFields[] = "contact_number = ?";
                    $params[] = $input['phone'];
                }
                if (isset($input['city'])) {
                    $updateFields[] = "city = ?";
                    $params[] = $input['city'];
                }
                if (isset($input['address'])) {
                    $updateFields[] = "address = ?";
                    $params[] = $input['address'];
                }
                
                if (!empty($updateFields)) {
                    // Get old data for history
                    $oldStmt = $pdo->prepare("SELECT * FROM agents WHERE id = ? OR agent_id = ?");
                    $oldStmt->execute([$sourceId, $sourceId]);
                    $oldAgent = $oldStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $params[] = $sourceId;
                    $sql = "UPDATE agents SET " . implode(', ', $updateFields) . " WHERE id = ? OR agent_id = ?";
                    $updateParams = array_merge($params, [$sourceId]);
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute($updateParams);
                    
                    if ($result) {
                        // Get updated data for history
                        $newStmt = $pdo->prepare("SELECT * FROM agents WHERE id = ? OR agent_id = ?");
                        $newStmt->execute([$sourceId, $sourceId]);
                        $newAgent = $newStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Log history
                        $helperPath = __DIR__ . '/../core/global-history-helper.php';
                        if (file_exists($helperPath) && $oldAgent && $newAgent) {
                            require_once $helperPath;
                            if (function_exists('logGlobalHistory')) {
                                $agentId = $oldAgent['id'] ?? $oldAgent['agent_id'] ?? $sourceId;
                                @logGlobalHistory('agents', $agentId, 'update', 'agents', $oldAgent, $newAgent);
                            }
                        }
                        
                        ob_clean();
                        echo json_encode(['success' => true, 'message' => 'Contact updated successfully'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit();
                    }
                }
                break;
                
            case 'worker':
                if (isset($input['name'])) {
                    $updateFields[] = "worker_name = ?";
                    $params[] = $input['name'];
                }
                if (isset($input['phone'])) {
                    $updateFields[] = "contact_number = ?";
                    $params[] = $input['phone'];
                }
                if (isset($input['city'])) {
                    $updateFields[] = "city = ?";
                    $params[] = $input['city'];
                }
                if (isset($input['country'])) {
                    $updateFields[] = "country = ?";
                    $params[] = $input['country'];
                }
                
                if (!empty($updateFields)) {
                    // Get old data for history
                    $oldStmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
                    $oldStmt->execute([$sourceId]);
                    $oldWorker = $oldStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $params[] = $sourceId;
                    $sql = "UPDATE workers SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute($params);
                    
                    if ($result) {
                        // Get updated data for history
                        $newStmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
                        $newStmt->execute([$sourceId]);
                        $newWorker = $newStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Log history
                        $helperPath = __DIR__ . '/../core/global-history-helper.php';
                        if (file_exists($helperPath) && $oldWorker && $newWorker) {
                            require_once $helperPath;
                            if (function_exists('logGlobalHistory')) {
                                @logGlobalHistory('workers', $sourceId, 'update', 'workers', $oldWorker, $newWorker);
                            }
                        }
                        
                        ob_clean();
                        echo json_encode(['success' => true, 'message' => 'Contact updated successfully'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit();
                    }
                }
                break;
        }
        
        // If we get here, the composite ID update failed
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact not found in source table'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    // Handle regular contact IDs (from contacts table)
    $updateFields = [];
    $params = [];
    
    $allowedFields = ['name', 'email', 'phone', 'city', 'country', 'contact_type', 'status', 'notes'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "{$field} = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($updateFields)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'No fields to update'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    // Get old data for history
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->execute([$contactId]);
    $oldContact = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldContact) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    $params[] = $contactId;
    $sql = "UPDATE contacts SET " . implode(', ', $updateFields) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        // Get updated contact for history
        $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
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
        echo json_encode(['success' => true, 'message' => 'Contact updated successfully'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to update contact'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function deleteContact($pdo) {
    $contactId = $_GET['id'] ?? '';
    
    if (!$contactId) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact ID required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    // Get deleted data for history (before deletion)
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->execute([$contactId]);
    $deletedContact = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deletedContact) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
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
        echo json_encode(['success' => true, 'message' => 'Contact deleted successfully'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to delete contact'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

/**
 * Resolve contact ID - converts prefixed IDs (agent_123) to contacts table numeric IDs
 * Creates contact in contacts table if it doesn't exist
 */
function resolveContactId($pdo, $contactId) {
    // If already a numeric ID and exists in contacts table, return it
    if (is_numeric($contactId)) {
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = ?");
        $stmt->execute([$contactId]);
        if ($stmt->fetch()) {
            return $contactId;
        }
    }
    
    // Handle prefixed IDs (agent_123, subagent_456, worker_789, hr_321)
    if (strpos($contactId, '_') !== false) {
        $parts = explode('_', $contactId);
        $table = $parts[0];
        $id = $parts[1];
        
        // Get contact details from source table
        $contactData = null;
        
        switch ($table) {
            case 'agent':
                try {
                    $stmt = $pdo->prepare("SELECT id, agent_name as name, email, contact_number as phone, city FROM agents WHERE id = ?");
                    $stmt->execute([$id]);
                    $contactData = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    try {
                        $stmt = $pdo->prepare("SELECT agent_id as id, full_name as name, email, phone, city FROM agents WHERE agent_id = ?");
                        $stmt->execute([$id]);
                        $contactData = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e2) {
                        return null;
                    }
                }
                break;
                
            case 'subagent':
                try {
                    $stmt = $pdo->prepare("SELECT id, subagent_name as name, email, contact_number as phone, city FROM subagents WHERE id = ?");
                    $stmt->execute([$id]);
                    $contactData = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    try {
                        $stmt = $pdo->prepare("SELECT subagent_id as id, full_name as name, email, phone, city FROM subagents WHERE subagent_id = ?");
                        $stmt->execute([$id]);
                        $contactData = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e2) {
                        return null;
                    }
                }
                break;
                
            case 'worker':
                try {
                    $stmt = $pdo->prepare("SELECT id, worker_name as name, email, contact_number as phone, city, country FROM workers WHERE id = ?");
                    $stmt->execute([$id]);
                    $contactData = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    try {
                        $stmt = $pdo->prepare("SELECT worker_id as id, full_name as name, email, phone, city, country FROM workers WHERE worker_id = ?");
                        $stmt->execute([$id]);
                        $contactData = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e2) {
                        return null;
                    }
                }
                break;
                
            case 'hr':
                try {
                    $stmt = $pdo->prepare("SELECT id, full_name as name, email, phone, city, country FROM hr_employees WHERE id = ?");
                    $stmt->execute([$id]);
                    $contactData = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    return null;
                }
                break;
                
            default:
                return null;
        }
        
        if (!$contactData || empty($contactData['name'])) {
            return null;
        }
        
        // Check if contact already exists in contacts table by name and type
        $checkStmt = $pdo->prepare("SELECT id FROM contacts WHERE name = ? AND contact_type = ? LIMIT 1");
        $checkStmt->execute([$contactData['name'], $table]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return $existing['id'];
        }
        
        // Create contact in contacts table
        $contactIdCode = strtoupper(substr($table, 0, 1)) . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $checkStmt = $pdo->prepare("SELECT id FROM contacts WHERE contact_id = ?");
        $checkStmt->execute([$contactIdCode]);
        
        while ($checkStmt->fetch()) {
            $contactIdCode = strtoupper(substr($table, 0, 1)) . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $checkStmt->execute([$contactIdCode]);
        }
        
        $sql = "INSERT INTO contacts (contact_id, name, email, phone, city, country, contact_type, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $contactIdCode,
            $contactData['name'],
            $contactData['email'] ?? '',
            $contactData['phone'] ?? '',
            $contactData['city'] ?? '',
            $contactData['country'] ?? '',
            $table,
            $_SESSION['user_id'] ?? 1
        ]);
        
        if ($result) {
            $newContactId = $pdo->lastInsertId();
            
            // Get created contact for history
            $fetchStmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
            $fetchStmt->execute([$newContactId]);
            $newContact = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath) && $newContact) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    @logGlobalHistory('contacts', $newContactId, 'create', 'contacts', null, $newContact);
                }
            }
            
            return $newContactId;
        }
    }
    
    return null;
}

function addCommunication($pdo) {
    $input = getJsonInput();
    
    if (!$input || empty($input['contact_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact ID is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    if (empty($input['subject']) && empty($input['message'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Subject or message is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    // Resolve contact ID (convert prefixed IDs to contacts table numeric ID)
    $resolvedContactId = resolveContactId($pdo, $input['contact_id']);
    
    if (!$resolvedContactId) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid contact ID or failed to resolve contact'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    // Use only the columns that actually exist in the database
    // Handle communication_date if provided (convert datetime-local format to MySQL datetime)
    $hasCustomDate = !empty($input['communication_date']);
    $columns = ['contact_id', 'communication_type', 'direction', 'priority', 'subject', 'message', 'outcome', 'next_action', 'follow_up_date', 'created_by', 'status'];
    $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
    
    if ($hasCustomDate) {
        $columns[] = 'communication_date';
        $placeholders[] = '?';
    }
    
    $sql = "INSERT INTO contact_communications (" . implode(', ', $columns) . ") 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    $params = [
        $resolvedContactId,
        $input['communication_type'] ?? 'email',
        $input['direction'] ?? 'outbound',
        $input['priority'] ?? 'medium',
        $input['subject'] ?? '',
        $input['message'] ?? '',
        $input['outcome'] ?? null,
        $input['next_action'] ?? null,
        $input['follow_up_date'] ?? null,
        $_SESSION['user_id'] ?? 1,
        $input['status'] ?? 'sent'
    ];
    
    if ($hasCustomDate) {
        // Convert datetime-local format (YYYY-MM-DDTHH:mm) to MySQL datetime format (YYYY-MM-DD HH:mm:ss)
        $datetimeStr = $input['communication_date'];
        if (strpos($datetimeStr, 'T') !== false) {
            $datetimeStr = str_replace('T', ' ', $datetimeStr) . ':00';
        }
        $params[] = $datetimeStr;
    }
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        $newId = $pdo->lastInsertId();
        
        // Get created communication for history
        $stmt = $pdo->prepare("SELECT * FROM contact_communications WHERE id = ?");
        $stmt->execute([$newId]);
        $newCommunication = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('contact_communications', $newId, 'create', 'communications', null, $newCommunication);
            }
        }
        
        // Update contact's country and city if provided
        if (!empty($input['country']) || !empty($input['city'])) {
            try {
                $updateFields = [];
                $updateParams = [];
                
                if (!empty($input['country'])) {
                    $updateFields[] = 'country = ?';
                    $updateParams[] = $input['country'];
                }
                
                if (!empty($input['city'])) {
                    $updateFields[] = 'city = ?';
                    $updateParams[] = $input['city'];
                }
                
                if (!empty($updateFields)) {
                    // Get old contact data for history
                    $oldStmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
                    $oldStmt->execute([$resolvedContactId]);
                    $oldContact = $oldStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $updateParams[] = $resolvedContactId;
                    $updateSql = "UPDATE contacts SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute($updateParams);
                    
                    // Get updated contact for history
                    $newStmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
                    $newStmt->execute([$resolvedContactId]);
                    $updatedContact = $newStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Log history
                    $helperPath = __DIR__ . '/../core/global-history-helper.php';
                    if (file_exists($helperPath) && $oldContact && $updatedContact) {
                        require_once $helperPath;
                        if (function_exists('logGlobalHistory')) {
                            @logGlobalHistory('contacts', $resolvedContactId, 'update', 'contacts', $oldContact, $updatedContact);
                        }
                    }
                }
            } catch (PDOException $e) {
                // Log error but don't fail the communication save
                // Error updating contact country/city - silently fail
            }
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Communication added successfully', 'data' => ['id' => $newId]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to add communication'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function getRecentCommunications($pdo) {
    $contactId = $_GET['contact_id'] ?? null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    // Ensure limit is between 1 and 100
    if ($limit < 1) $limit = 1;
    if ($limit > 100) $limit = 100;
    
    $offset = ($page - 1) * $limit;
    if ($offset < 0) $offset = 0;
    
    // Get filters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $direction = isset($_GET['direction']) ? $_GET['direction'] : '';
    $priority = isset($_GET['priority']) ? $_GET['priority'] : '';
    
    try {
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        
        if ($contactId) {
            $whereConditions[] = "cc.contact_id = ?";
            $params[] = $contactId;
        }
        
        if ($type) {
            $whereConditions[] = "cc.communication_type = ?";
            $params[] = $type;
        }
        
        if ($direction) {
            $whereConditions[] = "cc.direction = ?";
            $params[] = $direction;
        }
        
        if ($priority) {
            $whereConditions[] = "cc.priority = ?";
            $params[] = $priority;
        }
        
        if ($search) {
            $whereConditions[] = "(cc.subject LIKE ? OR cc.message LIKE ? OR c.name LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
        
        // Get total count first
        $countSql = "SELECT COUNT(*) as total FROM contact_communications cc LEFT JOIN contacts c ON cc.contact_id = c.id $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $total = $totalResult['total'] ?? 0;
        
        // Get statistics (total counts for all communications, not just filtered page)
        $statsSql = "SELECT 
                        COUNT(*) as total_all,
                        SUM(CASE WHEN cc.direction = 'inbound' THEN 1 ELSE 0 END) as inbound_all,
                        SUM(CASE WHEN cc.direction = 'outbound' THEN 1 ELSE 0 END) as outbound_all,
                        SUM(CASE WHEN cc.priority = 'urgent' THEN 1 ELSE 0 END) as urgent_all
                    FROM contact_communications cc";
        $statsStmt = $pdo->query($statsSql);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        $communicationColumns = [
            'cc.id', 'cc.contact_id', 'cc.communication_type', 'cc.direction',
            'cc.priority', 'cc.channel', 'cc.duration', 'cc.subject',
            'cc.message', 'cc.outcome', 'cc.next_action', 'cc.follow_up_date',
            'cc.communication_date', 'cc.created_by', 'cc.status'
        ];
        
        $selectColumns = implode(', ', $communicationColumns) . ",
                c.name as contact_name, 
                c.company as contact_company, 
                c.contact_type, 
                c.country as contact_country, 
                c.city as contact_city";

        // Get paginated communications - ensure LIMIT and OFFSET are properly applied
        $sql = "SELECT $selectColumns
                FROM contact_communications cc 
                LEFT JOIN contacts c ON cc.contact_id = c.id 
                $whereClause
                ORDER BY cc.communication_date DESC 
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $communications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure we only return the exact number requested (safety check)
        if (count($communications) > $limit) {
            $communications = array_slice($communications, 0, $limit);
        }

        ob_clean();
        echo json_encode([
            'success' => true, 
            'data' => [
                'communications' => $communications,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit),
                'stats' => $stats
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function getCommunication($pdo) {
    $commId = $_GET['id'] ?? '';
    
    if (empty($commId)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Communication ID is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        $communicationColumns = [
            'cc.id', 'cc.contact_id', 'cc.communication_type', 'cc.direction',
            'cc.priority', 'cc.channel', 'cc.duration', 'cc.subject',
            'cc.message', 'cc.outcome', 'cc.next_action', 'cc.follow_up_date',
            'cc.communication_date', 'cc.created_by', 'cc.status'
        ];
        
        $detailColumns = implode(', ', $communicationColumns) . ",
                c.name as contact_name, 
                c.company as contact_company, 
                c.contact_type, 
                c.country as contact_country, 
                c.city as contact_city";

        $stmt = $pdo->prepare("SELECT $detailColumns
                                FROM contact_communications cc 
                                LEFT JOIN contacts c ON cc.contact_id = c.id 
                                WHERE cc.id = ?");
        $stmt->execute([$commId]);
        $communication = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($communication) {
            ob_clean();
            echo json_encode(['success' => true, 'data' => $communication], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Communication not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function editCommunication($pdo) {
    $data = getJsonInput();
    
    if (!$data || !isset($data['id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Communication ID is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        // Get old data for history (before update)
        $stmt = $pdo->prepare("SELECT * FROM contact_communications WHERE id = ?");
        $stmt->execute([$data['id']]);
        $oldCommunication = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldCommunication) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Communication not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
        // Get contact_id from existing communication if not provided
        $resolvedContactId = null;
        if (empty($data['contact_id'])) {
            $resolvedContactId = $oldCommunication['contact_id'];
        } else {
            // Resolve contact ID (convert prefixed IDs to contacts table numeric ID)
            $resolvedContactId = resolveContactId($pdo, $data['contact_id']);
            if (!$resolvedContactId) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid contact ID or failed to resolve contact'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit();
            }
        }
        
        $stmt = $pdo->prepare("UPDATE contact_communications SET 
            contact_id = ?,
            communication_type = ?, 
            direction = ?, 
            priority = ?, 
            subject = ?, 
            message = ?, 
            outcome = ?, 
            next_action = ?, 
            follow_up_date = ?
            WHERE id = ?");
        
        $result = $stmt->execute([
            $resolvedContactId,
            $data['communication_type'] ?? '',
            $data['direction'] ?? 'outbound',
            $data['priority'] ?? 'medium',
            $data['subject'] ?? '',
            $data['message'] ?? '',
            $data['outcome'] ?? null,
            $data['next_action'] ?? null,
            $data['follow_up_date'] ?? null,
            $data['id']
        ]);
        
        if ($result) {
            // Get updated communication for history
            $stmt = $pdo->prepare("SELECT * FROM contact_communications WHERE id = ?");
            $stmt->execute([$data['id']]);
            $updatedCommunication = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath) && $oldCommunication && $updatedCommunication) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    @logGlobalHistory('contact_communications', $data['id'], 'update', 'communications', $oldCommunication, $updatedCommunication);
                }
            }
            
            // Update contact's country and city if provided
            if ((!empty($data['country']) || !empty($data['city'])) && $resolvedContactId) {
                try {
                    $updateFields = [];
                    $updateParams = [];
                    
                    if (!empty($data['country'])) {
                        $updateFields[] = 'country = ?';
                        $updateParams[] = $data['country'];
                    }
                    
                    if (!empty($data['city'])) {
                        $updateFields[] = 'city = ?';
                        $updateParams[] = $data['city'];
                    }
                    
                    if (!empty($updateFields)) {
                        // Get old contact data for history
                        $oldStmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
                        $oldStmt->execute([$resolvedContactId]);
                        $oldContact = $oldStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $updateParams[] = $resolvedContactId;
                        $updateSql = "UPDATE contacts SET " . implode(', ', $updateFields) . " WHERE id = ?";
                        $updateStmt = $pdo->prepare($updateSql);
                        $updateStmt->execute($updateParams);
                        
                        // Get updated contact for history
                        $newStmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
                        $newStmt->execute([$resolvedContactId]);
                        $updatedContact = $newStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Log history
                        $helperPath = __DIR__ . '/../core/global-history-helper.php';
                        if (file_exists($helperPath) && $oldContact && $updatedContact) {
                            require_once $helperPath;
                            if (function_exists('logGlobalHistory')) {
                                @logGlobalHistory('contacts', $resolvedContactId, 'update', 'contacts', $oldContact, $updatedContact);
                            }
                        }
                    }
                } catch (PDOException $e) {
                    // Log error but don't fail the communication update
                    // Error updating contact country/city - silently fail
                }
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Communication updated successfully'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to update communication'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function deleteCommunication($pdo) {
    $data = getJsonInput();
    
    if (!$data || !isset($data['id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Communication ID is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        // Get deleted data for history (before deletion)
        $stmt = $pdo->prepare("SELECT * FROM contact_communications WHERE id = ?");
        $stmt->execute([$data['id']]);
        $deletedCommunication = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("DELETE FROM contact_communications WHERE id = ?");
        $result = $stmt->execute([$data['id']]);
        
        if ($result) {
            // Log history
            if ($deletedCommunication) {
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        @logGlobalHistory('contact_communications', $data['id'], 'delete', 'communications', $deletedCommunication, null);
                    }
                }
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Communication deleted successfully'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to delete communication'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function bulkDeleteCommunications($pdo) {
    $data = getJsonInput();
    
    if (!$data || !isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Communication IDs are required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        // Get old data for history (before deletion)
        $placeholders = implode(',', array_fill(0, count($data['ids']), '?'));
        $fetchStmt = $pdo->prepare("SELECT * FROM contact_communications WHERE id IN ($placeholders)");
        $fetchStmt->execute($data['ids']);
        $deletedCommunications = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("DELETE FROM contact_communications WHERE id IN ($placeholders)");
        $result = $stmt->execute($data['ids']);
        
        $deletedCount = $stmt->rowCount();
        
        if ($result) {
            // Log history for each deleted communication
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    foreach ($deletedCommunications as $deletedComm) {
                        @logGlobalHistory('contact_communications', $deletedComm['id'], 'delete', 'communications', $deletedComm, null);
                    }
                }
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => "$deletedCount communication(s) deleted successfully", 'deleted_count' => $deletedCount], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to delete communications'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function bulkChangeStatusCommunications($pdo) {
    $data = getJsonInput();
    
    if (!$data || !isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Communication IDs are required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    if (!isset($data['status']) || empty($data['status'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Status is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        // Check if status column exists
        $columnsQuery = $pdo->query("SHOW COLUMNS FROM contact_communications LIKE 'status'");
        $hasStatusColumn = $columnsQuery->rowCount() > 0;
        
        if ($hasStatusColumn) {
            // Get old data for history (before update)
            $placeholders = implode(',', array_fill(0, count($data['ids']), '?'));
            $fetchStmt = $pdo->prepare("SELECT * FROM contact_communications WHERE id IN ($placeholders)");
            $fetchStmt->execute($data['ids']);
            $oldCommunications = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("UPDATE contact_communications SET status = ? WHERE id IN ($placeholders)");
            $params = array_merge([$data['status']], $data['ids']);
            $result = $stmt->execute($params);
            
            $updatedCount = $stmt->rowCount();
            
            if ($result) {
                // Log history for each updated communication
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        foreach ($data['ids'] as $commId) {
                            $oldComm = null;
                            foreach ($oldCommunications as $comm) {
                                if ($comm['id'] == $commId) {
                                    $oldComm = $comm;
                                    break;
                                }
                            }
                            
                            $newStmt = $pdo->prepare("SELECT * FROM contact_communications WHERE id = ?");
                            $newStmt->execute([$commId]);
                            $newComm = $newStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($oldComm && $newComm) {
                                @logGlobalHistory('contact_communications', $commId, 'update', 'communications', $oldComm, $newComm);
                            }
                        }
                    }
                }
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => "$updatedCount communication(s) status updated successfully", 'updated_count' => $updatedCount], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit();
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to update communications status'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit();
            }
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Status column does not exist in contact_communications table'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function bulkUpdateCommunications($pdo) {
    $data = getJsonInput();
    
    if (!$data || !isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Communication IDs are required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    if (!isset($data['field']) || empty($data['field'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Field is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    if (!isset($data['value'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Value is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    // Allowed fields for bulk update
    $allowedFields = ['priority', 'direction', 'communication_type', 'outcome'];
    $field = $data['field'];
    
    if (!in_array($field, $allowedFields)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid field for bulk update'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        // Get old data for history (before update)
        $placeholders = implode(',', array_fill(0, count($data['ids']), '?'));
        $fetchStmt = $pdo->prepare("SELECT * FROM contact_communications WHERE id IN ($placeholders)");
        $fetchStmt->execute($data['ids']);
        $oldCommunications = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sql = "UPDATE contact_communications SET `$field` = ? WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $params = array_merge([$data['value']], $data['ids']);
        $result = $stmt->execute($params);
        
        $updatedCount = $stmt->rowCount();
        
        if ($result) {
            // Log history for each updated communication
            $helperPath = __DIR__ . '/../core/global-history-helper.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
                if (function_exists('logGlobalHistory')) {
                    foreach ($data['ids'] as $commId) {
                        $oldComm = null;
                        foreach ($oldCommunications as $comm) {
                            if ($comm['id'] == $commId) {
                                $oldComm = $comm;
                                break;
                            }
                        }
                        
                        $newStmt = $pdo->prepare("SELECT * FROM contact_communications WHERE id = ?");
                        $newStmt->execute([$commId]);
                        $newComm = $newStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($oldComm && $newComm) {
                            @logGlobalHistory('contact_communications', $commId, 'update', 'communications', $oldComm, $newComm);
                        }
                    }
                }
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => "$updatedCount communication(s) updated successfully", 'updated_count' => $updatedCount], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to update communications'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function getContact($pdo) {
    $contactId = $_GET['id'] ?? '';
    
    if (empty($contactId)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact ID is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    try {
        // Handle different ID formats (e.g., "agent_1", "worker_5", etc.)
        if (strpos($contactId, '_') !== false) {
            $parts = explode('_', $contactId);
            $table = $parts[0];
            $id = $parts[1];
            
            switch ($table) {
                case 'agent':
                    // Try first table structure
                    try {
                        $stmt = $pdo->prepare("SELECT id, agent_name as name, email, contact_number as phone, city, 'agent' as source_type, 'agent' as contact_type, 'active' as status, created_at, updated_at FROM agents WHERE id = ?");
                    $stmt->execute([$id]);
                    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        // Try alternative table structure
                        try {
                            $stmt = $pdo->prepare("SELECT agent_id as id, full_name as name, email, phone, city, 'agent' as source_type, 'agent' as contact_type, 'active' as status, created_at, updated_at FROM agents WHERE agent_id = ?");
                            $stmt->execute([$id]);
                            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                        } catch (PDOException $e2) {
                            $contact = false;
                        }
                    }
                    
                    if ($contact) {
                        // Store the original numeric ID
                        $originalId = $contact['id'];
                        // Add missing fields with default values
                        $contact['secondary_email'] = '';
                        $contact['website'] = '';
                        $contact['secondary_phone'] = '';
                        $contact['company'] = '';
                        $contact['industry'] = '';
                        $contact['position'] = '';
                        $contact['department'] = '';
                        $contact['company_size'] = '';
                        $contact['address'] = '';
                        $contact['city'] = $contact['city'] ?? '';
                        $contact['country'] = ''; // Agents table doesn't have country
                        $contact['postal_code'] = '';
                        $contact['timezone'] = 'Asia/Riyadh';
                        $contact['lead_source'] = '';
                        $contact['priority'] = 'medium';
                        $contact['birth_date'] = '';
                        $contact['notes'] = '';
                        // Set ID to prefixed format to match getAllContactsFromAllSources
                        $contact['id'] = 'agent_' . $originalId;
                    }
                    break;
                case 'subagent':
                    // Try first table structure
                    try {
                        $stmt = $pdo->prepare("SELECT id, subagent_name as name, email, contact_number as phone, city, 'subagent' as source_type, 'subagent' as contact_type, 'active' as status, created_at, updated_at FROM subagents WHERE id = ?");
                    $stmt->execute([$id]);
                    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        // Try alternative table structure
                        try {
                            $stmt = $pdo->prepare("SELECT subagent_id as id, full_name as name, email, phone, city, 'subagent' as source_type, 'subagent' as contact_type, 'active' as status, created_at, updated_at FROM subagents WHERE subagent_id = ?");
                            $stmt->execute([$id]);
                            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                        } catch (PDOException $e2) {
                            $contact = false;
                        }
                    }
                    
                    if ($contact) {
                        // Store the original numeric ID
                        $originalId = $contact['id'];
                        // Add missing fields with default values
                        $contact['secondary_email'] = '';
                        $contact['website'] = '';
                        $contact['secondary_phone'] = '';
                        $contact['company'] = '';
                        $contact['industry'] = '';
                        $contact['position'] = '';
                        $contact['department'] = '';
                        $contact['company_size'] = '';
                        $contact['address'] = '';
                        $contact['city'] = $contact['city'] ?? '';
                        $contact['country'] = ''; // SubAgents table doesn't have country
                        $contact['postal_code'] = '';
                        $contact['timezone'] = 'Asia/Riyadh';
                        $contact['lead_source'] = '';
                        $contact['priority'] = 'medium';
                        $contact['birth_date'] = '';
                        $contact['notes'] = '';
                        // Set ID to prefixed format to match getAllContactsFromAllSources
                        $contact['id'] = 'subagent_' . $originalId;
                    }
                    break;
                case 'worker':
                    $stmt = $pdo->prepare("SELECT id, worker_name as name, contact_number as phone, email, city, country, 'worker' as source_type, 'worker' as contact_type, 'active' as status, created_at, updated_at FROM workers WHERE id = ?");
                    $stmt->execute([$id]);
                    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($contact) {
                        // Store the original numeric ID
                        $originalId = $contact['id'];
                        // Add missing fields with default values
                        $contact['email'] = $contact['email'] ?? '';
                        $contact['city'] = $contact['city'] ?? '';
                        $contact['country'] = $contact['country'] ?? 'Saudi Arabia';
                        $contact['secondary_email'] = '';
                        $contact['website'] = '';
                        $contact['secondary_phone'] = '';
                        $contact['company'] = '';
                        $contact['industry'] = '';
                        $contact['position'] = '';
                        $contact['department'] = '';
                        $contact['company_size'] = '';
                        $contact['address'] = '';
                        $contact['postal_code'] = '';
                        $contact['timezone'] = 'Asia/Riyadh';
                        $contact['lead_source'] = '';
                        $contact['priority'] = 'medium';
                        $contact['birth_date'] = '';
                        $contact['notes'] = '';
                        // Set ID to prefixed format to match getAllContactsFromAllSources
                        $contact['id'] = 'worker_' . $originalId;
                    }
                    break;
                case 'hr':
                    // Try to get HR employee data from various tables
                    try {
                        $stmt = $pdo->prepare("SELECT id, employee_name as name, email, phone, city, country, 'hr' as source_type, 'hr' as contact_type, 'active' as status, created_at, updated_at FROM hr_employees WHERE id = ?");
                        $stmt->execute([$id]);
                        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        // Try hr_documents table
                        try {
                        $stmt = $pdo->prepare("SELECT employee_id as id, employee_name as name, 'hr' as source_type, 'hr' as contact_type, 'active' as status FROM hr_documents WHERE employee_id = ? LIMIT 1");
                        $stmt->execute([$id]);
                        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                        } catch (PDOException $e2) {
                            // Try employees table
                            try {
                                $stmt = $pdo->prepare("SELECT id, name, email, phone, city, 'hr' as source_type, 'hr' as contact_type, 'active' as status, created_at, updated_at FROM employees WHERE id = ?");
                                $stmt->execute([$id]);
                                $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                            } catch (PDOException $e3) {
                                $contact = null;
                            }
                        }
                    }
                    if ($contact) {
                        // Store the original numeric ID
                        $originalId = $contact['id'];
                        // Add missing fields with default values
                        $contact['email'] = $contact['email'] ?? '';
                        $contact['phone'] = $contact['phone'] ?? '';
                        $contact['city'] = $contact['city'] ?? '';
                        $contact['country'] = $contact['country'] ?? '';
                        $contact['secondary_email'] = '';
                        $contact['website'] = '';
                        $contact['secondary_phone'] = '';
                        $contact['company'] = 'HR Department';
                        $contact['industry'] = '';
                        $contact['position'] = '';
                        $contact['department'] = '';
                        $contact['company_size'] = '';
                        $contact['address'] = '';
                        $contact['postal_code'] = '';
                        $contact['timezone'] = 'Asia/Riyadh';
                        $contact['lead_source'] = '';
                        $contact['priority'] = 'medium';
                        $contact['birth_date'] = '';
                        $contact['notes'] = '';
                        $contact['created_at'] = $contact['created_at'] ?? date('Y-m-d H:i:s');
                        $contact['updated_at'] = $contact['updated_at'] ?? date('Y-m-d H:i:s');
                        // Set ID to prefixed format to match getAllContactsFromAllSources
                        $contact['id'] = 'hr_' . $originalId;
                    }
                    break;
                default:
                    $contact = false;
            }
            
            if ($contact) {
                ob_clean();
                echo json_encode(['success' => true, 'data' => $contact], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit();
            }
        }
        
        // Try direct ID lookup in contacts table
        $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->execute([$contactId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($contact) {
            // Add source_type for regular contacts to match getAllContactsFromAllSources format
            $contact['source_type'] = 'Contact';
            ob_clean();
            echo json_encode(['success' => true, 'data' => $contact], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
        // Contact not found
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Contact not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

// Send message function
function sendMessage($pdo) {
    try {
        $contactId = $_POST['contact_id'] ?? '';
        $contactName = $_POST['contact_name'] ?? '';
        $contactEmail = $_POST['contact_email'] ?? '';
        $messageContent = $_POST['message_content'] ?? '';
        $messageType = $_POST['message_type'] ?? 'email';
        $subject = $_POST['subject'] ?? 'Message from Contact Management System';
        
        if (empty($contactId) || empty($messageContent)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Contact ID and message content are required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
        // Insert message into contact_communications table
        $stmt = $pdo->prepare("
            INSERT INTO contact_communications (
                contact_id, 
                communication_type, 
                direction, 
                priority, 
                subject, 
                message, 
                outcome, 
                status, 
                communication_date, 
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $result = $stmt->execute([
            $contactId,
            $messageType,
            'outbound',
            'medium',
            $subject,
            $messageContent,
            'neutral',
            'sent',
            1 // Assuming user ID 1 for now
        ]);
        
        $emailSent = null;
        if ($result) {
            // Log the message sending attempt
            
            // Attempt to send actual email if requested and email exists
            if ($messageType === 'email' && !empty($contactEmail)) {
                $emailSent = sendContactEmail($contactEmail, $subject, $messageContent);
            }
            
            ob_clean();
            echo json_encode([
                'success' => true, 
                'message' => 'Message sent successfully',
                'data' => [
                    'contact_id' => $contactId,
                    'contact_name' => $contactName,
                    'contact_email' => $contactEmail,
                    'message_type' => $messageType,
                    'subject' => $subject,
                    'email_sent' => $emailSent
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to send message'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

// Get sent messages for template dropdown
function getSentMessages($pdo) {
    try {
        $sql = "SELECT DISTINCT message, subject, communication_date 
                FROM contact_communications 
                WHERE status = 'sent' 
                AND message IS NOT NULL 
                AND message != '' 
                ORDER BY communication_date DESC 
                LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ob_clean();
        echo json_encode(['success' => true, 'data' => $messages], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

/**
 * Send email using PHPMailer if available, otherwise fall back to PHP mail()
 */
function sendContactEmail($to, $subject, $message) {
    $fromEmail = defined('SMTP_FROM_EMAIL') ? constant('SMTP_FROM_EMAIL') : 'noreply@ratibprogram.com';
    $fromName = defined('SMTP_FROM_NAME') ? constant('SMTP_FROM_NAME') : 'Ratib Program';
    $smtpHost = defined('SMTP_HOST') ? constant('SMTP_HOST') : 'smtp.gmail.com';
    $smtpPort = defined('SMTP_PORT') ? constant('SMTP_PORT') : 587;
    $smtpUser = defined('SMTP_USER') ? constant('SMTP_USER') : '';
    $smtpPass = defined('SMTP_PASS') ? constant('SMTP_PASS') : '';
    $smtpSecure = defined('SMTP_SECURE') ? constant('SMTP_SECURE') : 'tls';
    
    if (defined('ENABLE_REAL_EMAIL') && constant('ENABLE_REAL_EMAIL') && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = $smtpSecure;
            $mail->Port = $smtpPort;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = nl2br($message);
            $mail->AltBody = strip_tags($message);
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email send failed: " . $mail->ErrorInfo);
        }
    }
    
    // Fallback to PHP mail()
    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    $result = @mail($to, $subject, nl2br($message), $headers);
    if ($result) {
        // Email sent successfully
    } else {
        error_log("Email send failed via mail() to {$to}");
    }
    return $result;
}

function exportCommunications($pdo) {
    ob_clean();
    
    try {
        // Check if table exists first
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'contact_communications'");
        if ($tableCheck->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Communications table does not exist'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }
        
        // Get filters from query parameters
        $contactId = $_GET['contact_id'] ?? '';
        $communicationType = $_GET['communication_type'] ?? '';
        $direction = $_GET['direction'] ?? '';
        $priority = $_GET['priority'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        
        // Build WHERE clause
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($contactId)) {
            $whereConditions[] = "cc.contact_id = ?";
            $params[] = $contactId;
        }
        if (!empty($communicationType)) {
            $whereConditions[] = "cc.communication_type = ?";
            $params[] = $communicationType;
        }
        if (!empty($direction)) {
            $whereConditions[] = "cc.direction = ?";
            $params[] = $direction;
        }
        if (!empty($priority)) {
            $whereConditions[] = "cc.priority = ?";
            $params[] = $priority;
        }
        if (!empty($status)) {
            $whereConditions[] = "cc.status = ?";
            $params[] = $status;
        }
        if (!empty($search)) {
            $whereConditions[] = "(cc.subject LIKE ? OR cc.message LIKE ? OR c.name LIKE ? OR c.company LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Query communications with contact details
        $sql = "SELECT cc.*, c.name as contact_name, c.email as contact_email, c.phone as contact_phone, c.company as contact_company, c.contact_type, u.username as created_by_name FROM contact_communications cc LEFT JOIN contacts c ON cc.contact_id = c.id LEFT JOIN users u ON cc.created_by = u.user_id WHERE $whereClause ORDER BY cc.communication_date DESC, cc.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $communications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Clean output buffer before sending CSV headers (in case of any output from queries)
        ob_clean();
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="communications_export_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Output BOM for UTF-8 Excel compatibility
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID', 'Contact ID', 'Contact Name', 'Contact Email', 'Contact Phone', 'Company', 'Contact Type',
            'Communication Type', 'Direction', 'Priority', 'Channel', 'Duration', 
            'Subject', 'Message', 'Outcome', 'Next Action', 'Follow-up Date', 
            'Communication Date', 'Status', 'Created By', 'Created At', 'Updated At'
        ]);
        
        // CSV data
        foreach ($communications as $comm) {
            fputcsv($output, [
                $comm['id'] ?? '',
                $comm['contact_id'] ?? '',
                $comm['contact_name'] ?? '',
                $comm['contact_email'] ?? '',
                $comm['contact_phone'] ?? '',
                $comm['contact_company'] ?? '',
                $comm['contact_type'] ?? '',
                $comm['communication_type'] ?? '',
                $comm['direction'] ?? '',
                $comm['priority'] ?? '',
                $comm['channel'] ?? '',
                $comm['duration'] ?? '',
                $comm['subject'] ?? '',
                strip_tags($comm['message'] ?? ''),
                $comm['outcome'] ?? '',
                $comm['next_action'] ?? '',
                $comm['follow_up_date'] ?? '',
                $comm['communication_date'] ?? '',
                $comm['status'] ?? '',
                $comm['created_by_name'] ?? '',
                $comm['created_at'] ?? '',
                $comm['updated_at'] ?? ''
            ]);
        }
        
        fclose($output);
        exit();
        
    } catch (PDOException $e) {
        ob_clean();
        error_log('exportCommunications error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (Exception $e) {
        ob_clean();
        error_log('exportCommunications error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Export error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}
