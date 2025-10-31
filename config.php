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

// Migration functions
function create_migrations_table() {
    $sql = "CREATE TABLE IF NOT EXISTS migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration VARCHAR(255) NOT NULL,
        batch INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    return db_query($sql);
}

function get_executed_migrations() {
    $sql = "SELECT migration FROM migrations ORDER BY id";
    $results = db_fetch_all($sql);
    return array_column($results, 'migration');
}

function record_migration($migration_name, $batch) {
    $sql = "INSERT INTO migrations (migration, batch) VALUES (?, ?)";
    return db_query($sql, [$migration_name, $batch]);
}

function get_next_batch_number() {
    $sql = "SELECT MAX(batch) as max_batch FROM migrations";
    $result = db_fetch_one($sql);
    return ($result && $result['max_batch']) ? $result['max_batch'] + 1 : 1;
}

function run_migrations() {
    create_migrations_table();
    
    $migrations_dir = __DIR__ . '/migrations';
    if (!file_exists($migrations_dir)) {
        mkdir($migrations_dir, 0755, true);
    }
    
    $executed_migrations = get_executed_migrations();
    $migration_files = glob($migrations_dir . '/*.php');
    $new_migrations = [];
    
    // Sort migration files by name
    sort($migration_files);
    
    $batch = get_next_batch_number();
    $has_errors = false;
    
    foreach ($migration_files as $file) {
        $migration_name = basename($file, '.php');
        
        // Skip if already executed
        if (in_array($migration_name, $executed_migrations)) {
            continue;
        }
        
        echo "Running migration: $migration_name\n";
        
        try {
            // Include the migration file
            require_once $file;
            
            // The migration file should define a class with up() and down() methods
            $class_name = get_migration_class_name($migration_name);
            
            if (class_exists($class_name)) {
                $migration = new $class_name();
                $migration->up();
                
                // Record the migration
                record_migration($migration_name, $batch);
                echo "✓ $migration_name completed successfully\n";
            } else {
                throw new Exception("Migration class $class_name not found");
            }
            
        } catch (Exception $e) {
            echo "✗ $migration_name failed: " . $e->getMessage() . "\n";
            $has_errors = true;
            break;
        }
    }
    
    if (!$has_errors) {
        echo "All migrations completed successfully!\n";
    }
    
    return !$has_errors;
}

function get_migration_class_name($migration_name) {
    // Convert filename like 001_create_jobs_table to CreateJobsTable
    $name = preg_replace('/^\d+_/', '', $migration_name);
    $name = str_replace('_', ' ', $name);
    $name = ucwords($name);
    $name = str_replace(' ', '', $name);
    return $name;
}
?>