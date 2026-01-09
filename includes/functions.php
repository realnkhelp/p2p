<?php
/*
File: includes/functions.php
Purpose: Common calculations aur database operations handle karna
*/

require_once 'db_connect.php'; // Database connection jodna

// 1. Global Settings Lana (Admin Panel se set ki gayi)
function getSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. User Login/Register Logic (Auto-Detect)
function getOrCreateUser($pdo, $telegram_id, $first_name, $username, $ref_id = null) {
    // Check karein user pehle se hai ya nahi
    $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Purana user hai, data wapas karein
        return $user;
    } else {
        // Naya User hai -> Register karein
        $referrer_internal_id = null;

        // Agar kisi ne refer kiya hai, to Referrer ka asli ID dhundein
        if ($ref_id && $ref_id != $telegram_id) {
            $checkRef = $pdo->prepare("SELECT telegram_id FROM users WHERE telegram_id = ?");
            $checkRef->execute([$ref_id]);
            $refUser = $checkRef->fetch();
            if ($refUser) {
                $referrer_internal_id = $refUser['telegram_id'];
            }
        }

        // Database me insert karein
        $sql = "INSERT INTO users (telegram_id, first_name, username, referred_by) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$telegram_id, $first_name, $username, $referrer_internal_id]);

        // Ab naye user ka data wapas layein
        $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegram_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// 3. User ka Wallet Balance nikalna (Specific Coin ka)
function getUserBalance($pdo, $user_id, $symbol = 'USDT') {
    $stmt = $pdo->prepare("SELECT balance FROM user_wallets WHERE user_id = ? AND asset_symbol = ?");
    $stmt->execute([$user_id, $symbol]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Agar wallet nahi bana hai to 0 return karein
    return $result ? number_format($result['balance'], 4, '.', '') : '0.0000';
}

// 4. Balance Update karna (Credit/Debit) - Safe Way
function updateBalance($pdo, $user_id, $symbol, $amount, $type = 'credit') {
    // Pehle check karein wallet hai ya nahi
    $check = $pdo->prepare("SELECT id, balance FROM user_wallets WHERE user_id = ? AND asset_symbol = ?");
    $check->execute([$user_id, $symbol]);
    $wallet = $check->fetch();

    if ($wallet) {
        // Wallet exist karta hai -> Update karein
        if ($type == 'credit') {
            $new_bal = $wallet['balance'] + $amount;
        } else {
            $new_bal = $wallet['balance'] - $amount;
            if ($new_bal < 0) return false; // Balance minus me nahi ja sakta
        }
        $upd = $pdo->prepare("UPDATE user_wallets SET balance = ? WHERE id = ?");
        $upd->execute([$new_bal, $wallet['id']]);
    } else {
        // Wallet nahi hai -> Create karein (Only for Credit)
        if ($type == 'credit') {
            $ins = $pdo->prepare("INSERT INTO user_wallets (user_id, asset_symbol, balance) VALUES (?, ?, ?)");
            $ins->execute([$user_id, $symbol, $amount]);
        } else {
            return false; // Debit kar rahe hain par wallet hi nahi hai
        }
    }
    return true;
}

// 5. XSS Protection (Input safai)
function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>
