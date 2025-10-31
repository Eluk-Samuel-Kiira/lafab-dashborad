I'll create a simple, practical guide for adding new tables without losing existing ones.

## **MIGRATION_GUIDE.md**

```markdown
# Simple Migration Guide - Add New Tables Safely

## Quick Start - Add New Table in 3 Steps:

### Step 1: Create Migration File
```bash
php create_migration.php create_{table_name}_table
```

**Examples:**
```bash
php create_migration.php create_users_table
php create_migration.php create_applications_table
php create_migration.php create_categories_table
```

### Step 2: Edit the Generated Migration File

Open the created file in `migrations/` folder and update the `up()` method:

**Example for users table:**
```php
public function up() {
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!db_query($sql)) {
        throw new Exception("Failed to create users table");
    }
    
    // Add indexes for better performance
    db_query("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
}
```

**Example for applications table:**
```php
public function up() {
    $sql = "CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        job_id INTEGER NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (job_id) REFERENCES jobs(id)
    )";
    
    if (!db_query($sql)) {
        throw new Exception("Failed to create applications table");
    }
}
```

### Step 3: Run Migration
Visit in browser:
```
http://localhost/job-dashboard/init_db.php
```

**OR** via command line:
```bash
php init_db.php
```

---

## Common Table Examples:

### 1. Simple Table with Basic Fields
```php
$sql = "CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
```

### 2. Table with Foreign Keys
```php
$sql = "CREATE TABLE IF NOT EXISTS user_preferences (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    setting_name VARCHAR(100) NOT NULL,
    setting_value TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE(user_id, setting_name)
)";
```

### 3. Table with Indexes
```php
$sql = "CREATE TABLE IF NOT EXISTS job_statistics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id INTEGER NOT NULL,
    views INTEGER DEFAULT 0,
    clicks INTEGER DEFAULT 0,
    date DATE NOT NULL,
    FOREIGN KEY (job_id) REFERENCES jobs(id)
)";

// Add indexes after table creation
db_query("CREATE INDEX IF NOT EXISTS idx_job_stats_date ON job_statistics(date)");
db_query("CREATE INDEX IF NOT EXISTS idx_job_stats_job_id ON job_statistics(job_id)");
```

---

## Adding Columns to Existing Tables:

Create a new migration:
```bash
php create_migration.php add_columns_to_jobs_table
```

Then in the migration:
```php
public function up() {
    // Add new columns
    db_query("ALTER TABLE jobs ADD COLUMN salary_range VARCHAR(100)");
    db_query("ALTER TABLE jobs ADD COLUMN remote_ok BOOLEAN DEFAULT 0");
    db_query("ALTER TABLE jobs ADD COLUMN experience_level VARCHAR(50)");
}
```

---

## Insert Default Data:

```php
public function up() {
    // Create table first
    $sql = "CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(50) NOT NULL UNIQUE
    )";
    db_query($sql);
    
    // Insert default data
    $roles = ['admin', 'user', 'moderator'];
    foreach ($roles as $role) {
        db_query("INSERT OR IGNORE INTO roles (name) VALUES (?)", [$role]);
    }
}
```

---

## Important Notes:

✅ **SAFE**: Uses `IF NOT EXISTS` - won't crash if table exists  
✅ **TRACKED**: System remembers which migrations ran  
✅ **REPEATABLE**: Can run `init_db.php` multiple times safely  
✅ **NON-DESTRUCTIVE**: Won't delete existing data  

🚫 **NEVER** manually edit database tables  
🚫 **NEVER** delete migration files that have already run  
🚫 **NEVER** modify existing migrations - create new ones instead  

---

## Check Migration Status:

Visit: `http://localhost/job-dashboard/migration_status.php`

Shows which migrations ran and which are pending.

---

## Need Help?

1. **Migration fails?** Check error message - usually SQL syntax issue
2. **Table not created?** Verify you ran `init_db.php` after creating migration
3. **Data missing?** Check your INSERT statements in the migration

Remember: **Create → Edit → Run** - that's it!
```

## **create_migration.php** (Updated)
```php
<?php
// create_migration.php - Create new migration files
require_once 'config.php';

if (php_sapi_name() === 'cli') {
    // Command line usage
    if ($argc < 2) {
        echo "Usage: php create_migration.php <migration_name>\n";
        echo "Examples:\n";
        echo "  php create_migration.php create_users_table\n";
        echo "  php create_migration.php add_email_to_users\n";
        echo "  php create_migration.php create_categories_table\n";
        exit(1);
    }
    $migration_name = $argv[1];
} else {
    // Web usage
    if (!isset($_GET['name']) || empty($_GET['name'])) {
        die("Please provide migration name: create_migration.php?name=create_users_table");
    }
    $migration_name = $_GET['name'];
}

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
        
        // Add indexes if needed
        // db_query(\"CREATE INDEX IF NOT EXISTS idx_example_name ON example_table(name)\");
        
        // Insert default data if needed
        // db_query(\"INSERT OR IGNORE INTO example_table (name) VALUES ('default')\");
    }
    
    public function down() {
        \$sql = \"DROP TABLE IF EXISTS example_table\";
        db_query(\$sql);
    }
}
?>";

if (file_put_contents($filename, $template)) {
    $message = "Migration created: $filename\n";
    $message .= "Next steps:\n";
    $message .= "1. Edit the migration file\n";
    $message .= "2. Update the SQL to create your table\n";
    $message .= "3. Run: http://localhost/job-dashboard/init_db.php\n";
    
    if (php_sapi_name() === 'cli') {
        echo $message;
    } else {
        echo "<pre>" . htmlspecialchars($message) . "</pre>";
    }
} else {
    $error = "Failed to create migration: $filename";
    if (php_sapi_name() === 'cli') {
        echo $error . "\n";
    } else {
        echo "<pre>" . htmlspecialchars($error) . "</pre>";
    }
}
?>
```

## **Quick Reference Card**

**QUICK_START.md**
```markdown
# 🚀 Quick Start - Add New Table

## 3 COMMANDS ONLY:

1. **Create migration:**
   ```bash
   php create_migration.php create_{your_table}_table
   ```

2. **Edit the generated file in `/migrations/` folder**
   - Update the SQL in `up()` method
   - Use `CREATE TABLE IF NOT EXISTS`

3. **Run migration:**
   ```bash
   php init_db.php
   ```
   **OR visit:** `http://localhost/job-dashboard/init_db.php`

## ✅ THAT'S IT! Your new table is ready.

## 📋 Example:
```bash
php create_migration.php create_users_table
# Edit migrations/001_create_users_table.php
# Visit http://localhost/job-dashboard/init_db.php
```

## 🔍 Check Status:
Visit: `http://localhost/job-dashboard/migration_status.php`
```

This gives you a simple, foolproof process: **Create → Edit → Run** - no complex commands or risks of losing existing data!