<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/queries/AccountingQueries.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/queries/AccountingQueries.php`.
 */
/**
 * Accounting SQL Queries Repository
 * All SQL queries related to accounting/financial transactions are centralized here
 */
class AccountingQueries {
    
    public static function getTransactions($filters = []) {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['type'])) {
            $conditions[] = "ft.transaction_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['date_from'])) {
            $conditions[] = "ft.transaction_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "ft.transaction_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        
        $params[] = $limit;
        $params[] = $offset;
        
        return [
            'sql' => "SELECT 
                ft.*,
                u.username as created_by_name
            FROM financial_transactions ft
            LEFT JOIN users u ON ft.created_by = u.id
            $whereClause
            ORDER BY ft.transaction_date DESC, ft.created_at DESC
            LIMIT ? OFFSET ?",
            'params' => $params
        ];
    }
    
    public static function getTransactionCount($filters = []) {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['type'])) {
            $conditions[] = "ft.transaction_type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['date_from'])) {
            $conditions[] = "ft.transaction_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "ft.transaction_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        return [
            'sql' => "SELECT COUNT(*) as total FROM financial_transactions ft $whereClause",
            'params' => $params
        ];
    }
    
    public static function getTransactionById($id) {
        return [
            'sql' => "SELECT 
                ft.*,
                u.username as created_by_name
            FROM financial_transactions ft
            LEFT JOIN users u ON ft.created_by = u.id
            WHERE ft.id = ?",
            'params' => [$id]
        ];
    }
    
    public static function createTransaction($data) {
        return [
            'sql' => "INSERT INTO financial_transactions (
                transaction_date, description, reference_number, total_amount, 
                transaction_type, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)",
            'params' => [
                $data['transaction_date'],
                $data['description'] ?? '',
                $data['reference_number'] ?? '',
                $data['total_amount'],
                $data['transaction_type'],
                $data['status'] ?? 'pending',
                $data['created_by']
            ]
        ];
    }
    
    public static function updateTransaction($id, $data) {
        $updates = [];
        $params = [];
        
        $allowedFields = [
            'transaction_date', 'description', 'reference_number', 
            'total_amount', 'transaction_type', 'status'
        ];
        
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
            'sql' => "UPDATE financial_transactions SET " . implode(', ', $updates) . 
                    ", updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            'params' => $params
        ];
    }
    
    public static function deleteTransaction($id) {
        return [
            'sql' => "DELETE FROM financial_transactions WHERE id = ?",
            'params' => [$id]
        ];
    }
    
    public static function getEntityTransactions($entityType, $entityId) {
        return [
            'sql' => "SELECT 
                et.id,
                et.transaction_id,
                et.entity_type,
                et.entity_id,
                et.category,
                ft.transaction_date,
                ft.description,
                ft.reference_number,
                ft.total_amount,
                ft.transaction_type,
                ft.status,
                ft.created_at,
                u.username as created_by_name
            FROM entity_transactions et
            INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
            LEFT JOIN users u ON ft.created_by = u.user_id
            WHERE et.entity_type = ? AND et.entity_id = ?
            ORDER BY ft.transaction_date DESC, ft.created_at DESC
            LIMIT 1000",
            'params' => [$entityType, $entityId]
        ];
    }
    
    public static function createEntityTransaction($data) {
        return [
            'sql' => "INSERT INTO entity_transactions (
                transaction_id, entity_type, entity_id, category
            ) VALUES (?, ?, ?, ?)",
            'params' => [
                $data['transaction_id'],
                $data['entity_type'],
                $data['entity_id'],
                $data['category'] ?? 'other'
            ]
        ];
    }
    
    public static function deleteEntityTransaction($id) {
        return [
            'sql' => "DELETE FROM entity_transactions WHERE id = ?",
            'params' => [$id]
        ];
    }
}








