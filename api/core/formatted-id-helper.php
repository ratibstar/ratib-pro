<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/formatted-id-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/formatted-id-helper.php`.
 */
/**
 * Formatted ID Generator Helper
 * 
 * This helper replaces database triggers that auto-generate formatted IDs.
 * Use these functions when inserting new records into tables that require formatted IDs.
 * 
 * Usage:
 *   require_once 'api/core/formatted-id-helper.php';
 *   $agentId = generateAgentFormattedId($conn);
 *   $caseNumber = generateCaseNumber($conn);
 *   $contactId = generateContactId($conn);
 *   $subagentId = generateSubagentFormattedId($conn);
 *   $workerId = generateWorkerFormattedId($conn);
 * 
 * Note: $conn can be either mysqli or PDO connection
 */

/**
 * Helper function to execute query and get result (supports both mysqli and PDO)
 * 
 * @param mixed $conn Database connection (mysqli or PDO)
 * @param string $sql SQL query
 * @return int Next ID value
 */
function _getNextId($conn, $sql) {
    if ($conn instanceof mysqli) {
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            return (int)($row['next_id'] ?? 1);
        }
        return 1;
    } elseif ($conn instanceof PDO) {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['next_id'] ?? 1);
    } else {
        throw new Exception("Unsupported database connection type");
    }
}

/**
 * Generate formatted ID for agents table (format: A0001, A0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Formatted ID (e.g., 'A0001')
 */
function generateAgentFormattedId($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(formatted_id, 2) AS UNSIGNED)), 0) + 1 AS next_id FROM agents";
        $nextId = _getNextId($conn, $sql);
        return 'A' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating agent formatted ID: " . $e->getMessage());
        throw new Exception("Failed to generate agent formatted ID");
    }
}

/**
 * Generate case number for cases table (format: CA0001, CA0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Case number (e.g., 'CA0001')
 */
function generateCaseNumber($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(case_number, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM cases WHERE case_number REGEXP '^CA[0-9]+$'";
        $nextId = _getNextId($conn, $sql);
        return 'CA' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating case number: " . $e->getMessage());
        throw new Exception("Failed to generate case number");
    }
}

/**
 * Generate contact ID for contacts table (format: C0001, C0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Contact ID (e.g., 'C0001')
 */
function generateContactId($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(contact_id, 2) AS UNSIGNED)), 0) + 1 AS next_id FROM contacts";
        $nextId = _getNextId($conn, $sql);
        return 'C' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating contact ID: " . $e->getMessage());
        throw new Exception("Failed to generate contact ID");
    }
}

/**
 * Generate formatted ID for subagents table (format: SUB001, SUB002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Formatted ID (e.g., 'SUB001')
 */
function generateSubagentFormattedId($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(formatted_id, 4) AS UNSIGNED)), 0) + 1 AS next_id FROM subagents";
        $nextId = _getNextId($conn, $sql);
        return 'SUB' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating subagent formatted ID: " . $e->getMessage());
        throw new Exception("Failed to generate subagent formatted ID");
    }
}

/**
 * Generate formatted ID for workers table (format: W0001, W0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Formatted ID (e.g., 'W0001')
 */
function generateWorkerFormattedId($conn) {
    try {
        $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(formatted_id, 2) AS UNSIGNED)), 0) + 1 AS next_id FROM workers";
        $nextId = _getNextId($conn, $sql);
        return 'W' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating worker formatted ID: " . $e->getMessage());
        throw new Exception("Failed to generate worker formatted ID");
    }
}

/**
 * Generic function to generate formatted ID based on table and column
 * 
 * @param mysqli|PDO $conn Database connection
 * @param string $table Table name
 * @param string $column Column name containing the formatted ID
 * @param string $prefix Prefix for the formatted ID (e.g., 'A', 'CA', 'C', 'SUB', 'W')
 * @param int $paddingLength Number of digits to pad (e.g., 4 for '0001', 3 for '001')
 * @param int $substringStart Position to start substring (1-based, e.g., 2 for 'A0001' to get '0001')
 * @return string Formatted ID
 */
function generateFormattedId($conn, $table, $column, $prefix, $paddingLength = 4, $substringStart = 2) {
    try {
        // Escape table and column names for safety (basic protection)
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $substringStart = (int)$substringStart;
        
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING({$column}, {$substringStart}) AS UNSIGNED)), 0) + 1 AS next_id FROM {$table}";
        $nextId = _getNextId($conn, $sql);
        return $prefix . str_pad($nextId, $paddingLength, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating formatted ID for {$table}.{$column}: " . $e->getMessage());
        throw new Exception("Failed to generate formatted ID for {$table}.{$column}");
    }
}

/**
 * Generate formatted ID for employees table (format: EM0001, EM0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Formatted ID (e.g., 'EM0001')
 */
function generateHREmployeeId($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(employee_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM employees WHERE employee_id REGEXP '^EM[0-9]+$'";
        $nextId = _getNextId($conn, $sql);
        return 'EM' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating HR employee ID: " . $e->getMessage());
        throw new Exception("Failed to generate HR employee ID");
    }
}

/**
 * Generate formatted ID for attendance table (format: AT0001, AT0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Formatted ID (e.g., 'AT0001')
 */
function generateHRAttendanceId($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM attendance WHERE record_id REGEXP '^AT[0-9]+$'";
        $nextId = _getNextId($conn, $sql);
        return 'AT' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating HR attendance ID: " . $e->getMessage());
        throw new Exception("Failed to generate HR attendance ID");
    }
}

/**
 * Generate formatted ID for advances table (format: AD0001, AD0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Formatted ID (e.g., 'AD0001')
 */
function generateHRAdvanceId($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM advances WHERE record_id REGEXP '^AD[0-9]+$'";
        $nextId = _getNextId($conn, $sql);
        return 'AD' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating HR advance ID: " . $e->getMessage());
        throw new Exception("Failed to generate HR advance ID");
    }
}

/**
 * Generate formatted ID for salaries table (format: PA0001, PA0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Formatted ID (e.g., 'PA0001')
 */
function generateHRSalaryId($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM salaries WHERE record_id REGEXP '^PA[0-9]+$'";
        $nextId = _getNextId($conn, $sql);
        return 'PA' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating HR salary ID: " . $e->getMessage());
        throw new Exception("Failed to generate HR salary ID");
    }
}

/**
 * Generate formatted ID for hr_documents table (format: DO0001, DO0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Formatted ID (e.g., 'DO0001')
 */
function generateHRDocumentId($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM hr_documents WHERE record_id REGEXP '^DO[0-9]+$'";
        $nextId = _getNextId($conn, $sql);
        return 'DO' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating HR document ID: " . $e->getMessage());
        throw new Exception("Failed to generate HR document ID");
    }
}

/**
 * Generate formatted ID for cars table (format: VE0001, VE0002, etc.)
 * 
 * @param mysqli|PDO $conn Database connection
 * @return string Formatted ID (e.g., 'VE0001')
 */
function generateHRVehicleId($conn) {
    try {
        $sql = "SELECT IFNULL(MAX(CAST(SUBSTRING(record_id, 3) AS UNSIGNED)), 0) + 1 AS next_id FROM cars WHERE record_id REGEXP '^VE[0-9]+$'";
        $nextId = _getNextId($conn, $sql);
        return 'VE' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generating HR vehicle ID: " . $e->getMessage());
        throw new Exception("Failed to generate HR vehicle ID");
    }
}
