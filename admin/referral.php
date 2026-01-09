<?php
/*
File: admin/referral.php
Purpose: Update Site Settings (Referral Bonus, Wallets, UPI)
*/
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = "";

// --- Update Settings ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bonus = $_POST['referral_bonus'];
    $bot_url = $_POST['bot_url'];
    $upi = $_POST['admin_upi'];
    $min_withdraw = $_POST['min_withdraw'];
    
    // Update Query
    $stmt = $pdo->prepare("UPDATE settings SET referral_bonus_amount=?, bot_url=?, admin_upi=?, min_withdraw_limit=? WHERE id=1");
    
    if ($stmt->execute([$bonus, $bot_url, $upi, $min_withdraw])) {
        $msg = "Settings Updated Successfully!";
    } else {
        $msg = "Error Updating Settings.";
    }
}

// Fetch Current Settings
$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 80px; background: #000; }
        .admin-nav { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #111; border-bottom: 1px solid gold; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 100; }
        .form-card { background: #222; padding: 20px; border-radius: 10px; border: 1px solid #444; max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h3 style="color: white;">Site Settings</h3>
        <div></div>
    </div>

    <div class="container">
        
        <?php if($msg): ?>
            <div style="background: #28a745; color: white; padding: 10px; margin-bottom: 20px; text-align: center; border-radius: 5px;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST">
                
                <h4 style="color: gold; margin-bottom: 15px;">Referral System</h4>
                <div class="form-group">
                    <label>Referral Bonus (USDT)</label>
                    <input type="text" name="referral_bonus" value="<?php echo $settings['referral_bonus_amount']; ?>" required>
                    <small style="color: #888;">Amount given to referrer when friend deposits.</small>
                </div>

                <div class="form-group">
                    <label>Bot Link (Invite URL)</label>
                    <input type="text" name="bot_url" value="<?php echo $settings['bot_url']; ?>" required>
                </div>

                <hr style="border-color: #444; margin: 20px 0;">

                <h4 style="color: gold; margin-bottom: 15px;">Payment Details</h4>
                <div class="form-group">
                    <label>Admin UPI ID (For P2P Buy)</label>
                    <input type="text" name="admin_upi" value="<?php echo $settings['admin_upi']; ?>" required>
                </div>

                <div class="form-group">
                    <label>Min Withdrawal Limit ($)</label>
                    <input type="number" name="min_withdraw" value="<?php echo $settings['min_withdraw_limit']; ?>" required>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Save Changes</button>
            </form>
        </div>
    </div>

</body>
</html>
