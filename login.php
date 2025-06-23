<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize the session
session_start();

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";
$db_error = "";

// Check if the user is already logged in, if yes then redirect to index page
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: index.php");
    exit;
}

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_system';


// Attempt to connect to MySQL database
$mysqli = @new mysqli($host, $user, $pass, $dbname);

// Check connection
if($mysqli->connect_error){
    $db_error = "Database connection failed: " . $mysqli->connect_error;
} else {
    // Processing form data when form is submitted
    if($_SERVER["REQUEST_METHOD"] == "POST"){
    
        // Check if email is empty
        if(empty(trim($_POST["email"]))){
            $email_err = "Please enter your email.";
        } else{
            $email = trim($_POST["email"]);
        }
        
        // Check if password is empty
        if(empty(trim($_POST["password"]))){
            $password_err = "Please enter your password.";
        } else{
            $password = trim($_POST["password"]);
        }
        
        // Validate credentials
        if(empty($email_err) && empty($password_err)){
            try {
                // Prepare a select statement - using only email for login
                $sql = "SELECT Id, username, email, password, full_name, position, active FROM user_profiles WHERE email = ?";
                
                if($stmt = $mysqli->prepare($sql)){
                    // Bind variables to the prepared statement as parameters
                    $stmt->bind_param("s", $param_email);
                    
                    // Set parameters
                    $param_email = $email;
                    
                    // Attempt to execute the prepared statement
                    if($stmt->execute()){
                        // Store result
                        $stmt->store_result();
                        
                        // Check if email exists, if yes then verify password
                        if($stmt->num_rows == 1){                    
                            // Bind result variables
                            $stmt->bind_result($id, $username, $db_email, $hashed_password, $full_name, $position, $active);
                            if($stmt->fetch()){
                                // Check if account is active
                                if($active == 0){
                                    $login_err = "Your account is not activated. Please contact administrator.";
                                } else {
                                    // Check if the password verification is successful
                                    if(password_verify($password, $hashed_password)){
                                        // Password is correct, so start a new session
                                        
                                        // Store data in session variables
                                        $_SESSION["loggedin"] = true;
                                        $_SESSION["user_id"] = $id;  // FIXED: Changed from "id" to "user_id"
                                        $_SESSION["username"] = $username;
                                        $_SESSION["email"] = $db_email;
                                        $_SESSION["full_name"] = $full_name;
                                        $_SESSION["position"] = $position;
                                        
                                        // Remember me functionality
                                        if(isset($_POST["remember-me"]) && $_POST["remember-me"] == "on"){
                                            // Generate a random token
                                            $token = bin2hex(random_bytes(16));
                                            
                                            // Store token in database
                                            $update_sql = "UPDATE user_profiles SET remember_token = ? WHERE Id = ?";
                                            if($update_stmt = $mysqli->prepare($update_sql)){
                                                $update_stmt->bind_param("ss", $token, $id);
                                                $update_stmt->execute();
                                                $update_stmt->close();
                                                
                                                // Set cookie
                                                setcookie("remember_me", $token, time() + (86400 * 30), "/"); // 30 days
                                            }
                                        }
                                        
                                        // Redirect user to dashboard page
                                        header("location: index.php");
                                        exit(); // Make sure to exit after redirect
                                    } else{
                                        // Password is not valid
                                        $login_err = "Invalid email or password.";
                                    }
                                }
                            }
                        } else{
                            // Email doesn't exist
                            $login_err = "Invalid email or password.";
                        }
                    } else{
                        $login_err = "Something went wrong with the query execution. Error: " . $stmt->error;
                    }
    
                    // Close statement
                    $stmt->close();
                } else {
                    $login_err = "Something went wrong with query preparation. Error: " . $mysqli->error;
                }
            } catch (Exception $e) {
                $login_err = "An exception occurred: " . $e->getMessage();
            }
        }
    }
    
    // Close connection
    $mysqli->close();
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
    <style>
      body {
    background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), 
                url('assets/img/backgrounds/background.jpg');
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
    </style>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>Login - Inventomo</title>

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
    <!-- Page -->
    <link rel="stylesheet" href="assets/vendor/css/pages/page-auth.css" />
    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="assets/js/config.js"></script>

    <style>
      h4 {text-align: center;}
    </style>
  </head>

  <body>
    <!-- Content -->
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
          <!-- Register -->
          <div class="card">
            <div class="card-body">
              <!-- Logo -->
              <div class="app-brand justify-content-center">
                <span class="app-brand-logo demo">
                  <img
                    width="200"
                    viewBox="0 0 25 42"
                    version="1.1"
                    src="assets/img/icons/brands/inventomo.png"
                  >
                </span>
              </div>
              <!-- /Logo -->
              <h4 class="mb-2">Welcome to Inventomo! 👋</h4><br>

              <!-- Display error messages here -->
              <?php 
              if(!empty($db_error)){
                  echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($db_error) . '</div>';
              }
              if(!empty($login_err)){
                  echo '<div class="alert alert-danger">' . htmlspecialchars($login_err) . '</div>';
              }        
              ?>

              <form id="formAuthentication" class="mb-3" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="mb-3">
                  <label for="email" class="form-label">Email</label>
                  <input
                    type="email"
                    class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    autofocus
                    required
                  />
                  <span class="invalid-feedback"><?php echo htmlspecialchars($email_err); ?></span>
                </div>
                <div class="mb-3 form-password-toggle">
                  <div class="d-flex justify-content-between">
                    <label class="form-label" for="password">Password</label>
                    <a href="forgot-password.php">
                      <small>Forgot Password?</small>
                    </a>
                  </div>
                  <div class="input-group input-group-merge">
                    <input
                      type="password"
                      id="password"
                      class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>"
                      name="password"
                      placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                      aria-describedby="password"
                      required
                    />
                    <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                    <span class="invalid-feedback"><?php echo htmlspecialchars($password_err); ?></span>
                  </div>
                </div>
                <div class="mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember-me" name="remember-me" />
                    <label class="form-check-label" for="remember-me"> Remember Me </label>
                  </div>
                </div>
                <div class="mb-3">
                  <button class="btn btn-primary d-grid w-100" type="submit">Sign in</button>
                </div>
              </form>

              <p class="text-center">
                <span>New on our platform?</span>
                <a href="register.php">
                  <span>Create an account</span>
                </a>
              </p>
            </div>
          </div>
          <!-- /Register -->
        </div>
      </div>
    </div>

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->

    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
  </body>
</html>