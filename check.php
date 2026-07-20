<?php
echo "<h1>File Structure Check</h1>";
echo "<hr>";

$base = __DIR__;

$requiredFiles = [
    // Root files
    'index.php' => 'Root index.php',
    
    // Includes
    'includes/config.php' => 'Configuration file',
    'includes/auth.php' => 'Authentication functions',
    'includes/functions.php' => 'Helper functions',
    'includes/navbar.php' => 'Navigation bar',
    'includes/footer.php' => 'Footer',
    
    // Pages
    'pages/login.php' => 'Login page',
    'pages/logout.php' => 'Logout page',
    'pages/dashboard.php' => 'Dashboard page',
    'pages/flock.php' => 'Flock management',
    'pages/flock-updates.php' => 'Flock updates',
    'pages/production.php' => 'Production page',
    'pages/expenses.php' => 'Expenses page',
    'pages/reports.php' => 'Reports page',
    'pages/users.php' => 'User management',
    
    // CSS/JS
    'public/css/style.css' => 'CSS styles',
    'public/js/main.js' => 'JavaScript'
];

echo "<h3>Checking Required Files:</h3>";
echo "<ul>";

$allExist = true;
foreach ($requiredFiles as $file => $description) {
    $fullPath = $base . '/' . $file;
    if (file_exists($fullPath)) {
        echo "<li>✅ $description - <strong>$file</strong> - Found</li>";
    } else {
        echo "<li>❌ $description - <strong>$file</strong> - MISSING!</li>";
        $allExist = false;
    }
}
echo "</ul>";

echo "<hr>";

if ($allExist) {
    echo "<h3 style='color: green;'>✅ All files are present!</h3>";
} else {
    echo "<h3 style='color: red;'>❌ Some files are missing. Please create them.</h3>";
}

echo "<h3>Current Directory:</h3>";
echo "Base Path: " . $base . "<br>";

echo "<h3>Directory Structure:</h3>";
function showDir($dir, $indent = 0) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item != "." && $item != "..") {
            $path = $dir . "/" . $item;
            if (is_dir($path)) {
                echo str_repeat("&nbsp;&nbsp;&nbsp;", $indent) . "📁 <strong>$item/</strong><br>";
                showDir($path, $indent + 1);
            } else {
                echo str_repeat("&nbsp;&nbsp;&nbsp;", $indent) . "📄 $item<br>";
            }
        }
    }
}
showDir($base);
?>