<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$is_logged_in = isset($_SESSION['user']);
$user = $_SESSION['user'] ?? null;

$notif_badge_text = '0';
$unread_count = 0;
$notifications = [];
$has_more_notifs = false;

if ($is_logged_in && isset($user['id'])) {
    // 1. Get Unread Count for Badge
    $unread_count = function_exists('getUnreadNotificationCount') ? getUnreadNotificationCount($user['id']) : 0;
    $notif_badge_text = ($unread_count > 9) ? '9+' : $unread_count;

    // 2. Fetch Notifications for Dropdown (Server-Side Rendering)
    // We fetch 6 items to determine if there are more than 5 (for the "View full" link)
    $db = get_db();
    try {
        $stmt = $db->prepare('SELECT id, message, created_at, is_read FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 6');
        $stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $notifications[] = $row;
        }
        
        // Check if we have more than 5
        if (count($notifications) > 5) {
            $has_more_notifs = true;
            array_pop($notifications); // Remove the 6th item so we only show 5
        }
    } catch (Exception $e) {
        // Handle error silently or log it
        error_log("Notification fetch error: " . $e->getMessage());
    }
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../styles/header.css">

<style>
    .notification-wrapper {
        position: relative;
        cursor: pointer;
    }
    
    .notification-dropdown {
        display: none;
        position: absolute;
        top: 130%;
        right: -10px;
        width: 320px;
        background: #ffffff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        z-index: 1050;
        padding: 0;
        margin: 0;
        cursor: default;
    }
    
    .notification-dropdown::before {
        content: '';
        position: absolute;
        top: -6px;
        right: 14px;
        width: 12px;
        height: 12px;
        background: #fff;
        border-top: 1px solid #e0e0e0;
        border-left: 1px solid #e0e0e0;
        transform: rotate(45deg);
        z-index: 1051;
    }

    .notification-dropdown.show {
        display: block;
        animation: fadeIn 0.2s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Notification Header */
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid #f0f0f0;
        background-color: #fff;
        border-radius: 8px 8px 0 0;
    }

    .notification-title {
        font-weight: 600;
        font-size: 1rem;
        color: #333;
    }

    .mark-all-read {
        font-size: 0.8rem;
        color: #198754;
        cursor: pointer;
        transition: color 0.2s;
    }
    
    .mark-all-read:hover {
        color: #146c43;
        text-decoration: underline;
    }

    /* Notification List Container */
    .notification-list {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 300px;
        overflow-y: auto;
    }

    .notification-item {
        padding: 12px 16px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
        cursor: pointer;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
    }

    .notification-item.unread {
        background-color: #e8f5e9;
        border-left: 4px solid #198754;
    }

    .notification-content {
        font-size: 0.9rem;
        color: #333;
        line-height: 1.4;
    }

    .notification-date {
        display: block;
        font-size: 0.75rem;
        color: #888;
        margin-top: 4px;
    }

    .notification-empty {
        padding: 20px;
        text-align: center;
        color: #6c757d;
        font-style: italic;
    }

    /* Notification Footer */
    .notification-footer {
        padding: 10px;
        text-align: center;
        border-top: 1px solid #f0f0f0;
        background-color: #f8f9fa;
        border-radius: 0 0 8px 8px;
    }

    .view-full-link {
        font-size: 0.85rem;
        color: #198754;
        text-decoration: none;
        font-weight: 500;
    }

    .view-full-link:hover {
        text-decoration: underline;
    }
</style>

<header class="navbar navbar-light dashboard-header sticky-top mb-4 p-0">
    <nav class="navbar navbar-light bg-white border-bottom shadow-sm d-lg-none sticky-top py-3 mb-4 w-100">
        <div class="container-xl d-flex justify-content-between align-items-center">
            <a href="<?= $is_logged_in ? 'users_dashboard.php' : '../index.php' ?>" class="navbar-brand d-flex align-items-center gap-2 m-0">
                <i class="bi bi-book-half text-success"></i>
                <span class="fs-5 fw-bold text-dark">ReviewHub</span>
            </a>

            <?php if ($is_logged_in): ?>
                <div class="d-flex align-items-center gap-3">
                    
                    <div class="notification-wrapper" id="mobileNotificationWrapper">
                        <i class="bi bi-bell fs-5"></i>
                        
                        <span class="badge rounded-pill bg-danger notification-badge <?= ($unread_count > 0) ? '' : 'd-none' ?>">
                            <?= $notif_badge_text ?>
                            <span class="visually-hidden">unread notifications</span>
                        </span>

                        <div class="notification-dropdown">
                            <!-- Mobile Dropdown Content -->
                             <div class="notification-header">
                                <span class="notification-title">Notification</span>
                                <span class="mark-all-read mark-all-read-btn">Mark all done</span>
                            </div>
                            <ul class="notification-list">
                                <?php if (empty($notifications)): ?>
                                    <li class="notification-empty">No new notifications</li>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <?php 
                                            $is_unread = ($notif['is_read'] == 0) ? ' unread' : '';
                                            $date = new DateTime($notif['created_at']);
                                            $dateStr = $date->format('n/j/Y h:i A'); 
                                        ?>
                                        <li class="notification-item<?= $is_unread ?>">
                                            <div class="notification-content"><?= htmlspecialchars($notif['message']) ?></div>
                                            <span class="notification-date"><?= $dateStr ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            <?php if ($has_more_notifs): ?>
                                <div class="notification-footer">
                                    <a href="notifications.php" class="view-full-link">View full notification</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none" id="mobileHeaderUser" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 35px; height: 35px; border: 2px solid #fff;">
                                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="mobileHeaderUser">
                            <li><h6 class="dropdown-header">Signed in as <b><?= htmlspecialchars($user['username']) ?></b></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2 text-muted"></i> Profile</a></li>
                            <li><a class="dropdown-item py-2" href="contact.php"><i class="bi bi-headset me-2 text-muted"></i> Contact Us</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <a href="../index.php" class="btn btn-sm btn-outline-success">Log In</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container-fluid d-none d-lg-flex justify-content-end align-items-center p-3">
        
        <div class="d-flex align-items-center gap-4">
            
            <?php if ($is_logged_in): ?>
                <div class="notification-wrapper" id="desktopNotificationWrapper">
                    <i class="bi bi-bell fs-5"></i>
                    
                    <span class="badge rounded-pill bg-danger notification-badge <?= ($unread_count > 0) ? '' : 'd-none' ?>">
                        <?= $notif_badge_text ?>
                        <span class="visually-hidden">unread notifications</span>
                    </span>

                    <div class="notification-dropdown">
                        <!-- Desktop Dropdown Content -->
                        <div class="notification-header">
                            <span class="notification-title">Notification</span>
                            <span class="mark-all-read mark-all-read-btn">Mark all done</span>
                        </div>
                        <ul class="notification-list">
                            <?php if (empty($notifications)): ?>
                                <li class="notification-empty">No new notifications</li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <?php 
                                        $is_unread = ($notif['is_read'] == 0) ? ' unread' : '';
                                        $date = new DateTime($notif['created_at']);
                                        $dateStr = $date->format('n/j/Y h:i A'); 
                                    ?>
                                    <li class="notification-item<?= $is_unread ?>">
                                        <div class="notification-content"><?= htmlspecialchars($notif['message']) ?></div>
                                        <span class="notification-date"><?= $dateStr ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        <?php if ($has_more_notifs): ?>
                            <div class="notification-footer">
                                <a href="notifications.php" class="view-full-link">View full notification</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="contact.php" class="header-link d-flex align-items-center">
                    <i class="bi bi-headset fs-5 me-2"></i>
                    <span class="d-none d-sm-inline">Contact</span>
                </a>
                
                <div class="dropdown border-start ps-4 ms-2">
                    <a href="#" class="d-flex align-items-center link-dark text-decoration-none dropdown-toggle" id="headerUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="header-avatar bg-success text-white me-2 shadow-sm">
                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                        </div>
                        <span class="fw-bold d-none d-md-block"><?= htmlspecialchars($user['username']) ?></span>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="headerUserDropdown">
                        <li><h6 class="dropdown-header">Signed in as <b><?= htmlspecialchars($user['username']) ?></b></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person me-2 text-muted"></i> Profile
                            </a>
                        </li>
                    </ul>
                </div>

            <?php else: ?>
                <div class="d-flex gap-2">
                    <a href="login.php" class="btn btn-outline-success">Log In</a>
                    <a href="register.php" class="btn btn-success text-white">Sign Up</a>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Dropdown Toggle Logic
    const notifWrappers = document.querySelectorAll('.notification-wrapper');

    notifWrappers.forEach(wrapper => {
        wrapper.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const dropdown = this.querySelector('.notification-dropdown');
            const wasOpen = dropdown.classList.contains('show');

            document.querySelectorAll('.notification-dropdown').forEach(d => d.classList.remove('show'));

            if (!wasOpen) {
                dropdown.classList.add('show');
            }
        });
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.notification-dropdown').forEach(d => d.classList.remove('show'));
    });

    // 2. Mark All Read Logic
    const markButtons = document.querySelectorAll('.mark-all-read-btn');
    markButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent closing if you want to see the effect
            e.preventDefault();

            // A. Immediate UI Update (Critical Requirement)
            const badges = document.querySelectorAll('.notification-badge');
            badges.forEach(badge => badge.classList.add('d-none'));

            const unreadItems = document.querySelectorAll('.notification-item.unread');
            unreadItems.forEach(item => item.classList.remove('unread'));

            // B. Send Server Request
            fetch('fetch_notifications.php?action=mark_read')
                .then(response => {
                    if (!response.ok) console.error('Failed to mark notifications as read');
                })
                .catch(err => console.error('Error marking as read:', err));
        });
    });

    // 3. Badge Count Polling (Background update)
    function updateBadgeCount() {
        fetch('fetch_notifications.php?action=count')
            .then(response => {
                if (!response.ok) return;
                return response.json();
            })
            .then(data => {
                if (data && typeof data.count !== 'undefined') {
                    const count = parseInt(data.count);
                    const badgeText = count > 9 ? '9+' : count;
                    
                    const badges = document.querySelectorAll('.notification-badge');

                    badges.forEach(badge => {
                        if (count > 0) {
                            badge.classList.remove('d-none');
                            badge.innerHTML = `${badgeText} <span class="visually-hidden">unread notifications</span>`;
                        } else {
                            // Only hide if it's not already hidden (to prevent overriding the manual hide)
                            if (!badge.classList.contains('d-none')) {
                                badge.classList.add('d-none');
                            }
                        }
                    });
                }
            })
            .catch(err => console.error('Badge update failed:', err));
    }

    // Poll every 3 seconds instead of 1 to reduce load and conflict probability
    setInterval(updateBadgeCount, 3000);
});
</script>