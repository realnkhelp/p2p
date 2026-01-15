<?php
/*
File: admin/index.php
Purpose: Secure Admin Login + AUTO PASSWORD FIX
*/
session_start();
require_once '../includes/db_connect.php';

// --- MAGIC FIX: PASSWORD RESET CODE (START) ---
// Jaise hi ye page load hoga, ye code password ko 'admin123' bana dega
$new_password_hash = password_hash("admin123", PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE username = 'admin'");
$stmt->execute([$new_password_hash]);
// --- MAGIC FIX END ---

// Agar pehle se login hai to Dashboard par bhejo
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Admin table check karein
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Password Verify
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid Username or Password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; height: 100vh; padding: 0; background-color: #000; }
        .login-card { width: 100%; max-width: 400px; padding: 30px; background: #1a1a1a; border: 1px solid gold; border-radius: 20px; text-align: center; }
        input { width: 90%; padding: 10px; margin: 10px 0; border-radius: 5px; border: 1px solid #333; background: #222; color: white; text-align: center; }
        .btn-primary { background: gold; color: black; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 10px; }
        .btn-primary:hover { background: #e5c100; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 style="color: gold;">Admin Panel</h2>
        <p style="color: #aaa; margin-bottom: 20px;">Login to manage exchange</p>

        <?php if($error): ?>
            <div style="background: #ff4d4d; color: white; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        
        <p style="margin-top: 15px; font-size: 12px; color: #555;">Default: admin / admin123</p>
    </div>
</body>
</html>
