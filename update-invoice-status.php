<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for user authentication
session_start();

// Check if user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Database Connection Details
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'inventory_system';

// Establish a new database connection for this script
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check if connection was successful
if ($conn->connect_error) {
    // If connection fails, log the error and redirect with an error message
    error_log("Database connection failed in update-invoice-status.php: " . $conn->connect_error);
    $_SESSION['error_message'] = "Database connection failed. Please try again later.";
    header("location: order-billing.php"); // Redirect back to the main billing page
    exit;
}

// Set character set to UTF-8 for proper data handling
$conn->set_charset("utf8");

// Check if ID and status are provided in the URL (GET request)
if (isset($_GET['id']) && isset($_GET['status'])) {
    // Sanitize and cast the invoice ID to an integer for safety
    $invoice_id = (int)$_GET['id'];
    // Sanitize the new status string
    $new_status = mysqli_real_escape_string($conn, $_GET['status']);

    // Define a list of allowed statuses to prevent unauthorized status changes
    // This array should include all valid statuses for a customer invoice in your system.
    $allowed_statuses = ['paid', 'unpaid', 'kiv', 'overdue', 'draft', 'cancelled'];
    // Note: 'approved' and 'rejected' are typically for purchase orders,
    // 'paid' and 'unpaid' are more common for invoices.
    // Adjust this array based on your actual invoice status workflow.

    // Validate the new status against the allowed list (case-insensitive)
    if (!in_array(strtolower($new_status), $allowed_statuses)) {
        // If an invalid status is provided, set an error message and redirect
        $_SESSION['error_message'] = "Invalid status provided: " . htmlspecialchars($new_status);
        // Redirect back to the order-billing page, as there's no specific view for invoices in this context yet
        header("location: order-billing.php");
        exit;
    }

    // Prepare an UPDATE statement for the 'customer_invoices' table
    // (Corrected from 'supplier_bills' which was likely a copy-paste error based on 'order-billing.php' context)
    $sql = "UPDATE customer_invoices SET status = ? WHERE id = ?";

    // Prepare the SQL statement for secure execution
    if ($stmt = $conn->prepare($sql)) {
        // Bind parameters: 's' for string (new_status), 'i' for integer (invoice_id)
        $stmt->bind_param("si", $new_status, $invoice_id);

        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            // If update is successful, set a success message
            $_SESSION['success_message'] = "Customer Invoice status updated to '" . ucfirst($new_status) . "' successfully!";
        } else {
            // If execution fails, set an error message including the MySQL error
            error_log("Error executing invoice status update: " . $stmt->error);
            $_SESSION['error_message'] = "Error updating Customer Invoice status: " . $stmt->error;
        }
        // Close the prepared statement
        $stmt->close();
    } else {
        // If statement preparation fails, set an error message
        error_log("Database statement preparation failed in update-invoice-status.php: " . $conn->error);
        $_SESSION['error_message'] = "Database statement preparation failed: " . $conn->error;
    }
} else {
    // If required parameters (id or status) are missing from the GET request, set an error message
    $_SESSION['error_message'] = "Missing Customer Invoice ID or new status in the request.";
}

// Close the database connection
$conn->close();

// Redirect back to the order-billing page after processing
// This ensures that the user sees the updated status and any messages
header("location: order-billing.php");
exit; // Always exit after a header redirect
?>
