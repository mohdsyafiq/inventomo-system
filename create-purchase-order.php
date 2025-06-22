<?php
// Define the active page for the sidebar
$active_page = 'create_po'; 

// create-purchase-order.php (COMPLETE & FINAL)

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
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

// --- Page Variables ---
$success_message = '';
$error_message = '';

// --- DATA FETCHING FOR FORM DROPDOWNS ---
// Fetch active SUPPLIERS
$suppliers = [];
$supplier_query = "SELECT Id, companyName, firstName, lastName FROM customer_supplier WHERE registrationType = 'supplier' AND status = 'active' ORDER BY companyName, firstName";
$supplier_result = $conn->query($supplier_query);
if(!$supplier_result) {
     $error_message = "Error fetching suppliers: " . $conn->error;
} else {
    while($row = $supplier_result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Fetch PRODUCTS
$products = [];
$product_query = "SELECT id, name, price FROM products ORDER BY name"; 
$product_result = $conn->query($product_query);
if(!$product_result) {
    $error_message .= "<br>Error: The `products` table was not found or is misconfigured.";
} else {
    while($row = $product_result->fetch_assoc()) {
        $products[] = $row;
    }
}


// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        // Main PO Data
        $supplier_id = $_POST['supplier_id']; 
        $issue_date = $_POST['issue_date'];
        $due_date = $_POST['due_date'];
        $po_number = $_POST['po_number'];
        $notes = $_POST['notes'] ?? '';
        $total_amount = $_POST['total_due'] ?? 0;

        // 1. Insert into purchase_orders table
        $stmt_po = $conn->prepare("INSERT INTO purchase_orders (supplier_id, po_number, date_ordered, date_expected, total_amount, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt_po->bind_param("isssds", $supplier_id, $po_number, $issue_date, $due_date, $total_amount, $notes);
        
        if(!$stmt_po->execute()) {
            throw new Exception("Error saving PO: " . $stmt_po->error);
        }
        $purchase_order_id = $conn->insert_id;
        $stmt_po->close();
        
        // 2. Insert into purchase_order_items table
        $stmt_items = $conn->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, cost_price) VALUES (?, ?, ?, ?)");
        
        if (isset($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (empty($item['product_id'])) continue; // Skip empty rows

                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                $cost_price = $item['rate']; 
                
                $stmt_items->bind_param("iiid", $purchase_order_id, $product_id, $quantity, $cost_price);
                if(!$stmt_items->execute()) {
                    throw new Exception("Error adding item: " . $stmt_items->error);
                }
            }
        }
        $stmt_items->close();

        // If everything is OK, commit the transaction
        $conn->commit();
        $_SESSION['success_message'] = "Purchase Order #{$po_number} has been created successfully!";
        header("location: order-billing.php"); // Redirect to a listing page
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Failed to create Purchase Order: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Create Purchase Order - Inventomo</title>
    <meta name="description" content="Create a new Purchase Order" />
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
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <div class="layout-page">
                <div class="content-wrapper">
                    <form id="poForm" method="POST" action="create-purchase-order.php">
                        <div class="container-xxl flex-grow-1 container-p-y">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold py-3 mb-0"><span class="text-muted fw-light">Orders /</span> Create Purchase Order</h4>
                                <div class="action-buttons">
                                    <a href="order-billing.php" class="btn btn-secondary"><i class="bx bx-x me-1"></i>Cancel</a>
                                    <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i>Save PO</button>
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
                                                    <option value="<?php echo $supplier['Id']; ?>">
                                                        <?php echo htmlspecialchars($supplier['companyName'] ?: $supplier['firstName'] . ' ' . $supplier['lastName']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="issue_date" class="form-label">Issue Date</label>
                                            <input type="date" id="issue_date" name="issue_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="due_date" class="form-label">Expected Delivery</label>
                                            <input type="date" id="due_date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                         <div class="col-md-6 mb-3">
                                            <label for="po_number" class="form-label">PO Number</label>
                                            <input type="text" id="po_number" name="po_number" class="form-control" value="PO-<?php echo date('Ymd-His'); ?>" required>
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
                                                <th style="width: 15%;">Cost Price</th>
                                                <th style="width: 15%;">Amount</th>
                                                <th style="width: 5%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="items-table-body">
                                            </tbody>
                                    </table>
                                </div>
                                <div class="card-body">
                                    <button type="button" class="btn btn-primary" onclick="addItemRow()">+ Add Item</button>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <label for="notes" class="form-label">Notes</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Enter any notes for the supplier..."></textarea>
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
                                            <input type="hidden" name="total_due" id="total_due_input" value="0">
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
        const products = <?php echo json_encode($products); ?>;

        function buildProductOptions() {
            let options = '<option value="" selected disabled>Select an item...</option>';
            products.forEach(p => {
                options += `<option value="${p.id}" data-price="${p.price}">${p.name}</option>`;
            });
            return options;
        }

        function addItemRow() {
            const tableBody = document.getElementById('items-table-body');
            // Get the current row count to use as the index for the new row's form fields.
            const newIndex = tableBody.rows.length;
            
            const newRow = document.createElement('tr');

            // Use the 'newIndex' to group the fields for this row together (e.g., items[0][product_id], items[0][quantity], etc.)
            newRow.innerHTML = `
                <td><select name="items[${newIndex}][product_id]" class="form-select item-select" required>${buildProductOptions()}</select></td>
                <td><input type="number" name="items[${newIndex}][quantity]" class="form-control item-qty" value="1" min="1" required></td>
                <td><input type="number" name="items[${newIndex}][rate]" class="form-control item-rate" step="0.01" min="0" required></td>
                <td class="item-amount text-end">RM 0.00</td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(this)">X</button></td>
            `;

            tableBody.appendChild(newRow);
            attachRowListeners(newRow);
        }

        function removeItemRow(button) {
            button.closest('tr').remove();
            updateTotals();
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

        function updateTotals() {
            let subtotal = 0;
            const rows = document.querySelectorAll('#items-table-body tr');
            
            rows.forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
                const amount = qty * rate;
                row.querySelector('.item-amount').textContent = 'RM ' + amount.toFixed(2);
                subtotal += amount;
            });

            const sst = subtotal * 0.06;
            const totalDue = subtotal + sst;

            document.getElementById('subtotal-text').textContent = 'RM ' + subtotal.toFixed(2);
            document.getElementById('sst-text').textContent = 'RM ' + sst.toFixed(2);
            document.getElementById('total-due-text').textContent = 'RM ' + totalDue.toFixed(2);
            document.getElementById('total_due_input').value = totalDue.toFixed(2);
        }

        // Add one empty row to start with
        document.addEventListener('DOMContentLoaded', addItemRow);
    </script>
    </body>
</html>