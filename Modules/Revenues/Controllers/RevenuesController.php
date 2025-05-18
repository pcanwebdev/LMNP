<?php
namespace Modules\Finance\Revenues\Controllers;

use Core\Controller;
use Modules\Finance\Revenues\Models\RevenueModel;
use Modules\Finance\Properties\Models\PropertyModel;

/**
 * Revenues Controller
 * Handles revenue management operations
 */
class RevenuesController extends Controller
{
    /**
     * Revenue model
     */
    protected $revenueModel;
    
    /**
     * Property model
     */
    protected $propertyModel;
    
    /**
     * Initialize controller
     */
    protected function init()
    {
        $this->revenueModel = new RevenueModel();
        $this->propertyModel = new PropertyModel();
    }
    
    /**
     * List revenues
     * 
     * @return void
     */
    public function list()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get query parameters
        $propertyId = $this->getQuery('property_id');
        $year = $this->getQuery('year', date('Y'));
        $month = $this->getQuery('month');
        $category = $this->getQuery('category');
        
        // Get properties for filter dropdown
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get revenue categories
        $categories = getRevenueCategories();
        
        // Prepare filter
        $filter = [
            'property_id' => $propertyId,
            'year' => $year,
            'month' => $month,
            'category' => $category
        ];
        
        // Get revenues based on filter
        $revenues = $this->revenueModel->getFilteredRevenues($userId, $filter);
        
        // Calculate totals
        $total = 0;
        foreach ($revenues as $revenue) {
            $total += $revenue['amount'];
        }
        
        // Get monthly summary
        $monthlySummary = $this->revenueModel->getMonthlySummary($userId, $year);
        
        // Render revenue list
        $this->render('Modules/Finance/Revenues/Views/list.twig', [
            'revenues' => $revenues,
            'properties' => $properties,
            'categories' => $categories,
            'filter' => $filter,
            'total' => $total,
            'monthlySummary' => $monthlySummary
        ]);
    }
    
    /**
     * Add a new revenue
     * 
     * @return void
     */
    public function add()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get properties
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get revenue categories
        $categories = getRevenueCategories();
        
        // Pre-fill property ID if provided
        $propertyId = $this->getQuery('property_id');
        
        // Render add revenue form
        $this->render('Modules/Finance/Revenues/Views/edit.twig', [
            'action' => 'add',
            'revenue' => [
                'revenue_date' => date('Y-m-d'),
                'property_id' => $propertyId,
                'category' => 'rent',
                'recurring' => 0
            ],
            'properties' => $properties,
            'categories' => $categories
        ]);
    }
    
    /**
     * Edit an existing revenue
     * 
     * @param int $id Revenue ID
     * @return void
     */
    public function edit($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID du revenu non spécifié.');
            $this->redirect('/Revenues/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get revenue
        $revenue = $this->revenueModel->getRevenueById($id, $userId);
        
        if (!$revenue) {
            $this->setFlash('error', 'Revenu non trouvé ou accès non autorisé.');
            $this->redirect('/Revenues/list');
            return;
        }
        
        // Get properties
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get revenue categories
        $categories = getRevenueCategories();
        
        // Render edit revenue form
        $this->render('Modules/Finance/Revenues/Views/edit.twig', [
            'action' => 'edit',
            'revenue' => $revenue,
            'properties' => $properties,
            'categories' => $categories
        ]);
    }
    
    /**
     * Save revenue data
     * 
     * @return void
     */
    public function save()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get revenue data from form
        $id = $this->getPost('id');
        $propertyId = $this->getPost('property_id');
        $amount = floatval(str_replace(',', '.', $this->getPost('amount')));
        $revenueDate = $this->getPost('revenue_date');
        $description = $this->getPost('description');
        $category = $this->getPost('category');
        $recurring = $this->getPost('recurring') ? 1 : 0;
        $recurringFrequency = $recurring ? $this->getPost('recurring_frequency') : null;
        
        // Validate data
        if (empty($propertyId) || $amount <= 0 || empty($revenueDate)) {
            $this->setFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            $this->redirect($id ? "/Revenues/edit/{$id}" : '/Revenues/add');
            return;
        }
        
        // Check if property exists and belongs to user
        $property = $this->propertyModel->getPropertyById($propertyId, $userId);
        if (!$property) {
            $this->setFlash('error', 'Bien immobilier invalide.');
            $this->redirect($id ? "/Revenues/edit/{$id}" : '/Revenues/add');
            return;
        }
        
        // Prepare revenue data
        $revenueData = [
            'property_id' => $propertyId,
            'user_id' => $userId,
            'amount' => $amount,
            'revenue_date' => $revenueDate,
            'description' => $description,
            'category' => $category,
            'recurring' => $recurring,
            'recurring_frequency' => $recurringFrequency
        ];
        
        // Save revenue
        if ($id) {
            // Update existing revenue
            $result = $this->revenueModel->updateRevenue($id, $userId, $revenueData);
            $message = 'Revenu mis à jour avec succès.';
        } else {
            // Create new revenue
            $result = $this->revenueModel->createRevenue($revenueData);
            $message = 'Revenu ajouté avec succès.';
            
            // Generate recurring revenues if requested
            if ($recurring && $result) {
                $this->generateRecurringRevenues($result, $revenueData);
            }
        }
        
        if ($result) {
            $this->setFlash('success', $message);
            $this->redirect('/Revenues/list');
        } else {
            $this->setFlash('error', 'Erreur lors de la sauvegarde du revenu.');
            $this->redirect($id ? "/Revenues/edit/{$id}" : '/Revenues/add');
        }
    }
    
    /**
     * Delete a revenue
     * 
     * @param int $id Revenue ID
     * @return void
     */
    public function delete($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID du revenu non spécifié.');
            $this->redirect('/Revenues/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Check if revenue exists and belongs to user
        $revenue = $this->revenueModel->getRevenueById($id, $userId);
        
        if (!$revenue) {
            $this->setFlash('error', 'Revenu non trouvé ou accès non autorisé.');
            $this->redirect('/Revenues/list');
            return;
        }
        
        // Confirm deletion if not confirmed yet
        $confirmed = $this->getQuery('confirm');
        if (!$confirmed) {
            $this->render('Modules/Finance/Revenues/Views/delete_confirm.twig', [
                'revenue' => $revenue
            ]);
            return;
        }
        
        // Delete revenue
        $result = $this->revenueModel->deleteRevenue($id, $userId);
        
        if ($result) {
            $this->setFlash('success', 'Revenu supprimé avec succès.');
        } else {
            $this->setFlash('error', 'Erreur lors de la suppression du revenu.');
        }
        
        $this->redirect('/Revenues/list');
    }
    
    /**
     * Generate recurring revenues
     * 
     * @param int $originalId Original revenue ID
     * @param array $originalData Original revenue data
     * @return void
     */
    private function generateRecurringRevenues($originalId, $originalData)
    {
        $frequency = $originalData['recurring_frequency'];
        $startDate = new \DateTime($originalData['revenue_date']);
        
        // Define number of occurrences and interval based on frequency
        switch ($frequency) {
            case 'monthly':
                $occurrences = 11; // 1 year
                $interval = new \DateInterval('P1M');
                break;
                
            case 'quarterly':
                $occurrences = 3; // 1 year
                $interval = new \DateInterval('P3M');
                break;
                
            case 'semi_annual':
                $occurrences = 1; // 1 year
                $interval = new \DateInterval('P6M');
                break;
                
            case 'annual':
                $occurrences = 0; // No additional occurrences
                $interval = new \DateInterval('P1Y');
                break;
                
            default:
                return; // Invalid frequency
        }
        
        // Generate recurring revenues
        $date = clone $startDate;
        
        for ($i = 0; $i < $occurrences; $i++) {
            $date->add($interval);
            
            $recurringData = $originalData;
            $recurringData['revenue_date'] = $date->format('Y-m-d');
            $recurringData['description'] = 
                ($recurringData['description'] ? $recurringData['description'] . ' ' : '') . 
                '(Récurrent)';
            
            // Create the recurring revenue
            $this->revenueModel->createRevenue($recurringData);
        }
    }
}
