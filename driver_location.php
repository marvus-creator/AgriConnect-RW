<?php
// driver_location.php — buyer polls their order's live driver GPS position.
session_start();
require_once 'includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit();
}

$uid = (int) $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = mysqli_prepare($conn,
    "SELECT driver_lat, driver_lng, location_updated, order_status
     FROM orders WHERE order_id = ? AND buyer_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $order_id, $uid);
mysqli_stmt_execute($stmt);
$o = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$o) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit();
}

echo json_encode([
    'ok'      => true,
    'status'  => $o['order_status'],
    'lat'     => $o['driver_lat'] !== null ? (float) $o['driver_lat'] : null,
    'lng'     => $o['driver_lng'] !== null ? (float) $o['driver_lng'] : null,
    'updated' => $o['location_updated'],
]);
