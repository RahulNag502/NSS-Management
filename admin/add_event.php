<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");
require_once("../includes/EmailSender.php");

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $title       = $_POST['title'];
    $description = $_POST['description'];
    $event_date  = $_POST['event_date'];
    $location    = $_POST['location'];
    $event_hours = $_POST['event_hours'];
    $event_type  = $_POST['event_type'];

    // ✅ Insert Event
    $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, location, event_hours, event_type)
                           VALUES (?, ?, ?, ?, ?, ?)");

    if ($stmt->execute([$title, $description, $event_date, $location, $event_hours, $event_type])) {

        $success = "✅ Event created successfully & notifications sent!";

        // ✅ Prepare notification message
        $notifTitle   = "New NSS Event: $title";
        $notifMessage = "A new event \"$title\" has been added on $event_date at $location.";

        // ✅ Fetch all volunteers
        $volunteers = $pdo->query("SELECT volunteer_id, email, name FROM volunteers")->fetchAll();

        $mailer = new EmailSender();

        // ✅ Save WEB notification (for volunteers)
        $notifyStmt = $pdo->prepare("INSERT INTO notifications (title, message, target) VALUES (?, ?, ?)");
        $notifyStmt->execute([$notifTitle, $notifMessage, 'volunteer']);

        // ✅ Send EMAIL notifications
        foreach ($volunteers as $v) {
            if (!empty($v['email'])) {
                $mailer->sendNewEventNotificationEmail(
                    $v['name'],
                    $v['email'],
                    $title,
                    $event_date,
                    $location
                );
            }
        }

    } else {
        $error = "❌ Error creating event!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Event</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; --success-color: #10b981; --danger-color: #ef4444; --warning-color: #f59e0b; --dark-bg: #1f2937; --light-bg: #f9fafb; --border-color: #e5e7eb; --text-primary: #111827; --text-secondary: #6b7280; --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15); --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        * { transition: var(--transition); }
        body { padding-top: 80px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-primary); }
        .navbar { position: fixed !important; top: 0; width: 100%; z-index: 1030; background: linear-gradient(135deg, var(--dark-bg) 0%, #0f172a 100%); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); }
        .brand-container { display: flex; align-items: center; gap: 15px; }
        .college-logo { height: 60px; width: auto; border-radius: 8px; transition: var(--transition); }
        .college-logo:hover { transform: scale(1.05); filter: brightness(1.1); }
        .navbar-brand { font-weight: 700; font-size: 1.2rem; color: white !important; }
        .navbar-brand:hover { color: var(--primary-color) !important; }
        .nav-link { color: rgba(255, 255, 255, 0.8) !important; font-weight: 500; position: relative; margin: 0 5px; }
        .nav-link:hover, .nav-link.active { color: var(--primary-color) !important; }
        .nav-link::after { content: ''; position: absolute; bottom: -5px; left: 50%; width: 0; height: 2px; background: var(--primary-color); transform: translateX(-50%); transition: var(--transition); }
        .nav-link:hover::after, .nav-link.active::after { width: 80%; }
        .card { border: none; border-radius: 20px; box-shadow: var(--shadow-lg); background: white; overflow: hidden; transition: var(--transition); }
        .card:hover { box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: translateY(-5px); }
        .card-body h1, .card-body h2, .card-body h3 { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 700; margin-bottom: 25px; }
        .form-label { font-weight: 600; color: var(--text-primary); margin-bottom: 8px; font-size: 0.95rem; }
        .form-control, .form-select { border: 2px solid var(--border-color); border-radius: 10px; padding: 12px 15px; font-size: 0.95rem; transition: var(--transition); background-color: var(--light-bg); }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); background-color: white; }
        .btn { padding: 12px 24px; border-radius: 10px; font-weight: 600; transition: var(--transition); border: none; position: relative; overflow: hidden; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white !important; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
        .btn-primary:hover { box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4); transform: translateY(-2px); color: white !important; }
        .btn-success { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); color: white !important; }
        .btn-success:hover { transform: translateY(-2px); }
        .btn-danger { background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%); color: white !important; }
        .btn-danger:hover { transform: translateY(-2px); }
        .alert { border: none; border-radius: 10px; padding: 15px 20px; margin-bottom: 25px; animation: slideIn 0.3s ease; border-left: 4px solid; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background-color: rgba(16, 185, 129, 0.1); color: #047857; border-left-color: var(--success-color); }
        .alert-danger { background-color: rgba(239, 68, 68, 0.1); color: #991b1b; border-left-color: var(--danger-color); }
        @media (max-width: 768px) { body { padding-top: 70px; } .btn { font-size: 0.85rem; padding: 10px 16px; } }
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
    <a href="gallery_upload.php">🖼️ Gallery</a>
    <a href="manage_notifications.php">📢 Notifications</a>
    <a href="../logout.php">🚪 Logout</a>
</div>

<h2>Add New Event</h2>

<?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
<?php if (!empty($error))   echo "<div class='alert alert-danger'>$error</div>"; ?>

<form method="post" class="card p-4">
    
    <div class="mb-3">
        <label class="form-label">Event Title *</label>
        <input type="text" name="title" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Description *</label>
        <textarea name="description" class="form-control" rows="4" required></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Event Date *</label>
        <input type="date" name="event_date" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Event Location *</label>
        <input type="text" name="location" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Event Type *</label>
        <select name="event_type" class="form-select" required>
            <option value="blood_camp">🩸 Blood Donation Camp</option>
            <option value="tree_plantation">🌳 Tree Plantation</option>
            <option value="cleanliness_drive">🧹 Cleanliness Drive</option>
            <option value="awareness">📢 Awareness Program</option>
            <option value="medical_camp">🏥 Medical Camp</option>
            <option value="educational">📚 Educational Activity</option>
            <option value="cultural">🎭 Cultural Event</option>
            <option value="sports">⚽ Sports Activity</option>
            <option value="college_event">🏫 College Event</option>
            <option value="regular">🔄 Regular Activity</option>
            <option value="special_camp">🏕️ Special Camp</option>
            <option value="other">📋 Other</option>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Event Hours *</label>
        <input type="number" name="event_hours" class="form-control" min="1" max="24" value="8" required>
        <div class="form-text">Number of hours volunteers will earn for this event</div>
    </div>

    <button type="submit" class="btn btn-primary">Add Event</button>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>

</form>

</body>
</html>
