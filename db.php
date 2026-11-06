<?php
// Default XAMPP settings: MySQL user is "root" and password is blank.
// If you changed your MySQL password, update the password below.
$host = "localhost";
$user = "root";
$password = "";
$database = "qsystem_db";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8') .
        "<br>Check that MySQL is running in XAMPP and that you imported database.sql in phpMyAdmin.");
}
$conn->set_charset("utf8mb4");
?>
