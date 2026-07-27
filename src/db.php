<?php
function get_db(): SQLite3 
{
    static $db = null;
    if ($db !== null) {
        return $db;
    }
    
    $dbPath = __DIR__ . '/../database.db';
    $db = new SQLite3($dbPath);
    $db->enableExceptions(true);

    $db->exec('PRAGMA foreign_keys = ON;');

    $db->exec('CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT
    )');

    $roleCount = $db->querySingle("SELECT COUNT(*) FROM roles");
    if ($roleCount == 0) {
        $db->exec("
            INSERT INTO roles (name, description) VALUES
            ('admin', 'Full access to the system'),
            ('user', 'Regular user with standard access');
        ");
    }

    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER NOT NULL,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS account (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER NOT NULL,
        email TEXT NOT NULL UNIQUE,
        FOREIGN KEY (role_id) REFERENCES roles(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS content (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        question TEXT NOT NULL,
        answer TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // --- NEW SUBJECTS TABLE ---
    $db->exec('CREATE TABLE IF NOT EXISTS subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // --- ADD SUBJECT_ID TO CONTENT TABLE ---
    // Safely check if the column already exists before altering
    $resultContent = $db->query("PRAGMA table_info(content)");
    $hasSubjectId = false;
    while ($row = $resultContent->fetchArray()) {
        if ($row['name'] === 'subject_id') {
            $hasSubjectId = true;
            break;
        }
    }
    if (!$hasSubjectId) {
        $db->exec("ALTER TABLE content ADD COLUMN subject_id INTEGER REFERENCES subjects(id)");
    }

    // $db->exec("DROP TABLE IF EXISTS content");

    $db->exec('CREATE TABLE IF NOT EXISTS schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        day TEXT NOT NULL,
        time TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT "Upcoming",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // $db->exec("DROP TABLE IF EXISTS schedules");

    $db->exec('CREATE TABLE IF NOT EXISTS student_activities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        type TEXT NOT NULL,
        due_date DATETIME NOT NULL,
        is_completed INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // $db->exec("DROP TABLE IF EXISTS student_activities");

    $db->exec('CREATE TABLE IF NOT EXISTS feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        email TEXT,
        subject TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        type TEXT NOT NULL,
        message TEXT NOT NULL,
        is_read INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        reference_id INTEGER, 
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // $db->exec("DROP TABLE IF EXISTS notifications");


    $result = $db->query("PRAGMA table_info(notifications)");
    $hasRefId = false;
    while ($row = $result->fetchArray()) {
        if ($row['name'] === 'reference_id') {
            $hasRefId = true;
            break;
        }
    }
    if (!$hasRefId) {
        $db->exec("ALTER TABLE notifications ADD COLUMN reference_id INTEGER");
    }

    $db->exec("CREATE TRIGGER IF NOT EXISTS notify_admin_on_feedback
    AFTER INSERT ON feedback
    BEGIN
        INSERT INTO notifications (user_id, type, message, created_at, reference_id)
        SELECT u.id, 'feedback', 'New Feedback: ' || NEW.subject, datetime('now'), NEW.id
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.name = 'admin';
    END");

    $db->exec("CREATE TRIGGER IF NOT EXISTS notify_on_upcoming_deadline
    AFTER INSERT ON student_activities
    WHEN date(NEW.due_date) = date('now', '+8 hours', '+1 day')
    BEGIN
        INSERT INTO notifications (user_id, type, message, created_at, reference_id)
        VALUES (NEW.user_id, 'deadline', 'Upcoming Deadline: ' || NEW.title || ' is due tomorrow.', datetime('now'), NEW.id);
    END");

    $db->exec("CREATE TRIGGER IF NOT EXISTS cleanup_notification_on_deadline_delete
    AFTER DELETE ON student_activities
    BEGIN
        DELETE FROM notifications WHERE reference_id = OLD.id AND type = 'deadline';
    END");

    $db->exec("CREATE TRIGGER IF NOT EXISTS cleanup_notification_on_deadline_update
    AFTER UPDATE ON student_activities
    WHEN OLD.due_date != NEW.due_date OR OLD.title != NEW.title
    BEGIN
        DELETE FROM notifications WHERE reference_id = OLD.id AND type = 'deadline';

        INSERT INTO notifications (user_id, type, message, created_at, reference_id)
        SELECT 
            NEW.user_id, 
            'deadline', 
            'Upcoming Deadline: ' || NEW.title || ' is due tomorrow.', 
            datetime('now'), 
            NEW.id
        WHERE date(NEW.due_date) = date('now', '+8 hours', '+1 day');
    END");

    $db->exec("CREATE TRIGGER IF NOT EXISTS notify_on_upcoming_schedule
    AFTER INSERT ON schedules
    WHEN NEW.day = (
        CASE strftime('%w', 'now', '+8 hours', '+1 day')
            WHEN '0' THEN 'Sunday'
            WHEN '1' THEN 'Monday'
            WHEN '2' THEN 'Tuesday'
            WHEN '3' THEN 'Wednesday'
            WHEN '4' THEN 'Thursday'
            WHEN '5' THEN 'Friday'
            WHEN '6' THEN 'Saturday'
        END
    )
    BEGIN
        INSERT INTO notifications (user_id, type, message, created_at, reference_id)
        VALUES (NEW.user_id, 'schedule', 'Reminder: You have ' || NEW.title || ' scheduled for tomorrow (' || NEW.day || ').', datetime('now'), NEW.id);
    END");

    $db->exec("CREATE TRIGGER IF NOT EXISTS cleanup_notification_on_schedule_delete
    AFTER DELETE ON schedules
    BEGIN
        DELETE FROM notifications WHERE reference_id = OLD.id AND type = 'schedule';
    END");

    $db->exec("CREATE TRIGGER IF NOT EXISTS cleanup_notification_on_schedule_update
    AFTER UPDATE ON schedules
    WHEN OLD.day != NEW.day OR OLD.time != NEW.time
    BEGIN
        DELETE FROM notifications WHERE reference_id = OLD.id AND type = 'schedule';

        INSERT INTO notifications (user_id, type, message, created_at, reference_id)
        SELECT 
            NEW.user_id, 
            'schedule', 
            'Reminder: You have ' || NEW.title || ' scheduled for tomorrow (' || NEW.day || ').', 
            datetime('now'), 
            NEW.id
        WHERE NEW.day = (
            CASE strftime('%w', 'now', '+8 hours', '+1 day')
                WHEN '0' THEN 'Sunday'
                WHEN '1' THEN 'Monday'
                WHEN '2' THEN 'Tuesday'
                WHEN '3' THEN 'Wednesday'
                WHEN '4' THEN 'Thursday'
                WHEN '5' THEN 'Friday'
                WHEN '6' THEN 'Saturday'
            END
        );
    END");

    return $db;
}

function getUnreadNotificationCount($user_id) {
    $db = get_db();
    
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0');
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    return $row ? (int)$row['count'] : 0;
}
?>