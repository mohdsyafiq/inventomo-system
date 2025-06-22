<?php
// save-po.php (MODIFIED TO REDIRECT)

// Start the session to store success messages
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Database Connection ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'inventory_system';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Retrieve main form data ---
    $supplier_id = (int)$_POST['supplier_id'];
    $customer_id = null; // This form does not have customer_id, so we set it to null
    $po_number_input = trim($_POST['po_number']);
    $date_ordered = trim($_POST['date_ordered']); 
    $date_expected = !empty($_POST['date_expected']) ? trim($_POST['date_expected']) : null;
    $notes = trim($_POST['notes']);
    $status = trim($_POST['status']);

    // --- Retrieve product items data ---
    $product_ids = $_POST['product_id'];
    $quantities = $_POST['quantity'];
    $costs = $_POST['cost_price'];
    $total_amount = 0;
    
    // Generate a PO number if it's empty
    $po_number = !empty($po_number_input) ? $po_number_input : 'PO-' . date('Ymd-His');

    // --- Start a transaction for safety ---
    $conn->begin_transaction();

    try {
        // 1. Calculate the total amount from all items
        for ($i = 0; $i < count($product_ids); $i++) {
            if (!empty($product_ids[$i]) && !empty($quantities[$i]) && !empty($costs[$i])) {
                $total_amount += (float)$quantities[$i] * (float)$costs[$i];
            }
        }

        // 2. Insert into `purchase_orders` table
        $sql_po = "INSERT INTO purchase_orders (supplier_id, customer_id, po_number, date_ordered, date_expected, notes, status, total_amount) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_po = $conn->prepare($sql_po);
        if (!$stmt_po) throw new Exception("Prepare failed (PO): " . $conn->error);

        $stmt_po->bind_param("iisssssd", $supplier_id, $customer_id, $po_number, $date_ordered, $date_expected, $notes, $status, $total_amount);
        
        if (!$stmt_po->execute()) {
            throw new Exception("Execute failed (PO): " . $stmt_po->error);
        }

        // 3. Get the ID of the newly created Purchase Order
        $purchase_order_id = $conn->insert_id;
        $stmt_po->close();

        // 4. Insert each item into `purchase_order_items` table
        $sql_items = "INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, cost_price) VALUES (?, ?, ?, ?)";
        $stmt_items = $conn->prepare($sql_items);
        if (!$stmt_items) throw new Exception("Prepare failed (Items): " . $conn->error);

        for ($i = 0; $i < count($product_ids); $i++) {
            // Only process rows with a product selected
            if (!empty($product_ids[$i])) {
                $product_id = (int)$product_ids[$i];
                $quantity = (int)$quantities[$i];
                $cost_price = (float)$costs[$i];

                $stmt_items->bind_param("iiid", $purchase_order_id, $product_id, $quantity, $cost_price);
                
                if (!$stmt_items->execute()) {
                    throw new Exception("Execute failed (Item " . ($i+1) . "): " . $stmt_items->error);
                }
            }
        }
        $stmt_items->close();
        
        // If everything was successful, commit the changes and redirect
        $conn->commit();
        $_SESSION['success_message'] = "✅ Purchase Order #{$po_number} has been created successfully!";
        header("Location: order-billing.php");
        exit;

    } catch (Exception $e) {
        // If any error occurred, roll back all changes
        $conn->rollback();
        // Store the error message in a session variable
        $_SESSION['error_message'] = "❌ Failed to create Purchase Order: " . $e->getMessage();
        // Redirect back to order-billing.php to display the error
        header("Location: order-billing.php");
        exit;
    }
}

// --- DATA FETCHING for the form dropdowns ---
$suppliers_result = $conn->query("SELECT id, companyName as name FROM customer_supplier WHERE registrationType = 'supplier' ORDER BY name ASC");
$products_result = $conn->query("SELECT id, name, price FROM products ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Purchase Order</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f4f4f9; color: #333; }
        .container { max-width: 900px; margin: 2rem auto; padding: 2rem; background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; color: #007bff; }
        form { display: flex; flex-direction: column; gap: 25px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; font-weight: bold; color: #555; }
        input[type="text"], input[type="date"], input[type="number"], select, textarea {
            padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; transition: border-color 0.3s, box-shadow 0.3s;
        }
        input:focus, select:focus, textarea:focus { border-color: #007bff; box-shadow: 0 0 5px rgba(0,123,255,0.25); outline: none; }
        .btn { cursor: pointer; padding: 10px 20px; border: none; border-radius: 5px; font-size: 1rem; font-weight: bold; transition: background-color 0.3s; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-danger { background-color: #dc3545; color: white; font-size: 0.8rem; padding: 5px 10px; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-success:hover { background-color: #218838; }
        .text-right { text-align: right; margin-top: 1rem; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        #item-list th, #item-list td { padding: 12px; text-align: left; }
        #item-list th { background-color: #f8f9fa; }
        .total-row td { font-weight: bold; font-size: 1.2rem; border-top: 2px solid #333; }
    </style>
</head>
<body>

<div class="container">
    <h2>Create New Purchase Order</h2>

    <?php if(isset($error_message)): ?>
        <div class="message error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="post" action="save-po.php">
        <div class="form-grid">
            <div class="form-group">
                <label for="supplier_id">Supplier <span style="color:red;">*</span></label>
                <select name="supplier_id" id="supplier_id" required>
                    <option value="">-- Select Supplier --</option>
                    <?php if(isset($suppliers_result)) while ($row = $suppliers_result->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date_ordered">Date Ordered <span style="color:red;">*</span></label>
                <input type="date" name="date_ordered" id="date_ordered" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="po_number">PO Number (Optional, auto-generated if blank)</label>
                <input type="text" name="po_number" id="po_number">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="Pending">Pending</option>
                    <option value="Ordered">Ordered</option>
                    <option value="Shipped">Shipped</option>
                    <option value="Received">Received</option>
                </select>
            </div>
        </div>

        <fieldset style="border: 1px solid #ccc; padding: 20px; border-radius: 5px;">
            <legend style="font-weight: bold; color: #555;">Order Items</legend>
            <table id="item-list" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Product <span style="color:red;">*</span></th>
                        <th>Quantity <span style="color:red;">*</span></th>
                        <th>Cost Price <span style="color:red;">*</span></th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Total Amount:</td>
                        <td id="total-amount">RM 0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <button type="button" class="btn btn-success" onclick="addItemRow()" style="margin-top: 15px;">+ Add Product</button>
        </fieldset>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea name="notes" id="notes" rows="4"></textarea>
        </div>

        <div class="text-right">
             <input type="submit" value="Create Purchase Order" class="btn btn-primary">
        </div>
    </form>
</div>

<script type="text/template" id="item-row-template">
    <tr>
        <td>
            <select name="product_id[]" class="product-select" required>
                <option value="">-- Select Product --</option>
                <?php if(isset($products_result)) $products_result->data_seek(0); ?>
                <?php while ($row = $products_result->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>" data-price="<?= $row['price'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </td>
        <td><input type="number" name="quantity[]" class="quantity-input" min="1" value="1" required></td>
        <td><input type="number" name="cost_price[]" class="cost-input" step="0.01" min="0" required></td>
        <td class="subtotal">RM 0.00</td>
        <td><button type="button" class="btn btn-danger" onclick="removeItemRow(this)">X</button></td>
    </tr>
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add one item row by default when the page loads
    addItemRow();
    
    // Use event delegation for dynamically added elements
    document.getElementById('item-list').addEventListener('change', function(e) {
        if (e.target.classList.contains('product-select')) {
            const selectedOption = e.target.options[e.target.selectedIndex];
            const price = selectedOption.getAttribute('data-price') || 0;
            const row = e.target.closest('tr');
            row.querySelector('.cost-input').value = parseFloat(price).toFixed(2);
        }
        updateTotals();
    });

    document.getElementById('item-list').addEventListener('input', function(e) {
        if (e.target.classList.contains('quantity-input') || e.target.classList.contains('cost-input')) {
            updateTotals();
        }
    });
});

function addItemRow() {
    const template = document.getElementById('item-row-template').innerHTML;
    const tbody = document.querySelector('#item-list tbody');
    tbody.insertAdjacentHTML('beforeend', template);
}

function removeItemRow(button) {
    const row = button.closest('tr');
    row.remove();
    updateTotals();
}

function updateTotals() {
    const rows = document.querySelectorAll('#item-list tbody tr');
    let total = 0;

    rows.forEach(row => {
        const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
        const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
        const subtotal = quantity * cost;
        
        row.querySelector('.subtotal').textContent = 'RM ' + subtotal.toFixed(2);
        total += subtotal;
    });

    document.getElementById('total-amount').textContent = 'RM ' + total.toFixed(2);
}
</script>

</body>
</html>