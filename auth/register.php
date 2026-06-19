<?php
session_start();
require_once '../includes/db.php'; // Make sure this path points correctly to your db connection

// 1. SMART ROUTING: Read the URL to see what button they clicked on the homepage
$requested_role = $_GET['role'] ?? 'Buyer'; // Defaults to Buyer if they just clicked "Get Started"

$error = '';

// 2. PROCESS THE REGISTRATION FORM
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $contact = trim($_POST['contact']);
    $role = $_POST['role'];
    $district = $_POST['district'];

    // Validate role & district against the allowed values (defence-in-depth)
    $valid_roles = ['Farmer', 'Buyer', 'Driver'];
    $valid_districts = ['Kigali', 'Musanze', 'Huye', 'Rubavu', 'Nyagatare'];

    if (!in_array($role, $valid_roles, true) || !in_array($district, $valid_districts, true)) {
        $error = "Please choose a valid role and district.";
    } elseif (strlen($_POST['password']) < 4) {
        $error = "Password must be at least 4 characters.";
    } else {
        // Securely hash the password
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Prepared statement — no string interpolation of user input
        $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, phone_number, password_hash, role, district) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssss", $full_name, $contact, $password, $role, $district);

        if (mysqli_stmt_execute($stmt)) {
            // Log them in immediately after registering
            $_SESSION['user_id'] = mysqli_insert_id($conn);
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role'] = $role;

            header("Location: ../dashboard.php");
            exit();
        } elseif (mysqli_errno($conn) == 1062) {
            $error = "That phone number is already registered. Try logging in.";
        } else {
            $error = "Could not create account. Please try again.";
        }
    }
} // end POST handler
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join AgriConnect RW</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: { akagera: '#1B4332', savannah: '#FFB703', dark: '#0F172A' }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-gray-50 font-sans min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <div class="absolute inset-0 opacity-5 pointer-events-none" style="background-image: radial-gradient(#1B4332 2px, transparent 2px); background-size: 30px 30px;"></div>

    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg overflow-hidden relative z-10 border border-gray-100">
        
        <div class="bg-akagera p-8 text-center text-white relative">
            <a href="../index.php" class="absolute top-4 left-4 text-white/50 hover:text-white transition-colors">
                <i class="fa-solid fa-arrow-left"></i> Home
            </a>
            <div class="w-16 h-16 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-white/20">
                <i class="fa-solid fa-tractor text-2xl text-savannah"></i>
            </div>
            <h2 class="text-3xl font-black mb-1">Join the Network</h2>
            <p class="text-green-100/80 text-sm font-medium">Create your <?= $requested_role ?> Account</p>
        </div>

        <div class="p-8">
            <?php if($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-bold mb-6 border border-red-100 text-center">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="space-y-5">
                
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-2">How will you use AgriConnect?</label>
                    <select name="role" required class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl font-bold text-gray-700 outline-none focus:border-savannah focus:ring-2 focus:ring-savannah/20 transition-all cursor-pointer">
                        <option value="Buyer" <?= ($requested_role == 'Buyer') ? 'selected' : '' ?>>I want to Buy Produce (Restaurant/Market)</option>
                        <option value="Farmer" <?= ($requested_role == 'Farmer') ? 'selected' : '' ?>>I want to Sell Produce (Farmer/Co-op)</option>
                        <option value="Driver" <?= ($requested_role == 'Driver') ? 'selected' : '' ?>>I want to Deliver (Logistics Driver)</option>
                    </select>
                </div>

                <div>
                    <input type="text" name="full_name" required placeholder="Full Name or Business Name" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl font-medium outline-none focus:border-akagera transition-all">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <input type="text" name="contact" required placeholder="Email or Phone" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl font-medium outline-none focus:border-akagera transition-all">
                    
                    <select name="district" required class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl font-medium outline-none focus:border-akagera transition-all cursor-pointer text-gray-500">
                        <option value="" disabled selected>Select District...</option>
                        <option value="Kigali">Kigali</option>
                        <option value="Musanze">Musanze</option>
                        <option value="Huye">Huye</option>
                        <option value="Rubavu">Rubavu</option>
                        <option value="Nyagatare">Nyagatare</option>
                    </select>
                </div>

                <div>
                    <input type="password" name="password" required placeholder="Create Secure Password" class="w-full p-4 bg-gray-50 border border-gray-100 rounded-2xl font-medium outline-none focus:border-akagera transition-all">
                </div>

                <button type="submit" class="w-full bg-akagera text-white font-black text-lg py-4 rounded-2xl shadow-lg shadow-akagera/30 hover:bg-dark transition-all duration-300 transform active:scale-[0.98] mt-2">
                    Create Account <i class="fa-solid fa-user-plus ml-2"></i>
                </button>
            </form>

            <div class="text-center mt-8 border-t border-gray-100 pt-6">
                <p class="text-sm font-medium text-gray-500">Already have an account? <a href="login.php" class="text-savannah font-black hover:underline">Sign In Here</a></p>
            </div>
        </div>
    </div>

</body>
</html>