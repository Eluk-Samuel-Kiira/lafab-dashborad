<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle delete action
if (isset($_POST['delete_stat_id'])) {
    $stat_id = intval($_POST['delete_stat_id']);
    
    try {
        // Delete the social media statistic
        $stmt = $db->prepare("DELETE FROM social_media_daily_stats WHERE id = ?");
        $stmt->bindValue(1, $stat_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $success = "Social media statistic deleted successfully!";
        } else {
            $error = "Error deleting social media statistic!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get platforms
$platforms = db_fetch_all("SELECT * FROM social_media_platforms ORDER BY name");

// Countries
$countries = ['Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Zambia'];

// Content types
$content_types = [
    'Video', 'Image', 'Carousel', 'Story', 'Reel', 'Text Post', 
    'Link Share', 'Poll', 'Live Video', 'Event', 'Product Showcase'
];

if ($_POST && !empty($_POST['platform_id']) && !isset($_POST['delete_stat_id'])) {
    $platform_id = intval($_POST['platform_id']);
    $country = $_POST['country'];
    $stat_date = $_POST['stat_date'];
    $content_type = $_POST['content_type'] ?? '';
    $content_description = trim($_POST['content_description'] ?? '');
    
    // Common metrics for all platforms
    $followers = intval($_POST['followers'] ?? 0);
    $engagements = intval($_POST['engagements'] ?? 0);
    
    // Platform-specific metrics
    $likes = intval($_POST['likes'] ?? 0);
    $shares = intval($_POST['shares'] ?? 0);
    $comments = intval($_POST['comments'] ?? 0);
    $impressions = intval($_POST['impressions'] ?? 0);
    $reach = intval($_POST['reach'] ?? 0);
    $video_views = intval($_POST['video_views'] ?? 0);
    $link_clicks = intval($_POST['link_clicks'] ?? 0);
    $retweets = intval($_POST['retweets'] ?? 0);
    $reactions = intval($_POST['reactions'] ?? 0);
    $saves = intval($_POST['saves'] ?? 0);
    
    try {
        // Check if entry exists for this date, platform, and country
        $check_sql = "SELECT id FROM social_media_daily_stats WHERE platform_id = ? AND country = ? AND stat_date = ?";
        $existing = db_fetch_one($check_sql, [$platform_id, $country, $stat_date]);
        
        if ($existing) {
            // Update existing entry
            $sql = "UPDATE social_media_daily_stats SET 
                    followers = ?, engagements = ?, likes = ?, shares = ?, comments = ?, 
                    impressions = ?, reach = ?, video_views = ?, link_clicks = ?, 
                    retweets = ?, reactions = ?, saves = ?
                    WHERE id = ?";
            
            $params = [
                $followers, $engagements, $likes, $shares, $comments,
                $impressions, $reach, $video_views, $link_clicks,
                $retweets, $reactions, $saves, $existing['id']
            ];
        } else {
            // Insert new entry
            $sql = "INSERT INTO social_media_daily_stats 
                    (platform_id, country, stat_date, followers, engagements, likes, shares, comments, 
                     impressions, reach, video_views, link_clicks, retweets, reactions, saves) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $platform_id, $country, $stat_date, $followers, $engagements, $likes, $shares, $comments,
                $impressions, $reach, $video_views, $link_clicks, $retweets, $reactions, $saves
            ];
        }
        
        if (db_query($sql, $params)) {
            // Also save post details if content type is provided
            if (!empty($content_type)) {
                $post_sql = "INSERT INTO social_media_posts 
                            (platform_id, country, post_date, content_type, content_description, engagements) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                db_query($post_sql, [$platform_id, $country, $stat_date, $content_type, $content_description, $engagements]);
            }
            
            $success = "Social media data saved successfully!";
        } else {
            $error = "Error saving social media data!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get recent stats for reference
$recent_stats = db_fetch_all("
    SELECT sms.*, p.name as platform_name 
    FROM social_media_daily_stats sms 
    JOIN social_media_platforms p ON sms.platform_id = p.id 
    ORDER BY sms.stat_date DESC, sms.followers DESC 
    LIMIT 10
");

// Get growth trends for context
$growth_trends = db_fetch_all("
    SELECT 
        p.name as platform,
        sms.country,
        sms.stat_date,
        sms.followers,
        sms.engagements,
        LAG(sms.followers) OVER (PARTITION BY p.name, sms.country ORDER BY sms.stat_date) as prev_followers,
        LAG(sms.engagements) OVER (PARTITION BY p.name, sms.country ORDER BY sms.stat_date) as prev_engagements
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE sms.stat_date >= date('now', '-7 days')
    ORDER BY sms.stat_date DESC, p.name, sms.country
");
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Social Media Analytics Entry</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="social_stats.php" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar"></i> View Analytics
            </a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Entry Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-plus-circle"></i> Add Social Media Metrics</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="socialForm">
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Platform <span class="text-danger">*</span></label>
                                    <select name="platform_id" class="form-select" required id="platformSelect">
                                        <option value="">Select Platform</option>
                                        <?php foreach ($platforms as $platform): ?>
                                            <option value="<?php echo $platform['id']; ?>" data-metric="<?php echo $platform['engagement_metric']; ?>">
                                                <?php echo $platform['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Country <span class="text-danger">*</span></label>
                                    <select name="country" class="form-select" required>
                                        <option value="">Select Country</option>
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo $country; ?>"><?php echo $country; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="stat_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Content Information -->
                        <div class="card bg-light mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Content Information (Optional)</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Content Type</label>
                                            <select name="content_type" class="form-select">
                                                <option value="">Select Content Type</option>
                                                <?php foreach ($content_types as $type): ?>
                                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Content Description</label>
                                            <textarea name="content_description" class="form-control" rows="2" placeholder="Brief description of the post content..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Core Metrics -->
                        <div class="card bg-light mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Core Metrics <span class="text-danger">*</span></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Followers Count</label>
                                            <input type="number" name="followers" class="form-control" min="0" value="0" required>
                                            <div class="form-text">Total followers/subscribers</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Total Engagements</label>
                                            <input type="number" name="engagements" class="form-control" min="0" value="0" required>
                                            <div class="form-text">Total interactions (likes, comments, shares, etc.)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Engagement Metrics -->
                        <div class="card bg-light mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Engagement Metrics</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Likes/Reactions</label>
                                            <input type="number" name="likes" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Shares/Retweets</label>
                                            <input type="number" name="shares" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Comments</label>
                                            <input type="number" name="comments" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Saves/Bookmarks</label>
                                            <input type="number" name="saves" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Retweets (Twitter)</label>
                                            <input type="number" name="retweets" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Reactions (Facebook)</label>
                                            <input type="number" name="reactions" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reach & Performance -->
                        <div class="card bg-light mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Reach & Performance Metrics</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Impressions</label>
                                            <input type="number" name="impressions" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Reach</label>
                                            <input type="number" name="reach" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Video Views</label>
                                            <input type="number" name="video_views" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Link Clicks</label>
                                            <input type="number" name="link_clicks" class="form-control" min="0" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Engagement Rate Preview</label>
                                            <div class="form-control bg-white">
                                                <small class="text-muted" id="engagementRatePreview">
                                                    Enter followers and engagements to see engagement rate
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success btn-lg me-2">
                                    <i class="fas fa-save"></i> Save Social Media Metrics
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo"></i> Reset Form
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Stats & Recent Activity -->
        <div class="col-lg-4">
            <!-- Quick Stats -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Today's Overview</h6>
                </div>
                <div class="card-body">
                    <?php
                    $today_stats = db_fetch_one("
                        SELECT 
                            COUNT(*) as total_entries,
                            SUM(followers) as total_followers,
                            SUM(engagements) as total_engagements
                        FROM social_media_daily_stats 
                        WHERE stat_date = ?
                    ", [date('Y-m-d')]);
                    ?>
                    <div class="row text-center">
                        <div class="col-4">
                            <h5 class="text-primary"><?php echo $today_stats['total_entries'] ?? 0; ?></h5>
                            <small class="text-muted">Entries</small>
                        </div>
                        <div class="col-4">
                            <h5 class="text-success"><?php echo number_format($today_stats['total_followers'] ?? 0); ?></h5>
                            <small class="text-muted">Followers</small>
                        </div>
                        <div class="col-4">
                            <h5 class="text-info"><?php echo number_format($today_stats['total_engagements'] ?? 0); ?></h5>
                            <small class="text-muted">Engagements</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Growth Trends -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Recent Growth Trends</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($growth_trends)): ?>
                        <p class="text-muted text-center">No recent data for trends</p>
                    <?php else: ?>
                        <div style="max-height: 200px; overflow-y: auto;">
                            <?php 
                            $current_platform = '';
                            foreach ($growth_trends as $trend): 
                                if ($current_platform !== $trend['platform'] . $trend['country']):
                                    $current_platform = $trend['platform'] . $trend['country'];
                                    $follower_growth = $trend['prev_followers'] ? (($trend['followers'] - $trend['prev_followers']) / $trend['prev_followers'] * 100) : 0;
                            ?>
                                <div class="mb-2 p-2 border rounded">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo $trend['platform']; ?></strong>
                                        <span class="badge bg-<?php echo $follower_growth > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo number_format($follower_growth, 1); ?>%
                                        </span>
                                    </div>
                                    <small class="text-muted"><?php echo $trend['country']; ?> • <?php echo date('M j', strtotime($trend['stat_date'])); ?></small>
                                </div>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Platform Tips -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Platform Tips</h6>
                </div>
                <div class="card-body">
                    <div id="platformTips">
                        <p class="text-muted text-center">Select a platform to see specific tips</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Stats -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Recent Social Media Entries</h6>
            <span class="badge bg-primary">Last 10 entries</span>
        </div>
        <div class="card-body">
            <?php if (empty($recent_stats)): ?>
                <p class="text-muted text-center py-3">No social media entries found. Add your first entry above.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Platform</th>
                                <th>Country</th>
                                <th>Followers</th>
                                <th>Engagements</th>
                                <th>Engagement Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_stats as $stat): 
                                $engagement_rate = $stat['followers'] > 0 ? ($stat['engagements'] / $stat['followers'] * 100) : 0;
                            ?>
                                <tr>
                                    <td><?php echo date('M j', strtotime($stat['stat_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($stat['platform_name']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($stat['country']); ?></td>
                                    <td><strong><?php echo number_format($stat['followers']); ?></strong></td>
                                    <td><?php echo number_format($stat['engagements']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $engagement_rate >= 5 ? 'success' : ($engagement_rate >= 2 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($engagement_rate, 2); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this entry?');">
                                            <input type="hidden" name="delete_stat_id" value="<?php echo $stat['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Platform-specific tips
    const platformTips = {
        'Facebook': 'Focus on video content and stories. Use Facebook Insights for detailed analytics.',
        'LinkedIn': 'Professional content performs best. Articles and company updates get good engagement.',
        'Twitter': 'Short, timely content with hashtags. Threads perform well for detailed topics.',
        'Telegram': 'Channel updates and community engagement. Use polls and discussions.',
        'TikTok': 'Short, engaging videos with trending sounds. Consistency is key.',
        'WhatsApp': 'Broadcast messages and group engagement. Personal touch works best.'
    };

    // Update tips based on platform selection
    document.getElementById('platformSelect').addEventListener('change', function() {
        const platformName = this.options[this.selectedIndex].text;
        const tipsContainer = document.getElementById('platformTips');
        
        if (platformName in platformTips) {
            tipsContainer.innerHTML = `
                <div class="alert alert-info">
                    <h6>${platformName} Tips:</h6>
                    <p class="mb-0">${platformTips[platformName]}</p>
                </div>
            `;
        } else {
            tipsContainer.innerHTML = '<p class="text-muted text-center">Select a platform to see specific tips</p>';
        }
    });

    // Calculate engagement rate in real-time
    function calculateEngagementRate() {
        const followers = parseInt(document.querySelector('input[name="followers"]').value) || 0;
        const engagements = parseInt(document.querySelector('input[name="engagements"]').value) || 0;
        const preview = document.getElementById('engagementRatePreview');
        
        if (followers > 0) {
            const rate = (engagements / followers * 100).toFixed(2);
            preview.innerHTML = `<strong>Engagement Rate: ${rate}%</strong>`;
            preview.className = 'text-success';
        } else {
            preview.innerHTML = 'Enter followers and engagements to see engagement rate';
            preview.className = 'text-muted';
        }
    }

    // Add event listeners for real-time calculations
    document.querySelector('input[name="followers"]').addEventListener('input', calculateEngagementRate);
    document.querySelector('input[name="engagements"]').addEventListener('input', calculateEngagementRate);

    // Initialize engagement rate calculation
    document.addEventListener('DOMContentLoaded', calculateEngagementRate);
</script>

<?php require_once '../includes/footer.php'; ?>