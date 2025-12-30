<?php
class PosterAvailability {
    public function up() {
        $sql = "
            -- Run this SQL in your database or create a migration

            -- Table for storing poster availability and challenges
            CREATE TABLE IF NOT EXISTS poster_availability (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                poster_name VARCHAR(100) NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                is_poster_team BOOLEAN DEFAULT 1,
                is_admin_team BOOLEAN DEFAULT 0,
                can_post_weekends BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            -- Table for storing regular posting schedule
            CREATE TABLE IF NOT EXISTS poster_schedule (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                poster_name VARCHAR(100) NOT NULL,
                day_of_week VARCHAR(20) NOT NULL, -- 'monday', 'tuesday', etc
                is_available BOOLEAN DEFAULT 1,
                priority_level INTEGER DEFAULT 3, -- 1=high, 2=medium, 3=low
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(poster_name, day_of_week)
            );

            -- Table for daily assignments and challenges
            CREATE TABLE IF NOT EXISTS daily_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date DATE NOT NULL,
                poster_name VARCHAR(100) NOT NULL,
                assigned_role VARCHAR(50) DEFAULT 'primary', -- 'primary', 'backup', 'admin_cover'
                challenge_reason TEXT, -- Reason for absence if any
                is_completed BOOLEAN DEFAULT 0,
                completion_time DATETIME,
                jobs_posted INTEGER DEFAULT 0,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(date, poster_name)
            );

            -- Table for challenge/absence reporting
            CREATE TABLE IF NOT EXISTS challenges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                poster_name VARCHAR(100) NOT NULL,
                date DATE NOT NULL,
                challenge_type VARCHAR(50), -- 'sickness', 'internet', 'electricity', 'emergency', 'computer'
                severity INTEGER DEFAULT 2, -- 1=low (can post later), 2=medium (needs backup), 3=high (cannot post)
                reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME,
                status VARCHAR(20) DEFAULT 'active' -- 'active', 'resolved'
            );
            ";
        
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