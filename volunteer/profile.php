<?php
session_start();
if (!isset($_SESSION['volunteer'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");

$volunteer_id = $_SESSION['volunteer'];
$msg = "";

// Get current volunteer data
$stmt = $pdo->prepare("SELECT * FROM volunteers WHERE volunteer_id = ?");
$stmt->execute([$volunteer_id]);
$volunteer = $stmt->fetch();

if (!$volunteer) {
    header("Location: ../login.php");
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $year = $_POST['year'];
    $current_password = $_POST['current_password'] ?? '';
    
    // Check if email is already taken by another volunteer
    $check_email = $pdo->prepare("SELECT id FROM volunteers WHERE email = ? AND volunteer_id != ?");
    $check_email->execute([$email, $volunteer_id]);
    
    if ($check_email->fetch()) {
        $msg = "<div class='alert alert-danger'>Email already taken by another volunteer.</div>";
    } else {
        $profile_image = $volunteer['profile_image'];
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/profile_images/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            $fileExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($fileExtension, $allowedTypes)) {
                if ($_FILES['profile_image']['size'] <= 2 * 1024 * 1024) { // 2MB
                    // Delete old profile image if exists
                    if ($profile_image && file_exists($uploadDir . $profile_image)) {
                        unlink($uploadDir . $profile_image);
                    }
                    
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
        
        // Handle password change if provided
        if (!empty($_POST['new_password'])) {
            // Verify current password first
            if (empty($current_password)) {
                $msg = "<div class='alert alert-danger'>Please enter your current password to change password.</div>";
            } elseif (md5($current_password) !== $volunteer['password']) {
                $msg = "<div class='alert alert-danger'>Current password is incorrect.</div>";
            } elseif ($_POST['new_password'] !== $_POST['confirm_new_password']) {
                $msg = "<div class='alert alert-danger'>New passwords do not match.</div>";
            } else {
                $new_password = md5($_POST['new_password']);
                $update_stmt = $pdo->prepare("UPDATE volunteers SET name = ?, email = ?, phone = ?, department = ?, year = ?, profile_image = ?, password = ? WHERE volunteer_id = ?");
                if ($update_stmt->execute([$name, $email, $phone, $department, $year, $profile_image, $new_password, $volunteer_id])) {
                    $msg = "<div class='alert alert-success'>Profile and password updated successfully!</div>";
                    // Refresh volunteer data
                    $stmt = $pdo->prepare("SELECT * FROM volunteers WHERE volunteer_id = ?");
                    $stmt->execute([$volunteer_id]);
                    $volunteer = $stmt->fetch();
                }
            }
        } else {
            // Update without changing password (only require current password if changing sensitive info)
            if ($email !== $volunteer['email'] && empty($current_password)) {
                $msg = "<div class='alert alert-danger'>Please enter your current password to change email address.</div>";
            } else {
                // Verify current password if provided or if email is being changed
                if (!empty($current_password) || $email !== $volunteer['email']) {
                    if (empty($current_password) || md5($current_password) !== $volunteer['password']) {
                        $msg = "<div class='alert alert-danger'>Current password is incorrect.</div>";
                    } else {
                        // Update with password verification
                        $update_stmt = $pdo->prepare("UPDATE volunteers SET name = ?, email = ?, phone = ?, department = ?, year = ?, profile_image = ? WHERE volunteer_id = ?");
                        if ($update_stmt->execute([$name, $email, $phone, $department, $year, $profile_image, $volunteer_id])) {
                            $msg = "<div class='alert alert-success'>Profile updated successfully!</div>";
                            // Refresh volunteer data
                            $stmt = $pdo->prepare("SELECT * FROM volunteers WHERE volunteer_id = ?");
                            $stmt->execute([$volunteer_id]);
                            $volunteer = $stmt->fetch();
                        }
                    }
                } else {
                    // Update without password verification (only non-sensitive fields changed)
                    $update_stmt = $pdo->prepare("UPDATE volunteers SET name = ?, phone = ?, department = ?, year = ?, profile_image = ? WHERE volunteer_id = ?");
                    if ($update_stmt->execute([$name, $phone, $department, $year, $profile_image, $volunteer_id])) {
                        $msg = "<div class='alert alert-success'>Profile updated successfully!</div>";
                        // Refresh volunteer data
                        $stmt = $pdo->prepare("SELECT * FROM volunteers WHERE volunteer_id = ?");
                        $stmt->execute([$volunteer_id]);
                        $volunteer = $stmt->fetch();
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile - Navneet College of Arts ,Science & Commerce.</title>
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
        .nav-menu { background: #343a40; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .nav-menu a { color: white; text-decoration: none; margin-right: 20px; padding: 8px 15px; border-radius: 4px; }
        .nav-menu a:hover { background: #495057; color: #ffc107; }
        .profile-img { width: 150px; height: 150px; object-fit: cover; border: 4px solid #007bff; }
        .profile-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .password-section { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-top: 20px; }
        .required-field::after { content: " *"; color: red; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <div class="brand-container">
                <a class="navbar-brand" href="../index.php">
                    <img src="../assets/images/nss_logo.png" alt="NSS Logo" height="50" class="me-2">
                    Navneet College of Arts ,Science & Commerce.
                </a>
                <img src="../assets/images/college_logo.png" alt="College Logo" class="college-logo">
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto" style="font-size: 1rem;">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="view_events.php">Events</a></li>
                    <li class="nav-item"><a class="nav-link active" href="profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="my_certificates.php">Certificates</a></li>
                    <li class="nav-item"><a class="nav-link" href="notifications.php">Notifications</a></li>
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
    <div class="nav-menu">
        <a href="dashboard.php">🏠 Home</a>
        <a href="view_events.php">📅 Available Events</a>
        <a href="my_registrations.php">📝 My Registrations</a>
        <a href="my_attendance.php">✅ My Attendance</a>
        <a href="my_certificates.php">🎓 My Certificates</a>
        <a href="view_gallery.php">🖼️ Gallery</a>
        <a href="upload_photos.php">📸 Upload Photos</a>
        <a href="feedback.php">💬 Feedback</a>
        <a href="notifications.php">📢 Notifications</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>

    <h2>👤 My Profile</h2>
    
    <?= $msg ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <img src="../assets/profile_images/<?= $volunteer['profile_image'] ?: 'default_profile.jpg' ?>" 
                         class="profile-img rounded-circle mb-3"
                         alt="Profile Picture"
                         onerror="this.src='../assets/images/default_profile.jpg'">
                    <h4><?= htmlspecialchars($volunteer['name']) ?></h4>
                    <p class="text-muted"><?= htmlspecialchars($volunteer['volunteer_id']) ?></p>
                    <div class="mt-3">
                        <span class="badge bg-primary"><?= $volunteer['department'] ?></span>
                        <span class="badge bg-info"><?= $volunteer['year'] ?></span>
                    </div>
                    <div class="mt-3">
                        <span class="badge bg-success"><?= $volunteer['total_hours'] ?> Hours</span>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            Member since <?= date('M Y', strtotime($volunteer['registered_at'])) ?>
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body">
                    <h6>Security Tips</h6>
                    <ul class="small text-muted">
                        <li>Always verify your current password before making changes</li>
                        <li>Use a strong, unique password</li>
                        <li>Never share your password with anyone</li>
                        <li>Log out after each session</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required-field">Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($volunteer['name']) ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label required-field">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($volunteer['email']) ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($volunteer['phone']) ?>" pattern="[0-9]{10}">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label required-field">Department</label>
                                <select name="department" class="form-select" required>
                                    <option value="Computer Science" <?= $volunteer['department'] == 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                    <option value="Electronics" <?= $volunteer['department'] == 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                    <option value="Mechanical" <?= $volunteer['department'] == 'Mechanical' ? 'selected' : '' ?>>Mechanical</option>
                                    <option value="Civil" <?= $volunteer['department'] == 'Civil' ? 'selected' : '' ?>>Civil</option>
                                    <option value="Electrical" <?= $volunteer['department'] == 'Electrical' ? 'selected' : '' ?>>Electrical</option>
                                    <option value="Science" <?= $volunteer['department'] == 'Science' ? 'selected' : '' ?>>Science</option>
                                    <option value="Arts" <?= $volunteer['department'] == 'Arts' ? 'selected' : '' ?>>Arts</option>
                                    <option value="Commerce" <?= $volunteer['department'] == 'Commerce' ? 'selected' : '' ?>>Commerce</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label required-field">Year</label>
                                <select name="year" class="form-select" required>
                                    <option value="FY" <?= $volunteer['year'] == 'FY' ? 'selected' : '' ?>>First Year (FY)</option>
                                    <option value="SY" <?= $volunteer['year'] == 'SY' ? 'selected' : '' ?>>Second Year (SY)</option>
                                    <option value="TY" <?= $volunteer['year'] == 'TY' ? 'selected' : '' ?>>Third Year (TY)</option>
                                    <option value="Final" <?= $volunteer['year'] == 'Final' ? 'selected' : '' ?>>Final Year</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Update Profile Picture</label>
                                <input type="file" name="profile_image" class="form-control" accept="image/*">
                                <div class="form-text">Leave empty to keep current image. JPG, PNG, GIF, Max: 2MB</div>
                            </div>
                            
                            <!-- Current Password Field -->
                            <div class="col-12">
                                <div class="password-section">
                                    <h6>Password Verification</h6>
                                    <p class="text-muted small mb-3">
                                        <i class="fas fa-info-circle"></i>
                                        Enter your current password to verify your identity when changing sensitive information.
                                    </p>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Current Password</label>
                                            <input type="password" name="current_password" class="form-control" placeholder="Enter current password">
                                            <div class="form-text">Required for email changes and password updates</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- New Password Fields -->
                            <div class="col-12">
                                <div class="password-section">
                                    <h6>Change Password (Optional)</h6>
                                    <p class="text-muted small mb-3">
                                        Leave these fields empty if you don't want to change your password.
                                    </p>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">New Password</label>
                                            <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Enter new password">
                                            <div class="form-text">Minimum 6 characters</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" name="confirm_new_password" class="form-control" placeholder="Confirm new password">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Security Information -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6>When is Current Password Required?</h6>
                    <ul class="small">
                        <li><strong>Always required:</strong> When changing your password</li>
                        <li><strong>Required:</strong> When changing your email address</li>
                        <li><strong>Not required:</strong> When updating name, phone, department, year, or profile picture only</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <script>
        // Client-side validation for password changes
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_new_password"]').value;
            const currentPassword = document.querySelector('input[name="current_password"]').value;
            const emailField = document.querySelector('input[name="email"]');
            const currentEmail = '<?= $volunteer['email'] ?>';
            
            // If changing password, both new password fields must be filled and match
            if (newPassword && newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return;
            }
            
            // If changing email or password, current password is required
            if ((emailField.value !== currentEmail || newPassword) && !currentPassword) {
                e.preventDefault();
                alert('Please enter your current password to verify these changes.');
                return;
            }
        });
        
        // Show/hide password requirement messages dynamically
        const emailField = document.querySelector('input[name="email"]');
        const currentPasswordField = document.querySelector('input[name="current_password"]');
        const currentEmail = '<?= $volunteer['email'] ?>';
        
        emailField.addEventListener('change', function() {
            if (this.value !== currentEmail && !currentPasswordField.value) {
                showPasswordRequirement();
            }
        });
        
        function showPasswordRequirement() {
            // You can add a dynamic message here if needed
            console.log('Current password will be required for email change');
        }
    </script>
</body>
</html>