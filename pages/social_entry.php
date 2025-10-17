<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get platforms
$platforms = db_fetch_all("SELECT * FROM social_media_platforms ORDER BY name");

// Countries
$countries = ['Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Zambia'];

if ($_POST && !empty($_POST['platform_id'])) {
    $platform_id = intval($_POST['platform_id']);
    $country = $_POST['country'];
    $stat_date = $_POST['stat_date'];
    
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
    
    try {
        // Check if entry exists for this date, platform, and country
        $check_sql = "SELECT id FROM social_media_daily_stats WHERE platform_id = ? AND country = ? AND stat_date = ?";
        $existing = db_fetch_one($check_sql, [$platform_id, $country, $stat_date]);
        
        if ($existing) {
            // Update existing entry
            $sql = "UPDATE social_media_daily_stats SET 
                    followers = ?, engagements = ?, likes = ?, shares = ?, comments = ?, 
                    impressions = ?, reach = ?, video_views = ?, link_clicks = ?, 
                    retweets = ?, reactions = ?
                    WHERE id = ?";
            
            $params = [
                $followers, $engagements, $likes, $shares, $comments,
                $impressions, $reach, $video_views, $link_clicks,
                $retweets, $reactions, $existing['id']
            ];
        } else {
            // Insert new entry
            $sql = "INSERT INTO social_media_daily_stats 
                    (platform_id, country, stat_date, followers, engagements, likes, shares, comments, 
                     impressions, reach, video_views, link_clicks, retweets, reactions) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $platform_id, $country, $stat_date, $followers, $engagements, $likes, $shares, $comments,
                $impressions, $reach, $video_views, $link_clicks, $retweets, $reactions
            ];
        }
        
        if (db_query($sql, $params)) {
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
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add Social Media Stats</h1>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" id="socialForm">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Platform</label>
                            <select name="platform_id" class="form-select" required id="platformSelect">
                                <option value="">Select Platform</option>
                                <?php foreach ($platforms as $platform): ?>
                                    <option value="<?php echo $platform['id']; ?>">
                                        <?php echo $platform['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Country</label>
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
                            <label class="form-label">Date</label>
                            <input type="date" name="stat_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                
                <!-- Common Metrics for All Platforms -->
                <div class="card bg-light mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Common Metrics (All Platforms)</h6>
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
                
                <!-- Platform-specific metrics -->
                <div class="card bg-light">
                    <div class="card-header">
                        <h6 class="mb-0">Platform-Specific Metrics</h6>
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
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Link Clicks</label>
                                    <input type="number" name="link_clicks" class="form-control" min="0" value="0">
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
                
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-save"></i> Save Social Media Stats
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Recent Stats -->
    <div class="card mt-4">
        <div class="card-header">
            <h6>Recent Social Media Stats</h6>
        </div>
        <div class="card-body">
            <?php if (empty($recent_stats)): ?>
                <p class="text-muted">No recent social media stats found.</p>
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
                                <th>Likes</th>
                                <th>Shares</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_stats as $stat): ?>
                                <tr>
                                    <td><?php echo date('M j', strtotime($stat['stat_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($stat['platform_name']); ?></td>
                                    <td><?php echo htmlspecialchars($stat['country']); ?></td>
                                    <td><strong><?php echo number_format($stat['followers']); ?></strong></td>
                                    <td><?php echo number_format($stat['engagements']); ?></td>
                                    <td><?php echo number_format($stat['likes']); ?></td>
                                    <td><?php echo number_format($stat['shares']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>