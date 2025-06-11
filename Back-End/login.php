<?php
session_start();
require_once "config/db.php";
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, full_name FROM admins WHERE email = ? AND password = ?");
    $stmt->execute([$email, $password]);
    $admin = $stmt->fetch();

    if ($admin) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid email or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | KTL Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, #4D176D 0%, #6D3D8F 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: none;
            background-color: #FFFFFF;
        }
        .btn-login {
            background-color: #4D176D;
            border: none;
            padding: 12px;
            font-weight: 600;
            color: #FFFFFF;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background-color: #6D3D8F;
            transform: translateY(-2px);
        }
        .form-label {
            color: #4D176D;
            font-weight: 500;
        }
        .form-control {
            border: 1px solid #4D176D;
            border-radius: 8px;
            padding: 10px;
        }
        .form-control:focus {
            border-color: #6D3D8F;
            box-shadow: 0 0 5px rgba(109, 61, 143, 0.3);
        }
        .alert-danger {
            background-color: #E54B4B;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
        }
        .login-title {
            color: #4D176D;
            font-weight: 700;
        }
        .input-group-text {
            background-color: #4D176D;
            color: white;
            border: none;
        }
    </style>
</head>
<body class="login-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card card p-4">
                    <div class="text-center mb-4">
                        <img src="assets/images/logo.jpg" alt="Logo" height="60" class="mb-3">
                        <h3 class="login-title">K.T.L Login</h3>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger mt-3"><?= $error ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-login w-100 mt-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>