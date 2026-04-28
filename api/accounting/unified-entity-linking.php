<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/unified-entity-linking.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/unified-entity-linking.php`.
 */
/**
 * Unified Entity Linking System
 * Connects ALL accounting tables to ALL entity modules (Agent, SubAgent, Workers, HR, etc.)
 */

require_once '../../includes/config.php';

/**
 * Get all entity types and their IDs/names for dropdowns
 */
function getEntityOptions($conn, $entityType = null) {
    $entities = [];
    
    if (!$entityType || $entityType === 'agent') {
        // Agents
        $tableCheck = $conn->query("SHOW TABLES LIKE 'agents'");
        if ($tableCheck->num_rows > 0) {
            // Try different column names
            $nameCol = null;
            $colChecks = ['agent_name', 'full_name', 'name'];
            foreach ($colChecks as $col) {
                $check = $conn->query("SHOW COLUMNS FROM agents LIKE '$col'");
                if ($check->num_rows > 0) {
                    $nameCol = $col;
                    break;
                }
            }
            
            if ($nameCol) {
                $result = $conn->query("SELECT id, $nameCol as name FROM agents WHERE status = 'Active' ORDER BY $nameCol");
                while ($row = $result->fetch_assoc()) {
                    $entities[] = [
                        'type' => 'agent',
                        'id' => intval($row['id']),
                        'name' => $row['name'],
                        'type_label' => 'Agent'
                    ];
                }
            }
        }
    }
    
    if (!$entityType || $entityType === 'subagent') {
        // Subagents
        $tableCheck = $conn->query("SHOW TABLES LIKE 'subagents'");
        if ($tableCheck->num_rows > 0) {
            $nameCol = null;
            $colChecks = ['subagent_name', 'full_name', 'name'];
            foreach ($colChecks as $col) {
                $check = $conn->query("SHOW COLUMNS FROM subagents LIKE '$col'");
                if ($check->num_rows > 0) {
                    $nameCol = $col;
                    break;
                }
            }
            
            if ($nameCol) {
                $result = $conn->query("SELECT id, $nameCol as name FROM subagents WHERE status = 'Active' ORDER BY $nameCol");
                while ($row = $result->fetch_assoc()) {
                    $entities[] = [
                        'type' => 'subagent',
                        'id' => intval($row['id']),
                        'name' => $row['name'],
                        'type_label' => 'SubAgent'
                    ];
                }
            }
        }
    }
    
    if (!$entityType || $entityType === 'worker') {
        // Workers
        $tableCheck = $conn->query("SHOW TABLES LIKE 'workers'");
        if ($tableCheck->num_rows > 0) {
            $nameCol = null;
            $colChecks = ['worker_name', 'full_name', 'name'];
            foreach ($colChecks as $col) {
                $check = $conn->query("SHOW COLUMNS FROM workers LIKE '$col'");
                if ($check->num_rows > 0) {
                    $nameCol = $col;
                    break;
                }
            }
            
            if ($nameCol) {
                $result = $conn->query("SELECT id, $nameCol as name FROM workers ORDER BY $nameCol LIMIT 100");
                while ($row = $result->fetch_assoc()) {
                    $entities[] = [
                        'type' => 'worker',
                        'id' => intval($row['id']),
                        'name' => $row['name'],
                        'type_label' => 'Worker'
                    ];
                }
            }
        }
    }
    
    if (!$entityType || $entityType === 'hr' || $entityType === 'employee') {
        // HR Employees
        $tables = ['hr_employees', 'employees'];
        foreach ($tables as $table) {
            $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
            if ($tableCheck->num_rows > 0) {
                $nameCol = null;
                $colChecks = ['employee_name', 'full_name', 'name'];
                foreach ($colChecks as $col) {
                    $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
                    if ($check->num_rows > 0) {
                        $nameCol = $col;
                        break;
                    }
                }
                
                if ($nameCol) {
                    $result = $conn->query("SELECT id, $nameCol as name FROM $table ORDER BY $nameCol LIMIT 100");
                    while ($row = $result->fetch_assoc()) {
                        $entities[] = [
                            'type' => 'hr',
                            'id' => intval($row['id']),
                            'name' => $row['name'],
                            'type_label' => 'HR Employee'
                        ];
                    }
                    break; // Found table, no need to check others
                }
            }
        }
    }
    
    return $entities;
}

/**
 * Get entity name by type and ID
 */
function getEntityName($conn, $entityType, $entityId) {
    if (!$entityType || !$entityId) return null;
    
    $tableMap = [
        'agent' => ['table' => 'agents', 'cols' => ['agent_name', 'full_name', 'name']],
        'subagent' => ['table' => 'subagents', 'cols' => ['subagent_name', 'full_name', 'name']],
        'worker' => ['table' => 'workers', 'cols' => ['worker_name', 'full_name', 'name']],
        'hr' => ['table' => null, 'cols' => ['employee_name', 'full_name', 'name']],
        'employee' => ['table' => null, 'cols' => ['employee_name', 'full_name', 'name']]
    ];
    
    if (!isset($tableMap[$entityType])) return null;
    
    $config = $tableMap[$entityType];
    
    // For HR, try multiple tables
    $tables = $config['table'] ? [$config['table']] : ['hr_employees', 'employees'];
    
    foreach ($tables as $table) {
        $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->num_rows > 0) {
            foreach ($config['cols'] as $col) {
                $colCheck = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
                if ($colCheck->num_rows > 0) {
                    $stmt = $conn->prepare("SELECT $col as name FROM $table WHERE id = ?");
                    $stmt->bind_param('i', $entityId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        return $row['name'];
                    }
                    return null;
                }
            }
        }
    }
    
    return null;
}

/**
 * Ensure entity linking columns exist in a table
 */
function ensureEntityLinkingColumns($conn, $tableName) {
    $columns = [
        'entity_type' => "VARCHAR(50) NULL",
        'entity_id' => "INT NULL"
    ];
    
    foreach ($columns as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$col'");
        if ($check->num_rows === 0) {
            try {
                $conn->query("ALTER TABLE $tableName ADD COLUMN $col $def");
                $conn->query("ALTER TABLE $tableName ADD INDEX idx_entity_{$col} ($col)");
            } catch (Exception $e) {
                // Column might already exist or table doesn't exist
            }
        }
    }
}

