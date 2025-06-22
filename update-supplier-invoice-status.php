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

// Check if ID and status are provided in the URL
if (isset($_GET['id']) && isset($_GET['status'])) {
    $invoice_id = mysqli_real_escape_string($conn, $_GET['id']);
    $new_status = mysqli_real_escape_string($conn, $_GET['status']);

    // Validate the new status to prevent arbitrary updates
    $allowed_statuses = ['approved', 'rejected', 'kiv', 'pending', 'paid', 'overdue', 'draft']; // Add all possible statuses your system uses
    if (!in_array(strtolower($new_status), $allowed_statuses)) {
        $_SESSION['error_message'] = "Invalid status provided: " . htmlspecialchars($new_status);
        header("location: order-billing.php");
        exit;
    }

    // Prepare an update statement
    $sql = "UPDATE supplier_bills SET status = ? WHERE id = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        // Bind variables to the prepared statement as parameters
        $stmt->bind_param("si", $new_status, $invoice_id);
        
        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Supplier Invoice status updated to '" . ucfirst($new_status) . "' successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating Supplier Invoice status: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Database statement preparation failed: " . $conn->error;
    }
} else {
    $_SESSION['error_message'] = "Missing Supplier Invoice ID or new status.";
}

// Close connection
$conn->close();

// Redirect back to the order-billing page
header("location: order-billing.php");
exit;
?>