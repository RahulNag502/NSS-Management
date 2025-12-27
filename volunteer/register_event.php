<?php
session_start();
if (!isset($_SESSION['volunteer'])) {
    header("Location: ../login.php");
    exit;
}
include("../db/connection.php");

$volunteer_id = $_SESSION['volunteer'];
$message = "";

// Get volunteer details first
$user_stmt = $pdo->prepare("SELECT name, email FROM volunteers WHERE volunteer_id = ?");
$user_stmt->execute([$volunteer_id]);
$user = $user_stmt->fetch();

if (isset($_GET['id'])) {
    $event_id = $_GET['id'];
    
    // Get event details
    $event_stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
    $event_stmt->execute([$event_id]);
    $event = $event_stmt->fetch();
    
    if (!$event) {
        $message = "<div class='alert alert-danger'>Event not found.</div>";
    } else {
        // Check if already registered
        $check = $pdo->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND volunteer_id = ?");
        $check->execute([$event_id, $volunteer_id]);
        
        if ($check->fetch()) {
            $message = "<div class='alert alert-warning'>You are already registered for this event.</div>";
        } else {
            // Register for event
            $stmt = $pdo->prepare("INSERT INTO event_registrations (event_id, volunteer_id) VALUES (?, ?)");
            if ($stmt->execute([$event_id, $volunteer_id])) {
                
                // Send registration confirmation email
                require_once "../includes/EmailSender.php";
                $emailSender = new EmailSender();
                $emailResult = $emailSender->sendEventRegistrationEmail(
                    $user['name'], 
                    $user['email'], 
                    $event['title'], 
                    $event['event_date'], 
                    $event['location']
                );
                
                $message = "<div class='alert alert-success'>Successfully registered for the event!" . 
                           ($emailResult['success'] ? " Confirmation email sent." : "") . "</div>";
                           
            } else {
                $message = "<div class='alert alert-danger'>Registration failed. Please try again.</div>";
            }
        }
    }
} else {
    header("Location: view_events.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register for Event</title>
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
                    <li class="nav-item"><a class="nav-link" href="my_certificates.php">Certificates</a></li>
                    <li class="nav-item"><a class="nav-link" href="notifications.php">Notifications</a></li>
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
    <!-- Navigation Menu -->
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

    <h2>Register for Event</h2>
    
    <?= $message ?>
    
    <?php if (isset($event) && $event): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="card-title mb-0"><?= htmlspecialchars($event['title']) ?></h4>
            </div>
            <div class="card-body">
                <p class="card-text"><strong>Description:</strong> <?= htmlspecialchars($event['description']) ?></p>
                <p class="card-text"><strong>Date:</strong> <?= date('F j, Y', strtotime($event['event_date'])) ?></p>
                <p class="card-text"><strong>Location:</strong> <?= htmlspecialchars($event['location']) ?></p>
                <p class="card-text"><strong>Event Hours:</strong> <span class="badge bg-success"><?= $event['event_hours'] ?> hours</span></p>
                
                <?php if (!strpos($message, 'already registered') && !strpos($message, 'Successfully')): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        By registering, you'll earn <strong><?= $event['event_hours'] ?> service hours</strong> upon attendance.
                    </div>
                    <a href="?id=<?= $event['event_id'] ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus me-2"></i>Confirm Registration
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="mt-3">
        <a href="view_events.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Events
        </a>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-home me-2"></i>Back to Dashboard
        </a>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>