<?php
namespace Modules\Accounting\Depreciation\Controllers;

use Core\Controller;
use Modules\Accounting\Depreciation\Models\DepreciationModel;
use Modules\Finance\Properties\Models\PropertyModel;

/**
 * Depreciation Controller
 * Handles depreciation management operations
 */
class DepreciationController extends Controller
{
    /**
     * Depreciation model
     */
    protected $depreciationModel;
    
    /**
     * Property model
     */
    protected $propertyModel;
    
    /**
     * Initialize controller
     */
    protected function init()
    {
        $this->depreciationModel = new DepreciationModel();
        $this->propertyModel = new PropertyModel();
    }
    
    /**
     * List depreciation assets
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
        $categoryType = $this->getQuery('category_type');
        $status = $this->getQuery('status', 'active');
        
        // Get properties for filter dropdown
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get depreciation categories
        $categories = $this->depreciationModel->getDepreciationCategories();
        
        // Get category types
        $categoryTypes = [
            'property' => 'Bien immobilier',
            'furniture' => 'Mobilier',
            'improvement' => 'Travaux'
        ];
        
        // Prepare filter
        $filter = [
            'property_id' => $propertyId,
            'year' => $year,
            'category_type' => $categoryType,
            'status' => $status
        ];
        
        // Get assets based on filter
        $assets = $this->depreciationModel->getFilteredAssets($userId, $filter);
        
        // Calculate depreciation entries for current year for each asset
        foreach ($assets as &$asset) {
            $asset['entries'] = $this->depreciationModel->getDepreciationEntries($asset['id'], $year);
            
            // Calculate years elapsed since acquisition
            $acquisitionDate = new \DateTime($asset['acquisition_date']);
            $currentDate = new \DateTime();
            $yearsSinceAcquisition = $currentDate->format('Y') - $acquisitionDate->format('Y');
            if ($currentDate->format('m-d') < $acquisitionDate->format('m-d')) {
                $yearsSinceAcquisition--;
            }
            $asset['years_elapsed'] = $yearsSinceAcquisition;
            
            // Calculate progress percentage
            $asset['progress_percentage'] = min(100, round(($yearsSinceAcquisition / $asset['duration']) * 100));
        }
        
        // Calculate totals
        $totalBaseValue = 0;
        $totalAnnualDepreciation = 0;
        $totalAccumulatedDepreciation = 0;
        $totalRemaining = 0;
        
        foreach ($assets as $asset) {
            $totalBaseValue += $asset['base_value'];
            
            if (isset($asset['entries'][0])) {
                $entry = $asset['entries'][0];
                $totalAnnualDepreciation += $entry['amount'];
                $totalAccumulatedDepreciation += $entry['accumulated'];
                $totalRemaining += $entry['remaining'];
            } else {
                // If no entry for current year, calculate based on rate
                $annualAmount = $asset['base_value'] * ($asset['rate'] / 100);
                $totalAnnualDepreciation += $annualAmount;
                
                // Approximate accumulated based on years elapsed
                $accumulated = min($asset['years_elapsed'] * $annualAmount, $asset['base_value']);
                $totalAccumulatedDepreciation += $accumulated;
                $totalRemaining += $asset['base_value'] - $accumulated;
            }
        }
        
        // Render asset list
        $this->render('Modules/Accounting/Depreciation/Views/list.twig', [
            'assets' => $assets,
            'properties' => $properties,
            'categories' => $categories,
            'categoryTypes' => $categoryTypes,
            'filter' => $filter,
            'totalBaseValue' => $totalBaseValue,
            'totalAnnualDepreciation' => $totalAnnualDepreciation,
            'totalAccumulatedDepreciation' => $totalAccumulatedDepreciation,
            'totalRemaining' => $totalRemaining
        ]);
    }
    
    /**
     * Add a new depreciation asset
     * 
     * @return void
     */
    public function add()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get properties
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get depreciation categories
        $categories = $this->depreciationModel->getDepreciationCategories();
        
        // Pre-fill property ID if provided
        $propertyId = $this->getQuery('property_id');
        
        // Render add asset form
        $this->render('Modules/Accounting/Depreciation/Views/edit.twig', [
            'action' => 'add',
            'asset' => [
                'acquisition_date' => date('Y-m-d'),
                'property_id' => $propertyId,
                'status' => 'active',
                'duration' => 0,
                'rate' => 0
            ],
            'properties' => $properties,
            'categories' => $categories
        ]);
    }
    
    /**
     * Edit an existing depreciation asset
     * 
     * @param int $id Asset ID
     * @return void
     */
    public function edit($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID de l\'actif non spécifié.');
            $this->redirect('/Depreciation/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get asset
        $asset = $this->depreciationModel->getAssetById($id, $userId);
        
        if (!$asset) {
            $this->setFlash('error', 'Actif non trouvé ou accès non autorisé.');
            $this->redirect('/Depreciation/list');
            return;
        }
        
        // Get properties
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get depreciation categories
        $categories = $this->depreciationModel->getDepreciationCategories();
        
        // Render edit asset form
        $this->render('Modules/Accounting/Depreciation/Views/edit.twig', [
            'action' => 'edit',
            'asset' => $asset,
            'properties' => $properties,
            'categories' => $categories
        ]);
    }
    
    /**
     * Save depreciation asset data
     * 
     * @return void
     */
    public function save()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get asset data from form
        $id = $this->getPost('id');
        $propertyId = $this->getPost('property_id');
        $categoryId = $this->getPost('category_id');
        $name = $this->getPost('name');
        $acquisitionDate = $this->getPost('acquisition_date');
        $baseValue = floatval(str_replace(',', '.', $this->getPost('base_value')));
        $duration = intval($this->getPost('duration'));
        $rate = floatval(str_replace(',', '.', $this->getPost('rate')));
        $status = $this->getPost('status');
        $notes = $this->getPost('notes');
        
        // Validate data
        if (empty($propertyId) || empty($categoryId) || empty($name) || 
            empty($acquisitionDate) || $baseValue <= 0 || $duration <= 0 || $rate <= 0) {
            $this->setFlash('error', 'Veuillez remplir tous les champs obligatoires avec des valeurs valides.');
            $this->redirect($id ? "/Depreciation/edit/{$id}" : '/Depreciation/add');
            return;
        }
        
        // Check if property exists and belongs to user
        $property = $this->propertyModel->getPropertyById($propertyId, $userId);
        if (!$property) {
            $this->setFlash('error', 'Bien immobilier invalide.');
            $this->redirect($id ? "/Depreciation/edit/{$id}" : '/Depreciation/add');
            return;
        }
        
        // Prepare asset data
        $assetData = [
            'property_id' => $propertyId,
            'user_id' => $userId,
            'category_id' => $categoryId,
            'name' => $name,
            'acquisition_date' => $acquisitionDate,
            'base_value' => $baseValue,
            'duration' => $duration,
            'rate' => $rate,
            'status' => $status,
            'notes' => $notes
        ];
        
        // Save asset
        if ($id) {
            // Update existing asset
            $result = $this->depreciationModel->updateAsset($id, $userId, $assetData);
            $message = 'Actif d\'amortissement mis à jour avec succès.';
        } else {
            // Create new asset
            $result = $this->depreciationModel->createAsset($assetData);
            $message = 'Actif d\'amortissement ajouté avec succès.';
            $id = $result;
        }
        
        if ($result) {
            // Generate depreciation entries if requested
            if ($this->getPost('generate_entries') === 'yes' && $id) {
                $this->depreciationModel->generateDepreciationEntries($id);
            }
            
            $this->setFlash('success', $message);
            $this->redirect('/Depreciation/list');
        } else {
            $this->setFlash('error', 'Erreur lors de la sauvegarde de l\'actif d\'amortissement.');
            $this->redirect($id ? "/Depreciation/edit/{$id}" : '/Depreciation/add');
        }
    }
    
    /**
     * Delete a depreciation asset
     * 
     * @param int $id Asset ID
     * @return void
     */
    public function delete($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID de l\'actif non spécifié.');
            $this->redirect('/Depreciation/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Check if asset exists and belongs to user
        $asset = $this->depreciationModel->getAssetById($id, $userId);
        
        if (!$asset) {
            $this->setFlash('error', 'Actif non trouvé ou accès non autorisé.');
            $this->redirect('/Depreciation/list');
            return;
        }
        
        // Confirm deletion if not confirmed yet
        $confirmed = $this->getQuery('confirm');
        if (!$confirmed) {
            $this->render('Modules/Accounting/Depreciation/Views/delete_confirm.twig', [
                'asset' => $asset
            ]);
            return;
        }
        
        // Delete asset
        $result = $this->depreciationModel->deleteAsset($id, $userId);
        
        if ($result) {
            $this->setFlash('success', 'Actif d\'amortissement supprimé avec succès.');
        } else {
            $this->setFlash('error', 'Erreur lors de la suppression de l\'actif d\'amortissement.');
        }
        
        $this->redirect('/Depreciation/list');
    }
    
    /**
     * View depreciation table for an asset
     * 
     * @param int $id Asset ID
     * @return void
     */
    public function table($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID de l\'actif non spécifié.');
            $this->redirect('/Depreciation/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get asset
        $asset = $this->depreciationModel->getAssetById($id, $userId);
        
        if (!$asset) {
            $this->setFlash('error', 'Actif non trouvé ou accès non autorisé.');
            $this->redirect('/Depreciation/list');
            return;
        }
        
        // Get all depreciation entries for the asset
        $entries = $this->depreciationModel->getAllDepreciationEntries($id);
        
        // If no entries exist, generate them
        if (empty($entries)) {
            $this->depreciationModel->generateDepreciationEntries($id);
            $entries = $this->depreciationModel->getAllDepreciationEntries($id);
        }
        
        // Render depreciation table
        $this->render('Modules/Accounting/Depreciation/Views/table.twig', [
            'asset' => $asset,
            'entries' => $entries
        ]);
    }
    
    /**
     * Generate depreciation entries for an asset
     * 
     * @param int $id Asset ID
     * @return void
     */
    public function generate($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID de l\'actif non spécifié.');
            $this->redirect('/Depreciation/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get asset
        $asset = $this->depreciationModel->getAssetById($id, $userId);
        
        if (!$asset) {
            $this->setFlash('error', 'Actif non trouvé ou accès non autorisé.');
            $this->redirect('/Depreciation/list');
            return;
        }
        
        // Generate depreciation entries
        $result = $this->depreciationModel->generateDepreciationEntries($id);
        
        if ($result) {
            $this->setFlash('success', 'Entrées d\'amortissement générées avec succès.');
        } else {
            $this->setFlash('error', 'Erreur lors de la génération des entrées d\'amortissement.');
        }
        
        $this->redirect('/Depreciation/table/' . $id);
    }
    
    /**
     * Get depreciation category details via AJAX
     * 
     * @return void
     */
    public function getCategoryDetails()
    {
        // Get category ID
        $categoryId = $this->getPost('category_id');
        
        if (!$categoryId) {
            echo json_encode(['error' => 'ID de catégorie non spécifié.']);
            return;
        }
        
        // Get category details
        $category = $this->depreciationModel->getCategoryById($categoryId);
        
        if (!$category) {
            echo json_encode(['error' => 'Catégorie non trouvée.']);
            return;
        }
        
        // Return category details as JSON
        header('Content-Type: application/json');
        echo json_encode($category);
    }
}
