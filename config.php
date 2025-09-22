<?php
session_start();

// Database configuration
define('DB_TYPE', 'mysql'); // Changed to MySQL
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'u414139450_fbla2526proto');
define('DB_USER', $_ENV['DB_USER'] ?? 'u414139450_fblaadmin');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'Fbla2526');
define('DB_URL', $_ENV['DATABASE_URL'] ?? '');

// Application configuration
define('APP_NAME', 'Mainstreet');
define('APP_URL', 'http://localhost:5000'); // development config, remove
define('UPLOAD_PATH', 'uploads/');

// Email configuration
define('MAILJET_API_KEY', $_ENV['MAILJET_API_KEY'] ?? '');
define('MAILJET_SECRET_KEY', $_ENV['MAILJET_SECRET_KEY'] ?? '');

// Timezone
date_default_timezone_set('UTC');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>