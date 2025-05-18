<?php
namespace Modules\Core\Settings\Models;

use Core\Model;

/**
 * Settings Model
 * Handles application settings stored in the database
 */
class SettingsModel extends Model
{
    /**
     * Table name
     */
    protected $table = 'settings';
    
    /**
     * Fillable fields
     */
    protected $fillable = [
        'user_id',
        'setting_key',
        'setting_value'
    ];
    
    /**
     * Get a specific setting for a user
     * 
     * @param int $userId User ID
     * @param string $key Setting key
     * @return mixed Setting value or null if not found
     */
    public function getSetting($userId, $key)
    {
        $sql = "SELECT setting_value FROM {$this->table} 
                WHERE user_id = :user_id AND setting_key = :key LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'key' => $key
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result) {
            $value = $result['setting_value'];
            
            // Try to decode JSON values
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            return $value;
        }
        
        return null;
    }
    
    /**
     * Set a setting for a user
     * 
     * @param int $userId User ID
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success or failure
     */
    public function setSetting($userId, $key, $value)
    {
        // Convert arrays/objects to JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        // Check if setting already exists
        $existing = $this->findOneBy('setting_key', $key, ['user_id' => $userId]);
        
        if ($existing) {
            // Update existing setting
            $sql = "UPDATE {$this->table} 
                    SET setting_value = :value 
                    WHERE user_id = :user_id AND setting_key = :key";
                    
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'key' => $key,
                'value' => $value
            ]);
        } else {
            // Create new setting
            $data = [
                'user_id' => $userId,
                'setting_key' => $key,
                'setting_value' => $value
            ];
            
            return $this->create($data) ? true : false;
        }
    }
    
    /**
     * Get all settings for a user
     * 
     * @param int $userId User ID
     * @return array User settings
     */
    public function getUserSettings($userId)
    {
        $sql = "SELECT setting_key, setting_value FROM {$this->table} 
                WHERE user_id = :user_id";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $settings = [];
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $key = $row['setting_key'];
            $value = $row['setting_value'];
            
            // Try to decode JSON values
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $settings[$key] = $decoded;
            } else {
                $settings[$key] = $value;
            }
        }
        
        return $settings;
    }
    
    /**
     * Delete a setting
     * 
     * @param int $userId User ID
     * @param string $key Setting key
     * @return bool Success or failure
     */
    public function deleteSetting($userId, $key)
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE user_id = :user_id AND setting_key = :key";
                
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'key' => $key
        ]);
    }
    
    /**
     * Find one setting by key and additional conditions
     * 
     * @param string $field Field to search by
     * @param mixed $value Value to search for
     * @param array $conditions Additional conditions
     * @return array|false Setting or false if not found
     */
    private function findOneBy($field, $value, $conditions = [])
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = :value";
        $params = ['value' => $value];
        
        foreach ($conditions as $key => $val) {
            $sql .= " AND {$key} = :{$key}";
            $params[$key] = $val;
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
