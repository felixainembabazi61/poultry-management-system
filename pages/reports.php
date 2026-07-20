<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$operatorId = null;
if ($_SESSION['role'] !== 'admin') {
    $operatorId = getUserOperatorId($_SESSION['user_id']);
}

$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'production';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get all flocks for dropdown
$flocks = getFlockList($operatorId);
$flockId = isset($_GET['flock_id']) ? (int)$_GET['flock_id'] : 0;

// Get report data based on type
$reportData = [];
$chartData = [];

if ($reportType === 'production') {
    // Production Report
    $sql = "SELECT 
        p.production_date,
        SUM(CASE WHEN p.product_type = 'eggs' THEN p.quantity ELSE 0 END) as eggs,
        SUM(CASE WHEN p.product_type = 'meat' THEN p.quantity ELSE 0 END) as meat,
        COUNT(DISTINCT p.flock_id) as flocks_involved
    FROM production p";
    
    $params = [];
    $conditions = ["p.production_date BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($flockId > 0) {
        $conditions[] = "p.flock_id = ?";
        $params[] = $flockId;
    }
    
    $sql .= " WHERE " . implode(" AND ", $conditions);
    $sql .= " GROUP BY p.production_date ORDER BY p.production_date";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
    
    // Prepare chart data
    $chartData = [
        'labels' => array_column($reportData, 'production_date'),
        'eggs' => array_column($reportData, 'eggs'),
        'meat' => array_column($reportData, 'meat')
    ];
    
} elseif ($reportType === 'sales') {
    // Sales Report
    $sql = "SELECT 
        s.sale_date,
        s.product_type,
        SUM(s.quantity) as total_quantity,
        SUM(s.total_amount) as total_revenue,
        COUNT(*) as transaction_count
    FROM sale s";
    
    $params = [];
    $conditions = ["s.sale_date BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($operatorId) {
        $conditions[] = "s.operator_id = ?";
        $params[] = $operatorId;
    }
    
    $sql .= " WHERE " . implode(" AND ", $conditions);
    $sql .= " GROUP BY s.sale_date, s.product_type ORDER BY s.sale_date";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
    
    // Summary
    $summarySql = "SELECT 
        SUM(total_amount) as total_revenue,
        SUM(quantity) as total_quantity,
        COUNT(*) as total_transactions
    FROM sale";
    $summaryParams = [];
    if ($operatorId) {
        $summarySql .= " WHERE operator_id = ?";
        $summaryParams[] = $operatorId;
    }
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($summaryParams);
    $summary = $summaryStmt->fetch();
    
} elseif ($reportType === 'expenses') {
    // Expenses Report
    $sql = "SELECT 
        e.expense_date,
        e.category,
        SUM(e.amount) as total_amount,
        COUNT(*) as count
    FROM expense e";
    
    $params = [];
    $conditions = ["e.expense_date BETWEEN ? AND ?"];
    $params = [$startDate, $endDate];
    
    if ($operatorId) {
        $conditions[] = "e.operator_id = ?";
        $params[] = $operatorId;
    }
    
    $sql .= " WHERE " . implode(" AND ", $conditions);
    $sql .= " GROUP BY e.expense_date, e.category ORDER BY e.expense_date";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
    
    // Summary
    $summarySql = "SELECT 
        SUM(amount) as total_expenses,
        COUNT(*) as total_count,
        category,
        SUM(amount) as category_total
    FROM expense";
    $summaryParams = [];
    if ($operatorId) {
        $summarySql .= " WHERE operator_id = ?";
        $summaryParams[] = $operatorId;
    }
    $summarySql .= " GROUP BY category ORDER BY category_total DESC";
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($summaryParams);
    $categorySummary = $summaryStmt->fetchAll();
    
} elseif ($reportType === 'flock') {
    // Flock Report
    $sql = "SELECT 
        f.flock_id,
        f.name,
        f.poultry_type,
        f.breed,
        f.initial_quantity,
        f.current_quantity,
        (f.initial_quantity - f.current_quantity) as total_loss,
        f.status,
        f.arrival_date,
        COUNT(DISTINCT p.production_id) as production_days,
        SUM(p.quantity) as total_production
    FROM flock f
    LEFT JOIN production p ON f.flock_id = p.flock_id AND p.production_date BETWEEN ? AND ?";
    
    $params = [$startDate, $endDate];
    
    if ($operatorId) {
        $sql .= " WHERE f.operator_id = ?";
        $params[] = $operatorId;
    }
    
    $sql .= " GROUP BY f.flock_id ORDER BY f.flock_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

include __DIR__ . '/../includes/navbar.php';
?>

<div class="top-bar">
    <div class="page-title">
        <h1>Reports</h1>
        <p>Generate and view reports</p>
    </div>
    <div class="top-bar-actions">
        <button class="btn btn-success btn-sm" onclick="window.print()">
            <i class="fas fa-print"></i> Print Report
        </button>
        <button class="btn btn-primary btn-sm" onclick="exportReport()">
            <i class="fas fa-file-export"></i> Export
        </button>
    </div>
</div>

<div class="content-area">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <!-- Report Filter -->
    <div class="dashboard-card" style="margin-bottom: 20px;">
        <form method="GET" action="" class="filter-form">
            <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                    <label for="report_type">Report Type</label>
                    <select id="report_type" name="report_type" class="form-control" onchange="this.form.submit()">
                        <option value="production" <?php echo $reportType === 'production' ? 'selected' : ''; ?>>Production Report</option>
                        <option value="sales" <?php echo $reportType === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                        <option value="expenses" <?php echo $reportType === 'expenses' ? 'selected' : ''; ?>>Expenses Report</option>
                        <option value="flock" <?php echo $reportType === 'flock' ? 'selected' : ''; ?>>Flock Report</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                    <label for="flock_id">Flock</label>
                    <select id="flock_id" name="flock_id" class="form-control">
                        <option value="0">All Flocks</option>
                        <?php foreach ($flocks as $flock): ?>
                            <option value="<?php echo $flock['flock_id']; ?>" <?php echo $flockId == $flock['flock_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($flock['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Report Content -->
    <div class="dashboard-card" id="reportContent">
        <h3><?php echo ucfirst($reportType); ?> Report</h3>
        <p style="color: #888; margin-bottom: 20px;">Period: <?php echo formatDate($startDate); ?> to <?php echo formatDate($endDate); ?></p>
        
        <?php if ($reportType === 'production'): ?>
            <div style="height: 300px; margin-bottom: 30px;">
                <canvas id="reportChart"></canvas>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Eggs (pieces)</th>
                            <th>Meat (kg)</th>
                            <th>Flocks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?php echo formatDate($row['production_date']); ?></td>
                            <td><?php echo number_format($row['eggs'] ?? 0); ?></td>
                            <td><?php echo number_format($row['meat'] ?? 0); ?></td>
                            <td><?php echo $row['flocks_involved']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reportData)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #888;">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($reportType === 'sales'): ?>
            <div class="stats-grid" style="margin-bottom: 20px;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo formatCurrency($summary['total_revenue'] ?? 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($summary['total_quantity'] ?? 0); ?></div>
                    <div class="stat-label">Total Quantity</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($summary['total_transactions'] ?? 0); ?></div>
                    <div class="stat-label">Transactions</div>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Revenue</th>
                            <th>Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?php echo formatDate($row['sale_date']); ?></td>
                            <td><?php echo ucfirst($row['product_type']); ?></td>
                            <td><?php echo number_format($row['total_quantity']); ?></td>
                            <td><?php echo formatCurrency($row['total_revenue']); ?></td>
                            <td><?php echo $row['transaction_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reportData)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #888;">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($reportType === 'expenses'): ?>
            <div class="stats-grid" style="margin-bottom: 20px;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo formatCurrency(array_sum(array_column($categorySummary, 'category_total'))); ?></div>
                    <div class="stat-label">Total Expenses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($categorySummary); ?></div>
                    <div class="stat-label">Categories</div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <?php foreach ($categorySummary as $cat): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <strong><?php echo ucfirst($cat['category']); ?></strong>
                    <div style="font-size: 20px; font-weight: 700; color: #2c3e50;">
                        <?php echo formatCurrency($cat['category_total']); ?>
                    </div>
                    <div style="color: #888; font-size: 12px;"><?php echo $cat['total_count']; ?> records</div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?php echo formatDate($row['expense_date']); ?></td>
                            <td><?php echo ucfirst($row['category']); ?></td>
                            <td><?php echo formatCurrency($row['total_amount']); ?></td>
                            <td><?php echo $row['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reportData)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #888;">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($reportType === 'flock'): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Breed</th>
                            <th>Initial</th>
                            <th>Current</th>
                            <th>Loss</th>
                            <th>Status</th>
                            <th>Production Days</th>
                            <th>Total Production</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?php echo $row['flock_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $row['poultry_type'])); ?></td>
                            <td><?php echo htmlspecialchars($row['breed']); ?></td>
                            <td><?php echo number_format($row['initial_quantity']); ?></td>
                            <td><?php echo number_format($row['current_quantity']); ?></td>
                            <td><?php echo number_format($row['total_loss']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $row['status'] === 'active' ? 'success' : ($row['status'] === 'completed' ? 'info' : 'danger'); ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $row['production_days'] ?? 0; ?></td>
                            <td><?php echo number_format($row['total_production'] ?? 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reportData)): ?>
                        <tr><td colspan="10" style="text-align: center; color: #888;">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function exportReport() {
    // Simple export - print the report
    window.print();
}

// Report Chart
<?php if ($reportType === 'production' && !empty($chartData['labels'])): ?>
const ctx = document.getElementById('reportChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartData['labels']); ?>,
        datasets: [
            {
                label: 'Eggs (pieces)',
                data: <?php echo json_encode($chartData['eggs']); ?>,
                backgroundColor: 'rgba(52, 152, 219, 0.6)',
                borderColor: '#3498db',
                borderWidth: 2
            },
            {
                label: 'Meat (kg)',
                data: <?php echo json_encode($chartData['meat']); ?>,
                backgroundColor: 'rgba(39, 174, 96, 0.6)',
                borderColor: '#27ae60',
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>