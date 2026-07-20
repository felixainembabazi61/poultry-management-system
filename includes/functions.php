<?php
require_once __DIR__ . '/config.php';

function getDashboardStats() {
    global $pdo;
    
    $stats = [];
    
    // Total Flocks
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM flock");
    $result = $stmt->fetch();
    $stats['total_flocks'] = $result ? $result['total'] : 0;
    
    // Total Birds
    $stmt = $pdo->query("SELECT SUM(current_quantity) as total FROM flock WHERE status = 'active'");
    $result = $stmt->fetch();
    $stats['total_birds'] = $result && $result['total'] ? $result['total'] : 0;
    
    // Today's Production
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) as total 
        FROM production 
        WHERE production_date = CURDATE() AND product_type = 'eggs'
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['today_eggs'] = $result && $result['total'] ? $result['total'] : 0;
    
    // Today's Sales
    $stmt = $pdo->prepare("
        SELECT SUM(total_amount) as total 
        FROM sale 
        WHERE sale_date = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['today_sales'] = $result && $result['total'] ? $result['total'] : 0;
    
    // Monthly Revenue
    $stmt = $pdo->prepare("
        SELECT SUM(total_amount) as total 
        FROM sale 
        WHERE MONTH(sale_date) = MONTH(CURDATE()) 
        AND YEAR(sale_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['monthly_revenue'] = $result && $result['total'] ? $result['total'] : 0;
    
    // Monthly Expenses
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total 
        FROM expense 
        WHERE MONTH(expense_date) = MONTH(CURDATE()) 
        AND YEAR(expense_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['monthly_expenses'] = $result && $result['total'] ? $result['total'] : 0;
    
    // Mortality Rate (last 30 days)
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) as total 
        FROM flock_update 
        WHERE action_type = 'mortality' 
        AND update_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['mortality_30days'] = $result && $result['total'] ? $result['total'] : 0;
    
    return $stats;
}

function getRecentActivities($limit = 5) {
    global $pdo;
    
    try {
        // FIXED: Using bindParam instead of passing directly
        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name 
            FROM audit_log a
            LEFT JOIN user u ON a.user_id = u.user_id
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // If table doesn't exist, return empty array
        return [];
    }
}

function getFlockList($operatorId = null) {
    global $pdo;
    
    $sql = "SELECT f.*, u.full_name as operator_name 
            FROM flock f 
            JOIN operator o ON f.operator_id = o.operator_id 
            JOIN user u ON o.user_id = u.user_id";
    
    $params = [];
    if ($operatorId) {
        $sql .= " WHERE f.operator_id = ?";
        $params[] = $operatorId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getFlockUpdates($flockId = null, $limit = 10) {
    global $pdo;
    
    $sql = "SELECT fu.*, f.name as flock_name 
            FROM flock_update fu 
            JOIN flock f ON fu.flock_id = f.flock_id";
    
    $params = [];
    if ($flockId) {
        $sql .= " WHERE fu.flock_id = ?";
        $params[] = $flockId;
    }
    
    $sql .= " ORDER BY fu.update_date DESC LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    if ($flockId) {
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute($params);
    } else {
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

function getRecentSales($limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name as operator_name 
        FROM sale s 
        JOIN operator o ON s.operator_id = o.operator_id 
        JOIN user u ON o.user_id = u.user_id 
        ORDER BY s.sale_date DESC 
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getRecentExpenses($limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as operator_name 
        FROM expense e 
        JOIN operator o ON e.operator_id = o.operator_id 
        JOIN user u ON o.user_id = u.user_id 
        ORDER BY e.expense_date DESC 
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getProductionTrend($days = 30) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            production_date,
            SUM(CASE WHEN product_type = 'eggs' THEN quantity ELSE 0 END) as eggs,
            SUM(CASE WHEN product_type = 'meat' THEN quantity ELSE 0 END) as meat
        FROM production 
        WHERE production_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY production_date
        ORDER BY production_date
    ");
    $stmt->bindParam(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getPieChartData() {
    global $pdo;
    
    try {
        // Flock distribution by type
        $stmt = $pdo->query("
            SELECT poultry_type, COUNT(*) as count 
            FROM flock 
            WHERE status = 'active' 
            GROUP BY poultry_type
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// These functions are now only in auth.php
// formatCurrency and formatDate are in auth.php
?>