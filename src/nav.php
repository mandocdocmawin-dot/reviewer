<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['user']);
$user = $_SESSION['user'] ?? null;
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- MOBILE BOTTOM NAVIGATION -->
<?php if ($is_logged_in): ?>
    <div class="d-lg-none">
        <nav class="floating-nav">
            
            <a href="users_dashboard.php" class="floating-nav-link <?= $current_page == 'users_dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i>
                <span class="floating-nav-label">Dashboard</span>
            </a>

            <a href="user_tracker.php" class="floating-nav-link <?= $current_page == 'user_tracker.php' ? 'active' : '' ?>">
                <i class="bi bi-list-task"></i>
                <span class="floating-nav-label">Tracker</span>
            </a>

            <a href="create_review.php" class="floating-nav-link <?= $current_page == 'create_review.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle-fill"></i>
                <span class="floating-nav-label">Create</span>
            </a>

            <a href="schedule.php" class="floating-nav-link <?= $current_page == 'schedule.php' ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i>
                <span class="floating-nav-label">Schedule</span>
            </a>

            <a href="profile.php" class="floating-nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>">
                <?php if($current_page == 'profile.php'): ?>
                     <i class="bi bi-person-fill"></i>
                <?php else: ?>
                     <i class="bi bi-person"></i>
                <?php endif; ?>
                <span class="floating-nav-label">Profile</span>
            </a>
            
        </nav>
    </div>
<?php endif; ?>

<!-- DESKTOP SIDEBAR (Unchanged) -->
<div class="d-none d-lg-block bg-white border-end" id="sidebarMenu">
    <div class="d-flex flex-column h-100 p-3">
        <a href="<?= $is_logged_in ? 'users_dashboard.php' : '../index.php' ?>" class="d-flex align-items-center mb-4 mb-md-0 me-md-auto link-dark text-decoration-none px-2 mt-2">
            <i class="bi bi-book-half text-success fs-3 me-2"></i>
            <span class="fs-4 fw-bold">ReviewHub</span>
        </a>
        
        <hr class="my-3">
        
        <ul class="nav nav-pills flex-column mb-auto">
            <?php if ($is_logged_in): ?>
                <li class="nav-item mb-1">
                    <a href="users_dashboard.php" class="nav-link <?= $current_page == 'users_dashboard.php' ? 'active' : 'link-dark' ?>" aria-current="page">
                        <i class="bi bi-grid-fill me-2"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="create_review.php" class="nav-link <?= $current_page == 'create_review.php' ? 'active' : 'link-dark' ?>">
                        <i class="bi bi-plus-circle-fill me-2"></i>
                        Create Review
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="user_tracker.php" class="nav-link <?= $current_page == 'user_tracker.php' ? 'active' : 'link-dark' ?>">
                        <i class="bi bi-list-task me-2"></i>
                        Tracker
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="schedule.php" class="nav-link <?= $current_page == 'schedule.php' ? 'active' : 'link-dark' ?>">
                        <i class="bi bi-calendar3 me-2"></i>
                        Schedule
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <hr>
        
        <div class="dropdown">
            <?php if ($is_logged_in): ?>
                <a href="#" class="d-flex align-items-center link-dark text-decoration-none dropdown-toggle px-2 py-1 rounded hover-bg" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    </div>
                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                </a>
                <ul class="dropdown-menu text-small shadow" aria-labelledby="dropdownUser">
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                </ul>
            <?php else: ?>
                <a href="../index.php" class="btn btn-outline-success w-100">Log In</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../styles/nav.css">