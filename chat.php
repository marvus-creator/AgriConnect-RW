<?php
session_start();
require_once 'includes/db.php';

// Kick out anyone not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$uid = $_SESSION['user_id'];
$active_chat = isset($_GET['user']) ? mysqli_real_escape_string($conn, $_GET['user']) : null;

// Fetch Current User's Profile Pic for Navbar
$nav_pic_query = mysqli_query($conn, "SELECT profile_pic FROM users WHERE user_id = '$uid'");
$nav_pic = mysqli_fetch_assoc($nav_pic_query)['profile_pic'] ?? null;

// Send Message Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && $active_chat) {
    $msg = mysqli_real_escape_string($conn, $_POST['message']);
    if(!empty(trim($msg))) {
        mysqli_query($conn, "INSERT INTO messages (sender_id, receiver_id, message_text) VALUES ('$uid', '$active_chat', '$msg')");
        header("Location: chat.php?user=$active_chat");
        exit();
    }
}

// Fetch Active Chat User Details (NOW INCLUDING PROFILE PIC)
$chat_user = null;
if ($active_chat) {
    $chat_user_query = mysqli_query($conn, "SELECT full_name, role, is_verified, profile_pic FROM users WHERE user_id = '$active_chat'");
    $chat_user = mysqli_fetch_assoc($chat_user_query);
    
    // Mark messages as read
    mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE sender_id = '$active_chat' AND receiver_id = '$uid'");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages | AgriConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-100 font-sans h-screen flex flex-col overflow-hidden">

    <nav class="bg-white shadow-sm h-16 flex items-center px-6 justify-between z-10 flex-shrink-0">
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="text-gray-400 hover:text-akagera transition-colors"><i class="fa-solid fa-arrow-left text-xl"></i></a>
            <h1 class="font-black text-xl text-gray-900">AgriConnect <span class="text-savannah">Chat</span></h1>
        </div>
        <a href="profile.php" class="h-8 w-8 bg-akagera text-white rounded-full flex items-center justify-center font-bold text-xs overflow-hidden shadow-sm hover:scale-110 transition-transform">
            <?php if($nav_pic): ?>
                <img src="uploads/profiles/<?= $nav_pic ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
            <?php endif; ?>
        </a>
    </nav>

    <div class="flex flex-grow overflow-hidden max-w-7xl mx-auto w-full my-4 bg-white rounded-3xl shadow-xl border border-gray-200">
        
        <div class="w-1/3 bg-gray-50 border-r border-gray-200 flex flex-col">
            <div class="p-4 border-b border-gray-200 bg-white">
                <h2 class="font-black text-gray-800 text-sm uppercase tracking-widest">Recent Conversations</h2>
            </div>
            <div class="overflow-y-auto flex-grow p-2 space-y-1">
                <?php
                // Fetch people this user has talked to (NOW INCLUDING PROFILE PIC)
                $contacts_sql = "SELECT DISTINCT u.user_id, u.full_name, u.role, u.is_verified, u.profile_pic 
                                 FROM users u 
                                 JOIN messages m ON (u.user_id = m.sender_id OR u.user_id = m.receiver_id) 
                                 WHERE (m.sender_id = '$uid' OR m.receiver_id = '$uid') AND u.user_id != '$uid'";
                $contacts_res = mysqli_query($conn, $contacts_sql);

                if(mysqli_num_rows($contacts_res) > 0) {
                    while($contact = mysqli_fetch_assoc($contacts_res)) {
                        $is_active = ($active_chat == $contact['user_id']) ? 'bg-white shadow-sm border-l-4 border-savannah' : 'hover:bg-white/60 border-l-4 border-transparent';
                        $verified = $contact['is_verified'] ? "<i class='fa-solid fa-circle-check text-blue-500 text-[10px] ml-1'></i>" : "";
                        
                        // Set up the Avatar HTML
                        $avatar_html = $contact['profile_pic'] ? "<img src='uploads/profiles/{$contact['profile_pic']}' class='w-full h-full object-cover'>" : strtoupper(substr($contact['full_name'], 0, 1));

                        // Count unread messages from this user
                        $unread_sql = mysqli_query($conn, "SELECT COUNT(*) as c FROM messages WHERE sender_id = '{$contact['user_id']}' AND receiver_id = '$uid' AND is_read = 0");
                        $unread = mysqli_fetch_assoc($unread_sql)['c'];
                        $badge = $unread > 0 ? "<span class='bg-red-500 text-white text-[9px] font-black px-2 py-0.5 rounded-full'>$unread</span>" : "";

                        echo "
                        <a href='chat.php?user={$contact['user_id']}' class='flex items-center justify-between p-3 rounded-xl transition-all {$is_active}'>
                            <div class='flex items-center gap-3'>
                                <div class='h-10 w-10 bg-gray-200 rounded-full flex items-center justify-center font-bold text-gray-500 overflow-hidden'>
                                    {$avatar_html}
                                </div>
                                <div>
                                    <h4 class='font-bold text-sm text-gray-900'>{$contact['full_name']} {$verified}</h4>
                                    <p class='text-[10px] font-bold text-gray-400 uppercase tracking-widest'>{$contact['role']}</p>
                                </div>
                            </div>
                            {$badge}
                        </a>";
                    }
                } else {
                    echo "<p class='text-center text-gray-400 text-xs font-bold mt-10'>No messages yet.</p>";
                }
                ?>
            </div>
        </div>

        <div class="w-2/3 flex flex-col bg-[#efeae2] relative" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');">
            <?php if($active_chat && $chat_user): ?>
                
                <div class="p-4 bg-white border-b border-gray-200 flex justify-between items-center shadow-sm z-10">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 bg-savannah text-akagera rounded-full flex items-center justify-center font-black overflow-hidden shadow-sm">
                            <?php if($chat_user['profile_pic']): ?>
                                <img src="uploads/profiles/<?= $chat_user['profile_pic'] ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?= strtoupper(substr($chat_user['full_name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-black text-gray-900">
                                <?= $chat_user['full_name'] ?> <?= $chat_user['is_verified'] ? "<i class='fa-solid fa-circle-check text-blue-500 text-[12px] ml-1'></i>" : "" ?>
                            </h3>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest"><?= $chat_user['role'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="flex-grow p-6 overflow-y-auto flex flex-col gap-4" id="chatBox">
                    <?php
                    $msg_sql = "SELECT * FROM messages 
                                WHERE (sender_id = '$uid' AND receiver_id = '$active_chat') 
                                   OR (sender_id = '$active_chat' AND receiver_id = '$uid') 
                                ORDER BY created_at ASC";
                    $msg_res = mysqli_query($conn, $msg_sql);
                    
                    if(mysqli_num_rows($msg_res) > 0) {
                        while($m = mysqli_fetch_assoc($msg_res)) {
                            $is_me = ($m['sender_id'] == $uid);
                            $bubble_color = $is_me ? "bg-akagera text-white rounded-br-none" : "bg-white text-gray-900 rounded-bl-none shadow-sm";
                            $alignment = $is_me ? "self-end" : "self-start";
                            $time = date('H:i', strtotime($m['created_at']));
                            
                            echo "
                            <div class='max-w-[70%] {$alignment}'>
                                <div class='p-3 rounded-2xl {$bubble_color} text-sm'>
                                    {$m['message_text']}
                                </div>
                                <p class='text-[9px] text-gray-400 font-bold mt-1 px-1 " . ($is_me ? "text-right" : "text-left") . "'>{$time}</p>
                            </div>";
                        }
                    } else {
                        echo "<div class='bg-white/80 backdrop-blur mx-auto p-3 rounded-xl shadow-sm text-xs font-bold text-gray-500 text-center mt-10'>Send a message to start the conversation!</div>";
                    }
                    ?>
                </div>

                <div class="p-4 bg-gray-50 border-t border-gray-200">
                    <form method="POST" class="flex gap-2 relative">
                        <input type="text" name="message" required autocomplete="off" placeholder="Type a message..." class="flex-grow p-4 rounded-full border border-gray-300 outline-none focus:border-akagera transition-colors text-sm pl-6">
                        <button type="submit" class="h-12 w-12 bg-akagera text-white rounded-full flex items-center justify-center hover:bg-savannah hover:text-akagera transition-all absolute right-1 top-0.5 shadow-md">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </form>
                </div>

                <script>
                    const chatBox = document.getElementById("chatBox");
                    chatBox.scrollTop = chatBox.scrollHeight;
                </script>

            <?php else: ?>
                <div class="flex-grow flex flex-col items-center justify-center text-center p-8">
                    <div class="w-32 h-32 bg-white rounded-full flex items-center justify-center text-5xl text-gray-200 shadow-sm mb-6">
                        <i class="fa-regular fa-comments"></i>
                    </div>
                    <h2 class="text-2xl font-black text-gray-900 mb-2">AgriConnect Messenger</h2>
                    <p class="text-gray-500 font-medium max-w-sm">Select a conversation from the left menu or message a farmer directly from the marketplace to start negotiating.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>