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

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) { // check connection
    die("Connection failed: " . mysqli_connect_error());
}

// Initialize user variables with proper defaults
$profile_link = "#";
$current_user_name = "User";
$current_user_role = "User";
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

// Session check and user profile link logic
if (isset($_SESSION['user_id']) && $conn) {
    $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    
    // Use prepared statement for better security
    $user_query = "SELECT * FROM user_profiles WHERE Id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $user_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $user_id);
        mysqli_stmt_execute($stmt);
        $user_result = mysqli_stmt_get_result($stmt);
        
        if ($user_result && mysqli_num_rows($user_result) > 0) {
            $user_data = mysqli_fetch_assoc($user_result);
            
            // Set user information
            $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
            $current_user_role = ucfirst($user_data['position']); // Capitalize first letter
            
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
            
            // Profile link goes to user-profile.php with their ID
            $profile_link = "user-profile.php?op=view&Id=" . urlencode($user_data['Id']);
        }
        mysqli_stmt_close($stmt);
    }
}

$user_avatar_url = getUserAvatarUrl($current_user_avatar, $avatar_path);

// Initialize filter variables
$search_term = '';
$category_filter = '';
$stock_status = '';
$success_message = '';
$error_message = '';

// Process delete action - added in-page delete functionality
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $itemID = (int)$_POST['itemID'];
    
    // Retrieve the image filename before deleting
    $get_image_query = "SELECT image FROM inventory_item WHERE itemID = ?";
    $stmt = mysqli_prepare($conn, $get_image_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $itemID);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $image_to_delete);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        // Delete the product
        $delete_query = "DELETE FROM inventory_item WHERE itemID = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $itemID);
            
            if (mysqli_stmt_execute($stmt)) {
                // If deletion was successful and there's an image, delete the image file too
                if (!empty($image_to_delete)) {
                    $file_to_delete = "uploads/" . $image_to_delete;
                    if (file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                }
                $success_message = "Product has been deleted successfully.";
            } else {
                $error_message = "Database Error: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Error preparing statement: " . mysqli_error($conn);
        }
    }
}

// Process filter form submission
if (isset($_GET['filter'])) {
    $search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
    $stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
}

// Build SQL query with filters
$sql = "SELECT itemID, product_name, type_product, stock, price FROM inventory_item WHERE 1=1";

// Add search filter if provided
if (!empty($search_term)) {
    $sql .= " AND (product_name LIKE '%$search_term%' OR itemID LIKE '%$search_term%')";
}

// Add category filter if provided
if (!empty($category_filter)) {
    $sql .= " AND type_product = '$category_filter'";
}

// Add stock status filter if provided
if (!empty($stock_status)) {
    if ($stock_status == 'in_stock') {
        $sql .= " AND stock > 10";
    } elseif ($stock_status == 'low_stock') {
        $sql .= " AND stock <= 10 AND stock > 0";
    } elseif ($stock_status == 'out_of_stock') {
        $sql .= " AND stock <= 0";
    }
}

// Add order by clause
$sql .= " ORDER BY itemID";

// Execute query
$result = mysqli_query($conn, $sql);

// Check if query was successful
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Get all product categories for filter dropdown
$category_query = "SELECT DISTINCT type_product FROM inventory_item ORDER BY type_product";
$category_result = mysqli_query($conn, $category_query);
$categories = [];

if ($category_result) {
    while ($cat_row = mysqli_fetch_assoc($category_result)) {
        $categories[] = $cat_row['type_product'];
    }
    mysqli_free_result($category_result);
}
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Inventory Management - Inventomo</title>

    <meta name="description" content="Inventory Management System" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
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
        margin-bottom: 1.5rem;
    }

    .page-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #566a7f;
        margin: 0;
    }

    .add-product-btn {
        padding: 0.5rem 1rem;
        background-color: #696cff;
        border: none;
        border-radius: 0.375rem;
        cursor: pointer;
        font-size: 0.875rem;
        color: white;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .add-product-btn:hover {
        background-color: #5f63f2;
        color: white;
    }

    .inventory-card {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #d9dee3;
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #566a7f;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-section {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .filter-input,
    .filter-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: #566a7f;
        background-color: white;
    }

    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #696cff;
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
    }

    .search-input {
        flex: 1;
        min-width: 250px;
    }

    .filter-btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 0.375rem;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary {
        background-color: #696cff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #5f63f2;
    }

    .btn-outline {
        background-color: transparent;
        color: #566a7f;
        border: 1px solid #d9dee3;
        text-decoration: none;
    }

    .btn-outline:hover {
        background-color: #f5f5f9;
        border-color: #696cff;
        color: #696cff;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #d9dee3;
    }

    .data-table th {
        background-color: #f5f5f9;
        font-weight: 600;
        color: #566a7f;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .data-table td {
        color: #566a7f;
        font-size: 0.875rem;
    }

    .data-table tbody tr:hover {
        background-color: #f5f5f9;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-in-stock {
        background-color: #d1fae5;
        color: #065f46;
    }

    .status-low-stock {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-out-stock {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .status-indicator {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 50%;
    }

    .indicator-green {
        background-color: #10b981;
    }

    .indicator-yellow {
        background-color: #f59e0b;
    }

    .indicator-red {
        background-color: #ef4444;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .action-btn {
        padding: 0.375rem 0.75rem;
        border: 1px solid #d9dee3;
        background-color: white;
        cursor: pointer;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        color: #566a7f;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .action-btn:hover {
        background-color: #f5f5f9;
        border-color: #696cff;
        color: #696cff;
    }

    .action-btn.delete:hover {
        border-color: #ef4444;
        color: #ef4444;
    }

    .checkbox-input {
        width: 1rem;
        height: 1rem;
        cursor: pointer;
        accent-color: #696cff;
    }

    .filter-active-badge {
        background-color: #e0f2fe;
        color: #0277bd;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        margin-left: 0.5rem;
    }

    .alert {
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        animation: fadeIn 0.5s ease-in-out;
    }

    .alert-success {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .no-data {
        text-align: center;
        padding: 3rem 1rem;
        color: #9ca3af;
    }

    .no-data i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #d1d5db;
    }

    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #d9dee3;
    }

    .pagination-info {
        color: #6b7280;
        font-size: 0.875rem;
    }

    .pagination-controls {
        display: flex;
        gap: 0.25rem;
    }

    .page-btn {
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        background-color: white;
        cursor: pointer;
        border-radius: 0.375rem;
        color: #566a7f;
        font-size: 0.875rem;
        text-decoration: none;
    }

    .page-btn:hover,
    .page-btn.active {
        background-color: #696cff;
        border-color: #696cff;
        color: white;
    }

    .page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background-color: white;
        border-radius: 0.5rem;
        width: 90%;
        max-width: 500px;
        padding: 1.5rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
    }

    .modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #374151;
        margin: 0 0 0 0.5rem;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1.5rem;
    }

    /* Avatar styles for better profile picture display */
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #696cff, #5f63f2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 16px;
        position: relative;
    }

    .avatar-circle::after {
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

    .profile-image {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #f8f9fa;
    }

    @media (max-width: 768px) {
        .filter-section {
            flex-direction: column;
        }

        .search-input {
            min-width: auto;
        }

        .content-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }

        .pagination-wrapper {
            flex-direction: column;
            gap: 1rem;
        }

        .action-buttons {
            flex-direction: column;
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
                    <li class="menu-item active">
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
                                    <div class="avatar avatar-online">
                                        <?php if ($user_avatar_url): ?>
                                            <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" 
                                                 alt="<?php echo htmlspecialchars($current_user_name); ?>" 
                                                 class="profile-image" />
                                        <?php else: ?>
                                            <div class="avatar-circle">
                                                <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $profile_link; ?>">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <?php if ($user_avatar_url): ?>
                                                            <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" 
                                                                 alt="<?php echo htmlspecialchars($current_user_name); ?>" 
                                                                 class="profile-image" />
                                                        <?php else: ?>
                                                            <div class="avatar-circle">
                                                                <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block">
                                                        <?php echo htmlspecialchars($current_user_name); ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($current_user_role); ?>
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
                            <h4 class="page-title">Inventory Management</h4>
                            <a href="add-new-product.php" class="add-product-btn">
                                <i class="bx bx-plus"></i>Add New Product
                            </a>
                        </div>

                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="bx bx-check-circle"></i>
                            <span><?php echo $success_message; ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-error">
                            <i class="bx bx-error-circle"></i>
                            <span><?php echo $error_message; ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Inventory Card -->
                        <div class="inventory-card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bx bx-package"></i>Inventory List
                                    <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_status)): ?>
                                    <span class="filter-active-badge">
                                        <i class="bx bx-filter-alt"></i>Filters Applied
                                    </span>
                                    <?php endif; ?>
                                </h5>
                            </div>

                            <!-- Filter Section -->
                            <form method="GET" action="inventory.php" id="filterForm">
                                <div class="filter-section">
                                    <input type="text" name="search" class="filter-input search-input"
                                        placeholder="Search by product name or ID..."
                                        value="<?php echo htmlspecialchars($search_term); ?>" id="searchInput">

                                    <select name="category" class="filter-select" id="categoryFilter">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>"
                                            <?php echo ($category == $category_filter) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select name="stock_status" class="filter-select" id="stockFilter">
                                        <option value="">All Stock Status</option>
                                        <option value="in_stock"
                                            <?php echo ($stock_status == 'in_stock') ? 'selected' : ''; ?>>In Stock
                                        </option>
                                        <option value="low_stock"
                                            <?php echo ($stock_status == 'low_stock') ? 'selected' : ''; ?>>Low Stock
                                        </option>
                                        <option value="out_of_stock"
                                            <?php echo ($stock_status == 'out_of_stock') ? 'selected' : ''; ?>>Out of
                                            Stock</option>
                                    </select>

                                    <button type="submit" name="filter" class="filter-btn btn-primary">
                                        <i class="bx bx-filter-alt"></i>Filter
                                    </button>

                                    <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_status)): ?>
                                    <a href="inventory.php" class="filter-btn btn-outline">
                                        <i class="bx bx-x"></i>Reset
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <!-- Data Table -->
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="checkbox-input" id="selectAll"
                                                    onchange="toggleAllCheckboxes()">
                                            </th>
                                            <th>ID</th>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Stock</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th style="width: 150px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="inventoryTableBody">
                                        <?php 
                                        if (mysqli_num_rows($result) > 0) {
                                            while($row = mysqli_fetch_assoc($result)) {
                                                // Determine status based on stock level
                                                $status_class = '';
                                                $status_text = '';
                                                $indicator_class = '';
                                                
                                                if ($row['stock'] <= 0) {
                                                    $status_class = 'status-out-stock';
                                                    $status_text = 'Out of Stock';
                                                    $indicator_class = 'indicator-red';
                                                } elseif ($row['stock'] <= 10) {
                                                    $status_class = 'status-low-stock';
                                                    $status_text = 'Low Stock';
                                                    $indicator_class = 'indicator-yellow';
                                                } else {
                                                    $status_class = 'status-in-stock';
                                                    $status_text = 'In Stock';
                                                    $indicator_class = 'indicator-green';
                                                }
                                                
                                                echo "<tr>";
                                                echo "<td><input type='checkbox' class='checkbox-input row-checkbox' value='" . $row['itemID'] . "'></td>";
                                                echo "<td>" . $row['itemID'] . "</td>";
                                                echo "<td>" . htmlspecialchars($row['product_name']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['type_product']) . "</td>";
                                                echo "<td>" . $row['stock'] . "</td>";
                                                echo "<td>$" . number_format($row['price'], 2) . "</td>";
                                                echo "<td>
                                                        <span class='status-badge " . $status_class . "'>
                                                            <span class='status-indicator " . $indicator_class . "'></span>
                                                            " . $status_text . "
                                                        </span>
                                                      </td>";
                                                echo "<td class='action-buttons'>
                                                        <a href='add-new-product.php?edit=" . $row['itemID'] . "' class='action-btn'>
                                                            <i class='bx bx-edit'></i>Edit
                                                        </a>
                                                        <button class='action-btn delete' onclick=\"deleteProduct(" . $row['itemID'] . ", '" . addslashes($row['product_name']) . "')\">
                                                            <i class='bx bx-trash'></i>Delete
                                                        </button>
                                                      </td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr>
                                                    <td colspan='8'>
                                                        <div class='no-data'>
                                                            <i class='bx bx-search'></i>
                                                            <p>No products found matching your criteria.</p>
                                                            <a href='inventory.php' class='filter-btn btn-outline'>
                                                                <i class='bx bx-x'></i> Clear Filters
                                                            </a>
                                                        </div>
                                                    </td>
                                                  </tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="pagination-wrapper">
                                <div class="pagination-info">
                                    Showing <?php echo mysqli_num_rows($result); ?> results
                                </div>
                                <div class="pagination-controls">
                                    <button class="page-btn" disabled>
                                        <i class="bx bx-chevron-left"></i>
                                    </button>
                                    <button class="page-btn active">1</button>
                                    <button class="page-btn" disabled>
                                        <i class="bx bx-chevron-right"></i>
                                    </button>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="bx bx-error-circle" style="color: #ef4444; font-size: 1.5rem;"></i>
                <h3 class="modal-title">Confirm Deletion</h3>
            </div>
            <p id="deleteMessage">Are you sure you want to delete this product?</p>
            <div class="modal-actions">
                <button type="button" onclick="closeDeleteModal()" class="filter-btn btn-outline">
                    Cancel
                </button>
                <form id="deleteForm" method="POST" action="inventory.php" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteItemID" name="itemID" value="">
                    <button type="submit" class="filter-btn"
                        style="background-color: #ef4444; color: white; border: none;">
                        <i class="bx bx-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>

    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Page JS -->
    <script>
    // Toggle all checkboxes
    function toggleAllCheckboxes() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.getElementsByClassName('row-checkbox');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = selectAll.checked;
        }
    }

    // Delete product confirmation
    function deleteProduct(itemID, productName) {
        const modal = document.getElementById('deleteModal');
        const deleteMessage = document.getElementById('deleteMessage');
        const deleteItemIDInput = document.getElementById('deleteItemID');

        deleteMessage.innerHTML =
            `Are you sure you want to delete <strong>"${productName}"</strong>?<br>This action cannot be undone.`;
        deleteItemIDInput.value = itemID;

        modal.style.display = 'flex';
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        modal.style.display = 'none';
    }

    // Close modal if clicked outside
    window.onclick = function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target === modal) {
            closeDeleteModal();
        }
    }

    // Reset filters function
    function resetFilters() {
        window.location.href = 'inventory.php';
    }

    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        });

        // Enhanced search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('filterForm').submit();
                }
            });
        }

        // Real-time filter feedback
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(function(select) {
            select.addEventListener('change', function() {
                // Optional: Auto-submit form on filter change
                // document.getElementById('filterForm').submit();
            });
        });
    });

    // Bulk actions (for future enhancement)
    function getSelectedItems() {
        const checkboxes = document.getElementsByClassName('row-checkbox');
        const selected = [];
        for (let i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                selected.push(checkboxes[i].value);
            }
        }
        return selected;
    }

    // Export functionality (placeholder)
    function exportData() {
        const selected = getSelectedItems();
        if (selected.length > 0) {
            console.log('Exporting selected items:', selected);
            // Implement export functionality here
        } else {
            alert('Please select items to export.');
        }
    }
    </script>
</body>

</html>