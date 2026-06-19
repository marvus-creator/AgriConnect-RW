<?php
session_start();
require_once 'includes/db.php';

// Security: Kick out anyone not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$uid = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role == 'Farmer') {
    // 1. Delete all orders attached to this farmer's products FIRST
    mysqli_query($conn, "DELETE o FROM orders o JOIN products p ON o.product_id = p.product_id WHERE p.farmer_id = '$uid'");
    
    // 2. NOW the database will allow us to delete the actual harvests
    mysqli_query($conn, "DELETE FROM products WHERE farmer_id = '$uid'");
} 
elseif ($role == 'Buyer') {
    // 1. If we really want to reset a buyer, we need to put the food back on the shelf first!
    $orders = mysqli_query($conn, "SELECT product_id, total_price FROM orders WHERE buyer_id = '$uid'");
    while($row = mysqli_fetch_assoc($orders)) {
        $pid = $row['product_id'];
        $spent = $row['total_price'];
        
        // Find product price to reverse the math
        $prod_res = mysqli_query($conn, "SELECT price_per_kg FROM products WHERE product_id = '$pid'");
        if($prod = mysqli_fetch_assoc($prod_res)) {
            $restore_qty = $spent / $prod['price_per_kg'];
            mysqli_query($conn, "UPDATE products SET quantity_kg = quantity_kg + '$restore_qty' WHERE product_id = '$pid'");
        }
    }
    // 2. Now wipe the buyer's history
    mysqli_query($conn, "DELETE FROM orders WHERE buyer_id = '$uid'");
} 
elseif ($role == 'Driver') {
    // Unassign the driver
    mysqli_query($conn, "UPDATE orders SET driver_id = NULL, order_status = 'Accepted' WHERE driver_id = '$uid'");
}

// Drop them back into their fresh dashboard
header("Location: dashboard.php");
exit();
?>