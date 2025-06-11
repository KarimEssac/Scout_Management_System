<?php
session_start();
require_once "config/db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Recorded | KTL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .success-container {
            background: #1F487E;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .success-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: none;
            background-color: #FFFFFF;
        }
        .success-icon {
            color: #1F487E;
            font-size: 5rem;
            margin-bottom: 20px;
        }
        .success-title {
            color: #1F487E;
            font-weight: 700;
        }
        .success-message {
            color: #000000;
            margin-bottom: 30px;
        }
    </style>
</head>
<body class="success-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="success-card card p-4 text-center">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="success-title mb-4">Attendance Recorded Successfully!</h2>
                    <p class="success-message">Your attendance has been successfully recorded in the system.</p>
                    <p class="text-muted">You can close this page anytime.</p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>