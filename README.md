Reviewer Application - README, 

ReviewHub is a small PHP + SQLite web application that helps students manage review questions, study sessions, and academic tasks. It includes role‑based access, dashboards for admins and users, and simple notification helpers.

---

### Features

- **Authentication & Roles**  
  - Register and log in with email/username and password.  
  - First registered user becomes **admin** automatically.  
  - Additional accounts are controlled through an **allow‑list** (`account` table) managed by the admin.

- **Admin Dashboard (`src/admin_dashboard.php`)**  
  - Manage the email allow‑list used for new registrations.  
  - Assign roles (admin / user) to allowed emails.  
  - Keep users and allow‑list entries in sync.

- **User Review Management (`src/create_review.php`)**  
  - Create, read, update, and delete review items (question + answer).  
  - Pagination for long lists of reviews.  
  - Flash messages for CRUD actions.  
  - Modern Bootstrap‑based interface.

- **Study Dashboard (`src/users_dashboard.php`)**  
  - Paginated list of questions for the current user.  
  - Optional shuffle mode for randomized practice.  
  - Multiple view modes (list / cards / key) powered by JavaScript.

- **Weekly Schedule (`src/schedule.php`)**  
  - Manage weekly schedules (classes / review sessions).  
  - Filter by day and paginate when there are many items.  
  - Status badges (upcoming, current, done, etc.).

- **Task / Activity Tracker (`src/user_tracker.php`)**  
  - Track exams, quizzes, projects, assignments, and activities.  
  - Mark tasks as done/undone, edit, delete, and clear completed items.  
  - Visual urgency indicators: overdue / today / tomorrow / ongoing.

- **Notifications & Feedback**  
  - Feedback form stored in `feedback` table.  
  - SQLite triggers create entries in `notifications` when:  
    - new feedback is submitted (notifies admins),  
    - deadlines or schedules are coming up,  
    - related items are updated or deleted.

- **UI / Frontend**  
  - Responsive layout using **Bootstrap 5**, Google Fonts, and Font Awesome icons.  
  - Custom CSS in the `styles/` folder.  
  - JavaScript modules in `js/` for pagination, dashboards, schedule, and tracker behavior.

---

### Technology Stack

- PHP (tested with PHP 8+)
- SQLite3 (via PHP `SQLite3` extension)
- Bootstrap 5, Font Awesome, Google Fonts

---

### Setup & Installation

1. **Prerequisites**  
   - PHP with the `SQLite3` extension enabled.  
   - A local or hosted web server (Apache, Nginx, built‑in PHP server, etc.).

2. **Clone or copy the project**  
   Place the project folder (this repository) inside your web server’s document root.

3. **Database initialization**  
   - No manual SQL is required.  
   - On first request, `src/db.php` will automatically create a SQLite database file:
     - File: `database.db` (in the project root)  
     - Tables: `roles`, `users`, `account`, `content`, `schedules`, `student_activities`, `feedback`, `notifications`, plus triggers for notifications.

4. **Run the application**  
   - Point your browser to `index.php` (for example: `http://localhost/reviewer/index.php`).  
   - From there you can register, log in, and access the dashboards.

---

### Usage Overview

- **First‑time setup**  
  1. Visit `index.php`.  
  2. Use the **Get Started** / **Register** modal to create the first account. This user becomes the **admin**.  
  3. Log in as admin and open `Admin Dashboard` to add allowed emails and assign roles for future users.

- **Regular user flow**  
  - Log in from `index.php`.  
  - Manage review questions in **Review Questions** (`create_review.php`).  
  - Study using the **Study Dashboard** (`users_dashboard.php`) with pagination and shuffle mode.  
  - Plan weekly sessions in **Schedule** (`schedule.php`).  
  - Track tasks and deadlines in **Tracker** (`user_tracker.php`).

---

### Database Notes

- **Database file**: `database.db` in the project root.  
- **Key tables**:  
  - `roles`: role definitions (`admin`, `user`).  
  - `users`: registered application users.  
  - `account`: allow‑list of emails and roles for registration.  
  - `content`: stored review items (`question`, `answer`).  
  - `schedules`: weekly schedule entries.  
  - `student_activities`: tasks and activities with due dates.  
  - `feedback`: contact/feedback submissions.  
  - `notifications`: generated notifications based on triggers.

If you need to reset the app, you can safely delete `database.db`; it will be recreated on next request (you will also lose all existing data).

---

### Security Notes

- Passwords are currently stored as plain text in the database.  
- For production use, you should:  
  - Hash passwords (e.g. using `password_hash` / `password_verify`).  
  - Serve the app over HTTPS.  
  - Harden session and input validation according to your deployment environment.

---

### Project Structure (high level)

- `index.php` – Landing page with login and registration modals.  
- `src/` – Core PHP files (dashboards, CRUD pages, DB logic, layouts).  
- `js/` – Frontend scripts (pagination, dashboard interactions, schedule, tracker, etc.).  
- `styles/` – CSS for each section / page.  
- `database.db` – SQLite database created at runtime.