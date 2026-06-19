<?php
// ai_advisor.php — returns the AI Advisor panel HTML for the logged-in farmer.
session_start();
require_once 'includes/db.php';
require_once 'includes/ai.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Farmer') {
    http_response_code(403);
    echo "<div class='text-center py-6 text-gray-400 font-bold'>Not authorised.</div>";
    exit();
}

$uid = (int) $_SESSION['user_id'];

// Farmer's district
$urow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT district FROM users WHERE user_id = $uid"));
$district = $urow['district'] ?? 'Kigali';

// Farmer's listed products
$products = [];
$pres = mysqli_query($conn, "SELECT title, quantity_kg, price_per_kg, harvest_date FROM products WHERE farmer_id = $uid");
while ($r = mysqli_fetch_assoc($pres)) {
    $products[] = [
        'title' => $r['title'],
        'quantity_kg' => (float) $r['quantity_kg'],
        'price_per_kg' => (int) $r['price_per_kg'],
        'harvest_date' => $r['harvest_date'],
    ];
}

// Market prices
$market = [];
$mres = mysqli_query($conn, "SELECT crop_name, district_name, avg_price, trend FROM market_prices");
while ($r = mysqli_fetch_assoc($mres)) {
    $market[] = [
        'crop_name' => $r['crop_name'],
        'district_name' => $r['district_name'],
        'avg_price' => (int) $r['avg_price'],
        'trend' => $r['trend'],
    ];
}

// Platform demand for this farmer's crops (last 30 days)
$demand = [];
$dres = mysqli_query($conn, "SELECT p.title, COUNT(*) AS orders_30d
                             FROM orders o JOIN products p ON o.product_id = p.product_id
                             WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                             GROUP BY p.title");
while ($r = mysqli_fetch_assoc($dres)) {
    $demand[] = ['title' => $r['title'], 'orders_30d' => (int) $r['orders_30d']];
}

$advice = agri_market_advice($products, $market, $demand, $district);

// ---- Render ----
$verdict_style = [
    'sell_now'    => ['bg-orange-100 text-orange-700', 'fa-bolt', 'Sell Now'],
    'hold'        => ['bg-gray-100 text-gray-600', 'fa-pause', 'Hold'],
    'raise_price' => ['bg-green-100 text-green-700', 'fa-arrow-trend-up', 'Raise Price'],
    'lower_price' => ['bg-red-100 text-red-700', 'fa-arrow-trend-down', 'Lower Price'],
];

$badge = $advice['source'] === 'ai'
    ? "<span class='text-[9px] font-black uppercase tracking-widest bg-akagera text-savannah px-2 py-1 rounded-full'><i class='fa-solid fa-robot mr-1'></i>Claude AI</span>"
    : "<span class='text-[9px] font-black uppercase tracking-widest bg-gray-200 text-gray-600 px-2 py-1 rounded-full' title='Add an API key for full AI analysis'><i class='fa-solid fa-calculator mr-1'></i>Smart Estimate</span>";

echo "<div class='flex items-center justify-between mb-4'>
        <p class='text-sm text-gray-600 font-medium pr-3'>" . htmlspecialchars($advice['overall_summary']) . "</p>
        {$badge}
      </div>";

if (!empty($advice['note'])) {
    echo "<div class='text-[10px] text-gray-400 font-bold mb-3'>" . htmlspecialchars($advice['note']) . "</div>";
}

if (empty($advice['products'])) {
    echo "<div class='text-center py-6 text-gray-400 font-bold text-sm'>List some produce to unlock pricing advice.</div>";
} else {
    echo "<div class='space-y-3'>";
    foreach ($advice['products'] as $p) {
        $vs = $verdict_style[$p['verdict']] ?? $verdict_style['hold'];
        $cur = number_format($p['current_price_per_kg']);
        $rec = number_format($p['recommended_price_per_kg']);
        $delta = $p['recommended_price_per_kg'] - $p['current_price_per_kg'];
        $price_line = $delta == 0
            ? "<span class='text-gray-500'>Keep at {$cur} RWF/kg</span>"
            : "<span class='text-gray-400 line-through'>{$cur}</span> <i class='fa-solid fa-arrow-right text-gray-300 mx-1'></i> <span class='font-black text-akagera'>{$rec} RWF/kg</span>";

        echo "<div class='bg-gray-50 border border-gray-100 rounded-2xl p-4'>
                <div class='flex items-center justify-between mb-1'>
                    <h4 class='font-black text-gray-900 text-sm'>" . htmlspecialchars($p['title']) . "</h4>
                    <span class='text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-full {$vs[0]}'><i class='fa-solid {$vs[1]} mr-1'></i>{$vs[2]}</span>
                </div>
                <div class='text-xs font-bold mb-1'>{$price_line}</div>
                <p class='text-xs text-gray-500 font-medium'>" . htmlspecialchars($p['reason']) . "</p>
              </div>";
    }
    echo "</div>";
}

if (!empty($advice['demand_hotspots'])) {
    echo "<div class='mt-5 pt-4 border-t border-gray-100'>
            <p class='text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2'><i class='fa-solid fa-fire text-orange-400 mr-1'></i> Demand Hotspots</p>
            <div class='flex flex-wrap gap-2'>";
    foreach ($advice['demand_hotspots'] as $h) {
        echo "<span class='text-[10px] font-bold bg-orange-50 text-orange-700 px-2.5 py-1.5 rounded-lg border border-orange-100' title='" . htmlspecialchars($h['note']) . "'>
                <i class='fa-solid fa-location-dot mr-1'></i>" . htmlspecialchars($h['crop']) . " · " . htmlspecialchars($h['district']) . "
              </span>";
    }
    echo "</div></div>";
}
