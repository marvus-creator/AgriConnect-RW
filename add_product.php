<?php
session_start();
require_once 'includes/db.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Farmer') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $farmer_id = $_SESSION['user_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $harvest_date = mysqli_real_escape_string($conn, $_POST['harvest_date']);

    $image_name = NULL;

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        
        // 🚀 THE FIX: Expanded the allowed list to catch weird Windows/Google formats!
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif', 'bmp', 'svg'];
        $ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            if (!is_dir('uploads/products')) { mkdir('uploads/products', 0777, true); }
            
            $image_name = "prod_" . $farmer_id . "_" . time() . "." . $ext;
            $dest = "uploads/products/" . $image_name;
            
            if (!move_uploaded_file($_FILES['product_image']['tmp_name'], $dest)) {
                die("<div style='font-family: sans-serif; padding: 20px; text-align: center;'><h1 style='color:red;'>ERROR 3: FOLDER PERMISSIONS</h1> <p style='font-size: 18px;'>Failed to move the image into the uploads folder. Check folder permissions.</p></div>");
            }
        } else {
            // 🚀 Now it tells you EXACTLY what format it rejected!
            die("<div style='font-family: sans-serif; padding: 20px; text-align: center;'><h1 style='color:red;'>ERROR 4: INVALID FILE ({$ext})</h1> <p style='font-size: 18px;'>You tried to upload a <b>.{$ext}</b> file. Please use standard JPG or PNG images.</p></div>");
        }
    }

    // Insert everything into the database!
    $query = "INSERT INTO products (farmer_id, title, description, quantity_kg, price_per_kg, harvest_date, image) 
              VALUES ('$farmer_id', '$title', '$description', '$quantity', '$price', '$harvest_date', " . ($image_name ? "'$image_name'" : "NULL") . ")";
    
    if(mysqli_query($conn, $query)){
        header("Location: dashboard.php?msg=harvest_added");
    } else {
        echo "Database Error: " . mysqli_error($conn);
    }
    exit();
}
?>