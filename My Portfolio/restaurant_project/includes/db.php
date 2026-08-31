<?php
// ============================================================
// Database Configuration
// ============================================================

// BASE_URL define කරන්න - දැනටමත් define කර නැතිනම් පමණි
if (!defined('BASE_URL')) {
    define("BASE_URL", "/restaurant_project/");
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restaurant_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>