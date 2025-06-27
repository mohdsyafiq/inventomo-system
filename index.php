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

// Initialize count variables
$total_all = 0;
$total_staff = 0;
$total_admin = 0;
$total_manager = 0;
$new_staff = [];
$recent_activity = [];
$low_stock_items = [];

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

    // Get user counts by position
    if ($conn) {
        // Total All Users
        $total_query = "SELECT COUNT(*) as total FROM user_profiles";
        $total_result = mysqli_query($conn, $total_query);
        if ($total_result) {
            $total_row = mysqli_fetch_assoc($total_result);
            $total_all = $total_row['total'];
        }

        // Total Staff
        $staff_query = "SELECT COUNT(*) as total FROM user_profiles WHERE LOWER(position) = 'staff'";
        $staff_result = mysqli_query($conn, $staff_query);
        if ($staff_result) {
            $staff_row = mysqli_fetch_assoc($staff_result);
            $total_staff = $staff_row['total'];
        }

        // Total Admin
        $admin_query = "SELECT COUNT(*) as total FROM user_profiles WHERE LOWER(position) = 'admin'";
        $admin_result = mysqli_query($conn, $admin_query);
        if ($admin_result) {
            $admin_row = mysqli_fetch_assoc($admin_result);
            $total_admin = $admin_row['total'];
        }

        // Total Manager
        $manager_query = "SELECT COUNT(*) as total FROM user_profiles WHERE LOWER(position) = 'manager'";
        $manager_result = mysqli_query($conn, $manager_query);
        if ($manager_result) {
            $manager_row = mysqli_fetch_assoc($manager_result);
            $total_manager = $manager_row['total'];
        }

        // Get new staff (recently joined - last 30 days)
        $new_staff_query = "SELECT Id, full_name, position, date_join, profile_picture
                           FROM user_profiles
                           WHERE date_join >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                           ORDER BY date_join DESC
                           LIMIT 10";
        $new_staff_result = mysqli_query($conn, $new_staff_query);
        if ($new_staff_result) {
            while ($row = mysqli_fetch_assoc($new_staff_result)) {
                $new_staff[] = $row;
            }
        }

        // Fetch Recent Inventory Activity
        $limit = 5;
        $sql_recent_activity = "
            (SELECT
                product_name,
                'Stock In' AS activity_type,
                quantity_added AS quantity,
                transaction_date
            FROM
                stock_in_history)

            UNION ALL

            (SELECT
                product_name,
                'Stock Out' AS activity_type,
                quantity_deducted AS quantity,
                transaction_date
            FROM
                stock_out_history)

            ORDER BY
                transaction_date DESC
            LIMIT ?
        ";

        if ($stmt = $conn->prepare($sql_recent_activity)) {
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $recent_activity[] = $row;
            }
            $stmt->close();
        }

        // Generate chart data based on recent activity
        $chart_data = [];
        $product_totals = [];

        // Process recent activity for chart
        foreach ($recent_activity as $activity) {
            $product = $activity['product_name'];
            $quantity = (int)$activity['quantity'];

            if (!isset($product_totals[$product])) {
                $product_totals[$product] = 0;
            }

            if ($activity['activity_type'] == 'Stock In') {
                $product_totals[$product] += $quantity;
            } else {
                $product_totals[$product] -= $quantity;
            }
        }

        // Get top 5 products for chart
        arsort($product_totals);
        $chart_data = array_slice($product_totals, 0, 5, true);

        // Calculate max value for chart scaling
        $max_value = !empty($chart_data) ? max(array_map('abs', $chart_data)) : 100;
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

    <!-- Icons -->
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="assets/vendor/libs/apex-charts/apex-charts.css" />

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
        font-weight: 600;
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
        border-radius: 4px 4px 0 0;
    }

    .inventory-table {
        width: 100%;
        border-collapse: collapse;
    }

    .inventory-table th {
        border-bottom: 2px solid #000;
        text-align: left;
        padding: 10px;
        font-size: 14px;
        font-weight: 600;
    }

    .inventory-table td {
        border-bottom: 1px solid #ccc;
        padding: 10px;
        font-size: 14px;
    }

    .new-staff-table {
        width: 100%;
        border-collapse: collapse;
    }

    .new-staff-table th {
        border-bottom: 2px solid #000;
        text-align: left;
        padding: 10px;
        font-size: 14px;
        font-weight: 600;
    }

    .new-staff-table td {
        border-bottom: 1px solid #ccc;
        padding: 10px;
        font-size: 14px;
    }

    .alert-list {
        list-style-type: none;
        padding-left: 0;
        margin-top: 10px;
    }

    .alert-list li {
        margin-bottom: 10px;
        border-bottom: 1px solid #eee;
        padding-bottom: 8px;
        padding: 8px;
        background-color: #fff3cd;
        border-left: 4px solid #ffc107;
        border-radius: 4px;
    }

    @media (max-width: 768px) {
        .charts-container {
            grid-template-columns: 1fr;
        }
    }

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

    .user-details {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .user-name {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 2px;
        color: #333;
    }

    .user-role {
        font-size: 12px;
        color: #666;
        text-transform: capitalize;
    }

    /* Staff table specific styling */
    .staff-info {
        display: flex;
        align-items: center;
    }

    .staff-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.5rem;
        font-weight: 600;
        font-size: 11px;
        color: white;
        flex-shrink: 0;
    }

    .staff-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Navbar dropdown avatar styling */
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
                            <!-- Total All Users Card -->
                            <div class="col-lg-3 col-sm-6 mb-4">
                                <div class="card card-border-shadow-primary h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="avatar me-4">
                                                <span class="avatar-initial rounded bg-label-primary"><i
                                                        class="bx bx-user bx-sm"></i></span>
                                            </div>
                                            <h4 class="mb-0">Total All</h4>
                                        </div>
                                        <h2 class="mb-2"><?php echo $total_all; ?></h2>
                                        <p class="mb-2">Total Users</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Total Staff Card -->
                            <div class="col-lg-3 col-sm-6 mb-4">
                                <div class="card card-border-shadow-warning h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="avatar me-4">
                                                <span class="avatar-initial rounded bg-label-warning"><i
                                                        class="bx bx-user bx-sm"></i></span>
                                            </div>
                                            <h4 class="mb-0">Total Staff</h4>
                                        </div>
                                        <h2 class="mb-2"><?php echo $total_staff; ?></h2>
                                        <p class="mb-2">Staff Members</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Total Admin Card -->
                            <div class="col-lg-3 col-sm-6 mb-4">
                                <div class="card card-border-shadow-success h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="avatar me-4">
                                                <span class="avatar-initial rounded bg-label-success"><i
                                                        class="bx bx-user-check bx-sm"></i></span>
                                            </div>
                                            <h4 class="mb-0">Total Admin</h4>
                                        </div>
                                        <h2 class="mb-2"><?php echo $total_admin; ?></h2>
                                        <p class="mb-2">Admin Users</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Total Manager Card -->
                            <div class="col-lg-3 col-sm-6 mb-4">
                                <div class="card card-border-shadow-info h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="avatar me-4">
                                                <span class="avatar-initial rounded bg-label-info"><i
                                                        class="bx bx-crown bx-sm"></i></span>
                                            </div>
                                            <h4 class="mb-0">Total Manager</h4>
                                        </div>
                                        <h2 class="mb-2"><?php echo $total_manager; ?></h2>
                                        <p class="mb-2">Managers</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts and Tables Section -->
                        <div class="row">
                            <div class="charts-container">
                                <!-- Stock Overview Chart -->
                                <div class="chart-card">
                                    <div class="chart-title">Stock Overview - Recent Activity</div>
                                    <div class="stock-chart">
                                        <?php if (!empty($chart_data)): ?>
                                            <?php
                                            $colors = ['#690cff', '#690cff', '#690cff', '#690cff', '#690cff'];
                                            $color_index = 0;
                                            foreach ($chart_data as $product => $total):
                                                $height = $max_value > 0 ? abs($total) / $max_value * 100 : 10;
                                                $height = max($height, 10); // Minimum height for visibility
                                                $color = $colors[$color_index % count($colors)];
                                            ?>
                                                <div class="chart-bar"
                                                     style="height: <?php echo $height; ?>%; background-color: <?php echo $color; ?>;"
                                                     title="<?php echo htmlspecialchars($product) . ': ' . $total; ?>">
                                                </div>
                                            <?php
                                                $color_index++;
                                            endforeach; ?>
                                        <?php else: ?>
                                            <!-- Default bars when no data -->
                                            <div class="chart-bar" style="height: 30%; background-color: #e9ecef;" title="No data"></div>
                                            <div class="chart-bar" style="height: 20%; background-color: #e9ecef;" title="No data"></div>
                                            <div class="chart-bar" style="height: 15%; background-color: #e9ecef;" title="No data"></div>
                                            <div class="chart-bar" style="height: 10%; background-color: #e9ecef;" title="No data"></div>
                                            <div class="chart-bar" style="height: 25%; background-color: #e9ecef;" title="No data"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-around mt-3">
                                        <?php if (!empty($chart_data)): ?>
                                            <?php foreach ($chart_data as $product => $total): ?>
                                                <small title="<?php echo htmlspecialchars($product) . ': ' . $total; ?>">
                                                    <?php echo htmlspecialchars(substr($product, 0, 8)); ?><?php echo strlen($product) > 8 ? '...' : ''; ?>
                                                </small>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <small>Product 1</small>
                                            <small>Product 2</small>
                                            <small>Product 3</small>
                                            <small>Product 4</small>
                                            <small>Product 5</small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($chart_data)): ?>
                                        <div class="mt-2 text-center">
                                            <small class="text-muted">Net stock movement from recent activity</small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Recent Activity Table -->
                                <div class="card chart-card">
                                    <div class="card-header chart-title">Recent Inventory Activity</div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table inventory-table">
                                                <thead>
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Activity</th>
                                                        <th>Quantity</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($recent_activity)): ?>
                                                        <?php
                                                        $today_date = date('Y-m-d');
                                                        $yesterday_date = date('Y-m-d', strtotime('-1 day'));
                                                        ?>
                                                        <?php foreach ($recent_activity as $activity): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($activity['product_name']); ?></td>
                                                                <td>
                                                                    <?php
                                                                    $badge_class = '';
                                                                    $quantity_prefix = '';
                                                                    if ($activity['activity_type'] == 'Stock In') {
                                                                        $badge_class = 'bg-label-success';
                                                                        $quantity_prefix = '+';
                                                                    } else {
                                                                        $badge_class = 'bg-label-danger';
                                                                        $quantity_prefix = '-';
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($activity['activity_type']); ?></span>
                                                                </td>
                                                                <td><?php echo $quantity_prefix . htmlspecialchars($activity['quantity']); ?></td>
                                                                <td>
                                                                    <?php
                                                                    echo date('d/M/Y', strtotime($activity['transaction_date']));
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center text-muted">No recent activity found.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- New Staff Section -->
                                <div class="chart-card">
                                    <div class="chart-title">New Staff (Last 30 Days)</div>
                                    <div class="table-responsive">
                                        <table class="table new-staff-table">
                                            <thead>
                                                <tr>
                                                    <th>Staff</th>
                                                    <th>Position</th>
                                                    <th>Date Joined</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($new_staff)): ?>
                                                    <?php foreach ($new_staff as $staff): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="staff-info">
                                                                    <div class="staff-avatar bg-label-<?php echo getAvatarColor($staff['position']); ?>">
                                                                        <?php
                                                                        $staff_pic = getProfilePicture($staff['profile_picture'], $staff['full_name']);
                                                                        if ($staff_pic): ?>
                                                                            <img src="<?php echo htmlspecialchars($staff_pic); ?>" alt="Profile Picture">
                                                                        <?php else: ?>
                                                                            <?php echo strtoupper(substr($staff['full_name'] ?: 'U', 0, 1)); ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div>
                                                                        <div class="user-name"><?php echo htmlspecialchars($staff['full_name'] ?: 'N/A'); ?></div>
                                                                        <small class="text-muted">ID: <?php echo htmlspecialchars($staff['Id']); ?></small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-label-<?php
                                                                    $position = strtolower($staff['position']);
                                                                    if ($position == 'admin') echo 'success';
                                                                    elseif ($position == 'manager') echo 'info';
                                                                    elseif ($position == 'staff') echo 'warning';
                                                                    else echo 'secondary';
                                                                ?>">
                                                                    <?php echo htmlspecialchars(ucfirst($staff['position'])); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                if ($staff['date_join']) {
                                                                    echo date('d/M/Y', strtotime($staff['date_join']));
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">No new staff in the last 30 days</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="chart-card">
                                    <div class="chart-title">Quick Actions</div>
                                    <div class="d-grid gap-2">
                                        <a href="inventory.php" class="btn btn-primary">
                                            <i class="bx bx-package me-2"></i>Manage Inventory
                                        </a>
                                        <a href="stock-management.php" class="btn btn-outline-primary">
                                            <i class="bx bx-list-plus"></i> Stock Management
                                        </a>
                                        <a href="customer-supplier.php" class="btn btn-outline-primary">
                                            <i class="bx bxs-user-detail"></i> Supplier & Customer
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

    <?php
    // Close database connection at the end
    if (isset($conn)) {
        mysqli_close($conn);
    }
    ?>

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
        // Example: location.reload(); // Uncomment to auto-refresh page
    }, 300000);

    // Simple search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[aria-label="Search"]');

        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    const searchTerm = this.value;
                    if (searchTerm.trim()) {
                        // Simple search redirect
                        window.location.href = 'search.php?q=' + encodeURIComponent(searchTerm);
                    }
                }
            });
        }
    });

    // Add smooth transitions for cards
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.style.transition = 'transform 0.2s ease-in-out';
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });

    // Show notification for low stock items
    document.addEventListener('DOMContentLoaded', function() {
        // You can add other notifications here
        console.log('Dashboard loaded successfully');
    });
    </script>
</body>

</html>
