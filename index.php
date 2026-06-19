<?php
session_start();
// require_once 'includes/db.php'; // Uncomment when DB is ready

// Safely determine the dashboard link at the very top so PHP doesn't choke in the HTML
$dash_link = 'dashboard.php';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    $dash_link = 'admin_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriConnect Rwanda | Professional Marketplace</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703', dark: '#121212' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
    <style type="text/tailwindcss">
        @layer utilities { .glass { @apply bg-white/80 backdrop-blur-md border-b border-white/20 shadow-sm; } .reveal { @apply opacity-0 translate-y-10 transition-all duration-700 ease-out; } .reveal.active { @apply opacity-100 translate-y-0; } }
        .ticker-track { display: inline-flex; white-space: nowrap; animation: ticker 30s linear infinite; }
        @keyframes ticker { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        .ticker-track:hover { animation-play-state: paused; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased overflow-x-hidden">

    <div id="agri-preloader" class="fixed inset-0 z-[99999] bg-[#0F172A] flex flex-col items-center justify-center transition-all duration-[1200ms] ease-[cubic-bezier(0.87,0,0.13,1)]">
        
        <div class="absolute inset-0 overflow-hidden flex items-center justify-center pointer-events-none">
            <div class="w-[500px] h-[500px] bg-akagera/30 rounded-full blur-[120px] animate-[pulse_3s_infinite]"></div>
            <div class="absolute w-[300px] h-[300px] bg-savannah/20 rounded-full blur-[90px] animate-[pulse_2s_infinite] delay-75"></div>
        </div>

        <div class="relative z-10 flex flex-col items-center">
            <div class="relative w-36 h-36 flex items-center justify-center mb-8">
                <div class="absolute inset-0 border-4 border-dashed border-savannah/30 rounded-full animate-[spin_5s_linear_infinite]"></div>
                <div class="absolute inset-3 border-4 border-t-savannah border-r-transparent border-b-akagera border-l-transparent rounded-full animate-[spin_1.5s_ease-in-out_infinite]"></div>
                <i class="fa-solid fa-tractor text-5xl text-white animate-bounce shadow-savannah drop-shadow-[0_0_20px_rgba(255,183,3,0.8)]"></i>
            </div>
            
            <h1 class="text-4xl font-black text-white tracking-widest uppercase flex items-center gap-2 mb-3 drop-shadow-2xl">
                Agri<span class="text-savannah">Connect</span>
            </h1>
            <p class="text-akagera text-[10px] font-black uppercase tracking-[0.4em] bg-white px-4 py-1.5 rounded-full animate-pulse shadow-[0_0_20px_rgba(27,67,50,0.6)]">
                Harvesting Data...
            </p>
            
            <div class="w-64 h-1.5 bg-gray-800 rounded-full mt-12 overflow-hidden shadow-inner">
                <div class="h-full bg-gradient-to-r from-akagera to-savannah w-0 relative" id="loading-bar">
                    <div class="absolute top-0 right-0 bottom-0 w-4 bg-white/50 blur-[2px]"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Cinematic Preloader Logic
        document.addEventListener("DOMContentLoaded", () => {
            const preloader = document.getElementById('agri-preloader');
            const loadingBar = document.getElementById('loading-bar');
            
            // 1. Instantly start filling the loading bar smoothly
            setTimeout(() => {
                loadingBar.style.transition = 'width 1.6s cubic-bezier(0.4, 0, 0.2, 1)';
                loadingBar.style.width = '100%';
            }, 50);

            // 2. Wait for the bar to fill, then slide the whole preloader up into the sky
            setTimeout(() => {
                preloader.style.transform = 'translateY(-100vh)';
                preloader.style.opacity = '0';
                
                // 3. Delete the preloader from the website code so it doesn't block clicks
                setTimeout(() => {
                    preloader.remove();
                }, 1200);
            }, 1800); // Wait 1.8 seconds before sliding away
        });
    </script>
    <div class="bg-akagera text-white text-sm py-2 overflow-hidden border-b-2 border-savannah relative z-50">
        <div class="ticker-track font-semibold">
            <span class="mx-8"><i class="fa-solid fa-leaf text-savannah mr-2"></i>Musanze Potatoes: 450 RWF/kg <span class="text-green-400 ml-1"><i class="fa-solid fa-arrow-trend-up"></i></span></span>
            <span class="mx-8"><i class="fa-solid fa-seedling text-savannah mr-2"></i>Nyagatare Maize: 300 RWF/kg <span class="text-red-400 ml-1"><i class="fa-solid fa-arrow-trend-down"></i></span></span>
            <span class="mx-8"><i class="fa-solid fa-mug-hot text-savannah mr-2"></i>Huye Coffee: 1,200 RWF/kg <span class="text-gray-300 ml-1">- Stable</span></span>
            <span class="mx-8"><i class="fa-solid fa-apple-whole text-savannah mr-2"></i>Rubavu Bananas: 250 RWF/kg <span class="text-green-400 ml-1"><i class="fa-solid fa-arrow-trend-up"></i></span></span>
        </div>
    </div>

    <nav class="glass fixed w-full z-40 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="index.php" class="flex-shrink-0 flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-tractor text-3xl text-akagera"></i>
                    <span class="font-extrabold text-2xl text-akagera tracking-tight">AgriConnect <span class="text-savannah">RW</span></span>
                </a>
                <div class="hidden md:flex space-x-8 items-center">
                    <a href="overview.php" class="text-gray-600 hover:text-akagera font-semibold transition-colors">Market Overview</a>
                    <a href="buy.php" class="text-gray-600 hover:text-akagera font-semibold transition-colors">Buy Produce</a>
                    <a href="logistics.php" class="text-gray-600 hover:text-akagera font-semibold transition-colors">Logistics</a>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo $dash_link; ?>" class="bg-akagera text-white px-6 py-2.5 rounded-full font-bold shadow-lg hover:bg-savannah hover:text-akagera transition-all duration-300 transform hover:-translate-y-1">
                            My Dashboard <i class="fa-solid fa-arrow-right ml-2"></i>
                        </a>
                    <?php else: ?>
                        <a href="auth/login.php" class="bg-akagera text-white px-6 py-2.5 rounded-full font-bold shadow-lg hover:bg-savannah hover:text-akagera transition-all duration-300 transform hover:-translate-y-1">
                            Get Started <i class="fa-solid fa-arrow-right ml-2"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden bg-akagera">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#FFB703 2px, transparent 2px); background-size: 30px 30px;"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center reveal">
            <span class="inline-block py-1 px-3 rounded-full bg-savannah/20 text-savannah font-semibold text-sm mb-6 border border-savannah/50">
                <i class="fa-solid fa-check-circle mr-1"></i> Empowering Vision 2050
            </span>
            <h1 class="text-5xl md:text-7xl font-extrabold text-white mb-6 leading-tight">
                Digitizing Rwanda's <br> <span class="text-transparent bg-clip-text bg-gradient-to-r from-savannah to-yellow-200">Agricultural Future.</span>
            </h1>
            <p class="mt-4 text-xl text-gray-300 max-w-3xl mx-auto mb-10 font-light">
                Directly connecting rural farmers to urban buyers. No middlemen. Transparent pricing. Automated logistics right to your doorstep.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="auth/register.php?role=Farmer" class="bg-savannah text-akagera px-8 py-4 rounded-full font-bold text-lg shadow-[0_0_20px_rgba(255,183,3,0.4)] hover:shadow-[0_0_30px_rgba(255,183,3,0.7)] hover:-translate-y-1 transition-all duration-300">
                    Be A Member!
                </a>
                <a href="buy.php" class="bg-white/10 text-white border border-white/30 px-8 py-4 rounded-full font-bold text-lg hover:bg-white hover:text-akagera transition-all duration-300">
                    Explore Marketplace
                </a>
            </div>
        </div>
    </section>

    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 reveal">
                <h2 class="text-3xl md:text-4xl font-bold text-akagera">How The Ecosystem Works</h2>
                <div class="w-24 h-1 bg-savannah mx-auto mt-4 rounded-full"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 hover:-translate-y-3 hover:shadow-2xl transition-all duration-300 group reveal text-center">
                    <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-akagera transition-colors duration-300"><i class="fa-solid fa-seedling text-3xl text-akagera group-hover:text-white transition-colors"></i></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">1. Farmers List Produce</h3>
                    <p class="text-gray-500">Farmers upload their expected harvest, quantity, and set transparent prices visible to the national network.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 hover:-translate-y-3 hover:shadow-2xl transition-all duration-300 group reveal text-center" style="transition-delay: 100ms;">
                    <div class="w-20 h-20 bg-yellow-50 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-savannah transition-colors duration-300"><i class="fa-solid fa-hand-holding-dollar text-3xl text-savannah group-hover:text-akagera transition-colors"></i></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">2. Buyers Secure Orders</h3>
                    <p class="text-gray-500">Restaurants, schools, and markets purchase directly through our secure escrow payment system.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100 hover:-translate-y-3 hover:shadow-2xl transition-all duration-300 group reveal text-center" style="transition-delay: 200ms;">
                    <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-blue-600 transition-colors duration-300"><i class="fa-solid fa-truck-fast text-3xl text-blue-600 group-hover:text-white transition-colors"></i></div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">3. Smart Logistics</h3>
                    <p class="text-gray-500">Verified local drivers bid on the delivery route, ensuring fast and efficient farm-to-table transportation.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-gray-400 py-12 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center">
            <div class="mb-6 md:mb-0 text-center md:text-left">
                <div class="flex items-center gap-2 justify-center md:justify-start mb-2">
                    <i class="fa-solid fa-tractor text-2xl text-savannah"></i>
                    <span class="font-bold text-xl text-white">AgriConnect <span class="text-savannah">RW</span></span>
                </div>
                <p class="text-sm">Building the future of African Agriculture.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const reveals = document.querySelectorAll('.reveal');
            const revealOnScroll = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('active'); observer.unobserve(entry.target); } });
            }, { threshold: 0.15, rootMargin: "0px 0px -50px 0px" });
            reveals.forEach(reveal => revealOnScroll.observe(reveal));
        });
    </script>
</body>
</html>