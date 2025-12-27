<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

include("../db/connection.php");

// Get filter from URL
$filter_type = $_GET['type'] ?? 'all';

// Get all event types with their gallery items
$event_types_query = "
    SELECT DISTINCT e.event_type, e.event_id, e.title as event_title, e.event_date,
           COUNT(g.id) as file_count
    FROM events e 
    JOIN gallery g ON e.event_id = g.event_id 
    " . ($filter_type !== 'all' ? "WHERE e.event_type = ?" : "") . "
    GROUP BY e.event_type, e.event_id, e.title, e.event_date
    ORDER BY e.event_date DESC
";

$event_types_stmt = $pdo->prepare($event_types_query);
if ($filter_type !== 'all') {
    $event_types_stmt->execute([$filter_type]);
} else {
    $event_types_stmt->execute();
}
$event_types_data = $event_types_stmt->fetchAll();

// Organize data by event type
$organized_gallery = [];
foreach ($event_types_data as $event) {
    $event_type = $event['event_type'];
    if (!isset($organized_gallery[$event_type])) {
        $organized_gallery[$event_type] = [
            'type_name' => $event['event_type'],
            'events' => []
        ];
    }
    
    // Get files for this specific event
    $files_query = "
        SELECT g.*, 
               CASE 
                   WHEN g.user_type = 'volunteer' THEN v.name 
                   ELSE g.uploaded_by 
               END as display_name,
               g.user_type
        FROM gallery g
        LEFT JOIN volunteers v ON g.uploaded_by = v.volunteer_id AND g.user_type = 'volunteer'
        WHERE g.event_id = ?
        ORDER BY g.uploaded_at DESC
        LIMIT 8
    ";
    $files_stmt = $pdo->prepare($files_query);
    $files_stmt->execute([$event['event_id']]);
    $files = $files_stmt->fetchAll();
    
    $organized_gallery[$event_type]['events'][] = [
        'event_id' => $event['event_id'],
        'title' => $event['event_title'],
        'date' => $event['event_date'],
        'file_count' => $event['file_count'],
        'files' => $files
    ];
}

// Get statistics for the filter
$stats_query = "
    SELECT 
        COUNT(DISTINCT g.id) as total_files,
        COUNT(DISTINCT e.event_id) as total_events,
        COUNT(DISTINCT g.uploaded_by) as total_contributors,
        SUM(CASE WHEN LOWER(g.image_path) LIKE '%.mp4%' OR LOWER(g.image_path) LIKE '%.avi%' OR LOWER(g.image_path) LIKE '%.mov%' THEN 1 ELSE 0 END) as total_videos,
        SUM(CASE WHEN g.user_type = 'volunteer' THEN 1 ELSE 0 END) as volunteer_uploads,
        SUM(CASE WHEN g.user_type = 'admin' THEN 1 ELSE 0 END) as admin_uploads
    FROM gallery g
    JOIN events e ON g.event_id = e.event_id
    " . ($filter_type !== 'all' ? "WHERE e.event_type = ?" : "")
;

$stats_stmt = $pdo->prepare($stats_query);
if ($filter_type !== 'all') {
    $stats_stmt->execute([$filter_type]);
} else {
    $stats_stmt->execute();
}
$stats = $stats_stmt->fetch();

// Get event type counts for filter
$type_counts_query = "
    SELECT e.event_type, COUNT(DISTINCT g.id) as file_count, COUNT(DISTINCT e.event_id) as event_count
    FROM gallery g
    JOIN events e ON g.event_id = e.event_id
    GROUP BY e.event_type
    ORDER BY file_count DESC
";
$type_counts_stmt = $pdo->prepare($type_counts_query);
$type_counts_stmt->execute();
$type_counts = $type_counts_stmt->fetchAll();

// Event type display function
function getEventTypeDisplay($type) {
    $types = [
        'blood_camp' => ['icon' => '🩸', 'name' => 'Blood Donation', 'color' => 'danger', 'description' => 'Life-saving blood donation camps'],
        'tree_plantation' => ['icon' => '🌳', 'name' => 'Tree Plantation', 'color' => 'success', 'description' => 'Green initiatives for sustainable environment'],
        'cleanliness_drive' => ['icon' => '🧹', 'name' => 'Cleanliness Drive', 'color' => 'info', 'description' => 'Swachh Bharat and community cleaning'],
        'awareness' => ['icon' => '📢', 'name' => 'Awareness Program', 'color' => 'warning', 'description' => 'Spreading knowledge on social issues'],
        'medical_camp' => ['icon' => '🏥', 'name' => 'Medical Camp', 'color' => 'primary', 'description' => 'Health checkups and medical assistance'],
        'educational' => ['icon' => '📚', 'name' => 'Educational Activity', 'color' => 'secondary', 'description' => 'Teaching and educational support'],
        'cultural' => ['icon' => '🎭', 'name' => 'Cultural Event', 'color' => 'purple', 'description' => 'Celebrating diversity through culture'],
        'sports' => ['icon' => '⚽', 'name' => 'Sports Activity', 'color' => 'success', 'description' => 'Sports and physical activities'],
        'college_event' => ['icon' => '🏫', 'name' => 'College Event', 'color' => 'dark', 'description' => 'Institutional events and activities'],
        'regular' => ['icon' => '🔄', 'name' => 'Regular Activity', 'color' => 'secondary', 'description' => 'Regular community service'],
        'special_camp' => ['icon' => '🏕️', 'name' => 'Special Camp', 'color' => 'warning', 'description' => 'Intensive 7-day camps'],
        'other' => ['icon' => '📋', 'name' => 'Other', 'color' => 'light', 'description' => 'Other community activities']
    ];
    return $types[$type] ?? ['icon' => '📋', 'name' => 'Other', 'color' => 'light', 'description' => 'Various activities'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Gallery - Navneet College of Arts ,Science & Commerce.</title>
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
        <a href="gallery_upload.php">📸 Upload Gallery</a>
        <a href="gallery_view.php">🖼️ View Gallery</a>
        <a href="manage_notifications.php">📢 Notifications</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>

    <h1>🖼️ NSS Gallery Management</h1>
    <p class="lead">Manage and view all gallery content</p>

    <!-- Statistics Section -->
    <div class="stats-card">
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-2">
                    <div class="stat-number"><?= $stats['total_files'] ?></div>
                    <p class="mb-0">Total Files</p>
                </div>
                <div class="col-md-2">
                    <div class="stat-number"><?= $stats['total_events'] ?></div>
                    <p class="mb-0">Events</p>
                </div>
                <div class="col-md-2">
                    <div class="stat-number"><?= $stats['total_contributors'] ?></div>
                    <p class="mb-0">Contributors</p>
                </div>
                <div class="col-md-2">
                    <div class="stat-number"><?= $stats['total_videos'] ?></div>
                    <p class="mb-0">Videos</p>
                </div>
                <div class="col-md-2">
                    <div class="stat-number"><?= $stats['volunteer_uploads'] ?></div>
                    <p class="mb-0">Volunteer Files</p>
                </div>
                <div class="col-md-2">
                    <div class="stat-number"><?= $stats['admin_uploads'] ?></div>
                    <p class="mb-0">Admin Files</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Filter by Event Type</h5>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <span class="badge filter-badge <?= $filter_type == 'all' ? 'bg-primary active' : 'bg-secondary' ?>" 
                      onclick="filterGallery('all')">
                    All Events 
                    <span class="badge bg-light text-dark ms-1"><?= $stats['total_files'] ?></span>
                </span>
                <?php foreach ($type_counts as $type_count): 
                    $type_name = $type_count['event_type'];
                    $type_display = getEventTypeDisplay($type_name);
                    $badge_color = getEventTypeDisplay($type_name)['color'];
                ?>
                    <span class="badge filter-badge <?= $filter_type == $type_name ? 'bg-' . $badge_color . ' active' : 'bg-light text-dark' ?>" 
                          onclick="filterGallery('<?= $type_name ?>')">
                        <?= $type_display['icon'] ?> <?= $type_display['name'] ?>
                        <span class="badge bg-secondary ms-1"><?= $type_count['file_count'] ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php if ($filter_type !== 'all'): ?>
                <div class="mt-3">
                    <a href="gallery_view.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-times me-1"></i>Clear Filter
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Current Filter Info -->
    <?php if ($filter_type !== 'all'): ?>
        <?php 
        $current_type = getEventTypeDisplay($filter_type);
        $current_color = $current_type['color'];
        ?>
        <div class="alert alert-<?= $current_color ?>">
            <div class="row align-items-center">
                <div class="col-md-10">
                    <h5 class="mb-1">
                        <?= $current_type['icon'] ?> Showing <?= $current_type['name'] ?> Events
                    </h5>
                    <p class="mb-0"><?= $current_type['description'] ?></p>
                </div>
                <div class="col-md-2 text-end">
                    <span class="badge bg-white text-<?= $current_color ?> fs-6">
                        <?= $stats['total_files'] ?> files across <?= $stats['total_events'] ?> events
                    </span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-6">
            <a href="gallery_upload.php" class="btn btn-success w-100">
                <i class="fas fa-upload me-2"></i>Upload New Files
            </a>
        </div>
        <div class="col-md-6">
            <a href="view_events.php" class="btn btn-outline-primary w-100">
                <i class="fas fa-calendar me-2"></i>Manage Events
            </a>
        </div>
    </div>

    <?php if (count($organized_gallery) > 0): ?>
        <?php foreach ($organized_gallery as $event_type => $type_data): 
            $type_info = getEventTypeDisplay($event_type);
        ?>
        <section class="event-type-section">
            <div class="event-type-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="type-icon"><?= $type_info['icon'] ?></div>
                        <h2 class="display-6 fw-bold"><?= $type_info['name'] ?> Events</h2>
                        <p class="lead mb-0"><?= $type_info['description'] ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="bg-white text-<?= $type_info['color'] ?> rounded-pill px-4 py-2 d-inline-block">
                            <strong>
                                <?= count($type_data['events']) ?> Events • 
                                <?= array_sum(array_column($type_data['events'], 'file_count')) ?> Files
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($type_data['events'] as $event): ?>
            <div class="event-card">
                <div class="event-card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-1"><?= htmlspecialchars($event['title']) ?></h4>
                            <p class="text-muted mb-0">
                                <i class="fas fa-calendar me-1"></i>
                                <?= date('F j, Y', strtotime($event['date'])) ?>
                                • <?= $event['file_count'] ?> files
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="badge bg-<?= $type_info['color'] ?> px-3 py-2">
                                <?= $type_info['icon'] ?> <?= $type_info['name'] ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="gallery-grid">
                    <?php foreach ($event['files'] as $file): 
                        $isVideo = in_array(pathinfo($file['image_path'], PATHINFO_EXTENSION), ['mp4', 'avi', 'mov']);
                    ?>
                    <div class="gallery-item">
                        <?php if ($isVideo): ?>
                            <video controls>
                                <source src="../assets/uploads/<?= htmlspecialchars($file['image_path']) ?>" type="video/mp4">
                            </video>
                            <div class="video-icon">
                                <i class="fas fa-video"></i>
                            </div>
                        <?php else: ?>
                            <img src="../assets/uploads/<?= htmlspecialchars($file['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($event['title']) ?>"
                                 onerror="this.src='../assets/images/placeholder.jpg'">
                        <?php endif; ?>
                        <div class="uploader-badge">
                            <?= $file['user_type'] == 'volunteer' ? '👤 Volunteer' : '🛡️ Admin' ?>
                        </div>
                        <div class="admin-actions">
                            <a href="../assets/uploads/<?= htmlspecialchars($file['image_path']) ?>" 
                               target="_blank" 
                               class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="?delete=<?= $file['id'] ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Are you sure you want to delete this file?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($event['file_count'] > 8): ?>
                        <a href="event_gallery.php?event_id=<?= $event['event_id'] ?>" class="see-more-btn">
                            <div>
                                <i class="fas fa-images fa-2x mb-2"></i>
                                <div>See All <?= $event['file_count'] ?> Files</div>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-images fa-4x text-muted mb-3"></i>
            <h3>
                <?php if ($filter_type !== 'all'): ?>
                    No <?= getEventTypeDisplay($filter_type)['name'] ?> Events Found
                <?php else: ?>
                    No Gallery Content Yet
                <?php endif; ?>
            </h3>
            <p class="text-muted mb-4">
                <?php if ($filter_type !== 'all'): ?>
                    There are no <?= getEventTypeDisplay($filter_type)['name'] ?> events with gallery content yet.
                <?php else: ?>
                    Start by uploading files to the gallery.
                <?php endif; ?>
            </p>
            <div class="mt-3">
                <?php if ($filter_type !== 'all'): ?>
                    <a href="gallery_view.php" class="btn btn-primary me-2">
                        <i class="fas fa-images me-2"></i>View All Events
                    </a>
                <?php endif; ?>
                <a href="gallery_upload.php" class="btn btn-success">
                    <i class="fas fa-upload me-2"></i>Upload Files
                </a>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function filterGallery(type) {
            window.location.href = `gallery_view.php?type=${type}`;
        }
        
        // Add active class to current filter
        document.addEventListener('DOMContentLoaded', function() {
            const currentFilter = '<?= $filter_type ?>';
            const badges = document.querySelectorAll('.filter-badge');
            
            badges.forEach(badge => {
                if (badge.getAttribute('onclick') && badge.getAttribute('onclick').includes(`'${currentFilter}'`)) {
                    badge.classList.add('active');
                    if (currentFilter !== 'all') {
                        badge.classList.remove('bg-light', 'text-dark');
                    }
                }
            });
        });
    </script>

    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    </div>
</body>
</html>