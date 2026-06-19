<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/sms.php';

// Security: Only logged-in buyers can check out
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Buyer') {
    header("Location: dashboard.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$error = "";

// 1. Process the order (No payment yet, just creating the request)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_order'])) {
    $product_id = (int) $_POST['product_id'];
    $qty_requested = (float) $_POST['buy_quantity'];

    // Fetch the real price from DB to prevent HTML tampering
    $p_stmt = mysqli_prepare($conn, "SELECT price_per_kg, quantity_kg FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($p_stmt, "i", $product_id);
    mysqli_stmt_execute($p_stmt);
    $p_data = mysqli_fetch_assoc(mysqli_stmt_get_result($p_stmt));

    if (!$p_data) {
        $error = "Product no longer available.";
    } elseif ($qty_requested <= 0) {
        $error = "Please enter a valid quantity.";
    } elseif ($qty_requested > $p_data['quantity_kg']) {
        $error = "Not enough stock available. Max: " . $p_data['quantity_kg'] . " KG";
    } else {
        $total_crop_price = (int) round($qty_requested * $p_data['price_per_kg']);
        $delivery_fee = 2000; // Standard platform delivery fee

        // Insert Order into DB as 'Pending'
        $stmt = mysqli_prepare($conn, "INSERT INTO orders (buyer_id, product_id, total_price, delivery_fee, order_status) VALUES (?, ?, ?, ?, 'Pending')");
        mysqli_stmt_bind_param($stmt, "iiii", $buyer_id, $product_id, $total_crop_price, $delivery_fee);

        if (mysqli_stmt_execute($stmt)) {
            // Deduct the bought quantity from the farmer's inventory
            $new_stock = $p_data['quantity_kg'] - $qty_requested;
            $u_stmt = mysqli_prepare($conn, "UPDATE products SET quantity_kg = ? WHERE product_id = ?");
            mysqli_stmt_bind_param($u_stmt, "di", $new_stock, $product_id);
            mysqli_stmt_execute($u_stmt);

            // SMS the farmer about the new order
            $n_stmt = mysqli_prepare($conn, "SELECT f.phone_number, f.user_id, p.title FROM products p JOIN users f ON p.farmer_id = f.user_id WHERE p.product_id = ?");
            mysqli_stmt_bind_param($n_stmt, "i", $product_id);
            mysqli_stmt_execute($n_stmt);
            if ($farmer = mysqli_fetch_assoc(mysqli_stmt_get_result($n_stmt))) {
                sms_send($conn, $farmer['phone_number'],
                    "AgriConnect: New order! {$qty_requested}kg of {$farmer['title']} requested. Open your dashboard to accept.",
                    (int) $farmer['user_id']);
            }

            header("Location: dashboard.php?msg=order_placed");
            exit();
        } else {
            $error = "System error processing order.";
        }
    }
}

// 2. Load the checkout page data
if (!isset($_GET['id'])) {
    header("Location: marketplace.php");
    exit();
}

$pid = mysqli_real_escape_string($conn, $_GET['id']);
$query = "SELECT p.*, f.full_name as farmer_name, f.district 
          FROM products p 
          JOIN users f ON p.farmer_id = f.user_id 
          WHERE p.product_id = '$pid'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    die("Product no longer available.");
}
$product = mysqli_fetch_assoc($result);
$delivery_fee = 2000;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Order | AgriConnect RW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-50 font-sans min-h-screen flex flex-col">

    <nav class="bg-white shadow-sm h-16 flex items-center px-8 justify-between sticky top-0 z-50">
        <a href="marketplace.php" class="text-gray-400 hover:text-akagera transition-colors font-bold text-sm">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to Market
        </a>
        <h1 class="font-black text-xl text-gray-900"><i class="fa-solid fa-file-invoice text-blue-500 mr-2"></i>Order Confirmation</h1>
    </nav>

    <main class="flex-grow flex items-center justify-center p-4 py-12">
        <div class="max-w-2xl w-full">
            
            <div class="bg-white rounded-[2rem] p-8 shadow-xl border border-gray-100 relative overflow-hidden">
                <div class="absolute top-6 right-6 bg-blue-50 text-blue-600 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1 border border-blue-100">
                    <i class="fa-solid fa-handshake"></i> Pay On Delivery
                </div>

                <h2 class="text-2xl font-black text-gray-900 mb-2">Review Your Order</h2>
                <p class="text-sm text-gray-500 font-medium mb-8">You will only pay when the driver arrives with your produce.</p>
                
                <?php if($error): ?>
                    <div class="bg-red-50 text-red-500 p-4 rounded-xl font-bold text-sm mb-6 flex items-center"><i class="fa-solid fa-circle-exclamation mr-2"></i> <?= $error ?></div>
                <?php endif; ?>

                <div class="flex gap-4 mb-6 pb-6 border-b border-gray-100">
                    <div class="w-20 h-20 rounded-2xl bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-inner">
                        <?php if($product['image']): ?>
                            <img src="uploads/products/<?= $product['image'] ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fa-solid fa-leaf text-3xl text-gray-300"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow">
                        <h3 class="font-black text-lg text-gray-900 leading-tight"><?= $product['title'] ?></h3>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1 mb-2">Farm: <?= $product['farmer_name'] ?></p>
                        <p class="text-xs font-bold text-akagera bg-green-50 inline-block px-2 py-1 rounded"><?= number_format($product['price_per_kg']) ?> RWF / KG</p>
                    </div>
                </div>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                    <input type="hidden" name="confirm_order" value="1">

                    <div class="flex justify-between items-center bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <span class="font-bold text-gray-700 text-sm">Quantity Needed (Max <?= $product['quantity_kg'] ?>kg)</span>
                        <div class="flex items-center gap-2">
                            <input type="number" name="buy_quantity" id="qtyInput" min="1" max="<?= $product['quantity_kg'] ?>" value="1" required class="w-20 p-2 text-right bg-white border border-gray-300 rounded-lg outline-none focus:border-akagera font-bold" oninput="calculateTotal()">
                            <span class="font-black text-gray-400 text-xs uppercase tracking-widest">KG</span>
                        </div>
                    </div>
                    
                    <div class="bg-[#f8f9fa] rounded-2xl p-6 border border-gray-100 space-y-4">
                        <h4 class="font-black text-xs uppercase tracking-widest text-gray-400 mb-2">Transparent Pricing Breakdown</h4>
                        
                        <div class="flex justify-between items-center text-sm font-bold text-gray-600">
                            <span><i class="fa-solid fa-seedling text-green-500 mr-2 w-4"></i> Cost of Produce (To Farmer)</span>
                            <span id="cropCostDisplay" class="text-gray-900"><?= number_format($product['price_per_kg']) ?> RWF</span>
                        </div>
                        
                        <div class="flex justify-between items-center text-sm font-bold text-gray-600">
                            <span><i class="fa-solid fa-truck-fast text-orange-500 mr-2 w-4"></i> Logistics Fee (To Driver)</span>
                            <span><?= number_format($delivery_fee) ?> RWF</span>
                        </div>

                        <div class="pt-4 border-t-2 border-dashed border-gray-200 flex justify-between items-end">
                            <span class="text-xs font-black uppercase tracking-widest text-gray-900">Total to pay on arrival</span>
                            <span id="grandTotalDisplay" class="text-3xl font-black text-akagera"><?= number_format($product['price_per_kg'] + $delivery_fee) ?> RWF</span>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-akagera text-white font-black text-lg py-4 rounded-2xl shadow-lg shadow-akagera/20 hover:bg-savannah hover:text-akagera transition-all transform active:scale-95 flex justify-center items-center gap-2">
                        Place Order Request <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        const pricePerKg = <?= $product['price_per_kg'] ?>;
        const deliveryFee = <?= $delivery_fee ?>;

        function calculateTotal() {
            let qty = parseFloat(document.getElementById('qtyInput').value) || 1;
            
            // Prevent exceeding stock visually
            if(qty > <?= $product['quantity_kg'] ?>) {
                qty = <?= $product['quantity_kg'] ?>;
                document.getElementById('qtyInput').value = qty;
            }

            const cropCost = qty * pricePerKg;
            const currentTotal = cropCost + deliveryFee;
            
            document.getElementById('cropCostDisplay').innerText = cropCost.toLocaleString() + " RWF";
            document.getElementById('grandTotalDisplay').innerText = currentTotal.toLocaleString() + " RWF";
        }
    </script>
</body>
</html>