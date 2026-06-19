<?php
session_start();
require_once 'includes/db.php';

// 1. 🌍 NETWORK SCALE (User Counts)
$u_sql = mysqli_query($conn, "SELECT role, COUNT(*) as c FROM users GROUP BY role");
$network = ['Farmer' => 0, 'Buyer' => 0, 'Driver' => 0, 'Admin' => 0];
while($row = mysqli_fetch_assoc($u_sql)){
    $network[$row['role']] = $row['c'];
}

// 2. 📈 PLATFORM GDP (Total money successfully processed)
$gdp_sql = mysqli_query($conn, "SELECT SUM(total_price + delivery_fee) as total_gdp FROM orders WHERE order_status = 'Delivered'");
$platform_gdp = mysqli_fetch_assoc($gdp_sql)['total_gdp'] ?? 0;

// 3. 🚚 LOGISTICS VOLUME (Total KG of food moved)
$kg_sql = mysqli_query($conn, "SELECT SUM(o.total_price / p.price_per_kg) as total_kg FROM orders o JOIN products p ON o.product_id = p.product_id WHERE o.order_status = 'Delivered'");
$total_kg_moved = mysqli_fetch_assoc($kg_sql)['total_kg'] ?? 0;

// 4. 🌱 ACTIVE MARKET LIQUIDITY (Total value of food currently waiting to be sold)
$liq_sql = mysqli_query($conn, "SELECT SUM(quantity_kg * price_per_kg) as liquidity FROM products");
$market_liquidity = mysqli_fetch_assoc($liq_sql)['liquidity'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Market Overview | AgriConnect RW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703', dark: '#0A0F1C', panel: '#111827' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
    <style>
        body { background-color: #0A0F1C; color: #e2e8f0; }
        .grid-bg { background-image: linear-gradient(to right, #1e293b 1px, transparent 1px), linear-gradient(to bottom, #1e293b 1px, transparent 1px); background-size: 50px 50px; }
        .neon-text { text-shadow: 0 0 15px rgba(255, 183, 3, 0.5); }
    </style>
</head>
<body class="font-sans min-h-screen flex flex-col relative overflow-x-hidden">

    <div id="terminal-preloader" class="fixed inset-0 z-[99999] bg-[#0A0F1C] flex flex-col items-center justify-center transition-opacity duration-1000">
        <i class="fa-solid fa-server text-6xl text-savannah animate-pulse mb-6 drop-shadow-[0_0_15px_rgba(255,183,3,0.8)]"></i>
        <h2 class="text-2xl font-black text-white tracking-widest uppercase mb-2">Loading Market <span class="text-savannah">Overview</span></h2>
        <div class="w-64 h-1 bg-gray-800 rounded-full overflow-hidden mt-4 shadow-inner">
            <div id="term-bar" class="h-full bg-savannah w-0 transition-all duration-[1.5s] ease-out"></div>
        </div>
        <p class="text-xs text-gray-500 font-mono mt-6 animate-pulse tracking-[0.3em]">COMPILING NATIONAL COMMODITY INDEX...</p>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            setTimeout(() => { document.getElementById('term-bar').style.width = '100%'; }, 50);
            setTimeout(() => {
                const loader = document.getElementById('terminal-preloader');
                loader.style.opacity = '0';
                setTimeout(() => loader.remove(), 1000);
            }, 1800);
        });
    </script>
    <div class="absolute inset-0 grid-bg opacity-20 z-0 pointer-events-none"></div>

    <nav class="border-b border-gray-800 bg-dark/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3 group">
                <div class="w-10 h-10 bg-savannah/10 rounded-xl flex items-center justify-center border border-savannah/30 group-hover:bg-savannah/20 transition-all">
                    <i class="fa-solid fa-tractor text-savannah"></i>
                </div>
                <span class="font-black text-xl text-white tracking-tight">AgriConnect <span class="text-gray-500">Terminal</span></span>
            </a>
            
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest">
                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span>
                    Systems Live
                </div>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="bg-gray-800 hover:bg-gray-700 text-white px-5 py-2 rounded-lg text-sm font-bold transition-colors border border-gray-700">Dashboard</a>
                <?php else: ?>
                    <a href="auth/login.php" class="bg-savannah hover:bg-yellow-400 text-gray-900 px-5 py-2 rounded-lg text-sm font-black transition-colors shadow-[0_0_15px_rgba(255,183,3,0.3)]">Enter Platform</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="flex-grow max-w-7xl w-full mx-auto px-6 py-12 relative z-10">
        
        <div class="mb-12 text-center md:text-left">
            <h1 class="text-4xl md:text-5xl font-black text-white mb-2 tracking-tight">National Market Overview</h1>
            <p class="text-gray-400 font-medium">Real-time public analytics and commodity pricing indices.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            
            <div class="bg-panel p-8 rounded-[2rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-6 -bottom-6 text-savannah opacity-5 group-hover:scale-110 transition-transform duration-500"><i class="fa-solid fa-chart-line text-9xl"></i></div>
                <p class="text-xs font-black uppercase tracking-widest text-gray-500 mb-2 relative z-10">Platform GDP</p>
                <h2 class="text-4xl font-black text-savannah neon-text relative z-10"><?= number_format($platform_gdp) ?> <span class="text-lg text-gray-400">RWF</span></h2>
                <p class="text-[10px] text-gray-500 mt-2 font-bold relative z-10">Total transacted volume</p>
            </div>

            <div class="bg-panel p-8 rounded-[2rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-6 -bottom-6 text-green-500 opacity-5 group-hover:scale-110 transition-transform duration-500"><i class="fa-solid fa-weight-scale text-9xl"></i></div>
                <p class="text-xs font-black uppercase tracking-widest text-gray-500 mb-2 relative z-10">Food Distributed</p>
                <h2 class="text-4xl font-black text-green-400 relative z-10"><?= number_format($total_kg_moved) ?> <span class="text-lg text-gray-400">KG</span></h2>
                <p class="text-[10px] text-gray-500 mt-2 font-bold relative z-10">Farm-to-table logistics</p>
            </div>

            <div class="bg-panel p-8 rounded-[2rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-6 -bottom-6 text-blue-500 opacity-5 group-hover:scale-110 transition-transform duration-500"><i class="fa-solid fa-users text-9xl"></i></div>
                <p class="text-xs font-black uppercase tracking-widest text-gray-500 mb-2 relative z-10">Active Network</p>
                <h2 class="text-4xl font-black text-blue-400 relative z-10"><?= number_format($network['Farmer'] + $network['Buyer'] + $network['Driver']) ?> <span class="text-lg text-gray-400">Users</span></h2>
                <div class="flex gap-3 mt-2 relative z-10">
                    <span class="text-[10px] font-bold text-gray-400"><i class="fa-solid fa-seedling text-green-400 mr-1"></i><?= $network['Farmer'] ?></span>
                    <span class="text-[10px] font-bold text-gray-400"><i class="fa-solid fa-cart-shopping text-blue-400 mr-1"></i><?= $network['Buyer'] ?></span>
                    <span class="text-[10px] font-bold text-gray-400"><i class="fa-solid fa-truck text-orange-400 mr-1"></i><?= $network['Driver'] ?></span>
                </div>
            </div>

            <div class="bg-panel p-8 rounded-[2rem] border border-gray-800 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-6 -bottom-6 text-purple-500 opacity-5 group-hover:scale-110 transition-transform duration-500"><i class="fa-solid fa-droplet text-9xl"></i></div>
                <p class="text-xs font-black uppercase tracking-widest text-gray-500 mb-2 relative z-10">Market Liquidity</p>
                <h2 class="text-4xl font-black text-purple-400 relative z-10"><?= number_format($market_liquidity) ?> <span class="text-lg text-gray-400">RWF</span></h2>
                <p class="text-[10px] text-gray-500 mt-2 font-bold relative z-10">Value of listed harvests</p>
            </div>

        </div>

        <div class="bg-panel rounded-[2rem] border border-gray-800 shadow-2xl overflow-hidden">
            <div class="p-8 border-b border-gray-800 flex justify-between items-end bg-gradient-to-b from-gray-800/50 to-transparent">
                <div>
                    <h3 class="text-2xl font-black text-white flex items-center gap-3">
                        <i class="fa-solid fa-server text-savannah"></i> Live Commodity Index
                    </h3>
                    <p class="text-sm text-gray-400 mt-1">Aggregated real-time pricing data across all active Rwandan districts.</p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-900/50 text-xs uppercase text-gray-500 font-black tracking-widest border-b border-gray-800">
                        <tr>
                            <th class="p-6">Commodity</th>
                            <th class="p-6 text-right">Lowest Listing</th>
                            <th class="p-6 text-right">Highest Listing</th>
                            <th class="p-6 text-right bg-gray-800/30 text-white">National Average</th>
                            <th class="p-6 text-center">Total Market Supply</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php
                        $index_sql = "SELECT title, 
                                      MIN(price_per_kg) as low_price, 
                                      MAX(price_per_kg) as high_price, 
                                      AVG(price_per_kg) as avg_price, 
                                      SUM(quantity_kg) as total_supply 
                                      FROM products 
                                      GROUP BY title 
                                      ORDER BY total_supply DESC LIMIT 10";
                        $index_res = mysqli_query($conn, $index_sql);
                        
                        if(mysqli_num_rows($index_res) > 0) {
                            while($crop = mysqli_fetch_assoc($index_res)) {
                                echo "<tr class='hover:bg-gray-800/50 transition-colors'>
                                    <td class='p-6 font-black text-gray-200 text-lg'>{$crop['title']}</td>
                                    <td class='p-6 text-right font-bold text-green-400'>".number_format($crop['low_price'])." <span class='text-[10px] text-gray-500'>RWF</span></td>
                                    <td class='p-6 text-right font-bold text-red-400'>".number_format($crop['high_price'])." <span class='text-[10px] text-gray-500'>RWF</span></td>
                                    <td class='p-6 text-right font-black text-savannah bg-gray-800/30 text-xl'>".number_format($crop['avg_price'])." <span class='text-[10px] text-gray-500'>RWF/KG</span></td>
                                    <td class='p-6 text-center'>
                                        <span class='bg-gray-800 text-gray-300 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest border border-gray-700'>
                                            ".number_format($crop['total_supply'])." KG
                                        </span>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='p-12 text-center text-gray-500 font-bold'>No market data available yet. Waiting for farmers to list crops.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-6 bg-gray-900/50 border-t border-gray-800 text-center">
                <p class="text-[10px] font-bold text-gray-600 uppercase tracking-widest"><i class="fa-solid fa-shield-halved mr-1"></i> Data aggregated securely via AgriConnect Escrow System</p>
            </div>
        </div>

    </main>

</body>
</html>