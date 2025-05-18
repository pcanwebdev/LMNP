<?php
namespace Modules\Properties\Controllers;

use Core\Controller;
use Modules\Properties\Models\PropertyModel;

/**
 * Properties Controller
 * Handles property management operations
 */
class PropertiesController extends Controller
{
    /**
     * Property model
     */
    protected $propertyModel;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->propertyModel = new \Modules\Finance\Properties\Models\PropertyModel();
    }
    
    /**
     * List properties
     * 
     * @return void
     */
    public function list()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get properties for current user
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Render property list
        $this->render('Modules/Finance/Properties/Views/list.twig', [
            'properties' => $properties
        ]);
    }
    
    /**
     * Add a new property
     * 
     * @return void
     */
    public function add()
    {
        // Load property types
        $propertyTypes = getPropertyTypes();
        
        // Render add property form
        $this->render('Modules/Finance/Properties/Views/edit.twig', [
            'action' => 'add',
            'property' => [
                'acquisition_date' => date('Y-m-d'),
                'ownership_percentage' => 100.00,
                'status' => 'active'
            ],
            'propertyTypes' => $propertyTypes
        ]);
    }
    
    /**
     * Edit an existing property
     * 
     * @param int $id Property ID
     * @return void
     */
    public function edit($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID du bien immobilier non spécifié.');
            $this->redirect('/Properties/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get property
        $property = $this->propertyModel->getPropertyById($id, $userId);
        
        if (!$property) {
            $this->setFlash('error', 'Bien immobilier non trouvé ou accès non autorisé.');
            $this->redirect('/Properties/list');
            return;
        }
        
        // Load property types
        $propertyTypes = getPropertyTypes();
        
        // Render edit property form
        $this->render('Modules/Finance/Properties/Views/edit.twig', [
            'action' => 'edit',
            'property' => $property,
            'propertyTypes' => $propertyTypes
        ]);
    }
    
    /**
     * Save property data
     * 
     * @return void
     */
    public function save()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get property data from form
        $id = $this->getPost('id');
        $name = $this->getPost('name');
        $address = $this->getPost('address');
        $acquisitionDate = $this->getPost('acquisition_date');
        $acquisitionPrice = floatval(str_replace(',', '.', $this->getPost('acquisition_price')));
        $ownershipPercentage = floatval(str_replace(',', '.', $this->getPost('ownership_percentage')));
        $propertyType = $this->getPost('property_type');
        $status = $this->getPost('status');
        $notes = $this->getPost('notes');
        
        // Validate data
        if (empty($name) || empty($address) || empty($acquisitionDate) || $acquisitionPrice <= 0) {
            $this->setFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            $this->redirect($id ? "/Properties/edit/{$id}" : '/Properties/add');
            return;
        }
        
        // Prepare property data
        $propertyData = [
            'user_id' => $userId,
            'name' => $name,
            'address' => $address,
            'acquisition_date' => $acquisitionDate,
            'acquisition_price' => $acquisitionPrice,
            'ownership_percentage' => $ownershipPercentage,
            'property_type' => $propertyType,
            'status' => $status,
            'notes' => $notes
        ];
        
        // Save property
        if ($id) {
            // Update existing property
            $result = $this->propertyModel->updateProperty($id, $userId, $propertyData);
            $message = 'Bien immobilier mis à jour avec succès.';
        } else {
            // Create new property
            $result = $this->propertyModel->createProperty($propertyData);
            $message = 'Bien immobilier ajouté avec succès.';
            $id = $result;
        }
        
        if ($result) {
            $this->setFlash('success', $message);
            
            // Check if we should create default depreciation assets
            if ($this->getPost('create_depreciation') === 'yes' && $id) {
                $this->createDefaultDepreciationAssets($id, $userId, $acquisitionPrice, $acquisitionDate);
            }
            
            $this->redirect('/Properties/list');
        } else {
            $this->setFlash('error', 'Erreur lors de la sauvegarde du bien immobilier.');
            $this->redirect($id ? "/Properties/edit/{$id}" : '/Properties/add');
        }
    }
    
    /**
     * Delete a property
     * 
     * @param int $id Property ID
     * @return void
     */
    public function delete($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID du bien immobilier non spécifié.');
            $this->redirect('/Properties/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Check if property exists and belongs to user
        $property = $this->propertyModel->getPropertyById($id, $userId);
        
        if (!$property) {
            $this->setFlash('error', 'Bien immobilier non trouvé ou accès non autorisé.');
            $this->redirect('/Properties/list');
            return;
        }
        
        // Confirm deletion if not confirmed yet
        $confirmed = $this->getQuery('confirm');
        if (!$confirmed) {
            $this->render('Modules/Finance/Properties/Views/delete_confirm.twig', [
                'property' => $property
            ]);
            return;
        }
        
        // Delete property
        $result = $this->propertyModel->deleteProperty($id, $userId);
        
        if ($result) {
            $this->setFlash('success', 'Bien immobilier supprimé avec succès.');
        } else {
            $this->setFlash('error', 'Erreur lors de la suppression du bien immobilier.');
        }
        
        $this->redirect('/Properties/list');
    }
    
    /**
     * Create default depreciation assets for a new property
     * 
     * @param int $propertyId Property ID
     * @param int $userId User ID
     * @param float $acquisitionPrice Property acquisition price
     * @param string $acquisitionDate Property acquisition date
     * @return void
     */
    private function createDefaultDepreciationAssets($propertyId, $userId, $acquisitionPrice, $acquisitionDate)
    {
        // Get depreciation model
        $depreciationModel = new \Modules\Accounting\Depreciation\Models\DepreciationModel();
        
        // Get default categories
        $categories = $depreciationModel->getDepreciationCategories();
        
        // Find property category (default to immeuble)
        $propertyCategory = null;
        foreach ($categories as $category) {
            if ($category['category_type'] === 'property') {
                $propertyCategory = $category;
                break;
            }
        }
        
        if ($propertyCategory) {
            // Create building depreciation asset (90% of acquisition price)
            $buildingValue = $acquisitionPrice * 0.9;
            
            $assetData = [
                'property_id' => $propertyId,
                'user_id' => $userId,
                'category_id' => $propertyCategory['id'],
                'name' => 'Bâtiment',
                'acquisition_date' => $acquisitionDate,
                'base_value' => $buildingValue,
                'duration' => $propertyCategory['default_duration'],
                'rate' => $propertyCategory['rate'],
                'status' => 'active',
                'notes' => 'Créé automatiquement à la création du bien'
            ];
            
            $depreciationModel->createAsset($assetData);
            
            // Find furniture category
            $furnitureCategory = null;
            foreach ($categories as $category) {
                if ($category['category_type'] === 'furniture' && $category['name'] === 'Mobilier') {
                    $furnitureCategory = $category;
                    break;
                }
            }
            
            if ($furnitureCategory) {
                // Create furniture depreciation asset (10% of acquisition price)
                $furnitureValue = $acquisitionPrice * 0.1;
                
                $assetData = [
                    'property_id' => $propertyId,
                    'user_id' => $userId,
                    'category_id' => $furnitureCategory['id'],
                    'name' => 'Mobilier',
                    'acquisition_date' => $acquisitionDate,
                    'base_value' => $furnitureValue,
                    'duration' => $furnitureCategory['default_duration'],
                    'rate' => $furnitureCategory['rate'],
                    'status' => 'active',
                    'notes' => 'Créé automatiquement à la création du bien'
                ];
                
                $depreciationModel->createAsset($assetData);
            }
        }
    }
}
