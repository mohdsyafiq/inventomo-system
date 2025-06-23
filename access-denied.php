<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-login-basic.html");
    exit();
}

// Get the reason for access denial (optional)
$reason = isset($_GET['reason']) ? $_GET['reason'] : 'insufficient_privileges';

// Get user info for display
$current_user_id = $_SESSION['user_id'];
$current_user_name = "User";
$current_user_role = "user";

// Database connection to get user details
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';

try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);
    
    if ($conn) {
        // Fetch current user details
        $user_query = "SELECT * FROM user_profiles WHERE Id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $user_query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $current_user_id);
            mysqli_stmt_execute($stmt);
            $user_result = mysqli_stmt_get_result($stmt);
            
            if ($user_result && mysqli_num_rows($user_result) > 0) {
                $user_data = mysqli_fetch_assoc($user_result);
                $current_user_name = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
                $current_user_role = strtolower($user_data['position']);
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_close($conn);
    }
} catch (Exception $e) {
    // Handle connection error silently
}

// Define reason messages
$reason_messages = [
    'insufficient_privileges' => 'You do not have sufficient privileges to access this page.',
    'role_restriction' => 'This page is restricted to specific user roles only.',
    'admin_only' => 'This page is only accessible by administrators.',
    'manager_admin_only' => 'This page is only accessible by managers and administrators.'
];

$message = isset($reason_messages[$reason]) ? $reason_messages[$reason] : $reason_messages['insufficient_privileges'];

// Define role-specific help text
$role_help = [
    'staff' => 'As a Staff member, you have limited access. Contact your manager or administrator for assistance.',
    'manager' => 'As a Manager, you may have view-only access to some features. Contact an administrator for full access.',
    'admin' => 'As an Administrator, you should have full access. This may be a system error.'
];

$help_text = isset($role_help[$current_user_role]) ? $role_help[$current_user_role] : 'Contact your system administrator for access.';
?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Access Denied - Inventomo</title>

    <meta name="description" content="Access Denied - Insufficient Privileges" />

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

    <!-- Custom CSS -->
    <style>
    body {
        background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)),
            url('assets/img/backgrounds/inside-background.jpeg');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        background-repeat: no-repeat;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .error-container {
        max-width: 600px;
        margin: 0 auto;
        text-align: center;
        background: rgba(255, 255, 255, 0.95);
        padding: 3rem;
        border-radius: 1rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
    }

    .error-icon {
        font-size: 5rem;
        color: #ff6b6b;
        margin-bottom: 1.5rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }

    .error-title {
        color: #ff6b6b;
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .error-message {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        line-height: 1.6;
    }

    .error-help {
        color: #666;
        font-size: 1rem;
        margin-bottom: 2rem;
        padding: 1rem;
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        border-left: 4px solid #ffa726;
    }

    .role-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 1rem 0;
    }

    .role-staff {
        background-color: #e3f2fd;
        color: #1976d2;
        border: 2px solid #bbdefb;
    }

    .role-manager {
        background-color: #e8f5e8;
        color: #388e3c;
        border: 2px solid #c8e6c9;
    }

    .role-admin {
        background-color: #ffebee;
        color: #d32f2f;
        border: 2px solid #ffcdd2;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 2rem;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 140px;
        justify-content: center;
    }

    .btn-primary {
        background-color: #696cff;
        color: white;
        border: none;
    }

    .btn-primary:hover {
        background-color: #5f63f2;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(105, 108, 255, 0.3);
        color: white;
    }

    .btn-outline {
        background-color: transparent;
        color: #566a7f;
        border: 2px solid #d9dee3;
    }

    .btn-outline:hover {
        background-color: #f5f5f9;
        border-color: #696cff;
        color: #696cff;
        transform: translateY(-2px);
    }

    .btn-danger {
        background-color: #ff4757;
        color: white;
        border: none;
    }

    .btn-danger:hover {
        background-color: #ff3742;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 71, 87, 0.3);
        color: white;
    }

    .user-info {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        margin: 1.5rem 0;
        border: 1px solid #e9ecef;
    }

    .contact-info {
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid #e9ecef;
        color: #666;
        font-size: 0.9rem;
    }

    .logo {
        margin-bottom: 2rem;
    }

    .logo img {
        max-width: 200px;
        height: auto;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .error-container {
            margin: 1rem;
            padding: 2rem;
        }

        .error-title {
            font-size: 2rem;
        }

        .error-icon {
            font-size: 4rem;
        }

        .action-buttons {
            flex-direction: column;
            align-items: center;
        }

        .btn {
            width: 100%;
            max-width: 250px;
        }
    }
    </style>
</head>

<body>
    <div class="error-container">
        <!-- Logo -->
        <div class="logo">
            <img src="assets/img/icons/brands/inventomo.png" alt="Inventomo Logo">
        </div>

        <!-- Error Icon -->
        <div class="error-icon">
            <i class="bx bx-lock-alt"></i>
        </div>

        <!-- Error Title -->
        <h1 class="error-title">Access Denied</h1>

        <!-- Error Message -->
        <p class="error-message">
            <?php echo htmlspecialchars($message); ?>
        </p>

        <!-- User Info -->
        <div class="user-info">
            <strong>Current User:</strong> <?php echo htmlspecialchars($current_user_name); ?>
            <div class="role-badge role-<?php echo $current_user_role; ?>">
                <?php echo htmlspecialchars(ucfirst($current_user_role)); ?> Role
            </div>
        </div>

        <!-- Help Text -->
        <div class="error-help">
            <i class="bx bx-info-circle"></i>
            <?php echo htmlspecialchars($help_text); ?>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">
                <i class="bx bx-home"></i>
                Go to Dashboard
            </a>
            
            <a href="javascript:history.back()" class="btn btn-outline">
                <i class="bx bx-arrow-back"></i>
                Go Back
            </a>
            
            <?php if ($current_user_role === 'staff'): ?>
            <a href="mailto:admin@company.com?subject=Access Request - User Management" class="btn btn-outline">
                <i class="bx bx-envelope"></i>
                Request Access
            </a>
            <?php endif; ?>
            
            <a href="logout.php" class="btn btn-danger">
                <i class="bx bx-log-out"></i>
                Logout
            </a>
        </div>

        <!-- Contact Information -->
        <div class="contact-info">
            <p><strong>Need Help?</strong></p>
            <p>Contact your system administrator or IT support team for assistance.</p>
            
            <?php if ($current_user_role === 'staff'): ?>
            <p><em>Staff members: Please contact your manager or administrator to request access to user management features.</em></p>
            <?php elseif ($current_user_role === 'manager'): ?>
            <p><em>Managers: You may have view-only access to some features. Contact an administrator for full editing privileges.</em></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>

    <script>
    // Log access denial for security purposes
    console.log('Access denied for user:', '<?php echo $current_user_role; ?>');
    
    // Auto-redirect after 30 seconds (optional)
    // setTimeout(function() {
    //     window.location.href = 'index.php';
    // }, 30000);

    // Add some interactivity
    document.addEventListener('DOMContentLoaded', function() {
        // Animate the error icon
        const errorIcon = document.querySelector('.error-icon');
        if (errorIcon) {
            errorIcon.addEventListener('click', function() {
                this.style.animation = 'none';
                setTimeout(() => {
                    this.style.animation = 'pulse 2s infinite';
                }, 10);
            });
        }

        // Add hover effects to buttons
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Show additional info based on the reason
        const reason = '<?php echo $reason; ?>';
        if (reason === 'admin_only') {
            console.log('This page requires administrator privileges');
        } else if (reason === 'manager_admin_only') {
            console.log('This page requires manager or administrator privileges');
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Press 'H' to go home
        if (e.key.toLowerCase() === 'h') {
            window.location.href = 'index.php';
        }
        
        // Press 'B' to go back
        if (e.key.toLowerCase() === 'b') {
            history.back();
        }
        
        // Press Escape to go back
        if (e.key === 'Escape') {
            history.back();
        }
    });
    </script>
</body>

</html>