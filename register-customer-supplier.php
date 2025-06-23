<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for user authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-login-basic.html");
    exit();
}

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    throw new Exception("Connection failed: " . mysqli_connect_error());
}

// Initialize user variables with proper defaults
$current_user_id = $_SESSION['user_id'];
$current_user_name = "User";
$current_user_role = "user";
$current_user_avatar = "default.jpg";
$current_user_email = "";
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

// Get user initials from full name
function getUserInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    } else {
        return strtoupper(substr($name, 0, 1));
    }
}

// Fetch current user details from database with prepared statement
$user_query = "SELECT * FROM user_profiles WHERE Id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $user_query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $current_user_id);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    
    if ($user_result && mysqli_num_rows($user_result) > 0) {
        $user_data = mysqli_fetch_assoc($user_result);
        
        // Set user information
        $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
        $current_user_role = $user_data['position'];
        $current_user_email = $user_data['email'];
        
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
    mysqli_stmt_close($stmt);
}

$user_avatar_url = getUserAvatarUrl($current_user_avatar, $avatar_path);
$user_initials = getUserInitials($current_user_name);
$profile_link = "user-profile.php?op=view&Id=" . urlencode($current_user_id);

// Initialize variables
$registrationID = "";
$firstName = "";
$lastName = "";
$companyName = "";
$email = "";
$phone = "";
$address = "";
$city = "";
$state = "";
$zipCode = "";
$country = "";
$businessType = "";
$industry = "";
$registrationType = "";
$success_message = "";
$error_message = "";

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Function to validate phone
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10;
}

// Function to generate custom registration ID
function generateRegistrationID($conn, $type) {
    $prefix = ($type === 'customer') ? 'CS' : 'SP';
    
    // Get the last ID for this type
    $query = "SELECT registrationID FROM customer_supplier 
              WHERE registrationID LIKE ? 
              ORDER BY registrationID DESC LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $query);
    $search_pattern = $prefix . '%';
    mysqli_stmt_bind_param($stmt, 's', $search_pattern);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Extract the number part and increment
        $lastID = $row['registrationID'];
        $number = (int)substr($lastID, 2); // Remove prefix and convert to int
        $newNumber = $number + 1;
    } else {
        // First entry for this type
        $newNumber = 1;
    }
    
    mysqli_stmt_close($stmt);
    
    // Format with leading zeros (e.g., CS001, SP001)
    return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
}

// CRUD Operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // ADD NEW REGISTRATION
        if ($action === 'register') {
            // Sanitize and validate inputs
            $firstName = sanitize_input($_POST['firstName']);
            $lastName = sanitize_input($_POST['lastName']);
            $companyName = sanitize_input($_POST['companyName']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $address = sanitize_input($_POST['address']);
            $city = sanitize_input($_POST['city']);
            $state = sanitize_input($_POST['state']);
            $zipCode = sanitize_input($_POST['zipCode']);
            $country = sanitize_input($_POST['country']);
            $registrationType = sanitize_input($_POST['type']);
            $businessType = isset($_POST['businessType']) ? sanitize_input($_POST['businessType']) : '';
            $industry = isset($_POST['industry']) ? sanitize_input($_POST['industry']) : '';
            
            // Validation
            $validation_errors = [];
            
            if (empty($firstName)) $validation_errors[] = "First name is required";
            if (empty($lastName)) $validation_errors[] = "Last name is required";
            if (empty($companyName)) $validation_errors[] = "Company name is required";
            if (empty($email) || !isValidEmail($email)) $validation_errors[] = "Valid email is required";
            if (empty($phone) || !isValidPhone($phone)) $validation_errors[] = "Valid phone number is required";
            if (empty($address)) $validation_errors[] = "Address is required";
            if (empty($city)) $validation_errors[] = "City is required";
            if (empty($state)) $validation_errors[] = "State is required";
            if (empty($zipCode)) $validation_errors[] = "ZIP code is required";
            if (empty($country)) $validation_errors[] = "Country is required";
            if (empty($registrationType)) $validation_errors[] = "Registration type is required";
            
            // Additional validation for suppliers
            if ($registrationType === 'supplier') {
                if (empty($businessType)) $validation_errors[] = "Business type is required for suppliers";
                if (empty($industry)) $validation_errors[] = "Industry is required for suppliers";
            }
            
            // Check if email already exists
            $email_check_query = "SELECT email FROM customer_supplier WHERE email = ?";
            $stmt = mysqli_prepare($conn, $email_check_query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if (mysqli_num_rows($result) > 0) {
                    $validation_errors[] = "Email address already exists";
                }
                mysqli_stmt_close($stmt);
            }
            
            if (empty($validation_errors)) {
                // Generate custom registration ID
                $registrationID = generateRegistrationID($conn, $registrationType);
                
                // INSERT query
                $query = "INSERT INTO customer_supplier (
                    registrationID, firstName, lastName, companyName, email, phone, 
                    address, city, state, zipCode, country, registrationType, 
                    businessType, industry, dateRegistered, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')";
                
                $stmt = mysqli_prepare($conn, $query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssssssssssssss', 
                        $registrationID, $firstName, $lastName, $companyName, $email, $phone,
                        $address, $city, $state, $zipCode, $country, $registrationType,
                        $businessType, $industry
                    );
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = ucfirst($registrationType) . " registration completed successfully! Registration ID: " . $registrationID;
                        // Reset form after successful registration
                        $firstName = $lastName = $companyName = $email = $phone = "";
                        $address = $city = $state = $zipCode = $country = "";
                        $businessType = $industry = $registrationType = "";
                        $registrationID = ""; // Reset the ID for display
                    } else {
                        $error_message = "Database Error: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_message = "Error preparing statement: " . mysqli_error($conn);
                }
            } else {
                $error_message = implode(", ", $validation_errors);
            }
        }
        
        // UPDATE EXISTING REGISTRATION
        else if ($action === 'update') {
            $registrationID = sanitize_input($_POST['registrationID']);
            $firstName = sanitize_input($_POST['firstName']);
            $lastName = sanitize_input($_POST['lastName']);
            $companyName = sanitize_input($_POST['companyName']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $address = sanitize_input($_POST['address']);
            $city = sanitize_input($_POST['city']);
            $state = sanitize_input($_POST['state']);
            $zipCode = sanitize_input($_POST['zipCode']);
            $country = sanitize_input($_POST['country']);
            $registrationType = sanitize_input($_POST['type']);
            $businessType = isset($_POST['businessType']) ? sanitize_input($_POST['businessType']) : '';
            $industry = isset($_POST['industry']) ? sanitize_input($_POST['industry']) : '';
            
            $query = "UPDATE customer_supplier SET 
                    firstName = ?, lastName = ?, companyName = ?, email = ?, phone = ?,
                    address = ?, city = ?, state = ?, zipCode = ?, country = ?,
                    registrationType = ?, businessType = ?, industry = ?
                    WHERE registrationID = ?";
            
            $stmt = mysqli_prepare($conn, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssssssssssssss',
                    $firstName, $lastName, $companyName, $email, $phone,
                    $address, $city, $state, $zipCode, $country,
                    $registrationType, $businessType, $industry, $registrationID
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Registration updated successfully.";
                } else {
                    $error_message = "Database Error: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Error preparing statement: " . mysqli_error($conn);
            }
        }
        
        // DELETE REGISTRATION
        else if ($action === 'delete') {
            $registrationID = sanitize_input($_POST['registrationID']);
            
            $query = "DELETE FROM customer_supplier WHERE registrationID = ?";
            $stmt = mysqli_prepare($conn, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $registrationID);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Registration deleted successfully.";
                } else {
                    $error_message = "Database Error: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Error preparing statement: " . mysqli_error($conn);
            }
        }
        
        // SAVE DRAFT
        else if ($action === 'draft') {
            $firstName = sanitize_input($_POST['firstName']);
            $lastName = sanitize_input($_POST['lastName']);
            $companyName = sanitize_input($_POST['companyName']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $address = sanitize_input($_POST['address']);
            $city = sanitize_input($_POST['city']);
            $state = sanitize_input($_POST['state']);
            $zipCode = sanitize_input($_POST['zipCode']);
            $country = sanitize_input($_POST['country']);
            $registrationType = sanitize_input($_POST['type']);
            $businessType = isset($_POST['businessType']) ? sanitize_input($_POST['businessType']) : '';
            $industry = isset($_POST['industry']) ? sanitize_input($_POST['industry']) : '';
            
            // Generate a temporary draft ID
            $draftID = 'DRAFT_' . uniqid();
            
            $query = "INSERT INTO registration_drafts (
                draftID, firstName, lastName, companyName, email, phone,
                address, city, state, zipCode, country, registrationType,
                businessType, industry, dateSaved
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                firstName = VALUES(firstName), lastName = VALUES(lastName),
                companyName = VALUES(companyName), email = VALUES(email),
                phone = VALUES(phone), address = VALUES(address),
                city = VALUES(city), state = VALUES(state),
                zipCode = VALUES(zipCode), country = VALUES(country),
                registrationType = VALUES(registrationType),
                businessType = VALUES(businessType), industry = VALUES(industry),
                dateSaved = NOW()";
            
            $stmt = mysqli_prepare($conn, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssssssssssssss',
                    $draftID, $firstName, $lastName, $companyName, $email, $phone,
                    $address, $city, $state, $zipCode, $country, $registrationType,
                    $businessType, $industry
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Draft saved successfully! Draft ID: " . $draftID;
                } else {
                    $error_message = "Error saving draft: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// LOAD REGISTRATION FOR EDITING
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = sanitize_input($_GET['edit']);
    
    $edit_query = "SELECT * FROM customer_supplier WHERE registrationID = ?";
    $stmt = mysqli_prepare($conn, $edit_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $edit_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $registrationID = $row['registrationID'];
            $firstName = $row['firstName'];
            $lastName = $row['lastName'];
            $companyName = $row['companyName'];
            $email = $row['email'];
            $phone = $row['phone'];
            $address = $row['address'];
            $city = $row['city'];
            $state = $row['state'];
            $zipCode = $row['zipCode'];
            $country = $row['country'];
            $registrationType = $row['registrationType'];
            $businessType = $row['businessType'];
            $industry = $row['industry'];
        }
        mysqli_stmt_close($stmt);
    }
}

// Page title based on mode
$page_title = empty($registrationID) ? "New Registration" : "Update Registration #" . $registrationID;
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title><?php echo $page_title; ?> - Customer & Supplier</title>

    <meta name="description" content="" />

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

        .back-btn {
            padding: 0.5rem 1rem;
            background-color: #6c757d;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            color: white;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .back-btn:hover {
            background-color: #5a6268;
            color: white;
        }

        .registration-form {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(161, 172, 184, 0.15);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #566a7f;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f2f5;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-row.two-columns {
            grid-template-columns: 1fr 1fr;
        }

        .form-row.three-columns {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #566a7f;
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: ' *';
            color: #ff4444;
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 0.75rem;
            border: 1px solid #d9dee3;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            color: #566a7f;
            background-color: white;
            transition: all 0.2s ease;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #696cff;
            box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid #d9dee3;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
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
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
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
        }

        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
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

        .type-selection {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .type-option {
            flex: 1;
            padding: 1rem;
            border: 2px solid #d9dee3;
            border-radius: 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
        }

        .type-option:hover {
            border-color: #696cff;
            background-color: #f8f8ff;
        }

        .type-option.selected {
            border-color: #696cff;
            background-color: #696cff;
            color: white;
        }

        .type-option .type-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .type-option .type-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .type-option .type-description {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .id-preview {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-top: 0.5rem;
            font-family: monospace;
            font-size: 0.875rem;
            color: #495057;
        }

        .id-preview.customer {
            background-color: #e7e7ff;
            border-color: #696cff;
            color: #696cff;
        }

        .id-preview.supplier {
            background-color: #d4f5d4;
            border-color: #28a745;
            color: #28a745;
        }

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
            overflow: hidden;
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

        .profile-link {
            text-decoration: none;
            color: inherit;
        }

        .profile-link:hover {
            color: inherit;
        }

        @media (max-width: 768px) {
            .form-row.two-columns,
            .form-row.three-columns {
                grid-template-columns: 1fr;
            }
            
            .type-selection {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
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
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                        <?php if ($user_avatar_url): ?>
                                            <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" alt="<?php echo htmlspecialchars($current_user_name); ?>">
                                        <?php else: ?>
                                            <?php echo $user_initials; ?>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item profile-link" href="<?php echo $profile_link; ?>">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="user-avatar bg-label-<?php echo getAvatarColor($current_user_role); ?>">
                                                        <?php if ($user_avatar_url): ?>
                                                            <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" alt="<?php echo htmlspecialchars($current_user_name); ?>">
                                                        <?php else: ?>
                                                            <?php echo $user_initials; ?>
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
                                        <a class="dropdown-item" href="user-settings.php">
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
                            <h4 class="page-title"><?php echo $page_title; ?></h4>
                            <a href="customer-supplier.php" class="back-btn">
                                <i class="bx bx-arrow-back me-1"></i>Back to List
                            </a>
                        </div>

                        <!-- Success/Error Messages -->
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="bx bx-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="bx bx-error-circle me-2"></i>
                            <?php echo $error_message; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Registration Form -->
                        <form class="registration-form" method="POST" action="" id="registrationForm">
                            <input type="hidden" name="action" value="<?php echo empty($registrationID) ? 'register' : 'update'; ?>">
                            <?php if (!empty($registrationID)): ?>
                            <input type="hidden" name="registrationID" value="<?php echo $registrationID; ?>">
                            <?php endif; ?>

                            <!-- Type Selection -->
                            <div class="form-section">
                                <h5 class="section-title">Registration Type</h5>
                                <div class="type-selection">
                                    <div class="type-option <?php echo ($registrationType === 'customer') ? 'selected' : ''; ?>" 
                                         data-type="customer" onclick="selectType('customer')">
                                        <div class="type-icon">
                                            <i class="bx bx-user"></i>
                                        </div>
                                        <div class="type-title">Customer</div>
                                        <div class="type-description">Register as a customer to make purchases</div>
                                    </div>
                                    <div class="type-option <?php echo ($registrationType === 'supplier') ? 'selected' : ''; ?>" 
                                         data-type="supplier" onclick="selectType('supplier')">
                                        <div class="type-icon">
                                            <i class="bx bx-store"></i>
                                        </div>
                                        <div class="type-title">Supplier</div>
                                        <div class="type-description">Register as a supplier to provide products</div>
                                    </div>
                                </div>
                                
                                <input type="hidden" id="registrationType" name="type" value="<?php echo $registrationType; ?>" required>
                            </div>

                            <!-- Basic Information -->
                            <div class="form-section">
                                <h5 class="section-title">Basic Information</h5>
                                
                                <div class="form-row two-columns">
                                    <div class="form-group">
                                        <label class="form-label required" for="firstName">First Name</label>
                                        <input type="text" class="form-input" id="firstName" name="firstName" 
                                               value="<?php echo htmlspecialchars($firstName); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label required" for="lastName">Last Name</label>
                                        <input type="text" class="form-input" id="lastName" name="lastName" 
                                               value="<?php echo htmlspecialchars($lastName); ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label required" for="companyName">Company/Organization Name</label>
                                        <input type="text" class="form-input" id="companyName" name="companyName" 
                                               value="<?php echo htmlspecialchars($companyName); ?>" required>
                                    </div>
                                </div>

                                <div class="form-row two-columns">
                                    <div class="form-group">
                                        <label class="form-label required" for="email">Email Address</label>
                                        <input type="email" class="form-input" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($email); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label required" for="phone">Phone Number</label>
                                        <input type="tel" class="form-input" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($phone); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Address Information -->
                            <div class="form-section">
                                <h5 class="section-title">Address Information</h5>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label required" for="address">Street Address</label>
                                        <input type="text" class="form-input" id="address" name="address" 
                                               value="<?php echo htmlspecialchars($address); ?>" required>
                                    </div>
                                </div>

                                <div class="form-row three-columns">
                                    <div class="form-group">
                                        <label class="form-label required" for="city">City</label>
                                        <input type="text" class="form-input" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($city); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label required" for="state">State/Province</label>
                                        <input type="text" class="form-input" id="state" name="state" 
                                               value="<?php echo htmlspecialchars($state); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label required" for="zipCode">ZIP/Postal Code</label>
                                        <input type="text" class="form-input" id="zipCode" name="zipCode" 
                                               value="<?php echo htmlspecialchars($zipCode); ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label required" for="country">Country</label>
                                        <select class="form-select" id="country" name="country" required>
                                            <option value="">Select Country</option>
                                            <option value="MY" <?php echo ($country === 'MY') ? 'selected' : ''; ?>>Malaysia</option>
                                            <option value="SG" <?php echo ($country === 'SG') ? 'selected' : ''; ?>>Singapore</option>
                                            <option value="TH" <?php echo ($country === 'TH') ? 'selected' : ''; ?>>Thailand</option>
                                            <option value="ID" <?php echo ($country === 'ID') ? 'selected' : ''; ?>>Indonesia</option>
                                            <option value="VN" <?php echo ($country === 'VN') ? 'selected' : ''; ?>>Vietnam</option>
                                            <option value="PH" <?php echo ($country === 'PH') ? 'selected' : ''; ?>>Philippines</option>
                                            <option value="US" <?php echo ($country === 'US') ? 'selected' : ''; ?>>United States</option>
                                            <option value="GB" <?php echo ($country === 'GB') ? 'selected' : ''; ?>>United Kingdom</option>
                                            <option value="AU" <?php echo ($country === 'AU') ? 'selected' : ''; ?>>Australia</option>
                                            <option value="other" <?php echo ($country === 'other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Business Information (for suppliers) -->
                            <div class="form-section" id="businessSection" 
                                 style="display: <?php echo ($registrationType === 'supplier') ? 'block' : 'none'; ?>;">
                                <h5 class="section-title">Business Information</h5>
                                
                                <div class="form-row two-columns">
                                    <div class="form-group">
                                        <label class="form-label<?php echo ($registrationType === 'supplier') ? ' required' : ''; ?>" for="businessType">Business Type</label>
                                        <select class="form-select" id="businessType" name="businessType" 
                                                <?php echo ($registrationType === 'supplier') ? 'required' : ''; ?>>
                                            <option value="">Select Business Type</option>
                                            <option value="manufacturer" <?php echo ($businessType === 'manufacturer') ? 'selected' : ''; ?>>Manufacturer</option>
                                            <option value="distributor" <?php echo ($businessType === 'distributor') ? 'selected' : ''; ?>>Distributor</option>
                                            <option value="retailer" <?php echo ($businessType === 'retailer') ? 'selected' : ''; ?>>Retailer</option>
                                            <option value="wholesaler" <?php echo ($businessType === 'wholesaler') ? 'selected' : ''; ?>>Wholesaler</option>
                                            <option value="service" <?php echo ($businessType === 'service') ? 'selected' : ''; ?>>Service Provider</option>
                                            <option value="other" <?php echo ($businessType === 'other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label<?php echo ($registrationType === 'supplier') ? ' required' : ''; ?>" for="industry">Industry</label>
                                        <select class="form-select" id="industry" name="industry" 
                                                <?php echo ($registrationType === 'supplier') ? 'required' : ''; ?>>
                                            <option value="">Select Industry</option>
                                            <option value="technology" <?php echo ($industry === 'technology') ? 'selected' : ''; ?>>Technology</option>
                                            <option value="manufacturing" <?php echo ($industry === 'manufacturing') ? 'selected' : ''; ?>>Manufacturing</option>
                                            <option value="retail" <?php echo ($industry === 'retail') ? 'selected' : ''; ?>>Retail</option>
                                            <option value="healthcare" <?php echo ($industry === 'healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                                            <option value="finance" <?php echo ($industry === 'finance') ? 'selected' : ''; ?>>Finance</option>
                                            <option value="education" <?php echo ($industry === 'education') ? 'selected' : ''; ?>>Education</option>
                                            <option value="food" <?php echo ($industry === 'food') ? 'selected' : ''; ?>>Food & Beverage</option>
                                            <option value="construction" <?php echo ($industry === 'construction') ? 'selected' : ''; ?>>Construction</option>
                                            <option value="other" <?php echo ($industry === 'other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="button" class="btn btn-outline" onclick="resetForm()">
                                    <i class="bx bx-refresh"></i>
                                    Reset Form
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="saveDraft()">
                                    <i class="bx bx-save"></i>
                                    Save Draft
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-check"></i>
                                    <?php echo empty($registrationID) ? 'Register' : 'Update'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                © <script>document.write(new Date().getFullYear());</script> Inventomo. All rights reserved.
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

    <!-- JavaScript -->
    <script>
        // Initialize form on page load
        window.onload = function() {
            setupEventListeners();
            
            // Set business section visibility based on current selection
            const registrationType = document.getElementById('registrationType').value;
            if (registrationType === 'supplier') {
                document.getElementById('businessSection').style.display = 'block';
                makeBusinessFieldsRequired(true);
            }
        }

        // Setup event listeners
        function setupEventListeners() {
            // Real-time validation
            const inputs = document.querySelectorAll('.form-input, .form-select, .form-textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
                
                input.addEventListener('input', function() {
                    if (this.parentElement.classList.contains('error')) {
                        validateField(this);
                    }
                });
            });

            // Phone number formatting
            document.getElementById('phone').addEventListener('input', function(e) {
                formatPhoneNumber(e.target);
            });

            // Email validation
            document.getElementById('email').addEventListener('input', function(e) {
                validateEmail(e.target);
            });
        }

        // Type selection
        function selectType(type) {
            // Remove selected class from all options
            document.querySelectorAll('.type-option').forEach(option => {
                option.classList.remove('selected');
            });

            // Add selected class to clicked option
            document.querySelector(`.type-option[data-type="${type}"]`).classList.add('selected');

            // Set hidden input value
            document.getElementById('registrationType').value = type;

            // Show/hide business section based on type
            const businessSection = document.getElementById('businessSection');
            if (type === 'supplier') {
                businessSection.style.display = 'block';
                makeBusinessFieldsRequired(true);
            } else {
                businessSection.style.display = 'none';
                makeBusinessFieldsRequired(false);
            }
        }

        // Make business fields required/optional
        function makeBusinessFieldsRequired(required) {
            const businessFields = ['businessType', 'industry'];
            businessFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                const label = document.querySelector(`label[for="${fieldId}"]`);
                
                if (required) {
                    field.setAttribute('required', '');
                    if (!label.classList.contains('required')) {
                        label.classList.add('required');
                    }
                } else {
                    field.removeAttribute('required');
                    label.classList.remove('required');
                }
            });
        }

        // Field validation
        function validateField(field) {
            const formGroup = field.parentElement;
            const value = field.value.trim();
            let isValid = true;

            // Remove existing error state
            formGroup.classList.remove('error');

            // Check if required field is empty
            if (field.hasAttribute('required') && !value) {
                isValid = false;
            }

            // Specific validations
            switch (field.type) {
                case 'email':
                    if (value && !isValidEmail(value)) {
                        isValid = false;
                    }
                    break;
                case 'tel':
                    if (value && !isValidPhone(value)) {
                        isValid = false;
                    }
                    break;
            }

            // Show error if validation failed
            if (!isValid) {
                formGroup.classList.add('error');
            }

            return isValid;
        }

        // Email validation
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Phone validation
        function isValidPhone(phone) {
            const phoneRegex = /^[\+]?[1-9]?[\d\s\-\(\)]{10,}$/;
            return phoneRegex.test(phone);
        }

        // Phone number formatting
        function formatPhoneNumber(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 6) {
                if (value.length <= 10) {
                    value = value.replace(/(\d{3})(\d{3})(\d{1,4})/, '($1) $2-$3');
                } else {
                    value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                }
            }
            input.value = value;
        }

        // Email validation with visual feedback
        function validateEmail(input) {
            const email = input.value.trim();
            const formGroup = input.parentElement;
            
            if (email && !isValidEmail(email)) {
                formGroup.classList.add('error');
            } else {
                formGroup.classList.remove('error');
            }
        }

        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.getElementById('registrationForm').reset();
                
                // Clear type selection
                document.querySelectorAll('.type-option').forEach(option => {
                    option.classList.remove('selected');
                });
                document.getElementById('registrationType').value = '';
                
                // Hide business section
                document.getElementById('businessSection').style.display = 'none';
                makeBusinessFieldsRequired(false);
                
                // Clear all error states
                document.querySelectorAll('.form-group.error').forEach(group => {
                    group.classList.remove('error');
                });
            }
        }

        // Save draft
        function saveDraft() {
            const form = document.getElementById('registrationForm');
            const formData = new FormData(form);
            formData.set('action', 'draft'); // Override action to save as draft
            
            // Show loading state
            const draftBtn = document.querySelector('button[onclick="saveDraft()"]');
            const originalText = draftBtn.innerHTML;
            draftBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Saving...';
            draftBtn.disabled = true;
            
            // Submit as draft
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Reset button
                draftBtn.innerHTML = originalText;
                draftBtn.disabled = false;
                
                // Show success message
                alert('Draft saved successfully!');
            })
            .catch(error => {
                // Reset button
                draftBtn.innerHTML = originalText;
                draftBtn.disabled = false;
                
                console.error('Error:', error);
                alert('Error saving draft. Please try again.');
            });
        }
    </script>

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>">
                        