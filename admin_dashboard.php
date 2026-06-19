<?php
session_start();
require_once 'includes/db.php';

// SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$uid = $_SESSION['user_id'];
$name = $_SESSION['full_name'];

// --- VERIFY LOGIC ---
if (isset($_GET['verify_id'])) {
    $verify_id = mysqli_real_escape_string($conn, $_GET['verify_id']);
    mysqli_query($conn, "UPDATE users SET is_verified = NOT is_verified WHERE user_id = '$verify_id'");
    header("Location: admin_dashboard.php?msg=verification_updated");
    exit();
}

// --- BAN LOGIC ---
if (isset($_GET['ban_id'])) {
    $ban_id = mysqli_real_escape_string($conn, $_GET['ban_id']);
    mysqli_query($conn, "DELETE o FROM orders o JOIN products p ON o.product_id = p.product_id WHERE p.farmer_id = '$ban_id'");
    mysqli_query($conn, "DELETE FROM products WHERE farmer_id = '$ban_id'");
    mysqli_query($conn, "DELETE FROM orders WHERE buyer_id = '$ban_id'");
    mysqli_query($conn, "UPDATE orders SET driver_id = NULL, order_status = 'Accepted' WHERE driver_id = '$ban_id'");
    mysqli_query($conn, "DELETE FROM users WHERE user_id = '$ban_id'");
    header("Location: admin_dashboard.php?msg=user_banned");
    exit();
}

// --- NEW ADVANCED PLATFORM STATISTICS ---
$farmers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='Farmer'"))['c'];
$buyers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='Buyer'"))['c'];
$drivers_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='Driver'"))['c'];

// 🚀 FIXED: Total volume now includes the delivery fee so the GDP is accurate!
$total_volume_query = mysqli_query($conn, "SELECT SUM(total_price + delivery_fee) as v FROM orders WHERE order_status='Delivered'");
$total_volume = mysqli_fetch_assoc($total_volume_query)['v'] ?? 0;

$active_listings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products"))['c'];
$active_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE order_status != 'Delivered'"))['c'];

// Calculate total KG of food moved
$kg_query = mysqli_query($conn, "SELECT SUM(o.total_price / p.price_per_kg) as total_kg FROM orders o JOIN products p ON o.product_id = p.product_id WHERE o.order_status = 'Delivered'");
$total_kg = mysqli_fetch_assoc($kg_query)['total_kg'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advanced Command Center | AgriConnect RW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703', dark: '#0F172A' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-100 flex min-h-screen font-sans selection:bg-savannah selection:text-dark">

    <aside class="w-64 bg-dark text-white flex flex-col hidden md:flex fixed h-full shadow-2xl z-20">
        <div class="p-6 font-black text-2xl tracking-tight flex items-center gap-2">
            <i class="fa-solid fa-earth-africa text-savannah"></i> Admin<span class="text-gray-500">Panel</span>
        </div>
        <nav class="flex-grow px-4 space-y-2 mt-4">
            <a href="admin_dashboard.php" class="block px-4 py-3 bg-white/10 rounded-xl border-l-4 border-savannah font-bold shadow-sm">
                <i class="fa-solid fa-satellite-dish mr-2"></i> Command Center
            </a>
            <a href="overview.php" target="_blank" class="block px-4 py-3 hover:bg-white/5 rounded-xl transition-all font-medium text-gray-400">
                <i class="fa-solid fa-chart-pie mr-2"></i> Public Analytics
            </a>
        </nav>
        <div class="p-6 border-t border-white/10 space-y-4">
            <a href="auth/logout.php" class="flex items-center text-red-400 font-bold hover:text-red-300 transition-colors">
                <i class="fa-solid fa-right-from-bracket mr-2"></i> Secure Logout
            </a>
        </div>
    </aside>

    <main class="flex-grow flex flex-col ml-64 min-h-screen">
        
        <header class="bg-white shadow-sm h-20 flex items-center justify-between px-8 z-10 border-b border-gray-200 sticky top-0">
            <h2 class="font-extrabold text-red-600 uppercase tracking-widest text-sm bg-red-50 px-3 py-1 rounded-md border border-red-100 flex items-center gap-2">
                <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span></span>
                God Mode Active
            </h2>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <div class="font-bold text-gray-900"><?= $name ?></div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400">System Architect</div>
                </div>
                <div class="h-10 w-10 bg-dark text-white rounded-full flex items-center justify-center font-black shadow-lg border-2 border-savannah">
                    <i class="fa-solid fa-user-shield text-sm"></i>
                </div>
            </div>
        </header>

        <div class="p-8 space-y-8 max-w-7xl mx-auto w-full">
            
            <div>
                <h1 class="text-3xl font-black text-gray-900 tracking-tight">Ecosystem Intelligence 🧠</h1>
                <p class="text-gray-500 font-medium mt-1">Real-time overview of the AgriConnect network.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Farmers</p>
                    <h3 class="text-3xl font-black text-akagera"><?= $farmers_count ?></h3>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Buyers</p>
                    <h3 class="text-3xl font-black text-blue-500"><?= $buyers_count ?></h3>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Drivers</p>
                    <h3 class="text-3xl font-black text-orange-500"><?= $drivers_count ?></h3>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Live Listings</p>
                    <h3 class="text-3xl font-black text-green-500"><?= $active_listings ?></h3>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-center">
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Food Moved</p>
                    <h3 class="text-3xl font-black text-purple-600"><?= number_format($total_kg) ?> <span class="text-[10px]">KG</span></h3>
                </div>
                <div class="bg-dark p-5 rounded-2xl shadow-lg border border-gray-800 text-white relative overflow-hidden">
                    <div class="absolute -right-2 -bottom-2 opacity-10"><i class="fa-solid fa-money-bill-trend-up text-5xl"></i></div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1 relative z-10">Total GDP</p>
                    <h3 class="text-2xl font-black text-savannah relative z-10"><?= number_format($total_volume) ?> <span class="text-[10px]">RWF</span></h3>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] shadow-sm border border-blue-100 overflow-hidden relative">
                <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-blue-50/30">
                    <h3 class="font-black text-gray-900 text-lg flex items-center gap-2">
                        <i class="fa-solid fa-radar fa-spin-pulse text-blue-500"></i> Live Transaction Radar
                    </h3>
                    <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest"><?= $active_orders ?> Active Now</span>
                </div>
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-black">
                        <tr><th class="p-4">Order ID</th><th class="p-4">Flow (Farmer ➔ Buyer)</th><th class="p-4">Product</th><th class="p-4 text-right">Value (Incl. Delivery)</th><th class="p-4 text-center">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php
                        $radar_sql = "SELECT o.*, p.title, b.full_name as buyer_name, f.full_name as farmer_name 
                                      FROM orders o 
                                      JOIN products p ON o.product_id = p.product_id 
                                      JOIN users b ON o.buyer_id = b.user_id 
                                      JOIN users f ON p.farmer_id = f.user_id 
                                      ORDER BY o.order_id DESC LIMIT 5";
                        $radar_res = mysqli_query($conn, $radar_sql);
                        if(mysqli_num_rows($radar_res) > 0) {
                            while($row = mysqli_fetch_assoc($radar_res)) {
                                $bg = 'bg-gray-100'; $text = 'text-gray-600';
                                if($row['order_status'] == 'Pending') { $bg = 'bg-yellow-100'; $text = 'text-yellow-700'; }
                                if($row['order_status'] == 'Accepted') { $bg = 'bg-blue-100'; $text = 'text-blue-700'; }
                                if($row['order_status'] == 'In-Transit') { $bg = 'bg-purple-100'; $text = 'text-purple-700'; }
                                if($row['order_status'] == 'Delivered') { $bg = 'bg-green-100'; $text = 'text-green-700'; }

                                echo "<tr class='hover:bg-gray-50'>
                                    <td class='p-4 font-bold text-gray-400 text-xs'>#{$row['order_id']}</td>
                                    <td class='p-4 text-sm font-bold text-gray-800'>{$row['farmer_name']} <i class='fa-solid fa-arrow-right text-gray-300 mx-2 text-[10px]'></i> {$row['buyer_name']}</td>
                                    <td class='p-4 text-sm text-gray-500'>{$row['title']}</td>
                                    <td class='p-4 text-right font-black text-akagera'>".number_format($row['total_price'] + $row['delivery_fee'])." RWF</td>
                                    <td class='p-4 text-center'><span class='px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider {$bg} {$text}'>{$row['order_status']}</span></td>
                                </tr>";
                            }
                        } else { echo "<tr><td colspan='5' class='p-6 text-center text-gray-400 font-bold'>No transactions yet.</td></tr>"; }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-[2rem] shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-50 font-black text-gray-900 flex justify-between items-center bg-gray-50/50">
                    <span><i class="fa-solid fa-users-gear mr-2 text-gray-400"></i> Platform Directory & Security</span>
                </div>
                <table class="w-full text-left">
                    <thead class="bg-gray-100 text-[10px] uppercase text-gray-500 font-black tracking-widest">
                        <tr>
                            <th class="p-4">User Details</th>
                            <th class="p-4">Role</th>
                            <th class="p-4">Location & Contact</th>
                            <th class="p-4">Platform Intelligence (Metrics)</th>
                            <th class="p-4 text-right">Security Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $users_sql = "SELECT * FROM users WHERE role != 'Admin' ORDER BY user_id DESC";
                        $users_res = mysqli_query($conn, $users_sql);
                        
                        if(mysqli_num_rows($users_res) > 0) {
                            while($row = mysqli_fetch_assoc($users_res)) {
                                $uid = $row['user_id'];
                                
                                // ROLE COLORS
                                $role_color = 'bg-gray-100 text-gray-600';
                                if($row['role'] == 'Farmer') $role_color = 'bg-green-100 text-green-700';
                                if($row['role'] == 'Buyer') $role_color = 'bg-blue-100 text-blue-700';
                                if($row['role'] == 'Driver') $role_color = 'bg-orange-100 text-orange-700';

                                $is_verified = $row['is_verified'] == 1;
                                $badge = $is_verified ? "<i class='fa-solid fa-circle-check text-blue-500 ml-1' title='Verified'></i>" : "";
                                $verify_btn_class = $is_verified ? "bg-orange-50 text-orange-600 hover:bg-orange-600 hover:text-white" : "bg-green-50 text-green-600 hover:bg-green-600 hover:text-white";
                                $verify_icon = $is_verified ? "fa-user-xmark" : "fa-user-check";
                                $verify_text = $is_verified ? "Revoke" : "Verify";

                                // --- SMART METRICS BASED ON ROLE ---
                                $metrics_html = "";
                                if ($row['role'] == 'Farmer') {
                                    $stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as items FROM products WHERE farmer_id = '$uid'"));
                                    $rating = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(stars) as avg FROM ratings WHERE farmer_id = '$uid'"))['avg'];
                                    $star_text = $rating ? "⭐ ".number_format($rating,1) : "No Reviews";
                                    
                                    $earn_query = mysqli_query($conn, "SELECT SUM(o.total_price) as earned FROM orders o JOIN products p ON o.product_id = p.product_id WHERE p.farmer_id = '$uid' AND o.order_status = 'Delivered'");
                                    $earned = mysqli_fetch_assoc($earn_query)['earned'] ?? 0;
                                    $earned_formatted = number_format($earned);

                                    $metrics_html = "<div class='text-xs text-gray-500'>
                                        <span class='font-bold text-gray-900'>{$stats['items']}</span> Active Listings <br> 
                                        <span class='font-bold text-green-600'>Earned: {$earned_formatted} RWF</span> <br>
                                        <span class='font-bold text-yellow-500'>{$star_text}</span>
                                    </div>";
                                } 
                                elseif ($row['role'] == 'Buyer') {
                                    $stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as orders, SUM(total_price + delivery_fee) as spent FROM orders WHERE buyer_id = '$uid' AND order_status='Delivered'"));
                                    $spent = number_format($stats['spent'] ?? 0);
                                    $metrics_html = "<div class='text-xs text-gray-500'><span class='font-bold text-gray-900'>{$stats['orders']}</span> Completed Orders <br> <span class='font-bold text-blue-600'>Spent: {$spent} RWF</span></div>";
                                } 
                                elseif ($row['role'] == 'Driver') {
                                    $stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as deliveries, SUM(delivery_fee) as earned FROM orders WHERE driver_id = '$uid' AND order_status='Delivered'"));
                                    $rating = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(stars) as avg FROM driver_ratings WHERE driver_id = '$uid'"))['avg'];
                                    $star_text = $rating ? "⭐ ".number_format($rating,1) : "No Reviews";
                                    $d_earned = number_format($stats['earned'] ?? 0);
                                    
                                    $metrics_html = "<div class='text-xs text-gray-500'>
                                        <span class='font-bold text-gray-900'>{$stats['deliveries']}</span> Total Deliveries <br>
                                        <span class='font-bold text-green-600'>Earned: {$d_earned} RWF</span> <br>
                                        <span class='font-bold text-orange-500'>{$star_text}</span>
                                    </div>";
                                }

                                // 🚀 LOGIC: Check for profile pic, if NULL show initials block
                                $avatar_html = $row['profile_pic'] ? "<img src='uploads/profiles/{$row['profile_pic']}' class='w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm'>" : "<div class='w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-sm shadow-sm border border-gray-200'>".strtoupper(substr($row['full_name'],0,1))."</div>";

                                echo "<tr class='hover:bg-gray-50 transition-colors'>
                                    <td class='p-4'>
                                        <div class='flex items-center gap-3'>
                                            {$avatar_html}
                                            <div>
                                                <div class='font-extrabold text-gray-900 flex items-center'>{$row['full_name']} {$badge}</div>
                                                <div class='text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-0.5'>ID: #{$row['user_id']}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class='p-4'>
                                        <span class='px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shadow-sm {$role_color}'>{$row['role']}</span>
                                    </td>
                                    <td class='p-4'>
                                        <div class='font-bold text-gray-800 text-sm'>{$row['district']}</div>
                                        <div class='text-xs text-gray-500 font-medium'>{$row['phone_number']}</div>
                                    </td>
                                    <td class='p-4'>
                                        {$metrics_html}
                                    </td>
                                    <td class='p-4 text-right flex justify-end gap-2'>
                                        <a href='user_details.php?id={$row['user_id']}' class='bg-dark text-white hover:bg-gray-800 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition-colors shadow-sm'>
                                            <i class='fa-solid fa-folder-open mr-1'></i> Profile
                                        </a>
                                        <a href='admin_dashboard.php?verify_id={$row['user_id']}' class='{$verify_btn_class} px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition-colors shadow-sm'>
                                            <i class='fa-solid {$verify_icon} mr-1'></i> {$verify_text}
                                        </a>
                                        <a href='admin_dashboard.php?ban_id={$row['user_id']}' onclick=\"return confirm('DANGER: Ban {$row['full_name']} forever?');\" class='bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition-colors shadow-sm'>
                                            <i class='fa-solid fa-gavel mr-1'></i> Ban
                                        </a>
                                    </td>
                                </tr>";
                            }
                        } else { 
                            echo "<tr><td colspan='5' class='p-10 text-center text-gray-400 font-bold'>No active users on the platform yet.</td></tr>"; 
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

</body>
</html>