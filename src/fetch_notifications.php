<?php
// Start session to access user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$db = get_db();

// --- NEW: Handle Count Request ---
if (isset($_GET['action']) && $_GET['action'] === 'count') {
    try {
        $count = 0;
        // Use the existing helper function from db.php if available
        if (function_exists('getUnreadNotificationCount')) {
            $count = getUnreadNotificationCount($user_id);
        } else {
            // Fallback: Direct query if function is not available
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = :uid AND is_read = 0');
            $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $count = $row ? $row['count'] : 0;
        }

        echo json_encode(['count' => $count]);
        exit; // Stop execution to prevent returning the full list
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// --- NEW: Handle Mark Read Request ---
if (isset($_GET['action']) && $_GET['action'] === 'mark_read') {
    try {
        // Update all unread notifications for this user to read
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
        $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}
// --------------------------------

try {
    $stmt = $db->prepare('
        SELECT id, message, type, created_at, is_read 
        FROM notifications 
        WHERE user_id = :uid 
        ORDER BY created_at DESC 
        LIMIT 10
    ');
    
    $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $notifications = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notifications[] = $row;
    }
    
    echo json_encode($notifications);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>