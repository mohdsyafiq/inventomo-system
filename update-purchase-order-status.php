<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in, if not then redirect to login page
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
$conn->set_charset("utf8"); // Ensure proper character set

// --- PROCESS STATUS UPDATE ---
// IMPORTANT: Now expecting GET data from the direct link/button
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id']) && isset($_GET['status'])) {
    $po_id = (int)$_GET['id']; // Cast to int for safety
    $new_status = $_GET['status'];

    // Validate the new status
    // Use lowercase for comparison with allowed_statuses array
    // Added 'rejected' and 'kiv' statuses
    $allowed_statuses = ['pending', 'approved', 'cancelled', 'completed', 'rejected', 'kiv'];
    // Make sure these match the options you intend to set from buttons/links.

    if (!in_array(strtolower($new_status), $allowed_statuses)) {
        $_SESSION['error_message'] = "Invalid status provided: " . htmlspecialchars($new_status);
        header("location: view-purchase-order.php?id=" . $po_id); // Redirect back to the PO view
        exit;
    }

    // Prepare an update statement for the PURCHASE ORDERS table
    $sql = "UPDATE purchase_orders SET status = ? WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        // Bind variables to the prepared statement as parameters
        $stmt->bind_param("si", $new_status, $po_id); // 's' for string, 'i' for integer

        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Purchase Order status updated to '" . ucfirst($new_status) . "' successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating Purchase Order status: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Database statement preparation failed: " . $conn->error;
    }
} else {
    $_SESSION['error_message'] = "Missing Purchase Order ID or new status in GET request.";
}

// Close connection
$conn->close();

// Redirect back to the view-purchase-order page or order-billing page if PO ID is missing
if (isset($po_id) && $po_id > 0) {
    header("location: view-purchase-order.php?id=" . $po_id);
} else {
    header("location: order-billing.php"); // Fallback if PO ID was never valid
}
exit;
?>
