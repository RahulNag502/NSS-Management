<?php
session_start();
include("./db/connection.php");
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $year = $_POST['year'];
    $password = md5($_POST['password']);
    $profile_image = null;

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "<div class='alert alert-danger'>Please enter a valid email address.</div>";
    } else {
        // Check if email already exists
        $check_email = $pdo->prepare("SELECT id FROM volunteers WHERE email = ?");
        $check_email->execute([$email]);
        
        if ($check_email->fetch()) {
            $msg = "<div class='alert alert-danger'>Email already registered. Please use a different email.</div>";
        } else {
            // Handle profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = './assets/profile_images/';
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                $fileExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                
                if (in_array($fileExtension, $allowedTypes)) {
                    if ($_FILES['profile_image']['size'] <= 2 * 1024 * 1024) { // 2MB
                        $fileName = 'profile_' . time() . '_' . uniqid() . '.' . $fileExtension;
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                            $profile_image = $fileName;
                        }
                    } else {
                        $msg = "<div class='alert alert-warning'>Profile image too large. Maximum size is 2MB.</div>";
                    }
                } else {
                    $msg = "<div class='alert alert-warning'>Invalid image format. Please use JPG, PNG, or GIF.</div>";
                }
            }
            
            // Generate unique Volunteer ID
            $volunteer_id = "V" . strtoupper(bin2hex(random_bytes(3)));

            try {
                $stmt = $pdo->prepare("INSERT INTO volunteers (volunteer_id, name, email, phone, department, year, password, profile_image) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$volunteer_id, $name, $email, $phone, $department, $year, $password, $profile_image]);
                
                // Send welcome email
                require_once "./includes/EmailSender.php";
                $emailSender = new EmailSender();
                $emailResult = $emailSender->sendWelcomeEmail($name, $email, $volunteer_id);
                
                $msg = "<div class='alert alert-success'>
                    <h5>Registration Successful!</h5>
                    <p class='mb-1'><strong>Your Volunteer ID:</strong> <code>$volunteer_id</code></p>
                    <p class='mb-0'><strong>Please remember this ID for login.</strong></p>";
                
                if ($emailResult['success']) {
                    $msg .= "<p class='mt-2'><i class='fas fa-envelope text-success'></i> Welcome email sent to your registered email address.</p>";
                } else {
                    $msg .= "<p class='mt-2 text-warning'><i class='fas fa-exclamation-triangle'></i> Registration successful, but email notification failed.</p>";
                }
                
                $msg .= "</div>";
                
                // Clear form
                $_POST = array();
                
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    // Duplicate volunteer_id, try again
                    $volunteer_id = "V" . strtoupper(bin2hex(random_bytes(3)));
                    $stmt->execute([$volunteer_id, $name, $email, $phone, $department, $year, $password, $profile_image]);
                    
                    // Send welcome email for retry
                    require_once "./includes/EmailSender.php";
                    $emailSender = new EmailSender();
                    $emailResult = $emailSender->sendWelcomeEmail($name, $email, $volunteer_id);
                    
                    $msg = "<div class='alert alert-success'>
                        <h5>Registration Successful!</h5>
                        <p class='mb-1'><strong>Your Volunteer ID:</strong> <code>$volunteer_id</code></p>
                        <p class='mb-0'><strong>Please remember this ID for login.</strong></p>";
                    
                    if ($emailResult['success']) {
                        $msg .= "<p class='mt-2'><i class='fas fa-envelope text-success'></i> Welcome email sent to your registered email address.</p>";
                    } else {
                        $msg .= "<p class='mt-2 text-warning'><i class='fas fa-exclamation-triangle'></i> Registration successful, but email notification failed.</p>";
                    }
                    
                    $msg .= "</div>";
                    
                    $_POST = array();
                } else {
                    $msg = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Navneet College of Arts ,Science & Commerce.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Color Scheme */
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
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Global Styles */
        * {
            transition: var(--transition);
        }

        body {
            padding-top: 80px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
        }

        /* Navbar Styling */
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

        .brand-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .college-logo {
            height: 60px;
            width: auto;
            border-radius: 8px;
            transition: var(--transition);
        }

        .college-logo:hover {
            transform: scale(1.05);
            filter: brightness(1.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: white !important;
            letter-spacing: -0.5px;
        }

        .navbar-brand:hover {
            color: var(--primary-color) !important;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 500;
            position: relative;
            margin: 0 5px;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--primary-color) !important;
        }

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

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 80%;
        }

        /* Register Container */
        .register-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .register-card {
            border: none;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            background: white;
            overflow: hidden;
            transition: var(--transition);
        }

        .register-card:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            transform: translateY(-5px);
        }

        .card-body {
            background: white;
        }

        .card-body h2 {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            margin-bottom: 30px;
            font-size: 2rem;
        }

        /* Form Styling */
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-control,
        .form-select {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: var(--transition);
            background-color: var(--light-bg);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background-color: white;
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .form-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        /* Button Styling */
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white !important;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
            color: white !important;
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background-color: var(--light-bg);
            color: var(--primary-color) !important;
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: var(--primary-color);
            color: white !important;
        }

        /* Alert Styling */
        .alert {
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: #047857;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border-left: 4px solid var(--warning-color);
        }

        /* File Input */
        .form-control[type="file"]::file-selector-button {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .form-control[type="file"]::file-selector-button:hover {
            transform: scale(1.05);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .register-container {
                margin: 20px auto;
            }

            .card-body h2 {
                font-size: 1.5rem;
            }

            .btn {
                width: 100%;
            }
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
                    <li class="nav-item"><a class="nav-link active" href="register.php">Register</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="register-container">
            <div class="card register-card">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">👤 Volunteer Registration</h2>
                    <?= $msg; ?>
                    
                    <!-- Registration Form -->
                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" pattern="[0-9]{10}">
                            <div class="form-text">10-digit phone number (optional)</div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Department *</label>
                            <select name="department" class="form-select" required>
                                <option value="">Select Department</option>
                                <option value="Computer Science" <?= ($_POST['department'] ?? '') == 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                <option value="Electronics" <?= ($_POST['department'] ?? '') == 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                <option value="Mechanical" <?= ($_POST['department'] ?? '') == 'Mechanical' ? 'selected' : '' ?>>Mechanical</option>
                                <option value="Civil" <?= ($_POST['department'] ?? '') == 'Civil' ? 'selected' : '' ?>>Civil</option>
                                <option value="Electrical" <?= ($_POST['department'] ?? '') == 'Electrical' ? 'selected' : '' ?>>Electrical</option>
                                <option value="Science" <?= ($_POST['department'] ?? '') == 'Science' ? 'selected' : '' ?>>Science</option>
                                <option value="Arts" <?= ($_POST['department'] ?? '') == 'Arts' ? 'selected' : '' ?>>Arts</option>
                                <option value="Commerce" <?= ($_POST['department'] ?? '') == 'Commerce' ? 'selected' : '' ?>>Commerce</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Year *</label>
                            <select name="year" class="form-select" required>
                                <option value="">Select Year</option>
                                <option value="FY" <?= ($_POST['year'] ?? '') == 'FY' ? 'selected' : '' ?>>First Year (FY)</option>
                                <option value="SY" <?= ($_POST['year'] ?? '') == 'SY' ? 'selected' : '' ?>>Second Year (SY)</option>
                                <option value="TY" <?= ($_POST['year'] ?? '') == 'TY' ? 'selected' : '' ?>>Third Year (TY)</option>
                                <option value="Final" <?= ($_POST['year'] ?? '') == 'Final' ? 'selected' : '' ?>>Final Year</option>
                            </select>
                        </div>
                        
                        <!-- Profile Image Upload -->
                        <div class="col-md-6">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="profile_image" class="form-control" accept="image/*">
                            <div class="form-text">Upload a clear face photo (JPG, PNG, GIF, Max: 2MB)</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" required>
                                <label class="form-check-label">
                                    I agree to the <a href="#" class="text-decoration-none">Terms and Conditions</a>
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-success w-100 py-2">Register as Volunteer</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p>Already registered? <a href="login.php" class="text-decoration-none">Login here</a></p>
                        <p><a href="index.php" class="text-decoration-none">← Back to Home</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
 <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <img src="./assets/images/nss_logo.png" alt="NSS Logo" height="40" class="mb-2">
                    <p class="mb-0 small">National Service Scheme</p>
                </div>
                <div class="col-md-4">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Navneet College of Arts ,Science & Commerce.. All Rights Reserved.</p>
                    <p class="mb-0 small">Building responsible citizens through community service</p>
                </div>
                <div class="col-md-4">
                    <img src="./assets/images/college_logo.png" alt="College Logo" height="40" class="mb-2">
                    <p class="mb-0 small">Navneet College of Science & Arts</p>
                </div>
            </div>
        </div>
    </footer>
    <script>
        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                confirmPassword.focus();
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>