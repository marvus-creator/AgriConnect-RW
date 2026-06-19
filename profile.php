<?php
session_start();
require_once 'includes/db.php';

// Kick out guests
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$uid = $_SESSION['user_id'];
$msg = "";

// Auto-create upload directories if they don't exist
if (!is_dir('uploads/profiles')) { mkdir('uploads/profiles', 0777, true); }
if (!is_dir('uploads/products')) { mkdir('uploads/products', 0777, true); }

// FETCH CURRENT USER DATA
$user_sql = mysqli_query($conn, "SELECT * FROM users WHERE user_id = '$uid'");
$user = mysqli_fetch_assoc($user_sql);

// --- LOGIC: HANDLE PROFILE PIC UPLOAD ---
if (isset($_POST['upload_pic']) && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
    
    if (in_array(strtolower($ext), $allowed)) {
        // Create a unique name so images don't overwrite each other
        $new_name = "user_" . $uid . "_" . time() . "." . $ext;
        $dest = "uploads/profiles/" . $new_name;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest)) {
            // Delete old pic if it exists to save server space
            if ($user['profile_pic'] && file_exists("uploads/profiles/" . $user['profile_pic'])) {
                unlink("uploads/profiles/" . $user['profile_pic']);
            }

            mysqli_query($conn, "UPDATE users SET profile_pic = '$new_name' WHERE user_id = '$uid'");
            $_SESSION['profile_pic'] = $new_name; // Update session
            $user['profile_pic'] = $new_name; // Update local variable
            $msg = "<div class='bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold'><i class='fa-solid fa-check-circle mr-2'></i> Profile picture updated!</div>";
        }
    } else {
        $msg = "<div class='bg-red-100 text-red-700 p-4 rounded-xl mb-6 font-bold'><i class='fa-solid fa-triangle-exclamation mr-2'></i> Invalid file type! Use JPG or PNG.</div>";
    }
}

// --- LOGIC: REMOVE PIC ---
if (isset($_POST['remove_pic'])) {
    // Delete the actual image file from the server
    if ($user['profile_pic'] && file_exists("uploads/profiles/" . $user['profile_pic'])) {
        unlink("uploads/profiles/" . $user['profile_pic']);
    }

    mysqli_query($conn, "UPDATE users SET profile_pic = NULL WHERE user_id = '$uid'");
    $_SESSION['profile_pic'] = null;
    $user['profile_pic'] = null;
    $msg = "<div class='bg-yellow-100 text-yellow-700 p-4 rounded-xl mb-6 font-bold'><i class='fa-solid fa-trash mr-2'></i> Profile picture removed successfully.</div>";
}

// --- LOGIC: CHANGE PASSWORD ---
if (isset($_POST['change_password'])) {
    $new_pass = $_POST['new_password'];
    if (strlen($new_pass) < 4) {
        $msg = "<div class='bg-red-100 text-red-700 p-4 rounded-xl mb-6 font-bold'><i class='fa-solid fa-triangle-exclamation mr-2'></i> Password must be at least 4 characters.</div>";
    } else {
        // Securely hash and store in the correct column (password_hash)
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hash, $uid);
        mysqli_stmt_execute($stmt);
        $msg = "<div class='bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold'><i class='fa-solid fa-lock mr-2'></i> Password secured and updated!</div>";
    }
}

// --- LOGIC: DELETE ACCOUNT (DANGER ZONE) ---
if (isset($_POST['delete_account'])) {
    // Cascading delete to clean up the database completely
    mysqli_query($conn, "DELETE o FROM orders o JOIN products p ON o.product_id = p.product_id WHERE p.farmer_id = '$uid'");
    mysqli_query($conn, "DELETE FROM products WHERE farmer_id = '$uid'");
    mysqli_query($conn, "DELETE FROM orders WHERE buyer_id = '$uid'");
    mysqli_query($conn, "UPDATE orders SET driver_id = NULL, order_status = 'Accepted' WHERE driver_id = '$uid'");
    mysqli_query($conn, "DELETE FROM messages WHERE sender_id = '$uid' OR receiver_id = '$uid'");
    mysqli_query($conn, "DELETE FROM ratings WHERE buyer_id = '$uid' OR farmer_id = '$uid'");
    mysqli_query($conn, "DELETE FROM driver_ratings WHERE buyer_id = '$uid' OR driver_id = '$uid'");
    mysqli_query($conn, "DELETE FROM users WHERE user_id = '$uid'");
    
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile Settings | AgriConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703' } } } }</script>
</head>
<body class="bg-gray-50 font-sans min-h-screen">

    <nav class="bg-white shadow-sm h-16 flex items-center px-8 justify-between sticky top-0 z-50">
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="text-gray-400 hover:text-akagera transition-colors"><i class="fa-solid fa-arrow-left text-xl"></i></a>
            <h1 class="font-black text-xl text-gray-900">Account <span class="text-savannah">Settings</span></h1>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 py-12">
        <?= $msg ?>

        <div class="bg-white rounded-[2rem] shadow-sm border border-gray-200 overflow-hidden mb-8">
            <div class="h-32 bg-gradient-to-r from-akagera to-green-900"></div>
            
            <div class="px-8 pb-8 relative">
                <div class="absolute -top-16 left-8 h-32 w-32 bg-white rounded-full border-4 border-white shadow-lg overflow-hidden flex items-center justify-center text-4xl font-black text-akagera">
                    <?php if($user['profile_pic']): ?>
                        <img src="uploads/profiles/<?= $user['profile_pic'] ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>

                <div class="pt-20">
                    <h2 class="text-3xl font-black text-gray-900"><?= $user['full_name'] ?></h2>
                    <p class="text-gray-500 font-bold uppercase tracking-widest text-sm"><?= $user['role'] ?></p>
                </div>

                <div class="mt-8 pt-8 border-t border-gray-100 flex flex-col md:flex-row gap-6">
                    
                    <form method="POST" enctype="multipart/form-data" class="flex-1 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                        <h3 class="font-bold text-gray-900 mb-4"><i class="fa-solid fa-image text-savannah mr-2"></i> Update Picture</h3>
                        <input type="file" name="profile_pic" accept="image/*" required class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-akagera file:text-white hover:file:bg-green-800 transition-colors mb-4">
                        <div class="flex gap-2">
                            <button type="submit" name="upload_pic" class="bg-akagera text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm hover:bg-green-800 transition-all flex-1">Upload</button>
                            
                            <?php if($user['profile_pic']): ?>
                                <button type="submit" name="remove_pic" formnovalidate class="bg-red-50 text-red-500 px-4 py-2 rounded-xl text-xs font-bold hover:bg-red-500 hover:text-white transition-all"><i class="fa-solid fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </form>

                    <form method="POST" class="flex-1 bg-gray-50 p-6 rounded-2xl border border-gray-100">
                        <h3 class="font-bold text-gray-900 mb-4"><i class="fa-solid fa-shield-halved text-blue-500 mr-2"></i> Security</h3>
                        <input type="password" name="new_password" required placeholder="Enter new password" class="w-full p-3 rounded-xl border border-gray-200 outline-none focus:border-blue-500 mb-4 text-sm">
                        <button type="submit" name="change_password" class="w-full bg-blue-600 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm hover:bg-blue-700 transition-all">Update Password</button>
                    </form>

                </div>
            </div>
        </div>

        <div class="bg-red-50 border border-red-200 rounded-[2rem] p-8">
            <h3 class="font-black text-red-600 text-lg mb-2"><i class="fa-solid fa-triangle-exclamation mr-2"></i> Danger Zone</h3>
            <p class="text-sm text-red-400 font-medium mb-6">Once you delete your account, there is no going back. All your listings, messages, and history will be permanently erased.</p>
            
            <form method="POST" onsubmit="return confirm('Are you 100% sure you want to delete your account? This cannot be undone.');">
                <button type="submit" name="delete_account" formnovalidate class="bg-red-600 text-white px-6 py-3 rounded-xl font-bold shadow-md hover:bg-red-700 transition-all">Permanently Delete Account</button>
            </form>
        </div>

    </main>

</body>
</html>