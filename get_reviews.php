<?php
require_once 'includes/db.php';

// SCENARIO 1: Product Reviews
if(isset($_GET['product_id'])){
    $pid = mysqli_real_escape_string($conn, $_GET['product_id']);
    $sql = "SELECT r.*, b.full_name, p.title as context_title 
            FROM ratings r JOIN orders o ON r.order_id = o.order_id 
            JOIN products p ON o.product_id = p.product_id JOIN users b ON r.buyer_id = b.user_id 
            WHERE o.product_id = '$pid' ORDER BY r.created_at DESC";
} 
// SCENARIO 2: Farmer Reviews
elseif(isset($_GET['farmer_id'])){
    $fid = mysqli_real_escape_string($conn, $_GET['farmer_id']);
    $sql = "SELECT r.*, b.full_name, p.title as context_title 
            FROM ratings r JOIN orders o ON r.order_id = o.order_id 
            JOIN products p ON o.product_id = p.product_id JOIN users b ON r.buyer_id = b.user_id 
            WHERE p.farmer_id = '$fid' ORDER BY r.created_at DESC";
} 
// 🚀 SCENARIO 3: DRIVER REVIEWS (NEW!)
elseif(isset($_GET['driver_id'])){
    $did = mysqli_real_escape_string($conn, $_GET['driver_id']);
    $sql = "SELECT r.*, b.full_name, p.title as context_title 
            FROM driver_ratings r JOIN orders o ON r.order_id = o.order_id 
            JOIN products p ON o.product_id = p.product_id JOIN users b ON r.buyer_id = b.user_id 
            WHERE r.driver_id = '$did' ORDER BY r.created_at DESC";
} else {
    die("Invalid request.");
}

$res = mysqli_query($conn, $sql);

if(mysqli_num_rows($res) > 0){
    while($row = mysqli_fetch_assoc($res)){
        echo "
        <div class='bg-gray-50 p-4 rounded-2xl border border-gray-100 mb-3'>
            <div class='flex justify-between items-center mb-1'>
                <p class='font-black text-sm text-gray-900'>{$row['full_name']}</p>
                <span class='text-yellow-500 text-[10px] font-black bg-yellow-50 px-2 py-1 rounded-full'>⭐ {$row['stars']}.0</span>
            </div>
            <p class='text-[9px] font-bold text-blue-700 uppercase tracking-widest mb-2'><i class='fa-solid fa-box mr-1'></i> Delivery: {$row['context_title']}</p>
            <p class='text-gray-600 text-sm font-medium'>\"" . htmlspecialchars($row['review_text']) . "\"</p>
            <p class='text-[9px] text-gray-400 font-bold uppercase tracking-widest mt-2'>" . date('M j, Y', strtotime($row['created_at'])) . "</p>
        </div>";
    }
} else {
    echo "<div class='text-center py-8 text-gray-400 font-bold'>No reviews yet.</div>";
}
?>