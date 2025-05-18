<?php
namespace Core\Modules\User\Models;

use Core\Model;

/**
 * User Model
 * Gère les opérations liées aux utilisateurs dans la base de données
 */
class UserModel extends Model
{
    /**
     * Table name
     */
    protected $table = 'users';
    
    /**
     * Authenticate a user
     * 
     * @param string $username Username or email
     * @param string $password Password to verify
     * @return array|false User data if authenticated, false otherwise
     */
    public function authenticate($username, $password)
    {
        // Check if username is an email
        $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        // Find user by username or email
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$field} = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        
        return false;
    }
    
    /**
     * Get user by ID
     * 
     * @param int $id User ID
     * @return array|false User data if found, false otherwise
     */
    public function getUserById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get user by username
     * 
     * @param string $username Username to search for
     * @return array|false User data if found, false otherwise
     */
    public function getUserByUsername($username)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    /**
     * Get user by email
     * 
     * @param string $email Email to search for
     * @return array|false User data if found, false otherwise
     */
    public function getUserByEmail($email)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    /**
     * Create a new user
     * 
     * @param string $username Username
     * @param string $email Email
     * @param string $password Password (will be hashed)
     * @param string $role User role (default: 'user')
     * @return int|false User ID if created, false otherwise
     */
    public function createUser($username, $email, $password, $role = 'user')
    {
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Create user
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (username, email, password, role)
            VALUES (?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([$username, $email, $passwordHash, $role]);
        
        if ($success) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update user information
     * 
     * @param int $id User ID
     * @param string $username New username
     * @param string $email New email
     * @param string|null $password New password (null to keep current)
     * @return bool True if updated, false otherwise
     */
    public function updateUser($id, $username, $email, $password = null)
    {
        // If password is provided, update with new password
        if ($password !== null) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("
                UPDATE {$this->table}
                SET username = ?, email = ?, password = ?
                WHERE id = ?
            ");
            return $stmt->execute([$username, $email, $passwordHash, $id]);
        }
        
        // Otherwise, only update username and email
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET username = ?, email = ?
            WHERE id = ?
        ");
        return $stmt->execute([$username, $email, $id]);
    }
    
    /**
     * Save the remember me token for a user
     * 
     * @param int $userId User ID
     * @param string $token Remember token
     * @return bool True if saved, false otherwise
     */
    public function saveRememberToken($userId, $token)
    {
        // Insert or update the token in the settings table
        $stmt = $this->db->prepare("
            INSERT INTO settings (user_id, setting_key, setting_value)
            VALUES (?, 'remember_token', ?)
            ON CONFLICT (user_id, setting_key) DO UPDATE
            SET setting_value = ?
        ");
        
        return $stmt->execute([$userId, $token, $token]);
    }
    
    /**
     * Verify a remember token
     * 
     * @param string $token Token to verify
     * @return array|false User data if valid, false otherwise
     */
    public function verifyRememberToken($token)
    {
        // Get user by token
        $stmt = $this->db->prepare("
            SELECT u.*
            FROM {$this->table} u
            JOIN settings s ON u.id = s.user_id
            WHERE s.setting_key = 'remember_token'
            AND s.setting_value = ?
        ");
        
        $stmt->execute([$token]);
        return $stmt->fetch();
    }
    
    /**
     * Save a password reset token for a user
     * 
     * @param int $userId User ID
     * @param string $token Reset token
     * @return bool True if saved, false otherwise
     */
    public function saveResetToken($userId, $token)
    {
        // Insert or update the token in the settings table
        $stmt = $this->db->prepare("
            INSERT INTO settings (user_id, setting_key, setting_value)
            VALUES (?, 'reset_token', ?)
            ON CONFLICT (user_id, setting_key) DO UPDATE
            SET setting_value = ?
        ");
        
        return $stmt->execute([$userId, $token, $token]);
    }
    
    /**
     * Verify a password reset token
     * 
     * @param string $token Token to verify
     * @return array|false User data if valid, false otherwise
     */
    public function verifyResetToken($token)
    {
        // Get user by token
        $stmt = $this->db->prepare("
            SELECT u.*
            FROM {$this->table} u
            JOIN settings s ON u.id = s.user_id
            WHERE s.setting_key = 'reset_token'
            AND s.setting_value = ?
        ");
        
        $stmt->execute([$token]);
        return $stmt->fetch();
    }
}