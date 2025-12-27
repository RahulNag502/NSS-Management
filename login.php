<?php
session_start();
include("./db/connection.php");
$msg = "";

// Check for logout message
if (isset($_GET['message']) && $_GET['message'] == 'logout') {
    $logout_user = $_GET['user'] ?? '';
    $msg = "<div class='alert alert-info'>
        <i class='fas fa-check-circle'></i> 
        " . htmlspecialchars($logout_user) . "
    </div>";
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $username = trim($_POST['username']);
    $password = md5($_POST['password']);
    $role = $_POST['role'];

    // Clear any previous messages
    $msg = "";

    // Log login attempt
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    if ($role == "admin") {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username=? AND password=?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['admin'] = $user['username'];
            
            try {
                // Check if action column exists
                $check_column = $pdo->prepare("SHOW COLUMNS FROM login_activity LIKE 'action'");
                $check_column->execute();
                $column_exists = $check_column->fetch();
                
                if ($column_exists) {
                    // Log login activity with action column
                    $log_stmt = $pdo->prepare("INSERT INTO login_activity (user_id, user_type, login_time, ip_address, action) VALUES (?, 'admin', NOW(), ?, 'login')");
                    $log_stmt->execute([$user['username'], $ip_address]);
                } else {
                    // Log login activity without action column
                    $log_stmt = $pdo->prepare("INSERT INTO login_activity (user_id, user_type, login_time, ip_address) VALUES (?, 'admin', NOW(), ?)");
                    $log_stmt->execute([$user['username'], $ip_address]);
                }
            } catch (Exception $e) {
                // Silently continue even if logging fails
                error_log("Login activity logging failed: " . $e->getMessage());
            }
            
            header("Location: admin/dashboard.php");
            exit;
        } else {
            $msg = "<div class='alert alert-danger'>
                <i class='fas fa-exclamation-triangle'></i> 
                Invalid Admin Credentials
            </div>";
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM volunteers WHERE volunteer_id=? AND password=?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['volunteer'] = $user['volunteer_id'];
            
            try {
                // Check if action column exists
                $check_column = $pdo->prepare("SHOW COLUMNS FROM login_activity LIKE 'action'");
                $check_column->execute();
                $column_exists = $check_column->fetch();
                
                if ($column_exists) {
                    // Log login activity with action column
                    $log_stmt = $pdo->prepare("INSERT INTO login_activity (user_id, user_type, login_time, ip_address, action) VALUES (?, 'volunteer', NOW(), ?, 'login')");
                    $log_stmt->execute([$user['volunteer_id'], $ip_address]);
                } else {
                    // Log login activity without action column
                    $log_stmt = $pdo->prepare("INSERT INTO login_activity (user_id, user_type, login_time, ip_address) VALUES (?, 'volunteer', NOW(), ?)");
                    $log_stmt->execute([$user['volunteer_id'], $ip_address]);
                }
            } catch (Exception $e) {
                // Silently continue even if logging fails
                error_log("Login activity logging failed: " . $e->getMessage());
            }
            
            header("Location: volunteer/dashboard.php");
            exit;
        } else {
            $msg = "<div class='alert alert-danger'>
                <i class='fas fa-exclamation-triangle'></i> 
                Invalid Volunteer ID or Password
            </div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Navneet College of Arts ,Science & Commerce.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
        }
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .brand-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .college-logo {
            height: 60px;
            width: auto;
            border-radius: 5px;
        }
        .navbar {
            position: fixed !important;
            top: 0;
            width: 100%;
            z-index: 1030;
        }
        body {
            padding-top: 80px;
        }
        .alert i {
            margin-right: 8px;
        }

        /* CSS Variables and Modern Base Styles */
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-bg: #1f2937;
            --light-bg: #f9fafb;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { transition: var(--transition); }

        body {
            padding-top: 80px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
        }

        .navbar {
            position: fixed !important;
            top: 0;
            width: 100%;
            z-index: 1030;
            background: linear-gradient(135deg, var(--dark-bg) 0%, #0f172a 100%);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .brand-container { display: flex; align-items: center; gap: 15px; }
        .college-logo { height: 60px; width: auto; border-radius: 8px; }
        .college-logo:hover { transform: scale(1.05); filter: brightness(1.1); }
        .navbar-brand { font-weight: 700; font-size: 1.2rem; color: white !important; }
        .navbar-brand:hover { color: var(--primary-color) !important; }
        .nav-link { color: rgba(255, 255, 255, 0.8) !important; font-weight: 500; position: relative; }
        .nav-link:hover, .nav-link.active { color: var(--primary-color) !important; }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transform: translateX(-50%);
            transition: var(--transition);
        }
        .nav-link:hover::after, .nav-link.active::after { width: 80%; }

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            background: white;
            overflow: hidden;
        }
        .card:hover { box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: translateY(-5px); }
        .card-body h1, .card-body h2, .card-body h3 {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            margin-bottom: 25px;
        }

        .form-label { font-weight: 600; color: var(--text-primary); margin-bottom: 8px; }
        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 15px;
            background-color: var(--light-bg);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background-color: white;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white !important;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4); color: white !important; }
        .btn-success { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); color: white !important; }
        .btn-success:hover { transform: translateY(-2px); }
        .btn-danger { background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%); color: white !important; }
        .btn-danger:hover { transform: translateY(-2px); }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
        }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background-color: rgba(16, 185, 129, 0.1); color: #047857; border-left-color: var(--success-color); }
        .alert-danger { background-color: rgba(239, 68, 68, 0.1); color: #991b1b; border-left-color: var(--danger-color); }
        .alert-info { background-color: rgba(59, 130, 246, 0.1); color: #1e40af; border-left-color: #3b82f6; }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }
        .table tbody tr:hover { background-color: rgba(102, 126, 234, 0.05); }

        .badge {
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .badge-success { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); }
        .badge-danger { background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%); }

        @media (max-width: 768px) {
            body { padding-top: 70px; }
            .btn { font-size: 0.85rem; padding: 10px 16px; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <div class="brand-container">
                <a class="navbar-brand" href="index.php">
                    <img src="./assets/images/nss_logo.png" alt="NSS Logo" height="50" class="me-2">
                    Navneet College of Arts ,Science & Commerce.
                </a>
                <img src="./assets/images/college_logo.png" alt="College Logo" class="college-logo">
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto" style="font-size: 1.1rem;">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#gallery">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link active" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">
                        <i class="fas fa-lock text-primary"></i> Login
                    </h2>
                    <?= $msg; ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-user me-1"></i> Login As
                            </label>
                            <select name="role" class="form-select" required onchange="updatePlaceholder()">
                                <option value="volunteer">Volunteer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" id="usernameLabel">
                                <i class="fas fa-id-card me-1"></i> Volunteer ID
                            </label>
                            <input type="text" name="username" class="form-control" placeholder="Enter your Volunteer ID" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-key me-1"></i> Password
                            </label>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </button>
                        <!-- Add this in the login form after the submit button -->
<div class="text-center mt-3">
    <p><a href="forgot_password.php" class="text-decoration-none">Forgot your password?</a></p>
</div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p>Don't have an account? 
                            <a href="register.php" class="text-decoration-none">
                                <i class="fas fa-user-plus me-1"></i> Register as Volunteer
                            </a>
                        </p>
                        <p>
                            <a href="index.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i> Back to Home
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            

    <script>
        function updatePlaceholder() {
            const role = document.querySelector('select[name="role"]').value;
            const input = document.querySelector('input[name="username"]');
            const label = document.getElementById('usernameLabel');
            
            if (role === 'admin') {
                label.innerHTML = '<i class="fas fa-user me-1"></i> Username';
                input.placeholder = 'Enter your username';
            } else {
                label.innerHTML = '<i class="fas fa-id-card me-1"></i> Volunteer ID';
                input.placeholder = 'Enter your Volunteer ID';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', updatePlaceholder);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>