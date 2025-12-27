<?php
// config.php - SIMPLIFIED for shared JobSite database
// ====================================================

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Detect environment
$is_local = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
             $_SERVER['HTTP_HOST'] == '127.0.0.1' ||
             (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] == '127.0.0.1'));

// Load appropriate .env file
$env_file = $is_local ? '.env.local' : '.env.cpanel';

// Define country codes
$country_codes = [
    'ug' => 'Uganda',
    'ke' => 'Kenya', 
    'tz' => 'Tanzania',
    'rw' => 'Rwanda',
    'zm' => 'Zambia',
    'mw' => 'Malawi'
];

// Get current country from URL or default to 'rw' for Rwanda
if (isset($_GET['country']) && isset($country_codes[strtolower($_GET['country'])])) {
    $current_country = strtolower($_GET['country']);
} else {
    // Default to Rwanda if no country specified
    $current_country = 'rw';
}

define('CURRENT_COUNTRY', $current_country);
define('CURRENT_COUNTRY_NAME', $country_codes[$current_country]);

// Initialize config array
$config = [];

// Try to load .env file
if (file_exists(__DIR__ . '/' . $env_file)) {
    $lines = file(__DIR__ . '/' . $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        // Handle lines with = sign
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $config[$key] = $value;
        }
    }
} else {
    die("Error: Configuration file '$env_file' not found. Please create it.");
}

// DEFINE SHARED DATABASE CONSTANTS (NOT country-specific)
// --------------------------------------------------------
// Shared JobSite database (same for all countries)
define('DB_JOBS_HOST', $config['DB_JOBS_HOST'] ?? 'localhost');
define('DB_JOBS_USER', $config['DB_JOBS_USER'] ?? 'root');
define('DB_JOBS_PASS', $config['DB_JOBS_PASS'] ?? '');
define('DB_JOBS_NAME', $config['DB_JOBS_NAME'] ?? 'melafabs_JOBSITyson');

// Shared TeamSite database (same for all countries)
define('DB_TEAM_HOST', $config['DB_TEAM_HOST'] ?? 'localhost');
define('DB_TEAM_USER', $config['DB_TEAM_USER'] ?? 'root');
define('DB_TEAM_PASS', $config['DB_TEAM_PASS'] ?? '');
define('DB_TEAM_NAME', $config['DB_TEAM_NAME'] ?? 'melafabs_Teamayson');

// Common Settings
define('DB_CHARSET', 'utf8mb4');
define('BULK_CHUNK_SIZE_INSERT', 100);
define('BULK_CHUNK_SIZE_UPDATE', 50);
define('BULK_CHUNK_SIZE_FETCH', 1000);
define('MAX_LOG_ROWS', 10);

date_default_timezone_set('UTC');

// Function to get database credentials by name - SIMPLIFIED
function getDBCredentials($db_name) {
    // Check if it's the shared jobsite database
    if ($db_name === DB_JOBS_NAME) {
        return [
            'host' => DB_JOBS_HOST,
            'user' => DB_JOBS_USER,
            'pass' => DB_JOBS_PASS,
            'name' => DB_JOBS_NAME
        ];
    }
    
    // Check if it's the shared teamsite database
    if ($db_name === DB_TEAM_NAME) {
        return [
            'host' => DB_TEAM_HOST,
            'user' => DB_TEAM_USER,
            'pass' => DB_TEAM_PASS,
            'name' => DB_TEAM_NAME
        ];
    }
    
    throw new Exception("Database configuration not found for: $db_name");
}

// Debug function
function debugEnvironment() {
    global $is_local, $env_file;
    return [
        'is_local' => $is_local,
        'host' => $_SERVER['HTTP_HOST'],
        'current_country' => CURRENT_COUNTRY_NAME,
        'jobs_db' => DB_JOBS_NAME,
        'team_db' => DB_TEAM_NAME,
        'env_file' => $env_file
    ];
}
?>