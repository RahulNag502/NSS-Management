<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");

// Get counts for dashboard
$volunteers_count = $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn();
$events_count = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$registrations_count = $pdo->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn();
$certificates_count = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();

// Get statistics for charts
$monthly_registrations_stmt = $pdo->query("
    SELECT DATE_FORMAT(registered_at, '%Y-%m') as month, COUNT(*) as count 
    FROM volunteers 
    WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(registered_at, '%Y-%m') 
    ORDER BY month
");
$monthly_registrations = $monthly_registrations_stmt->fetchAll();

$event_participation_stmt = $pdo->query("
    SELECT e.title, COUNT(r.id) as participants
    FROM events e 
    LEFT JOIN event_registrations r ON e.event_id = r.event_id
    WHERE e.event_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY e.event_id 
    ORDER BY participants DESC 
    LIMIT 10
");
$event_participation = $event_participation_stmt->fetchAll();

$department_stats_stmt = $pdo->query("
    SELECT department, COUNT(*) as count 
    FROM volunteers 
    GROUP BY department 
    ORDER BY count DESC
");
$department_stats = $department_stats_stmt->fetchAll();

$certificate_stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_certs,
        SUM(CASE WHEN certificate_code LIKE 'CERT-120-%' OR certificate_type = '120_hours' THEN 1 ELSE 0 END) as cert_120,
        SUM(CASE WHEN certificate_code LIKE 'CERT-240-%' OR certificate_type = '240_hours' THEN 1 ELSE 0 END) as cert_240
    FROM certificates
");
$certificate_stats = $certificate_stats_stmt->fetch();

$volunteers_stmt = $pdo->query("SELECT * FROM volunteers ORDER BY registered_at DESC LIMIT 5");
$volunteers = $volunteers_stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-bg: #1f2937;
            --light-bg: #f9fafb;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { transition: var(--transition); }

        body {
            padding-top: 80px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
        }

        .navbar {
            position: fixed !important;
            top: 0;
            width: 100%;
            z-index: 1030;
            background: linear-gradient(135deg, var(--dark-bg) 0%, #0f172a 100%);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .brand-container { display: flex; align-items: center; gap: 15px; }
        .college-logo { height: 60px; width: auto; border-radius: 8px; transition: var(--transition); }
        .college-logo:hover { transform: scale(1.05); filter: brightness(1.1); }
        .navbar-brand { font-weight: 700; font-size: 1.2rem; color: white !important; }
        .navbar-brand:hover { color: var(--primary-color) !important; }
        .nav-link { color: rgba(255, 255, 255, 0.8) !important; font-weight: 500; position: relative; margin: 0 5px; }
        .nav-link:hover, .nav-link.active { color: var(--primary-color) !important; }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary-color);
            transform: translateX(-50%);
            transition: var(--transition);
        }
        .nav-link:hover::after, .nav-link.active::after { width: 80%; }

        .card { border: none; border-radius: 20px; box-shadow: var(--shadow-lg); background: white; overflow: hidden; transition: var(--transition); }
        .card:hover { box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: translateY(-5px); }
        .card-body h1, .card-body h2, .card-body h3 {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            margin-bottom: 25px;
        }

        .form-label { font-weight: 600; color: var(--text-primary); margin-bottom: 8px; font-size: 0.95rem; }
        .form-control, .form-select { border: 2px solid var(--border-color); border-radius: 10px; padding: 12px 15px; font-size: 0.95rem; transition: var(--transition); background-color: var(--light-bg); }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); background-color: white; }
        .form-control::placeholder { color: var(--text-secondary); }

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

        .badge { padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: 0.8rem; }
        .badge-success { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); }
        .badge-danger { background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%); }
        .badge-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); }

        .dashboard-stats { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 200px; padding: 20px; background: white; border-radius: 15px; text-align: center; border: none; box-shadow: var(--shadow-lg); transition: var(--transition); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); }
        .stat-number { font-size: 2.5em; font-weight: bold; background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .chart-container { background: white; padding: 20px; border-radius: 15px; box-shadow: var(--shadow-lg); margin-bottom: 30px; border: none; }
        .chart-title { font-size: 1.3em; font-weight: bold; margin-bottom: 15px; color: var(--text-primary); }

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
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
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

    <h2>Admin Dashboard 👨‍💼</h2>
    <p>Welcome, <?= htmlspecialchars($_SESSION['admin']); ?>!</p>

    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-number"><?= $volunteers_count ?></div>
            <div>Total Volunteers</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $events_count ?></div>
            <div>Total Events</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $registrations_count ?></div>
            <div>Registrations</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $certificates_count ?></div>
            <div>Certificates Issued</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <div class="chart-title">📈 Volunteer Registrations (Last 6 Months)</div>
                <canvas id="registrationsChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <div class="chart-title">🎓 Certificates Distribution</div>
                <canvas id="certificatesChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <div class="chart-title">👥 Volunteers by Department</div>
                <canvas id="departmentChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <div class="chart-title">📊 Top Events by Participation</div>
                <canvas id="eventsChart"></canvas>
            </div>
        </div>
    </div>

    <h3 class="mt-5">Recent Volunteers</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Volunteer ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Department</th>
                <th>Year</th>
                <th>Registered At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($volunteers as $v): ?>
            <tr>
                <td><?= htmlspecialchars($v['volunteer_id']); ?></td>
                <td><?= htmlspecialchars($v['name']); ?></td>
                <td><?= htmlspecialchars($v['email']); ?></td>
                <td><?= htmlspecialchars($v['department']); ?></td>
                <td><?= htmlspecialchars($v['year']); ?></td>
                <td><?= $v['registered_at']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        // Volunteer Registrations Chart
        const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
        new Chart(registrationsCtx, {
            type: 'line',
            data: {
                labels: [<?php foreach ($monthly_registrations as $reg) echo "'" . date('M Y', strtotime($reg['month'] . '-01')) . "',"; ?>],
                datasets: [{
                    label: 'New Volunteers',
                    data: [<?php foreach ($monthly_registrations as $reg) echo $reg['count'] . ','; ?>],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Certificates Distribution Chart
        const certificatesCtx = document.getElementById('certificatesChart').getContext('2d');
        new Chart(certificatesCtx, {
            type: 'doughnut',
            data: {
                labels: ['120-Hour Certificates', '240-Hour Certificates', 'Other Certificates'],
                datasets: [{
                    data: [
                        <?= $certificate_stats['cert_120'] ?? 0 ?>,
                        <?= $certificate_stats['cert_240'] ?? 0 ?>,
                        <?= ($certificate_stats['total_certs'] ?? 0) - ($certificate_stats['cert_120'] ?? 0) - ($certificate_stats['cert_240'] ?? 0) ?>
                    ],
                    backgroundColor: ['#ffc107', '#28a745', '#17a2b8']
                }]
            }
        });

        // Department Distribution Chart
        const departmentCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(departmentCtx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($department_stats as $dept) echo "'" . addslashes($dept['department']) . "',"; ?>],
                datasets: [{
                    label: 'Volunteers',
                    data: [<?php foreach ($department_stats as $dept) echo $dept['count'] . ','; ?>],
                    backgroundColor: '#6f42c1'
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true
            }
        });

        // Events Participation Chart
        const eventsCtx = document.getElementById('eventsChart').getContext('2d');
        new Chart(eventsCtx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($event_participation as $event) echo "'" . addslashes(substr($event['title'], 0, 20)) . (strlen($event['title']) > 20 ? '...' : '') . "',"; ?>],
                datasets: [{
                    label: 'Participants',
                    data: [<?php foreach ($event_participation as $event) echo $event['participants'] . ','; ?>],
                    backgroundColor: '#20c997'
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>
</body>
</html>