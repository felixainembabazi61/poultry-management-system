<?php
// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=poultry_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to reset password
function resetPassword($pdo, $username, $newPassword) {
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE user SET password_hash = ? WHERE username = ?");
    $stmt->execute([$passwordHash, $username]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Password for '$username' has been reset to: $newPassword<br>";
        return true;
    } else {
        echo "❌ User '$username' not found!<br>";
        return false;
    }
}

echo "<h1>Password Reset Tool</h1>";

// Reset passwords
echo "<h3>Resetting Passwords...</h3>";
resetPassword($pdo, 'admin', 'Admin@123');
resetPassword($pdo, 'ssenkubuge', 'Admin@123');

// Create your own account if you want
echo "<hr>";

// Check if you want to create a new user
if (isset($_POST['create_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $fullName = $_POST['full_name'];
    $role = $_POST['role'];
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM user WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        echo "⚠️ User already exists!<br>";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO user (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$username, $email, $passwordHash, $fullName, $role]);
        echo "✅ User '$username' created successfully!<br>";
        echo "🔑 Password: $password<br>";
    }
}

echo "<h3>Current Users:</h3>";
$stmt = $pdo->query("SELECT user_id, username, email, full_name, role, is_active FROM user");
$users = $stmt->fetchAll();

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th></tr>";
foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . $user['user_id'] . "</td>";
    echo "<td>" . $user['username'] . "</td>";
    echo "<td>" . $user['email'] . "</td>";
    echo "<td>" . $user['full_name'] . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "<td>" . ($user['is_active'] ? '✅' : '❌') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>Create New User:</h3>";
?>
<form method="POST">
    <table>
        <tr>
            <td>Username:</td>
            <td><input type="text" name="username" required></td>
        </tr>
        <tr>
            <td>Password:</td>
            <td><input type="text" name="password" required></td>
        </tr>
        <tr>
            <td>Email:</td>
            <td><input type="email" name="email" required></td>
        </tr>
        <tr>
            <td>Full Name:</td>
            <td><input type="text" name="full_name" required></td>
        </tr>
        <tr>
            <td>Role:</td>
            <td>
                <select name="role">
                    <option value="admin">Admin</option>
                    <option value="staff" selected>Staff</option>
                    <option value="operator">Operator</option>
                </select>
            </td>
        </tr>
        <tr>
            <td colspan="2"><button type="submit" name="create_user">Create User</button></td>
        </tr>
    </table>
</form>

<p><a href="pages/login.php">Go to Login Page</a></p>