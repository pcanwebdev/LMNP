<?php
namespace Modules\Finance\Revenues\Models;

use Core\Model;

/**
 * Revenue Model
 * Handles revenue-related database operations
 */
class RevenueModel extends Model
{
    /**
     * Table name
     */
    protected $table = 'revenues';
    
    /**
     * Fillable fields
     */
    protected $fillable = [
        'property_id',
        'user_id',
        'amount',
        'revenue_date',
        'description',
        'category',
        'recurring',
        'recurring_frequency'
    ];
    
    /**
     * Get revenues filtered by various criteria
     * 
     * @param int $userId User ID
     * @param array $filter Filter criteria
     * @return array Filtered revenues
     */
    public function getFilteredRevenues($userId, $filter = [])
    {
        $conditions = ['r.user_id = :user_id'];
        $params = ['user_id' => $userId];
        
        // Add property filter
        if (!empty($filter['property_id'])) {
            $conditions[] = 'r.property_id = :property_id';
            $params['property_id'] = $filter['property_id'];
        }
        
        // Add year filter
        if (!empty($filter['year'])) {
            $conditions[] = 'YEAR(r.revenue_date) = :year';
            $params['year'] = $filter['year'];
        }
        
        // Add month filter
        if (!empty($filter['month'])) {
            $conditions[] = 'MONTH(r.revenue_date) = :month';
            $params['month'] = $filter['month'];
        }
        
        // Add category filter
        if (!empty($filter['category'])) {
            $conditions[] = 'r.category = :category';
            $params['category'] = $filter['category'];
        }
        
        // Build WHERE clause
        $where = implode(' AND ', $conditions);
        
        // Build query
        $sql = "SELECT r.*, p.name as property_name 
                FROM {$this->table} r
                JOIN properties p ON r.property_id = p.id
                WHERE {$where}
                ORDER BY r.revenue_date DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a revenue by ID and user ID
     * 
     * @param int $id Revenue ID
     * @param int $userId User ID
     * @return array|false Revenue data or false if not found
     */
    public function getRevenueById($id, $userId)
    {
        $sql = "SELECT r.*, p.name as property_name
                FROM {$this->table} r
                JOIN properties p ON r.property_id = p.id
                WHERE r.id = :id AND r.user_id = :user_id
                LIMIT 1";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new revenue
     * 
     * @param array $data Revenue data
     * @return int|false New revenue ID or false on failure
     */
    public function createRevenue($data)
    {
        return $this->create($data);
    }
    
    /**
     * Update a revenue
     * 
     * @param int $id Revenue ID
     * @param int $userId User ID
     * @param array $data Updated data
     * @return bool Success or failure
     */
    public function updateRevenue($id, $userId, $data)
    {
        // Check if revenue exists and belongs to user
        $revenue = $this->getRevenueById($id, $userId);
        
        if (!$revenue) {
            return false;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Delete a revenue
     * 
     * @param int $id Revenue ID
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function deleteRevenue($id, $userId)
    {
        // Check if revenue exists and belongs to user
        $revenue = $this->getRevenueById($id, $userId);
        
        if (!$revenue) {
            return false;
        }
        
        return $this->delete($id);
    }
    
    /**
     * Get total revenues for a user
     * 
     * @param int $userId User ID
     * @param int $year Optional year for filtering
     * @return float Total revenues
     */
    public function getTotalByUser($userId, $year = null)
    {
        $sql = "SELECT SUM(amount) as total FROM {$this->table} WHERE user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($year) {
            $sql .= " AND YEAR(revenue_date) = :year";
            $params['year'] = $year;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ? (float)$result['total'] : 0;
    }
    
    /**
     * Get revenues for a user with optional limit
     * 
     * @param int $userId User ID
     * @param int $limit Optional limit
     * @return array Revenues
     */
    public function findByUserId($userId, $limit = null)
    {
        $sql = "SELECT r.*, p.name as property_name
                FROM {$this->table} r
                JOIN properties p ON r.property_id = p.id
                WHERE r.user_id = :user_id
                ORDER BY r.revenue_date DESC";
                
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get monthly revenue totals for a user
     * 
     * @param int $userId User ID
     * @param int $year Year
     * @return array Monthly totals
     */
    public function getMonthlyTotals($userId, $year)
    {
        $sql = "SELECT MONTH(revenue_date) as month, SUM(amount) as total
                FROM {$this->table}
                WHERE user_id = :user_id AND YEAR(revenue_date) = :year
                GROUP BY MONTH(revenue_date)
                ORDER BY month";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'year' => $year
        ]);
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Initialize all months with zero
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = 0;
        }
        
        // Fill with actual data
        foreach ($results as $row) {
            $months[(int)$row['month']] = (float)$row['total'];
        }
        
        return $months;
    }
    
    /**
     * Get monthly summary of revenues
     * 
     * @param int $userId User ID
     * @param int $year Year
     * @return array Monthly summary
     */
    public function getMonthlySummary($userId, $year)
    {
        $sql = "SELECT 
                MONTH(revenue_date) as month,
                COUNT(*) as count,
                SUM(amount) as total,
                AVG(amount) as average,
                MIN(amount) as minimum,
                MAX(amount) as maximum
                FROM {$this->table}
                WHERE user_id = :user_id AND YEAR(revenue_date) = :year
                GROUP BY MONTH(revenue_date)
                ORDER BY month";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'year' => $year
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get revenue breakdown by category
     * 
     * @param int $userId User ID
     * @param int $year Year
     * @return array Category breakdown
     */
    public function getCategoryBreakdown($userId, $year)
    {
        $sql = "SELECT 
                category,
                COUNT(*) as count,
                SUM(amount) as total
                FROM {$this->table}
                WHERE user_id = :user_id AND YEAR(revenue_date) = :year
                GROUP BY category
                ORDER BY total DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'year' => $year
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
