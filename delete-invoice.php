<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php"); // Redirect to login page if not authenticated
    exit;
}

// Database Connection Details
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'inventory_system';

// Establish database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    // Log and terminate if database connection fails
    error_log("Database connection failed: " . $conn->connect_error);
    die("Error: Could not connect to the database.");
}
$conn->set_charset("utf8"); // Set character set for proper data handling

// Check if invoice ID is provided in the URL
if (isset($_GET['id'])) {
    $invoice_id = (int)$_GET['id']; // Cast to integer for security and type consistency
    error_log("DEBUG: delete-customer-invoice.php initiated for ID: " . $invoice_id); // DEBUG LOG

    // Start a database transaction for atomicity.
    // This ensures that all operations (stock reversal, item deletion, invoice deletion)
    // either complete successfully, or all are rolled back if any step fails.
    $conn->begin_transaction();

    try {
        // --- Step 1: Fetch invoice items to revert stock ---
        // Before deleting items, we need their quantities and item IDs to add them back to inventory.
        $sql_fetch_items = "SELECT item_id, quantity FROM invoice_items WHERE invoice_id = ?";
        if ($stmt_fetch_items = $conn->prepare($sql_fetch_items)) {
            $stmt_fetch_items->bind_param("i", $invoice_id);
            $stmt_fetch_items->execute();
            $result_items = $stmt_fetch_items->get_result();
            $items_to_revert = []; // Array to store item data for stock reversal
            while ($row = $result_items->fetch_assoc()) {
                $items_to_revert[] = $row;
            }
            $stmt_fetch_items->close();
            error_log("DEBUG: Fetched " . count($items_to_revert) . " items for invoice ID " . $invoice_id . " for stock reversal."); // DEBUG LOG
        } else {
            // Throw an exception if preparing the statement fails
            throw new Exception("Error preparing item fetch statement: " . $conn->error);
        }

        // --- Step 2: Reverse stock for each item ---
        // For each item previously on the invoice, increase its stock quantity in `inventory_item`.
        if (!empty($items_to_revert)) {
            $sql_update_stock = "UPDATE inventory_item SET stock = stock + ?, last_updated = NOW() WHERE itemID = ?";
            if ($stmt_update_stock = $conn->prepare($sql_update_stock)) {
                foreach ($items_to_revert as $item) {
                    $stmt_update_stock->bind_param("ii", $item['quantity'], $item['item_id']);
                    $stmt_update_stock->execute();
                    if ($stmt_update_stock->errno) {
                        // Throw an exception if stock update fails for any item
                        throw new Exception("Error updating stock for item ID " . $item['item_id'] . ": " . $stmt_update_stock->error);
                    }
                    error_log("DEBUG: Updated stock for item ID " . $item['item_id'] . " by +" . $item['quantity'] . " for invoice " . $invoice_id . ". Affected: " . $stmt_update_stock->affected_rows); // DEBUG LOG
                }
                $stmt_update_stock->close();
            } else {
                // Throw an exception if preparing the stock update statement fails
                throw new Exception("Error preparing stock update statement: " . $conn->error);
            }
        } else {
            error_log("DEBUG: No items to revert stock for invoice ID " . $invoice_id . "."); // DEBUG LOG
        }

        // --- Step 3: Delete related invoice items from `invoice_items` table ---
        // This is crucial if `invoice_items` does NOT have an `ON DELETE CASCADE` foreign key
        // constraint referencing `customer_invoices`. If it *does*, this step can be removed.
        $sql_delete_items = "DELETE FROM invoice_items WHERE invoice_id = ?"; // Corrected table name
        if ($stmt_items = $conn->prepare($sql_delete_items)) {
            $stmt_items->bind_param("i", $invoice_id);
            $stmt_items->execute();
            if ($stmt_items->errno) {
                // Throw an exception if deleting invoice items fails
                throw new Exception("Error deleting invoice items: " . $stmt_items->error);
            }
            error_log("DEBUG: Deleted " . $stmt_items->affected_rows . " invoice items for invoice ID " . $invoice_id); // DEBUG LOG

            // Optionally, log if no items were affected (e.g., invoice had no items)
            if ($stmt_items->affected_rows === 0 && !empty($items_to_revert)) {
                 error_log("Warning: No invoice items deleted for invoice ID {$invoice_id}. Expected items based on initial fetch.");
            }
            $stmt_items->close();
        } else {
            // Throw an exception if preparing the item deletion statement fails
            throw new Exception("Error preparing item deletion statement: " . $conn->error);
        }

        // --- Step 4: Delete the main Invoice record from `customer_invoices` table ---
        $sql_delete_invoice = "DELETE FROM customer_invoices WHERE id = ?"; // Corrected table name
        if ($stmt_invoice = $conn->prepare($sql_delete_invoice)) {
            $stmt_invoice->bind_param("i", $invoice_id);
            if ($stmt_invoice->execute()) {
                error_log("DEBUG: Main invoice DELETE executed for ID " . $invoice_id . ". Affected rows: " . $stmt_invoice->affected_rows); // DEBUG LOG

                // Check if any row was actually deleted for the main invoice
                if ($stmt_invoice->affected_rows > 0) {
                    // All steps successful, commit the transaction
                    $conn->commit();
                    $_SESSION['success_message'] = "Invoice deleted successfully!";
                    error_log("DEBUG: Invoice ID " . $invoice_id . " deleted successfully."); // DEBUG LOG
                } else {
                    // If affected_rows is 0, no invoice with that ID was found or deleted.
                    throw new Exception("No invoice found with ID: " . $invoice_id . " or invoice already deleted.");
                }
            } else {
                // Throw an exception if executing the main invoice deletion fails
                throw new Exception("Error deleting Invoice: " . $stmt_invoice->error);
            }
            $stmt_invoice->close(); // Close the statement
        } else {
            // Throw an exception if preparing the main invoice deletion statement fails
            throw new Exception("Error preparing Invoice deletion statement: " . $conn->error);
        }

    } catch (Exception $e) {
        // If any error occurs in the try block, rollback the transaction
        $conn->rollback();
        $_SESSION['error_message'] = "Failed to delete Invoice: " . $e->getMessage();
        // Log the detailed error for server-side debugging
        error_log("ERROR: Invoice deletion failed for ID {$invoice_id}: " . $e->getMessage());
    }
} else {
    // If no invoice ID was provided in the URL
    $_SESSION['error_message'] = "Missing Invoice ID for deletion.";
    error_log("ERROR: delete-customer-invoice.php called without invoice ID."); // DEBUG LOG
}

// Close database connection
$conn->close();

// Redirect back to the order-billing page
header("location: order-billing.php");
exit;
?>
