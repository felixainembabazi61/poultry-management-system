<?php
// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=poultry_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get all users
$users = $pdo->query("SELECT user_id, username, full_name, role FROM user ORDER BY user_id")->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_POST['user_id'];
    $farmName = trim($_POST['farm_name']);
    $location = trim($_POST['location']);
    $phone = trim($_POST['phone']);
    
    if ($userId <= 0 || empty($farmName)) {
        $error = "Please select a user and enter farm name";
    } else {
        // Check if operator already exists
        $check = $pdo->prepare("SELECT * FROM operator WHERE user_id = ?");
        $check->execute([$userId]);
        if ($check->fetch()) {
            $error = "This user already has an operator profile";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO operator (user_id, farm_name, location, phone) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $farmName, $location, $phone]);
            $message = "✅ Operator profile created successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Operator Profile</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #2c3e50; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #555; }
        select, input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
        }
        select:focus, input:focus {
            outline: none;
            border-color: #3498db;
        }
        .btn {
            padding: 12px 30px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn:hover { background: #2980b9; }
        .message { 
            padding: 12px 16px; 
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th { background: #f8f9fa; }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            background: #27ae60;
            color: white;
        }
        .badge.missing {
            background: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏠 Create Operator Profile</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="user_id">Select User *</label>
                <select id="user_id" name="user_id" required>
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>">
                            <?php echo htmlspecialchars($user['username']); ?> 
                            (<?php echo htmlspecialchars($user['full_name']); ?>)
                            - <?php echo $user['role']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="farm_name">Farm Name *</label>
                <input type="text" id="farm_name" name="farm_name" placeholder="e.g., Abbey Poultry Farm" required>
            </div>
            
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" placeholder="e.g., Kampala, Uganda">
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" placeholder="e.g., 0777123456">
            </div>
            
            <button type="submit" class="btn">Create Operator Profile</button>
        </form>
        
        <h3 style="margin-top: 30px;">Current Users & Operators</h3>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Operator Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): 
                    $check = $pdo->prepare("SELECT * FROM operator WHERE user_id = ?");
                    $check->execute([$user['user_id']]);
                    $hasOperator = $check->fetch();
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td><?php echo $user['role']; ?></td>
                    <td>
                        <?php if ($hasOperator): ?>
                            <span class="badge">✅ Has Operator</span>
                        <?php else: ?>
                            <span class="badge missing">❌ No Operator</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p style="margin-top: 20px;">
            <a href="index.php">Go to Homepage</a>
        </p>
    </div>
</body>
</html>