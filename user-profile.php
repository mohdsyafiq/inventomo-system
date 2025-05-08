<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile Edit</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        .search-container {
            display: flex;
            align-items: center;
            background-color: #f5f7ff;
            border-radius: 8px;
            padding: 8px 15px;
            width: 300px;
        }

        .search-container input {
            border: none;
            outline: none;
            width: 100%;
            padding: 5px;
            font-size: 14px;
            background-color: transparent;
        }

        .search-icon {
            color: #777;
            margin-right: 10px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: white;
            font-weight: bold;
        }

        .page-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
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

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #6366f1;
            outline: none;
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

        input:checked + .toggle-slider {
            background-color: #10b981;
        }

        input:checked + .toggle-slider:before {
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
</head>
<body>
    <div class="container">
        <!-- Header -->
        
        
        <h1 class="page-title">User Profile</h1>
        
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-title">Profile Details</div>
                <div class="action-buttons">
                <a href="user.php" class="btn btn-outline">Cancel</a>
                    <button class="btn btn-primary">Save Changes</button>
                </div>
            </div>
            
            <form>
                <div class="profile-image-section">
                    <div class="profile-image">
                        <img src="/api/placeholder/120/120" alt="Profile avatar">
                    </div>
                    <div class="image-upload-details">
                        <h3 class="upload-title">Profile Picture</h3>
                        <p class="upload-desc">Upload a new profile picture. Recommended size: 300x300px, max 2MB.</p>
                        <div class="upload-actions">
                            <input type="file" id="profile-pic" class="file-input" accept="image/*">
                            <label for="profile-pic" class="btn btn-outline">Upload New</label>
                            <button type="button" class="btn btn-outline">Remove</button>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="userId" class="form-label">User ID</label>
                        <input type="text" id="userId" class="form-control readonly-field" value="UID-23456789" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="created-date" class="form-label">Created Date</label>
                        <input type="text" id="created-date" class="form-control readonly-field" value="May 12, 2023" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="fullName" class="form-label">Full Name</label>
                        <input type="text" id="fullName" class="form-control" value="John Smith">
                    </div>
                    
                    <div class="form-group">
                        <label for="updated-date" class="form-label">Last Updated</label>
                        <input type="text" id="updated-date" class="form-control readonly-field" value="April 28, 2025" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" class="form-control" value="john.smith@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" id="phone" class="form-control" value="+1 (555) 123-4567">
                    </div>
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" class="form-control" value="johnsmith">
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">Role/Permission</label>
                        <select id="role" class="form-select">
                            <option value="user">User</option>
                            <option value="moderator">Moderator</option>
                            <option value="admin" selected>Admin</option>
                            <option value="super-admin">Super Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" class="form-control" placeholder="●●●●●●●●">
                        <p class="info-text">Leave blank to keep current password</p>
                    </div>
                    
                    <div class="form-group status-toggle">
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                        <div class="status-text active-status">Active</div>
                    </div>
                    
                    <div class="form-group form-span-2">
                        <button type="button" class="btn btn-outline">Reset Password</button>
                        <p class="info-text">Send password reset link to user's email</p>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>