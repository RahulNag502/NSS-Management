<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");

// Handle feature/unfeature actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'feature') {
        $stmt = $pdo->prepare("UPDATE gallery SET featured = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Image featured successfully!";
    } elseif ($action == 'unfeature') {
        $stmt = $pdo->prepare("UPDATE gallery SET featured = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Image removed from featured!";
    }
}

// Get all gallery images
$gallery_stmt = $pdo->prepare("
    SELECT g.*, 
           CASE 
               WHEN g.user_type = 'volunteer' THEN v.name 
               ELSE g.uploaded_by 
           END as display_name
    FROM gallery g
    LEFT JOIN volunteers v ON g.uploaded_by = v.volunteer_id AND g.user_type = 'volunteer'
    ORDER BY g.featured DESC, g.uploaded_at DESC
");
$gallery_stmt->execute();
$gallery_images = $gallery_stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Gallery</title>
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
        <a href="gallery_upload.php">🖼️ Upload Photos</a>
        <a href="manage_gallery.php">⭐ Manage Gallery</a>
        <a href="manage_notifications.php">📢 Notifications</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>

    <h2>⭐ Manage Gallery Features</h2>
    
    <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
    
    <div class="alert alert-info">
        <strong>Featured Images</strong> will appear on the homepage gallery. Select the best photos to showcase your NSS activities.
    </div>

    <div class="row">
        <?php foreach ($gallery_images as $image): 
            $image_path = $image['user_type'] == 'volunteer' ? 
                "../assets/volunteer_uploads/" . $image['image_path'] : 
                "../assets/uploads/" . $image['image_path'];
        ?>
        <div class="col-md-4 mb-4">
            <div class="card">
                <img src="<?= $image_path ?>" class="gallery-img card-img-top" alt="Gallery image">
                <div class="card-body">
                    <h6 class="card-title">
                        <?php if ($image['featured']): ?>
                            <span class="badge featured-badge">⭐ Featured</span>
                        <?php endif; ?>
                    </h6>
                    <p class="card-text">
                        <small>
                            <strong>By:</strong> <?= htmlspecialchars($image['display_name'] ?? 'Unknown') ?><br>
                            <strong>Type:</strong> <?= $image['user_type'] == 'volunteer' ? 'Volunteer' : 'Official' ?><br>
                            <strong>Date:</strong> <?= date('M j, Y', strtotime($image['uploaded_at'])) ?>
                        </small>
                    </p>
                    <div class="d-grid gap-2">
                        <?php if ($image['featured']): ?>
                            <a href="?action=unfeature&id=<?= $image['id'] ?>" class="btn btn-warning btn-sm">
                                ❌ Remove from Featured
                            </a>
                        <?php else: ?>
                            <a href="?action=feature&id=<?= $image['id'] ?>" class="btn btn-success btn-sm">
                                ⭐ Feature on Homepage
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</body>
</html>