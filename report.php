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

// Database connection details
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';
$conn = null;

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

    // Initialize variables for report data and summaries
    $sales_data = [];
    $purchases_data = [];
    $report_data = [];

    $total_sales_transactions = 0;
    $total_sales_quantity = 0;
    $total_revenue = 0;
    $average_order = 0;
    $total_purchases_quantity = 0;
    $error_message = ''; // Initialize error message

    // Initialize filter variables to prevent undefined variable warnings
    $from_date = '';
    $to_date = '';
    $item_id_filter = '';
    $transaction_type_filter = 'all';

    // Check if search form was submitted
    $search_submitted = isset($_GET['search_report']) || 
                       (!empty($_GET['from_date']) && !empty($_GET['to_date'])) ||
                       !empty($_GET['item_id_filter']) ||
                       (isset($_GET['transaction_type_filter']) && $_GET['transaction_type_filter'] !== 'all');

    // Only fetch data if search was submitted
    if ($search_submitted) {

    // --- Server-side Filtering and Data Fetching using UNION ALL ---

    // Sanitize and get filter parameters from the GET request
    $from_date = isset($_GET['from_date']) ? mysqli_real_escape_string($conn, $_GET['from_date']) : '';
    $to_date = isset($_GET['to_date']) ? mysqli_real_escape_string($conn, $_GET['to_date']) : '';
    $item_id_filter = isset($_GET['item_id_filter']) ? mysqli_real_escape_string($conn, $_GET['item_id_filter']) : '';
    // Removed category_filter from $_GET as it's being removed
    $transaction_type_filter = isset($_GET['transaction_type_filter']) ? mysqli_real_escape_string($conn, $_GET['transaction_type_filter']) : 'all';

    // Base query parts
    $sales_query_part = "
        SELECT
            soh.transaction_date AS date,
            soh.product_id AS item_id,
            ii.product_name AS description,
            ii.type_product AS category,
            soh.quantity_deducted AS quantity,
            ii.price AS unit_price,
            (soh.quantity_deducted * ii.price) AS total,
            'Stock Out (Sales)' AS type_display
        FROM stock_out_history AS soh
        JOIN inventory_item AS ii ON soh.product_id = ii.itemID
        WHERE 1=1
    ";

    $purchases_query_part = "
        SELECT
            sih.transaction_date AS date,
            sih.product_id AS item_id,
            ii.product_name AS description,
            ii.type_product AS category,
            sih.quantity_added AS quantity,
            ii.price AS unit_price,
            (sih.quantity_added * ii.price) AS total,
            'Stock In (Purchases)' AS type_display
        FROM stock_in_history AS sih
        JOIN inventory_item AS ii ON sih.product_id = ii.itemID
        WHERE 1=1
    ";

    // Common conditions for both sales and purchases
    $common_conditions = "";

    // Add date filter
    if (!empty($from_date) && !empty($to_date)) {
        $common_conditions .= " AND combined.date BETWEEN '$from_date' AND '$to_date'";
    }

    // Add item ID filter
    if (!empty($item_id_filter)) {
        $common_conditions .= " AND combined.item_id = '$item_id_filter'";
    }

    // Construct the final query using UNION ALL
    $final_query_parts = [];
    if ($transaction_type_filter == 'all' || $transaction_type_filter == 'stock_out') {
        $final_query_parts[] = "($sales_query_part)";
    }
    if ($transaction_type_filter == 'all' || $transaction_type_filter == 'stock_in') {
        $final_query_parts[] = "($purchases_query_part)";
    }

    if (empty($final_query_parts)) {
        // Fallback if no specific type is selected, or if the filter is set to 'all' implicitly
        $base_union_query = "($sales_query_part) UNION ALL ($purchases_query_part)";
    } else {
        $base_union_query = implode(" UNION ALL ", $final_query_parts);
    }

    // Apply common conditions as an outer WHERE clause on the combined set
    $final_query = "SELECT * FROM ($base_union_query) AS combined WHERE 1=1 " . $common_conditions . " ORDER BY date DESC";


    // Execute the final combined query
    $report_result = mysqli_query($conn, $final_query);
    if (!$report_result) {
        $error_message .= "Database query failed: " . mysqli_error($conn) . "<br>";
    } else {
        $report_data = mysqli_fetch_all($report_result, MYSQLI_ASSOC);
    }

    // Recalculate sales and purchases data from the combined report_data for summaries
    $sales_data = array_filter($report_data, function($row) { return $row['type_display'] === 'Stock Out (Sales)'; });
    $purchases_data = array_filter($report_data, function($row) { return $row['type_display'] === 'Stock In (Purchases)'; });

    // Calculate totals from filtered and separated data
    $total_sales_transactions = count($sales_data);
    $total_sales_quantity = array_sum(array_column($sales_data, 'quantity'));
    $total_revenue = array_sum(array_column($sales_data, 'total'));
    $average_order = $total_sales_transactions > 0 ? $total_revenue / $total_sales_transactions : 0;
    $total_purchases_quantity = array_sum(array_column($purchases_data, 'quantity'));


    // Get all distinct categories for the filter dropdown
    $category_query = "SELECT DISTINCT type_product FROM inventory_item ORDER BY type_product";
    $category_result = mysqli_query($conn, $category_query);
    $categories = [];
    if ($category_result) {
        while ($cat_row = mysqli_fetch_assoc($category_result)) {
            $categories[] = $cat_row['type_product'];
        }
    }

    // Get all distinct items for the filter dropdown
    $items_query = "SELECT itemID, product_name FROM inventory_item ORDER BY product_name";
    $items_result = mysqli_query($conn, $items_query);
    $all_items = [];
    if ($items_result) {
        while ($item_row = mysqli_fetch_assoc($items_result)) {
            $all_items[] = $item_row;
        }
    }

} else {
    // No search submitted yet - show empty state
    $report_data = [];
    $sales_data = [];
    $purchases_data = [];
    $total_sales_transactions = 0;
    $total_sales_quantity = 0;
    $total_revenue = 0;
    $average_order = 0;
    $total_purchases_quantity = 0;
}

} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

// Close database connection
if ($conn) {
    mysqli_close($conn);
}
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
        background-color: #3B82F6;
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
        background-color: #2563EB;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.2);
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
        border-color: #3B82F6;
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
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
        background-color: #3B82F6;
        color: white;
    }

    .btn-primary:hover {
        background-color: #2563EB;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.2);
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
        border-color: #3B82F6;
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
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
        padding: 2rem 1.5rem;
        background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
        border-bottom: 1px solid #d9dee3;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .summary-card {
        background: white;
        padding: 1.5rem;
        border-radius: 0.75rem;
        border: 1px solid #d9dee3;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.1);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
        position: relative;
        overflow: hidden;
    }

    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #3B82F6, #2563EB);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .summary-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 0.75rem 1.5rem rgba(161, 172, 184, 0.25);
        border-color: #3B82F6;
    }

    .summary-card:hover::before {
        opacity: 1;
    }

    .summary-card.revenue {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border-color: #28a745;
    }

    .summary-card.revenue::before {
        background: linear-gradient(90deg, #28a745, #218838);
    }

    .summary-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        flex-shrink: 0;
        box-shadow: 0 0.25rem 0.5rem rgba(59, 130, 246, 0.3);
    }

    .summary-card.revenue .summary-icon {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        box-shadow: 0 0.25rem 0.5rem rgba(40, 167, 69, 0.3);
    }

    .summary-content {
        flex: 1;
        min-width: 0;
    }

    .summary-label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .summary-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #374151;
        line-height: 1.2;
        margin: 0;
    }

    .summary-card.revenue .summary-value {
        color: #155724;
    }

    /* Styling for when no search has been performed */
    .summary-card.no-data-state {
        background: #f8f9fa;
        border-color: #dee2e6;
        opacity: 0.7;
    }

    .summary-card.no-data-state .summary-icon {
        background: #6c757d;
        box-shadow: 0 0.25rem 0.5rem rgba(108, 117, 125, 0.3);
    }

    .summary-card.no-data-state .summary-value {
        color: #6c757d;
    }

    .summary-card.no-data-state:hover {
        transform: none;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.1);
        border-color: #dee2e6;
    }

    .summary-card.no-data-state::before {
        display: none;
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
        background-color: #3B82F6;
        border-color: #3B82F6;
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
        color: #3B82F6;
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
            gap: 1rem;
        }

        .summary-card {
            padding: 1rem;
            gap: 0.75rem;
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            font-size: 1.25rem;
        }

        .summary-value {
            font-size: 1.5rem;
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
    </style>

    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="index.php" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <img width="80" src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
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

                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Pages</span>
                    </li>
                    <li class="menu-item">
                        <a href="inventory.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-card"></i>
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
                            <i class="menu-icon tf-icons bx bx-cart"></i>
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
            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
                            </div>
                        </div>
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
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
                            </ul>
                    </div>
                </nav>
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="content-header">
                            <h4 class="page-title">
                                <i class="bx bxs-report"></i>Report
                            </h4>
                        </div>

                        <div class="report-card" style="position: relative;">
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

                            <!-- Summary Section moved to top -->
                            <div class="summary-section">
                                <div class="summary-grid">
                                    <div class="summary-card <?php echo !$search_submitted ? 'no-data-state' : ''; ?>">
                                        <div class="summary-icon">
                                            <i class="bx bx-transfer"></i>
                                        </div>
                                        <div class="summary-content">
                                            <div class="summary-label">Total Transactions</div>
                                            <div class="summary-value" id="totalTransactions">
                                                <?php echo $search_submitted ? count($report_data) : '--'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="summary-card <?php echo !$search_submitted ? 'no-data-state' : ''; ?>">
                                        <div class="summary-icon">
                                            <i class="bx bx-package"></i>
                                        </div>
                                        <div class="summary-content">
                                            <div class="summary-label">Total Stock Out</div>
                                            <div class="summary-value" id="totalQuantity">
                                                <?php echo $search_submitted ? $total_sales_quantity : '--'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="summary-card revenue <?php echo !$search_submitted ? 'no-data-state' : ''; ?>">
                                        <div class="summary-icon">
                                            <i class="bx bx-dollar-circle"></i>
                                        </div>
                                        <div class="summary-content">
                                            <div class="summary-label">Total Revenue</div>
                                            <div class="summary-value" id="totalRevenue">
                                                <?php echo $search_submitted ? 'RM ' . number_format($total_revenue, 2) : '--'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="summary-card <?php echo !$search_submitted ? 'no-data-state' : ''; ?>">
                                        <div class="summary-icon">
                                            <i class="bx bx-plus-circle"></i>
                                        </div>
                                        <div class="summary-content">
                                            <div class="summary-label">Total Stock In</div>
                                            <div class="summary-value" id="totalStockIn">
                                                <?php echo $search_submitted ? $total_purchases_quantity : '--'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form method="GET" action="report.php" onsubmit="showLoading()" autocomplete="off"> <!-- Added autocomplete="off" -->
                                <div class="filters-section">
                                    <div class="filter-row">
                                        <label class="filter-label">Date Range:</label>
                                        <input type="date" class="filter-input" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" placeholder="From Date">
                                        <span class="date-separator">to</span>
                                        <input type="date" class="filter-input" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" placeholder="To Date">
                                        <span class="filter-note">Filter by transaction date</span>
                                    </div>
                                    <div class="filter-row">
                                        <label class="filter-label">Item:</label>
                                        <select class="filter-input" name="item_id_filter">
                                            <option value="">All Items</option>
                                            <?php foreach($all_items as $item): ?>
                                                <option value="<?php echo htmlspecialchars($item['itemID']); ?>" <?php echo $item_id_filter == $item['itemID'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($item['product_name']); ?> (ID: <?php echo htmlspecialchars($item['itemID']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <!-- Removed Category Filter Dropdown -->
                                        <span class="filter-note">Filter by item or category</span>
                                    </div>
                                    <div class="filter-row">
                                        <label class="filter-label">Transaction Type:</label>
                                        <select class="filter-input" name="transaction_type_filter">
                                            <option value="all" <?php echo $transaction_type_filter == 'all' ? 'selected' : ''; ?>>All</option>
                                            <option value="stock_out" <?php echo $transaction_type_filter == 'stock_out' ? 'selected' : ''; ?>>Stock Out (Sales)</option>
                                            <option value="stock_in" <?php echo $transaction_type_filter == 'stock_in' ? 'selected' : ''; ?>>Stock In (Purchases)</option>
                                        </select>
                                        <span class="filter-note">Filter by stock movement</span>
                                    </div>
                                </div>

                                <div class="actions-section">
                                    <button type="submit" class="btn btn-primary" name="search_report">
                                        <i class="bx bx-search"></i>Search
                                    </button>
                                    <a href="report.php" class="btn btn-secondary">
                                        <i class="bx bx-refresh"></i>Reset
                                    </a>
                                    <button type="button" class="btn btn-success" onclick="generateAdvancedReport()">
                                        <i class="bx bx-chart"></i>Advanced Report
                                    </button>
                                    <span class="filter-note">Default shows all time</span>

                                    <div class="export-group">
                                        <button class="btn btn-outline" id="exportButton" type="button">
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
                            </form>

                            <div class="table-section">
                                <div class="table-responsive">
                                    <table class="report-table" id="reportTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 5%;">No.</th>
                                                <th style="width: 12%;">Date</th>
                                                <th style="width: 12%;">Item ID</th>
                                                <th style="width: 35%;">Item Name</th>
                                                <th style="width: 8%;">Qty</th>
                                                <th style="width: 14%;" class="amount-col">Unit Price / Cost</th>
                                                <th style="width: 14%;" class="amount-col">Total</th>
                                                <th style="width: 10%;">Type</th>
                                            </tr>
                                        </thead>
                                        <tbody id="reportTableBody">
                                            <?php if ($search_submitted && !empty($report_data)): ?>
                                                <?php foreach ($report_data as $index => $report): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo date('d-m-Y', strtotime($report['date'])); ?></td>
                                                    <td><strong><?php echo htmlspecialchars($report['item_id']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($report['description']); ?></td>
                                                    <td><?php echo $report['quantity']; ?></td>
                                                    <td class="amount-col">RM <?php echo number_format($report['unit_price'], 2); ?></td>
                                                    <td class="amount-col"><strong>RM <?php echo number_format($report['total'], 2); ?></strong></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($report['type_display'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php elseif ($search_submitted && empty($report_data)): ?>
                                            <tr>
                                                <td colspan="8">
                                                    <div class="no-data">
                                                        <i class="bx bx-search-alt"></i>
                                                        <h5>No data found</h5>
                                                        <p>No transactions match your filter criteria</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="8">
                                                    <div class="no-data">
                                                        <i class="bx bx-filter-alt"></i>
                                                        <h5>Ready to Search</h5>
                                                        <p>Use the filters above to search for transaction data</p>
                                                        <div style="margin-top: 1rem;">
                                                            <span style="color: #6c757d; font-size: 0.875rem;">or apply specific filters</span>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="pagination-wrapper">
                                <div class="pagination-info" id="paginationInfo">
                                    <?php if ($search_submitted): ?>
                                        Showing <?php echo count($report_data); ?> entries
                                    <?php else: ?>
                                        No search performed yet
                                    <?php endif; ?>
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
                    <div class="content-backdrop fade"></div>
                </div>
                </div>
            </div>

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <script>
    // Show loading overlay
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }

    // Hide loading overlay
    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }

    // Refresh report data - reloads the page with a loading spinner
    function refreshReport() {
        showLoading();
        // window.location.href = 'report.php'; // Simply reload to reset all filters
    }

    // Generate advanced report (placeholder)
    function generateAdvancedReport() {
        showLoading();

        setTimeout(() => {
            hideLoading();
            alert('Advanced Report Generated!\n\nThis would typically:\n• Generate detailed analytics\n• Create charts and graphs\n• Provide trend analysis\n• Export comprehensive PDF report\n\nFeature would be fully implemented in production.');
        }, 2000);
    }

    // Export functionality - now redirects to a new PHP script for file generation
    document.getElementById('exportButton').addEventListener('click', function() {
        event.preventDefault();
        const format = document.getElementById('exportFormat').value;

        if (!format) {
            alert('Please select an export format first.');
            return;
        }

        showLoading(); // Show loading spinner immediately

        // Get current filter parameters from the form
        const form = document.querySelector('form');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);

        // Add the selected export format to parameters
        params.append('export_format', format);

        // Construct the URL for the new export generation script
        const exportUrl = 'http://localhost/inventomo-system-main/generate-report-export.php?' + params.toString();
console.log(exportUrl);
        // Redirect the browser to this URL to initiate download
        window.location.href =exportUrl;
    //     fetch(exportUrl, {
    //     method: 'GET', // or 'POST', 'PUT', etc.
    //     // headers: { 'Content-Type': 'application/json' }, // if needed
    //     // body: JSON.stringify({ key: 'value' }) // for POST/PUT
    // })
    // .then(response => response.json()) // or response.text(), etc.
    // .then(data => {
    //     console.log('Success:', data);
    //     // Do something with the response
    // })
    // .catch(error => {
    //     console.error('Error:', error);
    // });
        console.log(exportUrl);
        // Hide loading overlay after a short delay (browser takes over download)
        // This is a heuristic, as we can't reliably detect when the download starts/completes
        setTimeout(() => {
            hideLoading();
            // Clear the selected format dropdown after triggering download
            document.getElementById('exportFormat').value = '';
        }, 1000); // Give enough time for the redirect to happen and download to start
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Handle form submission: show loading overlay
        const form = document.querySelector('form');
        form.addEventListener('submit', showLoading);

        // Hide loading overlay on page load (in case of a fast redirect)
        hideLoading();
    });

    // Helper function for capitalizing first letter
    function ucfirst(str) {
        if (!str) return str;
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Setup keyboard shortcuts
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + R to reset (prevent default browser refresh)
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                // window.location.href = 'report.php'; // Redirect to reset filters
            }
        });
    }
    setupKeyboardShortcuts();

    // Enhanced search in navbar (links to main search filter)
    document.querySelector('.navbar-nav input[aria-label="Search..."]').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const searchTerm = this.value;
            if (searchTerm.trim()) {
                // Set the item filter and trigger search
                const form = document.querySelector('form');
                const itemFilterSelect = form.querySelector('select[name="item_id_filter"]');
                 if (itemFilterSelect) {
                    // Try to find an option that matches the search term in text or value
                    let foundOption = Array.from(itemFilterSelect.options).find(option =>
                        option.textContent.toLowerCase().includes(searchTerm.toLowerCase()) ||
                        option.value.toLowerCase().includes(searchTerm.toLowerCase())
                    );
                    if (foundOption) {
                        itemFilterSelect.value = foundOption.value; // Select the matching option
                    } else {
                        // If no direct match, could default to "All Items" or just not filter by item_id
                        itemFilterSelect.value = ""; // Default to "All Items"
                    }
                    showLoading();
                    form.submit();
                }
            } else {
                // If search box is cleared, reset filters
                // window.location.href = 'report.php';
            }
        }
    });

    // Client-side pagination controls (disabled by default as filtering is server-side)
    // These buttons will currently just reload the page if clicked, as they are disabled by default.
    // For actual pagination, you'd send `page` and `items_per_page` parameters to PHP.
    document.getElementById('prevBtn').addEventListener('click', () => {
        alert('Pagination not implemented for server-side filtering. Use filters to narrow down results.');
    });

    document.getElementById('nextBtn').addEventListener('click', () => {
        alert('Pagination not implemented for server-side filtering. Use filters to narrow down results.');
    });

    // Disable pagination buttons by default if not implementing server-side pagination
    document.getElementById('prevBtn').disabled = true;
    document.getElementById('nextBtn').disabled = true;

    </script>

    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>
