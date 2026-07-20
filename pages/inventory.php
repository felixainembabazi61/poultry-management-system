<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$operatorId = null;
if ($_SESSION['role'] === 'admin') {
    // Admin: get first operator or use null to see all
    $stmt = $pdo->query("SELECT operator_id FROM operator LIMIT 1");
    $op = $stmt->fetch();
    if ($op) {
        $operatorId = $op['operator_id'];
    }
} else {
    $operatorId = getUserOperatorId($_SESSION['user_id']);
}

// Get inventory
$inventory = [];
if ($operatorId) {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE operator_id = ?");
    $stmt->execute([$operatorId]);
    $inventory = $stmt->fetchAll();
}

// Get inventory summary
$totalStock = 0;
$productCount = count($inventory);

include __DIR__ . '/../includes/navbar.php';
?>

<div class="top-bar">
    <div class="page-title">
        <h1>Inventory Management</h1>
        <p>View current stock levels across all products</p>
    </div>
    <div class="top-bar-actions">
        <button class="btn btn-primary btn-sm" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<div class="content-area">
    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-boxes"></i></div>
            <div class="stat-value"><?php echo $productCount; ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-egg"></i></div>
            <div class="stat-value">
                <?php 
                $eggs = array_filter($inventory, function($item) { return $item['product_type'] === 'eggs'; });
                $eggsStock = !empty($eggs) ? reset($eggs)['current_stock'] : 0;
                echo number_format($eggsStock);
                ?>
            </div>
            <div class="stat-label">Eggs Available</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-dove"></i></div>
            <div class="stat-value">
                <?php 
                $birds = array_filter($inventory, function($item) { return $item['product_type'] === 'live_birds'; });
                $birdsStock = !empty($birds) ? reset($birds)['current_stock'] : 0;
                echo number_format($birdsStock);
                ?>
            </div>
            <div class="stat-label">Live Birds</div>
        </div>
    </div>

    <div class="dashboard-card">
        <h3>Current Stock Levels</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php 
            $icons = [
                'eggs' => '🥚',
                'meat' => '🍗',
                'live_birds' => '🐔',
                'manure' => '🌱'
            ];
            $colors = [
                'eggs' => '#f39c12',
                'meat' => '#e74c3c',
                'live_birds' => '#27ae60',
                'manure' => '#8e44ad'
            ];
            $labels = [
                'eggs' => 'Eggs',
                'meat' => 'Meat',
                'live_birds' => 'Live Birds',
                'manure' => 'Manure'
            ];
            
            foreach ($inventory as $item): 
                $icon = $icons[$item['product_type']] ?? '📦';
                $color = $colors[$item['product_type']] ?? '#3498db';
                $label = $labels[$item['product_type']] ?? ucfirst(str_replace('_', ' ', $item['product_type']));
            ?>
            <div style="background: #f8f9fa; padding: 25px; border-radius: 12px; text-align: center; border-left: 5px solid <?php echo $color; ?>; transition: all 0.3s;">
                <div style="font-size: 48px;"><?php echo $icon; ?></div>
                <div style="font-size: 32px; font-weight: 700; margin: 10px 0; color: #2c3e50;">
                    <?php echo number_format($item['current_stock']); ?>
                </div>
                <div style="text-transform: capitalize; font-weight: 600; color: #2c3e50; font-size: 16px;">
                    <?php echo $label; ?>
                </div>
                <div style="color: #888; font-size: 13px; margin-top: 5px;">
                    Unit: <?php echo $item['unit']; ?>
                </div>
                <div style="color: #999; font-size: 11px; margin-top: 8px;">
                    Last updated: <?php echo date('d/m/Y H:i', strtotime($item['last_updated'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($inventory)): ?>
            <div style="grid-column: 1/-1; text-align: center; color: #888; padding: 60px 20px;">
                <i class="fas fa-boxes" style="font-size: 64px; display: block; margin-bottom: 15px; color: #ddd;"></i>
                <h3 style="color: #555;">No Inventory Data</h3>
                <p style="margin-top: 10px;">Start recording production to build your inventory.</p>
                <a href="production.php" class="btn btn-primary" style="margin-top: 15px; display: inline-block; text-decoration: none;">
                    <i class="fas fa-plus"></i> Record Production
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>