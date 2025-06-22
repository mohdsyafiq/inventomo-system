<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Database Connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'inventory_system';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if ID is provided in the URL
if (isset($_GET['id'])) {
    $invoice_id = mysqli_real_escape_string($conn, $_GET['id']);

    // Start a transaction (optional, but good practice for related deletions)
    $conn->begin_transaction();

    try {
        // If supplier invoices have related items (e.g., in a 'supplier_bill_items' table), delete them first.
        // Uncomment and adjust the following block if you have such a table.
        /*
        $sql_delete_items = "DELETE FROM supplier_bill_items WHERE bill_id = ?";
        if ($stmt_items = $conn->prepare($sql_delete_items)) {
            $stmt_items->bind_param("i", $invoice_id);
            $stmt_items->execute();
            $stmt_items->close();
        } else {
            throw new Exception("Error preparing item deletion statement: " . $conn->error);
        }
        */

        // Now, delete the supplier invoice itself
        $sql_delete_invoice = "DELETE FROM supplier_bills WHERE id = ?";
        if ($stmt_invoice = $conn->prepare($sql_delete_invoice)) {
            $stmt_invoice->bind_param("i", $invoice_id);
            if ($stmt_invoice->execute()) {
                $_SESSION['success_message'] = "Supplier Invoice deleted successfully!";
                $conn->commit(); // Commit transaction if successful
            } else {
                throw new Exception("Error deleting Supplier Invoice: " . $stmt_invoice->error);
            }
            $stmt_invoice->close();
        } else {
            throw new Exception("Error preparing Invoice deletion statement: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback(); // Rollback transaction on error
        $_SESSION['error_message'] = "Failed to delete Supplier Invoice: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Missing Supplier Invoice ID.";
}

// Close connection
$conn->close();

// Redirect back to the order-billing page
header("location: order-billing.php");
exit;
?>