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

// Sample order data - replace with actual database queries
$sample_orders = [
    [
        'id' => '#1001',
        'customer' => 'Aminah Binti Ahmad',
        'items' => 3,
        'total' => 1323.00,
        'status' => 'delivered',
        'date' => '2024-12-15'
    ],
    [
        'id' => '#1002',
        'customer' => 'Muhammad Ali',
        'items' => 5,
        'total' => 2456.50,
        'status' => 'cancelled',
        'date' => '2024-12-14'
    ],
    [
        'id' => '#1003',
        'customer' => 'Siti Nurhaliza',
        'items' => 2,
        'total' => 875.75,
        'status' => 'pending',
        'date' => '2024-12-13'
    ],
    [
        'id' => '#1004',
        'customer' => 'Ahmad Rahman',
        'items' => 4,
        'total' => 1634.25,
        'status' => 'shipped',
        'date' => '2024-12-12'
    ]
];

// Calculate statistics
$total_orders = count($sample_orders);
$delivered_orders = count(array_filter($sample_orders, fn($order) => $order['status'] === 'delivered'));
$pending_orders = count(array_filter($sample_orders, fn($order) => $order['status'] === 'pending'));
$total_value = array_sum(array_column($sample_orders, 'total'));
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Order Item - Inventomo</title>

    <meta name="description" content="Order Management System" />

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
    .page-title {
        font-size: 28px;
        font-weight: 600;
        margin-bottom: 8px;
        color: #1e293b;
    }

    .page-subtitle {
        color: #64748b;
        margin-bottom: 32px;
    }

    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .card-header {
        padding: 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-title {
        font-size: 20px;
        font-weight: 600;
        color: #1e293b;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }

    .btn-primary {
        background: #696cff;
        color: white;
    }

    .btn-primary:hover {
        background: #5f63f2;
    }

    .table-container {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th {
        background: #f8fafc;
        padding: 16px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        font-size: 14px;
        border-bottom: 1px solid #e2e8f0;
    }

    .table td {
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }

    .table tr:hover {
        background: #f8fafc;
    }

    .order-id {
        font-weight: 600;
        color: #1e293b;
    }

    .customer-name {
        color: #475569;
    }

    .item-count {
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        display: inline-block;
    }

    .total-amount {
        font-weight: 600;
        color: #059669;
    }

    .status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .status-delivered {
        background: #d1fae5;
        color: #065f46;
    }

    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-shipped {
        background: #dbeafe;
        color: #1e40af;
    }

    .actions {
        display: flex;
        gap: 8px;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        background: #f1f5f9;
        color: #64748b;
    }

    .action-btn:hover {
        background: #e2e8f0;
        color: #374151;
    }

    .action-btn.edit:hover {
        background: #dbeafe;
        color: #2563eb;
    }

    .action-btn.delete:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    .search-container {
        margin-bottom: 24px;
    }

    .search-box {
        width: 100%;
        max-width: 400px;
        padding: 12px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 16px;
        background: white;
        transition: border-color 0.2s;
    }

    .search-box:focus {
        outline: none;
        border-color: #696cff;
        box-shadow: 0 0 0 3px rgba(105, 108, 255, 0.1);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #1e293b;
    }

    .stat-label {
        color: #64748b;
        font-size: 14px;
        margin-top: 4px;
    }

    @media (max-width: 768px) {
        .card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .table th,
        .table td {
            padding: 12px 8px;
            font-size: 13px;
        }

        .stats {
            grid-template-columns: 1fr;
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
                            <li class="menu-item active">
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
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Settings</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <span class="d-flex align-items-center align-middle">
                                                <i class="flex-shrink-0 bx bx-credit-card me-2"></i>
                                                <span class="flex-grow-1 align-middle">Billing</span>
                                                <span
                                                    class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                                            </span>
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
                        <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Stock /</span> Order Item</h4>

                        <!-- Statistics Cards -->
                        <div class="stats">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $total_orders; ?></div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $delivered_orders; ?></div>
                                <div class="stat-label">Delivered</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $pending_orders; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">RM<?php echo number_format($total_value, 0); ?></div>
                                <div class="stat-label">Total Value</div>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="search-container">
                            <input type="text" class="search-box" placeholder="Search orders by ID or customer name..." id="orderSearch">
                        </div>

                        <!-- Orders Table -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">Recent Orders</h2>
                                <button class="btn btn-primary" onclick="createNewOrder()">
                                    <i class="bx bx-plus"></i>
                                    New Order
                                </button>
                            </div>
                            <div class="table-container">
                                <table class="table" id="ordersTable">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sample_orders as $order): ?>
                                        <tr>
                                            <td class="order-id"><?php echo htmlspecialchars($order['id']); ?></td>
                                            <td class="customer-name"><?php echo htmlspecialchars($order['customer']); ?></td>
                                            <td><span class="item-count"><?php echo $order['items']; ?> items</span></td>
                                            <td class="total-amount">RM<?php echo number_format($order['total'], 2); ?></td>
                                            <td><span class="status status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($order['date'])); ?></td>
                                            <td>
                                                <div class="actions">
                                                    <button class="action-btn edit" title="Edit" onclick="editOrder('<?php echo $order['id']; ?>')">
                                                        <i class="bx bx-edit-alt"></i>
                                                    </button>
                                                    <button class="action-btn delete" title="Delete" onclick="deleteOrder('<?php echo $order['id']; ?>')">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </div>
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
    document.getElementById('orderSearch').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('#ordersTable tbody tr');
        
        tableRows.forEach(row => {
            const orderID = row.querySelector('.order-id').textContent.toLowerCase();
            const customerName = row.querySelector('.customer-name').textContent.toLowerCase();
            
            if (orderID.includes(searchTerm) || customerName.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Order management functions
    function createNewOrder() {
        // Redirect to create order page or open modal
        alert('Create New Order functionality would be implemented here.\nThis would typically redirect to a form page or open a modal.');
        // window.location.href = 'create-order.php';
    }

    function editOrder(orderId) {
        alert('Edit Order ' + orderId + ' functionality would be implemented here.\nThis would typically redirect to an edit form.');
        // window.location.href = 'edit-order.php?id=' + orderId;
    }

    function deleteOrder(orderId) {
        if (confirm('Are you sure you want to delete order ' + orderId + '? This action cannot be undone.')) {
            alert('Delete Order ' + orderId + ' functionality would be implemented here.\nThis would send a request to delete the order from the database.');
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
        // You can add AJAX calls here to refresh order data
        console.log('Order data could be refreshed here');
    }, 300000); // 5 minutes

    // Filter by status functionality
    function filterByStatus(status) {
        const tableRows = document.querySelectorAll('#ordersTable tbody tr');
        
        tableRows.forEach(row => {
            const orderStatus = row.querySelector('.status').textContent.toLowerCase().trim();
            
            if (status === 'all' || orderStatus === status.toLowerCase()) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Add filter buttons (you can add these to the UI)
    function addStatusFilters() {
        const filterContainer = document.createElement('div');
        filterContainer.innerHTML = `
            <div style="margin-bottom: 20px;">
                <button onclick="filterByStatus('all')" class="btn btn-outline-secondary btn-sm me-2">All</button>
                <button onclick="filterByStatus('pending')" class="btn btn-outline-warning btn-sm me-2">Pending</button>
                <button onclick="filterByStatus('shipped')" class="btn btn-outline-info btn-sm me-2">Shipped</button>
                <button onclick="filterByStatus('delivered')" class="btn btn-outline-success btn-sm me-2">Delivered</button>
                <button onclick="filterByStatus('cancelled')" class="btn btn-outline-danger btn-sm">Cancelled</button>
            </div>
        `;
        
        // Insert before the search container
        const searchContainer = document.querySelector('.search-container');
        searchContainer.parentNode.insertBefore(filterContainer, searchContainer);
    }

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        // Add status filter buttons
        addStatusFilters();
        
        // Initialize tooltips for action buttons
        const actionButtons = document.querySelectorAll('.action-btn');
        actionButtons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                const title = this.getAttribute('title');
                if (title) {
                    // You can implement custom tooltip here if needed
                }
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N for new order
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                createNewOrder();
            }
            
            // Escape to clear search
            if (e.key === 'Escape') {
                const searchBox = document.getElementById('orderSearch');
                if (searchBox.value) {
                    searchBox.value = '';
                    searchBox.dispatchEvent(new Event('keyup'));
                }
            }
        });

        // Add sorting functionality
        const tableHeaders = document.querySelectorAll('#ordersTable th');
        tableHeaders.forEach((header, index) => {
            if (index < tableHeaders.length - 1) { // Don't add sorting to Actions column
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    sortTable(index);
                });
                
                // Add sort indicator
                header.innerHTML += ' <i class="bx bx-sort" style="opacity: 0.5; font-size: 12px;"></i>';
            }
        });
    });

    // Table sorting functionality
    let sortDirection = {};
    
    function sortTable(columnIndex) {
        const table = document.getElementById('ordersTable');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        // Toggle sort direction
        sortDirection[columnIndex] = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
        
        rows.sort((a, b) => {
            const aVal = a.cells[columnIndex].textContent.trim();
            const bVal = b.cells[columnIndex].textContent.trim();
            
            let comparison = 0;
            
            // Handle different data types
            if (columnIndex === 2) { // Items column
                const aNum = parseInt(aVal);
                const bNum = parseInt(bVal);
                comparison = aNum - bNum;
            } else if (columnIndex === 3) { // Total column
                const aNum = parseFloat(aVal.replace('RM', '').replace(',', ''));
                const bNum = parseFloat(bVal.replace('RM', '').replace(',', ''));
                comparison = aNum - bNum;
            } else if (columnIndex === 5) { // Date column
                const aDate = new Date(aVal);
                const bDate = new Date(bVal);
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

    // Export functionality (placeholder)
    function exportOrders() {
        const orders = [];
        const rows = document.querySelectorAll('#ordersTable tbody tr');
        
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const cells = row.querySelectorAll('td');
                orders.push({
                    id: cells[0].textContent,
                    customer: cells[1].textContent,
                    items: cells[2].textContent,
                    total: cells[3].textContent,
                    status: cells[4].textContent,
                    date: cells[5].textContent
                });
            }
        });
        
        console.log('Exporting orders:', orders);
        alert('Export functionality would be implemented here.\nThis would generate CSV, Excel, or PDF reports.');
    }

    // Status update functionality
    function updateOrderStatus(orderId, newStatus) {
        if (confirm(`Are you sure you want to update order ${orderId} status to ${newStatus}?`)) {
            // Find the row and update the status
            const rows = document.querySelectorAll('#ordersTable tbody tr');
            rows.forEach(row => {
                const orderIdCell = row.querySelector('.order-id');
                if (orderIdCell && orderIdCell.textContent === orderId) {
                    const statusCell = row.querySelector('.status');
                    if (statusCell) {
                        statusCell.className = `status status-${newStatus.toLowerCase()}`;
                        statusCell.textContent = newStatus;
                    }
                }
            });
            
            alert(`Order ${orderId} status updated to ${newStatus}`);
            // Here you would typically send an AJAX request to update the database
        }
    }

    // Bulk operations (for future enhancement)
    function addBulkOperations() {
        // Add checkboxes to each row
        const rows = document.querySelectorAll('#ordersTable tbody tr');
        rows.forEach(row => {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'order-checkbox';
            checkbox.style.marginRight = '10px';
            
            const firstCell = row.querySelector('td');
            firstCell.prepend(checkbox);
        });
        
        // Add bulk action buttons
        const bulkActions = document.createElement('div');
        bulkActions.innerHTML = `
            <div style="margin-bottom: 20px; display: none;" id="bulkActions">
                <button onclick="bulkUpdateStatus('delivered')" class="btn btn-success btn-sm me-2">Mark as Delivered</button>
                <button onclick="bulkUpdateStatus('cancelled')" class="btn btn-danger btn-sm me-2">Cancel Selected</button>
                <button onclick="bulkDelete()" class="btn btn-outline-danger btn-sm">Delete Selected</button>
            </div>
        `;
        
        const searchContainer = document.querySelector('.search-container');
        searchContainer.appendChild(bulkActions);
        
        // Show/hide bulk actions based on selections
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('order-checkbox')) {
                const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
                const bulkActionsDiv = document.getElementById('bulkActions');
                bulkActionsDiv.style.display = checkedBoxes.length > 0 ? 'block' : 'none';
            }
        });
    }

    // Print functionality
    function printOrders() {
        const printWindow = window.open('', '_blank');
        const tableHTML = document.getElementById('ordersTable').outerHTML;
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Orders Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                        .status-delivered { background: #d1fae5; color: #065f46; }
                        .status-cancelled { background: #fee2e2; color: #991b1b; }
                        .status-pending { background: #fef3c7; color: #92400e; }
                        .status-shipped { background: #dbeafe; color: #1e40af; }
                    </style>
                </head>
                <body>
                    <h1>Orders Report</h1>
                    <p>Generated on: ${new Date().toLocaleString()}</p>
                    ${tableHTML.replace(/<button[^>]*>.*?<\/button>/g, '')}
                </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    }
    </script>
</body>

</html>