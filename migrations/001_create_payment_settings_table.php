<?php
class CreatePaymentSettingsTable {
    public function up() {
        $sql = "CREATE TABLE IF NOT EXISTS payment_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            poster_name VARCHAR(255) NOT NULL UNIQUE,
            jobs_per_payment INTEGER NOT NULL DEFAULT 50,
            payment_amount DECIMAL(10,2) NOT NULL DEFAULT 100.00,
            currency VARCHAR(10) DEFAULT 'USD',
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!db_query($sql)) {
            throw new Exception("Failed to create payment_settings table");
        }
        
        // Create index for better performance
        db_query("CREATE INDEX IF NOT EXISTS idx_payment_settings_poster ON payment_settings(poster_name)");
        db_query("CREATE INDEX IF NOT EXISTS idx_payment_settings_active ON payment_settings(is_active)");
    }
    
    public function down() {
        $sql = "DROP TABLE IF EXISTS payment_settings";
        db_query($sql);
    }
}
?>