<?php
// Enable error reporting for debugging. IMPORTANT: Disable or restrict this in a production environment.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for user authentication
session_start();

// Database connection details
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';
$conn = null; // Initialize connection variable

// --- GLOBAL HELPER FUNCTIONS ---
// Helper function to get avatar background color based on position
function getAvatarColor($position) {
    switch (strtolower($position)) {
        case 'admin': return 'primary';
        case 'super-admin': return 'danger';
        case 'moderator': return 'warning';
        case 'manager': return 'success';
        case 'staff': return 'info';
        default: return 'secondary';
    }
}

// Helper function to get profile picture path
function getProfilePicture($profile_picture_name, $avatar_path) {
    if (!empty($profile_picture_name) && $profile_picture_name != 'default.jpg' && $profile_picture_name != '1.png') {
        $photo_path = $avatar_path . $profile_picture_name;
        // Check if the file actually exists on the server
        if (file_exists($photo_path)) {
            return $photo_path;
        }
    }
    return null; // Returns null to trigger initials generation or fallback
}

// Function to sanitize input data
function sanitize_input($data) {
    global $conn; // Access the global connection object

    // Ensure $data is a string before trimming/stripslashes
    if (!is_string($data)) {
        $data = (string)$data; // Convert to string if not already
    }

    $data = trim($data);
    $data = stripslashes($data);
    // ENT_QUOTES ensures both single and double quotes are converted
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    // Only apply mysqli_real_escape_string if connection is established and data is a string
    if ($conn instanceof mysqli && is_string($data)) {
        $data = mysqli_real_escape_string($conn, $data);
    }
    return $data;
}

// Initialize messages
$success_message = '';
$error_message = '';

// Check for and display session messages from previous redirects (e.g., successful PO creation)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear the message after displaying
}


// Attempt to establish database connection
try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);

    if (!$conn) {
        // If connection fails, throw an exception to be caught below
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, "utf8");

    // Check if user is logged in, if not, redirect to login page
    // This check is crucial for security and access control.
    if (!isset($_SESSION['user_id']) || $_SESSION["loggedin"] !== true) {
        // Use an absolute path or relative to web root for header redirects
        header("Location: auth-login-basic.html");
        exit(); // Always exit after a header redirect
    }

    // Initialize user variables and fetch details for the navbar/profile display
    $profile_link = "#";
    $current_user_name = "User";
    $current_user_role = "User";
    $current_user_avatar = "1.png"; // Default avatar
    $avatar_path = "uploads/photos/"; // Path where profile pictures are stored

    if (isset($_SESSION['user_id'])) {
        $user_id_sanitized = mysqli_real_escape_string($conn, $_SESSION['user_id']);
        // Using prepared statement for user data fetching
        $user_query_stmt = $conn->prepare("SELECT id, full_name, username, position, profile_picture FROM user_profiles WHERE id = ? LIMIT 1");
        if ($user_query_stmt) {
            $user_query_stmt->bind_param("i", $user_id_sanitized);
            $user_query_stmt->execute();
            $user_result = $user_query_stmt->get_result();
            if ($user_result && $user_result->num_rows > 0) {
                $user_data = $user_result->fetch_assoc();
                $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
                $current_user_role = $user_data['position'];
                if (!empty($user_data['profile_picture']) && file_exists($avatar_path . $user_data['profile_picture'])) {
                    $current_user_avatar = $user_data['profile_picture'];
                } else {
                    $current_user_avatar = '1.png'; // Fallback if file doesn't exist
                }
                $profile_link = "user-profile.php?op=view&Id=" . $user_data['id'];
            }
            $user_query_stmt->close();
        }
    }
    $current_user_avatar_url = getProfilePicture($current_user_avatar, $avatar_path);

    // --- FETCH DATA FOR DROPDOWNS ---

    // Fetch customers for the customer dropdown
    $customers = [];
    $sql_customers = "SELECT id, registrationID, companyName FROM customer_supplier WHERE registrationType = 'customer' ORDER BY companyName ASC";
    $result_customers = $conn->query($sql_customers);
    if (!$result_customers) {
        $error_message .= "Error fetching customers: " . $conn->error;
    } else {
        while($row = $result_customers->fetch_assoc()) {
            $customers[] = $row;
        }
    }

    // Fetch inventory items for the item dropdowns
    $inventory_items = [];
    // Select itemID, product_name, price (assuming this is sale price for invoices), and stock from inventory_item
    $sql_inventory_items = "SELECT itemID, product_name, price, stock FROM inventory_item ORDER BY product_name ASC";
    $result_inventory_items = $conn->query($sql_inventory_items);
    if (!$result_inventory_items) {
        $error_message .= "Error fetching inventory items: " . $conn->error;
    } else {
        while($row = $result_inventory_items->fetch_assoc()) {
            $inventory_items[] = $row;
        }
    }

    // --- HANDLE FORM SUBMISSION (CREATE INVOICE) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create_invoice') {

        // Sanitize and validate main Invoice details
        $invoice_number = sanitize_input($_POST['invoice_number']);
        $invoice_date = sanitize_input($_POST['invoice_date']);
        $customer_id = sanitize_input($_POST['customer_id']);
        $status = sanitize_input($_POST['status'] ?? 'unpaid'); // Default invoice status

        $total_amount_calculated = 0;
        $items_to_insert = [];

        // Server-side validation for items
        if (isset($_POST['item_id']) && is_array($_POST['item_id']) && count($_POST['item_id']) > 0) {
            for ($i = 0; $i < count($_POST['item_id']); $i++) {
                $item_id = intval($_POST['item_id'][$i]);
                $quantity = intval($_POST['item_quantity'][$i]);
                // This is now sale_price from the client, named item_sale_price in form fields
                $sale_price = floatval($_POST['item_sale_price'][$i]);

                // Basic validation for each item
                if ($item_id <= 0 || $quantity <= 0 || $sale_price < 0) {
                    $error_message = "Invalid item data provided. Please check item selection, quantity, and sale price.";
                    break; // Stop processing if any item is invalid
                }

                $line_total = $quantity * $sale_price;
                $total_amount_calculated += $line_total;

                $items_to_insert[] = [
                    'item_id' => $item_id,
                    'item_name' => sanitize_input($_POST['item_name'][$i]), // Use hidden input for item name
                    'quantity' => $quantity,
                    'sale_price' => $sale_price, // Renamed for clarity in internal array
                    'line_total' => $line_total
                ];
            }
            // Apply 6% tax to the total amount
            $total_amount_calculated *= 1.06;
        } else {
            $error_message = "Please add at least one item to the invoice.";
        }

        // Server-side validation for main Invoice details
        if (empty($error_message) && (empty($invoice_number) || empty($invoice_date) || empty($customer_id) || empty($items_to_insert))) {
            $error_message = "Please fill all required Invoice fields and ensure at least one valid item is added.";
        }

        // If no errors, proceed with database operations
        if (empty($error_message)) {
            // Start a database transaction for atomicity
            $conn->begin_transaction();
            try {
                // 1. Check if Invoice number already exists before attempting insert
                $check_invoice_stmt = $conn->prepare("SELECT COUNT(*) FROM customer_invoices WHERE invoice_number = ?"); // Changed table name
                if (!$check_invoice_stmt) {
                    throw new mysqli_sql_exception("Failed to prepare invoice number check statement: " . $conn->error);
                }
                $check_invoice_stmt->bind_param("s", $invoice_number);
                $check_invoice_stmt->execute();
                $check_invoice_stmt->bind_result($existing_invoice_count);
                $check_invoice_stmt->fetch();
                $check_invoice_stmt->close();

                if ($existing_invoice_count > 0) {
                    // Using 1062 code for custom error message logic in catch block
                    throw new mysqli_sql_exception("Invoice number '{$invoice_number}' already exists. Please use a unique invoice number.", 1062);
                }

                // Insert into invoices table
                // Note: The 'id' column in 'invoices' table is assumed to be AUTO_INCREMENT PRIMARY KEY
                // The PHP code does not insert into `id` as it's auto-generated.
                // If your 'invoices' table's primary key is NOT auto_increment, you will need to adjust.
                $stmt_invoice = $conn->prepare("INSERT INTO customer_invoices (invoice_number, customer_id, invoice_date, total_amount, status) VALUES (?, ?, ?, ?, ?)"); // Changed table name
                if (!$stmt_invoice) {
                    throw new mysqli_sql_exception("Failed to prepare invoice insert statement: " . $conn->error);
                }
                // Bind parameters: invoice_number (string), customer_id (int), invoice_date (string), total_amount (double), status (string)
                $stmt_invoice->bind_param("sisds", $invoice_number, $customer_id, $invoice_date, $total_amount_calculated, $status);
                $stmt_invoice->execute();
                if ($stmt_invoice->errno) { // Check for execution error
                    throw new mysqli_sql_exception("Invoice insert execution error: " . $stmt_invoice->error);
                }
                $invoice_id = $conn->insert_id; // Get the AUTO_INCREMENT ID of the newly inserted invoice
                $stmt_invoice->close();

                // 2. Insert into invoice_items (using ON DUPLICATE KEY UPDATE) and update inventory stock
                // This requires a UNIQUE INDEX on (invoice_id, item_id) in invoice_items table for aggregation.
                $stmt_item = $conn->prepare("
                    INSERT INTO invoice_items (invoice_id, item_id, item_name, quantity, sale_price, line_total) -- Changed table name
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        quantity = quantity + VALUES(quantity),        -- Add new quantity to existing quantity
                        line_total = line_total + VALUES(line_total),  -- Add new line_total to existing line_total
                        sale_price = VALUES(sale_price)                -- Update sale price to the latest submitted one
                ");

                // Stock will DECREASE for invoices
                $stmt_stock_update = $conn->prepare("UPDATE inventory_item SET stock = stock - ?, last_updated = NOW() WHERE itemID = ?");

                if (!$stmt_item) {
                    throw new mysqli_sql_exception("Failed to prepare invoice item insert/update statement: " . $conn->error);
                }
                if (!$stmt_stock_update) {
                    throw new mysqli_sql_exception("Failed to prepare stock update statement: " . $conn->error);
                }

                foreach ($items_to_insert as $item) {
                    // --- Stock Check Before Processing Item ---
                    $check_stock_stmt = $conn->prepare("SELECT stock FROM inventory_item WHERE itemID = ?");
                    if (!$check_stock_stmt) {
                        throw new mysqli_sql_exception("Failed to prepare stock check statement: " . $conn->error);
                    }
                    $check_stock_stmt->bind_param("i", $item['item_id']);
                    $check_stock_stmt->execute();
                    $check_stock_stmt->bind_result($current_stock);
                    $check_stock_stmt->fetch();
                    $check_stock_stmt->close();

                    if ($item['quantity'] > $current_stock) {
                        // Custom error code 10001 to distinguish from MySQL's 1062
                        throw new mysqli_sql_exception("Insufficient stock for item '{$item['item_name']}' (ID: {$item['item_id']}). Available: {$current_stock}, Requested: {$item['quantity']}.", 10001);
                    }
                    // --- End Stock Check ---

                    // Insert into invoice_items (will update if duplicate key based on ON DUPLICATE KEY UPDATE)
                    $stmt_item->bind_param("iisidd",
                        $invoice_id,
                        $item['item_id'],
                        $item['item_name'],
                        $item['quantity'],
                        $item['sale_price'], // Use sale_price from the item array
                        $item['line_total']
                    );
                    $stmt_item->execute();
                    if ($stmt_item->errno) {
                        throw new mysqli_sql_exception("Invoice item insert/update execution error for item ID {$item['item_id']}: " . $stmt_item->error);
                    }

                    // Update inventory_item stock (DECREASE for invoice)
                    $stmt_stock_update->bind_param("ii", $item['quantity'], $item['item_id']);
                    $stmt_stock_update->execute();
                    if ($stmt_stock_update->errno) {
                        throw new mysqli_sql_exception("Stock update execution error for item ID {$item['item_id']}: " . $stmt_stock_update->error);
                    }
                }
                $stmt_item->close();
                $stmt_stock_update->close();

                $conn->commit(); // Commit the transaction
                $_SESSION['success_message'] = "Invoice #{$invoice_number} created successfully!";
                header("Location: create-invoice.php"); // Redirect to invoices list page
                exit();

            } catch (mysqli_sql_exception $e) {
                $conn->rollback(); // Rollback on error
                // Check for specific error codes
                if ($e->getCode() == 1062) { // MySQL Duplicate entry error
                    // Attempt to parse the error message to get the duplicate key name
                    $duplicate_key_value = '';
                    $duplicate_key_name = '';
                    if (preg_match("/Duplicate entry '(.+)' for key '(.+)'/", $e->getMessage(), $matches)) {
                        $duplicate_key_value = $matches[1];
                        $duplicate_key_name = $matches[2];
                    }

                    // Customize error message based on the likely key causing the issue
                    if ($duplicate_key_name === 'invoice_number' || strpos($e->getMessage(), 'for key \'invoice_number\'') !== false) {
                        $error_message = "An Invoice with number '{$invoice_number}' already exists. Please use a unique Invoice number.";
                    } else if (strpos($e->getMessage(), 'for key \'unique_invoice_item\'') !== false || strpos($e->getMessage(), 'for key \'item_id\'') !== false) {
                         // This case should ideally not be hit for item duplicates if ON DUPLICATE KEY UPDATE works.
                         // It would only be hit if there's *another* unique constraint on item_id that conflicts, or an issue with ON DUPLICATE.
                         $error_message = "A database constraint was violated (Code: 1062). This usually indicates an unexpected duplicate item entry that was not aggregated. Details: " . htmlspecialchars($e->getMessage());
                    }
                    else {
                        $error_message = "Database Error: Duplicate entry detected. Details: " . htmlspecialchars($e->getMessage());
                    }
                } else if ($e->getCode() == 10001) { // Custom stock insufficient error
                    $error_message = htmlspecialchars($e->getMessage()); // Display the custom stock message directly
                }
                else {
                    $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
                }
                error_log("Create Invoice Transaction Error: " . $e->getMessage()); // Log detailed error
            } catch (Exception $e) {
                $conn->rollback(); // Rollback for any other exceptions
                $error_message = "Application Error during Invoice creation: " . htmlspecialchars($e->getMessage());
                error_log("Create Invoice Application Error: " . $e->getMessage());
            }
        }
    }

} catch (Exception $e) {
    // Catch database connection errors or other general application exceptions
    $error_message = "Application Initialization Error: " . $e->getMessage();
    $conn = null; // Ensure connection is null if it failed to establish
} finally {
    // Close database connection if it was successfully opened
    if ($conn && $conn instanceof mysqli) {
        $conn->close(); // Use close() for mysqli object
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Create Invoice - Inventomo</title>

    <meta name="description" content="Create a new Customer Invoice" />

    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <style>
    /* General styles for consistent look */
    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #566a7f;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .card {
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        border: none;
        margin-bottom: 1.5rem;
    }
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #d9dee3;
        padding: 1.5rem;
        font-size: 1.25rem;
        font-weight: 600;
        color: #566a7f;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .form-label {
        font-weight: 600;
        color: #566a7f;
    }
    .form-control, .form-select {
        border-radius: 0.375rem;
    }
    .btn-primary { background-color: #696cff; border-color: #696cff; }
    .btn-primary:hover { background-color: #5f63f2; border-color: #5f63f2; }
    .btn-secondary { background-color: #6c757d; border-color: #6c757d; }
    .btn-secondary:hover { background-color: #5a6268; border-color: #5a6268; }
    .btn-danger { background-color: #dc3545; border-color: #dc3545; }
    .btn-danger:hover { background-color: #c82333; border-color: #c82333; }
    .btn-success { background-color: #28a745; border-color: #28a745; }
    .btn-success:hover { background-color: #218838; border-color: #218838; }

    /* Item table specific styles */
    .order-items-table th, .order-items-table td {
        vertical-align: middle;
        padding: 0.75rem;
        font-size: 0.875rem;
    }
    .order-items-table th {
        background-color: #f5f5f9;
        font-weight: 600;
        color: #566a7f;
    }
    .order-items-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    .order-items-table .form-control, .order-items-table .form-select {
        height: calc(2.25rem + 2px); /* Standard Bootstrap form-control height */
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
    .remove-item-btn {
        background: none;
        border: none;
        color: #dc3545;
        font-size: 1.25rem;
        cursor: pointer;
        padding: 0 0.5rem;
        transition: color 0.2s;
    }
    .remove-item-btn:hover {
        color: #c82333;
    }
    .total-display {
        font-size: 1.5rem;
        font-weight: 700;
        color: #28a745;
    }
    .text-rm {
        font-weight: bold;
        color: #28a745;
    }
    /* Loading overlay */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        display: none; /* Hidden by default */
    }
    .loading-spinner-message {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #696cff;
        font-weight: 500;
        background: white;
        padding: 1rem 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    /* Custom Status Popup (for server-side messages) */
    .custom-popup {
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: #28a745; /* Green for success */
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 1100;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.5s, visibility 0.5s, transform 0.5s;
        transform: translateY(-20px);
        font-size: 1rem;
        font-weight: 500;
        display: flex; /* Use flex for icon alignment */
        align-items: center;
    }
    .custom-popup.error {
        background-color: #dc3545; /* Red for error */
    }
    .custom-popup.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    /* Profile Avatar Styles */
    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.5rem;
        font-weight: 600;
        font-size: 12px;
        color: white;
        flex-shrink: 0;
        position: relative;
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .dropdown-menu .user-avatar {
        width: 40px;
        height: 40px;
        margin-right: 0.75rem;
    }

    .dropdown-item .d-flex {
        align-items: center;
    }

    .dropdown-item .flex-grow-1 {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    </style>
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
</head>

<body>
    <!-- Custom Status Popup (for server-side messages) -->
    <div id="statusPopup" class="custom-popup">
        <i class='bx bx-info-circle me-2'></i> <span id="statusMessage"></span>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner-message">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span>Processing...</span>
        </div>
    </div>

    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="index.php" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <img width="180" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
                        </span>
                    </a>
                    <a href="javascript:void(0);"
                        class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="index.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Pages</span></li>
                    <li class="menu-item"><a href="inventory.php" class="menu-link"><i class="menu-icon tf-icons bx bx-card"></i><div>Inventory</div></a></li>
                    <li class="menu-item"><a href="stock-management.php" class="menu-link"><i class="menu-icon tf-icons bx bx-list-plus"></i><div>Stock Management</div></a></li>
                    <li class="menu-item"><a href="customer-supplier.php" class="menu-link"><i class="menu-icon tf-icons bx bxs-user-detail"></i><div>Supplier & Customer</div></a></li>
                    <li class="menu-item active"><a href="order-billing.php" class="menu-link"><i class="menu-icon tf-icons bx bx-cart"></i><div>Order & Billing</div></a></li>
                    <li class="menu-item"><a href="report.php" class="menu-link"><i class="menu-icon tf-icons bx bxs-report"></i><div>Report</div></a></li>
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Account</span></li>
                    <li class="menu-item"><a href="user.php" class="menu-link"><i class="menu-icon tf-icons bx bx-user"></i><div>User Management</div></a></li>
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User Dropdown -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                        <?php if ($current_user_avatar_url): ?>
                                            <img src="<?php echo htmlspecialchars($current_user_avatar_url); ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars($profile_link); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">My Profile</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Settings</span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="bx bx-power-off me-2"></i><span class="align-middle">Log Out</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="content-header">
                            <h4 class="page-title"><i class="bx bx-receipt"></i>Create Invoice</h4>
                            <a href="order-billing.php" class="btn btn-secondary"><i class="bx bx-arrow-back me-1"></i>Back</a>
                        </div>

                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bx bx-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bx bx-error-circle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                Invoice Details
                            </div>
                            <div class="card-body">
                                <form id="createInvoiceForm" method="POST" action="create-invoice.php">
                                    <input type="hidden" name="action" value="create_invoice">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="invoice_number" class="form-label">Invoice Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" required placeholder="e.g., INV-2023-001">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="invoice_date" class="form-label">Invoice Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
                                            <select class="form-select" id="customer_id" name="customer_id" required>
                                                <option value="">Select Customer</option>
                                                <?php foreach($customers as $customer): ?>
                                                    <option value="<?= htmlspecialchars($customer['id']) ?>"><?= htmlspecialchars($customer['companyName']) ?> (Reg ID: <?= htmlspecialchars($customer['registrationID']) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="draft">Draft</option>
                                                <option value="unpaid" selected>Unpaid</option>
                                                <option value="paid">Paid</option>
                                                <option value="cancelled">Cancelled</option>
                                            </select>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <h5 class="mb-3"><i class="bx bx-list-ul"></i>Invoice Items</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered invoice-items-table" id="invoiceItemsTable">
                                            <thead>
                                                <tr>
                                                    <th style="width: 35%;">Item</th>
                                                    <th style="width: 15%;">Quantity</th>
                                                    <th style="width: 20%;">Sale Price (RM)</th>
                                                    <th style="width: 20%;">Line Total (RM)</th>
                                                    <th style="width: 10%;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Item rows will be added here by JavaScript -->
                                                <!-- Template row is hidden and cloned by JS -->
                                                <tr class="item-row-template" style="display: none;">
                                                    <td>
                                                        <select class="form-select item-select" name="item_id[]" required disabled>
                                                            <option value="">Select an Item</option>
                                                            <?php foreach($inventory_items as $item): ?>
                                                                <option value="<?= htmlspecialchars($item['itemID']) ?>"
                                                                        data-item_name="<?= htmlspecialchars($item['product_name']) ?>"
                                                                        data-sale_price="<?= htmlspecialchars($item['price']) ?>"
                                                                        data-stock="<?= htmlspecialchars($item['stock']) ?>">
                                                                    <?= htmlspecialchars($item['product_name']) ?> (Stock: <?= htmlspecialchars($item['stock']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <input type="hidden" class="item-name-hidden" name="item_name[]" disabled>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control item-quantity" name="item_quantity[]" min="1" value="1" required disabled>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control item-sale-price" name="item_sale_price[]" step="0.01" min="0" value="0.00" required readonly disabled>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control line-total" name="line_total_display[]" value="0.00" readonly disabled>
                                                        <!-- Hidden input to send line_total to PHP -->
                                                        <input type="hidden" class="line-total-hidden" name="line_total[]" disabled>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn remove-item-btn text-danger" disabled><i class="bx bx-trash"></i></button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                                    <td><input type="text" class="form-control" id="subtotal" value="RM 0.00" readonly></td>
                                                    <td></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="text-end"><strong>Tax (6%):</strong></td>
                                                    <td><input type="text" class="form-control" id="taxAmount" value="RM 0.00" readonly></td>
                                                    <td></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                                    <td><input type="text" class="form-control total-display" id="grandTotal" value="RM 0.00" readonly></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary mt-3" id="addItemRowBtn"><i class="bx bx-plus me-1"></i>Add Item</button>

                                    <div class="mt-4 text-end">
                                        <button type="submit" class="btn btn-primary" id="submitInvoiceBtn"><i class="bx bx-save me-1"></i>Create Invoice</button>
                                        <a href="invoices.php" class="btn btn-secondary ms-2">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">© <script>document.write(new Date().getFullYear());</script> Inventomo. All rights reserved.</div>
                            <div>
                                <a href="#" class="footer-link me-4">Documentation</a>
                                <a href="#" class="footer-link me-4">Support</a>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const TAX_RATE = 0.06; // 6% tax

        const form = document.getElementById('createInvoiceForm');
        const invoiceItemsTableBody = document.querySelector('#invoiceItemsTable tbody');
        const addItemRowBtn = document.getElementById('addItemRowBtn');
        const subtotalInput = document.getElementById('subtotal');
        const taxAmountInput = document.getElementById('taxAmount');
        const grandTotalInput = document.getElementById('grandTotal');
        const submitInvoiceBtn = document.getElementById('submitInvoiceBtn');
        const loadingOverlay = document.getElementById('loadingOverlay');

        // Store available items (from PHP) for dynamic updates
        const availableItems = <?php echo json_encode($inventory_items); ?>;

        // Initial add of one item row when the page loads
        addItemRow();

        // Add Item Row button event listener
        addItemRowBtn.addEventListener('click', addItemRow);

        // Event delegation for changes within item rows
        invoiceItemsTableBody.addEventListener('change', function(event) {
            if (event.target.classList.contains('item-select')) {
                updateItemRowDetails(event.target);
            }
            calculateTotals();
            validateForm();
        });

        // Event delegation for input changes (quantity, sale price)
        invoiceItemsTableBody.addEventListener('input', function(event) {
            if (event.target.classList.contains('item-quantity') || event.target.classList.contains('item-sale-price')) {
                updateLineTotal(event.target.closest('tr'));
            }
            calculateTotals();
            validateForm();
        });

        // Event delegation for removing item rows
        invoiceItemsTableBody.addEventListener('click', function(event) {
            if (event.target.closest('.remove-item-btn')) {
                const rowToRemove = event.target.closest('tr');
                removeItemRow(rowToRemove);
                calculateTotals(); // Recalculate totals after removal
                validateForm();
            }
        });

        // Event listeners for main Invoice details fields to trigger validation
        document.getElementById('invoice_number').addEventListener('input', validateForm);
        document.getElementById('invoice_date').addEventListener('change', validateForm);
        document.getElementById('customer_id').addEventListener('change', validateForm);
        document.getElementById('status').addEventListener('change', validateForm);


        // Form submission listener
        form.addEventListener('submit', function(event) {
            if (!validateForm(true)) { // Pass true to show errors if form is submitted manually
                event.preventDefault(); // Stop form submission if validation fails
                showAlert('Please correct the errors in the form before submitting.', 'danger');
            } else {
                showLoading(); // Show loading spinner on successful client-side validation
            }
        });

        // Initial form validation on load
        validateForm();
        calculateTotals(); // Call initially to set values

        // --- FUNCTIONS ---

        function addItemRow() {
            const templateRow = document.querySelector('.item-row-template');
            const newRow = templateRow.cloneNode(true);
            newRow.classList.remove('item-row-template');
            newRow.style.display = 'table-row';
            invoiceItemsTableBody.appendChild(newRow);

            newRow.querySelectorAll('[disabled]').forEach(element => {
                element.removeAttribute('disabled');
            });

            const select = newRow.querySelector('.item-select');
            const quantityInput = newRow.querySelector('.item-quantity');
            const salePriceInput = newRow.querySelector('.item-sale-price');
            const lineTotalDisplay = newRow.querySelector('.line-total');
            const lineTotalHidden = newRow.querySelector('.line-total-hidden');
            const itemNameHidden = newRow.querySelector('.item-name-hidden');

            select.value = ""; // Clear selection
            itemNameHidden.value = "";
            quantityInput.value = "1";
            salePriceInput.value = "0.00";
            lineTotalDisplay.value = "0.00";
            lineTotalHidden.value = "0.00";

            select.classList.remove('is-invalid');
            quantityInput.classList.remove('is-invalid');
            salePriceInput.classList.remove('is-invalid');

            const actualRows = invoiceItemsTableBody.querySelectorAll('tr:not(.item-row-template)');
            if (actualRows.length === 1 && select) {
                select.focus();
            }
            validateForm();
        }

        function removeItemRow(row) {
            const actualItemRows = invoiceItemsTableBody.querySelectorAll('tr:not(.item-row-template)');

            if (actualItemRows.length > 1) {
                row.remove();
                calculateTotals(); // Recalculate totals after removal
                validateForm();
            } else {
                showAlert('Cannot remove the last item row. An invoice must have at least one item.', 'warning');
            }
        }

        function updateItemRowDetails(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const row = selectElement.closest('tr');
            const itemNameHidden = row.querySelector('.item-name-hidden');
            const salePriceInput = row.querySelector('.item-sale-price');
            const quantityInput = row.querySelector('.item-quantity');
            const itemStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;

            if (selectedOption.value) {
                const itemName = selectedOption.getAttribute('data-item_name');
                const itemSalePrice = parseFloat(selectedOption.getAttribute('data-sale_price')) || 0;

                itemNameHidden.value = itemName;
                salePriceInput.value = itemSalePrice.toFixed(2);

                let currentQuantity = parseFloat(quantityInput.value);
                if (currentQuantity <= 0 || isNaN(currentQuantity)) {
                    currentQuantity = 1; // Default to 1 if invalid
                    quantityInput.value = 1;
                }
                if (currentQuantity > itemStock) {
                    showAlert(`Quantity for ${itemName} exceeds available stock (${itemStock}). Adjusting to max available.`, 'warning');
                    quantityInput.value = itemStock > 0 ? itemStock : 1; // Set to max stock, or 1 if 0 stock
                }
                if (itemStock === 0) {
                     showAlert(`Item '${itemName}' is out of stock.`, 'warning');
                     quantityInput.value = 0;
                }
            } else {
                itemNameHidden.value = "";
                salePriceInput.value = "0.00";
                quantityInput.value = "1";
            }
            updateLineTotal(row);
        }

        function updateLineTotal(row) {
            const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const salePrice = parseFloat(row.querySelector('.item-sale-price').value) || 0;
            const lineTotalDisplay = row.querySelector('.line-total');
            const lineTotalHidden = row.querySelector('.line-total-hidden');

            const lineTotal = quantity * salePrice;
            lineTotalDisplay.value = lineTotal.toFixed(2);
            lineTotalHidden.value = lineTotal.toFixed(2);

            const quantityInput = row.querySelector('.item-quantity');
            const salePriceInput = row.querySelector('.item-sale-price');
            const itemSelect = row.querySelector('.item-select');

            let rowIsValid = true;

            if (!itemSelect.value) {
                itemSelect.classList.add('is-invalid');
                rowIsValid = false;
            } else {
                itemSelect.classList.remove('is-invalid');
            }

            const selectedOption = itemSelect.options[itemSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const itemStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
                if (quantity > itemStock) {
                    quantityInput.classList.add('is-invalid');
                    showAlert(`Requested quantity (${quantity}) for ${selectedOption.getAttribute('data-item_name')} exceeds available stock (${itemStock}).`, 'danger');
                    rowIsValid = false;
                } else if (quantity <= 0 || isNaN(quantity)) {
                    quantityInput.classList.add('is-invalid');
                    rowIsValid = false;
                } else {
                    quantityInput.classList.remove('is-invalid');
                }
            } else if (isNaN(quantity) || quantity <= 0) {
                 quantityInput.classList.add('is-invalid');
                 rowIsValid = false;
            } else {
                 quantityInput.classList.remove('is-invalid');
            }


            if (isNaN(salePrice) || salePrice < 0) {
                salePriceInput.classList.add('is-invalid');
                rowIsValid = false;
            } else {
                salePriceInput.classList.remove('is-invalid');
            }

            return rowIsValid;
        }

        function calculateTotals() {
            let subtotal = 0;
            invoiceItemsTableBody.querySelectorAll('tr:not(.item-row-template)').forEach(row => {
                const lineTotal = parseFloat(row.querySelector('.line-total-hidden').value) || 0;
                subtotal += lineTotal;
            });

            const taxAmount = subtotal * TAX_RATE;
            const grandTotal = subtotal + taxAmount;

            subtotalInput.value = `RM ${subtotal.toFixed(2)}`;
            taxAmountInput.value = `RM ${taxAmount.toFixed(2)}`;
            grandTotalInput.value = `RM ${grandTotal.toFixed(2)}`;
        }

        function validateForm(showErrors = false) {
            let formOverallIsValid = true;
            let hasAtLeastOneValidItem = false; // Flag to track if any item row is valid

            // Validate main Invoice details fields (invoice number, date, customer, status)
            const invoiceNumber = document.getElementById('invoice_number');
            const invoiceDate = document.getElementById('invoice_date');
            const customerId = document.getElementById('customer_id');
            const status = document.getElementById('status'); // Get status element

            if (!invoiceNumber.value.trim()) {
                formOverallIsValid = false;
                if (showErrors) invoiceNumber.classList.add('is-invalid');
            } else {
                invoiceNumber.classList.remove('is-invalid');
            }

            if (!invoiceDate.value) {
                formOverallIsValid = false;
                if (showErrors) invoiceDate.classList.add('is-invalid');
            } else {
                invoiceDate.classList.remove('is-invalid');
            }

            if (!customerId.value) {
                formOverallIsValid = false;
                if (showErrors) customerId.classList.add('is-invalid');
            } else {
                customerId.classList.remove('is-invalid');
            }

            // Status is required, so check its value
            if (!status.value) {
                formOverallIsValid = false;
                if (showErrors) status.classList.add('is-invalid');
            } else {
                status.classList.remove('is-invalid');
            }


            // Validate item rows
            const actualItemRows = invoiceItemsTableBody.querySelectorAll('tr:not(.item-row-template)');
            if (actualItemRows.length === 0) {
                formOverallIsValid = false; // No item rows physically present
                if (showErrors) showAlert('An invoice must contain at least one item. Please add an item.', 'danger');
            } else {
                actualItemRows.forEach(row => {
                    // updateLineTotal also applies validation styling to individual row fields and returns row validity
                    if (updateLineTotal(row)) { // If the current row IS valid
                        hasAtLeastOneValidItem = true;
                    } else { // If the current row is NOT valid
                        formOverallIsValid = false; // This makes the overall form invalid if any row is invalid
                    }
                });

                // After checking all rows, if no valid item was found across all rows, the form is invalid
                // This scenario means there are rows, but all of them are invalid (e.g., all are "Select an Item")
                if (!hasAtLeastOneValidItem) {
                    formOverallIsValid = false;
                    if (showErrors) showAlert('Please ensure at least one item is selected and all item details are valid.', 'danger');
                }
            }

            // Disable/enable submit button based on overall form validity
            submitInvoiceBtn.disabled = !formOverallIsValid;
            return formOverallIsValid;
        }


        // Show/Hide Loading Overlay
        function showLoading() {
            loadingOverlay.style.display = 'flex';
        }

        function hideLoading() {
            loadingOverlay.style.display = 'none';
        }

        // Custom Status Popup (client-side messages)
        const statusPopup = document.getElementById('statusPopup');
        const statusMessageSpan = document.getElementById('statusMessage');

        function showAlert(message, type = 'info') {
            statusMessageSpan.textContent = message;
            // Remove previous type classes
            statusPopup.classList.remove('error', 'warning', 'info', 'success');
            // Set icon based on type
            let iconClass = 'bx bx-info-circle me-2'; // Default info icon

            if (type === 'danger') {
                statusPopup.classList.add('error');
                iconClass = 'bx bx-error-circle me-2';
            } else if (type === 'success') {
                statusPopup.classList.add('success');
                iconClass = 'bx bx-check-circle me-2';
            } else if (type === 'warning') {
                statusPopup.classList.add('warning');
                iconClass = 'bx bx-warning me-2';
            } else {
                statusPopup.classList.add('info');
            }
            statusPopup.querySelector('i').className = iconClass;

            statusPopup.classList.add('show');
            setTimeout(() => {
                statusPopup.classList.remove('show');
            }, 5000); // Popup disappears after 5 seconds
        }

        // Auto-hide PHP generated messages on page load
        <?php if (!empty($success_message)): ?>
            showAlert('<?php echo htmlspecialchars($success_message); ?>', 'success');
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            showAlert('<?php echo htmlspecialchars($error_message); ?>', 'danger');
        <?php endif; ?>
    });
    </script>
</body>

</html>
