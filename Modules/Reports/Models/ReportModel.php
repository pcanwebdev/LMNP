<?php
namespace Modules\Accounting\Reports\Models;

use Core\Model;

/**
 * Report Model
 * Handles report-related database operations
 */
class ReportModel extends Model
{
    /**
     * Table name
     */
    protected $table = 'reports';
    
    /**
     * Fillable fields
     */
    protected $fillable = [
        'user_id',
        'report_type',
        'fiscal_year',
        'status',
        'data'
    ];
    
    /**
     * Get reports filtered by various criteria
     * 
     * @param int $userId User ID
     * @param array $filter Filter criteria
     * @return array Filtered reports
     */
    public function getFilteredReports($userId, $filter = [])
    {
        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];
        
        // Add year filter
        if (!empty($filter['year'])) {
            $conditions[] = 'fiscal_year = :year';
            $params['year'] = $filter['year'];
        }
        
        // Add report type filter
        if (!empty($filter['report_type'])) {
            $conditions[] = 'report_type = :report_type';
            $params['report_type'] = $filter['report_type'];
        }
        
        // Build WHERE clause
        $where = implode(' AND ', $conditions);
        
        // Build query
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a report by ID and user ID
     * 
     * @param int $id Report ID
     * @param int $userId User ID
     * @return array|false Report data or false if not found
     */
    public function getReportById($id, $userId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get a report by type, fiscal year, and user ID
     * 
     * @param int $userId User ID
     * @param string $type Report type
     * @param int $year Fiscal year
     * @return array|false Report data or false if not found
     */
    public function getReportByTypeAndYear($userId, $type, $year)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id AND report_type = :type AND fiscal_year = :year 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'year' => $year
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new report
     * 
     * @param array $data Report data
     * @return int|false New report ID or false on failure
     */
    public function createReport($data)
    {
        return $this->create($data);
    }
    
    /**
     * Update a report
     * 
     * @param int $id Report ID
     * @param int $userId User ID
     * @param array $data Updated data
     * @return bool Success or failure
     */
    public function updateReport($id, $userId, $data)
    {
        // Check if report exists and belongs to user
        $report = $this->getReportById($id, $userId);
        
        if (!$report) {
            return false;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Delete a report
     * 
     * @param int $id Report ID
     * @param int $userId User ID
     * @return bool Success or failure
     */
    public function deleteReport($id, $userId)
    {
        // Check if report exists and belongs to user
        $report = $this->getReportById($id, $userId);
        
        if (!$report) {
            return false;
        }
        
        return $this->delete($id);
    }
    
    /**
     * Get available fiscal years for a user
     * 
     * @param int $userId User ID
     * @return array Fiscal years
     */
    public function getAvailableFiscalYears($userId)
    {
        // Get years from revenues
        $sql = "SELECT DISTINCT YEAR(revenue_date) as year 
                FROM revenues 
                WHERE user_id = :user_id 
                ORDER BY year";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $revenueYears = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'year');
        
        // Get years from expenses
        $sql = "SELECT DISTINCT YEAR(expense_date) as year 
                FROM expenses 
                WHERE user_id = :user_id 
                ORDER BY year";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $expenseYears = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'year');
        
        // Get years from reports
        $sql = "SELECT DISTINCT fiscal_year as year 
                FROM {$this->table} 
                WHERE user_id = :user_id 
                ORDER BY year";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $reportYears = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'year');
        
        // Merge and sort years
        $years = array_unique(array_merge($revenueYears, $expenseYears, $reportYears));
        sort($years);
        
        return $years;
    }
    
    /**
     * Get report count by type for a specific year
     * 
     * @param int $userId User ID
     * @param int $year Fiscal year
     * @return array Report counts by type
     */
    public function getReportCountByType($userId, $year)
    {
        $sql = "SELECT report_type, COUNT(*) as count 
                FROM {$this->table} 
                WHERE user_id = :user_id AND fiscal_year = :year 
                GROUP BY report_type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'year' => $year
        ]);
        
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Convert to associative array with report type as key
        $counts = [];
        foreach ($result as $row) {
            $counts[$row['report_type']] = $row['count'];
        }
        
        return $counts;
    }
}
