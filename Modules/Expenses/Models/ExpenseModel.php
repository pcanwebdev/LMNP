<?php
namespace Modules\Finance\Expenses\Models;

use Core\Model;

/**
 * Expense Model
 * Handles expense-related database operations
 */
class ExpenseModel extends Model
{
    /**
     * Table name
     */
    protected $table = 'expenses';
    
    /**
     * Fillable fields
     */
    protected $fillable = [
        'property_id',
        'user_id',
        'amount',
        'expense_date',
        'description',
        'category',
        'receipt_path',
        'is_deductible'
    ];
    
    /**
     * Get expenses filtered by various criteria
     * 
     * @param int $userId User ID
     * @param array $filter Filter criteria
     * @return array Filtered expenses
     */
    public function getFilteredExpenses($userId, $filter = [])
    {
        $conditions = ['e.user_id = :user_id'];
        $params = ['user_id' => $userId];
        
        // Add property filter
        if (!empty($filter['property_id'])) {
            $conditions[] = 'e.property_id = :property_id';
            $params['property_id'] = $filter['property_id'];
        }
        
        // Add year filter
        if (!empty($filter['year'])) {
            $conditions[] = 'YEAR(e.expense_date) = :year';
            $params['year'] = $filter['year'];
        }
        
        // Add month filter
        if (!empty($filter['month'])) {
            $conditions[] = 'MONTH(e.expense_date) = :month';
            $params['month'] = $filter['month'];
        }
        
        // Add category filter
        if (!empty($filter['category'])) {
            $conditions[] = 'e.category = :category';
            $params['category'] = $filter['category'];
        }
        
        // Add deductible filter
        if (isset($filter['deductible']) && $filter['deductible'] !== '') {
            $conditions[] = 'e.is_deductible = :deductible';
            $params['deductible'] = $filter['deductible'];
        }
        
        // Build WHERE clause
        $where = implode(' AND ', $conditions);
        
        // Build query
        $sql = "SELECT e.*, p.name as property_name 
                FROM {$this->table} e
                JOIN properties p ON e.property_id = p.id
                WHERE {$where}
                ORDER BY e.expense_date DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get an expense by ID and user ID
     * 
     * @param int $id Expense ID
     * @param int $userId User ID
     * @return array|false Expense data or false if not found
     */
    public function getExpenseById($id, $userId)
    {
        $sql = "SELECT e.*, p.name as property_name
                FROM {$this->table} e
                JOIN properties p ON e.property_id = p.id
                WHERE e.id = :id AND e.user_id = :user_id
                LIMIT 1";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new expense
     * 
     * @param array $data Expense data
     * @return int|false New expense ID or false on failure
     */
    public function createExpense($data)
    {
        return $this->create($data);
    }
    
    /**
     * Update an expense
     * 
     * @param int $id Expense ID
     * @param int $userId User ID
     * @param array $data Updated data
     * @return bool Success or failure
     */
    public function updateExpense($id, $userId, $data)
    {
        // Check if expense exists and belongs to user
        $expense = $this->getExpenseById($id, $userId);
        
        if (!$expense) {
            return false;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Delete an expense
     * 
     * @param int $id Expense ID
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function deleteExpense($id, $userId)
    {
        // Check if expense exists and belongs to user
        $expense = $this->getExpenseById($id, $userId);
        
        if (!$expense) {
            return false;
        }
        
        return $this->delete($id);
    }
    
    /**
     * Get total expenses for a user
     * 
     * @param int $userId User ID
     * @param int $year Optional year for filtering
     * @return float Total expenses
     */
    public function getTotalByUser($userId, $year = null)
    {
        $sql = "SELECT SUM(amount) as total FROM {$this->table} WHERE user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($year) {
            $sql .= " AND YEAR(expense_date) = :year";
            $params['year'] = $year;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ? (float)$result['total'] : 0;
    }
    
    /**
     * Get expenses for a user with optional limit
     * 
     * @param int $userId User ID
     * @param int $limit Optional limit
     * @return array Expenses
     */
    public function findByUserId($userId, $limit = null)
    {
        $sql = "SELECT e.*, p.name as property_name
                FROM {$this->table} e
                JOIN properties p ON e.property_id = p.id
                WHERE e.user_id = :user_id
                ORDER BY e.expense_date DESC";
                
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get monthly expense totals for a user
     * 
     * @param int $userId User ID
     * @param int $year Year
     * @return array Monthly totals
     */
    public function getMonthlyTotals($userId, $year)
    {
        $sql = "SELECT MONTH(expense_date) as month, SUM(amount) as total
                FROM {$this->table}
                WHERE user_id = :user_id AND YEAR(expense_date) = :year
                GROUP BY MONTH(expense_date)
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
     * Get monthly summary of expenses
     * 
     * @param int $userId User ID
     * @param int $year Year
     * @return array Monthly summary
     */
    public function getMonthlySummary($userId, $year)
    {
        $sql = "SELECT 
                MONTH(expense_date) as month,
                COUNT(*) as count,
                SUM(amount) as total,
                AVG(amount) as average,
                MIN(amount) as minimum,
                MAX(amount) as maximum
                FROM {$this->table}
                WHERE user_id = :user_id AND YEAR(expense_date) = :year
                GROUP BY MONTH(expense_date)
                ORDER BY month";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'year' => $year
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get expense breakdown by category
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
                WHERE user_id = :user_id AND YEAR(expense_date) = :year
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
