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
$Id = '';
$date_join = '';
$full_name = '';
$email = '';
$phone_no = '';
$username = '';
$password = '';
$position = 'user'; // Default position
$profile_picture = 'default.jpg'; // Default profile picture
$active = isset($_POST['status']) ? 1 : 0; // checkbox status
$error = '';
$success = '';

// Try to establish database connection with error handling
try {
    $conn = mysqli_connect($host, $user, $pass, $dbname);
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Process form submission
    if (isset($_POST['submit'])) {
        // Validate and sanitize input data
        if (isset($_POST['Id'])) $Id = mysqli_real_escape_string($conn, $_POST['Id']);
        if (isset($_POST['date_join'])) $date_join = mysqli_real_escape_string($conn, $_POST['date_join']);
        if (isset($_POST['full_name'])) $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        if (isset($_POST['email'])) $email = mysqli_real_escape_string($conn, $_POST['email']);
        if (isset($_POST['phone_no'])) $phone_no = mysqli_real_escape_string($conn, $_POST['phone_no']);
        if (isset($_POST['username'])) $username = mysqli_real_escape_string($conn, $_POST['username']);
        if (isset($_POST['password'])) $password = mysqli_real_escape_string($conn, $_POST['password']);
        if (isset($_POST['position'])) $position = mysqli_real_escape_string($conn, $_POST['position']);
        
        
        // Handle profile picture upload (if implemented)
        // For now, we'll use a default value
        
        // Validate required fields
        if($Id && $date_join && $full_name && $email && $phone_no && $username && $password) {
            $sql = "INSERT INTO user_profiles(Id, date_join, full_name, email, phone_no, username, password, position,  profile_picture, active) 
                    VALUES ('$Id', '$date_join', '$full_name', '$email', '$phone_no', '$username', '$password', '$position', '$profile_picture' ,'$active')";
            
            $q1 = mysqli_query($conn, $sql);
            
            if($q1) {
                $success = "Data inserted successfully!";
            } else {
                $error = "Data insertion failed: " . mysqli_error($conn);
            }
        } else {
            $error = "All fields are required!";
        }
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>
