<?php
// update_location.php — a driver broadcasts their live GPS to their active deliveries.
session_start();
require_once 'includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Driver') {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit();
}

$uid = (int) $_SESSION['user_id'];
$lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float) $_POST['lng'] : null;

if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid coordinates']);
    exit();
}

// Update every order this driver is currently delivering
$stmt = mysqli_prepare($conn,
    "UPDATE orders SET driver_lat = ?, driver_lng = ?, location_updated = NOW()
     WHERE driver_id = ? AND order_status = 'In-Transit'");
mysqli_stmt_bind_param($stmt, "ddi", $lat, $lng, $uid);
mysqli_stmt_execute($stmt);

echo json_encode(['ok' => true, 'updated' => mysqli_stmt_affected_rows($stmt)]);
