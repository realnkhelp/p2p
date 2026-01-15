<?php
/*
File: includes/functions.php
Purpose: Database operations, User Sync (Photo/Name), and Wallet Logic
*/

require_once 'db_connect.php'; // Database connection

// 1. Global Settings Lana
function getSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. User Login/Register Logic (With Photo & Last Name Sync)
function getOrCreateUser($pdo, $telegram_id, $first_name, $last_name = '', $username = '', $photo_url = '', $ref_id = null) {
    
    // Check karein user pehle se hai ya nahi
    $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // --- CASE 1: OLD USER (Update Data) ---
        // Agar user purana hai, to hum uska Naam aur Photo update kar denge
        // taaki agar usne Telegram pe photo badli ho to yahan bhi badal jaye.
        
        $update = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, photo_url = ? WHERE telegram_id = ?");
        $update->execute([$first_name, $last_name, $username, $photo_url, $telegram_id]);
        
        // Return updated data merging with old ID
        return array_merge($user, [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'username' => $username,
            'photo_url' => $photo_url
        ]);

    } else {
        // --- CASE 2: NEW USER (Register) ---
        
        $referrer_internal_id = null; // Default: No referrer

        // Agar kisi ne refer kiya hai ($ref_id), to Referrer ka asli Telegram ID verify karein
        if ($ref_id && $ref_id != $telegram_id) {
            $checkRef = $pdo->prepare("SELECT telegram_id FROM users WHERE telegram_id = ?");
            $checkRef->execute([$ref_id]);
            $refUser = $checkRef->fetch();
            if ($refUser) {
                $referrer_internal_id = $refUser['telegram_id'];
            }
        }

        // Database me naya user insert karein
        $sql = "INSERT INTO users (telegram_id, first_name, last_name, username, photo_url, referred_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$telegram_id, $first_name, $last_name, $username, $photo_url, $referrer_internal_id]);

        // Ab naye user ka data wapas layein
        $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegram_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// 3. User ka Wallet Balance nikalna
// Ab ye dynamic hai, agar wallet row nahi hogi to 0.00 return karega
function getUserBalance($pdo, $user_id, $symbol = 'USDT') {
    // Hum user_id (Telegram ID) aur Symbol (USDT, TON, etc) se balance check karenge
    $stmt = $pdo->prepare("SELECT balance FROM user_wallets WHERE user_id = ? AND asset_symbol = ?");
    $stmt->execute([$user_id, $symbol]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format: 4 decimal places standard crypto ke liye
    return $result ? number_format($result['balance'], 4, '.', '') : '0.0000';
}

// 4. Balance Update karna (Credit/Debit)
function updateBalance($pdo, $user_id, $symbol, $amount, $type = 'credit') {
    // 1. Check karein wallet hai ya nahi
    $check = $pdo->prepare("SELECT id, balance FROM user_wallets WHERE user_id = ? AND asset_symbol = ?");
    $check->execute([$user_id, $symbol]);
    $wallet = $check->fetch();

    if ($wallet) {
        // Wallet exist karta hai -> Update karein
        if ($type == 'credit') {
            $new_bal = $wallet['balance'] + $amount;
        } else {
            // Debit logic
            if ($wallet['balance'] < $amount) {
                return false; // Insufficient Funds
            }
            $new_bal = $wallet['balance'] - $amount;
        }
        
        $upd = $pdo->prepare("UPDATE user_wallets SET balance = ? WHERE id = ?");
        $upd->execute([$new_bal, $wallet['id']]);
        
    } else {
        // Wallet nahi hai
        if ($type == 'credit') {
            // Naya wallet create karein aur balance add karein
            $ins = $pdo->prepare("INSERT INTO user_wallets (user_id, asset_symbol, balance) VALUES (?, ?, ?)");
            $ins->execute([$user_id, $symbol, $amount]);
        } else {
            return false; // Debit kar rahe hain par wallet hi nahi hai (Balance 0)
        }
    }
    return true; // Success
}

// 5. Input Safai (XSS Protection)
function cleanInput($data) {
    if (is_null($data)) return '';
    return htmlspecialchars(strip_tags(trim($data)));
}

// 6. Transaction Log (History ke liye helper function)
function logTransaction($pdo, $user_id, $type, $amount, $asset, $desc, $status = 'pending') {
    $sql = "INSERT INTO transactions (user_id, type, amount, asset_symbol, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$user_id, $type, $amount, $asset, $desc, $status]);
}
?>
