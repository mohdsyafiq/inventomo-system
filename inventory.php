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

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) { // check connection
    die("Connection failed: " . mysqli_connect_error());
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

// Initialize filter variables
$search_term = '';
$category_filter = '';
$stock_status = '';
$success_message = '';
$error_message = '';

// PAGINATION SETTINGS
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Process delete action - updated to handle new image path
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $itemID = (int)$_POST['itemID'];
    
    // Retrieve the image filename before deleting
    $get_image_query = "SELECT image FROM inventory_item WHERE itemID = ?";
    $stmt = mysqli_prepare($conn, $get_image_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $itemID);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $image_to_delete);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        // Delete the product
        $delete_query = "DELETE FROM inventory_item WHERE itemID = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $itemID);
            
            if (mysqli_stmt_execute($stmt)) {
                // If deletion was successful and there's an image, delete the image file too
                if (!empty($image_to_delete)) {
                    $file_to_delete = "uploads/images/" . $image_to_delete; // Updated path
                    if (file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                }
                $success_message = "Product has been deleted successfully.";
            } else {
                $error_message = "Database Error: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Error preparing statement: " . mysqli_error($conn);
        }
    }
}

// Process filter form submission
if (isset($_GET['filter'])) {
    $search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
    $stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
}

// Build base SQL query for counting total records
$count_sql = "SELECT COUNT(*) as total FROM inventory_item WHERE 1=1";

// Build SQL query with filters - now including image column and pagination
$sql = "SELECT itemID, product_name, type_product, stock, price, image FROM inventory_item WHERE 1=1";

// Add search filter if provided
if (!empty($search_term)) {
    $search_condition = " AND (product_name LIKE '%$search_term%' OR itemID LIKE '%$search_term%')";
    $sql .= $search_condition;
    $count_sql .= $search_condition;
}

// Add category filter if provided
if (!empty($category_filter)) {
    $category_condition = " AND type_product = '$category_filter'";
    $sql .= $category_condition;
    $count_sql .= $category_condition;
}

// Add stock status filter if provided
if (!empty($stock_status)) {
    $stock_condition = "";
    if ($stock_status == 'in_stock') {
        $stock_condition = " AND stock > 10";
    } elseif ($stock_status == 'low_stock') {
        $stock_condition = " AND stock <= 10 AND stock > 0";
    } elseif ($stock_status == 'out_of_stock') {
        $stock_condition = " AND stock <= 0";
    }
    $sql .= $stock_condition;
    $count_sql .= $stock_condition;
}

// Get total count of filtered records
$count_result = mysqli_query($conn, $count_sql);
$total_records = 0;
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_records = $count_row['total'];
    mysqli_free_result($count_result);
}

// Calculate pagination values
$total_pages = ceil($total_records / $items_per_page);
$current_page = min($current_page, $total_pages); // Ensure current page doesn't exceed total pages

// Add order by clause (newest first) and pagination
$sql .= " ORDER BY itemID DESC LIMIT $offset, $items_per_page";

// Execute query
$result = mysqli_query($conn, $sql);

// Check if query was successful
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Get all product categories for filter dropdown
$category_query = "SELECT DISTINCT type_product FROM inventory_item ORDER BY type_product";
$category_result = mysqli_query($conn, $category_query);
$categories = [];

if ($category_result) {
    while ($cat_row = mysqli_fetch_assoc($category_result)) {
        $categories[] = $cat_row['type_product'];
    }
    mysqli_free_result($category_result);
}

// Function to generate pagination URL with current filters
function getPaginationUrl($page, $search_term, $category_filter, $stock_status) {
    $params = array();
    $params['page'] = $page;
    
    if (!empty($search_term)) {
        $params['search'] = $search_term;
        $params['filter'] = '1';
    }
    if (!empty($category_filter)) {
        $params['category'] = $category_filter;
        $params['filter'] = '1';
    }
    if (!empty($stock_status)) {
        $params['stock_status'] = $stock_status;
        $params['filter'] = '1';
    }
    
    return 'inventory.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Inventory Management - Inventomo</title>

    <meta name="description" content="Inventory Management System" />

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

    .add-product-btn {
        padding: 0.5rem 1rem;
        background-color: #696cff;
        border: none;
        border-radius: 0.375rem;
        cursor: pointer;
        font-size: 0.875rem;
        color: white;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }

    .add-product-btn:hover {
        background-color: #5f63f2;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(105, 108, 255, 0.2);
    }

    .inventory-card {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: none;
        overflow: hidden;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #d9dee3;
        background-color: transparent;
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #566a7f;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-section {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .filter-input,
    .filter-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: #566a7f;
        background-color: white;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #696cff;
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
    }

    .search-input {
        flex: 1;
        min-width: 250px;
    }

    .filter-btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 0.375rem;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }

    .btn-primary {
        background-color: #696cff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #5f63f2;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(105, 108, 255, 0.2);
    }

    .btn-outline {
        background-color: transparent;
        color: #566a7f;
        border: 1px solid #d9dee3;
        text-decoration: none;
    }

    .btn-outline:hover {
        background-color: #f5f5f9;
        border-color: #696cff;
        color: #696cff;
        transform: translateY(-1px);
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .data-table th,
    .data-table td {
        padding: 1rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: middle;
        position: relative;
    }

    .data-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        border-bottom: 2px solid #e5e7eb;
    }

    .data-table td {
        color: #566a7f;
        font-size: 0.875rem;
        background-color: white;
    }

    .data-table tbody tr {
        border-bottom: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }

    .data-table tbody tr:last-child {
        border-bottom: none;
    }

    .data-table tbody tr:hover {
        background-color: #f8f9fa;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transform: translateY(-1px);
    }

    .data-table tbody tr:hover td {
        background-color: #f8f9fa;
    }

    /* Product image and name styling */
    .product-cell {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }

    .product-image {
        width: 60px;
        height: 60px;
        border-radius: 0.5rem;
        object-fit: cover;
        border: 1px solid #d9dee3;
        flex-shrink: 0;
        transition: transform 0.2s ease;
    }

    .product-image:hover {
        transform: scale(1.05);
    }

    .product-image-placeholder {
        width: 60px;
        height: 60px;
        border-radius: 0.5rem;
        background-color: #f5f5f9;
        border: 1px solid #d9dee3;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .product-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
    }

    .product-name {
        font-weight: 600;
        color: #374151;
        line-height: 1.4;
        word-wrap: break-word;
    }

    .product-id {
        font-size: 0.75rem;
        color: #9ca3af;
        font-weight: 400;
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

    .status-in-stock {
        background-color: #d1fae5;
        color: #065f46;
    }

    .status-low-stock {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-out-stock {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .status-indicator {
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 50%;
    }

    .indicator-green {
        background-color: #10b981;
    }

    .indicator-yellow {
        background-color: #f59e0b;
    }

    .indicator-red {
        background-color: #ef4444;
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
        font-size: 0.75rem;
        color: #566a7f;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: all 0.2s ease;
    }

    .action-btn:hover {
        background-color: #f5f5f9;
        border-color: #696cff;
        color: #696cff;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(105, 108, 255, 0.2);
    }

    .action-btn.delete:hover {
        border-color: #ef4444;
        color: #ef4444;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
    }

    .checkbox-input {
        width: 1rem;
        height: 1rem;
        cursor: pointer;
        accent-color: #696cff;
    }

    .filter-active-badge {
        background-color: #e0f2fe;
        color: #0277bd;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        margin-left: 0.5rem;
        font-weight: 500;
    }

    .alert {
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        animation: fadeIn 0.5s ease-in-out;
        border: 1px solid transparent;
    }

    .alert-success {
        background-color: #d1fae5;
        color: #065f46;
        border-color: #a7f3d0;
    }

    .alert-error {
        background-color: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .no-data {
        text-align: center;
        padding: 3rem 1rem;
        color: #9ca3af;
    }

    .no-data i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #d1d5db;
    }

    .no-data h6 {
        margin-bottom: 0.5rem;
        color: #6b7280;
    }

    .no-data p {
        margin-bottom: 1.5rem;
        color: #9ca3af;
    }

    /* ENHANCED PAGINATION STYLES */
    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #d9dee3;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .pagination-info {
        color: #6b7280;
        font-size: 0.875rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .pagination-stats {
        font-size: 0.75rem;
        color: #9ca3af;
    }

    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .pagination-nav {
        display: flex;
        gap: 0.25rem;
        align-items: center;
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
        transition: all 0.2s ease;
        min-width: 40px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
    }

    .page-btn:hover:not(:disabled):not(.active) {
        background-color: #f5f5f9;
        border-color: #696cff;
        color: #696cff;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(105, 108, 255, 0.15);
    }

    .page-btn.active {
        background-color: #696cff;
        border-color: #696cff;
        color: white;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(105, 108, 255, 0.25);
    }

    .page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background-color: #f8f9fa;
        color: #9ca3af;
    }

    .page-btn.prev,
    .page-btn.next {
        padding: 0.5rem;
        font-weight: 600;
    }

    .page-btn.first,
    .page-btn.last {
        padding: 0.5rem 1rem;
    }

    .pagination-ellipsis {
        padding: 0.5rem 0.25rem;
        color: #9ca3af;
        font-weight: 600;
    }

    .items-per-page-selector {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: #566a7f;
    }

    .items-per-page-select {
        padding: 0.375rem 0.5rem;
        border: 1px solid #d9dee3;
        border-radius: 0.25rem;
        background-color: white;
        font-size: 0.875rem;
        color: #566a7f;
    }

    .pagination-goto {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: #566a7f;
    }

    .pagination-goto input {
        width: 60px;
        padding: 0.375rem 0.5rem;
        border: 1px solid #d9dee3;
        border-radius: 0.25rem;
        text-align: center;
        font-size: 0.875rem;
    }

    .pagination-goto button {
        padding: 0.375rem 0.75rem;
        background-color: #696cff;
        color: white;
        border: none;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .pagination-goto button:hover {
        background-color: #5f63f2;
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
        backdrop-filter: blur(4px);
    }

    .modal-content {
        background-color: white;
        border-radius: 0.5rem;
        width: 90%;
        max-width: 500px;
        padding: 1.5rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
    }

    .modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #374151;
        margin: 0 0 0 0.5rem;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1.5rem;
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

    /* Improved table responsive design */
    .table-responsive {
        overflow-x: auto;
        border-radius: 0.5rem;
    }

    /* Loading states */
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #696cff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Responsive Design Improvements */
    @media (max-width: 768px) {
        .filter-section {
            flex-direction: column;
        }

        .search-input {
            min-width: auto;
        }

        .content-header {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }

        .pagination-wrapper {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }

        .pagination-controls {
            flex-direction: column;
            gap: 1rem;
        }

        .pagination-nav {
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-buttons {
            flex-direction: column;
        }

        .product-cell {
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.5rem;
        }

        .product-info {
            align-items: center;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.8rem;
        }

        .modal-content {
            width: 95%;
            margin: 1rem;
        }

        .page-btn {
            padding: 0.375rem 0.5rem;
            font-size: 0.8rem;
            min-width: 35px;
        }
    }

    @media (max-width: 480px) {
        .data-table {
            font-size: 0.75rem;
        }

        .product-image,
        .product-image-placeholder {
            width: 40px;
            height: 40px;
        }

        .action-buttons {
            flex-direction: row;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.6875rem;
        }

        .pagination-nav {
            gap: 0.125rem;
        }

        .page-btn {
            padding: 0.25rem 0.375rem;
            font-size: 0.75rem;
            min-width: 30px;
        }
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

    .page-title {
        color: white;
        font-size: 2.0rem;
        font-weight: bold;
    }

    /* New entry indicator */
    .new-entry {
        border-left: 4px solid #10b981;
        background-color: #f0fdf4;
    }

    .new-entry:hover {
        background-color: #ecfdf5;
    }

    .new-badge {
        background-color: #10b981;
        color: white;
        font-size: 0.6rem;
        padding: 0.125rem 0.375rem;
        border-radius: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.025rem;
        margin-left: 0.5rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.7;
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
                                                <div
                                                    class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                                    <?php if ($navbar_pic): ?>
                                                    <img src="<?php echo htmlspecialchars($navbar_pic); ?>"
                                                        alt="Profile Picture">
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
                        <!-- Page Header -->
                        <div class="content-header">
                            <h4 class="page-title">
                                <i class="bx bx-package me-2"></i>Inventory Management
                            </h4>
                            <a href="add-new-product.php" class="add-product-btn">
                                <i class="bx bx-plus"></i>Add New Product
                            </a>
                        </div>

                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" id="successAlert">
                            <i class="bx bx-check-circle"></i>
                            <span><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-error" id="errorAlert">
                            <i class="bx bx-error-circle"></i>
                            <span><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Inventory Card -->
                        <div class="inventory-card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bx bx-list-ul"></i>Inventory List
                                    <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_status)): ?>
                                    <span class="filter-active-badge">
                                        <i class="bx bx-filter-alt"></i>Filters Applied
                                    </span>
                                    <?php endif; ?>
                                </h5>
                                <div>
                                    <small class="text-muted">
                                        Total Items: <strong><?php echo number_format($total_records); ?></strong>
                                    </small>
                                </div>
                            </div>

                            <!-- Filter Section -->
                            <form method="GET" action="inventory.php" id="filterForm">
                                <div class="filter-section">
                                    <input type="text" name="search" class="filter-input search-input"
                                        placeholder="Search by product name or ID..."
                                        value="<?php echo htmlspecialchars($search_term); ?>" id="searchInput">

                                    <select name="category" class="filter-select" id="categoryFilter">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>"
                                            <?php echo ($category == $category_filter) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select name="stock_status" class="filter-select" id="stockFilter">
                                        <option value="">All Stock Status</option>
                                        <option value="in_stock"
                                            <?php echo ($stock_status == 'in_stock') ? 'selected' : ''; ?>>In Stock
                                            (>10)
                                        </option>
                                        <option value="low_stock"
                                            <?php echo ($stock_status == 'low_stock') ? 'selected' : ''; ?>>Low Stock
                                            (1-10)
                                        </option>
                                        <option value="out_of_stock"
                                            <?php echo ($stock_status == 'out_of_stock') ? 'selected' : ''; ?>>Out of
                                            Stock (0)
                                        </option>
                                    </select>

                                    <button type="submit" name="filter" class="filter-btn btn-primary">
                                        <i class="bx bx-filter-alt"></i>Apply Filters
                                    </button>

                                    <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_status)): ?>
                                    <a href="inventory.php" class="filter-btn btn-outline">
                                        <i class="bx bx-x"></i>Clear All
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <!-- Data Table -->
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="checkbox-input" id="selectAll"
                                                    onchange="toggleAllCheckboxes()" title="Select All">
                                            </th>
                                            <th style="min-width: 200px;">Product Details</th>
                                            <th style="min-width: 120px;">Category</th>
                                            <th style="width: 80px;">Stock Qty</th>
                                            <th style="width: 100px;">Price (RM)</th>
                                            <th style="width: 120px;">Status</th>
                                            <th style="width: 150px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="inventoryTableBody">
                                        <?php 
                                        if (mysqli_num_rows($result) > 0) {
                                            $row_count = 0;
                                            $latest_id = null;
                                            
                                            // Get the latest item ID to highlight new entries
                                            $latest_query = "SELECT MAX(itemID) as latest_id FROM inventory_item";
                                            $latest_result = mysqli_query($conn, $latest_query);
                                            if ($latest_result) {
                                                $latest_row = mysqli_fetch_assoc($latest_result);
                                                $latest_id = $latest_row['latest_id'];
                                                mysqli_free_result($latest_result);
                                            }
                                            
                                            while($row = mysqli_fetch_assoc($result)) {
                                                $row_count++;
                                                
                                                // Check if this is a new entry (added in last 24 hours or is the latest ID)
                                                $is_new_entry = ($row['itemID'] == $latest_id && $current_page == 1);
                                                
                                                // Determine status based on stock level
                                                $status_class = '';
                                                $status_text = '';
                                                $indicator_class = '';
                                                
                                                if ($row['stock'] <= 0) {
                                                    $status_class = 'status-out-stock';
                                                    $status_text = 'Out of Stock';
                                                    $indicator_class = 'indicator-red';
                                                } elseif ($row['stock'] <= 10) {
                                                    $status_class = 'status-low-stock';
                                                    $status_text = 'Low Stock';
                                                    $indicator_class = 'indicator-yellow';
                                                } else {
                                                    $status_class = 'status-in-stock';
                                                    $status_text = 'In Stock';
                                                    $indicator_class = 'indicator-green';
                                                }
                                                
                                                $row_class = $is_new_entry ? 'new-entry' : '';
                                                
                                                echo "<tr data-product-id='" . $row['itemID'] . "' class='$row_class'>";
                                                echo "<td><input type='checkbox' class='checkbox-input row-checkbox' value='" . $row['itemID'] . "' onchange='updateSelectAllState()'></td>";
                                                
                                                // Product column with image and name
                                                echo "<td>";
                                                echo "<div class='product-cell'>";
                                                
                                                // Product image
                                                if (!empty($row['image']) && file_exists("uploads/images/" . $row['image'])) {
                                                    echo "<img src='uploads/images/" . htmlspecialchars($row['image']) . "' alt='Product image for " . htmlspecialchars($row['product_name']) . "' class='product-image' loading='lazy'>";
                                                } else {
                                                    echo "<div class='product-image-placeholder' title='No image available'>";
                                                    echo "<i class='bx bx-image'></i>";
                                                    echo "</div>";
                                                }
                                                
                                                // Product info
                                                echo "<div class='product-info'>";
                                                echo "<div class='product-name' title='" . htmlspecialchars($row['product_name']) . "'>" . htmlspecialchars($row['product_name']);
                                                
                                                // Add "NEW" badge for latest entries
                                                if ($is_new_entry) {
                                                    echo "<span class='new-badge'>NEW</span>";
                                                }
                                                
                                                echo "</div>";
                                                echo "<div class='product-id'>ID: " . htmlspecialchars($row['itemID']) . "</div>";
                                                echo "</div>";
                                                
                                                echo "</div>";
                                                echo "</td>";
                                                
                                                echo "<td><span class='badge bg-light text-dark'>" . htmlspecialchars($row['type_product']) . "</span></td>";
                                                echo "<td><strong>" . number_format($row['stock']) . "</strong></td>";
                                                echo "<td><strong>RM " . number_format($row['price'], 2) . "</strong></td>";
                                                echo "<td>
                                                        <span class='status-badge " . $status_class . "' title='" . $status_text . "'>
                                                            <span class='status-indicator " . $indicator_class . "'></span>
                                                            " . $status_text . "
                                                        </span>
                                                      </td>";
                                                echo "<td>
                                                        <div class='action-buttons'>
                                                            <a href='add-new-product.php?edit=" . $row['itemID'] . "' class='action-btn' title='Edit Product'>
                                                                <i class='bx bx-edit'></i>Edit
                                                            </a>
                                                            <button class='action-btn delete' onclick=\"deleteProduct(" . $row['itemID'] . ", '" . addslashes($row['product_name']) . "')\" title='Delete Product'>
                                                                <i class='bx bx-trash'></i>Delete
                                                            </button>
                                                        </div>
                                                      </td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr>
                                                    <td colspan='7'>
                                                        <div class='no-data'>
                                                            <i class='bx bx-search-alt'></i>
                                                            <h6>No Products Found</h6>
                                                            <p>No products found matching your search criteria.</p>";
                                            
                                            if (!empty($search_term) || !empty($category_filter) || !empty($stock_status)) {
                                                echo "<a href='inventory.php' class='filter-btn btn-outline'>
                                                        <i class='bx bx-x'></i> Clear Filters
                                                      </a>";
                                            } else {
                                                echo "<a href='add-new-product.php' class='add-product-btn'>
                                                        <i class='bx bx-plus'></i>Add Your First Product
                                                      </a>";
                                            }
                                            
                                            echo "      </div>
                                                    </td>
                                                  </tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Enhanced Pagination -->
                            <?php if ($total_records > 0): ?>
                            <div class="pagination-wrapper">
                                <div class="pagination-info">
                                    <div>
                                        Showing <strong><?php echo ($offset + 1); ?></strong> to 
                                        <strong><?php echo min($offset + $items_per_page, $total_records); ?></strong> 
                                        of <strong><?php echo number_format($total_records); ?></strong> results
                                    </div>
                                    <div class="pagination-stats">
                                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                                        <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_status)): ?>
                                        | Filters applied
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="pagination-controls">
                                    <div class="pagination-nav">
                                        <!-- First Page -->
                                        <?php if ($current_page > 1): ?>
                                        <a href="<?php echo getPaginationUrl(1, $search_term, $category_filter, $stock_status); ?>" 
                                           class="page-btn first" title="First Page">
                                            <i class="bx bx-chevrons-left"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <!-- Previous Page -->
                                        <?php if ($current_page > 1): ?>
                                        <a href="<?php echo getPaginationUrl($current_page - 1, $search_term, $category_filter, $stock_status); ?>" 
                                           class="page-btn prev" title="Previous Page">
                                            <i class="bx bx-chevron-left"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="page-btn prev" style="opacity: 0.5; cursor: not-allowed;" title="Previous Page">
                                            <i class="bx bx-chevron-left"></i>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        // Show ellipsis at the beginning if needed
                                        if ($start_page > 1) {
                                            echo '<a href="' . getPaginationUrl(1, $search_term, $category_filter, $stock_status) . '" class="page-btn">1</a>';
                                            if ($start_page > 2) {
                                                echo '<span class="pagination-ellipsis">...</span>';
                                            }
                                        }
                                        
                                        // Show page numbers
                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                            if ($i == $current_page) {
                                                echo '<span class="page-btn active">' . $i . '</span>';
                                            } else {
                                                echo '<a href="' . getPaginationUrl($i, $search_term, $category_filter, $stock_status) . '" class="page-btn">' . $i . '</a>';
                                            }
                                        }
                                        
                                        // Show ellipsis at the end if needed
                                        if ($end_page < $total_pages) {
                                            if ($end_page < $total_pages - 1) {
                                                echo '<span class="pagination-ellipsis">...</span>';
                                            }
                                            echo '<a href="' . getPaginationUrl($total_pages, $search_term, $category_filter, $stock_status) . '" class="page-btn">' . $total_pages . '</a>';
                                        }
                                        ?>
                                        
                                        <!-- Next Page -->
                                        <?php if ($current_page < $total_pages): ?>
                                        <a href="<?php echo getPaginationUrl($current_page + 1, $search_term, $category_filter, $stock_status); ?>" 
                                           class="page-btn next" title="Next Page">
                                            <i class="bx bx-chevron-right"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="page-btn next" style="opacity: 0.5; cursor: not-allowed;" title="Next Page">
                                            <i class="bx bx-chevron-right"></i>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <!-- Last Page -->
                                        <?php if ($current_page < $total_pages): ?>
                                        <a href="<?php echo getPaginationUrl($total_pages, $search_term, $category_filter, $stock_status); ?>" 
                                           class="page-btn last" title="Last Page">
                                            <i class="bx bx-chevrons-right"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Quick Page Jump -->
                                    <?php if ($total_pages > 5): ?>
                                    <div class="pagination-goto">
                                        <span>Go to page:</span>
                                        <input type="number" id="gotoPage" min="1" max="<?php echo $total_pages; ?>" 
                                               value="<?php echo $current_page; ?>" style="width: 60px;">
                                        <button type="button" onclick="goToPage()">Go</button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Bulk Actions Section (Hidden by default, shown when items are selected) -->
                            <div id="bulkActionsSection"
                                style="display: none; margin-top: 1rem; padding: 1rem; background-color: #f8f9fa; border-radius: 0.375rem; border: 1px solid #d9dee3;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <span id="selectedCount" class="text-muted">0 items selected</span>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="button" class="filter-btn btn-outline" onclick="clearSelection()">
                                            <i class="bx bx-x"></i>Clear Selection
                                        </button>
                                        <button type="button" class="filter-btn"
                                            style="background-color: #17a2b8; color: white;" onclick="exportSelected()">
                                            <i class="bx bx-export"></i>Export Selected
                                        </button>
                                        <button type="button" class="filter-btn"
                                            style="background-color: #ef4444; color: white;" onclick="deleteSelected()">
                                            <i class="bx bx-trash"></i>Delete Selected
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

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
                                <a href="#" class="footer-link">Contact</a>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="bx bx-error-circle" style="color: #ef4444; font-size: 1.5rem;"></i>
                <h3 class="modal-title">Confirm Deletion</h3>
            </div>
            <div style="margin: 1rem 0;">
                <p id="deleteMessage">Are you sure you want to delete this product?</p>
                <div
                    style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 0.75rem; border-radius: 0.375rem; margin-top: 1rem;">
                    <small style="color: #856404;">
                        <i class="bx bx-info-circle"></i>
                        <strong>Warning:</strong> This action cannot be undone. The product and its associated image
                        will be permanently deleted.
                    </small>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeDeleteModal()" class="filter-btn btn-outline">
                    <i class="bx bx-x"></i>Cancel
                </button>
                <form id="deleteForm" method="POST" action="inventory.php" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteItemID" name="itemID" value="">
                    <button type="submit" class="filter-btn"
                        style="background-color: #ef4444; color: white; border: none;">
                        <i class="bx bx-trash"></i> Delete Product
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Confirmation Modal -->
    <div class="modal-overlay" id="bulkDeleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="bx bx-error-circle" style="color: #ef4444; font-size: 1.5rem;"></i>
                <h3 class="modal-title">Confirm Bulk Deletion</h3>
            </div>
            <div style="margin: 1rem 0;">
                <p id="bulkDeleteMessage">Are you sure you want to delete the selected products?</p>
                <div
                    style="background-color: #fee2e2; border: 1px solid #fecaca; padding: 0.75rem; border-radius: 0.375rem; margin-top: 1rem;">
                    <small style="color: #991b1b;">
                        <i class="bx bx-error-circle"></i>
                        <strong>Critical Warning:</strong> This will permanently delete multiple products and cannot be
                        undone.
                    </small>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeBulkDeleteModal()" class="filter-btn btn-outline">
                    <i class="bx bx-x"></i>Cancel
                </button>
                <button type="button" onclick="confirmBulkDelete()" class="filter-btn"
                    style="background-color: #ef4444; color: white; border: none;">
                    <i class="bx bx-trash"></i> Delete All Selected
                </button>
            </div>
        </div>
    </div>

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
    // Global variables
    let selectedItems = [];
    let isSelectAllChecked = false;

    // Initialize page functionality
    document.addEventListener('DOMContentLoaded', function() {
        initializeInventoryPage();
    });

    function initializeInventoryPage() {
        // Auto-dismiss alerts after 5 seconds
        dismissAlertsAfterDelay();

        // Enhanced search functionality
        setupSearchFunctionality();

        // Setup filter change handlers
        setupFilterHandlers();

        // Initialize tooltips if available
        initializeTooltips();

        // Setup keyboard shortcuts
        setupKeyboardShortcuts();

        // Highlight new entries
        highlightNewEntries();

        console.log('Inventory page initialized successfully');
    }

    // Alert management
    function dismissAlertsAfterDelay() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 500);
            }, 5000);
        });
    }

    // Search functionality
    function setupSearchFunctionality() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('filterForm').submit();
                }
            });

            // Add search icon click handler
            const searchIcon = document.querySelector('.bx-search');
            if (searchIcon) {
                searchIcon.addEventListener('click', function() {
                    document.getElementById('filterForm').submit();
                });
            }
        }
    }

    // Filter handlers
    function setupFilterHandlers() {
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(function(select) {
            select.addEventListener('change', function() {
                // Optional: Auto-submit form on filter change
                // document.getElementById('filterForm').submit();
            });
        });
    }

    // Highlight new entries
    function highlightNewEntries() {
        const newEntries = document.querySelectorAll('.new-entry');
        newEntries.forEach(function(entry) {
            // Add a subtle animation to draw attention
            entry.style.transition = 'all 0.3s ease';
            
            // Remove the highlight after 10 seconds
            setTimeout(function() {
                entry.classList.remove('new-entry');
                entry.style.borderLeft = 'none';
                entry.style.backgroundColor = 'white';
            }, 10000);
        });
    }

    // Pagination functions
    function goToPage() {
        const pageInput = document.getElementById('gotoPage');
        const pageNumber = parseInt(pageInput.value);
        const maxPages = <?php echo $total_pages; ?>;
        
        if (pageNumber >= 1 && pageNumber <= maxPages) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('page', pageNumber);
            window.location.href = currentUrl.toString();
        } else {
            alert(`Please enter a page number between 1 and ${maxPages}`);
            pageInput.value = <?php echo $current_page; ?>;
        }
    }

    // Enhanced pagination keyboard support
    document.addEventListener('keydown', function(e) {
        // Don't trigger if user is typing in an input field
        if (e.target.matches('input, textarea, select')) {
            return;
        }

        const currentPage = <?php echo $current_page; ?>;
        const totalPages = <?php echo $total_pages; ?>;
        
        // Left arrow or 'A' key for previous page
        if ((e.key === 'ArrowLeft' || e.key.toLowerCase() === 'a') && currentPage > 1) {
            e.preventDefault();
            window.location.href = '<?php echo getPaginationUrl($current_page - 1, $search_term, $category_filter, $stock_status); ?>';
        }
        
        // Right arrow or 'D' key for next page
        if ((e.key === 'ArrowRight' || e.key.toLowerCase() === 'd') && currentPage < totalPages) {
            e.preventDefault();
            window.location.href = '<?php echo getPaginationUrl($current_page + 1, $search_term, $category_filter, $stock_status); ?>';
        }
        
        // Home key for first page
        if (e.key === 'Home' && currentPage > 1) {
            e.preventDefault();
            window.location.href = '<?php echo getPaginationUrl(1, $search_term, $category_filter, $stock_status); ?>';
        }
        
        // End key for last page
        if (e.key === 'End' && currentPage < totalPages) {
            e.preventDefault();
            window.location.href = '<?php echo getPaginationUrl($total_pages, $search_term, $category_filter, $stock_status); ?>';
        }
    });

    // Checkbox management
    function toggleAllCheckboxes() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.getElementsByClassName('row-checkbox');
        isSelectAllChecked = selectAll.checked;

        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = isSelectAllChecked;
        }

        updateSelectedItems();
        updateBulkActionsVisibility();
    }

    function updateSelectAllState() {
        const checkboxes = document.getElementsByClassName('row-checkbox');
        const selectAll = document.getElementById('selectAll');
        let checkedCount = 0;

        for (let i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                checkedCount++;
            }
        }

        selectAll.checked = checkedCount === checkboxes.length && checkboxes.length > 0;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;

        updateSelectedItems();
        updateBulkActionsVisibility();
    }

    function updateSelectedItems() {
        const checkboxes = document.getElementsByClassName('row-checkbox');
        selectedItems = [];

        for (let i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                selectedItems.push(checkboxes[i].value);
            }
        }

        // Update selected count display
        const selectedCountElement = document.getElementById('selectedCount');
        if (selectedCountElement) {
            const count = selectedItems.length;
            selectedCountElement.textContent = count + ' item' + (count !== 1 ? 's' : '') + ' selected';
        }
    }

    function updateBulkActionsVisibility() {
        const bulkActionsSection = document.getElementById('bulkActionsSection');
        if (bulkActionsSection) {
            bulkActionsSection.style.display = selectedItems.length > 0 ? 'block' : 'none';
        }
    }

    function clearSelection() {
        const checkboxes = document.getElementsByClassName('row-checkbox');
        const selectAll = document.getElementById('selectAll');

        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }

        selectAll.checked = false;
        selectAll.indeterminate = false;
        selectedItems = [];
        updateBulkActionsVisibility();
    }

    // Delete functionality
    function deleteProduct(itemID, productName) {
        const modal = document.getElementById('deleteModal');
        const deleteMessage = document.getElementById('deleteMessage');
        const deleteItemIDInput = document.getElementById('deleteItemID');

        deleteMessage.innerHTML =
            `Are you sure you want to delete <strong>"${escapeHtml(productName)}"</strong>?<br>This action cannot be undone and will remove the product from your inventory.`;
        deleteItemIDInput.value = itemID;

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function deleteSelected() {
        if (selectedItems.length === 0) {
            alert('Please select items to delete.');
            return;
        }

        const modal = document.getElementById('bulkDeleteModal');
        const deleteMessage = document.getElementById('bulkDeleteMessage');

        deleteMessage.innerHTML =
            `Are you sure you want to delete <strong>${selectedItems.length}</strong> selected product${selectedItems.length !== 1 ? 's' : ''}?<br>This action cannot be undone.`;

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeBulkDeleteModal() {
        const modal = document.getElementById('bulkDeleteModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function confirmBulkDelete() {
        // In a real implementation, you would send an AJAX request here
        console.log('Bulk deleting items:', selectedItems);
        alert(`Feature coming soon: Bulk delete ${selectedItems.length} items`);
        closeBulkDeleteModal();
    }

    // Export functionality
    function exportSelected() {
        if (selectedItems.length === 0) {
            alert('Please select items to export.');
            return;
        }

        console.log('Exporting selected items:', selectedItems);
        alert(`Feature coming soon: Export ${selectedItems.length} selected items to CSV/Excel`);
    }

    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function resetFilters() {
        window.location.href = 'inventory.php';
    }

    // Modal click outside handler
    window.onclick = function(event) {
        const deleteModal = document.getElementById('deleteModal');
        const bulkDeleteModal = document.getElementById('bulkDeleteModal');

        if (event.target === deleteModal) {
            closeDeleteModal();
        }

        if (event.target === bulkDeleteModal) {
            closeBulkDeleteModal();
        }
    }

    // Keyboard shortcuts
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Escape key closes modals
            if (e.key === 'Escape') {
                closeDeleteModal();
                closeBulkDeleteModal();
            }

            // Ctrl/Cmd + A selects all (when not in input field)
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
                e.preventDefault();
                const selectAll = document.getElementById('selectAll');
                if (selectAll) {
                    selectAll.checked = !selectAll.checked;
                    toggleAllCheckboxes();
                }
            }

            // Ctrl/Cmd + F focuses search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f' && !e.target.matches('input, textarea')) {
                e.preventDefault();
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // Delete key for selected items
            if (e.key === 'Delete' && selectedItems.length > 0 && !e.target.matches('input, textarea')) {
                e.preventDefault();
                deleteSelected();
            }
        });
    }

    // Initialize tooltips (if Bootstrap tooltips are available)
    function initializeTooltips() {
        // Initialize Bootstrap tooltips if available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }

    // Page refresh with current filters
    function refreshPage() {
        window.location.reload();
    }

    // Auto-refresh notification for new products
    function checkForNewProducts() {
        // This could be implemented with AJAX to check for new products periodically
        // For now, it's just a placeholder
        console.log('Checking for new products...');
    }

    // Set up periodic check for new products (every 30 seconds)
    setInterval(checkForNewProducts, 30000);

    // Show pagination keyboard shortcuts help
    function showPaginationHelp() {
        alert(`Pagination Keyboard Shortcuts:
        
• Left Arrow / A: Previous page
• Right Arrow / D: Next page
• Home: First page
• End: Last page
• Ctrl+F: Focus search
• Ctrl+A: Select all items
• Delete: Delete selected items
• Escape: Close modals`);
    }

    // Add help button for pagination shortcuts
    if (document.querySelector('.pagination-wrapper')) {
        const helpBtn = document.createElement('button');
        helpBtn.innerHTML = '<i class="bx bx-help-circle"></i>';
        helpBtn.className = 'page-btn';
        helpBtn.title = 'Keyboard shortcuts help';
        helpBtn.onclick = showPaginationHelp;
        helpBtn.style.marginLeft = '0.5rem';
        
        const paginationNav = document.querySelector('.pagination-nav');
        if (paginationNav) {
            paginationNav.appendChild(helpBtn);
        }
    }

    console.log('Enhanced Inventory Management System with Pagination v2.0 - Fully Loaded ✓');
    </script>

    <!-- Additional Styles for Print -->
    <style media="print">
    .layout-menu,
    .layout-navbar,
    .content-footer,
    .filter-section,
    .pagination-wrapper,
    #bulkActionsSection,
    .action-buttons,
    .modal-overlay {
        display: none !important;
    }

    .content-wrapper {
        margin: 0 !important;
        padding: 0 !important;
    }

    .inventory-card {
        box-shadow: none !important;
        border: 1px solid #000;
    }

    .data-table {
        font-size: 10px;
    }

    .data-table th,
    .data-table td {
        padding: 0.25rem;
        border: 1px solid #000;
    }

    .product-image,
    .product-image-placeholder {
        width: 30px;
        height: 30px;
    }

    .page-title::after {
        content: " - Printed on "attr(data-print-date);
        font-size: 0.8rem;
        color: #666;
    }

    .new-badge {
        display: none;
    }
    </style>

    <!-- SEO and Meta Tags -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="description"
        content="Inventory Management System - Manage your products, track stock levels, and organize your inventory efficiently with advanced pagination.">
    <meta name="keywords" content="inventory, management, stock, products, warehouse, tracking, pagination">

    <!-- Open Graph Tags -->
    <meta property="og:title" content="Inventory Management - Inventomo">
    <meta property="og:description"
        content="Professional inventory management system for tracking products and stock levels with advanced pagination features.">
    <meta property="og:type" content="website">

    <!-- Favicon variations -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/img/favicon/site.webmanifest">

</body>

</html>

<?php
// Clean up and close database connection
if ($result) {
    mysqli_free_result($result);
}

if ($conn) {
    mysqli_close($conn);
}

// Log page access (optional)
if (isset($_SESSION['user_id'])) {
    error_log("User " . $_SESSION['user_id'] . " accessed inventory page " . $current_page . " at " . date('Y-m-d H:i:s'));
}
?>