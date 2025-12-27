<?php
session_start();
include("./db/connection.php");

date_default_timezone_set('Asia/Kolkata');

$msg = "";

if (!isset($_GET['token'])) {
    header("Location: forgot_password.php");
    exit;
}

$token = $_GET['token'];

// Validate token
$stmt = $pdo->prepare("SELECT volunteer_id, expires_at FROM password_reset_tokens WHERE token = ?");
$stmt->execute([$token]);
$valid_token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$valid_token) {
    $msg = "<div class='alert alert-danger'>Invalid reset link.</div>";
} else {
    if (strtotime($valid_token['expires_at']) < time()) {
        $msg = "<div class='alert alert-danger'>Reset link has expired.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST" && empty($msg) && $valid_token) {

    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $msg = "<div class='alert alert-danger'>Passwords do not match.</div>";
    } else {

        // ✅ MD5 because your login uses md5
        $hashed = md5($new_password);

        $update_stmt = $pdo->prepare("UPDATE volunteers SET password = ? WHERE volunteer_id = ?");
        if ($update_stmt->execute([$hashed, $valid_token['volunteer_id']])) {

            // Delete token after use
            $delete_stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
            $delete_stmt->execute([$token]);

            $msg = "<div class='alert alert-success'>
                Password updated successfully!
                <a href='login.php'>Login here</a>
            </div>";
        } else {
            $msg = "<div class='alert alert-danger'>Password reset failed.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Navneet College of Arts ,Science & Commerce.</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
        }

        .navbar {
            background: linear-gradient(135deg, var(--dark-bg) 0%, #0f172a 100%);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

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

        @media (max-width: 768px) {
            body { padding-top: 70px; }
            .btn { font-size: 0.85rem; padding: 10px 16px; }
        }
    </style>
</head>
<body>

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
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
                </ul>
            </div>
        </div>
    </nav>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card p-4 shadow">

                <h3 class="text-center mb-3">Reset Password</h3>

                <?= $msg; ?>

                <?php if (strpos($msg, 'successfully') === false): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button class="btn btn-primary w-100">Reset Password</button>
                </form>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="login.php">Back to Login</a>
                </div>

            </div>
        </div>
    </div>
</div>

</body>
</html>
