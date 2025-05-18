<?php
namespace Core;

use Symfony\Component\Yaml\Yaml;

/**
 * Module class
 * Handles module loading and management
 */
class Module
{
    /**
     * Configuration
     */
    protected $config;
    
    /**
     * Loaded modules
     */
    protected $modules = [];
    
    /**
     * Constructor
     * 
     * @param Config $config Configuration instance
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }
    
    /**
     * Load all enabled modules
     * 
     * @return void
     */
    public function loadModules()
    {
        // Get enabled modules from configuration
        $enabledModules = $this->config->get('modules.enabled', []);
        
        foreach ($enabledModules as $moduleName) {
            $this->loadModule($moduleName);
        }
    }
    
    /**
     * Load a specific module
     * 
     * @param string $name Module name
     * @return bool Success or failure
     */
    public function loadModule($name)
    {
        // Check if module configuration exists
        $moduleConfig = $this->config->getModule($name);
        
        if (!$moduleConfig) {
            // Try to load from file
            $file = APP_ROOT . "/Modules/{$name}/module.yaml";
            
            if (file_exists($file)) {
                $moduleConfig = Yaml::parseFile($file);
            } else {
                return false;
            }
        }
        
        // Check if module is already loaded
        if (isset($this->modules[$name])) {
            return true;
        }
        
        // Add module to loaded modules
        $this->modules[$name] = $moduleConfig;
        
        // Check for dependencies
        if (isset($moduleConfig['dependencies']) && is_array($moduleConfig['dependencies'])) {
            foreach ($moduleConfig['dependencies'] as $dependency) {
                // Load dependency if not already loaded
                if (!isset($this->modules[$dependency])) {
                    $this->loadModule($dependency);
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get all loaded modules
     * 
     * @return array
     */
    public function getModules()
    {
        return $this->modules;
    }
    
    /**
     * Get a specific module configuration
     * 
     * @param string $name Module name
     * @return array|null
     */
    public function getModule($name)
    {
        return $this->modules[$name] ?? null;
    }
    
    /**
     * Check if a module is loaded
     * 
     * @param string $name Module name
     * @return bool
     */
    public function isLoaded($name)
    {
        return isset($this->modules[$name]);
    }
    
    /**
     * Get module menu items
     * 
     * @return array
     */
    public function getMenuItems()
    {
        $menuItems = [];
        
        foreach ($this->modules as $name => $module) {
            if (isset($module['menu']) && is_array($module['menu'])) {
                foreach ($module['menu'] as $item) {
                    $item['module'] = $name;
                    $menuItems[] = $item;
                }
            }
        }
        
        // Sort by order
        usort($menuItems, function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA - $orderB;
        });
        
        return $menuItems;
    }
    
    /**
     * Get module dashboard widgets
     * 
     * @return array
     */
    public function getDashboardWidgets()
    {
        $widgets = [];
        
        foreach ($this->modules as $name => $module) {
            if (isset($module['dashboard_widgets']) && is_array($module['dashboard_widgets'])) {
                foreach ($module['dashboard_widgets'] as $widget) {
                    $widget['module'] = $name;
                    $widgets[] = $widget;
                }
            }
        }
        
        // Sort by order
        usort($widgets, function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA - $orderB;
        });
        
        return $widgets;
    }
}
