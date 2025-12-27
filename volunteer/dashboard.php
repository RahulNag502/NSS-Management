<?php
session_start();
if (!isset($_SESSION['volunteer'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");

$volunteer_id = $_SESSION['volunteer'];
$stmt = $pdo->prepare("SELECT * FROM volunteers WHERE volunteer_id=?");
$stmt->execute([$volunteer_id]);
$user = $stmt->fetch();

// Get counts for volunteer
$events_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE volunteer_id = ?");
$events_stmt->execute([$volunteer_id]);
$events_count = $events_stmt->fetchColumn();

$certificates_stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE volunteer_id = ?");
$certificates_stmt->execute([$volunteer_id]);
$certificates_count = $certificates_stmt->fetchColumn();

// Get hours progress
$total_hours = $user['total_hours'];
$progress_120 = min(100, ($total_hours / 120) * 100);
$progress_240 = min(100, ($total_hours / 240) * 100);

// Check certificate status
$has_120_stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE volunteer_id = ? AND (certificate_code LIKE 'CERT-120-%' OR certificate_type = '120_hours')");
$has_120_stmt->execute([$volunteer_id]);
$has_120_cert = $has_120_stmt->fetchColumn();

$has_240_stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE volunteer_id = ? AND (certificate_code LIKE 'CERT-240-%' OR certificate_type = '240_hours')");
$has_240_stmt->execute([$volunteer_id]);
$has_240_cert = $has_240_stmt->fetchColumn();

// Get event participation stats
$event_stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_events,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as attended_events,
        SUM(CASE WHEN a.status IS NULL OR a.status = 'Absent' THEN 1 ELSE 0 END) as missed_events
    FROM event_registrations r
    LEFT JOIN attendance a ON r.event_id = a.event_id AND r.volunteer_id = a.volunteer_id
    WHERE r.volunteer_id = ?
");
$event_stats_stmt->execute([$volunteer_id]);
$event_stats = $event_stats_stmt->fetch();

// Get recent activities
$recent_activities_stmt = $pdo->prepare("
    (SELECT 'event_registration' as type, title, registered_at as date 
     FROM event_registrations r 
     JOIN events e ON r.event_id = e.event_id 
     WHERE r.volunteer_id = ?)
    UNION ALL
    (SELECT 'certificate_issued' as type, certificate_code as title, issued_date as date 
     FROM certificates 
     WHERE volunteer_id = ?)
    ORDER BY date DESC 
    LIMIT 5
");
$recent_activities_stmt->execute([$volunteer_id, $volunteer_id]);
$recent_activities = $recent_activities_stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Volunteer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .dashboard-stats { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 200px; padding: 20px; background: #f8f9fa; border-radius: 10px; text-align: center; border: 1px solid #dee2e6; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007bff; }
        .nav-menu { background: #343a40; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .nav-menu a { color: white; text-decoration: none; margin-right: 20px; padding: 8px 15px; border-radius: 4px; }
        .nav-menu a:hover { background: #495057; color: #ffc107; }
        .progress { height: 25px; margin-bottom: 15px; }
        .progress-label { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .cert-badge { font-size: 0.9em; }
        .chart-container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .activity-item { padding: 10px; border-left: 3px solid #007bff; margin-bottom: 10px; background: #f8f9fa; }

        /* CSS Variables and Modern Base Styles */
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
        }

        .navbar {
            background: linear-gradient(135deg, var(--dark-bg) 0%, #0f172a 100%);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .college-logo:hover { transform: scale(1.05); filter: brightness(1.1); }
        .navbar-brand { font-weight: 700; font-size: 1.2rem; color: white !important; }
        .navbar-brand:hover { color: var(--primary-color) !important; }
        .nav-link { color: rgba(255, 255, 255, 0.8) !important; font-weight: 500; position: relative; }
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

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            background: white;
            overflow: hidden;
        }
        .card:hover { box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); transform: translateY(-5px); }

        .form-label { font-weight: 600; color: var(--text-primary); margin-bottom: 8px; }
        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 15px;
            background-color: var(--light-bg);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background-color: white;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white !important;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4); color: white !important; }
        .btn-success { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); color: white !important; }
        .btn-success:hover { transform: translateY(-2px); }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
        }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background-color: rgba(16, 185, 129, 0.1); color: #047857; border-left-color: var(--success-color); }
        .alert-danger { background-color: rgba(239, 68, 68, 0.1); color: #991b1b; border-left-color: var(--danger-color); }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }
        .table tbody tr:hover { background-color: rgba(102, 126, 234, 0.05); }

        .badge {
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .badge-success { background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%); }
        .badge-danger { background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%); }

        /* Enhanced stat cards */
        .stat-card { background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); border: 1px solid var(--border-color); }
        .stat-number { color: var(--primary-color); }

        .nav-menu { background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%); border-radius: 8px; }
        .nav-menu a { transition: all 0.3s ease; }
        .nav-menu a:hover { background: var(--primary-color); color: white !important; }

        @media (max-width: 768px) {
            body { padding-top: 70px; }
            .btn { font-size: 0.85rem; padding: 10px 16px; }
        }
    </style>
</head>
<body class="container my-5">
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
      
<a href="profile.php">👤 My Profile</a>
        <a href="view_events.php">📅 Available Events</a>
        <a href="my_registrations.php">📝 My Registrations</a>
        <a href="my_attendance.php">✅ My Attendance</a>
        <a href="my_certificates.php">🎓 My Certificates</a>
        <a href="view_gallery.php">🖼️ Gallery</a>
        <a href="upload_photos.php">📸 Upload Photos</a>
        <a href="feedback.php">💬 Feedback</a>
        <a href="notifications.php">📢 Notifications</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>

    <h2><img src="../assets/profile_images/<?= $user['profile_image'] ?: 'default_profile.jpg' ?>" 
     class="rounded-circle me-3" 
     width="40" height="40"
     alt="Profile"
     onerror="this.src='../assets/images/default_profile.jpg'">Welcome, <?= htmlspecialchars($user['name']); ?> 👋</h2>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-number"><?= $events_count ?></div>
            <div>Events Registered</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $total_hours ?></div>
            <div>Hours Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $certificates_count ?></div>
            <div>Certificates Earned</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $event_stats['attended_events'] ?? 0 ?></div>
            <div>Events Attended</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <h5>📊 Event Participation</h5>
                <canvas id="participationChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h5>🎯 Certificate Progress</h5>
                <canvas id="progressChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Profile Information</h5>
                    <p><strong>Volunteer ID:</strong> <code><?= htmlspecialchars($user['volunteer_id']); ?></code></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
                    <p><strong>Department:</strong> <?= htmlspecialchars($user['department']); ?></p>
                    <p><strong>Year:</strong> <?= htmlspecialchars($user['year']); ?></p>
                    <p><strong>Total Hours:</strong> <span class="badge bg-primary"><?= $total_hours ?> hours</span></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Certificate Progress</h5>
                    
                    <!-- 120 Hours Progress -->
                    <div class="progress-label">
                        <span>120 Hours Certificate</span>
                        <span>
                            <?php if ($has_120_cert): ?>
                                <span class="badge bg-success cert-badge">✓ Earned</span>
                            <?php else: ?>
                                <span><?= $total_hours ?>/120 hours</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-<?= $has_120_cert ? 'success' : 'warning' ?>" 
                             style="width: <?= $progress_120 ?>%">
                            <?= number_format($progress_120, 1) ?>%
                        </div>
                    </div>
                    
                    <!-- 240 Hours Progress -->
                    <div class="progress-label">
                        <span>240 Hours Certificate</span>
                        <span>
                            <?php if ($has_240_cert): ?>
                                <span class="badge bg-success cert-badge">✓ Earned</span>
                            <?php else: ?>
                                <span><?= $total_hours ?>/240 hours</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-<?= $has_240_cert ? 'success' : 'info' ?>" 
                             style="width: <?= $progress_240 ?>%">
                            <?= number_format($progress_240, 1) ?>%
                        </div>
                    </div>
                    
                    <?php if (!$has_120_cert && $total_hours < 120): ?>
                        <p class="text-muted mt-2">
                            <small>You need <?= 120 - $total_hours ?> more hours for 120-hour certificate</small>
                        </p>
                    <?php elseif (!$has_240_cert && $total_hours < 240): ?>
                        <p class="text-muted mt-2">
                            <small>You need <?= 240 - $total_hours ?> more hours for 240-hour certificate</small>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Activity</h5>
                    <?php if (count($recent_activities) > 0): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <strong>
                                    <?php if ($activity['type'] == 'event_registration'): ?>
                                        📅 Registered for:
                                    <?php else: ?>
                                        🎓 Certificate Issued:
                                    <?php endif; ?>
                                </strong>
                                <?= htmlspecialchars($activity['title']) ?><br>
                                <small class="text-muted"><?= date('M j, Y g:i A', strtotime($activity['date'])) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No recent activities.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="view_events.php" class="btn btn-primary">Browse Events</a>
                        <a href="my_registrations.php" class="btn btn-info">My Registrations</a>
                        <a href="my_certificates.php" class="btn btn-success">My Certificates</a>
                        <a href="upload_photos.php" class="btn btn-warning">Upload Photos</a>
                        <a href="feedback.php" class="btn btn-secondary">Submit Feedback</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Event Participation Chart
        const participationCtx = document.getElementById('participationChart').getContext('2d');
        new Chart(participationCtx, {
            type: 'doughnut',
            data: {
                labels: ['Attended Events', 'Missed Events', 'Upcoming Events'],
                datasets: [{
                    data: [
                        <?= $event_stats['attended_events'] ?? 0 ?>,
                        <?= $event_stats['missed_events'] ?? 0 ?>,
                        <?= $events_count - ($event_stats['attended_events'] ?? 0) - ($event_stats['missed_events'] ?? 0) ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#17a2b8']
                }]
            }
        });

        // Progress Chart
        const progressCtx = document.getElementById('progressChart').getContext('2d');
        new Chart(progressCtx, {
            type: 'bar',
            data: {
                labels: ['120 Hours', '240 Hours'],
                datasets: [{
                    label: 'Your Progress',
                    data: [<?= $total_hours ?>, <?= $total_hours ?>],
                    backgroundColor: ['#ffc107', '#17a2b8'],
                    maxBarThickness: 30
                }, {
                    label: 'Required',
                    data: [120, 240],
                    backgroundColor: ['#e9ecef', '#e9ecef'],
                    type: 'line',
                    fill: false,
                    borderColor: '#6c757d',
                    borderDash: [5, 5],
                    pointStyle: false
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 250
                    }
                }
            }
        });
    </script>
</body>
</html>