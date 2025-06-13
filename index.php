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
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Dashboard - Inventomo</title>

    <meta name="description" content="Inventory Management System Dashboard" />

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
    <link rel="stylesheet" href="assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>

    <style>
    .charts-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .chart-card {
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 15px;
        background-color: white;
    }

    .chart-title {
        margin-bottom: 15px;
        font-size: 18px;
    }

    .stock-chart {
        height: 200px;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        padding-top: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #000;
    }

    .chart-bar {
        width: 40px;
        background-color: #f0f0f0;
        border: 1px solid #ccc;
    }

    /* Table Styling */
    .inventory-table {
        width: 100%;
        border-collapse: collapse;
    }

    .inventory-table th {
        border-bottom: 2px solid #000;
        text-align: left;
        padding: 8px;
    }

    .inventory-table td {
        border-bottom: 1px solid #ccc;
        padding: 8px;
    }

    /* Alerts List */
    .alert-list {
        list-style-type: disc;
        padding-left: 20px;
        margin-top: 10px;
    }

    .alert-list li {
        margin-bottom: 10px;
        border-bottom: 1px solid #eee;
        padding-bottom: 5px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .charts-container {
            grid-template-columns: 1fr;
        }
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

                    <a href="javascript:void(0);"
                        class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
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
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-receipt"></i>
                            <div data-i18n="invoice">Invoice</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="receipt.php" class="menu-link">
                                    <div data-i18n="receipt">Receipt</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="report.php" class="menu-link">
                                    <div data-i18n="receipt">Report</div>
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
                        <!-- Welcome Message -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h4 class="card-title mb-2">Welcome back, <?php echo htmlspecialchars($current_user_name); ?>! 👋</h4>
                                        <p class="card-text">Here's what's happening with your inventory today.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cards row with proper spacing -->
                        <div class="row mb-4">
                            <!-- Card Border Shadow -->
                            <div class="col-lg-3 col-sm-6 mb-4">
                                <div class="card card-border-shadow-primary h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="avatar me-4">
                                                <span class="avatar-initial rounded bg-label-primary"><i
                                                        class="bx bx-package bx-sm"></i></span>
                                            </div>
                                            <h4 class="mb-0">1,248</h4>
                                        </div>
                                        <p class="mb-2">Total Products</p>
                                        <p class="mb-0">
                                            <span class="text-heading fw-medium me-2">+12.5%</span>
                                            <span class="text-body-secondary">than last month</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6 mb-4">
                                <div class="card card-border-shadow-warning h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="avatar me-4">
                                                <span class="avatar-initial rounded bg-label-warning"><i
                                                        class="bx bx-error bx-sm"></i></span>
                                            </div>
                                            <h4 class="mb-0">23</h4>
                                        </div>
                                        <p class="mb-2">Low Stock Items</p>
                                        <p class="mb-0">
                                            <span class="text-heading fw-medium me-2">-5.2%</span>
                                            <span class="text-body-secondary">than last week</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6 mb-4">
                                <div class="card card-border-shadow-success h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="avatar me-4">
                                                <span class="avatar-initial rounded bg-label-success"><i
                                                        class="bx bx-shopping-bag bx-sm"></i></span>
                                            </div>
                                            <h4 class="mb-0">156</h4>
                                        </div>
                                        <p class="mb-2">Orders Today</p>
                                        <p class="mb-0">
                                            <span class="text-heading fw-medium me-2">+8.3%</span>
                                            <span class="text-body-secondary">than yesterday</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6 mb-4">
                                <div class="card card-border-shadow-info h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="avatar me-4">
                                                <span class="avatar-initial rounded bg-label-info"><i
                                                        class="bx bx-dollar bx-sm"></i></span>
                                            </div>
                                            <h4 class="mb-0">$12,847</h4>
                                        </div>
                                        <p class="mb-2">Revenue Today</p>
                                        <p class="mb-0">
                                            <span class="text-heading fw-medium me-2">+15.7%</span>
                                            <span class="text-body-secondary">than yesterday</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts and Tables Section -->
                        <div class="row">
                            <div class="charts-container">
                                <!-- Stock Overview Chart -->
                                <div class="chart-card">
                                    <div class="chart-title">Stock Overview</div>
                                    <div class="stock-chart">
                                        <div class="chart-bar" style="height: 40%; background-color: #696cff;"></div>
                                        <div class="chart-bar" style="height: 60%; background-color: #8592a3;"></div>
                                        <div class="chart-bar" style="height: 75%; background-color: #71dd37;"></div>
                                        <div class="chart-bar" style="height: 100%; background-color: #ffab00;"></div>
                                        <div class="chart-bar" style="height: 65%; background-color: #ff3e1d;"></div>
                                    </div>
                                    <div class="d-flex justify-content-around mt-3">
                                        <small>Jan</small>
                                        <small>Feb</small>
                                        <small>Mar</small>
                                        <small>Apr</small>
                                        <small>May</small>
                                    </div>
                                </div>

                                <!-- Recent Activity Table -->
                                <div class="chart-card">
                                    <div class="chart-title">Recent Inventory Activity</div>
                                    <table class="inventory-table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Activity</th>
                                                <th>Quantity</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Laptop Dell XPS</td>
                                                <td><span class="badge bg-success">Stock In</span></td>
                                                <td>+15</td>
                                                <td>Today</td>
                                            </tr>
                                            <tr>
                                                <td>iPhone 14 Pro</td>
                                                <td><span class="badge bg-danger">Stock Out</span></td>
                                                <td>-8</td>
                                                <td>Today</td>
                                            </tr>
                                            <tr>
                                                <td>Samsung Monitor</td>
                                                <td><span class="badge bg-success">Stock In</span></td>
                                                <td>+20</td>
                                                <td>Yesterday</td>
                                            </tr>
                                            <tr>
                                                <td>Wireless Mouse</td>
                                                <td><span class="badge bg-danger">Stock Out</span></td>
                                                <td>-12</td>
                                                <td>Yesterday</td>
                                            </tr>
                                            <tr>
                                                <td>USB Drive 64GB</td>
                                                <td><span class="badge bg-success">Stock In</span></td>
                                                <td>+50</td>
                                                <td>2 days ago</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Low Stock Alerts -->
                                <div class="chart-card">
                                    <div class="chart-title">Low Stock Alerts</div>
                                    <ul class="alert-list">
                                        <li><span class="text-danger">⚠</span> iPhone 14 Pro - Only 3 units left</li>
                                        <li><span class="text-danger">⚠</span> MacBook Air M2 - Only 2 units left</li>
                                        <li><span class="text-danger">⚠</span> iPad Pro 12.9" - Only 1 unit left</li>
                                        <li><span class="text-danger">⚠</span> Sony Headphones - Only 5 units left</li>
                                    </ul>
                                </div>

                                <!-- Quick Actions -->
                                <div class="chart-card">
                                    <div class="chart-title">Quick Actions</div>
                                    <div class="d-grid gap-2">
                                        <a href="inventory.php" class="btn btn-primary">
                                            <i class="bx bx-package me-2"></i>Manage Inventory
                                        </a>
                                        <a href="order-item.php" class="btn btn-outline-primary">
                                            <i class="bx bx-cart me-2"></i>New Order
                                        </a>
                                        <a href="booking-item.php" class="btn btn-outline-primary">
                                            <i class="bx bx-calendar me-2"></i>Book Item
                                        </a>
                                        <a href="report.php" class="btn btn-outline-secondary">
                                            <i class="bx bx-file me-2"></i>View Reports
                                        </a>
                                    </div>
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

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>

    <!-- Vendors JS -->
    <script src="assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="assets/js/dashboards-analytics.js"></script>

    <script>
    // Auto-refresh dashboard data every 5 minutes
    setInterval(function() {
        // You can add AJAX calls here to refresh dashboard data
        console.log('Dashboard data refreshed');
    }, 300000);

    // Search functionality
    document.querySelector('input[aria-label="Search..."]').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const searchTerm = this.value;
            if (searchTerm.trim()) {
                // Implement search functionality
                console.log('Searching for:', searchTerm);
            }
        }
    });
    </script>
</body>

</html>
