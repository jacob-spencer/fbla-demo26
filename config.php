<?php
session_start();

// Database configuration
define('DB_TYPE', 'mysql'); // Changed to MySQL
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'u414139450_fbla');
define('DB_USER', $_ENV['DB_USER'] ?? 'u414139450_fbla');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'Fbla2526!');
define('DB_URL', $_ENV['DATABASE_URL'] ?? '');

// Application configuration
define('APP_NAME', 'Mainstreet');
define('APP_URL', 'http://localhost:5000'); // development config, remove
define('UPLOAD_PATH', 'uploads/');

// Email configuration (UNFINISHED DYLAN PLEASE DO LITERALLY ANYTHING)
define('MAILJET_API_KEY', $_ENV['MAILJET_API_KEY'] ?? '');
define('MAILJET_SECRET_KEY', $_ENV['MAILJET_SECRET_KEY'] ?? '');

// VAPID keys
define('PUBLIC_VAPID_KEY', 'BPqYynI8maPSJvcLLTWpvT25PQxW_PIh-8_ZazoQThczaVoWedvXCJuEPq8ZmmZmT_XipT8bbNfbTsowNP-k7BI');
define('PRIVATE_VAPID_KEY', 'qa3FMC6KWny3W21jksNsjjrb85EgJIDGY2ZIiV3N190');

// Timezone
date_default_timezone_set('UTC');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>