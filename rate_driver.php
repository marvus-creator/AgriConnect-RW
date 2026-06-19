<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Buyer') {
    header("Location: dashboard.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$buyer_id = $_SESSION['user_id'];

// Get order and driver info
$sql = "SELECT o.*, d.full_name as driver_name, d.user_id as driver_id, p.title 
        FROM orders o 
        JOIN users d ON o.driver_id = d.user_id 
        JOIN products p ON o.product_id = p.product_id 
        WHERE o.order_id = '$order_id' AND o.buyer_id = '$buyer_id' AND o.order_status = 'Delivered'";
$res = mysqli_query($conn, $sql);

if (mysqli_num_rows($res) == 0) {
    die("Invalid order or driver not found.");
}
$order = mysqli_fetch_assoc($res);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stars = (int)$_POST['stars'];
    $review = mysqli_real_escape_string($conn, $_POST['review']);
    $driver_id = $order['driver_id'];

    mysqli_query($conn, "INSERT INTO driver_ratings (order_id, buyer_id, driver_id, stars, review_text) 
                         VALUES ('$order_id', '$buyer_id', '$driver_id', '$stars', '$review')");
    
    header("Location: dashboard.php?msg=driver_rated");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rate Driver | AgriConnect RW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703' } } } }</script>
</head>
<body class="bg-gray-50 font-sans flex items-center justify-center min-h-screen p-4">

    <div class="bg-white rounded-[2rem] p-8 shadow-xl max-w-md w-full border border-gray-100">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 shadow-sm">
                <i class="fa-solid fa-truck-fast"></i>
            </div>
            <h2 class="text-2xl font-black text-gray-900">Rate your Delivery</h2>
            <p class="text-sm text-gray-500 font-medium mt-1">How was your delivery by <b><?= $order['driver_name'] ?></b>?</p>
        </div>

        <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 mb-6 flex justify-between items-center text-sm font-bold">
            <span class="text-gray-500"><i class="fa-solid fa-box mr-2"></i>Cargo:</span>
            <span class="text-gray-900"><?= $order['title'] ?></span>
        </div>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-3 text-center">Select Stars</label>
                <div class="flex justify-center gap-2 flex-row-reverse" id="star-container">
                    <style>
                        .star-radio { display: none; }
                        .star-label { font-size: 2rem; color: #e5e7eb; cursor: pointer; transition: color 0.2s; }
                        .star-radio:checked ~ .star-label { color: #FFB703; }
                        .star-label:hover, .star-label:hover ~ .star-label { color: #FFB703; }
                    </style>
                    <input type="radio" name="stars" id="star5" value="5" class="star-radio" required><label for="star5" class="star-label"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" name="stars" id="star4" value="4" class="star-radio"><label for="star4" class="star-label"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" name="stars" id="star3" value="3" class="star-radio"><label for="star3" class="star-label"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" name="stars" id="star2" value="2" class="star-radio"><label for="star2" class="star-label"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" name="stars" id="star1" value="1" class="star-radio"><label for="star1" class="star-label"><i class="fa-solid fa-star"></i></label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Write a Review</label>
                <textarea name="review" rows="3" required placeholder="Was the delivery fast? Was the driver polite?" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-xl outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition-all text-sm"></textarea>
            </div>

            <div class="flex gap-3">
                <a href="dashboard.php" class="flex-1 bg-gray-100 text-gray-600 font-bold py-3 rounded-xl text-center hover:bg-gray-200 transition-colors">Cancel</a>
                <button type="submit" class="flex-1 bg-orange-500 text-white font-bold py-3 rounded-xl hover:bg-orange-600 transition-colors shadow-md">Submit Rating</button>
            </div>
        </form>
    </div>

</body>
</html>