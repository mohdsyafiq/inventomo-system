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
    
    // Fetch current user details from database
    $user_query = "SELECT * FROM user_profiles WHERE Id = '$user_id' LIMIT 1";
    $user_result = mysqli_query($conn, $user_query);
    
    if ($user_result && mysqli_num_rows($user_result) > 0) {
        $user_data = mysqli_fetch_assoc($user_result);
        
        // Set user information
        $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
        $current_user_role = $user_data['position'];
        $current_user_avatar = !empty($user_data['profile_picture']) ? $user_data['profile_picture'] : '1.png';
        
        // Profile link goes to user-profile.php with their ID
        $profile_link = "user-profile.php?op=view&Id=" . $user_data['Id'];
    }
}

// Function to get all products
function getProducts($conn) {
    $sql = "SELECT id, name, description, price, quantity, supplier_id, last_updated FROM products";
    $result = $conn->query($sql);
    $products = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    return $products;
}

// Function to add a product (for demonstration, would typically be in stock-in.php)
function addProduct($conn, $name, $description, $price, $quantity, $supplier_id) {
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, quantity, supplier_id, last_updated) VALUES (?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("ssdsi", $name, $description, $price, $quantity, $supplier_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Function to update product quantity (stock in/out - logic would be in stock-in.php/stock-out.php)
function updateProductQuantity($conn, $product_id, $change_amount) {
    $stmt = $conn->prepare("UPDATE products SET quantity = quantity + ?, last_updated = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $change_amount, $product_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_product'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $quantity = $_POST['quantity'];
        $supplier_id = $_POST['supplier_id'];
        addProduct($conn, $name, $description, $price, $quantity, $supplier_id);
        header("Location: stock-management.php");
        exit();
    }
}

$products = getProducts($conn);

// Get stock statistics
$stats = [
    'total_products' => count($products),
    'low_stock' => 0,
    'out_of_stock' => 0,
    'total_value' => 0
];

foreach ($products as $product) {
    if ($product['quantity'] <= 0) {
        $stats['out_of_stock']++;
    } elseif ($product['quantity'] <= 10) {
        $stats['low_stock']++;
    }
    $stats['total_value'] += ($product['price'] * $product['quantity']);
}
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Stock Management - Inventomo</title>

    <meta name="description" content="Stock Management System" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->
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
        border-left: 4px solid #696cff;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(161, 172, 184, 0.2);
    }

    .stat-card.total {
        border-left-color: #696cff;
    }

    .stat-card.low-stock {
        border-left-color: #ffc107;
    }

    .stat-card.out-of-stock {
        border-left-color: #ff3e1d;
    }

    .stat-card.value {
        border-left-color: #28a745;
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
        background-color: #696cff;
        flex-shrink: 0;
    }

    .stat-card.total .stat-icon {
        background-color: #696cff;
    }

    .stat-card.low-stock .stat-icon {
        background-color: #ffc107;
    }

    .stat-card.out-of-stock .stat-icon {
        background-color: #ff3e1d;
    }

    .stat-card.value .stat-icon {
        background-color: #28a745;
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

    .action-buttons-section {
        background: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        margin-bottom: 1.5rem;
    }

    .action-buttons-section h6 {
        margin: 0 0 1rem 0;
        color: #566a7f;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .action-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 0.375rem;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
    }

    .action-btn:hover {
        transform: translateY(-1px);
        color: white;
    }

    .btn-success {
        background-color: #28a745;
        color: white;
    }

    .btn-success:hover {
        background-color: #218838;
        box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
    }

    .btn-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .btn-warning:hover {
        background-color: #e0a800;
        color: #212529;
        box-shadow: 0 4px 8px rgba(255, 193, 7, 0.2);
    }

    .btn-info {
        background-color: #17a2b8;
        color: white;
    }

    .btn-info:hover {
        background-color: #138496;
        box-shadow: 0 4px 8px rgba(23, 162, 184, 0.2);
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

    .stock-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge.bg-success {
        background-color: #d4edda !important;
        color: #155724;
    }

    .badge.bg-warning {
        background-color: #fff3cd !important;
        color: #856404;
    }

    .badge.bg-danger {
        background-color: #f8d7da !important;
        color: #721c24;
    }

    .btn-group .btn {
        margin: 0;
        border-radius: 0;
    }

    .btn-group .btn:first-child {
        border-radius: 0.375rem 0 0 0.375rem;
    }

    .btn-group .btn:last-child {
        border-radius: 0 0.375rem 0.375rem 0;
    }

    .btn-group .btn:only-child {
        border-radius: 0.375rem;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
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

        .btn-group {
            flex-direction: column;
        }

        .btn-group .btn {
            border-radius: 0.375rem !important;
            margin-bottom: 0.25rem;
        }
    }
    </style>

    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
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
                        <!-- Search -->
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
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
                            <!--/ User -->
                        </ul>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <!-- Page Header -->
                        <div class="content-header">
                            <h4 class="page-title">
                                <i class="bx bx-list-plus"></i>Stock Management
                                <span class="breadcrumb-text">/ Stock List</span>
                            </h4>
                        </div>

                        <!-- Statistics Grid -->
                        <div class="stats-grid">
                            <div class="stat-card total">
                                <div class="stat-icon">
                                    <i class="bx bx-package"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                                    <div class="stat-label">Total Products</div>
                                </div>
                            </div>
                            <div class="stat-card low-stock">
                                <div class="stat-icon">
                                    <i class="bx bx-error"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                                    <div class="stat-label">Low Stock Items</div>
                                </div>
                            </div>
                            <div class="stat-card out-of-stock">
                                <div class="stat-icon">
                                    <i class="bx bx-x-circle"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $stats['out_of_stock']; ?></div>
                                    <div class="stat-label">Out of Stock</div>
                                </div>
                            </div>
                            <div class="stat-card value">
                                <div class="stat-icon">
                                    <i class="bx bx-dollar"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value">$<?php echo number_format($stats['total_value'], 0); ?></div>
                                    <div class="stat-label">Total Stock Value</div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons Section -->
                        <div class="action-buttons-section">
                            <h6>
                                <i class="bx bx-cog"></i>Quick Actions
                            </h6>
                            <div class="action-buttons">
                                <a href="stock-in.php" class="action-btn btn-success">
                                    <i class="bx bx-plus"></i>Stock In
                                </a>
                                <a href="stock-out.php" class="action-btn btn-warning">
                                    <i class="bx bx-minus"></i>Stock Out
                                </a>
                                <a href="stock-report.php" class="action-btn btn-info">
                                    <i class="bx bx-file"></i>View Reports
                                </a>
                            </div>
                        </div>

                        <!-- Stock List Table -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bx bx-list-ul"></i>Stock List
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Item ID</th>
                                                <th>Product Name</th>
                                                <th>Description</th>
                                                <th>Price</th>
                                                <th>Stock Quantity</th>
                                                <th>Supplier ID</th>
                                                <th>Last Updated</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($products)): ?>
                                                <?php foreach ($products as $product): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($product['id']); ?></strong>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($product['description'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <strong>$<?php echo number_format($product['price'] ?? 0, 2); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $quantity = $product['quantity'];
                                                            $badge_class = 'bg-success';
                                                            $badge_icon = 'bx-check-circle';
                                                            
                                                            if ($quantity <= 0) {
                                                                $badge_class = 'bg-danger';
                                                                $badge_icon = 'bx-x-circle';
                                                            } elseif ($quantity <= 10) {
                                                                $badge_class = 'bg-warning';
                                                                $badge_icon = 'bx-error';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $badge_class; ?>">
                                                                <i class="bx <?php echo $badge_icon; ?>"></i>
                                                                <?php echo htmlspecialchars($quantity); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($product['supplier_id'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <?php 
                                                            $date = $product['last_updated'] ?? 'N/A';
                                                            if ($date !== 'N/A') {
                                                                echo date('M d, Y H:i', strtotime($date));
                                                            } else {
                                                                echo $date;
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="stock-in.php?id=<?php echo htmlspecialchars($product['id']); ?>" 
                                                                   class="btn btn-sm btn-success" title="Stock In">
                                                                    <i class="bx bx-plus"></i>
                                                                </a>
                                                                <a href="stock-out.php?id=<?php echo htmlspecialchars($product['id']); ?>" 
                                                                   class="btn btn-sm btn-warning" title="Stock Out">
                                                                    <i class="bx bx-minus"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-sm btn-info" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#productModal" 
                                                                        data-product='<?php echo json_encode($product); ?>'
                                                                        title="View Details">
                                                                    <i class="bx bx-show"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="empty-state">
                                                        <i class="bx bx-package"></i>
                                                        <h6>No Stock Items Found</h6>
                                                        <p>Start by adding your first product to manage stock levels.</p>
                                                        <a href="stock-in.php" class="action-btn btn-success">
                                                            <i class="bx bx-plus"></i>Add First Product
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
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
                    <!-- / Footer -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Product Details Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalLabel">
                        <i class="bx bx-package me-2"></i>Product Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bx bx-info-circle text-primary"></i>Basic Information
                                    </h6>
                                    <div class="row mb-2">
                                        <div class="col-sm-5"><strong>Product ID:</strong></div>
                                        <div class="col-sm-7" id="modalProductId"></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-sm-5"><strong>Name:</strong></div>
                                        <div class="col-sm-7" id="modalProductName"></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-sm-5"><strong>Description:</strong></div>
                                        <div class="col-sm-7" id="modalProductDescription"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-5"><strong>Supplier ID:</strong></div>
                                        <div class="col-sm-7" id="modalProductSupplier"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bx bx-dollar text-success"></i>Pricing & Stock
                                    </h6>
                                    <div class="row mb-2">
                                        <div class="col-sm-5"><strong>Price:</strong></div>
                                        <div class="col-sm-7" id="modalProductPrice"></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-sm-5"><strong>Quantity:</strong></div>
                                        <div class="col-sm-7" id="modalProductQuantity"></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-sm-5"><strong>Total Value:</strong></div>
                                        <div class="col-sm-7" id="modalProductValue"></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-5"><strong>Last Updated:</strong></div>
                                        <div class="col-sm-7" id="modalProductUpdated"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x"></i>Close
                    </button>
                    <a href="#" class="btn btn-success" id="modalStockInBtn">
                        <i class="bx bx-plus"></i>Stock In
                    </a>
                    <a href="#" class="btn btn-warning" id="modalStockOutBtn">
                        <i class="bx bx-minus"></i>Stock Out
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>

    <!-- Page JS -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        initializeStockManagement();
    });

    function initializeStockManagement() {
        // Product modal functionality
        var productModal = document.getElementById('productModal');
        
        if (productModal) {
            productModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var product = JSON.parse(button.getAttribute('data-product'));
                
                // Update modal content
                document.getElementById('modalProductId').textContent = product.id;
                document.getElementById('modalProductName').textContent = product.name;
                document.getElementById('modalProductDescription').textContent = product.description || 'N/A';
                
                var price = parseFloat(product.price || 0);
                var quantity = parseInt(product.quantity || 0);
                var totalValue = price * quantity;
                
                document.getElementById('modalProductPrice').textContent = ' + price.toFixed(2);
                document.getElementById('modalProductQuantity').innerHTML = getQuantityBadge(quantity);
                document.getElementById('modalProductValue').textContent = ' + totalValue.toFixed(2);
                document.getElementById('modalProductSupplier').textContent = product.supplier_id || 'N/A';
                
                var lastUpdated = product.last_updated || 'N/A';
                if (lastUpdated !== 'N/A') {
                    var date = new Date(lastUpdated);
                    lastUpdated = date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
                document.getElementById('modalProductUpdated').textContent = lastUpdated;
                
                // Update action buttons
                document.getElementById('modalStockInBtn').href = 'stock-in.php?id=' + product.id;
                document.getElementById('modalStockOutBtn').href = 'stock-out.php?id=' + product.id;
            });
        }

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
                        searchProducts(searchTerm);
                    } else {
                        clearSearch();
                    }
                }
            });
        }

        // Auto-refresh stock data every 5 minutes
        setInterval(function() {
            console.log('Auto-refreshing stock data...');
            // In a real application, you would make an AJAX call here
            // refreshStockData();
        }, 300000); // 5 minutes
    }

    // Helper function to get quantity badge HTML
    function getQuantityBadge(quantity) {
        var badgeClass = 'bg-success';
        var icon = 'bx-check-circle';
        
        if (quantity <= 0) {
            badgeClass = 'bg-danger';
            icon = 'bx-x-circle';
        } else if (quantity <= 10) {
            badgeClass = 'bg-warning';
            icon = 'bx-error';
        }
        
        return `<span class="badge ${badgeClass}"><i class="bx ${icon}"></i> ${quantity}</span>`;
    }

    // Search functionality within the products table
    function searchProducts(searchTerm) {
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
            showNoResultsMessage();
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

    // Show no results message
    function showNoResultsMessage() {
        console.log('No search results found');
        // Could implement a temporary "no results" message here
    }

    // Quick actions
    function quickStockIn() {
        window.location.href = 'stock-in.php';
    }

    function quickStockOut() {
        window.location.href = 'stock-out.php';
    }

    function viewReports() {
        window.location.href = 'stock-report.php';
    }

    // Export functionality
    function exportStockData() {
        // Implementation for exporting stock data
        alert('Exporting stock data... This feature will be implemented.');
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + I for stock in
        if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
            e.preventDefault();
            quickStockIn();
        }
        
        // Ctrl/Cmd + O for stock out
        if ((e.ctrlKey || e.metaKey) && e.key === 'o') {
            e.preventDefault();
            quickStockOut();
        }
        
        // Ctrl/Cmd + R for reports
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            viewReports();
        }
    });

    // Real-time stock level monitoring (placeholder)
    function monitorStockLevels() {
        // Future implementation for real-time stock level updates
        console.log('Stock level monitoring initialized');
    }

    // Initialize stock monitoring
    monitorStockLevels();
    </script>
</body>

</html>

<?php
// Close database connection
$conn->close();
?>