<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . "/db.php";
$db = get_db();

$userSession = $_SESSION['user'];
$userId = $userSession['id'];

$successMsg = "";
$errorMsg = "";

$stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id");
$stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
$userData = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$userData) {
    session_destroy();
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $newUsername = trim($_POST['username']);

        if (empty($newUsername)) {
            $errorMsg = "Username cannot be empty.";
        } else {
            $check = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = :u AND id != :id");
            $check->bindValue(':u', $newUsername, SQLITE3_TEXT);
            $check->bindValue(':id', $userId, SQLITE3_INTEGER);
            $res = $check->execute()->fetchArray(SQLITE3_ASSOC);
            if ($res['count'] > 0) {
                $errorMsg = "Username already taken.";
            } else {
                $upd = $db->prepare("UPDATE users SET username = :u WHERE id = :id");
                $upd->bindValue(':u', $newUsername, SQLITE3_TEXT);
                $upd->bindValue(':id', $userId, SQLITE3_INTEGER);
                $upd->execute();
                
                $_SESSION['user']['username'] = $newUsername;
                $userData['username'] = $newUsername; 
                $successMsg = "Profile info updated successfully.";
            }
        }
    } elseif ($action === 'update_password') {
        $currentPwd = $_POST['current_password'];
        $newPwd = $_POST['new_password'];
        $confirmPwd = $_POST['confirm_password'];

        if (strlen($newPwd) < 6) {
            $errorMsg = "New password must be at least 6 characters.";
        } elseif ($newPwd !== $confirmPwd) {
            $errorMsg = "New passwords do not match.";
        } else {
            if (password_verify($currentPwd, $userData['password'])) {
                $hashed = password_hash($newPwd, PASSWORD_DEFAULT);
                $upd = $db->prepare("UPDATE users SET password = :p WHERE id = :id");
                $upd->bindValue(':p', $hashed, SQLITE3_TEXT);
                $upd->bindValue(':id', $userId, SQLITE3_INTEGER);
                $upd->execute();
                $successMsg = "Password updated successfully.";
            } else {
                $errorMsg = "Incorrect current password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ReviewHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Changed font to Poppins to match schedule.php -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../styles/profile.css">
</head>
<body>
    <?php require_once "header.php"; ?>

    <!-- Outer Wrapper matching Schedule Layout -->
    <div class="container-fluid">
        <div class="row">
            
            <!-- Sidebar Integration -->
            <div class="col-lg-2 d-none d-lg-block p-0 min-vh-100 border-end bg-light">
                <?php include 'nav.php'; ?>
                
            </div>

            <!-- Main Content Wrapper -->
            <!-- Added p-0 so the mobile navbar extends full width to the screen edges -->
            <div class="col-12 col-lg-10 p-0">
                
                <!-- Mobile Navigation (Visible only on lg-none) -->
                <div class="d-lg-none">
                    <?php include 'nav.php'; ?>
                </div>

                <!-- Original Profile Content Structure -->
                <div class="container py-5">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            
                            <?php if ($successMsg): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($successMsg) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($errorMsg): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div class="glass-card mb-4">
                                <div class="profile-header-bg"></div>
                                <div class="px-4 pb-4">
                                    <div class="profile-avatar-container text-center mb-3">
                                        <div class="profile-avatar rounded-circle d-flex align-items-center justify-content-center mx-auto">
                                            <?= strtoupper(substr($userData['username'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center mb-4">
                                        <h3 class="fw-bold text-dark mb-1"><?= htmlspecialchars($userData['username']) ?></h3>
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">
                                            <?= htmlspecialchars($userData['role_name']) ?>
                                        </span>
                                    </div>

                                    <form method="POST" class="mb-5">
                                        <input type="hidden" name="action" value="update_info">
                                        <h5 class="fw-bold text-secondary mb-3"><i class="bi bi-person-lines-fill me-2"></i>Personal Info</h5>
                                        
                                        <div class="mb-3">
                                            <!-- Updated label class to match schedule.php -->
                                            <label class="form-label text-muted small fw-bold">Username</label>
                                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($userData['username']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <!-- Updated label class to match schedule.php -->
                                            <label class="form-label text-muted small fw-bold">Email Address</label>
                                            <input type="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" disabled readonly>
                                            <div class="form-text"><i class="bi bi-lock-fill"></i> Email cannot be changed.</div>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" class="btn btn-success px-4">Save Changes</button>
                                        </div>
                                    </form>

                                    <hr class="my-4 text-muted">

                                    <form method="POST" id="passwordForm">
                                        <input type="hidden" name="action" value="update_password">
                                        <h5 class="fw-bold text-secondary mb-3"><i class="bi bi-shield-lock-fill me-2"></i>Security</h5>
                                        
                                        <div class="mb-3">
                                            <!-- Updated label class to match schedule.php -->
                                            <label class="form-label text-muted small fw-bold">Current Password</label>
                                            <input type="password" name="current_password" class="form-control" required placeholder="••••••••">
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <!-- Updated label class to match schedule.php -->
                                                <label class="form-label text-muted small fw-bold">New Password</label>
                                                <input type="password" name="new_password" class="form-control" required placeholder="Min. 6 characters">
                                            </div>
                                            <div class="col-md-6">
                                                <!-- Updated label class to match schedule.php -->
                                                <label class="form-label text-muted small fw-bold">Confirm New Password</label>
                                                <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password">
                                            </div>
                                        </div>
                                        <div class="mt-4 text-end">
                                            <button type="submit" class="btn btn-outline-danger px-4">Update Password</button>
                                        </div>
                                    </form>
                                </div>

                            </div>
                        </div>
                    </div>
                </div> 
                <!-- End Original Profile Content -->

            </div>
        </div>
    </div>

    <script src="../js/confirm.js"></script>

    <?php include "footer.php"; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>