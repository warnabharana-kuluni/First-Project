<?php
// ============================================================
// GET TABLES - AJAX Endpoint for Floor Plan Updates
// ============================================================
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/table_functions.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$time = isset($_POST['time']) ? $_POST['time'] : date('H:i');

// Validate date/time
if (!strtotime($date) || !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date or time']);
    exit();
}

$tables = getTablesWithStatus($conn, $date, $time);

echo json_encode([
    'success' => true,
    'tables' => $tables,
    'date' => $date,
    'time' => $time
]);
exit();
?>