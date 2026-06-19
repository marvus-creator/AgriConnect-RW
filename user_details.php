<?php
session_start();
require_once 'includes/db.php';

// SECURITY: Only Admin allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$target_id = mysqli_real_escape_string($conn, $_GET['id']);

$user_sql = mysqli_query($conn, "SELECT * FROM users WHERE user_id = '$target_id'");
$user = mysqli_fetch_assoc($user_sql);

if (!$user) {
    die("User not found.");
}

$role = $user['role'];
$name = $user['full_name'];
$is_verified = $user['is_verified'] == 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $name ?> | Master Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703', dark: '#0F172A' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-100 font-sans min-h-screen pb-12">

    <nav class="bg-dark text-white py-4 px-8 sticky top-0 z-50 shadow-lg flex justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="admin_dashboard.php" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg text-sm font-bold transition-colors">
                <i class="fa-solid fa-arrow-left mr-2"></i> Back to Command Center
            </a>
            <span class="font-black text-xl tracking-tight text-gray-400">| User Dossier</span>
        </div>
        <div class="font-bold text-savannah flex items-center gap-2">
            <i class="fa-solid fa-shield-halved"></i> Admin Access
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-gray-200 text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-r from-dark to-gray-700"></div>
                
                <div class="relative z-10 w-28 h-28 bg-white rounded-full mx-auto border-4 border-white shadow-lg flex items-center justify-center text-4xl font-black text-akagera mt-6 mb-4 overflow-hidden">
                    <?php if($user['profile_pic']): ?>
                        <img src="uploads/profiles/<?= $user['profile_pic'] ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($name, 0, 1)) ?>
                    <?php endif; ?>
                </div>
                
                <h2 class="text-2xl font-black text-gray-900 flex justify-center items-center gap-2">
                    <?= $name ?> <?= $is_verified ? "<i class='fa-solid fa-circle-check text-blue-500'></i>" : "" ?>
                </h2>
                <p class="text-gray-500 font-bold uppercase tracking-widest text-xs mt-1 mb-6"><?= $role ?></p>
                
                <div class="space-y-3 text-left bg-gray-50 p-5 rounded-2xl border border-gray-100">
                    <div class="flex items-center gap-3 text-sm">
                        <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-gray-400 shadow-sm"><i class="fa-solid fa-phone"></i></div>
                        <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Phone</p><p class="font-bold text-gray-800"><?= $user['phone_number'] ?></p></div>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-gray-400 shadow-sm"><i class="fa-solid fa-location-dot"></i></div>
                        <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">District</p><p class="font-bold text-gray-800"><?= $user['district'] ?></p></div>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-gray-400 shadow-sm"><i class="fa-solid fa-fingerprint"></i></div>
                        <div><p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">System ID</p><p class="font-bold text-gray-800">USR-<?= $target_id ?></p></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            
            <?php if($role == 'Farmer'): ?>
                <?php
                // Farmer only earns the total crop price
                $earn_sql = mysqli_query($conn, "SELECT SUM(o.total_price) as earned FROM orders o JOIN products p ON o.product_id = p.product_id WHERE p.farmer_id = '$target_id' AND o.order_status = 'Delivered'");
                $earned = mysqli_fetch_assoc($earn_sql)['earned'] ?? 0;
                ?>
                <div class="bg-akagera text-white rounded-[2rem] p-8 shadow-lg flex justify-between items-center relative overflow-hidden">
                    <i class="fa-solid fa-seedling absolute -right-4 -bottom-4 text-8xl opacity-10"></i>
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-green-300 mb-1">Lifetime Revenue Generated</p>
                        <h2 class="text-4xl font-black"><?= number_format($earned) ?> <span class="text-lg">RWF</span></h2>
                    </div>
                </div>

                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/50 font-black text-gray-900"><i class="fa-solid fa-file-invoice-dollar mr-2 text-akagera"></i> Complete Sales History</div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-100 text-[10px] uppercase text-gray-500 font-black tracking-widest">
                            <tr><th class="p-4">Product Sold</th><th class="p-4">Sold To (Buyer)</th><th class="p-4 text-right">Amount</th><th class="p-4 text-center">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $sales = mysqli_query($conn, "SELECT o.*, p.title, b.full_name FROM orders o JOIN products p ON o.product_id = p.product_id JOIN users b ON o.buyer_id = b.user_id WHERE p.farmer_id = '$target_id' ORDER BY o.order_id DESC");
                            if(mysqli_num_rows($sales) > 0){
                                while($s = mysqli_fetch_assoc($sales)){
                                    echo "<tr class='hover:bg-gray-50'><td class='p-4 font-bold text-sm'>{$s['title']}</td><td class='p-4 text-sm'>{$s['full_name']}</td><td class='p-4 text-right font-black text-akagera'>".number_format($s['total_price'])."</td><td class='p-4 text-center text-xs font-bold text-gray-500'>{$s['order_status']}</td></tr>";
                                }
                            } else { echo "<tr><td colspan='4' class='p-6 text-center text-gray-400 font-bold'>No sales history.</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/50 font-black text-gray-900"><i class="fa-regular fa-comments mr-2 text-savannah"></i> Customer Feedback Received</div>
                    <div class="p-6 space-y-4">
                        <?php
                        $revs = mysqli_query($conn, "SELECT r.*, b.full_name, p.title FROM ratings r JOIN orders o ON r.order_id = o.order_id JOIN products p ON o.product_id = p.product_id JOIN users b ON r.buyer_id = b.user_id WHERE p.farmer_id = '$target_id' ORDER BY r.created_at DESC");
                        if(mysqli_num_rows($revs) > 0){
                            while($r = mysqli_fetch_assoc($revs)){
                                echo "<div class='bg-gray-50 p-4 rounded-xl border border-gray-100'>
                                    <div class='flex justify-between items-center mb-1'>
                                        <p class='font-bold text-sm'>From: {$r['full_name']} <span class='text-xs text-gray-400 font-normal ml-2'>on {$r['title']}</span></p>
                                        <span class='text-yellow-500 text-xs font-black'>⭐ {$r['stars']}.0</span>
                                    </div>
                                    <p class='text-gray-600 text-sm italic'>\"{$r['review_text']}\"</p>
                                </div>";
                            }
                        } else { echo "<p class='text-center text-gray-400 font-bold'>No reviews received yet.</p>"; }
                        ?>
                    </div>
                </div>

            <?php elseif($role == 'Buyer'): ?>
                <?php
                // 🚀 Buyer spend includes Crop Price + Delivery Fee
                $spent_sql = mysqli_query($conn, "SELECT SUM(total_price + delivery_fee) as spent FROM orders WHERE buyer_id = '$target_id' AND order_status = 'Delivered'");
                $spent = mysqli_fetch_assoc($spent_sql)['spent'] ?? 0;
                ?>
                <div class="bg-blue-600 text-white rounded-[2rem] p-8 shadow-lg flex justify-between items-center relative overflow-hidden">
                    <i class="fa-solid fa-cart-shopping absolute -right-4 -bottom-4 text-8xl opacity-10"></i>
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-blue-200 mb-1">Lifetime Platform Spend</p>
                        <h2 class="text-4xl font-black"><?= number_format($spent) ?> <span class="text-lg">RWF</span></h2>
                    </div>
                </div>

                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/50 font-black text-gray-900"><i class="fa-solid fa-receipt mr-2 text-blue-500"></i> Complete Purchase History</div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-100 text-[10px] uppercase text-gray-500 font-black tracking-widest">
                            <tr><th class="p-4">Product Bought</th><th class="p-4">Bought From (Farmer)</th><th class="p-4 text-right">Cost (Incl. Delivery)</th><th class="p-4 text-center">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $purchases = mysqli_query($conn, "SELECT o.*, p.title, f.full_name FROM orders o JOIN products p ON o.product_id = p.product_id JOIN users f ON p.farmer_id = f.user_id WHERE o.buyer_id = '$target_id' ORDER BY o.order_id DESC");
                            if(mysqli_num_rows($purchases) > 0){
                                while($p = mysqli_fetch_assoc($purchases)){
                                    echo "<tr class='hover:bg-gray-50'><td class='p-4 font-bold text-sm'>{$p['title']}</td><td class='p-4 text-sm'>{$p['full_name']}</td><td class='p-4 text-right font-black text-blue-600'>".number_format($p['total_price'] + $p['delivery_fee'])."</td><td class='p-4 text-center text-xs font-bold text-gray-500'>{$p['order_status']}</td></tr>";
                                }
                            } else { echo "<tr><td colspan='4' class='p-6 text-center text-gray-400 font-bold'>No purchase history.</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/50 font-black text-gray-900"><i class="fa-solid fa-pen-to-square mr-2 text-savannah"></i> Reviews Written by Buyer</div>
                    <div class="p-6 space-y-4">
                        <?php
                        $has_written_reviews = false;

                        // 1. Fetch Farm Reviews
                        $given_revs = mysqli_query($conn, "SELECT r.*, f.full_name, p.title FROM ratings r JOIN orders o ON r.order_id = o.order_id JOIN products p ON o.product_id = p.product_id JOIN users f ON p.farmer_id = f.user_id WHERE r.buyer_id = '$target_id' ORDER BY r.created_at DESC");
                        if(mysqli_num_rows($given_revs) > 0){
                            $has_written_reviews = true;
                            while($r = mysqli_fetch_assoc($given_revs)){
                                echo "<div class='bg-gray-50 p-4 rounded-xl border border-gray-100'>
                                    <div class='flex justify-between items-center mb-1'>
                                        <p class='font-bold text-sm'>Farm Review: {$r['full_name']}'s {$r['title']}</p>
                                        <span class='text-yellow-500 text-xs font-black'>⭐ {$r['stars']}.0</span>
                                    </div>
                                    <p class='text-gray-600 text-sm italic'>\"{$r['review_text']}\"</p>
                                </div>";
                            }
                        }

                        // 2. Fetch Driver Reviews
                        $given_d_revs = mysqli_query($conn, "SELECT dr.*, d.full_name, p.title FROM driver_ratings dr JOIN orders o ON dr.order_id = o.order_id JOIN products p ON o.product_id = p.product_id JOIN users d ON dr.driver_id = d.user_id WHERE dr.buyer_id = '$target_id' ORDER BY dr.created_at DESC");
                        if(mysqli_num_rows($given_d_revs) > 0){
                            $has_written_reviews = true;
                            while($r = mysqli_fetch_assoc($given_d_revs)){
                                echo "<div class='bg-orange-50/30 p-4 rounded-xl border border-orange-100'>
                                    <div class='flex justify-between items-center mb-1'>
                                        <p class='font-bold text-sm'>Driver Review: {$r['full_name']} <span class='text-xs text-gray-400 font-normal ml-2'>for {$r['title']} delivery</span></p>
                                        <span class='text-orange-500 text-xs font-black'>⭐ {$r['stars']}.0</span>
                                    </div>
                                    <p class='text-gray-600 text-sm italic'>\"{$r['review_text']}\"</p>
                                </div>";
                            }
                        }

                        if(!$has_written_reviews) { echo "<p class='text-center text-gray-400 font-bold'>No reviews written yet.</p>"; }
                        ?>
                    </div>
                </div>

            <?php elseif($role == 'Driver'): ?>
                <?php
                $del_sql = mysqli_query($conn, "SELECT COUNT(*) as c, SUM(delivery_fee) as earned FROM orders WHERE driver_id = '$target_id' AND order_status = 'Delivered'");
                $del_data = mysqli_fetch_assoc($del_sql);
                $del_count = $del_data['c'] ?? 0;
                $d_earned = $del_data['earned'] ?? 0;
                ?>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-orange-500 text-white rounded-[2rem] p-8 shadow-lg flex justify-between items-center relative overflow-hidden">
                        <i class="fa-solid fa-truck-fast absolute -right-4 -bottom-4 text-8xl opacity-10"></i>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-orange-200 mb-1">Total Deliveries</p>
                            <h2 class="text-3xl font-black"><?= $del_count ?></h2>
                        </div>
                    </div>
                    <div class="bg-green-600 text-white rounded-[2rem] p-8 shadow-lg flex justify-between items-center relative overflow-hidden">
                        <i class="fa-solid fa-money-bill-wave absolute -right-4 -bottom-4 text-8xl opacity-10"></i>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-green-200 mb-1">Logistics Revenue</p>
                            <h2 class="text-3xl font-black"><?= number_format($d_earned) ?> <span class="text-sm">RWF</span></h2>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/50 font-black text-gray-900"><i class="fa-solid fa-route mr-2 text-orange-500"></i> Complete Logistics History</div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-100 text-[10px] uppercase text-gray-500 font-black tracking-widest">
                            <tr><th class="p-4">Cargo</th><th class="p-4">Route (Farmer ➔ Buyer)</th><th class="p-4 text-center">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $routes = mysqli_query($conn, "SELECT o.*, p.title, f.full_name as fname, b.full_name as bname FROM orders o JOIN products p ON o.product_id = p.product_id JOIN users f ON p.farmer_id = f.user_id JOIN users b ON o.buyer_id = b.user_id WHERE o.driver_id = '$target_id' ORDER BY o.order_id DESC");
                            if(mysqli_num_rows($routes) > 0){
                                while($rt = mysqli_fetch_assoc($routes)){
                                    $status_color = $rt['order_status'] == 'Delivered' ? 'text-green-500' : 'text-orange-500';
                                    echo "<tr class='hover:bg-gray-50'>
                                        <td class='p-4 font-bold text-sm'>{$rt['title']}</td>
                                        <td class='p-4 text-sm font-medium'>{$rt['fname']} <i class='fa-solid fa-arrow-right text-gray-300 mx-2 text-[10px]'></i> {$rt['bname']}</td>
                                        <td class='p-4 text-center text-xs font-black uppercase {$status_color}'>{$rt['order_status']}</td>
                                    </tr>";
                                }
                            } else { echo "<tr><td colspan='3' class='p-6 text-center text-gray-400 font-bold'>No driving history.</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/50 font-black text-gray-900"><i class="fa-regular fa-comments mr-2 text-orange-500"></i> Delivery Feedback Received</div>
                    <div class="p-6 space-y-4">
                        <?php
                        $d_revs = mysqli_query($conn, "SELECT dr.*, b.full_name, p.title FROM driver_ratings dr JOIN orders o ON dr.order_id = o.order_id JOIN products p ON o.product_id = p.product_id JOIN users b ON dr.buyer_id = b.user_id WHERE dr.driver_id = '$target_id' ORDER BY dr.created_at DESC");
                        if(mysqli_num_rows($d_revs) > 0){
                            while($r = mysqli_fetch_assoc($d_revs)){
                                echo "<div class='bg-orange-50/30 p-4 rounded-xl border border-orange-100'>
                                    <div class='flex justify-between items-center mb-1'>
                                        <p class='font-bold text-sm'>From: {$r['full_name']} <span class='text-xs text-gray-400 font-normal ml-2'>on {$r['title']}</span></p>
                                        <span class='text-orange-500 text-xs font-black'>⭐ {$r['stars']}.0</span>
                                    </div>
                                    <p class='text-gray-600 text-sm italic'>\"{$r['review_text']}\"</p>
                                </div>";
                            }
                        } else { echo "<p class='text-center text-gray-400 font-bold'>No reviews received yet.</p>"; }
                        ?>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </main>

</body>
</html>