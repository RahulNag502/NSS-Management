<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");

// Search
$search = $_GET['search'] ?? '';
$where = "";
$params = [];

if (!empty($search)) {
    $where = "WHERE name LIKE ? OR volunteer_id LIKE ? OR email LIKE ? OR department LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Fetch data
$stmt = $pdo->prepare("SELECT * FROM volunteers $where ORDER BY registered_at DESC");
$stmt->execute($params);
$volunteers = $stmt->fetchAll();

// ---------- EXPORT FEATURE ----------
if (isset($_GET['export'])) {
    $format = $_GET['export'];

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=volunteers.csv');
        $out = fopen("php://output", "w");
        fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Department', 'Year', 'Registered At']);
        foreach ($volunteers as $v) {
            fputcsv($out, [
                $v['volunteer_id'],
                $v['name'],
                $v['email'],
                $v['phone'],
                $v['department'],
                $v['year'],
                $v['registered_at']
            ]);
        }
        fclose($out);
        exit;
    }

    if ($format === 'excel') {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=volunteers.xls");
        echo "ID\tName\tEmail\tPhone\tDepartment\tYear\tRegistered At\n";
        foreach ($volunteers as $v) {
            echo "{$v['volunteer_id']}\t{$v['name']}\t{$v['email']}\t{$v['phone']}\t{$v['department']}\t{$v['year']}\t{$v['registered_at']}\n";
        }
        exit;
    }

    if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=volunteers.json');
        echo json_encode($volunteers);
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Volunteers List</title>
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
        .btn { padding: 12px 24px; border-radius: 10px; font-weight: 600; transition: var(--transition); border: none; position: relative; overflow: hidden; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white !important; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); }
        .btn-primary:hover { box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4); transform: translateY(-2px); color: white !important; }
        .btn-success { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); color: white !important; }
        .btn-success:hover { transform: translateY(-2px); }
        .btn-danger { background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%); color: white !important; }
        .btn-danger:hover { transform: translateY(-2px); }
        .table thead th { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; border: none; font-weight: 600; padding: 15px; }
        .table tbody tr:hover { background-color: rgba(102, 126, 234, 0.05); }
        .alert { border: none; border-radius: 10px; padding: 15px 20px; animation: slideIn 0.3s ease; border-left: 4px solid; }
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
    <a href="view_events.php">📅 Events</a>
    <a href="attendance.php">📝 Attendance</a>
    <a href="view_registrations.php">👥 Registrations</a>
    <a href="view_volunteers.php">📋 Volunteers</a>
    <a href="issue_certificates.php">🎓 Certificates</a>
    <a href="gallery_upload.php">🖼️ Gallery</a>
    <a href="manage_notifications.php">📢 Notifications</a>
    <a href="../logout.php">🚪 Logout</a>
</div>

<h2>📋 Volunteers List</h2>

<!-- Search -->
<form method="get" class="d-flex mb-3">
    <input type="text" name="search" class="form-control me-2" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if (!empty($search)): ?>
        <a href="view_volunteers.php" class="btn btn-secondary ms-2">Clear</a>
    <?php endif; ?>
</form>

<!-- Export Buttons -->
<div class="mb-3">
    <a href="?export=csv&search=<?= urlencode($search) ?>" class="btn btn-success btn-sm">Download CSV</a>
    <a href="?export=excel&search=<?= urlencode($search) ?>" class="btn btn-warning btn-sm">Download Excel</a>
    <a href="?export=json&search=<?= urlencode($search) ?>" class="btn btn-dark btn-sm">Download JSON</a>
</div>

<!-- Table -->
<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Volunteer ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Department</th>
                <th>Year</th>
                <th>Registered At</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($volunteers): ?>
            <?php foreach ($volunteers as $v): ?>
            <tr>
                <td><?= htmlspecialchars($v['volunteer_id']) ?></td>
                <td><?= htmlspecialchars($v['name']) ?></td>
                <td><?= htmlspecialchars($v['email']) ?></td>
                <td><?= htmlspecialchars($v['phone']) ?></td>
                <td><?= htmlspecialchars($v['department']) ?></td>
                <td><?= htmlspecialchars($v['year']) ?></td>
                <td><?= date('d M Y, h:i A', strtotime($v['registered_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" class="text-center">No volunteers found</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>

    </div>
    </div>
</body>
</html>
