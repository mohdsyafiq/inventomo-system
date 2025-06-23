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

// Try to establish database connection with error handling
try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }

    // Set charset to handle special characters
    mysqli_set_charset($conn, "utf8");

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

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Sample report data - replace with actual database queries
$sample_reports = [
    [
        'id' => 1,
        'date' => '2024-03-20',
        'item_id' => 'INV-001',
        'description' => 'Wireless Bluetooth Headphones',
        'quantity' => 2,
        'price' => 89.99,
        'total' => 179.98,
        'category' => 'electronics'
    ],
    [
        'id' => 2,
        'date' => '2024-03-21',
        'item_id' => 'INV-002',
        'description' => 'Cotton T-Shirt Premium',
        'quantity' => 5,
        'price' => 24.99,
        'total' => 124.95,
        'category' => 'clothing'
    ],
    [
        'id' => 3,
        'date' => '2024-03-22',
        'item_id' => 'INV-003',
        'description' => 'Gaming Mechanical Keyboard',
        'quantity' => 1,
        'price' => 179.99,
        'total' => 179.99,
        'category' => 'electronics'
    ],
    [
        'id' => 4,
        'date' => '2024-03-23',
        'item_id' => 'INV-004',
        'description' => 'USB Drive 32GB',
        'quantity' => 3,
        'price' => 45.00,
        'total' => 135.00,
        'category' => 'electronics'
    ],
    [
        'id' => 5,
        'date' => '2024-03-24',
        'item_id' => 'INV-005',
        'description' => 'Power Bank 10000mAh',
        'quantity' => 2,
        'price' => 89.00,
        'total' => 178.00,
        'category' => 'electronics'
    ],
    [
        'id' => 6,
        'date' => '2024-03-25',
        'item_id' => 'INV-006',
        'description' => 'Smartphone Case',
        'quantity' => 4,
        'price' => 25.50,
        'total' => 102.00,
        'category' => 'electronics'
    ],
    [
        'id' => 7,
        'date' => '2024-03-26',
        'item_id' => 'INV-007',
        'description' => 'Wireless Charger',
        'quantity' => 1,
        'price' => 65.00,
        'total' => 65.00,
        'category' => 'electronics'
    ],
    [
        'id' => 8,
        'date' => '2024-03-27',
        'item_id' => 'INV-008',
        'description' => 'Bluetooth Speaker',
        'quantity' => 2,
        'price' => 120.00,
        'total' => 240.00,
        'category' => 'electronics'
    ]
];

// Calculate totals for initial display
$total_transactions = count($sample_reports);
$total_quantity = array_sum(array_column($sample_reports, 'quantity'));
$total_revenue = array_sum(array_column($sample_reports, 'total'));
$average_order = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Sales Report - Inventomo</title>

    <meta name="description" content="Sales and Inventory Reports" />

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

    .report-card {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .card-header {
        padding: 1.5rem;
        border-bottom: 1px solid #d9dee3;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #f8f9fa;
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #566a7f;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .card-actions {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn-refresh {
        background-color: #696cff;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
        font-weight: 500;
    }

    .btn-refresh:hover {
        background-color: #5f63f2;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(105, 108, 255, 0.2);
    }

    .filters-section {
        padding: 1.5rem;
        background-color: #f8f9fa;
        border-bottom: 1px solid #d9dee3;
    }

    .filter-row {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .filter-label {
        font-weight: 600;
        color: #566a7f;
        min-width: 100px;
        font-size: 0.875rem;
    }

    .filter-input {
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: #566a7f;
        background-color: white;
        min-width: 200px;
        transition: border-color 0.2s ease;
    }

    .filter-input:focus {
        outline: none;
        border-color: #696cff;
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
    }

    .date-separator {
        color: #566a7f;
        font-weight: 500;
        margin: 0 0.5rem;
    }

    .filter-note {
        color: #6b7280;
        font-size: 0.75rem;
        font-style: italic;
    }

    .actions-section {
        padding: 1rem 1.5rem;
        background-color: #f8f9fa;
        border-bottom: 1px solid #d9dee3;
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }

    .btn-primary {
        background-color: #696cff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #5f63f2;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(105, 108, 255, 0.2);
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
    }

    .btn-outline {
        background-color: transparent;
        color: #566a7f;
        border: 1px solid #d9dee3;
    }

    .btn-outline:hover {
        background-color: #f5f5f9;
        border-color: #696cff;
        color: #696cff;
        transform: translateY(-1px);
    }

    .btn-success {
        background-color: #28a745;
        color: white;
    }

    .btn-success:hover {
        background-color: #218838;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
    }

    .export-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .export-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: #566a7f;
        background-color: white;
        cursor: pointer;
        transition: border-color 0.2s ease;
    }

    .export-select:focus {
        outline: none;
        border-color: #696cff;
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
    }

    .table-section {
        padding: 1.5rem;
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
    }

    .report-table th,
    .report-table td {
        padding: 0.875rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid #d9dee3;
    }

    .report-table th {
        background-color: #f5f5f9;
        font-weight: 600;
        color: #566a7f;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        cursor: pointer;
        position: relative;
        transition: background-color 0.2s ease;
    }

    .report-table th:hover {
        background-color: #e9ecef;
    }

    .report-table td {
        color: #566a7f;
        font-size: 0.875rem;
        vertical-align: middle;
    }

    .report-table tbody tr:hover {
        background-color: #f8f9fa;
        transition: background-color 0.2s ease;
    }

    .amount-col {
        text-align: right !important;
        font-weight: 500;
    }

    .no-data {
        text-align: center;
        padding: 3rem 2rem;
        color: #6c757d;
    }

    .no-data i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #d9dee3;
    }

    .no-data h5 {
        margin-bottom: 0.5rem;
        color: #566a7f;
    }

    .no-data p {
        color: #9ca3af;
        margin-bottom: 0;
    }

    .summary-section {
        padding: 1.5rem;
        background-color: #f8f9fa;
        border-top: 1px solid #d9dee3;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .summary-card {
        background: white;
        padding: 1.5rem;
        border-radius: 0.5rem;
        border: 1px solid #d9dee3;
        text-align: center;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(161, 172, 184, 0.2);
    }

    .summary-label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .summary-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #374151;
    }

    .summary-card.revenue .summary-value {
        color: #28a745;
    }

    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        border-top: 1px solid #d9dee3;
        background-color: white;
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
        transition: all 0.2s;
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

    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10;
        backdrop-filter: blur(2px);
    }

    .loading-spinner {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #696cff;
        font-weight: 500;
        background: white;
        padding: 1rem 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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

        .filter-row {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-input {
            min-width: auto;
        }

        .actions-section {
            flex-direction: column;
            align-items: stretch;
        }

        .export-group {
            justify-content: center;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }

        .pagination-wrapper {
            flex-direction: column;
            gap: 1rem;
        }

        .card-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }

        .card-actions {
            justify-content: center;
        }

        .table-section {
            overflow-x: auto;
        }

        .report-table {
            min-width: 800px;
        }
    }

    body {
        background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)),
            url('assets/img/backgrounds/inside-background.jpeg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        background-repeat: no-repeat;
        min-height: 100vh;
    }

    /* Ensure layout wrapper takes full space */
    .layout-wrapper {
        background: transparent;
        min-height: 100vh;
    }

    /* Content wrapper with transparent background to show body background */
    .content-wrapper {
        background: transparent;
        min-height: 100vh;
    }

    .page-title {
        color: white;
        font-size: 2.0rem;
        font-weight: bold;
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
                            <i class="menu-icon tf-icons bx bx-package me-2"></i>
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
                            <i class="menu-icon tf-icons bx bx-receipt"></i>
                            <div data-i18n="Analytics">Order & Billing</div>
                        </a>
                    </li>
                    <li class="menu-item active">
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
                                                <div
                                                    class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                                    <?php if ($navbar_pic): ?>
                                                    <img src="<?php echo htmlspecialchars($navbar_pic); ?>"
                                                        alt="Profile Picture">
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
                                <i class="bx bxs-report"></i>Sales Report Analytics
                            </h4>
                        </div>

                        <!-- Report Card -->
                        <div class="report-card" style="position: relative;">
                            <!-- Loading Overlay -->
                            <div class="loading-overlay" id="loadingOverlay">
                                <div class="loading-spinner">
                                    <i class="bx bx-loader-alt bx-spin"></i>
                                    <span>Loading report data...</span>
                                </div>
                            </div>

                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bx bx-chart"></i>Sales Transaction Report
                                </h5>
                                <div class="card-actions">
                                    <button class="btn-refresh" onclick="refreshReport()">
                                        <i class="bx bx-refresh"></i>
                                        Refresh Data
                                    </button>
                                    <span class="filter-note">Last updated: <?php echo date('d M Y, H:i'); ?></span>
                                </div>
                            </div>

                            <!-- Filters Section -->
                            <div class="filters-section">
                                <div class="filter-row">
                                    <label class="filter-label">Date Range:</label>
                                    <input type="date" class="filter-input" id="fromDate" placeholder="From Date">
                                    <span class="date-separator">to</span>
                                    <input type="date" class="filter-input" id="toDate" placeholder="To Date">
                                    <span class="filter-note">Filter by transaction date</span>
                                </div>
                                <div class="filter-row">
                                    <label class="filter-label">Item Search:</label>
                                    <input type="text" class="filter-input" id="itemFilter"
                                        placeholder="Search by Item ID or Description">
                                    <select class="filter-input" id="categoryFilter">
                                        <option value="">All Categories</option>
                                        <option value="electronics">Electronics</option>
                                        <option value="clothing">Clothing</option>
                                        <option value="accessories">Accessories</option>
                                    </select>
                                    <span class="filter-note">Filter by product category</span>
                                </div>
                                <div class="filter-row">
                                    <label class="filter-label">Amount Range:</label>
                                    <input type="number" class="filter-input" id="minAmount"
                                        placeholder="Min Amount (RM)" step="0.01">
                                    <span class="date-separator">to</span>
                                    <input type="number" class="filter-input" id="maxAmount"
                                        placeholder="Max Amount (RM)" step="0.01">
                                    <span class="filter-note">Filter by transaction amount</span>
                                </div>
                            </div>

                            <!-- Actions Section -->
                            <div class="actions-section">
                                <button class="btn btn-primary" onclick="searchReport()">
                                    <i class="bx bx-search"></i>Search
                                </button>
                                <button class="btn btn-secondary" onclick="resetFilters()">
                                    <i class="bx bx-refresh"></i>Reset
                                </button>
                                <button class="btn btn-success" onclick="generateAdvancedReport()">
                                    <i class="bx bx-chart"></i>Advanced Report
                                </button>
                                <span class="filter-note">Default shows last 30 days</span>

                                <div class="export-group">
                                    <button class="btn btn-outline" onclick="exportReport()">
                                        <i class="bx bx-download"></i>Export
                                    </button>
                                    <select class="export-select" id="exportFormat">
                                        <option value="">Select format</option>
                                        <option value="excel">Excel (.xlsx)</option>
                                        <option value="pdf">PDF (.pdf)</option>
                                        <option value="csv">CSV (.csv)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Table Section -->
                            <div class="table-section">
                                <div class="table-responsive">
                                    <table class="report-table" id="reportTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 5%;">No.</th>
                                                <th style="width: 12%;">Date</th>
                                                <th style="width: 12%;">Item ID</th>
                                                <th style="width: 35%;">Item Description</th>
                                                <th style="width: 8%;">Qty</th>
                                                <th style="width: 14%;" class="amount-col">Unit Price</th>
                                                <th style="width: 14%;" class="amount-col">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="reportTableBody">
                                            <?php foreach ($sample_reports as $index => $report): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($report['date'])); ?></td>
                                                <td><strong><?php echo htmlspecialchars($report['item_id']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($report['description']); ?></td>
                                                <td><?php echo $report['quantity']; ?></td>
                                                <td class="amount-col">RM
                                                    <?php echo number_format($report['price'], 2); ?></td>
                                                <td class="amount-col"><strong>RM
                                                        <?php echo number_format($report['total'], 2); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Summary Section -->
                            <div class="summary-section">
                                <div class="summary-grid">
                                    <div class="summary-card">
                                        <div class="summary-label">Total Transactions</div>
                                        <div class="summary-value" id="totalTransactions">
                                            <?php echo $total_transactions; ?></div>
                                    </div>
                                    <div class="summary-card">
                                        <div class="summary-label">Total Quantity</div>
                                        <div class="summary-value" id="totalQuantity"><?php echo $total_quantity; ?>
                                        </div>
                                    </div>
                                    <div class="summary-card revenue">
                                        <div class="summary-label">Total Revenue</div>
                                        <div class="summary-value" id="totalRevenue">RM
                                            <?php echo number_format($total_revenue, 2); ?></div>
                                    </div>
                                    <div class="summary-card">
                                        <div class="summary-label">Average Order</div>
                                        <div class="summary-value" id="averageOrder">RM
                                            <?php echo number_format($average_order, 2); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div class="pagination-wrapper">
                                <div class="pagination-info" id="paginationInfo">
                                    Showing 1 to <?php echo count($sample_reports); ?> of
                                    <?php echo count($sample_reports); ?> entries
                                </div>
                                <div class="pagination-controls">
                                    <button class="page-btn" id="prevBtn" disabled>
                                        <i class="bx bx-chevron-left"></i>
                                    </button>
                                    <button class="page-btn active" id="currentPage">1</button>
                                    <button class="page-btn" id="nextBtn" disabled>
                                        <i class="bx bx-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div
                            class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                © <script>
                                document.write(new Date().getFullYear());
                                </script> Inventomo. All rights reserved.
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

    <!-- JavaScript -->
    <script>
    // Sample data for demonstration (this would come from PHP in a real application)
    const reportData = <?php echo json_encode($sample_reports); ?>;

    let filteredData = [...reportData];
    let currentPage = 1;
    const itemsPerPage = 10;

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        initializeReporting();
    });

    function initializeReporting() {
        renderTable();
        updateSummary();
        setDefaultDates();
        setupTableSorting();
        setupKeyboardShortcuts();
        setupSearchIntegration();
    }

    // Set default dates (last 30 days)
    function setDefaultDates() {
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));

        document.getElementById('fromDate').value = thirtyDaysAgo.toISOString().split('T')[0];
        document.getElementById('toDate').value = today.toISOString().split('T')[0];
    }

    // Show loading overlay
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    // Hide loading overlay
    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    // Search and filter functionality
    function searchReport() {
        showLoading();

        setTimeout(() => {
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            const itemFilter = document.getElementById('itemFilter').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const minAmount = parseFloat(document.getElementById('minAmount').value) || 0;
            const maxAmount = parseFloat(document.getElementById('maxAmount').value) || Infinity;

            filteredData = reportData.filter(item => {
                // Date filter
                let dateMatch = true;
                if (fromDate && toDate) {
                    const itemDate = new Date(item.date);
                    const startDate = new Date(fromDate);
                    const endDate = new Date(toDate);
                    dateMatch = itemDate >= startDate && itemDate <= endDate;
                }

                // Item filter
                const itemMatch = !itemFilter ||
                    item.item_id.toLowerCase().includes(itemFilter) ||
                    item.description.toLowerCase().includes(itemFilter);

                // Category filter
                const categoryMatch = !categoryFilter || item.category === categoryFilter;

                // Amount filter
                const amountMatch = item.total >= minAmount && item.total <= maxAmount;

                return dateMatch && itemMatch && categoryMatch && amountMatch;
            });

            currentPage = 1;
            renderTable();
            updateSummary();
            updatePagination();
            hideLoading();
        }, 800);
    }

    // Reset all filters
    function resetFilters() {
        document.getElementById('fromDate').value = '';
        document.getElementById('toDate').value = '';
        document.getElementById('itemFilter').value = '';
        document.getElementById('categoryFilter').value = '';
        document.getElementById('minAmount').value = '';
        document.getElementById('maxAmount').value = '';
        document.getElementById('exportFormat').value = '';

        filteredData = [...reportData];
        currentPage = 1;
        renderTable();
        updateSummary();
        updatePagination();

        // Reset to default dates
        setDefaultDates();
    }

    // Refresh report data
    function refreshReport() {
        const btn = event.target.closest('.btn-refresh');
        const originalContent = btn.innerHTML;

        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>Refreshing...';
        btn.disabled = true;

        setTimeout(() => {
            // In a real application, this would fetch fresh data from the server
            filteredData = [...reportData];
            renderTable();
            updateSummary();
            updatePagination();

            btn.innerHTML = originalContent;
            btn.disabled = false;

            // Update timestamp
            const timeElement = document.querySelector('.card-actions .filter-note');
            timeElement.textContent = `Last updated: ${new Date().toLocaleString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })}`;
        }, 1500);
    }

    // Generate advanced report
    function generateAdvancedReport() {
        showLoading();

        setTimeout(() => {
            hideLoading();
            alert(
                'Advanced Report Generated!\n\nThis would typically:\n• Generate detailed analytics\n• Create charts and graphs\n• Provide trend analysis\n• Export comprehensive PDF report\n\nFeature would be fully implemented in production.');
        }, 2000);
    }

    // Render table with current filtered data
    function renderTable() {
        const tbody = document.getElementById('reportTableBody');
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageData = filteredData.slice(startIndex, endIndex);

        if (pageData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="no-data">
                            <i class="bx bx-search-alt"></i>
                            <h5>No data found</h5>
                            <p>No transactions match your filter criteria</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = pageData.map((item, index) => `
            <tr>
                <td>${startIndex + index + 1}</td>
                <td>${formatDate(item.date)}</td>
                <td><strong>${item.item_id}</strong></td>
                <td>${item.description}</td>
                <td>${item.quantity}</td>
                <td class="amount-col">RM ${item.price.toFixed(2)}</td>
                <td class="amount-col"><strong>RM ${item.total.toFixed(2)}</strong></td>
            </tr>
        `).join('');
    }

    // Update summary cards
    function updateSummary() {
        const totalTransactions = filteredData.length;
        const totalQuantity = filteredData.reduce((sum, item) => sum + item.quantity, 0);
        const totalRevenue = filteredData.reduce((sum, item) => sum + item.total, 0);
        const averageOrder = totalTransactions > 0 ? totalRevenue / totalTransactions : 0;

        document.getElementById('totalTransactions').textContent = totalTransactions;
        document.getElementById('totalQuantity').textContent = totalQuantity;
        document.getElementById('totalRevenue').textContent = `RM ${totalRevenue.toFixed(2)}`;
        document.getElementById('averageOrder').textContent = `RM ${averageOrder.toFixed(2)}`;
    }

    // Update pagination info and controls
    function updatePagination() {
        const totalItems = filteredData.length;
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        const startItem = totalItems > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0;
        const endItem = Math.min(currentPage * itemsPerPage, totalItems);

        document.getElementById('paginationInfo').textContent =
            `Showing ${startItem} to ${endItem} of ${totalItems} entries`;

        document.getElementById('currentPage').textContent = currentPage;
        document.getElementById('prevBtn').disabled = currentPage <= 1;
        document.getElementById('nextBtn').disabled = currentPage >= totalPages;
    }

    // Setup table sorting
    function setupTableSorting() {
        const headers = document.querySelectorAll('#reportTable th');

        headers.forEach((header, index) => {
            if (index > 0 && index < headers.length) { // Skip No. column
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => sortTable(index));

                // Add sort indicator
                header.innerHTML +=
                    ' <i class="bx bx-sort" style="opacity: 0.5; font-size: 12px; margin-left: 5px;"></i>';
            }
        });
    }

    // Sort table by column
    function sortTable(columnIndex) {
        const isAscending = !filteredData.isSorted || filteredData.sortDirection !== 'asc';

        filteredData.sort((a, b) => {
            let aVal, bVal;

            switch (columnIndex) {
                case 1: // Date
                    aVal = new Date(a.date);
                    bVal = new Date(b.date);
                    break;
                case 2: // Item ID
                    aVal = a.item_id;
                    bVal = b.item_id;
                    break;
                case 3: // Description
                    aVal = a.description.toLowerCase();
                    bVal = b.description.toLowerCase();
                    break;
                case 4: // Quantity
                    aVal = a.quantity;
                    bVal = b.quantity;
                    break;
                case 5: // Price
                    aVal = a.price;
                    bVal = b.price;
                    break;
                case 6: // Total
                    aVal = a.total;
                    bVal = b.total;
                    break;
                default:
                    return 0;
            }

            if (aVal < bVal) return isAscending ? -1 : 1;
            if (aVal > bVal) return isAscending ? 1 : -1;
            return 0;
        });

        filteredData.isSorted = true;
        filteredData.sortDirection = isAscending ? 'asc' : 'desc';

        // Update sort indicators
        document.querySelectorAll('#reportTable th i').forEach(icon => {
            icon.className = 'bx bx-sort';
            icon.style.opacity = '0.5';
        });

        const currentIcon = document.querySelectorAll('#reportTable th')[columnIndex].querySelector('i');
        currentIcon.className = isAscending ? 'bx bx-sort-up' : 'bx bx-sort-down';
        currentIcon.style.opacity = '1';

        renderTable();
    }

    // Setup keyboard shortcuts
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter to search
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                searchReport();
            }

            // Ctrl/Cmd + R to reset (prevent default browser refresh)
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                resetFilters();
            }

            // Ctrl/Cmd + E to export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportReport();
            }
        });
    }

    // Setup search integration with navbar
    function setupSearchIntegration() {
        const navbarSearch = document.querySelector('input[aria-label="Search..."]');
        if (navbarSearch) {
            navbarSearch.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    const searchTerm = this.value;
                    if (searchTerm.trim()) {
                        // Set the item filter and trigger search
                        document.getElementById('itemFilter').value = searchTerm;
                        searchReport();
                    }
                }
            });
        }
    }

    // Format date for display
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    // Export functionality
    function exportReport() {
        const format = document.getElementById('exportFormat').value;

        if (!format) {
            alert('Please select an export format first');
            return;
        }

        // Simulate export process
        const loadingBtn = event.target;
        const originalText = loadingBtn.innerHTML;
        loadingBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>Exporting...';
        loadingBtn.disabled = true;

        setTimeout(() => {
            // Reset button
            loadingBtn.innerHTML = originalText;
            loadingBtn.disabled = false;

            // Show success message
            const summary = {
                transactions: filteredData.length,
                quantity: filteredData.reduce((sum, item) => sum + item.quantity, 0),
                revenue: filteredData.reduce((sum, item) => sum + item.total, 0)
            };

            alert(
                `Report exported successfully as ${format.toUpperCase()}!\n\n` +
                `📊 Export Summary:\n` +
                `• ${summary.transactions} transactions\n` +
                `• ${summary.quantity} total items\n` +
                `• RM ${summary.revenue.toFixed(2)} total revenue\n\n` +
                `File has been prepared for download.`
            );

            // Reset export dropdown
            document.getElementById('exportFormat').value = '';

            // In a real application, you would generate and download the actual file here
            console.log('Export data:', {
                format: format,
                data: filteredData,
                summary: summary,
                filters: {
                    fromDate: document.getElementById('fromDate').value,
                    toDate: document.getElementById('toDate').value,
                    itemFilter: document.getElementById('itemFilter').value,
                    categoryFilter: document.getElementById('categoryFilter').value,
                    minAmount: document.getElementById('minAmount').value,
                    maxAmount: document.getElementById('maxAmount').value
                }
            });
        }, 2000);
    }

    // Pagination controls
    document.getElementById('prevBtn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
            updatePagination();
        }
    });

    document.getElementById('nextBtn').addEventListener('click', () => {
        const totalPages = Math.ceil(filteredData.length / itemsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
            updatePagination();
        }
    });

    // Real-time search as user types
    document.getElementById('itemFilter').addEventListener('input', function() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(searchReport, 500); // Debounce search
    });

    // Auto-search when filters change
    document.getElementById('fromDate').addEventListener('change', searchReport);
    document.getElementById('toDate').addEventListener('change', searchReport);
    document.getElementById('categoryFilter').addEventListener('change', searchReport);

    // Debounced search for amount fields
    document.getElementById('minAmount').addEventListener('input', function() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(searchReport, 500);
    });

    document.getElementById('maxAmount').addEventListener('input', function() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(searchReport, 500);
    });

    // Handle export dropdown change
    document.getElementById('exportFormat').addEventListener('change', function() {
        if (this.value) {
            exportReport();
        }
    });

    // Initialize with default search after page load
    setTimeout(() => {
        searchReport();
    }, 100);
    </script>

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>

<?php
// Close database connection
if ($conn) {
    mysqli_close($conn);
}
?>