<?php
// Enable detailed error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // Include your database connection

$quotations = []; // Initialize an empty array to store quotations

try {
    // Fetch all quotations, ordering by date and then ID (descending for newest first)
    $sql = "SELECT id, quotation_number, quotation_date, customer_name, total_amount, status, created_at FROM quotations ORDER BY created_at DESC, id DESC";
    $stmt = $pdo->query($sql);
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching quotations: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Quotations - Electric Shop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; margin-bottom: 25px; }
        .action-buttons { text-align: right; margin-bottom: 20px; }
        .action-buttons a {
            display: inline-block;
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .action-buttons a:hover { background-color: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #007bff; color: white; }
        .view-link {
            color: #28a745;
            text-decoration: none;
            margin-right: 10px;
        }
        .view-link:hover { text-decoration: underline; }
        .status-badge {
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            color: white;
        }
        .status-Issued { background-color: #007bff; }
        .status-Draft { background-color: #6c757d; }
        .status-Accepted { background-color: #28a745; }
        .status-Rejected { background-color: #dc3545; }
        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>All Quotations</h2>
        <div class="action-buttons">
            <a href="create_quotation.php">Create New Quotation</a>
        </div>

        <?php if (empty($quotations)): ?>
            <p style="text-align: center;">No quotations found. Please create one!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Quote No.</th>
                        <th>Date</th>
                        <th>Customer Name</th>
                        <th>Total Amount (₹)</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotations as $quote): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($quote['quotation_number']); ?></td>
                            <td><?php echo htmlspecialchars($quote['quotation_date']); ?></td>
                            <td><?php echo htmlspecialchars($quote['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($quote['total_amount'], 2)); ?></td>
                            <td><span class="status-badge status-<?php echo htmlspecialchars($quote['status']); ?>"><?php echo htmlspecialchars($quote['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($quote['created_at']); ?></td>
                            <td>
                                <a href="view_quotation.php?id=<?php echo htmlspecialchars($quote['id']); ?>" class="view-link">View/Print</a>
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