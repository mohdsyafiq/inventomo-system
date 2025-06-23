<?php
// create-invoice.php
// This page handles the creation of bills received from suppliers.

// --- IMPORTANT: DATABASE SCHEMA ---
// This code assumes you have the 'supplier_bills' and 'supplier_bill_items' tables.
/*
CREATE TABLE `supplier_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `bill_number` varchar(255) DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `date_due` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `total_due` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'Pending',
  PRIMARY KEY (`id`)
);

CREATE TABLE `supplier_bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_purchase` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
);
*/

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- Database Connection ---
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// --- Initialize Variables ---
$error_message = '';
$success_message = '';

// Check for success/error messages from save-invoice.php
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it so it doesn't show again on refresh
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear it
}

// Initialize form data variables, potentially from a failed submission
$supplier_id = '';
$date_received = date('Y-m-d');
$due_date = date('Y-m-d', strtotime('+30 days'));
$bill_number = 'BILL-' . date('Ymd-His');
$notes = '';
$posted_items = []; // To hold item data if submission fails

// If there's form data from a failed submission, repopulate
if (isset($_SESSION['form_data'])) {
    $supplier_id = $_SESSION['form_data']['supplier_id'] ?? '';
    $date_received = $_SESSION['form_data']['date_received'] ?? date('Y-m-d');
    $due_date = $_SESSION['form_data']['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $bill_number = $_SESSION['form_data']['bill_number'] ?? ('BILL-' . date('Ymd-His'));
    $notes = $_SESSION['form_data']['notes'] ?? '';
    $posted_items = $_SESSION['form_data']['items'] ?? [];
    unset($_SESSION['form_data']); // Clear form data from session
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        // Main Bill Data
        $supplier_id = $_POST['supplier_id']; 
        $date_received = $_POST['date_received'];
        $due_date = $_POST['due_date'];
        $bill_number = $_POST['bill_number'];
        $notes = $_POST['notes'] ?? '';
        
        // Calculate total from items
        $total_due = 0;
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['product_id'])) {
                    $quantity = $item['quantity'];
                    $rate = $item['rate'];
                    $total_due += ($quantity * $rate);
                }
            }
        }
        
        // Add SST (6%)
        $total_due = $total_due * 1.06;

        // 1. Insert into supplier_bills table
        $stmt_bill = $conn->prepare("INSERT INTO supplier_bills (supplier_id, bill_number, date_received, date_due, total_due, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt_bill->bind_param("isssds", $supplier_id, $bill_number, $date_received, $due_date, $total_due, $notes);
        
        if(!$stmt_bill->execute()) {
            throw new Exception("Error saving bill: " . $stmt_bill->error);
        }
        $bill_id = $conn->insert_id;
        $stmt_bill->close();
        
        // 2. Insert into supplier_bill_items table
        $stmt_items = $conn->prepare("INSERT INTO supplier_bill_items (bill_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
        
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (empty($item['product_id'])) continue; // Skip empty rows

                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $price_at_purchase = $item['rate']; 
                
                $stmt_items->bind_param("iiid", $bill_id, $product_id, $quantity, $price_at_purchase);
                if(!$stmt_items->execute()) {
                    throw new Exception("Error adding item: " . $stmt_items->error);
                }
            }
        }
        $stmt_items->close();

        // If everything is OK, commit the transaction
        $conn->commit();
        $_SESSION['success_message'] = "Invoice #{$bill_number} has been created successfully!";
        header("location: order-billing.php"); // Redirect to a listing page
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Failed to create Invoice: " . $e->getMessage();
    }
}

// --- DATA FETCHING FOR FORM DROPDOWNS ---
$suppliers = [];
// FIXED: Fetch suppliers from `customer_supplier` table using correct column names
$supplier_query = "SELECT id, CONCAT(firstName, ' ', lastName, ' (', companyName, ')') as name FROM `customer_supplier` WHERE registrationType = 'supplier' AND status = 'active' ORDER BY companyName ASC";
$supplier_result = $conn->query($supplier_query);
if (!$supplier_result) {
    $error_message .= "Error fetching suppliers: " . $conn->error;
} else {
    while ($row = $supplier_result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

$products = [];
$product_query = "SELECT id, name, price FROM products ORDER BY name";
$product_result = $conn->query($product_query);
if (!$product_result) {
    $error_message .= "<br>Error: Could not fetch products. Make sure the `products` table exists and has 'id', 'name', 'price' columns.";
} else {
    while ($row = $product_result->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close(); // Close connection after fetching data for display
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Supplier Invoice - Inventomo</title>
    <meta name="description" content="Create a new bill received from a supplier" />
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
            padding: 6px 12px;
            font-size: 14px;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .form-content {
            padding: 30px;
        }

        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #c0392b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 6px;
            color: #2c3e50;
        }

        .required {
            color: #e74c3c;
        }

        .form-control {
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .items-table th {
            background: #c0392b;
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 500;
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .items-table tr:hover {
            background: #f8f9fa;
        }

        .add-item-btn {
            margin-top: 15px;
        }

        .summary-section {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 25px;
            margin-top: 25px;
        }

        .notes-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }

        .totals-section {
            background: #c0392b;
            color: white;
            border-radius: 8px;
            padding: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }

        .total-final {
            border-top: 2px solid rgba(255, 255, 255, 0.3);
            margin-top: 15px;
            padding-top: 15px;
            font-size: 18px;
            font-weight: 600;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s ease;
            transform: translateY(-20px);
        }

        .status-popup.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .status-popup.error {
            background: #e74c3c;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .summary-section {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .items-table {
                font-size: 12px;
            }

            .container {
                margin: 10px;
                border-radius: 8px;
            }

            body {
                padding: 10px;
            }
        }

        body {
    background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), 
                url('assets/img/backgrounds/inside-background.jpeg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    background-repeat: no-repeat;
    min-height: 100vh;
}

/* Ensure layout wrapper takes full space */
.layout-wrapper {
    background: transparent;
    min-height: 100vh;
}

/* Content wrapper with transparent background to show body background */
.content-wrapper {
    background: transparent;
    min-height: 100vh;
}
    </style>
</head>
<body>
    <div id="statusPopup" class="status-popup">
        <span id="statusMessage"></span>
    </div>

    <div class="container">
        <div class="header">
            <h1>Create Supplier Invoice</h1>
            <div class="header-actions">
                <a href="order-billing.php" class="btn btn-secondary">✕ Cancel</a>
                <button type="submit" class="btn btn-primary" form="supplierInvoiceForm">💾 Save Bill</button>
            </div>
        </div>

        <div class="form-content">
            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <span>⚠️</span>
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <span>✅</span>
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>

            <form id="supplierInvoiceForm" method="POST" action="create-invoice.php">
                <!-- Supplier & Basic Info Section -->
                <div class="form-section">
                    <h2 class="section-title">Invoice Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Supplier <span class="required">*</span></label>
                            <select id="supplier_id" name="supplier_id" class="form-control" required>
                                <option value="" disabled selected>Select a supplier...</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>" <?php echo ($supplier['id'] == $supplier_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bill / Invoice Number</label>
                            <input type="text" id="bill_number" name="bill_number" class="form-control" value="<?php echo htmlspecialchars($bill_number); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date Received</label>
                            <input type="date" id="date_received" name="date_received" class="form-control" value="<?php echo htmlspecialchars($date_received); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Due Date</label>
                            <input type="date" id="due_date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($due_date); ?>">
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="form-section">
                    <h2 class="section-title">Items</h2>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th style="width: 15%;">Qty</th>
                                <th style="width: 15%;">Price</th>
                                <th style="width: 15%;">Amount</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="items-table-body">
                            <?php if (!empty($posted_items)): ?>
                                <?php foreach ($posted_items as $index => $item): ?>
                                    <?php
                                        $p_id = $item['product_id'] ?? '';
                                        $qty = $item['quantity'] ?? 1;
                                        $rate = $item['rate'] ?? 0;
                                        $amount = number_format((float)$qty * (float)$rate, 2);
                                    ?>
                                    <tr>
                                        <td>
                                            <select name="items[<?php echo $index; ?>][product_id]" class="form-control item-select" required>
                                                <option value="">Select an item...</option>
                                                <?php foreach ($products as $product): ?>
                                                    <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>" <?php echo ($product['id'] == $p_id) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="items[<?php echo $index; ?>][quantity]" class="form-control item-qty" value="<?php echo htmlspecialchars($qty); ?>" min="1" required></td>
                                        <td><input type="number" name="items[<?php echo $index; ?>][rate]" class="form-control item-rate" step="0.01" value="<?php echo htmlspecialchars($rate); ?>" min="0" required></td>
                                        <td class="item-amount" style="text-align: right;">RM <?php echo $amount; ?></td>
                                        <td><button type="button" class="btn btn-danger" onclick="removeItemRow(this)">×</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-success add-item-btn" onclick="addItemRow()">+ Add Item</button>
                </div>

                <!-- Summary Section -->
                <div class="summary-section">
                    <div class="notes-section">
                        <h3 class="section-title">Notes</h3>
                        <textarea class="form-control" id="notes" name="notes" rows="6" placeholder="Enter any notes about this bill..."><?php echo htmlspecialchars($notes); ?></textarea>
                    </div>
                    <div class="totals-section">
                        <h3 style="margin-bottom: 20px; color: white;">Invoice Summary</h3>
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span id="subtotal-text">RM 0.00</span>
                        </div>
                        <div class="total-row">
                            <span>SST (6%)</span>
                            <span id="sst-text">RM 0.00</span>
                        </div>
                        <div class="total-row total-final">
                            <span>Total Due</span>
                            <span id="total-due-text">RM 0.00</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // PHP-generated JavaScript variable holding product data
        const products = <?php echo json_encode($products); ?>;
        console.log('Products loaded:', products); // DEBUG: Check if products array is populated

        /**
         * Builds HTML options for the product select dropdown.
         * @returns {string} HTML string of options.
         */
        function buildProductOptions() {
            let options = '<option value="" selected disabled>Select an item...</option>';
            if (products && products.length > 0) {
                products.forEach(p => {
                    // Store product price in a data attribute for easy retrieval
                    options += `<option value="${p.id}" data-price="${p.price}">${p.name}</option>`;
                });
            } else {
                console.warn('No products available to build options.'); // DEBUG
            }
            return options;
        }

        /**
         * Adds a new item row to the invoice table.
         */
        function addItemRow() {
            console.log('addItemRow called.'); // DEBUG
            const tableBody = document.getElementById('items-table-body');
            const newIndex = tableBody.rows.length; // Unique index for new row
            const newRow = document.createElement('tr');

            newRow.innerHTML = `
                <td><select name="items[${newIndex}][product_id]" class="form-control item-select" required>${buildProductOptions()}</select></td>
                <td><input type="number" name="items[${newIndex}][quantity]" class="form-control item-qty" value="1" min="1" required></td>
                <td><input type="number" name="items[${newIndex}][rate]" class="form-control item-rate" step="0.01" min="0" required></td>
                <td class="item-amount" style="text-align: right;">RM 0.00</td>
                <td><button type="button" class="btn btn-danger" onclick="removeItemRow(this)">×</button></td>
            `;
            tableBody.appendChild(newRow);
            attachRowListeners(newRow);
            updateTotals(); // Recalculate totals after adding a new row
            console.log('New row added.'); // DEBUG
        }
        
        /**
         * Updates the 'Price' input field of a row based on the selected product.
         * @param {HTMLSelectElement} selectElement - The product select dropdown that changed.
         */
        function updateRowPrice(selectElement) {
            console.log('updateRowPrice called.'); // DEBUG
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const price = selectedOption.dataset.price || 0; // Get price from data-price attribute
            const row = selectElement.closest('tr'); // Find the parent table row
            if (row) {
                const itemRateInput = row.querySelector('.item-rate');
                if (itemRateInput) {
                    itemRateInput.value = parseFloat(price).toFixed(2); // Set the price input
                    console.log('Price updated to:', price); // DEBUG
                } else {
                    console.error('Could not find .item-rate input in the row.');
                }
            } else {
                console.error('Could not find parent row for select element.');
            }
            updateTotals(); // Recalculate totals
        }

        function attachRowListeners(row) {
            const select = row.querySelector('.item-select');
            const qtyInput = row.querySelector('.item-qty');
            const rateInput = row.querySelector('.item-rate');

            select.addEventListener('change', () => {
                const selectedOption = select.options[select.selectedIndex];
                const price = selectedOption.dataset.price || 0;
                rateInput.value = parseFloat(price).toFixed(2);
                updateTotals();
            });

            [qtyInput, rateInput].forEach(input => {
                input.addEventListener('input', updateTotals);
            });
        }

        /**
         * Removes an item row from the invoice table.
         * @param {HTMLButtonElement} button - The 'X' button that was clicked.
         */
        function removeItemRow(button) {
            console.log('removeItemRow called.'); // DEBUG
            const tableBody = document.getElementById('items-table-body');
            if (tableBody.rows.length > 1) {
                const row = button.closest('tr');
                if (row) {
                    row.remove(); // Remove the entire row
                    updateTotals(); // Recalculate totals
                    console.log('Row removed.'); // DEBUG
                } else {
                    console.error('Could not find parent row for remove button.');
                }
            }
        }

        /**
         * Recalculates and updates the subtotal, SST, and total due.
         */
        function updateTotals() {
            console.log('updateTotals called.'); // DEBUG
            const rows = document.querySelectorAll('#items-table-body tr');
            let subtotal = 0;
            const sstRate = 0.06; // 6% SST

            rows.forEach(row => {
                const quantityInput = row.querySelector('.item-qty');
                const rateInput = row.querySelector('.item-rate');
                const amountCell = row.querySelector('.item-amount');

                const quantity = parseFloat(quantityInput ? quantityInput.value : 0) || 0;
                const rate = parseFloat(rateInput ? rateInput.value : 0) || 0;
                const itemAmount = quantity * rate;
                
                subtotal += itemAmount;
                if (amountCell) {
                    amountCell.textContent = 'RM ' + itemAmount.toFixed(2);
                }
            });

            const sst = subtotal * sstRate;
            const totalDue = subtotal + sst;

            document.getElementById('subtotal-text').textContent = 'RM ' + subtotal.toFixed(2);
            document.getElementById('sst-text').textContent = 'RM ' + sst.toFixed(2);
            document.getElementById('total-due-text').textContent = 'RM ' + totalDue.toFixed(2);
            console.log(`Subtotal: ${subtotal.toFixed(2)}, SST: ${sst.toFixed(2)}, Total: ${totalDue.toFixed(2)}`); // DEBUG
        }

        // --- DOMContentLoaded Event Listener ---
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded.'); // DEBUG

            // Add at least one item row if none exist (e.g., on initial load or after an error)
            if (document.getElementById('items-table-body').rows.length === 0) {
                addItemRow();
                console.log('Initial row added because table was empty.'); // DEBUG
            } else {
                // If rows exist (e.g., re-populated from $_SESSION['form_data']), update totals
                const existingRows = document.querySelectorAll('#items-table-body tr');
                existingRows.forEach(attachRowListeners);
                updateTotals();
                console.log('Table already had rows, updating totals.'); // DEBUG
            }

            // Display success or error popup if messages exist in session
            const statusPopup = document.getElementById('statusPopup');
            const statusMessageSpan = document.getElementById('statusMessage');

            // Check for PHP-generated success message
            <?php if (!empty($success_message)): ?>
                statusMessageSpan.textContent = '✅ <?php echo $success_message; ?>';
                statusPopup.classList.add('show');
                statusPopup.classList.remove('error'); // Ensure it's not red
                setTimeout(() => {
                    statusPopup.classList.remove('show');
                }, 5000); // Popup disappears after 5 seconds
                console.log('Success message displayed.'); // DEBUG
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                statusMessageSpan.textContent = '⚠️ Error occurred. Check form for details.';
                statusPopup.classList.add('show', 'error');
                setTimeout(() => {
                    statusPopup.classList.remove('show');
                }, 5000);
                console.log('Error message displayed.'); // DEBUG
            <?php endif; ?>
        });

        // Form submission validation
        document.getElementById('supplierInvoiceForm').addEventListener('submit', function(e) {
            const tableBody = document.getElementById('items-table-body');
            const rows = tableBody.querySelectorAll('tr');
            let hasValidItems = false;

            // Check if at least one row has a selected product
            rows.forEach(row => {
                const productSelect = row.querySelector('.item-select');
                if (productSelect && productSelect.value) {
                    hasValidItems = true;
                }
            });

            if (!hasValidItems) {
                e.preventDefault();
                alert('Please add at least one item to the invoice.');
                return false;
            }

            // Additional validation for required fields
            const supplier = document.getElementById('supplier_id').value;
            const billNumber = document.getElementById('bill_number').value;
            const dateReceived = document.getElementById('date_received').value;

            if (!supplier || !billNumber || !dateReceived) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }

            console.log('Form validation passed, submitting...'); // DEBUG
        });

        // Auto-save functionality (optional)
        function autoSave() {
            const formData = new FormData(document.getElementById('supplierInvoiceForm'));
            // This could be expanded to save draft data to localStorage or session
            console.log('Auto-save triggered'); // DEBUG
        }

        // Trigger auto-save every 30 seconds
        setInterval(autoSave, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('supplierInvoiceForm').submit();
            }
            
            // Ctrl+N to add new item
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                addItemRow();
            }
        });

        // Number formatting helper
        function formatCurrency(amount) {
            return 'RM ' + parseFloat(amount).toFixed(2);
        }

        // Enhanced error handling
        window.addEventListener('error', function(e) {
            console.error('JavaScript error:', e.error);
        });

        // Print functionality (bonus feature)
        function printInvoice() {
            window.print();
        }

        // Export functionality placeholder
        function exportInvoice() {
            console.log('Export functionality could be implemented here');
            // This could export to PDF, Excel, etc.
        }
    </script>
</body>
</html>