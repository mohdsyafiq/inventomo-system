<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for user authentication
session_start();

// Check if user is logged in, if not, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-login-basic.html");
    exit();
}

// Database connection details
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';

// Create database connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check if connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize user variables with proper defaults
$current_user_id = $_SESSION['user_id'];
$current_user_name = "User"; // Default name
$current_user_role = "user"; // Default role
$current_user_avatar = "default.jpg"; // Default avatar filename
$avatar_path = "uploads/photos/"; // Path where profile pictures are stored

// Function to determine user avatar URL based on filename and path
function getUserAvatarUrl($avatar_filename, $avatar_path) {
    // If filename is empty, default, or placeholder, return null to show initials
    if (empty($avatar_filename) || $avatar_filename == 'default.jpg' || $avatar_filename == '1.png') {
        return null;
    }

    // Check if the physical file exists before returning the path
    if (file_exists($avatar_path . $avatar_filename)) {
        return $avatar_path . $avatar_filename;
    }

    // If file doesn't exist, return null to show initials
    return null;
}

// Fetch current user details from database using a prepared statement for security
$user_query = "SELECT full_name, username, position, profile_picture FROM user_profiles WHERE Id = ? LIMIT 1";
$stmt = $conn->prepare($user_query);

if ($stmt) {
    // 'i' specifies the parameter type as integer (assuming Id is INT)
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();

    if ($user_result && $user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();

        // Set user information from fetched data
        $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
        $current_user_role = $user_data['position'];

        // Check and set the user's avatar filename
        if (!empty($user_data['profile_picture'])) {
            $profile_pic_name = $user_data['profile_picture'];
            // Additional check if the file exists on disk
            if (file_exists($avatar_path . $profile_pic_name)) {
                 $current_user_avatar = $profile_pic_name;
            } else {
                 $current_user_avatar = 'default.jpg'; // Fallback if file doesn't exist
            }
        } else {
            $current_user_avatar = 'default.jpg'; // Fallback if no profile picture is set
        }
    }
    $stmt->close(); // Close the prepared statement
}

// Get the final user avatar URL for display
$user_avatar_url = getUserAvatarUrl($current_user_avatar, $avatar_path);

// Helper function to get avatar background color based on user position/role
function getAvatarColor($position) {
    switch (strtolower($position)) {
        case 'admin': return 'primary';
        case 'super-admin': return 'danger';
        case 'manager': return 'success';
        case 'supervisor': return 'warning';
        case 'staff': return 'info';
        default: return 'secondary'; // Default color for unassigned roles
    }
}

$message = ""; // Variable to hold success/error messages for display

// --- Handle Form Submission (POST Request for Stock Deduction) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'stock_out_submit') {
    // Retrieve and sanitize input from the form
    $item_id_to_update = intval($_POST['product_id']); // This is the itemID from inventory_item
    $quantity_to_deduct = intval($_POST['quantity_deducted']);
    $user_who_deducted_stock = $current_user_name; // Use the logged-in user's name

    // Initialize variables for validation and history logging
    $current_item_stock = 0;
    $item_name_for_history = "";

    // Fetch current stock and item name from 'inventory_item' for validation and history
    $stmt_check = $conn->prepare("SELECT product_name, stock FROM inventory_item WHERE itemID = ?");
    if ($stmt_check) {
        $stmt_check->bind_param("i", $item_id_to_update); // 'i' for integer
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $item_data = $result_check->fetch_assoc();
            $item_name_for_history = $item_data['product_name'];
            $current_item_stock = $item_data['stock'];
        }
        $stmt_check->close();
    } else {
        // If preparing the statement fails, log error and set message
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        <strong>Error!</strong> Failed to prepare item check statement: " . htmlspecialchars($conn->error) . "
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    }

    // Server-side validation logic
    if ($item_id_to_update <= 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        <strong>Error!</strong> Please select an item to deduct stock from.
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    } elseif ($quantity_to_deduct <= 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        <strong>Error!</strong> Quantity to deduct must be a positive number.
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    } elseif ($quantity_to_deduct > $current_item_stock) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        <strong>Insufficient Stock!</strong> Cannot deduct {$quantity_to_deduct} units. Only {$current_item_stock} in stock for '{$item_name_for_history}'.
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    } else {
        // All validations passed, proceed with database transaction for atomicity
        $conn->begin_transaction(); // Start transaction

        try {
            // 1. Update stock quantity in the 'inventory_item' table
            // 'stock' is the column name for quantity in your inventory_item table
            $stmt_update = $conn->prepare("UPDATE inventory_item SET stock = stock - ?, last_updated = NOW() WHERE itemID = ?");
            if (!$stmt_update) {
                // If statement preparation fails, throw an exception
                throw new mysqli_sql_exception("Failed to prepare item update statement: " . $conn->error);
            }
            $stmt_update->bind_param("ii", $quantity_to_deduct, $item_id_to_update); // 'ii' for two integers
            $stmt_update->execute();

            // Check if any row was affected by the update
            if ($stmt_update->affected_rows === 0) {
                // If no row was affected, it means the itemID was not found
                throw new mysqli_sql_exception("Failed to update item stock. Item ID '{$item_id_to_update}' not found or no change occurred.");
            }
            $stmt_update->close(); // Close the update statement

            // 2. Record the deduction in the 'stock_out_history' table
            // 'product_id' (which is the itemID), 'product_name', 'quantity_deducted', 'username'
            $stmt_history = $conn->prepare("INSERT INTO stock_out_history (product_id, product_name, quantity_deducted, username, transaction_date) VALUES (?, ?, ?, ?, NOW())");
            if (!$stmt_history) {
                // If statement preparation fails, throw an exception
                throw new mysqli_sql_exception("Failed to prepare history insert statement: " . $conn->error);
            }
            $stmt_history->bind_param("isis", $item_id_to_update, $item_name_for_history, $quantity_to_deduct, $user_who_deducted_stock); // 'isis' for int, string, int, string
            $stmt_history->execute();
            $stmt_history->close(); // Close the history statement

            $conn->commit(); // Commit the transaction if both operations were successful
            // Set success message
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <strong>Success!</strong> Stock deducted and recorded successfully for '<strong>" . htmlspecialchars($item_name_for_history) . "</strong>'. Deducted <strong>{$quantity_to_deduct}</strong> units.
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } catch (mysqli_sql_exception $e) {
            $conn->rollback(); // Rollback the transaction if any error occurs
            // Set error message, HTML-encode the exception message for safety
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                            <strong>Database Error!</strong> Error processing stock out: " . htmlspecialchars($e->getMessage()) . "
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
            error_log("Stock Out Transaction Error: " . $e->getMessage()); // Log detailed error for server-side debugging
        }
    }
}

// --- Fetch Statistics for Stock Out Dashboard ---
$total_transactions = 0;
$total_quantity_deducted = 0;

$sql_stats = "SELECT COUNT(*) as transaction_count, COALESCE(SUM(quantity_deducted), 0) as total_deducted FROM stock_out_history";
$result_stats = $conn->query($sql_stats);
if ($result_stats && $result_stats->num_rows > 0) {
    $stats = $result_stats->fetch_assoc();
    $total_transactions = $stats['transaction_count'];
    $total_quantity_deducted = $stats['total_deducted'];
}

// --- Fetch all available items for the dropdown in the modal ---
$all_items = [];
// Select items from 'inventory_item' table where 'stock' is greater than 0
// Also fetch 'type_product' as per your database schema
$sql_all_items = "SELECT itemID, product_name, type_product, stock FROM inventory_item WHERE stock > 0 ORDER BY product_name ASC";
$result_all_items = $conn->query($sql_all_items);
if ($result_all_items && $result_all_items->num_rows > 0) {
    while($row = $result_all_items->fetch_assoc()) {
        $all_items[] = $row;
    }
}

// --- Fetch Recent Stock Out History for the table display ---
$stock_out_history = [];
$sql_history = "SELECT soh.id, soh.product_id, soh.product_name, soh.quantity_deducted, soh.username, soh.transaction_date
                FROM stock_out_history soh
                ORDER BY soh.transaction_date DESC LIMIT 50"; // Limit to recent 50 transactions
$result_history = $conn->query($sql_history);
if ($result_history && $result_history->num_rows > 0) {
    while($row = $result_history->fetch_assoc()) {
        $stock_out_history[] = $row;
    }
}

// Close the database connection at the very end of the PHP script
$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Stock Out - Inventomo</title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <!-- Icons -->
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />
    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendor/css/core.css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="assets/css/demo.css" />
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <!-- Helpers and Config JS (must be loaded early) -->
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>

    <style>
        /* General Layout & Colors (consistent with previous versions) */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: white;
            position: relative;
            flex-shrink: 0;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        /* Online Status Indicator (optional, adapt if needed) */
        .user-avatar::after {
            content: '';
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background-color: #10b981; /* Green color for online */
            border: 2px solid white;
            border-radius: 50%;
        }
        /* Card Styles */
        .stats-card {
            border-left: 4px solid #ff6b35; /* Orange for stock out related stats */
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(161, 172, 184, 0.2);
        }
        /* Loading spinner for buttons */
        .loading-spinner {
            display: none; /* Hidden by default */
        }
        /* Search highlight for table */
        .search-highlight {
            background-color: #fff3cd; /* Light yellow background */
            padding: 2px 4px;
            border-radius: 3px;
        }
        /* Custom styles for profile links in dropdown */
        .profile-link {
            text-decoration: none;
            color: inherit;
        }
        .profile-link:hover {
            color: inherit;
            background-color: #f8f9fa; /* Light background on hover */
        }
        .dropdown-item .d-flex {
            align-items: center;
        }
        .dropdown-item .flex-grow-1 {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        /* Alert animations */
        .alert {
            animation: fadeInDown 0.5s ease;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Specific for Stock Out Modal details display */
        #productDetailsSection {
            display: none; /* Hidden by default until a product is selected */
        }
    </style>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="index.php" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <img width="160" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
                        </span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
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

                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Pages</span>
                    </li>
                    <li class="menu-item">
                        <a href="inventory.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-card"></i>
                            <div data-i18n="Analytics">Inventory</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="stock-management.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-list-plus"></i>
                            <div data-i18n="Analytics">Stock Management</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="customer-supplier.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bxs-user-detail"></i>
                            <div data-i18n="Analytics">Supplier & Customer</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="order-billing.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Analytics">Order & Billing</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="report.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bxs-report"></i>
                            <div data-i18n="Analytics">Report</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Account</span></li>

                    <li class="menu-item">
                        <a href="user.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div data-i18n="Analytics">User Management</div>
                        </a>
                    </li>
                </ul>
            </aside>
            <!-- / Menu -->

            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" id="navbarSearch" class="form-control border-0 shadow-none"
                                            placeholder="Search history..." />
                            </div>
                        </div>
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                        <?php if ($user_avatar_url): ?>
                                            <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item profile-link" href="user-profile.php?op=view&Id=<?php echo urlencode($current_user_id); ?>">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                                        <?php if ($user_avatar_url): ?>
                                                            <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" alt="Profile Picture">
                                                        <?php else: ?>
                                                            <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?php echo htmlspecialchars($current_user_name); ?></span>
                                                    <small class="text-muted"><?php echo htmlspecialchars(ucfirst($current_user_role)); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="user-profile.php?op=view&Id=<?php echo urlencode($current_user_id); ?>"><i class="bx bx-user me-2"></i> My Profile</a></li>
                                    <li><a class="dropdown-item" href="user-settings.php"><i class="bx bx-cog me-2"></i> Settings</a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="bx bx-power-off me-2"></i> Log Out</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="fw-bold"><span class="text-muted fw-light">Stock Management /</span> Stock Out</h4>
                            <div class="d-flex gap-2">
                            </div>
                        </div>

                        <?php echo $message; // Display messages ?>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card stats-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="card-title mb-1">Total Stock Out Transactions</h5>
                                                <h3 class="text-primary mb-0"><?php echo number_format($total_transactions); ?></h3>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-primary">
                                                    <i class="bx bx-trending-down fs-4"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card stats-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="card-title mb-1">Total Quantity Deducted</h5>
                                                <h3 class="text-warning mb-0"><?php echo number_format($total_quantity_deducted); ?></h3>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-warning">
                                                    <i class="bx bx-package fs-4"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <h5 class="card-header d-flex justify-content-between align-items-center">
                                <span>Stock Out Actions</span>
                                <small class="text-muted">Logged in as: <?php echo htmlspecialchars($current_user_name); ?></small>
                            </h5>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#stockOutModal">
                                        <i class="bx bx-minus-circle me-1"></i> Deduct Stock
                                    </button>
                                    <!-- Added "Back to Stock Management" button here -->
                                    <a href="stock-management.php" class="btn btn-outline-primary">
                                        <i class="bx bx-arrow-back me-1"></i> Back to Stock Management
                                    </a>
                                    <button type="button" class="btn btn-outline-info" onclick="window.print()">
                                        <i class="bx bx-printer me-1"></i> Print History
                                    </button>
                                </div>
                                <div class="alert alert-info d-flex align-items-center" role="alert">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <div>
                                        <strong>Quick Actions:</strong> Press <kbd>Ctrl + B</kbd> to go back to stock management.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Stock Out History</h5>
                                <small class="text-muted">Latest 50 transactions</small>
                            </div>
                            <div class="table-responsive text-nowrap">
                                <table class="table table-bordered" id="historyTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>User</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-border-bottom-0">
                                        <?php if (!empty($stock_out_history)): ?>
                                            <?php foreach ($stock_out_history as $history_item): ?>
                                            <tr>
                                                <td><span class="badge bg-label-secondary">#<?php echo htmlspecialchars($history_item['id']); ?></span></td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($history_item['product_name']); ?></strong>
                                                        <br><small class="text-muted">ID: <?php echo htmlspecialchars($history_item['product_id']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-label-warning">
                                                        -<?php echo htmlspecialchars($history_item['quantity_deducted']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar bg-label-<?php echo getAvatarColor('user'); ?> me-2" style="width: 30px; height: 30px; font-size: 12px;">
                                                            <?php echo strtoupper(substr($history_item['username'], 0, 1)); ?>
                                                        </div>
                                                        <?php echo htmlspecialchars($history_item['username']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?php echo date('M j, Y', strtotime($history_item['transaction_date'])); ?><br>
                                                        <span class="text-muted"><?php echo date('g:i A', strtotime($history_item['transaction_date'])); ?></span>
                                                    </small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="d-flex flex-column align-items-center">
                                                    <i class="bx bx-package display-4 text-muted mb-2"></i>
                                                    <p class="text-muted mb-0">No stock out transactions found</p>
                                                    <small class="text-muted">Start by deducting stock from your inventory</small>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="stockOutModal" tabindex="-1" aria-labelledby="stockOutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockOutModalLabel">
                        <i class="bx bx-minus-circle me-2"></i>Deduct Stock from Inventory
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="stockOutForm" method="POST" action="stock-out.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="stock_out_submit">
                        <input type="hidden" id="modalProductId" name="product_id">

                        <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                            <i class="bx bx-info-circle me-2"></i>
                            <div>
                                <strong>Note:</strong> This action will permanently reduce the stock quantity.
                                Please verify the information before proceeding.
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="modalProductSelect" class="form-label"><i class="bx bx-package me-1"></i>Select Item *</label>
                                <select class="form-select" id="modalProductSelect" required>
                                    <option value="">-- Select an item with available stock --</option>
                                    <?php foreach ($all_items as $item): ?>
                                        <option value="<?php echo htmlspecialchars($item['itemID']); ?>"
                                                data-product_name="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                data-type_product="<?php echo htmlspecialchars($item['type_product'] ?? ''); ?>"
                                                data-stock="<?php echo htmlspecialchars($item['stock']); ?>">
                                            ID: <?php echo htmlspecialchars($item['itemID']); ?> - <?php echo htmlspecialchars($item['product_name']); ?> (Current Stock: <?php echo htmlspecialchars($item['stock']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Product Details Section - initially hidden, shown via JS -->
                        <div id="productDetailsSection" class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modalProductName" class="form-label">Item Name</label>
                                <input type="text" class="form-control" id="modalProductName" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modalProductType" class="form-label">Product Type</label>
                                <input type="text" class="form-control" id="modalProductType" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modalCurrentQuantity" class="form-label">Current Stock Quantity</label>
                                <input type="text" class="form-control" id="modalCurrentQuantity" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modalQuantityDeducted" class="form-label">Quantity to Deduct *</label>
                                <input type="number" class="form-control" id="modalQuantityDeducted"
                                    name="quantity_deducted" min="1" required>
                                <div class="invalid-feedback">Quantity must be greater than 0 and not exceed current stock.</div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Remaining Stock (After Deduction)</label>
                                <input type="text" class="form-control" id="modalRemainingStock" readonly>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bx bx-x me-1"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-warning" id="confirmDeductStockBtn" disabled>
                            <i class="bx bx-minus-circle me-1"></i> Proceed to Deduct
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmDeductStockModal" tabindex="-1" aria-labelledby="confirmDeductStockModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeductStockModalLabel">
                        <i class="bx bx-check-circle me-2"></i>Confirm Stock Deduction
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                        <i class="bx bx-error-circle me-2"></i>
                        <div><strong>Warning:</strong> This action cannot be undone!</div>
                    </div>

                    <p class="mb-3">Are you sure you want to deduct the following stock?</p>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Product:</strong>
                                <span id="confirmationProductName" class="text-primary"></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Quantity to Deduct:</strong>
                                <span id="confirmationQuantityDeducted" class="badge bg-label-warning"></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Current Stock:</strong>
                                <span id="confirmationCurrentStock" class="text-info"></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Remaining After:</strong>
                                <span id="confirmationRemainingStock" class="text-success"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="finalConfirmDeductStockBtn">
                        <span class="loading-spinner spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        <i class="bx bx-check me-1"></i> Confirm Deduction
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal elements
        var stockOutModalElement = document.getElementById('stockOutModal');
        var stockOutModal = new bootstrap.Modal(stockOutModalElement);
        var confirmDeductStockModalElement = document.getElementById('confirmDeductStockModal');
        var confirmDeductStockModal = new bootstrap.Modal(confirmDeductStockModalElement);

        // Form elements
        var modalProductSelect = document.getElementById('modalProductSelect');
        var modalProductIdInput = document.getElementById('modalProductId');
        var modalProductNameInput = document.getElementById('modalProductName');
        var modalProductTypeInput = document.getElementById('modalProductType');
        var modalCurrentQuantityInput = document.getElementById('modalCurrentQuantity');
        var modalQuantityDeductedInput = document.getElementById('modalQuantityDeducted');
        var modalRemainingStockInput = document.getElementById('modalRemainingStock');
        var productDetailsSection = document.getElementById('productDetailsSection');
        var deductStockButton = document.getElementById('confirmDeductStockBtn');
        var finalConfirmButton = document.getElementById('finalConfirmDeductStockBtn');

        // Confirmation elements
        var confirmationProductName = document.getElementById('confirmationProductName');
        var confirmationQuantityDeducted = document.getElementById('confirmationQuantityDeducted');
        var confirmationCurrentStock = document.getElementById('confirmationCurrentStock');
        var confirmationRemainingStock = document.getElementById('confirmationRemainingStock');

        // Search functionality for the history table (navbar search)
        var navbarSearch = document.getElementById('navbarSearch');
        var historyTable = document.getElementById('historyTable');

        // Function to show custom alerts (replaces browser's alert())
        function showAlert(message, type = 'info') {
            const alertContainer = document.querySelector('.container-xxl .d-flex.justify-content-between.align-items-center.mb-4').parentNode;
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`; // Added mt-3 for spacing
            alertDiv.innerHTML = `
                <i class="bx bx-info-circle me-2"></i>
                <div>${message}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            // Insert after the page header
            alertContainer.insertBefore(alertDiv, alertContainer.children[1]); // Assuming first child is header, second is message container

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const bsAlert = bootstrap.Alert.getInstance(alertDiv) || new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }, 5000);
        }

        // Auto-dismiss initial server-side alerts (if any) after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                var bsAlert = bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                stockOutModal.show();
            }
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                window.location.href = 'stock-management.php';
            }
        });

        // Search functionality for history table
        if (navbarSearch && historyTable) {
            navbarSearch.addEventListener('input', function() {
                var searchTerm = this.value.toLowerCase();
                var rows = historyTable.querySelectorAll('tbody tr');

                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    var shouldShow = text.includes(searchTerm);
                    row.style.display = shouldShow ? '' : 'none';

                    var cells = row.querySelectorAll('td');
                    cells.forEach(function(cell) {
                        var originalHtml = cell.getAttribute('data-original-html');
                        if (!originalHtml) {
                            originalHtml = cell.innerHTML;
                            cell.setAttribute('data-original-html', originalHtml);
                        }

                        if (searchTerm.length > 0) {
                            var regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                            cell.innerHTML = originalHtml.replace(regex, '<span class="search-highlight">$1</span>');
                        } else {
                            cell.innerHTML = originalHtml;
                        }
                    });
                });
            });
        }

        // Reset modal when opened
        stockOutModalElement.addEventListener('show.bs.modal', function() {
            resetModal();
            setTimeout(function() {
                modalProductSelect.focus();
            }, 100);
        });

        // Product selection handler
        modalProductSelect.addEventListener('change', function() {
            updateProductDetails();
            validateForm();
            // Only focus on quantity if a product is selected
            if (modalProductSelect.value) {
                modalQuantityDeductedInput.focus();
            }
        });

        // Quantity input handler
        modalQuantityDeductedInput.addEventListener('input', function() {
            updateRemainingStock();
            validateQuantity();
            validateForm();
        });

        // Function to reset all modal fields and state
        function resetModal() {
            modalProductSelect.value = '';
            modalProductIdInput.value = '';
            modalProductNameInput.value = '';
            modalProductTypeInput.value = '';
            modalCurrentQuantityInput.value = '';
            modalQuantityDeductedInput.value = '';
            modalRemainingStockInput.value = '';
            productDetailsSection.style.display = 'none';
            deductStockButton.disabled = true;
            modalQuantityDeductedInput.classList.remove('is-invalid', 'is-valid');
        }

        // Function to populate product details based on selection
        function updateProductDetails() {
            var selectedOption = modalProductSelect.options[modalProductSelect.selectedIndex];

            if (selectedOption.value) {
                var itemId = selectedOption.value;
                var productName = selectedOption.getAttribute('data-product_name');
                var productType = selectedOption.getAttribute('data-type_product');
                var currentStock = selectedOption.getAttribute('data-stock');

                modalProductIdInput.value = itemId;
                modalProductNameInput.value = productName;
                modalProductTypeInput.value = productType || 'N/A';
                modalCurrentQuantityInput.value = currentStock;

                productDetailsSection.style.display = 'block';

                // Re-validate quantity and update remaining stock immediately after details are loaded
                // This ensures correct initial state if input field has old value
                updateRemainingStock();
                validateQuantity();
                validateForm(); // Re-evaluate overall form state

            } else {
                productDetailsSection.style.display = 'none';
                modalProductIdInput.value = '';
                modalProductNameInput.value = '';
                modalProductTypeInput.value = '';
                modalCurrentQuantityInput.value = '';
                modalQuantityDeductedInput.value = '';
                modalRemainingStockInput.value = '';
                validateForm(); // Update button state after clearing fields
            }
        }

        // Function to update the calculated remaining stock
        function updateRemainingStock() {
            var currentStock = parseInt(modalCurrentQuantityInput.value) || 0;
            var quantityToDeduct = parseInt(modalQuantityDeductedInput.value) || 0;
            var remaining = currentStock - quantityToDeduct;

            modalRemainingStockInput.value = remaining;
            if (remaining < 0) {
                modalRemainingStockInput.classList.add('text-danger');
                modalRemainingStockInput.classList.remove('text-success');
            } else {
                modalRemainingStockInput.classList.add('text-success');
                modalRemainingStockInput.classList.remove('text-danger');
            }
        }

        // Function to validate the quantity to deduct
        function validateQuantity() {
            var quantityToDeduct = parseInt(modalQuantityDeductedInput.value);
            var currentStock = parseInt(modalCurrentQuantityInput.value);

            if (isNaN(quantityToDeduct) || quantityToDeduct <= 0 || quantityToDeduct > currentStock) {
                modalQuantityDeductedInput.classList.add('is-invalid');
                modalQuantityDeductedInput.classList.remove('is-valid');
                return false;
            } else {
                modalQuantityDeductedInput.classList.remove('is-invalid');
                modalQuantityDeductedInput.classList.add('is-valid');
                return true;
            }
        }

        // Overall form validation for enabling/disabling the main deduct button
        function validateForm() {
            var isProductSelected = modalProductSelect.value !== '';
            var isQuantityInputValid = validateQuantity(); // Check validity of quantity input field

            deductStockButton.disabled = !(isProductSelected && isQuantityInputValid);
            return isProductSelected && isQuantityInputValid; // Return overall form validity
        }

        // Handle click on "Proceed to Deduct" button (from first modal)
        deductStockButton.addEventListener('click', function() {
            // Re-validate just before showing confirmation, though button should be enabled only if valid
            if (!validateForm()) {
                showAlert('Please select an item and enter a valid quantity to deduct.', 'danger');
                return;
            }

            // Populate confirmation modal with details
            confirmationProductName.textContent = modalProductNameInput.value;
            confirmationQuantityDeducted.textContent = '-' + modalQuantityDeductedInput.value;
            confirmationCurrentStock.textContent = modalCurrentQuantityInput.value;
            confirmationRemainingStock.textContent = modalRemainingStockInput.value;

            stockOutModal.hide(); // Hide first modal
            confirmDeductStockModal.show(); // Show confirmation modal
        });

        // Handle click on "Confirm Deduction" button (from confirmation modal)
        finalConfirmButton.addEventListener('click', function() {
            var loadingSpinner = this.querySelector('.loading-spinner');
            var buttonIcon = this.querySelector('i');

            // Show loading state
            if (loadingSpinner) { // Check for existence before manipulating
                loadingSpinner.style.display = 'inline-block';
            }
            if (buttonIcon) { // Check for existence before manipulating
                buttonIcon.style.display = 'none';
            }
            this.disabled = true; // Disable button during processing

            // Submit form
            document.getElementById('stockOutForm').submit();
        });

        // When confirmation modal is hidden, show the first modal again
        confirmDeductStockModalElement.addEventListener('hidden.bs.modal', function() {
            // Re-enable the finalConfirmButton if it was disabled by loading state
            finalConfirmButton.disabled = false;
            var loadingSpinner = finalConfirmButton.querySelector('.loading-spinner');
            var buttonIcon = finalConfirmButton.querySelector('i');
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            if (buttonIcon) buttonIcon.style.display = 'inline-block'; // Restore icon display

            stockOutModal.show();
        });
    });
    </script>
</body>

</html>
