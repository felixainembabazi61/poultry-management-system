<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// Get action from POST or GET
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// If no action, redirect back
if (empty($action)) {
    header('Location: flock.php');
    exit;
}

// Handle Add Flock
if ($action === 'add') {
    // Check if user is admin or has operator profile
    $operatorId = null;
    
    if ($_SESSION['role'] === 'admin') {
        // Admin can add flocks without operator profile
        // Use the first available operator or create a default one
        $stmt = $pdo->query("SELECT operator_id FROM operator LIMIT 1");
        $existingOperator = $stmt->fetch();
        
        if ($existingOperator) {
            $operatorId = $existingOperator['operator_id'];
        } else {
            // Create a default operator for admin
            $stmt = $pdo->prepare("INSERT INTO operator (user_id, farm_name, location) VALUES (?, 'Default Farm', 'Kampala, Uganda')");
            $stmt->execute([$_SESSION['user_id']]);
            $operatorId = $pdo->lastInsertId();
        }
    } else {
        // Staff/Operator must have their own operator profile
        $operatorId = getUserOperatorId($_SESSION['user_id']);
        if (!$operatorId) {
            header('Location: flock.php?error=No operator profile found. Please contact administrator.');
            exit;
        }
    }
    
    $name = trim($_POST['name'] ?? '');
    $poultryType = $_POST['poultry_type'] ?? '';
    $breed = trim($_POST['breed'] ?? '');
    $initialQuantity = (int)($_POST['initial_quantity'] ?? 0);
    $arrivalDate = $_POST['arrival_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($name) || empty($poultryType) || empty($breed) || $initialQuantity <= 0 || empty($arrivalDate)) {
        header('Location: flock.php?error=Please fill in all required fields');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO flock (operator_id, name, poultry_type, breed, initial_quantity, current_quantity, arrival_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$operatorId, $name, $poultryType, $breed, $initialQuantity, $initialQuantity, $arrivalDate, $notes]);
        
        $flockId = $pdo->lastInsertId();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'create_flock', 'flock', $flockId, "Created flock: $name");
        
        header('Location: flock.php?success=Flock added successfully');
        exit;
    } catch (PDOException $e) {
        header('Location: flock.php?error=Database error: ' . $e->getMessage());
        exit;
    }
}

// Handle Delete Flock
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        header('Location: flock.php?error=Invalid flock ID');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM flock WHERE flock_id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_flock', 'flock', $id, "Deleted flock ID: $id");
        
        header('Location: flock.php?success=Flock deleted successfully');
        exit;
    } catch (PDOException $e) {
        header('Location: flock.php?error=Database error: ' . $e->getMessage());
        exit;
    }
}

// Handle Add Flock Update
if ($action === 'add_update') {
    $flockId = (int)($_POST['flock_id'] ?? 0);
    $updateDate = $_POST['update_date'] ?? '';
    $actionType = $_POST['action_type'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    
    if ($flockId <= 0 || empty($updateDate) || empty($actionType) || $quantity <= 0) {
        header('Location: flock-updates.php?error=Please fill in all required fields');
        exit;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert update
        $stmt = $pdo->prepare("
            INSERT INTO flock_update (flock_id, update_date, action_type, quantity, note)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$flockId, $updateDate, $actionType, $quantity, $note]);
        
        // Update flock current quantity
        if ($actionType === 'added') {
            $sql = "UPDATE flock SET current_quantity = current_quantity + ? WHERE flock_id = ?";
        } else {
            $sql = "UPDATE flock SET current_quantity = current_quantity - ? WHERE flock_id = ?";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$quantity, $flockId]);
        
        // Check if flock should be marked as completed or depleted
        $checkStmt = $pdo->prepare("SELECT current_quantity FROM flock WHERE flock_id = ?");
        $checkStmt->execute([$flockId]);
        $currentQty = $checkStmt->fetch()['current_quantity'];
        
        if ($currentQty <= 0) {
            $stmt = $pdo->prepare("UPDATE flock SET status = 'depleted' WHERE flock_id = ?");
            $stmt->execute([$flockId]);
        }
        
        $pdo->commit();
        
        logActivity($_SESSION['user_id'], 'add_flock_update', 'flock_update', $pdo->lastInsertId(), 
            "Added $actionType update for flock $flockId: $quantity birds");
        
        header('Location: flock-updates.php?success=Update added successfully');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: flock-updates.php?error=Database error: ' . $e->getMessage());
        exit;
    }
}

// Handle Delete Flock Update
if ($action === 'delete_update') {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        header('Location: flock-updates.php?error=Invalid update ID');
        exit;
    }
    
    try {
        // Get the update details to reverse the quantity change
        $stmt = $pdo->prepare("SELECT flock_id, action_type, quantity FROM flock_update WHERE update_id = ?");
        $stmt->execute([$id]);
        $update = $stmt->fetch();
        
        if ($update) {
            $pdo->beginTransaction();
            
            // Reverse the quantity change
            if ($update['action_type'] === 'added') {
                $sql = "UPDATE flock SET current_quantity = current_quantity - ? WHERE flock_id = ?";
            } else {
                $sql = "UPDATE flock SET current_quantity = current_quantity + ? WHERE flock_id = ?";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$update['quantity'], $update['flock_id']]);
            
            // Delete the update
            $stmt = $pdo->prepare("DELETE FROM flock_update WHERE update_id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
        }
        
        logActivity($_SESSION['user_id'], 'delete_flock_update', 'flock_update', $id, "Deleted update ID: $id");
        
        header('Location: flock-updates.php?success=Update deleted successfully');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: flock-updates.php?error=Database error: ' . $e->getMessage());
        exit;
    }
}

// If we get here, no valid action was found
header('Location: flock.php');
exit;
?>