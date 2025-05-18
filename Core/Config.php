<?php
namespace Core;

use Symfony\Component\Yaml\Yaml;

/**
 * Config class
 * Handles loading and accessing configuration files
 */
class Config
{
    /**
     * Configuration data
     */
    protected $data = [];
    
    /**
     * Loaded module configurations
     */
    protected $modules = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Load application configuration
        $this->loadConfig('app');
        
        // Load database configuration
        $this->loadConfig('database');
        
        // Load modules configuration
        $this->loadConfig('modules');
        
        // Load module-specific configurations
        $this->loadModuleConfigurations();
    }
    
    /**
     * Load a configuration file
     * 
     * @param string $name Configuration name
     * @return void
     */
    protected function loadConfig($name)
    {
        $file = APP_ROOT . "/config/{$name}.yaml";
        
        if (file_exists($file)) {
            $config = Yaml::parseFile($file);
            $this->data[$name] = $this->processEnvironmentVariables($config);
        } else {
            // Create default configurations if they don't exist
            $this->createDefaultConfig($name);
        }
    }
    
    /**
     * Process environment variables in configuration
     * 
     * @param array $config Configuration array
     * @return array Processed configuration
     */
    protected function processEnvironmentVariables($config)
    {
        $result = [];
        
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->processEnvironmentVariables($value);
            } else if (is_string($value) && preg_match('/\${([A-Za-z0-9_]+)}/', $value, $matches)) {
                $envVar = $matches[1];
                $envValue = getenv($envVar);
                
                if ($envValue !== false) {
                    $result[$key] = $envValue;
                } else {
                    $result[$key] = $value; // Keep original if not found
                }
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Load all module configurations
     * 
     * @return void
     */
    protected function loadModuleConfigurations()
    {
        // Find all module.yaml files in the modules directory
        $moduleFiles = glob(APP_ROOT . '/Modules/*/*/module.yaml');
        
        foreach ($moduleFiles as $file) {
            // Extract module name from path
            preg_match('/Modules\/([^\/]+)\/([^\/]+)\/module\.yaml$/', $file, $matches);
            
            if (isset($matches[1]) && isset($matches[2])) {
                $category = $matches[1];
                $name = $matches[2];
                $key = "{$category}/{$name}";
                
                // Load module configuration
                $this->modules[$key] = Yaml::parseFile($file);
            }
        }
    }
    
    /**
     * Create default configuration files
     * 
     * @param string $name Configuration name
     * @return void
     */
    protected function createDefaultConfig($name)
    {
        $file = APP_ROOT . "/config/{$name}.yaml";
        $directory = dirname($file);
        
        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Create default configuration based on name
        switch ($name) {
            case 'app':
                $config = [
                    'name' => 'LMNP Accounting',
                    'version' => '1.0.0',
                    'environment' => 'development',
                    'debug' => true,
                    'timezone' => 'Europe/Paris',
                    'locale' => 'fr_FR',
                    'secret' => bin2hex(random_bytes(32)),
                    'upload_dir' => APP_ROOT . '/uploads'
                ];
                break;
                
            case 'database':
                $config = [
                    'driver' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'database' => 'lmnp_accounting',
                    'username' => 'root',
                    'password' => '',
                    'charset' => 'utf8mb4',
                    'options' => [
                        'ATTR_ERRMODE' => 'ERRMODE_EXCEPTION',
                        'ATTR_DEFAULT_FETCH_MODE' => 'FETCH_ASSOC',
                        'ATTR_EMULATE_PREPARES' => false
                    ]
                ];
                break;
                
            case 'modules':
                $config = [
                    'enabled' => [
                        'Core/Dashboard',
                        'Core/User',
                        'Core/Settings',
                        'Finance/Properties',
                        'Finance/Revenues',
                        'Finance/Expenses',
                        'Accounting/Depreciation',
                        'Accounting/Reports'
                    ]
                ];
                break;
                
            default:
                $config = [];
        }
        
        // Save configuration
        $yaml = Yaml::dump($config, 4, 2);
        file_put_contents($file, $yaml);
        
        // Add to data
        $this->data[$name] = $config;
    }
    
    /**
     * Get a configuration value
     * 
     * @param string $key Configuration key (dot notation)
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $parts = explode('.', $key);
        $data = $this->data;
        
        foreach ($parts as $part) {
            if (!isset($data[$part])) {
                return $default;
            }
            $data = $data[$part];
        }
        
        return $data;
    }
    
    /**
     * Set a configuration value
     * 
     * @param string $key Configuration key (dot notation)
     * @param mixed $value Value to set
     * @return void
     */
    public function set($key, $value)
    {
        $parts = explode('.', $key);
        $data = &$this->data;
        
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $data[$part] = $value;
            } else {
                if (!isset($data[$part]) || !is_array($data[$part])) {
                    $data[$part] = [];
                }
                $data = &$data[$part];
            }
        }
        
        // Save configuration to file
        $rootKey = $parts[0];
        $file = APP_ROOT . "/config/{$rootKey}.yaml";
        $yaml = Yaml::dump($this->data[$rootKey], 4, 2);
        file_put_contents($file, $yaml);
    }
    
    /**
     * Get all module configurations
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
}
