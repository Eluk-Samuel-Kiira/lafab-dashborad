<?php
// create_migration.php - Create new migration files
if ($argc < 2) {
    echo "Usage: php create_migration.php <migration_name>\n";
    echo "Example: php create_migration.php create_users_table\n";
    exit(1);
}

$migration_name = $argv[1];
$migrations_dir = __DIR__ . '/migrations';

if (!file_exists($migrations_dir)) {
    mkdir($migrations_dir, 0755, true);
}

// Get next migration number
$existing_migrations = glob($migrations_dir . '/*.php');
$next_number = count($existing_migrations) + 1;
$migration_number = str_pad($next_number, 3, '0', STR_PAD_LEFT);

$filename = $migrations_dir . '/' . $migration_number . '_' . $migration_name . '.php';

// Convert migration name to class name
$class_name = str_replace('_', ' ', $migration_name);
$class_name = ucwords($class_name);
$class_name = str_replace(' ', '', $class_name);

$template = "<?php
class {$class_name} {
    public function up() {
        \$sql = \"CREATE TABLE IF NOT EXISTS example_table (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )\";
        
        if (!db_query(\$sql)) {
            throw new Exception(\"Failed to create example_table\");
        }
    }
    
    public function down() {
        \$sql = \"DROP TABLE IF EXISTS example_table\";
        db_query(\$sql);
    }
}
?>";

if (file_put_contents($filename, $template)) {
    echo "Migration created: $filename\n";
} else {
    echo "Failed to create migration: $filename\n";
}
?>