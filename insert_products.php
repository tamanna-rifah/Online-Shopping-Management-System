<?php
require 'db.php';

// Sample products data
$products = [
    ['Clothes', 'Classic White T-Shirt', 19.99, '1737047121_tops.jpg'],
    ['Clothes', 'Blue Denim Jeans', 49.99, 'jeans.jpeg'],
    ['Clothes', 'Elegant Black Dress', 79.99, '1737989656_sharee.jpg'],
    ['Clothes', 'Baby Girl Dress', 24.99, '1737989691_baby dress.jpg'],
    ['Clothes', 'Winter Coat for Girls', 89.99, '1737989725_girls-coats.jpg'],
    ['Accessories', 'Gold Earrings', 15.99, '1737989778_earing.jpg'],
    ['Accessories', 'Jewelry Set', 29.99, '1737989859_set.jpg'],
    ['Lifestyle', 'Modern Coffee Table', 199.99, '1737989880_table.webp'],
    ['Lifestyle', 'Comfortable Sofa', 599.99, '1737989961_sofa.jpg'],
    ['Health and Beauty', 'Beauty Kit', 39.99, '1737989928_beauty.png'],
    ['Health and Beauty', 'Blush Makeup', 12.99, '1737989946_blush.webp'],
    ['Clothes', 'Designer Shirt', 45.99, '1738061899_A7727a9adab8d47a2b04c2585df986644A.jpg_300x300.avif'],
    ['Clothes', 'Casual Tops', 22.99, '1738062441_images (1).jpeg'],
    ['Clothes', 'Summer Blouse', 28.99, '1738062468_images (2).jpeg']
];

// Clear existing products first
$conn->query("DELETE FROM products");

// Insert sample products
$stmt = $conn->prepare("INSERT INTO products (category, name, price, image) VALUES (?, ?, ?, ?)");
$inserted = 0;

foreach ($products as $product) {
    $stmt->bind_param("ssds", $product[0], $product[1], $product[2], $product[3]);
    if ($stmt->execute()) {
        $inserted++;
    }
}

echo "Successfully inserted $inserted sample products!<br>";
echo "<a href='index.php'>Go to Home Page</a><br>";
echo "<a href='all-products'>Go to All Products</a>";
?>
