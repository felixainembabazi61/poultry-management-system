<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if (empty($action)) {
    header('Location: expenses.php');
    exit;
}

if ($action === 'add') {
    // Get operator_id - handle admin case
    $operatorId = null;
    
    if ($_SESSION['role'] === 'admin') {
        // Admin: use first available operator or create one
        $stmt = $pdo->query("SELECT operator_id FROM operator LIMIT 1");
        $existingOperator = $stmt->fetch();
        if ($existingOperator) {
            $operatorId = $existingOperator['operator_id'];
        } else {
            // Create a default operator
            $stmt = $pdo->prepare("INSERT INTO operator (user_id, farm_name, location) VALUES (?, 'Default Farm', 'Kampala')");
            $stmt->execute([$_SESSION['user_id']]);
            $operatorId = $pdo->lastInsertId();
        }
    } else {
        $operatorId = getUserOperatorId($_SESSION['user_id']);
        if (!$operatorId) {
            header('Location: expenses.php?error=No operator profile found');
            exit;
        }
    }
    
    $expenseDate = $_POST['expense_date'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    
    if (empty($expenseDate) || empty($category) || empty($description) || $amount <= 0) {
        header('Location: expenses.php?error=Please fill in all required fields');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO expense (operator_id, expense_date, category, description, amount, note)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$operatorId, $expenseDate, $category, $description, $amount, $note]);
        
        logActivity($_SESSION['user_id'], 'add_expense', 'expense', $pdo->lastInsertId(), 
            "Added expense: $description - $amount");
        
        header('Location: expenses.php?success=Expense added successfully');
        exit;
    } catch (PDOException $e) {
        header('Location: expenses.php?error=Database error: ' . $e->getMessage());
        exit;
    }
} elseif ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        header('Location: expenses.php?error=Invalid expense ID');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM expense WHERE expense_id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_expense', 'expense', $id, "Deleted expense ID: $id");
        
        header('Location: expenses.php?success=Expense deleted successfully');
        exit;
    } catch (PDOException $e) {
        header('Location: expenses.php?error=Database error: ' . $e->getMessage());
        exit;
    }
} else {
    header('Location: expenses.php');
    exit;
}
?>