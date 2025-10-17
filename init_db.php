<?php
require_once 'config.php';

echo "<h2>Initializing Jobs Dashboard Database</h2>";

// Drop existing tables if they exist
$tables = ['seo_rankings', 'job_postings', 'posters'];
foreach ($tables as $table) {
    try {
        $db->exec("DROP TABLE IF EXISTS $table");
        echo "✓ Dropped table: $table<br>";
    } catch (Exception $e) {
        echo "Note: Could not drop $table: " . $e->getMessage() . "<br>";
    }
}

// Create tables with new structure
$tables = [
    "CREATE TABLE IF NOT EXISTS posters (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS job_postings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        website TEXT NOT NULL,
        poster_name TEXT NOT NULL,
        job_count INTEGER NOT NULL,
        post_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS seo_rankings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        website TEXT NOT NULL,
        page_url TEXT NOT NULL,
        keyword TEXT NOT NULL,
        page_number INTEGER NOT NULL,
        position_on_page INTEGER NOT NULL,
        week_start DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $table_sql) {
    try {
        if ($db->exec($table_sql)) {
            echo "✓ Table created successfully<br>";
        } else {
            echo "✗ Error creating table<br>";
        }
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "<br>";
    }
}

// Insert default posters
$default_posters = ['Mukhwana Colette Donata', 'Viola Charlotte', 'Mathias Kyam', 'Judith Kiiza', 'Batuuka kevin Joseph',
                     'MOSES WAMANYA', 'Samuel Kiira', 'Martin Mubiru', 'Twesigye Jordan', 'Musinguzi', 'Patricia', 'Evie',
                     'Sanyu', ''];
foreach ($default_posters as $poster) {
    try {
        $stmt = $db->prepare("INSERT OR IGNORE INTO posters (name) VALUES (?)");
        $stmt->bindValue(1, $poster, SQLITE3_TEXT);
        if ($stmt->execute()) {
            echo "✓ Added poster: $poster<br>";
        }
    } catch (Exception $e) {
        echo "Note: " . $e->getMessage() . "<br>";
    }
}

// Add updated social media tables
$social_tables = [
    "CREATE TABLE IF NOT EXISTS social_media_platforms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        engagement_metric TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS social_media_daily_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        platform_id INTEGER NOT NULL,
        country TEXT NOT NULL,
        stat_date DATE NOT NULL,
        followers INTEGER NOT NULL DEFAULT 0,
        engagements INTEGER NOT NULL DEFAULT 0,
        likes INTEGER DEFAULT 0,
        shares INTEGER DEFAULT 0,
        comments INTEGER DEFAULT 0,
        impressions INTEGER DEFAULT 0,
        reach INTEGER DEFAULT 0,
        video_views INTEGER DEFAULT 0,
        link_clicks INTEGER DEFAULT 0,
        retweets INTEGER DEFAULT 0,
        reactions INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (platform_id) REFERENCES social_media_platforms (id)
    )",
    
    "CREATE TABLE IF NOT EXISTS social_media_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        platform_id INTEGER NOT NULL,
        country TEXT NOT NULL,
        post_date DATE NOT NULL,
        content_type TEXT NOT NULL,
        content_description TEXT,
        engagements INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (platform_id) REFERENCES social_media_platforms (id)
    )"
];

foreach ($social_tables as $table_sql) {
    try {
        if ($db->exec($table_sql)) {
            echo "✓ Social media table created successfully<br>";
        } else {
            echo "✗ Error creating social media table<br>";
        }
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "<br>";
    }
}

// Insert social media platforms with their specific metrics
$platforms = [
    ['Facebook', 'reactions'],
    ['LinkedIn', 'impressions'],
    ['Twitter', 'retweets'],
    ['Telegram', 'engagements'],
    ['TikTok', 'video_views'],
    ['WhatsApp', 'shares']
];

foreach ($platforms as $platform) {
    try {
        $stmt = $db->prepare("INSERT OR IGNORE INTO social_media_platforms (name, engagement_metric) VALUES (?, ?)");
        $stmt->bindValue(1, $platform[0], SQLITE3_TEXT);
        $stmt->bindValue(2, $platform[1], SQLITE3_TEXT);
        if ($stmt->execute()) {
            echo "✓ Added platform: {$platform[0]}<br>";
        }
    } catch (Exception $e) {
        echo "Note: " . $e->getMessage() . "<br>";
    }
}

echo "<h3 style='color: green;'>Database initialized successfully with new SEO structure!</h3>";
echo "<p><a href='pages/dashboard.php'>Go to Dashboard</a></p>";
?>