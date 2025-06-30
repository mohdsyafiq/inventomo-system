<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Database connection details
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = "inventory_system";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize user variables
$profile_link = "#";
$current_user_name = "User";
$current_user_role = "User";
$current_user_avatar = "1.png";

// Helper function to get avatar background color based on position
function getAvatarColor($position) {
    switch (strtolower($position)) {
        case 'admin':
            return 'primary';
        case 'super-admin':
            return 'danger';
        case 'moderator':
            return 'warning';
        case 'manager':
            return 'success';
        case 'staff':
            return 'info';
        default:
            return 'secondary';
    }
}

// Helper function to get profile picture path
function getProfilePicture($profile_picture, $full_name) {
    if (!empty($profile_picture) && $profile_picture != 'default.jpg') {
        $photo_path = 'uploads/photos/' . $profile_picture;
        if (file_exists($photo_path)) {
            return $photo_path;
        }
    }
    // Return null to show initials instead
    return null;
}

// Session check and user profile link logic
if (isset($_SESSION['user_id']) && $conn) {
    $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);

    // Fetch current user details from database using prepared statement for security
    $user_query_stmt = $conn->prepare("SELECT full_name, username, position, profile_picture FROM user_profiles WHERE Id = ? LIMIT 1");
    if ($user_query_stmt) {
        $user_query_stmt->bind_param("i", $user_id);
        $user_query_stmt->execute();
        $user_result = $user_query_stmt->get_result();

        if ($user_result && $user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();

            // Set user information
            $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
            $current_user_role = $user_data['position'];
            $current_user_avatar = !empty($user_data['profile_picture']) ? $user_data['profile_picture'] : '1.png';

            // Profile link goes to user-profile.php with their ID
            $profile_link = "user-profile.php?op=view&Id=" . $user_id;
        }
        $user_query_stmt->close();
    }
}


$message = "";

// Handle Form Submission (POST Request from Modal)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'stock_in_submit') {
    // Note: Assuming 'product_id' from the form now refers to 'itemID' in inventory_item
    $item_id_to_update = intval($_POST['product_id']);
    $quantity_to_add = intval($_POST['quantity_added']);
    $user_who_stocked_in = $current_user_name; // Use actual logged-in user

    // Fetch item name for history logging from inventory_item table
    $item_name_for_history = "";
    $stmt_name = $conn->prepare("SELECT product_name FROM inventory_item WHERE itemID = ?");
    if ($stmt_name) {
        $stmt_name->bind_param("i", $item_id_to_update);
        $stmt_name->execute();
        $result_name = $stmt_name->get_result();
        if ($result_name->num_rows > 0) {
            $item_name_for_history = $result_name->fetch_assoc()['product_name'];
        }
        $stmt_name->close();
    }

    if ($quantity_to_add > 0 && $item_id_to_update > 0) {
        // Start a transaction for atomicity
        $conn->begin_transaction();
        try {
            // 1. Update item quantity in inventory_item table
            // 'stock' is the column name for quantity in inventory_item
            $stmt_update = $conn->prepare("UPDATE inventory_item SET stock = stock + ?, last_updated = NOW() WHERE itemID = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("ii", $quantity_to_add, $item_id_to_update);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                 throw new mysqli_sql_exception("Failed to prepare update statement: " . $conn->error);
            }

            // 2. Record in stock_in_history table
            // Use 'product_id' and 'product_name' as per stock_in_history table schema
            $stmt_history = $conn->prepare("INSERT INTO stock_in_history (product_id, product_name, quantity_added, username, transaction_date) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt_history) {
                $stmt_history->bind_param("isis", $item_id_to_update, $item_name_for_history, $quantity_to_add, $user_who_stocked_in);
                $stmt_history->execute();
                $stmt_history->close();
            } else {
                throw new mysqli_sql_exception("Failed to prepare history insert statement: " . $conn->error);
            }

            $conn->commit();
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <i class='bx bx-check-circle me-2'></i>
                            Stock updated successfully! Added <strong>{$quantity_to_add}</strong> units to <strong>'{$item_name_for_history}'</strong>
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                            <i class='bx bx-error-circle me-2'></i>
                            Error processing stock in: " . $e->getMessage() . "
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
            error_log("Stock In Transaction Error: " . $e->getMessage()); // Log detailed error
        }
    } else {
        $message = "<div class='alert alert-warning alert-dismissible fade show' role='alert'>
                            <i class='bx bx-error me-2'></i>
                            Please select an item and enter a quantity greater than 0.
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
    }
}

// Fetch all items from inventory_item for the dropdown
$all_items = [];
// *** IMPORTANT CHANGE: Select 'type_product' instead of 'product_type' ***
$sql_all_items = "SELECT itemID, product_name, type_product, stock, supplier_id, last_updated FROM inventory_item ORDER BY product_name ASC";
$result_all_items = $conn->query($sql_all_items);
if ($result_all_items && $result_all_items->num_rows > 0) {
    while($row = $result_all_items->fetch_assoc()) {
        $all_items[] = $row;
    }
}

// Fetch Stock In History (from stock_in_history table)
$stock_in_history = [];
$sql_history = "SELECT sih.id, sih.product_id, sih.product_name, sih.quantity_added, sih.username, sih.transaction_date, ii.price
                FROM stock_in_history AS sih
                LEFT JOIN inventory_item AS ii ON sih.product_id = ii.itemID
                ORDER BY sih.transaction_date DESC LIMIT 50";
$result_history = $conn->query($sql_history);
if ($result_history && $result_history->num_rows > 0) {
    while($row = $result_history->fetch_assoc()) {
        $stock_in_history[] = $row;
    }
}

// Calculate statistics
$total_stock_ins = count($stock_in_history);
$total_quantity_added = array_sum(array_column($stock_in_history, 'quantity_added'));
$recent_transactions = array_slice($stock_in_history, 0, 5);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Stock In - Inventomo</title>

    <meta name="description" content="Stock In Management System" />

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

    .breadcrumb-text {
        font-size: 1rem;
        color: #9ca3af;
        font-weight: 400;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        border-left: 4px solid #28a745;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(161, 172, 184, 0.2);
    }

    .stat-card.transactions {
        border-left-color: #696cff;
    }

    .stat-card.quantity {
        border-left-color: #28a745;
    }

    .stat-card.recent {
        border-left-color: #17a2b8;
    }

    .stat-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        background-color: #28a745;
        flex-shrink: 0;
    }

    .stat-card.transactions .stat-icon {
        background-color: #696cff;
    }

    .stat-card.quantity .stat-icon {
        background-color: #28a745;
    }

    .stat-card.recent .stat-icon {
        background-color: #17a2b8;
    }

    .stat-content {
        flex: 1;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #566a7f;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: #6c757d;
        font-weight: 500;
    }

    .action-card {
        background: white;
        border-radius: 0.5rem;
        padding: 1rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        margin-bottom: 1.5rem;
        text-align: left;
    }

    .action-card h5 {
        margin-bottom: 1rem;
        color: #566a7f;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: flex-start;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .action-btn {
        padding: 0.75rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        text-decoration: none;
        min-width: 100px;
    }

    .action-btn:hover {
        transform: translateY(-1px);
        color: white;
    }

    .btn-primary {
        background-color: #696cff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #5f63f2;
        box-shadow: 0 4px 8px rgba(105, 108, 255, 0.2);
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
        box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
    }

    .btn-success {
        background-color: #28a745;
        color: white;
    }

    .btn-success:hover {
        background-color: #218838;
        box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
    }

    .card {
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        border: none;
        overflow: hidden;
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

    .table {
        margin-bottom: 0;
    }

    .table th {
        background-color: #f5f5f9;
        color: #566a7f;
        font-weight: 600;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        border-bottom: 2px solid #d9dee3;
        padding: 1rem 0.75rem;
    }

    .table td {
        padding: 0.875rem 0.75rem;
        color: #566a7f;
        vertical-align: middle;
        border-bottom: 1px solid #d9dee3;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
        transition: background-color 0.2s ease;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 3rem;
        color: #d9dee3;
        margin-bottom: 1rem;
    }

    .empty-state h6 {
        margin-bottom: 0.5rem;
        color: #566a7f;
    }

    .empty-state p {
        margin-bottom: 1.5rem;
        color: #9ca3af;
    }

    /* Modal Enhancements */
    .modal-content {
        border-radius: 0.5rem;
        border: none;
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.15);
    }

    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #d9dee3;
        padding: 1.5rem;
    }

    .modal-title {
        font-weight: 600;
        color: #566a7f;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #d9dee3;
        padding: 1rem 1.5rem;
    }

    .form-label {
        font-weight: 600;
        color: #566a7f;
        margin-bottom: 0.5rem;
    }

    .form-control,
    .form-select {
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #696cff;
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
    }

    /* Alert Enhancements */
    .alert {
        border-radius: 0.5rem;
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        animation: fadeInDown 0.5s ease;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
    }

    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
    }

    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
    }

    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
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

    /* Dropdown menu avatar styling */
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

    @media (max-width: 768px) {
        .content-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }

        .table-responsive {
            font-size: 0.875rem;
        }
    }
    </style>

    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="index.php" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <img width="80" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
                        </span>
                    </a>

                    <a href="javascript:void(0);"
                        class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <li class="menu-item active">
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
            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
                            </div>
                        </div>
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                        <?php
                                        $navbar_pic = getProfilePicture($current_user_avatar, $current_user_name);
                                        if ($navbar_pic): ?>
                                            <img src="<?php echo htmlspecialchars($navbar_pic); ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                                    <?php if ($navbar_pic): ?>
                                                        <img src="<?php echo htmlspecialchars($navbar_pic); ?>" alt="Profile Picture">
                                                    <?php else: ?>
                                                        <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block">
                                                        <?php echo htmlspecialchars($current_user_name); ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars(ucfirst($current_user_role)); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $profile_link; ?>">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">My Profile</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Settings</span>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="logout.php">
                                            <i class="bx bx-power-off me-2"></i>
                                            <span class="align-middle">Log Out</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            </ul>
                    </div>
                </nav>
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="fw-bold"><span class="text-muted fw-light">Stock Management /</span> Stock In</h4>
                            <div class="d-flex gap-2">
                            </div>
                        </div>

                        <?php echo $message; ?>

                        <div class="stats-grid">
                            <div class="stat-card transactions">
                                <div class="stat-icon">
                                    <i class="bx bx-receipt"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $total_stock_ins; ?></div>
                                    <div class="stat-label">Total Transactions</div>
                                </div>
                            </div>
                            <div class="stat-card quantity">
                                <div class="stat-icon">
                                    <i class="bx bx-package"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $total_quantity_added; ?></div>
                                    <div class="stat-label">Total Units Added</div>
                                </div>
                            </div>
                            <div class="stat-card recent">
                                <div class="stat-icon">
                                    <i class="bx bx-time-five"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo count($recent_transactions); ?></div>
                                    <div class="stat-label">Recent Transactions</div>
                                </div>
                            </div>
                        </div>

                        <div class="action-card">
                            <h5>
                                <i class="bx bx-plus-circle"></i>Stock In Actions
                            </h5>
                            <div class="action-buttons">
                                <button type="button" class="action-btn btn-primary" data-bs-toggle="modal" data-bs-target="#stockInModal">
                                    <i class="bx bx-plus"></i>Add Stock
                                </button>
                                <a href="stock-management.php" class="action-btn btn-secondary">
                                    <i class="bx bx-arrow-back"></i>Back
                                </a>
                                <a href="stock-out.php" class="action-btn btn-success">
                                    <i class="bx bx-minus"></i>Stock Out
                                </a>
                            </div>
                            <p class="text-muted mt-3">
                                Click "Add Stock" to initiate a new stock-in transaction, view stock management, or process stock out operations.
                            </p>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <i class="bx bx-history"></i>Stock In History
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Transaction ID</th>
                                                <th>Item ID</th>
                                                <th>Item Name</th>
                                                <th>Quantity Added</th>
                                                <th>Total Price (RM)</th>
                                                <th>User</th>
                                                <th>Date & Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($stock_in_history)): ?>
                                                <?php foreach ($stock_in_history as $history_item): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($history_item['id']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($history_item['product_id']); ?></td>
                                                        <td><?php echo htmlspecialchars($history_item['product_name']); ?></td>
                                                        <td>
                                                            <span class="badge bg-success">
                                                                +<?php echo htmlspecialchars($history_item['quantity_added']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                $total_price = ($history_item['price'] ?? 0) * ($history_item['quantity_added'] ?? 0);
                                                                echo 'RM ' . number_format($total_price, 2);
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($history_item['username']); ?></td>
                                                        <td><?php echo date('M d, Y H:i', strtotime($history_item['transaction_date'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="empty-state">
                                                        <i class="bx bx-package"></i>
                                                        <h6>No Stock In History Found</h6>
                                                        <p>Start by adding your first stock transaction.</p>
                                                        <button type="button" class="action-btn btn-primary" data-bs-toggle="modal" data-bs-target="#stockInModal">
                                                            <i class="bx bx-plus"></i>Add First Stock
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                © <script>document.write(new Date().getFullYear());</script> Inventomo. All rights reserved.
                            </div>
                            <div>
                                <a href="#" class="footer-link me-4">Documentation</a>
                                <a href="#" class="footer-link me-4">Support</a>
                            </div>
                        </div>
                    </footer>
                    <div class="content-backdrop fade"></div>
                </div>
                </div>
            </div>

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <div class="modal fade" id="stockInModal" tabindex="-1" aria-labelledby="stockInModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockInModalLabel">
                        <i class="bx bx-plus-circle"></i>Add Stock to Item
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="stockInForm" method="POST" action="stock-in.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="stock_in_submit">
                        <input type="hidden" id="modalProductId" name="product_id"> <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modalProductSelect" class="form-label">
                                        <i class="bx bx-package me-1"></i>Select Item
                                    </label>
                                    <select class="form-select" id="modalProductSelect" required>
                                        <option value="">-- Select an item --</option>
                                        <?php foreach ($all_items as $item): ?>
                                            <option value="<?php echo htmlspecialchars($item['itemID']); ?>"
                                                    data-product_name="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                    data-product_type="<?php echo htmlspecialchars($item['type_product'] ?? ''); ?>" data-stock="<?php echo htmlspecialchars($item['stock']); ?>">
                                                ID: <?php echo htmlspecialchars($item['itemID']); ?> - <?php echo htmlspecialchars($item['product_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="modalProductName" class="form-label">
                                        <i class="bx bx-tag me-1"></i>Item Name
                                    </label>
                                    <input type="text" class="form-control" id="modalProductName" readonly>
                                </div>

                                <div class="mb-3">
                                    <label for="modalCurrentQuantity" class="form-label">
                                        <i class="bx bx-cube me-1"></i>Current Stock Quantity
                                    </label>
                                    <input type="text" class="form-control" id="modalCurrentQuantity" readonly>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="modalProductType" class="form-label">
                                        <i class="bx bx-category me-1"></i>Product Type
                                    </label>
                                    <input type="text" class="form-control" id="modalProductType" readonly>
                                </div>

                                <div class="mb-3">
                                    <label for="modalQuantityAdded" class="form-label">
                                        <i class="bx bx-plus me-1"></i>Quantity to Add
                                    </label>
                                    <input type="number" class="form-control" id="modalQuantityAdded" name="quantity_added" min="1" required>
                                    <div class="form-text">Enter the number of units to add to stock</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="bx bx-user me-1"></i>Added By
                                    </label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user_name); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bx bx-info-circle me-2"></i>
                            <strong>Note:</strong> This action will add the specified quantity to the current stock level and create a transaction record.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bx bx-x"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-primary" id="confirmAddStockBtn">
                            <i class="bx bx-check"></i>Add Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmAddStockModal" tabindex="-1" aria-labelledby="confirmAddStockModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmAddStockModalLabel">
                        <i class="bx bx-check-circle text-success"></i>Confirm Stock Addition
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <div class="alert alert-warning">
                            <i class="bx bx-error me-2"></i>
                            <strong>Please confirm this action:</strong>
                        </div>

                        <div class="confirmation-details">
                            <h6>Item: <span id="confirmationProductName" class="text-primary"></span></h6>
                            <h6>Quantity to Add: <span id="confirmationQuantityAdded" class="text-success">+0</span></h6>
                            <p class="text-muted">This action cannot be undone easily.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="finalConfirmAddStockBtn">
                        <i class="bx bx-check"></i>Confirm Addition
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        initializeStockIn();
    });

    function initializeStockIn() {
        var stockInModalElement = document.getElementById('stockInModal');
        var stockInModal = new bootstrap.Modal(stockInModalElement);
        var confirmAddStockModalElement = document.getElementById('confirmAddStockModal');
        var confirmAddStockModal = new bootstrap.Modal(confirmAddStockModalElement);

        var modalProductSelect = stockInModalElement.querySelector('#modalProductSelect');
        var modalProductIdInput = stockInModalElement.querySelector('#modalProductId'); // Hidden input for product_id (now itemID)
        var modalProductNameInput = stockInModalElement.querySelector('#modalProductName');
        var modalProductTypeInput = stockInModalElement.querySelector('#modalProductType'); // Product Type input
        var modalCurrentQuantityInput = stockInModalElement.querySelector('#modalCurrentQuantity');
        var modalQuantityAddedInput = stockInModalElement.querySelector('#modalQuantityAdded');
        var addStockButtonInModal = stockInModalElement.querySelector('#confirmAddStockBtn');

        var finalConfirmButton = confirmAddStockModalElement.querySelector('#finalConfirmAddStockBtn');
        var confirmationProductName = confirmAddStockModalElement.querySelector('#confirmationProductName');
        var confirmationQuantityAdded = confirmAddStockModalElement.querySelector('#confirmationQuantityAdded');

        // Event listener for when the main stock-in modal is shown
        stockInModalElement.addEventListener('show.bs.modal', function () {
            modalProductSelect.value = ""; // Reset dropdown
            updateModalDetails(); // Clear other fields
            modalQuantityAddedInput.value = ''; // Clear quantity input
            setTimeout(function() {
                modalProductSelect.focus(); // Focus on select
            }, 100);
        });

        // Event listener for when an item is selected from the dropdown
        modalProductSelect.addEventListener('change', updateModalDetails);

        function updateModalDetails() {
            var selectedOption = modalProductSelect.options[modalProductSelect.selectedIndex];

            if (selectedOption.value) {
                var itemId = selectedOption.value;
                // Get data from data-attributes
                var itemName = selectedOption.getAttribute('data-product_name');
                // Access 'data-product_type' (which stores 'type_product' from DB)
                var itemType = selectedOption.getAttribute('data-product_type');
                var currentStock = selectedOption.getAttribute('data-stock');

                modalProductIdInput.value = itemId;
                modalProductNameInput.value = itemName;
                modalProductTypeInput.value = itemType || 'N/A'; // Populate Product Type field
                modalCurrentQuantityInput.value = currentStock;
            } else {
                modalProductIdInput.value = '';
                modalProductNameInput.value = '';
                modalProductTypeInput.value = ''; // Clear Product Type field
                modalCurrentQuantityInput.value = '';
            }

            if (modalProductIdInput.value) {
                setTimeout(function() {
                    modalQuantityAddedInput.focus();
                }, 100);
            }
        }

        // Event listener for the "Add Stock" button in the first modal
        addStockButtonInModal.addEventListener('click', function() {
            if (!modalProductSelect.value || modalQuantityAddedInput.value <= 0) {
                showAlert('Please select an item and enter a valid quantity to add.', 'warning');
                return;
            }

            confirmationProductName.textContent = modalProductNameInput.value;
            confirmationQuantityAdded.textContent = '+' + modalQuantityAddedInput.value;

            stockInModal.hide();
            confirmAddStockModal.show();
        });

        // Event listener for the "Confirm" button in the confirmation modal
        finalConfirmButton.addEventListener('click', function() {
            // Show loading state
            finalConfirmButton.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>Processing...';
            finalConfirmButton.disabled = true;

            setTimeout(() => {
                document.getElementById('stockInForm').submit();
            }, 500);
        });

        // Add hover effects to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-2px)';
            });
        });

        // Enhanced search functionality
        const searchInput = document.querySelector('input[aria-label="Search..."]');
        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    const searchTerm = this.value.toLowerCase();
                    if (searchTerm.trim()) {
                        searchInTable(searchTerm);
                    } else {
                        clearSearch();
                    }
                }
            });
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-dismissible')) {
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            alert.remove();
                        }, 500);
                    }, 5000);
                }
            });
        }, 100);
    }

    // Search functionality within the history table
    function searchInTable(searchTerm) {
        const tableBody = document.querySelector('.table tbody');
        const rows = tableBody.querySelectorAll('tr');
        let hasResults = false;

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
                row.style.backgroundColor = '#fff3cd';
                hasResults = true;
            } else {
                row.style.display = 'none';
            }
        });

        if (!hasResults) {
            showAlert('No search results found', 'info');
        }
    }

    // Clear search results
    function clearSearch() {
        const tableBody = document.querySelector('.table tbody');
        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
            row.style.display = '';
            row.style.backgroundColor = '';
        });
    }

    // Show alert function
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <i class="bx bx-info-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        const container = document.querySelector('.container-xxl');
        container.insertBefore(alertDiv, container.firstChild);

        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            setTimeout(() => {
                alertDiv.remove();
            }, 500);
        }, 3000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + N for new stock in
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            const stockInModal = new bootstrap.Modal(document.getElementById('stockInModal'));
            stockInModal.show();
        }

        // Ctrl/Cmd + B for back to stock management
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            window.location.href = 'stock-management.php';
        }
    });

    // Real-time form validation
    document.getElementById('modalQuantityAdded').addEventListener('input', function() {
        const value = parseInt(this.value);
        const submitBtn = document.getElementById('confirmAddStockBtn');

        // Basic validation: quantity must be positive and reasonable
        if (value > 0 && value <= 10000) { // Max quantity of 10000 to prevent accidental huge inputs
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            submitBtn.disabled = false;
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
            submitBtn.disabled = true;
        }
    });

    // Enhanced modal animations
    document.getElementById('stockInModal').addEventListener('shown.bs.modal', function() {
        document.getElementById('modalProductSelect').focus();
    });

    document.getElementById('confirmAddStockModal').addEventListener('shown.bs.modal', function() {
        document.getElementById('finalConfirmAddStockBtn').focus();
    });
    </script>
</body>

</html>
