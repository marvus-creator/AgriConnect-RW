<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AgriConnect Logistics Fleet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703', dark: '#0F172A' } } } }</script>
</head>
<body class="bg-gray-50 font-sans">
    
    <nav class="bg-dark text-white shadow-sm py-4 px-8 flex justify-between items-center sticky top-0 z-50">
        <a href="index.php" class="font-black text-xl flex items-center gap-2">
            <i class="fa-solid fa-truck-fast text-savannah"></i> AgriConnect <span class="text-gray-400 font-medium">Fleet</span>
        </a>
        <a href="index.php" class="text-sm font-bold text-gray-400 hover:text-white transition-colors">Return Home</a>
    </nav>

    <header class="bg-dark py-24 px-8 text-center relative overflow-hidden">
        <div class="max-w-3xl mx-auto relative z-10">
            <h1 class="text-5xl md:text-6xl font-black text-white mb-6">Drive for Rwanda. <br><span class="text-savannah">Earn on your schedule.</span></h1>
            <p class="text-lg text-gray-400 mb-10">Join the national logistics network connecting rural harvests to urban markets. Own a truck or van? Start earning today.</p>
            <a href="auth/register.php?role=Driver" class="bg-savannah text-dark px-10 py-4 rounded-full font-black text-lg hover:scale-105 transition-transform shadow-[0_0_20px_rgba(255,183,3,0.3)] inline-block">
                Apply to Drive
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-20">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-black text-gray-900">How the Fleet Works</h2>
            <div class="w-24 h-1 bg-savannah mx-auto mt-4 rounded-full"></div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 max-w-6xl mx-auto">
            
            <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 hover:-translate-y-3 hover:shadow-2xl transition-all duration-300 group text-center">
                <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-blue-600 transition-colors duration-300">
                    <i class="fa-solid fa-mobile-screen text-3xl text-blue-600 group-hover:text-white transition-colors duration-300"></i>
                </div>
                <h3 class="text-xl font-black mb-3 text-gray-900">1. Accept Jobs</h3>
                <p class="text-gray-500 font-medium">Open your Driver Portal and view farmers who need transport to the city.</p>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 hover:-translate-y-3 hover:shadow-2xl transition-all duration-300 group text-center">
                <div class="w-20 h-20 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-orange-500 transition-colors duration-300">
                    <i class="fa-solid fa-box-open text-3xl text-orange-500 group-hover:text-white transition-colors duration-300"></i>
                </div>
                <h3 class="text-xl font-black mb-3 text-gray-900">2. Pick Up Cargo</h3>
                <p class="text-gray-500 font-medium">Navigate to the farm, verify the goods, and update the status to "In-Transit".</p>
            </div>

            <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 hover:-translate-y-3 hover:shadow-2xl transition-all duration-300 group text-center">
                <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-green-600 transition-colors duration-300">
                    <i class="fa-solid fa-wallet text-3xl text-green-600 group-hover:text-white transition-colors duration-300"></i>
                </div>
                <h3 class="text-xl font-black mb-3 text-gray-900">3. Deliver & Get Paid</h3>
                <p class="text-gray-500 font-medium">Drop off the fresh produce to the buyer, mark it delivered, and get paid directly.</p>
            </div>

        </div>
    </main>
</body>
</html>