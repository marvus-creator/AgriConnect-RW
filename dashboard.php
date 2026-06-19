<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/lang.php'; // 🚀 LOADS THE DICTIONARY
require_once 'includes/momo.php';
require_once 'includes/geo.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}
if ($_SESSION['role'] === 'Admin') {
    header("Location: admin_dashboard.php");
    exit();
}

$uid = $_SESSION['user_id'];
$name = $_SESSION['full_name'];
$role = $_SESSION['role'];

if (isset($_POST['release_payment']) && $role == 'Buyer') {
    $release_oid = (int) $_POST['momo_order_id'];

    // Fetch the order total + buyer phone for this (undelivered) order
    $o_stmt = mysqli_prepare($conn, "SELECT o.total_price, o.delivery_fee, u.phone_number
                                     FROM orders o JOIN users u ON u.user_id = o.buyer_id
                                     WHERE o.order_id = ? AND o.buyer_id = ? AND o.order_status != 'Delivered'");
    mysqli_stmt_bind_param($o_stmt, "ii", $release_oid, $uid);
    mysqli_stmt_execute($o_stmt);
    $ord = mysqli_fetch_assoc(mysqli_stmt_get_result($o_stmt));

    if ($ord) {
        $amount = (int) $ord['total_price'] + (int) $ord['delivery_fee'];
        $pay = momo_request_to_pay($amount, $ord['phone_number'], 'AgriConnect order #' . $release_oid);
        momo_log($conn, $pay, 'collection', $uid, $release_oid, $amount, $ord['phone_number']);

        if ($pay['status'] === 'SUCCESSFUL') {
            $d_stmt = mysqli_prepare($conn, "UPDATE orders SET order_status = 'Delivered' WHERE order_id = ? AND buyer_id = ?");
            mysqli_stmt_bind_param($d_stmt, "ii", $release_oid, $uid);
            mysqli_stmt_execute($d_stmt);
            header("Location: dashboard.php?msg=payment_released");
            exit();
        }
        header("Location: dashboard.php?msg=payment_failed");
        exit();
    }
    header("Location: dashboard.php?msg=payment_failed");
    exit();
}

$user_query = mysqli_query($conn, "SELECT profile_pic, withdrawn_amount, phone_number, district FROM users WHERE user_id = '$uid'");
$user_data = mysqli_fetch_assoc($user_query);
$profile_pic = $user_data['profile_pic'] ?? null;
$withdrawn_amount = $user_data['withdrawn_amount'] ?? 0;
$user_phone = $user_data['phone_number'] ?? 'Unknown Number';
$driver_district = $user_data['district'] ?? 'Kigali';

$unread_sql = mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = '$uid' AND is_read = 0");
$unread_msgs = mysqli_fetch_assoc($unread_sql)['count'] ?? 0;
$msg_badge = $unread_msgs > 0 ? "<span class='bg-red-500 text-white px-2 py-0.5 rounded-full text-[10px] font-black shadow-sm ml-auto'>{$unread_msgs}</span>" : "";

// Does this driver have a delivery in progress? (enables live GPS broadcasting)
$driver_has_route = false;
if ($role == 'Driver') {
    $route_count = mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE driver_id = '$uid' AND order_status = 'In-Transit'");
    $driver_has_route = (int)(mysqli_fetch_assoc($route_count)['c'] ?? 0) > 0;
}

if ($role == 'Farmer') {
    $earn_query = mysqli_query($conn, "SELECT SUM(o.total_price) as total FROM orders o JOIN products p ON o.product_id = p.product_id WHERE p.farmer_id = '$uid' AND o.order_status = 'Delivered'");
    $earnings = mysqli_fetch_assoc($earn_query)['total'] ?? 0;
    $available_balance = $earnings - $withdrawn_amount;
    
    $pending_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders o JOIN products p ON o.product_id = p.product_id WHERE p.farmer_id = '$uid' AND o.order_status = 'Pending'");
    $pending_requests = mysqli_fetch_assoc($pending_query)['count'] ?? 0;

    $rating_query = mysqli_query($conn, "SELECT AVG(stars) as avg_rating, COUNT(*) as review_count FROM ratings WHERE farmer_id = '$uid'");
    $rating_data = mysqli_fetch_assoc($rating_query);
    $avg_rating = $rating_data['avg_rating'] ? number_format($rating_data['avg_rating'], 1) : 'NEW';
    $review_count = $rating_data['review_count'] ?? 0;
} 
elseif ($role == 'Buyer') {
    $spent_query = mysqli_query($conn, "SELECT SUM(total_price + delivery_fee) as total FROM orders WHERE buyer_id = '$uid' AND order_status = 'Delivered'");
    $spent = mysqli_fetch_assoc($spent_query)['total'] ?? 0;
    
    $active_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE buyer_id = '$uid' AND order_status != 'Delivered'");
    $active_orders = mysqli_fetch_assoc($active_query)['count'] ?? 0;
} 
elseif ($role == 'Driver') {
    $del_query = mysqli_query($conn, "SELECT COUNT(*) as count, SUM(delivery_fee) as earned FROM orders WHERE driver_id = '$uid' AND order_status = 'Delivered'");
    $del_data = mysqli_fetch_assoc($del_query);
    $completed_deliveries = $del_data['count'] ?? 0;
    $driver_earnings = $del_data['earned'] ?? 0;
    $available_balance = $driver_earnings - $withdrawn_amount;

    $d_rating_query = mysqli_query($conn, "SELECT AVG(stars) as avg_rating, COUNT(*) as review_count FROM driver_ratings WHERE driver_id = '$uid'");
    $d_rating_data = mysqli_fetch_assoc($d_rating_query);
    $d_avg_rating = $d_rating_data['avg_rating'] ? number_format($d_rating_data['avg_rating'], 1) : 'NEW';
    $d_review_count = $d_rating_data['review_count'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $role ?> <?= $lang['dashboard'] ?> | AgriConnect RW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703', dark: '#0F172A', momo: '#ffcc00' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-50 flex min-h-screen font-sans">

    <aside class="w-64 bg-akagera text-white flex flex-col hidden md:flex">
        <div class="p-6 font-black text-2xl tracking-tight flex items-center gap-2">
            <i class="fa-solid fa-tractor text-savannah"></i> AgriConnect
        </div>
        <nav class="flex-grow px-4 space-y-2 mt-4">
            <a href="dashboard.php" class="flex items-center px-4 py-3 bg-white/10 rounded-xl border-l-4 border-savannah font-bold shadow-sm">
                <i class="fa-solid fa-house mr-3 w-4"></i> <?= $lang['nav_overview'] ?>
            </a>
            
            <a href="chat.php" class="flex items-center px-4 py-3 hover:bg-white/5 rounded-xl transition-all font-medium text-gray-300">
                <i class="fa-solid fa-comments mr-3 w-4"></i> <?= $lang['nav_messages'] ?> <?= $msg_badge ?>
            </a>

            <a href="transactions.php" class="flex items-center px-4 py-3 hover:bg-white/5 rounded-xl transition-all font-medium text-gray-300">
                <i class="fa-solid fa-mobile-screen-button mr-3 w-4"></i> MoMo History
            </a>
            
            <?php if($role == 'Buyer'): ?>
                <a href="marketplace.php" class="flex items-center px-4 py-3 hover:bg-white/5 rounded-xl transition-all font-medium text-gray-300">
                    <i class="fa-solid fa-store mr-3 w-4"></i> <?= $lang['nav_market'] ?>
                </a>
            <?php elseif($role == 'Farmer'): ?>
                <a href="#inventory" class="flex items-center px-4 py-3 hover:bg-white/5 rounded-xl transition-all font-medium text-gray-300">
                    <i class="fa-solid fa-seedling mr-3 w-4"></i> <?= $lang['nav_harvests'] ?>
                </a>
            <?php elseif($role == 'Driver'): ?>
                <a href="#routes" class="flex items-center px-4 py-3 hover:bg-white/5 rounded-xl transition-all font-medium text-gray-300">
                    <i class="fa-solid fa-map-location-dot mr-3 w-4"></i> <?= $lang['nav_routes'] ?>
                </a>
            <?php endif; ?>
        </nav>

        <div class="p-6 border-t border-white/10 space-y-4">
            <a href="reset_account.php" onclick="return confirm('Are you sure?');" class="flex items-center text-orange-400 font-bold hover:text-orange-300 transition-colors text-sm">
                <i class="fa-solid fa-rotate-left mr-2"></i> <?= $lang['nav_reset'] ?>
            </a>
            <a href="auth/logout.php" class="flex items-center text-red-400 font-bold hover:text-red-300 transition-colors">
                <i class="fa-solid fa-right-from-bracket mr-2"></i> <?= $lang['nav_logout'] ?>
            </a>
        </div>
    </aside>

    <main class="flex-grow flex flex-col overflow-hidden">
        
        <header class="bg-white shadow-sm h-20 flex items-center justify-between px-8 z-10">
            <h2 class="font-extrabold text-gray-800 uppercase tracking-widest text-sm bg-gray-100 px-3 py-1 rounded-md">
                <?= $role ?> <?= $lang['dashboard'] ?>
            </h2>
            <div class="flex items-center gap-4">
                
                <a href="switch_lang.php?lang=<?= $_SESSION['lang'] == 'en' ? 'rw' : 'en' ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-black transition-colors border border-gray-200 flex items-center gap-2">
                    <i class="fa-solid fa-globe text-savannah"></i> <?= $lang['switch_lang'] ?>
                </a>

                <div class="text-right hidden sm:block">
                    <div class="font-bold text-gray-900"><?= $name ?></div>
                </div>
                <a href="profile.php" class="h-10 w-10 bg-savannah/20 text-akagera rounded-full flex items-center justify-center font-black border-2 border-savannah hover:scale-110 transition-transform overflow-hidden shadow-md">
                    <?php if($profile_pic): ?>
                        <img src="uploads/profiles/<?= $profile_pic ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($name, 0, 1)) ?>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <div class="p-8 overflow-y-auto">
            
            <?php if(isset($_GET['msg'])): ?>
                <?php if($_GET['msg'] == 'payment_released'): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-xl font-bold shadow-sm">
                        <i class="fa-solid fa-check-circle mr-2"></i> <?= $lang['payment_success'] ?>
                    </div>
                <?php elseif($_GET['msg'] == 'payment_failed'): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-xl font-bold shadow-sm">
                        <i class="fa-solid fa-circle-exclamation mr-2"></i> MoMo payment did not go through. Your order was not charged — please try again.
                    </div>
                <?php elseif($_GET['msg'] == 'withdraw_success'): ?>
                    <div class="bg-momo border-l-4 border-yellow-600 text-gray-900 p-4 mb-6 rounded-r-xl font-bold shadow-sm">
                        <i class="fa-solid fa-money-bill-transfer mr-2"></i> <?= $lang['withdraw_success'] ?> <?= number_format($_GET['amt']) ?> <?= $lang['withdraw_to_momo'] ?>
                    </div>
                <?php elseif($_GET['msg'] == 'withdraw_error'): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-xl font-bold shadow-sm">
                        <i class="fa-solid fa-circle-exclamation mr-2"></i> <?= $lang['withdraw_error'] ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-black text-gray-900"><?= $lang['greeting'] ?>, <?= explode(' ', $name)[0] ?>! 👋</h1>
                    <p class="text-gray-500 font-medium mt-1"><?= $lang['welcome_sub'] ?></p>
                </div>
                
                <?php if($role == 'Farmer'): ?>
                    <button onclick="toggleProductModal()" class="bg-akagera text-white px-6 py-3 rounded-2xl font-bold hover:bg-savannah hover:text-akagera transition-all shadow-lg shadow-akagera/20">
                        <i class="fa-solid fa-plus mr-2"></i> <?= $lang['add_harvest'] ?>
                    </button>
                <?php elseif($role == 'Buyer'): ?>
                    <a href="marketplace.php" class="bg-savannah text-akagera px-6 py-3 rounded-2xl font-bold hover:scale-105 transition-all shadow-lg shadow-savannah/40">
                        <?= $lang['nav_market'] ?> <i class="fa-solid fa-arrow-right ml-2"></i>
                    </a>
                <?php endif; ?>
            </div>

            <?php if($role == 'Farmer'): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    
                    <div class="bg-dark p-6 rounded-3xl shadow-sm border border-gray-800 text-white relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 text-savannah opacity-10 group-hover:scale-110 transition-transform duration-500"><i class="fa-solid fa-wallet text-8xl"></i></div>
                        <div class="relative z-10 flex flex-col h-full justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1"><?= $lang['wallet_balance'] ?></p>
                                <h3 class="text-3xl font-black text-savannah"><?= number_format($available_balance) ?> <span class="text-sm text-gray-400">RWF</span></h3>
                                <p class="text-xs text-gray-500 font-bold mt-1 border-t border-gray-800 pt-2 inline-block"><?= $lang['lifetime_earned'] ?>: <?= number_format($earnings) ?></p>
                            </div>
                            <div class="mt-4">
                                <?php if($available_balance > 0): ?>
                                    <button onclick="openWithdrawModal(<?= $available_balance ?>)" class="w-full bg-savannah text-dark px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-yellow-400 transition-colors shadow-[0_0_15px_rgba(255,183,3,0.3)]"><?= $lang['withdraw_funds'] ?></button>
                                <?php else: ?>
                                    <button disabled class="w-full bg-gray-800 text-gray-600 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest cursor-not-allowed"><?= $lang['empty_wallet'] ?></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div <?= $review_count > 0 ? "onclick=\"openReviews({$uid}, 'farmer', 'Your')\"" : "" ?> class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center justify-between <?= $review_count > 0 ? 'cursor-pointer hover:border-yellow-300 hover:shadow-md transition-all group' : '' ?>">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-gray-400 mb-1 <?= $review_count > 0 ? 'group-hover:text-yellow-600 transition-colors' : '' ?>">
                                <?= $lang['my_rating'] ?> <?= $review_count > 0 ? '<i class="fa-solid fa-arrow-up-right-from-square ml-1 opacity-50"></i>' : '' ?>
                            </p>
                            <h3 class="text-3xl font-black text-gray-900">
                                <?= $avg_rating != 'NEW' ? '⭐ '.$avg_rating : 'NEW' ?> 
                                <span class="text-sm text-gray-400 font-medium <?= $review_count > 0 ? 'border-b border-dashed border-gray-400 group-hover:text-gray-600' : '' ?>">
                                    (<?= $review_count ?> <?= $lang['reviews'] ?>)
                                </span>
                            </h3>
                        </div>
                        <div class="h-14 w-14 bg-yellow-50 rounded-full flex items-center justify-center text-yellow-500 text-2xl <?= $review_count > 0 ? 'group-hover:scale-110 transition-transform' : '' ?>"><i class="fa-solid fa-star"></i></div>
                    </div>

                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-gray-400 mb-1"><?= $lang['incoming_requests'] ?></p>
                            <h3 class="text-3xl font-black text-orange-500"><?= $pending_requests ?></h3>
                        </div>
                        <div class="h-14 w-14 bg-orange-50 rounded-full flex items-center justify-center text-orange-500 text-2xl"><i class="fa-solid fa-bell"></i></div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-8 border-l-4 border-l-akagera">
                    <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-green-50/30">
                        <h3 class="font-bold text-gray-900"><i class="fa-solid fa-brain text-akagera mr-2"></i> AI Market Advisor</h3>
                        <button onclick="loadAdvisor()" class="text-xs font-black text-akagera hover:text-savannah transition-colors" title="Refresh advice"><i class="fa-solid fa-rotate"></i></button>
                    </div>
                    <div id="aiAdvisor" class="p-6">
                        <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                            <i class="fa-solid fa-circle-notch fa-spin text-2xl text-akagera mb-3"></i>
                            <p class="text-xs font-bold uppercase tracking-widest">Analysing the market for you...</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-orange-200 overflow-hidden mb-8 border-l-4 border-l-orange-500">
                    <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-orange-50/30">
                        <h3 class="font-bold text-gray-900"><i class="fa-solid fa-triangle-exclamation text-orange-500 mr-2"></i> <?= $lang['action_required'] ?></h3>
                    </div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-black">
                            <tr><th class="p-4"><?= $lang['buyer_name'] ?></th><th class="p-4"><?= $lang['product'] ?></th><th class="p-4 text-center"><?= $lang['qty_requested'] ?></th><th class="p-4 text-right"><?= $lang['earnings'] ?></th><th class="p-4 text-center"><?= $lang['action'] ?></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php
                            $req_sql = "SELECT o.*, b.full_name, p.title, p.image, p.price_per_kg FROM orders o JOIN products p ON o.product_id = p.product_id JOIN users b ON o.buyer_id = b.user_id WHERE p.farmer_id = '$uid' AND o.order_status = 'Pending'";
                            $req_res = mysqli_query($conn, $req_sql);
                            if(mysqli_num_rows($req_res) > 0) {
                                while($row = mysqli_fetch_assoc($req_res)) {
                                    $qty = $row['total_price'] / $row['price_per_kg'];
                                    $img_display = $row['image'] ? "<img src='uploads/products/{$row['image']}' class='w-8 h-8 rounded object-cover shadow-sm'>" : "<div class='w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-400'><i class='fa-solid fa-leaf'></i></div>";

                                    echo "<tr>
                                        <td class='p-4 font-bold text-sm'>{$row['full_name']}</td>
                                        <td class='p-4 text-sm font-medium flex items-center gap-3'>{$img_display} {$row['title']}</td>
                                        <td class='p-4 text-center'><span class='bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs font-black'>{$qty} KG</span></td>
                                        <td class='p-4 text-right font-black text-akagera'>".number_format($row['total_price'])." RWF</td>
                                        <td class='p-4 text-center flex justify-center gap-2 mt-1'>
                                            <a href='update_order.php?id={$row['order_id']}&action=accept' class='bg-akagera text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-green-700 shadow-sm'>{$lang['accept']}</a>
                                        </td>
                                    </tr>";
                                }
                            } else { echo "<tr><td colspan='5' class='p-8 text-center text-gray-400 text-sm'>{$lang['no_pending']}</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div id="inventory" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 font-bold text-gray-900"><?= $lang['my_listed_harvests'] ?></div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-black">
                            <tr><th class="p-4"><?= $lang['product'] ?></th><th class="p-4"><?= $lang['qty_remaining'] ?></th><th class="p-4 text-right"><?= $lang['price_kg'] ?></th><th class="p-4 text-center"><?= $lang['manage'] ?></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php
                            $prod_res = mysqli_query($conn, "SELECT * FROM products WHERE farmer_id = '$uid' ORDER BY product_id DESC");
                            if(mysqli_num_rows($prod_res) > 0) {
                                while($row = mysqli_fetch_assoc($prod_res)) {
                                    $img_display = $row['image'] ? "<img src='uploads/products/{$row['image']}' class='w-10 h-10 rounded-lg object-cover shadow-sm'>" : "<div class='w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400'><i class='fa-solid fa-leaf'></i></div>";

                                    echo "<tr>
                                        <td class='p-4 font-bold text-sm flex items-center gap-3'>{$img_display} {$row['title']}</td>
                                        <td class='p-4 text-sm'>{$row['quantity_kg']} kg</td>
                                        <td class='p-4 text-right font-black text-akagera'>".number_format($row['price_per_kg'])." RWF</td>
                                        <td class='p-4 text-center mt-2'>
                                            <a href='edit_product.php?id={$row['product_id']}' class='text-blue-500 font-bold hover:underline mr-3 text-xs'><i class='fa-solid fa-pen mr-1'></i> {$lang['edit']}</a>
                                            <a href='manage_actions.php?action=delete_harvest&id={$row['product_id']}' onclick=\"return confirm('Are you sure?');\" class='text-red-500 font-bold hover:underline text-xs'><i class='fa-solid fa-trash mr-1'></i> {$lang['delete']}</a>
                                        </td>
                                    </tr>";
                                }
                            } else { echo "<tr><td colspan='4' class='p-8 text-center text-gray-400'>{$lang['no_harvests']}</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($role == 'Buyer'): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-gray-400 mb-1"><?= $lang['total_spent'] ?></p>
                            <h3 class="text-3xl font-black text-red-500"><?= number_format($spent) ?> RWF</h3>
                        </div>
                        <div class="h-14 w-14 bg-red-50 rounded-full flex items-center justify-center text-red-500 text-2xl"><i class="fa-solid fa-receipt"></i></div>
                    </div>
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-gray-400 mb-1"><?= $lang['active_orders'] ?></p>
                            <h3 class="text-3xl font-black text-blue-500"><?= $active_orders ?></h3>
                        </div>
                        <div class="h-14 w-14 bg-blue-50 rounded-full flex items-center justify-center text-blue-500 text-2xl"><i class="fa-solid fa-box-open"></i></div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 font-bold text-gray-900"><?= $lang['my_order_history'] ?></div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-black">
                            <tr><th class="p-4"><?= $lang['farm_seller'] ?></th><th class="p-4"><?= $lang['product'] ?></th><th class="p-4 text-right"><?= $lang['cost_incl_delivery'] ?></th><th class="p-4 text-center"><?= $lang['live_status'] ?></th><th class="p-4 text-center"><?= $lang['action'] ?></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php
                            $hist_sql = "SELECT o.*, f.full_name, f.user_id as farmer_id, p.title, p.image 
                                         FROM orders o 
                                         JOIN products p ON o.product_id = p.product_id 
                                         JOIN users f ON p.farmer_id = f.user_id 
                                         WHERE o.buyer_id = '$uid' ORDER BY o.order_id DESC";
                            $hist_res = mysqli_query($conn, $hist_sql);
                            if(mysqli_num_rows($hist_res) > 0) {
                                while($row = mysqli_fetch_assoc($hist_res)) {
                                    $bg = 'bg-gray-100'; $text = 'text-gray-600';
                                    if($row['order_status'] == 'Pending') { $bg = 'bg-yellow-100'; $text = 'text-yellow-700'; }
                                    if($row['order_status'] == 'Accepted') { $bg = 'bg-blue-100'; $text = 'text-blue-700'; }
                                    if($row['order_status'] == 'In-Transit') { $bg = 'bg-purple-100'; $text = 'text-purple-700'; }
                                    if($row['order_status'] == 'Delivered') { $bg = 'bg-green-100'; $text = 'text-green-700'; }

                                    $action_button = "";
                                    if($row['order_status'] == 'Pending') {
                                        $action_button = "<a href='manage_actions.php?action=cancel_order&id={$row['order_id']}' onclick=\"return confirm('Cancel?');\" class='text-red-500 font-bold text-[10px] uppercase hover:underline'>{$lang['cancel']}</a>";
                                    } elseif($row['order_status'] == 'In-Transit') {
                                        $action_button = "<a href='track_order.php?id={$row['order_id']}' class='bg-blue-600 text-white px-3 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-blue-700 transition-all shadow-md animate-pulse flex items-center justify-center gap-1'><i class='fa-solid fa-location-crosshairs'></i> {$lang['live_track']}</a>";
                                    } elseif($row['order_status'] == 'Delivered') {
                                        $oid = $row['order_id'];
                                        $check_f = mysqli_query($conn, "SELECT rating_id FROM ratings WHERE order_id = '$oid'");
                                        $f_rated = mysqli_num_rows($check_f) > 0;
                                        
                                        $d_rated = false;
                                        if ($row['driver_id']) {
                                            $check_d = mysqli_query($conn, "SELECT rating_id FROM driver_ratings WHERE order_id = '$oid'");
                                            $d_rated = mysqli_num_rows($check_d) > 0;
                                        }

                                        $action_button = "<div class='flex flex-col gap-1.5 items-center justify-center'>";
                                        if ($f_rated) {
                                            $action_button .= "<span class='text-green-500 text-[9px] font-bold uppercase tracking-widest bg-green-50 px-2 py-0.5 rounded'><i class='fa-solid fa-check-double'></i> {$lang['farm_rated']}</span>";
                                        } else {
                                            $action_button .= "<a href='rate_farmer.php?order_id={$oid}' class='bg-savannah text-akagera px-2 py-1 rounded text-[9px] font-black uppercase hover:scale-105 transition-transform shadow-sm w-full text-center'>{$lang['rate_farm']}</a>";
                                        }
                                        if ($row['driver_id']) {
                                            if ($d_rated) {
                                                $action_button .= "<span class='text-orange-500 text-[9px] font-bold uppercase tracking-widest bg-orange-50 px-2 py-0.5 rounded'><i class='fa-solid fa-check-double'></i> {$lang['driver_rated']}</span>";
                                            } else {
                                                $action_button .= "<a href='rate_driver.php?order_id={$oid}' class='bg-orange-500 text-white px-2 py-1 rounded text-[9px] font-black uppercase hover:scale-105 transition-transform shadow-sm w-full text-center'>{$lang['rate_driver']}</a>";
                                            }
                                        }
                                        $action_button .= "</div>";
                                    } else {
                                        $action_button = "<span class='text-gray-300 text-[10px] uppercase font-bold'>{$lang['awaiting_driver']}</span>";
                                    }
                                    
                                    $img_display = $row['image'] ? "<img src='uploads/products/{$row['image']}' class='w-8 h-8 rounded object-cover shadow-sm'>" : "<div class='w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-400'><i class='fa-solid fa-leaf'></i></div>";

                                    echo "<tr>
                                        <td class='p-4 font-bold text-gray-900 text-sm'>
                                            {$row['full_name']} 
                                            <a href='chat.php?user={$row['farmer_id']}' class='text-blue-500 hover:text-blue-700 ml-2' title='Message Seller'><i class='fa-solid fa-comment-dots'></i></a>
                                        </td>
                                        <td class='p-4 text-sm font-medium flex items-center gap-3'>{$img_display} {$row['title']}</td>
                                        <td class='p-4 text-right font-black'>".number_format($row['total_price'] + $row['delivery_fee'])." RWF</td>
                                        <td class='p-4 text-center'>
                                            <span class='px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider {$bg} {$text}'>{$row['order_status']}</span>
                                        </td>
                                        <td class='p-4 text-center w-32'>{$action_button}</td>
                                    </tr>";
                                }
                            } else { echo "<tr><td colspan='5' class='p-8 text-center text-gray-400'>{$lang['no_buys']}</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($role == 'Driver'): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    
                    <div class="bg-dark p-6 rounded-3xl shadow-sm border border-gray-800 text-white relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 text-savannah opacity-10 group-hover:scale-110 transition-transform duration-500"><i class="fa-solid fa-wallet text-8xl"></i></div>
                        <div class="relative z-10 flex flex-col h-full justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1"><?= $lang['wallet_balance'] ?></p>
                                <h3 class="text-3xl font-black text-savannah"><?= number_format($available_balance) ?> <span class="text-sm text-gray-400">RWF</span></h3>
                                <p class="text-xs text-gray-500 font-bold mt-1 border-t border-gray-800 pt-2 inline-block"><?= $lang['lifetime_earned'] ?>: <?= number_format($driver_earnings) ?></p>
                            </div>
                            <div class="mt-4">
                                <?php if($available_balance > 0): ?>
                                    <button onclick="openWithdrawModal(<?= $available_balance ?>)" class="w-full bg-savannah text-dark px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-yellow-400 transition-colors shadow-[0_0_15px_rgba(255,183,3,0.3)]"><?= $lang['withdraw_funds'] ?></button>
                                <?php else: ?>
                                    <button disabled class="w-full bg-gray-800 text-gray-600 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest cursor-not-allowed"><?= $lang['empty_wallet'] ?></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-gray-400 mb-1"><?= $lang['successful_deliveries'] ?></p>
                            <h3 class="text-3xl font-black text-akagera"><?= $completed_deliveries ?></h3>
                        </div>
                        <div class="h-14 w-14 bg-blue-50 rounded-full flex items-center justify-center text-blue-500 text-2xl"><i class="fa-solid fa-check-double"></i></div>
                    </div>

                    <div <?= $d_review_count > 0 ? "onclick=\"openReviews({$uid}, 'driver', 'Your')\"" : "" ?> class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center justify-between <?= $d_review_count > 0 ? 'cursor-pointer hover:border-orange-300 hover:shadow-md transition-all group' : '' ?>">
                        <div>
                            <p class="text-xs font-black uppercase tracking-widest text-gray-400 mb-1 <?= $d_review_count > 0 ? 'group-hover:text-orange-500 transition-colors' : '' ?>">
                                <?= $lang['delivery_rating'] ?> <?= $d_review_count > 0 ? '<i class="fa-solid fa-arrow-up-right-from-square ml-1 opacity-50"></i>' : '' ?>
                            </p>
                            <h3 class="text-3xl font-black text-gray-900">
                                <?= $d_avg_rating != 'NEW' ? '⭐ '.$d_avg_rating : 'NEW' ?> 
                                <span class="text-sm text-gray-400 font-medium <?= $d_review_count > 0 ? 'border-b border-dashed border-gray-400 group-hover:text-gray-600' : '' ?>">
                                    (<?= $d_review_count ?> <?= $lang['reviews'] ?>)
                                </span>
                            </h3>
                        </div>
                        <div class="h-14 w-14 bg-orange-50 rounded-full flex items-center justify-center text-orange-500 text-2xl <?= $d_review_count > 0 ? 'group-hover:scale-110 transition-transform' : '' ?>"><i class="fa-solid fa-star"></i></div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-blue-200 overflow-hidden mb-8 border-l-4 border-l-blue-500">
                    <div class="p-6 border-b border-gray-50 font-bold text-gray-900 bg-blue-50/30"><?= $lang['available_jobs'] ?></div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-black">
                            <tr><th class="p-4"><?= $lang['pickup_info'] ?></th><th class="p-4"><?= $lang['dropoff_info'] ?></th><th class="p-4"><?= $lang['cargo'] ?></th><th class="p-4 text-center"><?= $lang['action'] ?></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php
                            $jobs_sql = "SELECT o.*, p.title, p.image, f.full_name as fname, f.user_id as fid, f.district as f_district, b.full_name as bname, b.user_id as bid, b.district as b_district
                                         FROM orders o JOIN products p ON o.product_id = p.product_id JOIN users f ON p.farmer_id = f.user_id JOIN users b ON o.buyer_id = b.user_id
                                         WHERE o.order_status = 'Accepted'";
                            $jobs_res = mysqli_query($conn, $jobs_sql);

                            // Buffer jobs so we can compute load-pooling (jobs sharing a pickup district)
                            $jobs = [];
                            $pickup_counts = [];
                            while($row = mysqli_fetch_assoc($jobs_res)) {
                                $jobs[] = $row;
                                $pickup_counts[$row['f_district']] = ($pickup_counts[$row['f_district']] ?? 0) + 1;
                            }
                            // Closest pickups first
                            [$drv_lat, $drv_lng] = geo_point($driver_district);
                            usort($jobs, function($a, $b) use ($drv_lat, $drv_lng) {
                                [$ala,$aln] = geo_point($a['f_district']); [$bla,$bln] = geo_point($b['f_district']);
                                return geo_distance_km($drv_lat,$drv_lng,$ala,$aln) <=> geo_distance_km($drv_lat,$drv_lng,$bla,$bln);
                            });

                            if(count($jobs) > 0) {
                                foreach($jobs as $row) {
                                    $img_display = $row['image'] ? "<img src='uploads/products/{$row['image']}' class='w-8 h-8 rounded object-cover shadow-sm'>" : "<div class='w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-400'><i class='fa-solid fa-leaf'></i></div>";

                                    [$pla,$pln] = geo_point($row['f_district']);
                                    $dist_km = geo_distance_km($drv_lat, $drv_lng, $pla, $pln);
                                    $dist_txt = $dist_km < 1 ? "near you" : number_format($dist_km, 0) . " km from you";

                                    // Load-pooling hint: more than one job waiting at this pickup district
                                    $pool = $pickup_counts[$row['f_district']] > 1
                                        ? "<span class='ml-1 bg-savannah/20 text-akagera text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full' title='Combine with ".($pickup_counts[$row['f_district']]-1)." other pickup(s) here — one trip, more earnings'><i class='fa-solid fa-layer-group mr-1'></i>Pool x{$pickup_counts[$row['f_district']]}</span>"
                                        : "";

                                    echo "<tr>
                                        <td class='p-4'>
                                            <div class='font-bold text-gray-900 flex items-center text-sm'>
                                                <i class='fa-solid fa-store w-4 text-gray-400 mr-1'></i> {$row['fname']} {$pool}
                                            </div>
                                            <div class='text-[10px] font-bold text-gray-400 mt-0.5 ml-5'>{$row['f_district']} · {$dist_txt}</div>
                                        </td>
                                        <td class='p-4'>
                                            <div class='font-bold text-blue-700 flex items-center text-sm'>
                                                <i class='fa-solid fa-location-dot w-4 text-blue-400 mr-1'></i> {$row['bname']}
                                            </div>
                                            <div class='text-[10px] font-bold text-gray-400 mt-0.5 ml-5'>{$row['b_district']}</div>
                                        </td>
                                        <td class='p-4 text-sm font-medium flex items-center gap-2'>{$img_display} {$row['title']}</td>
                                        <td class='p-4 text-center'>
                                            <a href='update_order.php?id={$row['order_id']}&action=pickup' class='bg-blue-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-blue-700 shadow-sm'>{$lang['take_job']} (+".number_format($row['delivery_fee'])." RWF)</a>
                                        </td>
                                    </tr>";
                                }
                            } else { echo "<tr><td colspan='4' class='p-8 text-center text-gray-400'>{$lang['no_jobs']}</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div id="routes" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 font-bold text-gray-900"><?= $lang['my_active_routes'] ?></div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-black">
                            <tr><th class="p-4"><?= $lang['route_contacts'] ?></th><th class="p-4"><?= $lang['cargo'] ?></th><th class="p-4 text-center"><?= $lang['status'] ?></th><th class="p-4 text-center"><?= $lang['manage'] ?></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php
                            $route_sql = "SELECT o.*, b.full_name as bname, b.user_id as bid, f.full_name as fname, f.user_id as fid, p.title, p.image 
                                          FROM orders o JOIN products p ON o.product_id = p.product_id JOIN users b ON o.buyer_id = b.user_id JOIN users f ON p.farmer_id = f.user_id
                                          WHERE o.driver_id = '$uid' AND o.order_status = 'In-Transit'";
                            $route_res = mysqli_query($conn, $route_sql);
                            if(mysqli_num_rows($route_res) > 0) {
                                while($row = mysqli_fetch_assoc($route_res)) {
                                    $img_display = $row['image'] ? "<img src='uploads/products/{$row['image']}' class='w-8 h-8 rounded object-cover shadow-sm'>" : "<div class='w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-400'><i class='fa-solid fa-leaf'></i></div>";

                                    echo "<tr>
                                        <td class='p-4'>
                                            <div class='font-bold text-gray-900 flex items-center text-sm'>
                                                <i class='fa-solid fa-store w-4 text-gray-400 mr-1'></i> {$row['fname']} 
                                            </div>
                                            <div class='font-bold text-blue-700 flex items-center mt-1 text-sm'>
                                                <i class='fa-solid fa-location-dot w-4 text-blue-400 mr-1'></i> {$row['bname']} 
                                            </div>
                                        </td>
                                        <td class='p-4 text-sm font-medium flex items-center gap-2'>{$img_display} {$row['title']}</td>
                                        <td class='p-4 text-center'>
                                            <span class='bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest animate-pulse'>{$lang['awaiting_buyer_pay']}</span>
                                        </td>
                                        <td class='p-4 text-center'><a href='manage_actions.php?action=release_job&id={$row['order_id']}' onclick=\"return confirm('Release?');\" class='text-orange-500 font-bold text-[10px] uppercase hover:underline'>{$lang['release_job']}</a></td>
                                    </tr>";
                                }
                            } else { echo "<tr><td colspan='4' class='p-8 text-center text-gray-400'>{$lang['empty_routes']}</td></tr>"; }
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php if($role == 'Farmer'): ?>
    <div id="productModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-[100] p-4 transition-opacity">
        <div class="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-extrabold text-akagera text-center"><?= $lang['new_listing'] ?></h3>
                <button onclick="toggleProductModal()" class="text-gray-400 hover:text-red-500"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            
            <form action="add_product.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="text" name="title" required placeholder="<?= $lang['item_name'] ?>" class="w-full p-4 bg-gray-50 rounded-2xl outline-none font-bold">
                <textarea name="description" placeholder="<?= $lang['description'] ?>" class="w-full p-4 bg-gray-50 rounded-2xl outline-none h-20 text-sm"></textarea>
                
                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-200">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2"><?= $lang['product_image'] ?></label>
                    <input type="file" name="product_image" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-akagera file:text-white hover:file:bg-green-800 transition-colors cursor-pointer">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <input type="number" name="quantity" required placeholder="KG" class="w-full p-4 bg-gray-50 rounded-2xl outline-none">
                    <input type="number" name="price" required placeholder="Price/KG" class="w-full p-4 bg-gray-50 rounded-2xl outline-none">
                </div>
                <input type="date" name="harvest_date" required class="w-full p-4 bg-gray-50 rounded-2xl outline-none text-sm text-gray-500">
                <button type="submit" class="w-full bg-akagera text-white font-bold py-4 rounded-2xl shadow-lg hover:bg-savannah hover:text-akagera transition-all mt-4"><?= $lang['list_to_market'] ?></button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if($role == 'Farmer' || $role == 'Driver'): ?>
    <div id="reviewModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-[100] p-4 transition-opacity">
        <div class="bg-white rounded-[2rem] p-8 w-full max-w-md shadow-2xl flex flex-col max-h-[80vh]">
            <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                <div>
                    <h3 class="text-xl font-black text-gray-900" id="reviewModalTitle"><?= $lang['customer_reviews'] ?></h3>
                    <p class="text-xs text-gray-500 font-medium mt-1"><?= $lang['what_buyers_say'] ?></p>
                </div>
                <button onclick="closeReviews()" class="h-10 w-10 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="reviewContent" class="overflow-y-auto flex-grow pr-2 space-y-4"></div>
        </div>
    </div>
    
    <div id="withdrawModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center z-[100] p-4 transition-opacity">
        <div class="bg-dark border border-gray-800 rounded-3xl p-8 w-full max-w-md shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-momo rounded-bl-full opacity-10 pointer-events-none"></div>
            
            <div class="flex justify-between items-center mb-6 relative z-10">
                <h3 class="text-2xl font-black text-white flex items-center gap-2"><i class="fa-solid fa-mobile-screen-button text-momo"></i> <?= $lang['momo_payout'] ?></h3>
                <button onclick="closeWithdrawModal()" class="text-gray-500 hover:text-white transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            
            <form action="withdraw.php" method="POST" class="space-y-6 relative z-10" onsubmit="return validateWithdrawal()">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2"><?= $lang['amount_to_withdraw'] ?></label>
                    <div class="bg-white/5 p-2 rounded-xl border border-white/10 flex items-center focus-within:border-momo transition-colors">
                        <span class="text-gray-400 font-black px-3">RWF</span>
                        <input type="number" name="withdraw_amount" id="withdrawAmountInput" required min="100" class="w-full bg-transparent text-white text-xl font-black outline-none p-2 tracking-wider">
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <p class="text-xs text-gray-500 font-bold"><?= $lang['max_available'] ?></p>
                        <button type="button" onclick="setMaxWithdraw()" class="text-[10px] text-momo font-black uppercase tracking-widest bg-momo/10 px-2 py-1 rounded hover:bg-momo/20"><span id="maxAvailableDisplay">0</span> RWF</button>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2"><?= $lang['transfer_to_number'] ?></label>
                    <div class="bg-gray-900 p-4 rounded-xl border border-gray-800 text-gray-300 font-bold flex items-center justify-between cursor-not-allowed" title="Locked for security">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-sim-card text-gray-500"></i> 
                            <span class="tracking-wider"><?= $user_phone ?></span>
                        </div>
                        <i class="fa-solid fa-lock text-gray-600 text-sm"></i>
                    </div>
                    <p class="text-[9px] text-gray-500 font-bold mt-2 uppercase tracking-widest"><i class="fa-solid fa-shield-halved text-gray-600 mr-1"></i> <?= $lang['locked_number_msg'] ?></p>
                </div>

                <button type="submit" class="w-full bg-momo text-[#004b50] font-black py-4 rounded-2xl shadow-[0_0_15px_rgba(255,204,0,0.3)] hover:bg-yellow-400 hover:scale-[1.02] transition-all flex justify-center items-center gap-2">
                    <?= $lang['initiate_transfer'] ?> <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        let maxWithdrawAmount = 0;

        function openWithdrawModal(maxAmount) {
            maxWithdrawAmount = maxAmount;
            document.getElementById('maxAvailableDisplay').innerText = maxAmount.toLocaleString();
            document.getElementById('withdrawAmountInput').max = maxAmount;
            document.getElementById('withdrawAmountInput').value = '';
            
            document.getElementById('withdrawModal').classList.remove('hidden');
            document.getElementById('withdrawModal').classList.add('flex');
        }

        function closeWithdrawModal() {
            document.getElementById('withdrawModal').classList.add('hidden');
            document.getElementById('withdrawModal').classList.remove('flex');
        }

        function setMaxWithdraw() {
            document.getElementById('withdrawAmountInput').value = maxWithdrawAmount;
        }

        function validateWithdrawal() {
            const val = parseInt(document.getElementById('withdrawAmountInput').value);
            if (val > maxWithdrawAmount) {
                alert("You cannot withdraw more than your available balance!");
                return false;
            }
            return true;
        }

        function toggleProductModal() {
            document.getElementById('productModal').classList.toggle('hidden');
            document.getElementById('productModal').classList.toggle('flex');
        }

        <?php if($role == 'Driver' && $driver_has_route): ?>
        // Live GPS broadcast — push this driver's location to their active deliveries.
        (function() {
            if (!navigator.geolocation) return;
            let lastSent = 0;
            function send(pos) {
                const now = Date.now();
                if (now - lastSent < 8000) return; // throttle to ~8s
                lastSent = now;
                const body = new URLSearchParams({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                fetch('update_location.php', { method: 'POST', body }).catch(() => {});
            }
            navigator.geolocation.watchPosition(send, () => {}, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });
        })();
        <?php endif; ?>

        function loadAdvisor() {
            const box = document.getElementById('aiAdvisor');
            if (!box) return;
            box.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-gray-400"><i class="fa-solid fa-circle-notch fa-spin text-2xl text-akagera mb-3"></i><p class="text-xs font-bold uppercase tracking-widest">Analysing the market for you...</p></div>';
            fetch('ai_advisor.php')
                .then(r => r.text())
                .then(html => box.innerHTML = html)
                .catch(() => box.innerHTML = '<div class="text-center py-6 text-gray-400 font-bold text-sm">Could not load advice right now.</div>');
        }
        document.addEventListener('DOMContentLoaded', loadAdvisor);

        function openReviews(userId, userType, userName) {
            document.getElementById('reviewModal').classList.remove('hidden');
            document.getElementById('reviewModal').classList.add('flex');
            document.getElementById('reviewModalTitle').innerText = userName + " Reviews";
            document.getElementById('reviewContent').innerHTML = '<div class="flex justify-center py-10"><i class="fa-solid fa-circle-notch fa-spin text-3xl text-savannah"></i></div>';
            
            let fetchUrl = userType === 'driver' ? 'get_reviews.php?driver_id=' + userId : 'get_reviews.php?farmer_id=' + userId;
            
            fetch(fetchUrl)
                .then(response => response.text())
                .then(data => document.getElementById('reviewContent').innerHTML = data);
        }

        function closeReviews() {
            document.getElementById('reviewModal').classList.add('hidden');
            document.getElementById('reviewModal').classList.remove('flex');
        }
    </script>
</body>
</html>