<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: index.php?login=true");
    exit;
}

require_once "db.php";

date_default_timezone_set('Asia/Manila');

$db = get_db();
$user = $_SESSION['user'];
$user_id = $user['id'];

function getBadgeStyle($type) {
    switch ($type) {
        case 'Exam': return 'background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7;'; 
        case 'Quiz': return 'background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5;'; 
        case 'Project': return 'background-color: #cff4fc; color: #055160; border: 1px solid #b6effb;'; 
        case 'Assignment': return 'background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc;'; 
        case 'Activities': return 'background-color: #e2d9f3; color: #5e35b1; border: 1px solid #d1c4e9;'; 
        default: return 'background-color: #e9ecef; color: #6c757d; border: 1px solid #dee2e6;'; 
    }
}

function renderSingleActivity($act) {
    $badgeStyle = getBadgeStyle($act['type']);
    $isDone = $act['is_completed'];
    
    $timestamp = strtotime($act['due_date']);
    $month = date('M', $timestamp); 
    $day = date('d', $timestamp);   
    $time = date('h:i A', $timestamp);
    
    $textClass = $isDone ? 'text-decoration-line-through text-muted' : 'text-dark';
    $bgClass = $isDone ? 'bg-light' : '';
    $grayscaleClass = $isDone ? 'opacity-50 grayscale' : '';
    
    // Dropdown Logic
    $toggleText = $isDone ? 'Mark as Undone' : 'Mark as Done';
    $toggleIcon = $isDone ? 'fa-undo' : 'fa-check';
    
    $title = htmlspecialchars($act['title']);
    $type = htmlspecialchars($act['type']);
    $actId = $act['id'];
    
    // Format date for the edit modal input (YYYY-MM-DDTHH:MM)
    $isoDate = date('Y-m-d\TH:i', $timestamp);

    // --- URGENCY BADGE LOGIC ---
    $urgencyBadge = '';
    
    // Only calculate urgency for pending items to avoid visual clutter on completed tasks
    if (!$isDone) {
        $nowDate = new DateTime('now');
        $nowDate->setTime(0, 0, 0); // Reset time to midnight for accurate date comparison

        $dueDate = new DateTime($act['due_date']);
        $dueDate->setTime(0, 0, 0); // Reset time to midnight

        if ($dueDate < $nowDate) {
            // Due date is in the past
            $urgencyBadge = "<span class='badge bg-danger ms-1' style='font-size: 0.7rem;'><i class='fas fa-exclamation-circle me-1'></i>Overdue</span>";
        } elseif ($dueDate == $nowDate) {
            // Due date is strictly today
            $urgencyBadge = "<span class='badge bg-warning text-dark ms-1' style='font-size: 0.7rem;'><i class='fas fa-bell me-1'></i>Today</span>";
        } else {
            // Check if tomorrow
            $tomorrow = clone $nowDate;
            $tomorrow->modify('+1 day');
            
            if ($dueDate == $tomorrow) {
                $urgencyBadge = "<span class='badge bg-info text-dark ms-1' style='font-size: 0.7rem;'><i class='fas fa-calendar-day me-1'></i>Tomorrow</span>";
            } else {
                // Any future date beyond tomorrow
                $urgencyBadge = "<span class='badge bg-primary ms-1' style='font-size: 0.7rem;'><i class='fas fa-spinner me-1'></i>On Going</span>";
            }
        }
    }
    // ----------------------------

    return "
    <div class='list-group-item px-3 px-lg-4 py-3 border-light {$bgClass}' id='activity-row-{$actId}'>
        <div class='d-flex align-items-center justify-content-between'>
            
            <div class='d-flex align-items-center flex-grow-1 overflow-hidden'>
                <div class='date-box flex-shrink-0 me-3 {$grayscaleClass}' id='date-box-{$actId}'>
                    <span>" . strtoupper($month) . "</span>
                    <span>{$day}</span>
                </div>
                
                <div class='overflow-hidden'>
                    <div class='d-flex flex-wrap align-items-center gap-2 mb-1'>
                        <h6 class='mb-0 fw-bold text-truncate {$textClass}' style='max-width: 100%;' id='title-{$actId}'>
                            {$title}
                        </h6>
                        <span class='badge rounded-pill fw-normal' style='{$badgeStyle} font-size: 0.75rem;'>
                            {$type}
                        </span>
                        {$urgencyBadge} 
                    </div>
                    
                    <div class='text-muted small'>
                        <i class='far fa-clock me-1'></i> {$time}
                        <span class='ms-2 text-success fw-bold " . ($isDone ? "" : "d-none") . "' id='completed-badge-{$actId}'><i class='fas fa-check-circle'></i> Completed</span>
                    </div>
                </div>
            </div>

            <!-- Three Dots Dropdown -->
            <div class='ms-3 ps-3 border-start'>
                <div class='dropdown'>
                    <button class='btn btn-link text-secondary p-0 border-0' type='button' data-bs-toggle='dropdown' aria-expanded='false'>
                        <i class='fas fa-ellipsis-v fa-lg'></i>
                    </button>
                    <ul class='dropdown-menu dropdown-menu-end shadow border-0'>
                        <li>
                            <a class='dropdown-item edit-activity-btn' href='#' 
                               data-id='{$actId}' 
                               data-title='{$title}' 
                               data-type='{$type}' 
                               data-due-date='{$isoDate}'>
                                <i class='fas fa-edit me-2 text-primary'></i> Edit
                            </a>
                        </li>
                        <li>
                            <a class='dropdown-item delete-activity-btn' href='#' data-id='{$actId}'>
                                <i class='fas fa-trash-alt me-2 text-danger'></i> Delete
                            </a>
                        </li>
                        <li><hr class='dropdown-divider'></li>
                        <li>
                            <a class='dropdown-item toggle-status-btn' href='#' data-id='{$actId}'>
                                <i class='fas {$toggleIcon} me-2 text-success'></i> <span class='toggle-text'>{$toggleText}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = isset($_POST['ajax']);
    $response = ['success' => false];

    // ADD ACTIVITY
    if (isset($_POST['add_activity'])) {
        $title = trim($_POST['title']);
        $type = trim($_POST['type']);
        $due_date = $_POST['due_date'];
        $status = 0; 
    
        if (!empty($title) && !empty($due_date)) {
            $stmt = $db->prepare("INSERT INTO student_activities (user_id, title, type, due_date, is_completed) VALUES (:uid, :title, :type, :due_date, :status)");
            $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':type', $type, SQLITE3_TEXT);
            $stmt->bindValue(':due_date', $due_date, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_INTEGER);
            $stmt->execute();
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                header("Location: user_tracker.php");
                exit;
            }
        }
    }

    // EDIT ACTIVITY
    if (isset($_POST['edit_activity'])) {
        $id = $_POST['activity_id'];
        $title = trim($_POST['title']);
        $type = trim($_POST['type']);
        $due_date = $_POST['due_date'];

        if (!empty($title) && !empty($due_date) && !empty($id)) {
            $stmt = $db->prepare("UPDATE student_activities SET title = :title, type = :type, due_date = :due_date WHERE id = :id AND user_id = :uid");
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':type', $type, SQLITE3_TEXT);
            $stmt->bindValue(':due_date', $due_date, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
            $stmt->execute();

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                header("Location: user_tracker.php");
                exit;
            }
        }
    }
    
    // TOGGLE STATUS
    if (isset($_POST['toggle_activity_id'])) {
        $id = $_POST['toggle_activity_id'];
        $current = $db->querySingle("SELECT is_completed FROM student_activities WHERE id = $id AND user_id = $user_id");
        $new_status = ($current == 1) ? 0 : 1;
    
        $stmt = $db->prepare("UPDATE student_activities SET is_completed = :status WHERE id = :id AND user_id = :uid");
        $stmt->bindValue(':status', $new_status, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'new_status' => $new_status]);
            exit;
        } else {
            header("Location: user_tracker.php");
            exit;
        }
    }

    // DELETE SINGLE
    if (isset($_POST['delete_activity_id'])) {
        $id = $_POST['delete_activity_id'];
        $stmt = $db->prepare("DELETE FROM student_activities WHERE id = :id AND user_id = :uid");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
        $stmt->execute();

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } else {
            header("Location: user_tracker.php");
            exit;
        }
    }
    
    // CLEAR COMPLETED
    if (isset($_POST['clear_completed'])) {
        $stmt = $db->prepare("DELETE FROM student_activities WHERE user_id = :uid AND is_completed = 1");
        $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        header("Location: user_tracker.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. EDIT ACTIVITY (Refactored for JSON response & Correct Table)
    if (isset($_POST['edit_activity'])) {
        header('Content-Type: application/json');
        
        try {
            $id = $_POST['activity_id'];
            $title = trim($_POST['title']);
            $type = $_POST['type'];
            $due_date = $_POST['due_date'];

            // Correct table: student_activities
            // We update title, type, and due_date to ensure DB triggers fire
            $stmt = $db->prepare("UPDATE student_activities SET title = :title, type = :type, due_date = :due_date WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':type', $type, SQLITE3_TEXT);
            $stmt->bindValue(':due_date', $due_date, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Activity updated successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database update failed']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 2. DELETE ACTIVITY (Refactored for JSON response & Correct Table)
    if (isset($_POST['delete_activity'])) {
        header('Content-Type: application/json');

        try {
            $id = $_POST['activity_id'];

            // Correct table: student_activities
            $stmt = $db->prepare("DELETE FROM student_activities WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Activity deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Delete failed']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 3. CREATE ACTIVITY (Standard Redirect Logic - Preserved)
    if (isset($_POST['add_activity'])) {
        $title = trim($_POST['title']);
        $type = $_POST['type'];
        $due_date = $_POST['due_date'];
        
        // Default status for new items
        $status = 'Pending'; 

        $stmt = $db->prepare("INSERT INTO student_activities (user_id, title, type, due_date, status) VALUES (:user_id, :title, :type, :due_date, :status)");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':due_date', $due_date, SQLITE3_TEXT);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        
        $stmt->execute();
        
        header("Location: user_tracker.php?success=added");
        exit;
    }
}


$query = "SELECT * FROM student_activities WHERE user_id = :user_id ORDER BY due_date ASC";
$stmt = $db->prepare($query);
$stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();

$filter = $_GET['filter'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 5; 
$offset = ($page - 1) * $limit;

$whereClause = "WHERE user_id = :uid";
if ($filter === 'pending') {
    $whereClause .= " AND is_completed = 0";
} elseif ($filter === 'completed') {
    $whereClause .= " AND is_completed = 1";
}

$countSql = "SELECT COUNT(*) as count FROM student_activities $whereClause";
$stmtCount = $db->prepare($countSql);
$stmtCount->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$countResult = $stmtCount->execute()->fetchArray(SQLITE3_ASSOC);
$totalItems = $countResult['count'];
$totalPages = ceil($totalItems / $limit);

$sql = "SELECT * FROM student_activities $whereClause ORDER BY due_date ASC LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
$stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
$result = $stmt->execute();

$activities = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $activities[] = $row;
}

function renderContent($activities, $totalPages, $currentPage, $filter) {
    if (empty($activities)) {
        echo '
        <div class="text-center py-5" id="no-activities-msg">
            <div class="mb-3 text-muted opacity-50"><i class="fas fa-clipboard-list fa-3x"></i></div>
            <h6 class="text-muted">No activities found.</h6>
        </div>
        <div class="list-group list-group-flush" id="activity-list-group"></div>';
    } else {
        echo '<div class="list-group list-group-flush" id="activity-list-group">';
        foreach ($activities as $act) {
            echo renderSingleActivity($act);
        }
        echo '</div>';
    }

    if ($totalPages > 1) {
        echo '<div class="d-flex justify-content-center py-3 border-top">';
        echo '<div class="btn-group" role="group">';
        
        // Prev
        if ($currentPage > 1) {
            $prevPage = $currentPage - 1;
            echo "<a href='?filter={$filter}&page={$prevPage}' class='btn btn-outline-secondary btn-sm pagination-btn' data-page='{$prevPage}'><i class='fas fa-chevron-left'></i></a>";
        } else {
            echo "<button class='btn btn-outline-secondary btn-sm disabled'><i class='fas fa-chevron-left'></i></button>";
        }

        // Dynamic Range (3 buttons)
        $range = 3; 
        $startPage = max(1, $currentPage - 1);
        $endPage = min($totalPages, $startPage + $range - 1);
        
        if ($endPage === $totalPages) {
            $startPage = max(1, $endPage - $range + 1);
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $isActive = ($i === $currentPage && $currentPage !== $totalPages);
            $activeClass = $isActive ? 'active bg-success text-white border-success' : '';
            echo "<a href='?filter={$filter}&page={$i}' class='btn btn-outline-secondary btn-sm pagination-btn {$activeClass}' data-page='{$i}'>{$i}</a>";
        }

        // Next
        if ($currentPage < $totalPages) {
            $nextPage = $currentPage + 1;
            echo "<a href='?filter={$filter}&page={$nextPage}' class='btn btn-outline-secondary btn-sm pagination-btn' data-page='{$nextPage}'><i class='fas fa-chevron-right'></i></a>";
        } else {
            echo "<button class='btn btn-outline-secondary btn-sm disabled'><i class='fas fa-chevron-right'></i></button>";
        }
        
        echo '</div>';
        echo '</div>';
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    renderContent($activities, $totalPages, $page, $filter);
    exit; 
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Tracker - ReviewHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../styles/schedule.css">
    <link rel="stylesheet" href="../styles/nav.css">
    <link rel="stylesheet" href="../styles/user_tracker.css">
</head>
<body>
    <?php require_once "header.php"; ?>
    <?php include 'nav.php'; ?>

    <main class="main-content">
        <div class="container-xl p-3 p-lg-4">
            
            <div class="d-flex justify-content-between align-items-center mb-3 mb-lg-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1 fs-3 fs-lg-2">Activity Tracker</h2>
                    <p class="text-muted mb-0 small">Track your assignments, quizzes, and projects.</p>
                </div>
                <button class="btn btn-success rounded-pill px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                    <i class="fas fa-plus me-2"></i> New Activity
                </button>
            </div>

            <div class="row g-3 g-lg-4 justify-content-center">
                <div class="col-lg-10">
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        
                        <div class="card-header bg-white border-0 py-3 px-3 px-lg-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <h5 class="fw-bold mb-0">Tasks</h5>
                                <span class="badge bg-success bg-opacity-10 text-success" id="total-counter"><?= $totalItems ?> Total</span>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <div class="btn-group shadow-sm rounded-pill" role="group">
                                    <a href="?filter=all" class="btn btn-sm btn-outline-success px-3 filter-btn <?= $filter == 'all' ? 'active' : '' ?>" data-filter="all">All</a>
                                    <a href="?filter=pending" class="btn btn-sm btn-outline-success px-3 filter-btn <?= $filter == 'pending' ? 'active' : '' ?>" data-filter="pending">Pending</a>
                                    <a href="?filter=completed" class="btn btn-sm btn-outline-success px-3 filter-btn <?= $filter == 'completed' ? 'active' : '' ?>" data-filter="completed">Done</a>
                                </div>
                                
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete all completed tasks? This cannot be undone.');">
                                    <input type="hidden" name="clear_completed" value="1">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm" title="Delete All Completed Tasks">
                                        <i class="fas fa-trash-alt"></i> <span class="d-none d-sm-inline">Clear Done</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card-body p-0" id="activity-list">
                            <?php renderContent($activities, $totalPages, $page, $filter); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div> 
    </main> 

    <!-- Add Activity Modal -->
    <div class="modal fade" id="addActivityModal" tabindex="-1" aria-labelledby="addActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold" id="addActivityModalLabel">Add New Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addActivityForm">
                        <input type="hidden" name="add_activity" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TITLE</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Math Final Project" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TYPE</label>
                            <select name="type" class="form-select" required>
                                <option value="Assignment">Assignment</option>
                                <option value="Quiz">Quiz</option>
                                <option value="Project">Project</option>
                                <option value="Exam">Exam</option>
                                <option value="Activities">Activities</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">DUE DATE</label>
                            <input type="datetime-local" name="due_date" class="form-control" required>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success fw-bold px-4">
                                Create Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Activity Modal -->
    <div class="modal fade" id="editActivityModal" tabindex="-1" aria-labelledby="editActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold" id="editActivityModalLabel">Edit Activity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editActivityForm">
                        <input type="hidden" name="edit_activity" value="1">
                        <input type="hidden" name="activity_id" id="edit_activity_id">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TITLE</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TYPE</label>
                            <select name="type" id="edit_type" class="form-select" required>
                                <option value="Assignment">Assignment</option>
                                <option value="Quiz">Quiz</option>
                                <option value="Project">Project</option>
                                <option value="Exam">Exam</option>
                                <option value="Activities">Activities</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">DUE DATE</label>
                            <input type="datetime-local" name="due_date" id="edit_due_date" class="form-control" required>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary fw-bold px-4">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/tracker.js"></script>
</body>
</html>