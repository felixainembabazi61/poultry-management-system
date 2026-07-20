<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if (empty($action)) {
    header('Location: production.php');
    exit;
}

// Function to update inventory when production is added
function updateInventoryFromProduction($pdo, $operatorId, $productType, $quantity, $unit) {
    // Map product types to inventory product types
    $productMap = [
        'eggs' => 'eggs',
        'meat' => 'meat'
    ];
    
    $inventoryType = $productMap[$productType] ?? null;
    if (!$inventoryType) {
        return; // Only eggs and meat update inventory
    }
    
    // Check if inventory exists
    $check = $pdo->prepare("SELECT inventory_id FROM inventory WHERE operator_id = ? AND product_type = ?");
    $check->execute([$operatorId, $inventoryType]);
    
    if ($check->fetch()) {
        // Update existing inventory
        $stmt = $pdo->prepare("
            UPDATE inventory 
            SET current_stock = current_stock + ?, unit = ? 
            WHERE operator_id = ? AND product_type = ?
        ");
        $stmt->execute([$quantity, $unit, $operatorId, $inventoryType]);
    } else {
        // Create new inventory record
        $stmt = $pdo->prepare("
            INSERT INTO inventory (operator_id, product_type, current_stock, unit) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$operatorId, $inventoryType, $quantity, $unit]);
    }
}

if ($action === 'add') {
    // Get operator_id for the user
    $operatorId = null;
    
    if ($_SESSION['role'] === 'admin') {
        // Admin: get operator from the flock
        $flockId = (int)($_POST['flock_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT operator_id FROM flock WHERE flock_id = ?");
        $stmt->execute([$flockId]);
        $flock = $stmt->fetch();
        if ($flock) {
            $operatorId = $flock['operator_id'];
        } else {
            header('Location: production.php?error=Invalid flock selected');
            exit;
        }
    } else {
        $operatorId = getUserOperatorId($_SESSION['user_id']);
        if (!$operatorId) {
            header('Location: production.php?error=No operator profile found');
            exit;
        }
    }
    
    $flockId = (int)($_POST['flock_id'] ?? 0);
    $productionDate = $_POST['production_date'] ?? '';
    $productType = $_POST['product_type'] ?? '';
    $quantity = (float)($_POST['quantity'] ?? 0);
    $unit = $_POST['unit'] ?? '';
    $note = trim($_POST['note'] ?? '');
    
    // Verify flock exists
    $check = $pdo->prepare("SELECT flock_id FROM flock WHERE flock_id = ?");
    $check->execute([$flockId]);
    if (!$check->fetch()) {
        header('Location: production.php?error=Invalid flock selected');
        exit;
    }
    
    if ($flockId <= 0 || empty($productionDate) || empty($productType) || $quantity <= 0 || empty($unit)) {
        header('Location: production.php?error=Please fill in all required fields');
        exit;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert production record
        $stmt = $pdo->prepare("
            INSERT INTO production (flock_id, production_date, product_type, quantity, unit, note)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$flockId, $productionDate, $productType, $quantity, $unit, $note]);
        
        $productionId = $pdo->lastInsertId();
        
        // Update inventory for eggs and meat
        if ($productType === 'eggs' || $productType === 'meat') {
            updateInventoryFromProduction($pdo, $operatorId, $productType, $quantity, $unit);
        }
        
        $pdo->commit();
        
        logActivity($_SESSION['user_id'], 'add_production', 'production', $productionId, 
            "Added $quantity $unit of $productType");
        
        header('Location: production.php?success=Production recorded successfully & inventory updated!');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: production.php?error=Database error: ' . $e->getMessage());
        exit;
    }
} elseif ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        header('Location: production.php?error=Invalid production ID');
        exit;
    }
    
    try {
        // Get the production details to reverse inventory
        $stmt = $pdo->prepare("SELECT flock_id, product_type, quantity, unit FROM production WHERE production_id = ?");
        $stmt->execute([$id]);
        $production = $stmt->fetch();
        
        if ($production) {
            // Get operator_id from flock
            $stmt2 = $pdo->prepare("SELECT operator_id FROM flock WHERE flock_id = ?");
            $stmt2->execute([$production['flock_id']]);
            $flock = $stmt2->fetch();
            
            if ($flock) {
                // Reverse inventory for eggs and meat
                if ($production['product_type'] === 'eggs' || $production['product_type'] === 'meat') {
                    $productMap = [
                        'eggs' => 'eggs',
                        'meat' => 'meat'
                    ];
                    $inventoryType = $productMap[$production['product_type']];
                    
                    // Deduct from inventory
                    $stmt3 = $pdo->prepare("
                        UPDATE inventory 
                        SET current_stock = current_stock - ? 
                        WHERE operator_id = ? AND product_type = ?
                    ");
                    $stmt3->execute([$production['quantity'], $flock['operator_id'], $inventoryType]);
                }
            }
        }
        
        // Delete production
        $stmt = $pdo->prepare("DELETE FROM production WHERE production_id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_production', 'production', $id, "Deleted production ID: $id");
        
        header('Location: production.php?success=Production record deleted successfully');
        exit;
    } catch (PDOException $e) {
        header('Location: production.php?error=Database error: ' . $e->getMessage());
        exit;
    }
} else {
    header('Location: production.php');
    exit;
}
?>