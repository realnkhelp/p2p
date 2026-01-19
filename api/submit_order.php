<?php
/*
File: api/submit_order.php
Purpose: Handle Buy, Sell, Deposit, Withdraw, and SWAP requests securely.
*/

header('Content-Type: application/json');
require_once '../includes/functions.php';

// 1. Get JSON Input (Because modern Fetch API sends JSON)
$input = json_decode(file_get_contents('php://input'), true);

// If JSON fails, try standard POST (Fallback)
if (!$input) {
    $input = $_POST;
}

// 2. User Authentication (By Telegram ID)
$tg_id = isset($input['tg_id']) ? cleanInput($input['tg_id']) : null;

if (!$tg_id) {
    echo json_encode(['success' => false, 'message' => 'User ID missing']);
    exit;
}

// Fetch user from DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
$stmt->execute([$tg_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found in database.']);
    exit;
}

// 3. Common Data Collection
$type = cleanInput($input['type']); // buy, sell, deposit, withdraw, swap
$amount = floatval($input['amount']);
$asset = isset($input['asset']) ? cleanInput($input['asset']) : 'USDT'; // Default asset

// Validation
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Amount']);
    exit;
}

try {
    $pdo->beginTransaction(); // Start Transaction

    // --- A. DEPOSIT ---
    if ($type === 'deposit') {
        $tx_hash = cleanInput($input['tx_hash']);
        
        // Log Transaction (Pending)
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, asset_symbol, amount, tx_hash, status) VALUES (?, 'deposit', ?, ?, ?, 'pending')");
        $stmt->execute([$tg_id, $asset, $amount, $tx_hash]);
        
        $message = "Deposit Request Submitted! Admin will verify.";
    } 

    // --- B. WITHDRAW ---
    elseif ($type === 'withdraw') {
        $wallet_address = cleanInput($input['address']); // From Wallet Modal

        // 1. Balance Check
        $current_bal = getUserBalance($pdo, $tg_id, $asset);
        if ($current_bal < $amount) {
            throw new Exception("Insufficient Balance!");
        }

        // 2. Deduct Balance Immediately (Lock Funds)
        updateBalance($pdo, $tg_id, $asset, $amount, 'debit');

        // 3. Save to DB
        $desc = "Withdraw request to $wallet_address";
        $sql = "INSERT INTO transactions (user_id, type, asset_symbol, amount, status, wallet_address, description, created_at) VALUES (?, 'withdraw', ?, ?, 'pending', ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tg_id, $asset, $amount, $wallet_address, $desc]);

        $message = "Withdrawal Requested! Funds deducted temporarily.";
    } 
    
    // --- C. SWAP (Updated Logic) ---
    elseif ($type === 'swap') {
        $from_coin = cleanInput($input['from_coin']);
        $to_coin = cleanInput($input['to_coin']);
        $get_amt = floatval($input['receive_amount']); // Frontend se aaya hua estimated amount
        
        // 1. Check Source Balance
        $current_bal = getUserBalance($pdo, $tg_id, $from_coin);
        if ($current_bal < $amount) {
            throw new Exception("Insufficient $from_coin Balance!");
        }

        // 2. Execute Swap (Debit Old, Credit New)
        updateBalance($pdo, $tg_id, $from_coin, $amount, 'debit');
        updateBalance($pdo, $tg_id, $to_coin, $get_amt, 'credit');

        // 3. Log Transaction
        $desc = "Swapped $amount $from_coin to $get_amt $to_coin";
        // Hum 'from_coin' ko asset_symbol maante hain main record ke liye
        $sql = "INSERT INTO transactions (user_id, type, asset_symbol, amount, status, description, created_at) VALUES (?, 'swap', ?, ?, 'completed', ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tg_id, $from_coin, $amount, $desc]);

        $message = "Swap Successful!";
    }

    // --- D. BUY (Legacy support for index.php) ---
    elseif ($type === 'buy') {
        $tx_hash = cleanInput($input['tx_hash']);
        $network = cleanInput($input['network']);
        $rec_wallet = cleanInput($input['receiver_wallet']);
        
        $sql = "INSERT INTO transactions (user_id, type, asset_symbol, amount, status, tx_hash, wallet_address, network, created_at) VALUES (?, 'buy', ?, ?, 'pending', ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tg_id, $asset, $amount, $tx_hash, $rec_wallet, $network]);

        $message = "Buy Order Placed!";
    }

    // --- E. SELL (Legacy support for index.php) ---
    elseif ($type === 'sell') {
        // 1. Check Balance
        $current_bal = getUserBalance($pdo, $tg_id, $asset);
        if ($current_bal < $amount) {
            throw new Exception("Insufficient Balance!");
        }

        // 2. Deduct Balance (Escrow)
        updateBalance($pdo, $tg_id, $asset, $amount, 'debit');

        // 3. Get Payment Details
        $pay_method = cleanInput($input['payment_method']);
        $upi = $input['upi_id'] ?? null;
        $bank = $input['bank_name'] ?? null;
        $acc = $input['account_number'] ?? null;
        $ifsc = $input['ifsc_code'] ?? null;
        $holder = $input['account_holder'] ?? null;
        $tx_hash = $input['tx_hash'] ?? ''; // Sell me UTR zaruri nahi, par agar user de raha hai to le lo

        // 4. Save to DB
        $sql = "INSERT INTO transactions (user_id, type, asset_symbol, amount, status, tx_hash, payment_method, bank_name, account_number, ifsc_code, account_holder, upi_id, created_at) 
                VALUES (?, 'sell', ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tg_id, $asset, $amount, $tx_hash, $pay_method, $bank, $acc, $ifsc, $holder, $upi]);

        $message = "Sell Order Placed! Funds locked.";
    }

    else {
        throw new Exception("Invalid Transaction Type");
    }

    $pdo->commit(); // Save Everything
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $pdo->rollBack(); // Undo changes on error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
