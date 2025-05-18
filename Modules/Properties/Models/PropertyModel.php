<?php
namespace Modules\Finance\Properties\Models;

use Core\Model;

/**
 * Property Model
 * Handles property-related database operations
 */
class PropertyModel extends Model
{
    /**
     * Table name
     */
    protected $table = 'properties';
    
    /**
     * Fillable fields
     */
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'acquisition_date',
        'acquisition_price',
        'ownership_percentage',
        'property_type',
        'status',
        'notes'
    ];
    
    /**
     * Find properties by user ID
     * 
     * @param int $userId User ID
     * @param int $limit Optional limit
     * @param string $orderBy Column to order by
     * @param string $direction Order direction
     * @return array Properties
     */
    public function findByUserId($userId, $limit = null, $orderBy = 'acquisition_date', $direction = 'DESC')
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id 
                ORDER BY {$orderBy} {$direction}";
                
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a property by ID and user ID
     * 
     * @param int $id Property ID
     * @param int $userId User ID
     * @return array|false Property or false if not found
     */
    public function getPropertyById($id, $userId)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE id = :id AND user_id = :user_id 
                LIMIT 1";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new property
     * 
     * @param array $data Property data
     * @return int|false New property ID or false on failure
     */
    public function createProperty($data)
    {
        return $this->create($data);
    }
    
    /**
     * Update a property
     * 
     * @param int $id Property ID
     * @param int $userId User ID
     * @param array $data Updated data
     * @return bool Success or failure
     */
    public function updateProperty($id, $userId, $data)
    {
        // Check if property exists and belongs to user
        $property = $this->getPropertyById($id, $userId);
        
        if (!$property) {
            return false;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Delete a property
     * 
     * @param int $id Property ID
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function deleteProperty($id, $userId)
    {
        // Check if property exists and belongs to user
        $property = $this->getPropertyById($id, $userId);
        
        if (!$property) {
            return false;
        }
        
        return $this->delete($id);
    }
    
    /**
     * Get summary of property data
     * 
     * @param int $userId User ID
     * @return array Summary data
     */
    public function getPropertySummary($userId)
    {
        // Get total value of properties
        $sql = "SELECT 
                COUNT(*) as total_count,
                SUM(acquisition_price) as total_value,
                SUM(acquisition_price * (ownership_percentage / 100)) as owned_value,
                MAX(acquisition_date) as latest_acquisition
                FROM {$this->table}
                WHERE user_id = :user_id";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get properties with their financial summary
     * 
     * @param int $userId User ID
     * @param int $year Optional year for filtering
     * @return array Properties with financial data
     */
    public function getPropertiesWithFinancials($userId, $year = null)
    {
        $yearFilter = $year ? " AND YEAR(r.revenue_date) = {$year}" : "";
        $yearFilterExpense = $year ? " AND YEAR(e.expense_date) = {$year}" : "";
        
        $sql = "SELECT 
                p.*,
                COALESCE(r.total_revenue, 0) as total_revenue,
                COALESCE(e.total_expense, 0) as total_expense,
                COALESCE(r.total_revenue, 0) - COALESCE(e.total_expense, 0) as net_income
                FROM {$this->table} p
                LEFT JOIN (
                    SELECT property_id, SUM(amount) as total_revenue
                    FROM revenues r
                    WHERE r.user_id = :user_id {$yearFilter}
                    GROUP BY property_id
                ) r ON p.id = r.property_id
                LEFT JOIN (
                    SELECT property_id, SUM(amount) as total_expense
                    FROM expenses e
                    WHERE e.user_id = :user_id {$yearFilterExpense}
                    GROUP BY property_id
                ) e ON p.id = e.property_id
                WHERE p.user_id = :user_id
                ORDER BY p.name";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
