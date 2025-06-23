<?php
// Define the active page for the sidebar
$active_page = 'create_po'; 

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Purchase Order - Inventomo</title>
    <meta name="description" content="Create a new Purchase Order" />
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
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
            color: #2c3e50;
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
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .items-table th {
            background: #34495e;
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
            background: #2c3e50;
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
    <div class="container">
        <div class="header">
            <h1>Create Purchase Order</h1>
            <div class="header-actions">
                <a href="order-billing.php" class="btn btn-secondary">✕ Cancel</a>
                <button type="submit" class="btn btn-primary" form="poForm">💾 Save PO</button>
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

            <form id="poForm" method="POST" action="create-purchase-order.php">
                <!-- Supplier & Basic Info Section -->
                <div class="form-section">
                    <h2 class="section-title">Order Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Supplier <span class="required">*</span></label>
                            <select id="supplier_id" name="supplier_id" class="form-control" required>
                                <option value="" disabled selected>Select a supplier...</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['Id']; ?>">
                                        <?php echo htmlspecialchars($supplier['companyName'] ?: $supplier['firstName'] . ' ' . $supplier['lastName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">PO Number</label>
                            <input type="text" id="po_number" name="po_number" class="form-control" value="PO-<?php echo date('Ymd-His'); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Issue Date</label>
                            <input type="date" id="issue_date" name="issue_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expected Delivery</label>
                            <input type="date" id="due_date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
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
                                <th style="width: 15%;">Cost Price</th>
                                <th style="width: 15%;">Amount</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="items-table-body">
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-success add-item-btn" onclick="addItemRow()">+ Add Item</button>
                </div>

                <!-- Summary Section -->
                <div class="summary-section">
                    <div class="notes-section">
                        <h3 class="section-title">Notes</h3>
                        <textarea class="form-control" id="notes" name="notes" rows="6" placeholder="Enter any notes for the supplier..."></textarea>
                    </div>
                    <div class="totals-section">
                        <h3 style="margin-bottom: 20px; color: white;">Order Summary</h3>
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
                        <input type="hidden" name="total_due" id="total_due_input" value="0">
                    </div>
                </div>
            </form>
        </div>
    </div>

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
            const newIndex = tableBody.rows.length;
            
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
        }

        function removeItemRow(button) {
            const tableBody = document.getElementById('items-table-body');
            if (tableBody.rows.length > 1) {
                button.closest('tr').remove();
                updateTotals();
            }
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