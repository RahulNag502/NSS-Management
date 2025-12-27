<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");

$success = "";
$error = "";

// Get events for dropdown
$events = $pdo->query("SELECT event_id, title, event_date, event_type FROM events ORDER BY event_date DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['images'])) {
    $event_id = $_POST['event_id'];
    $uploadDir = '../assets/uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $uploaded_files = [];
    $has_errors = false;
    
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
            $fileName = time() . '_admin_' . basename($_FILES['images']['name'][$key]);
            $targetPath = $uploadDir . $fileName;
            
            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedTypes)) {
                $error = "Only JPG, JPEG, PNG, GIF, MP4, AVI, MOV files are allowed.";
                $has_errors = true;
                break;
            }
            
            if ($_FILES['images']['size'][$key] > 10 * 1024 * 1024) { // 10MB limit
                $error = "File size must be less than 10MB.";
                $has_errors = true;
                break;
            }
            
            if (move_uploaded_file($tmp_name, $targetPath)) {
                $uploaded_files[] = $fileName;
            }
        }
    }
    
    if (!$has_errors && count($uploaded_files) > 0) {
        foreach ($uploaded_files as $file) {
            $stmt = $pdo->prepare("INSERT INTO gallery (image_path, uploaded_by, event_id) VALUES (?, ?, ?)");
            $stmt->execute([$file, $_SESSION['admin'], $event_id]);
        }
        $success = count($uploaded_files) . " file(s) uploaded successfully! Linked to event.";
    } elseif (!$has_errors) {
        $error = "No files were uploaded or there was an error.";
    }
}

// Get admin's uploaded files with event info
$admin_files_stmt = $pdo->prepare("
    SELECT g.*, e.title as event_title, e.event_type, e.event_date
    FROM gallery g 
    JOIN events e ON g.event_id = e.event_id
    WHERE g.uploaded_by = ?
    ORDER BY e.event_date DESC, g.uploaded_at DESC
    LIMIT 12
");
$admin_files_stmt->execute([$_SESSION['admin']]);
$files = $admin_files_stmt->fetchAll();

// Get upload statistics
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_files,
        COUNT(DISTINCT event_id) as events_covered,
        COUNT(DISTINCT uploaded_by) as total_contributors
    FROM gallery
");
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Gallery - Navneet College of Arts ,Science & Commerce.</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; --success-color: #10b981; --danger-color: #ef4444; --warning-color: #f59e0b; --dark-bg: #1f2937; --light-bg: #f9fafb; --border-color: #e5e7eb; --text-primary: #111827; --text-secondary: #6b7280; --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15); --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); } * { transition: var(--transition); } body { padding-top: 80px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-primary); } .navbar { position: fixed !important; top: 0; width: 100%; z-index: 1030; background: linear-gradient(135deg, var(--dark-bg) 0%, #0f172a 100%); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); } .brand-container { display: flex; align-items: center; gap: 15px; } .college-logo { height: 60px; width: auto; border-radius: 8px; } .college-logo:hover { transform: scale(1.05); filter: brightness(1.1); } .navbar-brand { font-weight: 700; font-size: 1.2rem; color: white !important; } .navbar-brand:hover { color: var(--primary-color) !important; } .nav-link { color: rgba(255, 255, 255, 0.8) !important; font-weight: 500; position: relative; } .nav-link:hover, .nav-link.active { color: var(--primary-color) !important; } .nav-link::after { content: ''; position: absolute; bottom: -5px; left: 50%; width: 0; height: 2px; background: var(--primary-color); transform: translateX(-50%); transition: var(--transition); } .nav-link:hover::after, .nav-link.active::after { width: 80%; } .card { border: none; border-radius: 20px; box-shadow: var(--shadow-lg); background: white; } .card:hover { box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: translateY(-5px); } .btn { padding: 12px 24px; border-radius: 10px; font-weight: 600; border: none; text-transform: uppercase; } .btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white !important; } .btn-primary:hover { transform: translateY(-2px); color: white !important; } .btn-success { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); color: white !important; } .btn-danger { background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%); color: white !important; } .alert { border: none; border-radius: 10px; padding: 15px 20px; animation: slideIn 0.3s ease; } @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } } .alert-success { background-color: rgba(16, 185, 129, 0.1); color: #047857; border-left: 4px solid var(--success-color); } .alert-danger { background-color: rgba(239, 68, 68, 0.1); color: #991b1b; border-left: 4px solid var(--danger-color); } .table thead th { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; } .table tbody tr:hover { background-color: rgba(102, 126, 234, 0.05); } @media (max-width: 768px) { body { padding-top: 70px; } .btn { font-size: 0.85rem; padding: 10px 16px; } }
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
                    <li class="nav-item"><a class="nav-link" href="view_volunteers.php">Volunteers</a></li>
                    <li class="nav-item"><a class="nav-link" href="issue_certificates.php">Certificates</a></li>
                    <li class="nav-item"><a class="nav-link" href="manage_gallery.php">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
    <div class="nav-menu">
        <a href="dashboard.php">🏠 Home</a>
        <a href="add_event.php">➕ Add Event</a>
        <a href="view_events.php">📅 Manage Events</a>
        <a href="attendance.php">📝 Attendance</a>
        <a href="view_registrations.php">👥 Registrations</a>
        <a href="view_volunteers.php">📋 Volunteers</a>
        <a href="issue_certificates.php">🎓 Certificates</a>
        <a href="gallery_upload.php">📸 Upload Gallery</a>
        <a href="gallery_view.php">🖼️ View Gallery</a>
        <a href="manage_notifications.php">📢 Notifications</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>

    <h2>📸 Upload Gallery Files</h2>
    
    <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['total_files'] ?></h3>
                    <p class="mb-0">Total Files</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['events_covered'] ?></h3>
                    <p class="mb-0">Events Covered</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $stats['total_contributors'] ?></h3>
                    <p class="mb-0">Contributors</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Upload New Files</h5>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Select Event *</label>
                            <select name="event_id" class="form-select" required>
                                <option value="">-- Choose Event --</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?= $event['event_id'] ?>">
                                        <?= htmlspecialchars($event['title']) ?> 
                                        (<?= date('M j, Y', strtotime($event['event_date'])) ?>)
                                        - <?= 
                                            [
                                                'blood_camp' => '🩸 Blood',
                                                'tree_plantation' => '🌳 Tree',
                                                'cleanliness_drive' => '🧹 Cleanliness',
                                                'awareness' => '📢 Awareness',
                                                'medical_camp' => '🏥 Medical',
                                                'educational' => '📚 Educational',
                                                'cultural' => '🎭 Cultural',
                                                'sports' => '⚽ Sports',
                                                'college_event' => '🏫 College',
                                                'regular' => '🔄 Regular',
                                                'special_camp' => '🏕️ Special Camp',
                                                'other' => '📋 Other'
                                            ][$event['event_type']] ?? '📋 Other'
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select the event to link your files</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="upload-area">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                            <h5>Drag & Drop or Click to Upload</h5>
                            <p class="text-muted">Upload photos and videos for the gallery</p>
                            <input type="file" name="images[]" class="form-control" multiple accept="image/*,video/*" required>
                            <div class="form-text mt-2">
                                Supported formats: JPG, PNG, GIF, MP4, AVI, MOV. Max file size: 10MB each. You can select multiple files.
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Files
                </button>
            </form>
        </div>
    </div>

    <h4 class="mt-5">Recently Uploaded Files</h4>
    <?php if (count($files) > 0): ?>
        <div class="photo-grid">
            <?php foreach ($files as $file): 
                $isVideo = in_array(pathinfo($file['image_path'], PATHINFO_EXTENSION), ['mp4', 'avi', 'mov']);
            ?>
            <div class="photo-card">
                <div class="position-relative">
                    <?php if ($isVideo): ?>
                        <video style="height: 150px; width: 100%;">
                            <source src="../assets/uploads/<?= htmlspecialchars($file['image_path']) ?>" type="video/mp4">
                        </video>
                        <div class="video-icon">
                            <i class="fas fa-video"></i> Video
                        </div>
                    <?php else: ?>
                        <img src="../assets/uploads/<?= htmlspecialchars($file['image_path']) ?>" 
                             alt="Uploaded file">
                    <?php endif; ?>
                </div>
                <div class="photo-actions">
                    <a href="../assets/uploads/<?= htmlspecialchars($file['image_path']) ?>" 
                       target="_blank" 
                       class="btn btn-sm btn-info">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
                <div class="mt-2">
                    <small class="text-primary d-block fw-bold" title="<?= htmlspecialchars($file['event_title']) ?>">
                        <?= strlen($file['event_title']) > 20 ? substr($file['event_title'], 0, 20) . '...' : $file['event_title'] ?>
                    </small>
                    <span class="badge <?= 
                        [
                            'blood_camp' => 'bg-danger',
                            'tree_plantation' => 'bg-success',
                            'cleanliness_drive' => 'bg-info',
                            'awareness' => 'bg-warning',
                            'medical_camp' => 'bg-primary',
                            'educational' => 'bg-secondary',
                            'cultural' => 'bg-purple',
                            'sports' => 'bg-success',
                            'college_event' => 'bg-dark',
                            'regular' => 'bg-secondary',
                            'special_camp' => 'bg-warning',
                            'other' => 'bg-light text-dark'
                        ][$file['event_type']] ?? 'bg-light text-dark'
                    ?> event-badge">
                        <?= 
                            [
                                'blood_camp' => '🩸 Blood',
                                'tree_plantation' => '🌳 Tree',
                                'cleanliness_drive' => '🧹 Cleanliness',
                                'awareness' => '📢 Awareness',
                                'medical_camp' => '🏥 Medical',
                                'educational' => '📚 Educational',
                                'cultural' => '🎭 Cultural',
                                'sports' => '⚽ Sports',
                                'college_event' => '🏫 College',
                                'regular' => '🔄 Regular',
                                'special_camp' => '🏕️ Special Camp',
                                'other' => '📋 Other'
                            ][$file['event_type']] ?? '📋 Other'
                        ?>
                    </span>
                    <small class="text-muted d-block mt-1">
                        <?= date('M j, Y', strtotime($file['event_date'])) ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
            <a href="gallery_view.php" class="btn btn-outline-primary">
                <i class="fas fa-images me-1"></i>View All Gallery Files
            </a>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No files uploaded yet. Start by uploading files above.
        </div>
    <?php endif; ?>

    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    </div>
</body>
</html>