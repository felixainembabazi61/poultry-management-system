<?php
require_once __DIR__ . '/config.php';

function loginUser($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        // Update last login
        $updateStmt = $pdo->prepare("UPDATE user SET last_login = NOW() WHERE user_id = ?");
        $updateStmt->execute([$user['user_id']]);
        
        // Log login activity
        logActivity($user['user_id'], 'login', 'user', $user['user_id'], 'User logged in');
        
        return true;
    }
    return false;
}

function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
    }
    session_destroy();
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/pages/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role && $_SESSION['role'] !== 'admin') {
        header('Location: ' . APP_URL . '/pages/dashboard.php?error=unauthorized');
        exit;
    }
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM user WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function logActivity($userId, $action, $tableName, $recordId, $details) {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, details, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $action, $tableName, $recordId, $details, $ip]);
}

function getUserOperatorId($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT operator_id FROM operator WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result ? $result['operator_id'] : null;
}

function formatCurrency($amount) {
    return 'UGX ' . number_format($amount, 0);
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}
?>