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

// Database Connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'inventory_system';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
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

// Initialize messages for display
$success_message = '';
$error_message = '';

// Check for success or error messages from other pages (e.g., save-invoice.php, delete-invoice.php)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear it so it doesn't show again on refresh
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear it
}


// Query 1: Fetch Customer Purchase Orders (formerly Invoices)
$sql_purchase_orders = "
    SELECT
        i.id,
        i.invoice_number AS doc_number,
        i.date_issued AS doc_date,
        i.status,
        (SUM(ii.quantity * ii.price_at_purchase) * 1.06) AS total_due,
        c.name AS party_name
    FROM invoices AS i
    JOIN customers AS c ON i.customer_id = c.id
    LEFT JOIN invoice_items AS ii ON i.id = ii.invoice_id
    GROUP BY i.id
    ORDER BY doc_date DESC
";
$result_purchase_orders = $conn->query($sql_purchase_orders);
if (!$result_purchase_orders) {
    die("SQL Error in Purchase Orders Query: " . $conn->error);
}

// Query 2: Fetch Supplier Invoices (formerly Bills)
// This query now joins with the customers table to get the supplier name
$sql_supplier_invoices = "
    SELECT
        b.id,
        b.bill_number AS doc_number,
        b.date_received AS doc_date,
        b.total_due,
        b.status,
        c.name AS party_name
    FROM supplier_bills AS b
    JOIN customers AS c ON b.supplier_id = c.id
    ORDER BY doc_date DESC
";
$result_supplier_invoices = $conn->query($sql_supplier_invoices);
if (!$result_supplier_invoices) {
    die("SQL Error in Supplier Invoices Query: " . $conn->error);
}

function format_rm_display($amount) {
    return 'RM ' . number_format((float)$amount, 2, '.', ',');
}
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Orders & Billing - Inventomo</title>

    <meta name="description" content="Orders and Billing Management System" />

    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

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

    .card-header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #d9dee3;
        background-color: #f8f9fa;
        border-radius: 0.5rem 0.5rem 0 0;
    }

    .card-header-flex h5 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #566a7f;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Original action button style for consistency */
    .action-button {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        text-decoration: none;
        color: white;
        background-color: #696cff; /* Default primary color */
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: none;
        cursor: pointer;
    }

    .action-button:hover {
        background-color: #5f63f2;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(105, 108, 255, 0.2);
    }

    .action-button.btn-success { background-color: #28a745; }
    .action-button.btn-success:hover { background-color: #218838; box-shadow: 0 44px 8px rgba(40, 167, 69, 0.2); }
    .action-button.btn-info { background-color: #17a2b8; }
    .action-button.btn-info:hover { background-color: #138496; box-shadow: 0 4px 8px rgba(23, 162, 184, 0.2); }
    .action-button.btn-warning { background-color: #ffc107; color: #212529; }
    .action-button.btn-warning:hover { background-color: #e0a800; color: #212529; box-shadow: 0 4px 8px rgba(255, 193, 7, 0.2); }
    .action-button.btn-danger { background-color: #dc3545; }
    .action-button.btn-danger:hover { background-color: #c82333; box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2); }

    /* Specific styles for dropdown buttons */
    .dropdown-toggle.action-button {
        padding-right: 1.5rem; /* Space for caret */
    }

    .dropdown-menu .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        color: #566a7f;
    }

    .dropdown-menu .dropdown-item:hover {
        background-color: #f5f5f9;
        color: #696cff; /* Example hover color */
    }

    .dropdown-menu .dropdown-item.text-success:hover { color: #28a745; }
    .dropdown-menu .dropdown-item.text-danger:hover { color: #dc3545; }
    .dropdown-menu .dropdown-item.text-warning:hover { color: #ffc107; }

    .actions-cell {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .card {
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        border: none;
        margin-bottom: 1.5rem;
        overflow: hidden;
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

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-paid { background-color: #d4edda; color: #155724; }
    .status-pending { background-color: #fff3cd; color: #856404; }
    .status-overdue { background-color: #f8d7da; color: #721c24; }
    .status-draft { background-color: #e2e3e5; color: #383d41; }
    .status-approved { background-color: #d4edda; color: #155724; } /* New status */
    .status-rejected { background-color: #f8d7da; color: #721c24; } /* New status */
    .status-kiv { background-color: #fff3cd; color: #856404; } /* New status */


    .amount-display { font-weight: 600; color: #28a745; }
    .doc-number { font-weight: 600; color: #696cff; }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #6c757d;
    }
    .empty-state i { font-size: 3rem; color: #d9dee3; margin-bottom: 1rem; }

    /* Custom popup for success/error messages */
    .custom-popup {
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: #28a745; /* Green for success */
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 1100;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.5s, visibility 0.5s, transform 0.5s;
        transform: translateY(-20px);
        font-size: 1rem;
        font-weight: 500;
    }
    .custom-popup.error {
        background-color: #dc3545; /* Red for error */
    }
    .custom-popup.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    </style>

    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
</head>

<body>
    <div id="statusPopup" class="custom-popup">
        <i class='bx bx-info-circle me-2'></i> <span id="statusMessage"></span>
    </div>

    <div class="modal fade" id="viewDocumentModal" tabindex="-1" aria-labelledby="viewDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDocumentModalLabel">Document Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="documentModalBody">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
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
                    <li class="menu-item">
                        <a href="index.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Pages</span></li>
                    <li class="menu-item"><a href="inventory.php" class="menu-link"><i class="menu-icon tf-icons bx bx-card"></i><div>Inventory</div></a></li>
                    <li class="menu-item"><a href="stock-management.php" class="menu-link"><i class="menu-icon tf-icons bx bx-list-plus"></i><div>Stock Management</div></a></li>
                    <li class="menu-item"><a href="customer-supplier.php" class="menu-link"><i class="menu-icon tf-icons bx bxs-user-detail"></i><div>Supplier & Customer</div></a></li>
                    <li class="menu-item active"><a href="order-billing.php" class="menu-link"><i class="menu-icon tf-icons bx bx-cart"></i><div>Order & Billing</div></a></li>
                    <li class="menu-item"><a href="report.php" class="menu-link"><i class="menu-icon tf-icons bx bxs-report"></i><div>Report</div></a></li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Account</span></li>
                    <li class="menu-item"><a href="user.php" class="menu-link"><i class="menu-icon tf-icons bx bx-user"></i><div>User Management</div></a></li>
                </ul>
            </aside>
            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online"><img src="assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" /></div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?php echo $profile_link; ?>"><i class="bx bx-user me-2"></i><span class="align-middle">My Profile</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Settings</span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="bx bx-power-off me-2"></i><span class="align-middle">Log Out</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="content-header">
                            <h4 class="page-title"><i class="bx bx-receipt"></i>Orders & Billing Management</h4>
                        </div>

                        <div class="card">
                            <div class="card-header-flex">
                                <h5><i class="bx bx-shopping-bag"></i>Customer Purchase Orders</h5>
                                <a href="create-purchase-order.php" class="action-button btn-success"><i class="bx bx-plus"></i>Create</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>PO #</th>
                                            <th>Customer Name</th>
                                            <th>Date Issued</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result_purchase_orders && $result_purchase_orders->num_rows > 0): ?>
                                            <?php while ($row = $result_purchase_orders->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="doc-number"><?= htmlspecialchars($row['doc_number']) ?></span></td>
                                                <td><?= htmlspecialchars($row['party_name']) ?></td>
                                                <td><?= date("M d, Y", strtotime($row['doc_date'])) ?></td>
                                                <td><span class="amount-display"><?= format_rm_display($row['total_due']) ?></span></td>
                                                <td>
                                                    <?php
                                                        $status = strtolower($row['status'] ?? 'pending');
                                                        $badge_class = 'status-pending';
                                                        if ($status == 'paid') $badge_class = 'status-paid';
                                                        elseif ($status == 'overdue') $badge_class = 'status-overdue';
                                                        elseif ($status == 'draft') $badge_class = 'status-draft';
                                                        elseif ($status == 'approved') $badge_class = 'status-approved';
                                                        elseif ($status == 'rejected') $badge_class = 'status-rejected';
                                                        elseif ($status == 'kiv') $badge_class = 'status-kiv';
                                                    ?>
                                                    <span class="status-badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span>
                                                </td>
                                                <td class="actions-cell">
                                                    <button type="button" class="action-button btn-info view-document-btn"
                                                        data-bs-toggle="modal" data-bs-target="#viewDocumentModal"
                                                        data-document-url="view-purchase-order.php?id=<?= $row['id'] ?>&modal=true">
                                                        <i class="bx bx-show"></i>View
                                                    </button>

                                                    <div class="dropdown">
                                                        <button class="action-button btn-warning dropdown-toggle" type="button" id="dropdownEditPurchaseOrder<?= $row['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bx bx-edit"></i>Edit
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdownEditPurchaseOrder<?= $row['id'] ?>">
                                                            <?php if (strtolower($row['status']) === 'pending' || strtolower($row['status']) === 'draft' || strtolower($row['status']) === 'kiv'): ?>
                                                                <li><a class="dropdown-item text-success" href="update-purchase-order-status.php?id=<?= $row['id'] ?>&status=approved"><i class="bx bx-check me-2"></i>Approve</a></li>
                                                                <li><a class="dropdown-item text-danger" href="update-purchase-order-status.php?id=<?= $row['id'] ?>&status=rejected"><i class="bx bx-x me-2"></i>Reject</a></li>
                                                                <li><a class="dropdown-item text-warning" href="update-purchase-order-status.php?id=<?= $row['id'] ?>&status=kiv"><i class="bx bx-bell me-2"></i>KIV</a></li>
                                                                <li><hr class="dropdown-divider"></li>
                                                            <?php endif; ?>
                                                            <li><a class="dropdown-item text-danger" href="delete-po.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete Purchase Order #<?= htmlspecialchars($row['doc_number']) ?>?');"><i class="bx bx-trash me-2"></i>Delete</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="empty-state">
                                                    <i class="bx bx-receipt"></i>
                                                    <h6>No Purchase Orders Found</h6>
                                                    <a href="create-purchase-order.php" class="action-button btn-success"><i class="bx bx-plus"></i>Create First PO</a>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header-flex">
                                <h5><i class="bx bx-file-blank"></i>Supplier Invoices</h5>
                                <a href="create-invoice.php" class="action-button btn-success"><i class="bx bx-plus"></i>Create</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Supplier Name</th>
                                            <th>Date Received</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result_supplier_invoices && $result_supplier_invoices->num_rows > 0): ?>
                                            <?php while ($row = $result_supplier_invoices->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="doc-number"><?= htmlspecialchars($row['doc_number']) ?></span></td>
                                                <td><?= htmlspecialchars($row['party_name']) ?></td>
                                                <td><?= date("M d, Y", strtotime($row['doc_date'])) ?></td>
                                                <td><span class="amount-display"><?= format_rm_display($row['total_due']) ?></span></td>
                                                <td>
                                                    <?php
                                                        $status = strtolower($row['status'] ?? 'pending');
                                                        $badge_class = 'status-pending';
                                                        if ($status == 'paid') $badge_class = 'status-paid';
                                                        elseif ($status == 'overdue') $badge_class = 'status-overdue';
                                                        elseif ($status == 'draft') $badge_class = 'status-draft';
                                                        elseif ($status == 'approved') $badge_class = 'status-approved';
                                                        elseif ($status == 'rejected') $badge_class = 'status-rejected';
                                                        elseif ($status == 'kiv') $badge_class = 'status-kiv';
                                                    ?>
                                                    <span class="status-badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span>
                                                </td>
                                                <td class="actions-cell">
                                                    <button type="button" class="action-button btn-info view-document-btn"
                                                        data-bs-toggle="modal" data-bs-target="#viewDocumentModal"
                                                        data-document-url="view-supplier-invoice.php?id=<?= $row['id'] ?>&modal=true">
                                                        <i class="bx bx-show"></i>View
                                                    </button>

                                                    <div class="dropdown">
                                                        <button class="action-button btn-warning dropdown-toggle" type="button" id="dropdownEditSupplierInvoice<?= $row['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bx bx-edit"></i>Edit
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdownEditSupplierInvoice<?= $row['id'] ?>">
                                                            <?php if (strtolower($row['status']) === 'pending' || strtolower($row['status']) === 'draft' || strtolower($row['status']) === 'kiv'): ?>
                                                                <li><a class="dropdown-item text-success" href="update-supplier-invoice-status.php?id=<?= $row['id'] ?>&status=approved"><i class="bx bx-check me-2"></i>Approve</a></li>
                                                                <li><a class="dropdown-item text-danger" href="update-supplier-invoice-status.php?id=<?= $row['id'] ?>&status=rejected"><i class="bx bx-x me-2"></i>Reject</a></li>
                                                                <li><a class="dropdown-item text-warning" href="update-supplier-invoice-status.php?id=<?= $row['id'] ?>&status=kiv"><i class="bx bx-bell me-2"></i>KIV</a></li>
                                                                <li><hr class="dropdown-divider"></li>
                                                            <?php endif; ?>
                                                            <li><a class="dropdown-item text-danger" href="delete-invoice.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete Supplier Invoice #<?= htmlspecialchars($row['doc_number']) ?>?');"><i class="bx bx-trash me-2"></i>Delete</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="empty-state">
                                                    <i class="bx bx-file-blank"></i>
                                                    <h6>No Supplier Invoices Found</h6>
                                                    <a href="create-invoice.php" class="action-button btn-success"><i class="bx bx-plus"></i>Create First Bill</a>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">© <script>document.write(new Date().getFullYear());</script> Inventomo. All rights reserved.</div>
                        </div>
                    </footer>
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusPopup = document.getElementById('statusPopup');
            const statusMessageSpan = document.getElementById('statusMessage');

            // Check for PHP-generated success message
            <?php if (!empty($success_message)): ?>
                statusMessageSpan.textContent = '<?php echo $success_message; ?>';
                statusPopup.classList.add('show');
                statusPopup.classList.remove('error'); // Ensure it's not red
                statusPopup.querySelector('i').className = 'bx bx-check-circle me-2'; // Check icon
                setTimeout(() => {
                    statusPopup.classList.remove('show');
                }, 5000); // Popup disappears after 5 seconds
            <?php endif; ?>

            // Check for PHP-generated error message
            <?php if (!empty($error_message)): ?>
                statusMessageSpan.textContent = '<?php echo $error_message; ?>';
                statusPopup.classList.add('show', 'error'); // Add error class for red background
                statusPopup.querySelector('i').className = 'bx bx-error-circle me-2'; // Error icon
                setTimeout(() => {
                    statusPopup.classList.remove('show');
                }, 5000); // Popup disappears after 5 seconds
            <?php endif; ?>

            // JavaScript for loading content into the modal
            const viewDocumentModal = document.getElementById('viewDocumentModal');
            viewDocumentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; // Button that triggered the modal
                const documentUrl = button.getAttribute('data-document-url'); // Extract info from data-* attributes
                const modalBody = viewDocumentModal.querySelector('#documentModalBody');

                // Clear previous content and show a loading message
                modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading document...</p></div>';

                // Fetch content from the document URL
                fetch(documentUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text();
                    })
                    .then(html => {
                        modalBody.innerHTML = html; // Inject the fetched content into the modal body
                    })
                    .catch(error => {
                        console.error('Error loading document:', error);
                        modalBody.innerHTML = '<p class="text-danger">Failed to load document. Please try again.</p>';
                    });
            });

            // Optional: Clear modal content when hidden to ensure fresh load next time
            viewDocumentModal.addEventListener('hidden.bs.modal', function () {
                const modalBody = viewDocumentModal.querySelector('#documentModalBody');
                modalBody.innerHTML = ''; // Clear content when modal is closed
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>