<?php
// File: config/database.php
// Database configuration and connection management for JM Animal Feeds ERP
// Handles database connection using PDO with error handling

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'jforfdxo_jmerp');
define('DB_USER', 'jforfdxo_jmerp');
define('DB_PASS', 'xte0DcOTH2gE');
define('DB_CHARSET', 'utf8mb4');

// Base URL configuration - Dynamic detection
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$baseDir = '';
if (strpos($scriptDir, '/jm') !== false) {
    // Extract the base directory up to /jm
    $baseDir = substr($scriptDir, 0, strpos($scriptDir, '/jm') + 3);
} else {
    // Fallback to current directory structure
    $baseDir = '/jm';
}
define('BASE_URL', rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $baseDir, '/'));

class Database {
    private static $instance = null;
    private $connection;

    // Private constructor to prevent multiple instances
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    // Get singleton instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get database connection
    public function getConnection() {
        return $this->connection;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {}
}

// Function to get database connection
function getDbConnection() {
    return Database::getInstance()->getConnection();
}
?>