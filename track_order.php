<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/momo.php';
require_once 'includes/geo.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Buyer') {
    header("Location: dashboard.php");
    exit();
}

$buyer_id = (int) $_SESSION['user_id'];

// 🚀 PROCESS MOMO PAYMENT FROM THE TRACKING SCREEN (real MoMo collection)
if (isset($_POST['release_payment'])) {
    $release_oid = (int) $_POST['momo_order_id'];

    $o_stmt = mysqli_prepare($conn, "SELECT o.total_price, o.delivery_fee, u.phone_number
                                     FROM orders o JOIN users u ON u.user_id = o.buyer_id
                                     WHERE o.order_id = ? AND o.buyer_id = ? AND o.order_status != 'Delivered'");
    mysqli_stmt_bind_param($o_stmt, "ii", $release_oid, $buyer_id);
    mysqli_stmt_execute($o_stmt);
    $ord = mysqli_fetch_assoc(mysqli_stmt_get_result($o_stmt));

    if ($ord) {
        $amount = (int) $ord['total_price'] + (int) $ord['delivery_fee'];
        $pay = momo_request_to_pay($amount, $ord['phone_number'], 'AgriConnect order #' . $release_oid);
        momo_log($conn, $pay, 'collection', $buyer_id, $release_oid, $amount, $ord['phone_number']);

        if ($pay['status'] === 'SUCCESSFUL') {
            $d_stmt = mysqli_prepare($conn, "UPDATE orders SET order_status = 'Delivered' WHERE order_id = ? AND buyer_id = ?");
            mysqli_stmt_bind_param($d_stmt, "ii", $release_oid, $buyer_id);
            mysqli_stmt_execute($d_stmt);
            header("Location: dashboard.php?msg=payment_released");
            exit();
        }
    }
    header("Location: dashboard.php?msg=payment_failed");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$order_id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch massive combined query for tracking
$query = "SELECT o.*, p.title, p.image, 
          f.full_name as farmer_name, f.district as f_district, 
          b.district as b_district, 
          d.full_name as driver_name, d.phone_number as driver_phone, d.profile_pic as driver_pic,
          (SELECT AVG(stars) FROM driver_ratings WHERE driver_id = d.user_id) as driver_rating
          FROM orders o 
          JOIN products p ON o.product_id = p.product_id 
          JOIN users f ON p.farmer_id = f.user_id 
          JOIN users b ON o.buyer_id = b.user_id 
          LEFT JOIN users d ON o.driver_id = d.user_id 
          WHERE o.order_id = '$order_id' AND o.buyer_id = '$buyer_id'";

$result = mysqli_query($conn, $query);
if(mysqli_num_rows($result) == 0) {
    die("Order not found.");
}
$order = mysqli_fetch_assoc($result);

$status = $order['order_status'];
$total_to_pay = $order['total_price'] + $order['delivery_fee'];

// Driver formatting
$d_rating = $order['driver_rating'] ? number_format($order['driver_rating'], 1) : 'NEW';
$d_avatar = $order['driver_pic'] ? "<img src='uploads/profiles/{$order['driver_pic']}' class='w-12 h-12 rounded-full object-cover'>" : "<div class='w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-500 font-bold text-lg'>".strtoupper(substr($order['driver_name'],0,1))."</div>";

// Logistics geo — real coordinates, distance and ETA from districts
[$pickup_lat, $pickup_lng]   = geo_point($order['f_district']);
[$drop_lat, $drop_lng]       = geo_point($order['b_district']);
$route_km   = geo_distance_km($pickup_lat, $pickup_lng, $drop_lat, $drop_lng);
$eta_min    = geo_eta_min($route_km);
$eta_label  = geo_eta_label($eta_min);
$has_live_gps = ($order['driver_lat'] !== null && $order['driver_lng'] !== null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Track #<?= $order_id ?> | AgriConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703', momo: '#ffcc00' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
    <style>
        /* The Animated Radar Map CSS */
        .grid-map {
            background-image: linear-gradient(to right, #1e293b 1px, transparent 1px), linear-gradient(to bottom, #1e293b 1px, transparent 1px);
            background-size: 40px 40px;
            background-color: #0f172a;
        }
        @keyframes drive {
            0% { left: 10%; top: 20%; transform: rotate(15deg); }
            50% { left: 50%; top: 60%; transform: rotate(45deg); }
            100% { left: 80%; top: 75%; transform: rotate(10deg); }
        }
        .truck-marker { animation: drive 15s ease-in-out infinite alternate; }
    </style>
</head>
<body class="bg-gray-50 font-sans min-h-screen flex flex-col">

    <nav class="bg-white shadow-sm h-16 flex items-center px-8 justify-between sticky top-0 z-50">
        <a href="dashboard.php" class="text-gray-400 hover:text-akagera transition-colors font-bold text-sm">
            <i class="fa-solid fa-arrow-left mr-2"></i> Dashboard
        </a>
        <h1 class="font-black text-xl text-gray-900 tracking-tight">Logistics <span class="text-blue-500">Radar</span></h1>
    </nav>

    <main class="flex-grow max-w-6xl w-full mx-auto p-4 py-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <div class="bg-white rounded-[2rem] shadow-2xl border border-gray-200 overflow-hidden relative h-[500px] lg:h-auto min-h-[480px] flex flex-col">
            <div class="p-5 flex justify-between items-center border-b border-gray-100 z-[500] bg-white">
                <div>
                    <div class="bg-blue-50 border border-blue-200 text-blue-600 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-2 mb-1">
                        <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span></span>
                        <?= $status == 'In-Transit' ? 'Live GPS' : 'Route Map' ?>
                    </div>
                    <h2 class="text-gray-900 font-bold text-base"><?= $order['f_district'] ?> <i class="fa-solid fa-arrow-right mx-1 text-gray-300 text-xs"></i> <?= $order['b_district'] ?></h2>
                </div>
                <div class="text-right">
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Distance</p>
                    <p class="font-black text-akagera text-lg"><?= number_format($route_km, 1) ?> <span class="text-xs text-gray-400">km</span></p>
                </div>
            </div>
            <div id="logimap" class="flex-grow w-full z-0"></div>
        </div>

        <div class="space-y-6">
            
            <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100">
                <h3 class="font-black text-gray-900 mb-6 text-lg">Order Status</h3>
                <div class="relative pl-6 space-y-8">
                    <div class="absolute left-[11px] top-2 bottom-2 w-0.5 bg-gray-200"></div>
                    
                    <div class="relative z-10 flex items-center gap-4">
                        <div class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center text-white text-[10px] shadow-md border-4 border-white -ml-3"><i class="fa-solid fa-check"></i></div>
                        <div><p class="font-bold text-gray-900 text-sm">Order Placed</p><p class="text-xs text-gray-500 font-medium">Farmer received request.</p></div>
                    </div>

                    <div class="relative z-10 flex items-center gap-4">
                        <div class="w-6 h-6 rounded-full <?= ($status == 'Accepted' || $status == 'In-Transit' || $status == 'Delivered') ? 'bg-green-500' : 'bg-gray-200' ?> flex items-center justify-center text-white text-[10px] shadow-md border-4 border-white -ml-3"><i class="fa-solid fa-check"></i></div>
                        <div><p class="font-bold <?= ($status == 'Accepted' || $status == 'In-Transit' || $status == 'Delivered') ? 'text-gray-900' : 'text-gray-400' ?> text-sm">Farmer Accepted</p></div>
                    </div>

                    <div class="relative z-10 flex items-center gap-4">
                        <div class="w-6 h-6 rounded-full <?= ($status == 'In-Transit' || $status == 'Delivered') ? 'bg-blue-500 animate-pulse' : 'bg-gray-200' ?> flex items-center justify-center text-white text-[10px] shadow-md border-4 border-white -ml-3"><i class="fa-solid fa-truck"></i></div>
                        <div><p class="font-bold <?= ($status == 'In-Transit' || $status == 'Delivered') ? 'text-blue-600' : 'text-gray-400' ?> text-sm">In Transit</p><p class="text-xs text-gray-500 font-medium">Driver is on the way.</p></div>
                    </div>

                    <div class="relative z-10 flex items-center gap-4">
                        <div class="w-6 h-6 rounded-full <?= ($status == 'Delivered') ? 'bg-green-500' : 'bg-gray-200' ?> flex items-center justify-center text-white text-[10px] shadow-md border-4 border-white -ml-3"><i class="fa-solid fa-handshake"></i></div>
                        <div><p class="font-bold <?= ($status == 'Delivered') ? 'text-green-600' : 'text-gray-400' ?> text-sm">Delivered & Paid</p></div>
                    </div>
                </div>
            </div>

            <?php if($order['driver_id']): ?>
                <div class="bg-gradient-to-br from-orange-50 to-white p-6 rounded-[2rem] shadow-sm border border-orange-100 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <?= $d_avatar ?>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-orange-500 mb-0.5">Assigned Driver</p>
                            <h3 class="font-bold text-gray-900 text-lg"><?= $order['driver_name'] ?></h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="bg-white text-orange-600 text-xs font-bold px-2 py-0.5 rounded-md shadow-sm border border-orange-100"><i class="fa-solid fa-star text-yellow-400 mr-1"></i> <?= $d_rating ?></span>
                                <span class="text-xs font-bold text-gray-500"><i class="fa-solid fa-phone mr-1"></i> <?= $order['driver_phone'] ?></span>
                            </div>
                        </div>
                    </div>
                    <a href="chat.php?user=<?= $order['driver_id'] ?>" class="h-12 w-12 bg-white rounded-full flex items-center justify-center text-blue-500 shadow-md hover:scale-110 transition-transform"><i class="fa-solid fa-comment-dots text-xl"></i></a>
                </div>
            <?php endif; ?>

            <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-gray-100 text-center">
                <?php if($status == 'In-Transit'): ?>
                    <p class="text-xs font-black uppercase tracking-widest text-gray-400 mb-2">ETA · <?= number_format($route_km, 1) ?> km away</p>
                    <h2 class="text-4xl font-black text-gray-900 mb-8 tracking-tighter" id="eta-timer">~<?= $eta_label ?></h2>
                    
                    <div class="bg-gray-50 p-4 rounded-xl mb-6 text-left border border-gray-100">
                        <div class="flex justify-between items-center text-sm font-bold text-gray-600 mb-2"><span>Crop & Delivery</span><span><?= number_format($total_to_pay) ?> RWF</span></div>
                        <p class="text-[10px] text-gray-400 font-medium">When the driver arrives, click below to authorize the escrow release.</p>
                    </div>

                    <button onclick="triggerMoMo()" class="w-full bg-momo text-[#004b50] font-black text-lg py-4 rounded-2xl hover:scale-105 transition-all shadow-[0_0_20px_rgba(255,204,0,0.4)] animate-pulse flex justify-center items-center gap-2">
                        Pay & Release Funds <i class="fa-solid fa-unlock-keyhole"></i>
                    </button>
                <?php elseif($status == 'Delivered'): ?>
                    <div class="text-green-500 mb-4"><i class="fa-solid fa-circle-check text-6xl"></i></div>
                    <h2 class="text-2xl font-black text-gray-900 mb-2">Transaction Complete</h2>
                    <p class="text-sm text-gray-500 font-medium">Thank you for using AgriConnect!</p>
                <?php else: ?>
                    <div class="text-gray-300 mb-4"><i class="fa-regular fa-clock text-6xl"></i></div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Awaiting Dispatch</h2>
                    <p class="text-sm text-gray-500 font-medium">Tracking and payment will unlock when the driver picks up your order.</p>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <div id="momoModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center z-[100] transition-opacity flex-col px-4">
        
        <div id="momoInitScreen" class="bg-[#004b50] rounded-[2rem] p-8 shadow-2xl relative overflow-hidden w-full max-w-sm border border-gray-800">
            <div class="absolute top-0 right-0 w-32 h-32 bg-momo rounded-bl-full opacity-20 pointer-events-none"></div>
            <div class="relative z-10 text-white">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-black mb-1 text-momo">Mobile Money</h2>
                        <p class="text-[10px] text-gray-300 font-bold uppercase tracking-widest">Pay on Delivery</p>
                    </div>
                    <button onclick="cancelMoMo()" class="text-white/50 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>

                <div class="bg-white/10 p-4 rounded-xl border border-white/20 mb-6">
                    <p class="text-[10px] uppercase font-black text-gray-400 tracking-widest mb-1">Total Due Now</p>
                    <p class="text-3xl font-black text-white mb-2"><?= number_format($total_to_pay) ?> <span class="text-sm">RWF</span></p>
                    <p class="text-xs text-momo font-medium"><?= number_format($order['total_price']) ?> RWF (Crop) + <?= number_format($order['delivery_fee']) ?> RWF (Driver)</p>
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">MTN Phone Number</label>
                    <div class="flex items-center bg-white/10 rounded-xl border border-white/20 p-1 focus-within:border-momo transition-colors">
                        <span class="pl-4 pr-2 font-bold text-gray-300">+250</span>
                        <input type="text" id="momoPhone" placeholder="78X XXX XXX" class="w-full bg-transparent p-3 text-white font-bold outline-none placeholder-gray-500 tracking-wider">
                    </div>
                </div>

                <button type="button" onclick="startMoMoProcess()" class="w-full bg-momo text-[#004b50] font-black py-4 rounded-2xl hover:bg-yellow-400 transition-all transform active:scale-95 flex justify-center items-center gap-2">
                    Authorize Payment <i class="fa-solid fa-lock"></i>
                </button>
            </div>
        </div>

        <div id="momoProcessing" class="hidden text-center flex-col items-center">
            <div class="w-16 h-16 border-4 border-momo border-t-transparent rounded-full animate-spin mb-4 shadow-[0_0_15px_#ffcc00]"></div>
            <h2 class="text-2xl font-black text-white mb-2">Connecting to MTN...</h2>
            <p class="text-gray-400 font-bold">Please wait, initiating USSD push.</p>
        </div>

        <div id="momoPinScreen" class="hidden bg-gray-100 rounded-3xl w-72 overflow-hidden shadow-2xl border-4 border-gray-800">
            <div class="bg-gray-800 text-white text-center py-2 text-[10px] font-bold flex justify-between px-4">
                <span>09:41</span>
                <span class="flex gap-1"><i class="fa-solid fa-signal"></i> <i class="fa-solid fa-wifi"></i> <i class="fa-solid fa-battery-full"></i></span>
            </div>
            <div class="p-6 text-center">
                <h3 class="font-black text-gray-900 text-lg leading-tight mb-2">Confirm Payment</h3>
                <p class="text-sm text-gray-600 font-medium mb-6">Pay <b class="text-gray-900"><?= number_format($total_to_pay) ?></b> RWF to AgriConnect.</p>
                <input type="password" id="momoPin" maxlength="5" placeholder="* * * * *" class="w-full text-center text-2xl tracking-[0.5em] font-black p-3 bg-white border border-gray-300 rounded-xl outline-none focus:border-momo mb-4">
                <div class="flex gap-2">
                    <button onclick="cancelMoMo()" class="flex-1 py-3 bg-gray-200 text-gray-700 font-bold rounded-xl text-sm">Cancel</button>
                    <button onclick="finalizePayment()" class="flex-1 py-3 bg-momo text-gray-900 font-black rounded-xl text-sm shadow-md">Send</button>
                </div>
            </div>
        </div>

        <form id="releaseForm" method="POST" class="hidden">
            <input type="hidden" name="momo_order_id" value="<?= $order_id ?>">
            <input type="hidden" name="release_payment" value="1">
        </form>
    </div>

    <script>
        // ---- Real Logistics Map (Leaflet + OpenStreetMap) ----
        const LOGI = {
            pickup: [<?= $pickup_lat ?>, <?= $pickup_lng ?>],
            drop:   [<?= $drop_lat ?>, <?= $drop_lng ?>],
            status: <?= json_encode($status) ?>,
            orderId: <?= (int)$order_id ?>,
            driver: <?= $has_live_gps ? '['.$order['driver_lat'].','.$order['driver_lng'].']' : 'null' ?>,
            etaMin: <?= (int)$eta_min ?>,
        };

        function haversineKm(a, b) {
            const R = 6371, dLat = (b[0]-a[0])*Math.PI/180, dLng = (b[1]-a[1])*Math.PI/180;
            const x = Math.sin(dLat/2)**2 + Math.cos(a[0]*Math.PI/180)*Math.cos(b[0]*Math.PI/180)*Math.sin(dLng/2)**2;
            return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1-x));
        }
        function pin(color, icon) {
            return L.divIcon({ className: '', iconSize: [34,34], iconAnchor: [17,17],
                html: `<div style="background:${color};width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 0 0 4px rgba(255,255,255,.9),0 3px 8px rgba(0,0,0,.3)"><i class="fa-solid ${icon}"></i></div>` });
        }

        const map = L.map('logimap', { zoomControl: true, attributionControl: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);

        L.marker(LOGI.pickup, { icon: pin('#1B4332', 'fa-seedling') }).addTo(map).bindPopup('Pickup: <?= addslashes($order['f_district']) ?> (Farm)');
        L.marker(LOGI.drop,   { icon: pin('#ef4444', 'fa-house-chimney') }).addTo(map).bindPopup('Drop-off: <?= addslashes($order['b_district']) ?>');
        const routeLine = L.polyline([LOGI.pickup, LOGI.drop], { color: '#1B4332', weight: 4, opacity: .65, dashArray: '8 10' }).addTo(map);
        map.fitBounds(routeLine.getBounds().pad(0.25));

        let truck = null;
        function placeTruck(latlng) {
            if (!truck) truck = L.marker(latlng, { icon: pin('#3b82f6', 'fa-truck-fast'), zIndexOffset: 1000 }).addTo(map).bindPopup('Driver');
            else truck.setLatLng(latlng);
        }
        function updateEta(fromLatLng) {
            const remain = haversineKm(fromLatLng, LOGI.drop);
            const mins = Math.max(1, Math.ceil(remain / 45 * 60));
            const el = document.getElementById('eta-timer');
            if (el) el.innerText = '~' + (mins < 60 ? mins + ' min' : Math.floor(mins/60) + ' hr ' + (mins%60) + ' min');
        }

        if (LOGI.status === 'In-Transit') {
            // Try live GPS first; fall back to simulated movement along the route.
            let simT = 0.15, simTimer = null;
            function simulate() {
                simTimer = setInterval(() => {
                    simT += 0.012; if (simT >= 0.95) simT = 0.15;
                    const lat = LOGI.pickup[0] + (LOGI.drop[0]-LOGI.pickup[0]) * simT;
                    const lng = LOGI.pickup[1] + (LOGI.drop[1]-LOGI.pickup[1]) * simT;
                    placeTruck([lat, lng]); updateEta([lat, lng]);
                }, 1200);
            }
            function poll() {
                fetch('driver_location.php?id=' + LOGI.orderId)
                    .then(r => r.json())
                    .then(d => {
                        if (d.ok && d.lat !== null && d.lng !== null) {
                            if (simTimer) { clearInterval(simTimer); simTimer = null; }
                            placeTruck([d.lat, d.lng]); updateEta([d.lat, d.lng]);
                        } else if (!simTimer) {
                            simulate(); // no live signal yet — animate the demo
                        }
                    }).catch(() => { if (!simTimer) simulate(); });
            }
            if (LOGI.driver) { placeTruck(LOGI.driver); updateEta(LOGI.driver); }
            poll();
            setInterval(poll, 5000);
        }

        // MoMo Logic
        function triggerMoMo() {
            document.getElementById('momoModal').classList.remove('hidden');
            document.getElementById('momoModal').classList.add('flex');
            document.getElementById('momoInitScreen').classList.remove('hidden');
            document.getElementById('momoProcessing').classList.add('hidden');
            document.getElementById('momoProcessing').classList.remove('flex');
            document.getElementById('momoPinScreen').classList.add('hidden');
        }

        function cancelMoMo() {
            document.getElementById('momoModal').classList.add('hidden');
            document.getElementById('momoModal').classList.remove('flex');
            document.getElementById('momoPin').value = '';
        }

        function startMoMoProcess() {
            const phoneInput = document.getElementById('momoPhone').value;
            if(phoneInput.length < 8) { alert("Please enter a valid phone number."); return; }
            document.getElementById('momoInitScreen').classList.add('hidden');
            document.getElementById('momoProcessing').classList.remove('hidden');
            document.getElementById('momoProcessing').classList.add('flex');
            setTimeout(() => {
                document.getElementById('momoProcessing').classList.remove('flex');
                document.getElementById('momoProcessing').classList.add('hidden');
                document.getElementById('momoPinScreen').classList.remove('hidden');
                document.getElementById('momoPin').focus();
            }, 1500);
        }

        function finalizePayment() {
            if(document.getElementById('momoPin').value.length < 4) { alert("Enter PIN."); return; }
            document.getElementById('releaseForm').submit();
        }
    </script>
</body>
</html>