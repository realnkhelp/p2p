<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'u495869485_p2p';
$user = 'u495869485_p2p';
$pass = 'YOUR_PASSWORD_HERE';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

$stmt_set = $pdo->query("SELECT maintenance_mode, maintenance_end_date, maintenance_end_time FROM settings WHERE id=1");
$settings = $stmt_set->fetch();

if ($settings && $settings['maintenance_mode'] == 1) {
    
    if (!empty($settings['maintenance_end_date']) && !empty($settings['maintenance_end_time'])) {
        $current_time = date('Y-m-d H:i:s');
        $end_time = $settings['maintenance_end_date'] . ' ' . $settings['maintenance_end_time'];
        
        if ($current_time > $end_time) {
            $pdo->query("UPDATE settings SET maintenance_mode = 0 WHERE id=1");
            $settings['maintenance_mode'] = 0;
        }
    }
}

if ($settings && $settings['maintenance_mode'] == 1) {
    $inAdmin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
    $currentPage = basename($_SERVER['PHP_SELF']);

    if (!$inAdmin && $currentPage != 'maintenance.php') {
        if(file_exists('maintenance.php')){
            header("Location: maintenance.php");
        } else {
            header("Location: ../maintenance.php"); 
        }
        exit;
    }
}

if (isset($_SESSION['user_id'])) {
    $stmt_check = $pdo->prepare("SELECT status FROM users WHERE telegram_id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    $currentUser = $stmt_check->fetch();

    if ($currentUser && $currentUser['status'] == 'blocked') {
        $currentPage = basename($_SERVER['PHP_SELF']);
        if ($currentPage != 'blocked.php') {
            if(file_exists('blocked.php')){
                header("Location: blocked.php");
            } else {
                header("Location: ../blocked.php"); 
            }
            exit;
        }
    }
}
?>
