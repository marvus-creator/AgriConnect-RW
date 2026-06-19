<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/sms.php';

// 1. SECURITY: Kick out anyone missing data or not logged in
if (!isset($_SESSION['user_id']) || !isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: dashboard.php");
    exit();
}

$uid      = (int) $_SESSION['user_id'];
$role     = $_SESSION['role'];
$order_id = (int) $_GET['id'];
$action   = $_GET['action'];

// Load the people attached to an order (for SMS notifications)
function order_parties(mysqli $conn, int $order_id): ?array {
    $stmt = mysqli_prepare($conn,
        "SELECT p.title,
                bu.user_id AS buyer_id, bu.full_name AS buyer_name, bu.phone_number AS buyer_phone,
                fu.user_id AS farmer_id, fu.full_name AS farmer_name, fu.phone_number AS farmer_phone
         FROM orders o
         JOIN products p ON o.product_id = p.product_id
         JOIN users bu ON o.buyer_id = bu.user_id
         JOIN users fu ON p.farmer_id = fu.user_id
         WHERE o.order_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
}

// ACTION 1: Farmer accepts an order
if ($action == 'accept' && $role == 'Farmer') {
    $stmt = mysqli_prepare($conn,
        "UPDATE orders o JOIN products p ON o.product_id = p.product_id
         SET o.order_status = 'Accepted'
         WHERE o.order_id = ? AND p.farmer_id = ? AND o.order_status = 'Pending'");
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $uid);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) > 0 && ($pp = order_parties($conn, $order_id))) {
        sms_send($conn, $pp['buyer_phone'],
            "AgriConnect: Your order for {$pp['title']} was accepted by {$pp['farmer_name']}. A driver will pick it up soon.",
            (int) $pp['buyer_id']);
    }
}

// ACTION 2: Logistics Driver picks up the cargo
elseif ($action == 'pickup' && $role == 'Driver') {
    $stmt = mysqli_prepare($conn,
        "UPDATE orders SET order_status = 'In-Transit', driver_id = ?
         WHERE order_id = ? AND order_status = 'Accepted'");
    mysqli_stmt_bind_param($stmt, "ii", $uid, $order_id);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) > 0 && ($pp = order_parties($conn, $order_id))) {
        sms_send($conn, $pp['buyer_phone'],
            "AgriConnect: Good news! Your {$pp['title']} is on the way. Track it live on your dashboard.",
            (int) $pp['buyer_id']);
    }
}

// ACTION 3: Logistics Driver delivers the cargo
elseif ($action == 'deliver' && $role == 'Driver') {
    $stmt = mysqli_prepare($conn,
        "UPDATE orders SET order_status = 'Delivered' WHERE order_id = ? AND driver_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $uid);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) > 0 && ($pp = order_parties($conn, $order_id))) {
        sms_send($conn, $pp['buyer_phone'],
            "AgriConnect: Your {$pp['title']} has been delivered. Thank you!", (int) $pp['buyer_id']);
        sms_send($conn, $pp['farmer_phone'],
            "AgriConnect: Your {$pp['title']} was delivered to {$pp['buyer_name']}. Earnings are ready to withdraw.",
            (int) $pp['farmer_id']);
    }
}

// 2. REDIRECT back to the dashboard
header("Location: dashboard.php?update=success");
exit();
