<?php
session_start();

if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'rw'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Send them back to the exact page they were just on
$previous_page = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
header("Location: " . $previous_page);
exit();
?>