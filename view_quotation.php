<?php
// Enable detailed error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // Include your database connection

$quotation = null;
$quotation_items = [];

// Check if an 'id' is provided in the URL
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $quotation_id = trim($_GET['id']);

    try {
        // Fetch main quotation details
        $sql_quote = "SELECT id, quotation_number, quotation_date, customer_name, total_amount, status, created_at FROM quotations WHERE id = :id";
        $stmt_quote = $pdo->prepare($sql_quote);
        $stmt_quote->bindParam(':id', $quotation_id, PDO::PARAM_INT);
        $stmt_quote->execute();
        $quotation = $stmt_quote->fetch(PDO::FETCH_ASSOC);

        if (!$quotation) {
            // If quote not found, redirect
            header("Location: view_quotations.php?status=notfound");
            exit();
        }

        // Fetch quotation items associated with this quotation
        // Join with products table to get product name, model, brand, image for display
        $sql_items = "SELECT qi.quantity, qi.unit_price_at_quote, qi.item_total,
                            p.name AS product_name, p.model AS product_model, p.brand AS product_brand, p.gst_rate AS product_gst_rate, p.image_path
                      FROM quotation_items qi
                      JOIN products p ON qi.product_id = p.id
                      WHERE qi.quotation_id = :quotation_id";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
        $stmt_items->execute();
        $quotation_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("Error fetching quotation details: " . $e->getMessage());
    }
} else {
    // If no ID provided, redirect
    header("Location: view_quotations.php?status=no_id");
    exit();
}

// Calculate Subtotal (Excl. GST) and Total GST Amount for display
$subtotal_excl_gst = 0;
$total_gst_amount = 0;

foreach ($quotation_items as $item) {
    $item_subtotal_before_gst = $item['quantity'] * $item['unit_price_at_quote'];
    $item_gst_amount_calc = $item_subtotal_before_gst * ($item['product_gst_rate'] / 100);

    $subtotal_excl_gst += $item_subtotal_before_gst;
    $total_gst_amount += $item_gst_amount_calc;
}

// Shop details (Placeholder - ideally from a settings table later)
$shop_name = "Electric Mart";
$shop_address = "123, Main Street, Pune, Maharashtra - 411001";
$shop_phone = "+91 98765 43210";
$shop_email = "info@electricmart.com";
$shop_gstin = "27ABCDE1234F1Z5";
$shop_logo_path = "images/electric_mart_logo.png"; // Placeholder for logo

// Ensure the placeholder image folder exists
// You might create an 'images' folder in electric_shop_app/
// And put a dummy logo.png there, e.g., electric_shop_app/images/electric_mart_logo.png

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation #<?php echo htmlspecialchars($quotation['quotation_number']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .quotation-container {
            width: 210mm; /* A4 width */
            min-height: 297mm; /* A4 height */
            margin: 20px auto;
            background: #fff;
            padding: 25mm;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            box-sizing: border-box; /* Include padding in width */
        }
        .header, .footer { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #333; }
        .header p { margin: 5px 0; font-size: 0.9em; color: #666; }
        .header .logo { max-width: 150px; height: auto; margin-bottom: 10px; }

        .quotation-details, .customer-details {
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            font-size: 0.95em;
        }
        .quotation-details div, .customer-details div { width: 48%; }
        .quotation-details strong, .customer-details strong { display: inline-block; width: 120px; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 0.9em;
        }
        .items-table th { background-color: #f2f2f2; }
        .items-table .item-image { max-width: 40px; height: auto; display: block; margin: 0 auto;}

        .totals-section {
            text-align: right;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .totals-section div { margin-bottom: 5px; }
        .totals-section strong { font-size: 1.1em; }

        .terms {
            margin-top: 30px;
            font-size: 0.85em;
            color: #555;
        }
        .terms h4 { margin-bottom: 5px; color: #333; }

        .signature-block {
            margin-top: 50px;
            text-align: right;
            font-size: 0.9em;
        }
        .signature-line {
            border-top: 1px solid #ccc;
            width: 200px;
            margin-left: auto;
            margin-top: 20px;
            padding-top: 5px;
        }

        .print-button-container {
            text-align: center;
            margin-top: 20px;
        }
        .print-button {
            padding: 12px 25px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
        }
        .print-button:hover { background-color: #218838; }

        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }


        /* Print-specific styles */
        @media print {
            body { background-color: #fff; } /* No background color when printing */
            .quotation-container {
                border: none;
                box-shadow: none;
                margin: 0;
                width: 100%; /* Use full width for printing */
                min-height: auto; /* Let content define height */
                padding: 0; /* Remove extra margin for print */
            }
            .print-button-container, .back-link {
                display: none; /* Hide buttons when printing */
            }
        }
    </style>
</head>
<body>
    <div class="quotation-container">
        <div class="header">
            <?php if (file_exists($shop_logo_path)): ?>
                <img src="<?php echo htmlspecialchars($shop_logo_path); ?>" alt="<?php echo htmlspecialchars($shop_name); ?> Logo" class="logo">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($shop_name); ?></h1>
            <p><?php echo nl2br(htmlspecialchars($shop_address)); ?></p>
            <p>Phone: <?php echo htmlspecialchars($shop_phone); ?> | Email: <?php echo htmlspecialchars($shop_email); ?></p>
            <p>GSTIN: <?php echo htmlspecialchars($shop_gstin); ?></p>
            <hr>
            <h2>QUOTATION</h2>
        </div>

        <div class="quotation-details">
            <div>
                <strong>Quotation No:</strong> <?php echo htmlspecialchars($quotation['quotation_number']); ?><br>
                <strong>Date:</strong> <?php echo htmlspecialchars($quotation['quotation_date']); ?><br>
                <strong>Status:</strong> <span class="status-badge status-<?php echo htmlspecialchars($quotation['status']); ?>"><?php echo htmlspecialchars($quotation['status']); ?></span>
            </div>
            <div>
                <strong>Customer Name:</strong> <?php echo htmlspecialchars($quotation['customer_name']); ?><br>
                </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 10%;">Image</th>
                    <th>Product Details</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 15%;">Unit Price (₹)</th>
                    <th style="width: 10%;">GST (%)</th>
                    <th style="width: 15%;">Line Total (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $item_counter = 0;
                foreach ($quotation_items as $item):
                    $item_counter++;
                    $current_item_total_before_gst = $item['quantity'] * $item['unit_price_at_quote'];
                    $current_item_gst_amount = $current_item_total_before_gst * ($item['product_gst_rate'] / 100);
                    $current_item_total_after_gst = $current_item_total_before_gst + $current_item_gst_amount;
                ?>
                    <tr>
                        <td><?php echo $item_counter; ?></td>
                        <td>
                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image">
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                            Model: <?php echo htmlspecialchars($item['product_model']); ?><br>
                            Brand: <?php echo htmlspecialchars($item['product_brand']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item['unit_price_at_quote'], 2)); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item['product_gst_rate'], 2)); ?></td>
                        <td><?php echo htmlspecialchars(number_format($current_item_total_after_gst, 2)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div>Subtotal (Excl. GST): <?php echo htmlspecialchars(number_format($subtotal_excl_gst, 2)); ?></div>
            <div>Total GST Amount: <?php echo htmlspecialchars(number_format($total_gst_amount, 2)); ?></div>
            <div><strong>Grand Total (Incl. GST): <?php echo htmlspecialchars(number_format($quotation['total_amount'], 2)); ?></strong></div>
        </div>

        <div class="terms">
            <h4>Terms & Conditions:</h4>
            <p>1. Prices are valid for 7 days from the quotation date.</p>
            <p>2. Payment: 50% advance, 50% upon delivery.</p>
            <p>3. Installation charges are separate unless explicitly mentioned.</p>
            <p>4. All goods once sold are non-returnable.</p>
            <p>5. Prices are subject to change without prior notice.</p>
        </div>

        <div class="signature-block">
            For <?php echo htmlspecialchars($shop_name); ?><br><br><br>
            <div class="signature-line">Authorized Signatory</div>
        </div>

    </div> <div class="print-button-container">
        <button onclick="window.print()" class="print-button">Print Quotation</button>
    </div>
    <a href="view_quotations.php" class="back-link">Back to All Quotations</a>
    <a href="index.php" class="back-link" style="margin-top: 0;">Back to Dashboard</a>
</body>
</html>