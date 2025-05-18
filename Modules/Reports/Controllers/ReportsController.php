<?php
namespace Modules\Accounting\Reports\Controllers;

use Core\Controller;
use Modules\Accounting\Reports\Models\ReportModel;
use Modules\Finance\Properties\Models\PropertyModel;
use Modules\Finance\Revenues\Models\RevenueModel;
use Modules\Finance\Expenses\Models\ExpenseModel;
use Modules\Accounting\Depreciation\Models\DepreciationModel;

/**
 * Reports Controller
 * Handles financial reports generation and management
 */
class ReportsController extends Controller
{
    /**
     * Report model
     */
    protected $reportModel;
    
    /**
     * Property model
     */
    protected $propertyModel;
    
    /**
     * Revenue model
     */
    protected $revenueModel;
    
    /**
     * Expense model
     */
    protected $expenseModel;
    
    /**
     * Depreciation model
     */
    protected $depreciationModel;
    
    /**
     * Initialize controller
     */
    protected function init()
    {
        $this->reportModel = new ReportModel();
        $this->propertyModel = new PropertyModel();
        $this->revenueModel = new RevenueModel();
        $this->expenseModel = new ExpenseModel();
        $this->depreciationModel = new DepreciationModel();
    }
    
    /**
     * List reports
     * 
     * @return void
     */
    public function list()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get query parameters
        $year = $this->getQuery('year', date('Y'));
        $reportType = $this->getQuery('report_type');
        
        // Prepare filter
        $filter = [
            'year' => $year,
            'report_type' => $reportType
        ];
        
        // Get reports based on filter
        $reports = $this->reportModel->getFilteredReports($userId, $filter);
        
        // Get available fiscal years
        $fiscalYears = $this->reportModel->getAvailableFiscalYears($userId);
        
        // Ensure current year is in the list
        if (!in_array($year, $fiscalYears)) {
            $fiscalYears[] = $year;
            sort($fiscalYears);
        }
        
        // Get report types
        $reportTypes = [
            'income_statement' => 'Compte de résultat',
            'tax_2031' => 'Déclaration 2031',
            'tax_2033A' => 'Annexe 2033-A',
            'balance_sheet' => 'Bilan',
            'custom' => 'Rapport personnalisé'
        ];
        
        // Render report list
        $this->render('Modules/Accounting/Reports/Views/list.twig', [
            'reports' => $reports,
            'fiscalYears' => $fiscalYears,
            'reportTypes' => $reportTypes,
            'filter' => $filter
        ]);
    }
    
    /**
     * Generate a new report
     * 
     * @return void
     */
    public function generate()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get query parameters
        $year = $this->getQuery('year', date('Y'));
        $reportType = $this->getQuery('report_type', 'income_statement');
        
        // Check if report exists
        $existingReport = $this->reportModel->getReportByTypeAndYear($userId, $reportType, $year);
        
        if ($existingReport) {
            $this->setFlash('info', 'Un rapport de ce type existe déjà pour cette année. Vous pouvez le visualiser ou le régénérer.');
            $this->redirect('/Reports/view/' . $existingReport['id']);
            return;
        }
        
        // Generate report data
        $reportData = [];
        
        switch ($reportType) {
            case 'income_statement':
                $reportData = $this->generateIncomeStatement($userId, $year);
                break;
                
            case 'tax_2031':
                $reportData = $this->generateTax2031($userId, $year);
                break;
                
            case 'tax_2033A':
                $reportData = $this->generateTax2033A($userId, $year);
                break;
                
            case 'balance_sheet':
                $reportData = $this->generateBalanceSheet($userId, $year);
                break;
                
            default:
                $this->setFlash('error', 'Type de rapport non pris en charge.');
                $this->redirect('/Reports/list');
                return;
        }
        
        // Create report
        $result = $this->reportModel->createReport([
            'user_id' => $userId,
            'report_type' => $reportType,
            'fiscal_year' => $year,
            'status' => 'completed',
            'data' => json_encode($reportData)
        ]);
        
        if ($result) {
            $this->setFlash('success', 'Rapport généré avec succès.');
            $this->redirect('/Reports/view/' . $result);
        } else {
            $this->setFlash('error', 'Erreur lors de la génération du rapport.');
            $this->redirect('/Reports/list');
        }
    }
    
    /**
     * View a report
     * 
     * @param int $id Report ID
     * @return void
     */
    public function view($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID du rapport non spécifié.');
            $this->redirect('/Reports/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get report
        $report = $this->reportModel->getReportById($id, $userId);
        
        if (!$report) {
            $this->setFlash('error', 'Rapport non trouvé ou accès non autorisé.');
            $this->redirect('/Reports/list');
            return;
        }
        
        // Parse report data
        $reportData = json_decode($report['data'], true);
        
        // Get report type label
        $reportTypeLabels = [
            'income_statement' => 'Compte de résultat',
            'tax_2031' => 'Déclaration 2031',
            'tax_2033A' => 'Annexe 2033-A',
            'balance_sheet' => 'Bilan',
            'custom' => 'Rapport personnalisé'
        ];
        
        $reportTypeLabel = $reportTypeLabels[$report['report_type']] ?? $report['report_type'];
        
        // Render appropriate view based on report type
        $view = 'Modules/Accounting/Reports/Views/' . $report['report_type'] . '.twig';
        
        $this->render($view, [
            'report' => $report,
            'reportData' => $reportData,
            'reportTypeLabel' => $reportTypeLabel
        ]);
    }
    
    /**
     * Delete a report
     * 
     * @param int $id Report ID
     * @return void
     */
    public function delete($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID du rapport non spécifié.');
            $this->redirect('/Reports/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Check if report exists and belongs to user
        $report = $this->reportModel->getReportById($id, $userId);
        
        if (!$report) {
            $this->setFlash('error', 'Rapport non trouvé ou accès non autorisé.');
            $this->redirect('/Reports/list');
            return;
        }
        
        // Confirm deletion if not confirmed yet
        $confirmed = $this->getQuery('confirm');
        if (!$confirmed) {
            $this->render('Modules/Accounting/Reports/Views/delete_confirm.twig', [
                'report' => $report
            ]);
            return;
        }
        
        // Delete report
        $result = $this->reportModel->deleteReport($id, $userId);
        
        if ($result) {
            $this->setFlash('success', 'Rapport supprimé avec succès.');
        } else {
            $this->setFlash('error', 'Erreur lors de la suppression du rapport.');
        }
        
        $this->redirect('/Reports/list');
    }
    
    /**
     * Export a report as PDF
     * 
     * @param int $id Report ID
     * @return void
     */
    public function export($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID du rapport non spécifié.');
            $this->redirect('/Reports/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get report
        $report = $this->reportModel->getReportById($id, $userId);
        
        if (!$report) {
            $this->setFlash('error', 'Rapport non trouvé ou accès non autorisé.');
            $this->redirect('/Reports/list');
            return;
        }
        
        // Get export format (PDF, CSV, Excel)
        $format = $this->getQuery('format', 'pdf');
        
        // For now, just render a view to simulate export
        // In a real implementation, use a PDF library or CSV generation
        
        $this->setFlash('info', 'Export en ' . strtoupper($format) . ' simulé. Cette fonctionnalité sera implémentée ultérieurement.');
        $this->redirect('/Reports/view/' . $id);
    }
    
    /**
     * Generate income statement data
     * 
     * @param int $userId User ID
     * @param int $year Fiscal year
     * @return array Report data
     */
    private function generateIncomeStatement($userId, $year)
    {
        // Get all properties for the user
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get revenues for the year
        $revenues = $this->revenueModel->getFilteredRevenues($userId, ['year' => $year]);
        
        // Get expenses for the year
        $expenses = $this->expenseModel->getFilteredExpenues($userId, ['year' => $year]);
        
        // Get depreciation for the year
        $depreciation = $this->depreciationModel->getTotalDepreciation($userId, $year);
        
        // Process revenues by category
        $revenuesByCategory = [];
        $totalRevenues = 0;
        
        foreach ($revenues as $revenue) {
            $category = $revenue['category'];
            if (!isset($revenuesByCategory[$category])) {
                $revenuesByCategory[$category] = 0;
            }
            $revenuesByCategory[$category] += $revenue['amount'];
            $totalRevenues += $revenue['amount'];
        }
        
        // Process expenses by category
        $expensesByCategory = [];
        $totalExpenses = 0;
        
        foreach ($expenses as $expense) {
            if (!$expense['is_deductible']) {
                continue; // Skip non-deductible expenses
            }
            
            $category = $expense['category'];
            if (!isset($expensesByCategory[$category])) {
                $expensesByCategory[$category] = 0;
            }
            $expensesByCategory[$category] += $expense['amount'];
            $totalExpenses += $expense['amount'];
        }
        
        // Calculate net income
        $netIncome = $totalRevenues - $totalExpenses - $depreciation['total_amount'];
        
        // Build report data
        return [
            'fiscal_year' => $year,
            'generation_date' => date('Y-m-d H:i:s'),
            'properties' => $properties,
            'revenues' => [
                'details' => $revenuesByCategory,
                'total' => $totalRevenues
            ],
            'expenses' => [
                'details' => $expensesByCategory,
                'total' => $totalExpenses
            ],
            'depreciation' => $depreciation['total_amount'],
            'net_income' => $netIncome,
            'tax_rate' => 0, // To be calculated or provided by user
            'estimated_tax' => 0 // To be calculated based on tax rate
        ];
    }
    
    /**
     * Generate 2031 tax form data
     * 
     * @param int $userId User ID
     * @param int $year Fiscal year
     * @return array Report data
     */
    private function generateTax2031($userId, $year)
    {
        // This is a simplified implementation
        // In a real-world scenario, this would be much more complex
        
        // Get income statement data
        $incomeStatement = $this->generateIncomeStatement($userId, $year);
        
        // Additional tax-specific calculations would be done here
        
        return [
            'fiscal_year' => $year,
            'generation_date' => date('Y-m-d H:i:s'),
            'income_statement' => $incomeStatement,
            'form_data' => [
                'identification' => [
                    'name' => $this->user['username'],
                    'address' => 'Adresse à compléter',
                    'tax_id' => 'À compléter',
                    'start_date' => $year . '-01-01',
                    'end_date' => $year . '-12-31'
                ],
                'revenues' => $incomeStatement['revenues']['total'],
                'expenses' => $incomeStatement['expenses']['total'],
                'depreciation' => $incomeStatement['depreciation'],
                'net_income' => $incomeStatement['net_income']
            ]
        ];
    }
    
    /**
     * Generate 2033A tax form data
     * 
     * @param int $userId User ID
     * @param int $year Fiscal year
     * @return array Report data
     */
    private function generateTax2033A($userId, $year)
    {
        // This is a simplified implementation
        // In a real-world scenario, this would be much more complex
        
        // Get income statement data
        $incomeStatement = $this->generateIncomeStatement($userId, $year);
        
        // Additional tax-specific calculations would be done here
        
        return [
            'fiscal_year' => $year,
            'generation_date' => date('Y-m-d H:i:s'),
            'income_statement' => $incomeStatement,
            'form_data' => [
                'identification' => [
                    'name' => $this->user['username'],
                    'address' => 'Adresse à compléter',
                    'tax_id' => 'À compléter',
                    'start_date' => $year . '-01-01',
                    'end_date' => $year . '-12-31'
                ],
                'capital_assets' => [
                    'gross_value' => 0,
                    'depreciation' => $incomeStatement['depreciation'],
                    'net_value' => 0
                ],
                'current_assets' => [
                    'receivables' => 0,
                    'cash' => 0
                ],
                'liabilities' => [
                    'equity' => 0,
                    'loans' => 0,
                    'payables' => 0
                ]
            ]
        ];
    }
    
    /**
     * Generate balance sheet data
     * 
     * @param int $userId User ID
     * @param int $year Fiscal year
     * @return array Report data
     */
    private function generateBalanceSheet($userId, $year)
    {
        // Get all properties for the user
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get depreciation assets
        $filter = [
            'year' => $year,
            'status' => 'active'
        ];
        $assets = $this->depreciationModel->getFilteredAssets($userId, $filter);
        
        // Process assets
        $assetsByType = [
            'property' => [
                'base_value' => 0,
                'accumulated_depreciation' => 0,
                'net_value' => 0
            ],
            'furniture' => [
                'base_value' => 0,
                'accumulated_depreciation' => 0,
                'net_value' => 0
            ],
            'improvement' => [
                'base_value' => 0,
                'accumulated_depreciation' => 0,
                'net_value' => 0
            ]
        ];
        
        foreach ($assets as $asset) {
            $type = $asset['category_type'];
            $assetsByType[$type]['base_value'] += $asset['base_value'];
            
            // Calculate accumulated depreciation
            $entries = $this->depreciationModel->getDepreciationEntries($asset['id'], $year);
            if (!empty($entries)) {
                $accumulated = $entries[0]['accumulated'];
            } else {
                // Approximate based on years elapsed
                $acquisitionDate = new \DateTime($asset['acquisition_date']);
                $currentDate = new \DateTime("{$year}-12-31");
                $yearsSinceAcquisition = $currentDate->format('Y') - $acquisitionDate->format('Y');
                if ($currentDate->format('m-d') < $acquisitionDate->format('m-d')) {
                    $yearsSinceAcquisition--;
                }
                $yearsSinceAcquisition = max(0, $yearsSinceAcquisition);
                
                $annualAmount = $asset['base_value'] * ($asset['rate'] / 100);
                $accumulated = min($yearsSinceAcquisition * $annualAmount, $asset['base_value']);
            }
            
            $assetsByType[$type]['accumulated_depreciation'] += $accumulated;
            $assetsByType[$type]['net_value'] += $asset['base_value'] - $accumulated;
        }
        
        // Total assets
        $totalAssets = [
            'base_value' => 0,
            'accumulated_depreciation' => 0,
            'net_value' => 0
        ];
        
        foreach ($assetsByType as $type => $values) {
            $totalAssets['base_value'] += $values['base_value'];
            $totalAssets['accumulated_depreciation'] += $values['accumulated_depreciation'];
            $totalAssets['net_value'] += $values['net_value'];
        }
        
        // Build report data
        return [
            'fiscal_year' => $year,
            'generation_date' => date('Y-m-d H:i:s'),
            'properties' => $properties,
            'assets' => [
                'by_type' => $assetsByType,
                'total' => $totalAssets
            ],
            'liabilities' => [
                'equity' => $totalAssets['net_value'], // Simplified assumption
                'loans' => 0, // Would need loan data to fill this
                'total' => $totalAssets['net_value'] // Should equal total assets
            ]
        ];
    }
}
