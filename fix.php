<?php
// File Location: /htdocs/p2p/fix.php

// Database connection file ko load karna
require_once 'includes/db_connect.php';

// Password jo hum set karna chahte hain
$password_text = "admin123";

// Iska asli secret Hash banana
$secure_pass = password_hash($password_text, PASSWORD_DEFAULT);

try {
    // Database me password update karna
    $sql = "UPDATE admin SET password = :p WHERE username = 'admin'";
    $stmt = $pdo->prepare($sql);
    
    if($stmt->execute(['p' => $secure_pass])) {
        echo "<h1 style='color:green; text-align:center; margin-top:50px;'>✅ Success!</h1>";
        echo "<h2 style='text-align:center;'>Password Reset to: admin123</h2>";
        echo "<p style='text-align:center;'><a href='admin/index.php'>Login Now</a></p>";
    } else {
        echo "<h1>❌ Failed to update.</h1>";
    }
} catch(PDOException $e) {
    echo "<h1>Database Error: " . $e->getMessage() . "</h1>";
    echo "<p>Check includes/db_connect.php file credentials.</p>";
}
?>
