<?php
namespace Core;

/**
 * Auth class
 * Handles user authentication and authorization
 */
class Auth
{
    /**
     * User model
     */
    protected $userModel;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->userModel = new \Modules\Core\User\Models\UserModel();
    }
    
    /**
     * Attempt to login a user
     * 
     * @param string $username Username
     * @param string $password Password
     * @return bool Success or failure
     */
    public function login($username, $password)
    {
        // Get user by username
        $user = $this->userModel->findOneBy('username', $username);
        
        // Check if user exists and password is correct
        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Register a new user
     * 
     * @param array $data User data
     * @return int|false User ID or false on failure
     */
    public function register($data)
    {
        // Check if username already exists
        if ($this->userModel->findOneBy('username', $data['username'])) {
            return false;
        }
        
        // Check if email already exists
        if ($this->userModel->findOneBy('email', $data['email'])) {
            return false;
        }
        
        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Set default role
        $data['role'] = $data['role'] ?? 'user';
        
        // Create user
        return $this->userModel->create($data);
    }
    
    /**
     * Logout the current user
     * 
     * @return void
     */
    public function logout()
    {
        // Unset session variables
        unset($_SESSION['user_id']);
        unset($_SESSION['user_username']);
        unset($_SESSION['user_role']);
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Check if a user is logged in
     * 
     * @return bool
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Get the current user
     * 
     * @return array|false User data or false if not logged in
     */
    public function getCurrentUser()
    {
        if ($this->isLoggedIn()) {
            return $this->userModel->findById($_SESSION['user_id']);
        }
        
        return false;
    }
    
    /**
     * Check if the current user has a specific role
     * 
     * @param string|array $roles Role(s) to check
     * @return bool
     */
    public function hasRole($roles)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Convert single role to array
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['user_role'], $roles);
    }
    
    /**
     * Check if the current user has a specific permission
     * 
     * @param string $permission Permission to check
     * @return bool
     */
    public function hasPermission($permission)
    {
        // Simple permission system based on role
        // Can be expanded to a more complex permission system
        
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $role = $_SESSION['user_role'];
        
        // Admin has all permissions
        if ($role === 'admin') {
            return true;
        }
        
        // Basic role-based permissions
        $permissions = [
            'user' => [
                'dashboard.view',
                'properties.view', 'properties.add', 'properties.edit', 'properties.delete',
                'revenues.view', 'revenues.add', 'revenues.edit', 'revenues.delete',
                'expenses.view', 'expenses.add', 'expenses.edit', 'expenses.delete',
                'depreciation.view', 'depreciation.add', 'depreciation.edit',
                'reports.view', 'reports.generate',
                'profile.edit'
            ]
        ];
        
        return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
    }
}
