<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) { // check connection
    throw new Exception("Connection failed: " . mysqli_connect_error());
}

$itemID = "";
$product_name = "";
$type_product = "";
$stock = "";
$price = "";
$image = "";
$success_message = "";
$error_message = "";

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate category-based integer ID
 * 
 * @param string $category The product category
 * @param mysqli $connection Database connection
 * @return int Generated ID (e.g., 101, 102, 201, 202, etc.)
 */
function generateCategoryID($category, $connection) {
    // Define category starting IDs
    $categoryRanges = [
        'Electronic' => 101,
        'Accessories' => 201,
        'Furniture' => 301,
        'Kitchen' => 401,
        'Office' => 501
    ];
    
    // Get the starting ID for the category
    $startingId = isset($categoryRanges[$category]) ? $categoryRanges[$category] : 1001;
    
    // Calculate the range for this category (100 IDs per category)
    $rangeStart = $startingId;
    $rangeEnd = $startingId + 99;
    
    // Query to find the highest existing ID in this category's range
    $query = "SELECT itemID FROM inventory_item WHERE itemID >= ? AND itemID <= ? ORDER BY itemID DESC LIMIT 1";
    $stmt = mysqli_prepare($connection, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $rangeStart, $rangeEnd);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $last_id);
        
        $next_id = $startingId; // Default starting ID
        
        if (mysqli_stmt_fetch($stmt)) {
            // If we found an existing ID, increment it
            $next_id = $last_id + 1;
        }
        
        mysqli_stmt_close($stmt);
        
        // Check if we've exceeded the range for this category
        if ($next_id > $rangeEnd) {
            throw new Exception("Category '{$category}' has reached maximum capacity (100 items). Please contact administrator.");
        }
        
        return $next_id;
    }
    
    // Fallback if query fails
    return $startingId;
}

/**
 * Handle file upload with better error handling
 * 
 * @param array $file The $_FILES array element
 * @return array ['success' => bool, 'filename' => string, 'error' => string]
 */
function handleFileUpload($file) {
    $result = [
        'success' => false,
        'filename' => '',
        'error' => ''
    ];
    
    // Check if file was actually uploaded
    if (!isset($file) || $file['error'] != 0) {
        $result['error'] = "Upload error: " . getFileUploadErrorMessage($file['error']);
        return $result;
    }
    
    // Create uploads directory structure if it doesn't exist
    $target_dir = "./uploads/images/";
    $absolute_path = realpath('./') . '/uploads/images/';
    
    // Create uploads directory first
    if (!file_exists("./uploads/")) {
        if (!mkdir("./uploads/", 0777, true)) {
            $result['error'] = "Failed to create uploads directory. Please create it manually and set permissions to 755 or 777.";
            return $result;
        }
        chmod("./uploads/", 0777);
    }
    
    // Create images subdirectory
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            $result['error'] = "Failed to create images directory at: " . $absolute_path . ". Please create this directory manually and set permissions to 755 or 777.";
            return $result;
        }
        chmod($target_dir, 0777);
    }
    
    // Check if uploads directory is writable
    if (!is_writable($target_dir)) {
        $result['error'] = "Images directory is not writable. Check permissions on: " . $absolute_path;
        return $result;
    }
    
    // Generate unique filename to prevent overwriting
    $filename = basename($file["name"]);
    $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    $uniqueFilename = uniqid() . '.' . $fileExtension;
    $target_file = $target_dir . $uniqueFilename;
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        $result['error'] = "File is too large. Maximum size is 5MB.";
        return $result;
    }
    
    // Allow only certain file formats
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
        $result['error'] = "Only JPG, JPEG, PNG & GIF files are allowed.";
        return $result;
    }
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        $result['success'] = true;
        $result['filename'] = $uniqueFilename;
    } else {
        $result['error'] = "Failed to upload file. Check permissions on: " . $absolute_path;
    }
    
    return $result;
}

/**
 * Get meaningful error message for file upload errors
 * 
 * @param int $error_code The error code from $_FILES['file']['error']
 * @return string Human-readable error message
 */
function getFileUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}

// CRUD Operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check which form action was submitted
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // ADD NEW PRODUCT
        if ($action === 'add') {
            $product_name = sanitize_input($_POST['product_name']);
            $type_product = sanitize_input($_POST['type_product']);
            $stock = (int)sanitize_input($_POST['stock']);
            $price = sanitize_input($_POST['price']); // Keep as string to match database schema
            
            // Generate category-based integer ID
            try {
                $itemID = generateCategoryID($type_product, $conn);
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                // Don't proceed with insertion if ID generation failed
                goto skip_add;
            }
            
            // Handle image upload - store the filename only, not the full path
            $image = '';
            if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] == 0) {
                $upload_result = handleFileUpload($_FILES['productImage']);
                if ($upload_result['success']) {
                    $image = $upload_result['filename'];
                } else {
                    $error_message = $upload_result['error'];
                    // Continue anyway without image if there was an error
                }
            }

            // INSERT query - using integer for itemID
            $query = "INSERT INTO inventory_item (itemID, product_name, type_product, stock, price, image) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            
            if ($stmt) {
                // itemID is now an integer, so use 'i' for integer binding
                mysqli_stmt_bind_param($stmt, 'ississ', $itemID, $product_name, $type_product, $stock, $price, $image);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Product added successfully with ID: " . $itemID;
                    // Reset form after successful addition
                    $product_name = "";
                    $type_product = "";
                    $stock = "";
                    $price = "";
                    $image = "";
                } else {
                    $error_message = "Database Error: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Error preparing statement: " . mysqli_error($conn);
            }
            
            skip_add:
        }
        
        // UPDATE EXISTING PRODUCT
        else if ($action === 'update') {
            $itemID = (int)sanitize_input($_POST['itemID']); // Convert to integer
            $product_name = sanitize_input($_POST['product_name']);
            $type_product = sanitize_input($_POST['type_product']);
            $stock = (int)sanitize_input($_POST['stock']);
            $price = sanitize_input($_POST['price']); // Keep as string to match database schema
            
            // Check if we're updating the image
            if (isset($_FILES['productImage']) && $_FILES['productImage']['error'] == 0) {
                $upload_result = handleFileUpload($_FILES['productImage']);
                if ($upload_result['success']) {
                    $image = $upload_result['filename'];
                    
                    // Get the old image to delete it
                    $get_old_image_query = "SELECT image FROM inventory_item WHERE itemID = ?";
                    $stmt = mysqli_prepare($conn, $get_old_image_query);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'i', $itemID); // 'i' since itemID is now integer
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_bind_result($stmt, $old_image);
                        mysqli_stmt_fetch($stmt);
                        mysqli_stmt_close($stmt);
                        
                        // Delete old image if it exists
                        if (!empty($old_image)) {
                            $old_file = "./uploads/images/" . $old_image;
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                    }
                    
                    // UPDATE with new image
                    $query = "UPDATE inventory_item SET 
                            product_name = ?, 
                            type_product = ?, 
                            stock = ?, 
                            price = ?, 
                            image = ? 
                            WHERE itemID = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    
                    if ($stmt) {
                        // Updated binding: itemID is now integer, so 'ssiisi'
                        mysqli_stmt_bind_param($stmt, 'ssiisi', $product_name, $type_product, $stock, $price, $image, $itemID);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success_message = "Product updated successfully.";
                        } else {
                            $error_message = "Database Error: " . mysqli_stmt_error($stmt);
                        }
                        mysqli_stmt_close($stmt);
                    } else {
                        $error_message = "Error preparing statement: " . mysqli_error($conn);
                    }
                } else {
                    $error_message = $upload_result['error'];
                }
            } else {
                // UPDATE without changing the image
                $query = "UPDATE inventory_item SET 
                        product_name = ?, 
                        type_product = ?, 
                        stock = ?, 
                        price = ? 
                        WHERE itemID = ?";
                $stmt = mysqli_prepare($conn, $query);
                
                if ($stmt) {
                    // Updated binding: itemID is now integer, so 'ssisi'
                    mysqli_stmt_bind_param($stmt, 'ssisi', $product_name, $type_product, $stock, $price, $itemID);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "Product updated successfully.";
                    } else {
                        $error_message = "Database Error: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_message = "Error preparing statement: " . mysqli_error($conn);
                }
            }
        }
        
        // DELETE PRODUCT
        else if ($action === 'delete') {
            $itemID = (int)sanitize_input($_POST['itemID']); // Convert to integer
            
            // Retrieve the image filename before deleting
            $get_image_query = "SELECT image FROM inventory_item WHERE itemID = ?";
            $stmt = mysqli_prepare($conn, $get_image_query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $itemID); // 'i' since itemID is now integer
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $image_to_delete);
                mysqli_stmt_fetch($stmt);
                mysqli_stmt_close($stmt);
                
                // Delete the product
                $delete_query = "DELETE FROM inventory_item WHERE itemID = ?";
                $stmt = mysqli_prepare($conn, $delete_query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'i', $itemID); // 'i' since itemID is now integer
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // If deletion was successful and there's an image, delete the image file too
                        if (!empty($image_to_delete)) {
                            $file_to_delete = "./uploads/images/" . $image_to_delete;
                            if (file_exists($file_to_delete)) {
                                unlink($file_to_delete);
                            }
                        }
                        $success_message = "Product deleted successfully.";
                    } else {
                        $error_message = "Database Error: " . mysqli_stmt_error($stmt);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error_message = "Error preparing statement: " . mysqli_error($conn);
                }
            }
        }
    }
}

// LOAD PRODUCT FOR EDITING
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int)sanitize_input($_GET['edit']); // Convert to integer
    
    // Fetch product details
    $edit_query = "SELECT itemID, product_name, type_product, stock, price, image FROM inventory_item WHERE itemID = ?";
    $stmt = mysqli_prepare($conn, $edit_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $edit_id); // 'i' since itemID is now integer
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $itemID = $row['itemID'];
            $product_name = $row['product_name'];
            $type_product = $row['type_product'];
            $stock = $row['stock'];
            $price = $row['price'];
            $image = $row['image'];
        }
        mysqli_stmt_close($stmt);
    }
}

// Page title based on mode
$page_title = empty($itemID) ? "Add New Product" : "Update Product #" . $itemID;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Inventomo</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />
    
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-color-dark: #4f46e5;
            --secondary-color: #6b7280;
            --secondary-color-dark: #4b5563;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --light-color: #f9fafb;
            --dark-color: #111827;
            --background-color: #f3f4f6;
            --card-color: #ffffff;
            --border-color: #e5e7eb;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --font-family: 'Public Sans', sans-serif;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-family);
            background-color: var(--background-color);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            color: var(--dark-color);
            display: flex;
            flex-direction: column;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 0 10px;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: var(--secondary-color);
        }
        
        .breadcrumb a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .breadcrumb a:hover {
            color: var(--primary-color);
        }
        
        .breadcrumb-separator {
            margin: 0 8px;
            color: var(--secondary-color);
        }
        
        .card {
            background-color: var(--card-color);
            border-radius: 10px;
            box-shadow: 0 4px 6px var(--shadow-color);
            overflow: hidden;
            margin-bottom: 25px;
            transition: box-shadow 0.3s;
        }
        
        .card:hover {
            box-shadow: 0 8px 15px var(--shadow-color);
        }
        
        .card-header {
            background-color: var(--light-color);
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .card-footer {
            background-color: var(--light-color);
            padding: 15px 25px;
            border-top: 1px solid var(--border-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        
        .alert-icon {
            margin-right: 10px;
            font-size: 1.25rem;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }
        
        .form-group {
            margin-bottom: 20px;
            flex: 1;
            min-width: 250px;
            padding: 10px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            color: var(--dark-color);
            background-color: var(--card-color);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
            outline: none;
        }
        
        .form-control::placeholder {
            color: var(--secondary-color);
        }
        
        .image-preview-container {
            margin-bottom: 20px;
        }
        
        .image-preview {
            width: 100%;
            height: 200px;
            border: 1px dashed var(--border-color);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 10px;
            background-color: var(--light-color);
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .file-input-container {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 1;
        }
        
        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 15px;
            background-color: var(--light-color);
            border: 1px dashed var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .file-input-label:hover {
            background-color: #e5e7eb;
        }
        
        .file-input-icon {
            margin-right: 8px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }
        
        .btn-icon {
            margin-right: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-color-dark);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: var(--secondary-color-dark);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--secondary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--light-color);
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.875rem;
        }
        
        .btn-lg {
            padding: 12px 25px;
            font-size: 1.125rem;
        }
        
        .btn-block {
            width: 100%;
            display: flex;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
        }
        
        .button-group-right {
            justify-content: flex-end;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            background-color: var(--secondary-color);
            color: white;
            margin-bottom: 20px;
        }
        
        .back-button:hover {
            background-color: var(--secondary-color-dark);
            color: white;
            text-decoration: none;
        }
        
        .back-button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.25);
        }
        
        .back-icon {
            margin-right: 8px;
        }
        
        .id-preview {
            background-color: var(--light-color);
            border: 1px solid var(--border-color);
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: 600;
            color: var(--primary-color);
            margin-top: 5px;
            display: none;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .form-group {
                min-width: 100%;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .breadcrumb {
                margin-top: 10px;
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
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title"><?php echo $page_title; ?></h1>
            <div class="breadcrumb">
                <a href="index.php">Dashboard</a>
                <span class="breadcrumb-separator">/</span>
                <a href="inventory.php">Inventory</a>
                <span class="breadcrumb-separator">/</span>
                <span><?php echo empty($itemID) ? "Add New" : "Edit #" . $itemID; ?></span>
            </div>
        </div>
        
        <!-- Back to Inventory -->
        <a href="inventory.php" class="back-button">
            <i class="fas fa-arrow-left back-icon"></i> Back to Inventory
        </a>
        
        <!-- Alerts -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle alert-icon"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Product Form Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-<?php echo empty($itemID) ? "plus" : "edit"; ?>"></i>
                    <?php echo empty($itemID) ? "Product Information" : "Edit Product"; ?>
                </h2>
            </div>
            <div class="card-body">
                <form id="productForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo empty($itemID) ? 'add' : 'update'; ?>">
                    
                    <?php if (!empty($itemID)): ?>
                        <input type="hidden" name="itemID" value="<?php echo $itemID; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_name" class="form-label">Product Name</label>
                            <input type="text" id="product_name" name="product_name" class="form-control" 
                                value="<?php echo $product_name; ?>" required placeholder="Enter product name">
                        </div>

                        <div class="form-group">
                            <label for="type_product" class="form-label">Category</label>
                            <select id="type_product" name="type_product" class="form-control" required onchange="updateIdPreview()">
                                <option value="" disabled <?php echo empty($type_product) ? 'selected' : ''; ?>>Select a category</option>
                                <option value="Electronic" <?php echo ($type_product == 'Electronic') ? 'selected' : ''; ?>>Electronic</option>
                                <option value="Accessories" <?php echo ($type_product == 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                                <option value="Furniture" <?php echo ($type_product == 'Furniture') ? 'selected' : ''; ?>>Furniture</option>
                                <option value="Kitchen" <?php echo ($type_product == 'Kitchen') ? 'selected' : ''; ?>>Kitchen</option>
                                <option value="Office" <?php echo ($type_product == 'Office') ? 'selected' : ''; ?>>Office</option>
                            </select>
                            <?php if (empty($itemID)): ?>
                                <div id="idPreview" class="id-preview">
                                    <i class="fas fa-tag"></i> Product ID will be generated automatically
                                </div>
                            <?php else: ?>
                                <div class="id-preview" style="display: block;">
                                    <i class="fas fa-tag"></i> Current ID: <?php echo $itemID; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="stock" class="form-label">Stock</label>
                            <input type="number" id="stock" name="stock" class="form-control" min="0" 
                                value="<?php echo $stock; ?>" required placeholder="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="price" class="form-label">Price (RM)</label>
                            <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" 
                                value="<?php echo $price; ?>" required placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Product Image</label>
                        <div class="image-preview" id="imagePreviewContainer">
                            <?php if (!empty($image)): ?>
                                <img src="./uploads/images/<?php echo $image; ?>" alt="Product image" id="imagePreview">
                            <?php else: ?>
                                <div id="placeholderText" style="color: #6b7280; text-align: center;">
                                    <i class="fas fa-image" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                                    <div>No image selected</div>
                                </div>
                                <img src="" alt="Product preview" id="imagePreview" style="display: none;">
                            <?php endif; ?>
                        </div>
                        <div class="file-input-container">
                            <input type="file" class="file-input" id="productImage" name="productImage" accept="image/*" onchange="previewImage(this)">
                            <label for="productImage" class="file-input-label">
                                <i class="fas fa-upload file-input-icon"></i>
                                <?php echo !empty($image) ? "Change Image" : "Choose Image"; ?>
                            </label>
                        </div>
                        <small style="color: #6b7280; display: block; margin-top: 5px;">
                            Supported formats: JPG, JPEG, PNG, GIF. Max size: 5MB. Images will be saved to /uploads/images/
                        </small>
                    </div>
                    
                    <div class="card-footer">
                        <div class="button-group button-group-right">
                            <?php if (!empty($itemID)): ?>
                                <a href="inventory.php" class="btn btn-outline">
                                    <i class="fas fa-times btn-icon"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save btn-icon"></i> Update Product
                                </button>
                            <?php else: ?>
                                <button type="reset" class="btn btn-outline" onclick="resetForm()">
                                    <i class="fas fa-redo btn-icon"></i> Clear Form
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus btn-icon"></i> Add Product
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Category ID ranges mapping
        const categoryRanges = {
            'Electronic': 101,
            'Accessories': 201,
            'Furniture': 301,
            'Kitchen': 401,
            'Office': 501
        };

        // Update ID preview when category changes
        function updateIdPreview() {
            const categorySelect = document.getElementById('type_product');
            const idPreview = document.getElementById('idPreview');
            
            if (categorySelect && idPreview) {
                const selectedCategory = categorySelect.value;
                
                if (selectedCategory && categoryRanges[selectedCategory]) {
                    const startingId = categoryRanges[selectedCategory];
                    idPreview.innerHTML = `<i class="fas fa-tag"></i> Next Product ID will start from: ${startingId} (auto-generated)`;
                    idPreview.style.display = 'block';
                } else {
                    idPreview.innerHTML = `<i class="fas fa-tag"></i> Product ID will be generated automatically`;
                    idPreview.style.display = 'block';
                }
            }
        }

        // Preview image before upload
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('placeholderText');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Reset form function
        function resetForm() {
            document.getElementById('productForm').reset();
            
            // Reset image preview
            const preview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('placeholderText');
            
            if (preview) {
                preview.style.display = 'none';
                preview.src = '';
            }
            
            if (placeholder) {
                placeholder.style.display = 'block';
            }
            
            // Reset ID preview
            updateIdPreview();
        }

        // Initialize ID preview on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateIdPreview();
        });
    </script>
</body>
</html>
