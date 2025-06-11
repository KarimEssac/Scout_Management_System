<?php
session_start();
require_once "config/db.php";
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_scouters' && isset($_GET['event_id'])) {
    header('Content-Type: application/json');
    
    try {
        $event_id = $_GET['event_id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                s.id, 
                COALESCE(s.full_name, '') as full_name, 
                COALESCE(s.team, '') as team, 
                COALESCE(s.taliaa, '') as taliaa 
            FROM scouter s
            WHERE s.id NOT IN (
                SELECT COALESCE(user_id, 0) FROM attendance WHERE event_id = ?
            )
            ORDER BY s.full_name
        ");
        $stmt->execute([$event_id]);
        $scouters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($scouters);
    } catch (Exception $e) {
        error_log("Error loading scouters: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_attendance'])) {
    $event_id = $_POST['event_id'];
    $scouter_id = $_POST['scouter_id'];
    $token = $_POST['token'];
    $stmt = $pdo->prepare("SELECT event_id FROM qr_codes WHERE token = ?");
    $stmt->execute([$token]);
    $qr_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($qr_data && $qr_data['event_id'] == $event_id) {
        $stmt = $pdo->prepare("SELECT attendance_score FROM event WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        $attendance_score = $event['attendance_score'];
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND event_id = ?");
        $stmt->execute([$scouter_id, $event_id]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, event_id, attended_at, attendance_score) VALUES (?, ?, NOW(), ?)");
            $stmt->execute([$scouter_id, $event_id, $attendance_score]);
            $stmt = $pdo->prepare("UPDATE scouter SET total_score = total_score + ? WHERE id = ?");
            $stmt->execute([$attendance_score, $scouter_id]);
            
            $manual_success = "Attendance recorded successfully!";
        } else {
            $manual_error = "This scouter has already attended this event.";
        }
    } else {
        $manual_error = "Invalid token for this event.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'])) {
    try {
        $event_id = $_POST['event_id'];
        $admin_id = $_SESSION['admin_id'];
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("SELECT id, open FROM event WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            $error = "Event not found.";
        } elseif (!$event['open']) {
            $error = "Cannot generate QR code for closed event.";
        } else {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM qr_codes");
            $stmt->execute();
            $next_id = $stmt->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO qr_codes (id, event_id, token, generated_by, generated_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$next_id, $event_id, $token, $admin_id]);
            
            $qr_url = "http://".$_SERVER['HTTP_HOST']."/attendance_record.php?token=".$token;
            $success = "QR Code generated successfully!";
            $generated_event_id = $event_id;
            $generated_token = $token;
        }
    } catch (PDOException $e) {
        error_log("QR Generation Error: " . $e->getMessage());
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM qr_codes");
                $stmt->execute();
                $next_id = $stmt->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO qr_codes (id, event_id, token, generated_by, generated_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$next_id, $event_id, $token, $admin_id]);
                $qr_url = "http://".$_SERVER['HTTP_HOST']."/attendance_record.php?token=".$token;
                $success = "QR Code generated successfully!";
                $generated_event_id = $event_id;
                $generated_token = $token;
            } catch (PDOException $e2) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO qr_codes (event_id, token, generated_by, generated_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$event_id, $token, $admin_id]);
                    
                    $qr_url = "http://".$_SERVER['HTTP_HOST']."/attendance_record.php?token=".$token;
                    $success = "QR Code generated successfully!";
                    $generated_event_id = $event_id;
                    $generated_token = $token;
                } catch (PDOException $e3) {
                    error_log("Final QR Generation Error: " . $e3->getMessage());
                    $error = "Unable to generate QR code. Please contact administrator.";
                }
            }
        } else {
            $error = "Database error occurred. Please try again.";
        }
    } catch (Exception $e) {
        error_log("General QR Generation Error: " . $e->getMessage());
        $error = "An unexpected error occurred. Please try again.";
    }
}

$events = $pdo->query("SELECT *, (open = 0) as is_closed FROM event ORDER BY event_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$qr_codes = $pdo->query("
    SELECT q.*, e.title as event_title, a.full_name as admin_name, e.open as event_open
    FROM qr_codes q
    JOIN event e ON q.event_id = e.id
    JOIN admins a ON q.generated_by = a.id
    ORDER BY q.generated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$latest_open_qr = $pdo->query("
    SELECT q.*, e.title as event_title, e.open as event_open
    FROM qr_codes q
    JOIN event e ON q.event_id = e.id
    WHERE e.open = 1
    ORDER BY q.generated_at DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($latest_open_qr) {
    $latest_qr_url = "http://".$_SERVER['HTTP_HOST']."/attendance_record.php?token=".$latest_open_qr['token'];
    $latest_event_id = $latest_open_qr['event_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance QR | KTL Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
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
        #qrCodeContainer {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 20px 0;
        }
        #qrCode {
            margin: 20px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .qr-url {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            word-break: break-all;
            margin-bottom: 20px;
        }
        .disabled-option {
            color: #6c757d;
            background-color: #f8f9fa;
        }
        .qr-modal-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .qr-modal-url {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            word-break: break-all;
            margin-top: 20px;
            width: 100%;
            text-align: center;
        }
        .btn-disabled-custom {
            opacity: 0.65;
            cursor: not-allowed;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .search-container {
            margin-bottom: 15px;
        }
        #scouterSearch {
            width: 100%;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        #scouterList {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .scouter-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .scouter-item:hover {
            background-color: #f5f5f5;
        }
        .scouter-item.selected {
            background-color: #e9f7fe;
            border-left: 4px solid #1F487E;
        }
        .scouter-item:last-child {
            border-bottom: none;
        }
        .no-scouters {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <main class="main-container">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Attendance QR Codes</h2>
            </div>
            
            <!-- Generate QR Code Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Generate QR Code</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="attendance.php">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="event_id" class="form-label">Select Event</label>
                                <select class="form-select" id="event_id" name="event_id" required>
                                    <option value="">-- Select Event --</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>" 
                                            <?= $event['is_closed'] ? 'disabled class="disabled-option"' : '' ?>
                                            <?= (isset($generated_event_id) && $generated_event_id == $event['id']) ? 'selected' : '' ?>
                                            <?= (isset($latest_event_id) && $latest_event_id == $event['id'] && !isset($generated_event_id)) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($event['title']) ?> 
                                            (<?= date('M j, Y', strtotime($event['event_date'])) ?>)
                                            <?= $event['is_closed'] ? ' (Closed)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" name="generate_qr" class="btn btn-primary w-100">
                                    <i class="fas fa-qrcode me-2"></i>Generate QR
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (isset($qr_url)): ?>
                        <div id="qrCodeContainer">
                            <div id="qrCode"></div>
                            <div class="qr-url"><?= htmlspecialchars($qr_url) ?></div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-success" onclick="downloadQR()">
                                    <i class="fas fa-download me-2"></i>Download QR
                                </button>
                                <button class="btn btn-primary" onclick="copyToClipboard()">
                                    <i class="fas fa-copy me-2"></i>Copy URL
                                </button>
                                <button class="btn btn-warning text-white" data-bs-toggle="modal" data-bs-target="#manualAttendanceModal"
                                        data-event-id="<?= $generated_event_id ?>" data-token="<?= $generated_token ?>">
                                    <i class="fas fa-user-plus me-2"></i>Attend Manually
                                </button>
                            </div>
                        </div>
                        
                        <script>
                            new QRCode(document.getElementById("qrCode"), {
                                text: "<?= $qr_url ?>",
                                width: 200,
                                height: 200,
                                colorDark : "#1F487E",
                                colorLight : "#ffffff",
                                correctLevel : QRCode.CorrectLevel.H
                            });
                            
                            function downloadQR() {
                                const canvas = document.querySelector("#qrCode canvas");
                                const link = document.createElement("a");
                                link.download = "attendance-qr-<?= time() ?>.png";
                                link.href = canvas.toDataURL("image/png");
                                link.click();
                            }
                            
                            function copyToClipboard() {
                                const text = "<?= $qr_url ?>";
                                navigator.clipboard.writeText(text).then(() => {
                                    alert("URL copied to clipboard!");
                                });
                            }
                        </script>
                    <?php elseif (isset($latest_open_qr)): ?>
                        <div id="qrCodeContainer">
                            <div id="qrCode"></div>
                            <div class="qr-url"><?= htmlspecialchars($latest_qr_url) ?></div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-success" onclick="downloadQR()">
                                    <i class="fas fa-download me-2"></i>Download QR
                                </button>
                                <button class="btn btn-primary" onclick="copyToClipboard()">
                                    <i class="fas fa-copy me-2"></i>Copy URL
                                </button>
                                <button class="btn btn-warning text-white" data-bs-toggle="modal" data-bs-target="#manualAttendanceModal"
                                        data-event-id="<?= $latest_open_qr['event_id'] ?>" data-token="<?= $latest_open_qr['token'] ?>">
                                    <i class="fas fa-user-plus me-2"></i>Attend Manually
                                </button>
                            </div>
                        </div>
                        
                        <script>
                            new QRCode(document.getElementById("qrCode"), {
                                text: "<?= $latest_qr_url ?>",
                                width: 200,
                                height: 200,
                                colorDark : "#1F487E",
                                colorLight : "#ffffff",
                                correctLevel : QRCode.CorrectLevel.H
                            });
                            
                            function downloadQR() {
                                const canvas = document.querySelector("#qrCode canvas");
                                const link = document.createElement("a");
                                link.download = "attendance-qr-<?= time() ?>.png";
                                link.href = canvas.toDataURL("image/png");
                                link.click();
                            }
                            
                            function copyToClipboard() {
                                const text = "<?= $latest_qr_url ?>";
                                navigator.clipboard.writeText(text).then(() => {
                                    alert("URL copied to clipboard!");
                                });
                            }
                        </script>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Generated QR Codes Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Generated QR Codes</h5>
                </div>
                <div class="card-body">
                    <div class="scrollable-table">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Event</th>
                                    <th>Generated By</th>
                                    <th>Generated At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($qr_codes as $qr): ?>
                                <tr>
                                    <td><?= htmlspecialchars($qr['id']) ?></td>
                                    <td><?= htmlspecialchars($qr['event_title']) ?></td>
                                    <td><?= htmlspecialchars($qr['admin_name']) ?></td>
                                    <td><?= date('M j, Y H:i', strtotime($qr['generated_at'])) ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm <?= $qr['event_open'] ? 'btn-primary' : 'btn-disabled-custom' ?> show-qr-btn" 
                                                    <?= $qr['event_open'] ? '' : 'disabled' ?>
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#qrModal"
                                                    data-qr-url="http://<?= $_SERVER['HTTP_HOST'] ?>/attendance_record.php?token=<?= $qr['token'] ?>">
                                                <i class="fas fa-qrcode me-1"></i>Show QR Code
                                            </button>
                                            <?php if ($qr['event_open']): ?>
                                                <button class="btn btn-sm btn-warning text-white show-manual-btn"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#manualAttendanceModal"
                                                        data-event-id="<?= $qr['event_id'] ?>"
                                                        data-token="<?= $qr['token'] ?>">
                                                    <i class="fas fa-user-plus me-1"></i>Manual
                                                </button>
                                            <?php endif; ?>
                                        </div>
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

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrModalLabel">QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="qr-modal-container">
                        <div id="modalQrCode"></div>
                        <div class="qr-modal-url" id="modalQrUrl"></div>
                        <div class="mt-3 d-flex gap-2">
                            <button class="btn btn-success" onclick="downloadModalQR()">
                                <i class="fas fa-download me-1"></i>Download
                            </button>
                            <button class="btn btn-primary" onclick="copyModalUrl()">
                                <i class="fas fa-copy me-1"></i>Copy URL
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Attendance Modal -->
    <div class="modal fade" id="manualAttendanceModal" tabindex="-1" aria-labelledby="manualAttendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manualAttendanceModalLabel">Manual Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="attendance.php">
                    <div class="modal-body">
                        <?php if (isset($manual_success)): ?>
                            <div class="alert alert-success"><?= $manual_success ?></div>
                            <script>
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            </script>
                        <?php endif; ?>
                        <?php if (isset($manual_error)): ?>
                            <div class="alert alert-danger"><?= $manual_error ?></div>
                        <?php endif; ?>
                        
                        <input type="hidden" name="manual_attendance" value="1">
                        <input type="hidden" id="manualEventId" name="event_id">
                        <input type="hidden" id="manualToken" name="token">
                        
                        <div class="mb-3">
                            <label class="form-label">Search and Select Scouter:</label>
                            <div class="search-container">
                                <input type="text" id="scouterSearch" class="form-control" placeholder="Search by ID or name...">
                            </div>
                            
                            <div id="scouterList">
                                <div class="no-scouters">Loading scouters...</div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="selectedScouter" name="scouter_id" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="submitAttendance" disabled>Record Attendance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const qrModal = document.getElementById('qrModal');
        let currentQrCode = null;
        
        qrModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const qrUrl = button.getAttribute('data-qr-url');
            document.getElementById('modalQrUrl').textContent = qrUrl;
            document.getElementById('modalQrCode').innerHTML = '';
            currentQrCode = new QRCode(document.getElementById("modalQrCode"), {
                text: qrUrl,
                width: 200,
                height: 200,
                colorDark : "#1F487E",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        });
        
        function downloadModalQR() {
            const canvas = document.querySelector("#modalQrCode canvas");
            const link = document.createElement("a");
            link.download = "attendance-qr-" + Date.now() + ".png";
            link.href = canvas.toDataURL("image/png");
            link.click();
        }
        
        function copyModalUrl() {
            const text = document.getElementById('modalQrUrl').textContent;
            navigator.clipboard.writeText(text).then(() => {
                alert("URL copied to clipboard!");
            });
        }
        const manualModal = document.getElementById('manualAttendanceModal');
        let allScouters = [];

        manualModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const eventId = button.getAttribute('data-event-id') || document.getElementById('event_id').value;
            const token = button.getAttribute('data-token') || "<?= isset($generated_token) ? $generated_token : '' ?>";
            
            document.getElementById('manualEventId').value = eventId;
            document.getElementById('manualToken').value = token;
            document.getElementById('selectedScouter').value = '';
            document.getElementById('submitAttendance').disabled = true;
            
            loadScouters(eventId);
        });

        function loadScouters(eventId) {
            const scouterList = document.getElementById('scouterList');
            scouterList.innerHTML = '<div class="no-scouters">Loading scouters...</div>';
            
            fetch(`attendance.php?ajax=get_scouters&event_id=${eventId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    allScouters = data;
                    displayScouters(data);
                })
                .catch(error => {
                    console.error('Error loading scouters:', error);
                    scouterList.innerHTML = `<div class="no-scouters">Error loading scouters: ${error.message}</div>`;
                });
        }

        function displayScouters(scouters) {
            const scouterList = document.getElementById('scouterList');
            scouterList.innerHTML = '';
            
            if (scouters.length === 0) {
                scouterList.innerHTML = '<div class="no-scouters">All scouters have already attended this event.</div>';
                return;
            }
            scouters.forEach(scouter => {
                const item = document.createElement('div');
                item.className = 'scouter-item';
                const fullName = scouter.full_name || 'Unknown';
                const team = scouter.team || 'No Team';
                const taliaa = scouter.taliaa || 'No Taliaa';
                
                item.innerHTML = `
                    <div><strong>ID: ${scouter.id}</strong> - ${fullName}</div>
                    <div class="text-muted small mt-1">Team: ${team} | Taliaa: ${taliaa}</div>
                `;
                item.dataset.id = scouter.id || '';
                item.dataset.name = (scouter.full_name || '').toLowerCase();
                item.dataset.team = (scouter.team || '').toLowerCase();
                item.dataset.taliaa = (scouter.taliaa || '').toLowerCase();
                item.addEventListener('click', function() {
                    document.querySelectorAll('.scouter-item').forEach(el => {
                        el.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    document.getElementById('selectedScouter').value = this.dataset.id;
                    document.getElementById('submitAttendance').disabled = false;
                });
                
                scouterList.appendChild(item);
            });
        }
        document.getElementById('scouterSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            if (searchTerm === '') {
                displayScouters(allScouters);
                return;
            }
            
            const filteredScouters = allScouters.filter(scouter => {
                const id = (scouter.id || '').toString();
                const fullName = (scouter.full_name || '').toLowerCase();
                const team = (scouter.team || '').toLowerCase();
                const taliaa = (scouter.taliaa || '').toLowerCase();
                
                return id.includes(searchTerm) ||
                       fullName.includes(searchTerm) ||
                       team.includes(searchTerm) ||
                       taliaa.includes(searchTerm);
            });
            
            displayScouters(filteredScouters);
        });
        manualModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('scouterSearch').value = '';
            document.getElementById('selectedScouter').value = '';
            document.getElementById('submitAttendance').disabled = true;
        });
    </script>
</body>
</html>