<?php
namespace Modules\Accounting\Depreciation\Models;

use Core\Model;

/**
 * Depreciation Model
 * Handles depreciation-related database operations
 */
class DepreciationModel extends Model
{
    /**
     * Assets table name
     */
    protected $assetsTable = 'depreciation_assets';
    
    /**
     * Entries table name
     */
    protected $entriesTable = 'depreciation_entries';
    
    /**
     * Categories table name
     */
    protected $categoriesTable = 'depreciation_categories';
    
    /**
     * Fillable fields for assets
     */
    protected $assetsFillable = [
        'property_id',
        'user_id',
        'category_id',
        'name',
        'acquisition_date',
        'base_value',
        'duration',
        'rate',
        'status',
        'notes'
    ];
    
    /**
     * Fillable fields for entries
     */
    protected $entriesFillable = [
        'asset_id',
        'year',
        'amount',
        'accumulated',
        'remaining'
    ];
    
    /**
     * Get all depreciation categories
     * 
     * @return array Categories
     */
    public function getDepreciationCategories()
    {
        $sql = "SELECT * FROM {$this->categoriesTable} ORDER BY category_type, name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a depreciation category by ID
     * 
     * @param int $id Category ID
     * @return array|false Category or false if not found
     */
    public function getCategoryById($id)
    {
        $sql = "SELECT * FROM {$this->categoriesTable} WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get depreciation assets filtered by various criteria
     * 
     * @param int $userId User ID
     * @param array $filter Filter criteria
     * @return array Filtered assets
     */
    public function getFilteredAssets($userId, $filter = [])
    {
        $conditions = ['a.user_id = :user_id'];
        $params = ['user_id' => $userId];
        
        // Add property filter
        if (!empty($filter['property_id'])) {
            $conditions[] = 'a.property_id = :property_id';
            $params['property_id'] = $filter['property_id'];
        }
        
        // Add category type filter
        if (!empty($filter['category_type'])) {
            $conditions[] = 'c.category_type = :category_type';
            $params['category_type'] = $filter['category_type'];
        }
        
        // Add status filter
        if (!empty($filter['status'])) {
            $conditions[] = 'a.status = :status';
            $params['status'] = $filter['status'];
        }
        
        // Build WHERE clause
        $where = implode(' AND ', $conditions);
        
        // Build query
        $sql = "SELECT a.*, p.name as property_name, c.name as category_name, c.category_type
                FROM {$this->assetsTable} a
                JOIN properties p ON a.property_id = p.id
                JOIN {$this->categoriesTable} c ON a.category_id = c.id
                WHERE {$where}
                ORDER BY a.acquisition_date DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a depreciation asset by ID and user ID
     * 
     * @param int $id Asset ID
     * @param int $userId User ID
     * @return array|false Asset or false if not found
     */
    public function getAssetById($id, $userId)
    {
        $sql = "SELECT a.*, p.name as property_name, c.name as category_name, c.category_type
                FROM {$this->assetsTable} a
                JOIN properties p ON a.property_id = p.id
                JOIN {$this->categoriesTable} c ON a.category_id = c.id
                WHERE a.id = :id AND a.user_id = :user_id
                LIMIT 1";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new depreciation asset
     * 
     * @param array $data Asset data
     * @return int|false New asset ID or false on failure
     */
    public function createAsset($data)
    {
        $this->table = $this->assetsTable;
        $this->fillable = $this->assetsFillable;
        
        return $this->create($data);
    }
    
    /**
     * Update a depreciation asset
     * 
     * @param int $id Asset ID
     * @param int $userId User ID
     * @param array $data Updated data
     * @return bool Success or failure
     */
    public function updateAsset($id, $userId, $data)
    {
        // Check if asset exists and belongs to user
        $asset = $this->getAssetById($id, $userId);
        
        if (!$asset) {
            return false;
        }
        
        $this->table = $this->assetsTable;
        $this->fillable = $this->assetsFillable;
        
        return $this->update($id, $data);
    }
    
    /**
     * Delete a depreciation asset
     * 
     * @param int $id Asset ID
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function deleteAsset($id, $userId)
    {
        // Check if asset exists and belongs to user
        $asset = $this->getAssetById($id, $userId);
        
        if (!$asset) {
            return false;
        }
        
        // Delete associated entries first
        $sql = "DELETE FROM {$this->entriesTable} WHERE asset_id = :asset_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['asset_id' => $id]);
        
        // Now delete the asset
        $this->table = $this->assetsTable;
        return $this->delete($id);
    }
    
    /**
     * Get depreciation entries for an asset for a specific year
     * 
     * @param int $assetId Asset ID
     * @param int $year Year
     * @return array Entries
     */
    public function getDepreciationEntries($assetId, $year)
    {
        $sql = "SELECT * FROM {$this->entriesTable} 
                WHERE asset_id = :asset_id AND year = :year
                ORDER BY year";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'asset_id' => $assetId,
            'year' => $year
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all depreciation entries for an asset
     * 
     * @param int $assetId Asset ID
     * @return array Entries
     */
    public function getAllDepreciationEntries($assetId)
    {
        $sql = "SELECT * FROM {$this->entriesTable} 
                WHERE asset_id = :asset_id
                ORDER BY year";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['asset_id' => $assetId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate depreciation entries for an asset
     * 
     * @param int $assetId Asset ID
     * @return bool Success or failure
     */
    public function generateDepreciationEntries($assetId)
    {
        // Get asset details
        $sql = "SELECT * FROM {$this->assetsTable} WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $assetId]);
        $asset = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$asset) {
            return false;
        }
        
        // Clear existing entries
        $sql = "DELETE FROM {$this->entriesTable} WHERE asset_id = :asset_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['asset_id' => $assetId]);
        
        // Get acquisition year
        $acquisitionYear = date('Y', strtotime($asset['acquisition_date']));
        
        // Generate entries for each year
        $baseValue = $asset['base_value'];
        $duration = $asset['duration'];
        $rate = $asset['rate'];
        
        $accumulated = 0;
        
        for ($year = 0; $year < $duration; $year++) {
            $currentYear = $acquisitionYear + $year;
            
            // Calculate depreciation for this year
            $depreciation = calculateDepreciation($baseValue, $rate, $year + 1, $duration);
            
            // Insert entry
            $entryData = [
                'asset_id' => $assetId,
                'year' => $currentYear,
                'amount' => $depreciation['annual'],
                'accumulated' => $depreciation['accumulated'],
                'remaining' => $depreciation['remaining']
            ];
            
            $this->table = $this->entriesTable;
            $this->fillable = $this->entriesFillable;
            $this->create($entryData);
        }
        
        return true;
    }
    
    /**
     * Get total depreciation for a user for a specific year
     * 
     * @param int $userId User ID
     * @param int $year Year
     * @return array Depreciation totals
     */
    public function getTotalDepreciation($userId, $year)
    {
        $sql = "SELECT 
                SUM(e.amount) as total_amount,
                SUM(e.accumulated) as total_accumulated,
                SUM(e.remaining) as total_remaining,
                SUM(a.base_value) as total_base_value
                FROM {$this->entriesTable} e
                JOIN {$this->assetsTable} a ON e.asset_id = a.id
                WHERE a.user_id = :user_id AND e.year = :year";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'year' => $year
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ? $result : [
            'total_amount' => 0,
            'total_accumulated' => 0,
            'total_remaining' => 0,
            'total_base_value' => 0
        ];
    }
    
    /**
     * Get depreciation summary by category type
     * 
     * @param int $userId User ID
     * @param int $year Year
     * @return array Summary by category type
     */
    public function getDepreciationByCategory($userId, $year)
    {
        $sql = "SELECT 
                c.category_type,
                SUM(e.amount) as total_amount,
                SUM(e.accumulated) as total_accumulated,
                SUM(e.remaining) as total_remaining,
                SUM(a.base_value) as total_base_value
                FROM {$this->entriesTable} e
                JOIN {$this->assetsTable} a ON e.asset_id = a.id
                JOIN {$this->categoriesTable} c ON a.category_id = c.id
                WHERE a.user_id = :user_id AND e.year = :year
                GROUP BY c.category_type";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'year' => $year
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get depreciation assets for a property
     * 
     * @param int $propertyId Property ID
     * @param int $userId User ID
     * @return array Assets
     */
    public function getAssetsByProperty($propertyId, $userId)
    {
        $sql = "SELECT a.*, c.name as category_name, c.category_type
                FROM {$this->assetsTable} a
                JOIN {$this->categoriesTable} c ON a.category_id = c.id
                WHERE a.property_id = :property_id AND a.user_id = :user_id
                ORDER BY a.acquisition_date DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'property_id' => $propertyId,
            'user_id' => $userId
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
