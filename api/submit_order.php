<?php
/*
File: api/submit_order.php
Purpose: Handle Buy, Sell, Deposit, Withdraw, and SWAP requests securely.
*/

header('Content-Type: application/json');
require_once '../includes/functions.php';

// 1. Request Method Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

// 2. User Authentication (By Telegram ID)
$tg_id = isset($_POST['tg_id']) ? cleanInput($_POST['tg_id']) : null;

if (!$tg_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID missing']);
    exit;
}

// Fetch user from DB (Functions.php wala function use karein)
// Note: API call me hum naam update nahi kar rahe, bas ID se user dhund rahe hain
$stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
$stmt->execute([$tg_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found. Please restart bot.']);
    exit;
}

// 3. Common Data Collection
$type = cleanInput($_POST['type']); // buy, sell, deposit, withdraw, swap
$amount = floatval($_POST['amount']);
$asset = isset($_POST['asset']) ? cleanInput($_POST['asset']) : 'USDT';
$tx_hash = isset($_POST['tx_hash']) ? cleanInput($_POST['tx_hash']) : '';
$network = isset($_POST['network']) ? cleanInput($_POST['network']) : '';

// Validation
if ($amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Amount']);
    exit;
}

try {
    $pdo->beginTransaction(); // Transaction Start (Data safety ke liye)

    // --- LOGIC BASED ON TYPE ---

    if ($type === 'deposit') {
        // --- DEPOSIT ---
        // Sirf request save karein, Admin verify karega
        logTransaction($pdo, $user['telegram_id'], 'deposit', $amount, $asset, "Deposit Request: $tx_hash", 'pending');
        
        $message = "Deposit Request Submitted! Please wait for approval.";

    } elseif ($type === 'withdraw') {
        // --- WITHDRAW ---
        $wallet_address = cleanInput($_POST['wd_address'] ?? ''); // From Wallet Modal

        // 1. Balance Check
        $current_bal = getUserBalance($pdo, $user['telegram_id'], $asset);
        if ($current_bal < $amount) {
            throw new Exception("Insufficient Balance for Withdrawal!");
        }

        // 2. Deduct Balance Immediately (Lock Funds)
        if (!updateBalance($pdo, $user['telegram_id'], $asset, $amount, 'debit')) {
            throw new Exception("Balance update failed.");
        }

        // 3. Save to DB
        $sql = "INSERT INTO transactions (user_id, type, asset_symbol, amount, status, wallet_address, created_at) VALUES (?, 'withdraw', ?, ?, 'pending', ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['telegram_id'], $asset, $amount, $wallet_address]);

        $message = "Withdrawal Requested! Amount deducted temporarily.";

    } elseif ($type === 'buy') {
        // --- BUY ---
        // User wants to buy USDT, save receiver wallet & UTR
        $receiver_wallet = cleanInput($_POST['receiver_wallet'] ?? '');
        
        $sql = "INSERT INTO transactions (user_id, type, asset_symbol, amount, status, tx_hash, wallet_address, network, created_at) VALUES (?, 'buy', ?, ?, 'pending', ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['telegram_id'], $asset, $amount, $tx_hash, $receiver_wallet, $network]);

        $message = "Buy Order Placed! waiting for Admin confirmation.";

    } elseif ($type === 'sell') {
        // --- SELL ---
        // User selling USDT -> INR
        
        // 1. Check Balance
        $current_bal = getUserBalance($pdo, $user['telegram_id'], $asset);
        if ($current_bal < $amount) {
            throw new Exception("Insufficient USDT Balance!");
        }

        // 2. Deduct Balance (Escrow)
        if (!updateBalance($pdo, $user['telegram_id'], $asset, $amount, 'debit')) {
            throw new Exception("Failed to lock funds.");
        }

        // 3. Get Payment Details
        $payment_method = cleanInput($_POST['payment_method']);
        $bank_name = null; $acc_num = null; $ifsc = null; $holder = null; $upi_id = null;

        if ($payment_method === 'BANK') {
            $bank_name = cleanInput($_POST['bank_name']);
            $acc_num = cleanInput($_POST['account_number']);
            $ifsc = cleanInput($_POST['ifsc_code']);
            $holder = cleanInput($_POST['account_holder']);
        } else {
            $upi_id = cleanInput($_POST['upi_id']);
        }

        // 4. Save to DB
        $sql = "INSERT INTO transactions (user_id, type, asset_symbol, amount, status, tx_hash, payment_method, bank_name, account_number, ifsc_code, account_holder, upi_id, created_at) 
                VALUES (?, 'sell', ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['telegram_id'], $asset, $amount, $tx_hash, $payment_method, $bank_name, $acc_num, $ifsc, $holder, $upi_id]);

        $message = "Sell Order Placed! Funds locked. Admin will send INR.";

    } elseif ($type === 'swap') {
        // --- SWAP (Real-time) ---
        // Example: Swap USDT -> TON
        $from_asset = $asset;
        $to_asset = cleanInput($_POST['to_asset']);
        
        // 1. Check Source Balance
        $current_bal = getUserBalance($pdo, $user['telegram_id'], $from_asset);
        if ($current_bal < $amount) {
            throw new Exception("Insufficient $from_asset Balance!");
        }

        // 2. Get Exchange Rate (Dynamic or Mock)
        // Note: Real project me yahan Live API se rate check karein
        $rates = [
            'USDT' => 1,
            'TON' => 0.15, // Example: 1 TON = $6.6 (Reverse rate approx 0.15 TON per USDT is wrong math but for demo logic)
            // Correct Logic: 
            // If USDT -> TON: Amount / Price of TON
            // If TON -> USDT: Amount * Price of TON
        ];
        
        // Simple Demo Rate Logic (Assume USDT = 1)
        // Aap baad me isko DB settings se connect kar sakte hain
        $usd_value = $amount; // Default assumption input is USD value equivalent
        
        // Agar input USDT nahi hai, to pehle USD value nikalein (Mock)
        if($from_asset !== 'USDT') {
             // Mock: 1 TON = 5 USDT, 1 BTC = 90000 USDT
             if($from_asset == 'TON') $usd_value = $amount * 5.0; 
             if($from_asset == 'BTC') $usd_value = $amount * 90000.0;
        }

        // Ab Output amount nikalein
        $receive_amount = $usd_value; // Default
        if($to_asset == 'TON') $receive_amount = $usd_value / 5.0; // Price of TON
        if($to_asset == 'BTC') $receive_amount = $usd_value / 90000.0;
        if($to_asset == 'USDT') $receive_amount = $usd_value;

        // 3. Execute Swap (Debit Old, Credit New)
        if (!updateBalance($pdo, $user['telegram_id'], $from_asset, $amount, 'debit')) {
            throw new Exception("Swap Debit Failed");
        }
        updateBalance($pdo, $user['telegram_id'], $to_asset, $receive_amount, 'credit');

        // 4. Log Transaction
        $desc = "Swapped $amount $from_asset to " . number_format($receive_amount, 6) . " $to_asset";
        $sql = "INSERT INTO transactions (user_id, type, asset_symbol, amount, status, description, created_at) VALUES (?, 'swap', ?, ?, 'completed', ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['telegram_id'], $from_asset, $amount, $desc]);

        $message = "Swap Successful! Received " . number_format($receive_amount, 4) . " $to_asset";

    } else {
        throw new Exception("Invalid Transaction Type");
    }

    $pdo->commit(); // Save changes permanently
    echo json_encode(['status' => 'success', 'message' => $message]);

} catch (Exception $e) {
    $pdo->rollBack(); // Agar error aaye to sab purana jaisa kar do
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
