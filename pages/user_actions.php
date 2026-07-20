<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('admin');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (empty($username) || empty($email) || empty($fullName) || empty($password)) {
        header('Location: users.php?error=Please fill in all required fields');
        exit;
    }
    
    if ($password !== $confirmPassword) {
        header('Location: users.php?error=Passwords do not match');
        exit;
    }
    
    if (strlen($password) < 6) {
        header('Location: users.php?error=Password must be at least 6 characters');
        exit;
    }
    
    // Check if username exists
    $checkStmt = $pdo->prepare("SELECT user_id FROM user WHERE username = ? OR email = ?");
    $checkStmt->execute([$username, $email]);
    if ($checkStmt->fetch()) {
        header('Location: users.php?error=Username or email already exists');
        exit;
    }
    
    try {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO user (username, email, password_hash, full_name, role, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$username, $email, $passwordHash, $fullName, $role]);
        
        $userId = $pdo->lastInsertId();
        
        // If operator, create operator record
        if ($role === 'operator') {
            $farmName = trim($_POST['farm_name'] ?? $fullName . "'s Farm");
            $location = trim($_POST['location'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            $opStmt = $pdo->prepare("
                INSERT INTO operator (user_id, farm_name, location, phone)
                VALUES (?, ?, ?, ?)
            ");
            $opStmt->execute([$userId, $farmName, $location, $phone]);
        }
        
        logActivity($_SESSION['user_id'], 'add_user', 'user', $userId, "Added user: $username ($role)");
        
        header('Location: users.php?success=User created successfully');
    } catch (PDOException $e) {
        header('Location: users.php?error=Database error: ' . $e->getMessage());
    }
} elseif ($action === 'toggle_status') {
    $id = (int)($_GET['id'] ?? 0);
    $status = (int)($_GET['status'] ?? 0);
    
    if ($id <= 0) {
        header('Location: users.php?error=Invalid user ID');
        exit;
    }
    
    if ($id == $_SESSION['user_id']) {
        header('Location: users.php?error=You cannot change your own status');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE user SET is_active = ? WHERE user_id = ?");
        $stmt->execute([$status, $id]);
        
        logActivity($_SESSION['user_id'], 'toggle_user_status', 'user', $id, 
            ($status ? 'Activated' : 'Deactivated') . " user ID: $id");
        
        header('Location: users.php?success=User status updated successfully');
    } catch (PDOException $e) {
        header('Location: users.php?error=Database error: ' . $e->getMessage());
    }
} elseif ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        header('Location: users.php?error=Invalid user ID');
        exit;
    }
    
    if ($id == $_SESSION['user_id']) {
        header('Location: users.php?error=You cannot delete your own account');
        exit;
    }
    
    try {
        // Delete operator record if exists
        $stmt = $pdo->prepare("DELETE FROM operator WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM user WHERE user_id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_user', 'user', $id, "Deleted user ID: $id");
        
        header('Location: users.php?success=User deleted successfully');
    } catch (PDOException $e) {
        header('Location: users.php?error=Database error: ' . $e->getMessage());
    }
} else {
    header('Location: users.php');
}
exit;
?>