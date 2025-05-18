<?php
/**
 * Bootstrap file for the LMNP Accounting Application
 * Initializes core components and autoloads classes
 */

// Enable error reporting in development environment
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autoloader function
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $path = str_replace('\\', '/', $class) . '.php';
    
    // Check if file exists
    if (file_exists($path)) {
        require_once $path;
        return true;
    }
    
    return false;
});

// Load Composer dependencies (Twig, etc.)
require_once 'vendor/autoload.php';

// Initialize core components
require_once 'Core/Config.php';
$config = new \Core\Config();

// Initialize database connection
require_once 'Core/Database.php';
$db = new \Core\Database($config->get('database'));

// Initialize Twig template engine
$loader = new \Twig\Loader\FilesystemLoader([
    __DIR__,  // Root directory
    __DIR__ . '/templates', // Templates directory
]);
$twig = new \Twig\Environment($loader, [
    'cache' => false, // Set to a path for production
    'debug' => true,
]);
$twig->addExtension(new \Twig\Extension\DebugExtension());

// Set global variables for Twig
$twig->addGlobal('config', $config);
$twig->addGlobal('session', $_SESSION ?? []);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load helpers
require_once 'Core/Helpers.php';

// Initialize module system
require_once 'Core/Module.php';
$moduleManager = new \Core\Module($config);
$moduleManager->loadModules();
