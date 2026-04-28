<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/queries/AgentQueries.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/queries/AgentQueries.php`.
 */
/**
 * Agent SQL Queries Repository
 * All SQL queries related to agents are centralized here
 */
class AgentQueries {
    // WARNING: Legacy methods in this class are NOT TENANT SAFE unless explicitly marked tenant-scoped.

    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function getAll($filters = []) {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(agent_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        return [
            'sql' => "SELECT 
                id,
                CONCAT('AG', LPAD(id, 4, '0')) as formatted_id,
                agent_name,
                email,
                contact_number,
                address,
                city,
                status,
                created_at,
                updated_at
            FROM agents 
            $whereClause
            ORDER BY id DESC",
            'params' => $params
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function getById($id) {
        return [
            'sql' => "SELECT 
                id,
                agent_name,
                email,
                contact_number,
                address,
                city,
                status,
                created_at,
                updated_at
            FROM agents 
            WHERE id = ?",
            'params' => [$id]
        ];
    }

    /**
     * Tenant-safe example: enforce tenant_id in WHERE.
     */
    public static function getByIdTenantScoped($id, $tenantId = null) {
        if ($tenantId === null) {
            if (!function_exists('getCurrentTenantId')) {
                require_once __DIR__ . '/../../../includes/helpers/get_tenant_db.php';
            }
            $tenantId = getCurrentTenantId();
        }
        return [
            'sql' => "SELECT 
                id,
                agent_name,
                email,
                contact_number,
                address,
                city,
                status,
                created_at,
                updated_at
            FROM agents
            WHERE id = ? AND tenant_id = ?",
            'params' => [$id, (int) $tenantId]
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function getByEmail($email) {
        return [
            'sql' => "SELECT id FROM agents WHERE email = ?",
            'params' => [$email]
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function create($data) {
        return [
            'sql' => "INSERT INTO agents (
                agent_name, email, contact_number, city, address, status
            ) VALUES (?, ?, ?, ?, ?, ?)",
            'params' => [
                $data['full_name'] ?? $data['agent_name'],
                $data['email'],
                $data['phone'] ?? $data['contact_number'],
                $data['city'] ?? '',
                $data['address'] ?? '',
                $data['status'] ?? 'active'
            ]
        ];
    }

    /**
     * Tenant-safe example: enforce tenant_id on INSERT.
     */
    public static function createTenantScoped($data, $tenantId = null) {
        if ($tenantId === null) {
            if (!function_exists('getCurrentTenantId')) {
                require_once __DIR__ . '/../../../includes/helpers/get_tenant_db.php';
            }
            $tenantId = getCurrentTenantId();
        }
        return [
            'sql' => "INSERT INTO agents (
                tenant_id, agent_name, email, contact_number, city, address, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)",
            'params' => [
                (int) $tenantId,
                $data['full_name'] ?? $data['agent_name'],
                $data['email'],
                $data['phone'] ?? $data['contact_number'],
                $data['city'] ?? '',
                $data['address'] ?? '',
                $data['status'] ?? 'active'
            ]
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function update($id, $data) {
        $updates = [];
        $params = [];
        
        $fieldMappings = [
            'full_name' => 'agent_name',
            'phone' => 'contact_number'
        ];
        
        foreach (['full_name', 'email', 'phone', 'city', 'address', 'status'] as $field) {
            if (isset($data[$field])) {
                $dbField = $fieldMappings[$field] ?? $field;
                $updates[] = "$dbField = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            throw new Exception('No fields to update');
        }
        
        $params[] = $id;
        
        return [
            'sql' => "UPDATE agents SET " . implode(', ', $updates) . 
                    ", updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            'params' => $params
        ];
    }

    /**
     * Tenant-safe example: enforce tenant_id in UPDATE WHERE.
     */
    public static function updateTenantScoped($id, $data, $tenantId = null) {
        if ($tenantId === null) {
            if (!function_exists('getCurrentTenantId')) {
                require_once __DIR__ . '/../../../includes/helpers/get_tenant_db.php';
            }
            $tenantId = getCurrentTenantId();
        }

        $updates = [];
        $params = [];

        $fieldMappings = [
            'full_name' => 'agent_name',
            'phone' => 'contact_number'
        ];

        foreach (['full_name', 'email', 'phone', 'city', 'address', 'status'] as $field) {
            if (isset($data[$field])) {
                $dbField = $fieldMappings[$field] ?? $field;
                $updates[] = "$dbField = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            throw new Exception('No fields to update');
        }

        $params[] = $id;
        $params[] = (int) $tenantId;

        return [
            'sql' => "UPDATE agents SET " . implode(', ', $updates) .
                    ", updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?",
            'params' => $params
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function delete($id) {
        return [
            'sql' => "DELETE FROM agents WHERE id = ?",
            'params' => [$id]
        ];
    }

    /**
     * Tenant-safe example: enforce tenant_id in DELETE WHERE.
     */
    public static function deleteTenantScoped($id, $tenantId = null) {
        if ($tenantId === null) {
            if (!function_exists('getCurrentTenantId')) {
                require_once __DIR__ . '/../../../includes/helpers/get_tenant_db.php';
            }
            $tenantId = getCurrentTenantId();
        }
        return [
            'sql' => "DELETE FROM agents WHERE id = ? AND tenant_id = ?",
            'params' => [$id, (int) $tenantId]
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function bulkUpdateStatus($ids, $status) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $params = array_merge([$status], $ids);
        
        return [
            'sql' => "UPDATE agents SET status = ? WHERE id IN ($placeholders)",
            'params' => $params
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function bulkDelete($ids) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        return [
            'sql' => "DELETE FROM agents WHERE id IN ($placeholders)",
            'params' => $ids
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function getStats() {
        return [
            'total' => [
                'sql' => "SELECT COUNT(*) as count FROM agents",
                'params' => []
            ],
            'active' => [
                'sql' => "SELECT COUNT(*) as count FROM agents WHERE status = 'active'",
                'params' => []
            ],
            'inactive' => [
                'sql' => "SELECT COUNT(*) as count FROM agents WHERE status = 'inactive'",
                'params' => []
            ]
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function getWorking() {
        return [
            'sql' => "SELECT id, agent_name, email, contact_number, city, status 
                     FROM agents 
                     WHERE status = 'active' 
                     ORDER BY agent_name ASC",
            'params' => []
        ];
    }
    
    // WARNING: NOT TENANT SAFE - DO NOT USE IN TENANT CONTEXT.
    public static function getEmpty() {
        return [
            'sql' => "SELECT id, agent_name, email, contact_number, city, status 
                     FROM agents 
                     WHERE agent_name IS NULL OR agent_name = '' OR email IS NULL OR email = ''",
            'params' => []
        ];
    }

    /**
     * QueryRepository backed by getTenantDB(): tenant PDO when domain middleware resolved a tenant,
     * otherwise the existing app connection (backward compatible).
     */
    public static function repositoryForTenantRequest() {
        require_once __DIR__ . '/../QueryRepository.php';
        if (!function_exists('getTenantDB')) {
            require_once __DIR__ . '/../../../includes/helpers/get_tenant_db.php';
        }
        return new QueryRepository(getTenantDB());
    }

    /**
     * QueryRepository for control/system data only — never the tenant-isolated DB.
     */
    public static function repositoryForSystem() {
        require_once __DIR__ . '/../QueryRepository.php';
        if (!function_exists('ratib_app_default_db_connection')) {
            require_once __DIR__ . '/../../../includes/helpers/get_tenant_db.php';
        }
        return new QueryRepository(ratib_app_default_db_connection());
    }
}








