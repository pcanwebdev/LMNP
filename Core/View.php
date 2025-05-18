<?php
namespace Core;

/**
 * View class
 * Handles view rendering with Twig
 */
class View
{
    /**
     * Twig environment
     */
    protected $twig;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        global $twig;
        $this->twig = $twig;
    }
    
    /**
     * Render a template
     * 
     * @param string $template Template name
     * @param array $data Data to pass to the template
     * @return string The rendered template
     */
    public function render($template, $data = [])
    {
        // Add flash messages if available
        if (isset($_SESSION['flash'])) {
            $data['flash'] = $_SESSION['flash'];
            unset($_SESSION['flash']);
        }
        
        // Render the template
        return $this->twig->render($template, $data);
    }
    
    /**
     * Add a global variable to all templates
     * 
     * @param string $name Variable name
     * @param mixed $value Variable value
     * @return void
     */
    public function addGlobal($name, $value)
    {
        $this->twig->addGlobal($name, $value);
    }
    
    /**
     * Add a Twig extension
     * 
     * @param \Twig\Extension\ExtensionInterface $extension The extension
     * @return void
     */
    public function addExtension($extension)
    {
        $this->twig->addExtension($extension);
    }
    
    /**
     * Register custom template functions
     * 
     * @return void
     */
    public function registerFunctions()
    {
        // Function to generate URLs
        $this->twig->addFunction(new \Twig\TwigFunction('url', function($path) {
            return '/' . ltrim($path, '/');
        }));
        
        // Function to check permissions
        $this->twig->addFunction(new \Twig\TwigFunction('can', function($permission) {
            // Simple permission check - can be expanded
            return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'user']);
        }));
        
        // Function to format currency
        $this->twig->addFunction(new \Twig\TwigFunction('currency', function($amount, $symbol = '€') {
            return number_format($amount, 2, ',', ' ') . ' ' . $symbol;
        }));
        
        // Function to format date
        $this->twig->addFunction(new \Twig\TwigFunction('formatDate', function($date, $format = 'd/m/Y') {
            if (!$date) return '';
            $datetime = new \DateTime($date);
            return $datetime->format($format);
        }));
    }
}
