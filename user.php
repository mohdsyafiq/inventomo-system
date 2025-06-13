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

// Initialize variables before any processing
$Id = '';
$email = '';
$username = '';
$password = '';

// Initialize error and success messages
$error = '';
$success = '';

// Try to establish database connection with error handling
try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }

    // Handle delete operation
    if(isset($_GET['op']) && $_GET['op'] == 'delete'){
        $deleteId = mysqli_real_escape_string($conn, $_GET['Id']);
        $deleteSql = "DELETE FROM user_profiles WHERE Id = '$deleteId'";
        if(mysqli_query($conn, $deleteSql)){
            $success = "User deleted successfully!";
        } else {
            $error = "Error deleting user: " . mysqli_error($conn);
        }
    }

    // Other operations would go here
    if(isset($_GET['op'])){
        $op = $_GET['op'];
    } else {
        $op = "";
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Session check and user profile link logic
// Determine the profile link based on user role and database lookup
$profile_link = "#";
$current_user_name = "User";
$current_user_role = "User";
$current_user_avatar = "1.png";

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
        
        // All users go to user-profile.php with their ID
        $profile_link = "user-profile.php?op=view&Id=" . $user_data['Id'];
    }
}

// Helper function to get avatar background color based on position
function getAvatarColor($position) {
    switch (strtolower($position)) {
        case 'admin':
            return 'primary';
        case 'super-admin':
            return 'danger';
        case 'moderator':
            return 'warning';
        default:
            return 'info';
    }
}

?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>User Management</title>

    <meta name="description" content="Inventory Management System - User Management" />

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

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />

    <!-- Custom CSS -->
    <style>
    .card {
        box-shadow: 0 4px 24px 0 rgba(34, 41, 47, 0.1);
        transition: all 0.3s ease-in-out;
    }

    .card-header {
        padding: 1.5rem;
    }

    .action-btns {
        display: flex;
        gap: 0.5rem;
    }

    .action-btn {
        background-color: transparent;
        border: none;
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 4px;
        transition: all 0.2s;
    }

    .edit-btn:hover {
        color: #696cff;
        background-color: rgba(105, 108, 255, 0.1);
    }

    .delete-btn:hover {
        color: #ff3e1d;
        background-color: rgba(255, 62, 29, 0.1);
    }

    .badge-status {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
        border-radius: 0.25rem;
        font-weight: 500;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: #f0f2f5;
        margin-right: 0.5rem;
    }

    .user-info {
        display: flex;
        align-items: center;
    }

    .page-title {
        font-size: 1.25rem;
        font-weight: 500;
        margin-bottom: 1rem;
    }

    .table th {
        font-weight: 600;
        white-space: nowrap;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }

    .table td {
        vertical-align: middle;
        padding: 0.75rem 1rem;
    }

    .alert {
        border-radius: 0.375rem;
    }

    .alert-dismissible .btn-close {
        padding: 1rem;
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

                    <li class="menu-item active">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div data-i18n="admin">Admin</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
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
                                <input type="text" class="form-control border-0 shadow-none" id="navbar-search"
                                    placeholder="Search..." aria-label="Search..." />
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
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="fw-bold mb-0">
                                <span class="text-muted fw-light">Account /</span> User Management
                            </h4>
                            <button class="btn btn-primary d-flex align-items-center gap-2"
                                onclick="window.location.href='user-profile.php'">
                                <i class="bx bx-plus"></i>
                                <span>Add New User</span>
                            </button>
                        </div>

                        <!-- Display success or error messages -->
                        <?php if(!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible mb-4" role="alert">
                            <div class="d-flex">
                                <i class="bx bx-check-circle me-2 bx-sm"></i>
                                <div>
                                    <?php echo $success; ?>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible mb-4" role="alert">
                            <div class="d-flex">
                                <i class="bx bx-error-circle me-2 bx-sm"></i>
                                <div>
                                    <?php echo $error; ?>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <!-- User List Card -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Users</h5>
                                <p class="card-text text-muted">Manage your system users and their permissions</p>
                            </div>
                            <div class="table-responsive text-nowrap">
                                <table class="table table-hover" id="userTable">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>User</th>
                                            <th>ID</th>
                                            <th>Email</th>
                                            <th>Position</th>
                                            <th>Joined</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-border-bottom-0">
                                        <?php
                                        $sql1 = "SELECT * FROM user_profiles ORDER BY date_join DESC";
                                        $q2 = mysqli_query($conn, $sql1);
                                        $next = 1;
                                        if (mysqli_num_rows($q2) > 0) {
                                            while($r2 = mysqli_fetch_assoc($q2)) {
                                                $id = $r2['Id'];
                                                $username = $r2['username'];
                                                $email = $r2['email'];
                                                $position = $r2['position'];
                                                $date_join = date('M d, Y', strtotime($r2['date_join']));
                                                $full_name = isset($r2['full_name']) ? $r2['full_name'] : $username;
                                                // Default status (you can adjust this based on your database structure)
                                                $status = isset($r2['active']) ? $r2['active'] : '1';
                                                
                                                // Profile picture or default
                                                $profile_pic = isset($r2['profile_picture']) && !empty($r2['profile_picture']) ? 
                                                    'uploads/' . $r2['profile_picture'] : 'assets/img/avatars/default.png';
                                        ?>
                                        <tr>
                                            <td><?php echo $next++; ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <div
                                                        class="user-avatar bg-label-<?php echo getAvatarColor($position); ?>">
                                                        <?php if ($profile_pic === 'assets/img/avatars/default.png'): ?>
                                                        <span
                                                            class="avatar-initial"><?php echo strtoupper(substr($full_name, 0, 1)); ?></span>
                                                        <?php else: ?>
                                                        <img src="<?php echo $profile_pic; ?>" alt="Profile Picture">
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo $full_name; ?></h6>
                                                        <small class="text-muted">@<?php echo $username; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $id; ?></td>
                                            <td><?php echo $email; ?></td>
                                            <td>
                                                <span class="text-capitalize"><?php echo $position; ?></span>
                                            </td>
                                            <td><?php echo $date_join; ?></td>
                                            <td>
                                                <span
                                                    class="badge bg-label-<?php echo ($status == '1' || $status == 'Active') ? 'success' : 'danger'; ?> badge-status">
                                                    <?php echo ($status == '1' || $status == 'Active') ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns d-flex justify-content-center">
                                                    <a href="user-profile.php?op=view&Id=<?php echo $id; ?>"
                                                        class="action-btn edit-btn" data-bs-toggle="tooltip"
                                                        data-bs-placement="top" title="Edit User">
                                                        <i class="bx bx-edit-alt"></i>
                                                    </a>
                                                    <button type="button" class="action-btn delete-btn"
                                                        onclick="deleteUser('<?php echo $id; ?>')"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Delete User">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                            }
                                        } else {
                                        ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <div class="d-flex flex-column align-items-center">
                                                    <i class="bx bx-user-x mb-2"
                                                        style="font-size: 3rem; opacity: 0.5;"></i>
                                                    <h6 class="mb-1">No users found</h6>
                                                    <p class="text-muted mb-3">Start by adding a new user</p>
                                                    <a href="user-profile.php" class="btn btn-primary btn-sm">
                                                        <i class="bx bx-plus me-1"></i> Add New User
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="card-footer d-flex align-items-center justify-content-between flex-wrap">
                                <div class="d-flex align-items-center mb-3 mb-md-0">
                                    <div class="text-muted me-3">Showing <span
                                            class="fw-bold">1-<?php echo min($next - 1, 10); ?></span> of <span
                                            class="fw-bold"><?php echo $next - 1; ?></span></div>
                                </div>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item prev">
                                            <a class="page-link" href="javascript:void(0);"><i
                                                    class="tf-icon bx bx-chevrons-left"></i></a>
                                        </li>
                                        <li class="page-item active">
                                            <a class="page-link" href="javascript:void(0);">1</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="javascript:void(0);">2</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="javascript:void(0);">3</a>
                                        </li>
                                        <li class="page-item next">
                                            <a class="page-link" href="javascript:void(0);"><i
                                                    class="tf-icon bx bx-chevrons-right"></i></a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <!--/ User List Card -->
                    </div>
                    <!-- / Content -->

                    <!-- Delete User Modal -->
                    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Delete User</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>

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

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>

    <!-- DataTables JS -->
    <script src="assets/vendor/libs/datatables/jquery.dataTables.js"></script>
    <script src="assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
    <script src="assets/vendor/libs/datatables-responsive/datatables.responsive.js"></script>
    <script src="assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js"></script>

    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Page JS -->
    <script>
    // Initialize DataTable
    $(document).ready(function() {
        const userTable = $('#userTable').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                search: "",
                searchPlaceholder: "Search...",
                paginate: {
                    previous: '<i class="bx bx-chevron-left"></i>',
                    next: '<i class="bx bx-chevron-right"></i>'
                }
            },
            order: [
                [0, 'asc']
            ],
            columnDefs: [{
                    orderable: false,
                    targets: [7]
                }, // Disable sorting for actions column
                {
                    responsivePriority: 1,
                    targets: [1, 7]
                }, // Ensure name and actions columns always visible
                {
                    responsivePriority: 2,
                    targets: 6
                } // Status column has second priority
            ]
        });

        // Sync navbar search with DataTable search
        $('#navbar-search').on('keyup', function() {
            userTable.search(this.value).draw();
        });

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                delay: {
                    show: 500,
                    hide: 100
                }
            });
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert-dismissible').alert('close');
        }, 5000);
    });

    // User deletion handling
    let deleteUserId = null;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));

    function deleteUser(userId) {
        deleteUserId = userId;
        deleteModal.show();
    }

    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (deleteUserId) {
            window.location.href = 'user.php?op=delete&Id=' + deleteUserId;
        }
        deleteModal.hide();
    });

    // Handle status filter
    function filterByStatus(status) {
        const statusSelect = document.getElementById('status-filter');
        if (statusSelect) {
            statusSelect.value = status;
            $('#userTable').DataTable().draw();
        }
    }

    // Handle role filter
    function filterByRole(role) {
        const roleSelect = document.getElementById('role-filter');
        if (roleSelect) {
            roleSelect.value = role;
            $('#userTable').DataTable().draw();
        }
    }
    </script>

</body>

</html>
