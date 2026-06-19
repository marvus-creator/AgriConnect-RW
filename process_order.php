<?php
session_start();
require_once 'includes/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Buyer' || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
$requested_qty = mysqli_real_escape_string($conn, $_POST['requested_quantity']);

// 1. Double-check the product exists and still has enough stock
$check_sql = "SELECT * FROM products WHERE product_id = '$product_id'";
$check_res = mysqli_query($conn, $check_sql);
$product = mysqli_fetch_assoc($check_res);

if (!$product || $product['quantity_kg'] < $requested_qty || $requested_qty <= 0) {
    // If someone else bought it first, or they try to hack the quantity
    echo "<script>alert('Error: Not enough stock available!'); window.location.href='buy.php';</script>";
    exit();
}

// 2. Calculate the Final Price (Requested KG * Price per KG)
$total_price = $requested_qty * $product['price_per_kg'];

// 3. Create the Order in the database
// Note: If you don't have a 'quantity' column in your orders table yet, it will just save the total_price which is perfectly fine for your dashboard logic!
$order_sql = "INSERT INTO orders (buyer_id, product_id, total_price, order_status) 
              VALUES ('$buyer_id', '$product_id', '$total_price', 'Pending')";

if (mysqli_query($conn, $order_sql)) {
    // 4. THE MAGIC: Deduct the purchased amount from the Farmer's inventory!
    $new_qty = $product['quantity_kg'] - $requested_qty;
    mysqli_query($conn, "UPDATE products SET quantity_kg = '$new_qty' WHERE product_id = '$product_id'");

    // Send the buyer back to their portal to see the new order
    header("Location: dashboard.php?msg=order_success");
    exit();
} else {
    echo "Error processing order. Please try again.";
}
?>