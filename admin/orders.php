<?php
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = cleanInput($_POST['action']);
    $tx_id = intval($_POST['tx_id']);

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$tx_id]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tx && $tx['status'] == 'pending') {
        
        if ($action == 'approve') {
            if ($tx['type'] == 'buy' || $tx['type'] == 'deposit') {
                updateBalance($pdo, $tx['user_id'], $tx['asset_symbol'], $tx['amount'], 'credit');
                checkReferralReward($pdo, $tx['user_id'], $tx['amount']);
            }
            
            $upd = $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
            if ($upd->execute([$tx_id])) {
                $msg = "<div class='alert success'>Order #$tx_id Approved Successfully!</div>";
            }
        } 
        
        elseif ($action == 'reject') {
            $reason = cleanInput($_POST['reason']);
            
            if ($tx['type'] == 'sell' || $tx['type'] == 'withdraw' || $tx['type'] == 'swap') {
                updateBalance($pdo, $tx['user_id'], $tx['asset_symbol'], $tx['amount'], 'credit'); 
            }
            
            $upd = $pdo->prepare("UPDATE transactions SET status = 'rejected', reject_reason = ? WHERE id = ?");
            $upd->execute([$reason, $tx_id]);
            $msg = "<div class='alert error'>Order #$tx_id Rejected & Refunded!</div>";
        }
    }
}

function checkReferralReward($pdo, $user_tg_id, $deposit_amount) {
    $settings = getSettings($pdo);
    $min_trade = $settings['referral_min_trade'];
    $bonus_amt = $settings['referral_bonus_amount'];

    if ($deposit_amount >= $min_trade) {
        $u = $pdo->prepare("SELECT referred_by FROM users WHERE telegram_id = ?");
        $u->execute([$user_tg_id]);
        $referrer_id = $u->fetchColumn();

        if ($referrer_id) {
            $chk = $pdo->prepare("SELECT id FROM transactions WHERE user_id = ? AND type = 'referral_bonus' AND description LIKE ?");
            $chk->execute([$referrer_id, "%$user_tg_id%"]);
            if (!$chk->fetch()) {
                updateBalance($pdo, $referrer_id, 'USDT', $bonus_amt, 'credit');
                $desc = "Referral Bonus for User: $user_tg_id";
                logTransaction($pdo, $referrer_id, 'referral_bonus', $bonus_amt, 'USDT', $desc, 'completed');
            }
        }
    }
}

$filter = isset($_GET['view']) ? cleanInput($_GET['view']) : 'pending';
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
        body { padding-top: 80px; background: #000; color: #ddd; }
        .admin-nav { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #111; border-bottom: 1px solid gold; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 100; }
        
        .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 10px; border: 1px solid #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; font-size: 13px; }
        th { background: #222; color: gold; text-transform: uppercase; font-size: 11px; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .bg-pending { background: #ffc107; color: #000; }
        .bg-approved { background: #28a745; color: #fff; }
        .bg-rejected { background: #ff4d4d; color: #fff; }
        
        .details-box { background: rgba(255,255,255,0.05); padding: 8px; border-radius: 4px; font-size: 11px; margin-top: 5px; color: #ccc; border: 1px solid #333; }
        
        .copy-icon { cursor: pointer; color: gold; margin-left: 5px; font-size: 12px; transition: 0.2s; }
        .copy-icon:hover { color: white; transform: scale(1.2); }
        .data-row { margin-bottom: 4px; display: flex; align-items: center; }

        .alert { padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .alert.success { background: #28a745; color: white; }
        .alert.error { background: #ff4d4d; color: white; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h3 style="color: white;">Orders Manager</h3>
        <div style="width: 50px;"></div>
    </div>

    <div class="container">
        
        <?php echo $msg; ?>

        <div style="margin-bottom: 15px; display: flex; gap: 10px;">
            <a href="?view=pending" class="btn" style="width: auto; background: <?php echo $filter=='pending'?'gold':'#333'; ?>; color: <?php echo $filter=='pending'?'#000':'#fff'; ?>;">Pending</a>
            <a href="?view=all" class="btn" style="width: auto; background: <?php echo $filter=='all'?'gold':'#333'; ?>; color: <?php echo $filter=='all'?'#000':'#fff'; ?>;">History</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID / User</th>
                        <th>Type / Asset</th>
                        <th>Amount</th>
                        <th>Copy Details</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($orders) > 0): ?>
                        <?php foreach($orders as $row): ?>
                        <tr>
                            <td>
                                <b>#<?php echo $row['id']; ?></b><br>
                                <span style="font-size: 10px; color: #888;">UID: <?php echo $row['user_id']; ?></span>
                            </td>
                            
                            <td>
                                <span style="text-transform: uppercase; font-weight: bold; 
                                    color: <?php echo ($row['type']=='buy' || $row['type']=='deposit') ? '#28a745' : '#ff4d4d'; ?>">
                                    <?php echo $row['type']; ?>
                                </span>
                                <br>
                                <span style="font-size: 10px; color: gold;"><?php echo $row['asset_symbol']; ?></span>
                            </td>

                            <td style="font-weight: bold; font-family: monospace;">
                                <?php echo number_format($row['amount'], 4); ?>
                            </td>

                            <td>
                                <?php if(($row['type'] == 'buy' || $row['type'] == 'deposit') && $row['tx_hash']): ?>
                                    <div class="details-box">
                                        <div class="data-row">
                                            <span>Hash: <?php echo substr($row['tx_hash'], 0, 10).'...'; ?></span>
                                            <i class="fa-regular fa-copy copy-icon" onclick="copyData('<?php echo $row['tx_hash']; ?>')"></i>
                                        </div>
                                        <div class="data-row">Network: <?php echo $row['asset_symbol']; ?> (Check)</div>
                                    </div>
                                <?php endif; ?>

                                <?php if($row['type'] == 'withdraw'): ?>
                                    <div class="details-box">
                                        <div class="data-row">
                                            <span>Addr: <?php echo substr($row['wallet_address'], 0, 15).'...'; ?></span>
                                            <i class="fa-regular fa-copy copy-icon" onclick="copyData('<?php echo $row['wallet_address']; ?>')"></i>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if($row['type'] == 'sell'): ?>
                                    <div class="details-box">
                                        <?php if($row['payment_method'] == 'UPI'): ?>
                                            <div class="data-row">
                                                <span>UPI: <?php echo $row['upi_id']; ?></span>
                                                <i class="fa-regular fa-copy copy-icon" onclick="copyData('<?php echo $row['upi_id']; ?>')"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="data-row" style="color:#aaa;">Bank Transfer</div>
                                            <div class="data-row">
                                                <span>Acc: <?php echo $row['account_number']; ?></span>
                                                <i class="fa-regular fa-copy copy-icon" onclick="copyData('<?php echo $row['account_number']; ?>')"></i>
                                            </div>
                                            <div class="data-row">
                                                <span>IFSC: <?php echo $row['ifsc_code']; ?></span>
                                                <i class="fa-regular fa-copy copy-icon" onclick="copyData('<?php echo $row['ifsc_code']; ?>')"></i>
                                            </div>
                                            <div style="font-size:10px; color:#888; margin-top:2px;">
                                                Name: <?php echo $row['account_holder']; ?><br>
                                                Bank: <?php echo $row['bank_name']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge bg-<?php echo $row['status']; ?>">
                                    <?php echo strtoupper($row['status']); ?>
                                </span>
                            </td>

                            <td>
                                <?php if($row['status'] == 'pending'): ?>
                                    <div style="display: flex; gap: 5px;">
                                        <form method="POST" onsubmit="return confirm('Approve this order?');">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="tx_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn" style="padding: 5px 10px; width: auto; background: #28a745; color: white;" title="Approve">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        </form>

                                        <button class="btn" onclick="openRejectModal(<?php echo $row['id']; ?>)" style="padding: 5px 10px; width: auto; background: #ff4d4d; color: white;" title="Reject">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #666;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 20px;">No Orders Found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="rejectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:2000; align-items:center; justify-content:center;">
        <div class="card" style="width: 300px; padding: 20px; border: 1px solid red; background: #222; border-radius: 10px;">
            <h3 style="color: #ff4d4d; margin-bottom: 15px;">Reject Order</h3>
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="tx_id" id="rejectTxId">
                
                <div class="form-group">
                    <label style="color: #ccc;">Reason for Rejection</label>
                    <input type="text" name="reason" placeholder="e.g. Invalid Hash / Wrong Amount" required 
                           style="width: 100%; padding: 10px; margin-top: 5px; background: #333; border: 1px solid #555; color: white; border-radius: 5px;">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn" style="background: #ff4d4d; color: white;">Reject & Refund</button>
                    <button type="button" class="btn" onclick="document.getElementById('rejectModal').style.display='none'" style="background: #333; color: white;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(id) {
            document.getElementById('rejectTxId').value = id;
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function copyData(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert("Copied: " + text);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
    </script>
</body>
</html>
