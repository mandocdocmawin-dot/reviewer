<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/db.php";
$db = get_db();

$messageSent = false;
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = trim($_POST['subject'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');
    
    // Get email from session if logged in, otherwise from form
    $email = $_SESSION['user']['email'] ?? trim($_POST['email'] ?? '');
    $userId = $_SESSION['user']['id'] ?? null;

    if (empty($feedback)) {
        $error = "Please provide your feedback or suggestion.";
    } elseif (empty($email)) {
        $error = "Email is required.";
    } else {
        try {
            // Prepare INSERT statement
            $stmt = $db->prepare("INSERT INTO feedback (user_id, email, subject, content) VALUES (:user_id, :email, :subject, :content)");
            
            // Bind parameters
            $stmt->bindValue(':user_id', $userId, $userId ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':subject', $subject, SQLITE3_TEXT);
            $stmt->bindValue(':content', $feedback, SQLITE3_TEXT);
            
            // Execute the query
            // NOTE: The trigger in db.php will automatically detect this INSERT 
            // and create a notification for the admin.
            if ($stmt->execute()) {
                $messageSent = true;
            } else {
                $error = "Failed to save feedback.";
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['user']);
$user = $_SESSION['user'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback & Suggestions | ReviewHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../styles/schedule.css">
    <link rel="stylesheet" href="../styles/nav.css">
    <link rel="stylesheet" href="../styles/contact.css">
</head>
<body>
    <?php require_once "header.php"; ?>
    <?php include "nav.php"; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-10">
                <div class="glass-card p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-lightbulb fa-2x"></i>
                        </div>
                        <h2 class="fw-bold">Help Us Grow</h2>
                        <p class="text-secondary">Do you have a suggestion for a new feature? Found a bug? We'd love to hear from you.</p>
                    </div>

                    <?php if ($messageSent): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                            <h4 class="fw-bold">Thank You!</h4>
                            <p class="text-secondary">Your feedback has been received. We appreciate you helping us improve ReviewHub.</p>
                            <a href="users_dashboard.php" class="btn btn-brand px-4 mt-2">Back to Dashboard</a>
                        </div>
                    <?php else: ?>
                        <form action="contact.php" method="POST">
                            <?php if ($error): ?>
                                <div class="alert alert-danger rounded-3 mb-4"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-uppercase">Reason for Contact</label>
                                <select name="subject" class="form-select form-control">
                                    <option value="Feature Suggestion">Feature Suggestion</option>
                                    <option value="Bug Report">Bug Report</option>
                                    <option value="General Feedback">General Feedback</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <?php if (!$is_logged_in): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-uppercase">Email Address</label>
                                    <input type="email" name="email" required class="form-control" placeholder="your@email.com">
                                </div>
                            <?php endif; ?>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-uppercase">Your Message</label>
                                <textarea name="feedback" required class="form-control" rows="5" placeholder="Share your thoughts or suggestions for the app..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-brand w-100 fw-bold shadow-sm">
                                <i class="fas fa-paper-plane me-2"></i> Submit Feedback
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-5">
                    <p class="text-muted small">
                        Join our Discord community for faster support.<br>
                        &copy; 2025 ReviewHub Team
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php include "footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>