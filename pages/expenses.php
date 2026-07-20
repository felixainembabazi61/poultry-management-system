<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$operatorId = null;
if ($_SESSION['role'] !== 'admin') {
    $operatorId = getUserOperatorId($_SESSION['user_id']);
}

// Get expenses with filters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$sql = "SELECT e.*, u.full_name as operator_name 
        FROM expense e 
        JOIN operator o ON e.operator_id = o.operator_id 
        JOIN user u ON o.user_id = u.user_id
        WHERE e.expense_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($operatorId) {
    $sql .= " AND e.operator_id = ?";
    $params[] = $operatorId;
}

if (!empty($category)) {
    $sql .= " AND e.category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY e.expense_date DESC, e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Get summary by category
$summarySql = "SELECT 
    category,
    SUM(amount) as total,
    COUNT(*) as count
FROM expense";
$summaryParams = [];

if ($operatorId) {
    $summarySql .= " WHERE operator_id = ?";
    $summaryParams[] = $operatorId;
}

$summarySql .= " GROUP BY category ORDER BY total DESC";

$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($summaryParams);
$categorySummary = $summaryStmt->fetchAll();

// Get total expenses
$totalSql = "SELECT SUM(amount) as total FROM expense";
$totalParams = [];
if ($operatorId) {
    $totalSql .= " WHERE operator_id = ?";
    $totalParams[] = $operatorId;
}
$totalStmt = $pdo->prepare($totalSql);
$totalStmt->execute($totalParams);
$totalExpenses = $totalStmt->fetch()['total'] ?? 0;

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

include __DIR__ . '/../includes/navbar.php';
?>

<div class="top-bar">
    <div class="page-title">
        <h1>Expenses</h1>
        <p>Track all farm expenses</p>
    </div>
    <div class="top-bar-actions">
        <button class="btn btn-primary btn-sm" onclick="showAddExpenseModal()">
            <i class="fas fa-plus"></i> Add Expense
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
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-value"><?php echo formatCurrency($totalExpenses); ?></div>
            <div class="stat-label">Total Expenses</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-list"></i></div>
            <div class="stat-value"><?php echo count($expenses); ?></div>
            <div class="stat-label">Total Records</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-value"><?php echo count($categorySummary); ?></div>
            <div class="stat-label">Categories</div>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="dashboard-card" style="margin-bottom: 20px;">
        <form method="GET" action="" class="filter-form">
            <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">All Categories</option>
                        <option value="feed" <?php echo $category === 'feed' ? 'selected' : ''; ?>>Feed</option>
                        <option value="medication" <?php echo $category === 'medication' ? 'selected' : ''; ?>>Medication</option>
                        <option value="utilities" <?php echo $category === 'utilities' ? 'selected' : ''; ?>>Utilities</option>
                        <option value="equipment" <?php echo $category === 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                        <option value="transport" <?php echo $category === 'transport' ? 'selected' : ''; ?>>Transport</option>
                        <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Other</option>
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
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="expenses.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
        <!-- Expenses Table -->
        <div class="dashboard-card">
            <h3>Expense Records</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo $expense['expense_id']; ?></td>
                            <td><?php echo formatDate($expense['expense_date']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $expense['category'] === 'feed' ? 'success' : ($expense['category'] === 'medication' ? 'info' : ($expense['category'] === 'utilities' ? 'warning' : 'secondary')); ?>">
                                    <?php echo ucfirst($expense['category']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                            <td><?php echo formatCurrency($expense['amount']); ?></td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="deleteExpense(<?php echo $expense['expense_id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #888; padding: 30px;">
                                <i class="fas fa-money-bill-wave" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                                No expenses found. Add your first expense.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Category Summary -->
        <div class="dashboard-card">
            <h3>Expenses by Category</h3>
            <div style="height: 300px;">
                <canvas id="expensePieChart"></canvas>
            </div>
            <div style="margin-top: 15px;">
                <?php foreach ($categorySummary as $cat): ?>
                <div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee;">
                    <span><?php echo ucfirst($cat['category']); ?></span>
                    <span><strong><?php echo formatCurrency($cat['total']); ?></strong> (<?php echo $cat['count']; ?> records)</span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($categorySummary)): ?>
                <p style="color: #888; text-align: center;">No expense data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div id="addExpenseModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Expense</h3>
            <button class="modal-close" onclick="closeModal('addExpenseModal')">&times;</button>
        </div>
        <form method="POST" action="expense_actions.php">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label for="expense_date">Expense Date *</label>
                    <input type="date" id="expense_date" name="expense_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="exp_category">Category *</label>
                    <select id="exp_category" name="category" class="form-control" required>
                        <option value="feed">Feed</option>
                        <option value="medication">Medication</option>
                        <option value="utilities">Utilities</option>
                        <option value="equipment">Equipment</option>
                        <option value="transport">Transport</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="description">Description *</label>
                <input type="text" id="description" name="description" class="form-control" required placeholder="What was the expense for?">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Amount (UGX) *</label>
                    <input type="number" id="amount" name="amount" class="form-control" required min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="exp_note">Note</label>
                    <textarea id="exp_note" name="note" class="form-control" rows="3" placeholder="Optional note"></textarea>
                </div>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Expense
                </button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addExpenseModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function showAddExpenseModal() {
    document.getElementById('addExpenseModal').style.display = 'flex';
    document.getElementById('expense_date').value = new Date().toISOString().split('T')[0];
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function deleteExpense(id) {
    if (confirm('Are you sure you want to delete this expense?')) {
        window.location.href = 'expense_actions.php?action=delete&id=' + id;
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Expense Chart
const ctx = document.getElementById('expensePieChart').getContext('2d');
const expenseData = <?php echo json_encode($categorySummary); ?>;

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: expenseData.map(d => d.category || 'Unknown'),
        datasets: [{
            data: expenseData.map(d => d.total || 0),
            backgroundColor: ['#3498db', '#27ae60', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c'],
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