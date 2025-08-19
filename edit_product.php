<?php
// Enable detailed error reporting for debugging.
// REMEMBER TO REMOVE THESE LINES IN A PRODUCTION ENVIRONMENT!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // Include your database connection

$message = ''; // Initialize a message variable for success/error
$product = null; // Will store product data if found
$product_id = null; // To store the ID from the URL

// Define allowed image types and max size (same as add_product.php)
$allowed_image_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_file_size = 5 * 1024 * 1024; // 5 MB (5 * 1024 * 1024 bytes)

// --- Part 1: Fetch Product Data for Display (on initial page load or after update) ---
// This block runs when the page is first loaded via GET request,
// or after a POST request if we need to re-fetch updated data.
$id_to_fetch = null;

if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_to_fetch = trim($_GET['id']);
} elseif (isset($_POST['product_id']) && !empty(trim($_POST['product_id']))) {
    // If it's a POST request (form submission), get the ID from the hidden field
    $id_to_fetch = trim($_POST['product_id']);
}

if ($id_to_fetch) {
    try {
        $sql = "SELECT id, name, model, brand, category, unit_price, gst_rate, image_path FROM products WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id_to_fetch, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(); // Fetch a single row

        if (!$product) {
            // If no product found with that ID, redirect back to products list
            header("Location: products.php?status=notfound");
            exit();
        }
    } catch (PDOException $e) {
        die("Error fetching product for edit: " . $e->getMessage());
    }
} else {
    // If no ID is provided in the URL or POST, redirect to products list
    header("Location: products.php?status=no_id");
    exit();
}

// --- Part 2: Handle Form Submission (Update Logic) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Important: Re-assigning variables from POST data for validation and display after submission
    $name = trim($_POST['name'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $unit_price = trim($_POST['unit_price'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $gst_rate = trim($_POST['gst_rate'] ?? '');
    $current_image_path = trim($_POST['current_image_path'] ?? ''); // Hidden field for existing image

    $errors = []; // Array to store validation errors

    // --- Server-side Validation ---
    if (empty($name)) {
        $errors[] = "Product name cannot be empty.";
    }
    if (empty($model)) {
        $errors[] = "Model cannot be empty.";
    }
    if (!is_numeric($unit_price) || $unit_price < 0) {
        $errors[] = "Unit price must be a non-negative number.";
    }
    if (!is_numeric($gst_rate) || $gst_rate < 0 || $gst_rate > 100) {
        $errors[] = "GST rate must be a number between 0 and 100.";
    }

    $new_image_path = $current_image_path; // Assume current image if no new one uploaded

    // --- Image Upload Handling ---
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['product_image']['tmp_name'];
        $file_name = $_FILES['product_image']['name'];
        $file_type = $_FILES['product_image']['type'];
        $file_size = $_FILES['product_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Generate a unique file name
        $new_file_name = uniqid('product_', true) . '.' . $file_ext;
        $upload_dir = 'uploads/products/'; // Directory to save images

        // Ensure upload directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create with full permissions for local dev
        }

        $dest_path = $upload_dir . $new_file_name;

        // Image validation
        if (!in_array($file_type, $allowed_image_types)) {
            $errors[] = "Invalid image type. Only JPG, PNG, GIF are allowed.";
        }
        if ($file_size > $max_file_size) {
            $errors[] = "Image file size exceeds " . ($max_file_size / (1024 * 1024)) . " MB limit.";
        }
        
        // If no errors so far, attempt to move the uploaded file
        if (empty($errors)) {
            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                // Delete old image if a new one is successfully uploaded and old one exists
                if (!empty($current_image_path) && file_exists($current_image_path)) {
                    unlink($current_image_path);
                }
                $new_image_path = $dest_path; // Update path to new image
            } else {
                $errors[] = "Failed to upload new image. Check server permissions or disk space.";
            }
        }
    } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] != UPLOAD_ERR_NO_FILE) {
        // Handle other PHP upload errors (e.g., UPLOAD_ERR_INI_SIZE)
        $errors[] = "Image upload error: " . $_FILES['product_image']['error'] . ". Check server PHP configuration (php.ini max_fileupload_size).";
    }

    // --- Proceed with Database Update if no validation or upload errors ---
    if (empty($errors)) {
        try {
            $sql = "UPDATE products SET name = :name, model = :model, brand = :brand, category = :category, unit_price = :unit_price, gst_rate = :gst_rate, image_path = :image_path, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $pdo->prepare($sql);

            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':model', $model, PDO::PARAM_STR);
            $stmt->bindParam(':brand', $brand, PDO::PARAM_STR);
            $stmt->bindParam(':category', $category, PDO::PARAM_STR);
            $stmt->bindParam(':unit_price', $unit_price, PDO::PARAM_STR);
            $stmt->bindParam(':gst_rate', $gst_rate, PDO::PARAM_STR);
            $stmt->bindParam(':image_path', $new_image_path, PDO::PARAM_STR); // Use the new or existing path
            $stmt->bindParam(':id', $id_to_fetch, PDO::PARAM_INT); // Use the ID originally fetched/passed

            if ($stmt->execute()) {
                $message = "<div style='color: green;'>Product updated successfully!</div>";
                // Re-fetch product data to display the *latest* information on the form
                // This is crucial, especially if an image path changed or new values were saved
                $sql = "SELECT id, name, model, brand, category, unit_price, gst_rate, image_path FROM products WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $id_to_fetch, PDO::PARAM_INT);
                $stmt->execute();
                $product = $stmt->fetch();
            } else {
                $message = "<div style='color: red;'>Error: Could not update product.</div>";
            }
        } catch (PDOException $e) {
            $message = "<div style='color: red;'>Database error: " . $e->getMessage() . "</div>";
        }
    } else {
        // Display all accumulated validation/upload errors
        $message = "<div style='color: red;'>" . implode('<br>', $errors) . "</div>";
        // To retain user's input on the form after an error, re-assign $product values from POST
        // This makes the form "sticky"
        $product['name'] = $name;
        $product['model'] = $model;
        $product['brand'] = $brand;
        $product['category'] = $category;
        $product['unit_price'] = $unit_price;
        $product['gst_rate'] = $gst_rate;
        $product['image_path'] = $current_image_path; // Retain current image path if new upload failed
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Electric Shop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="file"] {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="file"] { border: none; padding: 0; }
        .btn-submit {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-submit:hover { background-color: #0056b3; }
        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        .message {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            color: white;
        }
        .message div {
            padding: 5px;
            margin-bottom: 5px;
            border-radius: 3px;
            /* Default to red for general errors, overridden for green success */
            background-color: #dc3545;
        }
        .message div[style*="green"] { /* Targets div with inline style 'color: green;' */
            background-color: #28a745; /* Green for success */
        }
        .current-image-preview {
            margin-top: 10px;
            text-align: center;
        }
        .current-image-preview img {
            max-width: 150px;
            height: auto;
            border: 1px solid #ddd;
            padding: 5px;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Product</h2>
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($product): // Only show form if product data was fetched ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars($product['image_path'] ?? ''); ?>">

                <div class="form-group">
                    <label for="name">Product Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="model">Model:</label>
                    <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($product['model']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="brand">Brand:</label>
                    <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="category">Category:</label>
                    <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="unit_price">Unit Price (₹):</label>
                    <input type="number" id="unit_price" name="unit_price" step="0.01" value="<?php echo htmlspecialchars($product['unit_price']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="gst_rate">GST Rate (%):</label>
                    <input type="number" id="gst_rate" name="gst_rate" step="0.01" value="<?php echo htmlspecialchars($product['gst_rate']); ?>" min="0" max="100" required>
                </div>
                <div class="form-group">
                    <label for="product_image">Product Image:</label>
                    <?php if (!empty($product['image_path']) && file_exists($product['image_path'])): ?>
                        <div class="current-image-preview">
                            Current Image:<br>
                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="Current Product Image">
                            <br>
                            <small>Leave blank to keep current image. Choose new file to replace.</small>
                        </div>
                    <?php else: ?>
                        <div class="current-image-preview">
                            <small>No current image.</small>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="product_image" name="product_image" accept="image/jpeg, image/png, image/gif">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-submit">Update Product</button>
                </div>
            </form>
        <?php else: ?>
            <p style="text-align: center;">Product not found or invalid ID.</p>
        <?php endif; ?>

        <a href="products.php" class="back-link">Back to Product List</a>
    </div>
</body>
</html>