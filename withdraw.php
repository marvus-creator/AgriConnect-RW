<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/momo.php';

// Security check
if (!isset($_SESSION['user_id']) || !isset($_POST['withdraw_amount'])) {
    header("Location: dashboard.php");
    exit();
}

$uid = (int) $_SESSION['user_id'];
$amount_requested = (int) $_POST['withdraw_amount'];

// 1. Get user's role, withdrawal history, and MoMo phone number
$u_stmt = mysqli_prepare($conn, "SELECT role, withdrawn_amount, phone_number FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($u_stmt, "i", $uid);
mysqli_stmt_execute($u_stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($u_stmt));
$role = $user_data['role'];
$current_withdrawn = (int) $user_data['withdrawn_amount'];
$phone = $user_data['phone_number'];

// 2. Calculate their Total Lifetime Earnings safely
$total_earned = 0;
if ($role == 'Farmer') {
    $earn_sql = mysqli_query($conn, "SELECT SUM(o.total_price) as total FROM orders o JOIN products p ON o.product_id = p.product_id WHERE p.farmer_id = '$uid' AND o.order_status = 'Delivered'");
    $total_earned = mysqli_fetch_assoc($earn_sql)['total'] ?? 0;
} elseif ($role == 'Driver') {
    $earn_sql = mysqli_query($conn, "SELECT SUM(delivery_fee) as earned FROM orders WHERE driver_id = '$uid' AND order_status = 'Delivered'");
    $total_earned = mysqli_fetch_assoc($earn_sql)['earned'] ?? 0;
}

// 3. Determine Available Balance
$available_balance = $total_earned - $current_withdrawn;

// 4. Validate, then push the payout through MoMo (disbursement)
if ($amount_requested > 0 && $amount_requested <= $available_balance) {
    $payout = momo_transfer($amount_requested, $phone, 'AgriConnect earnings withdrawal');
    momo_log($conn, $payout, 'disbursement', $uid, null, $amount_requested, $phone);

    if ($payout['status'] === 'SUCCESSFUL') {
        // Only debit the wallet once MoMo confirms the transfer
        $new_withdrawn_total = $current_withdrawn + $amount_requested;
        $w_stmt = mysqli_prepare($conn, "UPDATE users SET withdrawn_amount = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($w_stmt, "ii", $new_withdrawn_total, $uid);
        mysqli_stmt_execute($w_stmt);

        header("Location: dashboard.php?msg=withdraw_success&amt=" . $amount_requested);
    } else {
        // Payment failed/pending — do NOT debit
        header("Location: dashboard.php?msg=withdraw_error");
    }
} else {
    header("Location: dashboard.php?msg=withdraw_error");
}
exit();
