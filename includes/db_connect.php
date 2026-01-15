<?php
/*
File: includes/db_connect.php
Purpose: Database se connection banana
*/

// Yahan apni Hosting ki details dalen
$host = "localhost";
$db_name = "apka_db_name";    // Database ka naam (Example: u12345_crypto)
$username = "apka_db_user";   // Database username (Example: u12345_admin)
$password = "apka_db_pass";   // Database password

try {
    // PDO Connection create kar rahe hain
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    
    // Error aane par exception throw karega
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Hindi/Unicode characters ke liye support
    $pdo->exec("set names utf8mb4");

} catch(PDOException $e) {
    // Agar connection fail hua to ye error dikhega
    die("Database Connection Failed: " . $e->getMessage());
}
?>
