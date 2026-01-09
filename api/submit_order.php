<?php
/*
File: api/submit_order.php
Purpose: Save Buy/Sell/Deposit/Withdraw Requests
*/
header('Content-Type: application/json');
require_once '../includes/functions.php';

// Check Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

// Get User
$tg_id = isset($_POST['tg_id']) ? cleanInput($_POST['tg_id']) : null;
$user = getOrCreateUser($pdo, $tg_id, 'User', 'user');

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

// Get Data
$type = cleanInput($_POST['type']); // buy, sell, deposit, withdraw
$amount = floatval($_POST['amount']);
$asset = isset($_POST['asset']) ? cleanInput($_POST['asset']) : 'USDT';
$tx_hash = isset($_POST['tx_hash']) ? cleanInput($_POST['tx_hash']) : '';
$network = isset($_POST['network']) ? cleanInput($_POST['network']) : '';
$payment_method = isset($_POST['payment_method']) ? cleanInput($_POST['payment_method']) : '';
$user_details = isset($_POST['user_payment_details']) ? cleanInput($_POST['user_payment_details']) : '';

// Validation
if ($amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Amount']);
    exit;
}

// Logic based on Type
try {
    // SELL/WITHDRAW ke liye balance check karein
    if ($type == 'sell' || $type == 'withdraw') {
        $current_bal = getUserBalance($pdo, $user['id'], $asset);
        if ($current_bal < $amount) {
            echo json_encode(['status' => 'error', 'message' => 'Insufficient Balance!']);
            exit;
        }
    }

    // Insert into Transactions Table
    $sql = "INSERT INTO transactions (user_id, type, asset_symbol, amount, status, tx_hash, network, payment_method, user_payment_details_json) 
            VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $user['telegram_id'], 
        $type, 
        $asset, 
        $amount, 
        $tx_hash, 
        $network, 
        $payment_method, 
        $user_details
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Order Submitted Successfully!']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
