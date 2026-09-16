<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear remember me cookies
setcookie('user_id', '', time() - 3600, '/');
setcookie('user_email', '', time() - 3600, '/');
setcookie('user_name', '', time() - 3600, '/');

// Redirect to home with success message
header('Location: index.php?logout=success');
exit;
?>