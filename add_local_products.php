<?php
require 'db.php';

// Clear existing products
$conn->query("DELETE FROM products");

// Sample products using existing local images - All Categories
$products = [
    // CLOTHES CATEGORY (8 products)
    ['Clothes', 'Premium Cotton T-Shirt', 24.99, '1737047121_tops.jpg'],
    ['Clothes', 'Classic Blue Jeans', 59.99, 'jeans.jpeg'],
    ['Clothes', 'Elegant Black Dress', 89.99, '1737989656_sharee.jpg'],
    ['Clothes', 'Baby Girl Dress', 24.99, '1737989691_baby dress.jpg'],
    ['Clothes', 'Winter Coat for Girls', 89.99, '1737989725_girls-coats.jpg'],
    ['Clothes', 'Designer Shirt', 45.99, '1738061899_A7727a9adab8d47a2b04c2585df986644A.jpg_300x300.avif'],
    ['Clothes', 'Casual Tops', 22.99, '1738062441_images (1).jpeg'],
    ['Clothes', 'Summer Blouse', 28.99, '1738062468_images (2).jpeg'],
    
    // ACCESSORIES CATEGORY (3 products)
    ['Accessories', 'Gold Earrings', 15.99, '1737989778_earing.jpg'],
    ['Accessories', 'Jewelry Set', 29.99, '1737989859_set.jpg'],
    ['Accessories', 'Fashion Accessories', 19.99, '1737989778_earing.jpg'],
    
    // LIFESTYLE CATEGORY (3 products)
    ['Lifestyle', 'Modern Coffee Table', 199.99, '1737989880_table.webp'],
    ['Lifestyle', 'Comfortable Sofa', 599.99, '1737989961_sofa.jpg'],
    ['Lifestyle', 'Home Decor Item', 49.99, '1737989880_table.webp'],
    
    // HEALTH AND BEAUTY CATEGORY (3 products)
    ['Health and Beauty', 'Beauty Kit', 39.99, '1737989928_beauty.png'],
    ['Health and Beauty', 'Blush Makeup', 12.99, '1737989946_blush.webp'],
    ['Health and Beauty', 'Skincare Products', 29.99, '1737989928_beauty.png']
];

$inserted = 0;

foreach ($products as $product) {
    $stmt = $conn->prepare("INSERT INTO products (category, name, price, image) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssds", $product[0], $product[1], $product[2], $product[3]);
    
    if ($stmt->execute()) {
        $inserted++;
    }
}

echo "<h2>Local Products Added Successfully!</h2>";
echo "<p>Added <strong>$inserted products</strong> using existing images.</p>";
echo "<br><a href='index.php' style='background: #007BFF; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Home Page</a> ";
echo "<a href='all-products' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View All Products</a>";
?>
