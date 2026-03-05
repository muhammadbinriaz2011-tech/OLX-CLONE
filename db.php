<?php
// Database configuration
 $host = 'localhost';
 $dbname = 'rsoa_rsoa0142_2';
 $user = 'rsoa_rsoa0142_2';
 $pass = '123456';
 $charset = 'utf8mb4';
 
// Data Source Name (DSN)
 $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
 
// PDO options
 $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
 
try {
    // Create PDO instance
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If connection fails, show a user-friendly error
    die("Database connection failed. Please check your database settings.");
}
?>
