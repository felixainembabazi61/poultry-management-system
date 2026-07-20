<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('admin');

// Get all users with their roles
$stmt = $pdo->query("
    SELECT u.*, 
           o.operator_id, o.farm_name,
           CASE 
               WHEN u.role = 'admin' THEN 'Administrator'
               WHEN u.role = 'staff' THEN 'Staff'
               WHEN u.role = 'operator' THEN 'Operator'
               ELSE u.role
           END as role_display
    FROM user u
    LEFT JOIN operator o ON u.user_id = o.user_id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

include __DIR__ . '/../includes/navbar.php';
?>

<div class="top-bar">
    <div class="page-title">
        <h1>User Management</h1>
        <p>Manage system users</p>
    </div>
    <div class="top-bar-actions">
        <button class="btn btn-primary btn-sm" onclick="showAddUserModal()">
            <i class="fas fa-user-plus"></i> Add User
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
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Farm</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'staff' ? 'info' : 'success'); ?>">
                                <?php echo $user['role_display']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($user['farm_name'] ?? '-'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-'; ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="editUser(<?php echo $user['user_id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-<?php echo $user['is_active'] ? 'warning' : 'success'; ?> btn-sm" 
                                    onclick="toggleUserStatus(<?php echo $user['user_id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)">
                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #888; padding: 30px;">
                            <i class="fas fa-users" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>
                            No users found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New User</h3>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="user_actions.php">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="role">Role *</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="admin">Administrator</option>
                        <option value="staff">Staff</option>
                        <option value="operator">Operator</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create User
                </button>
                <button type="button" class="btn btn-danger" onclick="closeModal('addUserModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddUserModal() {
    document.getElementById('addUserModal').style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function editUser(id) {
    alert('Edit user ID: ' + id + ' (Implement in production)');
}

function toggleUserStatus(id, status) {
    if (confirm('Are you sure you want to ' + (status ? 'activate' : 'deactivate') + ' this user?')) {
        window.location.href = 'user_actions.php?action=toggle_status&id=' + id + '&status=' + status;
    }
}

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        window.location.href = 'user_actions.php?action=delete&id=' + id;
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>