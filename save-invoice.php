<?php
// save-invoice.php
// This page handles saving a new supplier invoice to the database.

// Start the session to store success or error messages
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Initialize error message variable
$error_message = '';

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Retrieve main form data ---
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $bill_number = trim($_POST['bill_number'] ?? '');
    $date_received = trim($_POST['date_received'] ?? date('Y-m-d'));
    $date_due = trim($_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'))); // 'due_date' from form maps to 'date_due' in DB
    $notes = trim($_POST['notes'] ?? '');

    // --- Retrieve product items data ---
    // The items array is structured as items[index][product_id], items[index][quantity], items[index][rate]
    $posted_items = $_POST['items'] ?? [];

    // Basic validation
    $is_valid = true;
    if (empty($supplier_id) || $supplier_id === 0) {
        $error_message = "Please select a supplier.";
        $is_valid = false;
    }
    // Generate a bill number if it's empty
    if (empty($bill_number)) {
        $bill_number = 'BILL-' . date('Ymd-His');
    }
    if (empty($posted_items)) {
        $error_message = "Please add at least one item to the bill.";
        $is_valid = false;
    } else {
        foreach ($posted_items as $item) {
            // Ensure product_id, quantity, and rate are present and valid
            if (empty($item['product_id']) || !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0 || !isset($item['rate']) || !is_numeric($item['rate']) || $item['rate'] < 0) {
                $error_message = "Validation failed. Please ensure every item has a product selected, a valid quantity, and a valid price.";
                $is_valid = false;
                break;
            }
        }
    }

    if ($is_valid) {
        // --- Calculate Total Due on Server-Side for security and accuracy ---
        $subtotal = 0;
        foreach($posted_items as $item){
            $subtotal += (float)($item['quantity'] ?? 0) * (float)($item['rate'] ?? 0);
        }
        $sst_rate = 0.06; // Assuming 6% SST
        $sst = $subtotal * $sst_rate;
        $total_due = $subtotal + $sst;

        // --- Start a transaction for safety ---
        $conn->begin_transaction();

        try {
            // 1. Insert into `supplier_bills` table
            $sql_bill = "INSERT INTO supplier_bills (supplier_id, bill_number, date_received, date_due, notes, total_due, status)
                         VALUES (?, ?, ?, ?, ?, ?, 'Pending')"; // Default status 'Pending'

            $stmt_bill = $conn->prepare($sql_bill);
            if (!$stmt_bill) {
                throw new Exception("Database prepare error (supplier_bills): " . $conn->error);
            }

            // 'issssd' -> integer, string, string, string, string, double
            $stmt_bill->bind_param("issssd", $supplier_id, $bill_number, $date_received, $date_due, $notes, $total_due);

            if (!$stmt_bill->execute()) {
                throw new Exception("Error saving supplier bill: " . $stmt_bill->error);
            }

            // 2. Get the ID of the newly created Supplier Bill
            $bill_id = $conn->insert_id;
            $stmt_bill->close();

            // 3. Insert each item into `supplier_bill_items` table
            $sql_items = "INSERT INTO supplier_bill_items (bill_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)";
            $stmt_items = $conn->prepare($sql_items);
            if (!$stmt_items) {
                throw new Exception("Database prepare error (supplier_bill_items): " . $conn->error);
            }

            foreach ($posted_items as $item) {
                $product_id = (int)$item['product_id'];
                $quantity = (int)$item['quantity'];
                $price_at_purchase = (float)$item['rate']; // 'rate' from form maps to 'price_at_purchase' in DB

                // 'iiid' -> integer, integer, integer, double
                $stmt_items->bind_param("iiid", $bill_id, $product_id, $quantity, $price_at_purchase);

                if (!$stmt_items->execute()) {
                    throw new Exception("Error adding item to supplier bill: " . $stmt_items->error);
                }
            }
            $stmt_items->close();

            // If everything was successful, commit the changes and redirect
            $conn->commit();
            $_SESSION['success_message'] = "✅ Supplier Invoice #{$bill_number} has been created successfully!";
            header("Location: order-billing.php"); // Redirect to order-billing.php
            exit;

        } catch (Exception $e) {
            // If any error occurred, roll back all changes
            $conn->rollback();
            $error_message = "❌ Failed to create supplier invoice: " . $e->getMessage();
            $_SESSION['error_message'] = $error_message; // Store error for display after redirect
            $_SESSION['form_data'] = $_POST; // Store form data to repopulate
            header("Location: create-invoice.php"); // Redirect back to the form with error
            exit;
        }
    } else {
        // If initial validation failed, set error message and redirect back
        $_SESSION['error_message'] = $error_message;
        $_SESSION['form_data'] = $_POST; // Re-populate the form data in session
        header("Location: create-invoice.php");
        exit;
    }
} else {
    // If accessed directly without POST data, redirect to create-invoice form
    header("Location: create-invoice.php");
    exit;
}

$conn->close();
?>