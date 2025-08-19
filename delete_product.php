<?php
// Enable detailed error reporting for debugging.
// REMEMBER TO REMOVE THESE LINES IN A PRODUCTION ENVIRONMENT!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // Include your database connection

// Check if an 'id' is provided in the URL query string
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $product_id = trim($_GET['id']);

    try {
        // --- 1. Get the image path of the product to be deleted ---
        // This is important because we want to delete the physical image file too.
        $sql_select_image = "SELECT image_path FROM products WHERE id = :id";
        $stmt_select_image = $pdo->prepare($sql_select_image);
        $stmt_select_image->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt_select_image->execute();
        $product_to_delete = $stmt_select_image->fetch();

        $image_to_delete = $product_to_delete['image_path'] ?? null; // Get the path, or null if not set

        // --- 2. Prepare and Execute the DELETE SQL statement ---
        $sql_delete = "DELETE FROM products WHERE id = :id";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':id', $product_id, PDO::PARAM_INT);

        if ($stmt_delete->execute()) {
            // --- 3. If deletion from DB is successful, attempt to delete the image file ---
            if (!empty($image_to_delete) && file_exists($image_to_delete)) {
                // unlink() deletes a file from the file system
                unlink($image_to_delete);
            }
            // Redirect back to the products list page with a success status
            header("Location: products.php?status=deleted");
            exit(); // Always call exit() after header("Location")
        } else {
            // If database deletion failed for some reason
            header("Location: products.php?status=delete_failed");
            exit();
        }

    } catch (PDOException $e) {
        // --- Handle Database Errors ---
        // Specifically check for foreign key constraint violation (if product is used in a quote)
        if ($e->getCode() == '23000') { // SQLSTATE for Integrity Constraint Violation
            header("Location: products.php?status=delete_fk_violation");
            exit();
        }
        // For any other unexpected database error, show a generic error page (or log it)
        die("Error deleting product: " . $e->getMessage());
    }
} else {
    // --- If no ID is provided in the URL, redirect back to products list ---
    header("Location: products.php?status=no_id_provided");
    exit();
}
?>