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

// Sample booking data - replace with actual database queries
$sample_bookings = [
    [
        'id' => 'BK-20349',
        'item' => 'Laptop Dell XPS',
        'customer' => 'Aminah Binti Ahmad',
        'status' => 'complete',
        'date' => '2024-05-01',
        'amount' => 2500.00
    ],
    [
        'id' => 'BK-20350',
        'item' => 'iPhone 14 Pro',
        'customer' => 'Ahmad Rahman',
        'status' => 'pending',
        'date' => '2024-05-02',
        'amount' => 4200.00
    ],
    [
        'id' => 'BK-20351',
        'item' => 'Gaming Chair',
        'customer' => 'Sarah Lim',
        'status' => 'cancelled',
        'date' => '2024-05-03',
        'amount' => 680.00
    ],
    [
        'id' => 'BK-20352',
        'item' => 'Wireless Headphones',
        'customer' => 'Maria Santos',
        'status' => 'complete',
        'date' => '2024-05-04',
        'amount' => 350.00
    ],
    [
        'id' => 'BK-20353',
        'item' => 'MacBook Pro',
        'customer' => 'David Tan',
        'status' => 'processing',
        'date' => '2024-05-05',
        'amount' => 5800.00
    ]
];

// Calculate statistics
$total_bookings = count($sample_bookings);
$pending_bookings = count(array_filter($sample_bookings, fn($booking) => $booking['status'] === 'pending'));
$completed_bookings = count(array_filter($sample_bookings, fn($booking) => $booking['status'] === 'complete'));
$total_value = array_sum(array_column($sample_bookings, 'amount'));
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Booking Item - Inventomo</title>

    <meta name="description" content="Booking Management System" />

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
    .stats-card {
        transition: all 0.3s ease;
        border-radius: 0.5rem;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .stats-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        margin-right: 1rem;
    }

    .stats-detail {
        flex: 1;
    }

    .stats-value {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .stats-label {
        font-size: 0.875rem;
        color: #697a8d;
        margin-bottom: 0;
    }

    .stats-change {
        font-size: 0.75rem;
    }

    .growth-positive {
        color: #71dd37;
    }

    .growth-negative {
        color: #ff3e1d;
    }

    /* Custom table styles */
    .table th {
        background-color: #f5f5f9;
        font-weight: 600;
        color: #566a7f;
        border-bottom: 2px solid #d9dee3;
    }

    .table td {
        vertical-align: middle;
    }

    .booking-id {
        font-weight: 600;
        color: #696cff;
    }

    .customer-name {
        font-weight: 500;
    }

    .item-name {
        color: #566a7f;
    }

    /* Action buttons */
    .action-dropdown .dropdown-toggle {
        border: none;
        background: transparent;
    }

    .action-dropdown .dropdown-toggle:focus {
        box-shadow: none;
    }

    /* Status badges styling */
    .badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
    }

    /* Card header improvement */
    .card-header {
        border-bottom: 1px solid #d9dee3;
        background-color: transparent;
        display: flex;
        justify-content: between;
        align-items: center;
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

    .filter-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        background-color: white;
    }

    .btn-new-booking {
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

    .btn-new-booking:hover {
        background-color: #5f63f2;
        color: white;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .stats-card .card-body {
            padding: 1rem;
        }

        .stats-icon {
            width: 40px;
            height: 40px;
            margin-right: 0.75rem;
        }

        .stats-value {
            font-size: 1.5rem;
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
                                    <div data-i18n="order-item">Order Item</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item active">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-notepad"></i>
                            <div data-i18n="sales">Sales</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="booking-item.php" class="menu-link">
                                    <div data-i18n="booking-item">Booking Item</div>
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
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Sales /</span> Booking Item</h4>

                        <!-- Booking Dashboard Stats -->
                        <div class="row mb-4">
                            <!-- Total Bookings Card -->
                            <div class="col-lg-3 col-md-6 col-12 mb-4">
                                <div class="card stats-card h-100">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="stats-icon bg-label-primary">
                                            <i class="bx bx-package fs-3"></i>
                                        </div>
                                        <div class="stats-detail">
                                            <h3 class="stats-value"><?php echo $total_bookings; ?></h3>
                                            <p class="stats-label">Total Bookings</p>
                                            <small class="stats-change growth-positive">
                                                <i class="bx bx-up-arrow-alt"></i> +12.5% this month
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pending Approvals Card -->
                            <div class="col-lg-3 col-md-6 col-12 mb-4">
                                <div class="card stats-card h-100">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="stats-icon bg-label-warning">
                                            <i class="bx bx-time-five fs-3"></i>
                                        </div>
                                        <div class="stats-detail">
                                            <h3 class="stats-value"><?php echo $pending_bookings; ?></h3>
                                            <p class="stats-label">Pending Approvals</p>
                                            <small class="stats-change growth-negative">
                                                <i class="bx bx-down-arrow-alt"></i> -3.8% this week
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Completed Bookings Card -->
                            <div class="col-lg-3 col-md-6 col-12 mb-4">
                                <div class="card stats-card h-100">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="stats-icon bg-label-success">
                                            <i class="bx bx-check-circle fs-3"></i>
                                        </div>
                                        <div class="stats-detail">
                                            <h3 class="stats-value"><?php echo $completed_bookings; ?></h3>
                                            <p class="stats-label">Completed Bookings</p>
                                            <small class="stats-change growth-positive">
                                                <i class="bx bx-up-arrow-alt"></i> +8.1% growth
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Value Card -->
                            <div class="col-lg-3 col-md-6 col-12 mb-4">
                                <div class="card stats-card h-100">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="stats-icon bg-label-info">
                                            <i class="bx bx-dollar-circle fs-3"></i>
                                        </div>
                                        <div class="stats-detail">
                                            <h3 class="stats-value">RM<?php echo number_format($total_value, 0); ?></h3>
                                            <p class="stats-label">Total Value</p>
                                            <small class="stats-change growth-positive">
                                                <i class="bx bx-up-arrow-alt"></i> +15.3% revenue
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /Booking Dashboard Stats -->

                        <!-- Search and Filter Section -->
                        <div class="search-filter-container">
                            <input type="text" class="search-box" placeholder="Search bookings by ID, item, or customer..." id="bookingSearch">
                            <select class="filter-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="complete">Complete</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <a href="#" class="btn-new-booking" onclick="createNewBooking()">
                                <i class="bx bx-plus"></i>
                                New Booking
                            </a>
                        </div>

                        <!-- Booking List Table -->
                        <div class="card">
                            <h5 class="card-header">
                                <i class="bx bx-list-ul me-2"></i>Booking List
                            </h5>
                            <div class="table-responsive text-nowrap">
                                <table class="table table-hover" id="bookingsTable">
                                    <caption class="ms-4 text-muted">
                                        Manage and track all booking requests
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Item Name</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sample_bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <span class="booking-id"><?php echo htmlspecialchars($booking['id']); ?></span>
                                            </td>
                                            <td class="item-name"><?php echo htmlspecialchars($booking['item']); ?></td>
                                            <td class="customer-name"><?php echo htmlspecialchars($booking['customer']); ?></td>
                                            <td>RM<?php echo number_format($booking['amount'], 2); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = '';
                                                $status_text = ucfirst($booking['status']);
                                                switch($booking['status']) {
                                                    case 'complete':
                                                        $badge_class = 'bg-label-success';
                                                        break;
                                                    case 'pending':
                                                        $badge_class = 'bg-label-warning';
                                                        break;
                                                    case 'processing':
                                                        $badge_class = 'bg-label-info';
                                                        break;
                                                    case 'cancelled':
                                                        $badge_class = 'bg-label-danger';
                                                        break;
                                                    default:
                                                        $badge_class = 'bg-label-secondary';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                            </td>
                                            <td><?php echo date('d-m-Y', strtotime($booking['date'])); ?></td>
                                            <td>
                                                <div class="dropdown action-dropdown">
                                                    <button type="button" class="btn p-0 dropdown-toggle hide-arrow"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bx bx-dots-vertical-rounded"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="javascript:void(0);" onclick="editBooking('<?php echo $booking['id']; ?>')">
                                                            <i class="bx bx-edit-alt me-1"></i> Edit
                                                        </a>
                                                        <a class="dropdown-item" href="javascript:void(0);" onclick="viewBookingDetails('<?php echo $booking['id']; ?>')">
                                                            <i class="bx bx-show me-1"></i> View Details
                                                        </a>
                                                        <?php if($booking['status'] === 'pending'): ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-success" href="javascript:void(0);" onclick="approveBooking('<?php echo $booking['id']; ?>')">
                                                            <i class="bx bx-check me-1"></i> Approve
                                                        </a>
                                                        <?php endif; ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="deleteBooking('<?php echo $booking['id']; ?>')">
                                                            <i class="bx bx-trash me-1"></i> Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- /Booking List Table -->
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

    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Page JS -->
    <script>
    // Search functionality
    document.getElementById('bookingSearch').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#bookingsTable tbody tr');
        
        tableRows.forEach(row => {
            const bookingID = row.querySelector('.booking-id').textContent.toLowerCase();
            const itemName = row.querySelector('.item-name').textContent.toLowerCase();
            const customerName = row.querySelector('.customer-name').textContent.toLowerCase();
            
            if (bookingID.includes(searchTerm) || itemName.includes(searchTerm) || customerName.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Status filter functionality
    document.getElementById('statusFilter').addEventListener('change', function() {
        const selectedStatus = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#bookingsTable tbody tr');
        
        tableRows.forEach(row => {
            const statusBadge = row.querySelector('.badge');
            const rowStatus = statusBadge.textContent.toLowerCase().trim();
            
            if (selectedStatus === '' || rowStatus === selectedStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Booking management functions
    function createNewBooking() {
        alert('Create New Booking functionality would be implemented here.\nThis would typically redirect to a booking form or open a modal.');
        // window.location.href = 'create-booking.php';
    }

    function editBooking(bookingId) {
        alert('Edit Booking ' + bookingId + ' functionality would be implemented here.\nThis would typically redirect to an edit form.');
        // window.location.href = 'edit-booking.php?id=' + bookingId;
    }

    function viewBookingDetails(bookingId) {
        alert('View Details for Booking ' + bookingId + ' functionality would be implemented here.\nThis would show detailed booking information.');
        // window.location.href = 'booking-details.php?id=' + bookingId;
    }

    function approveBooking(bookingId) {
        if (confirm('Are you sure you want to approve booking ' + bookingId + '?')) {
            // Find the row and update the status
            const rows = document.querySelectorAll('#bookingsTable tbody tr');
            rows.forEach(row => {
                const bookingIdElement = row.querySelector('.booking-id');
                if (bookingIdElement && bookingIdElement.textContent === bookingId) {
                    const statusBadge = row.querySelector('.badge');
                    if (statusBadge) {
                        statusBadge.className = 'badge bg-label-success';
                        statusBadge.textContent = 'Complete';
                    }
                }
            });
            
            alert('Booking ' + bookingId + ' has been approved and marked as complete.');
            // Here you would typically send an AJAX request to update the database
        }
    }

    function deleteBooking(bookingId) {
        if (confirm('Are you sure you want to delete booking ' + bookingId + '? This action cannot be undone.')) {
            alert('Delete Booking ' + bookingId + ' functionality would be implemented here.\nThis would send a request to delete the booking from the database.');
            // Implement actual delete functionality here
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

    // Auto-refresh functionality (optional)
    setInterval(function() {
        // You can add AJAX calls here to refresh booking data
        console.log('Booking data could be refreshed here');
    }, 300000); // 5 minutes

    // Table sorting functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tableHeaders = document.querySelectorAll('#bookingsTable th');
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
            const table = document.getElementById('bookingsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Toggle sort direction
            sortDirection[columnIndex] = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
            
            rows.sort((a, b) => {
                const aVal = a.cells[columnIndex].textContent.trim();
                const bVal = b.cells[columnIndex].textContent.trim();
                
                let comparison = 0;
                
                // Handle different data types
                if (columnIndex === 3) { // Amount column
                    const aNum = parseFloat(aVal.replace('RM', '').replace(',', ''));
                    const bNum = parseFloat(bVal.replace('RM', '').replace(',', ''));
                    comparison = aNum - bNum;
                } else if (columnIndex === 5) { // Date column
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
            // Ctrl/Cmd + N for new booking
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                createNewBooking();
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                const searchBox = document.getElementById('bookingSearch');
                if (searchBox.value) {
                    searchBox.value = '';
                    searchBox.dispatchEvent(new Event('keyup'));
                }
                
                // Also reset status filter
                const statusFilter = document.getElementById('statusFilter');
                statusFilter.value = '';
                statusFilter.dispatchEvent(new Event('change'));
            }
        });

        // Add hover effects to stats cards
        const statsCards = document.querySelectorAll('.stats-card');
        statsCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });

    // Export functionality (placeholder)
    function exportBookings() {
        const bookings = [];
        const rows = document.querySelectorAll('#bookingsTable tbody tr');
        
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const cells = row.querySelectorAll('td');
                bookings.push({
                    id: cells[0].textContent,
                    item: cells[1].textContent,
                    customer: cells[2].textContent,
                    amount: cells[3].textContent,
                    status: cells[4].textContent,
                    date: cells[5].textContent
                });
            }
        });
        
        console.log('Exporting bookings:', bookings);
        alert('Export functionality would be implemented here.\nThis would generate CSV, Excel, or PDF reports.');
    }

    // Status update functionality
    function updateBookingStatus(bookingId, newStatus) {
        if (confirm(`Are you sure you want to update booking ${bookingId} status to ${newStatus}?`)) {
            // Find the row and update the status
            const rows = document.querySelectorAll('#bookingsTable tbody tr');
            rows.forEach(row => {
                const bookingIdCell = row.querySelector('.booking-id');
                if (bookingIdCell && bookingIdCell.textContent === bookingId) {
                    const statusCell = row.querySelector('.badge');
                    if (statusCell) {
                        // Update badge class based on new status
                        let badgeClass = '';
                        switch(newStatus.toLowerCase()) {
                            case 'complete':
                                badgeClass = 'badge bg-label-success';
                                break;
                            case 'pending':
                                badgeClass = 'badge bg-label-warning';
                                break;
                            case 'processing':
                                badgeClass = 'badge bg-label-info';
                                break;
                            case 'cancelled':
                                badgeClass = 'badge bg-label-danger';
                                break;
                            default:
                                badgeClass = 'badge bg-label-secondary';
                        }
                        statusCell.className = badgeClass;
                        statusCell.textContent = newStatus;
                    }
                }
            });
            
            alert(`Booking ${bookingId} status updated to ${newStatus}`);
            // Here you would typically send an AJAX request to update the database
        }
    }

    // Print functionality
    function printBookings() {
        const printWindow = window.open('', '_blank');
        const tableHTML = document.getElementById('bookingsTable').outerHTML;
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Bookings Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                        .bg-label-success { background: #d1fae5; color: #065f46; }
                        .bg-label-warning { background: #fef3c7; color: #92400e; }
                        .bg-label-info { background: #dbeafe; color: #1e40af; }
                        .bg-label-danger { background: #fee2e2; color: #991b1b; }
                    </style>
                </head>
                <body>
                    <h1>Bookings Report</h1>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                    ${tableHTML.replace(/<div class="dropdown[^>]*>.*?<\/div>/gs, '')}
                </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    }

    // Add context menu for quick actions (right-click)
    document.addEventListener('contextmenu', function(e) {
        const row = e.target.closest('tr');
        if (row && row.querySelector('.booking-id')) {
            e.preventDefault();
            const bookingId = row.querySelector('.booking-id').textContent;
            
            // Create context menu (basic implementation)
            const contextMenu = document.createElement('div');
            contextMenu.style.position = 'fixed';
            contextMenu.style.left = e.pageX + 'px';
            contextMenu.style.top = e.pageY + 'px';
            contextMenu.style.background = 'white';
            contextMenu.style.border = '1px solid #ccc';
            contextMenu.style.padding = '5px';
            contextMenu.style.zIndex = '1000';
            contextMenu.innerHTML = `
                <div style="padding: 5px; cursor: pointer;" onclick="viewBookingDetails('${bookingId}')">View Details</div>
                <div style="padding: 5px; cursor: pointer;" onclick="editBooking('${bookingId}')">Edit</div>
                <hr style="margin: 5px 0;">
                <div style="padding: 5px; cursor: pointer; color: red;" onclick="deleteBooking('${bookingId}')">Delete</div>
            `;
            
            document.body.appendChild(contextMenu);
            
            // Remove context menu when clicking elsewhere
            document.addEventListener('click', function() {
                if (contextMenu.parentNode) {
                    contextMenu.parentNode.removeChild(contextMenu);
                }
            }, { once: true });
        }
    });
    </script>
</body>

</html>