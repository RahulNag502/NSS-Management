<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");
require_once "../includes/EmailSender.php"; // Include your EmailSender class
$emailSender = new EmailSender();

$success = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $target = $_POST['target'];

    // Insert notification into database
    $stmt = $pdo->prepare("INSERT INTO notifications (title, message, target) VALUES (?, ?, ?)");
    $stmt->execute([$title, $message, $target]);
    $success = "Notification added successfully!";

    // Fetch recipients based on target
    if ($target === 'all') {
        $recipients = $pdo->query("SELECT name, email FROM volunteers WHERE email_notifications = 1
                                   UNION SELECT username AS name, username AS email FROM admins")->fetchAll();
    } elseif ($target === 'volunteer') {
        $recipients = $pdo->query("SELECT name, email FROM volunteers WHERE email_notifications = 1")->fetchAll();
    } elseif ($target === 'admin') {
        $recipients = $pdo->query("SELECT username AS name, username AS email FROM admins")->fetchAll();
    } else {
        $recipients = [];
    }

    // Send email to each recipient
    foreach ($recipients as $r) {
        $emailSender->sendEmail(
            $r['email'],
            "📢 New NSS Notification: $title",
            "<h3>Hello {$r['name']},</h3><p>$message</p>"
        );
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Notification deleted successfully!";
}

// Fetch all notifications
$notifications = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Notifications</title>
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
        <a href="gallery_upload.php">🖼️ Gallery</a>
        <a href="manage_notifications.php">📢 Notifications</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>

    <h2>📢 Manage Notifications</h2>
    
    <?php if (!empty($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
    
    <form method="POST" class="card p-4 mb-4">
        <h4>Add New Notification</h4>
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea name="message" class="form-control" rows="3" required></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Target Audience</label>
            <select name="target" class="form-select" required>
                <option value="all">All Users</option>
                <option value="volunteer">Volunteers Only</option>
                <option value="admin">Admins Only</option>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Add Notification</button>
    </form>

    <h3>Existing Notifications</h3>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Title</th>
                <th>Message</th>
                <th>Target</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notifications as $n): ?>
            <tr>
                <td><?= htmlspecialchars($n['title']) ?></td>
                <td><?= htmlspecialchars($n['message']) ?></td>
                <td><span class="badge bg-secondary"><?= ucfirst($n['target']) ?></span></td>
                <td><?= $n['created_at'] ?></td>
                <td>
                    <a href="?delete=<?= $n['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this notification?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</body>
</html>