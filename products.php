<?php
require_once 'config.php'; // Include your database connection

$products = []; // Initialize an empty array

try {
    $sql = "SELECT id, name, model, brand, category, unit_price, gst_rate, image_path FROM products ORDER BY name ASC, model ASC";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error fetching products: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Electric Shop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .add-button {
            display: inline-block;
            padding: 10px 15px;
            margin-bottom: 20px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .add-button:hover { background-color: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #007bff; color: white; }
        .action-links a {
            margin-right: 10px;
            color: #007bff;
            text-decoration: none;
        }
        .action-links a.delete { color: #dc3545; }
        .action-links a:hover { text-decoration: underline; }
        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        table img {
            max-width: 50px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Manage Products</h2>
        <a href="add_product.php" class="add-button">Add New Product</a>

        <?php if (empty($products)): ?>
            <p>No products found. Please add some.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Model</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Unit Price (₹)</th>
                        <th>GST Rate (%)</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['id']); ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['model']); ?></td>
                            <td><?php echo htmlspecialchars($product['brand']); ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($product['unit_price'], 2)); ?></td>
                            <td><?php echo htmlspecialchars(number_format($product['gst_rate'], 2)); ?></td>
                            <td>
                                <?php if (!empty($product['image_path']) && file_exists($product['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td class="action-links">
                                <a href="edit_product.php?id=<?php echo htmlspecialchars($product['id']); ?>">Edit</a> |
                                <a href="delete_product.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="delete" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <a href="index.php" class="back-link">Back to Dashboard</a>
    </div>
</body>
</html>