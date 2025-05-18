<?php
namespace Modules\Core\Settings\Controllers;

use Core\Controller;
use Modules\Core\Settings\Models\SettingsModel;

/**
 * Settings Controller
 * Handles application settings management
 */
class SettingsController extends Controller
{
    /**
     * Settings model
     */
    protected $settingsModel;
    
    /**
     * Initialize controller
     */
    protected function init()
    {
        $this->settingsModel = new SettingsModel();
    }
    
    /**
     * Settings index action
     * 
     * @return void
     */
    public function index()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get user settings
        $settings = $this->settingsModel->getUserSettings($userId);
        
        // Default settings if not set
        $defaults = [
            'theme' => 'light',
            'dashboard_widgets' => ['summary', 'properties', 'revenues', 'expenses', 'chart_monthly', 'chart_breakdown'],
            'currency' => 'EUR',
            'date_format' => 'd/m/Y',
            'fiscal_year_start' => '01-01',
            'items_per_page' => 10
        ];
        
        // Merge with defaults
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
        
        // Render settings page
        $this->render('Modules/Core/Settings/Views/settings.twig', [
            'settings' => $settings
        ]);
    }
    
    /**
     * Save settings
     * 
     * @return void
     */
    public function save()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get form data
        $theme = $this->getPost('theme');
        $dashboardWidgets = $this->getPost('dashboard_widgets');
        $currency = $this->getPost('currency');
        $dateFormat = $this->getPost('date_format');
        $fiscalYearStart = $this->getPost('fiscal_year_start');
        $itemsPerPage = (int) $this->getPost('items_per_page');
        
        // Validate inputs
        if (!in_array($theme, ['light', 'dark'])) {
            $theme = 'light';
        }
        
        if (!is_array($dashboardWidgets)) {
            $dashboardWidgets = ['summary', 'properties', 'revenues', 'expenses'];
        }
        
        if (!in_array($currency, ['EUR', 'USD', 'GBP'])) {
            $currency = 'EUR';
        }
        
        if (!in_array($dateFormat, ['d/m/Y', 'Y-m-d', 'm/d/Y'])) {
            $dateFormat = 'd/m/Y';
        }
        
        if (!preg_match('/^\d{2}-\d{2}$/', $fiscalYearStart)) {
            $fiscalYearStart = '01-01';
        }
        
        if ($itemsPerPage < 5 || $itemsPerPage > 100) {
            $itemsPerPage = 10;
        }
        
        // Save settings
        $this->settingsModel->setSetting($userId, 'theme', $theme);
        $this->settingsModel->setSetting($userId, 'dashboard_widgets', $dashboardWidgets);
        $this->settingsModel->setSetting($userId, 'currency', $currency);
        $this->settingsModel->setSetting($userId, 'date_format', $dateFormat);
        $this->settingsModel->setSetting($userId, 'fiscal_year_start', $fiscalYearStart);
        $this->settingsModel->setSetting($userId, 'items_per_page', $itemsPerPage);
        
        // Set flash message
        $this->setFlash('success', 'Paramètres enregistrés avec succès.');
        
        // Redirect back to settings
        $this->redirect('/Settings');
    }
    
    /**
     * Get a user setting
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function getSetting($key, $default = null)
    {
        // Get user ID
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            return $default;
        }
        
        // Get setting value
        $value = $this->settingsModel->getSetting($userId, $key);
        
        return $value !== null ? $value : $default;
    }
}
