<?php
// auth/login.php
session_start();
require_once '../includes/db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, role, password_hash FROM users WHERE phone_number = ?");
    mysqli_stmt_bind_param($stmt, "s", $phone);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Verify the hashed password
        if (password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // Redirect to dashboard
            header("Location: ../dashboard.php");
            exit();
        } else {
            $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 text-sm'>Invalid password!</div>";
        }
    } else {
        $message = "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 text-sm'>User not found!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | AgriConnect RW</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { akagera: '#1B4332', savannah: '#FFB703' },
                    fontFamily: { sans: ['Poppins', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">

    <nav class="bg-white shadow-sm py-4">
        <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
            <a href="../index.php" class="flex items-center gap-2">
                <i class="fa-solid fa-tractor text-2xl text-akagera"></i>
                <span class="font-bold text-xl text-akagera">AgriConnect <span class="text-savannah">RW</span></span>
            </a>
        </div>
    </nav>

    <div class="flex-grow flex items-center justify-center p-6">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="bg-akagera py-8 text-center text-white">
                <h2 class="text-2xl font-bold">Welcome Back</h2>
                <p class="text-green-100 text-sm">Log in to manage your harvest or orders.</p>
            </div>

            <div class="p-8">
                <?= $message ?>
                <form action="" method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Phone Number</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <i class="fa-solid fa-phone"></i>
                            </span>
                            <input type="text" name="phone" required placeholder="078..." 
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-akagera/20 outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" name="password" required placeholder="••••••••" 
                                class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-akagera/20 outline-none">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-akagera text-white font-bold py-3.5 rounded-lg shadow hover:bg-savannah hover:text-akagera transition-all duration-300">
                        Sign In <i class="fa-solid fa-right-to-bracket ml-2"></i>
                    </button>
                </form>

                <div class="mt-8 text-center text-sm">
                    <span class="text-gray-500">New here?</span> 
                    <a href="register.php" class="text-akagera font-bold hover:underline">Create an account</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>