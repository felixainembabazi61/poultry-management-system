<?php
// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=poultry_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to create user
function createUser($pdo, $username, $password, $email, $fullName, $role = 'staff') {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        echo "⚠️ User '$username' already exists!<br>";
        return false;
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO user (username, email, password_hash, full_name, role, is_active) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$username, $email, $passwordHash, $fullName, $role]);
    
    $userId = $pdo->lastInsertId();
    
    // If operator, create operator record
    if ($role === 'operator') {
        $stmt = $pdo->prepare("
            INSERT INTO operator (user_id, farm_name, location) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $fullName . "'s Farm", 'Kampala, Uganda']);
    }
    
    echo "✅ User '$username' created successfully!<br>";
    echo "🔑 Password: $password<br>";
    return true;
}

echo "<h1>User Creation Tool</h1>";

// Create users
echo "<h3>Creating Users...</h3>";

// Create Admin
createUser($pdo, 'admin', 'Admin@123', 'admin@poultry.com', 'System Administrator', 'admin');

// Create Staff
createUser($pdo, 'staff', 'staff123', 'staff@poultry.com', 'Staff User', 'staff');

// Create Operator
createUser($pdo, 'operator', 'op123', 'operator@poultry.com', 'Farm Operator', 'operator');

// Create your own account - CHANGE THESE!
createUser($pdo, 'your_username', 'your_password', 'your_email@email.com', 'Your Full Name', 'staff');

echo "<hr>";
echo "<h3>Login Credentials:</h3>";
echo "<ul>";
echo "<li><strong>Admin:</strong> username: admin, password: Admin@123</li>";
echo "<li><strong>Staff:</strong> username: staff, password: staff123</li>";
echo "<li><strong>Operator:</strong> username: operator, password: op123</li>";
echo "<li><strong>YOUR ACCOUNT:</strong> username: your_username, password: your_password (change these!)</li>";
echo "</ul>";

echo "<p><a href='pages/login.php'>Go to Login Page</a></p>";
?>