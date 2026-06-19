-- config.sql: Master Database Blueprint for AgriConnect RW

CREATE DATABASE IF NOT EXISTS agriconnect_rw;
USE agriconnect_rw;

-- 1. Users: Farmers, Buyers, Drivers, and Admins
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(15) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Farmer', 'Buyer', 'Driver', 'Admin') DEFAULT 'Farmer',
    district ENUM('Kigali', 'Musanze', 'Huye', 'Rubavu', 'Nyagatare') NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Categories: Fruits, Grains, Vegetables
CREATE TABLE categories (
    cat_id INT AUTO_INCREMENT PRIMARY KEY,
    cat_name VARCHAR(50) NOT NULL
);

-- 3. Products: The crops farmers are selling
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT,
    cat_id INT,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    quantity_kg DECIMAL(10,2) NOT NULL,
    price_per_kg INT NOT NULL,
    harvest_date DATE,
    image_url VARCHAR(255),
    FOREIGN KEY (farmer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (cat_id) REFERENCES categories(cat_id)
);

-- 4. Orders: Connecting Buyers to Products & Drivers
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT,
    product_id INT,
    driver_id INT DEFAULT NULL,
    order_status ENUM('Pending', 'Accepted', 'In-Transit', 'Delivered') DEFAULT 'Pending',
    total_price INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(user_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (driver_id) REFERENCES users(user_id)
);

-- 5. Market Ticker: Real-time Price Index 
CREATE TABLE market_prices (
    price_id INT AUTO_INCREMENT PRIMARY KEY,
    crop_name VARCHAR(50),
    district_name VARCHAR(50),
    avg_price INT,
    trend ENUM('UP', 'DOWN', 'STABLE'),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);