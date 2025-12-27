<?php
session_start();
if (!isset($_SESSION['volunteer'])) {
    header("Location: ../login.php");
    exit;
}
include("../db/connection.php");

$volunteer_id = $_SESSION['volunteer'];

// Get events that volunteer hasn't registered for and are in future
$events = $pdo->prepare("
    SELECT e.* 
    FROM events e 
    WHERE e.event_date >= CURDATE() 
    AND e.event_id NOT IN (
        SELECT event_id FROM event_registrations WHERE volunteer_id = ?
    )
    ORDER BY e.event_date ASC
");
$events->execute([$volunteer_id]);
$events = $events->fetchAll();

// Event type display function
function getEventTypeDisplay($type) {
    $types = [
        'blood_camp' => ['icon' => '🩸', 'name' => 'Blood Donation', 'color' => 'danger'],
        'tree_plantation' => ['icon' => '🌳', 'name' => 'Tree Plantation', 'color' => 'success'],
        'cleanliness_drive' => ['icon' => '🧹', 'name' => 'Cleanliness Drive', 'color' => 'info'],
        'awareness' => ['icon' => '📢', 'name' => 'Awareness Program', 'color' => 'warning'],
        'medical_camp' => ['icon' => '🏥', 'name' => 'Medical Camp', 'color' => 'primary'],
        'educational' => ['icon' => '📚', 'name' => 'Educational Activity', 'color' => 'secondary'],
        'cultural' => ['icon' => '🎭', 'name' => 'Cultural Event', 'color' => 'purple'],
        'sports' => ['icon' => '⚽', 'name' => 'Sports Activity', 'color' => 'success'],
        'college_event' => ['icon' => '🏫', 'name' => 'College Event', 'color' => 'dark'],
        'regular' => ['icon' => '🔄', 'name' => 'Regular Activity', 'color' => 'secondary'],
        'special_camp' => ['icon' => '🏕️', 'name' => 'Special Camp', 'color' => 'warning'],
        'other' => ['icon' => '📋', 'name' => 'Other', 'color' => 'light']
    ];
    return $types[$type] ?? ['icon' => '📋', 'name' => 'Other', 'color' => 'light'];
}

// Get registered events count for stats
$registered_count = $pdo->prepare("
    SELECT COUNT(*) FROM event_registrations WHERE volunteer_id = ?
");
$registered_count->execute([$volunteer_id]);
$registered_count = $registered_count->fetchColumn();

// Get upcoming events count
$upcoming_count = $pdo->query("
    SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()
")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Available Events - Navneet College of Arts ,Science & Commerce.</title>
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
        .hours-badge { font-size: 0.9em; }
        .event-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .event-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .bg-purple { background-color: #6f42c1 !important; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
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
                    <li class="nav-item"><a class="nav-link active" href="view_events.php">Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
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

    <h2>📅 Available Events</h2>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= count($events) ?></h3>
                    <p class="mb-0">Available Events</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $registered_count ?></h3>
                    <p class="mb-0">Registered Events</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= $upcoming_count ?></h3>
                    <p class="mb-0">Total Upcoming Events</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (count($events) > 0): ?>
        <div class="row">
            <?php foreach ($events as $e): 
                $type_info = getEventTypeDisplay($e['event_type']);
            ?>
            <div class="col-md-6 mb-4">
                <div class="card event-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="badge bg-<?= $type_info['color'] ?>">
                            <?= $type_info['icon'] ?> <?= $type_info['name'] ?>
                        </span>
                        <span class="badge bg-primary hours-badge"><?= $e['event_hours'] ?> hours</span>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($e['title']) ?></h5>
                        <p class="card-text"><?= htmlspecialchars($e['description']) ?></p>
                        <div class="event-details">
                            <p class="mb-1">
                                <i class="fas fa-calendar text-primary"></i>
                                <strong>Date:</strong> <?= date('l, F j, Y', strtotime($e['event_date'])) ?>
                            </p>
                            <p class="mb-1">
                                <i class="fas fa-map-marker-alt text-danger"></i>
                                <strong>Location:</strong> <?= htmlspecialchars($e['location']) ?>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-clock text-success"></i>
                                <strong>Hours:</strong> <span class="badge bg-success"><?= $e['event_hours'] ?> service hours</span>
                            </p>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-grid">
                            <a href="register_event.php?id=<?= $e['event_id'] ?>" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Register for Event
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">
            <div class="py-4">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h4>No Available Events</h4>
                <p class="mb-3">There are no events available for registration at the moment.</p>
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-warning">
                            <h6>Possible Reasons:</h6>
                            <ul class="text-start small">
                                <li>You've already registered for all upcoming events</li>
                                <li>No new events have been scheduled yet</li>
                                <li>Events might be in the past</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-success">
                            <h6>What to do:</h6>
                            <ul class="text-start small">
                                <li>Check your <a href="my_registrations.php" class="alert-link">registered events</a></li>
                                <li>Wait for new events to be announced</li>
                                <li>Contact NSS coordinator for more information</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <a href="my_registrations.php" class="btn btn-primary me-2">
                    <i class="fas fa-list me-1"></i>View My Registrations
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Debug Information (Remove in production) 
    <div class="mt-4">
        <details>
            <summary class="text-muted small">Debug Info</summary>
            <div class="alert alert-warning small">
                <strong>Volunteer ID:</strong> <?= $volunteer_id ?><br>
                <strong>Available Events Count:</strong> <?= count($events) ?><br>
                <strong>Query:</strong> SELECT events WHERE event_date >= CURDATE() AND event_id NOT IN registered events
            </div>
        </details>
    </div>
    -->

    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>