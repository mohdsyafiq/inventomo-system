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


// --- DATA FETCHING FOR FORM DROPDOWNS ---
$suppliers = [];
// MODIFIED: Fetch suppliers from `customer_supplier` table where type is 'supplier'
$supplier_query = "SELECT id, name FROM `customer_supplier` WHERE type = 'supplier' ORDER BY name ASC";
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
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Create Supplier Invoice - Inventomo</title>
    <meta name="description" content="Create a new bill received from a supplier" />
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
    <style>
        /* Custom popup for success/error messages */
        .custom-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #28a745; /* Green for success */
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1100;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.5s, visibility 0.5s, transform 0.5s;
            transform: translateY(-20px);
            font-size: 1rem;
            font-weight: 500;
        }
        .custom-popup.error {
            background-color: #dc3545; /* Red for error */
        }
        .custom-popup.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
    </style>
</head>

<body>
    <div id="statusPopup" class="custom-popup">
        <i class='bx bx-info-circle me-2'></i> <span id="statusMessage"></span>
    </div>

    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <div class="layout-page">
                <div class="content-wrapper">
                    <form id="supplierInvoiceForm" method="POST" action="save-invoice.php">
                        <div class="container-xxl flex-grow-1 container-p-y">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold py-3 mb-0"><span class="text-muted fw-light">Orders /</span> Create Supplier Invoice</h4>
                                <div class="action-buttons">
                                    <a href="order-billing.php" class="btn btn-secondary"><i class="bx bx-x me-1"></i>Cancel</a>
                                    <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i>Save Bill</button>
                                </div>
                            </div>

                            <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert"><i class="bx bx-error-circle me-2"></i><?php echo $error_message; ?></div>
                            <?php endif; ?>

                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                                            <select id="supplier_id" name="supplier_id" class="form-select" required>
                                                <option value="" disabled selected>Select a supplier...</option>
                                                <?php foreach ($suppliers as $supplier): ?>
                                                    <option value="<?php echo $supplier['id']; ?>" <?php echo ($supplier['id'] == $supplier_id) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="date_received" class="form-label">Date Received</label>
                                            <input type="date" id="date_received" name="date_received" class="form-control" value="<?php echo htmlspecialchars($date_received); ?>" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="due_date" class="form-label">Due Date</label>
                                            <input type="date" id="due_date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($due_date); ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                         <div class="col-md-6 mb-3">
                                            <label for="bill_number" class="form-label">Bill / Invoice Number</label>
                                            <input type="text" id="bill_number" name="bill_number" class="form-control" value="<?php echo htmlspecialchars($bill_number); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <h5 class="card-header">Items</h5>
                                <div class="table-responsive text-nowrap">
                                    <table class="table">
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
                                                            <select name="items[<?php echo $index; ?>][product_id]" class="form-select item-select" required onchange="updateRowPrice(this)">
                                                                <option value="">Select an item...</option>
                                                                <?php foreach ($products as $product): ?>
                                                                    <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>" <?php echo ($product['id'] == $p_id) ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td><input type="number" name="items[<?php echo $index; ?>][quantity]" class="form-control item-qty" value="<?php echo htmlspecialchars($qty); ?>" min="1" required oninput="updateTotals()"></td>
                                                        <td><input type="number" name="items[<?php echo $index; ?>][rate]" class="form-control item-rate" step="0.01" value="<?php echo htmlspecialchars($rate); ?>" min="0" required oninput="updateTotals()"></td>
                                                        <td class="item-amount text-end">RM <?php echo $amount; ?></td>
                                                        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(this)">X</button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-body">
                                    <button type="button" class="btn btn-success" onclick="addItemRow()">+ Add Item</button>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <label for="notes" class="form-label">Notes</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Enter any notes about this bill..."><?php echo htmlspecialchars($notes); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Subtotal</span>
                                                <span id="subtotal-text">RM 0.00</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>SST (6%)</span>
                                                <span id="sst-text">RM 0.00</span>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between fw-bold h5">
                                                <span>Total Due</span>
                                                <span id="total-due-text">RM 0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>
    
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
                <td><select name="items[${newIndex}][product_id]" class="form-select item-select" required onchange="updateRowPrice(this)">${buildProductOptions()}</select></td>
                <td><input type="number" name="items[${newIndex}][quantity]" class="form-control item-qty" value="1" min="1" required oninput="updateTotals()"></td>
                <td><input type="number" name="items[${newIndex}][rate]" class="form-control item-rate" step="0.01" min="0" required oninput="updateTotals()"></td>
                <td class="item-amount text-end">RM 0.00</td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(this)">X</button></td>
            `;
            tableBody.appendChild(newRow);
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

        /**
         * Removes an item row from the invoice table.
         * @param {HTMLButtonElement} button - The 'X' button that was clicked.
         */
        function removeItemRow(button) {
            console.log('removeItemRow called.'); // DEBUG
            const row = button.closest('tr');
            if (row) {
                row.remove(); // Remove the entire row
                updateTotals(); // Recalculate totals
                console.log('Row removed.'); // DEBUG
            } else {
                console.error('Could not find parent row for remove button.');
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
                updateTotals();
                console.log('Table already had rows, updating totals.'); // DEBUG
            }

            // Display success or error popup if messages exist in session
            const statusPopup = document.getElementById('statusPopup');
            const statusMessageSpan = document.getElementById('statusMessage');

            // Check for PHP-generated success message
            <?php if (!empty($success_message)): ?>
                statusMessageSpan.textContent = '<?php echo $success_message; ?>';
                statusPopup.classList.add('show');
                statusPopup.classList.remove('error'); // Ensure it's not red
                statusPopup.querySelector('i').className = 'bx bx-check-circle me-2'; // Check icon
                setTimeout(() => {
                    statusPopup.classList.remove('show');
                }, 5000); // Popup disappears after 5 seconds
                console.log('Success message displayed.'); // DEBUG
            <?php endif; ?>

            // If there was an error message from save-invoice.php (which is already displayed in an alert)
            // Or if an error occurs client-side and needs a popup (not currently implemented for client-side validation errors)
            // You can add more complex logic here if needed.
        });
    </script>
</body>
</html>