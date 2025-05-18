<?php
/**
 * Helper functions for the application
 */

/**
 * Format a number as currency
 * 
 * @param float $amount Amount to format
 * @param string $symbol Currency symbol
 * @return string Formatted currency
 */
function formatCurrency($amount, $symbol = '€')
{
    return number_format($amount, 2, ',', ' ') . ' ' . $symbol;
}

/**
 * Format a date
 * 
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'd/m/Y')
{
    if (!$date) {
        return '';
    }
    
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Generate a URL
 * 
 * @param string $path URL path
 * @return string Full URL
 */
function url($path)
{
    return '/' . ltrim($path, '/');
}

/**
 * Get a configuration value
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value
 * @return mixed Configuration value
 */
function config($key, $default = null)
{
    global $config;
    return $config->get($key, $default);
}

/**
 * Check if a user has a specific permission
 * 
 * @param string $permission Permission to check
 * @return bool Has permission
 */
function hasPermission($permission)
{
    // Simple permission check - can be expanded
    global $auth;
    return $auth->hasPermission($permission);
}

/**
 * Generate a unique file name
 * 
 * @param string $originalName Original file name
 * @return string Unique file name
 */
function generateUniqueFilename($originalName)
{
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Upload a file
 * 
 * @param array $file File from $_FILES
 * @param string $directory Upload directory
 * @return string|false File path or false on failure
 */
function uploadFile($file, $directory = 'uploads')
{
    // Check if file exists
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Create directory if it doesn't exist
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Generate unique filename
    $filename = generateUniqueFilename($file['name']);
    $path = $directory . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $path;
    }
    
    return false;
}

/**
 * Remove special characters from a string
 * 
 * @param string $string String to sanitize
 * @return string Sanitized string
 */
function sanitizeString($string)
{
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $string);
}

/**
 * Get the fiscal year for a given date
 * 
 * @param string $date Date string
 * @return int Fiscal year
 */
function getFiscalYear($date = null)
{
    $date = $date ? new DateTime($date) : new DateTime();
    
    // In France, fiscal year is the calendar year
    return (int) $date->format('Y');
}

/**
 * Calculate the linear depreciation
 * 
 * @param float $value Initial value
 * @param float $rate Depreciation rate
 * @param int $year Current year (1-based)
 * @param int $duration Total duration in years
 * @return array Depreciation information
 */
function calculateDepreciation($value, $rate, $year, $duration)
{
    $annualAmount = $value * ($rate / 100);
    $accumulated = $annualAmount * min($year, $duration);
    $remaining = $value - $accumulated;
    
    // Last year might have a different amount to account for rounding
    if ($year == $duration) {
        $annualAmount = $remaining;
        $accumulated = $value;
        $remaining = 0;
    }
    
    return [
        'annual' => $annualAmount,
        'accumulated' => $accumulated,
        'remaining' => $remaining
    ];
}

/**
 * Get expense categories
 * 
 * @return array Expense categories
 */
function getExpenseCategories()
{
    return [
        'property_tax' => 'Taxe foncière',
        'insurance' => 'Assurance',
        'management_fees' => 'Frais de gestion',
        'maintenance' => 'Entretien et réparation',
        'accountant_fees' => 'Honoraires comptable',
        'bank_fees' => 'Frais bancaires',
        'loan_interest' => 'Intérêts d\'emprunt',
        'electricity' => 'Électricité',
        'water' => 'Eau',
        'gas' => 'Gaz',
        'internet' => 'Internet',
        'condo_fees' => 'Charges de copropriété',
        'travel' => 'Frais de déplacement',
        'other' => 'Autres frais'
    ];
}

/**
 * Get revenue categories
 * 
 * @return array Revenue categories
 */
function getRevenueCategories()
{
    return [
        'rent' => 'Loyer',
        'deposit' => 'Dépôt de garantie',
        'service_charges' => 'Charges locatives',
        'other' => 'Autres revenus'
    ];
}

/**
 * Get property types
 * 
 * @return array Property types
 */
function getPropertyTypes()
{
    return [
        'apartment' => 'Appartement',
        'house' => 'Maison',
        'commercial' => 'Local commercial',
        'land' => 'Terrain',
        'other' => 'Autre'
    ];
}
