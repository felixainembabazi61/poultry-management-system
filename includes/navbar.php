<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-dove"></i>
                    <h2>PoultrySys</h2>
                </div>
                <div class="user-info">
                    <div class="avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                        <span class="user-role"><?php echo ucfirst($_SESSION['role'] ?? 'Guest'); ?></span>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="<?php echo APP_URL; ?>/pages/dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'flock.php' ? 'active' : ''; ?>">
                        <a href="<?php echo APP_URL; ?>/pages/flock.php">
                            <i class="fas fa-egg"></i>
                            <span>Flock Management</span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'flock-updates.php' ? 'active' : ''; ?>">
                        <a href="<?php echo APP_URL; ?>/pages/flock-updates.php">
                            <i class="fas fa-exchange-alt"></i>
                            <span>Flock Updates</span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'production.php' ? 'active' : ''; ?>">
                        <a href="<?php echo APP_URL; ?>/pages/production.php">
                            <i class="fas fa-boxes"></i>
                            <span>Daily Production</span>
                        </a>
                    </li>
                    <li class="<?php echo $current_page == 'expenses.php' ? 'active' : ''; ?>">
                        <a href="<?php echo APP_URL; ?>/pages/expenses.php">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Expenses</span>
                        </a>
                    </li>
		   <li class="<?php echo $current_page == 'sales.php' ? 'active' : ''; ?>">
    			<a href="<?php echo APP_URL; ?>/pages/sales.php">
        		    <i class="fas fa-dollar-sign"></i>
        		    <span>Sales</span>
    			</a>
		    </li>
<li class="<?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">
    <a href="<?php echo APP_URL; ?>/pages/inventory.php">
        <i class="fas fa-boxes"></i>
        <span>Inventory</span>
    </a>
</li>
                    <li class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                        <a href="<?php echo APP_URL; ?>/pages/reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                        <a href="<?php echo APP_URL; ?>/pages/users.php">
                            <i class="fas fa-users-cog"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <a href="<?php echo APP_URL; ?>/pages/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">