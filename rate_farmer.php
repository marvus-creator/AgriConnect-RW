<?php
session_start();
require_once 'includes/db.php';

// Security: Only logged-in buyers with a valid order ID can access this
if (!isset($_SESSION['user_id']) || !isset($_GET['order_id']) || $_SESSION['role'] !== 'Buyer') {
    header("Location: dashboard.php");
    exit();
}

$order_id = mysqli_real_escape_string($conn, $_GET['order_id']);
$buyer_id = $_SESSION['user_id'];

// Check if this order actually belongs to this buyer AND is Delivered
$order_check = mysqli_query($conn, "SELECT o.*, p.farmer_id, p.title, f.full_name as farmer_name 
                                    FROM orders o 
                                    JOIN products p ON o.product_id = p.product_id 
                                    JOIN users f ON p.farmer_id = f.user_id
                                    WHERE o.order_id = '$order_id' AND o.buyer_id = '$buyer_id' AND o.order_status = 'Delivered'");

$order = mysqli_fetch_assoc($order_check);

// If they try to rate an undelivered order or someone else's order, kick them out
if (!$order) { 
    die("Error: Order not found or not yet delivered."); 
}

// Check if they already rated this order so they can't spam reviews
$existing_rating = mysqli_query($conn, "SELECT rating_id FROM ratings WHERE order_id = '$order_id'");
if (mysqli_num_rows($existing_rating) > 0) {
    header("Location: dashboard.php?msg=already_rated");
    exit();
}

// Process the form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stars = (int)$_POST['stars'];
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $farmer_id = $order['farmer_id'];

    $sql = "INSERT INTO ratings (order_id, buyer_id, farmer_id, stars, review_text) 
            VALUES ('$order_id', '$buyer_id', '$farmer_id', '$stars', '$comment')";
    
    if (mysqli_query($conn, $sql)) {
        header("Location: dashboard.php?msg=rating_success");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rate Farmer | AgriConnect RW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-100 font-sans min-h-screen flex items-center justify-center p-4">
    
    <div class="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl border border-gray-100 text-center relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-savannah"></div>
        
        <div class="w-20 h-20 bg-yellow-50 border-4 border-white shadow-lg rounded-full flex items-center justify-center mx-auto mb-6 text-savannah text-3xl -mt-4">
            <i class="fa-solid fa-star"></i>
        </div>
        
        <h2 class="text-2xl font-black text-gray-900 mb-1">Rate your delivery!</h2>
        <p class="text-gray-500 mb-6 text-sm">How was the <strong><?= $order['title'] ?></strong> from <strong><?= $order['farmer_name'] ?></strong>?</p>

        <form method="POST" class="space-y-6">
            
            <div class="flex justify-center gap-2 flex-row-reverse text-4xl text-gray-200 rating-container">
                <input type="radio" name="stars" value="5" id="s5" class="hidden peer" required>
                <label for="s5" class="cursor-pointer hover:text-savannah peer-checked:text-savannah transition-colors"><i class="fa-solid fa-star"></i></label>
                
                <input type="radio" name="stars" value="4" id="s4" class="hidden peer">
                <label for="s4" class="cursor-pointer hover:text-savannah peer-checked:text-savannah peer-checked:~label:text-savannah transition-colors"><i class="fa-solid fa-star"></i></label>
                
                <input type="radio" name="stars" value="3" id="s3" class="hidden peer">
                <label for="s3" class="cursor-pointer hover:text-savannah peer-checked:text-savannah peer-checked:~label:text-savannah transition-colors"><i class="fa-solid fa-star"></i></label>
                
                <input type="radio" name="stars" value="2" id="s2" class="hidden peer">
                <label for="s2" class="cursor-pointer hover:text-savannah peer-checked:text-savannah peer-checked:~label:text-savannah transition-colors"><i class="fa-solid fa-star"></i></label>
                
                <input type="radio" name="stars" value="1" id="s1" class="hidden peer">
                <label for="s1" class="cursor-pointer hover:text-savannah peer-checked:text-savannah peer-checked:~label:text-savannah transition-colors"><i class="fa-solid fa-star"></i></label>
            </div>
            
            <style>
                /* CSS trick to make the stars fill in from left to right */
                .rating-container label:hover,
                .rating-container label:hover ~ label,
                .rating-container input:checked ~ label {
                    color: #FFB703;
                }
            </style>

            <textarea name="comment" placeholder="Write a short review (optional)..." class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none h-32 text-sm focus:border-savannah focus:ring-2 focus:ring-savannah/20 transition-all font-medium"></textarea>
            
            <button type="submit" class="w-full bg-akagera text-white font-black text-lg py-4 rounded-2xl hover:bg-dark transition-all shadow-lg shadow-akagera/30 transform active:scale-95">
                Submit Review <i class="fa-solid fa-paper-plane ml-2"></i>
            </button>
            
            <a href="dashboard.php" class="block text-gray-400 text-xs font-bold uppercase tracking-widest hover:text-gray-600 transition-colors mt-4">
                Skip for now
            </a>
        </form>
    </div>

</body>
</html>

