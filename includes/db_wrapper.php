<?php

/**
 * Lightweight Database wrapper around PDO (PostgreSQL)
 * Provides a few helpers used across the codebase.
 */
class Database {
    private $pdo;
    private $userId;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userId = null;
    }
    
    /**
     * Set user ID for user-scoped queries
     */
    public function setUserId($userId) {
        $this->userId = $userId;
    }
    
    /**
     * Get user ID
     */
    public function getUserId() {
        return $this->userId;
    }
    
    /**
     * Execute a query (compatibility with SQLite3)
     */
    public function query($query, $params = []) {
        try {
            if (empty($params)) {
                return $this->pdo->query($query);
            } else {
                $stmt = $this->pdo->prepare($query);
                $stmt->execute($params);
                return $stmt;
            }
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage() . " | Query: " . $query);
            throw $e;
        }
    }
    
    /**
     * Prepare a statement
     */
    public function prepare($query) {
        return $this->pdo->prepare($query);
    }
    
    /**
     * Execute a statement
     */
    public function exec($query) {
        return $this->pdo->exec($query);
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Fetch a single record for the current user
     */
    public function fetchForUser($table, $conditions = '1=1') {
        if ($this->userId === null) {
            throw new Exception('User ID not set for user-scoped query');
        }
        
        if (is_string($table)) {
            // If table is a string, build a simple SELECT query
            $query = "SELECT * FROM {$table} WHERE user_id = :user_id AND ({$conditions}) LIMIT 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['user_id' => $this->userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // If table is actually a full query, execute it directly
            $stmt = $this->pdo->prepare($table);
            $stmt->execute(['user_id' => $this->userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    /**
     * Fetch all records for the current user
     */
    public function fetchAllForUser($query, $params = []) {
        if ($this->userId === null) {
            throw new Exception('User ID not set for user-scoped query');
        }
        
        // Add user_id to params if not already present
        if (!isset($params['user_id'])) {
            $params['user_id'] = $this->userId;
        }
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Insert record for current user
     */
    public function insertForUser($table, $data) {
        if ($this->userId === null) {
            throw new Exception('User ID not set for user-scoped query');
        }
        
        $data['user_id'] = $this->userId;
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($data);
    }
    
    /**
     * Close connection (compatibility method - does nothing for PDO)
     */
    public function close() {
        // PDO connections are closed automatically
        return true;
    }
    
    /**
     * Get the underlying PDO instance
     */
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * Compatibility method - lastInsertRowID
     */
    public function lastInsertRowID() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Execute and return result (helper)
     */
    public function execute($query, $params = []) {
        if (empty($params)) {
            return $this->pdo->query($query);
        } else {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        }
    }
}
?>
