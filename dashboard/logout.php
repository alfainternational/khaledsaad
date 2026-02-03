<?php
/**
 * Client Logout
 * تسجيل خروج العميل
 */
session_start();

// Clear client session
unset($_SESSION['client_id']);
unset($_SESSION['client_name']);
unset($_SESSION['client_email']);

// Redirect to login
header('Location: login.php');
exit;
