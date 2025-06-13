<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';

// Initialize variables before any processing
$email = '';
$username = '';
$password = '';
$full_name = '';
$phone_no = '';
$success = '';
$error = '';

// Try to establish database connection with error handling
try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Process form submission
    if (isset($_POST['submit'])) {
        // Validate and sanitize input data
        $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, trim($_POST['email'])) : '';
        $username = isset($_POST['username']) ? mysqli_real_escape_string($conn, trim($_POST['username'])) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $full_name = isset($_POST['full_name']) ? mysqli_real_escape_string($conn, trim($_POST['full_name'])) : '';
        $phone_no = isset($_POST['phone_no']) ? mysqli_real_escape_string($conn, trim($_POST['phone_no'])) : '';
        
        // Validate required fields
        if($email && $username && $password && $full_name && $phone_no) {
            // Check if email or username already exists
            $check_sql = "SELECT Id FROM user_profiles WHERE email = '$email' OR username = '$username'";
            $check_result = mysqli_query($conn, $check_sql);
            
            if(mysqli_num_rows($check_result) > 0) {
                $error = "Email or username already exists!";
            } else {
                // Generate new ID: ABxxxx
                $query = "SELECT MAX(Id) as Id FROM user_profiles WHERE Id LIKE 'AB%'";
                $result = mysqli_query($conn, $query);
                $row = mysqli_fetch_assoc($result);
                         
                if ($row && $row['Id']) {
                    // Extract numeric part and increment
                    $last_num = (int)substr($row['Id'], 2);
                    $new_num = $last_num + 1;
                    $id = 'AB' . str_pad($new_num, 4, '0', STR_PAD_LEFT);
                } else {
                    $id = 'AB0001'; // First ID
                }
                
                // Hash password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user data into user_profiles table
                $sql = "INSERT INTO user_profiles (Id, date_join, full_name, email, phone_no, username, password, position, profile_picture, active) 
                        VALUES ('$id', CURDATE(), '$full_name', '$email', '$phone_no', '$username', '$hashed_password', 'user', 'default.jpg', 1)";
                
                $q1 = mysqli_query($conn, $sql);
                
                if($q1) {
                    $success = "Registration successful! You can now login.";
                    // Reset form fields after successful submission
                    $email = $username = $password = $full_name = $phone_no = '';
                } else {
                    $error = "Registration failed: " . mysqli_error($conn);
                }
            }
        } else {
            $error = "All fields are required!";
        }
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html
  lang="en"
  class="light-style customizer-hide"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>Register</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/inventomo.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="assets/vendor/css/pages/page-auth.css" />
    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>

    <script src="assets/js/config.js"></script>
  </head>

  <body>
    <!-- Content -->
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
          <!-- Register Card -->
          <div class="card">
            <div class="card-body">
              <!-- Logo -->
              <div class="app-brand justify-content-center">
                <span class="app-brand-logo demo">
                    <img
                      width="200"
                      src="assets/img/icons/brands/inventomo.png"
                      alt="Inventomo Logo"
                    >
                </span>
              </div>
              <!-- /Logo -->
              <h4 class="mb-2">Adventure starts here 🚀</h4>
              <p class="mb-4">Make your app management easy and fun!</p>

              <?php if($success): ?>
              <div class="alert alert-success"><?php echo $success; ?></div>
              <?php endif; ?>
              
              <?php if($error): ?>
              <div class="alert alert-danger"><?php echo $error; ?></div>
              <?php endif; ?>

              <form id="formAuthentication" class="mb-3" action="" method="POST">
                <div class="mb-3">
                  <label for="full_name" class="form-label">Full Name</label>
                  <input
                    type="text"
                    class="form-control"
                    id="full_name"
                    name="full_name"
                    placeholder="Enter your full name"
                    autofocus
                    value="<?php echo htmlspecialchars($full_name); ?>"
                    required
                  />
                </div>
                <div class="mb-3">
                  <label for="username" class="form-label">Username</label>
                  <input
                    type="text"
                    class="form-control"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    value="<?php echo htmlspecialchars($username); ?>"
                    required
                  />
                </div>
                <div class="mb-3">
                  <label for="email" class="form-label">Email</label>
                  <input 
                    type="email" 
                    class="form-control" 
                    id="email" 
                    name="email" 
                    placeholder="Enter your email" 
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                  />
                </div>
                <div class="mb-3">
                  <label for="phone_no" class="form-label">Phone Number</label>
                  <input 
                    type="tel" 
                    class="form-control" 
                    id="phone_no" 
                    name="phone_no" 
                    placeholder="Enter your phone number" 
                    value="<?php echo htmlspecialchars($phone_no); ?>"
                    required
                  />
                </div>
                <div class="mb-3 form-password-toggle">
                  <label class="form-label" for="password">Password</label>
                  <div class="input-group input-group-merge">
                    <input
                      type="password"
                      id="password"
                      class="form-control"
                      name="password"
                      placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                      aria-describedby="password"
                      required
                    />
                    <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                  </div>
                </div>


                <button type="submit" name="submit" class="btn btn-primary d-grid w-100">Sign up</button>
              </form>

              <p class="text-center">
                <span>Already have an account?</span>
                <a href="login.php">
                  <span>Sign in instead</span>
                </a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Core JS -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/js/menu.js"></script>
    <script src="assets/js/main.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
  </body>
</html>
