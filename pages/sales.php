<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$operatorId = null;
if ($_SESSION['role'] !== 'admin') {
    $operatorId = getUserOperatorId($_SESSION['user_id']);
}

// Get sales
$sql = "SELECT s.*, u.full_name as operator_name 
        FROM sale s 
        JOIN operator o ON s.operator_id = o.operator_id 
        JOIN user u ON o.user_id = u.user_id";
$params = [];

if ($operatorId) {
    $sql .= " WHERE s.operator_id = ?";
    $params[] = $operatorId;
}

$sql .= " ORDER BY s.sale_date DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Get total sales
$totalSql = "SELECT SUM(total_amount) as total FROM sale";
$totalParams = [];
if ($operatorId) {
    $totalSql .= " WHERE operator_id = ?";
    $totalParams[] = $operatorId;
}
$totalStmt = $pdo->prepare($totalSql);
$totalStmt->execute($totalParams);
$totalSales = $totalStmt->fetch()['total'] ?? 0;

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

include __DIR__ . '/../includes/navbar.php';
?>

<div class="top-bar">
    <div class="page-title">
        <h1>Sales</h1>
        <p>Track all sales</p>
    </div>
    <div class="top-bar-actions">
        <button class="btn btn-primary btn-sm" onclick="showAddSaleModal()">
            <i class="fas fa-plus"></i> Add Sale
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
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo formatCurrency($totalSales); ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($sales); ?></div>
            <div class="stat-label">Total Transactions</div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                        <th>Customer</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><?php echo formatDate($sale['sale_date']); ?></td>
                        <td><?php echo ucfirst($sale['product_type']); ?></td>
                        <td><?php echo number_format($sale['quantity']); ?></td>
                        <td><?php echo formatCurrency($sale['unit_price']); ?></td>
                        <td><strong><?php echo formatCurrency($sale['total_amount']); ?></strong></td>
                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? '-'); ?></td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteSale(<?php echo $sale['sale_id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #888; padding: 30px;">
                            <i class="fas fa-dollar-sign" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                            No sales recorded yet.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Sale Modal -->
<div id="addSaleModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Sale</h3>
            <button class="modal-close" onclick="closeModal('addSaleModal')">&times;</button>
        </div>
        <form method="POST" action="sale_actions.php">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label for="sale_date">Sale Date *</label>
                    <input type="date" id="sale_date" name="sale_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="product_type">Product Type *</label>
                    <select id="product_type" name="product_type" class="form-control" required>
                        <option value="eggs">Eggs</option>
                        <option value="meat">Meat</option>
                        <option value="live_birds">Live Birds</option>
                        <option value="manure">Manure</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" class="form-control" required min="1" step="0.01">
                </div>
                <div class="form-group">
                    <label for="unit">Unit *</label>
                    <select id="unit" name="unit" class="form-control" required>
                        <option value="pieces">Pieces</option>
                        <option value="kg">Kilograms (kg)</option>
                        <option value="bird">Bird</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="unit_price">Unit Price (UGX) *</label>
                    <input type="number" id="unit_price" name="unit_price" class="form-control" required min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="customer_name">Customer Name</label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="Optional">
                </div>
            </div>
            <div class="form-group">
                <label for="sale_note">Note</label>
                <textarea id="sale_note" name="note" class="form-control" rows="2" placeholder="Optional note"></textarea>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Sale
                </button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addSaleModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddSaleModal() {
    document.getElementById('addSaleModal').style.display = 'flex';
    document.getElementById('sale_date').value = new Date().toISOString().split('T')[0];
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function deleteSale(id) {
    if (confirm('Are you sure you want to delete this sale?')) {
        window.location.href = 'sale_actions.php?action=delete&id=' + id;
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>