<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
$totalScouts = $pdo->query("SELECT COUNT(*) FROM scouter")->fetchColumn();
$totalEvents = $pdo->query("SELECT COUNT(*) FROM event")->fetchColumn();

$lastEvent = $pdo->query("
    SELECT e.id, e.title, COUNT(a.id) as attendance_count 
    FROM event e
    LEFT JOIN attendance a ON e.id = a.event_id
    GROUP BY e.id
    ORDER BY e.event_date DESC 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$recentAttendances = $pdo->query("
    SELECT 
        s.full_name, 
        s.year, 
        s.taliaa, 
        e.title as event_title, 
        a.event_id, 
        a.attended_at, 
        a.attendance_score
    FROM attendance a
    JOIN scouter s ON a.user_id = s.id
    JOIN event e ON a.event_id = e.id
    ORDER BY a.attended_at DESC 
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scout Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .main-content {
            background-color: #FFFFFF;
            padding: 2.5rem;
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
        .card-title {
            color: #1F487E;
            font-weight: 600;
        }
        .display-4 {
            color: #000000;
            font-weight: 700;
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
        .dashboard-header {
            background: linear-gradient(90deg, #1F487E 0%, #E54B4B 100%);
            color: #FFFFFF;
            padding: 1rem 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .dashboard-header h2 {
            margin: 0;
            font-weight: 700;
        }
        .attendance-score-1 {
            color: #E54B4B;
            font-weight: bold;
        }
        .attendance-score-2 {
            color: #FFA500;
            font-weight: bold;
        }
        .attendance-score-3 {
            color: #28A745;
            font-weight: bold;
        }
        .scrollable-table {
            max-height: 500px;
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
    </style>
</head>
<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <main class="main-container">
        <div class="main-content">
            <div class="dashboard-header">
                <h2>Dashboard Overview</h2>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Total Scouts</h5>
                            <p class="display-4"><?= $totalScouts ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Total Events</h5>
                            <p class="display-4"><?= $totalEvents ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Last Event Attendance: <?= htmlspecialchars($lastEvent['title'] ?? 'No events') ?></h5>
                            <p class="display-4"><?= $lastEvent['attendance_count'] ?? 0 ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Attendances (Last 30)</h5>
                </div>
                <div class="card-body">
                    <div class="scrollable-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Scout Name</th>
                                    <th>Year</th>
                                    <th>Taliaa</th>
                                    <th>Event</th>
                                    <th>Attendance Score</th>
                                    <th>Date Attended</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAttendances as $attendance): ?>
                                <tr>
                                    <td><?= htmlspecialchars($attendance['full_name']) ?></td>
                                    <td><?= htmlspecialchars($attendance['year']) ?></td>
                                    <td><?= htmlspecialchars($attendance['taliaa']) ?></td>
                                    <td><?= htmlspecialchars($attendance['event_title']) ?></td>
                                    <td>
                                        <span class="attendance-score-<?= $attendance['attendance_score'] ?>">
                                            <?= $attendance['attendance_score'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y H:i', strtotime($attendance['attended_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include "includes/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>