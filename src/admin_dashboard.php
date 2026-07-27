<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: ../index.php?login=true");
    exit;
}
$currentUser = $_SESSION["user"];

require_once __DIR__ . "/db.php";
$db = get_db();

$action = isset($_GET["action"]) ? $_GET["action"] : "list";
$msg = isset($_GET["msg"]) ? $_GET["msg"] : "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "store") {
    $email = trim($_POST["email"] ?? "");
    $roleId = (int)($_POST["role_id"] ?? 0);
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && $roleId > 0) {
        try {
            $stmt = $db->prepare("INSERT INTO account (role_id, email) VALUES (?, ?)");
            $stmt->bindValue(1, $roleId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $email, SQLITE3_TEXT);
            $stmt->execute();
            
            header("Location: admin_dashboard.php?msg=Account+added+successfully");
            exit;
        } catch (Exception $e) {
            $error = "Error: Email might already exist.";
            $action = "create";
        }
    } else {
        $error = "Please fill in all fields (Email, Role).";
        $action = "create";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "update") {
    $id = (int)($_POST["id"] ?? 0);
    $newEmail = trim($_POST["email"] ?? "");
    $newRoleId = (int)($_POST["role_id"] ?? 0);

    if ($id > 0 && filter_var($newEmail, FILTER_VALIDATE_EMAIL) && $newRoleId > 0) {
        try {
            $oldEmailStmt = $db->prepare("SELECT email FROM account WHERE id = ?");
            $oldEmailStmt->bindValue(1, $id, SQLITE3_INTEGER);
            $oldRes = $oldEmailStmt->execute()->fetchArray(SQLITE3_ASSOC);
            $oldEmail = $oldRes ? $oldRes['email'] : null;

            $stmt = $db->prepare("UPDATE account SET email = ?, role_id = ? WHERE id = ?");
            $stmt->bindValue(1, $newEmail, SQLITE3_TEXT);
            $stmt->bindValue(2, $newRoleId, SQLITE3_INTEGER);
            $stmt->bindValue(3, $id, SQLITE3_INTEGER);
            $stmt->execute();

            if ($oldEmail && $oldEmail !== $newEmail) {
                $updateUserStmt = $db->prepare("UPDATE users SET email = ?, role_id = ? WHERE email = ?");
                $updateUserStmt->bindValue(1, $newEmail, SQLITE3_TEXT);
                $updateUserStmt->bindValue(2, $newRoleId, SQLITE3_INTEGER);
                $updateUserStmt->bindValue(3, $oldEmail, SQLITE3_TEXT);
                $updateUserStmt->execute();
            } else {
                $updateRoleOnly = $db->prepare("UPDATE users SET role_id = ? WHERE email = ?");
                $updateRoleOnly->bindValue(1, $newRoleId, SQLITE3_INTEGER);
                $updateRoleOnly->bindValue(2, $newEmail, SQLITE3_TEXT);
                $updateRoleOnly->execute();
            }

            header("Location: admin_dashboard.php?msg=Account+updated");
            exit;
        } catch (Exception $e) {
            $error = "Error updating account. Email might already be taken.";
            $action = "edit";
            $_GET["id"] = (string)$id;
        }
    } else {
        $error = "Please provide valid email and role.";
        $action = "edit";
        $_GET["id"] = (string)$id;
    }
}

$editData = null;
if ($action === "edit" && isset($_GET["id"])) {
    $id = (int)$_GET["id"];
    $stmt = $db->prepare("SELECT * FROM account WHERE id = ?");
    $stmt->bindValue(1, $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $editData = $result->fetchArray(SQLITE3_ASSOC);
    if (!$editData) {
        header("Location: admin_dashboard.php");
        exit;
    }
}

if ($action === "delete" && isset($_GET["id"])) {
    $id = (int)$_GET["id"];
    if ($id > 0) {
        $delStmt = $db->prepare("SELECT email FROM account WHERE id = ?");
        $delStmt->bindValue(1, $id, SQLITE3_INTEGER);
        $delRes = $delStmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        if ($delRes) {
            $emailToDelete = $delRes['email'];
            
            $stmt = $db->prepare("DELETE FROM account WHERE id = ?");
            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
            $stmt->execute();

            $userDel = $db->prepare("DELETE FROM users WHERE email = ?");
            $userDel->bindValue(1, $emailToDelete, SQLITE3_TEXT);
            $userDel->execute();
        }
    }
    header("Location: admin_dashboard.php?msg=Account+deleted");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | ReviewHub</title>
    <link rel="icon" type="image/x-icon" href="../img/logo.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/admin_dashboard.css">
</head>
<body>
    <div class="blob-bg">
        <div class="blob" style="top: -10%; left: -10%; width: 500px; height: 500px; background: var(--brand-light);"></div>
        <div class="blob" style="bottom: -10%; right: -10%; width: 600px; height: 600px; background: #fef3c7;"></div>
    </div>

    <nav class="navbar sticky-top py-3">
        <div class="container-fluid px-3 px-md-4 d-flex flex-nowrap align-items-center justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-2 m-0 p-0" href="admin_dashboard.php">
                <img src="../img/logo.png" alt="Logo" width="32" height="32" class="d-inline-block align-text-top">
                <span class="fw-bold text-dark d-none d-sm-inline-block">
                    ReviewHub 
                    <span class="badge bg-brand-light text-brand ms-2 rounded-pill fs-6">Admin</span>
                </span>
            </a>
            
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <div class="d-none d-md-block text-end">
                    <small class="text-muted d-block" style="line-height: 1;">Logged in as</small>
                    <span class="fw-bold text-dark" style="font-size: 0.9rem;">Administrator</span>
                </div>
                
                <div class="rounded-circle bg-brand-light text-brand d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3 d-flex align-items-center" style="height: 38px;">
                    <i class="fa-solid fa-right-from-bracket"></i> 
                    <span class="d-none d-sm-inline ms-2">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-md-4 py-4 flex-grow-1">
        <div class="row g-4 h-100">
            <div class="col-12 col-lg-3 col-xl-2">
                <div class="card sidebar-card h-100 bg-white shadow-sm">
                    <div class="card-body p-3">
                        <small class="text-muted fw-bold text-uppercase px-3 mb-2 d-block" style="font-size: 0.75rem;">Main Menu</small>
                        <nav class="nav flex-column sidebar-nav gap-1">
                            <a href="admin_dashboard.php" class="sidebar-link <?php echo ($action === 'list') ? 'active' : ''; ?>">
                                <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                            </a>
                            <a href="?action=create" class="sidebar-link <?php echo ($action === 'create') ? 'active' : ''; ?>">
                                <i class="fa-solid fa-user-plus me-2"></i> Add Account
                            </a>
                            <a href="?action=users_list" class="sidebar-link <?php echo ($action === 'users_list') ? 'active' : ''; ?>">
                                <i class="fa-solid fa-users me-2"></i> Users List
                            </a>
                        </nav>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-9 col-xl-10">
                <div class="fade-in-up">
                    <?php if ($msg): ?>
                        <div class="alert alert-success d-flex align-items-center rounded-3 shadow-sm border-0 mb-4" role="alert">
                            <i class="fa-solid fa-circle-check fs-4 me-2"></i>
                            <div><?php echo htmlspecialchars($msg); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-sm border-0 mb-4" role="alert">
                            <i class="fa-solid fa-circle-exclamation fs-4 me-2"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === "create"): 
                        $rolesResult = $db->query("SELECT * FROM roles");
                    ?>
                        <div class="card shadow-sm">
                            <div class="card-header bg-white border-bottom p-4">
                                <h4 class="mb-0 fw-bold text-dark">Add New Account</h4>
                                <p class="text-muted small mb-0">Create a new account with specific role access.</p>
                            </div>
                            <div class="card-body p-4">
                                <form method="post" action="?action=store" class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-secondary">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0"><i class="fa-regular fa-envelope text-muted"></i></span>
                                            <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="user@example.com" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-secondary">Assign Role</label>
                                        <select name="role_id" class="form-select" required>
                                            <option value="" disabled selected>Select an account role...</option>
                                            <?php while ($role = $rolesResult->fetchArray(SQLITE3_ASSOC)): ?>
                                                <option value="<?php echo $role['id']; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($role['name'])); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 mt-4 d-flex gap-2 flex-wrap">
                                        <button class="btn btn-brand px-4" type="submit">
                                            <i class="fa-solid fa-save me-2"></i>Create Account
                                        </button>
                                        <a class="btn btn-light rounded-pill px-4 text-secondary fw-bold" href="admin_dashboard.php">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    
                    <?php elseif ($action === "edit"):
                        $id = (int)($_GET["id"] ?? 0);
                        $userData = null;
                        if ($id > 0) {
                            $stmt = $db->prepare("SELECT * FROM account WHERE id = ?");
                            $stmt->bindValue(1, $id, SQLITE3_INTEGER);
                            $result = $stmt->execute();
                            $userData = $result->fetchArray(SQLITE3_ASSOC);
                        }
                        if (!$userData): ?>
                            <div class="text-center py-5">
                                <div class="text-muted mb-3"><i class="fa-regular fa-face-frown fs-1"></i></div>
                                <h3>Account not found</h3>
                                <a class="btn btn-brand mt-3" href="admin_dashboard.php">Return to Dashboard</a>
                            </div>
                        <?php else: 
                            $rolesResult = $db->query("SELECT * FROM roles");
                        ?>
                            <div class="card shadow-sm">
                                <div class="card-header bg-white border-bottom p-4">
                                    <h4 class="mb-0 fw-bold text-dark">Edit Account</h4>
                                    <p class="text-muted small mb-0">Update account information for ID #<?php echo (int)$userData["id"]; ?></p>
                                </div>
                                <div class="card-body p-4">
                                    <form method="post" action="?action=update" class="row g-3">
                                        <input type="hidden" name="id" value="<?php echo (int)$userData["id"]; ?>">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold small text-secondary">Email Address</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light border-end-0"><i class="fa-regular fa-envelope text-muted"></i></span>
                                                <input type="email" name="email" class="form-control border-start-0 ps-0" value="<?php echo htmlspecialchars($userData["email"]); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold small text-secondary">Assign Role</label>
                                            <select name="role_id" class="form-select" required>
                                                <?php while ($role = $rolesResult->fetchArray(SQLITE3_ASSOC)): ?>
                                                    <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $userData['role_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars(ucfirst($role['name'])); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-12 mt-4 d-flex gap-2 flex-wrap">
                                            <button class="btn btn-brand px-4" type="submit">
                                                <i class="fa-solid fa-check me-2"></i>Save Changes
                                            </button>
                                            <a class="btn btn-light rounded-pill px-4 text-secondary fw-bold" href="admin_dashboard.php">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($action === "users_list"): 
                        $realUsers = $db->query("SELECT * FROM users ORDER BY id DESC");
                    ?>
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white border-bottom p-4">
                                <h4 class="mb-0 fw-bold text-dark">System Users</h4>
                                <p class="text-muted small mb-0">List of users who have registered and have passwords.</p>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 text-nowrap">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="ps-4">ID</th>
                                                <th>Email</th>
                                                <th>Password</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($ru = $realUsers->fetchArray(SQLITE3_ASSOC)): ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-muted">#<?php echo (int)$ru['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($ru['email']); ?></td>
                                                    <td class="text-muted small font-monospace"><?php echo htmlspecialchars($ru['password']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h4 class="mb-0 fw-bold text-dark">Registered Accounts</h4>
                                    <p class="text-muted small mb-0">Manage system access and accounts.</p>
                                </div>
                                <a class="btn btn-brand btn-sm shadow-sm" href="?action=create">
                                    <i class="fa-solid fa-plus me-2"></i>Add Account
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <?php
                                $result = $db->query("SELECT u.*, r.name as role_name FROM account u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC");
                                $users = [];
                                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                                    $users[] = $row;
                                }
                                ?>
                                <?php if (empty($users)): ?>
                                    <div class="text-center py-5">
                                        <div class="mb-3 text-muted opacity-25">
                                            <i class="fa-solid fa-users-slash fa-4x"></i>
                                        </div>
                                        <h5 class="text-muted">No accounts found</h5>
                                        <p class="text-muted small">Get started by adding a new account.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0 text-nowrap">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="ps-4" style="width: 50px;">ID</th>
                                                    <th>Account</th>
                                                    <th>Role</th>
                                                    <th class="text-end pe-4">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($users as $u): ?>
                                                    <tr>
                                                        <td class="ps-4 fw-bold text-muted">#<?php echo (int)$u["id"]; ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="rounded-circle bg-light text-secondary d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 36px; height: 36px;">
                                                                    <i class="fa-solid fa-user small"></i>
                                                                </div>
                                                                <div class="text-truncate" style="max-width: 200px;">
                                                                    <div class="fw-bold text-dark text-truncate"><?php echo htmlspecialchars($u["email"]); ?></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                                $role = strtolower($u["role_name"] ?? '');
                                                                $badgeClass = $role === 'admin' ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success';
                                                            ?>
                                                            <span class="badge rounded-pill <?php echo $badgeClass; ?> px-3 py-2 border-0 fw-bold">
                                                                <?php echo htmlspecialchars(ucfirst($u["role_name"] ?? 'N/A')); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-4">
                                                            <div class="d-flex justify-content-end gap-1">
                                                                <a href="?action=edit&id=<?php echo (int)$u["id"]; ?>" class="btn btn-light btn-sm rounded-circle text-primary" title="Edit">
                                                                    <i class="fa-solid fa-pen"></i>
                                                                </a>
                                                                <a href="?action=delete&id=<?php echo (int)$u["id"]; ?>" onclick="return confirm('Delete this account?');" class="btn btn-light btn-sm rounded-circle text-danger" title="Delete">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>