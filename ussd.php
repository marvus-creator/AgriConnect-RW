<?php
/**
 * ussd.php — USSD gateway callback (Africa's Talking format).
 *
 * Lets farmers/buyers with basic phones use AgriConnect by dialing a short code
 * (e.g. *384*1234#). The gateway POSTs sessionId, serviceCode, phoneNumber, text;
 * we reply with "CON ..." (await more input) or "END ..." (close session).
 *
 * Point your Africa's Talking USSD channel callback at this URL. Test locally
 * with ussd_sim.php.
 */

require_once 'includes/db.php';

header('Content-Type: text/plain');

$phone = $_POST['phoneNumber'] ?? ($_GET['phoneNumber'] ?? '');
$text  = $_POST['text'] ?? ($_GET['text'] ?? '');

// Normalise +2507... or 2507... to local 07... to match stored phone_number
function ussd_local(string $p): string {
    $p = preg_replace('/\D+/', '', $p);
    if (str_starts_with($p, '250')) $p = '0' . substr($p, 3);
    if (!str_starts_with($p, '0') && strlen($p) === 9) $p = '0' . $p;
    return $p;
}
$local = ussd_local($phone);

// Identify the caller (if registered)
$user = null;
$stmt = mysqli_prepare($conn, "SELECT user_id, full_name, role FROM users WHERE phone_number = ?");
mysqli_stmt_bind_param($stmt, "s", $local);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;

$parts = $text === '' ? [] : explode('*', $text);

// ---- Main menu ----
if (empty($parts)) {
    $greet = $user ? "Welcome {$user['full_name']}" : "Welcome to AgriConnect RW";
    echo "CON {$greet}\n";
    echo "1. Market Prices\n";
    echo "2. My Orders\n";
    echo "3. My Listings\n";
    exit();
}

// ---- 1. Market prices (open to everyone) ----
if ($parts[0] === '1') {
    $res = mysqli_query($conn, "SELECT crop_name, district_name, avg_price, trend FROM market_prices ORDER BY crop_name LIMIT 6");
    $out = "END Market Prices (RWF/kg):\n";
    while ($r = mysqli_fetch_assoc($res)) {
        $arrow = $r['trend'] === 'UP' ? '+' : ($r['trend'] === 'DOWN' ? '-' : '=');
        $out .= "{$r['crop_name']} {$r['district_name']}: {$r['avg_price']} {$arrow}\n";
    }
    echo $out;
    exit();
}

// Everything below needs a registered user
if (!$user) {
    echo "END This number is not registered on AgriConnect. Visit a kiosk or the website to sign up.";
    exit();
}

// ---- 2. My Orders (buyers) ----
if ($parts[0] === '2') {
    $uid = (int) $user['user_id'];
    $res = mysqli_query($conn, "SELECT p.title, o.order_status FROM orders o JOIN products p ON o.product_id = p.product_id WHERE o.buyer_id = $uid ORDER BY o.order_id DESC LIMIT 4");
    if (mysqli_num_rows($res) === 0) { echo "END You have no orders yet."; exit(); }
    $out = "END Your recent orders:\n";
    while ($r = mysqli_fetch_assoc($res)) $out .= "- {$r['title']}: {$r['order_status']}\n";
    echo $out;
    exit();
}

// ---- 3. My Listings (farmers) ----
if ($parts[0] === '3') {
    if ($user['role'] !== 'Farmer') { echo "END Listings are for farmer accounts only."; exit(); }
    $uid = (int) $user['user_id'];
    $res = mysqli_query($conn, "SELECT title, quantity_kg, price_per_kg FROM products WHERE farmer_id = $uid ORDER BY product_id DESC LIMIT 5");
    if (mysqli_num_rows($res) === 0) { echo "END You have no produce listed yet."; exit(); }
    $out = "END Your listings:\n";
    while ($r = mysqli_fetch_assoc($res)) $out .= "- {$r['title']}: " . (int)$r['quantity_kg'] . "kg @ {$r['price_per_kg']}\n";
    echo $out;
    exit();
}

echo "END Invalid choice. Please dial again.";
