<?php
echo "<h1>Database Diagnostic Tool</h1>";
 
// Test 1: Check MySQL connection without database
echo "<h2>Step 1: Testing MySQL Connection</h2>";
try {
    $pdo = new PDO("mysql:host=localhost", 'rsoa_rsoa0142_2', '123456');
    echo "<p style='color:green;'>✓ MySQL server connected successfully!</p>";
 
    // Test 2: Show all databases
    echo "<h2>Step 2: Available Databases</h2>";
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
 
    echo "<ul>";
    foreach ($databases as $db) {
        echo "<li>$db</li>";
    }
    echo "</ul>";
 
    // Test 3: Try to find the correct database
    echo "<h2>Step 3: Finding Your Database</h2>";
    $found_db = null;
    foreach ($databases as $db) {
        if (strpos($db, 'olx') !== false || strpos($db, 'rsoa0142') !== false) {
            echo "<p style='color:orange;'>Found possible database: <strong>$db</strong></p>";
            $found_db = $db;
        }
    }
 
    if ($found_db) {
        echo "<h2>Step 4: Testing Database Connection</h2>";
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=$found_db", 'rsoa_rsoa0142_2', '123456');
            echo "<p style='color:green;'>✓ Connected to database '$found_db' successfully!</p>";
 
            // Check if tables exist
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
 
            if (empty($tables)) {
                echo "<p style='color:orange;'>⚠ No tables found. You need to import the SQL file.</p>";
                echo "<p><strong>Use this database name in db.php:</strong> $found_db</p>";
            } else {
                echo "<p style='color:green;'>✓ Tables found: " . implode(', ', $tables) . "</p>";
                echo "<p><strong>Your database is ready!</strong></p>";
            }
        } catch(PDOException $e) {
            echo "<p style='color:red;'>✗ Cannot connect to database '$found_db': " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:red;'>✗ No suitable database found!</p>";
        echo "<h3>Solution:</h3>";
        echo "<ol>";
        echo "<li>Go to your cPanel</li>";
        echo "<li>Click on 'MySQL Databases'</li>";
        echo "<li>Create a new database with name: <strong>rsoa_rsoa0142_2_olx</strong></li>";
        echo "<li>Add user 'rsoa_rsoa0142_2' to this database with all privileges</li>";
        echo "<li>Import the SQL file</li>";
        echo "</ol>";
    }
 
} catch(PDOException $e) {
    echo "<p style='color:red;'>✗ Cannot connect to MySQL: " . $e->getMessage() . "</p>";
    echo "<h3>Possible Solutions:</h3>";
    echo "<ol>";
    echo "<li>Check if MySQL is running on your hosting</li>";
    echo "<li>Verify username: 'rsoa_rsoa0142_2'</li>";
    echo "<li>Verify password: '123456'</li>";
    echo "<li>Contact hosting support</li>";
    echo "</ol>";
}
?>
