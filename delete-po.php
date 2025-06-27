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
    $po_id = mysqli_real_escape_string($conn, $_GET['id']);

    // Start a transaction (optional, but good practice for related deletions)
    $conn->begin_transaction();

    try {
        // Delete related items first (e.g., from invoice_items table)
        // Adjust 'invoice_items' and 'invoice_id' if your related table/column names are different
        $sql_delete_items = "DELETE FROM purchase_orders WHERE id = ?";
        if ($stmt_items = $conn->prepare($sql_delete_items)) {
            $stmt_items->bind_param("i", $po_id);
            $stmt_items->execute();
            $stmt_items->close();
        } else {
            throw new Exception("Error preparing item deletion statement: " . $conn->error);
        }

        // Now, delete the purchase order itself
        $sql_delete_po = "DELETE FROM invoices WHERE id = ?";
        if ($stmt_po = $conn->prepare($sql_delete_po)) {
            $stmt_po->bind_param("i", $po_id);
            if ($stmt_po->execute()) {
                $_SESSION['success_message'] = "Purchase Order deleted successfully!";
                $conn->commit(); // Commit transaction if successful
            } else {
                throw new Exception("Error deleting Purchase Order: " . $stmt_po->error);
            }
            $stmt_po->close();
        } else {
            throw new Exception("Error preparing PO deletion statement: " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback(); // Rollback transaction on error
        $_SESSION['error_message'] = "Failed to delete Purchase Order: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Missing Purchase Order ID.";
}

// Close connection
$conn->close();

// Redirect back to the order-billing page
header("location: order-billing.php");
exit;
?>
