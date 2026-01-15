<?php
/*
File: admin/index.php
Purpose: Secure Admin Login (Production Ready)
*/
session_start();
require_once '../includes/db_connect.php';

// 1. Agar pehle se login hai to Dashboard par bhejo
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// 2. Login Logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if(empty($username) || empty($password)) {
        $error = "Please fill both fields.";
    } else {
        // Admin table check karein
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // Password Verify
        if ($admin && password_verify($password, $admin['password'])) {
            // Session Set Karein
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid Username or Password!";
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; height: 100vh; padding: 0; background-color: #000; margin: 0; }
        
        .login-card { 
            width: 100%; 
            max-width: 400px; 
            padding: 40px; 
            background: linear-gradient(135deg, #1a1a1a 0%, #111 100%); 
            border: 1px solid gold; 
            border-radius: 20px; 
            text-align: center; 
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.1);
        }
        
        input { 
            width: 100%; 
            padding: 12px; 
            margin: 10px 0; 
            border-radius: 8px; 
            border: 1px solid #444; 
            background: #222; 
            color: white; 
            text-align: center; 
            font-size: 16px;
            outline: none;
        }
        
        input:focus { border-color: gold; }

        .btn-primary { 
            background: gold; 
            color: black; 
            padding: 12px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold; 
            width: 100%; 
            margin-top: 20px; 
            font-size: 16px;
            transition: 0.3s;
        }
        
        .btn-primary:hover { 
            background: #e5c100; 
            transform: scale(1.02);
        }
        
        .logo-icon {
            font-size: 50px;
            color: gold;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <i class="fa-solid fa-user-shield logo-icon"></i>
        <h2 style="color: white; margin-bottom: 5px;">Admin Access</h2>
        <p style="color: #666; margin-bottom: 30px; font-size: 14px;">Secure Exchange Panel</p>

        <?php if($error): ?>
            <div style="background: rgba(255, 77, 77, 0.1); border: 1px solid #ff4d4d; color: #ff4d4d; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 14px;">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="text" name="username" placeholder="Enter Username" required autocomplete="off">
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Enter Password" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Login Now</button>
        </form>
        
        <div style="margin-top: 20px; border-top: 1px solid #333; padding-top: 10px;">
            <p style="font-size: 11px; color: #444;">&copy; P2P Exchange Secure System</p>
        </div>
    </div>
</body>
</html>
