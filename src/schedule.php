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

$message = '';
$error = '';

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => 'Invalid request'];
    
    if (isset($_POST['action']) && $_POST['action'] === 'add_schedule') {
        $title = trim($_POST['title']);
        $day = $_POST['day']; 
        $time = $_POST['time'];
        $description = trim($_POST['description']);
        $status = $_POST['status'] ?? 'Upcoming';

        if (!empty($title) && !empty($day) && !empty($time)) {
            $stmt = $db->prepare("INSERT INTO schedules (user_id, title, day, time, description, status) VALUES (:user_id, :title, :day, :time, :description, :status)");
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':day', $day, SQLITE3_TEXT);
            $stmt->bindValue(':time', $time, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);

            if ($stmt->execute()) {
                if (is_ajax_request()) {
                    echo json_encode(['status' => 'success', 'message' => 'Schedule added successfully']);
                    exit;
                }
                $message = "Schedule added successfully!";
            } else {
                if (is_ajax_request()) {
                    echo json_encode(['status' => 'error', 'message' => 'Database error']);
                    exit;
                }
                $error = "Failed to add schedule.";
            }
        } else {
             if (is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
                exit;
            }
            $error = "All fields are required.";
        }
    } 
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_schedule') {
        $id = $_POST['schedule_id'];
        $title = trim($_POST['title']);
        $day = $_POST['day'];
        $time = $_POST['time'];
        $description = trim($_POST['description']);
        
        $stmt = $db->prepare("UPDATE schedules SET title = :title, day = :day, time = :time, description = :description WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':day', $day, SQLITE3_TEXT);
        $stmt->bindValue(':time', $time, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            if (is_ajax_request()) {
                echo json_encode(['status' => 'success', 'message' => 'Schedule updated successfully']);
                exit;
            }
            $message = "Schedule updated successfully!";
        } else {
             if (is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update']);
                exit;
            }
            $error = "Failed to update schedule.";
        }
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_schedule') {
        $id = $_POST['schedule_id'];
        $stmt = $db->prepare("DELETE FROM schedules WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
             if (is_ajax_request()) {
                echo json_encode(['status' => 'success', 'message' => 'Schedule deleted successfully']);
                exit;
            }
            $message = "Schedule deleted successfully!";
        } else {
             if (is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete']);
                exit;
            }
            $error = "Failed to delete schedule.";
        }
    }
}

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

function renderScheduleHTML($schedules, $totalPages = 0, $currentPage = 1, $filterDay = 'All') {
    if (empty($schedules)) {
        echo '<div class="text-center py-5">
                <div class="mb-3 text-muted opacity-50"><i class="fas fa-calendar-week fa-3x"></i></div>
                <h6 class="text-muted">No schedules found.</h6>
                <button class="btn btn-sm btn-outline-success mt-2" data-bs-toggle="modal" data-bs-target="#addScheduleModal">Add your first class</button>
              </div>';
        return;
    }

    foreach ($schedules as $review) {
        $dayName = $review['day'] ?? 'Mon';
        $shortDay = substr($dayName, 0, 3);
        
        $statusClass = 'status-upcoming';
        switch(strtolower($review['status'])) {
            case 'pending': $statusClass = 'status-pending'; break;
            case 'confirmed': $statusClass = 'status-confirmed'; break;
            case 'urgent': $statusClass = 'status-urgent'; break;
            case 'current': $statusClass = 'status-current'; break;
            case 'done': $statusClass = 'status-done'; break;
        }

        $desc = htmlspecialchars($review['description'] ?? '');
        $title = htmlspecialchars($review['title']);
        $time = htmlspecialchars($review['time']);
        $day = htmlspecialchars($review['day']);
        $descSafe = htmlspecialchars($review['description'] ?? '', ENT_QUOTES);
        
        // Responsive Padding: px-3 px-lg-4
        echo '
        <div class="list-group-item px-3 px-lg-4 py-3 border-light">
            <div class="d-flex align-items-start">
                <div class="date-box flex-shrink-0 me-3 mt-1">
                    <span>WEEKLY</span>
                    <span>'.strtoupper($shortDay).'</span>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="mb-0 fw-bold text-dark">'.$title.'</h6>
                        <span class="badge rounded-pill fw-normal px-2 py-1 '.$statusClass.'" style="font-size: 0.75rem;">
                            '.htmlspecialchars($review['status']).'
                        </span>
                    </div>
                    
                    <div class="text-muted small mb-2"><i class="far fa-clock me-1"></i> '.$time.'</div>';
                    
                    if(!empty($review['description'])) {
                        echo '<div class="markdown-content bg-light p-2 rounded border border-light small" data-markdown="'.$desc.'"></div>';
                    }
                    
        echo '  </div>
                <div class="dropdown ms-2">
                    <button class="btn btn-link text-muted p-0" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                        <li>
                            <a href="#" class="dropdown-item" 
                               data-bs-toggle="modal" 
                               data-bs-target="#editScheduleModal"
                               data-id="'.$review['id'].'"
                               data-title="'.htmlspecialchars($title, ENT_QUOTES).'"
                               data-day="'.$day.'"
                               data-time="'.$time.'"
                               data-description="'.$descSafe.'">
                                <i class="fas fa-edit me-2 text-primary"></i> Edit
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" onsubmit="return confirm(\'Are you sure you want to delete this?\');">
                                <input type="hidden" name="action" value="delete_schedule">
                                <input type="hidden" name="schedule_id" value="'.$review['id'].'">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-trash-alt me-2"></i> Delete
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>';
    }

    if ($filterDay === 'All' && $totalPages > 1) {
        echo '<div class="d-flex justify-content-center pt-3 pb-2">';
        echo '<nav aria-label="Schedule pagination">';
        echo '<ul class="pagination pagination-sm mb-0">';
        
        $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
        $prevPage = max(1, $currentPage - 1);
        echo '<li class="page-item '.$prevDisabled.'">';
        echo '<button class="page-link pagination-btn" data-page="'.$prevPage.'" data-day="All" aria-label="Previous"><span aria-hidden="true">&laquo;</span></button>';
        echo '</li>';

        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $currentPage) ? 'active' : '';
            echo '<li class="page-item '.$active.'">';
            echo '<button class="page-link pagination-btn" data-page="'.$i.'" data-day="All">'.$i.'</button>';
            echo '</li>';
        }

        $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
        $nextPage = min($totalPages, $currentPage + 1);
        echo '<li class="page-item '.$nextDisabled.'">';
        echo '<button class="page-link pagination-btn" data-page="'.$nextPage.'" data-day="All" aria-label="Next"><span aria-hidden="true">&raquo;</span></button>';
        echo '</li>';
        
        echo '</ul>';
        echo '</nav>';
        echo '</div>';
    }
}

$days_order = [
    'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 
    'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7
];

function calculateScheduleStatuses(&$schedules, $days_order) {
    $current_timestamp_now = time();
    $current_day_name = date('l');
    
    $schedules_by_day_list = [];
    foreach ($schedules as $idx => $item) {
        $schedules_by_day_list[$item['day']][] = $idx;
    }
    
    foreach ($schedules_by_day_list as $dayStr => $indices) {
        $count = count($indices);
        for ($i = 0; $i < $count; $i++) {
            $schIdx = $indices[$i];
            
            $schDayVal = $days_order[$dayStr] ?? 8;
            $todayVal = $days_order[$current_day_name] ?? 8;
            
            if ($schDayVal < $todayVal) {
                $schedules[$schIdx]['status'] = 'Done';
                continue;
            } elseif ($schDayVal > $todayVal) {
                $schedules[$schIdx]['status'] = 'Upcoming';
                continue;
            } 

            $startTimeStr = $schedules[$schIdx]['time'];
            $startTimeTs = strtotime(date('Y-m-d') . ' ' . $startTimeStr);
            
            if (isset($indices[$i + 1])) {
                $nextSchIdx = $indices[$i + 1];
                $nextTimeStr = $schedules[$nextSchIdx]['time'];
                $endTimeTs = strtotime(date('Y-m-d') . ' ' . $nextTimeStr);
            } else {
                $endTimeTs = $startTimeTs + (2 * 60 * 60); 
            }
            
            if ($current_timestamp_now < $startTimeTs) {
                $schedules[$schIdx]['status'] = 'Upcoming';
            } elseif ($current_timestamp_now >= $startTimeTs && $current_timestamp_now < $endTimeTs) {
                $schedules[$schIdx]['status'] = 'Current';
            } else {
                $schedules[$schIdx]['status'] = 'Done';
            }
        }
    }
}

$all_result = $db->query("SELECT * FROM schedules WHERE user_id = $user_id");
$all_schedules = [];
$stats_by_status = ['Upcoming' => 0, 'Pending' => 0, 'Confirmed' => 0, 'Urgent' => 0];

if ($all_result) {
    while ($row = $all_result->fetchArray(SQLITE3_ASSOC)) {
        if (empty($row['day']) && !empty($row['date'])) {
            $row['day'] = date('l', strtotime($row['date']));
        }
        $all_schedules[] = $row;
    }
}

usort($all_schedules, function($a, $b) use ($days_order) {
    $da = $days_order[$a['day']] ?? 8;
    $db = $days_order[$b['day']] ?? 8;
    if ($da === $db) {
        return strcmp($a['time'], $b['time']);
    }
    return $da - $db;
});

calculateScheduleStatuses($all_schedules, $days_order);

$isAjax = isset($_GET['ajax_day']);
$filter_day = $isAjax ? $_GET['ajax_day'] : (isset($_GET['filter_day']) ? $_GET['filter_day'] : date('l')); 

$limit = 5; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$display_schedules = [];
$totalPages = 1;

if ($filter_day === 'All') {
    $totalRecords = count($all_schedules);
    $totalPages = ceil($totalRecords / $limit);
    if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
    
    $offset = ($page - 1) * $limit;
    $display_schedules = array_slice($all_schedules, $offset, $limit);
    
} else {
    $display_schedules = array_filter($all_schedules, function($row) use ($filter_day) {
        return $row['day'] === $filter_day;
    });
}

if ($isAjax) {
    renderScheduleHTML($display_schedules, $totalPages, $page, $filter_day);
    exit;
}

$today_day = date('l'); 
$current_time = date('H:i');
$today_index = $days_order[$today_day];

$next_session = null;
foreach ($all_schedules as $sch) {
    if ($sch['day'] === $today_day && $sch['time'] > $current_time) {
        $next_session = $sch;
        break;
    }
}
if (!$next_session) {
    foreach ($all_schedules as $sch) {
        $sch_day_idx = $days_order[$sch['day']] ?? 8;
        if ($sch_day_idx > $today_index) {
            $next_session = $sch;
            break;
        }
    }
}
if (!$next_session && !empty($all_schedules)) {
    $next_session = $all_schedules[0]; 
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule | ReviewHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="../styles/schedule.css">
    <link rel="stylesheet" href="../styles/nav.css">
</head>
<body>
<?php require_once "header.php"; ?>
<?php include 'nav.php'; ?>

<main class="main-content">
    <!-- Responsive Container: p-3 for mobile, p-lg-4 for desktop -->
    <div class="container-xl p-3 p-lg-4">
        
        <!-- UP NEXT SECTION (MOBILE ONLY) -->
        <div class="row mb-4 d-lg-none">
            <div class="col-12">
                <?php if ($next_session): ?>
                <div class="card border-0 shadow-sm rounded-4 bg-success text-white">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1">Up Next</h5>
                        <div class="d-flex align-items-center bg-white bg-opacity-25 p-3 rounded-3 mt-3">
                            <i class="fas fa-bell fs-3 me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($next_session['title']) ?></h6>
                                <small class="d-block"><?= htmlspecialchars($next_session['day']) ?>, <?= htmlspecialchars($next_session['time']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 bg-secondary text-white">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1">Free Time!</h5>
                        <p class="opacity-75 mb-0">No upcoming recurring sessions.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 mb-lg-4">
            <div>
                <!-- Responsive Heading Size -->
                <h2 class="fw-bold text-dark mb-1 fs-3 fs-lg-2">Weekly Schedule</h2>
                <p class="text-muted mb-0 small">Manage your weekly recurring sessions.</p>
            </div>
            <button class="btn btn-success d-none d-md-block" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <i class="fas fa-plus me-2"></i> Add New
            </button>
            <button class="btn btn-success d-md-none rounded-circle shadow" style="width:40px; height:40px; display:flex; align-items:center; justify-content:center;" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <i class="fas fa-plus"></i>
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($message) ?>  
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Responsive Grid Gap -->
        <div class="row g-3 g-lg-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <!-- Responsive Header Padding -->
                    <div class="card-header bg-white border-0 py-3 px-3 px-lg-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="fw-bold mb-0">Your Classes</h5>
                            <span class="badge bg-success bg-opacity-10 text-success"><?= count($all_schedules) ?> Total</span>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <label for="dayFilter" class="me-2 small fw-bold text-muted">Filter:</label>
                            <select id="dayFilter" class="form-select form-select-sm border-light bg-light fw-bold text-secondary" style="width: 140px;">
                                <option value="All" <?= ($filter_day === 'All') ? 'selected' : '' ?>>All Days</option>
                                <?php 
                                $days_list = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                foreach($days_list as $d_opt): 
                                    $selected = ($d_opt === $filter_day) ? 'selected' : '';
                                ?>
                                    <option value="<?= $d_opt ?>" <?= $selected ?>><?= $d_opt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" id="scheduleList">
                            <?php renderScheduleHTML($display_schedules, $totalPages, $page, $filter_day); ?>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 py-3 px-3 px-lg-4">
                        <h5 class="fw-bold mb-0">Week at a Glance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless text-center align-middle mb-0">
                                <thead class="text-muted small text-uppercase">
                                    <tr>
                                        <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <?php 
                                        $days_map = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                        foreach($days_map as $d): 
                                            $isToday = ($d === date('l'));
                                            $hasEvent = false;
                                            foreach($all_schedules as $s) {
                                                if(($s['day'] ?? '') === $d) {
                                                    $hasEvent = true;
                                                    break;
                                                }
                                            }
                                            $bgClass = $isToday ? 'bg-success text-white' : ($hasEvent ? 'bg-success bg-opacity-25 text-success fw-bold' : 'text-muted bg-light');
                                            $dShort = substr($d, 0, 1);
                                        ?>
                                            <td>
                                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle <?= $bgClass ?>" style="width: 35px; height: 35px; font-size: 0.8rem;">
                                                    <?= $dShort ?>
                                                </div>
                                                <?php if($hasEvent && !$isToday): ?>
                                                    <div class="mt-1"><i class="fas fa-circle text-success" style="font-size: 5px;"></i></div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 d-flex flex-column gap-3 gap-lg-4">
                
                <!-- UP NEXT SECTION (DESKTOP ONLY) -->
                <div class="d-none d-lg-block">
                    <?php if ($next_session): ?>
                    <div class="card border-0 shadow-sm rounded-4 bg-success text-white">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-1">Up Next</h5>
                            <div class="d-flex align-items-center bg-white bg-opacity-25 p-3 rounded-3 mt-3">
                                <i class="fas fa-bell fs-3 me-3"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($next_session['title']) ?></h6>
                                    <small class="d-block"><?= htmlspecialchars($next_session['day']) ?>, <?= htmlspecialchars($next_session['time']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card border-0 shadow-sm rounded-4 bg-secondary text-white">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-1">Free Time!</h5>
                            <p class="opacity-75 mb-0">No upcoming recurring sessions.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</main>

<!-- Modals remain unchanged, just included for context/file completeness -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold" id="addScheduleModalLabel">Add New Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_schedule">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">TITLE</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g., Biology Midterm Study" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold">DAY</label>
                            <select name="day" class="form-select" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold">TIME</label>
                            <input type="time" name="time" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">DESCRIPTION</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Topic: Algebra - Review Chapter 1 - Solve 5 equations"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold" id="editScheduleModalLabel">Edit Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" name="schedule_id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">TITLE</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold">DAY</label>
                            <select name="day" class="form-select" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-bold">TIME</label>
                            <input type="time" name="time" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">DESCRIPTION</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/day.js"></script>
<?php include "footer.php"; ?>
</body>
</html>