<?php
require_once 'config.php'; // Include your database connection

$message = ''; // Initialize a message variable for success/error
$name = $model = $unit_price = $brand = $category = $gst_rate = ''; // Initialize variables for form fields

// Define allowed image types and max size
$allowed_image_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_file_size = 5 * 1024 * 1024; // 5 MB

// Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data and sanitize it
    $name = trim($_POST['name'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $unit_price = trim($_POST['unit_price'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $gst_rate = trim($_POST['gst_rate'] ?? '');
    $image_path = null; // Will store the path if image uploaded

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

    // --- Image Upload Handling ---
    // Check if a file was uploaded and there are no system errors with the upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['product_image']['tmp_name'];
        $file_name = $_FILES['product_image']['name'];
        $file_type = $_FILES['product_image']['type'];
        $file_size = $_FILES['product_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Generate a unique file name to prevent overwrites and security issues
        $new_file_name = uniqid('product_', true) . '.' . $file_ext;
        $upload_dir = 'uploads/products/'; // Directory to save images

        // Create the directory if it doesn't exist (important!)
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // 0777 is for full permissions, adjust for production if needed
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
                $image_path = $dest_path; // Store this path in the database
            } else {
                $errors[] = "Failed to upload image. Check server permissions or disk space.";
            }
        }
    } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] != UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors (e.g., file too large by php.ini settings)
        $errors[] = "Image upload error: " . $_FILES['product_image']['error'] . ". Check php.ini max_fileupload_size.";
    }


    // If no validation errors, proceed with database insertion
    if (empty($errors)) {
        try {
            // Prepare an INSERT SQL statement using prepared statements for security
            $sql = "INSERT INTO products (name, model, unit_price, brand, category, gst_rate, image_path) VALUES (:name, :model, :unit_price, :brand, :category, :gst_rate, :image_path)";
            $stmt = $pdo->prepare($sql);

            // Bind parameters
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':model', $model, PDO::PARAM_STR);
            $stmt->bindParam(':unit_price', $unit_price, PDO::PARAM_STR);
            $stmt->bindParam(':brand', $brand, PDO::PARAM_STR);
            $stmt->bindParam(':category', $category, PDO::PARAM_STR);
            $stmt->bindParam(':gst_rate', $gst_rate, PDO::PARAM_STR);
            $stmt->bindParam(':image_path', $image_path, PDO::PARAM_STR);

            // Execute the prepared statement
            if ($stmt->execute()) {
                $message = "<div style='color: green;'>Product added successfully!</div>";
                // Clear form fields after successful submission
                $name = $model = $unit_price = $brand = $category = $gst_rate = '';
            } else {
                $message = "<div style='color: red;'>Error: Could not add product.</div>";
            }
        } catch (PDOException $e) {
            // Catch any database errors
            $message = "<div style='color: red;'>Database error: " . $e->getMessage() . "</div>";
        }
    } else {
        // Display all accumulated errors
        $message = "<div style='color: red;'>" . implode('<br>', $errors) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - Electric Shop</title>
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
            background-color: #dc3545; /* Red for errors */
        }
        .message div[style*="green"] {
            background-color: #28a745; /* Green for success */
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add New Product</h2>
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Product Name:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
            </div>
            <div class="form-group">
                <label for="model">Model:</label>
                <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($model); ?>" required>
            </div>
            <div class="form-group">
                <label for="brand">Brand:</label>
                <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($brand); ?>">
            </div>
            <div class="form-group">
                <label for="category">Category:</label>
                <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($category); ?>">
            </div>
            <div class="form-group">
                <label for="unit_price">Unit Price (₹):</label>
                <input type="number" id="unit_price" name="unit_price" step="0.01" value="<?php echo htmlspecialchars($unit_price); ?>" required>
            </div>
            <div class="form-group">
                <label for="gst_rate">GST Rate (%):</label>
                <input type="number" id="gst_rate" name="gst_rate" step="0.01" value="<?php echo htmlspecialchars($gst_rate); ?>" min="0" max="100" required>
            </div>
            <div class="form-group">
                <label for="product_image">Product Image:</label>
                <input type="file" id="product_image" name="product_image" accept="image/jpeg, image/png, image/gif">
            </div>
            <div class="form-group">
                <button type="submit" class="btn-submit">Add Product</button>
            </div>
        </form>
        <a href="products.php" class="back-link">Back to Product List</a>
    </div>
</body>
</html>