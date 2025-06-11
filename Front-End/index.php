<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['scouter_id'])) {
    header("Location: login.php");
    exit();
}
try {
    $scouter_id = $_SESSION['scouter_id'];
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, phone, address, birthdate, year, school, 
               has_mandeel, entrance_year, taliaa, team, ab_eetraf, total_score, 
               missed_events_count
        FROM scouter 
        WHERE id = ?
    ");
    $stmt->execute([$scouter_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profile) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attended_count
        FROM attendance 
        WHERE user_id = ?
    ");
    $stmt->execute([$scouter_id]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    $attended_count = $attendance['attended_count'] ?? 0;
    $missed_count = $profile['missed_events_count'] ?? 0;
    $total_events = $attended_count + $missed_count;
    $attendance_rate = $total_events > 0 ? round(($attended_count / $total_events) * 100) : 0;
    $stmt = $pdo->prepare("
        SELECT score_change, reason, date_given
        FROM bonuses_or_penalties
        WHERE user_id = ?
        ORDER BY date_given DESC
        LIMIT 5
    ");
    $stmt->execute([$scouter_id]);
    $bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    $stmt = $pdo->prepare("
        SELECT score_change, reason, date_given
        FROM bonuses_or_penalties
        WHERE user_id = ?
        ORDER BY date_given DESC
    ");
    $stmt->execute([$scouter_id]);
    $bonus_history = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    $stmt = $pdo->prepare("
        SELECT a.attendance_score, e.title, a.attended_at
        FROM attendance a
        JOIN event e ON a.event_id = e.id
        WHERE a.user_id = ?
        ORDER BY a.attended_at DESC
    ");
    $stmt->execute([$scouter_id]);
    $attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    $stmt = $pdo->prepare("
        SELECT flag_type, description, flagged_at
        FROM scouter_flags
        WHERE scouter_id = ?
        ORDER BY flagged_at DESC
    ");
    $stmt->execute([$scouter_id]);
    $flags = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        $stmt = $pdo->prepare("SELECT password FROM scouter WHERE id = ?");
        $stmt->execute([$scouter_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $current_password === $result['password']) {
            if ($new_password === $confirm_password) {
                $stmt = $pdo->prepare("UPDATE scouter SET password = ? WHERE id = ?");
                $stmt->execute([$new_password, $scouter_id]);
                
                $_SESSION['success_message'] = "Password changed successfully!";
                header("Location: index.php");
                exit();
            } else {
                $_SESSION['error_message'] = "New passwords do not match.";
                header("Location: index.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Current password is incorrect.";
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "An error occurred. Please try again.";
        header("Location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scouter Portal - <?php echo htmlspecialchars($profile['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2c5aa0;
            --primary-light: #3a6bc0;
            --secondary: #17a2b8;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --light-gray: #f5f5f5;
            --dark-gray: #495057;
            --sidebar-width: 280px;
            --header-height: 70px;
        }
        
        * {
            font-family: 'Tajawal', sans-serif;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f5f7fb;
            color: var(--dark);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .app-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary), var(--primary-light));
            color: white;
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            transform: translateX(-100%);
        }
        
        .app-sidebar.show {
            transform: translateX(0);
        }
        
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .profile-info h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
        }
                .profile-card {
            background-color: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            border-left: 5px solid var(--primary);
        }
        
        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .profile-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            background-color: var(--light-gray);
        }
        
        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(44, 90, 160, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .profile-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.3rem;
        }
        
        .profile-value {
            font-size: 0.95rem;
            color: var(--dark-gray);
        }
        .profile-info p {
            margin: 0.2rem 0 0;
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        .score-deduction {
            color: #dc3545;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s ease;
            margin: 0.2rem 0;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover, .menu-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 3px solid white;
        }
        
        .menu-item i {
            margin-right: 0.8rem;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .menu-item span {
            font-size: 0.95rem;
        }

        .app-main {
            flex: 1;
            margin-left: 0;
            padding-top: var(--header-height);
            transition: all 0.3s ease;
        }
        
        .app-header {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            height: var(--header-height);
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            z-index: 100;
            transition: all 0.3s ease;
        }
        
        .header-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--dark);
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            display: none;
        }
        
        /* Content Styles */
        .content-container {
            padding: 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: var(--primary);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(44, 90, 160, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .stat-title {
            font-size: 0.95rem;
            color: var(--gray);
            margin: 0;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
            color: var(--dark);
        }

        .chart-container {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            height: auto;
            max-height: 300px;
            overflow: hidden;
        }
        
        .chart-container canvas {
            max-height: 200px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .activity-card {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .activity-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }
        
        .activity-item:hover {
            background-color: var(--light-gray);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .warnings-card {
            background-color: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border-left: 6px solid var(--danger);
        }
        
        .warnings-header {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-bottom: 2rem;
        }
        
        .warnings-icon {
            font-size: 2.5rem;
            color: var(--danger);
        }
        
        .warnings-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--dark);
            margin: 0;
        }
        
.warning-item {
    display: flex;
    align-items: center;
    gap: 1.2rem;
    padding: 1.2rem;
    border-radius: 10px;
    margin-bottom: 1.2rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    background-color: rgba(255, 255, 255, 0.1);
}

    .warning-item.لفت_انتباه {
    background-color: rgba(255, 193, 7, 0.15) !important;
    border-color: #ffc107 !important;
}

    .warning-item.تحذير {
    background-color: rgba(220, 53, 69, 0.15) !important;
    border-color: #dc3545 !important;
}

    .warning-item.مجلس_شرف {
    background-color: rgba(52, 58, 64, 0.15) !important;
    border-color: #343a40 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}


    .warning-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.8rem;
}

    .warning-icon.لفت_انتباه {
    background-color: rgba(255, 193, 7, 0.2) !important;
    color: #ffc107 !important;
}

    .warning-icon.تحذير {
    background-color: rgba(220, 53, 69, 0.2) !important;
    color: #dc3545 !important;
}

    .warning-icon.مجلس_شرف {
    background-color: rgba(52, 58, 64, 0.2) !important;
    color: #343a40 !important;
}
        
      .warning-details {
    flex: 1;
}
        
.warning-text {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
}

    .warning-text.لفت_انتباه {
    color: #ffc107 !important;
}

    .warning-text.تحذير {
    color: #dc3545 !important;
}

    .warning-text.مجلس_شرف {
    color: #343a40 !important;
    font-weight: 700;
    font-size: 1.15rem;
}


.warning-date {
    font-size: 0.9rem;
    color: var(--gray);
    margin-top: 0.5rem;
    display: block;
}
        .activity-icon.success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .activity-icon.warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .activity-icon.danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .activity-icon.info {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-text {
            margin: 0;
            font-size: 0.95rem;
        }
        
        .activity-date {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.3rem;
            display: block;
        }
        .password-toggle {
            position: relative;
        }
        
        .password-toggle .form-control {
            padding-right: 2.5rem;
        }
        
        .password-toggle .toggle-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
            font-size: 1.2rem;
        }
        .badge {
            padding: 0.35rem 0.65rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background-color: rgba(44, 90, 160, 0.1);
            color: var(--primary);
        }
        
        .badge-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .badge-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .badge-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .badge-info {
            background-color: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        .password-form {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .password-form-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .toast {
            background-color: white;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            max-width: 350px;
            transform: translateX(120%);
            transition: all 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .toast-icon.success {
            background-color: var(--success);
            color: white;
        }
        
        .toast-icon.error {
            background-color: var(--danger);
            color: white;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            margin: 0 0 0.2rem;
        }
        
        .toast-message {
            font-size: 0.9rem;
            margin: 0;
            color: var(--gray);
        }
        
        .close-toast {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            padding: 0;
            font-size: 1.2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light);
            color: white;
        }
        
        .btn-outline-primary {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: white;
        }

        @media (min-width: 992px) {
            .app-main {
                margin-left: var(--sidebar-width);
            }
            
            .app-header {
                left: var(--sidebar-width);
            }
            
            .app-sidebar {
                transform: translateX(0);
            }
        }
        
        @media (max-width: 768px) {
            .content-container {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .sidebar-toggle {
                display: block;
            }
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        .animate-slide-up {
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="app-sidebar">
            <div class="sidebar-header">
                <div class="profile-info">
                    <h4><?php echo htmlspecialchars($profile['full_name']); ?></h4>
                    <p><?php echo htmlspecialchars($profile['year']); ?></p>
                </div>
            </div>
            
            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item active">
                    <i class="bi bi-person"></i>
                    <span>Profile</span>
                </a>
                <a href="events.php" class="menu-item">
                    <i class="bi bi-calendar-event"></i>
                    <span>Events</span>
                </a>
                <a href="apologies.php" class="menu-item">
                    <i class="bi bi-chat-square-text"></i>
                    <span>Apologies</span>
                </a>
                <a href="communication.php" class="menu-item">
                    <i class="bi bi-envelope"></i>
                    <span>Communication</span>
                </a>
            </nav>
        </aside>
        <main class="app-main">
            <header class="app-header">
                <button class="sidebar-toggle">
                    <i class="bi bi-list"></i>
                </button>
                <div class="header-title">Profile</div>
                <div class="header-actions">
                    <div class="user-name">
                        <?php echo htmlspecialchars($profile['full_name']); ?>
                    </div>
                </div>
            </header>
            <div class="content-container">
                <div class="toast-container">
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="toast show animate-fade-in" id="successToast">
                            <div class="toast-icon success">
                                <i class="bi bi-check"></i>
                            </div>
                            <div class="toast-content">
                                <h6 class="toast-title">Success</h6>
                                <p class="toast-message"><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                            </div>
                            <button class="close-toast" data-toast="successToast">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="toast show animate-fade-in" id="errorToast">
                            <div class="toast-icon error">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div class="toast-content">
                                <h6 class="toast-title">Error</h6>
                                <p class="toast-message"><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                            </div>
                            <button class="close-toast" data-toast="errorToast">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                </div>
                
                <div class="page-header">
                    <h1 class="page-title">My Profile</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                        Change Password
                    </button>
                </div>
                
                <!-- Warnings Section -->
<div class="warnings-card">
    <div class="warnings-header">
        <i class="bi bi-exclamation-triangle warnings-icon"></i>
        <h3 class="warnings-title">Warnings</h3>
    </div>
    <?php if (empty($flags)): ?>
        <p class="text-muted">No warnings to display.</p>
    <?php else: ?>
        <?php foreach ($flags as $flag): ?>
            <?php
            $flag_type = trim($flag['flag_type']);
            $flag_class = 'لفت_انتباه';
            
            if ($flag_type === 'مجلس شرف' || $flag_type === 'مجلس_شرف') {
                $flag_class = 'مجلس_شرف';
            } elseif ($flag_type === 'تحذير') {
                $flag_class = 'تحذير';
            } elseif ($flag_type === 'لفت انتباه' || $flag_type === 'لفت_انتباه') {
                $flag_class = 'لفت_انتباه';
            }
            ?>
            <div class="warning-item <?php echo htmlspecialchars($flag_class); ?>">
                <div class="warning-icon <?php echo htmlspecialchars($flag_class); ?>">
                    <i class="bi bi-flag"></i>
                </div>
                <div class="warning-details">
                    <p class="warning-text <?php echo htmlspecialchars($flag_class); ?>">
                        <?php echo htmlspecialchars($flag_type); ?>: <?php echo htmlspecialchars($flag['description']); ?>
                    </p>
                    <span class="warning-date"><?php echo date('M d, Y', strtotime($flag['flagged_at'])); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
                <div class="profile-card">
                    <h3 class="activity-title">Profile Information</h3>
                    <div class="profile-details">
                        <div class="profile-item">
                            <div class="profile-icon"><i class="bi bi-person"></i></div>
                            <div>
                                <div class="profile-label">Full Name</div>
                                <div class="profile-value"><?php echo htmlspecialchars($profile['full_name']); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($profile['birthdate'])): ?>
                            <div class="profile-item">
                                <div class="profile-icon"><i class="bi bi-calendar"></i></div>
                                <div>
                                    <div class="profile-label">Birthdate</div>
                                    <div class="profile-value"><?php echo htmlspecialchars($profile['birthdate']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="profile-item">
                            <div class="profile-icon"><i class="bi bi-telephone"></i></div>
                            <div>
                                <div class="profile-label">Phone</div>
                                <div class="profile-value"><?php echo htmlspecialchars($profile['phone']); ?></div>
                            </div>
                        </div>
                        <div class="profile-item">
                            <div class="profile-icon"><i class="bi bi-envelope"></i></div>
                            <div>
                                <div class="profile-label">Email</div>
                                <div class="profile-value"><?php echo htmlspecialchars($profile['email']); ?></div>
                            </div>
                        </div>
                        <div class="profile-item">
                            <div class="profile-icon"><i class="bi bi-book"></i></div>
                            <div>
                                <div class="profile-label">Year</div>
                                <div class="profile-value"><?php echo htmlspecialchars($profile['year']); ?></div>
                            </div>
                        </div>
                        <?php if ($profile['has_mandeel']): ?>
                            <div class="profile-item">
                                <div class="profile-icon"><i class="bi bi-award"></i></div>
                                <div>
                                    <div class="profile-label">Mandeel</div>
                                    <div class="profile-value">Yes <span class="badge badge-success ms-2">Mandeel Holder</span></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="profile-item">
                            <div class="profile-icon"><i class="bi bi-door-open"></i></div>
                            <div>
                                <div class="profile-label">Entrance Year</div>
                                <div class="profile-value"><?php echo htmlspecialchars($profile['entrance_year']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Events Attended</h3>
                            <div class="stat-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                        <h2 class="stat-value"><?php echo $attended_count; ?></h2>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Events Missed</h3>
                            <div class="stat-icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                        </div>
                        <h2 class="stat-value"><?php echo $missed_count; ?></h2>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Attendance Rate</h3>
                            <div class="stat-icon">
                                <i class="bi bi-percent"></i>
                            </div>
                        </div>
                        <h2 class="stat-value"><?php echo $attendance_rate; ?>%</h2>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">Total Score</h3>
                            <div class="stat-icon">
                                <i class="bi bi-star"></i>
                            </div>
                        </div>
                        <h2 class="stat-value"><?php echo $profile['total_score']; ?></h2>
                        <button class="btn btn-outline-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#scoreHistoryModal">
                            Display Score History
                        </button>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3 class="chart-title">Attendance Overview</h3>
                            </div>
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="activity-card">
                            <div class="activity-header">
                                <h3 class="activity-title">Recent Activity</h3>
                            </div>
                            <div class="activity-list">
                                <?php foreach ($bonuses as $bonus): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?php echo $bonus['score_change'] >= 0 ? 'success' : 'warning'; ?>">
                                            <i class="bi <?php echo $bonus['score_change'] >= 0 ? 'bi-plus-circle' : 'bi-dash-circle'; ?>"></i>
                                        </div>
                                        <div class="activity-details">
                                            <p class="activity-text">
                                                <?php echo htmlspecialchars($bonus['reason']); ?>
                                                <span class="badge <?php echo $bonus['score_change'] >= 0 ? 'badge-success' : 'badge-warning'; ?> ms-2">
                                                    <?php echo $bonus['score_change'] >= 0 ? '+' : ''; ?><?php echo $bonus['score_change']; ?>
                                                </span>
                                            </p>
                                            <span class="activity-date"><?php echo date('M d, Y', strtotime($bonus['date_given'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($flags as $flag): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon danger">
                                            <i class="bi bi-flag"></i>
                                        </div>
                                        <div class="activity-details">
                                            <p class="activity-text">
                                                <?php echo htmlspecialchars($flag['flag_type']); ?>: <?php echo htmlspecialchars($flag['description']); ?>
                                            </p>
                                            <span class="activity-date"><?php echo date('M d, Y', strtotime($flag['flagged_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" action="index.php">
                                    <div class="form-group password-toggle">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <i class="bi bi-eye-slash toggle-icon" id="toggleCurrentPassword"></i>
                                    </div>
                                    <div class="form-group password-toggle">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <i class="bi bi-eye-slash toggle-icon" id="toggleNewPassword"></i>
                                    </div>
                                    <div class="form-group password-toggle">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <i class="bi bi-eye-slash toggle-icon" id="toggleConfirmPassword"></i>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="scoreHistoryModal" tabindex="-1" aria-labelledby="scoreHistoryModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="scoreHistoryModalLabel">Score History</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="activity-list">
                                    <?php foreach ($attendance_history as $attendance): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon success">
                                                <i class="bi bi-plus-circle"></i>
                                            </div>
                                            <div class="activity-details">
                                                <p class="activity-text">
                                                    Earned <?php echo $attendance['attendance_score']; ?> points for attending event <?php echo htmlspecialchars($attendance['title']); ?>
                                                    <span class="badge badge-success ms-2">+<?php echo $attendance['attendance_score']; ?></span>
                                                </p>
                                                <span class="activity-date"><?php echo date('M d, Y', strtotime($attendance['attended_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php foreach ($bonus_history as $bonus): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon <?php echo $bonus['score_change'] >= 0 ? 'success' : 'warning'; ?>">
                                                <i class="bi <?php echo $bonus['score_change'] >= 0 ? 'bi-plus-circle' : 'bi-dash-circle'; ?>"></i>
                                            </div>
                                            <div class="activity-details">
                                                <p class="activity-text <?php echo $bonus['score_change'] < 0 ? 'score-deduction' : ''; ?>">
                                                    <?php echo $bonus['score_change'] >= 0 ? 'Earned' : 'Deducted'; ?> 
                                                    <?php echo abs($bonus['score_change']); ?> points for <?php echo htmlspecialchars($bonus['reason']); ?>
                                                    <span class="badge <?php echo $bonus['score_change'] >= 0 ? 'badge-success' : 'badge-warning'; ?> ms-2">
                                                        <?php echo $bonus['score_change'] >= 0 ? '+' : '-'; ?><?php echo abs($bonus['score_change']); ?>
                                                    </span>
                                                </p>
                                                <span class="activity-date"><?php echo date('M d, Y', strtotime($bonus['date_given'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            const attendanceChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Attended', 'Missed'],
                    datasets: [{
                        data: [<?php echo $attended_count; ?>, <?php echo $missed_count; ?>],
                        backgroundColor: ['#4CAF50', '#FF9800'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            sidebarToggle.addEventListener('click', function() {
                document.querySelector('.app-sidebar').classList.toggle('show');
            });
            document.querySelectorAll('.close-toast').forEach(button => {
                button.addEventListener('click', function() {
                    const toastId = this.getAttribute('data-toast');
                    document.getElementById(toastId).classList.remove('show');
                });
            });
            setTimeout(() => {
                document.querySelectorAll('.toast.show').forEach(toast => {
                    toast.classList.remove('show');
                });
            }, 5000);
            function checkScreenSize() {
                if (window.innerWidth >= 992) {
                    document.querySelector('.app-sidebar').classList.add('show');
                }
            }
            window.addEventListener('resize', checkScreenSize);
            checkScreenSize();
            const toggleIcons = document.querySelectorAll('.toggle-icon');
            toggleIcons.forEach(icon => {
                icon.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    this.classList.toggle('bi-eye-slash', !isPassword);
                    this.classList.toggle('bi-eye', isPassword);
                });
            });
        });
    </script>
</body>
</html>