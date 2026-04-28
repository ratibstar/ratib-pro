<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/QueryRepository.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/QueryRepository.php`.
 */
/**
 * SQL Query Repository
 * Centralizes all SQL queries to remove inline SQL from API files
 */
class QueryRepository {
    private $db;
    
    public function __construct($connection) {
        $this->db = $connection;
    }
    
    /**
     * Get connection type (mysqli or pdo)
     */
    private function getConnectionType() {
        if ($this->db instanceof mysqli) {
            return 'mysqli';
        } elseif ($this->db instanceof PDO) {
            return 'pdo';
        }
        return 'unknown';
    }
    
    /**
     * Deprecated execution wrapper.
     * QueryGateway is the single enforcement + execution entry point.
     */
    public function execute($sql, $params = []) {
        if (!class_exists('QueryGateway', false)) {
            require_once __DIR__ . '/../../core/query/QueryGateway.php';
        }
        QueryGateway::setConnection($this->db);
        return QueryGateway::execute($sql, $params);
    }
    
    /**
     * Fetch all results
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        $type = $this->getConnectionType();
        
        if ($type === 'mysqli') {
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    /**
     * Fetch single result
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        $type = $this->getConnectionType();
        
        if ($type === 'mysqli') {
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } else {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        $type = $this->getConnectionType();
        
        if ($type === 'mysqli') {
            return $this->db->insert_id;
        } else {
            return $this->db->lastInsertId();
        }
    }
    
    /**
     * Get affected rows
     */
    public function affectedRows() {
        $type = $this->getConnectionType();
        
        if ($type === 'mysqli') {
            return $this->db->affected_rows;
        } else {
            // For PDO, we need to get it from the statement
            // This is a limitation - we'd need to track the last statement
            return 0;
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $type = $this->getConnectionType();
        
        if ($type === 'mysqli') {
            return $this->db->begin_transaction();
        } else {
            return $this->db->beginTransaction();
        }
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        $type = $this->getConnectionType();
        
        if ($type === 'mysqli') {
            return $this->db->commit();
        } else {
            return $this->db->commit();
        }
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        $type = $this->getConnectionType();
        
        if ($type === 'mysqli') {
            return $this->db->rollback();
        } else {
            return $this->db->rollBack();
        }
    }
}








