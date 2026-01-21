<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_logged_in']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($user['status'] !== 'blocked') {
    header("Location: dashboard.php");
    exit;
}

$full_name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
$photo_url = !empty($user['photo_url']) ? htmlspecialchars($user['photo_url']) : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Blocked</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .card-container {
            background-color: white;
            width: 100%;
            max-width: 380px;
            padding: 30px 20px;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            text-align: center;
        }

        .profile-wrapper {
            margin-bottom: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e4e6eb;
            background-color: #ddd; 
            margin-bottom: 10px;
        }

        .user-name {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .status-text {
            color: #d93025;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
            margin-bottom: 20px;
        }

        .warning-box {
            background-color: #fdecea;
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid #f5c6cb;
        }

        .warning-main {
            font-weight: 600;
            color: #d93025;
            display: block;
            margin-bottom: 10px;
            font-size: 15px;
        }

        .support-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .contact-btn {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 14px 0;
            background-color: #0084ff;
            color: white;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            border-radius: 50px;
            transition: background-color 0.2s ease;
            box-shadow: 0 4px 8px rgba(0, 132, 255, 0.25);
        }

        .contact-btn:hover {
            background-color: #006bce;
        }

        .contact-btn i {
            margin-right: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>

    <div class="card-container">
        
        <div class="profile-wrapper">
            <img src="<?php echo $photo_url; ?>" alt="Profile" class="profile-img">
            
            <h2 class="user-name"><?php echo $full_name; ?></h2>
            
            <div class="status-text">Blocked</div>
        </div>

        <div class="warning-box">
            <span class="warning-main">Your account has been deactivated by the administrator due to multiple reports of incomplete tasks.</span>
            <span>Note: Should you wish to enhance your performance and ensure successful task completion, please contact the administrator below to reactivate your account.</span>
        </div>

        <div class="support-buttons">
            <a href="https://t.me/YourSupportUsername" class="contact-btn">
                <i class="fas fa-headset"></i>
                Contact Admin
            </a>
        </div>
    </div>

</body>
</html>
