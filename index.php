<?php
session_start();
require_once __DIR__ . "/src/db.php";

$loginError = "";
$registerError = "";
$registerSuccess = "";

$openLoginModal = false;
$openRegisterModal = false;

if (isset($_GET["registered"])) {
    $registerSuccess = "Registration successful! Please login.";
    $openLoginModal = true;
}

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['action_type']) && $_POST['action_type'] === 'login') {

        $input = strtolower(trim($_POST["username_or_email"]));
        $password = trim($_POST["password"]);

        if (empty($input) || empty($password)) {
            $loginError = "Invalid email or password.";
            $openLoginModal = true;
        } else {
            if (function_exists('get_db')) {
                $db = get_db();
                $sql = "SELECT u.*, r.name as role_name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.id 
                        WHERE u.email = :input OR u.username = :input";
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':input', $input, SQLITE3_TEXT);

                $result = $stmt->execute();
                $user = $result->fetchArray(SQLITE3_ASSOC);

                if ($user && $password === $user["password"]) {
                    $_SESSION["user"] = [
                        "id"        => $user["id"],
                        "email"     => $user["email"],
                        "username"  => $user["username"],
                        "role"      => $user["role_name"], 
                        "role_id"   => $user["role_id"]
                    ];
                    
                    if ($user["role_name"] === "admin") {
                        header("Location: src/admin_dashboard.php");
                        exit;
                    } else {
                        header("Location: src/users_dashboard.php");
                        exit;
                    }
                } else {
                    $loginError = "Invalid credentials.";
                    $openLoginModal = true;
                }
            }
        }
    } elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'register') {
        
        $username = trim($_POST["username"] ?? "");
        $email = strtolower(trim($_POST["email"] ?? ""));
        $password = trim($_POST["password"] ?? "");

        if (empty($username) || empty($email) || empty($password)) {
            $registerError = "All fields are required.";
            $openRegisterModal = true;
        } else if (function_exists('get_db')) {
            $db = get_db();

            // Check if email or username already exists
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE email = :email OR username = :user");
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':user', $username, SQLITE3_TEXT);
            $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

            if ($res['count'] > 0) {
                $registerError = "Username or Email is already taken.";
                $openRegisterModal = true;
            } else {
                $countAll = $db->querySingle("SELECT COUNT(*) FROM users");

                if ($countAll == 0) {
                    // First user is Admin
                    $roleId = $db->querySingle("SELECT id FROM roles WHERE name='admin'");
                    
                    if (!$roleId) {
                        $registerError = "System error: Roles not defined.";
                        $openRegisterModal = true;
                    } else {
                        $stmtIns = $db->prepare("INSERT INTO users (role_id, username, email, password) VALUES (:role, :user, :email, :pass)");
                        $stmtIns->bindValue(':role', $roleId, SQLITE3_INTEGER);
                        $stmtIns->bindValue(':user', $username, SQLITE3_TEXT);
                        $stmtIns->bindValue(':email', $email, SQLITE3_TEXT);
                        $stmtIns->bindValue(':pass', $password, SQLITE3_TEXT);
                        $stmtIns->execute();

                        header("Location: index.php?registered=true");
                        exit;
                    }

                } else {
                    // Check allow list (account table)
                    $stmtCheck = $db->prepare("SELECT role_id FROM account WHERE email = :email");
                    $stmtCheck->bindValue(':email', $email, SQLITE3_TEXT);
                    $accountRes = $stmtCheck->execute()->fetchArray(SQLITE3_ASSOC);

                    if ($accountRes) {
                        $roleId = $accountRes['role_id'];
                        
                        $stmtIns = $db->prepare("INSERT INTO users (role_id, username, email, password) VALUES (:role, :user, :email, :pass)");
                        $stmtIns->bindValue(':role', $roleId, SQLITE3_INTEGER);
                        $stmtIns->bindValue(':user', $username, SQLITE3_TEXT);
                        $stmtIns->bindValue(':email', $email, SQLITE3_TEXT);
                        $stmtIns->bindValue(':pass', $password, SQLITE3_TEXT);
                        $stmtIns->execute();

                        header("Location: index.php?registered=true");
                        exit;
                    } else {
                        $registerError = "This email is not authorized to register.";
                        $openRegisterModal = true;
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Student Review Hub</title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles/index.css"> 
</head>
<body class="position-relative">

    <div class="blob-shape blob-1"></div>
    <div class="blob-shape blob-2"></div>

    <!-- Reduced padding from py-md-4 to py-md-2 for compaction -->
    <nav class="navbar py-2 py-md-3 w-100 flex-shrink-0" style="z-index: 10;">
        <div class="container-xl px-2 px-md-4 d-flex justify-content-between align-items-center flex-nowrap">
            <!-- Brand -->
            <a href="#" class="navbar-brand d-flex align-items-center gap-2">
                <img src="img/logo.png" alt="ReviewHub Logo" class="logo-img">
                <!-- Hide text on extra small screens to save space -->
                <span class="fs-5 fs-md-3 fw-bold text-brand d-none d-sm-block" style="letter-spacing: -0.5px;">ReviewHub</span>
            </a>
            
            <!-- Action Buttons (Always Visible) -->
            <div class="d-flex gap-1 gap-md-2 ms-auto align-items-center">
                <button class="btn text-brand fw-semibold hover-bg-brand-light rounded-pill btn-mobile-tight text-nowrap" data-bs-toggle="modal" data-bs-target="#loginModal">Log In</button>
                <button class="btn btn-brand btn-mobile-tight text-nowrap" data-bs-toggle="modal" data-bs-target="#registerModal">Get Started</button>
            </div>
        </div>
    </nav>

    <header class="container-xl d-flex align-items-center flex-grow-1 px-4 position-relative" style="z-index: 0;">
        <!-- Reduced gutter from gy-5 to gy-3 to fit fixed screen better -->
        <div class="row align-items-center gy-3">
            <div class="col-md-6">
                <div class="d-inline-flex align-items-center px-3 py-1 bg-success bg-opacity-10 text-success rounded-pill fw-bold text-uppercase small mb-2">
                    <i class="fa-solid fa-star me-2"></i> For Students, By Students
                </div>
                <h1 class="display-5 fw-bold text-dark mb-3 lh-tight">
                    Unlock Your Potential Through <span class="text-brand">Feedback</span>.
                </h1>
                <p class="lead text-secondary mb-4 fs-6">
                    Join a community dedicated to growth. Share your work, get constructive reviews, and build the confidence you need to excel in your studies.
                </p>
                <div class="d-flex flex-column flex-sm-row gap-3 mt-2">
                    <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#registerModal">
                        Start Reviewing Now
                    </button>
                </div>
            </div>

            <div class="col-md-6 d-none d-md-block position-relative">
                <div class="position-absolute top-0 start-0 w-100 h-100 bg-brand-light rounded-circle opacity-25" style="transform: rotate(3deg) scale(0.9);"></div>
                <div class="card border-0 shadow-lg p-3 position-relative bg-white rounded-4" style="transform: rotate(-2deg);">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-4">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-brand-accent" style="width: 40px; height: 40px; color: #b45309;">
                                <i class="fa-solid fa-pen-nib text-brand"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Reviewer</h6>
                                <small class="text-muted" style="font-size: 0.75rem;">Uploaded 2 hours ago</small>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3 bg-success bg-opacity-10 p-3 rounded-3">
                                <i class="fa-solid fa-check-circle text-success mt-1"></i>
                                <div>
                                    <p class="small fw-bold text-success mb-0">Great argument structure!</p>
                                    <p class="small text-success mb-0" style="font-size: 0.75rem;">Maybe expand on the third point a bit more?</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-3 bg-primary bg-opacity-10 p-3 rounded-3">
                                <i class="fa-solid fa-lightbulb text-primary mt-1"></i>
                                <div>
                                    <p class="small fw-bold text-primary mb-0">Insightful conclusion.</p>
                                    <p class="small text-primary mb-0" style="font-size: 0.75rem;">This really ties everything together perfectly.</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-between align-items-center">
                            <div class="small fw-bold text-muted text-uppercase">Feedback Score</div>
                            <div class="text-warning small">
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star"></i>
                                <i class="fa-solid fa-star-half-stroke"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-brand-light text-brand mb-3" style="width: 48px; height: 48px;">
                        <i class="fa-solid fa-right-to-bracket fs-5"></i>
                    </div>
                    <h2 class="h3 fw-bold text-dark">Welcome Back!</h2>
                    <p class="text-muted small">Ready to continue your learning journey?</p>
                </div>

                <?php if ($loginError): ?>
                    <div class="alert alert-danger d-flex align-items-center p-2 mb-3 small" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <?php echo htmlspecialchars($loginError); ?>
                    </div>
                <?php endif; ?>

                <?php if ($registerSuccess): ?>
                    <div class="alert alert-success d-flex align-items-center p-2 mb-3 small" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($registerSuccess); ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="d-flex flex-column gap-3">
                    <input type="hidden" name="action_type" value="login">
                    <div>
                        <label class="form-label small fw-bold text-secondary mb-1">Username or Email</label>
                        <input type="text" name="username_or_email" required 
                            class="form-control"
                            placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="form-label small fw-bold text-secondary mb-1">Password</label>
                        <input type="password" name="password" required 
                            class="form-control"
                            placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn btn-brand w-100 shadow mt-2">
                        Log In
                    </button>
                </form>
                <div class="mt-4 text-center small text-muted">
                    Don't have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" class="text-brand fw-bold text-decoration-none">Sign up</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success mb-3" style="width: 48px; height: 48px;">
                        <i class="fa-solid fa-user-plus fs-5"></i>
                    </div>
                    <h2 class="h3 fw-bold text-dark">Join the Community</h2>
                    <p class="text-muted small">Create an account to start reviewing.</p>
                </div>

                <?php if ($registerError): ?>
                    <div class="alert alert-danger d-flex align-items-center p-2 mb-3 small" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <?php echo htmlspecialchars($registerError); ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="d-flex flex-column gap-3">
                    <input type="hidden" name="action_type" value="register">
                    <div>
                        <label class="form-label small fw-bold text-secondary mb-1">Username</label>
                        <input type="text" name="username" required 
                            class="form-control"
                            placeholder="Create a username">
                    </div>
                    <div>
                        <label class="form-label small fw-bold text-secondary mb-1">Email Address</label>
                        <input type="email" name="email" required 
                            class="form-control"
                            placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="form-label small fw-bold text-secondary mb-1">Password</label>
                        <input type="password" name="password" required 
                            class="form-control"
                            placeholder="Create a strong password">
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold shadow mt-2">
                        Create Account
                    </button>
                </form>
                <div class="mt-4 text-center small text-muted">
                    Already have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="text-brand fw-bold text-decoration-none">Log in</a>
                </div>
            </div>
        </div>
    </div>
    <?php include "src/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($openLoginModal): ?>
                var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            <?php endif; ?>
            
            <?php if ($openRegisterModal): ?>
                var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
                registerModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>