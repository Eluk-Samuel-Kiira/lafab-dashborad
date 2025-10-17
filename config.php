<?php
// config.php - SQLite3 without PDO
$db_file = __DIR__ . '/jobs_dashboard.db';

// Create database directory if it doesn't exist
if (!file_exists(dirname($db_file))) {
    mkdir(dirname($db_file), 0755, true);
}

// Connect to SQLite database
try {
    $db = new SQLite3($db_file);
    $db->enableExceptions(true);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Define websites array for consistent usage
$websites = [
    'greatugandajobs.com',
    'greatkenyanjobs.com', 
    'greattanzaniajobs.com',
    'greatrwandajobs.com',
    'greatzambiajobs.com'
];

// Helper function to execute queries
function db_query($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    
    // Bind parameters if any
    $index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($index++, $param);
    }
    
    return $stmt->execute();
}

// Helper function to fetch all results
function db_fetch_all($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    if (!$stmt) return [];
    
    // Bind parameters if any
    $index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($index++, $param);
    }
    
    $result = $stmt->execute();
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

// Helper function to fetch single row
function db_fetch_one($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    if (!$stmt) return null;
    
    // Bind parameters if any
    $index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($index++, $param);
    }
    
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

// Helper function to get last insert ID
function db_last_id() {
    global $db;
    return $db->lastInsertRowID();
}
?>