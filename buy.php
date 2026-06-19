<?php
session_start();
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Buy Fresh Produce | AgriConnect RW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703' } } } }</script>
</head>
<body class="bg-gray-50 font-sans">
    
    <nav class="bg-white shadow-sm py-4 px-8 flex justify-between items-center sticky top-0 z-50">
        <a href="index.php" class="font-black text-xl text-akagera"><i class="fa-solid fa-basket-shopping text-savannah mr-2"></i>AgriFresh Store</a>
        <div class="flex gap-4">
            <a href="index.php" class="text-sm font-bold text-gray-500 hover:text-akagera pt-2">Home</a>
            <a href="marketplace.php" class="bg-savannah text-akagera px-6 py-2 rounded-full font-bold shadow-sm hover:scale-105 transition-transform">Advanced Filter</a>
        </div>
    </nav>

    <header class="bg-[#f8f9fa] py-12 px-8 border-b border-gray-200">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-4xl font-black text-gray-900 mb-2">Farm to Table, <span class="text-green-600">Delivered.</span></h1>
            <p class="text-gray-500 font-medium">Shop directly from Rwanda's top-rated farmers. Freshness guaranteed.</p>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php
            // THE SMART QUERY (grabs image, rating avg, and rating count)
            $query = "SELECT p.*, u.full_name, u.district, u.is_verified,
                      (SELECT AVG(r.stars) FROM ratings r JOIN orders o ON r.order_id = o.order_id WHERE o.product_id = p.product_id) as avg_rating,
                      (SELECT COUNT(*) FROM ratings r JOIN orders o ON r.order_id = o.order_id WHERE o.product_id = p.product_id) as review_count
                      FROM products p 
                      JOIN users u ON p.farmer_id = u.user_id 
                      ORDER BY p.product_id DESC";
            $result = mysqli_query($conn, $query);

            if(mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
                    
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

                    // --- 🚀 THE FIX: THE PRODUCT IMAGE OPTIONAL LOGIC ---
                    // Try to load the image. Fallback to leaf icon.
                    $img_display = $row['image'] ? "<img src='uploads/products/{$row['image']}' class='w-full h-full object-cover hover:scale-110 transition-transform duration-500'>" : "<i class='fa-solid fa-leaf text-5xl text-gray-200'></i>";
                    ?>
                    <div class="bg-white rounded-3xl p-5 shadow-sm border border-gray-100 hover:shadow-xl transition-shadow flex flex-col h-full overflow-hidden">
                        
                        <div class="h-40 bg-gray-50 rounded-2xl mb-4 flex items-center justify-center relative overflow-hidden flex-shrink-0 group cursor-pointer">
                            <span class="absolute top-3 left-3 bg-white/90 px-3 py-1 text-[10px] font-black uppercase rounded-lg shadow-sm z-10"><?= $row['district'] ?></span>
                            <?= $img_display ?>
                        </div>

                        <div class="flex-grow flex flex-col">
                            <h3 class="font-black text-lg text-gray-900"><?= $row['title'] ?></h3>
                            
                            <p class="text-xs text-gray-400 font-bold mb-3 uppercase flex items-center">
                                By <?= $row['full_name'] ?> <?= $verified_badge ?> 
                            </p>
                            <div class="mb-4"><?= $star_display ?></div>
                            
                            <div class="flex justify-between items-end mb-6">
                                <div>
                                    <p class="text-[10px] text-gray-400 uppercase font-black">Price per KG</p>
                                    <p class="text-xl font-black text-akagera"><?= number_format($row['price_per_kg']) ?> RWF</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-5 gap-2 mt-auto">
                                <a href="checkout.php?id=<?= $row['product_id'] ?>" class="col-span-3 bg-gray-900 text-white text-center py-3 rounded-xl font-bold hover:bg-savannah hover:text-gray-900 transition-colors shadow-sm text-sm flex items-center justify-center gap-2">
                                    Buy <i class="fa-solid fa-cart-shopping"></i>
                                </a>
                                <a href="chat.php?user=<?= $row['farmer_id'] ?>" class="col-span-2 bg-akagera/10 text-akagera border border-akagera/20 text-center py-3 rounded-xl font-bold hover:bg-akagera hover:text-white transition-colors shadow-sm text-sm flex items-center justify-center gap-2">
                                    Chat <i class="fa-solid fa-comment-dots"></i>
                                </a>
                            </div>

                        </div>
                    </div>
                    <?php
                }
            } else { echo "<p class='col-span-full text-center text-gray-400 font-bold py-20'>Market is currently empty.</p>"; }
            ?>
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