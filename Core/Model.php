<?php
namespace Core;

/**
 * Base Model class
 * All models should extend this class
 */
class Model
{
    /**
     * Database instance
     */
    protected $db;
    
    /**
     * Table name
     */
    protected $table;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Find all records
     * 
     * @param string $orderBy Column to order by
     * @param string $direction Order direction (ASC or DESC)
     * @return array Records
     */
    public function findAll($orderBy = 'id', $direction = 'ASC')
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction}";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Find a record by ID
     * 
     * @param int $id ID to find
     * @return array|false Record if found, false otherwise
     */
    public function find($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Find records by a field
     * 
     * @param string $field Field to search by
     * @param mixed $value Value to search for
     * @return array Records
     */
    public function findBy($field, $value)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }
    
    /**
     * Find one record by a field
     * 
     * @param string $field Field to search by
     * @param mixed $value Value to search for
     * @return array|false Record if found, false otherwise
     */
    public function findOneBy($field, $value)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetch();
    }
    
    /**
     * Create a new record
     * 
     * @param array $data Data to insert
     * @return int|false ID of the new record, or false on failure
     */
    public function create($data)
    {
        // Prepare column names and placeholders
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        // Build SQL
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        
        // Execute
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute(array_values($data));
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update a record
     * 
     * @param int $id ID of the record to update
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public function update($id, $data)
    {
        // Prepare column assignments
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = "{$column} = ?";
        }
        $assignments = implode(', ', $assignments);
        
        // Build SQL
        $sql = "UPDATE {$this->table} SET {$assignments} WHERE id = ?";
        
        // Execute
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([...array_values($data), $id]);
    }
    
    /**
     * Delete a record
     * 
     * @param int $id ID of the record to delete
     * @return bool True on success, false on failure
     */
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Count records
     * 
     * @param string $field Field to count by (optional)
     * @param mixed $value Value to count (optional)
     * @return int Count
     */
    public function count($field = null, $value = null)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        
        if ($field !== null) {
            $sql .= " WHERE {$field} = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$value]);
        } else {
            $stmt = $this->db->query($sql);
        }
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Execute a custom query
     * 
     * @param string $sql SQL query
     * @param array $params Parameters for the query
     * @param bool $fetchAll Whether to fetch all records or just one
     * @return mixed Query result
     */
    public function query($sql, $params = [], $fetchAll = true)
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        if ($fetchAll) {
            return $stmt->fetchAll();
        }
        
        return $stmt->fetch();
    }
}