<?php
namespace Modules\Finance\Expenses\Controllers;

use Core\Controller;
use Modules\Finance\Expenses\Models\ExpenseModel;
use Modules\Finance\Properties\Models\PropertyModel;

/**
 * Expenses Controller
 * Handles expense management operations
 */
class ExpensesController extends Controller
{
    /**
     * Expense model
     */
    protected $expenseModel;
    
    /**
     * Property model
     */
    protected $propertyModel;
    
    /**
     * Initialize controller
     */
    protected function init()
    {
        $this->expenseModel = new ExpenseModel();
        $this->propertyModel = new PropertyModel();
    }
    
    /**
     * List expenses
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
        $deductible = $this->getQuery('deductible');
        
        // Get properties for filter dropdown
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get expense categories
        $categories = getExpenseCategories();
        
        // Prepare filter
        $filter = [
            'property_id' => $propertyId,
            'year' => $year,
            'month' => $month,
            'category' => $category,
            'deductible' => $deductible
        ];
        
        // Get expenses based on filter
        $expenses = $this->expenseModel->getFilteredExpenses($userId, $filter);
        
        // Calculate totals
        $total = 0;
        foreach ($expenses as $expense) {
            $total += $expense['amount'];
        }
        
        // Get monthly summary
        $monthlySummary = $this->expenseModel->getMonthlySummary($userId, $year);
        
        // Get category breakdown
        $categoryBreakdown = $this->expenseModel->getCategoryBreakdown($userId, $year);
        
        // Render expense list
        $this->render('Modules/Finance/Expenses/Views/list.twig', [
            'expenses' => $expenses,
            'properties' => $properties,
            'categories' => $categories,
            'filter' => $filter,
            'total' => $total,
            'monthlySummary' => $monthlySummary,
            'categoryBreakdown' => $categoryBreakdown
        ]);
    }
    
    /**
     * Add a new expense
     * 
     * @return void
     */
    public function add()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get properties
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get expense categories
        $categories = getExpenseCategories();
        
        // Pre-fill property ID if provided
        $propertyId = $this->getQuery('property_id');
        
        // Render add expense form
        $this->render('Modules/Finance/Expenses/Views/edit.twig', [
            'action' => 'add',
            'expense' => [
                'expense_date' => date('Y-m-d'),
                'property_id' => $propertyId,
                'is_deductible' => 1
            ],
            'properties' => $properties,
            'categories' => $categories
        ]);
    }
    
    /**
     * Edit an existing expense
     * 
     * @param int $id Expense ID
     * @return void
     */
    public function edit($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID de la dépense non spécifié.');
            $this->redirect('/Expenses/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get expense
        $expense = $this->expenseModel->getExpenseById($id, $userId);
        
        if (!$expense) {
            $this->setFlash('error', 'Dépense non trouvée ou accès non autorisé.');
            $this->redirect('/Expenses/list');
            return;
        }
        
        // Get properties
        $properties = $this->propertyModel->findByUserId($userId);
        
        // Get expense categories
        $categories = getExpenseCategories();
        
        // Render edit expense form
        $this->render('Modules/Finance/Expenses/Views/edit.twig', [
            'action' => 'edit',
            'expense' => $expense,
            'properties' => $properties,
            'categories' => $categories
        ]);
    }
    
    /**
     * Save expense data
     * 
     * @return void
     */
    public function save()
    {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get expense data from form
        $id = $this->getPost('id');
        $propertyId = $this->getPost('property_id');
        $amount = floatval(str_replace(',', '.', $this->getPost('amount')));
        $expenseDate = $this->getPost('expense_date');
        $description = $this->getPost('description');
        $category = $this->getPost('category');
        $isDeductible = $this->getPost('is_deductible') ? 1 : 0;
        
        // Validate data
        if (empty($propertyId) || $amount <= 0 || empty($expenseDate)) {
            $this->setFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            $this->redirect($id ? "/Expenses/edit/{$id}" : '/Expenses/add');
            return;
        }
        
        // Check if property exists and belongs to user
        $property = $this->propertyModel->getPropertyById($propertyId, $userId);
        if (!$property) {
            $this->setFlash('error', 'Bien immobilier invalide.');
            $this->redirect($id ? "/Expenses/edit/{$id}" : '/Expenses/add');
            return;
        }
        
        // Handle receipt upload
        $receiptPath = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $receiptPath = $this->uploadReceipt($_FILES['receipt']);
            
            if (!$receiptPath) {
                $this->setFlash('error', 'Erreur lors de l\'upload du justificatif.');
                $this->redirect($id ? "/Expenses/edit/{$id}" : '/Expenses/add');
                return;
            }
        }
        
        // Prepare expense data
        $expenseData = [
            'property_id' => $propertyId,
            'user_id' => $userId,
            'amount' => $amount,
            'expense_date' => $expenseDate,
            'description' => $description,
            'category' => $category,
            'is_deductible' => $isDeductible
        ];
        
        // Add receipt path if uploaded
        if ($receiptPath) {
            $expenseData['receipt_path'] = $receiptPath;
        }
        
        // Save expense
        if ($id) {
            // Update existing expense
            $result = $this->expenseModel->updateExpense($id, $userId, $expenseData);
            $message = 'Dépense mise à jour avec succès.';
        } else {
            // Create new expense
            $result = $this->expenseModel->createExpense($expenseData);
            $message = 'Dépense ajoutée avec succès.';
        }
        
        if ($result) {
            $this->setFlash('success', $message);
            $this->redirect('/Expenses/list');
        } else {
            $this->setFlash('error', 'Erreur lors de la sauvegarde de la dépense.');
            $this->redirect($id ? "/Expenses/edit/{$id}" : '/Expenses/add');
        }
    }
    
    /**
     * Delete an expense
     * 
     * @param int $id Expense ID
     * @return void
     */
    public function delete($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID de la dépense non spécifié.');
            $this->redirect('/Expenses/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Check if expense exists and belongs to user
        $expense = $this->expenseModel->getExpenseById($id, $userId);
        
        if (!$expense) {
            $this->setFlash('error', 'Dépense non trouvée ou accès non autorisé.');
            $this->redirect('/Expenses/list');
            return;
        }
        
        // Confirm deletion if not confirmed yet
        $confirmed = $this->getQuery('confirm');
        if (!$confirmed) {
            $this->render('Modules/Finance/Expenses/Views/delete_confirm.twig', [
                'expense' => $expense
            ]);
            return;
        }
        
        // Delete expense
        $result = $this->expenseModel->deleteExpense($id, $userId);
        
        if ($result) {
            // Delete receipt file if exists
            if (!empty($expense['receipt_path']) && file_exists($expense['receipt_path'])) {
                unlink($expense['receipt_path']);
            }
            
            $this->setFlash('success', 'Dépense supprimée avec succès.');
        } else {
            $this->setFlash('error', 'Erreur lors de la suppression de la dépense.');
        }
        
        $this->redirect('/Expenses/list');
    }
    
    /**
     * View receipt
     * 
     * @param int $id Expense ID
     * @return void
     */
    public function viewReceipt($id = null)
    {
        // Check if ID is provided
        if (!$id) {
            $this->setFlash('error', 'ID de la dépense non spécifié.');
            $this->redirect('/Expenses/list');
            return;
        }
        
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get expense
        $expense = $this->expenseModel->getExpenseById($id, $userId);
        
        if (!$expense || empty($expense['receipt_path'])) {
            $this->setFlash('error', 'Justificatif non trouvé ou accès non autorisé.');
            $this->redirect('/Expenses/list');
            return;
        }
        
        // Check if file exists
        if (!file_exists($expense['receipt_path'])) {
            $this->setFlash('error', 'Le fichier du justificatif n\'existe pas.');
            $this->redirect('/Expenses/list');
            return;
        }
        
        // Get file extension
        $extension = pathinfo($expense['receipt_path'], PATHINFO_EXTENSION);
        
        // Set content type based on extension
        switch (strtolower($extension)) {
            case 'pdf':
                header('Content-Type: application/pdf');
                break;
                
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
                
            case 'png':
                header('Content-Type: image/png');
                break;
                
            default:
                header('Content-Type: application/octet-stream');
        }
        
        // Output file
        header('Content-Disposition: inline; filename="' . basename($expense['receipt_path']) . '"');
        header('Content-Length: ' . filesize($expense['receipt_path']));
        readfile($expense['receipt_path']);
        exit;
    }
    
    /**
     * Upload receipt file
     * 
     * @param array $file File from $_FILES
     * @return string|false Path to the uploaded file or false on failure
     */
    private function uploadReceipt($file)
    {
        // Check if upload directory exists, create if not
        $uploadDir = APP_ROOT . '/uploads/receipts';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('receipt_') . '.' . $extension;
        $filepath = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $filepath;
        }
        
        return false;
    }
}
