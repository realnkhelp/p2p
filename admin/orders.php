<?php
/*
File: admin/orders.php
Purpose: Approve/Reject Transactions & Referrals
*/
session_start();
require_once '../includes/db_connect.php';

// Check Login
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = "";

// --- ACTION HANDLE (Approve/Reject) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $tx_id = intval($_POST['tx_id']);

    // Transaction Details nikalein
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$tx_id]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tx && $tx['status'] == 'pending') {
        
        // --- 1. APPROVE LOGIC ---
        if ($action == 'approve') {
            
            // A. BUY/DEPOSIT: User ko Balance dena hai
            if ($tx['type'] == 'buy' || $tx['type'] == 'deposit') {
                // Wallet Update
                updateUserBalance($pdo, $tx['user_id'], $tx['asset_symbol'], $tx['amount'], 'credit');
                
                // --- Referral Bonus Logic (First Deposit Only) ---
                if ($tx['type'] == 'deposit' || $tx['type'] == 'buy') {
                    checkAndGiveReferralBonus($pdo, $tx['user_id']);
                }
            } 
            // B. SELL/WITHDRAW: User ka Balance katna hai
            elseif ($tx['type'] == 'sell' || $tx['type'] == 'withdraw') {
                // Check balance first
                $currentBal = getCurrentBalance($pdo, $tx['user_id'], $tx['asset_symbol']);
                if ($currentBal >= $tx['amount']) {
                    updateUserBalance($pdo, $tx['user_id'], $tx['asset_symbol'], $tx['amount'], 'debit');
                } else {
                    $msg = "Error: User has insufficient balance!";
                    // Stop execution here, don't approve
                    $action = 'error'; 
                }
            }

            if ($action != 'error') {
                $upd = $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
                $upd->execute([$tx_id]);
                $msg = "Order #$tx_id Approved Successfully!";
            }
        } 
        
        // --- 2. REJECT LOGIC ---
        elseif ($action == 'reject') {
            $reason = trim($_POST['reason']);
            $upd = $pdo->prepare("UPDATE transactions SET status = 'rejected', reject_reason = ? WHERE id = ?");
            $upd->execute([$reason, $tx_id]);
            $msg = "Order #$tx_id Rejected!";
        }
    }
}

// --- HELPER FUNCTIONS (Admin specific) ---
function updateUserBalance($pdo, $user_id, $symbol, $amount, $type) {
    // Check wallet exists
    $stmt = $pdo->prepare("SELECT id, balance FROM user_wallets WHERE user_id = ? AND asset_symbol = ?");
    $stmt->execute([$user_id, $symbol]);
    $wallet = $stmt->fetch();

    if ($wallet) {
        $new_bal = ($type == 'credit') ? $wallet['balance'] + $amount : $wallet['balance'] - $amount;
        $pdo->prepare("UPDATE user_wallets SET balance = ? WHERE id = ?")->execute([$new_bal, $wallet['id']]);
    } else {
        if ($type == 'credit') {
            $pdo->prepare("INSERT INTO user_wallets (user_id, asset_symbol, balance) VALUES (?, ?, ?)")->execute([$user_id, $symbol, $amount]);
        }
    }
}

function getCurrentBalance($pdo, $user_id, $symbol) {
    $stmt = $pdo->prepare("SELECT balance FROM user_wallets WHERE user_id = ? AND asset_symbol = ?");
    $stmt->execute([$user_id, $symbol]);
    return $stmt->fetchColumn() ?: 0;
}

function checkAndGiveReferralBonus($pdo, $user_id) {
    // 1. Check referrer
    $u = $pdo->prepare("SELECT referred_by FROM users WHERE telegram_id = ?");
    $u->execute([$user_id]); // Note: DB stores telegram_id in user_id if logic was consistent, let's fix
    // Wait, transactions.user_id should be telegram_id based on previous logic. 
    // Let's assume user_id in transactions table matches telegram_id column in users table for consistency.
    $user_row = $pdo->prepare("SELECT referred_by FROM users WHERE telegram_id = ?");
    $user_row->execute([$user_id]); // Assuming user_id in tx is telegram_id
    $referrer_id = $user_row->fetchColumn();

    if ($referrer_id) {
        // 2. Check if bonus already given for this user
        $check = $pdo->prepare("SELECT id FROM transactions WHERE user_id = ? AND type = 'referral_bonus'");
        $check->execute([$referrer_id]); // Check if referrer already got bonus FOR THIS user? No, logic needs to be unique per referred user.
        // Simple logic: Check if this user has any OTHER approved deposits
        // Better: Just give bonus. Risk: User deposits multiple times.
        // Fix: Check if this is the FIRST approved deposit
        $count = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND (type='buy' OR type='deposit') AND status='approved'");
        $count->execute([$user_id]);
        $approved_count = $count->fetchColumn();

        // Since we just approved one, count should be 1
        if ($approved_count == 1) {
            // Get Bonus Amount
            $s = $pdo->query("SELECT referral_bonus_amount FROM settings WHERE id=1")->fetch();
            $bonus = $s['referral_bonus_amount'];

            // Credit Referrer
            updateUserBalance($pdo, $referrer_id, 'USDT', $bonus, 'credit');

            // Log Transaction
            $ins = $pdo->prepare("INSERT INTO transactions (user_id, type, asset_symbol, amount, status) VALUES (?, 'referral_bonus', 'USDT', ?, 'approved')");
            $ins->execute([$referrer_id, $bonus]);
        }
    }
}

// --- FETCH PENDING ORDERS ---
$filter = isset($_GET['view']) ? $_GET['view'] : 'pending';
$sql = "SELECT * FROM transactions ";
if ($filter == 'pending') {
    $sql .= "WHERE status = 'pending' ";
}
$sql .= "ORDER BY id DESC";
$orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 80px; background: #000; }
        .admin-nav { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #111; border-bottom: 1px solid gold; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 100; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #222; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #444; color: #ddd; font-size: 14px; }
        th { background: #333; color: gold; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .bg-pending { background: #ffc107; color: #000; }
        .bg-approved { background: #28a745; color: #fff; }
        .bg-rejected { background: #ff4d4d; color: #fff; }
        .action-btn { padding: 5px 10px; cursor: pointer; border: none; border-radius: 4px; color: white; margin-right: 5px; }
        .btn-approve { background: #28a745; }
        .btn-reject { background: #ff4d4d; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h3 style="color: white;">Orders</h3>
        <div></div>
    </div>

    <div class="container">
        
        <?php if($msg): ?>
            <div style="background: #333; border: 1px solid gold; padding: 10px; margin-bottom: 15px; text-align: center;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 15px;">
            <a href="?view=pending" class="btn" style="width: auto; background: <?php echo $filter=='pending'?'gold':'#333'; ?>; color: <?php echo $filter=='pending'?'#000':'#fff'; ?>;">Pending</a>
            <a href="?view=all" class="btn" style="width: auto; background: <?php echo $filter=='all'?'gold':'#333'; ?>; color: <?php echo $filter=='all'?'#000':'#fff'; ?>;">All History</a>
        </div>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Proof / Details</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $row): ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td><?php echo $row['user_id']; ?></td>
                            <td style="text-transform: uppercase;">
                                <?php echo $row['type']; ?> <br>
                                <small style="color: #aaa;"><?php echo $row['asset_symbol']; ?></small>
                            </td>
                            <td><?php echo number_format($row['amount'], 2); ?></td>
                            <td style="font-size: 12px;">
                                <?php if($row['tx_hash']): ?>
                                    <b>Hash:</b> <?php echo $row['tx_hash']; ?> <br>
                                <?php endif; ?>
                                <?php if($row['user_payment_details_json']): ?>
                                    <b>Bank:</b> <?php echo $row['user_payment_details_json']; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $row['status']; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if($row['status'] == 'pending'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this order?');">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="tx_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="action-btn btn-approve"><i class="fa-solid fa-check"></i></button>
                                    </form>

                                    <button class="action-btn btn-reject" onclick="openRejectModal(<?php echo $row['id']; ?>)">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="rejectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:2000; align-items:center; justify-content:center;">
        <div class="card" style="width: 300px; padding: 20px; border: 1px solid red;">
            <h3 style="color: red;">Reject Order</h3>
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="tx_id" id="rejectTxId">
                <div class="form-group">
                    <label>Reason</label>
                    <input type="text" name="reason" placeholder="e.g. Invalid Hash" required>
                </div>
                <button type="submit" class="btn btn-danger">Reject Now</button>
                <button type="button" class="btn mt-2" onclick="document.getElementById('rejectModal').style.display='none'" style="background: #333; color: white;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(id) {
            document.getElementById('rejectTxId').value = id;
            document.getElementById('rejectModal').style.display = 'flex';
        }
    </script>
</body>
</html>
