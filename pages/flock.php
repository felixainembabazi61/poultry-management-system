<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$operatorId = null;
if ($_SESSION['role'] !== 'admin') {
    $operatorId = getUserOperatorId($_SESSION['user_id']);
}

$flocks = getFlockList($operatorId);
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

include __DIR__ . '/../includes/navbar.php';
?>

<div class="top-bar">
    <div class="page-title">
        <h1>Flock Management</h1>
        <p>Manage your poultry flocks</p>
    </div>
    <div class="top-bar-actions">
        <button class="btn btn-primary btn-sm" onclick="showAddFlockModal()">
            <i class="fas fa-plus"></i> Add New Flock
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
    
    <div class="dashboard-card">
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
                        <th>Arrival Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($flocks as $flock): ?>
                    <tr>
                        <td><?php echo $flock['flock_id']; ?></td>
                        <td><?php echo htmlspecialchars($flock['name']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $flock['poultry_type'])); ?></td>
                        <td><?php echo htmlspecialchars($flock['breed']); ?></td>
                        <td><?php echo number_format($flock['initial_quantity']); ?></td>
                        <td><?php echo number_format($flock['current_quantity']); ?></td>
                        <td><?php echo formatDate($flock['arrival_date']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $flock['status'] === 'active' ? 'success' : ($flock['status'] === 'completed' ? 'info' : 'danger'); ?>">
                                <?php echo ucfirst($flock['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="editFlock(<?php echo $flock['flock_id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteFlock(<?php echo $flock['flock_id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($flocks)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #888; padding: 30px;">
                            <i class="fas fa-egg" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                            No flocks found. Click "Add New Flock" to get started.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Flock Modal -->
<div id="addFlockModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Flock</h3>
            <button class="modal-close" onclick="closeModal('addFlockModal')">&times;</button>
        </div>
        <form method="POST" action="flock_actions.php">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Flock Name *</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="poultry_type">Poultry Type *</label>
                    <select id="poultry_type" name="poultry_type" class="form-control" required>
                        <option value="broiler">Broiler</option>
                        <option value="layer">Layer</option>
                        <option value="breeder">Breeder</option>
                        <option value="dual_purpose">Dual Purpose</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="breed">Breed *</label>
                    <input type="text" id="breed" name="breed" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="initial_quantity">Initial Quantity *</label>
                    <input type="number" id="initial_quantity" name="initial_quantity" class="form-control" required min="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="arrival_date">Arrival Date *</label>
                    <input type="date" id="arrival_date" name="arrival_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Flock
                </button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addFlockModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #888;
}

.modal-close:hover {
    color: #333;
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<script>
function showAddFlockModal() {
    document.getElementById('addFlockModal').style.display = 'flex';
    // Set default date to today
    document.getElementById('arrival_date').value = new Date().toISOString().split('T')[0];
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function editFlock(id) {
    // Implement edit functionality
    alert('Edit flock ID: ' + id + ' (Implement in production)');
}

function deleteFlock(id) {
    if (confirm('Are you sure you want to delete this flock?')) {
        window.location.href = 'flock_actions.php?action=delete&id=' + id;
    }
}

// Close modal on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>