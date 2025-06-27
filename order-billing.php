<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for user authentication
session_start();

// Database connection details (defined globally for easy access)
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';
$conn = null; // Initialize connection variable to null

// --- GLOBAL HELPER FUNCTIONS (DEFINED EARLY TO ENSURE AVAILABILITY) ---

// Helper function to get avatar background color based on position
function getAvatarColor($position) {
    switch (strtolower($position)) {
        case 'admin': return 'primary';
        case 'super-admin': return 'danger';
        case 'moderator': return 'warning';
        case 'manager': return 'success';
        case 'staff': return 'info';
        default: return 'secondary';
    }
}

// Helper function to get profile picture path
function getProfilePicture($profile_picture_name, $avatar_path) {
    if (!empty($profile_picture_name) && $profile_picture_name != 'default.jpg' && $profile_picture_name != '1.png') {
        $photo_path = $avatar_path . $profile_picture_name;
        if (file_exists($photo_path)) {
            return $photo_path;
        }
    }
    return null; // Returns null to trigger initials generation or fallback
}

// Function to sanitize input data
function sanitize_input($data) {
    global $conn; // Access the global connection object

    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    // Only apply mysqli_real_escape_string if connection is established and data is a string
    if ($conn && is_object($conn) && is_string($data)) {
        $data = mysqli_real_escape_string($conn, $data);
    }
    return $data;
}

// Global functions for formatting and icons (used in HTML rendering)
function format_rm_display($amount) {
    return 'RM ' . number_format((float)$amount, 2, '.', ',');
}

function formatDate($date) {
    // Handle null or empty dates gracefully
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date('M d, Y', strtotime($date));
}

function getStatusIcon($status) {
    switch(strtolower($status)) { // Use strtolower for case-insensitivity
        case 'paid': return 'bx-check-circle';
        case 'approved': return 'bx-check-circle'; // New status
        case 'pending': return 'bx-time-five';
        case 'overdue': return 'bx-error-alt';
        case 'draft': return 'bx-file-blank';
        case 'rejected': return 'bx-x-circle'; // New status
        case 'kiv': return 'bx-bell'; // New status
        default: return 'bx-help-circle';
    }
}

// Helper function to generate pagination links
function generatePaginationLinks($current_page, $total_pages, $param_name) {
    $links = [];
    
    // Get current URL parameters and preserve them
    $current_params = $_GET;
    
    // Previous button
    if ($current_page > 1) {
        $prev_page = $current_page - 1;
        $current_params[$param_name] = $prev_page;
        $query_string = http_build_query($current_params);
        $links[] = '<a href="?' . $query_string . '" class="page-link"><i class="bx bx-chevron-left"></i></a>';
    } else {
        $links[] = '<span class="page-link disabled"><i class="bx bx-chevron-left"></i></span>';
    }
    
    // Page numbers
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $current_params[$param_name] = 1;
        $query_string = http_build_query($current_params);
        $links[] = '<a href="?' . $query_string . '" class="page-link">1</a>';
        if ($start_page > 2) {
            $links[] = '<span class="page-link disabled">...</span>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $links[] = '<span class="page-link active">' . $i . '</span>';
        } else {
            $current_params[$param_name] = $i;
            $query_string = http_build_query($current_params);
            $links[] = '<a href="?' . $query_string . '" class="page-link">' . $i . '</a>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $links[] = '<span class="page-link disabled">...</span>';
        }
        $current_params[$param_name] = $total_pages;
        $query_string = http_build_query($current_params);
        $links[] = '<a href="?' . $query_string . '" class="page-link">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $next_page = $current_page + 1;
        $current_params[$param_name] = $next_page;
        $query_string = http_build_query($current_params);
        $links[] = '<a href="?' . $query_string . '" class="page-link"><i class="bx bx-chevron-right"></i></a>';
    } else {
        $links[] = '<span class="page-link disabled"><i class="bx bx-chevron-right"></i></span>';
    }
    
    return implode('', $links);
}

// --- MAIN SCRIPT EXECUTION BEGINS ---
$success_message = '';
$error_message = '';

// Check for success or error messages from other pages (e.g., save-invoice.php, delete-invoice.php)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// --- Pagination Setup ---
$records_per_page = 10;
$po_page = isset($_GET['po_page']) ? max(1, intval($_GET['po_page'])) : 1;
$ci_page = isset($_GET['ci_page']) ? max(1, intval($_GET['ci_page'])) : 1;
$po_offset = ($po_page - 1) * $records_per_page;
$ci_offset = ($ci_page - 1) * $records_per_page;

// Attempt to establish database connection
try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);

    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, "utf8");

    // Check if user is logged in, if not, redirect to login page
    // This check is duplicated, but keeps the script from running queries if not logged in.
    if (!isset($_SESSION['user_id']) || $_SESSION["loggedin"] !== true) {
        header("Location: auth-login-basic.html");
        exit();
    }

    // Initialize user variables and fetch details
    $profile_link = "#";
    $current_user_name = "User";
    $current_user_role = "User";
    $current_user_avatar = "1.png"; // Default avatar
    $avatar_path = "uploads/photos/"; // Path where profile pictures are stored

    if (isset($_SESSION['user_id'])) {
        $user_id_sanitized = mysqli_real_escape_string($conn, $_SESSION['user_id']);
        $user_query_stmt = mysqli_prepare($conn, "SELECT id, full_name, username, position, profile_picture FROM user_profiles WHERE id = ? LIMIT 1");
        if ($user_query_stmt) {
            mysqli_stmt_bind_param($user_query_stmt, "i", $user_id_sanitized);
            mysqli_stmt_execute($user_query_stmt);
            $user_result = mysqli_stmt_get_result($user_query_stmt);
            if ($user_result && mysqli_num_rows($user_result) > 0) {
                $user_data = mysqli_fetch_assoc($user_result);
                $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
                $current_user_role = $user_data['position'];
                if (!empty($user_data['profile_picture']) && file_exists($avatar_path . $user_data['profile_picture'])) {
                    $current_user_avatar = $user_data['profile_picture'];
                } else {
                    $current_user_avatar = '1.png';
                }
                $profile_link = "user-profile.php?op=view&Id=" . $user_data['id']; // Use lowercase 'id'
            }
            mysqli_stmt_close($user_query_stmt);
        }
    }
    $current_user_avatar_url = getProfilePicture($current_user_avatar, $avatar_path);

    // --- Query 1: Fetch Customer Purchase Orders ---
    // CORRECTED: 'purchase_orders.supplier_id' references 'customer_supplier.id' (not registrationID)
    // Added COALESCE for total_due to handle cases with no items
    $sql_purchase_orders = "
        SELECT
            po.id,
            po.po_number AS doc_number,
            po.date_ordered AS doc_date,
            po.status,
            COALESCE(SUM(poi.quantity * poi.cost_price), 0) * 1.06 AS total_due, -- Apply 6% tax
            COALESCE(cs.companyName, 'N/A') AS party_name,
            COALESCE(cs.registrationID, 'N/A') AS party_id  -- Added registrationID for display
        FROM purchase_orders AS po
        LEFT JOIN customer_supplier AS cs ON po.supplier_id = cs.id
        LEFT JOIN purchase_order_items AS poi ON po.id = poi.purchase_order_id
        GROUP BY po.id, po.po_number, po.date_ordered, po.status, cs.companyName, cs.registrationID -- Added to GROUP BY
        ORDER BY doc_date DESC
        LIMIT $records_per_page OFFSET $po_offset
    ";
    $result_purchase_orders = $conn->query($sql_purchase_orders);
    if (!$result_purchase_orders) {
        throw new Exception("SQL Error in Purchase Orders Query: " . $conn->error);
    }

    // Get total count of purchase orders for pagination
    $sql_po_count = "
        SELECT COUNT(DISTINCT po.id) as total_count
        FROM purchase_orders AS po
        LEFT JOIN customer_supplier AS cs ON po.supplier_id = cs.id
    ";
    $result_po_count = $conn->query($sql_po_count);
    $po_total_count = $result_po_count ? $result_po_count->fetch_assoc()['total_count'] : 0;
    $po_total_pages = ceil($po_total_count / $records_per_page);

    // Debug: Check if we're getting data
    if ($result_purchase_orders->num_rows > 0) {
        error_log("DEBUG: Found " . $result_purchase_orders->num_rows . " purchase orders");
    } else {
        error_log("DEBUG: No purchase orders found");
    }

    // --- Query 2: Fetch Customer Invoices (formerly Supplier Bills) ---
    // CORRECTED: 'customer_invoices.customer_id' references 'customer_supplier.id' (not registrationID)
    $sql_customer_invoices = "
        SELECT
            ci.id,
            ci.invoice_number AS doc_number,
            ci.invoice_date AS doc_date,
            ci.total_amount AS total_due, -- Assuming total_amount is already calculated in customer_invoices
            ci.status,
            COALESCE(cs.companyName, 'N/A') AS party_name,
            COALESCE(cs.registrationID, 'N/A') AS party_id -- Added registrationID for display
        FROM customer_invoices AS ci
        LEFT JOIN customer_supplier AS cs ON ci.customer_id = cs.id -- CORRECTED JOIN CONDITION
        ORDER BY doc_date DESC
        LIMIT $records_per_page OFFSET $ci_offset
    ";
    // Check if customer_invoices table exists and fetch
    $table_exists_query = $conn->query("SHOW TABLES LIKE 'customer_invoices'");
    if($table_exists_query && $table_exists_query->num_rows > 0) {
        $result_customer_invoices = $conn->query($sql_customer_invoices);
        if (!$result_customer_invoices) {
            throw new Exception("SQL Error in Customer Invoices Query: " . $conn->error);
        }
        
        // Get total count of customer invoices for pagination
        $sql_ci_count = "
            SELECT COUNT(*) as total_count
            FROM customer_invoices AS ci
            LEFT JOIN customer_supplier AS cs ON ci.customer_id = cs.id
        ";
        $result_ci_count = $conn->query($sql_ci_count);
        $ci_total_count = $result_ci_count ? $result_ci_count->fetch_assoc()['total_count'] : 0;
        $ci_total_pages = ceil($ci_total_count / $records_per_page);
        
        // Debug: Check if we're getting customer invoice data
        if ($result_customer_invoices->num_rows > 0) {
            error_log("DEBUG: Found " . $result_customer_invoices->num_rows . " customer invoices");
        } else {
            error_log("DEBUG: No customer invoices found");
        }
    } else {
        $result_customer_invoices = false; // Table does not exist
        $ci_total_count = 0;
        $ci_total_pages = 0;
        $error_message .= "Warning: 'customer_invoices' table not found. Customer Invoices section might be empty. ";
    }


} catch (Exception $e) {
    $error_message = "Application Error: " . $e->getMessage();
    $conn = null; // Ensure $conn is null if connection failed
} finally {
    // Close database connection if it was successfully opened
    if ($conn && is_object($conn)) {
        mysqli_close($conn);
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
    /* Popup styling moved to global section as it's common for error/success alerts */
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
        display: flex; /* Use flex for icon alignment */
        align-items: center;
    }
    .custom-popup.error {
        background-color: #dc3545; /* Red for error */
    }
    .custom-popup.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    /* Specific styles for this page's layout */
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

    /* General action button style for consistency */
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
    .action-button.btn-success:hover { background-color: #218838; box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2); }
    .action-button.btn-info { background-color: #17a2b8; }
    .action-button.btn-info:hover { background-color: #138496; box-shadow: 0 4px 8px rgba(23, 162, 184, 0.2); }
    .action-button.btn-warning { background-color: #ffc107; color: #212529; }
    .action-button.btn-warning:hover { background-color: #e0a800; color: #212529; box-shadow: 0 4px 8px rgba(255, 193, 7, 0.2); }
    .action-button.btn-danger { background-color: #dc3545; }
    .action-button.btn-danger:hover { background-color: #c82333; box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2); }

    /* Specific styles for dropdown buttons in actions cell */
    .actions-cell .dropdown-toggle.action-button {
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
        overflow: hidden; /* Ensures rounded corners are visible */
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

    /* Specific status badge colors */
    .status-paid, .status-approved { background-color: #d4edda; color: #155724; } /* Green */
    .status-pending, .status-kiv { background-color: #fff3cd; color: #856404; } /* Yellow/Orange */
    .status-overdue, .status-rejected { background-color: #f8d7da; color: #721c24; } /* Red */
    .status-draft { background-color: #e2e3e5; color: #383d41; } /* Grey */

    .amount-display { font-weight: 600; color: #28a745; }
    .doc-number { font-weight: 600; color: #696cff; }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: #6c757d;
    }
    .empty-state i { font-size: 3rem; color: #d9dee3; margin-bottom: 1rem; }

    /* Modal for viewing documents (PO/Invoice) */
    #viewDocumentModal .modal-body {
        padding: 0; /* Remove padding if content is full-width/height */
    }
    #documentModalBody {
        min-height: 200px; /* Ensure loading spinner has space */
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #documentModalBody > div { /* Style for loading message */
        text-align: center;
        color: #6c757d;
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
            align-items: flex-start;
        }
        .card-header-flex {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            min-width: 700px; /* Ensure table doesn't collapse too much */
        }
        
        /* Mobile improvements for scrollable tables */
        .scrollable-table-container {
            max-height: 400px; /* Slightly smaller on mobile */
        }
        
        .card-footer {
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
        }
        
        .card-footer .d-flex {
            flex-direction: column;
            gap: 0.25rem;
        }
    }

    /* Scrollable table container styles */
    .scrollable-table-container {
        max-height: 500px; /* Height for approximately 10 rows */
        overflow-y: auto;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
    }

    .scrollable-table-container .table {
        margin-bottom: 0; /* Remove default table margin */
    }

    .scrollable-table-container .table thead th {
        position: sticky;
        top: 0;
        background-color: #f5f5f9;
        z-index: 10;
        border-bottom: 2px solid #d9dee3;
    }

    /* Custom scrollbar styling */
    .scrollable-table-container::-webkit-scrollbar {
        width: 8px;
    }

    .scrollable-table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .scrollable-table-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    .scrollable-table-container::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Pagination styles */
    .pagination-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem 1.5rem;
        background-color: #f8f9fa;
        border-top: 1px solid #d9dee3;
    }

    .pagination-info {
        font-size: 0.875rem;
        color: #6c757d;
        margin-right: 1rem;
    }

    .pagination-controls {
        display: flex;
        gap: 0.25rem;
        align-items: center;
    }

    .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.5rem;
        height: 2.5rem;
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        background-color: white;
        color: #566a7f;
        text-decoration: none;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .page-link:hover {
        background-color: #e9ecef;
        border-color: #696cff;
        color: #696cff;
        text-decoration: none;
    }

    .page-link.active {
        background-color: #696cff;
        border-color: #696cff;
        color: white;
    }

    .page-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    .page-link:first-child,
    .page-link:last-child {
        font-weight: 600;
    }

    /* Mobile improvements for pagination */
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
    }
    
    .pagination-info {
        margin-right: 0;
        text-align: center;
    }
    
    .pagination-controls {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .page-link {
        min-width: 2.25rem;
        height: 2.25rem;
        font-size: 0.8rem;
    }
    </style>
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
</head>

<body>
    <!-- Custom Status Popup (for server-side messages) -->
    <div id="statusPopup" class="custom-popup">
        <i class='bx bx-info-circle me-2'></i> <span id="statusMessage"></span>
    </div>

    <!-- Modal for viewing documents (Purchase Orders / Customer Invoices) -->
    <div class="modal fade" id="viewDocumentModal" tabindex="-1" aria-labelledby="viewDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDocumentModalLabel">Document Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="documentModalBody">
                    <!-- Content will be loaded here via AJAX -->
                    <div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading document...</p></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <!-- Print button for modal content -->
                    <button type="button" class="btn btn-info" id="printModalContentBtn"><i class="bx bx-printer me-1"></i> Print</button>
                </div>
            </div>
        </div>
    </div>

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
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User Dropdown -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                        <?php if ($current_user_avatar_url): ?>
                                            <img src="<?php echo htmlspecialchars($current_user_avatar_url); ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars($profile_link); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">My Profile</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Settings</span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="bx bx-power-off me-2"></i><span class="align-middle">Log Out</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="content-header">
                            <h4 class="page-title"><i class="bx bx-receipt"></i>Orders & Billing Management</h4>
                        </div>

                        <!-- Messages from PHP (success/error) -->
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bx bx-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bx bx-error-circle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Debug Information (remove this after confirming fix works) -->
                        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="bx bx-info-circle me-2"></i>
                            <strong>Debug Info:</strong><br>
                            Purchase Orders: <?php echo isset($result_purchase_orders) ? $result_purchase_orders->num_rows : 'N/A'; ?><br>
                            Customer Invoices: <?php echo isset($result_customer_invoices) ? $result_customer_invoices->num_rows : 'N/A'; ?><br>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Purchase Orders Card -->
                        <div class="card">
                            <div class="card-header-flex">
                                <h5><i class="bx bx-shopping-bag"></i>Purchase Orders</h5>
                                <a href="create-purchase-order.php" class="action-button btn-success"><i class="bx bx-plus"></i>Create</a>
                            </div>
                            <div class="scrollable-table-container">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>PO #</th>
                                            <th>Supplier Name (ID)</th> <!-- Changed header -->
                                            <th>Date Issued</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($result_purchase_orders) && $result_purchase_orders->num_rows > 0): ?>
                                            <?php while ($row = $result_purchase_orders->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="doc-number"><?= htmlspecialchars($row['doc_number']) ?></span></td>
                                                <td>
                                                    <?= htmlspecialchars($row['party_name']) ?>
                                                    (ID: <?= htmlspecialchars($row['party_id']) ?>) <!-- Display both -->
                                                </td>
                                                <td><?= formatDate($row['doc_date']) ?></td> <!-- Changed: Use new formatDate function -->
                                                <td><span class="amount-display"><?= format_rm_display($row['total_due']) ?></span></td>
                                                <td>
                                                    <?php
                                                        $status = strtolower($row['status'] ?? 'pending');
                                                        $badge_class = 'status-' . $status; // Dynamically set class
                                                    ?>
                                                    <span class="status-badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($row['status'] ?? '')) ?></span>
                                                </td>
                                                <td class="actions-cell">
                                                    <!-- View Button - Corrected data-document-url -->
                                                    <button type="button" class="action-button btn-info view-document-btn"
                                                        data-bs-toggle="modal" data-bs-target="#viewDocumentModal"
                                                        data-document-url="view-purchase-order.php?id=<?= $row['id'] ?>&modal=true"
                                                        title="View Details">
                                                        <i class="bx bx-show"></i>
                                                    </button>

                                                    <!-- Update Dropdown (Purchase Order) -->
                                                    <div class="dropdown">
                                                        <button class="action-button btn-warning dropdown-toggle" type="button" id="dropdownEditPurchaseOrder<?= $row['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bx bx-edit"></i>Update
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdownEditPurchaseOrder<?= $row['id'] ?>">
                                                            <?php if (in_array(strtolower($row['status']), ['pending', 'draft', 'kiv'])): ?>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li><a class="dropdown-item text-success" href="update-purchase-order-status.php?id=<?= $row['id'] ?>&status=approved"><i class="bx bx-check me-2"></i>Approve</a></li>
                                                                <li><a class="dropdown-item text-danger" href="update-purchase-order-status.php?id=<?= $row['id'] ?>&status=rejected"><i class="bx bx-x me-2"></i>Reject</a></li>
                                                                <li><a class="dropdown-item text-warning" href="update-purchase-order-status.php?id=<?= $row['id'] ?>&status=kiv"><i class="bx bx-bell me-2"></i>KIV</a></li>
                                                            <?php endif; ?>
                                                            <li><hr class="dropdown-divider"></li>
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
                            <!-- Pagination for Purchase Orders -->
                            <?php if ($po_total_pages > 1): ?>
                            <div class="pagination-container">
                                <div class="pagination-info">
                                    Showing <?php echo ($po_offset + 1); ?>-<?php echo min($po_offset + $records_per_page, $po_total_count); ?> of <?php echo $po_total_count; ?> purchase orders
                                </div>
                                <div class="pagination-controls">
                                    <?php echo generatePaginationLinks($po_page, $po_total_pages, 'po_page'); ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Footer showing record count (when no pagination needed) -->
                            <div class="card-footer" style="background-color: #f8f9fa; border-top: 1px solid #d9dee3; padding: 0.75rem 1.5rem; font-size: 0.875rem; color: #6c757d;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Showing <?php echo isset($result_purchase_orders) ? $result_purchase_orders->num_rows : 0; ?> purchase orders</span>
                                    <span><i class="bx bx-info-circle"></i> Sorted by date (newest first)</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Customer Invoices Card -->
                        <div class="card">
                            <div class="card-header-flex">
                                <h5><i class="bx bx-file-blank"></i>Customer Invoices</h5>
                                <a href="create-invoice.php" class="action-button btn-success"><i class="bx bx-plus"></i>Create</a>
                            </div>
                            <div class="scrollable-table-container">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Customer Name (ID)</th> <!-- Changed header -->
                                            <th>Date Issued</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($result_customer_invoices) && $result_customer_invoices->num_rows > 0): ?>
                                            <?php while ($row = $result_customer_invoices->fetch_assoc()): ?>
                                            <tr>
                                                <td><span class="doc-number"><?= htmlspecialchars($row['doc_number']) ?></span></td>
                                                <td>
                                                    <?= htmlspecialchars($row['party_name']) ?>
                                                    (ID: <?= htmlspecialchars($row['party_id']) ?>) <!-- Display both -->
                                                </td>
                                                <td><?= formatDate($row['doc_date']) ?></td> <!-- Changed: Use new formatDate function -->
                                                <td><span class="amount-display"><?= format_rm_display($row['total_due']) ?></span></td>
                                                <td>
                                                    <?php
                                                        $status = strtolower($row['status'] ?? 'pending');
                                                        $badge_class = 'status-' . $status; // Dynamically set class
                                                    ?>
                                                    <span class="status-badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst($row['status'] ?? '')) ?></span>
                                                </td>
                                                <td class="actions-cell">
                                                    <!-- View Button - Corrected data-document-url -->
                                                    <button type="button" class="action-button btn-info view-document-btn"
                                                        data-bs-toggle="modal" data-bs-target="#viewDocumentModal"
                                                        data-document-url="view-customer-invoice.php?id=<?= $row['id'] ?>&modal=true"
                                                        title="View Details">
                                                        <i class="bx bx-show"></i>
                                                    </button>

                                                    <!-- Update Dropdown (Customer Invoice) -->
                                                    <div class="dropdown">
                                                        <button class="action-button btn-warning dropdown-toggle" type="button" id="dropdownEditCustomerInvoice<?= $row['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bx bx-edit"></i>Update
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdownEditCustomerInvoice<?= $row['id'] ?>">
                                                            <?php if (in_array(strtolower($row['status']), ['unpaid'])): // Removed 'kiv' from condition ?>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li><a class="dropdown-item text-success" href="update-invoice-status.php?id=<?= $row['id'] ?>&status=paid"><i class="bx bx-check me-2"></i>Paid</a></li>
                                                                <!-- Removed KIV option -->
                                                            <?php endif; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-danger" href="delete-invoice.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete Customer Invoice #<?= htmlspecialchars($row['doc_number']) ?>?');"><i class="bx bx-trash me-2"></i>Delete</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="empty-state">
                                                    <i class="bx bx-file-blank"></i>
                                                    <h6>No Customer Invoices Found</h6>
                                                    <a href="create-invoice.php" class="action-button btn-success"><i class="bx bx-plus"></i>Create First Invoice</a>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination for Customer Invoices -->
                            <?php if ($ci_total_pages > 1): ?>
                            <div class="pagination-container">
                                <div class="pagination-info">
                                    Showing <?php echo ($ci_offset + 1); ?>-<?php echo min($ci_offset + $records_per_page, $ci_total_count); ?> of <?php echo $ci_total_count; ?> customer invoices
                                </div>
                                <div class="pagination-controls">
                                    <?php echo generatePaginationLinks($ci_page, $ci_total_pages, 'ci_page'); ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Footer showing record count (when no pagination needed) -->
                            <div class="card-footer" style="background-color: #f8f9fa; border-top: 1px solid #d9dee3; padding: 0.75rem 1.5rem; font-size: 0.875rem; color: #6c757d;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Showing <?php echo isset($result_customer_invoices) ? $result_customer_invoices->num_rows : 0; ?> customer invoices</span>
                                    <span><i class="bx bx-info-circle"></i> Sorted by date (newest first)</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">© <script>document.write(new Date().getFullYear());</script> Inventomo. All rights reserved.</div>
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

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
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

            // Function to show custom status popups
            function showStatusPopup(message, isError = false) {
                statusMessageSpan.textContent = message;
                if (isError) {
                    statusPopup.classList.add('error');
                    statusPopup.querySelector('i').className = 'bx bx-error-circle me-2';
                } else {
                    statusPopup.classList.remove('error');
                    statusPopup.querySelector('i').className = 'bx bx-check-circle me-2';
                }
                statusPopup.classList.add('show');
                setTimeout(() => {
                    statusPopup.classList.remove('show');
                }, 5000); // Popup disappears after 5 seconds
            }

            // Check for PHP-generated messages on page load
            <?php if (!empty($success_message)): ?>
                showStatusPopup('<?php echo htmlspecialchars($success_message); ?>', false);
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                showStatusPopup('<?php echo htmlspecialchars($error_message); ?>', true);
            <?php endif; ?>

            // JavaScript for loading content into the document view modal
            const viewDocumentModal = document.getElementById('viewDocumentModal');
            const documentModalBody = viewDocumentModal.querySelector('#documentModalBody');
            const printModalContentBtn = document.getElementById('printModalContentBtn'); // Get the print button

            viewDocumentModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; // Button that triggered the modal
                const documentUrl = button.getAttribute('data-document-url'); // Extract info from data-* attributes

                // Clear previous content and show a loading message
                documentModalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading document...</p></div>';

                // Fetch content from the document URL
                fetch(documentUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.text();
                    })
                    .then(html => {
                        documentModalBody.innerHTML = html; // Inject the fetched content into the modal body
                    })
                    .catch(error => {
                        console.error('Error loading document:', error);
                        documentModalBody.innerHTML = '<p class="text-danger text-center py-5">Failed to load document. Please try again. <br> Error: ' + error.message + '</p>';
                    });
            });

            // Optional: Clear modal content when hidden to ensure fresh load next time
            viewDocumentModal.addEventListener('hidden.bs.modal', function () {
                documentModalBody.innerHTML = ''; // Clear content when modal is closed
            });

            // Print functionality for the modal content
            printModalContentBtn.addEventListener('click', function() {
                const printContent = documentModalBody.innerHTML;

                // Create a new window or iframe to print, preventing styling conflicts
                const printWindow = window.open('', '_blank');
                printWindow.document.write('<html><head><title>Print Document</title>');
                // Copy all stylesheets from the current document to the print window
                document.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                    printWindow.document.write('<link rel="stylesheet" href="' + link.href + '">');
                });
                printWindow.document.write('<style>');
                // Add print-specific styles to hide everything except the modal content
                printWindow.document.write('@media print { body * { visibility: hidden; } .modal-body-print, .modal-body-print * { visibility: visible; } .modal-body-print { position: absolute; left: 0; top: 0; width: 100%; } }');
                printWindow.document.write('</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write('<div class="modal-body-print">' + printContent + '</div>');
                printWindow.document.write('</body></html>');
                printWindow.document.close();

                // Wait for styles to load, then print
                printWindow.onload = function() {
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                };
            });
        });
    </script>
</body>
</html>
