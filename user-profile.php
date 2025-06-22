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
$date_join = '';
$full_name = '';
$email = '';
$phone_no = '';
$username = '';
$password = '';
$position = 'user'; // Default position
$profile_picture = 'default.jpg'; // Default profile picture
$active = 1; // Default active status
$error = '';
$success = '';
$isEdit = false;

// Photo upload configuration
$uploadDir = 'uploads/photos/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 2 * 1024 * 1024; // 2MB

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $error = "Failed to create upload directory: $uploadDir";
    }
}

// Check if directory is writable
if (!is_writable($uploadDir)) {
    chmod($uploadDir, 0755);
    if (!is_writable($uploadDir)) {
        $error = "Upload directory is not writable: $uploadDir";
    }
}

// Try to establish database connection with error handling
try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Check if we're editing an existing user
    if (isset($_GET['op']) && $_GET['op'] == 'view' && isset($_GET['Id'])) {
        $isEdit = true;
        $editId = mysqli_real_escape_string($conn, $_GET['Id']);
        
        // Security check: Users can only edit their own profile unless they're admin/super-admin
        $current_user_id = $_SESSION['user_id'];
        $current_user_position = $_SESSION['position'] ?? 'user';
        
        if ($editId != $current_user_id && 
            !in_array($current_user_position, ['admin', 'super-admin'])) {
            $error = "You don't have permission to edit this profile.";
            // Redirect to their own profile
            header("Location: user-profile.php?op=view&Id=" . $current_user_id);
            exit();
        }
        
        // Fetch existing user data
        $sql = "SELECT * FROM user_profiles WHERE Id = '$editId'";
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $userData = mysqli_fetch_assoc($result);
            $Id = $userData['Id'];
            $date_join = $userData['date_join'];
            $full_name = $userData['full_name'];
            $email = $userData['email'];
            $phone_no = $userData['phone_no'];
            $username = $userData['username'];
            $password = ''; // Don't pre-fill password for security
            $position = $userData['position'];
            $profile_picture = $userData['profile_picture'];
            $active = $userData['active'];
        } else {
            $error = "User not found!";
        }
    }
    
    // Process form submission
    if (isset($_POST['submit'])) {
        // Handle photo upload first
        $uploadedFileName = $profile_picture; // Keep existing if no new upload
        
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            
            // Validate file
            if ($file['size'] > $maxFileSize) {
                $error = "File is too large. Maximum size is 2MB.";
            } elseif (!in_array($file['type'], $allowedTypes)) {
                $error = "Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.";
            } else {
                // Generate unique filename
                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $newFileName;
                
                // Move uploaded file to destination
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    // Delete old profile picture if it's not the default
                    if ($isEdit && $profile_picture && $profile_picture != 'default.jpg' && file_exists($uploadDir . $profile_picture)) {
                        unlink($uploadDir . $profile_picture);
                    }
                    $uploadedFileName = $newFileName;
                } else {
                    $error = "Failed to upload profile picture.";
                }
            }
        }
        
        // Continue with form processing only if no upload errors
        if (empty($error)) {
            // Validate and sanitize input data
            if (isset($_POST['Id'])) $Id = mysqli_real_escape_string($conn, $_POST['Id']);
            if (isset($_POST['date_join'])) $date_join = mysqli_real_escape_string($conn, $_POST['date_join']);
            if (isset($_POST['full_name'])) $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            if (isset($_POST['email'])) $email = mysqli_real_escape_string($conn, $_POST['email']);
            if (isset($_POST['phone_no'])) $phone_no = mysqli_real_escape_string($conn, $_POST['phone_no']);
            if (isset($_POST['username'])) $username = mysqli_real_escape_string($conn, $_POST['username']);
            if (isset($_POST['password'])) $password = mysqli_real_escape_string($conn, $_POST['password']);
            if (isset($_POST['position'])) $position = mysqli_real_escape_string($conn, $_POST['position']);
            $active = isset($_POST['status']) ? 1 : 0;
            $profile_picture = $uploadedFileName;
            
            // Handle password hashing
            if (!empty($password)) {
                $password = password_hash($password, PASSWORD_DEFAULT);
            }
            
            // Check if this is an update or insert
            $isUpdate = isset($_POST['is_edit']) && $_POST['is_edit'] == '1';
            
            if ($isUpdate) {
                // Update existing user
                if($Id && $full_name && $email && $phone_no && $username) {
                    // Build update query - only update password if new one is provided
                    if (!empty($password)) {
                        $sql = "UPDATE user_profiles SET 
                                date_join = '$date_join',
                                full_name = '$full_name', 
                                email = '$email', 
                                phone_no = '$phone_no', 
                                username = '$username', 
                                password = '$password', 
                                position = '$position',
                                profile_picture = '$profile_picture',
                                active = '$active'
                                WHERE Id = '$Id'";
                    } else {
                        $sql = "UPDATE user_profiles SET 
                                date_join = '$date_join',
                                full_name = '$full_name', 
                                email = '$email', 
                                phone_no = '$phone_no', 
                                username = '$username', 
                                position = '$position',
                                profile_picture = '$profile_picture',
                                active = '$active'
                                WHERE Id = '$Id'";
                    }
                    
                    $q1 = mysqli_query($conn, $sql);
                    
                    if($q1) {
                        $success = "User updated successfully!";
                        // Update session data if user updated their own profile
                        if ($Id == $_SESSION['user_id']) {
                            $_SESSION['full_name'] = $full_name;
                            $_SESSION['username'] = $username;
                            $_SESSION['email'] = $email;
                            $_SESSION['position'] = $position;
                            $_SESSION['profile_picture'] = $profile_picture;
                        }
                    } else {
                        $error = "Update failed: " . mysqli_error($conn);
                    }
                } else {
                    $error = "All required fields must be filled!";
                }
            } else {
                // Insert new user
                // Generate new ID: ABxxxx
                $query = "SELECT MAX(Id) as Id FROM user_profiles WHERE Id LIKE 'AB%'";
                $result = mysqli_query($conn, $query);
                $row = mysqli_fetch_assoc($result);
                
                if ($row && $row['Id']) {
                    // Extract numeric part and increment
                    $last_num = (int)substr($row['Id'], 2);
                    $new_num = $last_num + 1;
                    $Id = 'AB' . str_pad($new_num, 4, '0', STR_PAD_LEFT);
                } else {
                    $Id = 'AB0001'; // First ID
                }
                
                // Validate required fields
                if($Id && $date_join && $full_name && $email && $phone_no && $username && $password) {
                    // Check if username or email already exists
                    $checkSql = "SELECT Id FROM user_profiles WHERE username = '$username' OR email = '$email'";
                    $checkResult = mysqli_query($conn, $checkSql);
                    
                    if (mysqli_num_rows($checkResult) > 0) {
                        $error = "Username or email already exists!";
                    } else {
                        $sql = "INSERT INTO user_profiles(Id, date_join, full_name, email, phone_no, username, password, position, profile_picture, active) 
                                VALUES ('$Id', '$date_join', '$full_name', '$email', '$phone_no', '$username', '$password', '$position', '$profile_picture', '$active')";
                        
                        $q1 = mysqli_query($conn, $sql);
                        
                        if($q1) {
                            $success = "User created successfully!";
                            // Reset form for new entry
                            if (!$isEdit) {
                                $Id = $date_join = $full_name = $email = $phone_no = $username = $password = '';
                                $position = 'user';
                                $profile_picture = 'default.jpg';
                                $active = 1;
                            }
                        } else {
                            $error = "Data insertion failed: " . mysqli_error($conn);
                        }
                    }
                } else {
                    $error = "All fields are required!";
                }
            }
        }
    }
    
    // Handle remove profile picture
    if (isset($_POST['remove_picture']) && $isEdit) {
        if ($profile_picture && $profile_picture != 'default.jpg' && file_exists($uploadDir . $profile_picture)) {
            unlink($uploadDir . $profile_picture);
        }
        
        $sql = "UPDATE user_profiles SET profile_picture = 'default.jpg' WHERE Id = '$Id'";
        if (mysqli_query($conn, $sql)) {
            $profile_picture = 'default.jpg';
            $success = "Profile picture removed successfully!";
        } else {
            $error = "Failed to remove profile picture.";
        }
    }
    
    // Set default date if not editing
    if (!$isEdit && empty($date_join)) {
        $date_join = date('Y-m-d');
    }
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title><?php echo $isEdit ? 'Edit User' : 'Create New User'; ?> - User Management</title>

    <meta name="description" content="User Profile Management" />

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

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Public Sans', sans-serif;
    }

    body {
        background-color: #f8f9fd;
        color: #333;
        padding: 20px;
    }

    .container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        margin-bottom: 25px;
    }

    .breadcrumb {
        font-size: 14px;
        color: #777;
        margin-bottom: 10px;
    }

    .breadcrumb a {
        color: #6366f1;
        text-decoration: none;
    }

    .breadcrumb a:hover {
        text-decoration: underline;
    }

    .page-title {
        font-size: 24px;
        margin-bottom: 20px;
        color: #333;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .back-btn {
        background: none;
        border: none;
        font-size: 20px;
        color: #777;
        cursor: pointer;
        padding: 5px;
        border-radius: 5px;
        transition: all 0.2s;
    }

    .back-btn:hover {
        background-color: #f5f7ff;
        color: #6366f1;
    }

    .profile-card {
        background-color: #fff;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        margin-bottom: 25px;
    }

    .profile-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }

    .profile-title {
        font-size: 18px;
        font-weight: 600;
    }

    .action-buttons {
        display: flex;
        gap: 12px;
    }

    .btn {
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        outline: none;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-primary {
        background-color: #6366f1;
        color: #fff;
    }

    .btn-primary:hover {
        background-color: #4f46e5;
    }

    .btn-outline {
        background-color: transparent;
        color: #6366f1;
        border: 1px solid #6366f1;
    }

    .btn-outline:hover {
        background-color: #f5f7ff;
    }

    .btn-secondary {
        background-color: #6b7280;
        color: #fff;
    }

    .btn-secondary:hover {
        background-color: #4b5563;
    }

    .btn-danger {
        background-color: #ef4444;
        color: #fff;
    }

    .btn-danger:hover {
        background-color: #dc2626;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        color: #555;
        font-weight: 500;
    }

    .required {
        color: #ef4444;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        border-color: #6366f1;
        outline: none;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .form-select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        background-color: #fff;
        cursor: pointer;
    }

    .profile-image-section {
        grid-column: span 2;
        display: flex;
        gap: 30px;
        align-items: flex-start;
    }

    .profile-image {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        background-color: #f5f7ff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        color: #ccc;
        border: 1px dashed #ccc;
    }

    .profile-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .image-upload-details {
        flex: 1;
    }

    .upload-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .upload-desc {
        font-size: 14px;
        color: #777;
        margin-bottom: 15px;
    }

    .upload-actions {
        display: flex;
        gap: 10px;
    }

    .file-input {
        display: none;
    }

    .readonly-field {
        background-color: #f8f9fd;
        color: #777;
        cursor: not-allowed;
    }

    .info-text {
        font-size: 12px;
        color: #777;
        margin-top: 5px;
    }

    .form-span-2 {
        grid-column: span 2;
    }

    .status-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked+.toggle-slider {
        background-color: #10b981;
    }

    input:checked+.toggle-slider:before {
        transform: translateX(26px);
    }

    .status-text {
        font-size: 14px;
        font-weight: 500;
    }

    .active-status {
        color: #10b981;
    }

    .inactive-status {
        color: #777;
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        border-left: 4px solid;
    }

    .alert-success {
        background-color: #d1fae5;
        color: #065f46;
        border-left-color: #10b981;
    }

    .alert-danger {
        background-color: #fee2e2;
        color: #b91c1c;
        border-left-color: #ef4444;
    }

    .alert-icon {
        display: inline-block;
        margin-right: 8px;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .profile-image-section {
            grid-column: span 1;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .form-span-2 {
            grid-column: span 1;
        }

        .profile-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .action-buttons {
            width: 100%;
            justify-content: space-between;
        }
    }
    </style>

    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
</head>

<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="user.php">User Management</a> / <?php echo $isEdit ? 'Edit User' : 'Create New User'; ?>
        </div>

        <h1 class="page-title">
            <button type="button" class="back-btn" onclick="window.location.href='user.php'">
                ←
            </button>
            <?php echo $isEdit ? 'Edit User Profile' : 'Create New User'; ?>
        </h1>

        <!-- Alert Messages -->
        <?php if(!empty($error)) { ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon">⚠</span>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php } ?>

        <?php if(!empty($success)) { ?>
        <div class="alert alert-success" role="alert">
            <span class="alert-icon">✓</span>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php } ?>

        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-title">
                    <?php echo $isEdit ? 'Edit Profile Details' : 'Profile Details'; ?>
                </div>
                <div class="action-buttons">
                    <a href="user.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" form="user-form" name="submit" class="btn btn-primary">
                        <?php echo $isEdit ? 'Update User' : 'Create User'; ?>
                    </button>
                </div>
            </div>

            <form id="user-form" action="" method="POST" enctype="multipart/form-data">
                <!-- Hidden fields -->
                <input type="hidden" name="is_edit" value="<?php echo $isEdit ? '1' : '0'; ?>">

                <div class="profile-image-section">
                    <div class="profile-image" id="profile-preview">
                        <?php if ($profile_picture && $profile_picture != 'default.jpg' && file_exists($uploadDir . $profile_picture)): ?>
                            <img src="<?php echo $uploadDir . htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <span><?php echo $full_name ? strtoupper(substr($full_name, 0, 1)) : '👤'; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="image-upload-details">
                        <h3 class="upload-title">Profile Picture</h3>
                        <p class="upload-desc">Upload a new profile picture. Recommended size: 300x300px, max 2MB. Supported formats: JPG, PNG, GIF, WebP.</p>
                        <div class="upload-actions">
                            <input type="file" id="profile-pic" name="profile_pic" class="file-input" accept="image/*">
                            <label for="profile-pic" class="btn btn-outline">Upload New</label>
                            <?php if ($profile_picture && $profile_picture != 'default.jpg'): ?>
                                <button type="submit" name="remove_picture" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove the profile picture?')">Remove</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="Id" class="form-label">
                            User ID <?php if (!$isEdit): ?><span class="required">*</span><?php endif; ?>
                        </label>
                        <input type="text" id="Id" name="Id" class="form-control <?php echo $isEdit ? 'readonly-field' : ''; ?>"
                            value="<?php echo htmlspecialchars($Id); ?>" 
                            <?php echo $isEdit ? 'readonly' : ''; ?>
                            placeholder="<?php echo $isEdit ? '' : 'Auto-generated (AB0001, AB0002, etc.)'; ?>">
                        <?php if (!$isEdit): ?>
                            <p class="info-text">User ID will be auto-generated when you save</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="date_join" class="form-label">Created Date <span class="required">*</span></label>
                        <input type="date" id="date_join" name="date_join" class="form-control"
                            value="<?php echo htmlspecialchars($date_join); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                            value="<?php echo htmlspecialchars($full_name); ?>" required
                            placeholder="Enter full name">
                    </div>

                    <div class="form-group">
                        <label for="updated-date" class="form-label">Last Updated</label>
                        <input type="text" id="updated-date" class="form-control readonly-field"
                            value="<?php echo date('F d, Y'); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control"
                            value="<?php echo htmlspecialchars($email); ?>" required
                            placeholder="user@example.com">
                    </div>

                    <div class="form-group">
                        <label for="phone_no" class="form-label">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone_no" name="phone_no" class="form-control"
                            value="<?php echo htmlspecialchars($phone_no); ?>" required
                            placeholder="+1 (555) 123-4567">
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">Username <span class="required">*</span></label>
                        <input type="text" id="username" name="username" class="form-control"
                            value="<?php echo htmlspecialchars($username); ?>" required
                            placeholder="username">
                    </div>

                    <div class="form-group">
                        <label for="position" class="form-label">Role/Permission <span class="required">*</span></label>
                        <select id="position" name="position" class="form-select" required>
                            <option value="staff" <?php if($position == "staff") echo "selected"; ?>>Staff</option>
                            <option value="admin" <?php if($position == "admin") echo "selected"; ?>>Admin</option>
                            <option value="manager" <?php if($position == "manager") echo "selected"; ?>>Manager</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            Password <?php if (!$isEdit): ?><span class="required">*</span><?php endif; ?>
                        </label>
                        <input type="password" id="password" name="password" class="form-control"
                            placeholder="<?php echo $isEdit ? 'Leave blank to keep current password' : 'Enter password'; ?>"
                            <?php if (!$isEdit): ?>required<?php endif; ?>>
                        <?php if ($isEdit): ?>
                            <p class="info-text">Leave blank to keep current password</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group status-toggle">
                        <label class="toggle-switch">
                            <input type="checkbox" name="status" <?php echo ($active == 1) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="status-text <?php echo ($active == 1) ? 'active-status' : 'inactive-status'; ?>">
                            <?php echo ($active == 1) ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="form-group form-span-2">
                        <button type="button" class="btn btn-secondary" onclick="resetPassword()">Reset Password</button>
                        <p class="info-text">Send password reset link to user's email</p>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusToggle = document.querySelector('input[name="status"]');
        const statusText = document.querySelector('.status-text');

        statusToggle.addEventListener('change', function() {
            if (this.checked) {
                statusText.textContent = 'Active';
                statusText.className = 'status-text active-status';
            } else {
                statusText.textContent = 'Inactive';
                statusText.className = 'status-text inactive-status';
            }
        });

        // Form validation
        const form = document.getElementById('user-form');
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('input[required], select[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#ef4444';
                    isValid = false;
                } else {
                    field.style.borderColor = '#ddd';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Profile picture preview
        const profilePicInput = document.getElementById('profile-pic');
        const profilePreview = document.getElementById('profile-preview');

        profilePicInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file size
                if (file.size > 2 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 2MB.');
                    this.value = '';
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type.toLowerCase())) {
                    alert('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
                    this.value = '';
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePreview.innerHTML = '<img src="' + e.target.result + '" alt="Profile Picture">';
                };
                reader.readAsDataURL(file);
            }
        });

        // Dynamic profile picture initial based on name
        const fullNameInput = document.getElementById('full_name');
        fullNameInput.addEventListener('input', function() {
            const profileImg = profilePreview.querySelector('img');
            if (!profileImg && this.value) {
                const initial = this.value.charAt(0).toUpperCase();
                if (profilePreview.querySelector('span')) {
                    profilePreview.querySelector('span').textContent = initial;
                }
            }
        });
    });

    function resetPassword() {
        if (confirm('Are you sure you want to reset this user\'s password? A new temporary password will be generated and sent to their email.')) {
            // Add reset password functionality here
            alert('Password reset functionality would be implemented here.');
        }
    }
    </script>
</body>
</html>