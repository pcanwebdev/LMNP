<?php
namespace Core;

/**
 * Base Controller class
 * All controllers should extend this class
 */
class Controller
{
    /**
     * Database instance
     */
    protected $db;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Render a view with the given data
     * 
     * @param string $view View file
     * @param array $data Data to pass to the view
     * @return string Rendered view
     */
    protected function render($view, $data = [])
    {
        global $twig;
        return $twig->render($view, $data);
    }
    
    /**
     * Redirect to a URL
     * 
     * @param string $url URL to redirect to
     * @return void
     */
    protected function redirect($url)
    {
        header("Location: {$url}");
        exit;
    }
    
    /**
     * Get POST data
     * 
     * @param string $key Key to get
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    protected function post($key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }
    
    /**
     * Get GET data
     * 
     * @param string $key Key to get
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    protected function get($key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Set a session value
     * 
     * @param string $key Key to set
     * @param mixed $value Value to set
     * @return void
     */
    protected function setSession($key, $value)
    {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get a session value
     * 
     * @param string $key Key to get
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    protected function getSession($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Delete a session value
     * 
     * @param string $key Key to delete
     * @return void
     */
    protected function deleteSession($key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Set a flash message
     * 
     * @param string $type Message type (success, error, info, warning)
     * @param string $message Message to set
     * @return void
     */
    protected function setFlash($type, $message)
    {
        $_SESSION['flash'][$type] = $message;
    }
    
    /**
     * Get flash messages
     * 
     * @return array Flash messages
     */
    protected function getFlash()
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
}