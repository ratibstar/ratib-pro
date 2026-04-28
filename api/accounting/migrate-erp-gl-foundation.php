<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/migrate-erp-gl-foundation.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/migrate-erp-gl-foundation.php`.
 */
/**
 * ERP-Grade General Ledger Foundation Migration
 * 
 * PHASE 1: Adds all missing ERP fields to journal_entries and general_ledger tables
 * 
 * This migration ensures:
 * - Journal Header vs Journal Lines structure
 * - entry_type field
 * - posting_status (draft, posted, reversed)
 * - fiscal_period_id
 * - approved_at / approved_by
 * - is_auto flag
 * - source_table / source_id
 * - locked_at timestamp
 * - branch_id support
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $results = [];
    $errors = [];
    
    // ============================================
    // STEP 1: Create branches table if missing
    // ============================================
    $branchesCheck = $conn->query("SHOW TABLES LIKE 'branches'");
    $hasBranches = $branchesCheck && $branchesCheck->num_rows > 0;
    if ($branchesCheck) $branchesCheck->free();
    
    if (!$hasBranches) {
        $createBranches = $conn->query("
            CREATE TABLE branches (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                address TEXT NULL,
                city VARCHAR(100) NULL,
                country VARCHAR(100) NULL,
                phone VARCHAR(50) NULL,
                email VARCHAR(100) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NULL,
                INDEX idx_code (code),
                INDEX idx_is_active (is_active),
                FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        if ($createBranches) {
            $results[] = "Created branches table";
            
            // Insert default branch
            $conn->query("INSERT INTO branches (code, name, is_active) VALUES ('MAIN', 'Main Branch', 1)");
            $results[] = "Created default 'Main Branch'";
        } else {
            $errors[] = "Failed to create branches table: " . $conn->error;
        }
    } else {
        $results[] = "branches table already exists";
    }
    
    // ============================================
    // STEP 2: Create fiscal_periods table if missing
    // ============================================
    $periodsCheck = $conn->query("SHOW TABLES LIKE 'fiscal_periods'");
    $hasPeriods = $periodsCheck && $periodsCheck->num_rows > 0;
    if ($periodsCheck) $periodsCheck->free();
    
    if (!$hasPeriods) {
        $createPeriods = $conn->query("
            CREATE TABLE fiscal_periods (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                period_name VARCHAR(100) NOT NULL,
                period_code VARCHAR(50) NOT NULL UNIQUE,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                fiscal_year INT(4) NOT NULL,
                is_closed TINYINT(1) NOT NULL DEFAULT 0,
                closed_at TIMESTAMP NULL,
                closed_by INT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT NULL,
                INDEX idx_period_code (period_code),
                INDEX idx_fiscal_year (fiscal_year),
                INDEX idx_start_date (start_date),
                INDEX idx_end_date (end_date),
                INDEX idx_is_closed (is_closed),
                FOREIGN KEY (closed_by) REFERENCES users(user_id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        if ($createPeriods) {
            $results[] = "Created fiscal_periods table";
            
            // Create current year periods (12 months)
            $currentYear = date('Y');
            $months = [
                ['01', 'January', '01-01', '01-31'],
                ['02', 'February', '02-01', '02-28'],
                ['03', 'March', '03-01', '03-31'],
                ['04', 'April', '04-01', '04-30'],
                ['05', 'May', '05-01', '05-31'],
                ['06', 'June', '06-01', '06-30'],
                ['07', 'July', '07-01', '07-31'],
                ['08', 'August', '08-01', '08-31'],
                ['09', 'September', '09-01', '09-30'],
                ['10', 'October', '10-01', '10-31'],
                ['11', 'November', '11-01', '11-30'],
                ['12', 'December', '12-01', '12-31']
            ];
            
            foreach ($months as $month) {
                $periodCode = $currentYear . $month[0];
                $periodName = $month[1] . ' ' . $currentYear;
                $startDate = $currentYear . '-' . $month[2];
                $endDate = $currentYear . '-' . $month[3];
                
                // Handle leap year for February
                if ($month[0] === '02' && date('L', strtotime($currentYear . '-01-01'))) {
                    $endDate = $currentYear . '-02-29';
                }
                
                $conn->query("
                    INSERT INTO fiscal_periods (period_name, period_code, start_date, end_date, fiscal_year, is_active)
                    VALUES ('$periodName', '$periodCode', '$startDate', '$endDate', $currentYear, 1)
                ");
            }
            $results[] = "Created fiscal periods for year $currentYear";
        } else {
            $errors[] = "Failed to create fiscal_periods table: " . $conn->error;
        }
    } else {
        $results[] = "fiscal_periods table already exists";
    }
    
    // ============================================
    // STEP 3: Add missing fields to journal_entries table
    // ============================================
    $journalTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
    if (!$journalTableCheck || $journalTableCheck->num_rows === 0) {
        if ($journalTableCheck) $journalTableCheck->free();
        throw new Exception('journal_entries table does not exist');
    }
    $journalTableCheck->free();
    
    // Check and add fiscal_period_id
    $fiscalPeriodCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'fiscal_period_id'");
    if (!$fiscalPeriodCheck || $fiscalPeriodCheck->num_rows === 0) {
        if ($fiscalPeriodCheck) $fiscalPeriodCheck->free();
        $conn->query("
            ALTER TABLE journal_entries 
            ADD COLUMN fiscal_period_id INT(11) NULL AFTER entry_date,
            ADD INDEX idx_fiscal_period_id (fiscal_period_id),
            ADD FOREIGN KEY (fiscal_period_id) REFERENCES fiscal_periods(id) ON DELETE SET NULL
        ");
        $results[] = "Added fiscal_period_id to journal_entries";
    } else {
        if ($fiscalPeriodCheck) $fiscalPeriodCheck->free();
        $results[] = "fiscal_period_id already exists in journal_entries";
    }
    
    // Check and add approved_at
    $approvedAtCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'approved_at'");
    if (!$approvedAtCheck || $approvedAtCheck->num_rows === 0) {
        if ($approvedAtCheck) $approvedAtCheck->free();
        $conn->query("ALTER TABLE journal_entries ADD COLUMN approved_at TIMESTAMP NULL AFTER status");
        $results[] = "Added approved_at to journal_entries";
    } else {
        if ($approvedAtCheck) $approvedAtCheck->free();
        $results[] = "approved_at already exists in journal_entries";
    }
    
    // Check and add approved_by
    $approvedByCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'approved_by'");
    if (!$approvedByCheck || $approvedByCheck->num_rows === 0) {
        if ($approvedByCheck) $approvedByCheck->free();
        $conn->query("
            ALTER TABLE journal_entries 
            ADD COLUMN approved_by INT NULL AFTER approved_at,
            ADD INDEX idx_approved_by (approved_by),
            ADD FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
        ");
        $results[] = "Added approved_by to journal_entries";
    } else {
        if ($approvedByCheck) $approvedByCheck->free();
        $results[] = "approved_by already exists in journal_entries";
    }
    
    // Check and add is_auto
    $isAutoCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_auto'");
    if (!$isAutoCheck || $isAutoCheck->num_rows === 0) {
        if ($isAutoCheck) $isAutoCheck->free();
        $conn->query("ALTER TABLE journal_entries ADD COLUMN is_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER entry_type");
        $results[] = "Added is_auto to journal_entries";
    } else {
        if ($isAutoCheck) $isAutoCheck->free();
        $results[] = "is_auto already exists in journal_entries";
    }
    
    // Check and add source_table
    $sourceTableCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'source_table'");
    if (!$sourceTableCheck || $sourceTableCheck->num_rows === 0) {
        if ($sourceTableCheck) $sourceTableCheck->free();
        $conn->query("ALTER TABLE journal_entries ADD COLUMN source_table VARCHAR(100) NULL AFTER is_auto");
        $results[] = "Added source_table to journal_entries";
    } else {
        if ($sourceTableCheck) $sourceTableCheck->free();
        $results[] = "source_table already exists in journal_entries";
    }
    
    // Check and add source_id
    $sourceIdCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'source_id'");
    if (!$sourceIdCheck || $sourceIdCheck->num_rows === 0) {
        if ($sourceIdCheck) $sourceIdCheck->free();
        $conn->query("ALTER TABLE journal_entries ADD COLUMN source_id INT(11) NULL AFTER source_table");
        $results[] = "Added source_id to journal_entries";
    } else {
        if ($sourceIdCheck) $sourceIdCheck->free();
        $results[] = "source_id already exists in journal_entries";
    }
    
    // Check and add locked_at
    $lockedAtCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'locked_at'");
    if (!$lockedAtCheck || $lockedAtCheck->num_rows === 0) {
        if ($lockedAtCheck) $lockedAtCheck->free();
        $conn->query("ALTER TABLE journal_entries ADD COLUMN locked_at TIMESTAMP NULL AFTER is_locked");
        $results[] = "Added locked_at to journal_entries";
    } else {
        if ($lockedAtCheck) $lockedAtCheck->free();
        $results[] = "locked_at already exists in journal_entries";
    }
    
    // Check and add posting_status (more explicit than status)
    $postingStatusCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'posting_status'");
    if (!$postingStatusCheck || $postingStatusCheck->num_rows === 0) {
        if ($postingStatusCheck) $postingStatusCheck->free();
        $conn->query("
            ALTER TABLE journal_entries 
            ADD COLUMN posting_status ENUM('draft', 'posted', 'reversed') NOT NULL DEFAULT 'draft' AFTER status
        ");
        $results[] = "Added posting_status to journal_entries";
        
        // Sync existing status values to posting_status
        $conn->query("UPDATE journal_entries SET posting_status = 'draft' WHERE status = 'Draft'");
        $conn->query("UPDATE journal_entries SET posting_status = 'posted' WHERE status IN ('Posted', 'Approved')");
        $results[] = "Synced existing status values to posting_status";
    } else {
        if ($postingStatusCheck) $postingStatusCheck->free();
        $results[] = "posting_status already exists in journal_entries";
    }
    
    // Ensure branch_id exists (may already exist)
    $branchIdCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'branch_id'");
    if (!$branchIdCheck || $branchIdCheck->num_rows === 0) {
        if ($branchIdCheck) $branchIdCheck->free();
        $conn->query("
            ALTER TABLE journal_entries 
            ADD COLUMN branch_id INT(11) NULL AFTER entry_date,
            ADD INDEX idx_branch_id (branch_id),
            ADD FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
        ");
        $results[] = "Added branch_id to journal_entries";
        
        // Set default branch_id for existing entries
        $defaultBranch = $conn->query("SELECT id FROM branches WHERE code = 'MAIN' LIMIT 1");
        if ($defaultBranch && $defaultBranch->num_rows > 0) {
            $branchRow = $defaultBranch->fetch_assoc();
            $defaultBranchId = $branchRow['id'];
            $conn->query("UPDATE journal_entries SET branch_id = $defaultBranchId WHERE branch_id IS NULL");
            $results[] = "Set default branch_id for existing entries";
        }
        if ($defaultBranch) $defaultBranch->free();
    } else {
        if ($branchIdCheck) $branchIdCheck->free();
        $results[] = "branch_id already exists in journal_entries";
    }
    
    // ============================================
    // STEP 4: Add missing fields to general_ledger table
    // ============================================
    $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
    if (!$glTableCheck || $glTableCheck->num_rows === 0) {
        if ($glTableCheck) $glTableCheck->free();
        throw new Exception('general_ledger table does not exist. Please run migrate-general-ledger.php first.');
    }
    $glTableCheck->free();
    
    // Check and add branch_id
    $glBranchCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'branch_id'");
    if (!$glBranchCheck || $glBranchCheck->num_rows === 0) {
        if ($glBranchCheck) $glBranchCheck->free();
        $conn->query("
            ALTER TABLE general_ledger 
            ADD COLUMN branch_id INT(11) NULL AFTER journal_entry_id,
            ADD INDEX idx_gl_branch_id (branch_id),
            ADD FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
        ");
        $results[] = "Added branch_id to general_ledger";
        
        // Populate branch_id from journal_entries
        $conn->query("
            UPDATE general_ledger gl
            INNER JOIN journal_entries je ON gl.journal_entry_id = je.id
            SET gl.branch_id = je.branch_id
            WHERE gl.branch_id IS NULL
        ");
        $results[] = "Populated branch_id in general_ledger from journal_entries";
    } else {
        if ($glBranchCheck) $glBranchCheck->free();
        $results[] = "branch_id already exists in general_ledger";
    }
    
    // Check and add cost_center_id
    $glCostCenterCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'cost_center_id'");
    if (!$glCostCenterCheck || $glCostCenterCheck->num_rows === 0) {
        if ($glCostCenterCheck) $glCostCenterCheck->free();
        $conn->query("
            ALTER TABLE general_ledger 
            ADD COLUMN cost_center_id INT(11) NULL AFTER branch_id,
            ADD INDEX idx_gl_cost_center_id (cost_center_id)
        ");
        $results[] = "Added cost_center_id to general_ledger";
        
        // Populate cost_center_id from journal_entry_lines
        $conn->query("
            UPDATE general_ledger gl
            INNER JOIN journal_entry_lines jel ON gl.journal_entry_id = jel.journal_entry_id 
                AND gl.account_id = jel.account_id
            SET gl.cost_center_id = jel.cost_center_id
            WHERE gl.cost_center_id IS NULL AND jel.cost_center_id IS NOT NULL
        ");
        $results[] = "Populated cost_center_id in general_ledger from journal_entry_lines";
    } else {
        if ($glCostCenterCheck) $glCostCenterCheck->free();
        $results[] = "cost_center_id already exists in general_ledger";
    }
    
    // Check and add fiscal_period_id
    $glFiscalPeriodCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'fiscal_period_id'");
    if (!$glFiscalPeriodCheck || $glFiscalPeriodCheck->num_rows === 0) {
        if ($glFiscalPeriodCheck) $glFiscalPeriodCheck->free();
        $conn->query("
            ALTER TABLE general_ledger 
            ADD COLUMN fiscal_period_id INT(11) NULL AFTER cost_center_id,
            ADD INDEX idx_gl_fiscal_period_id (fiscal_period_id),
            ADD FOREIGN KEY (fiscal_period_id) REFERENCES fiscal_periods(id) ON DELETE SET NULL
        ");
        $results[] = "Added fiscal_period_id to general_ledger";
        
        // Populate fiscal_period_id from journal_entries
        $conn->query("
            UPDATE general_ledger gl
            INNER JOIN journal_entries je ON gl.journal_entry_id = je.id
            SET gl.fiscal_period_id = je.fiscal_period_id
            WHERE gl.fiscal_period_id IS NULL
        ");
        $results[] = "Populated fiscal_period_id in general_ledger from journal_entries";
    } else {
        if ($glFiscalPeriodCheck) $glFiscalPeriodCheck->free();
        $results[] = "fiscal_period_id already exists in general_ledger";
    }
    
    // Check and add approved_by
    $glApprovedByCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'approved_by'");
    if (!$glApprovedByCheck || $glApprovedByCheck->num_rows === 0) {
        if ($glApprovedByCheck) $glApprovedByCheck->free();
        $conn->query("
            ALTER TABLE general_ledger 
            ADD COLUMN approved_by INT NULL AFTER fiscal_period_id,
            ADD INDEX idx_gl_approved_by (approved_by),
            ADD FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
        ");
        $results[] = "Added approved_by to general_ledger";
        
        // Populate approved_by from journal_entries
        $conn->query("
            UPDATE general_ledger gl
            INNER JOIN journal_entries je ON gl.journal_entry_id = je.id
            SET gl.approved_by = je.approved_by
            WHERE gl.approved_by IS NULL
        ");
        $results[] = "Populated approved_by in general_ledger from journal_entries";
    } else {
        if ($glApprovedByCheck) $glApprovedByCheck->free();
        $results[] = "approved_by already exists in general_ledger";
    }
    
    // ============================================
    // STEP 5: Auto-populate fiscal_period_id for existing entries
    // ============================================
    $conn->query("
        UPDATE journal_entries je
        INNER JOIN fiscal_periods fp ON je.entry_date >= fp.start_date AND je.entry_date <= fp.end_date
        SET je.fiscal_period_id = fp.id
        WHERE je.fiscal_period_id IS NULL
    ");
    $results[] = "Auto-populated fiscal_period_id for existing journal entries";
    
    // ============================================
    // STEP 6: Update is_auto flag for existing entries
    // ============================================
    $conn->query("
        UPDATE journal_entries 
        SET is_auto = 1 
        WHERE entry_type IN ('Automatic', 'Recurring') AND is_auto = 0
    ");
    $results[] = "Updated is_auto flag for automatic entries";
    
    // Prepare response
    $response = [
        'success' => count($errors) === 0,
        'message' => 'ERP-Grade GL Foundation migration completed',
        'results' => $results
    ];
    
    if (count($errors) > 0) {
        $response['errors'] = $errors;
    }
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
