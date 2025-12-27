<?php
session_start();
if (!isset($_SESSION['volunteer'])) {
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
        SUM(CASE WHEN LOWER(g.image_path) LIKE '%.mp4%' OR LOWER(g.image_path) LIKE '%.avi%' OR LOWER(g.image_path) LIKE '%.mov%' THEN 1 ELSE 0 END) as total_videos
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
    <title>Gallery - Navneet College of Arts ,Science & Commerce.</title>
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
        .event-type-section { margin-bottom: 3rem; }
        .event-type-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .event-card { 
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .event-card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        .gallery-grid { 
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            padding: 1.5rem;
        }
        .gallery-item { 
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .gallery-item:hover {
            transform: scale(1.05);
        }
        .gallery-item img, .gallery-item video {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .video-icon {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7em;
        }
        .uploader-badge {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7em;
        }
        .bg-purple { background-color: #6f42c1 !important; }
        .text-purple { color: #6f42c1 !important; }
        .see-more-btn {
            background: rgba(255,255,255,0.9);
            border: 2px dashed #007bff;
            color: #007bff;
            padding: 1rem;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .see-more-btn:hover {
            background: #007bff;
            color: white;
            text-decoration: none;
        }
        .type-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 8px 12px;
        }
        .filter-badge:hover {
            transform: scale(1.05);
        }
        .filter-badge.active {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(0,123,255,0.3);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
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
        <a href="upload_photos.php">📸 Upload Photos</a>
        <a href="feedback.php">💬 Feedback</a>
        <a href="notifications.php">📢 Notifications</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>

    <h1>🖼️ NSS Gallery</h1>
    <p class="lead">Explore memories from our community service activities</p>

    <!-- Statistics Section -->
    <div class="stats-card">
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="stat-number"><?= $stats['total_files'] ?></div>
                    <p class="mb-0">Total Files</p>
                </div>
                <div class="col-md-3">
                    <div class="stat-number"><?= $stats['total_events'] ?></div>
                    <p class="mb-0">Events Covered</p>
                </div>
                <div class="col-md-3">
                    <div class="stat-number"><?= $stats['total_contributors'] ?></div>
                    <p class="mb-0">Contributors</p>
                </div>
                <div class="col-md-3">
                    <div class="stat-number"><?= $stats['total_videos'] ?></div>
                    <p class="mb-0">Videos</p>
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
                    <a href="view_gallery.php" class="btn btn-outline-primary btn-sm">
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
                            <?= $file['user_type'] == 'volunteer' ? '👤' : '🛡️' ?>
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
                    Be the first to upload photos and videos from NSS events!
                <?php endif; ?>
            </p>
            <div class="mt-3">
                <?php if ($filter_type !== 'all'): ?>
                    <a href="view_gallery.php" class="btn btn-primary me-2">
                        <i class="fas fa-images me-2"></i>View All Events
                    </a>
                <?php endif; ?>
                <a href="upload_photos.php" class="btn btn-success">
                    <i class="fas fa-upload me-2"></i>Upload Files
                </a>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function filterGallery(type) {
            window.location.href = `view_gallery.php?type=${type}`;
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
</body>
</html>