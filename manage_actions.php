<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['action']) || !isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$uid = $_SESSION['user_id'];
$action = $_GET['action'];
$id = mysqli_real_escape_string($conn, $_GET['id']);

// --- 👨🏾‍🌾 FARMER: Delete Harvest ---
if ($action == 'delete_harvest' && $_SESSION['role'] == 'Farmer') {
    mysqli_query($conn, "DELETE FROM products WHERE product_id = '$id' AND farmer_id = '$uid'");
}

// --- 🛒 BUYER: Cancel Order (AND RESTORE INVENTORY!) ---
elseif ($action == 'cancel_order' && $_SESSION['role'] == 'Buyer') {
    
    // 1. Find the order first so we know what to restore
    $order_query = mysqli_query($conn, "SELECT * FROM orders WHERE order_id = '$id' AND buyer_id = '$uid' AND order_status = 'Pending'");
    
    if (mysqli_num_rows($order_query) > 0) {
        $order = mysqli_fetch_assoc($order_query);
        $product_id = $order['product_id'];
        $total_price = $order['total_price'];
        
        // 2. Find the product to get the price per KG
        $prod_query = mysqli_query($conn, "SELECT price_per_kg FROM products WHERE product_id = '$product_id'");
        if (mysqli_num_rows($prod_query) > 0) {
            $product = mysqli_fetch_assoc($prod_query);
            
            // 3. THE MAGIC MATH: Total RWF / Price per KG = The KGs they bought
            $restored_qty = $total_price / $product['price_per_kg'];
            
            // 4. Put the stock back on the shelf for the Farmer!
            mysqli_query($conn, "UPDATE products SET quantity_kg = quantity_kg + '$restored_qty' WHERE product_id = '$product_id'");
        }
        
        // 5. Finally, delete the cancelled order
        mysqli_query($conn, "DELETE FROM orders WHERE order_id = '$id'");
    }
}

// --- 🚚 DRIVER: Release Job ---
elseif ($action == 'release_job' && $_SESSION['role'] == 'Driver') {
    mysqli_query($conn, "UPDATE orders SET driver_id = NULL, order_status = 'Accepted' WHERE order_id = '$id' AND driver_id = '$uid'");
}

header("Location: dashboard.php");
exit();
?>