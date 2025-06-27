<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
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
function getProfilePicture($profile_picture_name, $avatar_path) { // Pass $avatar_path explicitly
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
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Ensure proper HTML encoding

    // Only apply mysqli_real_escape_string if connection is established and data is a string
    // IMPORTANT: For IDs, it's safer to cast to int directly, especially if binding as 'i'.
    // This function is general, so keep it for strings. For IDs, cast (int) directly.
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
        case 'active': return 'bx-check-circle';
        case 'inactive': return 'bx-x-circle';
        case 'pending': return 'bx-time-five';
        default: return 'bx-help-circle';
    }
}

function getTypeIcon($type) {
    switch(strtolower($type)) {
        case 'customer': return 'bx-user';
        case 'supplier': return 'bx-store';
        default: return 'bx-user-circle';
    }
}

// Country code to full name mapping (made global)
$country_names = [
    'MY' => 'Malaysia', 'SG' => 'Singapore', 'TH' => 'Thailand', 'ID' => 'Indonesia',
    'VN' => 'Vietnam', 'PH' => 'Philippines', 'US' => 'United States', 'GB' => 'United Kingdom',
    'AU' => 'Australia', 'IN' => 'India', 'CN' => 'China', 'JP' => 'Japan', 'KR' => 'South Korea',
    'other' => 'Other'
];

function getCountryName($code, $country_names) {
    return isset($country_names[$code]) ? $country_names[$code] : $code;
}


// --- BEGIN SCRIPT EXECUTION ---
// Attempt to establish database connection once at the start
try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);

    if (!$conn) {
        // If connection fails, throw an exception
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }

    // Set charset for the connection
    mysqli_set_charset($conn, "utf8");

    // Check if user is logged in, if not, redirect to login page
    if (!isset($_SESSION['user_id']) || $_SESSION["loggedin"] !== true) {
        header("Location: auth-login-basic.html");
        exit();
    }

    // Initialize user variables
    $profile_link = "#";
    $current_user_name = "User";
    $current_user_role = "User";
    $current_user_avatar = "1.png";
    $avatar_path = "uploads/photos/";

    // Session check and user profile link logic
    if (isset($_SESSION['user_id'])) {
        $user_id_from_session = $_SESSION['user_id'];
        // Use prepared statement for fetching user details
        $user_query_stmt = mysqli_prepare($conn, "SELECT id, full_name, username, position, profile_picture FROM user_profiles WHERE id = ? LIMIT 1");
        if ($user_query_stmt) {
            mysqli_stmt_bind_param($user_query_stmt, "i", $user_id_from_session); // Assuming user_id is int
            mysqli_stmt_execute($user_query_stmt);
            $user_result = mysqli_stmt_get_result($user_query_stmt);

            if ($user_result && mysqli_num_rows($user_result) > 0) {
                $user_data = mysqli_fetch_assoc($user_result);

                $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
                $current_user_role = $user_data['position'];

                if (!empty($user_data['profile_picture'])) {
                    $profile_pic_name = $user_data['profile_picture'];
                    if (file_exists($avatar_path . $profile_pic_name)) {
                        $current_user_avatar = $profile_pic_name;
                    } else {
                        $current_user_avatar = '1.png';
                    }
                } else {
                    $current_user_avatar = '1.png';
                }

                $profile_link = "user-profile.php?op=view&Id=" . $user_data['id']; // Use lowercase 'id'
            }
            mysqli_stmt_close($user_query_stmt);
        }
    }

    // Define avatar URL for display
    $current_user_avatar_url = getProfilePicture($current_user_avatar, $avatar_path);


    $success_message = '';
    $error_message = '';

    // --- AJAX Endpoint for Fetching Single Record Details ---
    // This block should run early, before any HTML output, if it's an AJAX call
    if (isset($_GET['action']) && $_GET['action'] == 'fetch_details') {
        if (isset($_GET['id'])) {
            $registrationID = (int)$_GET['id']; // Cast to int for ID

            // Ensure all columns expected by the modal are selected
            $fetch_detail_query = "SELECT registrationID, firstName, lastName, companyName, email, phone, registrationType, status, country, businessType, industry, dateRegistered, address, city, postcode, state, notes
                                 FROM customer_supplier WHERE registrationID = ?";
            $stmt = mysqli_prepare($conn, $fetch_detail_query);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $registrationID); // Bind as integer
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                header('Content-Type: application/json');
                echo json_encode($data);
                exit; // IMPORTANT: Exit here to prevent rendering full HTML for AJAX
            } else {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Error preparing statement for details fetch: ' . mysqli_error($conn)]);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing registration ID for details fetch.']);
            exit;
        }
    }


    // --- Form Submission Handlers (POST requests) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['action']) && $_POST['action'] == 'delete') {
            $registrationID = (int)$_POST['registrationID']; // Cast to int

            $delete_query = "DELETE FROM customer_supplier WHERE registrationID = ?";
            $stmt = mysqli_prepare($conn, $delete_query);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $registrationID); // Bind as integer
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Record has been deleted successfully.";
                } else {
                    $error_message = "Database Error: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Error preparing statement: " . mysqli_error($conn);
            }
        } elseif (isset($_POST['action']) && $_POST['action'] == 'bulk_delete') {
            if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
                // Ensure IDs are integers for security
                $selected_ids = array_map('intval', $_POST['selected_ids']); // Use intval for array elements
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

                $delete_query = "DELETE FROM customer_supplier WHERE registrationID IN ($placeholders)";
                $stmt = mysqli_prepare($conn, $delete_query);

                if ($stmt) {
                    // Create type string for bind_param (e.g., 'iii' for 3 integers)
                    $types = str_repeat('i', count($selected_ids));
                    mysqli_stmt_bind_param($stmt, $types, ...$selected_ids);

                    if (mysqli_stmt_execute($stmt)) {
                        $deleted_count = mysqli_stmt_affected_rows($stmt);
                        $success_message = "Successfully deleted $deleted_count record(s).";
                    } else {
                        $error_message = "Database Error: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_message = "Error preparing statement: " . mysqli_error($conn);
                }
            }
        } elseif (isset($_POST['action']) && $_POST['action'] == 'export') {
            $export_format = sanitize_input($_POST['export_format']);

            $export_query_conditions = " WHERE 1=1";
            // Retrieve filter terms from POST and sanitize them
            $current_search_term = isset($_POST['search']) ? sanitize_input($_POST['search']) : '';
            $current_type_filter = isset($_POST['type']) ? sanitize_input($_POST['type']) : '';
            $current_status_filter = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';
            $current_country_filter = isset($_POST['country']) ? sanitize_input($_POST['country']) : '';

            if (!empty($current_search_term)) {
                $export_query_conditions .= " AND (firstName LIKE '%" . $current_search_term . "%' OR lastName LIKE '%" . $current_search_term . "%'
                                     OR companyName LIKE '%" . $current_search_term . "%' OR email LIKE '%" . $current_search_term . "%'
                                     OR registrationID LIKE '%" . $current_search_term . "%')";
            }
            if (!empty($current_type_filter)) {
                $export_query_conditions .= " AND registrationType = '" . $current_type_filter . "'";
            }
            if (!empty($current_status_filter)) {
                $export_query_conditions .= " AND status = '" . $current_status_filter . "'";
            }
            if (!empty($current_country_filter)) {
                $export_query_conditions .= " AND country = '" . $current_country_filter . "'";
            }

            $export_query = "SELECT * FROM customer_supplier" . $export_query_conditions . " ORDER BY dateRegistered DESC";

            // This is where actual export logic would go (e.g., generate Excel/CSV/PDF file)
            $success_message = "Export initiated for " . htmlspecialchars($export_format) . " format. File will be downloaded shortly.";
        }
    }

    // Initialize filter variables (for GET requests/display)
    $search_term = '';
    $type_filter = '';
    $status_filter = '';
    $country_filter = '';

    // Process filter form submission (GET request for initial page load and filters)
    if (isset($_GET['filter'])) {
        $search_term = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
        $type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
        $status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
        $country_filter = isset($_GET['country']) ? sanitize_input($_GET['country']) : '';
    }

    // Build main SQL query with filters for table display
    $sql = "SELECT registrationID, firstName, lastName, COALESCE(companyName, 'N/A') AS companyName, email, phone, registrationType,
                     status, country, businessType, industry, dateRegistered
             FROM customer_supplier WHERE 1=1"; // Added COALESCE to companyName

    // Add search filter if provided
    if (!empty($search_term)) {
        $sql .= " AND (firstName LIKE '%" . $search_term . "%' OR lastName LIKE '%" . $search_term . "%'
                     OR companyName LIKE '%" . $search_term . "%' OR email LIKE '%" . $search_term . "%'
                     OR registrationID LIKE '%" . $search_term . "%')";
    }

    // Add type filter if provided
    if (!empty($type_filter)) {
        $sql .= " AND registrationType = '" . $type_filter . "'";
    }

    // Add status filter if provided
    if (!empty($status_filter)) {
        $sql .= " AND status = '" . $status_filter . "'";
    }

    // Add country filter if provided
    if (!empty($country_filter)) {
        $sql .= " AND country = '" . $country_filter . "'";
    }

    // Add order by clause
    $sql .= " ORDER BY dateRegistered DESC";

    // Execute main query for table data
    $result = mysqli_query($conn, $sql);

    // Check if query was successful
    if (!$result) {
        $error_message = "Query failed: " . mysqli_error($conn);
        // Create empty result set for display to prevent further errors
        $result = mysqli_query($conn, "SELECT * FROM customer_supplier WHERE 1=0");
    }

    // Get counts for different types and statuses (apply filters to counts as well)
    $count_query = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN registrationType = 'customer' THEN 1 ELSE 0 END) as customers,
            SUM(CASE WHEN registrationType = 'supplier' THEN 1 ELSE 0 END) as suppliers,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
        FROM customer_supplier"; // Removed pending_count from here

    if (!empty($search_term) || !empty($type_filter) || !empty($status_filter) || !empty($country_filter)) {
        $count_query .= " WHERE 1=1";
        if (!empty($search_term)) {
            $count_query .= " AND (firstName LIKE '%" . $search_term . "%' OR lastName LIKE '%" . $search_term . "%'
                                 OR companyName LIKE '%" . $search_term . "%' OR email LIKE '%" . $search_term . "%'
                                 OR registrationID LIKE '%" . $search_term . "%')";
        }
        if (!empty($type_filter)) {
            $count_query .= " AND registrationType = '" . $type_filter . "'";
        }
        if (!empty($status_filter)) {
            $count_query .= " AND status = '" . $status_filter . "'";
        }
        if (!empty($country_filter)) {
            $count_query .= " AND country = '" . $current_country_filter . "'";
        }
    }

    $count_result = mysqli_query($conn, $count_query);
    $counts = mysqli_fetch_assoc($count_result);

    // Get all distinct countries for the filter dropdown
    $country_query = "SELECT DISTINCT country FROM customer_supplier ORDER BY country";
    $country_result = mysqli_query($conn, $country_query);
    $countries = [];

    if ($country_result) {
        while ($country_row = mysqli_fetch_assoc($country_result)) {
            if (!empty($country_row['country'])) {
                $countries[] = $country_row['country'];
            }
        }
        mysqli_free_result($country_result);
    }


} catch (Exception $e) {
    // Catch database connection errors or other exceptions
    $error_message = "Application Error: " . $e->getMessage();
    // Ensure $conn is null if connection failed, to prevent mysqli_close on non-object
    $conn = null;
} finally {
    // Close database connection if it was successfully opened
    if ($conn && is_object($conn)) { // Ensure $conn is an object before trying to close
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

    <title>Customer & Supplier Management - Inventomo</title>

    <meta name="description" content="Customer and Supplier Management System" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
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

    .header-actions {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }

    .add-btn {
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
        gap: 0.25rem;
        transition: all 0.2s;
    }

    .add-btn:hover {
        background-color: #5f63f2;
        color: white;
    }

    .export-btn {
        padding: 0.5rem 1rem;
        background-color: #28a745;
        border: none;
        border-radius: 0.375rem;
        cursor: pointer;
        font-size: 0.875rem;
        color: white;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: all 0.2s;
    }

    .export-btn:hover {
        background-color: #218838;
        color: white;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: white;
        padding: 1.25rem;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        border-left: 4px solid #696cff;
    }

    .stat-card.customer {
        border-left-color: #28a745;
    }

    .stat-card.supplier {
        border-left-color: #ffc107;
    }

    .stat-card.pending { /* This rule is now orphaned as the 'pending' card is removed */
        border-left-color: #fd7e14;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #566a7f;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: #6c757d;
        font-weight: 500;
    }

    .filter-tabs {
        display: flex;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid #d9dee3;
        background: white;
        border-radius: 0.5rem 0.5rem 0 0;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
    }

    .filter-tab {
        padding: 0.875rem 1.5rem;
        border: none;
        background: none;
        cursor: pointer;
        color: #566a7f;
        font-weight: 500;
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
        position: relative;
    }

    .filter-tab:hover {
        color: #696cff;
        background-color: #f8f9fa;
    }

    .filter-tab.active {
        color: #696cff;
        border-bottom-color: #696cff;
        background-color: #f8f9fa;
    }

    .filter-count {
        background-color: #e9ecef;
        color: #495057;
        padding: 0.125rem 0.375rem;
        border-radius: 0.75rem;
        font-size: 0.75rem;
        margin-left: 0.5rem;
    }

    .filter-tab.active .filter-count {
        background-color: #696cff;
        color: white;
    }

    .filters-section {
        background: white;
        padding: 1.5rem;
        border-radius: 0 0 0.5rem 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        margin-bottom: 1.5rem;
    }

    .filter-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .filter-input {
        padding: 0.5rem 0.75rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: #566a7f;
        background-color: white;
        transition: border-color 0.2s;
    }

    .filter-input:focus {
        outline: none;
        border-color: #696cff;
        box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
    }

    .filter-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 0.5rem 1rem;
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        cursor: pointer;
        font-size: 0.875rem;
        color: #566a7f;
        background-color: white;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: all 0.2s;
    }

    .filter-btn:hover {
        background-color: #f5f5f9;
        border-color: #696cff;
        color: #696cff;
    }

    .filter-btn.primary {
        background-color: #696cff;
        color: white;
        border-color: #696cff;
    }

    .filter-btn.primary:hover {
        background-color: #5f63f2;
    }

    .filter-btn.danger {
        background-color: #ff3e1d;
        color: white;
        border-color: #ff3e1d;
    }

    .filter-btn.danger:hover {
        background-color: #e5381a;
    }

    .filter-btn.success {
        background-color: #28a745;
        color: white;
        border-color: #28a745;
    }

    .filter-btn.success:hover {
        background-color: #218838;
    }

    .data-table-container {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        overflow: hidden;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #d9dee3;
    }

    .data-table th {
        background-color: #f5f5f9;
        font-weight: 600;
        color: #566a7f;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .data-table td {
        color: #566a7f;
        font-size: 0.875rem;
    }

    .data-table tbody tr:hover {
        background-color: #f5f5f9;
    }

    .checkbox-input {
        width: 1rem;
        height: 1rem;
        cursor: pointer;
        accent-color: #696cff;
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .action-button {
        width: 2rem;
        height: 2rem;
        border: 1px solid #d9dee3;
        background-color: white;
        cursor: pointer;
        border-radius: 0.375rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        color: #566a7f;
        transition: all 0.2s ease;
    }

    .action-button:hover {
        background-color: #f5f5f9;
        border-color: #696cff;
        color: #696cff;
    }

    .action-button.danger:hover {
        background-color: #ffe5e5;
        border-color: #ff3e1d;
        color: #ff3e1d;
    }

    .action-button.info:hover {
        background-color: #e5f3ff;
        border-color: #17a2b8;
        color: #17a2b8;
    }

    .badge {
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .badge.customer {
        background-color: #e7e7ff;
        color: #696cff;
    }

    .badge.supplier {
        background-color: #fff3cd;
        color: #856404;
    }

    .badge.active {
        background-color: #d4edda;
        color: #155724;
    }

    .badge.inactive {
        background-color: #f8d7da;
        color: #721c24;
    }

    .badge.pending { /* This CSS rule is now orphaned */
        background-color: #fff3cd;
        color: #856404;
    }

    .alert {
        padding: 1rem;
        border-radius: 0.375rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .no-results {
        text-align: center;
        padding: 3rem 2rem;
        color: #6c757d;
    }

    .no-results i {
        font-size: 3rem;
        color: #d9dee3;
        margin-bottom: 1rem;
    }

    .pagination-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding: 1rem 0;
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
        padding: 1rem 1.5rem;
    }

    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .loading-spinner {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #696cff;
        font-weight: 500;
        background: white;
        padding: 1rem 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    /* Profile Avatar Styles (for navbar and dropdown) */
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
        .filter-row {
            grid-template-columns: 1fr;
        }

        .filter-actions {
            flex-direction: column;
        }

        .content-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }

        .header-actions {
            width: 100%;
            justify-content: stretch;
        }

        .filter-tabs {
            flex-wrap: wrap;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .data-table-container {
            overflow-x: auto;
        }

        .data-table {
            min-width: 800px;
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
                    <li class="menu-item active">
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
                            <!-- User Dropdown -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                        <?php if ($current_user_avatar_url): ?>
                                            <img src="<?php echo htmlspecialchars($current_user_avatar_url); ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo $profile_link; ?>">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                                        <?php if ($current_user_avatar_url): ?>
                                                            <img src="<?php echo htmlspecialchars($current_user_avatar_url); ?>" alt="Profile Picture">
                                                        <?php else: ?>
                                                            <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                                        <?php endif; ?>
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
                        <!-- Page Header -->
                        <div class="content-header">
                            <h4 class="page-title">Customer & Supplier Management</h4>
                            <div class="header-actions">
                                <button class="export-btn" onclick="showExportModal()">
                                    <i class="bx bx-download"></i>Export
                                </button>
                                <a href="register-customer-supplier.php" class="add-btn">
                                    <i class="bx bx-plus"></i>Add New
                                </a>
                            </div>
                        </div>

                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bx bx-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bx bx-error-circle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Statistics Grid -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $counts['total']; ?></div>
                                <div class="stat-label">Total Records</div>
                            </div>
                            <div class="stat-card customer">
                                <div class="stat-value"><?php echo $counts['customers']; ?></div>
                                <div class="stat-label">Customers</div>
                            </div>
                            <div class="stat-card supplier">
                                <div class="stat-value"><?php echo $counts['suppliers']; ?></div>
                                <div class="stat-label">Suppliers</div>
                            </div>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="filter-tabs">
                            <button class="filter-tab <?php echo empty($type_filter) ? 'active' : ''; ?>"
                                onclick="filterByType('')">
                                ALL <span class="filter-count"><?php echo $counts['total']; ?></span>
                            </button>
                            <button class="filter-tab <?php echo $type_filter === 'customer' ? 'active' : ''; ?>"
                                onclick="filterByType('customer')">
                                CUSTOMERS <span class="filter-count"><?php echo $counts['customers']; ?></span>
                            </button>
                            <button class="filter-tab <?php echo $type_filter === 'supplier' ? 'active' : ''; ?>"
                                onclick="filterByType('supplier')">
                                SUPPLIERS <span class="filter-count"><?php echo $counts['suppliers']; ?></span>
                            </button>
                        </div>

                        <!-- Filters Section -->
                        <form method="GET" class="filters-section" id="filterForm">
                            <div class="filter-row">
                                <input type="text" class="filter-input" name="search"
                                    placeholder="Search by Name, Email, Company, or ID..."
                                    value="<?php echo htmlspecialchars($search_term); ?>">

                                <select class="filter-input" name="type">
                                    <option value="">All Types</option>
                                    <option value="customer"
                                        <?php echo $type_filter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                                    <option value="supplier"
                                        <?php echo $type_filter === 'supplier' ? 'selected' : ''; ?>>Supplier</option>
                                </select>

                                <select class="filter-input" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>
                                        Active</option>
                                    <option value="inactive"
                                        <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>

                                <select class="filter-input" name="country">
                                    <option value="">All Countries</option>
                                    <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country; ?>"
                                        <?php echo $country_filter === $country ? 'selected' : ''; ?>>
                                        <?php echo getCountryName($country, $country_names); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-actions">
                                <button type="submit" name="filter" class="filter-btn primary">
                                    <i class="bx bx-filter-alt"></i>Apply Filters
                                </button>
                                <a href="?" class="filter-btn">
                                    <i class="bx bx-refresh"></i>Reset Filters
                                </a>
                                <button type="button" class="filter-btn success" onclick="refreshData()">
                                    <i class="bx bx-sync"></i>Refresh Data
                                </button>
                                <button type="button" class="filter-btn danger" onclick="bulkDelete()"
                                    id="bulkDeleteBtn" style="display: none;">
                                    <i class="bx bx-trash"></i>Delete Selected
                                </button>
                            </div>
                        </form>

                        <!-- Data Table -->
                        <div class="data-table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" class="checkbox-input" id="selectAll">
                                        </th>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Company</th>
                                        <th>Type</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Country</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($result) && mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="checkbox-input row-checkbox"
                                                value="<?php echo htmlspecialchars($row['registrationID']); ?>">
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($row['registrationID']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['companyName']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['registrationType']; ?>">
                                                <i class="bx <?php echo getTypeIcon($row['registrationType']); ?>"></i>
                                                <?php echo ucfirst($row['registrationType']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                        <td><?php echo getCountryName($row['country'], $country_names); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['status']; ?>">
                                                <i class="bx <?php echo getStatusIcon($row['status']); ?>"></i>
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($row['dateRegistered']); ?></td>
                                        <td class="action-buttons">
                                            <button class="action-button info"
                                                onclick="viewRecord('<?php echo htmlspecialchars($row['registrationID']); ?>')"
                                                title="View Details">
                                                <i class="bx bx-show"></i>
                                            </button>
                                            <button class="action-button"
                                                onclick="editRecord('<?php echo htmlspecialchars($row['registrationID']); ?>')"
                                                title="Edit">
                                                <i class="bx bx-edit"></i>
                                            </button>
                                            <button class="action-button danger"
                                                onclick="deleteRecord('<?php echo htmlspecialchars($row['registrationID']); ?>')"
                                                title="Delete">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="no-results">
                                            <i class="bx bx-search-alt-2"></i>
                                            <h5>No records found</h5>
                                            <p>Try adjusting your search or filter criteria</p>
                                            <a href="register-customer-supplier.php" class="add-btn" style="margin-top: 1rem;">
                                                <i class="bx bx-plus"></i>Add First Record
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="pagination-section">
                            <div class="pagination-info">
                                <span class="text-muted">
                                    Showing <?php echo isset($result) ? mysqli_num_rows($result) : 0; ?>
                                    of <?php echo $counts['total']; ?> entries
                                    <?php if (!empty($search_term) || !empty($type_filter) || !empty($status_filter) || !empty($country_filter)): ?>
                                    (filtered)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-muted">Last updated: <?php echo date('M d, Y \a\t H:i'); ?></span>
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

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span>Loading...</span>
        </div>
    </div>


    <!-- Delete confirmation form (hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="registrationID" id="deleteRegistrationID">
    </form>

    <!-- Bulk delete form (hidden) -->
    <form id="bulkDeleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="bulk_delete">
        <div id="bulkDeleteIds"></div>
    </form>

    <!-- Export form (hidden) -->
    <form id="exportForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="export">
        <input type="hidden" name="export_format" id="exportFormat">
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($type_filter); ?>">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
        <input type="hidden" name="country" value="<?php echo htmlspecialchars($country_filter); ?>">
    </form>

    <!-- Details/View Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">
                        <i class="bx bx-info-circle me-2"></i>Customer/Supplier Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6><i class="bx bx-user me-1 text-primary"></i>Personal Info</h6>
                            <hr class="mt-0">
                            <div class="mb-2"><strong>ID:</strong> <span id="modalDetailID"></span></div>
                            <div class="mb-2"><strong>Name:</strong> <span id="modalDetailName"></span></div>
                            <div class="mb-2"><strong>Email:</strong> <span id="modalDetailEmail"></span></div>
                            <div class="mb-2"><strong>Phone:</strong> <span id="modalDetailPhone"></span></div>
                            <div class="mb-2"><strong>Registration Type:</strong> <span id="modalDetailType"></span></div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bx bx-briefcase me-1 text-success"></i>Business Info</h6>
                            <hr class="mt-0">
                            <div class="mb-2"><strong>Company:</strong> <span id="modalDetailCompany"></span></div>
                            <div class="mb-2"><strong>Business Type:</strong> <span id="modalDetailBusinessType"></span></div>
                            <div class="mb-2"><strong>Industry:</strong> <span id="modalDetailIndustry"></span></div>
                            <div class="mb-2"><strong>Status:</strong> <span id="modalDetailStatus"></span></div>
                            <div class="mb-2"><strong>Date Registered:</strong> <span id="modalDetailDateRegistered"></span></div>
                        </div>
                        <div class="col-12">
                            <h6><i class="bx bx-home me-1 text-info"></i>Address Info</h6>
                            <hr class="mt-0">
                            <div class="mb-2"><strong>Address:</strong> <span id="modalDetailAddress"></span></div>
                            <div class="mb-2"><strong>City:</strong> <span id="modalDetailCity"></span></div>
                            <div class="mb-2"><strong>Postcode:</strong> <span id="modalDetailPostcode"></span></div>
                            <div class="mb-2"><strong>State:</strong> <span id="modalDetailState"></span></div>
                            <div class="mb-2"><strong>Country:</strong> <span id="modalDetailCountry"></span></div>
                        </div>
                        <div class="col-12">
                            <h6><i class="bx bx-note me-1 text-secondary"></i>Notes</h6>
                            <hr class="mt-0">
                            <div id="modalDetailNotes" class="text-muted"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="modalEditButton" class="btn btn-primary"><i class="bx bx-edit me-1"></i>Edit Record</a>
                </div>
            </div>
        </div>
    </div>


    <!-- JavaScript -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <!-- Assuming menu.js is now correctly loaded or its functionality is within main.js -->
    <!-- <script src="assets/vendor/js/menu.js"></script> -->
    <script src="assets/js/main.js"></script>

    <script>
    // Page initialization
    document.addEventListener('DOMContentLoaded', function() {
        setupEventListeners();
        setupKeyboardShortcuts();
        autoHideAlerts(); // Auto-hide success/error messages on load
    });

    // Setup event listeners
    function setupEventListeners() {
        // Select all checkbox functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            toggleBulkActions();
        });

        // Individual checkbox functionality
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('row-checkbox')) {
                const allCheckboxes = document.querySelectorAll('.row-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
                const selectAllCheckbox = document.getElementById('selectAll');

                selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;

                toggleBulkActions();
            }
        });
    }

    // Setup keyboard shortcuts
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N for new record
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'register-customer-supplier.php';
            }

            // Ctrl/Cmd + F to focus search (if exists)
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }

            // Escape to clear search (if exists)
            if (e.key === 'Escape') {
                const searchBox = document.querySelector('input[name="search"]');
                if (searchBox && searchBox.value) {
                    searchBox.value = '';
                    document.getElementById('filterForm').submit();
                }
            }
        });
    }

    // Show loading overlay
    function showLoading() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'flex';
        }
    }

    // Hide loading overlay
    function hideLoading() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'none';
        }
    }

    // Filter by type function
    function filterByType(type) {
        showLoading();
        const url = new URL(window.location);
        url.searchParams.set('filter', '1');
        url.searchParams.set('type', type);
        window.location.href = url.toString();
    }

    // Toggle bulk action buttons display
    function toggleBulkActions() {
        const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

        if (checkedCheckboxes.length > 0) {
            bulkDeleteBtn.style.display = 'inline-flex';
            bulkDeleteBtn.innerHTML = `<i class="bx bx-trash"></i>Delete Selected (${checkedCheckboxes.length})`;
        } else {
            bulkDeleteBtn.style.display = 'none';
        }
    }

    // View record function - now opens a modal with full details
    async function viewRecord(registrationID) {
        showLoading(); // Show loading spinner
        const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));

        try {
            // Fetch all details for the given registrationID via AJAX
            const response = await fetch(`customer-supplier.php?action=fetch_details&id=${encodeURIComponent(registrationID)}`);
            const data = await response.json();

            if (response.ok && !data.error) {
                // Populate modal fields
                document.getElementById('modalDetailID').textContent = data.registrationID || 'N/A';
                document.getElementById('modalDetailName').textContent = (data.firstName || '') + ' ' + (data.lastName || '');
                document.getElementById('modalDetailEmail').textContent = data.email || 'N/A';
                document.getElementById('modalDetailPhone').textContent = data.phone || 'N/A';

                // For badges, recreate elements or use innerHTML
                const typeSpan = document.createElement('span');
                typeSpan.className = `badge ${data.registrationType}`;
                typeSpan.innerHTML = `<i class="bx ${getTypeIcon(data.registrationType)}"></i> ${data.registrationType ? ucfirst(data.registrationType) : 'N/A'}`;
                document.getElementById('modalDetailType').innerHTML = ''; // Clear previous content
                document.getElementById('modalDetailType').appendChild(typeSpan);

                document.getElementById('modalDetailCompany').textContent = data.companyName || 'N/A';
                document.getElementById('modalDetailBusinessType').textContent = data.businessType || 'N/A';
                document.getElementById('modalDetailIndustry').textContent = data.industry || 'N/A';

                const statusSpan = document.createElement('span');
                statusSpan.className = `badge ${data.status}`;
                statusSpan.innerHTML = `<i class="bx ${getStatusIcon(data.status)}"></i> ${data.status ? ucfirst(data.status) : 'N/A'}`;
                document.getElementById('modalDetailStatus').innerHTML = ''; // Clear previous content
                document.getElementById('modalDetailStatus').appendChild(statusSpan);

                document.getElementById('modalDetailDateRegistered').textContent = data.dateRegistered ? formatDate(data.dateRegistered) : 'N/A';
                document.getElementById('modalDetailAddress').textContent = data.address || 'N/A';
                document.getElementById('modalDetailCity').textContent = data.city || 'N/A';
                document.getElementById('modalDetailPostcode').textContent = data.postcode || 'N/A';
                document.getElementById('modalDetailState').textContent = data.state || 'N/A';
                document.getElementById('modalDetailCountry').textContent = data.country ? getCountryNameJs(data.country) : 'N/A'; // Use JS helper
                document.getElementById('modalDetailNotes').textContent = data.notes || 'No notes.';

                // Set the href for the "Edit Record" button in the modal
                document.getElementById('modalEditButton').href = `register-customer-supplier.php?edit=${encodeURIComponent(registrationID)}`;

                detailsModal.show(); // Show the populated modal
            } else {
                alert('Error fetching details: ' + (data.error || 'Unknown error.'));
            }
        } catch (error) {
            console.error('Error in viewRecord:', error);
            alert('An unexpected error occurred while fetching details. Check console for more info.');
        } finally {
            hideLoading(); // Hide loading spinner regardless of success or failure
        }
    }

    // Helper function for capitalizing first letter
    function ucfirst(str) {
        if (!str) return str;
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Helper function for country name mapping (needs to be available in JS scope)
    const countryNamesJs = <?php echo json_encode($country_names); ?>;
    function getCountryNameJs(code) {
        return countryNamesJs[code] || code;
    }


    // Edit record function - redirects to the registration/edit page
    function editRecord(registrationID) {
        showLoading(); // Show loading spinner before redirect
        window.location.href = 'register-customer-supplier.php?edit=' + encodeURIComponent(registrationID);
    }

    // Delete single record function
    function deleteRecord(registrationID) {
        // IMPORTANT: In a production environment, replace this with a custom modal confirmation
        if (confirm('Are you sure you want to delete this record?\n\nThis action cannot be undone and will remove:\n• All customer/supplier information\n• Associated transaction history\n• Contact details and documents')) {
            showLoading();
            document.getElementById('deleteRegistrationID').value = registrationID;
            document.getElementById('deleteForm').submit();
        }
    }

    // Bulk delete function
    function bulkDelete() {
        const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
        const selectedIds = Array.from(checkedCheckboxes).map(cb => cb.value);

        if (selectedIds.length === 0) {
            // IMPORTANT: In a production environment, replace this with a custom modal alert
            alert('Please select at least one record to delete.');
            return;
        }

        // IMPORTANT: In a production environment, replace this with a custom modal confirmation
        if (confirm(
                `Are you sure you want to delete ${selectedIds.length} selected record(s)?\n\nThis action cannot be undone and will permanently remove:\n• All selected customer/supplier information\n• Associated transaction histories\n• Contact details and documents`
                )) {
            showLoading();

            // Clear existing hidden inputs
            const bulkDeleteIds = document.getElementById('bulkDeleteIds');
            bulkDeleteIds.innerHTML = '';

            // Add hidden inputs for selected IDs
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = id;
                bulkDeleteIds.appendChild(input);
            });

            // Submit the form
            document.getElementById('bulkDeleteForm').submit();
        }
    }

    // Refresh data function (simply reloads the page)
    function refreshData() {
        showLoading();
        setTimeout(() => {
            window.location.reload();
        }, 500); // Small delay for loading animation
    }

    // Show export modal (simplified prompt as actual export logic is backend)
    function showExportModal() {
        // IMPORTANT: In a production environment, replace this with a custom modal for format selection
        const format = prompt(
            'Select export format:\n\n' +
            '1. Excel (.xlsx) - Full data with formatting\n' +
            '2. CSV (.csv) - Raw data for spreadsheets\n' +
            '3. PDF (.pdf) - Formatted report\n\n' +
            'Enter 1, 2, or 3:'
        );

        let exportFormat = '';
        switch(format) {
            case '1': exportFormat = 'excel'; break;
            case '2': exportFormat = 'csv'; break;
            case '3': exportFormat = 'pdf'; break;
            default: return; // Cancel export if invalid input
        }

        if (exportFormat) {
            showLoading();
            document.getElementById('exportFormat').value = exportFormat;
            document.getElementById('exportForm').submit();

            // Hide loading after a delay (simulate export processing)
            setTimeout(() => {
                hideLoading();
            }, 2000); // Give user a sense of processing
        }
    }

    // Auto-submit filter form on select change
    document.querySelectorAll('select[name="type"], select[name="status"], select[name="country"]').forEach(select => {
        select.addEventListener('change', function() {
            showLoading();
            this.form.submit();
        });
    });

    // Real-time search functionality with delay
    let searchTimeout;
    document.querySelector('input[name="search"]').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            showLoading();
            this.form.submit();
        }, 750); // 750ms delay for better UX
    });

    // Enhanced search in navbar (links to main search filter)
    document.querySelector('.navbar-nav input[aria-label="Search..."]').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            const searchTerm = this.value;
            if (searchTerm.trim()) {
                // Set the main filter search input and submit the form
                const mainSearchInput = document.querySelector('.filters-section input[name="search"]');
                if (mainSearchInput) {
                    mainSearchInput.value = searchTerm;
                    document.getElementById('filterForm').submit();
                } else {
                    // Fallback if main search input not found (unlikely in this template)
                    window.location.href = `?filter=1&search=${encodeURIComponent(searchTerm)}`;
                }
            } else {
                // If search box is cleared, reset filters
                   window.location.href = '?';
            }
            showLoading(); // Show loading overlay during navigation
        }
    });

    // Tab click handlers for filter tabs
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');
        });
    });

    // Auto-hide success/error messages (Bootstrap 5 way with fade)
    function autoHideAlerts() {
        const alerts = document.querySelectorAll('.alert.alert-dismissible');
        alerts.forEach(alertEl => {
            const bsAlert = bootstrap.Alert.getInstance(alertEl) || new bootstrap.Alert(alertEl);
            setTimeout(() => {
                bsAlert.hide(); // Use Bootstrap's hide method which handles fade out
            }, 5000); // 5 seconds before hiding
        });
    }

    // Handle page visibility changes (optional, for real-time like behavior)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // Page became visible, could trigger a refreshData() if real-time updates are critical
            // console.log('Page is now visible - consider refreshing data.');
        }
    });
    </script>
</body>

</html>
