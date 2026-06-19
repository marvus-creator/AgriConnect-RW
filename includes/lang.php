<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default to English if no language is set
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en'; 
}

$current_lang = $_SESSION['lang'];

// 🌍 The Master Dictionary (Fully Translated Dashboard)
$translations = [
    'en' => [
        'switch_lang' => 'RW',
        'dashboard' => 'Portal',
        'greeting' => 'Hello',
        'welcome_sub' => 'Here is the latest data for your account.',
        
        // Sidebar
        'nav_overview' => 'Overview',
        'nav_messages' => 'Messages',
        'nav_market' => 'National Market',
        'nav_harvests' => 'My Harvests',
        'nav_routes' => 'Active Routes',
        'nav_reset' => 'Reset My Data',
        'nav_logout' => 'Secure Logout',
        
        // Cards & Stats
        'wallet_balance' => 'Wallet Balance',
        'lifetime_earned' => 'Lifetime Earned',
        'withdraw_funds' => 'Withdraw Funds',
        'empty_wallet' => 'Empty Wallet',
        'total_earnings' => 'Total Earnings',
        'my_rating' => 'My Rating',
        'reviews' => 'reviews',
        'incoming_requests' => 'Incoming Requests',
        'total_spent' => 'Total Spent',
        'active_orders' => 'Active Orders',
        'successful_deliveries' => 'Successful Deliveries',
        'delivery_rating' => 'Delivery Rating',
        
        // Tables
        'action_required' => 'Action Required: Pending Orders',
        'buyer_name' => 'Buyer Name',
        'product' => 'Product',
        'qty_requested' => 'Qty Requested',
        'earnings' => 'Earnings',
        'action' => 'Action',
        'accept' => 'Accept',
        'no_pending' => 'No pending orders right now.',
        
        'my_listed_harvests' => 'My Listed Harvests',
        'qty_remaining' => 'Quantity Remaining',
        'price_kg' => 'Price/KG',
        'manage' => 'Manage',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'no_harvests' => 'No harvests listed yet.',
        
        'my_order_history' => 'My Order History',
        'farm_seller' => 'Farm / Seller',
        'cost_incl_delivery' => 'Cost (Incl. Delivery)',
        'live_status' => 'Live Status',
        'cancel' => 'Cancel',
        'live_track' => 'Live Track',
        'farm_rated' => 'Farm Rated',
        'rate_farm' => 'Rate Farm',
        'driver_rated' => 'Driver Rated',
        'rate_driver' => 'Rate Driver',
        'awaiting_driver' => 'Awaiting Driver',
        'no_buys' => 'You haven\'t bought anything yet!',
        
        'available_jobs' => 'Available Transport Jobs',
        'pickup_info' => 'Pickup Info (Farmer)',
        'dropoff_info' => 'Dropoff Info (Buyer)',
        'cargo' => 'Cargo',
        'take_job' => 'Take Job',
        'no_jobs' => 'No jobs right now.',
        
        'my_active_routes' => 'My Active Routes (In-Transit)',
        'route_contacts' => 'Route Contacts',
        'status' => 'Status',
        'awaiting_buyer_pay' => 'Awaiting Buyer Payment',
        'release_job' => 'Release Job',
        'empty_routes' => 'Empty.',

        // Modals
        'add_harvest' => 'Add Harvest',
        'new_listing' => 'New Listing',
        'item_name' => 'Item Name',
        'description' => 'Description...',
        'product_image' => 'Product Image (Optional)',
        'list_to_market' => 'List to Market',
        'customer_reviews' => 'Customer Reviews',
        'what_buyers_say' => 'What buyers are saying.',
        
        'momo_payout' => 'MoMo Payout',
        'amount_to_withdraw' => 'Amount to Withdraw (RWF)',
        'max_available' => 'Max Available:',
        'transfer_to_number' => 'Transfer to Number',
        'locked_number_msg' => 'Locked to registered number to prevent fraud.',
        'initiate_transfer' => 'Initiate Transfer',
        
        // System Messages
        'payment_success' => 'Payment Successful! Funds released to Farmer and Driver.',
        'withdraw_success' => 'Successfully transferred',
        'withdraw_to_momo' => 'RWF to your MoMo account!',
        'withdraw_error' => 'Withdrawal failed. Invalid amount or insufficient funds.'
    ],
    'rw' => [
        'switch_lang' => 'EN',
        'dashboard' => 'Urubuga',
        'greeting' => 'Muraho',
        'welcome_sub' => 'Dore amakuru mashya ya konti yawe.',
        
        // Sidebar
        'nav_overview' => 'Incamake',
        'nav_messages' => 'Ubutumwa',
        'nav_market' => 'Isoko Rusange',
        'nav_harvests' => 'Imyaka Yanjye',
        'nav_routes' => 'Inzira Nkorera',
        'nav_reset' => 'Siba Amakuru Yanjye',
        'nav_logout' => 'Sohoka',
        
        // Cards & Stats
        'wallet_balance' => 'Amafaranga Asigaye',
        'lifetime_earned' => 'Ayo Winjije Yose',
        'withdraw_funds' => 'Bikuza Amafaranga',
        'empty_wallet' => 'Nta Mafaranga Ahari',
        'total_earnings' => 'Inyungu Yose',
        'my_rating' => 'Amanota Yanjye',
        'reviews' => 'ibitekerezo',
        'incoming_requests' => 'Ubusabe Bushya',
        'total_spent' => 'Ayo Wakoresheje Yose',
        'active_orders' => 'Ibyo Waguze Bikomeje',
        'successful_deliveries' => 'Ibyagejejweyo neza',
        'delivery_rating' => 'Amanota y\'Ubwikorezi',
        
        // Tables
        'action_required' => 'Igikorwa Gikenewe: Ibitegereje Kwemezwa',
        'buyer_name' => 'Umuguzi',
        'product' => 'Igicuruzwa',
        'qty_requested' => 'Ingano Yasabwe',
        'earnings' => 'Inyungu',
        'action' => 'Igikorwa',
        'accept' => 'Emeza',
        'no_pending' => 'Nta busabe butegereje buhari.',
        
        'my_listed_harvests' => 'Ibyo Nashyize Ku Isoko',
        'qty_remaining' => 'Ingano Isigaye',
        'price_kg' => 'Igiciro/KG',
        'manage' => 'Koresha',
        'edit' => 'Hindura',
        'delete' => 'Siba',
        'no_harvests' => 'Nta myaka irashyirwaho.',
        
        'my_order_history' => 'Amateka y\'Ibyo Waguze',
        'farm_seller' => 'Umuhinzi / Umugurisha',
        'cost_incl_delivery' => 'Ikiguzi (N\'ubwikorezi)',
        'live_status' => 'Uko Bimeze',
        'cancel' => 'Siba',
        'live_track' => 'Kurikirana',
        'farm_rated' => 'Watanze Amanota',
        'rate_farm' => 'Tanga Amanota',
        'driver_rated' => 'Shoferi Yabonye Amanota',
        'rate_driver' => 'Ha Shoferi Amanota',
        'awaiting_driver' => 'Tegereza Umushoferi',
        'no_buys' => 'Nta kintu uragura!',
        
        'available_jobs' => 'Akazi K\'ubwikorezi Gahari',
        'pickup_info' => 'Aho Ufata (Umuhinzi)',
        'dropoff_info' => 'Aho Ugeza (Umuguzi)',
        'cargo' => 'Umutwaro',
        'take_job' => 'Fata Akazi',
        'no_jobs' => 'Nta kazi gahari ubu.',
        
        'my_active_routes' => 'Inzira Zanjye (Mu nzira)',
        'route_contacts' => 'Abo Muvugana',
        'status' => 'Imiterere',
        'awaiting_buyer_pay' => 'Tegereza Umuguzi Yishyure',
        'release_job' => 'Rekura Akazi',
        'empty_routes' => 'Nta zihari.',

        // Modals
        'add_harvest' => 'Ongeraho Imyaka',
        'new_listing' => 'Gushyiraho Igicuruzwa',
        'item_name' => 'Izina ry\'Igicuruzwa',
        'description' => 'Ibisobanuro...',
        'product_image' => 'Ifoto y\'Igicuruzwa (Simbwa)',
        'list_to_market' => 'Shyira Ku Isoko',
        'customer_reviews' => 'Ibyo Abakiriya Bavuga',
        'what_buyers_say' => 'Ibyo abaguzi batekereza.',
        
        'momo_payout' => 'Kubikuza Kuri MoMo',
        'amount_to_withdraw' => 'Umubare Wifuza Kubikuza (RWF)',
        'max_available' => 'Umubare Ntarihuka:',
        'transfer_to_number' => 'Oherereza Kuri Nimero',
        'locked_number_msg' => 'Ifunze kuri nimero wandikishije kwirinda ubujura.',
        'initiate_transfer' => 'Ohereza Amafaranga',
        
        // System Messages
        'payment_success' => 'Kwishyura byagenze neza! Amafaranga yohererejwe Umuhinzi n\'Umushoferi.',
        'withdraw_success' => 'Wohererejwe',
        'withdraw_to_momo' => 'RWF kuri konti yawe ya MoMo!',
        'withdraw_error' => 'Kubikuza byanze. Umubare si wo cyangwa amafaranga ntahagije.'
    ]
];

$lang = $translations[$current_lang];
?>