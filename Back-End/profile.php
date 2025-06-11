<?php
session_start();
require_once "config/db.php";
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        if (!empty($new_password)) {
            if ($admin['password'] !== $current_password) {
                $error = "Current password is incorrect!";
            } else {
                $password = $new_password;
            }
        } else {
            $password = $admin['password'];
        }
        
        if (!isset($error)) {
            $stmt = $pdo->prepare("UPDATE admins SET full_name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $password, $_SESSION['admin_id']]);
            $_SESSION['admin_name'] = $full_name;
            
            $success = "Profile updated successfully!";
            header("Refresh:1");
        }
    }

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_image']['type'];
        
        if (in_array($file_type, $allowed_types)) {

            if (!file_exists('uploads/admins')) {
                mkdir('uploads/admins', 0777, true);
            }

            $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $_SESSION['admin_id'] . '_' . time() . '.' . $extension;
            $destination = 'uploads/admins/' . $filename;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                if (!empty($admin['image'])) {
                    @unlink($admin['image']);
                }
                $stmt = $pdo->prepare("UPDATE admins SET image = ? WHERE id = ?");
                $stmt->execute([$destination, $_SESSION['admin_id']]);
                $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $success = "Profile picture updated successfully!";
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Only JPG, PNG, and GIF files are allowed.";
        }
    }
}
$admins = $pdo->query("SELECT id, full_name, email, image FROM admins ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | KTL Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-card {
            max-width: 600px;
            margin: 0 auto;
        }
        .profile-pic-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            cursor: pointer;
        }
        .profile-pic {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #1F487E;
        }
        .profile-pic-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .profile-pic-container:hover .profile-pic-overlay {
            opacity: 1;
        }
        .profile-pic-overlay i {
            color: white;
            font-size: 1.5rem;
        }
        .admin-table th {
            background-color: #1F487E;
            color: white;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        .form-group {
            position: relative;
        }
        #imageUpload {
            display: none;
        }
    </style>
</head>
<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <main class="main-container">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Admin Profile</h2>
            </div>
            <div class="card profile-card">
                <div class="card-header">
                    <h5 class="mb-0">My Profile</h5>
                </div>
                <div class="card-body">
                    <form id="imageForm" action="profile.php" method="POST" enctype="multipart/form-data">
                        <input type="file" id="imageUpload" name="profile_image" accept="image/*">
                    </form>
                    
                    <div class="profile-pic-container" onclick="document.getElementById('imageUpload').click()">
                        <?php if (!empty($admin['image'])): ?>
                            <img src="<?= htmlspecialchars($admin['image']) ?>" alt="Profile Picture" class="profile-pic">
                        <?php else: ?>
                            <img src="assets/images/admin-default.png" alt="Profile Picture" class="profile-pic">
                        <?php endif; ?>
                        <div class="profile-pic-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="profile.php">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?= htmlspecialchars($admin['full_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($admin['email']) ?>" required>
                        </div>
                        <div class="mb-3 form-group">
                            <label for="current_password" class="form-label">Current Password (for verification)</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('current_password')"></i>
                        </div>
                        <div class="mb-3 form-group">
                            <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password')"></i>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">System Administrators</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($admin['id']) ?></td>
                                    <td><?= htmlspecialchars($admin['full_name']) ?></td>
                                    <td><?= htmlspecialchars($admin['email']) ?></td>
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
    <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling;
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
        document.getElementById('imageUpload').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('imageForm').submit();
            }
        });
    </script>
</body>
</html>