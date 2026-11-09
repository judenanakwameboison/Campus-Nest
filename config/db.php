<?php
// config/db.php - settings and PDO connection
$basePath = getenv('BASE_PATH');
define('BASE', $basePath === false ? '/campusnest' : rtrim($basePath, '/'));
define('APP_NAME', 'CampusNest');
define('HOSTEL_ADDRESS', 'Accra, Ghana');   // used by the Google Maps link on the map page - put your real address here

$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');
$dbPort = getenv('DB_PORT');

$dbHost = $dbHost === false ? 'localhost' : $dbHost;
$dbName = $dbName === false ? 'campusnest_db' : $dbName;
$dbUser = $dbUser === false ? 'root' : $dbUser;
$dbPassword = $dbPassword === false ? '' : $dbPassword;

$dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4';
if ($dbPort !== false && $dbPort !== '') {
    $dsn .= ';port=' . $dbPort;
}
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check config/db.php and that MySQL is running.');
}
