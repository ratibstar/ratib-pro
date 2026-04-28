<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/queries/UserQueries.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/queries/UserQueries.php`.
 */
/**
 * User SQL Queries Repository
 * All SQL queries related to users are centralized here
 */
class UserQueries {
    
    public static function getAll() {
        return [
            'sql' => "SELECT user_id, username, email, role_id, status, created_at 
                     FROM users 
                     ORDER BY created_at DESC",
            'params' => []
        ];
    }
    
    public static function getById($id) {
        return [
            'sql' => "SELECT * FROM users WHERE user_id = ?",
            'params' => [$id]
        ];
    }
    
    public static function getByEmail($email) {
        return [
            'sql' => "SELECT user_id FROM users WHERE email = ?",
            'params' => [$email]
        ];
    }
    
    public static function getByUsername($username) {
        return [
            'sql' => "SELECT * FROM users WHERE username = ?",
            'params' => [$username]
        ];
    }
    
    public static function create($data) {
        return [
            'sql' => "INSERT INTO users (username, email, password, role_id, status) VALUES (?, ?, ?, ?, ?)",
            'params' => [
                $data['username'],
                $data['email'],
                $data['password'],
                $data['role_id'] ?? 1,
                $data['status'] ?? 'active'
            ]
        ];
    }
    
    public static function update($id, $data) {
        $updates = [];
        $params = [];
        
        $allowedFields = ['username', 'email', 'role_id', 'status'];
        
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
            'sql' => "UPDATE users SET " . implode(', ', $updates) . 
                    ", updated_at = CURRENT_TIMESTAMP WHERE user_id = ?",
            'params' => $params
        ];
    }
    
    public static function updatePassword($id, $password) {
        return [
            'sql' => "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?",
            'params' => [$password, $id]
        ];
    }
    
    public static function delete($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        return [
            'sql' => "DELETE FROM users WHERE user_id IN ($placeholders)",
            'params' => $ids
        ];
    }
    
    public static function updateStatus($id, $status) {
        return [
            'sql' => "UPDATE users SET status = ? WHERE user_id = ?",
            'params' => [$status, $id]
        ];
    }
}








