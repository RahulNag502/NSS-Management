<?php
session_start();
if (!isset($_SESSION['volunteer'])) {
    header("Location: ../login.php");
    exit;
}
include("../db/connection.php");

$volunteer_id = $_SESSION['volunteer'];

// Simple query that works with any certificate table structure
$stmt = $pdo->prepare("
    SELECT certificate_code, issued_date 
    FROM certificates 
    WHERE volunteer_id = ?
    ORDER BY issued_date DESC
");
$stmt->execute([$volunteer_id]);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total hours
$hours_stmt = $pdo->prepare("SELECT total_hours FROM volunteers WHERE volunteer_id = ?");
$hours_stmt->execute([$volunteer_id]);
$total_hours = $hours_stmt->fetchColumn();

// Determine certificate types based on certificate codes
$certificates_with_types = [];
foreach ($certificates as $cert) {
    $code = $cert['certificate_code'];
    if (strpos($code, 'CERT-240-') === 0) {
        $cert['certificate_type'] = '240_hours';
        $cert['type_text'] = '240 Hours';
        $cert['badge_color'] = 'bg-success';
    } elseif (strpos($code, 'CERT-120-') === 0) {
        $cert['certificate_type'] = '120_hours';
        $cert['type_text'] = '120 Hours';
        $cert['badge_color'] = 'bg-warning';
    } else {
        $cert['certificate_type'] = 'manual';
        $cert['type_text'] = 'Participation';
        $cert['badge_color'] = 'bg-info';
    }
    $certificates_with_types[] = $cert;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Certificates</title>
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
        .certificate-card { border-left: 4px solid #28a745; }
        .hours-badge { font-size: 1.1em; }
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
                    <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link active" href="my_certificates.php">Certificates</a></li>
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
        <a href="feedback.php">💬 Feedback</a>
        <a href="notifications.php">📢 Notifications</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>

    <h2>🏅 My Certificates</h2>
    
    <!-- Hours Summary -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Service Hours Summary</h5>
            <p class="card-text">
                Total Hours Completed: 
                <span class="badge bg-primary hours-badge"><?= $total_hours ?> hours</span>
            </p>
            <?php if ($total_hours < 120): ?>
                <p class="text-warning">
                    <i class="fas fa-info-circle"></i>
                    You need <?= 120 - $total_hours ?> more hours to qualify for the 120-hour certificate.
                </p>
            <?php elseif ($total_hours < 240): ?>
                <p class="text-info">
                    <i class="fas fa-info-circle"></i>
                    You need <?= 240 - $total_hours ?> more hours to qualify for the 240-hour certificate.
                </p>
            <?php else: ?>
                <p class="text-success">
                    <i class="fas fa-check-circle"></i>
                    Congratulations! You have completed all certificate requirements.
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (count($certificates_with_types) > 0): ?>
        <div class="row">
            <?php foreach ($certificates_with_types as $c): ?>
            <div class="col-md-6 mb-3">
                <div class="card certificate-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title">NSS Certificate</h5>
                            <span class="badge <?= $c['badge_color'] ?>"><?= $c['type_text'] ?></span>
                        </div>
                        <p class="card-text">
                            <strong>Certificate Code:</strong><br>
                            <code class="fs-5"><?= htmlspecialchars($c['certificate_code']) ?></code>
                        </p>
                        <p class="card-text">
                            <strong>Issued On:</strong><br>
                            <?= date('F j, Y', strtotime($c['issued_date'])) ?>
                        </p>
                        <p class="card-text">
                            <small class="text-muted">
                                This certificate recognizes your dedication and service to the community through the National Service Scheme.
                            </small>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <h5>No Certificates Yet</h5>
            <p class="mb-2">You haven't earned any certificates yet. Certificates are automatically issued when you complete:</p>
            <ul>
                <li><strong>120 hours</strong> of community service for the Basic Certificate</li>
                <li><strong>240 hours</strong> of community service for the Advanced Certificate</li>
            </ul>
            <p class="mb-0">Keep participating in events to earn your certificates!</p>
        </div>
    <?php endif; ?>
    
    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</body>
</html>