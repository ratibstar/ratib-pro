<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/core/ReceiptPaymentVoucherManager.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/core/ReceiptPaymentVoucherManager.php`.
 */
/**
 * Receipt and Payment Voucher Manager
 * 
 * Modern, Guardian-protected, audit-ready PHP class for managing
 * Receipt and Payment Vouchers
 * 
 * Features:
 * - Dynamic voucher fields support
 * - Full ERP Guardian integration
 * - Comprehensive audit trail with correlation IDs
 * - GL-based balance calculations
 * - PDO with prepared statements
 * - Production-ready and maintainable
 * 
 * @package Accounting
 * @author ERP Guardian System
 * @version 2.0.0
 */

require_once __DIR__ . '/erp-guardian.php';
require_once __DIR__ . '/audit-trail-helper.php';
require_once __DIR__ . '/general-ledger-helper.php';
require_once __DIR__ . '/erp-posting-controls.php';
require_once __DIR__ . '/fiscal-period-helper.php';
if (file_exists(__DIR__ . '/../../core/date-helper.php')) { require_once __DIR__ . '/../../core/date-helper.php'; }
elseif (file_exists(__DIR__ . '/date-helper.php')) { require_once __DIR__ . '/date-helper.php'; }
if (!function_exists('formatDateForDatabase')) {
    function formatDateForDatabase($s) { if (empty($s)) return null; $s = trim($s); if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return explode(' ', $s)[0]; if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]); $t = strtotime($s); return $t ? date('Y-m-d', $t) : null; }
}
if (!function_exists('formatDateForDisplay')) {
    function formatDateForDisplay($s) { if (empty($s) || $s === '0000-00-00') return ''; if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return sprintf('%02d/%02d/%04d', $m[2], $m[3], $m[1]); $t = strtotime($s); return $t ? date('m/d/Y', $t) : $s; }
}
if (!function_exists('formatDatesInArray')) {
    function formatDatesInArray($data, $fields = null) { if (!is_array($data)) return $data; $fields = $fields ?? ['date','entry_date','invoice_date','bill_date','due_date','payment_date','voucher_date','created_at','updated_at','transaction_date']; foreach ($data as $k => $v) { if (is_array($v)) $data[$k] = formatDatesInArray($v, $fields); elseif (in_array($k, $fields) && !empty($v)) $data[$k] = formatDateForDisplay($v); } return $data; }
}

class ReceiptPaymentVoucherManager {
    
    /**
     * @var PDO Database connection
     */
    private $pdo;
    
    /**
     * @var string Voucher type: 'receipt' or 'payment'
     */
    private $voucherType;
    
    /**
     * @var string Correlation ID for audit trail
     */
    private $correlationId;
    
    /**
     * @var int Current user ID
     */
    private $userId;
    
    /**
     * @var array Dynamic voucher fields configuration
     */
    private $dynamicFields = [];
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     * @param string $voucherType 'receipt' or 'payment'
     * @param int|null $userId User ID (defaults to session)
     * @throws Exception If invalid voucher type
     */
    public function __construct(PDO $pdo, string $voucherType, ?int $userId = null) {
        if (!in_array($voucherType, ['receipt', 'payment'])) {
            throw new Exception("Invalid voucher type. Must be 'receipt' or 'payment'");
        }
        
        $this->pdo = $pdo;
        $this->voucherType = $voucherType;
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $this->correlationId = $this->generateCorrelationId();
        
        if (!$this->userId) {
            throw new Exception("User ID is required");
        }
        
        // Initialize table structure
        $this->ensureTableExists();
    }
    
    /**
     * Generate unique correlation ID for audit trail
     * 
     * @return string Correlation ID
     */
    private function generateCorrelationId(): string {
        return 'VCH-' . strtoupper(substr($this->voucherType, 0, 1)) . '-' . 
               date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }
    
    /**
     * Ensure voucher table exists with dynamic fields support
     * 
     * @return void
     */
    private function ensureTableExists(): void {
        $tableName = $this->getTableName();
        
        // Check if table exists
        $stmt = $this->pdo->query("SHOW TABLES LIKE '$tableName'");
        if ($stmt->rowCount() === 0) {
            $this->createTable();
        } else {
            // Ensure ERP fields exist
            $this->ensureERPFields();
        }
        
        // Load dynamic fields configuration
        $this->loadDynamicFields();
    }
    
    /**
     * Get table name based on voucher type
     * 
     * @return string Table name
     */
    private function getTableName(): string {
        // Backward compatibility: Use old table name for receipts if it exists
        if ($this->voucherType === 'receipt') {
            // Check if old payment_receipts table exists
            try {
                $stmt = $this->pdo->query("SHOW TABLES LIKE 'payment_receipts'");
                if ($stmt && $stmt->rowCount() > 0) {
                    return 'payment_receipts'; // Use old table for backward compatibility
                }
            } catch (Exception $e) {
                // Table doesn't exist, use new table
            }
            return 'receipt_vouchers'; // Use new table
        }
        return 'payment_vouchers';
    }
    
    /**
     * Check if using old table structure (payment_receipts)
     * 
     * @return bool
     */
    private function isOldTableStructure(): bool {
        return $this->getTableName() === 'payment_receipts';
    }
    
    /**
     * Map old column names to new column names
     * 
     * @param string $column Column name
     * @return string|null Mapped column name (null if column doesn't exist in old table)
     */
    private function mapColumnName(string $column): ?string {
        if (!$this->isOldTableStructure()) {
            return $column;
        }
        
        // Map new column names to old column names for payment_receipts table
        $mapping = [
            'voucher_number' => 'receipt_number',
            'voucher_date' => 'payment_date',
            'voucher_type' => null, // Old table doesn't have this
            'vendor_id' => null, // Old table doesn't have this
            'branch_id' => null, // Old table doesn't have this
            'fiscal_period_id' => null, // Old table doesn't have this
            'account_id' => null, // Old table doesn't have this
            'collected_from_account_id' => null, // Old table doesn't have this
            'description' => null, // Old table uses notes
            'posting_status' => null, // Old table doesn't have this
            'is_posted' => null, // Old table doesn't have this
            'is_locked' => null, // Old table doesn't have this
            'is_auto' => null, // Old table doesn't have this
            'journal_entry_id' => null, // Old table doesn't have this
            'source_table' => null, // Old table doesn't have this
            'source_id' => null, // Old table doesn't have this
            'approved_at' => null, // Old table doesn't have this
            'approved_by' => null, // Old table doesn't have this
            'locked_at' => null, // Old table doesn't have this
            'reversed_at' => null, // Old table doesn't have this
            'reversed_by' => null, // Old table doesn't have this
            'reversal_entry_id' => null, // Old table doesn't have this
            'dynamic_fields' => null, // Old table doesn't have this
        ];
        
        return $mapping[$column] ?? $column;
    }
    
    /**
     * Map old status values to new status values
     * 
     * @param string $status Status value
     * @param bool $reverse If true, map new to old
     * @return string Mapped status
     */
    private function mapStatus(string $status, bool $reverse = false): string {
        if (!$this->isOldTableStructure()) {
            return $status;
        }
        
        if ($reverse) {
            // Map new status to old status
            $mapping = [
                'Posted' => 'Cleared',
                'Reversed' => 'Cancelled',
                'Draft' => 'Draft',
                'Cancelled' => 'Cancelled'
            ];
        } else {
            // Map old status to new status
            $mapping = [
                'Cleared' => 'Posted',
                'Deposited' => 'Posted',
                'Draft' => 'Draft',
                'Cancelled' => 'Cancelled'
            ];
        }
        
        return $mapping[$status] ?? $status;
    }
    
    /**
     * Create voucher table with ERP-grade structure
     * 
     * @return void
     */
    private function createTable(): void {
        $tableName = $this->getTableName();
        $prefix = strtoupper(substr($this->voucherType, 0, 1));
        
        $sql = "
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `voucher_number` VARCHAR(50) NOT NULL UNIQUE,
                `voucher_date` DATE NOT NULL,
                `voucher_type` ENUM('receipt', 'payment') NOT NULL DEFAULT '{$this->voucherType}',
                `amount` DECIMAL(15,2) NOT NULL,
                `currency` VARCHAR(3) DEFAULT 'SAR',
                `payment_method` ENUM('Cash', 'Bank Transfer', 'Cheque', 'Credit Card', 'Other') NOT NULL DEFAULT 'Cash',
                `bank_account_id` INT NULL,
                `cheque_number` VARCHAR(50) NULL,
                `reference_number` VARCHAR(100) NULL,
                `customer_id` INT NULL,
                `vendor_id` INT NULL,
                `entity_type` VARCHAR(50) NULL,
                `entity_id` INT NULL,
                `cost_center_id` INT NULL,
                `branch_id` INT NULL,
                `fiscal_period_id` INT NULL,
                `account_id` INT NULL COMMENT 'GL Account ID for Payee/Expense when Payee is GL',
                `source_account_id` INT NULL COMMENT 'GL Account ID for Cash/Bank when source is GL',
                `collected_from_account_id` INT NULL COMMENT 'GL Account ID for Account Collected From',
                `description` TEXT NULL,
                `notes` TEXT NULL,
                `status` ENUM('Draft', 'Posted', 'Reversed', 'Cancelled') DEFAULT 'Draft',
                `posting_status` ENUM('draft', 'posted', 'reversed') DEFAULT 'draft',
                `is_posted` TINYINT(1) DEFAULT 0,
                `is_locked` TINYINT(1) DEFAULT 0,
                `is_auto` TINYINT(1) DEFAULT 0,
                `journal_entry_id` INT NULL COMMENT 'Linked journal entry ID',
                `source_table` VARCHAR(100) DEFAULT '{$tableName}',
                `source_id` INT NULL COMMENT 'Self-reference for reversals',
                `approved_at` TIMESTAMP NULL,
                `approved_by` INT NULL,
                `locked_at` TIMESTAMP NULL,
                `reversed_at` TIMESTAMP NULL,
                `reversed_by` INT NULL,
                `reversal_entry_id` INT NULL COMMENT 'Reversal journal entry ID',
                `created_by` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `dynamic_fields` JSON NULL COMMENT 'Dynamic custom fields',
                INDEX `idx_voucher_number` (`voucher_number`),
                INDEX `idx_voucher_date` (`voucher_date`),
                INDEX `idx_status` (`status`),
                INDEX `idx_posting_status` (`posting_status`),
                INDEX `idx_journal_entry_id` (`journal_entry_id`),
                INDEX `idx_branch_id` (`branch_id`),
                INDEX `idx_fiscal_period_id` (`fiscal_period_id`),
                INDEX `idx_created_by` (`created_by`),
                FOREIGN KEY (`bank_account_id`) REFERENCES `accounting_banks`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
                -- FOREIGN KEY (`fiscal_period_id`) REFERENCES `fiscal_periods`(`id`) ON DELETE SET NULL, -- Commented: table may not exist
                FOREIGN KEY (`account_id`) REFERENCES `financial_accounts`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`source_account_id`) REFERENCES `financial_accounts`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Ensure ERP fields exist in table
     * 
     * @return void
     */
    private function ensureERPFields(): void {
        $tableName = $this->getTableName();
        $columns = [
            'bank_account_id' => "INT NULL COMMENT 'Cash/Bank account from accounting_banks'",
            'posting_status' => "ENUM('draft', 'posted', 'reversed') DEFAULT 'draft'",
            'is_posted' => "TINYINT(1) DEFAULT 0",
            'is_locked' => "TINYINT(1) DEFAULT 0",
            'is_auto' => "TINYINT(1) DEFAULT 0",
            'journal_entry_id' => "INT NULL",
            'source_table' => "VARCHAR(100) NULL",
            'source_id' => "INT NULL",
            'approved_at' => "TIMESTAMP NULL",
            'approved_by' => "INT NULL",
            'locked_at' => "TIMESTAMP NULL",
            'reversed_at' => "TIMESTAMP NULL",
            'reversed_by' => "INT NULL",
            'reversal_entry_id' => "INT NULL",
            'branch_id' => "INT NULL",
            'fiscal_period_id' => "INT NULL",
            'cost_center_id' => "INT NULL",
            'collected_from_account_id' => "INT NULL COMMENT 'GL Account ID for Account Collected From'",
            'source_account_id' => "INT NULL COMMENT 'GL Account ID for Cash/Bank when source is GL'",
            'vat_report' => "VARCHAR(10) DEFAULT '0'",
            'dynamic_fields' => "JSON NULL"
        ];
        
        foreach ($columns as $column => $definition) {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$column}'");
            if ($stmt->rowCount() === 0) {
                $this->pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$column}` {$definition}");
            }
        }
    }
    
    /**
     * Load dynamic fields configuration from database
     * 
     * @return void
     */
    private function loadDynamicFields(): void {
        // Check if dynamic_fields_config table exists
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'voucher_fields_config'");
        if ($stmt->rowCount() === 0) {
            // Create configuration table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `voucher_fields_config` (
                    `id` INT PRIMARY KEY AUTO_INCREMENT,
                    `voucher_type` ENUM('receipt', 'payment') NOT NULL,
                    `field_name` VARCHAR(100) NOT NULL,
                    `field_label` VARCHAR(255) NOT NULL,
                    `field_type` ENUM('text', 'number', 'date', 'select', 'textarea') NOT NULL,
                    `field_options` JSON NULL COMMENT 'Options for select fields',
                    `is_required` TINYINT(1) DEFAULT 0,
                    `display_order` INT DEFAULT 0,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `unique_field` (`voucher_type`, `field_name`),
                    INDEX `idx_voucher_type` (`voucher_type`),
                    INDEX `idx_display_order` (`display_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Load fields for this voucher type
        $stmt = $this->pdo->prepare("
            SELECT * FROM `voucher_fields_config`
            WHERE `voucher_type` = ? AND `is_active` = 1
            ORDER BY `display_order` ASC
        ");
        $stmt->execute([$this->voucherType]);
        $this->dynamicFields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add dynamic field to configuration
     * 
     * @param string $fieldName Field name (snake_case)
     * @param string $fieldLabel Display label
     * @param string $fieldType Field type
     * @param array|null $fieldOptions Options for select fields
     * @param bool $isRequired Is field required
     * @param int $displayOrder Display order
     * @return bool Success status
     */
    public function addDynamicField(
        string $fieldName,
        string $fieldLabel,
        string $fieldType = 'text',
        ?array $fieldOptions = null,
        bool $isRequired = false,
        int $displayOrder = 0
    ): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO `voucher_fields_config`
            (`voucher_type`, `field_name`, `field_label`, `field_type`, `field_options`, `is_required`, `display_order`)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `field_label` = VALUES(`field_label`),
                `field_type` = VALUES(`field_type`),
                `field_options` = VALUES(`field_options`),
                `is_required` = VALUES(`is_required`),
                `display_order` = VALUES(`display_order`),
                `is_active` = 1
        ");
        
        return $stmt->execute([
            $this->voucherType,
            $fieldName,
            $fieldLabel,
            $fieldType,
            $fieldOptions ? json_encode($fieldOptions) : null,
            $isRequired ? 1 : 0,
            $displayOrder
        ]);
    }
    
    /**
     * Remove dynamic field from configuration
     * 
     * @param string $fieldName Field name
     * @return bool Success status
     */
    public function removeDynamicField(string $fieldName): bool {
        $stmt = $this->pdo->prepare("
            UPDATE `voucher_fields_config`
            SET `is_active` = 0
            WHERE `voucher_type` = ? AND `field_name` = ?
        ");
        
        return $stmt->execute([$this->voucherType, $fieldName]);
    }
    
    /**
     * Generate unique voucher number
     *
     * Format: RC00001, RC00002, ... (Receipt) or PY00001, PY00002, ... (Payment)
     *
     * @return string Voucher number
     */
    private function generateVoucherNumber(): string {
        $prefix = $this->voucherType === 'receipt' ? 'RC' : 'PY';
        $tableName = $this->getTableName();
        $voucherNumberColumn = $this->isOldTableStructure() ? 'receipt_number' : 'voucher_number';
        $prefixLen = strlen($prefix);

        $pattern = $prefix . '%';
        $stmt = $this->pdo->prepare("
            SELECT `{$voucherNumberColumn}` as num
            FROM `{$tableName}`
            WHERE `{$voucherNumberColumn}` LIKE ?
        ");
        $stmt->execute([$pattern]);
        $maxNum = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $val = $row['num'];
            $suffix = substr($val, $prefixLen);
            if ($suffix !== '' && ctype_digit($suffix)) {
                $n = (int) $suffix;
                if ($n > $maxNum && $n < 100000) {
                    $maxNum = $n;
                }
            }
        }

        $nextNum = $maxNum + 1;
        return $prefix . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Renumber all voucher numbers in this table to PY00001, PY00002... or RC00001, RC00002...
     * Ordered by id ASC. Run once to migrate from old PV-/RV- format.
     *
     * @return array ['success' => bool, 'updated' => int, 'message' => string]
     */
    public function renumberVoucherNumbers(): array {
        $prefix = $this->voucherType === 'receipt' ? 'RC' : 'PY';
        $tableName = $this->getTableName();
        $voucherNumberColumn = $this->isOldTableStructure() ? 'receipt_number' : 'voucher_number';
        $idColumn = 'id';
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("SELECT `{$idColumn}` FROM `{$tableName}` ORDER BY `{$idColumn}` ASC");
            $stmt->execute();
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $updateStmt = $this->pdo->prepare("UPDATE `{$tableName}` SET `{$voucherNumberColumn}` = ? WHERE `{$idColumn}` = ?");
            $seq = 1;
            foreach ($ids as $id) {
                $newNum = $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
                $updateStmt->execute([$newNum, $id]);
                $seq++;
            }
            $this->pdo->commit();
            return ['success' => true, 'updated' => count($ids), 'message' => 'Voucher numbers renumbered to ' . $prefix . '00001 format.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("ReceiptPaymentVoucherManager::renumberVoucherNumbers() Error: " . $e->getMessage());
            return ['success' => false, 'updated' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create new voucher
     * 
     * @param array $data Voucher data
     * @return array ['success' => bool, 'voucher_id' => int|null, 'voucher_number' => string|null, 'message' => string]
     */
    public function create(array $data): array {
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Validate required fields
            $this->validateCreateData($data);
            
            // Generate voucher number if not provided
            $voucherNumber = $data['voucher_number'] ?? $this->generateVoucherNumber();
            
            // Extract dynamic fields
            $dynamicFields = $this->extractDynamicFields($data);
            
            // Convert voucher_date from MM/DD/YYYY to YYYY-MM-DD for database
            if (isset($data['voucher_date'])) {
                $data['voucher_date'] = formatDateForDatabase($data['voucher_date']);
            }
            
            // Get fiscal period
            $fiscalPeriodId = null;
            if (isset($data['voucher_date'])) {
                $fiscalPeriodId = $this->getFiscalPeriodId($data['voucher_date']);
            }
            
            // Prepare insert data
            $status = $data['status'] ?? 'Draft';
            $status = $this->mapStatus($status, true); // Map to old status if using old table
            
            // Use correct column names based on table structure
            if ($this->isOldTableStructure()) {
                // Old table structure uses receipt_number and payment_date
                // Check which columns exist in the old table
                $tableName = $this->getTableName();
                $existingColumns = [];
                try {
                    $columnsStmt = $this->pdo->query("SHOW COLUMNS FROM `{$tableName}`");
                    while ($col = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
                        $existingColumns[] = $col['Field'];
                    }
                } catch (Exception $e) {
                    // If we can't check columns, use safe defaults
                }
                
                $insertData = [
                    'receipt_number' => $voucherNumber,
                    'payment_date' => $data['voucher_date'] ?? date('Y-m-d'),
                    'amount' => round(floatval($data['amount'] ?? 0), 2),
                    'currency' => $data['currency'] ?? 'SAR',
                    'payment_method' => $data['payment_method'] ?? 'Cash',
                    'bank_account_id' => $data['bank_account_id'] ?? null,
                    'cheque_number' => $data['cheque_number'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'customer_id' => $data['customer_id'] ?? null,
                    'entity_type' => $data['entity_type'] ?? null,
                    'entity_id' => $data['entity_id'] ?? null,
                    'cost_center_id' => $data['cost_center_id'] ?? null,
                    'status' => $status,
                    'notes' => $data['notes'] ?? $data['description'] ?? null,
                    'created_by' => $this->userId
                ];
                // Add collected_from_account_id for GL "Account Collected From" when column exists
                if (in_array('collected_from_account_id', $existingColumns)) {
                    $insertData['collected_from_account_id'] = $data['collected_from_account_id'] ?? null;
                }
                
                // Only add vat_report if column exists in table, otherwise add it
                if (isset($data['vat_report'])) {
                    if (in_array('vat_report', $existingColumns)) {
                        $insertData['vat_report'] = $data['vat_report'];
                    } else {
                        // Column doesn't exist, try to add it first
                        try {
                            // Check if cost_center_id column exists to determine position
                            $afterColumn = in_array('cost_center_id', $existingColumns) ? 'cost_center_id' : 'status';
                            $this->pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `vat_report` VARCHAR(10) DEFAULT '0' AFTER `{$afterColumn}`");
                            $insertData['vat_report'] = $data['vat_report'];
                            // Update existingColumns array for next operations
                            $existingColumns[] = 'vat_report';
                        } catch (Exception $e) {
                            // If we can't add the column, skip it (don't fail the insert)
                            error_log("Could not add vat_report column: " . $e->getMessage());
                            // Don't add vat_report to insertData
                        }
                    }
                }
            } else {
                // New table structure
                $insertData = [
                    'voucher_number' => $voucherNumber,
                    'voucher_date' => $data['voucher_date'] ?? date('Y-m-d'),
                    'voucher_type' => $this->voucherType,
                    'amount' => round(floatval($data['amount'] ?? 0), 2),
                    'currency' => $data['currency'] ?? 'SAR',
                    'payment_method' => $data['payment_method'] ?? 'Cash',
                    'bank_account_id' => $data['bank_account_id'] ?? null,
                    'cheque_number' => $data['cheque_number'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'customer_id' => $data['customer_id'] ?? null,
                    'vendor_id' => $data['vendor_id'] ?? null,
                    'entity_type' => $data['entity_type'] ?? null,
                    'entity_id' => $data['entity_id'] ?? null,
                    'cost_center_id' => $data['cost_center_id'] ?? null,
                    'branch_id' => $data['branch_id'] ?? null,
                    'fiscal_period_id' => $fiscalPeriodId,
                    'account_id' => $data['account_id'] ?? null,
                    'source_account_id' => $data['source_account_id'] ?? null,
                    'collected_from_account_id' => $data['collected_from_account_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'status' => $status,
                    'posting_status' => 'draft',
                    'is_posted' => 0,
                    'is_locked' => 0,
                    'is_auto' => 0,
                    'source_table' => $this->getTableName(),
                    'dynamic_fields' => !empty($dynamicFields) ? json_encode($dynamicFields) : null,
                    'created_by' => $this->userId
                ];
            }
            
            // Build INSERT query
            $columns = array_keys($insertData);
            $placeholders = array_fill(0, count($columns), '?');
            $tableName = $this->getTableName();
            
            // Filter out null values for columns that might not exist in old table
            if ($this->isOldTableStructure()) {
                $filteredInsertData = [];
                $filteredColumns = [];
                foreach ($insertData as $col => $val) {
                    if (in_array($col, $existingColumns)) {
                        $filteredColumns[] = $col;
                        $filteredInsertData[] = $val;
                    }
                }
                $columns = $filteredColumns;
                $placeholders = array_fill(0, count($columns), '?');
            }
            
            $sql = "INSERT INTO `{$tableName}` (" . implode(', ', array_map(function($col) { return "`{$col}`"; }, $columns)) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->pdo->prepare($sql);
            
            $values = $this->isOldTableStructure() && isset($filteredInsertData) ? $filteredInsertData : array_values($insertData);
            $stmt->execute($values);
            
            $voucherId = (int)$this->pdo->lastInsertId();
            
            // Note: ERP Guardian validation happens on POST, not CREATE
            // Vouchers are created as Draft, journal entry created when posting
            
            // Log audit trail
            $this->logAuditTrail('CREATE', $voucherId, null, $insertData, "Voucher created: {$voucherNumber}");
            
            // Commit transaction
            $this->pdo->commit();
            
            return [
                'success' => true,
                'voucher_id' => $voucherId,
                'voucher_number' => $voucherNumber,
                'correlation_id' => $this->correlationId,
                'message' => ucfirst($this->voucherType) . ' voucher created successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("ReceiptPaymentVoucherManager::create() Error: " . $e->getMessage());
            return [
                'success' => false,
                'voucher_id' => null,
                'voucher_number' => null,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update voucher
     * 
     * @param int $voucherId Voucher ID
     * @param array $data Update data
     * @return array ['success' => bool, 'message' => string]
     */
    public function update(int $voucherId, array $data): array {
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Get existing voucher
            $existing = $this->get($voucherId);
            if (!$existing['success']) {
                throw new Exception('Voucher not found');
            }
            
            $oldData = $existing['voucher'];
            
            // ERP GUARDIAN: Check if can edit
            $this->guardPostedEntryModification($voucherId, 'UPDATE');
            
            // Validate update data
            $this->validateUpdateData($data, $oldData);
            
            // Extract dynamic fields
            $dynamicFields = $this->extractDynamicFields($data);
            
            // Convert voucher_date from MM/DD/YYYY to YYYY-MM-DD for database if provided
            if (isset($data['voucher_date'])) {
                $data['voucher_date'] = formatDateForDatabase($data['voucher_date']);
            }
            // Normalize amount to 2 decimals for consistent storage
            if (array_key_exists('amount', $data)) {
                $data['amount'] = round(floatval($data['amount']), 2);
            }
            
            // Get fiscal period if date changed
            if (isset($data['voucher_date']) && $data['voucher_date'] !== $oldData['voucher_date']) {
                $data['fiscal_period_id'] = $this->getFiscalPeriodId($data['voucher_date']);
            }
            
            // Prepare update data
            $updateData = [];
            $tableName = $this->getTableName();
            
            // Get existing columns for old table
            $existingColumns = [];
            if ($this->isOldTableStructure()) {
                try {
                    $columnsStmt = $this->pdo->query("SHOW COLUMNS FROM `{$tableName}`");
                    while ($col = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
                        $existingColumns[] = $col['Field'];
                    }
                } catch (Exception $e) {
                    // If we can't check columns, use safe defaults
                }
            }
            
            // Map fields based on table structure
            $fieldMappings = [
                'voucher_date' => $this->isOldTableStructure() ? 'payment_date' : 'voucher_date',
                'amount' => 'amount',
                'currency' => 'currency',
                'payment_method' => 'payment_method',
                'bank_account_id' => 'bank_account_id',
                'vendor_id' => 'vendor_id',
                'account_id' => 'account_id',
                'source_account_id' => 'source_account_id',
                'cheque_number' => 'cheque_number',
                'reference_number' => 'reference_number',
                'customer_id' => 'customer_id',
                'collected_from_account_id' => 'collected_from_account_id',
                'entity_type' => 'entity_type',
                'entity_id' => 'entity_id',
                'cost_center_id' => 'cost_center_id',
                'notes' => 'notes',
                'status' => 'status',
                'vat_report' => 'vat_report'
            ];
            
            // Only include fields that exist in the table
            // Use array_key_exists instead of isset to handle null values correctly
            foreach ($fieldMappings as $dataField => $dbField) {
                if (array_key_exists($dataField, $data)) {
                    if ($this->isOldTableStructure()) {
                        // For old table, only include if column exists
                        if (in_array($dbField, $existingColumns)) {
                            $updateData[$dbField] = $data[$dataField];
                        } else if ($dbField === 'vat_report') {
                            try {
                                $this->pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `vat_report` VARCHAR(10) DEFAULT '0' AFTER `cost_center_id`");
                                $updateData[$dbField] = $data[$dataField];
                            } catch (Exception $e) {
                                error_log("Could not add vat_report column: " . $e->getMessage());
                            }
                        } else if ($dbField === 'account_id') {
                            try {
                                $this->pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `account_id` INT NULL AFTER `bank_account_id`");
                                $updateData[$dbField] = $data[$dataField];
                            } catch (Exception $e) {
                                error_log("Could not add account_id column: " . $e->getMessage());
                            }
                        } else if ($dbField === 'collected_from_account_id') {
                            try {
                                $this->pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `collected_from_account_id` INT NULL AFTER `customer_id`");
                                $updateData[$dbField] = $data[$dataField];
                            } catch (Exception $e) {
                                error_log("Could not add collected_from_account_id column: " . $e->getMessage());
                            }
                        } else if ($dbField === 'source_account_id') {
                            try {
                                $afterCol = in_array('account_id', $existingColumns) ? 'account_id' : 'bank_account_id';
                                $this->pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `source_account_id` INT NULL AFTER `{$afterCol}`");
                                $updateData[$dbField] = $data[$dataField];
                            } catch (Exception $e) {
                                error_log("Could not add source_account_id column: " . $e->getMessage());
                            }
                        }
                    } else {
                        // New table structure - include all fields
                        $updateData[$dbField] = $data[$dataField];
                    }
                }
            }
            
            // Add dynamic fields (only for new table structure)
            if (!$this->isOldTableStructure() && !empty($dynamicFields)) {
                $updateData['dynamic_fields'] = json_encode($dynamicFields);
            }
            
            if (empty($updateData)) {
                throw new Exception('No fields to update');
            }
            
            // Build UPDATE query
            $setClause = [];
            $values = [];
            foreach ($updateData as $field => $value) {
                $setClause[] = "`{$field}` = ?";
                $values[] = $value;
            }
            $values[] = $voucherId;
            
            $tableName = $this->getTableName();
            $sql = "UPDATE `{$tableName}` SET " . implode(', ', $setClause) . " WHERE `id` = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            
            // Log audit trail
            $this->logAuditTrail('UPDATE', $voucherId, $oldData, $updateData, "Voucher updated: {$oldData['voucher_number']}");
            
            // Commit transaction
            $this->pdo->commit();
            
            return [
                'success' => true,
                'correlation_id' => $this->correlationId,
                'message' => ucfirst($this->voucherType) . ' voucher updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("ReceiptPaymentVoucherManager::update() Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Post voucher to General Ledger
     * 
     * @param int $voucherId Voucher ID
     * @return array ['success' => bool, 'journal_entry_id' => int|null, 'message' => string]
     */
    public function post(int $voucherId): array {
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Get voucher
            $voucher = $this->get($voucherId);
            if (!$voucher['success']) {
                throw new Exception('Voucher not found');
            }
            
            $voucherData = $voucher['voucher'];
            
            // ERP GUARDIAN: Check if can post
            if ($voucherData['is_posted'] == 1 || $voucherData['posting_status'] === 'posted') {
                throw new Exception('Voucher is already posted');
            }
            
            // Validate posting date
            $this->validatePostingDate($voucherData['voucher_date']);
            
            // Create journal entry
            $journalEntryId = $this->createJournalEntry($voucherData);
            
            // Update voucher status
            $tableName = $this->getTableName();
            $stmt = $this->pdo->prepare("
                UPDATE `{$tableName}`
                SET `status` = 'Posted',
                    `posting_status` = 'posted',
                    `is_posted` = 1,
                    `is_locked` = 1,
                    `journal_entry_id` = ?,
                    `locked_at` = NOW(),
                    `approved_at` = NOW(),
                    `approved_by` = ?
                WHERE `id` = ?
            ");
            $stmt->execute([$journalEntryId, $this->userId, $voucherId]);
            
            // ERP GUARDIAN: Validate journal entry was created
            $this->guardFinancialAction('create', $journalEntryId);
            
            // Post to General Ledger
            $ledgerResult = $this->postJournalEntryToLedger($journalEntryId);
            
            if (!$ledgerResult['success']) {
                throw new Exception('Failed to post to General Ledger: ' . $ledgerResult['message']);
            }
            
            // Log audit trail
            $this->logAuditTrail('POST', $voucherId, $voucherData, ['journal_entry_id' => $journalEntryId], "Voucher posted: {$voucherData['voucher_number']}");
            
            // Commit transaction
            $this->pdo->commit();
            
            return [
                'success' => true,
                'journal_entry_id' => $journalEntryId,
                'correlation_id' => $this->correlationId,
                'message' => ucfirst($this->voucherType) . ' voucher posted successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("ReceiptPaymentVoucherManager::post() Error: " . $e->getMessage());
            return [
                'success' => false,
                'journal_entry_id' => null,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reverse posted voucher
     * 
     * @param int $voucherId Voucher ID
     * @param string|null $reversalDate Reversal date (defaults to today)
     * @param string|null $description Reversal description
     * @return array ['success' => bool, 'reversal_entry_id' => int|null, 'message' => string]
     */
    public function reverse(int $voucherId, ?string $reversalDate = null, ?string $description = null): array {
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Get voucher
            $voucher = $this->get($voucherId);
            if (!$voucher['success']) {
                throw new Exception('Voucher not found');
            }
            
            $voucherData = $voucher['voucher'];
            
            // ERP GUARDIAN: Check if can reverse
            if ($voucherData['is_posted'] != 1 && $voucherData['posting_status'] !== 'posted') {
                throw new Exception('Can only reverse posted vouchers');
            }
            
            if ($voucherData['posting_status'] === 'reversed') {
                throw new Exception('Voucher is already reversed');
            }
            
            if (!$voucherData['journal_entry_id']) {
                throw new Exception('Voucher has no journal entry to reverse');
            }
            
            // Validate reversal date
            $reversalDate = $reversalDate ?? date('Y-m-d');
            $this->validatePostingDate($reversalDate);
            
            // Create reversal entry
            $reversalResult = $this->createReversalEntry(
                $voucherData['journal_entry_id'],
                $reversalDate,
                $description ?? "Reversal of {$this->voucherType} voucher: {$voucherData['voucher_number']}"
            );
            
            if (!$reversalResult['success']) {
                throw new Exception('Failed to create reversal entry: ' . $reversalResult['message']);
            }
            
            // Update voucher status
            $tableName = $this->getTableName();
            $stmt = $this->pdo->prepare("
                UPDATE `{$tableName}`
                SET `status` = 'Reversed',
                    `posting_status` = 'reversed',
                    `reversal_entry_id` = ?,
                    `reversed_at` = NOW(),
                    `reversed_by` = ?
                WHERE `id` = ?
            ");
            $stmt->execute([$reversalResult['reversal_entry_id'], $this->userId, $voucherId]);
            
            // Log audit trail
            $this->logAuditTrail('REVERSE', $voucherId, $voucherData, ['reversal_entry_id' => $reversalResult['reversal_entry_id']], "Voucher reversed: {$voucherData['voucher_number']}");
            
            // Commit transaction
            $this->pdo->commit();
            
            return [
                'success' => true,
                'reversal_entry_id' => $reversalResult['reversal_entry_id'],
                'correlation_id' => $this->correlationId,
                'message' => ucfirst($this->voucherType) . ' voucher reversed successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("ReceiptPaymentVoucherManager::reverse() Error: " . $e->getMessage());
            return [
                'success' => false,
                'reversal_entry_id' => null,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get single voucher
     * 
     * @param int $voucherId Voucher ID
     * @return array ['success' => bool, 'voucher' => array|null, 'message' => string]
     */
    public function get(int $voucherId): array {
        try {
            $tableName = $this->getTableName();
            
            // Build SELECT query with column mapping for old table
            if ($this->isOldTableStructure()) {
                $existingCols = [];
                try {
                    $colsStmt = $this->pdo->query("SHOW COLUMNS FROM `{$tableName}`");
                    while ($row = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
                        $existingCols[] = $row['Field'];
                    }
                } catch (Exception $e) {
                    // ignore
                }
                $accountIdSel = in_array('account_id', $existingCols)
                    ? 'account_id' : 'NULL as account_id';
                $collectedFromSel = in_array('collected_from_account_id', $existingCols)
                    ? 'collected_from_account_id' : 'NULL as collected_from_account_id';
                $vatReportSel = in_array('vat_report', $existingCols)
                    ? 'vat_report' : "NULL as vat_report";
                // Map old column names to new column names in SELECT
                $stmt = $this->pdo->prepare("
                    SELECT 
                        id,
                        receipt_number as voucher_number,
                        payment_date as voucher_date,
                        'receipt' as voucher_type,
                        amount,
                        currency,
                        payment_method,
                        bank_account_id,
                        cheque_number,
                        reference_number,
                        customer_id,
                        NULL as vendor_id,
                        entity_type,
                        entity_id,
                        cost_center_id,
                        NULL as branch_id,
                        NULL as fiscal_period_id,
                        {$accountIdSel},
                        {$collectedFromSel},
                        notes as description,
                        notes,
                        status,
                        {$vatReportSel},
                        CASE 
                            WHEN status IN ('Cleared', 'Deposited') THEN 'posted'
                            WHEN status = 'Cancelled' THEN 'reversed'
                            ELSE 'draft'
                        END as posting_status,
                        CASE WHEN status IN ('Cleared', 'Deposited') THEN 1 ELSE 0 END as is_posted,
                        0 as is_locked,
                        0 as is_auto,
                        NULL as journal_entry_id,
                        '{$tableName}' as source_table,
                        NULL as source_id,
                        NULL as approved_at,
                        NULL as approved_by,
                        NULL as locked_at,
                        NULL as reversed_at,
                        NULL as reversed_by,
                        NULL as reversal_entry_id,
                        created_by,
                        created_at,
                        updated_at,
                        NULL as dynamic_fields
                    FROM `{$tableName}` 
                    WHERE id = ?
                ");
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM `{$tableName}` WHERE `id` = ?");
            }
            
            $stmt->execute([$voucherId]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$voucher) {
                return [
                    'success' => false,
                    'voucher' => null,
                    'message' => 'Voucher not found'
                ];
            }
            
            // Map status from old to new format
            if ($this->isOldTableStructure()) {
                $voucher['status'] = $this->mapStatus($voucher['status']);
            }
            
            // Decode dynamic fields
            if (!empty($voucher['dynamic_fields'])) {
                $voucher['dynamic_fields'] = json_decode($voucher['dynamic_fields'], true);
            } else {
                $voucher['dynamic_fields'] = [];
            }
            
            // Format dates in voucher for display
            if (is_array($voucher)) {
                $voucher = formatDatesInArray($voucher);
            }
            
            return [
                'success' => true,
                'voucher' => $voucher,
                'message' => 'Voucher retrieved successfully'
            ];
            
        } catch (Exception $e) {
            error_log("ReceiptPaymentVoucherManager::get() Error: " . $e->getMessage());
            return [
                'success' => false,
                'voucher' => null,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * List vouchers with filters
     * 
     * @param array $filters Filters (date_from, date_to, status, etc.)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array ['success' => bool, 'vouchers' => array, 'total' => int, 'page' => int, 'per_page' => int]
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 50): array {
        try {
            $tableName = $this->getTableName();
            
            // Build WHERE clause
            if ($this->isOldTableStructure()) {
                $where = ["1=1"]; // Old table doesn't have voucher_type
                $params = [];
            } else {
                $where = ["`voucher_type` = ?"];
                $params = [$this->voucherType];
            }
            
            // Apply filters
            if (isset($filters['date_from'])) {
                $dateColumn = $this->isOldTableStructure() ? 'payment_date' : 'voucher_date';
                $where[] = "`{$dateColumn}` >= ?";
                $params[] = $filters['date_from'];
            }
            if (isset($filters['date_to'])) {
                $dateColumn = $this->isOldTableStructure() ? 'payment_date' : 'voucher_date';
                $where[] = "`{$dateColumn}` <= ?";
                $params[] = $filters['date_to'];
            }
            if (isset($filters['status'])) {
                // Map status filter for old table
                $statusFilter = $filters['status'];
                if ($this->isOldTableStructure()) {
                    // Map new status to old status for filtering
                    $statusMap = ['Posted' => ['Cleared', 'Deposited'], 'Reversed' => 'Cancelled', 'Draft' => 'Draft'];
                    if (isset($statusMap[$statusFilter])) {
                        if (is_array($statusMap[$statusFilter])) {
                            $statusFilter = $statusMap[$statusFilter];
                            $placeholders = implode(',', array_fill(0, count($statusFilter), '?'));
                            $where[] = "`status` IN ({$placeholders})";
                            $params = array_merge($params, $statusFilter);
                        } else {
                            $where[] = "`status` = ?";
                            $params[] = $statusFilter;
                        }
                    } else {
                        $where[] = "`status` = ?";
                        $params[] = $statusFilter;
                    }
                } else {
                    $where[] = "`status` = ?";
                    $params[] = $statusFilter;
                }
            }
            if (isset($filters['posting_status']) && !$this->isOldTableStructure()) {
                $where[] = "`posting_status` = ?";
                $params[] = $filters['posting_status'];
            }
            if (isset($filters['customer_id'])) {
                $where[] = "`customer_id` = ?";
                $params[] = $filters['customer_id'];
            }
            if (isset($filters['vendor_id']) && !$this->isOldTableStructure()) {
                $where[] = "`vendor_id` = ?";
                $params[] = $filters['vendor_id'];
            }
            if (isset($filters['branch_id']) && !$this->isOldTableStructure()) {
                $where[] = "`branch_id` = ?";
                $params[] = $filters['branch_id'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Build SELECT query
            if ($this->isOldTableStructure()) {
                $selectQuery = "
                    SELECT 
                        id,
                        receipt_number as voucher_number,
                        payment_date as voucher_date,
                        'receipt' as voucher_type,
                        amount,
                        currency,
                        payment_method,
                        bank_account_id,
                        cheque_number,
                        reference_number,
                        customer_id,
                        NULL as vendor_id,
                        entity_type,
                        entity_id,
                        cost_center_id,
                        NULL as branch_id,
                        NULL as fiscal_period_id,
                        NULL as account_id,
                        NULL as collected_from_account_id,
                        notes as description,
                        notes,
                        status,
                        CASE 
                            WHEN status IN ('Cleared', 'Deposited') THEN 'posted'
                            WHEN status = 'Cancelled' THEN 'reversed'
                            ELSE 'draft'
                        END as posting_status,
                        CASE WHEN status IN ('Cleared', 'Deposited') THEN 1 ELSE 0 END as is_posted,
                        0 as is_locked,
                        0 as is_auto,
                        NULL as journal_entry_id,
                        '{$tableName}' as source_table,
                        NULL as source_id,
                        NULL as approved_at,
                        NULL as approved_by,
                        NULL as locked_at,
                        NULL as reversed_at,
                        NULL as reversed_by,
                        NULL as reversal_entry_id,
                        created_by,
                        created_at,
                        updated_at,
                        NULL as dynamic_fields
                    FROM `{$tableName}`
                    WHERE {$whereClause}
                    ORDER BY payment_date DESC, created_at DESC
                    LIMIT ? OFFSET ?
                ";
            } else {
                $selectQuery = "
                    SELECT * FROM `{$tableName}`
                    WHERE {$whereClause}
                    ORDER BY `voucher_date` DESC, `created_at` DESC
                    LIMIT ? OFFSET ?
                ";
            }
            
            // Get total count (use current $params - LIMIT/OFFSET not added yet)
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM `{$tableName}` WHERE {$whereClause}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get paginated results (append LIMIT and OFFSET to params)
            $offset = ($page - 1) * $perPage;
            $stmt = $this->pdo->prepare($selectQuery);
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);
            $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Map status, decode dynamic fields, and format dates
            foreach ($vouchers as &$voucher) {
                if ($this->isOldTableStructure()) {
                    $voucher['status'] = $this->mapStatus($voucher['status']);
                }
                if (!empty($voucher['dynamic_fields'])) {
                    $voucher['dynamic_fields'] = json_decode($voucher['dynamic_fields'], true);
                } else {
                    $voucher['dynamic_fields'] = [];
                }
                // Format dates for display
                $voucher = formatDatesInArray($voucher);
            }
            unset($voucher);
            
            return [
                'success' => true,
                'vouchers' => $vouchers,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
            
        } catch (Exception $e) {
            error_log("ReceiptPaymentVoucherManager::list() Error: " . $e->getMessage());
            return [
                'success' => false,
                'vouchers' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete voucher (only if draft)
     * 
     * @param int $voucherId Voucher ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function delete(int $voucherId): array {
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Get voucher
            $voucher = $this->get($voucherId);
            if (!$voucher['success']) {
                throw new Exception('Voucher not found');
            }
            
            $voucherData = $voucher['voucher'];
            
            // ERP GUARDIAN: Check if can delete
            $this->guardPostedEntryModification($voucherId, 'DELETE');
            
            // Delete journal entry if exists and not posted
            if ($voucherData['journal_entry_id'] && $voucherData['is_posted'] != 1) {
                $stmt = $this->pdo->prepare("DELETE FROM `journal_entry_lines` WHERE `journal_entry_id` = ?");
                $stmt->execute([$voucherData['journal_entry_id']]);
                
                $stmt = $this->pdo->prepare("DELETE FROM `journal_entries` WHERE `id` = ?");
                $stmt->execute([$voucherData['journal_entry_id']]);
            }
            
            // Delete voucher
            $tableName = $this->getTableName();
            $stmt = $this->pdo->prepare("DELETE FROM `{$tableName}` WHERE `id` = ?");
            $stmt->execute([$voucherId]);
            
            // Log audit trail
            $this->logAuditTrail('DELETE', $voucherId, $voucherData, null, "Voucher deleted: {$voucherData['voucher_number']}");
            
            // Commit transaction
            $this->pdo->commit();
            
            return [
                'success' => true,
                'correlation_id' => $this->correlationId,
                'message' => ucfirst($this->voucherType) . ' voucher deleted successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("ReceiptPaymentVoucherManager::delete() Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create journal entry for voucher
     * 
     * @param array $voucherData Voucher data
     * @return int Journal entry ID
     * @throws Exception On failure
     */
    private function createJournalEntry(array $voucherData): int {
        // Determine accounts based on voucher type
        $cashAccountId = $this->getCashAccountId($voucherData);
        $counterAccountId = $this->getCounterAccountId($voucherData);
        
        if (!$cashAccountId || !$counterAccountId) {
            throw new Exception('Required accounts not found');
        }
        
        // Generate journal entry number
        $entryNumber = $this->generateJournalEntryNumber();
        
        // Get fiscal period
        $fiscalPeriodId = $voucherData['fiscal_period_id'] ?? $this->getFiscalPeriodId($voucherData['voucher_date']);
        
        // Determine debit/credit based on voucher type
        if ($this->voucherType === 'receipt') {
            // Receipt: Debit Cash, Credit Revenue/AR
            $debitAccountId = $cashAccountId;
            $creditAccountId = $counterAccountId;
            $debitAmount = $voucherData['amount'];
            $creditAmount = $voucherData['amount'];
        } else {
            // Payment: Debit Expense/AP, Credit Cash
            $debitAccountId = $counterAccountId;
            $creditAccountId = $cashAccountId;
            $debitAmount = $voucherData['amount'];
            $creditAmount = $voucherData['amount'];
        }
        
        // Create journal entry header
        $stmt = $this->pdo->prepare("
            INSERT INTO `journal_entries` (
                `entry_number`, `entry_date`, `description`, `entry_type`,
                `total_debit`, `total_credit`, `status`, `posting_status`,
                `is_posted`, `is_locked`, `is_auto`, `source_table`, `source_id`,
                `currency`, `branch_id`, `fiscal_period_id`, `cost_center_id`,
                `locked_at`, `approved_at`, `approved_by`, `created_by`
            ) VALUES (?, ?, ?, ?, ?, ?, 'Posted', 'posted', 1, 1, 1, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)
        ");
        
        $description = ucfirst($this->voucherType) . " Voucher: {$voucherData['voucher_number']}";
        if (!empty($voucherData['description'])) {
            $description .= " - {$voucherData['description']}";
        }
        
        $stmt->execute([
            $entryNumber,
            $voucherData['voucher_date'],
            $description,
            'Automatic',
            $debitAmount,
            $creditAmount,
            $this->getTableName(),
            $voucherData['id'],
            $voucherData['currency'] ?? 'SAR',
            $voucherData['branch_id'] ?? null,
            $fiscalPeriodId,
            $voucherData['cost_center_id'] ?? null,
            $this->userId,
            $this->userId
        ]);
        
        $journalEntryId = (int)$this->pdo->lastInsertId();
        
        // Create journal entry lines
        // Line 1: Debit
        $stmt = $this->pdo->prepare("
            INSERT INTO `journal_entry_lines` (
                `journal_entry_id`, `account_id`, `debit_amount`, `credit_amount`,
                `description`, `line_order`, `cost_center_id`
            ) VALUES (?, ?, ?, 0, ?, 1, ?)
        ");
        $stmt->execute([
            $journalEntryId,
            $debitAccountId,
            $debitAmount,
            $description,
            $voucherData['cost_center_id'] ?? null
        ]);
        
        // Line 2: Credit
        $stmt = $this->pdo->prepare("
            INSERT INTO `journal_entry_lines` (
                `journal_entry_id`, `account_id`, `debit_amount`, `credit_amount`,
                `description`, `line_order`, `cost_center_id`
            ) VALUES (?, ?, 0, ?, ?, 2, ?)
        ");
        $stmt->execute([
            $journalEntryId,
            $creditAccountId,
            $creditAmount,
            $description,
            $voucherData['cost_center_id'] ?? null
        ]);
        
        return $journalEntryId;
    }
    
    /**
     * Get cash account ID based on payment method
     * 
     * @param array $voucherData Voucher data
     * @return int|null Account ID
     */
    private function getCashAccountId(array $voucherData): ?int {
        $paymentMethod = $voucherData['payment_method'] ?? 'Cash';
        
        if ($paymentMethod === 'Cash') {
            // Get Cash account (1100)
            $stmt = $this->pdo->prepare("SELECT `id` FROM `financial_accounts` WHERE `account_code` = '1100' AND `is_active` = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['id'] : null;
        } elseif ($paymentMethod === 'Bank Transfer' && $voucherData['bank_account_id']) {
            // Get bank's GL account
            $stmt = $this->pdo->prepare("SELECT `account_id` FROM `accounting_banks` WHERE `id` = ?");
            $stmt->execute([$voucherData['bank_account_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['account_id'] ? (int)$result['account_id'] : null;
        }
        
        // Default to Cash account
        $stmt = $this->pdo->prepare("SELECT `id` FROM `financial_accounts` WHERE `account_code` = '1100' AND `is_active` = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }
    
    /**
     * Get counter account ID (Revenue/AR for receipts, Expense/AP for payments)
     * 
     * @param array $voucherData Voucher data
     * @return int|null Account ID
     */
    private function getCounterAccountId(array $voucherData): ?int {
        // Use provided account_id if available
        if (!empty($voucherData['account_id'])) {
            return (int)$voucherData['account_id'];
        }
        
        if ($this->voucherType === 'receipt') {
            // Receipt: Use Accounts Receivable or Revenue
            if ($voucherData['customer_id']) {
                // Get AR account (1200)
                $stmt = $this->pdo->prepare("SELECT `id` FROM `financial_accounts` WHERE `account_code` = '1200' AND `is_active` = 1 LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) return (int)$result['id'];
            }
            
            // Default to Revenue account (4000)
            $stmt = $this->pdo->prepare("SELECT `id` FROM `financial_accounts` WHERE `account_code` LIKE '4%' AND `account_type` = 'REVENUE' AND `is_active` = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['id'] : null;
        } else {
            // Payment: Use Accounts Payable or Expense
            if ($voucherData['vendor_id']) {
                // Get AP account (2000)
                $stmt = $this->pdo->prepare("SELECT `id` FROM `financial_accounts` WHERE `account_code` = '2000' AND `is_active` = 1 LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) return (int)$result['id'];
            }
            
            // Default to Expense account (5000)
            $stmt = $this->pdo->prepare("SELECT `id` FROM `financial_accounts` WHERE `account_code` LIKE '5%' AND `account_type` = 'EXPENSE' AND `is_active` = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['id'] : null;
        }
    }
    
    /**
     * Generate journal entry number
     * 
     * @return string Entry number
     */
    private function generateJournalEntryNumber(): string {
        $stmt = $this->pdo->query("SELECT MAX(CAST(SUBSTRING(`entry_number`, 4) AS UNSIGNED)) as max_num FROM `journal_entries` WHERE `entry_number` LIKE 'JE-%'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($result['max_num'] ?? 0) + 1;
        return 'JE-' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get fiscal period ID for date
     * 
     * @param string $date Date (YYYY-MM-DD)
     * @return int|null Period ID
     */
    private function getFiscalPeriodId(string $date): ?int {
        try {
            // Check if fiscal_periods table exists
            $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'fiscal_periods'");
            if ($tableCheck->rowCount() === 0) {
                return null; // Table doesn't exist, return null gracefully
            }
            
            $stmt = $this->pdo->prepare("
                SELECT `id` FROM `fiscal_periods`
                WHERE ? >= `start_date` AND ? <= `end_date` AND `is_active` = 1
                LIMIT 1
            ");
            $stmt->execute([$date, $date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['id'] : null;
        } catch (Exception $e) {
            // If fiscal_periods table doesn't exist or query fails, return null
            error_log("ReceiptPaymentVoucherManager::getFiscalPeriodId() - Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validate posting date
     * 
     * @param string $date Date (YYYY-MM-DD)
     * @return void
     * @throws Exception If date is in closed period
     */
    private function validatePostingDate(string $date): void {
        try {
            // Check if fiscal_periods table exists
            $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'fiscal_periods'");
            if ($tableCheck->rowCount() > 0) {
                // Check fiscal periods
                $stmt = $this->pdo->prepare("
                    SELECT `id`, `is_closed` FROM `fiscal_periods`
                    WHERE ? >= `start_date` AND ? <= `end_date` AND `is_active` = 1
                    LIMIT 1
                ");
                $stmt->execute([$date, $date]);
                $period = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($period && $period['is_closed'] == 1) {
                    throw new Exception("Cannot post to closed fiscal period");
                }
            }
            
            // Check if financial_closings table exists
            $closingsCheck = $this->pdo->query("SHOW TABLES LIKE 'financial_closings'");
            if ($closingsCheck->rowCount() > 0) {
                // Check financial closings
                $stmt = $this->pdo->prepare("
                    SELECT `id` FROM `financial_closings`
                    WHERE `status` = 'Completed' AND ? >= `period_start` AND ? <= `period_end`
                    LIMIT 1
                ");
                $stmt->execute([$date, $date]);
                if ($stmt->rowCount() > 0) {
                    throw new Exception("Cannot post to closed period");
                }
            }
        } catch (Exception $e) {
            // If tables don't exist, skip validation gracefully
            if (strpos($e->getMessage(), "doesn't exist") === false && 
                strpos($e->getMessage(), "Base table") === false &&
                strpos($e->getMessage(), "Table") === false &&
                strpos($e->getMessage(), "closed") === false) {
                // Only throw if it's not a table-not-found error
                throw $e;
            }
            // Otherwise, silently skip validation if tables don't exist
        }
    }
    
    /**
     * Create reversal entry (wrapper for erp-posting-controls function)
     * 
     * @param int $originalEntryId Original journal entry ID
     * @param string $reversalDate Reversal date
     * @param string $description Description
     * @return array Result array
     */
    private function createReversalEntry(int $originalEntryId, string $reversalDate, string $description): array {
        // Convert PDO to mysqli for compatibility with existing functions
        // Note: In production, consider refactoring helper functions to use PDO
        $mysqli = $this->getMysqliConnection();
        
        if (function_exists('createReversalEntry')) {
            return createReversalEntry($mysqli, $originalEntryId, $reversalDate, $description, $this->userId);
        }
        
        throw new Exception('createReversalEntry function not available');
    }
    
    /**
     * Post journal entry to ledger (wrapper for general-ledger-helper function)
     * 
     * @param int $journalEntryId Journal entry ID
     * @return array Result array
     */
    private function postJournalEntryToLedger(int $journalEntryId): array {
        $mysqli = $this->getMysqliConnection();
        
        if (function_exists('postJournalEntryToLedger')) {
            return postJournalEntryToLedger($mysqli, $journalEntryId);
        }
        
        throw new Exception('postJournalEntryToLedger function not available');
    }
    
    /**
     * Get mysqli connection for compatibility with existing helper functions
     * 
     * @return mysqli Connection
     * @throws Exception If mysqli connection is not available
     */
    private function getMysqliConnection(): \mysqli {
        if (!empty($GLOBALS['conn']) && $GLOBALS['conn'] instanceof \mysqli) {
            return $GLOBALS['conn'];
        }
        throw new Exception('Database connection (mysqli) is not available. Receipt voucher operations require config.php to set $GLOBALS[\'conn\'].');
    }
    
    /**
     * Guard financial action
     * 
     * @param string $action Action name
     * @param int|null $journalEntryId Journal entry ID (null if not yet created)
     * @return void
     * @throws Exception If violation detected
     */
    private function guardFinancialAction(string $action, ?int $journalEntryId = null): void {
        $mysqli = $this->getMysqliConnection();
        
        if (function_exists('erpGuardian')) {
            erpGuardian($mysqli, 'CREATE', [
                'module' => $this->voucherType === 'receipt' ? 'receipt' : 'payment',
                'action' => $action,
                'journal_entry_id' => $journalEntryId
            ]);
        }
    }
    
    /**
     * Guard posted entry modification
     * 
     * @param int $voucherId Voucher ID
     * @param string $operation Operation (UPDATE/DELETE)
     * @return void
     * @throws Exception If violation detected
     */
    private function guardPostedEntryModification(int $voucherId, string $operation): void {
        $voucher = $this->get($voucherId);
        if (!$voucher['success']) {
            return; // Let normal flow handle not found
        }
        
        $voucherData = $voucher['voucher'];
        
        if ($voucherData['is_posted'] == 1 || $voucherData['posting_status'] === 'posted' || $voucherData['is_locked'] == 1) {
            throw new Exception("ERP VIOLATION: Posted vouchers are IMMUTABLE. Cannot {$operation} voucher #{$voucherId}. Use reversal instead.");
        }
    }
    
    /**
     * Extract dynamic fields from data array
     * 
     * @param array $data Data array
     * @return array Dynamic fields
     */
    private function extractDynamicFields(array $data): array {
        $dynamicFields = [];
        $fieldNames = array_column($this->dynamicFields, 'field_name');
        
        foreach ($fieldNames as $fieldName) {
            if (isset($data[$fieldName])) {
                $dynamicFields[$fieldName] = $data[$fieldName];
            }
        }
        
        return $dynamicFields;
    }
    
    /**
     * Validate create data
     * 
     * @param array $data Data array
     * @return void
     * @throws Exception If validation fails
     */
    private function validateCreateData(array $data): void {
        if (empty($data['amount']) || floatval($data['amount']) <= 0) {
            throw new Exception('Amount is required and must be greater than 0');
        }
        
        if (empty($data['voucher_date'])) {
            throw new Exception('Voucher date is required');
        }
        
        // Validate dynamic required fields
        foreach ($this->dynamicFields as $field) {
            if ($field['is_required'] == 1 && empty($data[$field['field_name']])) {
                throw new Exception("Field '{$field['field_label']}' is required");
            }
        }
    }
    
    /**
     * Validate update data
     * 
     * @param array $data Update data
     * @param array $oldData Old data
     * @return void
     * @throws Exception If validation fails
     */
    private function validateUpdateData(array $data, array $oldData): void {
        if (isset($data['amount']) && floatval($data['amount']) <= 0) {
            throw new Exception('Amount must be greater than 0');
        }
        
        // Validate dynamic required fields
        foreach ($this->dynamicFields as $field) {
            if ($field['is_required'] == 1 && isset($data[$field['field_name']]) && empty($data[$field['field_name']])) {
                throw new Exception("Field '{$field['field_label']}' is required");
            }
        }
    }
    
    /**
     * Log audit trail entry
     * 
     * @param string $action Action (CREATE, UPDATE, DELETE, POST, REVERSE)
     * @param int $voucherId Voucher ID
     * @param array|null $oldData Old data
     * @param array|null $newData New data
     * @param string|null $notes Additional notes
     * @return void
     */
    private function logAuditTrail(string $action, int $voucherId, ?array $oldData, ?array $newData, ?string $notes = null): void {
        try {
            $mysqli = $this->getMysqliConnection();
        } catch (Exception $e) {
            error_log('ReceiptPaymentVoucherManager::logAuditTrail - mysqli not available: ' . $e->getMessage());
            return;
        }
        $tableName = $this->getTableName();
        
        // Add correlation_id to notes
        $fullNotes = $notes ?? '';
        if ($this->correlationId) {
            $fullNotes .= " [Correlation ID: {$this->correlationId}]";
        }
        
        try {
            if (function_exists('logAuditTrail')) {
                logAuditTrail(
                    $mysqli,
                    $tableName,
                    $voucherId,
                    $action,
                    null,
                    $oldData ? json_encode($oldData) : null,
                    $newData ? json_encode($newData) : null,
                    $this->userId,
                    $fullNotes
                );
            }
        } catch (Throwable $e) {
            error_log('ReceiptPaymentVoucherManager::logAuditTrail - failed: ' . $e->getMessage());
        }
    }
}
