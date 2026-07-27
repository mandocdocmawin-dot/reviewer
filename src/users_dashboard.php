<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: ../index.php?login=true");
    exit;
}
$currentUser = $_SESSION["user"];

require_once __DIR__ . "/db.php";
$db = get_db();

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 5;

// Check for shuffle parameter
$isShuffled = isset($_GET['shuffle']) && $_GET['shuffle'] == 'true';

// Check for mode parameter (persist view mode)
$allowedModes = ['list', 'card', 'notes'];
$currentMode = isset($_GET['mode']) && in_array($_GET['mode'], $allowedModes) ? $_GET['mode'] : 'list';

// --- NEW: Handle Subject Parameter ---
$subjectIdParam = isset($_GET['subject_id']) ? $_GET['subject_id'] : 'uncategorized';

// Fetch all subjects for the user to generate tabs
$subjects = [];
$subStmt = $db->prepare("SELECT id, name FROM subjects WHERE user_id = :uid ORDER BY name ASC");
$subStmt->bindValue(':uid', $currentUser['id'], SQLITE3_INTEGER);
$subResult = $subStmt->execute();
while ($row = $subResult->fetchArray(SQLITE3_ASSOC)) {
    $subjects[] = $row;
}

// Build the subject query condition
$subjectCondition = "";
if ($subjectIdParam === 'uncategorized') {
    $subjectCondition = " AND (subject_id IS NULL OR subject_id = 0)";
} else {
    $subjectCondition = " AND subject_id = :subject_id";
}

// Session key to store the randomized order (now scoped to the current subject)
$sessionShuffleKey = 'shuffled_ids_subj_' . $subjectIdParam;

// --- SHUFFLE LOGIC START ---
// If shuffle is active
if ($isShuffled) {
    // If we don't have a stored order yet for this subject, create one
    if (!isset($_SESSION[$sessionShuffleKey]) || empty($_SESSION[$sessionShuffleKey])) {
        // 1. Fetch ALL question IDs for this user AND specific subject
        $idsQuery = "SELECT id FROM content WHERE user_id = :uid" . $subjectCondition;
        $idsStmt = $db->prepare($idsQuery);
        $idsStmt->bindValue(':uid', $currentUser['id'], SQLITE3_INTEGER);
        
        if ($subjectIdParam !== 'uncategorized') {
            $idsStmt->bindValue(':subject_id', (int)$subjectIdParam, SQLITE3_INTEGER);
        }
        
        $idsResult = $idsStmt->execute();
        
        $allIds = [];
        while ($row = $idsResult->fetchArray(SQLITE3_ASSOC)) {
            $allIds[] = $row['id'];
        }
        
        // 2. Randomize the IDs once
        shuffle($allIds);
        
        // 3. Store in session
        $_SESSION[$sessionShuffleKey] = $allIds;
    }
} else {
    // If shuffle is turned OFF, clear the session so next time it starts fresh for this subject
    if (isset($_SESSION[$sessionShuffleKey])) {
        unset($_SESSION[$sessionShuffleKey]);
    }
}
// --- SHUFFLE LOGIC END ---

$questions = [];
$totalRecords = 0;
$totalPages = 0;

try {
    if ($isShuffled && isset($_SESSION[$sessionShuffleKey])) {
        // --- FETCHING SHUFFLED DATA FROM SESSION ---
        $totalRecords = count($_SESSION[$sessionShuffleKey]);
        
        $totalPages = ceil($totalRecords / $limit);
        if ($totalPages < 1) $totalPages = 1;
        if ($page > $totalPages) $page = $totalPages;
        
        $offset = ($page - 1) * $limit;
        
        // Get the specific IDs for the current page
        $pageIds = array_slice($_SESSION[$sessionShuffleKey], $offset, $limit);
        
        if (!empty($pageIds)) {
            // Fetch content for these specific IDs
            $inList = implode(',', $pageIds);
            $stmt = $db->prepare("SELECT * FROM content WHERE id IN ($inList)");
            $result = $stmt->execute();
            
            $tempQuestions = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $tempQuestions[$row['id']] = $row;
            }
            
            // Reconstruct array in the exact order of $pageIds
            foreach ($pageIds as $id) {
                if (isset($tempQuestions[$id])) {
                    $questions[] = $tempQuestions[$id];
                }
            }
        }

    } else {
        // --- STANDARD FETCH (Date Descending, Filtered by Subject) ---
        $countQuery = "SELECT COUNT(*) as count FROM content WHERE user_id = :uid" . $subjectCondition;
        $countStmt = $db->prepare($countQuery);
        $countStmt->bindValue(':uid', $currentUser['id'], SQLITE3_INTEGER);
        if ($subjectIdParam !== 'uncategorized') {
            $countStmt->bindValue(':subject_id', (int)$subjectIdParam, SQLITE3_INTEGER);
        }
        
        $result = $countStmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $totalRecords = $row['count'];

        $totalPages = ceil($totalRecords / $limit);
        if ($totalPages < 1) $totalPages = 1;
        if ($page > $totalPages) $page = $totalPages;
        
        $offset = ($page - 1) * $limit;

        $fetchQuery = "SELECT * FROM content WHERE user_id = :uid" . $subjectCondition . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($fetchQuery);
        $stmt->bindValue(':uid', $currentUser['id'], SQLITE3_INTEGER);
        if ($subjectIdParam !== 'uncategorized') {
            $stmt->bindValue(':subject_id', (int)$subjectIdParam, SQLITE3_INTEGER);
        }
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $questions[] = $row;
        }
    }

} catch (Exception $e) {
}

// Fallback mock data only if uncategorized is empty (to prevent showing mock data in empty custom folders)
if (empty($questions) && $totalRecords == 0 && $page == 1 && $subjectIdParam === 'uncategorized') {
    $questions = [
        ["id" => 1, "question" => "What is the capital of France?", "answer" => "Paris"],
        ["id" => 2, "question" => "Who wrote 'To Kill a Mockingbird'?", "answer" => "Harper Lee"],
        ["id" => 3, "question" => "What is the powerhouse of the cell?", "answer" => "Mitochondria"]
    ];
    $totalRecords = 3;
    $totalPages = 1;
}

function renderQuestionsList($questions, $startIndex = 1) {
    if (empty($questions)) {
        echo '
        <div class="text-center py-5">
            <div class="mb-3 text-muted opacity-50"><i class="fas fa-folder-open fa-3x"></i></div>
            <h6 class="text-muted">No study items found in this folder.</h6>
        </div>';
        return;
    }

    echo '<div class="list-group list-group-flush">';
    $displayNum = $startIndex;
    foreach ($questions as $q) {
        $questionText = htmlspecialchars($q['question']);
        
        echo "
        <div class='list-group-item px-3 px-lg-4 py-3 border-light'>
            <div class='d-flex align-items-center justify-content-between'>
                <div class='flex-grow-1'>
                    <h6 class='mb-1 fw-bold text-dark'>{$questionText}</h6>
                    <span class='badge bg-light text-secondary border'>Question #{$displayNum}</span>
                </div>
                <div class='ms-3'>
                   <button class='btn btn-sm btn-outline-success rounded-pill' onclick='switchMode(\"card\");'>
                        <i class='fas fa-eye me-1'></i> View
                   </button>
                </div>
            </div>
        </div>";
        $displayNum++;
    }
    echo '</div>';
}

function renderPagination($totalPages, $currentPage, $isShuffled, $currentMode, $subjectIdParam) {
    if ($totalPages <= 1) return;

    // Append shuffle, mode, and subject parameters to pagination links
    $shuffleParam = $isShuffled ? '&shuffle=true' : '';
    $modeParam = '&mode=' . urlencode($currentMode);
    $subjectParam = '&subject_id=' . urlencode($subjectIdParam);
    $queryParams = $shuffleParam . $modeParam . $subjectParam;

    echo '<div class="d-flex justify-content-center py-3 border-top">';
    echo '<div class="btn-group" role="group">';
    
    // Previous Button
    if ($currentPage > 1) {
        $prevPage = $currentPage - 1;
        echo "<a href='?page={$prevPage}{$queryParams}' class='btn btn-outline-secondary btn-sm pagination-btn'><i class='fas fa-chevron-left'></i></a>";
    } else {
        echo "<button class='btn btn-outline-secondary btn-sm disabled'><i class='fas fa-chevron-left'></i></button>";
    }

    $range = 3; 
    $startPage = max(1, $currentPage - 1);
    $endPage = min($totalPages, $startPage + $range - 1);
    
    if ($endPage === $totalPages) {
        $startPage = max(1, $endPage - $range + 1);
    }

    // Page Numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        $isActive = ($i === $currentPage);
        $activeClass = $isActive ? 'active bg-success text-white border-success' : '';
        echo "<a href='?page={$i}{$queryParams}' class='btn btn-outline-secondary btn-sm pagination-btn {$activeClass}'>{$i}</a>";
    }

    // Next Button
    if ($currentPage < $totalPages) {
        $nextPage = $currentPage + 1;
        echo "<a href='?page={$nextPage}{$queryParams}' class='btn btn-outline-secondary btn-sm pagination-btn'><i class='fas fa-chevron-right'></i></a>";
    } else {
        echo "<button class='btn btn-outline-secondary btn-sm disabled'><i class='fas fa-chevron-right'></i></button>";
    }
    
    echo '</div>';
    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Dashboard | Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/nav.css">
    <link rel="stylesheet" href="../styles/users_dashboard.css">
    <style>
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #198754;
            font-weight: 600;
            border-bottom: 3px solid #198754;
        }
        .folder-tabs-container {
            overflow-x: auto;
            white-space: nowrap;
        }
        .folder-tabs-container::-webkit-scrollbar {
            height: 6px;
        }
        .folder-tabs-container::-webkit-scrollbar-thumb {
            background-color: #dee2e6;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php require_once "header.php"; ?>
    <?php require_once "nav.php"; ?>

    <main class="main-content">
        <div class="container-xl p-3 p-lg-4">
            
            <div class="d-flex justify-content-between align-items-center mb-3 mb-lg-4">
                <div>
                    <h2 class="fw-bold text-dark mb-1 fs-3 fs-lg-2">Study Dashboard</h2>
                    <p class="text-muted mb-0 small">Master your reviews with different study modes.</p>
                </div>
                <div></div> 
            </div>

            <div class="folder-tabs-container mb-4">
                <ul class="nav nav-tabs flex-nowrap border-bottom-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $subjectIdParam === 'uncategorized' ? 'active' : '' ?>" 
                           href="?subject_id=uncategorized&mode=<?= urlencode($currentMode) ?>">
                           📂 Uncategorized
                        </a>
                    </li>
                    <?php foreach ($subjects as $subj): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $subjectIdParam == $subj['id'] ? 'active' : '' ?>" 
                               href="?subject_id=<?= $subj['id'] ?>&mode=<?= urlencode($currentMode) ?>">
                               📂 <?= htmlspecialchars($subj['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="row g-3 g-lg-4 justify-content-center">
                <div class="col-lg-10">
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        
                        <div class="card-header bg-white border-0 py-3 px-3 px-lg-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <h5 class="fw-bold mb-0">Questions</h5>
                                <span class="badge bg-success bg-opacity-10 text-success">Page <?= $page ?> of <?= $totalPages ?></span>
                                
                                <a href="?page=<?= $page ?>&shuffle=<?= $isShuffled ? 'false' : 'true' ?>&mode=<?= $currentMode ?>&subject_id=<?= $subjectIdParam ?>" 
                                   class="btn btn-sm <?= $isShuffled ? 'btn-success' : 'btn-outline-secondary' ?> rounded-pill d-flex align-items-center gap-2 shuffle-btn"
                                   title="<?= $isShuffled ? 'Disable Shuffle' : 'Shuffle Questions' ?>">
                                    <i class="fas fa-random"></i>
                                    <span class="d-none d-sm-inline"><?= $isShuffled ? 'Shuffled' : 'Shuffle' ?></span>
                                </a>
                            </div>
                            
                            <div class="btn-group shadow-sm rounded-pill" role="group">
                                <button class="btn btn-sm btn-outline-success px-3 mode-btn active" id="btn-list" onclick="switchMode('list')">
                                    <i class="fas fa-list me-1"></i> List
                                </button>
                                <button class="btn btn-sm btn-outline-success px-3 mode-btn" id="btn-card" onclick="switchMode('card')">
                                    <i class="fas fa-clone me-1"></i> Cards
                                </button>
                                <button class="btn btn-sm btn-outline-success px-3 mode-btn" id="btn-notes" onclick="switchMode('notes')">
                                    <i class="fas fa-key me-1"></i> Key
                                </button>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div id="contentDisplay" class="min-vh-50">
                                <?php 
                                    $startIndex = ($page - 1) * $limit + 1;
                                    renderQuestionsList($questions, $startIndex); 
                                ?>
                            </div>
                        </div>

                        <?php renderPagination($totalPages, $page, $isShuffled, $currentMode, $subjectIdParam); ?>

                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="position-fixed bottom-0 start-50 translate-middle-x mb-4" style="z-index: 1050">
        <div id="copyToast" class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle text-success me-2"></i> Copied to clipboard!
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const questions = <?php echo json_encode($questions); ?>;
        const totalPages = <?php echo $totalPages; ?>;
        const currentPage = <?php echo $page; ?>;   
        const itemsPerPage = <?php echo $limit; ?>;
        const initialMode = "<?php echo $currentMode; ?>";
    </script>
    <script src="../js/user_dashboard.js"></script>
    <script>
        // Initialize mode from PHP session/URL
        if (typeof setMode === 'function') {
            setMode(initialMode);
        }

        // Wrapper function to update URL and Links when mode changes
        function switchMode(mode) {
            // Call the original view-switching function
            if (typeof setMode === 'function') {
                setMode(mode);
            }

            // Update URL without reloading
            const url = new URL(window.location);
            url.searchParams.set('mode', mode);
            window.history.pushState({}, '', url);

            // Update all pagination links and shuffle button to include the new mode
            document.querySelectorAll('.pagination-btn, .shuffle-btn').forEach(btn => {
                try {
                    const href = new URL(btn.href, window.location.origin);
                    href.searchParams.set('mode', mode);
                    btn.href = href.toString();
                } catch (e) {
                    console.error("Error updating link:", btn.href);
                }
            });
        }
    </script>
    <?php include "footer.php"; ?>
</body>
</html> 