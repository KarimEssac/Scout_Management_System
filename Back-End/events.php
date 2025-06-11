<?php
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
session_start();
require_once "config/db.php";
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT role FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$admin_role = $admin['role'];
if ($admin_role === 'dashboard' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $_POST = array_map(function($item) {
        return mb_convert_encoding($item, 'UTF-8', 'auto');
    }, $_POST);

    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $attendance_score = $_POST['attendance_score'];
    $created_by = $_SESSION['admin_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO event (title, description, event_date, attendance_score, created_by, open) 
                             VALUES (:title, :description, :event_date, :attendance_score, :created_by, 1)");
        $stmt->bindParam(':title', $title, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':event_date', $event_date, PDO::PARAM_STR);
        $stmt->bindParam(':attendance_score', $attendance_score, PDO::PARAM_INT);
        $stmt->bindParam(':created_by', $created_by, PDO::PARAM_INT);
        $stmt->execute();
        
        header("Location: events.php");
        exit();
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        die("Error saving event. Please check logs for details.");
    }
}

if ($admin_role === 'dashboard' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $event_date = $_POST['event_date'];
    $attendance_score = $_POST['attendance_score'];

    $stmt = $pdo->prepare("UPDATE event SET 
                          title = ?, 
                          description = ?, 
                          event_date = ?, 
                          attendance_score = ? 
                          WHERE id = ?");
    $stmt->execute([$title, $description, $event_date, $attendance_score, $id]);
    
    header("Location: events.php");
    exit();
}
if ($admin_role === 'dashboard' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_event'])) {
    $id = $_POST['id'];
    $admin_id = $_SESSION['admin_id'];
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE event SET open = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("UPDATE scouter 
                              SET missed_events_count = missed_events_count + 1 
                              WHERE id NOT IN (
                                  SELECT user_id FROM attendance WHERE event_id = ?
                              ) AND id NOT IN (
                                  SELECT scouter_id FROM apologies WHERE event_id = ? AND status = 'approved'
                              )");
        $stmt->execute([$id, $id]);
        $stmt = $pdo->prepare("SELECT id, full_name FROM scouter 
                              WHERE missed_events_count = 2 
                              AND id NOT IN (
                                  SELECT user_id FROM attendance WHERE event_id = ?
                              )
                              AND id NOT IN (
                                  SELECT scouter_id FROM apologies WHERE event_id = ? AND status = 'approved'
                              )");
        $stmt->execute([$id, $id]);
        $scoutersWith2Missed = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($scoutersWith2Missed as $scouter) {
            $stmt = $pdo->prepare("INSERT INTO scouter_flags 
                                 (scouter_id, flag_type, description, flagged_at) 
                                 VALUES (?, ?, ?, NOW())");
            $stmt->execute([
                $scouter['id'],
                'تحذير',
                'غاب مرتين بدون عذر، وده أول إنذار ليه. لازم ينتبه علشان ميخدش إجراء أكبر بعد كده.'
            ]);
        }
        $stmt = $pdo->prepare("SELECT id, full_name FROM scouter 
                              WHERE missed_events_count = 4 
                              AND id NOT IN (
                                  SELECT user_id FROM attendance WHERE event_id = ?
                              )
                              AND id NOT IN (
                                  SELECT scouter_id FROM apologies WHERE event_id = ? AND status = 'approved'
                              )");
        $stmt->execute([$id, $id]);
        $scoutersWith4Missed = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($scoutersWith4Missed as $scouter) {
            $stmt = $pdo->prepare("INSERT INTO scouter_flags 
                                 (scouter_id, flag_type, description, flagged_at) 
                                 VALUES (?, ?, ?, NOW())");
            $stmt->execute([
                $scouter['id'],
                'مجلس شرف',
                'الطالب غاب ٤ مرات بدون عذر، وبالتالي هيتعرض للتحقيق قدام مجلس الشرف. لازم يوضح أسباب الغياب ويواجه العقوبة المحتملة.'
            ]);
        }
        
        $pdo->commit();
        
        header("Location: events.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error: " . $e->getMessage());
        die("Error closing event. Please check logs for details.");
    }
}
if ($admin_role === 'dashboard' && isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE scouter 
                              SET missed_events_count = missed_events_count - 1 
                              WHERE id NOT IN (
                                  SELECT user_id FROM attendance WHERE event_id = ?
                              )");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM qr_codes WHERE event_id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE event_id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM apologies WHERE event_id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM event WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        
        header("Location: events.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error: " . $e->getMessage());
        die("Error deleting event. Please check logs for details.");
    }
}
$events = $pdo->query("SELECT e.*, a.full_name as created_by_name 
                      FROM event e
                      JOIN admins a ON e.created_by = a.id
                      ORDER BY e.event_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$attendance_counts = [];
$absent_scouters = [];
foreach ($events as $event) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE event_id = ?");
    $stmt->execute([$event['id']]);
    $attendance_counts[$event['id']] = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT s.id, s.full_name 
                          FROM scouter s
                          WHERE s.id NOT IN (
                              SELECT user_id FROM attendance WHERE event_id = ?
                          )
                          ORDER BY s.full_name");
    $stmt->execute([$event['id']]);
    $absent_scouters[$event['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management | KTL Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    .main-content {
        background-color: #FFFFFF;
        padding: 2.5rem;
        overflow-x: hidden; 
    }
    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
    }
    .card-header {
        background-color: #1F487E;
        color: #FFFFFF;
        border-radius: 10px 10px 0 0;
        font-weight: 600;
    }
    .table th {
        background-color: #1F487E;
        color: #FFFFFF;
        font-weight: 600;
        position: sticky;
        top: 0;
    }
    .table td {
        color: #000000;
    }
    .table tr:hover {
        background-color: rgba(31, 5, 126, 0.1);
    }
    .btn-primary {
        background-color: #1F487E;
        border: none;
    }
    .btn-primary:hover {
        background-color: #15325c;
    }
    .btn-danger {
        background-color: #E54B4B;
        border: none;
    }
    .btn-danger:hover {
        background-color: #c23333;
    }
    .btn-warning {
        background-color: #FFA500;
        border: none;
    }
    .btn-warning:hover {
        background-color: #e59400;
    }
    .scrollable-table {
        max-height: 600px;
        overflow-y: auto;
        margin-bottom: 20px;
    }
    .scrollable-table::-webkit-scrollbar {
        width: 8px;
    }
    .scrollable-table::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .scrollable-table::-webkit-scrollbar-thumb {
        background: #1F487E;
        border-radius: 10px;
    }
    .scrollable-table::-webkit-scrollbar-thumb:hover {
        background: #6D3D8F;
    }
    .form-control:focus {
        border-color: #1F487E;
        box-shadow: 0 0 5px rgba(31, 72, 126, 0.3);
    }
    .modal-header {
        background-color: #1F487E;
        color: white;
    }
    .events-carousel-container {
        position: relative;
        margin-bottom: 30px;
        max-width: 100%; 
        overflow: hidden; 
    }
    .events-carousel {
        display: flex;
        overflow-x: auto;
        gap: 15px;
        padding: 15px;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none; 
        max-width: 100%; 
        scroll-behavior: smooth; 
    }
    .events-carousel::-webkit-scrollbar {
        display: none; 
    }
    .event-card {
        min-width: 280px;
        max-width: 280px;
        scroll-snap-align: start;
        cursor: pointer;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    .event-card:hover {
        transform: scale(1.03);
    }
    .event-score-1 {
        color: #E54B4B;
        font-weight: bold;
    }
    .event-score-2 {
        color: #FFA500;
        font-weight: bold;
    }
    .event-score-3 {
        color: #28A745;
        font-weight: bold;
    }
    .badge-attendance {
        background-color: #6D3D8F;
        color: white;
        font-size: 0.9rem;
        padding: 5px 10px;
        border-radius: 20px;
    }
    .badge-absent {
        background-color: #E54B4B;
        color: white;
        font-size: 0.9rem;
        padding: 5px 10px;
        border-radius: 20px;
    }
    .carousel-nav {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 10px; 
        position: static; 
        transform: none; 
    }
    .carousel-btn {
        background: rgba(31, 72, 126, 0.7);
        color: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .carousel-btn:hover {
        background: #1F487E;
    }
    .carousel-btn.hidden {
        opacity: 0;
        pointer-events: none;
    }
    .absent-scouters {
        max-height: 150px;
        overflow-y: auto;
        margin-top: 10px;
        border-top: 1px solid #eee;
        padding-top: 10px;
    }
    .absent-scouters-list {
        list-style-type: none;
        padding-left: 0;
        margin-bottom: 0;
    }
    .absent-scouters-list li {
        padding: 3px 0;
        font-size: 0.85rem;
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 3px 6px;
        border-radius: 10px;
    }
    .status-open {
        background-color: #28A745;
        color: white;
    }
    .status-closed {
        background-color: #6c757d;
        color: white;
    }
    .modal-header.bg-warning {
        background-color: #FFA500 !important;
    }
    .form-control:disabled, .form-check-input:disabled, textarea:disabled {
        background-color: #e9ecef;
        opacity: 1;
    }
    @media (max-width: 768px) {
        .event-card {
            min-width: 200px;
            max-width: 200px;
        }
        .events-carousel {
            gap: 10px;
            padding: 10px;
        }
    }
    @media (max-width: 576px) {
        .event-card {
            min-width: 160px;
            max-width: 160px;
        }
        .events-carousel {
            gap: 8px;
            padding: 8px;
        }
        .carousel-btn {
            width: 32px;
            height: 32px;
            font-size: 0.8rem;
        }
    }
</style>
</head>
<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <main class="main-container">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Events Management</h2>
                <?php if ($admin_role === 'dashboard'): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                        <i class="fas fa-plus me-2"></i>Add Event
                    </button>
                <?php endif; ?>
            </div>

            <!-- Events Carousel -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Latest Events</h5>
                </div>
                <div class="card-body p-0">
                    <div class="events-carousel-container">
                        <div class="events-carousel" id="eventsCarousel">
                            <?php foreach (array_slice($events, 0, 10) as $event): ?>
                            <div class="card event-card" 
                                 data-bs-toggle="modal" 
                                 data-bs-target="#eventDetailsModal"
                                 data-id="<?= $event['id'] ?>"
                                 data-title="<?= htmlspecialchars($event['title']) ?>"
                                 data-description="<?= htmlspecialchars($event['description']) ?>"
                                 data-event_date="<?= date('M j, Y', strtotime($event['event_date'])) ?>"
                                 data-attendance_score="<?= $event['attendance_score'] ?>"
                                 data-created_by="<?= htmlspecialchars($event['created_by_name']) ?>"
                                 data-attendance_count="<?= $attendance_counts[$event['id']] ?>"
                                 data-open="<?= $event['open'] ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h5 class="card-title"><?= htmlspecialchars($event['title']) ?></h5>
                                        <span class="status-badge <?= $event['open'] ? 'status-open' : 'status-closed' ?>">
                                            <?= $event['open'] ? 'Open' : 'Closed' ?>
                                        </span>
                                    </div>
                                    <p class="card-text text-muted">
                                        <i class="fas fa-calendar-day me-2"></i>
                                        <?= date('M j, Y', strtotime($event['event_date'])) ?>
                                    </p>
                                    <p class="card-text">
                                        <span class="event-score-<?= $event['attendance_score'] ?>">
                                            Score: <?= $event['attendance_score'] ?>
                                        </span>
                                    </p>
                                    <span class="badge badge-attendance">
                                        <i class="fas fa-users me-1"></i>
                                        <?= $attendance_counts[$event['id']] ?> attended
                                    </span>
                                    <span class="badge badge-absent ms-2">
                                        <i class="fas fa-user-times me-1"></i>
                                        <?= count($absent_scouters[$event['id']]) ?> absent
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="carousel-nav">
                            <button class="carousel-btn prev-btn" onclick="scrollCarousel(-1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="carousel-btn next-btn" onclick="scrollCarousel(1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Events Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">All Events</h5>
                </div>
                <div class="card-body">
                    <div class="scrollable-table">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Score</th>
                                    <th>Attendance</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?= htmlspecialchars($event['id']) ?></td>
                                    <td><?= htmlspecialchars($event['title']) ?></td>
                                    <td><?= date('M j, Y', strtotime($event['event_date'])) ?></td>
                                    <td>
                                        <span class="event-score-<?= $event['attendance_score'] ?>">
                                            <?= $event['attendance_score'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-attendance">
                                            <?= $attendance_counts[$event['id']] ?>
                                        </span>
                                        <span class="badge badge-absent ms-1">
                                            <?= count($absent_scouters[$event['id']]) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $event['open'] ? 'status-open' : 'status-closed' ?>">
                                            <?= $event['open'] ? 'Open' : 'Closed' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($event['created_by_name']) ?></td>
                                    <td>
                                        <?php if ($admin_role === 'dashboard'): ?>
                                            <button class="btn btn-sm btn-primary edit-btn" 
                                                    data-id="<?= $event['id'] ?>"
                                                    data-title="<?= htmlspecialchars($event['title']) ?>"
                                                    data-description="<?= htmlspecialchars($event['description']) ?>"
                                                    data-event_date="<?= htmlspecialchars($event['event_date']) ?>"
                                                    data-attendance_score="<?= $event['attendance_score'] ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($event['open']): ?>
                                                <button class="btn btn-sm btn-warning disable-event-btn" 
                                                        data-id="<?= $event['id'] ?>">
                                                    <i class="fas fa-lock"></i> Close
                                                </button>
                                            <?php endif; ?>
                                            <a href="events.php?delete=<?= $event['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this event? All related attendance records will also be deleted.')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-primary view-btn" 
                                                    data-id="<?= $event['id'] ?>"
                                                    data-title="<?= htmlspecialchars($event['title']) ?>"
                                                    data-description="<?= htmlspecialchars($event['description']) ?>"
                                                    data-event_date="<?= htmlspecialchars($event['event_date']) ?>"
                                                    data-attendance_score="<?= $event['attendance_score'] ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Event Modal -->
    <?php if ($admin_role === 'dashboard'): ?>
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEventModalLabel">Add New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="events.php" accept-charset="UTF-8">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="event_date" class="form-label">Event Date</label>
                        <input type="datetime-local" class="form-control" id="event_date" name="event_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="attendance_score" class="form-label">Attendance Score</label>
                        <input type="number" class="form-control" id="attendance_score" name="attendance_score" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
                </div>
            </form>
        </div>
    </div>
</div>
    <?php endif; ?>

    <!-- Edit/View Event Modal -->
    <div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEventModalLabel"><?= $admin_role === 'viewer' ? 'View Event' : 'Edit Event' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="events.php">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required <?= $admin_role === 'viewer' ? 'disabled' : '' ?>>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" <?= $admin_role === 'viewer' ? 'disabled' : '' ?>></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_event_date" class="form-label">Event Date</label>
                            <input type="datetime-local" class="form-control" id="edit_event_date" name="event_date" required <?= $admin_role === 'viewer' ? 'disabled' : '' ?>>
                        </div>
                        <div class="mb-3">
                            <label for="edit_attendance_score" class="form-label">Attendance Score</label>
                            <input type="number" class="form-control" id="edit_attendance_score" name="attendance_score" 
                                   min="1" max="3" required <?= $admin_role === 'viewer' ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <?php if ($admin_role === 'dashboard'): ?>
                            <button type="submit" name="update_event" class="btn btn-primary">Update Event</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Close Event Modal -->
    <?php if ($admin_role === 'dashboard'): ?>
    <div class="modal fade" id="disableEventModal" tabindex="-1" aria-labelledby="disableEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="disableEventModalLabel">Close Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="events.php">
                    <input type="hidden" id="disable_event_id" name="id">
                    <div class="modal-body">
                        <p>Are you sure you want to close this event? This will:</p>
                        <ul>
                            <li>Mark the event as closed</li>
                            <li>Increase missed events count for absent scouters without approved apologies</li>
                            <li>No further attendance will be allowed for this event</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="disable_event" class="btn btn-warning">Yes, Close Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Event Details Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventDetailsModalLabel">Event Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 id="detail_title"></h4>
                        <span id="detail_status_badge" class="status-badge"></span>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="text-muted">
                                <i class="fas fa-calendar-day me-2"></i>
                                <span id="detail_event_date"></span>
                            </p>
                            <p>
                                <strong>Score:</strong> 
                                <span id="detail_attendance_score"></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <strong>Created By:</strong> 
                                <span id="detail_created_by"></span>
                            </p>
                            <p>
                                <strong>Attendance:</strong> 
                                <span id="detail_attendance_count" class="badge badge-attendance"></span>
                                <span id="detail_absent_count" class="badge badge-absent ms-1"></span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Description:</h6>
                        <p id="detail_description" class="text-muted"></p>
                    </div>
                    
                    <div>
                        <h6>Scouters who didn't attend:</h6>
                        <div class="absent-scouters">
                            <ul class="absent-scouters-list" id="absentScoutersList">
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function populateEventModal(button, isViewMode) {
            const eventData = {
                id: button.getAttribute('data-id'),
                title: button.getAttribute('data-title'),
                description: button.getAttribute('data-description'),
                event_date: button.getAttribute('data-event_date'),
                attendance_score: button.getAttribute('data-attendance_score')
            };
            document.getElementById('edit_id').value = eventData.id;
            document.getElementById('edit_title').value = eventData.title;
            document.getElementById('edit_description').value = eventData.description;
            const date = new Date(eventData.event_date);
            const formattedDate = date.toISOString().slice(0, 16);
            document.getElementById('edit_event_date').value = formattedDate;
            document.getElementById('edit_attendance_score').value = eventData.attendance_score;
            const editModal = new bootstrap.Modal(document.getElementById('editEventModal'));
            editModal.show();
        }

        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                populateEventModal(this, false);
            });
        });

        document.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', function() {
                populateEventModal(this, true);
            });
        });
        document.querySelectorAll('.disable-event-btn').forEach(button => {
            button.addEventListener('click', function() {
                const eventId = this.getAttribute('data-id');
                document.getElementById('disable_event_id').value = eventId;
                const disableModal = new bootstrap.Modal(document.getElementById('disableEventModal'));
                disableModal.show();
            });
        });
        document.querySelectorAll('.event-card').forEach(card => {
            card.addEventListener('click', function() {
                const eventData = {
                    id: this.getAttribute('data-id'),
                    title: this.getAttribute('data-title'),
                    description: this.getAttribute('data-description'),
                    event_date: this.getAttribute('data-event_date'),
                    attendance_score: this.getAttribute('data-attendance_score'),
                    created_by: this.getAttribute('data-created_by'),
                    attendance_count: this.getAttribute('data-attendance_count'),
                    open: this.getAttribute('data-open')
                };
                
                document.getElementById('detail_title').textContent = eventData.title;
                document.getElementById('detail_description').textContent = eventData.description || 'No description provided';
                document.getElementById('detail_event_date').textContent = eventData.event_date;
                document.getElementById('detail_created_by').textContent = eventData.created_by;
                document.getElementById('detail_attendance_count').textContent = eventData.attendance_count + ' attended';
                const totalScouters = <?= $pdo->query("SELECT COUNT(*) FROM scouter")->fetchColumn() ?>;
                const absentCount = totalScouters - parseInt(eventData.attendance_count);
                document.getElementById('detail_absent_count').textContent = absentCount + ' absent';
                const statusBadge = document.getElementById('detail_status_badge');
                statusBadge.textContent = eventData.open === '1' ? 'Open' : 'Closed';
                statusBadge.className = eventData.open === '1' ? 'status-badge status-open' : 'status-badge status-closed';
                const scoreElement = document.getElementById('detail_attendance_score');
                scoreElement.textContent = eventData.attendance_score;
                scoreElement.className = 'event-score-' + eventData.attendance_score;
                fetch(`get_absent_scouters.php?event_id=${eventData.id}`)
                    .then(response => response.json())
                    .then(data => {
                        const list = document.getElementById('absentScoutersList');
                        list.innerHTML = '';
                        
                        if (data.length === 0) {
                            list.innerHTML = '<li class="text-muted">All scouters attended this event</li>';
                        } else {
                            data.forEach(scouter => {
                                const li = document.createElement('li');
                                li.textContent = scouter.full_name;
                                list.appendChild(li);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error loading absent scouters:', error);
                        document.getElementById('absentScoutersList').innerHTML = '<li class="text-danger">Error loading absent scouters</li>';
                    });
                
                const detailsModal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
                detailsModal.show();
            });
        });
        const eventDetailsModal = document.getElementById('eventDetailsModal');
        eventDetailsModal.addEventListener('hidden.bs.modal', function () {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => {
                backdrop.parentNode.removeChild(backdrop);
            });
            document.body.style.overflow = 'auto';
            document.body.style.paddingRight = '0';
        });
        function scrollCarousel(direction) {
            const carousel = document.getElementById('eventsCarousel');
            const scrollAmount = 300; 
            carousel.scrollBy({
                left: direction * scrollAmount,
                behavior: 'smooth'
            });
        }

        const carousel = document.getElementById('eventsCarousel');
        const prevBtn = document.querySelector('.prev-btn');
        const nextBtn = document.querySelector('.next-btn');

        carousel.addEventListener('scroll', function() {
            if (carousel.scrollLeft <= 10) {
                prevBtn.classList.add('hidden');
            } else {
                prevBtn.classList.remove('hidden');
            }
            if (carousel.scrollLeft >= carousel.scrollWidth - carousel.clientWidth - 10) {
                nextBtn.classList.add('hidden');
            } else {
                nextBtn.classList.remove('hidden');
            }
        });

        if (carousel.scrollLeft <= 10) {
            prevBtn.classList.add('hidden');
        }
        if (carousel.scrollWidth <= carousel.clientWidth) {
            nextBtn.classList.add('hidden');
        }
    </script>
</body>
</html>