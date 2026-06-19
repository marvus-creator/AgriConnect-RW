<?php
// includes/db.php

// This codebase checks `if (mysqli_query(...))` for failures (the classic style).
// PHP 8.1+ defaults mysqli to THROW exceptions instead of returning false, which
// would turn every handled DB error into an uncaught fatal. Restore the expected
// behaviour: queries return false on error so the existing checks work.
mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$user = "root";       // Default XAMPP username
$pass = "";           // Default XAMPP password is blank
$dbname = "agriconnect_rw";

// Create connection
$conn = mysqli_connect($host, $user, $pass, $dbname);

// Check connection
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Optional: Set charset to handle Rwandan names/characters properly
mysqli_set_charset($conn, "utf8mb4");
?>