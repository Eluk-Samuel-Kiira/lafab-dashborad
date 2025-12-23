<?php
// config.php - Database Credentials Only
// =======================================

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration - ONLY CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

// Database Names - ONLY DB NAMES
define('DB_JOBS', 'melafabs_JOBSITyson');    // Job site database name
define('DB_TEAM', 'melafabs_Teamayson');     // Team site database name

// Connection Settings
define('DB_CHARSET', 'utf8mb4');

// Sync Settings (optional, can stay here or move to script)
define('BULK_CHUNK_SIZE_INSERT', 100);
define('BULK_CHUNK_SIZE_UPDATE', 50);
define('BULK_CHUNK_SIZE_FETCH', 1000);
define('MAX_LOG_ROWS', 10);

// Timezone
date_default_timezone_set('UTC');

// Security: Prevent direct access to config file
// if (!defined('INCLUDED')) {
//     die('Direct access not permitted');
// }
?>