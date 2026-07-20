<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$stats = getDashboardStats();

// Get inventory for the current operator
$inventoryItems = [];
$operatorId = null;

if ($_SESSION['role'] === 'admin') {
    // Admin: get first operator or show all
    $stmt = $pdo->query("SELECT operator_id FROM operator LIMIT 1");
    $op = $stmt->fetch();
    if ($op) {
        $operatorId = $op['operator_id'];
    }
} else {
    $operatorId = getUserOperatorId($_SESSION['user_id']);
}

// Fetch inventory if operator exists
if ($operatorId) {
    $stmt = $pdo->prepare("SELECT product_type, current_stock, unit, last_updated FROM inventory WHERE operator_id = ?");
    $stmt->execute([$operatorId]);
    $inventoryItems = $stmt->fetchAll();
}

// If no inventory exists, create default entries
if (empty($inventoryItems) && $operatorId) {
    $defaultProducts = [
        ['product_type' => 'eggs', 'unit' => 'pieces'],
        ['product_type' => 'meat', 'unit' => 'kg'],
        ['product_type' => 'live_birds', 'unit' => 'bird'],
        ['product_type' => 'manure', 'unit' => 'kg']
    ];
    
    foreach ($defaultProducts as $product) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO inventory (operator_id, product_type, current_stock, unit) 
            VALUES (?, ?, 0, ?)
        ");
        $stmt->execute([$operatorId, $product['product_type'], $product['unit']]);
    }
    
    // Refetch inventory
    $stmt = $pdo->prepare("SELECT product_type, current_stock, unit, last_updated FROM inventory WHERE operator_id = ?");
    $stmt->execute([$operatorId]);
    $inventoryItems = $stmt->fetchAll();
}

$recentActivities = getRecentActivities(5);
$recentSales = getRecentSales(5);
$productionTrend = getProductionTrend(7);
$pieData = getPieChartData();

include __DIR__ . '/../includes/navbar.php';
?>

<div class="top-bar">
    <div class="page-title">
        <h1>Dashboard</h1>
        <p>Overview of your poultry farm</p>
    </div>
    <div class="top-bar-actions">
        <button class="btn btn-primary btn-sm" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<div class="content-area">
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-egg"></i></div>
            <div class="stat-value"><?php echo $stats['total_flocks'] ?? 0; ?></div>
            <div class="stat-label">Total Flocks</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-dove"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_birds'] ?? 0); ?></div>
            <div class="stat-label">Total Birds</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-egg"></i></div>
            <div class="stat-value"><?php echo number_format($stats['today_eggs'] ?? 0); ?></div>
            <div class="stat-label">Today's Eggs</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?php echo formatCurrency($stats['today_sales'] ?? 0); ?></div>
            <div class="stat-label">Today's Sales</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-value"><?php echo formatCurrency($stats['monthly_revenue'] ?? 0); ?></div>
            <div class="stat-label">Monthly Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-skull-crossbones"></i></div>
            <div class="stat-value"><?php echo number_format($stats['mortality_30days'] ?? 0); ?></div>
            <div class="stat-label">Mortality (30 days)</div>
        </div>
    </div>
    
    <!-- Inventory Section -->
    <div class="dashboard-card" style="margin-bottom: 20px;">
        <h3><i class="fas fa-boxes"></i> Current Inventory</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 15px;">
            <?php 
            $productIcons = [
                'eggs' => '🥚',
                'meat' => '🍗',
                'live_birds' => '🐔',
                'manure' => '🌱'
            ];
            $productColors = [
                'eggs' => '#f39c12',
                'meat' => '#e74c3c',
                'live_birds' => '#27ae60',
                'manure' => '#8e44ad'
            ];
            $productLabels = [
                'eggs' => 'Eggs',
                'meat' => 'Meat',
                'live_birds' => 'Live Birds',
                'manure' => 'Manure'
            ];
            
            if (!empty($inventoryItems)):
                foreach ($inventoryItems as $item):
                    $icon = $productIcons[$item['product_type']] ?? '📦';
                    $color = $productColors[$item['product_type']] ?? '#3498db';
                    $label = $productLabels[$item['product_type']] ?? ucfirst(str_replace('_', ' ', $item['product_type']));
            ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center; border-left: 4px solid <?php echo $color; ?>;">
                    <div style="font-size: 32px;"><?php echo $icon; ?></div>
                    <div style="font-size: 24px; font-weight: 700; margin: 5px 0; color: #2c3e50;">
                        <?php echo number_format($item['current_stock']); ?>
                    </div>
                    <div style="color: #555; font-size: 14px; font-weight: 500;">
                        <?php echo $label; ?>
                    </div>
                    <div style="color: #999; font-size: 11px;">
                        <?php echo $item['unit']; ?>
                    </div>
                </div>
            <?php 
                endforeach;
            else: 
            ?>
                <div style="grid-column: 1/-1; text-align: center; color: #888; padding: 30px;">
                    <i class="fas fa-boxes" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                    <p>No inventory data available. Start recording production to build inventory.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-line"></i> Production Trend (Last 7 Days)</h3>
            <div style="height: 250px;">
                <canvas id="productionChart"></canvas>
            </div>
        </div>
        
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-pie"></i> Flock Distribution</h3>
            <div style="height: 250px;">
                <canvas id="flockPieChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="dashboard-grid" style="margin-top: 20px;">
        <div class="dashboard-card">
            <h3><i class="fas fa-history"></i> Recent Activities</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivities as $activity): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></td>
                            <td><?php echo htmlspecialchars($activity['action']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentActivities)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #888;">No recent activities</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="dashboard-card">
            <h3><i class="fas fa-dollar-sign"></i> Recent Sales</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSales as $sale): ?>
                        <tr>
                            <td><?php echo formatDate($sale['sale_date']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $sale['product_type'])); ?></td>
                            <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentSales)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #888;">No recent sales</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Production Chart
const ctx1 = document.getElementById('productionChart').getContext('2d');
const productionData = <?php echo json_encode($productionTrend); ?>;

new Chart(ctx1, {
    type: 'line',
    data: {
        labels: productionData.map(d => d.production_date),
        datasets: [
            {
                label: 'Eggs',
                data: productionData.map(d => d.eggs || 0),
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                fill: true,
                tension: 0.4
            },
            {
                label: 'Meat (kg)',
                data: productionData.map(d => d.meat || 0),
                borderColor: '#27ae60',
                backgroundColor: 'rgba(39, 174, 96, 0.1)',
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 12,
                    padding: 10
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Pie Chart
const ctx2 = document.getElementById('flockPieChart').getContext('2d');
const pieData = <?php echo json_encode($pieData); ?>;

new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: pieData.map(d => d.poultry_type || 'Unknown'),
        datasets: [{
            data: pieData.map(d => d.count),
            backgroundColor: ['#3498db', '#27ae60', '#f39c12', '#e74c3c', '#9b59b6'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 10
                }
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>