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

// Get updates based on filter
$flockId = isset($_GET['flock_id']) ? (int)$_GET['flock_id'] : 0;
$updates = getFlockUpdates($flockId, 50);

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

include __DIR__ . '/../includes/navbar.php';
?>

<div class="top-bar">
    <div class="page-title">
        <h1>Flock Updates</h1>
        <p>Track all changes to your flocks</p>
    </div>
    <div class="top-bar-actions">
        <button class="btn btn-primary btn-sm" onclick="showAddUpdateModal()">
            <i class="fas fa-plus"></i> Add Update
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
    
    <!-- Filter -->
    <div class="dashboard-card" style="margin-bottom: 20px;">
        <form method="GET" action="" class="filter-form">
            <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                    <label for="flock_id">Filter by Flock</label>
                    <select id="flock_id" name="flock_id" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Flocks</option>
                        <?php foreach ($flocks as $flock): ?>
                            <option value="<?php echo $flock['flock_id']; ?>" <?php echo $flockId == $flock['flock_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($flock['name']); ?> (<?php echo ucfirst($flock['poultry_type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="flock-updates.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Updates Table -->
    <div class="dashboard-card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Flock</th>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Quantity</th>
                        <th>Note</th>
                        <th>Recorded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($updates as $update): ?>
                    <tr>
                        <td><?php echo $update['update_id']; ?></td>
                        <td><?php echo htmlspecialchars($update['flock_name'] ?? 'N/A'); ?></td>
                        <td><?php echo formatDate($update['update_date']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $update['action_type'] === 'added' ? 'success' : ($update['action_type'] === 'removed' ? 'warning' : ($update['action_type'] === 'mortality' ? 'danger' : ($update['action_type'] === 'sold' ? 'info' : 'secondary'))); ?>">
                                <?php echo ucfirst($update['action_type']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($update['quantity']); ?></td>
                        <td><?php echo htmlspecialchars($update['note'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($update['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteUpdate(<?php echo $update['update_id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($updates)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: #888; padding: 30px;">
                            <i class="fas fa-exchange-alt" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                            No updates found. Add an update to track flock changes.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Update Modal -->
<div id="addUpdateModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Flock Update</h3>
            <button class="modal-close" onclick="closeModal('addUpdateModal')">&times;</button>
        </div>
        <form method="POST" action="flock_actions.php">
            <input type="hidden" name="action" value="add_update">
            <div class="form-group">
                <label for="update_flock_id">Select Flock *</label>
                <select id="update_flock_id" name="flock_id" class="form-control" required>
                    <option value="">-- Select Flock --</option>
                    <?php foreach ($flocks as $flock): ?>
                        <option value="<?php echo $flock['flock_id']; ?>">
                            <?php echo htmlspecialchars($flock['name']); ?> (Current: <?php echo number_format($flock['current_quantity']); ?> birds)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="update_date">Update Date *</label>
                    <input type="date" id="update_date" name="update_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="action_type">Action Type *</label>
                    <select id="action_type" name="action_type" class="form-control" required>
                        <option value="added">Added (New birds)</option>
                        <option value="removed">Removed (Transferred out)</option>
                        <option value="mortality">Mortality (Deaths)</option>
                        <option value="sold">Sold</option>
                        <option value="transferred">Transferred</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="update_quantity">Quantity *</label>
                    <input type="number" id="update_quantity" name="quantity" class="form-control" required min="1">
                </div>
                <div class="form-group">
                    <label for="update_note">Note</label>
                    <textarea id="update_note" name="note" class="form-control" rows="3" placeholder="Optional note about this update"></textarea>
                </div>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Update
                </button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addUpdateModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.filter-form .form-group {
    margin-bottom: 0;
}
</style>

<script>
function showAddUpdateModal() {
    document.getElementById('addUpdateModal').style.display = 'flex';
    document.getElementById('update_date').value = new Date().toISOString().split('T')[0];
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function deleteUpdate(id) {
    if (confirm('Are you sure you want to delete this update?')) {
        window.location.href = 'flock_actions.php?action=delete_update&id=' + id;
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>