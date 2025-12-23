<?php
// config.php - Using .env files with fallback
// ===========================================

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Detect environment
$is_local = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
             $_SERVER['HTTP_HOST'] == '127.0.0.1' ||
             (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] == '127.0.0.1'));

// Load appropriate .env file
$env_file = $is_local ? '.env.local' : '.env.cpanel';

// Define default values as fallback
$default_config = [
    'local' => [
        'DB_JOBS_HOST' => 'localhost',
        'DB_JOBS_USER' => 'root',
        'DB_JOBS_PASS' => '',
        'DB_JOBS_NAME' => 'melafabs_JOBSITyson',
        'DB_TEAM_HOST' => 'localhost',
        'DB_TEAM_USER' => 'root',
        'DB_TEAM_PASS' => '',
        'DB_TEAM_NAME' => 'melafabs_Teamayson'
    ],
    'cpanel' => [
        'DB_JOBS_HOST' => 'localhost',
        'DB_JOBS_USER' => 'melafabs_JOBSITEjkg',
        'DB_JOBS_PASS' => 'KfhLkf645gErBgsF',
        'DB_JOBS_NAME' => 'melafabs_JOBSITyson',
        'DB_TEAM_HOST' => 'localhost',
        'DB_TEAM_USER' => 'melafabs_TEAMHDjkg',
        'DB_TEAM_PASS' => 'KfhTsGs3gErBgsF',
        'DB_TEAM_NAME' => 'melafabs_Teamayson'
    ]
];

// Try to load .env file
$env_config = [];
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
            $env_config[$key] = $value;
        }
    }
}

// Choose which config to use
$config_to_use = $is_local ? $default_config['local'] : $default_config['cpanel'];

// Override with .env values if they exist
foreach ($env_config as $key => $value) {
    $config_to_use[$key] = $value;
}

// Define constants
foreach ($config_to_use as $key => $value) {
    if (!defined($key)) {
        define($key, $value);
    }
}

// Common Settings
define('DB_CHARSET', 'utf8mb4');
define('BULK_CHUNK_SIZE_INSERT', 100);
define('BULK_CHUNK_SIZE_UPDATE', 50);
define('BULK_CHUNK_SIZE_FETCH', 1000);
define('MAX_LOG_ROWS', 10);

date_default_timezone_set('UTC');

// Security
// if (!defined('INCLUDED')) {
//     die('Direct access not permitted');
// }

// Function to get database credentials by name
function getDBCredentials($db_name) {
    if ($db_name === DB_JOBS_NAME) {
        return [
            'host' => DB_JOBS_HOST,
            'user' => DB_JOBS_USER,
            'pass' => DB_JOBS_PASS,
            'name' => DB_JOBS_NAME
        ];
    } elseif ($db_name === DB_TEAM_NAME) {
        return [
            'host' => DB_TEAM_HOST,
            'user' => DB_TEAM_USER,
            'pass' => DB_TEAM_PASS,
            'name' => DB_TEAM_NAME
        ];
    } else {
        throw new Exception("Database configuration not found for: $db_name");
    }
}

// Debug function to check current environment
function debugEnvironment() {
    return [
        'is_local' => (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                       $_SERVER['HTTP_HOST'] == '127.0.0.1'),
        'host' => $_SERVER['HTTP_HOST'],
        'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'not_set',
        'jobs_db' => DB_JOBS_NAME,
        'team_db' => DB_TEAM_NAME,
        'jobs_user' => DB_JOBS_USER,
        'team_user' => DB_TEAM_USER
    ];
}
?>