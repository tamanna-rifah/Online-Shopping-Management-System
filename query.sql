-- Create the database
CREATE DATABASE IF NOT EXISTS onlineshopping;

-- Use the database
USE onlineshopping;

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Users table (Updated)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    location VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255) NOT NULL
);

-- Sales table
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(100) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE
);

-- Insert initial admin account
INSERT INTO admins (username, password) VALUES ('Rifah', '1234')
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- Insert sample products
INSERT INTO products (category, name, price, image) VALUES 
('Clothes', 'Classic White T-Shirt', 19.99, '1737047121_tops.jpg'),
('Clothes', 'Blue Denim Jeans', 49.99, 'jeans.jpeg'),
('Clothes', 'Elegant Black Dress', 79.99, '1737989656_sharee.jpg'),
('Clothes', 'Baby Girl Dress', 24.99, '1737989691_baby dress.jpg'),
('Clothes', 'Winter Coat for Girls', 89.99, '1737989725_girls-coats.jpg'),
('Accessories', 'Gold Earrings', 15.99, '1737989778_earing.jpg'),
('Accessories', 'Jewelry Set', 29.99, '1737989859_set.jpg'),
('Lifestyle', 'Modern Coffee Table', 199.99, '1737989880_table.webp'),
('Lifestyle', 'Comfortable Sofa', 599.99, '1737989961_sofa.jpg'),
('Health and Beauty', 'Beauty Kit', 39.99, '1737989928_beauty.png'),
('Health and Beauty', 'Blush Makeup', 12.99, '1737989946_blush.webp'),
('Clothes', 'Designer Shirt', 45.99, '1738061899_A7727a9adab8d47a2b04c2585df986644A.jpg_300x300.avif'),
('Clothes', 'Casual Tops', 22.99, '1738062441_images (1).jpeg'),
('Clothes', 'Summer Blouse', 28.99, '1738062468_images (2).jpeg')
ON DUPLICATE KEY UPDATE name = VALUES(name);