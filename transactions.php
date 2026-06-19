<?php
// transactions.php — the logged-in user's MoMo payment/payout history.
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$uid  = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['full_name'];

$stmt = mysqli_prepare($conn,
    "SELECT type, amount, status, simulated, order_id, reference_id, created_at
     FROM transactions WHERE user_id = ? ORDER BY txn_id DESC");
mysqli_stmt_bind_param($stmt, "i", $uid);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$rows = [];
$total_in = 0; $total_out = 0;
while ($t = mysqli_fetch_assoc($res)) {
    $rows[] = $t;
    if ($t['status'] === 'SUCCESSFUL') {
        if ($t['type'] === 'disbursement') $total_out += (int) $t['amount'];
        else $total_in += (int) $t['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MoMo History | AgriConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703', momo: '#ffcc00', dark: '#0F172A' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }</script>
</head>
<body class="bg-gray-50 font-sans min-h-screen">

    <nav class="bg-white shadow-sm h-16 flex items-center px-8 justify-between sticky top-0 z-50">
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="text-gray-400 hover:text-akagera transition-colors"><i class="fa-solid fa-arrow-left text-xl"></i></a>
            <h1 class="font-black text-xl text-gray-900"><i class="fa-solid fa-mobile-screen-button text-momo mr-1"></i> MoMo <span class="text-savannah">History</span></h1>
        </div>
        <span class="text-sm font-bold text-gray-500"><?= htmlspecialchars($name) ?></span>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-10">

        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1"><i class="fa-solid fa-arrow-down text-green-500 mr-1"></i> Received (Payments In)</p>
                <h3 class="text-2xl font-black text-green-600"><?= number_format($total_in) ?> <span class="text-sm text-gray-400">RWF</span></h3>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1"><i class="fa-solid fa-arrow-up text-momo mr-1"></i> Paid Out (Withdrawals)</p>
                <h3 class="text-2xl font-black text-gray-900"><?= number_format($total_out) ?> <span class="text-sm text-gray-400">RWF</span></h3>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-50 font-bold text-gray-900">Transaction Ledger</div>
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-[10px] uppercase text-gray-400 font-black">
                    <tr>
                        <th class="p-4">Type</th>
                        <th class="p-4 text-right">Amount</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4">Reference</th>
                        <th class="p-4 text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="p-10 text-center text-gray-400 text-sm font-bold">No MoMo transactions yet.</td></tr>
                    <?php else: foreach ($rows as $t):
                        $is_out = $t['type'] === 'disbursement';
                        $type_label = $is_out ? 'Payout' : 'Payment';
                        $type_icon  = $is_out ? 'fa-arrow-up text-momo' : 'fa-arrow-down text-green-500';
                        $amt_cls    = $is_out ? 'text-gray-900' : 'text-green-600';
                        $sign       = $is_out ? '-' : '+';
                        $st = $t['status'];
                        $st_cls = $st === 'SUCCESSFUL' ? 'bg-green-100 text-green-700' : ($st === 'FAILED' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700');
                        $sim = $t['simulated'] ? "<span class='text-[8px] font-black uppercase tracking-widest text-gray-400 ml-1' title='Simulated — add MoMo keys to go live'>(sim)</span>" : "";
                    ?>
                        <tr>
                            <td class="p-4 font-bold text-sm text-gray-900"><i class="fa-solid <?= $type_icon ?> mr-2"></i><?= $type_label ?><?= $t['order_id'] ? " <span class='text-gray-400 font-medium'>· order #{$t['order_id']}</span>" : '' ?></td>
                            <td class="p-4 text-right font-black <?= $amt_cls ?>"><?= $sign ?><?= number_format($t['amount']) ?> RWF</td>
                            <td class="p-4 text-center"><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?= $st_cls ?>"><?= $st ?></span><?= $sim ?></td>
                            <td class="p-4 text-[10px] font-mono text-gray-400"><?= htmlspecialchars(substr($t['reference_id'], 0, 13)) ?>…</td>
                            <td class="p-4 text-right text-xs text-gray-500 font-bold"><?= date('M j, H:i', strtotime($t['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <p class="text-center text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-6">
            <i class="fa-solid fa-shield-halved mr-1"></i> Powered by MTN Mobile Money
        </p>
    </main>
</body>
</html>
