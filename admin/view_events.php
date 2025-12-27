<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");
$events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC")->fetchAll();

// Event type display function
function getEventTypeDisplay($type) {
    $types = [
        'blood_camp' => '🩸 Blood',
        'tree_plantation' => '🌳 Tree Plantation',
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
    ];
    return $types[$type] ?? '📋 Other';
}

function getEventTypeBadgeColor($type) {
    $colors = [
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
    ];
    return $colors[$type] ?? 'bg-light text-dark';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Events</title>
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
        .table thead th { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; border: none; font-weight: 600; padding: 15px; text-transform: uppercase; font-size: 0.85rem; }
        .table tbody tr:hover { background-color: rgba(102, 126, 234, 0.05); }
        .badge-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); }
        .bg-purple { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important; }
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
                    <li class="nav-item"><a class="nav-link active" href="view_events.php">Events</a></li>
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

    <h2>Manage Events</h2>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Event ID</th>
                <th>Title</th>
                <th>Type</th>
                <th>Description</th>
                <th>Date</th>
                <th>Location</th>
                <th>Hours</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $e): ?>
            <tr>
                <td><?= $e['event_id']; ?></td>
                <td><?= htmlspecialchars($e['title']); ?></td>
                <td>
                    <span class="badge <?= getEventTypeBadgeColor($e['event_type']) ?>">
                        <?= getEventTypeDisplay($e['event_type']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($e['description']); ?></td>
                <td><?= $e['event_date']; ?></td>
                <td><?= htmlspecialchars($e['location']); ?></td>
                <td class="text-center">
                    <span class="badge bg-primary"><?= $e['event_hours']; ?> hrs</span>
                </td>
                <td>
                    <a href="edit_event.php?id=<?= $e['event_id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a href="delete_event.php?id=<?= $e['event_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this event?')">Delete</a>
                    <a href="attendance.php?event_id=<?= $e['event_id']; ?>" class="btn btn-sm btn-info">Attendance</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</body>
</html>