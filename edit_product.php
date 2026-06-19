<?php
session_start();
require_once 'includes/db.php';

// Security: Farmer check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Farmer') {
    header("Location: dashboard.php");
    exit();
}

$uid = $_SESSION['user_id'];
$msg = "";

// Get Product ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}
$pid = mysqli_real_escape_string($conn, $_GET['id']);

// --- LOGIC 1: FETCH CURRENT PRODUCT & VERIFY OWNER ---
$check_sql = "SELECT * FROM products WHERE product_id = '$pid' AND farmer_id = '$uid'";
$result = mysqli_query($conn, $check_sql);
if (mysqli_num_rows($result) === 0) {
    // Not found or not owned by this farmer
    header("Location: dashboard.php");
    exit();
}
$prod = mysqli_fetch_assoc($result);


// --- LOGIC 2: HANDLE REMOVING IMAGE ONLY ---
if (isset($_POST['remove_pic'])) {
    // 1. Delete actual file to save space
    if ($prod['image'] && file_exists("uploads/products/" . $prod['image'])) {
        unlink("uploads/products/" . $prod['image']);
    }

    // 2. Update DB set column to NULL
    mysqli_query($conn, "UPDATE products SET image = NULL WHERE product_id = '$pid'");
    
    // 3. Refresh data
    $result = mysqli_query($conn, $check_sql);
    $prod = mysqli_fetch_assoc($result);
    $msg = "<div class='bg-yellow-100 text-yellow-700 p-4 rounded-xl mb-6 font-bold'><i class='fa-solid fa-trash mr-2'></i> Product photo removed successfully. Listing reverted to leaf icon.</div>";
}


// --- LOGIC 3: HANDLE FULL PRODUCT UPDATE ---
if (isset($_POST['update_product'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $qty = mysqli_real_escape_string($conn, $_POST['quantity']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $harvest_date = mysqli_real_escape_string($conn, $_POST['harvest_date']);

    // Handle Image Replacement
    $image_part = "";
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        // Same expanded allowed list as add_product.php
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];
        $ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            if (!is_dir('uploads/products')) { mkdir('uploads/products', 0777, true); }
            
            // Unique filename: prod_{fid}_{time}.ext
            $new_name = "prod_" . $uid . "_" . time() . "." . $ext;
            $dest = "uploads/products/" . $new_name;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $dest)) {
                // *** CRUCIAL: Delete OLD file to save server storage space ***
                if ($prod['image'] && file_exists("uploads/products/" . $prod['image'])) {
                    unlink("uploads/products/" . $prod['image']);
                }
                
                $image_part = ", image = '$new_name'";
            }
        } else {
            $msg = "<div class='bg-red-100 text-red-700 p-4 rounded-xl mb-6 font-bold'><i class='fa-solid fa-file-excel mr-2'></i> Invalid photo type (.$ext). Only standard image formats allowed.</div>";
        }
    }

    // Update DB (text fields + optional image)
    $update_sql = "UPDATE products SET 
                   title = '$title', 
                   description = '$desc', 
                   quantity_kg = '$qty', 
                   price_per_kg = '$price', 
                   harvest_date = '$harvest_date'
                   $image_part 
                   WHERE product_id = '$pid'";
    
    if(mysqli_query($conn, $update_sql)){
        $msg = "<div class='bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold'><i class='fa-solid fa-check-circle mr-2'></i> Harvest listing updated beautifully!</div>";
        // Refresh product data after update
        $result = mysqli_query($conn, $check_sql);
        $prod = mysqli_fetch_assoc($result);
    } else {
        $msg = "<div class='bg-red-100 text-red-700 p-4 rounded-xl mb-6 font-bold'><i class='fa-solid fa-database mr-2'></i> DB Error: " . mysqli_error($conn) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Harvest: <?= $prod['title'] ?> | AgriConnect RW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }</script>
</head>
<body class="bg-gray-50 flex min-h-screen font-sans">

    <aside class="w-64 bg-akagera text-white flex flex-col hidden md:flex">
        <div class="p-6 font-black text-2xl tracking-tight flex items-center gap-2">
            <a href="dashboard.php" class="flex gap-2"><i class="fa-solid fa-tractor text-savannah"></i> AgriConnect</a>
        </div>
        <nav class="flex-grow px-4 space-y-2 mt-4">
            <a href="dashboard.php" class="flex items-center px-4 py-3 hover:bg-white/5 rounded-xl transition-all font-medium text-gray-300">
                <i class="fa-solid fa-house mr-3 w-4"></i> Return to Overview
            </a>
        </nav>
        <div class="p-6 border-t border-white/10">
            <a href="auth/logout.php" class="flex items-center text-red-400 font-bold hover:text-red-300 transition-colors">
                <i class="fa-solid fa-right-from-bracket mr-2"></i> Secure Logout
            </a>
        </div>
    </aside>

    <main class="flex-grow flex flex-col">
        <header class="bg-white shadow-sm h-20 flex items-center px-8">
            <h1 class="font-extrabold text-2xl text-gray-900">Edit <span class="text-akagera"><?= $prod['title'] ?></span> Listing</h1>
        </header>

        <div class="p-8 md:p-12 max-w-7xl mx-auto w-full">
            <?= $msg ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                
                <div class="lg:col-span-2">
                    <div class="bg-white p-10 rounded-[2.5rem] shadow-sm border border-gray-100">
                        <h2 class="text-lg font-bold text-gray-900 mb-8"><i class="fa-solid fa-pen-to-square text-savannah mr-2"></i> Harvest Details</h2>
                        
                        <form method="POST" enctype="multipart/form-data" class="space-y-6">
                            
                            <input type="text" name="title" required placeholder="Item Name (e.g., Sweet Potatoes)" value="<?= $prod['title'] ?>" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none font-bold text-lg focus:border-akagera">
                            
                            <textarea name="description" placeholder="Describe quality, variety, or organic status..." class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none h-32 text-sm focus:border-akagera"><?= $prod['description'] ?></textarea>
                            
                            <div class="bg-gray-100 p-6 rounded-2xl border border-gray-200">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Replace Photo (Optional)</label>
                                <input type="file" name="product_image" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-black file:bg-akagera file:text-white hover:file:bg-green-800 transition-colors cursor-pointer">
                                <p class="text-xs text-gray-400 mt-2 font-medium">Selecting a new file will automatically delete the old one.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-6 pt-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-2">Quantity Available (KG)</label>
                                    <input type="number" name="quantity" required value="<?= $prod['quantity_kg'] ?>" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none font-bold">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 mb-2">Price per KG (RWF)</label>
                                    <input type="number" name="price" required value="<?= $prod['price_per_kg'] ?>" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none font-bold text-akagera">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-2">Harvest Date</label>
                                <input type="date" name="harvest_date" required value="<?= $prod['harvest_date'] ?>" class="w-full p-4 bg-gray-50 rounded-2xl border border-gray-100 outline-none text-gray-600">
                            </div>

                            <div class="flex gap-4 pt-6">
                                <a href="dashboard.php" class="bg-gray-100 text-gray-500 font-bold px-8 py-4 rounded-2xl hover:bg-gray-200 transition-all flex items-center justify-center gap-2">
                                   <i class="fa-solid fa-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" name="update_product" class="flex-grow bg-akagera text-white font-bold py-4 rounded-2xl shadow-lg shadow-akagera/20 hover:bg-savannah hover:text-akagera transition-all transform active:scale-95">
                                    <i class="fa-solid fa-arrows-rotate mr-2"></i> Update Harvest Listing
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-1 space-y-8">
                    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-900 mb-6 text-center">Current Photo</h3>
                        
                        <div class="h-64 bg-gray-50 rounded-2xl mb-6 flex items-center justify-center relative overflow-hidden flex-shrink-0 group border border-gray-100">
                            <?php if($prod['image']): ?>
                                <img src="uploads/products/<?= $prod['image'] ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <?php else: ?>
                                <i class='fa-solid fa-leaf text-7xl text-gray-200'></i>
                            <?php endif; ?>
                        </div>

                        <?php if($prod['image']): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this photo? Listing will revert to default leaf icon.');">
                                <button type="submit" name="remove_pic" class="w-full bg-red-50 text-red-500 py-3 rounded-xl font-bold hover:bg-red-500 hover:text-white transition-all text-sm flex items-center justify-center gap-2 transform active:scale-95">
                                    <i class="fa-solid fa-trash"></i> Permanently Delete Photo
                                </button>
                            </form>
                        <?php else: ?>
                             <div class="text-center bg-gray-50 p-6 rounded-xl border border-dashed border-gray-200">
                                 <p class="text-gray-400 text-sm font-medium">Listing uses default leaf icon. Use the main form to upload a real photo.</p>
                             </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-red-50 border-2 border-dashed border-red-200 rounded-3xl p-6 text-center">
                        <p class="text-xs text-red-400 font-medium mb-3">If this harvest is no longer available or was listed by mistake:</p>
                        <a href='manage_actions.php?action=delete_harvest&id=<?= $pid ?>' onclick="return confirm('Delete permanently? This cannot be undone.');" class='text-red-500 font-bold hover:underline text-xs'><i class='fa-solid fa-trash mr-1'></i> Permanently Erase Listing</a>
                    </div>
                </div>

            </div>
        </div>
    </main>

</body>
</html>