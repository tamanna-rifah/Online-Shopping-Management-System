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

-- Insert initial admin account
INSERT INTO admins (username, password) VALUES ('Rifah', '1234')
ON DUPLICATE KEY UPDATE username = VALUES(username);

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

-- Cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(100) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE
);

-- Order table
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(100) NOT NULL,
  `payment_method` enum('cod','online') NOT NULL,
  `status` enum('Pending','Shipped','Delivered','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Order_items table
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Payment table
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `method` enum('online','cod') NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `verified` enum('Yes','No') DEFAULT 'No',
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reviews table
CREATE TABLE `reviews` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `user_email` VARCHAR(255) NOT NULL,
  `rating` INT(1) NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `review_text` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `reviews_ibfk_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Payment table
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `user_email` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `line1` varchar(255) NOT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `city` varchar(128) NOT NULL,
  `state` varchar(128) DEFAULT NULL,
  `postal_code` varchar(32) DEFAULT NULL,
  `country` varchar(128) DEFAULT 'Bangladesh',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE orders 
MODIFY status ENUM('pending','processing','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending';

-- --------------------------------------------------------
