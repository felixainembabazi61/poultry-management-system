<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$operatorId = null;
if ($_SESSION['role'] !== 'admin') {
    $operatorId = getUserOperatorId($_SESSION['user_id']);
}

// Get all flocks for dropdown
$flocks = getFlockList($operatorId);

// Get production records
$flockId = isset($_GET['flock_id']) ? (int)$_GET['flock_id'] : 0;
$period = isset($_GET['period']) ? $_GET['period'] : 'month';

$sql = "SELECT p.*, f.name as flock_name 
        FROM production p 
        JOIN flock f ON p.flock_id = f.flock_id";
$params = [];

if ($flockId > 0) {
    $sql .= " WHERE p.flock_id = ?";
    $params[] = $flockId;
}

if ($period === 'week') {
    $sql .= ($flockId > 0 ? " AND" : " WHERE") . " p.production_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($period === 'month') {
    $sql .= ($flockId > 0 ? " AND" : " WHERE") . " p.production_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif ($period === 'year') {
    $sql .= ($flockId > 0 ? " AND" : " WHERE") . " p.production_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
}

$sql .= " ORDER BY p.production_date DESC, p.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productions = $stmt->fetchAll();

// Get summary statistics
$summarySql = "SELECT 
    SUM(CASE WHEN product_type = 'eggs' THEN quantity ELSE 0 END) as total_eggs,
    SUM(CASE WHEN product_type = 'meat' THEN quantity ELSE 0 END) as total_meat,
    COUNT(DISTINCT production_date) as production_days
FROM production p";
if ($flockId > 0) {
    $summarySql .= " WHERE flock_id = ?";
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute([$flockId]);
} else {
    $summaryStmt = $pdo->query($summarySql);
}
$summary = $summaryStmt->fetch();

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

include __DIR__ . '/../includes/navbar.php';
?>

<div class="top-bar">
    <div class="page-title">
        <h1>Daily Production</h1>
        <p>Track eggs and meat production</p>
    </div>
    <div class="top-bar-actions">
        <button class="btn btn-primary btn-sm" onclick="showAddProductionModal()">
            <i class="fas fa-plus"></i> Record Production
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
    
    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-egg"></i></div>
            <div class="stat-value"><?php echo number_format($summary['total_eggs'] ?? 0); ?></div>
            <div class="stat-label">Total Eggs</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-drumstick-bite"></i></div>
            <div class="stat-value"><?php echo number_format($summary['total_meat'] ?? 0); ?> kg</div>
            <div class="stat-label">Total Meat</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-value"><?php echo number_format($summary['production_days'] ?? 0); ?></div>
            <div class="stat-label">Production Days</div>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="dashboard-card" style="margin-bottom: 20px;">
        <form method="GET" action="" class="filter-form">
            <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                    <label for="flock_id">Flock</label>
                    <select id="flock_id" name="flock_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Flocks</option>
                        <?php foreach ($flocks as $flock): ?>
                            <option value="<?php echo $flock['flock_id']; ?>" <?php echo $flockId == $flock['flock_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($flock['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                    <label for="period">Period</label>
                    <select id="period" name="period" class="form-control" onchange="this.form.submit()">
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Last Year</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <a href="production.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Production Table -->
    <div class="dashboard-card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Flock</th>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Note</th>
                        <th>Recorded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productions as $production): ?>
                    <tr>
                        <td><?php echo $production['production_id']; ?></td>
                        <td><?php echo htmlspecialchars($production['flock_name'] ?? 'N/A'); ?></td>
                        <td><?php echo formatDate($production['production_date']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $production['product_type'] === 'eggs' ? 'warning' : 'info'; ?>">
                                <?php echo ucfirst($production['product_type']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($production['quantity']); ?></td>
                        <td><?php echo $production['unit']; ?></td>
                        <td><?php echo htmlspecialchars($production['note'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($production['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteProduction(<?php echo $production['production_id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($productions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #888; padding: 30px;">
                            <i class="fas fa-boxes" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                            No production records found. Record your first production.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Production Modal -->
<div id="addProductionModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Record Production</h3>
            <button class="modal-close" onclick="closeModal('addProductionModal')">&times;</button>
        </div>
        <form method="POST" action="production_actions.php">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="prod_flock_id">Flock *</label>
                <select id="prod_flock_id" name="flock_id" class="form-control" required>
                    <option value="">-- Select Flock --</option>
                    <?php foreach ($flocks as $flock): ?>
                        <option value="<?php echo $flock['flock_id']; ?>">
                            <?php echo htmlspecialchars($flock['name']); ?> (<?php echo ucfirst($flock['poultry_type']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="production_date">Production Date *</label>
                    <input type="date" id="production_date" name="production_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="product_type">Product Type *</label>
                    <select id="product_type" name="product_type" class="form-control" required>
                        <option value="eggs">Eggs</option>
                        <option value="meat">Meat</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" class="form-control" required min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="unit">Unit *</label>
                    <select id="unit" name="unit" class="form-control" required>
                        <option value="pieces">Pieces</option>
                        <option value="kg">Kilograms (kg)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="prod_note">Note</label>
                <textarea id="prod_note" name="note" class="form-control" rows="3" placeholder="Optional note"></textarea>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Production
                </button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addProductionModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddProductionModal() {
    document.getElementById('addProductionModal').style.display = 'flex';
    document.getElementById('production_date').value = new Date().toISOString().split('T')[0];
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function deleteProduction(id) {
    if (confirm('Are you sure you want to delete this production record?')) {
        window.location.href = 'production_actions.php?action=delete&id=' + id;
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>