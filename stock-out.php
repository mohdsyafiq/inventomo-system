<?php
// Start session for user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-login-basic.html");
    exit();
}

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize user variables with proper defaults
$current_user_id = $_SESSION['user_id'];
$current_user_name = "User";
$current_user_role = "user";
$current_user_avatar = "default.jpg";
$avatar_path = "uploads/photos/"; // Path where profile pictures are stored

// Function to get user avatar URL
function getUserAvatarUrl($avatar_filename, $avatar_path) {
    if (empty($avatar_filename) || $avatar_filename == 'default.jpg') {
        return null; // Will use initials instead
    }
    
    if (file_exists($avatar_path . $avatar_filename)) {
        return $avatar_path . $avatar_filename;
    }
    
    return null; // Will use initials instead
}

// Fetch current user details from database with prepared statement
$user_query = "SELECT * FROM user_profiles WHERE Id = ? LIMIT 1";
$stmt = $conn->prepare($user_query);

if ($stmt) {
    $stmt->bind_param("s", $current_user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result && $user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        
        // Set user information
        $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
        $current_user_role = $user_data['position'];
        
        // Handle profile picture path correctly
        if (!empty($user_data['profile_picture']) && $user_data['profile_picture'] != 'default.jpg') {
            // Check if the file exists in uploads/photos/
            if (file_exists($avatar_path . $user_data['profile_picture'])) {
                $current_user_avatar = $user_data['profile_picture'];
            } else {
                $current_user_avatar = 'default.jpg';
            }
        } else {
            $current_user_avatar = 'default.jpg';
        }
    }
    $stmt->close();
}

$user_avatar_url = getUserAvatarUrl($current_user_avatar, $avatar_path);

// Helper function to get avatar background color based on position
function getAvatarColor($position) {
    switch (strtolower($position)) {
        case 'admin': return 'primary';
        case 'super-admin': return 'danger';
        case 'manager': return 'success';
        case 'supervisor': return 'warning';
        case 'staff': return 'info';
        default: return 'secondary';
    }
}

$message = "";

// --- Handle Form Submission (POST Request from Modal) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'stock_out_submit') {
    $product_id_to_update = intval($_POST['product_id']); // This comes from the dropdown selection
    $quantity_to_deduct = intval($_POST['quantity_deducted']);
    // Use actual logged-in username instead of hardcoded "Admin"
    $user_who_deducted_stock = $current_user_name;

    // Fetch current quantity and product name for validation and history logging
    $current_product_quantity = 0;
    $product_name_for_history = "";

    $stmt_check = $conn->prepare("SELECT name, quantity FROM products WHERE id = ?");
    $stmt_check->bind_param("i", $product_id_to_update);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        $product_data = $result_check->fetch_assoc();
        $product_name_for_history = $product_data['name'];
        $current_product_quantity = $product_data['quantity'];
    }
    $stmt_check->close();

    if ($product_id_to_update <= 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                      <strong>Error!</strong> Please select a product.
                      <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    } elseif ($quantity_to_deduct <= 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                      <strong>Error!</strong> Quantity to deduct must be greater than 0.
                      <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    } elseif ($quantity_to_deduct > $current_product_quantity) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                      <strong>Insufficient Stock!</strong> Cannot deduct {$quantity_to_deduct} items. Only {$current_product_quantity} in stock for '{$product_name_for_history}'.
                      <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    } else {
        // All validations passed, proceed with transaction
        $conn->begin_transaction();
        try {
            // 1. Update product quantity
            $stmt_update = $conn->prepare("UPDATE products SET quantity = quantity - ?, last_updated = NOW() WHERE id = ?");
            $stmt_update->bind_param("ii", $quantity_to_deduct, $product_id_to_update);
            $stmt_update->execute();

            // 2. Record in stock_out_history
            $stmt_history = $conn->prepare("INSERT INTO stock_out_history (product_id, product_name, quantity_deducted, username, transaction_date) VALUES (?, ?, ?, ?, NOW())");
            $stmt_history->bind_param("isis", $product_id_to_update, $product_name_for_history, $quantity_to_deduct, $user_who_deducted_stock);
            $stmt_history->execute();

            $conn->commit(); // Commit transaction if all successful
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                          <strong>Success!</strong> Stock deducted and recorded successfully for '{$product_name_for_history}'!
                          <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } catch (mysqli_sql_exception $e) {
            $conn->rollback(); // Rollback on error
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                          <strong>Database Error!</strong> Error processing stock out: " . $e->getMessage() . "
                          <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } finally {
            if (isset($stmt_update)) $stmt_update->close();
            if (isset($stmt_history)) $stmt_history->close();
        }
    }
}

// --- Fetch Statistics ---
$total_transactions = 0;
$total_quantity_deducted = 0;

$sql_stats = "SELECT COUNT(*) as transaction_count, COALESCE(SUM(quantity_deducted), 0) as total_deducted FROM stock_out_history";
$result_stats = $conn->query($sql_stats);
if ($result_stats->num_rows > 0) {
    $stats = $result_stats->fetch_assoc();
    $total_transactions = $stats['transaction_count'];
    $total_quantity_deducted = $stats['total_deducted'];
}

// --- Fetch all products for the dropdown ---
$all_products = [];
$sql_all_products = "SELECT id, name, description, quantity FROM products WHERE quantity > 0 ORDER BY name ASC";
$result_all_products = $conn->query($sql_all_products);
if ($result_all_products->num_rows > 0) {
    while($row = $result_all_products->fetch_assoc()) {
        $all_products[] = $row;
    }
}

// --- Fetch Stock Out History ---
$stock_out_history = [];
$sql_history = "SELECT soh.id, soh.product_id, soh.product_name, soh.quantity_deducted, soh.username, soh.transaction_date
                FROM stock_out_history soh
                ORDER BY soh.transaction_date DESC LIMIT 50";
$result_history = $conn->query($sql_history);
if ($result_history->num_rows > 0) {
    while($row = $result_history->fetch_assoc()) {
        $stock_out_history[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Stock Out - Inventomo</title>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="assets/vendor/css/core.css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="assets/css/demo.css" />
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
    
    <style>
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
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .user-avatar::after {
            content: '';
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background-color: #10b981;
            border: 2px solid white;
            border-radius: 50%;
        }
        .stats-card {
            border-left: 4px solid #ff6b35;
            transition: transform 0.2s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .loading-spinner {
            display: none;
        }
        .search-highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .profile-link {
            text-decoration: none;
            color: inherit;
        }
        .profile-link:hover {
            color: inherit;
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
                    <li class="menu-item active">
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
                                <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                    <i class="bx bx-refresh me-1"></i> Refresh
                                </button>
                                <a href="stock-management.php" class="btn btn-outline-primary">
                                    <i class="bx bx-arrow-back me-1"></i> Back
                                </a>
                            </div>
                        </div>

                        <?php echo $message; // Display messages ?>

                        <!-- Statistics Cards -->
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

                        <!-- Action Card -->
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
                                    <button type="button" class="btn btn-outline-info" onclick="window.print()">
                                        <i class="bx bx-printer me-1"></i> Print History
                                    </button>
                                </div>
                                <div class="alert alert-info d-flex align-items-center" role="alert">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <div>
                                        <strong>Quick Actions:</strong> Press <kbd>Ctrl + N</kbd> to add new stock out transaction, 
                                        <kbd>Ctrl + B</kbd> to go back to stock management.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- History Table -->
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

    <!-- Stock Out Modal -->
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
                                <label for="modalProductDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="modalProductDescription" readonly rows="2"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="modalQuantityDeducted" class="form-label">Quantity to Deduct *</label>
                                <input type="number" class="form-control" id="modalQuantityDeducted"
                                    name="quantity_deducted" min="1" required>
                                <div class="invalid-feedback">Cannot deduct more than current stock.</div>
                            </div>
                            <div class="col-md-6 mb-3">
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

    <!-- Confirmation Modal -->
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

    <!-- Scripts -->
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
        var modalProductDescriptionInput = document.getElementById('modalProductDescription');
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

        // Search functionality
        var navbarSearch = document.getElementById('navbarSearch');
        var historyTable = document.getElementById('historyTable');

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
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

        // Search functionality
        if (navbarSearch && historyTable) {
            navbarSearch.addEventListener('input', function() {
                var searchTerm = this.value.toLowerCase();
                var rows = historyTable.querySelectorAll('tbody tr');
                
                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    var shouldShow = text.includes(searchTerm);
                    row.style.display = shouldShow ? '' : 'none';
                    
                    // Highlight search terms
                    if (searchTerm && shouldShow) {
                        var cells = row.querySelectorAll('td');
                        cells.forEach(function(cell) {
                            var originalText = cell.getAttribute('data-original') || cell.innerHTML;
                            if (!cell.getAttribute('data-original')) {
                                cell.setAttribute('data-original', originalText);
                            }
                            
                            if (searchTerm.length > 0) {
                                var regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\                            <div class="col-12 mb-3') + ')', 'gi');
                                cell.innerHTML = originalText.replace(regex, '<span class="search-highlight">$1</span>');
                            } else {
                                cell.innerHTML = originalText;
                            }
                        });
                    }
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
        });

        // Quantity input handler
        modalQuantityDeductedInput.addEventListener('input', function() {
            updateRemainingStock();
            validateQuantity();
            validateForm();
        });

        function resetModal() {
            modalProductSelect.value = '';
            modalProductIdInput.value = '';
            modalProductNameInput.value = '';
            modalProductDescriptionInput.value = '';
            modalCurrentQuantityInput.value = '';
            modalQuantityDeductedInput.value = '';
            modalRemainingStockInput.value = '';
            productDetailsSection.style.display = 'none';
            deductStockButton.disabled = true;
            modalQuantityDeductedInput.classList.remove('is-invalid');
        }

        function updateProductDetails() {
            var selectedOption = modalProductSelect.options[modalProductSelect.selectedIndex];
            
            if (selectedOption.value) {
                var productId = selectedOption.value;
                var productName = selectedOption.getAttribute('data-name');
                var productDescription = selectedOption.getAttribute('data-description');
                var currentQuantity = selectedOption.getAttribute('data-quantity');

                modalProductIdInput.value = productId;
                modalProductNameInput.value = productName;
                modalProductDescriptionInput.value = productDescription || 'No description available';
                modalCurrentQuantityInput.value = currentQuantity;
                
                productDetailsSection.style.display = 'block';
                
                setTimeout(function() {
                    modalQuantityDeductedInput.focus();
                }, 100);
            } else {
                productDetailsSection.style.display = 'none';
            }
        }

        function updateRemainingStock() {
            var currentStock = parseInt(modalCurrentQuantityInput.value) || 0;
            var quantityToDeduct = parseInt(modalQuantityDeductedInput.value) || 0;
            var remaining = currentStock - quantityToDeduct;
            
            modalRemainingStockInput.value = remaining >= 0 ? remaining : 'Invalid';
            modalRemainingStockInput.className = remaining >= 0 ? 'form-control text-success' : 'form-control text-danger';
        }

        function validateQuantity() {
            var quantityToDeduct = parseInt(modalQuantityDeductedInput.value);
            var currentStock = parseInt(modalCurrentQuantityInput.value);

            if (isNaN(quantityToDeduct) || quantityToDeduct <= 0 || quantityToDeduct > currentStock) {
                modalQuantityDeductedInput.classList.add('is-invalid');
                return false;
            } else {
                modalQuantityDeductedInput.classList.remove('is-invalid');
                return true;
            }
        }

        function validateForm() {
            var isValid = modalProductSelect.value && 
                         modalQuantityDeductedInput.value && 
                         validateQuantity();
            
            deductStockButton.disabled = !isValid;
        }

        // Confirm button handler
        deductStockButton.addEventListener('click', function() {
            if (!validateForm()) {
                return;
            }

            // Populate confirmation modal
            confirmationProductName.textContent = modalProductNameInput.value;
            confirmationQuantityDeducted.textContent = modalQuantityDeductedInput.value;
            confirmationCurrentStock.textContent = modalCurrentQuantityInput.value;
            confirmationRemainingStock.textContent = modalRemainingStockInput.value;

            stockOutModal.hide();
            confirmDeductStockModal.show();
        });

        // Final confirmation handler
        finalConfirmButton.addEventListener('click', function() {
            var loadingSpinner = this.querySelector('.loading-spinner');
            var buttonText = this.querySelector('i');
            
            // Show loading state
            loadingSpinner.style.display = 'inline-block';
            buttonText.style.display = 'none';
            this.disabled = true;
            
            // Submit form
            document.getElementById('stockOutForm').submit();
        });

        // Handle modal backdrop clicks
        confirmDeductStockModalElement.addEventListener('hidden.bs.modal', function() {
            stockOutModal.show();
        });
    });
    </script>
</body>

</html>">
                                