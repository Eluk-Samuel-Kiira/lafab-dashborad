<?php
// init_db.php - Initialize database with migrations
require_once 'config.php';

echo "Starting database initialization...\n";

try {
    // Run migrations
    $success = run_migrations();
    
    if ($success) {
        echo "\nDatabase initialization completed successfully!\n";
        
        // Show current status
        $tables = db_fetch_all("SELECT name FROM sqlite_master WHERE type='table'");
        echo "\nCurrent tables in database:\n";
        foreach ($tables as $table) {
            echo "- " . $table['name'] . "\n";
        }
        
        // Show migration status
        $migrations = db_fetch_all("SELECT migration, batch, created_at FROM migrations ORDER BY batch, id");
        echo "\nExecuted migrations:\n";
        foreach ($migrations as $migration) {
            echo "\n- {$migration['migration']} (Batch: {$migration['batch']})\n";
        }
        
    } else {
        echo "\nDatabase initialization completed with errors.\n";
    }
    
} catch (Exception $e) {
    echo "Error during database initialization: " . $e->getMessage() . "\n";
}
?>