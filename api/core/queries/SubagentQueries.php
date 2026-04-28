<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/queries/SubagentQueries.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/queries/SubagentQueries.php`.
 */
/**
 * Subagent SQL Queries Repository
 * All SQL queries related to subagents are centralized here
 */
class SubagentQueries {
    
    public static function getAll($filters = []) {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(subagent_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        return [
            'sql' => "SELECT 
                id,
                subagent_name,
                email,
                contact_number,
                address,
                city,
                status,
                created_at,
                updated_at
            FROM subagents 
            $whereClause
            ORDER BY id DESC",
            'params' => $params
        ];
    }
    
    public static function getById($id) {
        return [
            'sql' => "SELECT * FROM subagents WHERE id = ?",
            'params' => [$id]
        ];
    }
    
    public static function create($data) {
        return [
            'sql' => "INSERT INTO subagents (
                subagent_name, email, contact_number, city, address, status
            ) VALUES (?, ?, ?, ?, ?, ?)",
            'params' => [
                $data['subagent_name'] ?? $data['full_name'],
                $data['email'],
                $data['contact_number'] ?? $data['phone'],
                $data['city'] ?? '',
                $data['address'] ?? '',
                $data['status'] ?? 'active'
            ]
        ];
    }
    
    public static function update($id, $data) {
        $updates = [];
        $params = [];
        
        $allowedFields = ['subagent_name', 'email', 'contact_number', 'city', 'address', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            throw new Exception('No fields to update');
        }
        
        $params[] = $id;
        
        return [
            'sql' => "UPDATE subagents SET " . implode(', ', $updates) . 
                    ", updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            'params' => $params
        ];
    }
    
    public static function delete($id) {
        return [
            'sql' => "DELETE FROM subagents WHERE id = ?",
            'params' => [$id]
        ];
    }
    
    public static function bulkUpdateStatus($ids, $status) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $params = array_merge([$status], $ids);
        
        return [
            'sql' => "UPDATE subagents SET status = ? WHERE id IN ($placeholders)",
            'params' => $params
        ];
    }
    
    public static function getStats() {
        return [
            'total' => [
                'sql' => "SELECT COUNT(*) as count FROM subagents",
                'params' => []
            ],
            'active' => [
                'sql' => "SELECT COUNT(*) as count FROM subagents WHERE status = 'active'",
                'params' => []
            ],
            'inactive' => [
                'sql' => "SELECT COUNT(*) as count FROM subagents WHERE status = 'inactive'",
                'params' => []
            ]
        ];
    }
}








