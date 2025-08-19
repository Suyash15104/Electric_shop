<?php
// Enable detailed error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // Include your database connection

$message = '';
$products_available = []; // To store products fetched from DB for selection

// --- Fetch all products to populate the dropdown/selection list ---
try {
    $sql = "SELECT id, name, model, brand, unit_price, gst_rate, image_path FROM products ORDER BY name ASC, model ASC";
    $stmt = $pdo->query($sql);
    $products_available = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching available products: " . $e->getMessage());
}

// --- Handle Form Submission (Saving the Quotation) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $quotation_date = trim($_POST['quotation_date'] ?? date('Y-m-d')); // Default to today
    $item_ids = $_POST['item_id'] ?? [];
    $item_quantities = $_POST['item_quantity'] ?? [];

    $errors = [];

    // Basic validation for customer name and at least one item
    if (empty($customer_name)) {
        $errors[] = "Customer name cannot be empty.";
    }
    if (empty($item_ids) || empty($item_quantities) || count($item_ids) != count($item_quantities)) {
        $errors[] = "Please add at least one product to the quotation and ensure quantities are valid.";
    }

    $quotation_items_data = [];
    $total_amount_quote = 0; // Initialize total for the quote

    // Process each submitted item to gather detailed product info and calculate totals
    if (empty($errors)) {
        foreach ($item_ids as $index => $product_id_from_form) {
            $quantity = (int) ($item_quantities[$index] ?? 0);

            if ($quantity <= 0) {
                $errors[] = "Quantity for an item cannot be zero or negative.";
                continue; // Skip to next item if quantity is invalid
            }

            // Fetch product details from DB using the ID to ensure accurate current prices/GST
            $sql_item = "SELECT id, name, model, brand, unit_price, gst_rate FROM products WHERE id = :id";
            $stmt_item = $pdo->prepare($sql_item);
            $stmt_item->bindParam(':id', $product_id_from_form, PDO::PARAM_INT);
            $stmt_item->execute();
            $product_details = $stmt_item->fetch(PDO::FETCH_ASSOC);

            if (!$product_details) {
                $errors[] = "Product with ID " . htmlspecialchars($product_id_from_form) . " not found.";
                continue; // Skip if product doesn't exist
            }

            $item_unit_price = $product_details['unit_price'];
            $item_gst_rate = $product_details['gst_rate'];
            $item_subtotal_before_gst = $item_unit_price * $quantity;
            $item_gst_amount = $item_subtotal_before_gst * ($item_gst_rate / 100);
            $item_total_after_gst = $item_subtotal_before_gst + $item_gst_amount;

            $quotation_items_data[] = [
                'product_id' => $product_details['id'],
                'product_name' => $product_details['name'], // Store name for easier display later
                'product_model' => $product_details['model'], // Store model for easier display later
                'quantity' => $quantity,
                'unit_price_at_quote' => $item_unit_price, // Crucial: price at time of quote
                'gst_rate_at_quote' => $item_gst_rate,    // Crucial: GST at time of quote
                'item_total' => $item_total_after_gst,
            ];

            $total_amount_quote += $item_total_after_gst;
        }
    }

    if (empty($errors)) {
        try {
            // Generate a unique quotation number (simple example: QTN-YYYYMMDD-#####)
            $quotation_number = 'QTN-' . date('Ymd') . '-' . uniqid();

            // Start Transaction for Atomicity
            $pdo->beginTransaction();

            // Insert into quotations table
            $sql_quote = "INSERT INTO quotations (quotation_number, quotation_date, customer_name, total_amount, status) VALUES (:quotation_number, :quotation_date, :customer_name, :total_amount, 'Issued')";
            $stmt_quote = $pdo->prepare($sql_quote);
            $stmt_quote->bindParam(':quotation_number', $quotation_number, PDO::PARAM_STR);
            $stmt_quote->bindParam(':quotation_date', $quotation_date, PDO::PARAM_STR);
            $stmt_quote->bindParam(':customer_name', $customer_name, PDO::PARAM_STR);
            $stmt_quote->bindParam(':total_amount', $total_amount_quote, PDO::PARAM_STR); // PDO::PARAM_STR for DECIMAL

            $stmt_quote->execute();
            $quotation_id = $pdo->lastInsertId(); // Get the ID of the newly created quotation

            // Insert into quotation_items table
            $sql_item_insert = "INSERT INTO quotation_items (quotation_id, product_id, quantity, unit_price_at_quote, item_total) VALUES (:quotation_id, :product_id, :quantity, :unit_price_at_quote, :item_total)";
            $stmt_item_insert = $pdo->prepare($sql_item_insert);

            foreach ($quotation_items_data as $item) {
                $stmt_item_insert->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
                $stmt_item_insert->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $stmt_item_insert->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                $stmt_item_insert->bindParam(':unit_price_at_quote', $item['unit_price_at_quote'], PDO::PARAM_STR);
                $stmt_item_insert->bindParam(':item_total', $item['item_total'], PDO::PARAM_STR);
                $stmt_item_insert->execute();
            }

            $pdo->commit(); // Commit the transaction
            $message = "<div style='color: green;'>Quotation #{$quotation_number} created successfully!</div>";
            // Optionally redirect to view the new quote
            // header("Location: view_quotation.php?id=" . $quotation_id);
            // exit();

            // Clear form fields after success for a new quote
            $customer_name = '';
            // (Other form fields will be reset by page reload)

        } catch (PDOException $e) {
            $pdo->rollBack(); // Rollback if any error occurs
            $message = "<div style='color: red;'>Database error: " . $e->getMessage() . "</div>";
        }
    } else {
        // Display validation errors
        $message = "<div style='color: red;'>" . implode('<br>', $errors) . "</div>";
        // Re-populate form with submitted data if there were errors
        // (You'd need to reconstruct the item rows for this, more complex for now)
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Quotation - Electric Shop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="date"], select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .item-table th, .item-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .item-table th { background-color: #f2f2f2; }
        .item-row select, .item-row input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .add-item-btn, .remove-item-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .add-item-btn {
            background-color: #007bff;
            color: white;
            margin-top: 10px;
        }
        .add-item-btn:hover { background-color: #0056b3; }
        .remove-item-btn {
            background-color: #dc3545;
            color: white;
        }
        .remove-item-btn:hover { background-color: #c82333; }
        .totals-section {
            margin-top: 20px;
            text-align: right;
        }
        .totals-section div {
            margin-bottom: 5px;
        }
        .totals-section strong {
            font-size: 1.1em;
        }
        .btn-submit {
            display: block; /* Make button full width */
            width: 100%;
            padding: 12px 20px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }
        .btn-submit:hover { background-color: #218838; }
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
        <h2>Create New Quotation</h2>
        <?php if (!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="customer_name">Customer Name:</label>
                <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($customer_name ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="quotation_date">Quotation Date:</label>
                <input type="date" id="quotation_date" name="quotation_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <h3>Quotation Items</h3>
            <table class="item-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 15%;">Unit Price (₹)</th>
                        <th style="width: 10%;">GST (%)</th>
                        <th style="width: 15%;">Item Total (₹)</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody id="quotation-items-body">
                    </tbody>
            </table>
            <button type="button" class="add-item-btn" onclick="addItemRow()">Add Item</button>

            <div class="totals-section">
                <div>Subtotal (Excl. GST): <span id="subtotal_excl_gst">0.00</span></div>
                <div>Total GST Amount: <span id="total_gst_amount">0.00</span></div>
                <div><strong>Grand Total (Incl. GST): <span id="grand_total">0.00</span></strong></div>
            </div>

            <button type="submit" class="btn-submit">Generate Quotation</button>
        </form>

        <a href="index.php" class="back-link">Back to Dashboard</a>
    </div>

    <script>
        // Convert PHP products array to JavaScript for client-side use
        const products = <?php echo json_encode($products_available); ?>;
        let itemCounter = 0; // To keep track of item rows for unique IDs

        function addItemRow() {
            itemCounter++;
            const tbody = document.getElementById('quotation-items-body');
            const newRow = tbody.insertRow();
            newRow.id = `item-row-${itemCounter}`;
            newRow.className = 'item-row';

            // Product Select Column
            const productCell = newRow.insertCell();
            const selectElement = document.createElement('select');
            selectElement.name = `item_id[]`; // [] makes it an array in PHP $_POST
            selectElement.required = true;
            selectElement.onchange = function() { updateItemRow(newRow.id); };

            let defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select Product';
            defaultOption.disabled = true;
            defaultOption.selected = true;
            selectElement.appendChild(defaultOption);

            products.forEach(product => {
                let option = document.createElement('option');
                option.value = product.id;
                option.textContent = `${product.name} - ${product.model} (${product.brand})`;
                selectElement.appendChild(option);
            });
            productCell.appendChild(selectElement);

            // Quantity Input Column
            const qtyCell = newRow.insertCell();
            const qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.name = `item_quantity[]`;
            qtyInput.value = '1';
            qtyInput.min = '1';
            qtyInput.required = true;
            qtyInput.onchange = function() { updateItemRow(newRow.id); };
            qtyInput.onkeyup = function() { updateItemRow(newRow.id); }; // Also update on key up
            qtyCell.appendChild(qtyInput);

            // Unit Price Display Column (non-editable, for current price from DB)
            const unitPriceCell = newRow.insertCell();
            unitPriceCell.className = 'unit-price-display';
            unitPriceCell.textContent = '0.00'; // Initial display

            // GST Rate Display Column (non-editable, for current GST from DB)
            const gstRateCell = newRow.insertCell();
            gstRateCell.className = 'gst-rate-display';
            gstRateCell.textContent = '0.00'; // Initial display

            // Item Total Display Column
            const itemTotalCell = newRow.insertCell();
            itemTotalCell.className = 'item-total-display';
            itemTotalCell.textContent = '0.00'; // Initial display

            // Action Button Column (Remove)
            const actionCell = newRow.insertCell();
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-item-btn';
            removeBtn.textContent = 'X';
            removeBtn.onclick = function() {
                newRow.remove();
                calculateTotals(); // Recalculate after removing a row
            };
            actionCell.appendChild(removeBtn);

            // Update this new row immediately to populate price/GST/total
            updateItemRow(newRow.id);
        }

        function updateItemRow(rowId) {
            const row = document.getElementById(rowId);
            const selectElement = row.querySelector('select[name="item_id[]"]');
            const qtyInput = row.querySelector('input[name="item_quantity[]"]');
            const unitPriceDisplay = row.querySelector('.unit-price-display');
            const gstRateDisplay = row.querySelector('.gst-rate-display');
            const itemTotalDisplay = row.querySelector('.item-total-display');

            const productId = selectElement.value;
            let quantity = parseFloat(qtyInput.value);

            // Basic client-side validation for quantity
            if (isNaN(quantity) || quantity < 1) {
                quantity = 1;
                qtyInput.value = 1; // Correct the input if invalid
            }

            const selectedProduct = products.find(p => p.id == productId);

            if (selectedProduct) {
                const unitPrice = parseFloat(selectedProduct.unit_price);
                const gstRate = parseFloat(selectedProduct.gst_rate);
                const itemSubtotalExclGst = unitPrice * quantity;
                const itemGstAmount = itemSubtotalExclGst * (gstRate / 100);
                const itemTotal = itemSubtotalExclGst + itemGstAmount;

                unitPriceDisplay.textContent = unitPrice.toFixed(2);
                gstRateDisplay.textContent = gstRate.toFixed(2);
                itemTotalDisplay.textContent = itemTotal.toFixed(2);
            } else {
                unitPriceDisplay.textContent = '0.00';
                gstRateDisplay.textContent = '0.00';
                itemTotalDisplay.textContent = '0.00';
            }
            calculateTotals(); // Recalculate grand totals whenever an item row changes
        }

        function calculateTotals() {
            let subtotalExclGst = 0;
            let totalGstAmount = 0;
            let grandTotal = 0;

            const itemRows = document.querySelectorAll('#quotation-items-body .item-row');
            itemRows.forEach(row => {
                const selectElement = row.querySelector('select[name="item_id[]"]');
                const qtyInput = row.querySelector('input[name="item_quantity[]"]');

                const productId = selectElement.value;
                let quantity = parseFloat(qtyInput.value);

                if (isNaN(quantity) || quantity < 1) {
                    quantity = 0; // Treat as 0 for total calculation if invalid
                }

                const selectedProduct = products.find(p => p.id == productId);

                if (selectedProduct) {
                    const unitPrice = parseFloat(selectedProduct.unit_price);
                    const gstRate = parseFloat(selectedProduct.gst_rate);

                    const itemSubtotal = unitPrice * quantity;
                    const itemGst = itemSubtotal * (gstRate / 100);
                    const itemTotalWithGst = itemSubtotal + itemGst;

                    subtotalExclGst += itemSubtotal;
                    totalGstAmount += itemGst;
                    grandTotal += itemTotalWithGst;
                }
            });

            document.getElementById('subtotal_excl_gst').textContent = subtotalExclGst.toFixed(2);
            document.getElementById('total_gst_amount').textContent = totalGstAmount.toFixed(2);
            document.getElementById('grand_total').textContent = grandTotal.toFixed(2);
        }

        // Initial call to add one item row when the page loads
        document.addEventListener('DOMContentLoaded', addItemRow);
    </script>
</body>
</html>