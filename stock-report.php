<?php
// Start session for user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-login-basic.html");
    exit();
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "inventory_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

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

// --- Fetch all products for the item dropdown ---
$products_list = [];
$sql_products = "SELECT itemID, product_name FROM inventory_item ORDER BY product_name ASC";
$result_products = $conn->query($sql_products);
if ($result_products->num_rows > 0) {
    while($row = $result_products->fetch_assoc()) {
        $products_list[] = $row;
    }
}

// --- Generate last 12 months for the month dropdown ---
$months_list = [];
for ($i = 0; $i < 12; $i++) {
    $month_year = date('Y-m', strtotime("-$i month"));
    $months_list[$month_year] = date('F Y', strtotime("-$i month"));
}

// --- Get selected filters from GET request or set defaults ---
$selected_item_id = isset($_GET['item_id']) && is_numeric($_GET['item_id']) ? intval($_GET['item_id']) : 'all';
$selected_month_filter = isset($_GET['report_month']) ? $_GET['report_month'] : date('Y-m');

// Validate selected month format
if (!preg_match('/^\d{4}-\d{2}$/', $selected_month_filter)) {
    $selected_month_filter = date('Y-m'); // Default to current month if invalid
}

// Calculate start and end date for the selected month
$start_date_obj = new DateTime($selected_month_filter . '-01');
$end_date_obj = new DateTime($selected_month_filter . '-01');
$end_date_obj->modify('last day of this month');
$end_date_obj->setTime(23, 59, 59); // Set to end of day
$start_date = $start_date_obj->format('Y-m-d H:i:s');
$end_date = $end_date_obj->format('Y-m-d H:i:s');

// --- Calculate summary statistics ---
$total_stock_in = 0;
$total_stock_out = 0;
$net_change = 0;
$active_days = 0;

// Get stock in summary
$sql_summary_in = "SELECT COALESCE(SUM(quantity_added), 0) as total_in, COUNT(DISTINCT DATE(transaction_date)) as days_in
                   FROM stock_in_history
                   WHERE transaction_date BETWEEN ? AND ?";
if ($selected_item_id != 'all') {
    $sql_summary_in .= " AND product_id = ?";
}

$stmt_summary_in = $conn->prepare($sql_summary_in);
if ($selected_item_id != 'all') {
    $stmt_summary_in->bind_param("ssi", $start_date, $end_date, $selected_item_id);
} else {
    $stmt_summary_in->bind_param("ss", $start_date, $end_date);
}
$stmt_summary_in->execute();
$result_summary_in = $stmt_summary_in->get_result();
if ($row = $result_summary_in->fetch_assoc()) {
    $total_stock_in = (int)$row['total_in'];
    $active_days_in = (int)$row['days_in'];
}
$stmt_summary_in->close();

// Get stock out summary
$sql_summary_out = "SELECT COALESCE(SUM(quantity_deducted), 0) as total_out, COUNT(DISTINCT DATE(transaction_date)) as days_out
                    FROM stock_out_history
                    WHERE transaction_date BETWEEN ? AND ?";
if ($selected_item_id != 'all') {
    $sql_summary_out .= " AND product_id = ?";
}

$stmt_summary_out = $conn->prepare($sql_summary_out);
if ($selected_item_id != 'all') {
    $stmt_summary_out->bind_param("ssi", $start_date, $end_date, $selected_item_id);
} else {
    $stmt_summary_out->bind_param("ss", $start_date, $end_date);
}
$stmt_summary_out->execute();
$result_summary_out = $stmt_summary_out->get_result();
if ($row = $result_summary_out->fetch_assoc()) {
    $total_stock_out = (int)$row['total_out'];
    $active_days_out = (int)$row['days_out'];
}
$stmt_summary_out->close();

$net_change = $total_stock_in - $total_stock_out;
$active_days = max($active_days_in, $active_days_out);

// --- Fetch Stock In Data for the selected month and item ---
$stock_in_data = [];
$sql_stock_in = "SELECT DATE(transaction_date) as report_date, SUM(quantity_added) as total_quantity
                 FROM stock_in_history
                 WHERE transaction_date BETWEEN ? AND ?";
if ($selected_item_id != 'all') {
    $sql_stock_in .= " AND product_id = ?";
}
$sql_stock_in .= " GROUP BY report_date ORDER BY report_date ASC";

$stmt_in = $conn->prepare($sql_stock_in);
if ($selected_item_id != 'all') {
    $stmt_in->bind_param("ssi", $start_date, $end_date, $selected_item_id);
} else {
    $stmt_in->bind_param("ss", $start_date, $end_date);
}
$stmt_in->execute();
$result_in = $stmt_in->get_result();
while ($row = $result_in->fetch_assoc()) {
    $stock_in_data[$row['report_date']] = (int)$row['total_quantity'];
}
$stmt_in->close();

// --- Fetch Stock Out Data for the selected month and item ---
$stock_out_data = [];
$sql_stock_out = "SELECT DATE(transaction_date) as report_date, SUM(quantity_deducted) as total_quantity
                  FROM stock_out_history
                  WHERE transaction_date BETWEEN ? AND ?";
if ($selected_item_id != 'all') {
    $sql_stock_out .= " AND product_id = ?";
}
$sql_stock_out .= " GROUP BY report_date ORDER BY report_date ASC";

$stmt_out = $conn->prepare($sql_stock_out);
if ($selected_item_id != 'all') {
    $stmt_out->bind_param("ssi", $start_date, $end_date, $selected_item_id);
} else {
    $stmt_out->bind_param("ss", $start_date, $end_date);
}
$stmt_out->execute();
$result_out = $stmt_out->get_result();
while ($row = $result_out->fetch_assoc()) {
    $stock_out_data[$row['report_date']] = (int)$row['total_quantity'];
}
$stmt_out->close();

// --- Prepare Data for Chart.js ---
$labels = []; // Dates for the X-axis
$quantities_in = []; // Stock In quantities
$quantities_out = []; // Stock Out quantities

// Generate all days in the selected month to ensure continuous labels
$current_date_loop = clone $start_date_obj; // Start from the first day of the selected month
while ($current_date_loop <= $end_date_obj) {
    $date_str = $current_date_loop->format('Y-m-d');
    $labels[] = $current_date_loop->format('M d'); // Format for chart label (e.g., "Jun 14")
    $quantities_in[] = $stock_in_data[$date_str] ?? 0; // Use 0 if no data for that date
    $quantities_out[] = $stock_out_data[$date_str] ?? 0; // Use 0 if no data for that date
    $current_date_loop->modify('+1 day');
}

// Encode data to JSON for JavaScript
$chart_data = [
    'labels' => $labels,
    'datasets' => [
        [
            'label' => 'Stock In',
            'backgroundColor' => 'rgba(40, 199, 111, 0.8)',
            'borderColor' => 'rgba(40, 199, 111, 1)',
            'borderWidth' => 2,
            'data' => $quantities_in,
        ],
        [
            'label' => 'Stock Out',
            'backgroundColor' => 'rgba(255, 62, 29, 0.8)',
            'borderColor' => 'rgba(255, 62, 29, 1)',
            'borderWidth' => 2,
            'data' => $quantities_out,
        ],
    ],
];
$chart_data_json = json_encode($chart_data);

// Get selected product name for display
$selected_product_name = 'All Items';
if ($selected_item_id != 'all') {
    foreach ($products_list as $product) {
        if ($product['itemID'] == $selected_item_id) {
            $selected_product_name = $product['product_name'];
            break;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Stock Report - Inventomo</title>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="assets/vendor/css/core.css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="assets/css/demo.css" />
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            border-left: 4px solid #28c76f;
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 25px 0 rgba(0,0,0,0.1);
        }
        .stats-card.negative {
            border-left-color: #ff3e1d;
        }
        .stats-card.neutral {
            border-left-color: #ff9f43;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .filter-card .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: white;
        }
        .form-select, .form-label {
            color: #333;
        }
        .filter-card .form-select {
            background-color: rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.3);
        }
        .filter-card .form-label {
            color: white;
            font-weight: 500;
        }
        .report-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .export-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            color: white;
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
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search reports..." />
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
                        <!-- Report Header -->
                        <div class="report-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-2">Stock Movement Report</h2>
                                    <p class="mb-0">Comprehensive analysis of stock in and out transactions</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn export-btn" onclick="exportChart()">
                                        <i class="bx bx-download me-1"></i> Export Chart
                                    </button>
                                    <button type="button" class="btn btn-outline-light" onclick="window.print()">
                                        <i class="bx bx-printer me-1"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Card -->
                        <div class="card filter-card mb-4">
                            <h5 class="card-header">
                                <i class="bx bx-filter-alt me-2"></i>Report Filters
                            </h5>
                            <div class="card-body">
                                <form method="GET" action="stock-report.php" class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label for="item_id" class="form-label">Select Item</label>
                                        <select class="form-select" id="item_id" name="item_id">
                                            <option value="all">All Items</option>
                                            <?php foreach ($products_list as $product): ?>
                                            <option value="<?php echo htmlspecialchars($product['itemID']); ?>"
                                                <?php echo ($selected_item_id == $product['itemID']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="report_month" class="form-label">Select Month</label>
                                        <select class="form-select" id="report_month" name="report_month">
                                            <?php foreach ($months_list as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>"
                                                <?php echo ($selected_month_filter == $value) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-light me-2">
                                            <i class="bx bx-search-alt me-1"></i>Generate Report
                                        </button>
                                        <a href="stock-management.php" class="btn btn-outline-light">
                                            <i class="bx bx-arrow-back me-1"></i>Back
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stats-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title text-muted mb-1">Total Stock In</h6>
                                                <h3 class="text-success mb-0"><?php echo number_format($total_stock_in); ?></h3>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-success">
                                                    <i class="bx bx-trending-up fs-4"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card negative">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title text-muted mb-1">Total Stock Out</h6>
                                                <h3 class="text-danger mb-0"><?php echo number_format($total_stock_out); ?></h3>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-danger">
                                                    <i class="bx bx-trending-down fs-4"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card <?php echo $net_change >= 0 ? '' : 'negative'; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title text-muted mb-1">Net Change</h6>
                                                <h3 class="<?php echo $net_change >= 0 ? 'text-success' : 'text-danger'; ?> mb-0">
                                                    <?php echo ($net_change >= 0 ? '+' : '') . number_format($net_change); ?>
                                                </h3>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-<?php echo $net_change >= 0 ? 'success' : 'danger'; ?>">
                                                    <i class="bx bx-<?php echo $net_change >= 0 ? 'up' : 'down'; ?>-arrow-alt fs-4"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card neutral">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title text-muted mb-1">Active Days</h6>
                                                <h3 class="text-warning mb-0"><?php echo number_format($active_days); ?></h3>
                                            </div>
                                            <div class="avatar">
                                                <span class="avatar-initial rounded bg-label-warning">
                                                    <i class="bx bx-calendar fs-4"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Card -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">Daily Stock Movement - <?php echo htmlspecialchars($months_list[$selected_month_filter] ?? date('F Y')); ?></h5>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($selected_product_name); ?> |
                                        <?php echo htmlspecialchars($start_date_obj->format('M d, Y')); ?> to
                                        <?php echo htmlspecialchars($end_date_obj->format('M d, Y')); ?>
                                    </small>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeChartType('bar')">
                                            <i class="bx bx-bar-chart me-2"></i>Bar Chart</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeChartType('line')">
                                            <i class="bx bx-line-chart me-2"></i>Line Chart</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="toggleDataLabels()">
                                            <i class="bx bx-label me-2"></i>Toggle Labels</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="downloadChart()">
                                            <i class="bx bx-download me-2"></i>Download PNG</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <div>
                                        <strong>Analysis:</strong>
                                        <?php if ($net_change > 0): ?>
                                            Positive stock movement detected. Net gain of <?php echo number_format($net_change); ?> units this period.
                                        <?php elseif ($net_change < 0): ?>
                                            Negative stock movement detected. Net loss of <?php echo number_format(abs($net_change)); ?> units this period.
                                        <?php else: ?>
                                            Balanced stock movement. No net change in inventory this period.
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="chart-container">
                                    <canvas id="stockMovementChart"></canvas>
                                </div>

                                <!-- Chart Legend & Summary -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-2">
                                            <div style="width: 20px; height: 20px; background-color: rgba(40, 199, 111, 0.8); margin-right: 10px; border-radius: 3px;"></div>
                                            <span>Stock In - Total: <?php echo number_format($total_stock_in); ?> units</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div style="width: 20px; height: 20px; background-color: rgba(255, 62, 29, 0.8); margin-right: 10px; border-radius: 3px;"></div>
                                            <span>Stock Out - Total: <?php echo number_format($total_stock_out); ?> units</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <small class="text-muted">
                                            Report generated by <?php echo htmlspecialchars($current_user_name); ?> on <?php echo date('M d, Y \a\t g:i A'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Insights -->
                        <?php if ($active_days > 0): ?>
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bx bx-trending-up me-2"></i>Daily Averages</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Avg. Stock In per Day:</span>
                                            <strong class="text-success"><?php echo number_format($total_stock_in / $active_days, 1); ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Avg. Stock Out per Day:</span>
                                            <strong class="text-danger"><?php echo number_format($total_stock_out / $active_days, 1); ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Avg. Net Change per Day:</span>
                                            <strong class="<?php echo $net_change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo ($net_change >= 0 ? '+' : '') . number_format($net_change / $active_days, 1); ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bx bx-bar-chart-alt-2 me-2"></i>Peak Activity</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $max_in_day = max($quantities_in);
                                        $max_out_day = max($quantities_out);
                                        $max_in_index = array_search($max_in_day, $quantities_in);
                                        $max_out_index = array_search($max_out_day, $quantities_out);
                                        ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Highest Stock In:</span>
                                            <strong class="text-success">
                                                <?php echo number_format($max_in_day); ?>
                                                <small class="text-muted">(<?php echo $labels[$max_in_index]; ?>)</small>
                                            </strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Highest Stock Out:</span>
                                            <strong class="text-danger">
                                                <?php echo number_format($max_out_day); ?>
                                                <small class="text-muted">(<?php echo $labels[$max_out_index]; ?>)</small>
                                            </strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Activity Rate:</span>
                                            <strong class="text-info">
                                                <?php echo number_format(($active_days / $end_date_obj->format('d')) * 100, 1); ?>%
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
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
    let stockChart;
    let currentChartType = 'bar';
    let showDataLabels = false;

    document.addEventListener('DOMContentLoaded', function() {
        // PHP data embedded directly into JavaScript
        var chartData = <?php echo $chart_data_json; ?>;

        initializeChart(chartData);

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportChart();
            }
        });
    });

    function initializeChart(chartData) {
        var ctx = document.getElementById('stockMovementChart').getContext('2d');

        // Destroy existing chart if it exists
        if (stockChart) {
            stockChart.destroy();
        }

        stockChart = new Chart(ctx, {
            type: currentChartType,
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Stock In vs. Stock Out Movement',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: 20
                    },
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString() + ' units';
                            },
                            footer: function(tooltipItems) {
                                let total = 0;
                                tooltipItems.forEach(function(tooltipItem) {
                                    total += tooltipItem.parsed.y;
                                });
                                return 'Total Activity: ' + total.toLocaleString() + ' units';
                            }
                        }
                    },
                    datalabels: {
                        display: showDataLabels,
                        anchor: 'end',
                        align: 'top',
                        formatter: function(value) {
                            return value > 0 ? value : '';
                        },
                        font: {
                            size: 10,
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Date',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantity',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                },
                animation: {
                    duration: 1500,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }

    function changeChartType(type) {
        currentChartType = type;
        var chartData = <?php echo $chart_data_json; ?>;

        // Update chart type specific styling
        if (type === 'line') {
            chartData.datasets[0].fill = false;
            chartData.datasets[1].fill = false;
            chartData.datasets[0].tension = 0.4;
            chartData.datasets[1].tension = 0.4;
            chartData.datasets[0].pointRadius = 4;
            chartData.datasets[1].pointRadius = 4;
            chartData.datasets[0].pointHoverRadius = 6;
            chartData.datasets[1].pointHoverRadius = 6;
        } else {
            chartData.datasets[0].fill = true;
            chartData.datasets[1].fill = true;
            delete chartData.datasets[0].tension;
            delete chartData.datasets[1].tension;
            delete chartData.datasets[0].pointRadius;
            delete chartData.datasets[1].pointRadius;
        }

        initializeChart(chartData);
    }

    function toggleDataLabels() {
        showDataLabels = !showDataLabels;
        var chartData = <?php echo $chart_data_json; ?>;
        initializeChart(chartData);
    }

    function exportChart() {
        if (stockChart) {
            const link = document.createElement('a');
            link.download = 'stock-movement-report-<?php echo $selected_month_filter; ?>.png';
            link.href = stockChart.toBase64Image('image/png', 1.0);
            link.click();
        }
    }

    function downloadChart() {
        exportChart();
    }

    // Print styles
    window.addEventListener('beforeprint', function() {
        document.body.classList.add('printing');
    });

    window.addEventListener('afterprint', function() {
        document.body.classList.remove('printing');
    });
    </script>

    <style>
    @media print {
        .layout-menu, .layout-navbar, .export-btn, .dropdown {
            display: none !important;
        }
        .content-wrapper {
            margin: 0 !important;
            padding: 20px !important;
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
        .report-header {
            background: #f8f9fa !important;
            color: #333 !important;
            -webkit-print-color-adjust: exact;
        }
        .chart-container {
            height: 300px !important;
        }
    }

    .printing .layout-menu,
    .printing .layout-navbar {
        display: none;
    }

    .printing .layout-page {
        margin: 0;
    }
    </style>
</body>

</html>
