<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Redirect to landing page with login tab active
header('Location: ../index.php?login=1');
exit;
?>