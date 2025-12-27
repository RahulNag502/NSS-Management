<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}
include("../db/connection.php");

// Fetch events
$events = $pdo->query("SELECT * FROM events ORDER BY event_date DESC")->fetchAll();

$success = "";
$certificates_issued = [];

// If event selected
if (isset($_GET['event_id']) && !empty($_GET['event_id'])) {
    $event_id = $_GET['event_id'];
    
    // Get event details including hours
    $event_stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
    $event_stmt->execute([$event_id]);
    $event = $event_stmt->fetch();
    
    // Get registered volunteers for this event
    $volunteers = $pdo->prepare("
        SELECT v.volunteer_id, v.name, v.email 
        FROM event_registrations r
        JOIN volunteers v ON r.volunteer_id = v.volunteer_id
        WHERE r.event_id = ?
    ");
    $volunteers->execute([$event_id]);
    $volunteers = $volunteers->fetchAll();

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['attendance'])) {
        // Include EmailSender for certificate notifications
        require_once "../includes/EmailSender.php";
        $emailSender = new EmailSender();
        
        foreach ($_POST['attendance'] as $volunteer_id => $status) {
            // Check if attendance already exists
            $check = $pdo->prepare("SELECT id FROM attendance WHERE event_id = ? AND volunteer_id = ?");
            $check->execute([$event_id, $volunteer_id]);
            $existing_attendance = $check->fetch();
            
            if ($existing_attendance) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE attendance SET status = ? WHERE event_id = ? AND volunteer_id = ?");
                $stmt->execute([$status, $event_id, $volunteer_id]);
                
                // Update hours if status changed to Present
                if ($status == 'Present') {
                    // Check if hours already awarded
                    $hours_check = $pdo->prepare("SELECT id FROM volunteer_hours WHERE event_id = ? AND volunteer_id = ?");
                    $hours_check->execute([$event_id, $volunteer_id]);
                    
                    if (!$hours_check->fetch()) {
                        // Award hours
                        $hours_stmt = $pdo->prepare("INSERT INTO volunteer_hours (volunteer_id, event_id, hours_earned) VALUES (?, ?, ?)");
                        $hours_stmt->execute([$volunteer_id, $event_id, $event['event_hours']]);
                        
                        // Update volunteer's total hours
                        $update_total = $pdo->prepare("UPDATE volunteers SET total_hours = total_hours + ? WHERE volunteer_id = ?");
                        $update_total->execute([$event['event_hours'], $volunteer_id]);
                        
                        // Check for certificate eligibility
                        $certificate_info = checkCertificateEligibility($pdo, $volunteer_id, $emailSender);
                        if ($certificate_info) {
                            $certificates_issued[] = $certificate_info;
                        }
                    }
                } else {
                    // Remove hours if status changed to Absent
                    $hours_check = $pdo->prepare("SELECT id, hours_earned FROM volunteer_hours WHERE event_id = ? AND volunteer_id = ?");
                    $hours_check->execute([$event_id, $volunteer_id]);
                    $hours_data = $hours_check->fetch();
                    
                    if ($hours_data) {
                        // Remove hours record
                        $remove_hours = $pdo->prepare("DELETE FROM volunteer_hours WHERE id = ?");
                        $remove_hours->execute([$hours_data['id']]);
                        
                        // Update volunteer's total hours
                        $update_total = $pdo->prepare("UPDATE volunteers SET total_hours = total_hours - ? WHERE volunteer_id = ?");
                        $update_total->execute([$hours_data['hours_earned'], $volunteer_id]);
                    }
                }
            } else {
                // Insert new attendance
                $stmt = $pdo->prepare("INSERT INTO attendance (event_id, volunteer_id, status) VALUES (?, ?, ?)");
                $stmt->execute([$event_id, $volunteer_id, $status]);
                
                // Award hours if present
                if ($status == 'Present') {
                    $hours_stmt = $pdo->prepare("INSERT INTO volunteer_hours (volunteer_id, event_id, hours_earned) VALUES (?, ?, ?)");
                    $hours_stmt->execute([$volunteer_id, $event_id, $event['event_hours']]);
                    
                    // Update volunteer's total hours
                    $update_total = $pdo->prepare("UPDATE volunteers SET total_hours = total_hours + ? WHERE volunteer_id = ?");
                    $update_total->execute([$event['event_hours'], $volunteer_id]);
                    
                    // Check for certificate eligibility
                    $certificate_info = checkCertificateEligibility($pdo, $volunteer_id, $emailSender);
                    if ($certificate_info) {
                        $certificates_issued[] = $certificate_info;
                    }
                }
            }
        }
        
        // Build success message
        $success = "Attendance marked successfully! Hours have been updated for present volunteers.";
        
        if (!empty($certificates_issued)) {
            $success .= "<br><br><strong>Certificates Issued:</strong><br>";
            foreach ($certificates_issued as $cert) {
                $success .= "• {$cert['volunteer_name']} - {$cert['certificate_type']} Certificate<br>";
                if ($cert['email_sent']) {
                    $success .= "&nbsp;&nbsp;&nbsp;&nbsp;📧 Notification email sent<br>";
                }
            }
        }
    }
}

// Function to check certificate eligibility
function checkCertificateEligibility($pdo, $volunteer_id, $emailSender = null) {
    // Get volunteer's total hours, name, and email
    $hours_stmt = $pdo->prepare("SELECT total_hours, name, email FROM volunteers WHERE volunteer_id = ?");
    $hours_stmt->execute([$volunteer_id]);
    $volunteer = $hours_stmt->fetch();
    
    if (!$volunteer) return null;
    
    $total_hours = $volunteer['total_hours'];
    $volunteer_name = $volunteer['name'];
    $volunteer_email = $volunteer['email'];
    
    // Check if certificate_type column exists
    $check_column = $pdo->prepare("SHOW COLUMNS FROM certificates LIKE 'certificate_type'");
    $check_column->execute();
    $column_exists = $check_column->fetch();
    
    $certificate_issued = null;
    $email_sent = false;
    
    // Check for 240-hour certificate first (highest priority)
    if ($total_hours >= 240) {
        if ($column_exists) {
            $cert_check = $pdo->prepare("SELECT id FROM certificates WHERE volunteer_id = ? AND certificate_type = '240_hours'");
        } else {
            $cert_check = $pdo->prepare("SELECT id FROM certificates WHERE volunteer_id = ? AND certificate_code LIKE 'CERT-240-%'");
        }
        $cert_check->execute([$volunteer_id]);
        
        if (!$cert_check->fetch()) {
            // Remove any existing 120-hour certificates first
            if ($column_exists) {
                $remove_120 = $pdo->prepare("DELETE FROM certificates WHERE volunteer_id = ? AND certificate_type = '120_hours'");
                $remove_120->execute([$volunteer_id]);
            } else {
                $remove_120 = $pdo->prepare("DELETE FROM certificates WHERE volunteer_id = ? AND certificate_code LIKE 'CERT-120-%'");
                $remove_120->execute([$volunteer_id]);
            }
            
            // Issue 240-hour certificate
            $certificate_code = "CERT-240-" . strtoupper(uniqid());
            if ($column_exists) {
                $cert_stmt = $pdo->prepare("INSERT INTO certificates (volunteer_id, certificate_code, certificate_type, issued_date) VALUES (?, ?, '240_hours', NOW())");
                $cert_stmt->execute([$volunteer_id, $certificate_code]);
            } else {
                $cert_stmt = $pdo->prepare("INSERT INTO certificates (volunteer_id, certificate_code, issued_date) VALUES (?, ?, NOW())");
                $cert_stmt->execute([$volunteer_id, $certificate_code]);
            }
            
            // Send email notification if email sender is provided
            if ($emailSender && $volunteer_email) {
                $emailResult = $emailSender->sendCertificateEmail(
                    $volunteer_name,
                    $volunteer_email,
                    '240_hours',
                    $certificate_code
                );
                $email_sent = $emailResult['success'];
            }
            
            $certificate_issued = [
                'volunteer_name' => $volunteer_name,
                'certificate_type' => '240 Hours',
                'certificate_code' => $certificate_code,
                'email_sent' => $email_sent
            ];
        }
    }
    // Check for 120-hour certificate (only if they don't qualify for 240-hour yet)
    elseif ($total_hours >= 120 && $total_hours < 240) {
        if ($column_exists) {
            $cert_check = $pdo->prepare("SELECT id FROM certificates WHERE volunteer_id = ? AND certificate_type = '120_hours'");
        } else {
            $cert_check = $pdo->prepare("SELECT id FROM certificates WHERE volunteer_id = ? AND certificate_code LIKE 'CERT-120-%'");
        }
        $cert_check->execute([$volunteer_id]);
        
        if (!$cert_check->fetch()) {
            // Also check if they already have a 240-hour certificate (shouldn't happen but just in case)
            if ($column_exists) {
                $cert_check_240 = $pdo->prepare("SELECT id FROM certificates WHERE volunteer_id = ? AND certificate_type = '240_hours'");
            } else {
                $cert_check_240 = $pdo->prepare("SELECT id FROM certificates WHERE volunteer_id = ? AND certificate_code LIKE 'CERT-240-%'");
            }
            $cert_check_240->execute([$volunteer_id]);
            
            if (!$cert_check_240->fetch()) {
                // Issue 120-hour certificate
                $certificate_code = "CERT-120-" . strtoupper(uniqid());
                if ($column_exists) {
                    $cert_stmt = $pdo->prepare("INSERT INTO certificates (volunteer_id, certificate_code, certificate_type, issued_date) VALUES (?, ?, '120_hours', NOW())");
                    $cert_stmt->execute([$volunteer_id, $certificate_code]);
                } else {
                    $cert_stmt = $pdo->prepare("INSERT INTO certificates (volunteer_id, certificate_code, issued_date) VALUES (?, ?, NOW())");
                    $cert_stmt->execute([$volunteer_id, $certificate_code]);
                }
                
                // Send email notification if email sender is provided
                if ($emailSender && $volunteer_email) {
                    $emailResult = $emailSender->sendCertificateEmail(
                        $volunteer_name,
                        $volunteer_email,
                        '120_hours',
                        $certificate_code
                    );
                    $email_sent = $emailResult['success'];
                }
                
                $certificate_issued = [
                    'volunteer_name' => $volunteer_name,
                    'certificate_type' => '120 Hours',
                    'certificate_code' => $certificate_code,
                    'email_sent' => $email_sent
                ];
            }
        }
    }
    
    return $certificate_issued;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Attendance</title>
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
        .event-info { background: linear-gradient(135deg, var(--light-bg) 0%, #f0f1f5 100%); padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 2px solid var(--border-color); }
        .hours-progress { height: 20px; margin-bottom: 5px; border-radius: 10px; background: var(--light-bg); overflow: hidden; }
        .cert-badge { font-size: 0.75em; font-weight: 600; padding: 4px 8px; border-radius: 6px; }
        .volunteer-row:hover { background-color: rgba(102, 126, 234, 0.05); }
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

    <h2>✔ Mark Attendance</h2>

    <?php if (!empty($success)): ?>
        <div class='alert alert-success'>
            <?= $success ?>
        </div>
    <?php endif; ?>

    <form method="get" class="mb-4 card p-3">
        <div class="mb-3">
            <label class="form-label">Select Event:</label>
            <select name="event_id" class="form-select" onchange="this.form.submit()" required>
                <option value="">-- Choose Event --</option>
                <?php foreach ($events as $e): ?>
                    <option value="<?= $e['event_id'] ?>" <?= (isset($_GET['event_id']) && $_GET['event_id']==$e['event_id'])?'selected':'' ?>>
                        <?= htmlspecialchars($e['title']) ?> (<?= $e['event_date'] ?>) - <?= $e['event_hours'] ?> hours
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if (isset($event) && isset($volunteers)): ?>
        <div class="event-info">
            <h5>Event Information</h5>
            <p><strong>Event:</strong> <?= htmlspecialchars($event['title']) ?></p>
            <p><strong>Date:</strong> <?= $event['event_date'] ?></p>
            <p><strong>Hours:</strong> <span class="badge bg-primary"><?= $event['event_hours'] ?> hours</span></p>
            <p class="text-muted mb-0">Volunteers marked as 'Present' will automatically earn <?= $event['event_hours'] ?> hours.</p>
            <?php if ($event['event_hours'] > 0): ?>
                <p class="text-muted"><small>Certificates are automatically issued when volunteers reach 120 or 240 total hours.</small></p>
            <?php endif; ?>
        </div>

        <?php if (count($volunteers) > 0): ?>
            <form method="post" class="card p-3">
                <h4>Mark Attendance for Volunteers</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Name</th>
                                <th>Volunteer ID</th>
                                <th>Current Hours</th>
                                <th>Certificate Status</th>
                                <th>Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($volunteers as $v): 
                                // Get current status if exists
                                $status_stmt = $pdo->prepare("SELECT status FROM attendance WHERE event_id = ? AND volunteer_id = ?");
                                $status_stmt->execute([$event_id, $v['volunteer_id']]);
                                $current_status = $status_stmt->fetchColumn();
                                
                                // Get volunteer's total hours
                                $hours_stmt = $pdo->prepare("SELECT total_hours FROM volunteers WHERE volunteer_id = ?");
                                $hours_stmt->execute([$v['volunteer_id']]);
                                $total_hours = $hours_stmt->fetchColumn();
                                
                                // Get certificate status
                                $cert_stmt = $pdo->prepare("SELECT certificate_code FROM certificates WHERE volunteer_id = ? ORDER BY issued_date DESC LIMIT 1");
                                $cert_stmt->execute([$v['volunteer_id']]);
                                $latest_cert = $cert_stmt->fetch();
                                
                                $certificate_status = 'None';
                                $certificate_badge = 'bg-secondary';
                                
                                if ($latest_cert) {
                                    $cert_code = $latest_cert['certificate_code'];
                                    if (strpos($cert_code, 'CERT-240-') === 0) {
                                        $certificate_status = '240 Hours';
                                        $certificate_badge = 'bg-success';
                                    } elseif (strpos($cert_code, 'CERT-120-') === 0) {
                                        $certificate_status = '120 Hours';
                                        $certificate_badge = 'bg-warning';
                                    } else {
                                        $certificate_status = 'Participation';
                                        $certificate_badge = 'bg-info';
                                    }
                                } else {
                                    if ($total_hours >= 240) {
                                        $certificate_status = 'Eligible for 240H';
                                        $certificate_badge = 'bg-danger';
                                    } elseif ($total_hours >= 120) {
                                        $certificate_status = 'Eligible for 120H';
                                        $certificate_badge = 'bg-danger';
                                    }
                                }
                                
                                // Calculate progress percentages
                                $progress_120 = min(100, ($total_hours / 120) * 100);
                                $progress_240 = min(100, ($total_hours / 240) * 100);
                            ?>
                            <tr class="volunteer-row">
                                <td><?= htmlspecialchars($v['name']) ?></td>
                                <td><code><?= htmlspecialchars($v['volunteer_id']) ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-<?= $total_hours >= 240 ? 'success' : ($total_hours >= 120 ? 'warning' : 'info') ?> me-2">
                                            <?= $total_hours ?> hrs
                                        </span>
                                        <?php if ($total_hours < 240): ?>
                                            <small class="text-muted">
                                                <?php if ($total_hours < 120): ?>
                                                    (<?= 120 - $total_hours ?> to 120H)
                                                <?php else: ?>
                                                    (<?= 240 - $total_hours ?> to 240H)
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($total_hours < 240): ?>
                                        <div class="progress hours-progress" title="Progress to next certificate">
                                            <div class="progress-bar 
                                                <?= $total_hours >= 120 ? 'bg-warning' : 'bg-info' ?>" 
                                                style="width: <?= $total_hours >= 120 ? $progress_240 : $progress_120 ?>%">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $certificate_badge ?> cert-badge">
                                        <?= $certificate_status ?>
                                    </span>
                                </td>
                                <td>
                                    <select name="attendance[<?= $v['volunteer_id'] ?>]" class="form-select form-select-sm">
                                        <option value="Present" <?= ($current_status == 'Present') ? 'selected' : '' ?>>Present</option>
                                        <option value="Absent" <?= ($current_status == 'Absent' || !$current_status) ? 'selected' : '' ?>>Absent</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">
                            <strong>Legend:</strong> 
                            <span class="badge bg-success">240H Cert</span>
                            <span class="badge bg-warning">120H Cert</span>
                            <span class="badge bg-info">Participation</span>
                            <span class="badge bg-danger">Eligible</span>
                        </small>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">No volunteers registered for this event.</div>
        <?php endif; ?>
    <?php elseif (isset($_GET['event_id'])): ?>
        <div class="alert alert-warning">No volunteers registered for this event.</div>
    <?php endif; ?>
    
    <div class="mt-3">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    </div>
</body>
</html>