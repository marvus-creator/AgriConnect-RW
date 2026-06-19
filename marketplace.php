<?php
session_start();
require_once 'includes/db.php';

// --- 🚀 PRO LOGIC: MULTI-FILTER SYSTEM ---
$district_filter = isset($_GET['district']) ? mysqli_real_escape_string($conn, $_GET['district']) : '';
$search_filter = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$conditions = [];
if ($district_filter != "") {
    $conditions[] = "u.district = '$district_filter'";
}
if ($search_filter != "") {
    $conditions[] = "p.title LIKE '%$search_filter%'";
}

$where_clause = "";
if (count($conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch Profile Pic for Navbar if logged in
$nav_pic = null;
if(isset($_SESSION['user_id'])){
    $uid_nav = $_SESSION['user_id'];
    $pic_q = mysqli_query($conn, "SELECT profile_pic FROM users WHERE user_id='$uid_nav'");
    if($pic_q && mysqli_num_rows($pic_q) > 0){
        $nav_pic = mysqli_fetch_assoc($pic_q)['profile_pic'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>National Marketplace | AgriConnect RW</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
    <style>
        /* Sleek custom scrollbar for the dynamic district list */
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #FFB703; }
    </style>
</head>
<body class="bg-gray-50 font-sans">

    <nav class="bg-white shadow-sm sticky top-0 z-50 py-4">
        <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-2">
                <i class="fa-solid fa-tractor text-2xl text-akagera"></i>
                <span class="font-bold text-xl text-akagera">AgriConnect <span class="text-savannah">RW</span></span>
            </a>
            <div class="flex items-center gap-6">
                <?php
                $dash_link = 'dashboard.php';
                if(isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'){
                    $dash_link = 'admin_dashboard.php';
                }
                ?>
                <a href="<?= $dash_link ?>" class="text-gray-600 font-semibold hover:text-akagera transition-colors">My Dashboard</a>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="profile.php" class="h-10 w-10 bg-akagera rounded-full flex items-center justify-center text-white font-bold border-2 border-savannah shadow-sm overflow-hidden hover:scale-110 transition-transform">
                        <?php if($nav_pic): ?>
                            <img src="uploads/profiles/<?= $nav_pic ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <header class="bg-akagera py-16 text-center text-white relative">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#FFB703 2px, transparent 2px); background-size: 30px 30px;"></div>
        <h1 class="text-4xl font-extrabold mb-4 relative z-10">National Agricultural Exchange</h1>
        <p class="text-green-100 max-w-2xl mx-auto opacity-80 relative z-10 font-light">Directly connecting Rwanda's farmers to the marketplace.</p>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-12">
        <div class="flex flex-col lg:flex-row gap-12">
            
            <aside class="w-full lg:w-64 flex-shrink-0">
                <form id="filterForm" method="GET" action="marketplace.php" class="space-y-10 sticky top-28">
                    
                    <div>
                        <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2 uppercase tracking-widest text-xs">
                            <i class="fa-solid fa-magnifying-glass text-savannah"></i> Search Crops
                        </h3>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_filter) ?>" placeholder="e.g., Rice, Beans..." class="w-full p-3 pl-10 bg-white rounded-xl border border-gray-200 outline-none focus:border-akagera text-sm shadow-sm transition-colors">
                            <i class="fa-solid fa-search absolute left-4 top-3.5 text-gray-400 text-sm"></i>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2 uppercase tracking-widest text-xs">
                            <i class="fa-solid fa-location-dot text-savannah"></i> Active Regions
                        </h3>
                        <div class="space-y-3 max-h-56 overflow-y-auto pr-3 custom-scrollbar">
                            <?php 
                            // 🚀 PRO LOGIC: Only fetch districts that actually have products for sale!
                            $dist_query = "SELECT DISTINCT u.district FROM users u JOIN products p ON u.user_id = p.farmer_id WHERE u.district IS NOT NULL AND u.district != '' ORDER BY u.district ASC";
                            $dist_res = mysqli_query($conn, $dist_query);

                            if(mysqli_num_rows($dist_res) > 0) {
                                while($d_row = mysqli_fetch_assoc($dist_res)) {
                                    $d = $d_row['district'];
                                    $isChecked = ($district_filter == $d) ? 'checked' : '';
                                    echo "
                                    <label class='flex items-center group cursor-pointer'>
                                        <input type=".'"radio"'." name=".'"district"'." value="."'$d'"." onchange=".'"document.getElementById(\'filterForm\').submit();"'." $isChecked class='w-5 h-5 border-gray-300 text-akagera focus:ring-akagera'>
                                        <span class='ml-3 text-gray-600 group-hover:text-akagera font-medium transition-colors'>$d</span>
                                    </label>";
                                }
                            } else {
                                echo "<p class='text-xs font-bold text-gray-400'>No active regions.</p>";
                            }
                            ?>
                        </div>
                        
                        <?php if($district_filter != "" || $search_filter != ""): ?>
                            <a href="marketplace.php" class="inline-block mt-6 text-xs font-bold text-red-500 underline uppercase tracking-widest hover:text-red-700 transition-colors">
                                <i class="fa-solid fa-circle-xmark mr-1"></i> Clear All Filters
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="hidden"></button>
                </form>
            </aside>

            <div class="flex-grow">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                    <?php
                    $query = "SELECT p.*, u.full_name, u.district, u.is_verified,
                              (SELECT AVG(r.stars) FROM ratings r JOIN orders o ON r.order_id = o.order_id WHERE o.product_id = p.product_id) as avg_rating,
                              (SELECT COUNT(*) FROM ratings r JOIN orders o ON r.order_id = o.order_id WHERE o.product_id = p.product_id) as review_count
                              FROM products p 
                              JOIN users u ON p.farmer_id = u.user_id 
                              $where_clause
                              ORDER BY p.product_id DESC";
                    $result = mysqli_query($conn, $query);

                    if(mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)) {
                            $isMyProduct = (isset($_SESSION['user_id']) && $row['farmer_id'] == $_SESSION['user_id']);
                            $verified_badge = ($row['is_verified'] == 1) ? "<i class='fa-solid fa-circle-check text-blue-500 ml-1' title='Verified Farmer'></i>" : "";
                            
                            if ($row['review_count'] > 0) {
                                $formatted_rating = number_format($row['avg_rating'], 1);
                                $star_display = "<button onclick=\"openReviews({$row['product_id']}, '" . addslashes($row['title']) . "')\" class='text-yellow-500 font-black text-xs ml-2 cursor-pointer group'>
                                    <i class='fa-solid fa-star'></i> {$formatted_rating} 
                                    <span class='text-gray-400 font-medium border-b border-dashed border-gray-400 group-hover:text-gray-600 transition-colors'>({$row['review_count']} reviews)</span>
                                </button>";
                            } else {
                                $star_display = "<span class='text-gray-300 font-bold text-[10px] ml-2 uppercase tracking-widest bg-gray-100 px-2 py-0.5 rounded'>New</span>";
                            }
                            ?>
                            <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 group flex flex-col h-full">
                                
                                <div class="h-52 bg-gray-50 relative overflow-hidden flex-shrink-0">
                                    <div class="absolute top-5 left-5 bg-akagera/90 backdrop-blur text-white px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest shadow-lg z-10">
                                        <i class="fa-solid fa-location-dot mr-1 text-savannah"></i> <?= $row['district'] ?>
                                    </div>
                                    
                                    <?php if($row['image']): ?>
                                        <img src="uploads/products/<?= $row['image'] ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt="<?= $row['title'] ?>">
                                    <?php else: ?>
                                        <div class="flex items-center justify-center h-full text-gray-200 group-hover:scale-110 group-hover:text-gray-300 transition-transform duration-700">
                                            <i class="fa-solid fa-leaf text-7xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="p-8 flex-grow flex flex-col">
                                    <h3 class="text-xl font-extrabold text-gray-900 mb-2"><?= $row['title'] ?></h3>
                                    <p class="text-sm text-gray-500 mb-6 line-clamp-2 leading-relaxed flex-grow"><?= $row['description'] ?></p>
                                    
                                    <div class="grid grid-cols-2 gap-4 mb-6 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                        <div>
                                            <p class="text-[9px] uppercase font-black text-gray-400 tracking-widest mb-1">Price / KG</p>
                                            <p class="text-lg font-black text-akagera"><?= number_format($row['price_per_kg']) ?> <span class="text-[10px] font-bold">RWF</span></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[9px] uppercase font-black text-gray-400 tracking-widest mb-1">Available</p>
                                            <p class="text-lg font-black text-gray-800"><?= $row['quantity_kg'] ?> <span class="text-[10px] font-bold">KG</span></p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3 mb-8">
                                        <div class="h-9 w-9 bg-savannah/10 rounded-full flex items-center justify-center text-akagera text-xs font-black">
                                            <?= strtoupper(substr($row['full_name'], 0, 1)) ?>
                                        </div>
                                        <div class="text-[11px] flex-grow">
                                            <p class="text-gray-400 font-bold uppercase">Farmer</p>
                                            <p class="text-gray-900 font-extrabold flex items-center">
                                                <?= $row['full_name'] ?> <?= $verified_badge ?>
                                            </p>
                                            <div class="mt-1"><?= $star_display ?></div>
                                        </div>
                                    </div>

                                    <div class="space-y-3 mt-auto">
                                        <?php if($isMyProduct): ?>
                                            <button disabled class="w-full bg-gray-100 text-gray-400 font-bold py-4 rounded-2xl cursor-not-allowed border border-gray-200">
                                                <i class="fa-solid fa-user-check mr-2"></i> Your Listing
                                            </button>
                                        <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == 'Buyer'): ?>
                                            <div class="grid grid-cols-5 gap-2">
                                                <a href="checkout.php?id=<?= $row['product_id'] ?>" class="col-span-3 text-center bg-akagera text-white font-black py-4 rounded-2xl hover:bg-savannah hover:text-akagera transition-all duration-300 shadow-lg shadow-akagera/20 transform active:scale-95 flex items-center justify-center gap-2">
                                                    Buy <i class="fa-solid fa-cart-plus"></i>
                                                </a>
                                                <a href="chat.php?user=<?= $row['farmer_id'] ?>" class="col-span-2 text-center bg-akagera/10 text-akagera border border-akagera/20 font-black py-4 rounded-2xl hover:bg-akagera hover:text-white transition-all shadow-sm flex items-center justify-center gap-2">
                                                    Chat <i class="fa-solid fa-comment-dots"></i>
                                                </a>
                                            </div>
                                        <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == 'Farmer'): ?>
                                             <button disabled class="w-full bg-gray-50 text-gray-400 font-bold py-4 rounded-2xl cursor-not-allowed">
                                                Buyer Account Needed
                                            </button>
                                        <?php else: ?>
                                            <a href="auth/login.php" class="block w-full text-center border-2 border-akagera text-akagera font-bold py-4 rounded-2xl hover:bg-akagera hover:text-white transition-all">
                                                Login to Purchase
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='col-span-full py-32 text-center opacity-30'><i class='fa-solid fa-store-slash text-7xl mb-6'></i><p class='text-2xl font-bold'>No produce found matching criteria.</p></div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </main>

    <div id="reviewModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-[100] p-4 transition-opacity">
        <div class="bg-white rounded-[2rem] p-8 w-full max-w-md shadow-2xl flex flex-col max-h-[80vh]">
            <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-4">
                <div>
                    <h3 class="text-xl font-black text-gray-900" id="reviewModalTitle">Product Reviews</h3>
                </div>
                <button onclick="closeReviews()" class="h-10 w-10 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="reviewContent" class="overflow-y-auto flex-grow pr-2 space-y-4"></div>
        </div>
    </div>

    <script>
        function openReviews(productId, productName) {
            document.getElementById('reviewModal').classList.remove('hidden');
            document.getElementById('reviewModal').classList.add('flex');
            document.getElementById('reviewModalTitle').innerText = productName + " Reviews";
            document.getElementById('reviewContent').innerHTML = '<div class="flex justify-center py-10"><i class="fa-solid fa-circle-notch fa-spin text-3xl text-savannah"></i></div>';
            
            fetch('get_reviews.php?product_id=' + productId)
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