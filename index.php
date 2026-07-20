<?php
// Start session to check if user is already logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=poultry_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error = '';
$success = '';
$showLogin = isset($_GET['login']) ? true : false;
$showRegister = isset($_GET['register']) ? true : false;

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
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
            
            header('Location: pages/dashboard.php');
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    
    // Validate
    if (empty($username) || empty($password) || empty($email) || empty($fullName)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or email already exists.";
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
            
            $success = "Account created successfully! Please login.";
            $showLogin = true;
            $showRegister = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poultry Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .landing-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-wrap: wrap;
            background: white;
            border-radius: 20px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.3);
            overflow: hidden;
            min-height: 600px;
        }

        /* Left Side - Branding with Image */
        .branding {
            flex: 1;
            min-width: 300px;
            padding: 50px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            /* Minimal gradient overlay for readability */
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.7) 0%, rgba(118, 75, 162, 0.7) 100%);
        }

        /* Background image - MORE PROMINENT */
        .branding::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/poultry-bg.jpg') center/cover no-repeat;
            opacity: 0.9;
            z-index: 0;
            /* Add a subtle animation */
            animation: zoomIn 20s ease-in-out infinite alternate;
        }

        @keyframes zoomIn {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }

        /* Minimal dark overlay - REDUCED for better image visibility */
        .branding::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 0;
        }

        .branding > * {
            position: relative;
            z-index: 1;
        }

        /* Glass effect for text container */
        .branding-content {
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 30px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .branding .logo-image {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.5);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: white;
            padding: 5px;
            /* Add a subtle glow */
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.2);
        }

        /* Fallback if no image - show icon */
        .branding .logo-icon {
            font-size: 80px;
            margin-bottom: 20px;
            background: rgba(255,255,255,0.15);
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid rgba(255,255,255,0.3);
        }

        .branding h1 {
            font-size: 36px;
            margin-bottom: 15px;
            font-weight: 700;
            color: #ffffff;
            text-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }

        .branding p {
            font-size: 16px;
            opacity: 0.95;
            line-height: 1.8;
            margin-bottom: 30px;
            color: #ffffff;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }

        .branding .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .branding .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #ffffff;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 12px;
            border-radius: 8px;
            backdrop-filter: blur(4px);
            transition: all 0.3s ease;
        }

        .branding .feature-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.02);
        }

        .branding .feature-item i {
            font-size: 18px;
            width: 30px;
            height: 30px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        /* Right Side - Forms */
        .forms-container {
            flex: 1;
            min-width: 300px;
            padding: 50px 40px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .forms-container .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .forms-container .tab-btn {
            flex: 1;
            padding: 12px;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            color: #888;
            transition: all 0.3s;
            border-radius: 8px;
        }

        .forms-container .tab-btn:hover {
            background: #f5f5f5;
        }

        .forms-container .tab-btn.active {
            color: #667eea;
            background: #f0f0ff;
        }

        .forms-container .tab-btn i {
            margin-right: 8px;
        }

        .form-container {
            display: none;
        }

        .form-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #fafafa;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            background: #667eea;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            background: #5a67d8;
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: #2c3e50;
        }

        .btn-secondary:hover {
            background: #1a252f;
            box-shadow: 0 10px 25px rgba(44, 62, 80, 0.3);
        }

        .message {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.error {
            background: #fee;
            color: #c0392b;
            border: 1px solid #fcc;
        }

        .message.success {
            background: #e8f5e9;
            color: #27ae60;
            border: 1px solid #c8e6c9;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #888;
        }

        .form-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .password-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .landing-container {
                flex-direction: column;
                border-radius: 15px;
            }

            .branding {
                padding: 30px 25px;
                text-align: center;
            }

            .branding .features {
                grid-template-columns: 1fr;
            }

            .branding .feature-item {
                justify-content: center;
            }

            .forms-container {
                padding: 30px 25px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .forms-container .tabs {
                flex-direction: column;
            }
            
            .branding .logo-image,
            .branding .logo-icon {
                margin: 0 auto 20px auto;
            }

            .branding-content {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .branding {
                padding: 20px;
            }

            .branding h1 {
                font-size: 28px;
            }

            .forms-container {
                padding: 20px;
            }

            .form-group input,
            .form-group select {
                padding: 10px 12px;
            }

            .btn {
                padding: 12px;
            }

            .branding-content {
                padding: 15px;
            }
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #5a67d8;
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <!-- Left Side - Branding with Image -->
        <div class="branding">
            <div class="branding-content">
                <!-- Option A: Use an image file -->
                <img src="assets/images/logo.png" alt="Poultry Management System" class="logo-image" 
                     onerror="this.style.display='none'; document.getElementById('fallbackIcon').style.display='flex';">
                
                <!-- Option B: Fallback icon if image doesn't load -->
                <div id="fallbackIcon" class="logo-icon" style="display: none;">
                    🐔
                </div>
                
                <h1>Poultry Management System</h1>
                <p>Streamline your poultry farm operations with our comprehensive management solution. Track flocks, production, expenses, and more all in one place.</p>
                <div class="features">
                    <div class="feature-item">
                        <i class="fas fa-egg"></i>
                        <span>Flock Management</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Production Tracking</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Expense Management</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Reports & Analytics</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>User Management</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Access</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Forms -->
        <div class="forms-container">
            <?php if ($error): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn <?php echo !$showRegister ? 'active' : ''; ?>" onclick="switchTab('login')" id="loginTab">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                <button class="tab-btn <?php echo $showRegister ? 'active' : ''; ?>" onclick="switchTab('register')" id="registerTab">
                    <i class="fas fa-user-plus"></i> Sign Up
                </button>
            </div>

            <!-- Login Form -->
            <div class="form-container <?php echo !$showRegister ? 'active' : ''; ?>" id="loginForm">
                <h2 style="margin-bottom: 10px; color: #333;">Welcome Back</h2>
                <p style="color: #888; margin-bottom: 25px;">Login to your account to continue</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="login_username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="login_username" name="username" placeholder="Enter your username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="login_password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" id="login_password" name="password" placeholder="Enter your password" required>
                    </div>
                    
                    <button type="submit" name="login" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="form-footer">
                    Don't have an account? <a href="#" onclick="switchTab('register')">Sign Up</a>
                </div>
                
                <div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px; font-size: 12px; color: #666; text-align: center;">
                    <strong>Demo Accounts:</strong><br>
                    Admin: admin / Admin@123 &nbsp;|&nbsp; Staff: staff / staff123
                </div>
            </div>

            <!-- Registration Form -->
            <div class="form-container <?php echo $showRegister ? 'active' : ''; ?>" id="registerForm">
                <h2 style="margin-bottom: 10px; color: #333;">Create Account</h2>
                <p style="color: #888; margin-bottom: 25px;">Join Poultry Management System today</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="reg_full_name"><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" id="reg_full_name" name="full_name" placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg_username"><i class="fas fa-user-tag"></i> Username *</label>
                            <input type="text" id="reg_username" name="username" placeholder="Choose a username" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_email"><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" id="reg_email" name="email" placeholder="Enter your email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg_password"><i class="fas fa-lock"></i> Password *</label>
                            <input type="password" id="reg_password" name="password" placeholder="Min 6 characters" required minlength="6">
                            <div class="password-hint">Password must be at least 6 characters</div>
                        </div>
                        <div class="form-group">
                            <label for="reg_confirm_password"><i class="fas fa-check-circle"></i> Confirm Password *</label>
                            <input type="password" id="reg_confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_role"><i class="fas fa-user-tag"></i> Role</label>
                        <select id="reg_role" name="role">
                            <option value="staff">Staff</option>
                            <option value="admin">Administrator</option>
                            <option value="operator">Farm Operator</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="register" class="btn btn-secondary">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="form-footer">
                    Already have an account? <a href="#" onclick="switchTab('login')">Login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            const loginTab = document.getElementById('loginTab');
            const registerTab = document.getElementById('registerTab');
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            
            loginTab.classList.remove('active');
            registerTab.classList.remove('active');
            loginForm.classList.remove('active');
            registerForm.classList.remove('active');
            
            if (tab === 'login') {
                loginTab.classList.add('active');
                loginForm.classList.add('active');
                const url = new URL(window.location);
                url.searchParams.delete('register');
                url.searchParams.set('login', '1');
                window.history.pushState({}, '', url);
            } else {
                registerTab.classList.add('active');
                registerForm.classList.add('active');
                const url = new URL(window.location);
                url.searchParams.delete('login');
                url.searchParams.set('register', '1');
                window.history.pushState({}, '', url);
            }
        }

        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('register')) {
                switchTab('register');
            } else if (urlParams.get('login')) {
                switchTab('login');
            }
        };

        // Password matching validation
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('reg_password');
            const confirmPassword = document.getElementById('reg_confirm_password');
            
            if (password && confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.style.borderColor = '#e74c3c';
                    } else {
                        confirmPassword.style.borderColor = '#27ae60';
                    }
                });
                
                password.addEventListener('input', function() {
                    if (password.value.length > 0 && password.value.length < 6) {
                        password.style.borderColor = '#f39c12';
                    } else if (password.value.length >= 6) {
                        password.style.borderColor = '#27ae60';
                    }
                });
            }
        });
    </script>
</body>
</html>