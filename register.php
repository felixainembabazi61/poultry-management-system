<?php
// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=poultry_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    $fullName = trim($_POST['full_name']);
    $role = $_POST['role'];
    
    // Validate
    if (empty($username) || empty($password) || empty($email) || empty($fullName)) {
        $error = "Please fill in all fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or email already exists";
        } else {
            // Create user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
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
            
            $message = "✅ Account created successfully! You can now login.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - Poultry Management System</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
        }
        .register-container h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }
        .register-container p.subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .role-select {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>🐔 Create Account</h1>
        <p class="subtitle">Register for Poultry Management System</p>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" required placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required placeholder="Choose a username">
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label for="password">Password * (min 6 characters)</label>
                <input type="password" id="password" name="password" required placeholder="Create a password">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
            </div>
            
            <div class="role-select">
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="staff">Staff</option>
                        <option value="admin">Administrator</option>
                        <option value="operator">Operator</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="pages/login.php">Login here</a>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 13px; color: #666;">
            <strong>📝 Default Accounts:</strong><br>
            • Admin: admin / Admin@123<br>
            • Staff: staff / staff123<br>
            • Operator: operator / op123
        </div>
    </div>
</body>
</html>