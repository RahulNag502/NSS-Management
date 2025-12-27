<?php
session_start();
if (!isset($_SESSION['volunteer'])) {
    header("Location: ../login.php");
    exit;
}
include("../db/connection.php");

$volunteer_id = $_SESSION['volunteer'];
$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $event_id = $_POST['event_id'];
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];

    // Check if feedback already exists
    $check = $pdo->prepare("SELECT id FROM feedback WHERE event_id = ? AND volunteer_id = ?");
    $check->execute([$event_id, $volunteer_id]);
    
    if ($check->fetch()) {
        $msg = "<div class='alert alert-warning'>You have already submitted feedback for this event.</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO feedback (event_id, volunteer_id, rating, comments) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$event_id, $volunteer_id, $rating, $comments])) {
            $msg = "<div class='alert alert-success'>Feedback submitted successfully!</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Failed to submit feedback. Please try again.</div>";
        }
    }
}

// Fetch events registered by volunteer that they haven't given feedback for
$stmt = $pdo->prepare("
    SELECT e.event_id, e.title
    FROM event_registrations r
    JOIN events e ON r.event_id = e.event_id
    WHERE r.volunteer_id = ?
    AND e.event_id NOT IN (SELECT event_id FROM feedback WHERE volunteer_id = ?)
    ORDER BY e.event_date DESC
");
$stmt->execute([$volunteer_id, $volunteer_id]);
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Submit Feedback</title>
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
        .star-rating { font-size: 1.5em; color: #ffc107; }
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

    <h2>💬 Submit Feedback</h2>

    <?= $msg ?>

    <?php if (count($events) > 0): ?>
        <form method="post" class="card p-4">
            <div class="mb-3">
                <label class="form-label">Select Event</label>
                <select name="event_id" class="form-control" required>
                    <option value="">-- Choose Event --</option>
                    <?php foreach ($events as $e): ?>
                        <option value="<?= $e['event_id'] ?>"><?= htmlspecialchars($e['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-control" required>
                    <option value="">-- Select Rating --</option>
                    <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                    <option value="4">⭐⭐⭐⭐ Very Good</option>
                    <option value="3">⭐⭐⭐ Good</option>
                    <option value="2">⭐⭐ Fair</option>
                    <option value="1">⭐ Poor</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Comments</label>
                <textarea name="comments" class="form-control" rows="4" placeholder="Share your experience..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Submit Feedback</button>
        </form>
    <?php else: ?>
        <div class="alert alert-info">No events available for feedback at the moment.</div>
    <?php endif; ?>
    
    <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</body>
</html>