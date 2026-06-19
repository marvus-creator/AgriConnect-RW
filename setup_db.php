<?php
/**
 * setup_db.php — AgriConnect RW
 * One-time setup: reconciles the database schema with what the code expects,
 * then seeds realistic demo data so you can log in and explore immediately.
 *
 * Run from CLI:  php setup_db.php
 * Or open in browser:  http://localhost/AgriConnect-RW/setup_db.php
 *
 * Safe to run multiple times (idempotent).
 */

require_once __DIR__ . '/includes/db.php';

$cli = (php_sapi_name() === 'cli');
function out($msg, $ok = true) {
    global $cli;
    $prefix = $ok ? '[OK]  ' : '[ERR] ';
    echo $cli ? ($prefix . $msg . "\n") : ('<div style="font-family:monospace">' . $prefix . htmlspecialchars($msg) . '</div>');
}
function run($conn, $sql, $label) {
    if (mysqli_query($conn, $sql)) { out($label); }
    else { out($label . ' -> ' . mysqli_error($conn), false); }
}
function column_exists($conn, $table, $col) {
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $r && mysqli_num_rows($r) > 0;
}

echo $cli ? "\n=== AgriConnect RW :: Database Setup ===\n\n" : "<h2>AgriConnect RW :: Database Setup</h2>";

/* ---------------------------------------------------------------------------
 * 1. SCHEMA RECONCILIATION
 * ------------------------------------------------------------------------- */

// users: add columns the dashboard/profile/withdraw code relies on
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) DEFAULT NULL", "users.profile_pic ready");
run($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS withdrawn_amount INT NOT NULL DEFAULT 0", "users.withdrawn_amount ready");

// products: code uses `image`, old schema had `image_url`
if (!column_exists($conn, 'products', 'image')) {
    if (column_exists($conn, 'products', 'image_url')) {
        run($conn, "ALTER TABLE products CHANGE COLUMN image_url image VARCHAR(255) DEFAULT NULL", "products.image_url renamed to image");
    } else {
        run($conn, "ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT NULL", "products.image added");
    }
} else { out("products.image ready"); }

// orders: delivery fee used everywhere for driver payouts & totals
run($conn, "ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_fee INT NOT NULL DEFAULT 0", "orders.delivery_fee ready");

// orders: live driver GPS location for logistics tracking
run($conn, "ALTER TABLE orders ADD COLUMN IF NOT EXISTS driver_lat DECIMAL(10,7) DEFAULT NULL", "orders.driver_lat ready");
run($conn, "ALTER TABLE orders ADD COLUMN IF NOT EXISTS driver_lng DECIMAL(10,7) DEFAULT NULL", "orders.driver_lng ready");
run($conn, "ALTER TABLE orders ADD COLUMN IF NOT EXISTS location_updated TIMESTAMP NULL DEFAULT NULL", "orders.location_updated ready");

// messages (chat)
run($conn, "CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (sender_id), INDEX (receiver_id)
)", "messages table ready");

// ratings (farmer reviews)
run($conn, "CREATE TABLE IF NOT EXISTS ratings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    buyer_id INT NOT NULL,
    farmer_id INT NOT NULL,
    stars INT NOT NULL,
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (farmer_id), INDEX (order_id)
)", "ratings table ready");

// driver_ratings (driver reviews)
run($conn, "CREATE TABLE IF NOT EXISTS driver_ratings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    buyer_id INT NOT NULL,
    driver_id INT NOT NULL,
    stars INT NOT NULL,
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (driver_id), INDEX (order_id)
)", "driver_ratings table ready");

// transactions (MoMo collection + disbursement ledger)
run($conn, "CREATE TABLE IF NOT EXISTS transactions (
    txn_id INT AUTO_INCREMENT PRIMARY KEY,
    reference_id VARCHAR(64) NOT NULL,
    type ENUM('collection','disbursement') NOT NULL,
    user_id INT NOT NULL,
    order_id INT DEFAULT NULL,
    amount INT NOT NULL,
    phone VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    simulated TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id), INDEX (order_id), INDEX (reference_id)
)", "transactions table ready");

// sms_log (outbound SMS notifications)
run($conn, "CREATE TABLE IF NOT EXISTS sms_log (
    sms_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    recipient VARCHAR(20) NOT NULL,
    message VARCHAR(480) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Sent',
    simulated TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id), INDEX (recipient)
)", "sms_log table ready");

/* ---------------------------------------------------------------------------
 * 2. SEED DATA  (only inserts if the table is empty)
 * ------------------------------------------------------------------------- */
function table_empty($conn, $t) {
    $r = mysqli_query($conn, "SELECT COUNT(*) c FROM `$t`");
    return $r && ((int) mysqli_fetch_assoc($r)['c'] === 0);
}

// Categories
if (table_empty($conn, 'categories')) {
    foreach (['Vegetables','Fruits','Grains & Cereals','Tubers','Cash Crops'] as $c) {
        $c = mysqli_real_escape_string($conn, $c);
        mysqli_query($conn, "INSERT INTO categories (cat_name) VALUES ('$c')");
    }
    out("categories seeded (5)");
} else { out("categories already populated — skipped"); }

// Market prices (matches the homepage ticker vibe)
if (table_empty($conn, 'market_prices')) {
    $prices = [
        ['Irish Potatoes','Musanze',450,'UP'],
        ['Maize','Nyagatare',300,'DOWN'],
        ['Coffee Beans','Huye',1200,'STABLE'],
        ['Bananas','Rubavu',250,'UP'],
        ['Beans','Kigali',900,'STABLE'],
        ['Tomatoes','Kigali',700,'UP'],
        ['Cassava','Huye',400,'DOWN'],
        ['Rice','Nyagatare',1100,'STABLE'],
    ];
    foreach ($prices as $p) {
        $crop = mysqli_real_escape_string($conn, $p[0]);
        $dist = mysqli_real_escape_string($conn, $p[1]);
        mysqli_query($conn, "INSERT INTO market_prices (crop_name, district_name, avg_price, trend)
                             VALUES ('$crop','$dist',{$p[2]},'{$p[3]}')");
    }
    out("market_prices seeded (8)");
} else { out("market_prices already populated — skipped"); }

// Demo users — phone number is the login. All passwords are the role name + 123.
$demo_users = [
    ['Admin Control',        '0780000000', 'admin123',  'Admin',  'Kigali',    1],
    ['Jean Bosco Farms',     '0781111111', 'farmer123', 'Farmer', 'Musanze',   1],
    ['Mukamana Vegetables',  '0784444444', 'farmer123', 'Farmer', 'Huye',      1],
    ['Nyagatare Grain Co-op','0785555555', 'farmer123', 'Farmer', 'Nyagatare', 1],
    ['Kigali Fresh Bistro',  '0782222222', 'buyer123',  'Buyer',  'Kigali',    1],
    ['Heaven Restaurant',    '0786666666', 'buyer123',  'Buyer',  'Kigali',    0],
    ['Eric Habimana',        '0783333333', 'driver123', 'Driver', 'Kigali',    1],
    ['Patrick Niyonzima',    '0787777777', 'driver123', 'Driver', 'Musanze',   1],
];
$user_ids = [];
foreach ($demo_users as $u) {
    [$name, $phone, $pass, $role, $district, $verified] = $u;
    // skip if phone already exists
    $check = mysqli_query($conn, "SELECT user_id FROM users WHERE phone_number = '$phone'");
    if ($check && mysqli_num_rows($check) > 0) {
        $user_ids[$phone] = mysqli_fetch_assoc($check)['user_id'];
        continue;
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $name = mysqli_real_escape_string($conn, $name);
    mysqli_query($conn, "INSERT INTO users (full_name, phone_number, password_hash, role, district, is_verified)
                         VALUES ('$name','$phone','$hash','$role','$district',$verified)");
    $user_ids[$phone] = mysqli_insert_id($conn);
}
out("demo users ready (" . count($user_ids) . ")");

// Demo products (only if products table empty)
if (table_empty($conn, 'products')) {
    $cat = [];
    $cr = mysqli_query($conn, "SELECT cat_id, cat_name FROM categories");
    while ($row = mysqli_fetch_assoc($cr)) { $cat[$row['cat_name']] = $row['cat_id']; }

    $f1 = $user_ids['0781111111']; // Jean Bosco (Musanze)
    $f2 = $user_ids['0784444444']; // Mukamana (Huye)
    $f3 = $user_ids['0785555555']; // Nyagatare co-op

    $products = [
        [$f1, 'Tubers',          'Fresh Irish Potatoes', 'Premium grade Musanze highland potatoes, freshly harvested.', 500, 450],
        [$f1, 'Vegetables',      'Garden Carrots',       'Crisp organic carrots grown in volcanic soil.',               120, 600],
        [$f2, 'Vegetables',      'Ripe Tomatoes',        'Juicy vine-ripened tomatoes, sorted and graded.',             200, 700],
        [$f2, 'Fruits',          'Sweet Bananas',        'Sun-ripened dessert bananas from Huye.',                       300, 250],
        [$f3, 'Grains & Cereals','Dry Maize Grain',      'Sun-dried, cleaned maize ready for milling.',                1000, 300],
        [$f3, 'Grains & Cereals','White Rice',           'Locally milled premium white rice.',                          800,1100],
    ];
    foreach ($products as $p) {
        [$fid, $cname, $title, $desc, $qty, $price] = $p;
        $cid = $cat[$cname] ?? 'NULL';
        $title = mysqli_real_escape_string($conn, $title);
        $desc  = mysqli_real_escape_string($conn, $desc);
        mysqli_query($conn, "INSERT INTO products (farmer_id, cat_id, title, description, quantity_kg, price_per_kg, harvest_date, image)
                             VALUES ($fid, $cid, '$title', '$desc', $qty, $price, CURDATE(), NULL)");
    }
    out("products seeded (6)");
} else { out("products already populated — skipped"); }

echo $cli ? "\n=== DONE ===\n" : "<h3 style='font-family:sans-serif'>Done!</h3>";

/* Login cheat-sheet */
$lines = [
    "",
    "Login with (phone / password):",
    "  Admin   ->  0780000000 / admin123",
    "  Farmer  ->  0781111111 / farmer123   (Jean Bosco Farms)",
    "  Buyer   ->  0782222222 / buyer123     (Kigali Fresh Bistro)",
    "  Driver  ->  0783333333 / driver123    (Eric Habimana)",
    "",
];
if ($cli) { echo implode("\n", $lines) . "\n"; }
else { echo '<pre style="background:#0F172A;color:#FFB703;padding:16px;border-radius:8px">' . htmlspecialchars(implode("\n", $lines)) . '</pre>'; }
