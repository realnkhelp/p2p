<?php
/*
File: includes/db_connect.php
Purpose: Database se connection banana
*/

// InfinityFree Credentials
$host = "sql100.infinityfree.com";  // MySQL Host Name
$db_name = "if0_40905403_p2p";      // MySQL DB Name
$username = "if0_40905403";         // MySQL User Name
$password = "ughiR1Z7QvtYJo";       // Password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8mb4");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
