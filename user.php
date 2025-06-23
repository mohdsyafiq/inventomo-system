<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check if user is logged in - Updated to use consistent session variable
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-login-basic.html");
    exit();
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

// PAGINATION SETTINGS
$users_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $users_per_page;

// Initialize filter variables
$search_term = '';
$role_filter = '';
$status_filter = '';
$date_from = '';
$date_to = '';

// Initialize user variables with proper defaults
$current_user_id = $_SESSION['user_id'];
$current_user_name = "User";
$current_user_role = "user";
$current_user_avatar = "1.png";
$current_user_email = "";

// Helper function to get avatar background color based on position (same as index.php)
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

// Helper function to get profile picture path (same as index.php)
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

    // Session check and user profile link logic (following index.php pattern)
    if (isset($_SESSION['user_id']) && $conn) {
        $user_id = mysqli_real_escape_string($conn, $_SESSION['user_id']);

        // Fetch current user details from database (same query pattern as index.php)
        $user_query = "SELECT * FROM user_profiles WHERE Id = '$user_id' LIMIT 1";
        $user_result = mysqli_query($conn, $user_query);

        if ($user_result && mysqli_num_rows($user_result) > 0) {
            $user_data = mysqli_fetch_assoc($user_result);

            // Set user information (same pattern as index.php)
            $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
            $current_user_role = strtolower($user_data['position']); // Convert to lowercase for consistency
            $current_user_avatar = !empty($user_data['profile_picture']) ? $user_data['profile_picture'] : '1.png';
            $current_user_email = $user_data['email'];

            // Profile link goes to user-profile.php with their ID (same as index.php)
            $profile_link = "user-profile.php?op=view&Id=" . $user_data['Id'];
        }
    }

    // **ROLE-BASED ACCESS CONTROL**
    // Define allowed roles for this page
    $allowed_roles = ['admin', 'manager'];
    
    // Check if current user role is allowed to access this page
    if (!in_array($current_user_role, $allowed_roles)) {
        // Staff and other roles are not allowed - redirect to access denied or dashboard
        header("Location: access-denied.php?reason=insufficient_privileges");
        exit();
    }

    // Set permissions based on role
    $can_edit = ($current_user_role === 'admin');
    $can_delete = ($current_user_role === 'admin');
    $can_view = in_array($current_user_role, ['admin', 'manager']);
    $can_bulk_actions = ($current_user_role === 'admin');
    $can_export = in_array($current_user_role, ['admin', 'manager']);
    $can_add_user = ($current_user_role === 'admin');

    // Only process edit/delete operations if user has permission
    if ($can_edit || $can_delete) {
        // Process filter form submission
        if (isset($_GET['filter'])) {
            $search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
            $role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
            $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
            $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
            $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        }

        // Handle delete operation - Only for admin
        if($can_delete && isset($_GET['op']) && $_GET['op'] == 'delete' && isset($_GET['Id'])){
            $deleteId = sanitize_input($_GET['Id']);
            
            // Prevent admin from deleting themselves
            if ($deleteId === $current_user_id) {
                $error = "You cannot delete your own account.";
            } else {
                // Get profile picture before deleting user
                $get_picture_query = "SELECT profile_picture FROM user_profiles WHERE Id = ?";
                $stmt = mysqli_prepare($conn, $get_picture_query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 's', $deleteId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_bind_result($stmt, $profile_picture);
                    mysqli_stmt_fetch($stmt);
                    mysqli_stmt_close($stmt);
                    
                    // Delete the user
                    $deleteSql = "DELETE FROM user_profiles WHERE Id = ?";
                    $stmt = mysqli_prepare($conn, $deleteSql);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 's', $deleteId);
                        if(mysqli_stmt_execute($stmt)){
                            // Delete profile picture if exists
                            if (!empty($profile_picture) && $profile_picture != 'default.jpg') {
                                $file_to_delete = "uploads/photos/" . $profile_picture;
                                if (file_exists($file_to_delete)) {
                                    unlink($file_to_delete);
                                }
                            }
                            $success = "User deleted successfully!";
                        } else {
                            $error = "Error deleting user: " . mysqli_stmt_error($stmt);
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error = "Error preparing delete statement: " . mysqli_error($conn);
                    }
                }
            }
        }

        // Handle bulk delete operation - Only for admin
        if ($can_bulk_actions && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'bulk_delete') {
            if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
                $deleted_count = 0;
                $cannot_delete = [];
                
                foreach ($_POST['user_ids'] as $user_id) {
                    $user_id = sanitize_input($user_id);
                    
                    // Prevent admin from deleting themselves
                    if ($user_id === $current_user_id) {
                        $cannot_delete[] = "yourself";
                        continue;
                    }
                    
                    // Get profile picture before deleting
                    $get_picture_query = "SELECT profile_picture FROM user_profiles WHERE Id = ?";
                    $stmt = mysqli_prepare($conn, $get_picture_query);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 's', $user_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_bind_result($stmt, $profile_picture);
                        mysqli_stmt_fetch($stmt);
                        mysqli_stmt_close($stmt);
                        
                        // Delete the user
                        $delete_query = "DELETE FROM user_profiles WHERE Id = ?";
                        $stmt = mysqli_prepare($conn, $delete_query);
                        
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, 's', $user_id);
                            if (mysqli_stmt_execute($stmt)) {
                                // Delete profile picture if exists
                                if (!empty($profile_picture) && $profile_picture != 'default.jpg') {
                                    $file_to_delete = "uploads/photos/" . $profile_picture;
                                    if (file_exists($file_to_delete)) {
                                        unlink($file_to_delete);
                                    }
                                }
                                $deleted_count++;
                            }
                            mysqli_stmt_close($stmt);
                        }
                    }
                }
                
                if ($deleted_count > 0) {
                    $success = "Successfully deleted $deleted_count user(s)!";
                    if (!empty($cannot_delete)) {
                        $success .= " Note: Cannot delete " . implode(", ", $cannot_delete) . ".";
                    }
                } elseif (!empty($cannot_delete)) {
                    $error = "Cannot delete " . implode(", ", $cannot_delete) . ".";
                }
            }
        }

        // Handle status toggle operation - Only for admin
        if ($can_edit && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'toggle_status') {
            $user_id = sanitize_input($_POST['user_id']);
            $new_status = sanitize_input($_POST['status']);
            
            // Prevent admin from deactivating themselves
            if ($user_id === $current_user_id && $new_status === '0') {
                echo json_encode(['success' => false, 'message' => 'Cannot deactivate your own account']);
                exit();
            }
            
            $update_query = "UPDATE user_profiles SET active = ? WHERE Id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ss', $new_status, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => mysqli_stmt_error($stmt)]);
                }
                mysqli_stmt_close($stmt);
                exit();
            }
        }
    } else {
        // For managers, only allow filter form submission
        if (isset($_GET['filter'])) {
            $search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
            $role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
            $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
            $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
            $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        }
    }

    // Build base SQL query for counting total records
    $count_sql = "SELECT COUNT(*) as total FROM user_profiles WHERE 1=1";

    // Build SQL query with filters for pagination
    $sql = "SELECT * FROM user_profiles WHERE 1=1";

    // Add search filter if provided
    if (!empty($search_term)) {
        $search_condition = " AND (full_name LIKE '%$search_term%' OR username LIKE '%$search_term%' OR email LIKE '%$search_term%' OR Id LIKE '%$search_term%')";
        $sql .= $search_condition;
        $count_sql .= $search_condition;
    }

    // Add role filter if provided
    if (!empty($role_filter)) {
        $role_condition = " AND position = '$role_filter'";
        $sql .= $role_condition;
        $count_sql .= $role_condition;
    }

    // Add status filter if provided
    if (!empty($status_filter)) {
        if ($status_filter == 'active') {
            $status_condition = " AND (active = '1' OR active = 'Active')";
        } else {
            $status_condition = " AND (active = '0' OR active = 'Inactive' OR active IS NULL)";
        }
        $sql .= $status_condition;
        $count_sql .= $status_condition;
    }

    // Add date range filter if provided
    if (!empty($date_from) && !empty($date_to)) {
        $date_condition = " AND date_join BETWEEN '$date_from' AND '$date_to'";
        $sql .= $date_condition;
        $count_sql .= $date_condition;
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
    $total_pages = ceil($total_records / $users_per_page);
    $current_page = min($current_page, max(1, $total_pages)); // Ensure current page is valid

    // Add order by clause (newest first) and pagination
    $sql .= " ORDER BY date_join DESC LIMIT $offset, $users_per_page";

    // Execute main query
    $result = mysqli_query($conn, $sql);

    // Get all unique positions for filter dropdown
    $position_query = "SELECT DISTINCT position FROM user_profiles WHERE position IS NOT NULL AND position != '' ORDER BY position";
    $position_result = mysqli_query($conn, $position_query);
    $positions = [];

    if ($position_result) {
        while ($pos_row = mysqli_fetch_assoc($position_result)) {
            $positions[] = $pos_row['position'];
        }
        mysqli_free_result($position_result);
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

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to generate pagination URL with current filters
function getUserPaginationUrl($page, $search_term, $role_filter, $status_filter, $date_from, $date_to) {
    $params = array();
    $params['page'] = $page;
    
    if (!empty($search_term)) {
        $params['search'] = $search_term;
        $params['filter'] = '1';
    }
    if (!empty($role_filter)) {
        $params['role'] = $role_filter;
        $params['filter'] = '1';
    }
    if (!empty($status_filter)) {
        $params['status'] = $status_filter;
        $params['filter'] = '1';
    }
    if (!empty($date_from)) {
        $params['date_from'] = $date_from;
        $params['filter'] = '1';
    }
    if (!empty($date_to)) {
        $params['date_to'] = $date_to;
        $params['filter'] = '1';
    }
    
    return 'user.php?' . http_build_query($params);
}

?>

<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>User Management - Inventomo</title>

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

    /* Avatar styling matching index.php */
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

    .profile-link {
        text-decoration: none;
        color: inherit;
    }

    .profile-link:hover {
        color: inherit;
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

    /* Enhanced Filter Styles */
    .filter-section {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        padding: 1.5rem;
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
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

    .filter-active-badge {
        background-color: #e0f2fe;
        color: #0277bd;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        margin-left: 0.5rem;
        font-weight: 500;
    }

    /* Role-based styling */
    .role-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .role-badge.admin {
        background-color: #ff6b6b;
        color: white;
    }

    .role-badge.manager {
        background-color: #4ecdc4;
        color: white;
    }

    .role-badge.view-only {
        background-color: #ffa726;
        color: white;
    }

    /* Disabled state for non-admin users */
    .disabled-action {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    .status-badge.non-clickable {
        cursor: default !important;
    }

    /* Enhanced Pagination Styles */
    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #d9dee3;
        flex-wrap: wrap;
        gap: 1rem;
        background: white;
        padding: 1.5rem;
        border-radius: 0 0 0.5rem 0.5rem;
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

    .pagination-ellipsis {
        padding: 0.5rem 0.25rem;
        color: #9ca3af;
        font-weight: 600;
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

    /* Bulk Actions Styling */
    .bulk-actions-section {
        display: none;
        margin-top: 1rem;
        padding: 1rem;
        background-color: #f8f9fa;
        border-radius: 0.375rem;
        border: 1px solid #d9dee3;
    }

    .checkbox-input {
        width: 1rem;
        height: 1rem;
        cursor: pointer;
        accent-color: #696cff;
    }

    /* New user indicator */
    .new-user {
        border-left: 4px solid #10b981;
        background-color: #f0fdf4;
    }

    .new-user:hover {
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

    /* Responsive Design */
    @media (max-width: 768px) {
        .filter-section {
            flex-direction: column;
        }

        .search-input {
            min-width: auto;
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

        .page-btn {
            padding: 0.375rem 0.5rem;
            font-size: 0.8rem;
            min-width: 35px;
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

                    <li class="menu-item active">
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
                                    aria-label="Search..." id="navbar-search" />
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
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="page-title" style="color: white;">
                                <i class="bx bx-user"></i> User Management
                                <span class="role-badge <?php echo $current_user_role; ?>">
                                    <?php echo ucfirst($current_user_role); ?> <?php echo $can_edit ? '' : '(View Only)'; ?>
                                </span>
                            </h4>
                            <?php if ($can_add_user): ?>
                            <button class="btn btn-primary d-flex align-items-center gap-2"
                                onclick="window.location.href='user-profile.php'">
                                <i class="bx bx-plus"></i>
                                <span>Add New User</span>
                            </button>
                            <?php endif; ?>
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

                        <!-- Role-based access notification for managers -->
                        <?php if ($current_user_role === 'manager'): ?>
                        <div class="alert alert-info mb-4" role="alert">
                            <div class="d-flex">
                                <i class="bx bx-info-circle me-2 bx-sm"></i>
                                <div>
                                    <strong>Manager Access:</strong> You have view-only access to user management. Contact an administrator for user modifications.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Enhanced Filter Section -->
                        <form method="GET" action="user.php" id="filterForm">
                            <div class="filter-section">
                                <input type="text" name="search" class="filter-input search-input"
                                    placeholder="Search by name, username, email, or ID..."
                                    value="<?php echo htmlspecialchars($search_term); ?>" id="searchInput">

                                <select name="role" class="filter-select" id="roleFilter">
                                    <option value="">All Roles</option>
                                    <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo htmlspecialchars($position); ?>"
                                        <?php echo ($position == $role_filter) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($position)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="status" class="filter-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>
                                        Active
                                    </option>
                                    <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>
                                        Inactive
                                    </option>
                                </select>

                                <input type="date" name="date_from" class="filter-input" id="dateFromFilter"
                                    placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>">

                                <input type="date" name="date_to" class="filter-input" id="dateToFilter"
                                    placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>">

                                <button type="submit" name="filter" class="filter-btn btn-primary">
                                    <i class="bx bx-filter-alt"></i>Apply Filters
                                </button>

                                <?php if (!empty($search_term) || !empty($role_filter) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                                <a href="user.php" class="filter-btn btn-outline">
                                    <i class="bx bx-x"></i>Clear All
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- User List Card -->
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title mb-0">
                                            Users
                                            <?php if (!empty($search_term) || !empty($role_filter) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                                            <span class="filter-active-badge">
                                                <i class="bx bx-filter-alt"></i>Filters Applied
                                            </span>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="card-text text-muted">
                                            Total Users: <strong><?php echo number_format($total_records); ?></strong>
                                        </p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <?php if ($can_export): ?>
                                        <button class="btn btn-outline-primary btn-sm" onclick="exportUsers('csv')">
                                            <i class="bx bx-export"></i> Export CSV
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($can_bulk_actions): ?>
                                        <button class="btn btn-outline-danger btn-sm" onclick="bulkDeleteUsers()" 
                                                id="bulkDeleteBtn" style="display: none;">
                                            <i class="bx bx-trash"></i> Delete Selected
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive text-nowrap">
                                <table class="table table-hover" id="userTable">
                                    <thead>
                                        <tr>
                                            <?php if ($can_bulk_actions): ?>
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="checkbox-input" id="selectAll"
                                                    onchange="toggleAllCheckboxes()" title="Select All">
                                            </th>
                                            <?php endif; ?>
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
                                    <tbody class="table-border-bottom-0" id="userTableBody">
                                        <?php
                                        if ($result && mysqli_num_rows($result) > 0) {
                                            $row_number = $offset + 1;
                                            
                                            // Get the latest user ID to highlight new entries
                                            $latest_query = "SELECT MAX(Id) as latest_id FROM user_profiles";
                                            $latest_result = mysqli_query($conn, $latest_query);
                                            $latest_id = null;
                                            if ($latest_result) {
                                                $latest_row = mysqli_fetch_assoc($latest_result);
                                                $latest_id = $latest_row['latest_id'];
                                                mysqli_free_result($latest_result);
                                            }
                                            
                                            while($r2 = mysqli_fetch_assoc($result)) {
                                                $id = $r2['Id'];
                                                $username = $r2['username'];
                                                $email = $r2['email'];
                                                $position = $r2['position'];
                                                $date_join = date('M d, Y', strtotime($r2['date_join']));
                                                $full_name = isset($r2['full_name']) ? $r2['full_name'] : $username;
                                                $status = isset($r2['active']) ? $r2['active'] : '1';
                                                $profile_picture = isset($r2['profile_picture']) ? $r2['profile_picture'] : '';
                                                
                                                // Check if this is a new user (latest ID and on first page)
                                                $is_new_user = ($id == $latest_id && $current_page == 1);
                                                
                                                // Get profile picture path using the same function as index.php
                                                $profile_pic_path = getProfilePicture($profile_picture, $full_name);
                                                
                                                $row_class = $is_new_user ? 'new-user' : '';
                                        ?>
                                        <tr class="<?php echo $row_class; ?>" data-user-id="<?php echo htmlspecialchars($id); ?>">
                                            <?php if ($can_bulk_actions): ?>
                                            <td>
                                                <input type="checkbox" class="checkbox-input row-checkbox" 
                                                       name="user_ids[]" value="<?php echo htmlspecialchars($id); ?>" 
                                                       onchange="updateSelectAllState()"
                                                       <?php echo ($id === $current_user_id) ? 'disabled title="Cannot select your own account"' : ''; ?>>
                                            </td>
                                            <?php endif; ?>
                                            <td><?php echo $row_number++; ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($position); ?>">
                                                        <?php if ($profile_pic_path): ?>
                                                        <img src="<?php echo htmlspecialchars($profile_pic_path); ?>"
                                                            alt="Profile Picture">
                                                        <?php else: ?>
                                                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="user-details">
                                                        <div class="user-name">
                                                            <?php echo htmlspecialchars($full_name); ?>
                                                            <?php if ($is_new_user): ?>
                                                            <span class="new-badge">NEW</span>
                                                            <?php endif; ?>
                                                            <?php if ($id === $current_user_id): ?>
                                                            <span class="badge bg-label-primary ms-1" style="font-size: 0.6rem;">YOU</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="user-role">
                                                            <?php echo htmlspecialchars($position); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($id); ?></td>
                                            <td><?php echo htmlspecialchars($email); ?></td>
                                            <td>
                                                <span
                                                    class="text-capitalize"><?php echo htmlspecialchars($position); ?></span>
                                            </td>
                                            <td><?php echo $date_join; ?></td>
                                            <td>
                                                <span
                                                    class="badge bg-label-<?php echo ($status == '1' || $status == 'Active') ? 'success' : 'danger'; ?> badge-status <?php echo (!$can_edit || $id === $current_user_id) ? 'non-clickable' : ''; ?>"
                                                    <?php if ($can_edit && $id !== $current_user_id): ?>
                                                    onclick="toggleUserStatus('<?php echo htmlspecialchars($id); ?>', '<?php echo $status; ?>')"
                                                    style="cursor: pointer;" title="Click to toggle status"
                                                    <?php else: ?>
                                                    title="<?php echo ($id === $current_user_id) ? 'Cannot change your own status' : 'View only - Contact admin to change status'; ?>"
                                                    <?php endif; ?>>
                                                    <?php echo ($status == '1' || $status == 'Active') ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns d-flex justify-content-center">
                                                    <a href="user-profile.php?op=view&Id=<?php echo urlencode($id); ?>"
                                                        class="action-btn edit-btn" data-bs-toggle="tooltip"
                                                        data-bs-placement="top" 
                                                        title="<?php echo $can_edit ? 'View/Edit User' : 'View User Details'; ?>">
                                                        <i class="bx <?php echo $can_edit ? 'bx-edit-alt' : 'bx-show'; ?>"></i>
                                                    </a>
                                                    <?php if ($can_delete && $id !== $current_user_id): ?>
                                                    <button type="button" class="action-btn delete-btn"
                                                        onclick="deleteUser('<?php echo htmlspecialchars($id); ?>', '<?php echo htmlspecialchars($full_name); ?>')"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Delete User">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                    <?php elseif ($id === $current_user_id): ?>
                                                    <button type="button" class="action-btn disabled-action"
                                                        data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="Cannot delete your own account">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                            }
                                        } else {
                                        ?>
                                        <tr>
                                            <td colspan="<?php echo $can_bulk_actions ? '9' : '8'; ?>" class="text-center py-4">
                                                <div class="d-flex flex-column align-items-center">
                                                    <i class="bx bx-user-x mb-2"
                                                        style="font-size: 3rem; opacity: 0.5;"></i>
                                                    <h6 class="mb-1">No users found</h6>
                                                    <p class="text-muted mb-3">
                                                        <?php if (!empty($search_term) || !empty($role_filter) || !empty($status_filter)): ?>
                                                        No users found matching your search criteria.
                                                        <?php else: ?>
                                                        Start by adding a new user
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if (!empty($search_term) || !empty($role_filter) || !empty($status_filter)): ?>
                                                    <a href="user.php" class="btn btn-outline-primary btn-sm">
                                                        <i class="bx bx-x me-1"></i> Clear Filters
                                                    </a>
                                                    <?php elseif ($can_add_user): ?>
                                                    <a href="user-profile.php" class="btn btn-primary btn-sm">
                                                        <i class="bx bx-plus me-1"></i> Add New User
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
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
                                        <strong><?php echo min($offset + $users_per_page, $total_records); ?></strong> 
                                        of <strong><?php echo number_format($total_records); ?></strong> users
                                    </div>
                                    <div class="pagination-stats">
                                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                                        <?php if (!empty($search_term) || !empty($role_filter) || !empty($status_filter)): ?>
                                        | Filters applied
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="pagination-controls">
                                    <div class="pagination-nav">
                                        <!-- First Page -->
                                        <?php if ($current_page > 1): ?>
                                        <a href="<?php echo getUserPaginationUrl(1, $search_term, $role_filter, $status_filter, $date_from, $date_to); ?>" 
                                           class="page-btn" title="First Page">
                                            <i class="bx bx-chevrons-left"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <!-- Previous Page -->
                                        <?php if ($current_page > 1): ?>
                                        <a href="<?php echo getUserPaginationUrl($current_page - 1, $search_term, $role_filter, $status_filter, $date_from, $date_to); ?>" 
                                           class="page-btn" title="Previous Page">
                                            <i class="bx bx-chevron-left"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="page-btn" style="opacity: 0.5; cursor: not-allowed;" title="Previous Page">
                                            <i class="bx bx-chevron-left"></i>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <!-- Page Numbers -->
                                        <?php
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        // Show ellipsis at the end if needed
                                        if ($end_page < $total_pages) {
                                            if ($end_page < $total_pages - 1) {
                                                echo '<span class="pagination-ellipsis">...</span>';
                                            }
                                            echo '<a href="' . getUserPaginationUrl($total_pages, $search_term, $role_filter, $status_filter, $date_from, $date_to) . '" class="page-btn">' . $total_pages . '</a>';
                                        }
                                        ?>
                                        
                                        <!-- Next Page -->
                                        <?php if ($current_page < $total_pages): ?>
                                        <a href="<?php echo getUserPaginationUrl($current_page + 1, $search_term, $role_filter, $status_filter, $date_from, $date_to); ?>" 
                                           class="page-btn" title="Next Page">
                                            <i class="bx bx-chevron-right"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="page-btn" style="opacity: 0.5; cursor: not-allowed;" title="Next Page">
                                            <i class="bx bx-chevron-right"></i>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <!-- Last Page -->
                                        <?php if ($current_page < $total_pages): ?>
                                        <a href="<?php echo getUserPaginationUrl($total_pages, $search_term, $role_filter, $status_filter, $date_from, $date_to); ?>" 
                                           class="page-btn" title="Last Page">
                                            <i class="bx bx-chevrons-right"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Quick Page Jump -->
                                    <?php if ($total_pages > 5): ?>
                                    <div class="pagination-goto">
                                        <span>Go to page:</span>
                                        <input type="number" id="gotoPage" min="1" max="<?php echo $total_pages; ?>" 
                                               value="<?php echo $current_page; ?>">
                                        <button type="button" onclick="goToPage()">Go</button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Bulk Actions Section - Only for Admin -->
                            <?php if ($can_bulk_actions): ?>
                            <div id="bulkActionsSection" class="bulk-actions-section">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <span id="selectedCount" class="text-muted">0 users selected</span>
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
                            <?php endif; ?>
                        </div>
                        <!--/ User List Card -->
                    </div>
                    <!-- / Content -->

                    <!-- Delete User Modal - Only for Admin -->
                    <?php if ($can_delete): ?>
                    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Delete User</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p id="deleteMessage">Are you sure you want to delete this user?</p>
                                    <div class="alert alert-warning">
                                        <i class="bx bx-info-circle"></i>
                                        <strong>Warning:</strong> This action cannot be undone. The user and their profile picture will be permanently deleted.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Delete Modal - Only for Admin -->
                    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Delete Multiple Users</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p id="bulkDeleteMessage">Are you sure you want to delete the selected users?</p>
                                    <div class="alert alert-danger">
                                        <i class="bx bx-error-circle"></i>
                                        <strong>Critical Warning:</strong> This will permanently delete multiple users and cannot be undone.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmBulkDelete">Delete All Selected</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

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

    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Role-based JavaScript -->
    <script>
    // Global variables with role-based permissions
    let selectedUsers = [];
    let isSelectAllChecked = false;
    let deleteUserId = null;
    let deleteModal, bulkDeleteModal;
    
    // Role-based permissions from PHP
    const userPermissions = {
        canEdit: <?php echo json_encode($can_edit); ?>,
        canDelete: <?php echo json_encode($can_delete); ?>,
        canBulkActions: <?php echo json_encode($can_bulk_actions); ?>,
        canExport: <?php echo json_encode($can_export); ?>,
        canAddUser: <?php echo json_encode($can_add_user); ?>,
        userRole: '<?php echo $current_user_role; ?>',
        currentUserId: '<?php echo $current_user_id; ?>'
    };

    // Initialize page functionality
    document.addEventListener('DOMContentLoaded', function() {
        initializeUserManagement();
    });

    function initializeUserManagement() {
        // Initialize modals only if user has delete permissions
        if (userPermissions.canDelete) {
            deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            bulkDeleteModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
        }

        // Auto-dismiss alerts after 5 seconds
        dismissAlertsAfterDelay();

        // Enhanced search functionality
        setupSearchFunctionality();

        // Setup filter change handlers
        setupFilterHandlers();

        // Initialize tooltips
        initializeTooltips();

        // Setup keyboard shortcuts
        setupKeyboardShortcuts();

        // Highlight new users
        highlightNewUsers();

        // Setup event listeners
        setupEventListeners();

        // Show role-based welcome message
        showRoleBasedWelcome();

        console.log(`User Management page initialized for ${userPermissions.userRole} role`);
    }

    // Role-based welcome message
    function showRoleBasedWelcome() {
        const roleMessages = {
            'admin': 'Full administrative access enabled',
            'manager': 'Manager view-only access - Contact admin for modifications',
            'staff': 'Access restricted'
        };
        
        const message = roleMessages[userPermissions.userRole];
        if (message && userPermissions.userRole !== 'admin') {
            console.log(`Role: ${userPermissions.userRole} - ${message}`);
        }
    }

    // Alert management
    function dismissAlertsAfterDelay() {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                if (alert && alert.parentNode) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 500);
                }
            }, 5000);
        });
    }

    // Search functionality
    function setupSearchFunctionality() {
        const searchInput = document.getElementById('searchInput');
        const navbarSearch = document.getElementById('navbar-search');
        
        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('filterForm').submit();
                }
            });
        }

        if (navbarSearch) {
            navbarSearch.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    searchInput.value = this.value;
                    document.getElementById('filterForm').submit();
                }
            });
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

    // Event listeners setup
    function setupEventListeners() {
        // Only setup delete-related listeners for admin
        if (userPermissions.canDelete) {
            // Confirm delete button
            const confirmDeleteBtn = document.getElementById('confirmDelete');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', function() {
                    if (deleteUserId) {
                        window.location.href = 'user.php?op=delete&Id=' + encodeURIComponent(deleteUserId);
                    }
                    deleteModal.hide();
                });
            }

            // Confirm bulk delete button
            const confirmBulkDeleteBtn = document.getElementById('confirmBulkDelete');
            if (confirmBulkDeleteBtn) {
                confirmBulkDeleteBtn.addEventListener('click', function() {
                    confirmBulkDelete();
                });
            }
        }
    }

    // Highlight new users
    function highlightNewUsers() {
        const newUsers = document.querySelectorAll('.new-user');
        newUsers.forEach(function(user) {
            // Add a subtle animation to draw attention
            user.style.transition = 'all 0.3s ease';
            
            // Remove the highlight after 10 seconds
            setTimeout(function() {
                user.classList.remove('new-user');
                user.style.borderLeft = 'none';
                user.style.backgroundColor = 'white';
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

    // Checkbox management - Only for admin
    function toggleAllCheckboxes() {
        if (!userPermissions.canBulkActions) {
            showPermissionAlert('bulk selection');
            return;
        }
        
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.getElementsByClassName('row-checkbox');
        isSelectAllChecked = selectAll.checked;

        for (let i = 0; i < checkboxes.length; i++) {
            if (!checkboxes[i].disabled) {
                checkboxes[i].checked = isSelectAllChecked;
            }
        }

        updateSelectedUsers();
        updateBulkActionsVisibility();
    }

    function updateSelectAllState() {
        if (!userPermissions.canBulkActions) return;
        
        const checkboxes = document.getElementsByClassName('row-checkbox');
        const selectAll = document.getElementById('selectAll');
        let checkedCount = 0;
        let enabledCount = 0;

        for (let i = 0; i < checkboxes.length; i++) {
            if (!checkboxes[i].disabled) {
                enabledCount++;
                if (checkboxes[i].checked) {
                    checkedCount++;
                }
            }
        }

        if (selectAll) {
            selectAll.checked = checkedCount === enabledCount && enabledCount > 0;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < enabledCount;
        }

        updateSelectedUsers();
        updateBulkActionsVisibility();
    }

    function updateSelectedUsers() {
        if (!userPermissions.canBulkActions) return;
        
        const checkboxes = document.getElementsByClassName('row-checkbox');
        selectedUsers = [];

        for (let i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked && !checkboxes[i].disabled) {
                selectedUsers.push(checkboxes[i].value);
            }
        }

        // Update selected count display
        const selectedCountElement = document.getElementById('selectedCount');
        if (selectedCountElement) {
            const count = selectedUsers.length;
            selectedCountElement.textContent = count + ' user' + (count !== 1 ? 's' : '') + ' selected';
        }

        // Show/hide bulk delete button
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        if (bulkDeleteBtn) {
            bulkDeleteBtn.style.display = selectedUsers.length > 0 ? 'inline-block' : 'none';
        }
    }

    function updateBulkActionsVisibility() {
        if (!userPermissions.canBulkActions) return;
        
        const bulkActionsSection = document.getElementById('bulkActionsSection');
        if (bulkActionsSection) {
            bulkActionsSection.style.display = selectedUsers.length > 0 ? 'block' : 'none';
        }
    }

    function clearSelection() {
        if (!userPermissions.canBulkActions) {
            showPermissionAlert('bulk selection');
            return;
        }
        
        const checkboxes = document.getElementsByClassName('row-checkbox');
        const selectAll = document.getElementById('selectAll');

        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }

        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        
        selectedUsers = [];
        updateBulkActionsVisibility();
        
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        if (bulkDeleteBtn) {
            bulkDeleteBtn.style.display = 'none';
        }
    }

    // Delete functionality - Admin only
    function deleteUser(userId, userName) {
        if (!userPermissions.canDelete) {
            showPermissionAlert('delete users');
            return;
        }
        
        if (userId === userPermissions.currentUserId) {
            alert('You cannot delete your own account.');
            return;
        }
        
        const deleteMessage = document.getElementById('deleteMessage');
        deleteMessage.innerHTML = `Are you sure you want to delete <strong>"${escapeHtml(userName)}"</strong>?<br>This action cannot be undone and will remove the user from your system.`;
        deleteUserId = userId;
        deleteModal.show();
    }

    function deleteSelected() {
        if (!userPermissions.canBulkActions) {
            showPermissionAlert('bulk delete');
            return;
        }
        
        if (selectedUsers.length === 0) {
            alert('Please select users to delete.');
            return;
        }

        // Filter out current user from selection
        const validUsers = selectedUsers.filter(userId => userId !== userPermissions.currentUserId);
        
        if (validUsers.length === 0) {
            alert('Cannot delete your own account.');
            return;
        }

        const deleteMessage = document.getElementById('bulkDeleteMessage');
        deleteMessage.innerHTML = `Are you sure you want to delete <strong>${validUsers.length}</strong> selected user${validUsers.length !== 1 ? 's' : ''}?<br>This action cannot be undone.`;
        bulkDeleteModal.show();
    }

    function confirmBulkDelete() {
        if (!userPermissions.canBulkActions) {
            showPermissionAlert('bulk delete');
            return;
        }
        
        const validUsers = selectedUsers.filter(userId => userId !== userPermissions.currentUserId);
        
        if (validUsers.length === 0) {
            return;
        }

        // Create form for bulk delete
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'user.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_delete';
        form.appendChild(actionInput);

        validUsers.forEach(userId => {
            const userInput = document.createElement('input');
            userInput.type = 'hidden';
            userInput.name = 'user_ids[]';
            userInput.value = userId;
            form.appendChild(userInput);
        });

        document.body.appendChild(form);
        form.submit();
    }

    function bulkDeleteUsers() {
        deleteSelected();
    }

    // Export functionality
    function exportUsers(format) {
        if (!userPermissions.canExport) {
            showPermissionAlert('export users');
            return;
        }
        
        console.log('Exporting users in format:', format);
        alert(`Feature coming soon: Export users to ${format.toUpperCase()}`);
    }

    function exportSelected() {
        if (!userPermissions.canExport) {
            showPermissionAlert('export users');
            return;
        }
        
        if (selectedUsers.length === 0) {
            alert('Please select users to export.');
            return;
        }

        console.log('Exporting selected users:', selectedUsers);
        alert(`Feature coming soon: Export ${selectedUsers.length} selected users to CSV/Excel`);
    }

    // User status toggle - Admin only
    function toggleUserStatus(userId, currentStatus) {
        if (!userPermissions.canEdit) {
            showPermissionAlert('change user status');
            return;
        }
        
        if (userId === userPermissions.currentUserId) {
            alert('You cannot change your own account status.');
            return;
        }
        
        const newStatus = (currentStatus == '1' || currentStatus == 'Active') ? '0' : '1';
        const statusText = newStatus == '1' ? 'activate' : 'deactivate';

        if (confirm(`Are you sure you want to ${statusText} this user?`)) {
            fetch('user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_status&user_id=${encodeURIComponent(userId)}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating user status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating user status');
            });
        }
    }

    // Permission alert helper
    function showPermissionAlert(action) {
        const roleMessage = userPermissions.userRole === 'manager' 
            ? 'Managers have view-only access. Contact an administrator to ' + action + '.'
            : 'You do not have permission to ' + action + '.';
        
        alert(roleMessage);
    }

    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function resetFilters() {
        window.location.href = 'user.php';
    }

    // Keyboard shortcuts
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Don't trigger if user is typing in an input field
            if (e.target.matches('input, textarea, select')) {
                return;
            }

            // Escape key closes modals
            if (e.key === 'Escape') {
                if (deleteModal && deleteModal._isShown) {
                    deleteModal.hide();
                }
                if (bulkDeleteModal && bulkDeleteModal._isShown) {
                    bulkDeleteModal.hide();
                }
            }

            // Ctrl/Cmd + A selects all (only for admin)
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && userPermissions.canBulkActions) {
                e.preventDefault();
                const selectAll = document.getElementById('selectAll');
                if (selectAll) {
                    selectAll.checked = !selectAll.checked;
                    toggleAllCheckboxes();
                }
            }

            // Ctrl/Cmd + F focuses search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // Ctrl/Cmd + N for new user (only for admin)
            if ((e.ctrlKey || e.metaKey) && e.key === 'n' && userPermissions.canAddUser) {
                e.preventDefault();
                window.location.href = 'user-profile.php';
            }

            // Delete key for selected users (only for admin)
            if (e.key === 'Delete' && selectedUsers.length > 0 && userPermissions.canBulkActions) {
                e.preventDefault();
                deleteSelected();
            }

            // Pagination shortcuts
            const currentPage = <?php echo $current_page; ?>;
            const totalPages = <?php echo $total_pages; ?>;
            
            // Left arrow or 'A' key for previous page
            if ((e.key === 'ArrowLeft' || e.key.toLowerCase() === 'a') && currentPage > 1) {
                e.preventDefault();
                window.location.href = '<?php echo getUserPaginationUrl($current_page - 1, $search_term, $role_filter, $status_filter, $date_from, $date_to); ?>';
            }
            
            // Right arrow or 'D' key for next page
            if ((e.key === 'ArrowRight' || e.key.toLowerCase() === 'd') && currentPage < totalPages) {
                e.preventDefault();
                window.location.href = '<?php echo getUserPaginationUrl($current_page + 1, $search_term, $role_filter, $status_filter, $date_from, $date_to); ?>';
            }
        });
    }

    // Initialize tooltips
    function initializeTooltips() {
        // Initialize Bootstrap tooltips if available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    delay: {
                        show: 500,
                        hide: 100
                    }
                });
            });
        }
    }

    // Public API for external scripts
    window.UserManagement = {
        refreshData: function() {
            window.location.reload();
        },
        clearFilters: resetFilters,
        exportData: exportUsers,
        getSelectedUsers: function() {
            return selectedUsers.slice(); // Return copy
        },
        selectAll: userPermissions.canBulkActions ? toggleAllCheckboxes : () => showPermissionAlert('bulk selection'),
        clearSelection: userPermissions.canBulkActions ? clearSelection : () => showPermissionAlert('bulk selection'),
        toggleUserStatus: userPermissions.canEdit ? toggleUserStatus : () => showPermissionAlert('change user status'),
        getUserPermissions: function() {
            return {...userPermissions}; // Return copy
        }
    };

    console.log(`Enhanced User Management System with Role-Based Access Control v3.0 - ${userPermissions.userRole.toUpperCase()} MODE ✓`);
    </script>

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

// Log page access with role information
if (isset($_SESSION['user_id'])) {
    error_log("User " . $_SESSION['user_id'] . " (" . $current_user_role . ") accessed user management page " . $current_page . " at " . date('Y-m-d H:i:s'));
}
?> 