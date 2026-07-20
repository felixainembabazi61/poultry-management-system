<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if (empty($action)) {
    header('Location: sales.php');
    exit;
}

// Function to get operator_id for admin or regular user
function getOperatorIdForUser($pdo) {
    if ($_SESSION['role'] === 'admin') {
        // Admin: use first available operator or create one
        $stmt = $pdo->query("SELECT operator_id FROM operator LIMIT 1");
        $existingOperator = $stmt->fetch();
        if ($existingOperator) {
            return $existingOperator['operator_id'];
        } else {
            // Create a default operator for admin
            $stmt = $pdo->prepare("INSERT INTO operator (user_id, farm_name, location) VALUES (?, 'Default Farm', 'Kampala, Uganda')");
            $stmt->execute([$_SESSION['user_id']]);
            return $pdo->lastInsertId();
        }
    } else {
        // Staff/Operator must have their own operator profile
        $operatorId = getUserOperatorId($_SESSION['user_id']);
        if (!$operatorId) {
            header('Location: sales.php?error=No operator profile found. Please contact administrator.');
            exit;
        }
        return $operatorId;
    }
}

// Function to check and update inventory
function checkAndDeductInventory($pdo, $operatorId, $productType, $quantity, $unit) {
    // Map product types
    $productMap = [
        'eggs' => 'eggs',
        'meat' => 'meat',
        'live_birds' => 'live_birds',
        'manure' => 'manure'
    ];
    
    $inventoryType = $productMap[$productType] ?? null;
    if (!$inventoryType) {
        return ['success' => false, 'message' => 'Unknown product type'];
    }
    
    // Check current stock
    $stmt = $pdo->prepare("SELECT current_stock, unit FROM inventory WHERE operator_id = ? AND product_type = ?");
    $stmt->execute([$operatorId, $inventoryType]);
    $inventory = $stmt->fetch();
    
    if (!$inventory) {
        // No inventory record exists, create one with zero stock
        $stmt = $pdo->prepare("
            INSERT INTO inventory (operator_id, product_type, current_stock, unit) 
            VALUES (?, ?, 0, ?)
        ");
        $stmt->execute([$operatorId, $inventoryType, $unit]);
        $inventory = ['current_stock' => 0, 'unit' => $unit];
    }
    
    // Check if enough stock
    if ($inventory['current_stock'] < $quantity) {
        return [
            'success' => false, 
            'message' => "Insufficient stock! Available: {$inventory['current_stock']} {$inventory['unit']}, Requested: $quantity $unit"
        ];
    }
    
    // Deduct from inventory
    $stmt = $pdo->prepare("
        UPDATE inventory 
        SET current_stock = current_stock - ?, unit = ? 
        WHERE operator_id = ? AND product_type = ?
    ");
    $stmt->execute([$quantity, $unit, $operatorId, $inventoryType]);
    
    return ['success' => true, 'message' => 'Stock deducted successfully'];
}

if ($action === 'add') {
    // Get operator_id
    $operatorId = getOperatorIdForUser($pdo);
    
    $saleDate = $_POST['sale_date'] ?? '';
    $productType = $_POST['product_type'] ?? '';
    $quantity = (float)($_POST['quantity'] ?? 0);
    $unit = $_POST['unit'] ?? '';
    $unitPrice = (float)($_POST['unit_price'] ?? 0);
    $customerName = trim($_POST['customer_name'] ?? '');
    $note = trim($_POST['note'] ?? '');
    
    // Validate inputs
    if (empty($saleDate) || empty($productType) || $quantity <= 0 || $unitPrice <= 0) {
        header('Location: sales.php?error=Please fill in all required fields');
        exit;
    }
    
    // Check inventory before processing sale
    $inventoryCheck = checkAndDeductInventory($pdo, $operatorId, $productType, $quantity, $unit);
    if (!$inventoryCheck['success']) {
        header('Location: sales.php?error=' . urlencode($inventoryCheck['message']));
        exit;
    }
    
    // Calculate total amount
    $totalAmount = $quantity * $unitPrice;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert sale
        $stmt = $pdo->prepare("
            INSERT INTO sale (operator_id, sale_date, product_type, quantity, unit, unit_price, total_amount, customer_name, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $operatorId, 
            $saleDate, 
            $productType, 
            $quantity, 
            $unit, 
            $unitPrice, 
            $totalAmount, 
            $customerName, 
            $note
        ]);
        
        $saleId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'add_sale', 'sale', $saleId, 
            "Added sale: $quantity $unit of $productType for " . formatCurrency($totalAmount));
        
        header('Location: sales.php?success=Sale recorded successfully! Stock updated.');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: sales.php?error=Database error: ' . $e->getMessage());
        exit;
    }
} elseif ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        header('Location: sales.php?error=Invalid sale ID');
        exit;
    }
    
    try {
        // Get sale details to reverse inventory
        $stmt = $pdo->prepare("SELECT operator_id, product_type, quantity, unit FROM sale WHERE sale_id = ?");
        $stmt->execute([$id]);
        $sale = $stmt->fetch();
        
        if ($sale) {
            // Reverse inventory deduction
            $productMap = [
                'eggs' => 'eggs',
                'meat' => 'meat',
                'live_birds' => 'live_birds',
                'manure' => 'manure'
            ];
            $inventoryType = $productMap[$sale['product_type']] ?? null;
            
            if ($inventoryType) {
                // Add back to inventory
                $stmt2 = $pdo->prepare("
                    UPDATE inventory 
                    SET current_stock = current_stock + ? 
                    WHERE operator_id = ? AND product_type = ?
                ");
                $stmt2->execute([$sale['quantity'], $sale['operator_id'], $inventoryType]);
            }
        }
        
        // Delete sale
        $stmt = $pdo->prepare("DELETE FROM sale WHERE sale_id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_sale', 'sale', $id, "Deleted sale ID: $id");
        
        header('Location: sales.php?success=Sale deleted successfully! Stock restored.');
        exit;
    } catch (PDOException $e) {
        header('Location: sales.php?error=Database error: ' . $e->getMessage());
        exit;
    }
} else {
    header('Location: sales.php');
    exit;
}
?>