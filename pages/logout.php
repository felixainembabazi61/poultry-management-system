<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy session
session_destroy();

// Redirect to landing page
header('Location: ../index.php');
exit;
?>