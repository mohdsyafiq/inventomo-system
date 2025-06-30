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

// Initialize user variables with proper defaults
$profile_link = "#";
$current_user_name = "User";
$current_user_role = "User";
$current_user_avatar = "default.jpg";

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

// Helper function to get product image path
function getProductImage($image_name) {
    if (!empty($image_name) && $image_name != 'default.jpg') {
        $image_path = 'uploads/images/' . $image_name;
        if (file_exists($image_path)) {
            return $image_path;
        }
    }
    // Return default product image or placeholder
    return 'assets/img/defaults/product-placeholder.png';
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

// Pagination variables
$items_per_page = 7;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1
$offset = ($current_page - 1) * $items_per_page;

// Process delete action - added in-page delete functionality
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
                    $file_to_delete = "uploads/images/" . $image_to_delete;
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
if (isset($_GET['filter']) || isset($_GET['search']) || isset($_GET['category']) || isset($_GET['stock_status'])) {
    $search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
    $category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
    $stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
    
    // Reset to page 1 when applying new filters (unless explicitly maintaining page)
    if (isset($_GET['filter'])) {
        $current_page = 1;
        $offset = 0;
    }
}

// Build SQL query with filters - UPDATED to include image column
$sql = "SELECT itemID, product_name, type_product, stock, price, image FROM inventory_item WHERE 1=1";
$count_sql = "SELECT COUNT(*) as total FROM inventory_item WHERE 1=1";

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

// Get total count for pagination
$count_result = mysqli_query($conn, $count_sql);
$total_items = 0;
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_items = (int)$count_row['total'];
    mysqli_free_result($count_result);
}

// Calculate pagination values
$total_pages = ceil($total_items / $items_per_page);
$current_page = min($current_page, max(1, $total_pages)); // Ensure current page is within valid range
$offset = ($current_page - 1) * $items_per_page; // Recalculate offset after page validation

// Add order by clause and limit for pagination
$sql .= " ORDER BY itemID LIMIT $offset, $items_per_page";

// Debug information (remove in production)
// echo "<!-- Debug: Current Page: $current_page, Offset: $offset, Total Items: $total_items, Total Pages: $total_pages -->";

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
    /* Background styling matching index.php */
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

    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .page-title {
        font-size: 2.0rem;
        font-weight: bold;
        color: white;
        margin: 0;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
    }

    .add-product-btn {
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #696cff, #5f63f2);
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.875rem;
        color: white;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 15px rgba(105, 108, 255, 0.3);
        transition: all 0.3s ease;
    }

    .add-product-btn:hover {
        background: linear-gradient(135deg, #5f63f2, #696cff);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(105, 108, 255, 0.4);
    }

    /* Card styling with glassmorphism effect */
    .inventory-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 1rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 2rem;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
    }

    .inventory-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid rgba(105, 108, 255, 0.1);
    }

    .card-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .card-title i {
        color: #696cff;
        font-size: 1.75rem;
    }

    .filter-section {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        padding: 1.5rem;
        background: rgba(105, 108, 255, 0.05);
        border-radius: 0.75rem;
        border: 1px solid rgba(105, 108, 255, 0.1);
    }

    .filter-input,
    .filter-select {
        padding: 0.75rem 1rem;
        border: 2px solid rgba(105, 108, 255, 0.2);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        color: #566a7f;
        background-color: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        transition: all 0.3s ease;
    }

    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #696cff;
        background-color: rgba(255, 255, 255, 0.95);
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
    }

    .search-input {
        flex: 1;
        min-width: 250px;
    }

    .filter-btn {
        padding: 0.75rem 1.25rem;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: linear-gradient(135deg, #696cff, #5f63f2);
        color: white;
        box-shadow: 0 4px 15px rgba(105, 108, 255, 0.3);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #5f63f2, #696cff);
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(105, 108, 255, 0.4);
    }

    .btn-outline {
        background: rgba(255, 255, 255, 0.8);
        color: #566a7f;
        border: 2px solid rgba(105, 108, 255, 0.3);
        text-decoration: none;
        backdrop-filter: blur(5px);
    }

    .btn-outline:hover {
        background: rgba(105, 108, 255, 0.1);
        border-color: #696cff;
        color: #696cff;
        transform: translateY(-1px);
    }

    /* Table styling with enhanced visual appeal */
    .table-responsive {
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        background: transparent;
    }

    .data-table th,
    .data-table td {
        padding: 1rem 0.75rem;
        text-align: left;
        border-bottom: 1px solid rgba(105, 108, 255, 0.1);
        vertical-align: middle;
    }

    .data-table th {
        background: linear-gradient(135deg, #696cff, #5f63f2);
        color: white;
        font-weight: 700;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
    }

    .data-table td {
        color: #566a7f;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .data-table tbody tr {
        transition: all 0.3s ease;
    }

    .data-table tbody tr:hover {
        background: rgba(105, 108, 255, 0.05);
        transform: scale(1.005);
    }

    /* Product cell styling with image */
    .product-cell {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-height: 60px;
    }

    .product-image {
        width: 50px;
        height: 50px;
        border-radius: 0.5rem;
        object-fit: cover;
        border: 2px solid rgba(105, 108, 255, 0.2);
        background: rgba(255, 255, 255, 0.8);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .product-image:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 15px rgba(105, 108, 255, 0.3);
        border-color: #696cff;
    }

    .product-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .product-name {
        font-weight: 600;
        color: #374151;
        font-size: 0.9rem;
        line-height: 1.2;
    }

    .product-id {
        font-size: 0.75rem;
        color: #9ca3af;
        font-weight: 500;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.75rem;
        border-radius: 2rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-in-stock {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .status-low-stock {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .status-out-stock {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .status-indicator {
        width: 0.75rem;
        height: 0.75rem;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    .indicator-green {
        background: radial-gradient(circle, #10b981, #059669);
    }

    .indicator-yellow {
        background: radial-gradient(circle, #f59e0b, #d97706);
    }

    .indicator-red {
        background: radial-gradient(circle, #ef4444, #dc2626);
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .action-btn {
        padding: 0.5rem 0.75rem;
        border: 2px solid rgba(105, 108, 255, 0.2);
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        cursor: pointer;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        color: #566a7f;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .action-btn:hover {
        background: rgba(105, 108, 255, 0.1);
        border-color: #696cff;
        color: #696cff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(105, 108, 255, 0.2);
    }

    .action-btn.delete:hover {
        border-color: #ef4444;
        color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    }

    .checkbox-input {
        width: 1.2rem;
        height: 1.2rem;
        cursor: pointer;
        accent-color: #696cff;
        border-radius: 0.25rem;
    }

    .filter-active-badge {
        background: linear-gradient(135deg, #e0f2fe, #b3e5fc);
        color: #0277bd;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        font-size: 0.75rem;
        margin-left: 0.75rem;
        font-weight: 600;
        border: 1px solid #81d4fa;
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: 0.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: slideInDown 0.5s ease-in-out;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(209, 250, 229, 0.9), rgba(167, 243, 208, 0.9));
        color: #065f46;
        border-color: #a7f3d0;
        box-shadow: 0 4px 20px rgba(16, 185, 129, 0.2);
    }

    .alert-error {
        background: linear-gradient(135deg, rgba(254, 226, 226, 0.9), rgba(252, 165, 165, 0.9));
        color: #991b1b;
        border-color: #fecaca;
        box-shadow: 0 4px 20px rgba(239, 68, 68, 0.2);
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .no-data {
        text-align: center;
        padding: 4rem 1rem;
        color: #9ca3af;
    }

    .no-data i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        color: #d1d5db;
        opacity: 0.7;
    }

    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 2px solid rgba(105, 108, 255, 0.1);
    }

    .pagination-info {
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .pagination-controls {
        display: flex;
        gap: 0.5rem;
    }

    .page-btn {
        padding: 0.75rem 1rem;
        border: 2px solid rgba(105, 108, 255, 0.2);
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        cursor: pointer;
        border-radius: 0.5rem;
        color: #566a7f;
        font-size: 0.875rem;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .page-btn:hover,
    .page-btn.active {
        background: linear-gradient(135deg, #696cff, #5f63f2);
        border-color: #696cff;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(105, 108, 255, 0.3);
    }

    .page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    /* Modal Styles with glassmorphism */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(15px);
        border-radius: 1rem;
        width: 90%;
        max-width: 500px;
        padding: 2rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .modal-header {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #374151;
        margin: 0 0 0 0.75rem;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }

    /* Image Modal Styles */
    .image-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
        z-index: 1001;
        justify-content: center;
        align-items: center;
        cursor: pointer;
    }

    .image-modal-content {
        max-width: 90%;
        max-height: 90%;
        border-radius: 1rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        animation: zoomIn 0.3s ease-out;
    }

    @keyframes zoomIn {
        from {
            opacity: 0;
            transform: scale(0.5);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* User avatar styles matching index.php */
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
        }

        .action-buttons {
            flex-direction: column;
        }

        .page-title {
            font-size: 1.75rem;
            text-align: center;
        }

        .product-cell {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .product-image {
            width: 40px;
            height: 40px;
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
                        <!-- Page Header -->
                        <div class="content-header">
                            <h4 class="page-title">Inventory Management</h4>
                            <a href="add-new-product.php" class="add-product-btn">
                                <i class="bx bx-plus"></i>Add New Product
                            </a>
                        </div>

                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="bx bx-check-circle"></i>
                            <span><?php echo $success_message; ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-error">
                            <i class="bx bx-error-circle"></i>
                            <span><?php echo $error_message; ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Inventory Card -->
                        <div class="inventory-card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bx bx-package"></i>Inventory List
                                    <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_status)): ?>
                                    <span class="filter-active-badge">
                                        <i class="bx bx-filter-alt"></i>Filters Applied
                                    </span>
                                    <?php endif; ?>
                                </h5>
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
                                        </option>
                                        <option value="low_stock"
                                            <?php echo ($stock_status == 'low_stock') ? 'selected' : ''; ?>>Low Stock
                                        </option>
                                        <option value="out_of_stock"
                                            <?php echo ($stock_status == 'out_of_stock') ? 'selected' : ''; ?>>Out of
                                            Stock</option>
                                    </select>

                                    <button type="submit" name="filter" class="filter-btn btn-primary">
                                        <i class="bx bx-filter-alt"></i>Filter
                                    </button>

                                    <?php if (!empty($search_term) || !empty($category_filter) || !empty($stock_status)): ?>
                                    <a href="inventory.php" class="filter-btn btn-outline">
                                        <i class="bx bx-x"></i>Reset
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
                                                    onchange="toggleAllCheckboxes()">
                                            </th>
                                            <th style="width: 250px;">Product</th>
                                            <th>Category</th>
                                            <th>Stock</th>
                                            <th>Total Price (RM)</th>
                                            <th>Status</th>
                                            <th style="width: 150px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="inventoryTableBody">
                                        <?php
                                        if (mysqli_num_rows($result) > 0) {
                                            while($row = mysqli_fetch_assoc($result)) {
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

                                                // Calculate the total price for the item
                                                $total_item_price = $row['price'] * $row['stock'];

                                                // Get product image path
                                                $product_image = getProductImage($row['image']);

                                                echo "<tr>";
                                                echo "<td><input type='checkbox' class='checkbox-input row-checkbox' value='" . $row['itemID'] . "'></td>";
                                                
                                                // Product cell with image
                                                echo "<td>
                                                        <div class='product-cell'>
                                                            <img src='" . htmlspecialchars($product_image) . "' 
                                                                 alt='" . htmlspecialchars($row['product_name']) . "' 
                                                                 class='product-image' 
                                                                 onclick='showImageModal(\"" . htmlspecialchars($product_image) . "\", \"" . htmlspecialchars($row['product_name']) . "\")'>
                                                            <div class='product-info'>
                                                                <div class='product-name'>" . htmlspecialchars($row['product_name']) . "</div>
                                                                <div class='product-id'>ID: " . $row['itemID'] . "</div>
                                                            </div>
                                                        </div>
                                                      </td>";
                                                
                                                echo "<td>" . htmlspecialchars($row['type_product']) . "</td>";
                                                echo "<td>" . $row['stock'] . "</td>";
                                                echo "<td>RM" . number_format($total_item_price, 2) . "</td>";
                                                echo "<td>
                                                            <span class='status-badge " . $status_class . "'>
                                                                <span class='status-indicator " . $indicator_class . "'></span>
                                                                " . $status_text . "
                                                            </span>
                                                        </td>";
                                                echo "<td class='action-buttons'>
                                                            <a href='add-new-product.php?edit=" . $row['itemID'] . "' class='action-btn'>
                                                                <i class='bx bx-edit'></i>Edit
                                                            </a>
                                                            <button class='action-btn delete' onclick=\"deleteProduct(" . $row['itemID'] . ", '" . addslashes($row['product_name']) . "')\">
                                                                <i class='bx bx-trash'></i>Delete
                                                            </button>
                                                        </td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr>
                                                        <td colspan='7'>
                                                            <div class='no-data'>
                                                                <i class='bx bx-search'></i>
                                                                <p>No products found matching your criteria.</p>
                                                                <a href='inventory.php' class='filter-btn btn-outline'>
                                                                    <i class='bx bx-x'></i> Clear Filters
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="pagination-wrapper">
                                <div class="pagination-info">
                                    <?php
                                    $start_item = ($current_page - 1) * $items_per_page + 1;
                                    $end_item = min($current_page * $items_per_page, $total_items);
                                    ?>
                                    Showing <?php echo $start_item; ?>-<?php echo $end_item; ?> of <?php echo $total_items; ?> results
                                </div>
                                <div class="pagination-controls">
                                    <?php
                                    // Build URL parameters for pagination links
                                    $url_params = [];
                                    if (!empty($search_term)) $url_params[] = "search=" . urlencode($search_term);
                                    if (!empty($category_filter)) $url_params[] = "category=" . urlencode($category_filter);
                                    if (!empty($stock_status)) $url_params[] = "stock_status=" . urlencode($stock_status);
                                    
                                    $base_url = "inventory.php";
                                    if (!empty($url_params)) {
                                        $base_url .= "?" . implode("&", $url_params);
                                        $separator = "&";
                                    } else {
                                        $separator = "?";
                                    }
                                    
                                    // Previous button
                                    if ($current_page > 1): ?>
                                        <a href="<?php echo $base_url . $separator . 'page=' . ($current_page - 1); ?>" class="page-btn">
                                            <i class="bx bx-chevron-left"></i> Previous
                                        </a>
                                    <?php else: ?>
                                        <button class="page-btn" disabled>
                                            <i class="bx bx-chevron-left"></i> Previous
                                        </button>
                                    <?php endif;
                                    
                                    // Page numbers
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    // Show first page if not in range
                                    if ($start_page > 1): ?>
                                        <a href="<?php echo $base_url . $separator . 'page=1'; ?>" class="page-btn">1</a>
                                        <?php if ($start_page > 2): ?>
                                            <span class="page-btn" style="border: none; background: none; cursor: default;">...</span>
                                        <?php endif;
                                    endif;
                                    
                                    // Show page numbers
                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <?php if ($i == $current_page): ?>
                                            <button class="page-btn active"><?php echo $i; ?></button>
                                        <?php else: ?>
                                            <a href="<?php echo $base_url . $separator . 'page=' . $i; ?>" class="page-btn"><?php echo $i; ?></a>
                                        <?php endif;
                                    endfor;
                                    
                                    // Show last page if not in range
                                    if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <span class="page-btn" style="border: none; background: none; cursor: default;">...</span>
                                        <?php endif; ?>
                                        <a href="<?php echo $base_url . $separator . 'page=' . $total_pages; ?>" class="page-btn"><?php echo $total_pages; ?></a>
                                    <?php endif;
                                    
                                    // Next button
                                    if ($current_page < $total_pages): ?>
                                        <a href="<?php echo $base_url . $separator . 'page=' . ($current_page + 1); ?>" class="page-btn">
                                            Next <i class="bx bx-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="page-btn" disabled>
                                            Next <i class="bx bx-chevron-right"></i>
                                        </button>
                                    <?php endif; ?>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="bx bx-error-circle" style="color: #ef4444; font-size: 1.5rem;"></i>
                <h3 class="modal-title">Confirm Deletion</h3>
            </div>
            <p id="deleteMessage">Are you sure you want to delete this product?</p>
            <div class="modal-actions">
                <button type="button" onclick="closeDeleteModal()" class="filter-btn btn-outline">
                    Cancel
                </button>
                <form id="deleteForm" method="POST" action="inventory.php" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteItemID" name="itemID" value="">
                    <button type="submit" class="filter-btn"
                        style="background-color: #ef4444; color: white; border: none;">
                        <i class="bx bx-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="image-modal-overlay" id="imageModal" onclick="closeImageModal()">
        <img class="image-modal-content" id="modalImage" src="" alt="">
    </div>

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

    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Page JS -->
    <script>
    // Toggle all checkboxes
    function toggleAllCheckboxes() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.getElementsByClassName('row-checkbox');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = selectAll.checked;
        }
    }

    // Delete product confirmation
    function deleteProduct(itemID, productName) {
        const modal = document.getElementById('deleteModal');
        const deleteMessage = document.getElementById('deleteMessage');
        const deleteItemIDInput = document.getElementById('deleteItemID');

        deleteMessage.innerHTML =
            `Are you sure you want to delete <strong>"${productName}"</strong>?<br>This action cannot be undone.`;
        deleteItemIDInput.value = itemID;

        modal.style.display = 'flex';
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        modal.style.display = 'none';
    }

    // Image modal functions
    function showImageModal(imageSrc, productName) {
        const modal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');
        
        modalImage.src = imageSrc;
        modalImage.alt = productName;
        modal.style.display = 'flex';
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        modal.style.display = 'none';
    }

    // Close modal if clicked outside
    window.onclick = function(event) {
        const deleteModal = document.getElementById('deleteModal');
        const imageModal = document.getElementById('imageModal');
        
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
        if (event.target === imageModal) {
            closeImageModal();
        }
    }

    // Reset filters function
    function resetFilters() {
        window.location.href = 'inventory.php';
    }

    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        });

        // Enhanced search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('filterForm').submit();
                }
            });
        }

        // Real-time filter feedback
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(function(select) {
            select.addEventListener('change', function() {
                // Optional: Auto-submit form on filter change
                // document.getElementById('filterForm').submit();
            });
        });

        // Add smooth transitions for cards
        const cards = document.querySelectorAll('.inventory-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Add hover effects for product images
        const productImages = document.querySelectorAll('.product-image');
        productImages.forEach(img => {
            img.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1)';
            });
            img.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    });

    // Bulk actions (for future enhancement)
    function getSelectedItems() {
        const checkboxes = document.getElementsByClassName('row-checkbox');
        const selected = [];
        for (let i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                selected.push(checkboxes[i].value);
            }
        }
        return selected;
    }

    // Export functionality (placeholder)
    function exportData() {
        const selected = getSelectedItems();
        if (selected.length > 0) {
            console.log('Exporting selected items:', selected);
            // Implement export functionality here
        } else {
            alert('Please select items to export.');
        }
    }

    // Keyboard navigation for image modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageModal();
            closeDeleteModal();
        }
    });
    </script>
</body>

</html>
