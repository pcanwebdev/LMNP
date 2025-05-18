<?php
namespace Core;

/**
 * Router class
 * Handles routing requests to the appropriate controller and action
 */
class Router
{
    /**
     * Default module, controller and action
     */
    protected $defaultModule = 'Core/Dashboard';
    protected $defaultController = 'Dashboard';
    protected $defaultAction = 'index';
    
    /**
     * Module, controller and action from the current request
     */
    protected $module;
    protected $controller;
    protected $action;
    protected $params = [];
    
    /**
     * Dispatch the request to the appropriate controller
     * 
     * @param string $uri The request URI
     * @return void
     */
    public function dispatch($uri)
    {
        // Parse the URL
        $this->parseUrl($uri);
        
        // Check if user is authenticated for non-public routes
        $this->checkAuth();
        
        // Get the controller class
        $controllerClass = $this->getControllerClass();
        
        // Check if controller class exists
        if (class_exists($controllerClass)) {
            $controller = new $controllerClass();
            
            // Check if the action method exists
            if (method_exists($controller, $this->action)) {
                // Call the action with parameters
                call_user_func_array([$controller, $this->action], $this->params);
            } else {
                // Action not found, show 404 page
                $this->handleError(404, "Action '{$this->action}' not found");
            }
        } else {
            // Controller not found, show 404 page
            $this->handleError(404, "Controller '{$controllerClass}' not found");
        }
    }
    
    /**
     * Parse the URL into module, controller, action and parameters
     * 
     * @param string $url The URL to parse
     * @return void
     */
    protected function parseUrl($url)
    {
        // Remove query string
        $url = parse_url($url, PHP_URL_PATH);
        
        // Trim trailing slash
        $url = rtrim($url, '/');
        
        // If empty, use default
        if (empty($url)) {
            $this->module = $this->defaultModule;
            $this->controller = $this->defaultController;
            $this->action = $this->defaultAction;
            return;
        }       
        // Split URL into parts
        $parts = explode('/', ltrim($url, '/'));
print_r($parts);
        // Check if we have enough parts for module/controller/action
        if (count($parts) >= 1) {
            // Pour les modules Core, le premier segment est le nom du module
            if (in_array($parts[0], ['User', 'Dashboard', 'Settings'])) {
                $this->module = 'Core/Modules/' . $parts[0];
               // array_shift($parts);
            } else {
                // Pour les modules normaux
                $this->module = 'Modules/';
            }

            // Get the controller name
            $this->controller = array_shift($parts);
            
            // Get the action name if available, otherwise use default
            $this->action = !empty($parts) ? array_shift($parts) : $this->defaultAction;
            
            // Remaining parts are parameters
            $this->params = $parts;
        } else {
            // Not enough parts, use defaults
            $this->module = $this->defaultModule;
            $this->controller = $this->defaultController;
            $this->action = $this->defaultAction;
        }
    }
    
    /**
     * Get the full controller class name based on module and controller
     * 
     * @return string The controller class name
     */
    protected function getControllerClass()
    {
        if (strpos($this->module, 'Core/') === 0) {
            // Pour les modules Core
            return "\\Core\\Modules\\" . substr($this->module, 13) . "\\Controllers\\{$this->controller}Controller";
        } else {
            // Pour les autres modules
            return "\\Modules\\{$this->controller}\\Controllers\\{$this->controller}Controller";
        }
    }
    
    /**
     * Check if user is authenticated for non-public routes
     * 
     * @return void
     */
    protected function checkAuth()
    {
        // Skip auth check for login/register pages
        if ($this->module === 'Core/Modules/User' && 
            in_array($this->action, ['login', 'register', 'doLogin', 'doRegister'])) {
            return;
        }
        
        // Check if user is authenticated
        if (!isset($_SESSION['user_id'])) {
            header('Location: /User/login');
            exit;
        }
    }
    
    /**
     * Handle errors like 404 Not Found
     * 
     * @param int $code HTTP error code
     * @param string $message Error message
     * @return void
     */
    protected function handleError($code, $message)
    {
        http_response_code($code);
        
        // Use error template if available
        global $twig;
        if ($twig) {
            echo $twig->render('error.twig', [
                'code' => $code,
                'message' => $message
            ]);
        } else {
            echo "<h1>Error {$code}</h1>";
            echo "<p>{$message}</p>";
        }
        
        exit;
    }
}
