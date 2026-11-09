<?php
// config/db.php - settings and PDO connection
define('BASE', '/campusnest');          // folder name under htdocs; change if you rename it
define('APP_NAME', 'CampusNest');
define('HOSTEL_ADDRESS', 'Accra, Ghana');   // used by the Google Maps link on the map page - put your real address here

$dsn = 'mysql:host=localhost;dbname=campusnest_db;charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, 'root', '', $options);   // XAMPP default: user root, no password
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check config/db.php and that MySQL is running.');
}
