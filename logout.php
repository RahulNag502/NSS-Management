<?php
session_start();

// Log logout activity if user was logged in
if (isset($_SESSION['admin']) || isset($_SESSION['volunteer'])) {
    include("./db/connection.php");
    
    $user_type = isset($_SESSION['admin']) ? 'admin' : 'volunteer';
    $user_id = $_SESSION['admin'] ?? $_SESSION['volunteer'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    try {
        // Check if action column exists
        $check_column = $pdo->prepare("SHOW COLUMNS FROM login_activity LIKE 'action'");
        $check_column->execute();
        $column_exists = $check_column->fetch();
        
        if ($column_exists) {
            // Log logout activity with action column
            $log_stmt = $pdo->prepare("INSERT INTO login_activity (user_id, user_type, login_time, ip_address, action) VALUES (?, ?, NOW(), ?, 'logout')");
            $log_stmt->execute([$user_id, $user_type, $ip_address]);
        } else {
            // Log logout activity without action column
            $log_stmt = $pdo->prepare("INSERT INTO login_activity (user_id, user_type, login_time, ip_address) VALUES (?, ?, NOW(), ?)");
            $log_stmt->execute([$user_id, $user_type, $ip_address]);
        }
    } catch (Exception $e) {
        // Silently fail if logging fails
        error_log("Logout activity logging failed: " . $e->getMessage());
    }
}

// Store logout message in session before destroying
$logout_message = "";
if (isset($_SESSION['admin'])) {
    $logout_message = "Admin " . $_SESSION['admin'] . " logged out successfully.";
} elseif (isset($_SESSION['volunteer'])) {
    // Get volunteer name for better message
    if (isset($pdo)) {
        try {
            $volunteer_stmt = $pdo->prepare("SELECT name FROM volunteers WHERE volunteer_id = ?");
            $volunteer_stmt->execute([$_SESSION['volunteer']]);
            $volunteer = $volunteer_stmt->fetch();
            if ($volunteer) {
                $logout_message = "Volunteer " . $volunteer['name'] . " logged out successfully.";
            } else {
                $logout_message = "Volunteer logged out successfully.";
            }
        } catch (Exception $e) {
            // Fallback if we can't get the name
            $logout_message = "Volunteer logged out successfully.";
        }
    } else {
        $logout_message = "Volunteer logged out successfully.";
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Redirect to login page with logout message
header("Location: login.php?message=logout&user=" . urlencode($logout_message));
exit;
?>