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

// Try to establish database connection with error handling
try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
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

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Sample receipt data - replace with actual database queries
$sample_receipts = [
    [
        'id' => 'INV-2025-00120',
        'invoice' => '1234567',
        'customer' => 'Aminah Binti Ali',
        'email' => 'aminah@gmail.com',
        'product' => 'Wireless Mouse & HDMI Cable',
        'date' => '2024-03-20',
        'amount' => 190.50
    ],
    [
        'id' => 'INV-2025-00121',
        'invoice' => '1234568',
        'customer' => 'Ahmad Rahman',
        'email' => 'ahmad@gmail.com',
        'product' => 'Bluetooth Speaker',
        'date' => '2024-03-21',
        'amount' => 135.20
    ],
    [
        'id' => 'INV-2025-00122',
        'invoice' => '1234569',
        'customer' => 'Siti Nurhaliza',
        'email' => 'siti@gmail.com',
        'product' => 'Gaming Keyboard',
        'date' => '2024-03-22',
        'amount' => 223.30
    ],
    [
        'id' => 'INV-2025-00123',
        'invoice' => '1234570',
        'customer' => 'Lee Wei Ming',
        'email' => 'wei.ming@gmail.com',
        'product' => 'USB Drive & Power Bank',
        'date' => '2024-03-23',
        'amount' => 234.84
    ]
];
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Receipt Management - Inventomo</title>

    <meta name="description" content="Receipt Management System" />

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

    .search-filter-container {
        margin-bottom: 1.5rem;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .search-box {
        flex: 1;
        min-width: 250px;
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        font-size: 0.875rem;
    }

    .search-box:focus {
        outline: none;
        border-color: #696cff;
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
    }

    .btn-new-receipt {
        background-color: #696cff;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        transition: all 0.2s;
    }

    .btn-new-receipt:hover {
        background-color: #5f63f2;
        color: white;
    }

    .receipt-card {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #566a7f;
        margin: 0 0 1.5rem 0;
        padding-bottom: 1rem;
        border-bottom: 1px solid #d9dee3;
    }

    .receipt-table {
        width: 100%;
        border-collapse: collapse;
    }

    .receipt-table th,
    .receipt-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #d9dee3;
    }

    .receipt-table th {
        background-color: #f5f5f9;
        font-weight: 600;
        color: #566a7f;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .receipt-table td {
        color: #566a7f;
        font-size: 0.875rem;
    }

    .receipt-table tbody tr:hover {
        background-color: #f5f5f9;
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
        font-size: 0.875rem;
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

    .receipt-id {
        font-weight: 600;
        color: #566a7f;
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

    .modal {
        background-color: white;
        border-radius: 0.5rem;
        width: 90%;
        max-width: 1000px;
        max-height: 90vh;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #d9dee3;
    }

    .modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #566a7f;
        margin: 0;
    }

    .modal-close {
        font-size: 1.5rem;
        cursor: pointer;
        color: #566a7f;
        border: none;
        background: none;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.25rem;
    }

    .modal-close:hover {
        background-color: #f5f5f9;
        color: #696cff;
    }

    .modal-content {
        flex: 1;
        display: flex;
        overflow: hidden;
    }

    .pdf-preview {
        flex: 1;
        background-color: #f5f5f9;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 1.5rem;
        overflow: auto;
    }

    .pdf-settings {
        width: 280px;
        background-color: white;
        border-left: 1px solid #d9dee3;
        padding: 1.5rem;
        overflow-y: auto;
    }

    .settings-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        color: #566a7f;
    }

    .setting-group {
        margin-bottom: 1rem;
    }

    .setting-label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #566a7f;
    }

    .setting-input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: #566a7f;
        background-color: white;
    }

    .setting-input:focus {
        outline: none;
        border-color: #696cff;
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
    }

    .button-group {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid #d9dee3;
    }

    .btn {
        flex: 1;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        text-align: center;
        border: none;
    }

    .btn-primary {
        background-color: #696cff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #5f63f2;
    }

    .btn-secondary {
        background-color: #f5f5f9;
        color: #566a7f;
        border: 1px solid #d9dee3;
    }

    .btn-secondary:hover {
        background-color: #e9ecef;
    }

    /* A4 Receipt Styles */
    .a4-paper {
        width: 210mm;
        min-height: 297mm;
        background-color: white;
        padding: 20mm;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        margin: 0 auto;
        font-family: 'Public Sans', sans-serif;
        transform: scale(0.75);
        transform-origin: top center;
    }

    .receipt-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .company-logo {
        font-size: 2rem;
        font-weight: 700;
        color: #696cff;
        margin-bottom: 0.5rem;
    }

    .receipt-info {
        text-align: center;
        margin-bottom: 2rem;
    }

    .receipt-number {
        font-size: 1.125rem;
        font-weight: 600;
        color: #566a7f;
        margin-bottom: 0.5rem;
    }

    .receipt-date {
        font-size: 0.875rem;
        color: #6b7280;
    }

    .divider {
        border: none;
        border-top: 1px solid #d9dee3;
        margin: 1.5rem 0;
    }

    .address-section {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2rem;
    }

    .address-block {
        width: 48%;
    }

    .address-title {
        font-weight: 600;
        color: #566a7f;
        margin-bottom: 0.5rem;
    }

    .address-content {
        font-size: 0.875rem;
        line-height: 1.5;
        color: #6b7280;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 2rem;
    }

    .items-table th,
    .items-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #d9dee3;
    }

    .items-table th {
        background-color: #f5f5f9;
        font-weight: 600;
        color: #566a7f;
    }

    .items-table .amount-col {
        text-align: right;
    }

    .totals-section {
        border-top: 2px solid #d9dee3;
    }

    .totals-section td {
        padding: 0.5rem 0.75rem;
        font-weight: 500;
    }

    .grand-total {
        font-weight: 700;
        font-size: 1.125rem;
        background-color: #f5f5f9;
    }

    .terms-section {
        margin-top: 2rem;
        font-size: 0.875rem;
    }

    .terms-title {
        font-weight: 600;
        margin-bottom: 0.75rem;
        color: #566a7f;
    }

    .terms-list {
        line-height: 1.6;
        color: #6b7280;
    }

    .receipt-footer {
        margin-top: 3rem;
        text-align: center;
        font-size: 0.875rem;
        color: #6b7280;
        border-top: 1px solid #d9dee3;
        padding-top: 1.5rem;
    }

    @media print {
        body * {
            visibility: hidden;
        }

        .a4-paper,
        .a4-paper * {
            visibility: visible;
        }

        .a4-paper {
            transform: scale(1);
            width: 210mm;
            height: 297mm;
            box-shadow: none;
            margin: 0;
            padding: 15mm;
        }

        .modal,
        .modal-overlay,
        .pdf-settings,
        .modal-header {
            display: none !important;
        }

        .pdf-preview {
            padding: 0;
            background: white;
        }
    }

    @media (max-width: 768px) {
        .modal {
            width: 95%;
            max-height: 95vh;
        }

        .modal-content {
            flex-direction: column;
        }

        .pdf-settings {
            width: 100%;
            border-left: none;
            border-top: 1px solid #d9dee3;
        }

        .a4-paper {
            transform: scale(0.5);
        }

        .address-section {
            flex-direction: column;
            gap: 1rem;
        }

        .address-block {
            width: 100%;
        }

        .search-filter-container {
            flex-direction: column;
        }

        .search-box {
            min-width: auto;
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
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-dock-top"></i>
                            <div data-i18n="stock">Stock</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="inventory.php" class="menu-link">
                                    <div data-i18n="inventory">Inventory</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="order-item.php" class="menu-link">
                                    <div data-i18n="order_item">Order Item</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-notepad"></i>
                            <div data-i18n="sales">Sales</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="booking-item.php" class="menu-link">
                                    <div data-i18n="booking_item">Booking Item</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item active">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-receipt"></i>
                            <div data-i18n="invoice">Invoice</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="receipt.php" class="menu-link">
                                    <div data-i18n="receipt">Receipt</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="report.php" class="menu-link">
                                    <div data-i18n="report">Report</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bxs-user-detail"></i>
                            <div data-i18n="sales">Customer & Supplier</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="customer-supplier.php" class="menu-link">
                                    <div data-i18n="booking_item">Customer & Supplier Management</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Account</span></li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div data-i18n="admin">Admin</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="user.php" class="menu-link">
                                    <div data-i18n="user">User</div>
                                </a>
                            </li>
                        </ul>
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
                                        <img src="assets/img/avatars/<?php echo htmlspecialchars($current_user_avatar); ?>"
                                            alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="assets/img/avatars/<?php echo htmlspecialchars($current_user_avatar); ?>"
                                                            alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
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
                            <h4 class="page-title">Receipt Management</h4>
                        </div>

                        <!-- Search and Filter Section -->
                        <div class="search-filter-container">
                            <input type="text" class="search-box" placeholder="Search receipts by ID, customer, or product..." id="receiptSearch">
                            <a href="#" class="btn-new-receipt" onclick="createNewReceipt()">
                                <i class="bx bx-plus"></i>
                                Generate Receipt
                            </a>
                        </div>

                        <!-- Receipt List Card -->
                        <div class="receipt-card">
                            <h5 class="card-title">List of Receipts</h5>

                            <div class="table-responsive">
                                <table class="receipt-table" id="receiptsTable">
                                    <thead>
                                        <tr>
                                            <th>Receipt ID</th>
                                            <th>Invoice</th>
                                            <th>Customer Name</th>
                                            <th>Email</th>
                                            <th>Product</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th style="width: 150px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sample_receipts as $receipt): ?>
                                        <tr>
                                            <td><span class="receipt-id"><?php echo htmlspecialchars($receipt['id']); ?></span></td>
                                            <td><?php echo htmlspecialchars($receipt['invoice']); ?></td>
                                            <td><?php echo htmlspecialchars($receipt['customer']); ?></td>
                                            <td><?php echo htmlspecialchars($receipt['email']); ?></td>
                                            <td><?php echo htmlspecialchars($receipt['product']); ?></td>
                                            <td>RM<?php echo number_format($receipt['amount'], 2); ?></td>
                                            <td><?php echo date('d-m-Y', strtotime($receipt['date'])); ?></td>
                                            <td class="action-buttons">
                                                <a href="javascript:void(0);" class="action-btn"
                                                    onclick="openPdfViewer('<?php echo $receipt['id']; ?>')">
                                                    <i class="bx bx-file-find"></i>View
                                                </a>
                                                <a href="javascript:void(0);" class="action-btn delete" onclick="deleteReceipt('<?php echo $receipt['id']; ?>')">
                                                    <i class="bx bx-trash"></i>Delete
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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

    <!-- PDF Viewer Modal -->
    <div class="modal-overlay" id="pdfModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Receipt Preview - INV-2025-00123</h3>
                <button class="modal-close" onclick="closePdfViewer()">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <div class="modal-content">
                <div class="pdf-preview">
                    <div class="a4-paper" id="receiptContent">
                        <div class="receipt-header">
                            <div class="company-logo">INVENTOMO</div>
                            <div style="font-size: 0.875rem; color: #6b7280;">Inventory Management System</div>
                        </div>

                        <div class="receipt-info">
                            <div class="receipt-number" id="receiptNumber">Receipt ID: #INV-2025-00123</div>
                            <div class="receipt-date" id="receiptDate">Date: March 23, 2024</div>
                        </div>

                        <hr class="divider">

                        <div class="address-section">
                            <div class="address-block">
                                <div class="address-title">Billed To:</div>
                                <div class="address-content">
                                    <strong>Lee Wei Ming</strong><br>
                                    123 Taman Harmoni<br>
                                    Jalan Sejahtera<br>
                                    Petaling Jaya, Selangor 47300<br>
                                    wei.ming@gmail.com
                                </div>
                            </div>

                            <div class="address-block">
                                <div class="address-title">From:</div>
                                <div class="address-content">
                                    <strong>Inventomo Inc.</strong><br>
                                    456 Business Park<br>
                                    Technology Avenue<br>
                                    Cyberjaya, Selangor 63000<br>
                                    support@inventomo.com<br>
                                    Tel: +603-8888-9999
                                </div>
                            </div>
                        </div>

                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 45%;">Description</th>
                                    <th style="width: 10%;">Qty</th>
                                    <th style="width: 20%;">Unit Price</th>
                                    <th style="width: 20%;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>USB Drive 32GB</td>
                                    <td>2</td>
                                    <td class="amount-col">RM 45.00</td>
                                    <td class="amount-col">RM 90.00</td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>Power Bank 10000mAh</td>
                                    <td>1</td>
                                    <td class="amount-col">RM 89.00</td>
                                    <td class="amount-col">RM 89.00</td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>Phone Case Premium</td>
                                    <td>1</td>
                                    <td class="amount-col">RM 35.00</td>
                                    <td class="amount-col">RM 35.00</td>
                                </tr>
                                <tr class="totals-section">
                                    <td colspan="4" style="text-align: right; font-weight: 500;">Subtotal:</td>
                                    <td class="amount-col">RM 214.00</td>
                                </tr>
                                <tr class="totals-section">
                                    <td colspan="4" style="text-align: right; font-weight: 500;">Tax (6% SST):</td>
                                    <td class="amount-col">RM 12.84</td>
                                </tr>
                                <tr class="totals-section">
                                    <td colspan="4" style="text-align: right; font-weight: 500;">Shipping:</td>
                                    <td class="amount-col">RM 8.00</td>
                                </tr>
                                <tr class="totals-section grand-total">
                                    <td colspan="4" style="text-align: right; font-weight: 700;">Total:</td>
                                    <td class="amount-col">RM 234.84</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="terms-section">
                            <div class="terms-title">Payment Information:</div>
                            <div class="terms-list">
                                <p><strong>Payment Method:</strong> Credit Card (**** 1234)</p>
                                <p><strong>Transaction ID:</strong> TXN-20240323-001</p>
                                <p><strong>Payment Status:</strong> Completed</p>
                            </div>
                        </div>

                        <div class="terms-section">
                            <div class="terms-title">Terms & Conditions:</div>
                            <div class="terms-list">
                                <p>1. All sales are final unless defective upon receipt.</p>
                                <p>2. Returns must be initiated within 14 days of purchase.</p>
                                <p>3. Warranty terms apply as per manufacturer specifications.</p>
                                <p>4. For support inquiries, contact us at support@inventomo.com</p>
                            </div>
                        </div>

                        <div class="receipt-footer">
                            <p><strong>Thank you for your business!</strong></p>
                            <p>This is a computer-generated receipt and does not require a signature.</p>
                            <p>For questions about this receipt, email support@inventomo.com or call +603-8888-9999</p>
                        </div>
                    </div>
                </div>

                <div class="pdf-settings">
                    <h4 class="settings-title">Print Settings</h4>

                    <div class="setting-group">
                        <label class="setting-label">Destination</label>
                        <select class="setting-input">
                            <option>Save as PDF</option>
                            <option>Print to Printer</option>
                        </select>
                    </div>

                    <div class="setting-group">
                        <label class="setting-label">Pages</label>
                        <select class="setting-input">
                            <option>All Pages</option>
                            <option>Current Page</option>
                        </select>
                    </div>

                    <div class="setting-group">
                        <label class="setting-label">Copies</label>
                        <input type="number" value="1" min="1" max="10" class="setting-input">
                    </div>

                    <div class="setting-group">
                        <label class="setting-label">Layout</label>
                        <select class="setting-input">
                            <option>Portrait</option>
                            <option>Landscape</option>
                        </select>
                    </div>

                    <div class="setting-group">
                        <label class="setting-label">Paper Size</label>
                        <select class="setting-input">
                            <option>A4</option>
                            <option>Letter</option>
                            <option>Legal</option>
                        </select>
                    </div>

                    <div class="setting-group">
                        <label class="setting-label">Margins</label>
                        <select class="setting-input">
                            <option>Default</option>
                            <option>Minimum</option>
                            <option>None</option>
                        </select>
                    </div>

                    <div class="button-group">
                        <button class="btn btn-secondary" onclick="closePdfViewer()">Cancel</button>
                        <button class="btn btn-primary" onclick="printReceipt()">
                            <i class="bx bx-printer me-1"></i>Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    // Sample receipt data
    const receiptData = {
        'INV-2025-00120': {
            title: 'Receipt Preview - INV-2025-00120',
            customer: 'Aminah Binti Ali',
            email: 'aminah@gmail.com',
            address: '123 Taman Indah<br>Jalan Harmoni<br>Shah Alam, Selangor 40000',
            date: 'March 20, 2024',
            items: [{
                    desc: 'Wireless Mouse',
                    qty: 2,
                    price: 50.00,
                    amount: 100.00
                },
                {
                    desc: 'HDMI Cable 2m',
                    qty: 1,
                    price: 75.00,
                    amount: 75.00
                }
            ],
            subtotal: 175.00,
            tax: 10.50,
            shipping: 5.00,
            total: 190.50,
            paymentMethod: 'Credit Card (**** 5678)',
            transactionId: 'TXN-20240320-001'
        },
        'INV-2025-00121': {
            title: 'Receipt Preview - INV-2025-00121',
            customer: 'Ahmad Rahman',
            email: 'ahmad@gmail.com',
            address: '456 Taman Sentosa<br>Jalan Makmur<br>Kuala Lumpur 50000',
            date: 'March 21, 2024',
            items: [{
                desc: 'Bluetooth Speaker',
                qty: 1,
                price: 120.00,
                amount: 120.00
            }],
            subtotal: 120.00,
            tax: 7.20,
            shipping: 8.00,
            total: 135.20,
            paymentMethod: 'Online Banking',
            transactionId: 'TXN-20240321-001'
        },
        'INV-2025-00122': {
            title: 'Receipt Preview - INV-2025-00122',
            customer: 'Siti Nurhaliza',
            email: 'siti@gmail.com',
            address: '789 Taman Bahagia<br>Jalan Sejahtera<br>Seremban, Negeri Sembilan 70000',
            date: 'March 22, 2024',
            items: [{
                    desc: 'Gaming Keyboard RGB',
                    qty: 1,
                    price: 180.00,
                    amount: 180.00
                },
                {
                    desc: 'Mouse Pad XL',
                    qty: 1,
                    price: 25.00,
                    amount: 25.00
                }
            ],
            subtotal: 205.00,
            tax: 12.30,
            shipping: 6.00,
            total: 223.30,
            paymentMethod: 'Credit Card (**** 9012)',
            transactionId: 'TXN-20240322-001'
        },
        'INV-2025-00123': {
            title: 'Receipt Preview - INV-2025-00123',
            customer: 'Lee Wei Ming',
            email: 'wei.ming@gmail.com',
            address: '123 Taman Harmoni<br>Jalan Sejahtera<br>Petaling Jaya, Selangor 47300',
            date: 'March 23, 2024',
            items: [{
                    desc: 'USB Drive 32GB',
                    qty: 2,
                    price: 45.00,
                    amount: 90.00
                },
                {
                    desc: 'Power Bank 10000mAh',
                    qty: 1,
                    price: 89.00,
                    amount: 89.00
                },
                {
                    desc: 'Phone Case Premium',
                    qty: 1,
                    price: 35.00,
                    amount: 35.00
                }
            ],
            subtotal: 214.00,
            tax: 12.84,
            shipping: 8.00,
            total: 234.84,
            paymentMethod: 'Credit Card (**** 1234)',
            transactionId: 'TXN-20240323-001'
        }
    };

    // Search functionality
    document.getElementById('receiptSearch').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#receiptsTable tbody tr');
        
        tableRows.forEach(row => {
            const receiptID = row.querySelector('.receipt-id').textContent.toLowerCase();
            const customerName = row.cells[2].textContent.toLowerCase();
            const product = row.cells[4].textContent.toLowerCase();
            
            if (receiptID.includes(searchTerm) || customerName.includes(searchTerm) || product.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Receipt management functions
    function createNewReceipt() {
        alert('Generate New Receipt functionality would be implemented here.\nThis would typically redirect to a receipt generation form or integration with order system.');
        // window.location.href = 'create-receipt.php';
    }

    function deleteReceipt(receiptId) {
        if (confirm('Are you sure you want to delete receipt ' + receiptId + '? This action cannot be undone.')) {
            alert('Delete Receipt ' + receiptId + ' functionality would be implemented here.\nThis would send a request to delete the receipt from the database.');
            // Implement actual delete functionality here
        }
    }

    // Open PDF viewer modal
    function openPdfViewer(receiptId) {
        const modal = document.getElementById('pdfModal');
        const data = receiptData[receiptId] || receiptData['INV-2025-00123'];

        // Update modal title
        document.getElementById('modalTitle').textContent = data.title;

        // Update receipt content
        updateReceiptContent(receiptId, data);

        // Show modal
        modal.style.display = 'flex';
    }

    // Update receipt content
    function updateReceiptContent(receiptId, data) {
        document.getElementById('receiptNumber').textContent = `Receipt ID: #${receiptId}`;
        document.getElementById('receiptDate').textContent = `Date: ${data.date}`;

        // Update customer address
        const addressContent = document.querySelector('.address-block .address-content');
        addressContent.innerHTML = `
            <strong>${data.customer}</strong><br>
            ${data.address}<br>
            ${data.email}
        `;

        // Update items table
        const itemsTableBody = document.querySelector('.items-table tbody');
        let itemsHTML = '';

        data.items.forEach((item, index) => {
            itemsHTML += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.desc}</td>
                    <td>${item.qty}</td>
                    <td class="amount-col">RM ${item.price.toFixed(2)}</td>
                    <td class="amount-col">RM ${item.amount.toFixed(2)}</td>
                </tr>
            `;
        });

        itemsHTML += `
            <tr class="totals-section">
                <td colspan="4" style="text-align: right; font-weight: 500;">Subtotal:</td>
                <td class="amount-col">RM ${data.subtotal.toFixed(2)}</td>
            </tr>
            <tr class="totals-section">
                <td colspan="4" style="text-align: right; font-weight: 500;">Tax (6% SST):</td>
                <td class="amount-col">RM ${data.tax.toFixed(2)}</td>
            </tr>
            <tr class="totals-section">
                <td colspan="4" style="text-align: right; font-weight: 500;">Shipping:</td>
                <td class="amount-col">RM ${data.shipping.toFixed(2)}</td>
            </tr>
            <tr class="totals-section grand-total">
                <td colspan="4" style="text-align: right; font-weight: 700;">Total:</td>
                <td class="amount-col">RM ${data.total.toFixed(2)}</td>
            </tr>
        `;

        itemsTableBody.innerHTML = itemsHTML;

        // Update payment information
        const paymentInfo = document.querySelector('.terms-section .terms-list');
        paymentInfo.innerHTML = `
            <p><strong>Payment Method:</strong> ${data.paymentMethod}</p>
            <p><strong>Transaction ID:</strong> ${data.transactionId}</p>
            <p><strong>Payment Status:</strong> Completed</p>
        `;
    }

    // Close PDF viewer modal
    function closePdfViewer() {
        const modal = document.getElementById('pdfModal');
        modal.style.display = 'none';
    }

    // Print receipt
    function printReceipt() {
        window.print();
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('pdfModal');
        if (event.target === modal) {
            closePdfViewer();
        }
    }

    // Enhanced search in navbar
    document.querySelector('input[aria-label="Search..."]').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const searchTerm = this.value;
            if (searchTerm.trim()) {
                // Implement global search functionality
                console.log('Global search for:', searchTerm);
            }
        }
    });

    // Table sorting functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tableHeaders = document.querySelectorAll('#receiptsTable th');
        let sortDirection = {};
        
        tableHeaders.forEach((header, index) => {
            if (index < tableHeaders.length - 1) { // Don't add sorting to Actions column
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    sortTable(index);
                });
                
                // Add sort indicator
                header.innerHTML += ' <i class="bx bx-sort" style="opacity: 0.5; font-size: 12px; margin-left: 5px;"></i>';
            }
        });

        function sortTable(columnIndex) {
            const table = document.getElementById('receiptsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Toggle sort direction
            sortDirection[columnIndex] = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
            
            rows.sort((a, b) => {
                const aVal = a.cells[columnIndex].textContent.trim();
                const bVal = b.cells[columnIndex].textContent.trim();
                
                let comparison = 0;
                
                // Handle different data types
                if (columnIndex === 5) { // Amount column
                    const aNum = parseFloat(aVal.replace('RM', '').replace(',', ''));
                    const bNum = parseFloat(bVal.replace('RM', '').replace(',', ''));
                    comparison = aNum - bNum;
                } else if (columnIndex === 6) { // Date column
                    const aDate = new Date(aVal.split('-').reverse().join('-')); // Convert DD-MM-YYYY to YYYY-MM-DD
                    const bDate = new Date(bVal.split('-').reverse().join('-'));
                    comparison = aDate - bDate;
                } else {
                    comparison = aVal.localeCompare(bVal);
                }
                
                return sortDirection[columnIndex] === 'asc' ? comparison : -comparison;
            });
            
            // Clear tbody and append sorted rows
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
            
            // Update sort indicators
            const headers = table.querySelectorAll('th');
            headers.forEach((header, index) => {
                const icon = header.querySelector('i');
                if (icon) {
                    if (index === columnIndex) {
                        icon.className = sortDirection[columnIndex] === 'asc' ? 'bx bx-sort-up' : 'bx bx-sort-down';
                        icon.style.opacity = '1';
                    } else {
                        icon.className = 'bx bx-sort';
                        icon.style.opacity = '0.5';
                    }
                }
            });
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N for new receipt
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                createNewReceipt();
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                const searchBox = document.getElementById('receiptSearch');
                if (searchBox.value) {
                    searchBox.value = '';
                    searchBox.dispatchEvent(new Event('keyup'));
                }
            }
        });
    });

    // Export functionality (placeholder)
    function exportReceipts() {
        const receipts = [];
        const rows = document.querySelectorAll('#receiptsTable tbody tr');
        
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const cells = row.querySelectorAll('td');
                receipts.push({
                    id: cells[0].textContent,
                    invoice: cells[1].textContent,
                    customer: cells[2].textContent,
                    email: cells[3].textContent,
                    product: cells[4].textContent,
                    amount: cells[5].textContent,
                    date: cells[6].textContent
                });
            }
        });
        
        console.log('Exporting receipts:', receipts);
        alert('Export functionality would be implemented here.\nThis would generate CSV, Excel, or PDF reports.');
    }
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
