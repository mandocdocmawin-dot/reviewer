<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: index.php?login=true");
    exit;
}

require_once "db.php";

$db = get_db();
$user = $_SESSION['user'];
$user_id = $user['id'];

// --- FETCH SUBJECTS ---
$subjectStmt = $db->prepare("SELECT * FROM subjects WHERE user_id = :uid ORDER BY id ASC");
$subjectStmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$subjectResult = $subjectStmt->execute();
$subjects = [];
while ($row = $subjectResult->fetchArray(SQLITE3_ASSOC)) {
    $subjects[] = $row;
}

// Determine active subject (null means 'Uncategorized')
$active_subject_id = isset($_GET['subject_id']) && is_numeric($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;

// Get the name of the active subject for the rename modal
$active_subject_name = "";
if ($active_subject_id) {
    foreach ($subjects as $sub) {
        if ($sub['id'] == $active_subject_id) {
            $active_subject_name = $sub['name'];
            break;
        }
    }
}

// --- PAGINATION INIT ---
$page = isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
$limit = 5; 
$offset = ($page - 1) * $limit;

// Handle standard PHP Form Actions (CRUD)
$message = "";
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirect_page = $_POST['page'] ?? 1;
    $target_subject = $_POST['subject_id'] ?? null;
    $subject_param = $target_subject ? "&subject_id=" . $target_subject : "";

    // --- ADD SUBJECT ---
    if ($action === 'add_subject') {
        $subject_name = trim($_POST['subject_name']);
        if (!empty($subject_name)) {
            $stmt = $db->prepare("INSERT INTO subjects (user_id, name) VALUES (:uid, :name)");
            $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':name', $subject_name, SQLITE3_TEXT);
            if ($stmt->execute()) {
                $new_id = $db->lastInsertRowID();
                $_SESSION['flash_message'] = "Subject created successfully!";
                header("Location: create_review.php?subject_id=" . $new_id);
                exit;
            }
        }
    }

    // --- RENAME SUBJECT ---
    if ($action === 'rename_subject') {
        $new_name = trim($_POST['new_subject_name']);
        if (!empty($new_name) && $target_subject) {
            $stmt = $db->prepare("UPDATE subjects SET name = :name WHERE id = :id AND user_id = :uid");
            $stmt->bindValue(':name', $new_name, SQLITE3_TEXT);
            $stmt->bindValue(':id', $target_subject, SQLITE3_INTEGER);
            $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Folder renamed successfully!";
                header("Location: create_review.php?subject_id=" . $target_subject);
                exit;
            }
        }
    }

    // --- DELETE SUBJECT ---
    if ($action === 'delete_subject') {
        if ($target_subject) {
            // 1. Move all items in this subject to 'Uncategorized' (NULL)
            $stmtMove = $db->prepare("UPDATE content SET subject_id = NULL WHERE subject_id = :sid AND user_id = :uid");
            $stmtMove->bindValue(':sid', $target_subject, SQLITE3_INTEGER);
            $stmtMove->bindValue(':uid', $user_id, SQLITE3_INTEGER);
            $stmtMove->execute();

            // 2. Delete the subject itself
            $stmtDel = $db->prepare("DELETE FROM subjects WHERE id = :sid AND user_id = :uid");
            $stmtDel->bindValue(':sid', $target_subject, SQLITE3_INTEGER);
            $stmtDel->bindValue(':uid', $user_id, SQLITE3_INTEGER);
            
            if ($stmtDel->execute()) {
                $_SESSION['flash_message'] = "Folder deleted! Items were safely moved to Uncategorized.";
                header("Location: create_review.php"); // Redirect back to Uncategorized
                exit;
            }
        }
    }

    // --- ADD REVIEW ---
    if ($action === 'add_review') {
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        $subj_id = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
        
        if (!empty($question) && !empty($answer)) {
            $stmt = $db->prepare("INSERT INTO content (user_id, subject_id, question, answer) VALUES (:uid, :sid, :q, :a)");
            $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
            $stmt->bindValue(':sid', $subj_id, $subj_id ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->bindValue(':q', $question, SQLITE3_TEXT);
            $stmt->bindValue(':a', $answer, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Review created successfully!";
                header("Location: create_review.php?page=1" . ($subj_id ? "&subject_id=".$subj_id : ""));
                exit;
            }
        }
    } 
    // --- UPDATE REVIEW ---
    elseif ($action === 'edit_review') {
        $id = $_POST['id'];
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        
        if (!empty($question) && !empty($answer)) {
            $stmt = $db->prepare("UPDATE content SET question = :q, answer = :a WHERE id = :id AND user_id = :uid");
            $stmt->bindValue(':q', $question, SQLITE3_TEXT);
            $stmt->bindValue(':a', $answer, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Review updated successfully!";
                header("Location: create_review.php?page=" . $redirect_page . $subject_param);
                exit;
            }
        }
    } 
    // --- DELETE SINGLE REVIEW ---
    elseif ($action === 'delete_review') {
        $id = $_POST['id'];
        $stmt = $db->prepare("DELETE FROM content WHERE id = :id AND user_id = :uid");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "Review deleted successfully!";
            header("Location: create_review.php?page=" . $redirect_page . $subject_param);
            exit;
        }
    }
    // --- CLEAR ALL REVIEWS ---
    elseif ($action === 'clear_all_reviews') {
        if ($target_subject) {
            $stmt = $db->prepare("DELETE FROM content WHERE user_id = :uid AND subject_id = :sid");
            $stmt->bindValue(':sid', $target_subject, SQLITE3_INTEGER);
        } else {
            $stmt = $db->prepare("DELETE FROM content WHERE user_id = :uid AND subject_id IS NULL");
        }
        $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "Reviews in this tab deleted successfully!";
            header("Location: create_review.php?page=1" . $subject_param);
            exit;
        }
    }
}

// Count total records for the ACTIVE tab
if ($active_subject_id) {
    $countStmt = $db->prepare("SELECT COUNT(*) as count FROM content WHERE user_id = :uid AND subject_id = :sid");
    $countStmt->bindValue(':sid', $active_subject_id, SQLITE3_INTEGER);
} else {
    $countStmt = $db->prepare("SELECT COUNT(*) as count FROM content WHERE user_id = :uid AND subject_id IS NULL");
}
$countStmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$countResult = $countStmt->execute()->fetchArray(SQLITE3_ASSOC);
$total_records = $countResult['count'];
$total_pages = ceil($total_records / $limit);

// Adjust page if out of bounds
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch records for current page and active tab
if ($active_subject_id) {
    $stmt = $db->prepare("SELECT * FROM content WHERE user_id = :uid AND subject_id = :sid ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $stmt->bindValue(':sid', $active_subject_id, SQLITE3_INTEGER);
} else {
    $stmt = $db->prepare("SELECT * FROM content WHERE user_id = :uid AND subject_id IS NULL ORDER BY id DESC LIMIT $limit OFFSET $offset");
}
$stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();

$reviews = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $reviews[] = $row;
}

// --- AJAX RESPONSE HANDLER ---
if (isset($_GET['ajax'])) {
    ob_start(); 
    renderReviewList($reviews, $offset, $total_records, $page, $active_subject_id);
    $listHtml = ob_get_clean();

    ob_start(); 
    renderPagination($page, $total_pages, $active_subject_id);
    $paginationHtml = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'list' => $listHtml,
        'pagination' => $paginationHtml
    ]);
    exit;
}

function renderReviewList($reviews, $offset, $total_records, $current_page, $active_subject_id) {
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <h6 class="fw-bold text-muted text-uppercase small ls-1 mb-0">Review List</h6>
        <span class="badge bg-light text-secondary border rounded-pill px-3">
            Total: <?php echo $total_records; ?>
        </span>
    </div>
    <?php
    $display_index = $offset + 1;

    if (!empty($reviews)) {
        foreach ($reviews as $row) {
            ?>
            <div class="card mb-3 border-0 shadow-sm hover-shadow transition-all activity-card">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 text-center rounded p-2 me-3 q-box">
                            <small class="d-block fw-bold text-uppercase opacity-75" style="font-size: 0.65rem; letter-spacing: 1px;">ITEM</small>
                            <span class="h4 d-block mb-0 fw-bold">#<?php echo $display_index; ?></span>
                        </div>

                        <div class="flex-grow-1 min-w-0 me-3" style="min-width: 0;">
                            <h6 class="mb-1 fw-bold text-truncate text-dark">
                                <?php echo htmlspecialchars($row['question']); ?>
                            </h6>
                            <div class="text-muted small text-truncate-2">
                                <?php echo htmlspecialchars($row['answer']); ?>
                            </div>
                        </div>

                        <div class="flex-shrink-0">
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                    <li>
                                        <button class="dropdown-item small" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editReviewModal"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-question="<?php echo htmlspecialchars($row['question']); ?>"
                                                data-answer="<?php echo htmlspecialchars($row['answer']); ?>">
                                            <i class="bi bi-pencil me-2 text-warning"></i> Edit
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this review?');" class="m-0 p-0">
                                            <input type="hidden" name="action" value="delete_review">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="page" value="<?php echo $current_page; ?>">
                                            <input type="hidden" name="subject_id" value="<?php echo $active_subject_id; ?>">
                                            <button type="submit" class="dropdown-item small text-danger">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $display_index++;
        }
    } else {
        echo '
        <div class="text-center py-5 text-muted grayscale">
            <div class="mb-3">
                <i class="bi bi-folder2-open display-1 opacity-25"></i>
            </div>
            <h5 class="fw-normal">No reviews in this subject</h5>
            <p class="small opacity-75">Click "Create New Review" to add one here</p>
        </div>';
    }
}

function renderPagination($page, $total_pages, $active_subject_id) {
    if ($total_pages <= 1) return;

    // Pass the subject ID to our JS function
    $subjParam = $active_subject_id ? ", $active_subject_id" : ", null";

    $prev = $page - 1;
    $next = $page + 1;

    echo '<nav aria-label="Review pagination" class="mt-4"><ul class="pagination justify-content-center border-0">';

    if ($page > 1) {
        echo '<li class="page-item"><a class="page-link border-0 rounded-circle mx-1 shadow-sm d-flex align-items-center justify-content-center pagination-btn" href="#" onclick="loadPage('.$prev.$subjParam.')"><i class="bi bi-chevron-left"></i></a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link border-0 rounded-circle mx-1 bg-transparent pagination-btn"><i class="bi bi-chevron-left text-muted opacity-25"></i></span></li>';
    }

    $start = max(1, $page - 2);
    $end = min($total_pages, $page + 2);
    
    if ($start > 1) {
        echo '<li class="page-item"><a class="page-link border-0 rounded-circle mx-1 pagination-btn" href="#" onclick="loadPage(1'.$subjParam.')">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? 'active shadow-sm' : '';
        echo '<li class="page-item '.$active.'"><a class="page-link border-0 rounded-circle mx-1 fw-bold d-flex align-items-center justify-content-center pagination-btn" href="#" onclick="loadPage('.$i.$subjParam.')">'.$i.'</a></li>';
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>';
        echo '<li class="page-item"><a class="page-link border-0 rounded-circle mx-1 pagination-btn" href="#" onclick="loadPage('.$total_pages.$subjParam.')">'.$total_pages.'</a></li>';
    }

    if ($page < $total_pages) {
        echo '<li class="page-item"><a class="page-link border-0 rounded-circle mx-1 shadow-sm d-flex align-items-center justify-content-center pagination-btn" href="#" onclick="loadPage('.$next.$subjParam.')"><i class="bi bi-chevron-right"></i></a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link border-0 rounded-circle mx-1 bg-transparent pagination-btn"><i class="bi bi-chevron-right text-muted opacity-25"></i></span></li>';
    }

    echo '</ul></nav>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Questions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles/schedule.css">
    <link rel="stylesheet" href="../styles/nav.css">
    <link rel="stylesheet" href="../styles/create_review.css">
</head>
<body>
    <?php require_once "header.php"; ?>
    <?php include "nav.php"; ?>

    <main class="main-content">
        <div class="container-xl p-3 p-lg-4">
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row align-items-center justify-content-between mb-4 g-3">
                <div class="col-12 col-md-auto">
                    <h4 class="mb-0 fw-bold text-dark">
                        Review Questions
                    </h4>
                    <div class="text-muted small mt-1">Manage your review items by subject</div>
                </div>
                
                <div class="col-12 col-md-auto d-grid gap-2 d-md-flex align-items-center">
                    
                    <?php if ($active_subject_id): ?>
                        <button class="btn btn-outline-primary rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#renameSubjectModal">
                            <i class="bi bi-pencil-square me-1"></i>Rename
                        </button>

                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this folder?\n\nDon\'t worry, the reviews inside will NOT be deleted. They will be safely moved to the Uncategorized folder.');" class="d-grid d-md-block m-0">
                            <input type="hidden" name="action" value="delete_subject">
                            <input type="hidden" name="subject_id" value="<?php echo $active_subject_id; ?>">
                            <button type="submit" class="btn btn-outline-danger rounded-pill px-3 fw-bold shadow-sm">
                                <i class="bi bi-folder-x me-1"></i>Delete Folder
                            </button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete ALL reviews in this folder? This cannot be undone.');" class="d-grid d-md-block m-0">
                        <input type="hidden" name="action" value="clear_all_reviews">
                        <input type="hidden" name="subject_id" value="<?php echo $active_subject_id; ?>">
                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                            <i class="bi bi-trash me-2"></i>Clear All List
                        </button>
                    </form>

                    <button class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                        <i class="bi bi-plus-lg me-2"></i>Create New Review
                    </button>
                </div>
            </div>

            <ul class="nav nav-tabs border-bottom-0 mb-3" style="gap: 5px;">
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_subject_id === null ? 'active fw-bold shadow-sm border-bottom-0' : 'text-muted bg-light border-0'; ?> rounded-top" 
                       href="create_review.php">
                       <i class="bi bi-folder-fill me-1 text-warning"></i> Uncategorized
                    </a>
                </li>
                <?php foreach ($subjects as $sub): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_subject_id === $sub['id'] ? 'active fw-bold shadow-sm border-bottom-0' : 'text-muted bg-light border-0'; ?> rounded-top" 
                           href="create_review.php?subject_id=<?php echo $sub['id']; ?>">
                           <i class="bi bi-folder-fill me-1 text-warning"></i> <?php echo htmlspecialchars($sub['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item ms-1">
                    <button class="nav-link text-primary bg-light border-0 rounded-top" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                        <i class="bi bi-plus-circle-fill"></i> Add Folder
                    </button>
                </li>
            </ul>

            <div class="card border-0 shadow-sm rounded-4 rounded-top-start-0">
                <div class="card-body p-4">
                    <div id="review-list-container">
                        <?php renderReviewList($reviews, $offset, $total_records, $page, $active_subject_id); ?>
                    </div>
                    <div id="pagination-container">
                        <?php renderPagination($page, $total_pages, $active_subject_id); ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <div class="modal fade" id="addSubjectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">New Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_subject">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">SUBJECT NAME</label>
                            <input type="text" name="subject_name" class="form-control bg-light" required placeholder="e.g. Science">
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="submit" class="btn btn-primary rounded-pill w-100">Create Folder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($active_subject_id): ?>
    <div class="modal fade" id="renameSubjectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Rename Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="rename_subject">
                        <input type="hidden" name="subject_id" value="<?php echo $active_subject_id; ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">FOLDER NAME</label>
                            <input type="text" name="new_subject_name" class="form-control bg-light" required value="<?php echo htmlspecialchars($active_subject_name); ?>">
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Rename</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="addReviewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Create New Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_review">
                        <input type="hidden" name="subject_id" value="<?php echo $active_subject_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">QUESTION</label>
                            <textarea name="question" class="form-control bg-light" rows="3" required placeholder="Type your question here..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">ANSWER</label>
                            <textarea name="answer" class="form-control bg-light" rows="4" required placeholder="Type the answer here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4">Save Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editReviewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_review">
                        <input type="hidden" name="id" id="edit-id">
                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                        <input type="hidden" name="subject_id" value="<?php echo $active_subject_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Question</label>
                            <textarea name="question" id="edit-question" class="form-control bg-light" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Answer</label>
                            <textarea name="answer" id="edit-answer" class="form-control bg-light" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4">Update Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include "footer.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Inline JS to ensure pagination works with the new subject_id parameters
        function loadPage(page, subjectId = null) {
            event.preventDefault();
            let url = 'create_review.php?ajax=true&page=' + page;
            if (subjectId !== null) {
                url += '&subject_id=' + subjectId;
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('review-list-container').innerHTML = data.list;
                    document.getElementById('pagination-container').innerHTML = data.pagination;
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        // Keep your existing modal populating logic
        const editReviewModal = document.getElementById('editReviewModal');
        if (editReviewModal) {
            editReviewModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                document.getElementById('edit-id').value = button.getAttribute('data-id');
                document.getElementById('edit-question').value = button.getAttribute('data-question');
                document.getElementById('edit-answer').value = button.getAttribute('data-answer');
            });
        }
    </script>
</body>
</html>