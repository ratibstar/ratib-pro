<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/entry-approval.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/entry-approval.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

// Include entity linking helper if available
if (file_exists(__DIR__ . '/unified-entity-linking.php')) {
    require_once __DIR__ . '/unified-entity-linking.php';
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permissions - use journal-entries permission as fallback
$method = $_SERVER['REQUEST_METHOD'];
$MODULE_PERMISSIONS = require __DIR__ . '/../core/module-permissions.php';
if (isset($MODULE_PERMISSIONS['entry_approval'])) {
    if ($method === 'GET') {
        enforceApiPermission('entry_approval', 'view');
    } elseif ($method === 'POST') {
        enforceApiPermission('entry_approval', 'create');
    } elseif ($method === 'PUT') {
        enforceApiPermission('entry_approval', 'update');
    }
} else {
    // Fallback to journal-entries permission if entry_approval doesn't exist
    if ($method === 'GET') {
        enforceApiPermission('journal-entries', 'view');
    } elseif ($method === 'POST') {
        enforceApiPermission('journal-entries', 'update');
    } elseif ($method === 'PUT') {
        enforceApiPermission('journal-entries', 'update');
    }
}

try {
    // Check if entry_approval table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'entry_approval'");
    if (!$tableCheck) {
        throw new Exception('Failed to check table existence: ' . $conn->error);
    }
    
    $tableExists = $tableCheck->num_rows > 0;
    $tableCheck->free();
    
    if (!$tableExists) {
        $createTable = $conn->query("
            CREATE TABLE IF NOT EXISTS entry_approval (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entry_number VARCHAR(100) NOT NULL,
                entry_date DATE NOT NULL,
                description TEXT,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(10) DEFAULT 'SAR',
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                journal_entry_id INT NULL,
                cost_center_id INT NULL,
                bank_guarantee_id INT NULL,
                created_by INT,
                approved_by INT NULL,
                approved_at TIMESTAMP NULL,
                rejection_reason TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_entry_number (entry_number),
                INDEX idx_status (status),
                INDEX idx_entry_date (entry_date),
                INDEX idx_journal_entry (journal_entry_id),
                INDEX idx_cost_center (cost_center_id),
                INDEX idx_bank_guarantee (bank_guarantee_id),
                INDEX idx_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        if (!$createTable) {
            throw new Exception('Failed to create entry_approval table: ' . $conn->error);
        }
    } else {
        // Ensure linking columns exist in existing table
        $columnCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'journal_entry_id'");
        if ($columnCheck->num_rows === 0) {
            $columnCheck->free();
            try {
                $conn->query("ALTER TABLE entry_approval ADD COLUMN journal_entry_id INT NULL AFTER status");
                $conn->query("ALTER TABLE entry_approval ADD INDEX idx_journal_entry (journal_entry_id)");
            } catch (Exception $e) {
                // Index might already exist, continue
            }
        } else {
            $columnCheck->free();
        }
        $columnCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'cost_center_id'");
        if ($columnCheck->num_rows === 0) {
            $columnCheck->free();
            try {
                $conn->query("ALTER TABLE entry_approval ADD COLUMN cost_center_id INT NULL AFTER journal_entry_id");
                $conn->query("ALTER TABLE entry_approval ADD INDEX idx_cost_center (cost_center_id)");
            } catch (Exception $e) {
                // Index might already exist, continue
            }
        } else {
            $columnCheck->free();
        }
        $columnCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'bank_guarantee_id'");
        if ($columnCheck->num_rows === 0) {
            $columnCheck->free();
            try {
                $conn->query("ALTER TABLE entry_approval ADD COLUMN bank_guarantee_id INT NULL AFTER cost_center_id");
                $conn->query("ALTER TABLE entry_approval ADD INDEX idx_bank_guarantee (bank_guarantee_id)");
            } catch (Exception $e) {
                // Index might already exist, continue
            }
        } else {
            $columnCheck->free();
        }
        // Ensure entity linking columns exist
        $columnCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_type'");
        if ($columnCheck->num_rows === 0) {
            $columnCheck->free();
            try {
                $conn->query("ALTER TABLE entry_approval ADD COLUMN entity_type VARCHAR(50) NULL AFTER bank_guarantee_id");
                $conn->query("ALTER TABLE entry_approval ADD INDEX idx_entity_type (entity_type)");
            } catch (Exception $e) {
                // Continue
            }
        } else {
            $columnCheck->free();
        }
        $columnCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_id'");
        if ($columnCheck->num_rows === 0) {
            $columnCheck->free();
            try {
                $conn->query("ALTER TABLE entry_approval ADD COLUMN entity_id INT NULL AFTER entity_type");
                $conn->query("ALTER TABLE entry_approval ADD INDEX idx_entity_id (entity_id)");
                $conn->query("ALTER TABLE entry_approval ADD INDEX idx_entity (entity_type, entity_id)");
            } catch (Exception $e) {
                // Continue
            }
        } else {
            $columnCheck->free();
        }
        // Ensure debit_amount and credit_amount columns exist
        $columnCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'debit_amount'");
        if ($columnCheck->num_rows === 0) {
            $columnCheck->free();
            try {
                $conn->query("ALTER TABLE entry_approval ADD COLUMN debit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER amount");
            } catch (Exception $e) {
                // Continue
            }
        } else {
            $columnCheck->free();
        }
        $columnCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'credit_amount'");
        if ($columnCheck->num_rows === 0) {
            $columnCheck->free();
            try {
                $conn->query("ALTER TABLE entry_approval ADD COLUMN credit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER debit_amount");
            } catch (Exception $e) {
                // Continue
            }
        } else {
            $columnCheck->free();
        }
    }

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        
        if ($id) {
            // Get single entry
            // Check if users table exists and has user_id column
            $usersTableCheck = $conn->query("SHOW TABLES LIKE 'users'");
            $usersTableExists = $usersTableCheck && $usersTableCheck->num_rows > 0;
            $usersTableHasUserId = false;
            
            if ($usersTableExists) {
                $columnsCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'user_id'");
                $usersTableHasUserId = $columnsCheck && $columnsCheck->num_rows > 0;
                if ($columnsCheck) {
                    $columnsCheck->free();
                }
            }
            if ($usersTableCheck) {
                $usersTableCheck->free();
            }
            
            // Check if journal_entries table exists for debit/credit
            $journalEntriesTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
            $journalEntriesTableExists = $journalEntriesTableCheck && $journalEntriesTableCheck->num_rows > 0;
            if ($journalEntriesTableCheck) {
                $journalEntriesTableCheck->free();
            }
            
            // Check if debit_amount and credit_amount columns exist
            $debitCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'debit_amount'");
            $hasDebitAmount = $debitCheck && $debitCheck->num_rows > 0;
            if ($debitCheck) {
                $debitCheck->free();
            }
            $creditCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'credit_amount'");
            $hasCreditAmount = $creditCheck && $creditCheck->num_rows > 0;
            if ($creditCheck) {
                $creditCheck->free();
            }
            
            if ($usersTableExists && $usersTableHasUserId) {
                if ($journalEntriesTableExists) {
                    if ($hasDebitAmount && $hasCreditAmount) {
                        $stmt = $conn->prepare("SELECT ea.*, u.username as created_by_name, u2.username as approved_by_name,
                                               COALESCE(NULLIF(ea.debit_amount, 0), je.total_debit, 0) as total_debit,
                                               COALESCE(NULLIF(ea.credit_amount, 0), je.total_credit, 0) as total_credit,
                                               COALESCE(NULLIF(ea.debit_amount, 0), je.total_debit, 0) as debit_amount,
                                               COALESCE(NULLIF(ea.credit_amount, 0), je.total_credit, 0) as credit_amount
                                            FROM entry_approval ea
                                            LEFT JOIN users u ON ea.created_by = u.user_id
                                            LEFT JOIN users u2 ON ea.approved_by = u2.user_id
                                            LEFT JOIN journal_entries je ON ea.journal_entry_id = je.id
                                            WHERE ea.id = ?");
                    } else {
                        $stmt = $conn->prepare("SELECT ea.*, u.username as created_by_name, u2.username as approved_by_name,
                                               COALESCE(je.total_debit, 0) as total_debit,
                                               COALESCE(je.total_credit, 0) as total_credit
                                            FROM entry_approval ea
                                            LEFT JOIN users u ON ea.created_by = u.user_id
                                            LEFT JOIN users u2 ON ea.approved_by = u2.user_id
                                            LEFT JOIN journal_entries je ON ea.journal_entry_id = je.id
                                            WHERE ea.id = ?");
                    }
                } else {
                    if ($hasDebitAmount && $hasCreditAmount) {
                        $stmt = $conn->prepare("SELECT ea.*, u.username as created_by_name, u2.username as approved_by_name,
                                               COALESCE(ea.debit_amount, 0) as total_debit,
                                               COALESCE(ea.credit_amount, 0) as total_credit,
                                               COALESCE(ea.debit_amount, 0) as debit_amount,
                                               COALESCE(ea.credit_amount, 0) as credit_amount
                                            FROM entry_approval ea
                                            LEFT JOIN users u ON ea.created_by = u.user_id
                                            LEFT JOIN users u2 ON ea.approved_by = u2.user_id
                                            WHERE ea.id = ?");
                    } else {
                        $stmt = $conn->prepare("SELECT ea.*, u.username as created_by_name, u2.username as approved_by_name,
                                               0 as total_debit, 0 as total_credit
                                            FROM entry_approval ea
                                            LEFT JOIN users u ON ea.created_by = u.user_id
                                            LEFT JOIN users u2 ON ea.approved_by = u2.user_id
                                            WHERE ea.id = ?");
                    }
                }
            } else {
                if ($journalEntriesTableExists) {
                    if ($hasDebitAmount && $hasCreditAmount) {
                        $stmt = $conn->prepare("SELECT ea.*, NULL as created_by_name, NULL as approved_by_name,
                                               COALESCE(NULLIF(ea.debit_amount, 0), je.total_debit, 0) as total_debit,
                                               COALESCE(NULLIF(ea.credit_amount, 0), je.total_credit, 0) as total_credit,
                                               COALESCE(NULLIF(ea.debit_amount, 0), je.total_debit, 0) as debit_amount,
                                               COALESCE(NULLIF(ea.credit_amount, 0), je.total_credit, 0) as credit_amount
                                            FROM entry_approval ea
                                            LEFT JOIN journal_entries je ON ea.journal_entry_id = je.id
                                            WHERE ea.id = ?");
                    } else {
                        $stmt = $conn->prepare("SELECT ea.*, NULL as created_by_name, NULL as approved_by_name,
                                               COALESCE(je.total_debit, 0) as total_debit,
                                               COALESCE(je.total_credit, 0) as total_credit
                                            FROM entry_approval ea
                                            LEFT JOIN journal_entries je ON ea.journal_entry_id = je.id
                                            WHERE ea.id = ?");
                    }
                } else {
                    if ($hasDebitAmount && $hasCreditAmount) {
                        $stmt = $conn->prepare("SELECT ea.*, NULL as created_by_name, NULL as approved_by_name,
                                               COALESCE(ea.debit_amount, 0) as total_debit,
                                               COALESCE(ea.credit_amount, 0) as total_credit,
                                               COALESCE(ea.debit_amount, 0) as debit_amount,
                                               COALESCE(ea.credit_amount, 0) as credit_amount
                                            FROM entry_approval ea
                                            WHERE ea.id = ?");
                    } else {
                        $stmt = $conn->prepare("SELECT ea.*, NULL as created_by_name, NULL as approved_by_name,
                                               0 as total_debit, 0 as total_credit
                                            FROM entry_approval ea
                                            WHERE ea.id = ?");
                    }
                }
            }
            
            if (!$stmt) {
                throw new Exception('Failed to prepare query: ' . $conn->error);
            }
            
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $result->free();
                $stmt->close();

                // Try to fetch debit/credit account display (to match General Ledger columns)
                $debitAccountName = null;
                $creditAccountName = null;
                $linkedJournalEntryId = isset($row['journal_entry_id']) && $row['journal_entry_id'] ? intval($row['journal_entry_id']) : null;
                if ($linkedJournalEntryId) {
                    $jelTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
                    $hasJelTable = $jelTableCheck && $jelTableCheck->num_rows > 0;
                    if ($jelTableCheck) $jelTableCheck->free();

                    $faTableCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
                    $hasFaTable = $faTableCheck && $faTableCheck->num_rows > 0;
                    if ($faTableCheck) $faTableCheck->free();

                    if ($hasJelTable && $hasFaTable) {
                        $debitStmt = $conn->prepare("
                            SELECT
                                CASE
                                    WHEN COALESCE(fa.account_code, '') = '' THEN COALESCE(fa.account_name, '')
                                    ELSE CONCAT(fa.account_code, ' - ', COALESCE(fa.account_name, ''))
                                END AS account_display
                            FROM journal_entry_lines jel
                            LEFT JOIN financial_accounts fa ON jel.account_id = fa.id
                            WHERE jel.journal_entry_id = ?
                              AND COALESCE(jel.debit_amount, 0) > 0
                            ORDER BY jel.id ASC
                            LIMIT 1
                        ");
                        if ($debitStmt) {
                            $debitStmt->bind_param('i', $linkedJournalEntryId);
                            $debitStmt->execute();
                            $debitRes = $debitStmt->get_result();
                            if ($dr = $debitRes->fetch_assoc()) {
                                $debitAccountName = $dr['account_display'] ?? null;
                            }
                            $debitRes->free();
                            $debitStmt->close();
                        }

                        $creditStmt = $conn->prepare("
                            SELECT
                                CASE
                                    WHEN COALESCE(fa.account_code, '') = '' THEN COALESCE(fa.account_name, '')
                                    ELSE CONCAT(fa.account_code, ' - ', COALESCE(fa.account_name, ''))
                                END AS account_display
                            FROM journal_entry_lines jel
                            LEFT JOIN financial_accounts fa ON jel.account_id = fa.id
                            WHERE jel.journal_entry_id = ?
                              AND COALESCE(jel.credit_amount, 0) > 0
                            ORDER BY jel.id ASC
                            LIMIT 1
                        ");
                        if ($creditStmt) {
                            $creditStmt->bind_param('i', $linkedJournalEntryId);
                            $creditStmt->execute();
                            $creditRes = $creditStmt->get_result();
                            if ($cr = $creditRes->fetch_assoc()) {
                                $creditAccountName = $cr['account_display'] ?? null;
                            }
                            $creditRes->free();
                            $creditStmt->close();
                        }
                    }
                }

                echo json_encode([
                    'success' => true,
                    'entry' => [
                        'id' => intval($row['id']),
                        'entry_number' => $row['entry_number'],
                        'entry_date' => $row['entry_date'],
                        'description' => $row['description'],
                        'amount' => floatval($row['amount']),
                        'currency' => $row['currency'],
                        'debit_account_name' => $debitAccountName,
                        'credit_account_name' => $creditAccountName,
                        'debit_amount' => isset($row['debit_amount']) ? floatval($row['debit_amount']) : (isset($row['total_debit']) ? floatval($row['total_debit']) : 0),
                        'credit_amount' => isset($row['credit_amount']) ? floatval($row['credit_amount']) : (isset($row['total_credit']) ? floatval($row['total_credit']) : 0),
                        'total_debit' => isset($row['total_debit']) ? floatval($row['total_debit']) : (isset($row['debit_amount']) ? floatval($row['debit_amount']) : 0),
                        'total_credit' => isset($row['total_credit']) ? floatval($row['total_credit']) : (isset($row['credit_amount']) ? floatval($row['credit_amount']) : 0),
                        'status' => $row['status'],
                        'journal_entry_id' => isset($row['journal_entry_id']) && $row['journal_entry_id'] ? intval($row['journal_entry_id']) : null,
                        'cost_center_id' => isset($row['cost_center_id']) && $row['cost_center_id'] ? intval($row['cost_center_id']) : null,
                        'bank_guarantee_id' => isset($row['bank_guarantee_id']) && $row['bank_guarantee_id'] ? intval($row['bank_guarantee_id']) : null,
                        'entity_type' => isset($row['entity_type']) ? $row['entity_type'] : null,
                        'entity_id' => isset($row['entity_id']) && $row['entity_id'] ? intval($row['entity_id']) : null,
                        'entity_name' => null, // Will be populated below
                        'created_by' => intval($row['created_by']),
                        'created_by_name' => $row['created_by_name'],
                        'approved_by' => $row['approved_by'] ? intval($row['approved_by']) : null,
                        'approved_by_name' => $row['approved_by_name'],
                        'approved_at' => $row['approved_at'],
                        'rejection_reason' => $row['rejection_reason'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ]
                ]);
            } else {
                $result->free();
                $stmt->close();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Entry not found']);
            }
        } else {
            // Get all entries
            // Check if users table exists and has user_id column
            $usersTableCheck = $conn->query("SHOW TABLES LIKE 'users'");
            $usersTableExists = $usersTableCheck && $usersTableCheck->num_rows > 0;
            $usersTableHasUserId = false;
            
            if ($usersTableExists) {
                $columnsCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'user_id'");
                $usersTableHasUserId = $columnsCheck && $columnsCheck->num_rows > 0;
                if ($columnsCheck) {
                    $columnsCheck->free();
                }
            }
            if ($usersTableCheck) {
                $usersTableCheck->free();
            }
            
            // Check if journal_entries table exists for debit/credit
            $journalEntriesTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
            $journalEntriesTableExists = $journalEntriesTableCheck && $journalEntriesTableCheck->num_rows > 0;
            if ($journalEntriesTableCheck) {
                $journalEntriesTableCheck->free();
            }
            $debitCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'debit_amount'");
            $hasDebitAmount = $debitCheck && $debitCheck->num_rows > 0;
            if ($debitCheck) {
                $debitCheck->free();
            }
            $creditCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'credit_amount'");
            $hasCreditAmount = $creditCheck && $creditCheck->num_rows > 0;
            if ($creditCheck) {
                $creditCheck->free();
            }
            
            if ($usersTableExists && $usersTableHasUserId) {
                if ($journalEntriesTableExists) {
                    if ($hasDebitAmount && $hasCreditAmount) {
                        $query = "SELECT ea.*, u.username as created_by_name, u2.username as approved_by_name,
                                 COALESCE(NULLIF(ea.debit_amount, 0), je.total_debit, 0) as total_debit,
                                 COALESCE(NULLIF(ea.credit_amount, 0), je.total_credit, 0) as total_credit,
                                 COALESCE(NULLIF(ea.debit_amount, 0), je.total_debit, 0) as debit_amount,
                                 COALESCE(NULLIF(ea.credit_amount, 0), je.total_credit, 0) as credit_amount
                              FROM entry_approval ea
                              LEFT JOIN users u ON ea.created_by = u.user_id
                              LEFT JOIN users u2 ON ea.approved_by = u2.user_id
                              LEFT JOIN journal_entries je ON ea.journal_entry_id = je.id
                              WHERE 1=1";
                    } else {
                        $query = "SELECT ea.*, u.username as created_by_name, u2.username as approved_by_name,
                                 COALESCE(je.total_debit, 0) as total_debit,
                                 COALESCE(je.total_credit, 0) as total_credit
                              FROM entry_approval ea
                              LEFT JOIN users u ON ea.created_by = u.user_id
                              LEFT JOIN users u2 ON ea.approved_by = u2.user_id
                              LEFT JOIN journal_entries je ON ea.journal_entry_id = je.id
                              WHERE 1=1";
                    }
                } else {
                    if ($hasDebitAmount && $hasCreditAmount) {
                        $query = "SELECT ea.*, u.username as created_by_name, u2.username as approved_by_name,
                                 COALESCE(ea.debit_amount, 0) as total_debit,
                                 COALESCE(ea.credit_amount, 0) as total_credit,
                                 COALESCE(ea.debit_amount, 0) as debit_amount,
                                 COALESCE(ea.credit_amount, 0) as credit_amount
                              FROM entry_approval ea
                              LEFT JOIN users u ON ea.created_by = u.user_id
                              LEFT JOIN users u2 ON ea.approved_by = u2.user_id
                              WHERE 1=1";
                    } else {
                        $query = "SELECT ea.*, u.username as created_by_name, u2.username as approved_by_name,
                                 0 as total_debit, 0 as total_credit
                              FROM entry_approval ea
                              LEFT JOIN users u ON ea.created_by = u.user_id
                              LEFT JOIN users u2 ON ea.approved_by = u2.user_id
                              WHERE 1=1";
                    }
                }
            } else {
                if ($journalEntriesTableExists) {
                    if ($hasDebitAmount && $hasCreditAmount) {
                        $query = "SELECT ea.*, NULL as created_by_name, NULL as approved_by_name,
                                 COALESCE(NULLIF(ea.debit_amount, 0), je.total_debit, 0) as total_debit,
                                 COALESCE(NULLIF(ea.credit_amount, 0), je.total_credit, 0) as total_credit,
                                 COALESCE(NULLIF(ea.debit_amount, 0), je.total_debit, 0) as debit_amount,
                                 COALESCE(NULLIF(ea.credit_amount, 0), je.total_credit, 0) as credit_amount
                              FROM entry_approval ea
                              LEFT JOIN journal_entries je ON ea.journal_entry_id = je.id
                              WHERE 1=1";
                    } else {
                        $query = "SELECT ea.*, NULL as created_by_name, NULL as approved_by_name,
                                 COALESCE(je.total_debit, 0) as total_debit,
                                 COALESCE(je.total_credit, 0) as total_credit
                              FROM entry_approval ea
                              LEFT JOIN journal_entries je ON ea.journal_entry_id = je.id
                              WHERE 1=1";
                    }
                } else {
                    if ($hasDebitAmount && $hasCreditAmount) {
                        $query = "SELECT ea.*, NULL as created_by_name, NULL as approved_by_name,
                                 COALESCE(ea.debit_amount, 0) as total_debit,
                                 COALESCE(ea.credit_amount, 0) as total_credit,
                                 COALESCE(ea.debit_amount, 0) as debit_amount,
                                 COALESCE(ea.credit_amount, 0) as credit_amount
                              FROM entry_approval ea
                              WHERE 1=1";
                    } else {
                        $query = "SELECT ea.*, NULL as created_by_name, NULL as approved_by_name,
                                 0 as total_debit, 0 as total_credit
                              FROM entry_approval ea
                              WHERE 1=1";
                    }
                }
            }
            
            $params = [];
            $types = '';
            
            // Show entries based on status filter
            if ($status && $status !== 'all') {
                $query .= " AND ea.status = ?";
                $params[] = $status;
                $types .= 's';
            } else if (!$status || $status === 'all') {
                // By default, exclude approved entries from Entry Approval table
                // Approved entries are already processed and don't need approval workflow
                $query .= " AND ea.status != 'approved'";
            }
            // If status='all', show all non-approved entries (pending and rejected only)
            
            $query .= " ORDER BY ea.entry_date DESC, ea.created_at DESC, ea.entry_number";
            
            // Check if entity linking columns exist
            $entityTypeCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_type'");
            $hasEntityType = $entityTypeCheck && $entityTypeCheck->num_rows > 0;
            if ($entityTypeCheck) {
                $entityTypeCheck->free();
            }
            $entityIdCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_id'");
            $hasEntityId = $entityIdCheck && $entityIdCheck->num_rows > 0;
            if ($entityIdCheck) {
                $entityIdCheck->free();
            }
            
            // Execute query - use direct query if no params, prepared statement if params exist
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    throw new Exception('Failed to prepare query: ' . $conn->error);
                }
            } else {
                $result = $conn->query($query);
                if (!$result) {
                    throw new Exception('Query failed: ' . $conn->error);
                }
            }
            
            $entries = [];

            // Prepare account lookup for linked journal entries (to match General Ledger columns)
            $hasJelTable = false;
            $hasFaTable = false;
            $jelTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
            if ($jelTableCheck && $jelTableCheck->num_rows > 0) {
                $hasJelTable = true;
            }
            if ($jelTableCheck) $jelTableCheck->free();
            $faTableCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
            if ($faTableCheck && $faTableCheck->num_rows > 0) {
                $hasFaTable = true;
            }
            if ($faTableCheck) $faTableCheck->free();

            $debitAccountStmt = null;
            $creditAccountStmt = null;
            if ($hasJelTable && $hasFaTable) {
                $debitAccountStmt = $conn->prepare("
                    SELECT
                        CASE
                            WHEN COALESCE(fa.account_code, '') = '' THEN COALESCE(fa.account_name, '')
                            ELSE CONCAT(fa.account_code, ' - ', COALESCE(fa.account_name, ''))
                        END AS account_display
                    FROM journal_entry_lines jel
                    LEFT JOIN financial_accounts fa ON jel.account_id = fa.id
                    WHERE jel.journal_entry_id = ?
                      AND COALESCE(jel.debit_amount, 0) > 0
                    ORDER BY jel.id ASC
                    LIMIT 1
                ");
                $creditAccountStmt = $conn->prepare("
                    SELECT
                        CASE
                            WHEN COALESCE(fa.account_code, '') = '' THEN COALESCE(fa.account_name, '')
                            ELSE CONCAT(fa.account_code, ' - ', COALESCE(fa.account_name, ''))
                        END AS account_display
                    FROM journal_entry_lines jel
                    LEFT JOIN financial_accounts fa ON jel.account_id = fa.id
                    WHERE jel.journal_entry_id = ?
                      AND COALESCE(jel.credit_amount, 0) > 0
                    ORDER BY jel.id ASC
                    LIMIT 1
                ");
            }

            while ($row = $result->fetch_assoc()) {
                // Get entity name if linked
                $entityName = null;
                if ($hasEntityType && $hasEntityId && isset($row['entity_type']) && isset($row['entity_id']) && $row['entity_type'] && $row['entity_id']) {
                    // Use getEntityName function if available, otherwise try direct query
                    if (function_exists('getEntityName')) {
                        $entityName = getEntityName($conn, $row['entity_type'], $row['entity_id']);
                    } else {
                        // Fallback: try to get entity name directly
                        $entityType = $row['entity_type'];
                        $entityId = intval($row['entity_id']);
                        $entityName = null;
                        
                        if ($entityType === 'agent') {
                            $tableCheck = $conn->query("SHOW TABLES LIKE 'agents'");
                            if ($tableCheck->num_rows > 0) {
                                $nameStmt = $conn->prepare("SELECT COALESCE(agent_name, full_name, name) as name FROM agents WHERE id = ? LIMIT 1");
                                if ($nameStmt) {
                                    $nameStmt->bind_param('i', $entityId);
                                    $nameStmt->execute();
                                    $nameResult = $nameStmt->get_result();
                                    if ($nameRow = $nameResult->fetch_assoc()) {
                                        $entityName = $nameRow['name'];
                                    }
                                    $nameResult->free();
                                    $nameStmt->close();
                                }
                                $tableCheck->free();
                            } else {
                                if ($tableCheck) {
                                    $tableCheck->free();
                                }
                            }
                        } elseif ($entityType === 'subagent') {
                            $tableCheck = $conn->query("SHOW TABLES LIKE 'subagents'");
                            if ($tableCheck && $tableCheck->num_rows > 0) {
                                $tableCheck->free();
                                $nameStmt = $conn->prepare("SELECT COALESCE(subagent_name, full_name, name) as name FROM subagents WHERE id = ? LIMIT 1");
                                if ($nameStmt) {
                                    $nameStmt->bind_param('i', $entityId);
                                    $nameStmt->execute();
                                    $nameResult = $nameStmt->get_result();
                                    if ($nameRow = $nameResult->fetch_assoc()) {
                                        $entityName = $nameRow['name'];
                                    }
                                    $nameResult->free();
                                    $nameStmt->close();
                                }
                            } else {
                                if ($tableCheck) {
                                    $tableCheck->free();
                                }
                            }
                        } elseif ($entityType === 'worker') {
                            $tableCheck = $conn->query("SHOW TABLES LIKE 'workers'");
                            if ($tableCheck && $tableCheck->num_rows > 0) {
                                $tableCheck->free();
                                $nameStmt = $conn->prepare("SELECT COALESCE(worker_name, full_name, name) as name FROM workers WHERE id = ? LIMIT 1");
                                if ($nameStmt) {
                                    $nameStmt->bind_param('i', $entityId);
                                    $nameStmt->execute();
                                    $nameResult = $nameStmt->get_result();
                                    if ($nameRow = $nameResult->fetch_assoc()) {
                                        $entityName = $nameRow['name'];
                                    }
                                    $nameResult->free();
                                    $nameStmt->close();
                                }
                            } else {
                                if ($tableCheck) {
                                    $tableCheck->free();
                                }
                            }
                        }
                    }
                }
            
            // Try to fetch debit/credit account display for linked journal entry
            $debitAccountName = null;
            $creditAccountName = null;
            $linkedJournalEntryId = isset($row['journal_entry_id']) && $row['journal_entry_id'] ? intval($row['journal_entry_id']) : null;
            if ($linkedJournalEntryId && $debitAccountStmt && $creditAccountStmt) {
                $debitAccountStmt->bind_param('i', $linkedJournalEntryId);
                $debitAccountStmt->execute();
                $dr = $debitAccountStmt->get_result();
                if ($drow = $dr->fetch_assoc()) {
                    $debitAccountName = $drow['account_display'] ?? null;
                }
                $dr->free();

                $creditAccountStmt->bind_param('i', $linkedJournalEntryId);
                $creditAccountStmt->execute();
                $cr = $creditAccountStmt->get_result();
                if ($crow = $cr->fetch_assoc()) {
                    $creditAccountName = $crow['account_display'] ?? null;
                }
                $cr->free();
            }

            $entries[] = [
                    'id' => intval($row['id']),
                    'entry_number' => $row['entry_number'],
                    'entry_date' => $row['entry_date'],
                    'description' => $row['description'],
                    'amount' => floatval($row['amount']),
                    'currency' => $row['currency'],
                    'debit_account_name' => $debitAccountName,
                    'credit_account_name' => $creditAccountName,
                    'debit_amount' => isset($row['debit_amount']) ? floatval($row['debit_amount']) : (isset($row['total_debit']) ? floatval($row['total_debit']) : 0),
                    'credit_amount' => isset($row['credit_amount']) ? floatval($row['credit_amount']) : (isset($row['total_credit']) ? floatval($row['total_credit']) : 0),
                    'total_debit' => isset($row['total_debit']) ? floatval($row['total_debit']) : (isset($row['debit_amount']) ? floatval($row['debit_amount']) : 0),
                    'total_credit' => isset($row['total_credit']) ? floatval($row['total_credit']) : (isset($row['credit_amount']) ? floatval($row['credit_amount']) : 0),
                    'status' => $row['status'],
                    'journal_entry_id' => isset($row['journal_entry_id']) && $row['journal_entry_id'] ? intval($row['journal_entry_id']) : null,
                    'cost_center_id' => isset($row['cost_center_id']) && $row['cost_center_id'] ? intval($row['cost_center_id']) : null,
                    'bank_guarantee_id' => isset($row['bank_guarantee_id']) && $row['bank_guarantee_id'] ? intval($row['bank_guarantee_id']) : null,
                    'entity_type' => isset($row['entity_type']) ? $row['entity_type'] : null,
                    'entity_id' => isset($row['entity_id']) && $row['entity_id'] ? intval($row['entity_id']) : null,
                    'entity_name' => $entityName,
                    'created_by' => intval($row['created_by']),
                    'created_by_name' => $row['created_by_name'],
                    'approved_by' => $row['approved_by'] ? intval($row['approved_by']) : null,
                    'approved_by_name' => $row['approved_by_name'],
                    'approved_at' => $row['approved_at'],
                    'rejection_reason' => $row['rejection_reason'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }
            $result->free();
            if (isset($stmt)) {
                $stmt->close();
            }

            if ($debitAccountStmt) {
                $debitAccountStmt->close();
            }
            if ($creditAccountStmt) {
                $creditAccountStmt->close();
            }
            
            echo json_encode([
                'success' => true,
                'entries' => $entries,
                'count' => count($entries)
            ]);
        }
    } elseif ($method === 'POST') {
        // Approve or reject entry
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $action = $data['action'] ?? ''; // 'approve' or 'reject'
        $ids = $data['ids'] ?? []; // Array of entry IDs
        $rejectionReason = trim($data['rejection_reason'] ?? '');
        $userId = $_SESSION['user_id'] ?? null;
        
        if (empty($ids) || !is_array($ids)) {
            throw new Exception('Entry IDs are required');
        }
        
        if ($action === 'approve') {
            // Use transaction for atomicity
            $conn->begin_transaction();
            try {
                // Update entry_approval - only approve pending entries (prevent duplicate approvals)
                $stmt = $conn->prepare("
                    UPDATE entry_approval 
                    SET status = 'approved', approved_by = ?, approved_at = NOW()
                    WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                    AND status = 'pending'
                ");
                $params = array_merge([$userId], $ids);
                $types = 'i' . str_repeat('i', count($ids));
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $approvedCount = $stmt->affected_rows;
                $stmt->close();
                
                if ($approvedCount === 0) {
                    $conn->rollback();
                    throw new Exception('No entries were approved. Entries may already be approved or rejected.');
                }
                
                // If entries are linked to journal entries, update journal entry status to Posted
                $linkedEntries = $conn->prepare("SELECT journal_entry_id, entity_type, entity_id, amount, entry_date FROM entry_approval WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
                $linkedEntries->bind_param(str_repeat('i', count($ids)), ...$ids);
                $linkedEntries->execute();
                $linkedResult = $linkedEntries->get_result();
                
                $journalEntryIds = [];
                $transactionUpdates = [];
                while ($linkedRow = $linkedResult->fetch_assoc()) {
                    if ($linkedRow['journal_entry_id']) {
                        $journalEntryIds[] = intval($linkedRow['journal_entry_id']);
                    }
                    
                    // Track entries that might link to financial transactions
                    if ($linkedRow['entity_type'] && $linkedRow['entity_id'] && $linkedRow['amount'] && $linkedRow['entry_date']) {
                        $transactionUpdates[] = [
                            'entity_type' => $linkedRow['entity_type'],
                            'entity_id' => intval($linkedRow['entity_id']),
                            'amount' => floatval($linkedRow['amount']),
                            'entry_date' => $linkedRow['entry_date']
                        ];
                    }
                }
                $linkedResult->free();
                $linkedEntries->close();
                
                if (!empty($journalEntryIds)) {
                    // Update linked journal entries to Posted status (approved entries become visible)
                    $journalTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
                    $hasJournalTable = $journalTableCheck && $journalTableCheck->num_rows > 0;
                    if ($hasJournalTable) {
                        $journalTableCheck->free();
                        // Check if is_posted and is_locked columns exist
                        $isPostedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
                        $hasIsPosted = $isPostedCheck->num_rows > 0;
                        $isPostedCheck->free();
                        
                        $isLockedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_locked'");
                        $hasIsLocked = $isLockedCheck->num_rows > 0;
                        $isLockedCheck->free();
                        
                        // Build UPDATE query with is_posted and is_locked if columns exist
                        $updateFields = ["status = 'Posted'"];
                        if ($hasIsPosted) {
                            $updateFields[] = "is_posted = TRUE";
                        }
                        if ($hasIsLocked) {
                            $updateFields[] = "is_locked = TRUE";
                        }
                        
                        $journalUpdateStmt = $conn->prepare("UPDATE journal_entries SET " . implode(', ', $updateFields) . " WHERE id IN (" . implode(',', array_fill(0, count($journalEntryIds), '?')) . ")");
                        $journalUpdateStmt->bind_param(str_repeat('i', count($journalEntryIds)), ...$journalEntryIds);
                        $journalUpdateStmt->execute();
                        $journalUpdateStmt->close();
                        
                        // Post to general ledger
                        $ledgerHelperPath = __DIR__ . '/core/general-ledger-helper.php';
                        if (file_exists($ledgerHelperPath)) {
                            require_once $ledgerHelperPath;
                            if (function_exists('postJournalEntryToLedger')) {
                                foreach ($journalEntryIds as $jeId) {
                                    try {
                                        $ledgerResult = postJournalEntryToLedger($conn, $jeId);
                                        error_log("Entry Approval - General ledger posting for entry {$jeId}: " . $ledgerResult['message']);
                                    } catch (Exception $e) {
                                        error_log("Entry Approval - WARNING: Failed to post entry {$jeId} to general ledger: " . $e->getMessage());
                                        // Don't fail the transaction, but log the error
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Update linked financial transactions to Posted status
                $updatedTransactions = 0;
                if (!empty($transactionUpdates)) {
                    $financialTableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
                    $entityTableCheck = $conn->query("SHOW TABLES LIKE 'entity_transactions'");
                    if ($financialTableCheck && $financialTableCheck->num_rows > 0 && 
                        $entityTableCheck && $entityTableCheck->num_rows > 0) {
                        foreach ($transactionUpdates as $transUpdate) {
                            // First try: Find matching Draft transaction by entity link, amount, and date
                            // Check both total_amount and debit/credit amounts
                            $findTransaction = $conn->prepare("
                                SELECT ft.id 
                                FROM financial_transactions ft
                                INNER JOIN entity_transactions et ON ft.id = et.transaction_id
                                WHERE et.entity_type = ? 
                                AND et.entity_id = ? 
                                AND (
                                    ABS(ft.total_amount - ?) < 0.01 
                                    OR ABS(COALESCE(ft.debit_amount, 0) + COALESCE(ft.credit_amount, 0) - ?) < 0.01
                                )
                                AND ft.transaction_date = ?
                                AND ft.status = 'Draft'
                                ORDER BY ft.id DESC
                                LIMIT 1
                            ");
                            $findTransaction->bind_param('sidds', $transUpdate['entity_type'], $transUpdate['entity_id'], $transUpdate['amount'], $transUpdate['amount'], $transUpdate['entry_date']);
                            $findTransaction->execute();
                            $transactionResult = $findTransaction->get_result();
                            $foundTransaction = null;
                            if ($transactionRow = $transactionResult->fetch_assoc()) {
                                $foundTransaction = $transactionRow['id'];
                            }
                            $transactionResult->free();
                            $findTransaction->close();
                            
                            if ($foundTransaction) {
                                $updateTransaction = $conn->prepare("UPDATE financial_transactions SET status = 'Posted' WHERE id = ?");
                                $updateTransaction->bind_param('i', $foundTransaction);
                                $updateTransaction->execute();
                                if ($updateTransaction->affected_rows > 0) {
                                    $updatedTransactions++;
                                }
                                $updateTransaction->close();
                            }
                        }
                        $financialTableCheck->free();
                        $entityTableCheck->free();
                    }
                }
                
                // Also try to update entries by reference number matching (for cases where direct links don't exist)
                if (!empty($ids)) {
                    $entryNumbersQuery = $conn->prepare("
                        SELECT entry_number 
                        FROM entry_approval 
                        WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                    ");
                    $entryNumbersQuery->bind_param(str_repeat('i', count($ids)), ...$ids);
                    $entryNumbersQuery->execute();
                    $entryNumbersResult = $entryNumbersQuery->get_result();
                    $entryNumbers = [];
                    while ($numRow = $entryNumbersResult->fetch_assoc()) {
                        $entryNumbers[] = $numRow['entry_number'];
                    }
                    $entryNumbersResult->free();
                    $entryNumbersQuery->close();
                    
                    // Try to find and update entries by entry number
                    if (!empty($entryNumbers)) {
                        foreach ($entryNumbers as $entryNumber) {
                            // Extract the original entry number from APP-REF-* or APP-JE-*
                            $originalNumber = str_replace('APP-', '', $entryNumber);
                            
                            // Try to find journal entry by entry_number
                            $journalTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
                            if ($journalTableCheck && $journalTableCheck->num_rows > 0) {
                            $journalTableCheck->free();
                            $findJeByNumber = $conn->prepare("
                                SELECT id FROM journal_entries 
                                WHERE entry_number = ? AND status = 'Draft'
                                LIMIT 1
                            ");
                            $findJeByNumber->bind_param('s', $originalNumber);
                            $findJeByNumber->execute();
                            $jeResult = $findJeByNumber->get_result();
                            if ($jeRow = $jeResult->fetch_assoc()) {
                                if (!in_array($jeRow['id'], $journalEntryIds)) {
                                    $journalEntryIds[] = $jeRow['id'];
                                    
                                    // Check if is_posted and is_locked columns exist
                                    $isPostedCheck2 = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
                                    $hasIsPosted = $isPostedCheck2->num_rows > 0;
                                    $isPostedCheck2->free();
                                    
                                    $isLockedCheck2 = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_locked'");
                                    $hasIsLocked = $isLockedCheck2->num_rows > 0;
                                    $isLockedCheck2->free();
                                    
                                    // Build UPDATE query with is_posted and is_locked if columns exist
                                    $updateFields = ["status = 'Posted'"];
                                    if ($hasIsPosted) {
                                        $updateFields[] = "is_posted = TRUE";
                                    }
                                    if ($hasIsLocked) {
                                        $updateFields[] = "is_locked = TRUE";
                                    }
                                    
                                    $updateJe = $conn->prepare("UPDATE journal_entries SET " . implode(', ', $updateFields) . " WHERE id = ?");
                                    $updateJe->bind_param('i', $jeRow['id']);
                                    $updateJe->execute();
                                    $updateJe->close();
                                    
                                    // Post to general ledger for this entry
                                    $ledgerHelperPath = __DIR__ . '/core/general-ledger-helper.php';
                                    if (file_exists($ledgerHelperPath)) {
                                        require_once $ledgerHelperPath;
                                        if (function_exists('postJournalEntryToLedger')) {
                                            try {
                                                $ledgerResult = postJournalEntryToLedger($conn, $jeRow['id']);
                                                error_log("Entry Approval - General ledger posting for entry {$jeRow['id']}: " . $ledgerResult['message']);
                                            } catch (Exception $e) {
                                                error_log("Entry Approval - WARNING: Failed to post entry {$jeRow['id']} to general ledger: " . $e->getMessage());
                                            }
                                        }
                                    }
                                }
                            }
                            $jeResult->free();
                            $findJeByNumber->close();
                            }
                            
                            // Try to find financial_transaction by reference_number
                            $financialTableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
                            $hasFinancialTable = $financialTableCheck && $financialTableCheck->num_rows > 0;
                            if ($hasFinancialTable) {
                                $findFtByRef = $conn->prepare("
                                    SELECT id FROM financial_transactions 
                                    WHERE reference_number = ? AND status = 'Draft'
                                    LIMIT 1
                                ");
                                $findFtByRef->bind_param('s', $originalNumber);
                                $findFtByRef->execute();
                                $ftResult = $findFtByRef->get_result();
                                if ($ftRow = $ftResult->fetch_assoc()) {
                                    $updateFt = $conn->prepare("UPDATE financial_transactions SET status = 'Posted' WHERE id = ?");
                                    $updateFt->bind_param('i', $ftRow['id']);
                                    $updateFt->execute();
                                    if ($updateFt->affected_rows > 0) {
                                        $updatedTransactions++;
                                    }
                                    $updateFt->close();
                                    $ftResult->free();
                                } else {
                                    $ftResult->free();
                                }
                                $findFtByRef->close();
                                $financialTableCheck->free();
                            } else {
                                if ($financialTableCheck) {
                                    $financialTableCheck->free();
                                }
                            }
                        }
                    }
                }
                
                $message = $approvedCount . ' entry(ies) approved successfully';
                if (!empty($journalEntryIds)) {
                    $message .= ' and ' . count($journalEntryIds) . ' linked journal entry(ies) updated';
                }
                if ($updatedTransactions > 0) {
                    $message .= ' and ' . $updatedTransactions . ' linked transaction(s) updated';
                }
                
                // Commit transaction
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => $message
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
        } elseif ($action === 'reject') {
            if (empty($rejectionReason)) {
                throw new Exception('Rejection reason is required');
            }
            
            // Verify status column supports 'rejected' value
            $statusCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'status'");
            if ($statusCheck && $statusCheck->num_rows > 0) {
                $statusColumn = $statusCheck->fetch_assoc();
                // If it's an ENUM, verify 'rejected' is in the allowed values
                if (strpos($statusColumn['Type'], 'enum') !== false || strpos(strtolower($statusColumn['Type']), 'enum') !== false) {
                    // Extract ENUM values
                    preg_match("/enum\((.*)\)/i", $statusColumn['Type'], $matches);
                    if (isset($matches[1])) {
                        $enumValues = array_map(function($v) { return trim($v, "'\""); }, explode(',', $matches[1]));
                        if (!in_array('rejected', $enumValues)) {
                            // Alter table to add 'rejected' to ENUM
                            $conn->query("ALTER TABLE entry_approval MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
                        }
                    }
                }
                $statusCheck->free();
            } else {
                if ($statusCheck) {
                    $statusCheck->free();
                }
            }
            
            $stmt = $conn->prepare("
                UPDATE entry_approval 
                SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ?
                WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
            ");
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }
            
            $params = array_merge([$userId, $rejectionReason], $ids);
            $types = 'is' . str_repeat('i', count($ids));
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Failed to execute update: ' . $error);
            }
            
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affectedRows === 0) {
                throw new Exception('No entries were updated. Please check if the entry IDs are valid and the entries exist.');
            }
            
            echo json_encode([
                'success' => true,
                'message' => count($ids) . ' entry(ies) rejected successfully',
                'affected_rows' => $affectedRows
            ]);
        } else {
            throw new Exception('Invalid action. Use "approve" or "reject"');
        }
    } elseif ($method === 'PUT') {
        // Update entry (for editing)
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($id <= 0) {
            throw new Exception('Entry ID is required');
        }
        
        $entryNumber = trim($data['entry_number'] ?? '');
        $entryDate = $data['entry_date'] ?? date('Y-m-d');
        $description = trim($data['description'] ?? '');
        $debitAmount = floatval($data['debit_amount'] ?? 0);
        $creditAmount = floatval($data['credit_amount'] ?? 0);
        $amount = floatval($data['amount'] ?? ($debitAmount > 0 ? $debitAmount : ($creditAmount > 0 ? $creditAmount : 0)));
        $currency = $data['currency'] ?? 'SAR';
        $journalEntryId = isset($data['journal_entry_id']) && $data['journal_entry_id'] ? intval($data['journal_entry_id']) : null;
        $costCenterId = isset($data['cost_center_id']) && $data['cost_center_id'] ? intval($data['cost_center_id']) : null;
        $bankGuaranteeId = isset($data['bank_guarantee_id']) && $data['bank_guarantee_id'] ? intval($data['bank_guarantee_id']) : null;
        $entityType = isset($data['entity_type']) ? trim($data['entity_type']) : null;
        $entityId = isset($data['entity_id']) && $data['entity_id'] ? intval($data['entity_id']) : null;
        
        if (empty($entryNumber)) {
            throw new Exception('Entry number is required');
        }
        
        // Check if linking columns exist
        $journalLinkCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'journal_entry_id'");
        $hasJournalLink = $journalLinkCheck && $journalLinkCheck->num_rows > 0;
        if ($journalLinkCheck) {
            $journalLinkCheck->free();
        }
        $costCenterLinkCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'cost_center_id'");
        $hasCostCenterLink = $costCenterLinkCheck && $costCenterLinkCheck->num_rows > 0;
        if ($costCenterLinkCheck) {
            $costCenterLinkCheck->free();
        }
        $bankGuaranteeLinkCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'bank_guarantee_id'");
        $hasBankGuaranteeLink = $bankGuaranteeLinkCheck && $bankGuaranteeLinkCheck->num_rows > 0;
        if ($bankGuaranteeLinkCheck) {
            $bankGuaranteeLinkCheck->free();
        }
        $debitAmountCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'debit_amount'");
        $hasDebitAmount = $debitAmountCheck && $debitAmountCheck->num_rows > 0;
        if ($debitAmountCheck) {
            $debitAmountCheck->free();
        }
        $creditAmountCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'credit_amount'");
        $hasCreditAmount = $creditAmountCheck && $creditAmountCheck->num_rows > 0;
        if ($creditAmountCheck) {
            $creditAmountCheck->free();
        }
        
        if ($hasJournalLink && $hasCostCenterLink && $hasBankGuaranteeLink) {
            // Check for entity linking columns
            $entityTypeCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_type'");
            $hasEntityType = $entityTypeCheck && $entityTypeCheck->num_rows > 0;
            if ($entityTypeCheck) {
                $entityTypeCheck->free();
            }
            $entityIdCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_id'");
            $hasEntityId = $entityIdCheck && $entityIdCheck->num_rows > 0;
            if ($entityIdCheck) {
                $entityIdCheck->free();
            }
            $entityType = isset($data['entity_type']) ? trim($data['entity_type']) : null;
            $entityId = isset($data['entity_id']) && $data['entity_id'] ? intval($data['entity_id']) : null;
            
            if ($hasEntityType && $hasEntityId && $hasJournalLink && $hasCostCenterLink && $hasBankGuaranteeLink) {
                if ($hasDebitAmount && $hasCreditAmount) {
                    $stmt = $conn->prepare("
                        UPDATE entry_approval 
                        SET entry_number = ?, entry_date = ?, description = ?, debit_amount = ?, credit_amount = ?, amount = ?, currency = ?, 
                            journal_entry_id = ?, cost_center_id = ?, bank_guarantee_id = ?,
                            entity_type = ?, entity_id = ?
                        WHERE id = ?
                    ");
                    if (!$stmt) {
                        throw new Exception('Failed to prepare statement: ' . $conn->error);
                    }
                    // sssdddsiiiisi = 13 params: entry_number(s), entry_date(s), description(s), debit_amount(d), credit_amount(d), amount(d), currency(s), journal_entry_id(i), cost_center_id(i), bank_guarantee_id(i), entity_type(s), entity_id(i), id(i)
                    if (!$stmt->bind_param('sssdddsiiiisi', $entryNumber, $entryDate, $description, $debitAmount, $creditAmount, $amount, $currency, $journalEntryId, $costCenterId, $bankGuaranteeId, $entityType, $entityId, $id)) {
                        throw new Exception('Failed to bind parameters: ' . $stmt->error);
                    }
                } else {
                    $stmt = $conn->prepare("
                        UPDATE entry_approval 
                        SET entry_number = ?, entry_date = ?, description = ?, amount = ?, currency = ?, 
                            journal_entry_id = ?, cost_center_id = ?, bank_guarantee_id = ?,
                            entity_type = ?, entity_id = ?
                        WHERE id = ?
                    ");
                    // sssdiiiisi = 10 params: entry_number(s), entry_date(s), description(s), amount(d), currency(s), journal_entry_id(i), cost_center_id(i), bank_guarantee_id(i), entity_type(s), entity_id(i), id(i)
                    if (!$stmt->bind_param('sssdiiiisi', $entryNumber, $entryDate, $description, $amount, $currency, $journalEntryId, $costCenterId, $bankGuaranteeId, $entityType, $entityId, $id)) {
                        throw new Exception('Failed to bind parameters: ' . $stmt->error);
                    }
                }
            } else if ($hasJournalLink && $hasCostCenterLink && $hasBankGuaranteeLink) {
                if ($hasDebitAmount && $hasCreditAmount) {
                    $stmt = $conn->prepare("
                        UPDATE entry_approval 
                        SET entry_number = ?, entry_date = ?, description = ?, debit_amount = ?, credit_amount = ?, amount = ?, currency = ?, 
                            journal_entry_id = ?, cost_center_id = ?, bank_guarantee_id = ?
                        WHERE id = ?
                    ");
                    if (!$stmt) {
                        throw new Exception('Failed to prepare statement: ' . $conn->error);
                    }
                    // sssdddsiiii = 11 params: entry_number(s), entry_date(s), description(s), debit_amount(d), credit_amount(d), amount(d), currency(s), journal_entry_id(i), cost_center_id(i), bank_guarantee_id(i), id(i)
                    $stmt->bind_param('sssdddsiiii', $entryNumber, $entryDate, $description, $debitAmount, $creditAmount, $amount, $currency, $journalEntryId, $costCenterId, $bankGuaranteeId, $id);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE entry_approval 
                        SET entry_number = ?, entry_date = ?, description = ?, amount = ?, currency = ?, 
                            journal_entry_id = ?, cost_center_id = ?, bank_guarantee_id = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param('sssdiiiii', $entryNumber, $entryDate, $description, $amount, $currency, $journalEntryId, $costCenterId, $bankGuaranteeId, $id);
                }
            } else {
                if ($hasDebitAmount && $hasCreditAmount) {
                    $stmt = $conn->prepare("
                        UPDATE entry_approval 
                        SET entry_number = ?, entry_date = ?, description = ?, debit_amount = ?, credit_amount = ?, amount = ?, currency = ?
                        WHERE id = ?
                    ");
                    if (!$stmt) {
                        throw new Exception('Failed to prepare statement: ' . $conn->error);
                    }
                    // sssdddsi = 8 params: entry_number(s), entry_date(s), description(s), debit_amount(d), credit_amount(d), amount(d), currency(s), id(i)
                    $stmt->bind_param('sssdddsi', $entryNumber, $entryDate, $description, $debitAmount, $creditAmount, $amount, $currency, $id);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE entry_approval 
                        SET entry_number = ?, entry_date = ?, description = ?, amount = ?, currency = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param('sssdsi', $entryNumber, $entryDate, $description, $amount, $currency, $id);
                }
            }
        } else {
            if ($hasDebitAmount && $hasCreditAmount) {
                $stmt = $conn->prepare("
                    UPDATE entry_approval 
                    SET entry_number = ?, entry_date = ?, description = ?, debit_amount = ?, credit_amount = ?, amount = ?, currency = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('sssdddsi', $entryNumber, $entryDate, $description, $debitAmount, $creditAmount, $amount, $currency, $id);
            } else {
                $stmt = $conn->prepare("
                    UPDATE entry_approval 
                    SET entry_number = ?, entry_date = ?, description = ?, amount = ?, currency = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('sssdsi', $entryNumber, $entryDate, $description, $amount, $currency, $id);
            }
        }
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Entry updated successfully'
        ]);
    } elseif ($method === 'DELETE') {
        // Delete entry
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            throw new Exception('Entry ID is required');
        }
        
        // Check if entry exists and is pending (only allow deletion of pending entries)
        $checkStmt = $conn->prepare("SELECT status FROM entry_approval WHERE id = ?");
        $checkStmt->bind_param('i', $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            throw new Exception('Entry not found');
        }
        
        $entry = $checkResult->fetch_assoc();
        if ($entry['status'] !== 'pending') {
            throw new Exception('Only pending entries can be deleted');
        }
        
        $checkStmt->close();
        
        // Delete the entry
        $stmt = $conn->prepare("DELETE FROM entry_approval WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Entry deleted successfully'
            ]);
        } else {
            throw new Exception('Failed to delete entry');
        }
        
        $stmt->close();
    }
} catch (Exception $e) {
    http_response_code(400);
    error_log('Entry Approval API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    http_response_code(500);
    error_log('Entry Approval API Fatal Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}

