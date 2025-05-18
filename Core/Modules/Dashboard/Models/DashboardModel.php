<?php
namespace Modules\Core\Dashboard\Models;

use Core\Model;

/**
 * Dashboard Model
 * Handles dashboard-specific data operations
 */
class DashboardModel extends Model
{
    /**
     * Get dashboard data for a user
     * 
     * @param int $userId User ID
     * @return array Dashboard data
     */
    public function getDashboardData($userId)
    {
        // Combine data from multiple tables for dashboard
        
        // Property count
        $propertyCount = $this->query("
            SELECT COUNT(*) as count
            FROM properties
            WHERE user_id = :user_id
        ", ['user_id' => $userId], false);
        
        // Current year revenues
        $revenues = $this->query("
            SELECT SUM(amount) as total
            FROM revenues
            WHERE user_id = :user_id
              AND YEAR(revenue_date) = YEAR(CURRENT_DATE())
        ", ['user_id' => $userId], false);
        
        // Current year expenses
        $expenses = $this->query("
            SELECT SUM(amount) as total
            FROM expenses
            WHERE user_id = :user_id
              AND YEAR(expense_date) = YEAR(CURRENT_DATE())
        ", ['user_id' => $userId], false);
        
        // Recent transactions
        $transactions = $this->query("
            (SELECT 'revenue' as type, amount, revenue_date as date, description, property_id
             FROM revenues
             WHERE user_id = :user_id
             ORDER BY revenue_date DESC
             LIMIT 5)
            UNION ALL
            (SELECT 'expense' as type, amount, expense_date as date, description, property_id
             FROM expenses
             WHERE user_id = :user_id
             ORDER BY expense_date DESC
             LIMIT 5)
            ORDER BY date DESC
            LIMIT 10
        ", ['user_id' => $userId]);
        
        // Property names for transactions
        $propertyIds = array_map(function($item) {
            return $item['property_id'];
        }, $transactions);
        
        $propertyNames = [];
        
        if (!empty($propertyIds)) {
            $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
            $propertyData = $this->query("
                SELECT id, name
                FROM properties
                WHERE id IN ({$placeholders})
            ", $propertyIds);
            
            foreach ($propertyData as $property) {
                $propertyNames[$property['id']] = $property['name'];
            }
        }
        
        // Add property names to transactions
        foreach ($transactions as &$transaction) {
            $transaction['property_name'] = $propertyNames[$transaction['property_id']] ?? 'Unknown';
        }
        
        return [
            'propertyCount' => $propertyCount['count'] ?? 0,
            'totalRevenues' => $revenues['total'] ?? 0,
            'totalExpenses' => $expenses['total'] ?? 0,
            'netIncome' => ($revenues['total'] ?? 0) - ($expenses['total'] ?? 0),
            'transactions' => $transactions
        ];
    }
    
    /**
     * Get monthly revenue and expense totals for a year
     * 
     * @param int $userId User ID
     * @param int $year Year
     * @return array Monthly totals
     */
    public function getMonthlyTotals($userId, $year)
    {
        // Generate months
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = [
                'month' => $i,
                'revenues' => 0,
                'expenses' => 0
            ];
        }
        
        // Get monthly revenues
        $revenues = $this->query("
            SELECT MONTH(revenue_date) as month, SUM(amount) as total
            FROM revenues
            WHERE user_id = :user_id
              AND YEAR(revenue_date) = :year
            GROUP BY MONTH(revenue_date)
        ", ['user_id' => $userId, 'year' => $year]);
        
        foreach ($revenues as $revenue) {
            $months[$revenue['month']]['revenues'] = (float) $revenue['total'];
        }
        
        // Get monthly expenses
        $expenses = $this->query("
            SELECT MONTH(expense_date) as month, SUM(amount) as total
            FROM expenses
            WHERE user_id = :user_id
              AND YEAR(expense_date) = :year
            GROUP BY MONTH(expense_date)
        ", ['user_id' => $userId, 'year' => $year]);
        
        foreach ($expenses as $expense) {
            $months[$expense['month']]['expenses'] = (float) $expense['total'];
        }
        
        return array_values($months);
    }
    
    /**
     * Get expense breakdown by category
     * 
     * @param int $userId User ID
     * @param int $year Year
     * @return array Category breakdown
     */
    public function getExpenseBreakdown($userId, $year)
    {
        return $this->query("
            SELECT category, SUM(amount) as total
            FROM expenses
            WHERE user_id = :user_id
              AND YEAR(expense_date) = :year
            GROUP BY category
            ORDER BY total DESC
        ", ['user_id' => $userId, 'year' => $year]);
    }
}
