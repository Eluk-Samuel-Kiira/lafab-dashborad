<?php
class QrRules {
    public function up() {
        $sql = "CREATE TABLE IF NOT EXISTS qr_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                rule_type VARCHAR(50) DEFAULT 'general', -- 'general', 'technical', 'quality', etc.
                priority INTEGER DEFAULT 5, -- 1-10, 1 being highest
                is_active BOOLEAN DEFAULT 1,
                website_filter VARCHAR(100) DEFAULT 'all', -- 'all' or specific website
                poster_filter VARCHAR(100) DEFAULT 'all', -- 'all' or specific poster
                min_job_count INTEGER DEFAULT 0, -- Minimum jobs to apply rule
                max_job_count INTEGER DEFAULT NULL, -- Maximum jobs to apply rule
                effective_date DATE DEFAULT CURRENT_DATE,
                expiry_date DATE DEFAULT NULL,
                created_by VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            -- Create index for better performance
            CREATE INDEX idx_qr_rules_active ON qr_rules(is_active);
            CREATE INDEX idx_qr_rules_type ON qr_rules(rule_type);
            CREATE INDEX idx_qr_rules_priority ON qr_rules(priority);
        )";
        
        if (!db_query($sql)) {
            throw new Exception("Failed to create example_table");
        }
    }
    
    public function down() {
        $sql = "DROP TABLE IF EXISTS example_table";
        db_query($sql);
    }
}
?>