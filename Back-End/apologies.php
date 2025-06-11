<?php
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
if ($admin_role === 'dashboard' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $apology_id = intval($_POST['apology_id']);
    $status = trim($_POST['status']);
    $reviewer_reason = trim($_POST['reviewer_reason'] ?? '');
    $admin_id = $_SESSION['admin_id'];
    $valid_statuses = ['accepted' => 'approved', 'declined' => 'declined'];
    
    if (!isset($valid_statuses[$status])) {
        $_SESSION['error'] = "Invalid status provided.";
        header("Location: apologies.php");
        exit();
    }
    
    $db_status = $valid_statuses[$status];
    if ($apology_id <= 0) {
        $_SESSION['error'] = "Invalid apology ID provided.";
        header("Location: apologies.php");
        exit();
    }
    
    $pdo->beginTransaction();
    
    try {
        $checkStmt = $pdo->prepare("SELECT a.id, a.status, a.scouter_id, a.event_id, e.open 
                                  FROM apologies a 
                                  JOIN event e ON a.event_id = e.id 
                                  WHERE a.id = ?");
        $checkStmt->execute([$apology_id]);
        $existingApology = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingApology) {
            throw new Exception("Apology not found (ID: $apology_id).");
        }
        
        if ($existingApology['status'] !== 'pending') {
            throw new Exception("This apology has already been reviewed. Current status: " . $existingApology['status']);
        }
        
        $updateStmt = $pdo->prepare("UPDATE apologies SET status = ?, reviewed_by = ?, reviewer_reason = ? WHERE id = ?");
        $updateResult = $updateStmt->execute([$db_status, $admin_id, $reviewer_reason, $apology_id]);
        
        if (!$updateResult) {
            $errorInfo = $updateStmt->errorInfo();
            throw new Exception("Database update failed: " . $errorInfo[2]);
        }
        
        if ($status === 'accepted' && !$existingApology['open']) {
            $decrementStmt = $pdo->prepare("UPDATE scouter SET missed_events_count = GREATEST(0, missed_events_count - 1) WHERE id = ?");
            $decrementResult = $decrementStmt->execute([$existingApology['scouter_id']]);
            
            if (!$decrementResult) {
                $errorInfo = $decrementStmt->errorInfo();
                throw new Exception("Failed to update missed events count: " . $errorInfo[2]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Apology " . ucfirst($status) . " successfully!";
        header("Location: apologies.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating apology: " . $e->getMessage();
        header("Location: apologies.php");
        exit();
    }
}

// Fetch pending apologies and history
$pending_apologies = $pdo->query("
    SELECT a.*, 
           s.full_name as scouter_name,
           s.missed_events_count,
           e.title as event_title,
           e.event_date
    FROM apologies a
    JOIN scouter s ON a.scouter_id = s.id
    JOIN event e ON a.event_id = e.id
    WHERE a.status = 'pending'
    ORDER BY a.submitted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$apologies_history = $pdo->query("
    SELECT a.*, 
           s.full_name as scouter_name,
           s.missed_events_count,
           e.title as event_title,
           e.event_date,
           admin.full_name as reviewed_by_name
    FROM apologies a
    JOIN scouter s ON a.scouter_id = s.id
    JOIN event e ON a.event_id = e.id
    LEFT JOIN admins admin ON a.reviewed_by = admin.id
    WHERE a.status IN ('approved', 'declined')
    ORDER BY a.submitted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apologies Management | KTL Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
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
            margin-bottom: 2rem;
        }
        .card:hover {
            transform: translateY(-2px);
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
        .btn-success {
            background-color: #28A745;
            border: none;
        }
        .btn-success:hover {
            background-color: #1d7d33;
        }
        .btn-danger {
            background-color: #E54B4B;
            border: none;
        }
        .btn-danger:hover {
            background-color: #c23333;
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
        .badge-pending {
            background-color: #FFA500;
            color: white;
        }
        .badge-accepted {
            background-color: #28A745;
            color: white;
        }
        .badge-declined {
            background-color: #E54B4B;
            color: white;
        }
        .badge-primary {
            background-color: #1F487E;
            color: white;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .apology-reason {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: text;
        }
        .reviewer-reason {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: text
        }
        .section-divider {
            border-top: 3px solid #1F487E;
            margin: 3rem 0 2rem 0;
        }
    </style>
</head>
<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <main class="main-container">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Apologies Management</h2>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Pending Apologies Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clock"></i> Pending Apologies 
                        <span class="badge bg-light text-dark ms-2"><?= count($pending_apologies) ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_apologies)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No pending apologies found.
                        </div>
                    <?php else: ?>
                        <div class="scrollable-table">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Scout</th>
                                        <th>Event</th>
                                        <th>Reason</th>
                                        <th>Submitted At</th>
                                        <th>Missed Events</th>
                                        <?php if ($admin_role === 'dashboard'): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_apologies as $apology): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($apology['id']) ?></td>
                                        <td><?= htmlspecialchars($apology['scouter_name']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($apology['event_title']) ?>
                                            <br>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($apology['event_date'])) ?></small>
                                        </td>
                                        <td class="apology-reason" title="<?= htmlspecialchars($apology['reason']) ?>">
                                            <?= htmlspecialchars($apology['reason']) ?>
                                        </td>
                                        <td><?= date('M j, Y H:i', strtotime($apology['submitted_at'])) ?></td>
                                        <td>
                                            <?php 
                                            $missed_count = $apology['missed_events_count'];
                                            $badge_class = 'badge-primary';
                                            if ($missed_count > 2) {
                                                $badge_class = 'badge-danger';
                                            } elseif ($missed_count > 0) {
                                                $badge_class = 'badge-warning';
                                            }
                                            ?>
                                            <span class="badge <?= $badge_class ?>">
                                                <?= $missed_count ?>
                                            </span>
                                        </td>
                                        <?php if ($admin_role === 'dashboard'): ?>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-success" onclick="showReviewModal(<?= $apology['id'] ?>, 'accepted')">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="showReviewModal(<?= $apology['id'] ?>, 'declined')">
                                                    <i class="fas fa-times"></i> Decline
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="section-divider"></div>

            <!-- Apologies History Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history"></i> Apologies History 
                        <span class="badge bg-light text-dark ms-2"><?= count($apologies_history) ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($apologies_history)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No reviewed apologies found.
                        </div>
                    <?php else: ?>
                        <div class="scrollable-table">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Scout</th>
                                        <th>Event</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Reviewer Reason</th>
                                        <th>Submitted At</th>
                                        <th>Reviewed By</th>
                                        <th>Missed Events</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apologies_history as $apology): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($apology['id']) ?></td>
                                        <td><?= htmlspecialchars($apology['scouter_name']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($apology['event_title']) ?>
                                            <br>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($apology['event_date'])) ?></small>
                                        </td>
                                        <td class="apology-reason" title="<?= htmlspecialchars($apology['reason']) ?>">
                                            <?= htmlspecialchars($apology['reason']) ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_class = $apology['status'] === 'approved' ? 'badge-accepted' : 'badge-declined';
                                            $display_status = $apology['status'] === 'approved' ? 'Accepted' : 'Declined';
                                            ?>
                                            <span class="badge <?= $status_class ?>">
                                                <?= $display_status ?>
                                            </span>
                                        </td>
                                        <td class="reviewer-reason" title="<?= htmlspecialchars($apology['reviewer_reason'] ?? '') ?>">
                                            <?= htmlspecialchars($apology['reviewer_reason'] ?? '-') ?>
                                        </td>
                                        <td><?= date('M j, Y H:i', strtotime($apology['submitted_at'])) ?></td>
                                        <td><?= htmlspecialchars($apology['reviewed_by_name'] ?? '-') ?></td>
                                        <td>
                                            <?php 
                                            $missed_count = $apology['missed_events_count'];
                                            $badge_class = 'badge-primary';
                                            if ($missed_count > 2) {
                                                $badge_class = 'badge-danger';
                                            } elseif ($missed_count > 0) {
                                                $badge_class = 'badge-warning';
                                            }
                                            ?>
                                            <span class="badge <?= $badge_class ?>">
                                                <?= $missed_count ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Review Apology Modal (Only for dashboard role) -->
    <?php if ($admin_role === 'dashboard'): ?>
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel">Review Apology</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="apologies.php">
                    <div class="modal-body">
                        <input type="hidden" name="apology_id" id="modal_apology_id">
                        <input type="hidden" name="status" id="modal_status">
                        
                        <div class="alert alert-info" id="modal_action_info"></div>
                        
                        <div class="mb-3">
                            <label for="reviewer_reason" class="form-label">Reason for your decision:</label>
                            <textarea class="form-control" name="reviewer_reason" id="reviewer_reason" rows="3" placeholder="Enter your reason for accepting/declining this apology..."></textarea>
                            <div class="form-text">This reason will be recorded and visible in the apologies history.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn" id="modal_submit_btn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include "includes/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($admin_role === 'dashboard'): ?>
        function showReviewModal(apologyId, status) {
            document.getElementById('modal_apology_id').value = apologyId;
            document.getElementById('modal_status').value = status;
            
            const actionInfo = document.getElementById('modal_action_info');
            const submitBtn = document.getElementById('modal_submit_btn');
            const modalTitle = document.getElementById('reviewModalLabel');
            
            if (status === 'accepted') {
                actionInfo.innerHTML = '<i class="fas fa-check-circle"></i> You are about to <strong>ACCEPT</strong> this apology.';
                actionInfo.className = 'alert alert-success';
                submitBtn.className = 'btn btn-success';
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Accept Apology';
                modalTitle.textContent = 'Accept Apology';
            } else {
                actionInfo.innerHTML = '<i class="fas fa-times-circle"></i> You are about to <strong>DECLINE</strong> this apology.';
                actionInfo.className = 'alert alert-danger';
                submitBtn.className = 'btn btn-danger';
                submitBtn.innerHTML = '<i class="fas fa-times"></i> Decline Apology';
                modalTitle.textContent = 'Decline Apology';
            }
            document.getElementById('reviewer_reason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
            modal.show();
        }
        <?php endif; ?>
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('apology-reason') || e.target.classList.contains('reviewer-reason')) {
                const fullText = e.target.getAttribute('title');
                if (fullText && fullText !== '-') {
                    alert(fullText);
                }
            }
        });
    </script>
</body>
</html>