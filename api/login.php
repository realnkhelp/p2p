<?php
/*
File: api/login.php
Purpose: Receive Telegram Data via AJAX and Save to DB
*/
header('Content-Type: application/json');
require_once '../includes/functions.php';

// Check Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Method']);
    exit;
}

// Get Data
$tg_id = cleanInput($_POST['tg_id']);
$first_name = cleanInput($_POST['first_name']);
$last_name = cleanInput($_POST['last_name'] ?? '');
$username = cleanInput($_POST['username'] ?? '');
$photo_url = cleanInput($_POST['photo_url'] ?? '');
$ref_id = cleanInput($_POST['ref_id'] ?? null);

if (!$tg_id) {
    echo json_encode(['success' => false, 'message' => 'No ID Provided']);
    exit;
}

// --- UPDATE OR INSERT USER ---
// Check if user exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
$stmt->execute([$tg_id]);
$user = $stmt->fetch();

if ($user) {
    // Update existing user (Photo, Name change ho sakta hai)
    $sql = "UPDATE users SET first_name=?, last_name=?, username=?, photo_url=? WHERE telegram_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$first_name, $last_name, $username, $photo_url, $tg_id]);
} else {
    // Insert New User
    $sql = "INSERT INTO users (telegram_id, first_name, last_name, username, photo_url, referred_by) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tg_id, $first_name, $last_name, $username, $photo_url, $ref_id]);
    
    // Optional: New User Bonus Logic here
}

echo json_encode(['success' => true]);
?>
