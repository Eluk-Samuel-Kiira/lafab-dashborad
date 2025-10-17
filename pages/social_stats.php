<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get filter parameters
$platform_filter = $_GET['platform'] ?? '';
$country_filter = $_GET['country'] ?? '';
$date_range = $_GET['date_range'] ?? 'last_30_days';

// Calculate date ranges
$end_date = date('Y-m-d');
switch ($date_range) {
    case 'last_7_days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        break;
    case 'last_3_months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        break;
    default: // last_30_days
        $start_date = date('Y-m-d', strtotime('-30 days'));
}

// Build WHERE clause
$where_conditions = ["stat_date BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if ($platform_filter) {
    $where_conditions[] = "p.name = ?";
    $params[] = $platform_filter;
}

if ($country_filter) {
    $where_conditions[] = "sms.country = ?";
    $params[] = $country_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get platforms for filter
$platforms = db_fetch_all("SELECT name FROM social_media_platforms ORDER BY name");
$countries = ['Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Zambia'];

// Calculate growth percentages
$current_period_stats = db_fetch_one("
    SELECT 
        SUM(followers) as total_followers,
        SUM(engagements) as total_engagements,
        COUNT(DISTINCT platform_id) as platform_count
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
", $params);

// Previous period for comparison
$previous_start_date = date('Y-m-d', strtotime($start_date . ' -1 month'));
$previous_end_date = date('Y-m-d', strtotime($end_date . ' -1 month'));

$previous_period_stats = db_fetch_one("
    SELECT 
        SUM(followers) as total_followers,
        SUM(engagements) as total_engagements
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE stat_date BETWEEN ? AND ?
", [$previous_start_date, $previous_end_date]);

// Calculate growth percentages
$follower_growth = 0;
$engagement_growth = 0;

if ($previous_period_stats['total_followers'] > 0) {
    $follower_growth = (($current_period_stats['total_followers'] - $previous_period_stats['total_followers']) / $previous_period_stats['total_followers']) * 100;
}

if ($previous_period_stats['total_engagements'] > 0) {
    $engagement_growth = (($current_period_stats['total_engagements'] - $previous_period_stats['total_engagements']) / $previous_period_stats['total_engagements']) * 100;
}

// Overall growth percentage (average of follower and engagement growth)
$overall_growth = ($follower_growth + $engagement_growth) / 2;

// Platform performance
$platform_stats = db_fetch_all("
    SELECT 
        p.name as platform,
        MAX(sms.followers) as current_followers,
        MAX(sms.engagements) as current_engagements,
        AVG(sms.engagements) as avg_engagements,
        COUNT(sms.id) as days_tracked
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
    GROUP BY p.name
    ORDER BY current_followers DESC
", $params);

// Country performance
$country_stats = db_fetch_all("
    SELECT 
        country,
        SUM(followers) as total_followers,
        SUM(engagements) as total_engagements,
        COUNT(DISTINCT platform_id) as platforms_count
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
    GROUP BY country
    ORDER BY total_followers DESC
", $params);

// Daily trends for charts
$daily_trends = db_fetch_all("
    SELECT 
        stat_date,
        p.name as platform,
        AVG(followers) as avg_followers,
        AVG(engagements) as avg_engagements
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
    GROUP BY stat_date, p.name
    ORDER BY stat_date ASC
", $params);
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Social Media Statistics</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <span class="text-muted"><?php echo date('F j, Y'); ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h6>Filters</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Platform</label>
                    <select name="platform" class="form-select">
                        <option value="">All Platforms</option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?php echo $platform['name']; ?>" <?php echo $platform_filter === $platform['name'] ? 'selected' : ''; ?>>
                                <?php echo $platform['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Country</label>
                    <select name="country" class="form-select">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo $country; ?>" <?php echo $country_filter === $country ? 'selected' : ''; ?>>
                                <?php echo $country; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <select name="date_range" class="form-select">
                        <option value="last_7_days" <?php echo $date_range === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="last_30_days" <?php echo $date_range === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="last_month" <?php echo $date_range === 'last_month' ? 'selected' : ''; ?>>Last Calendar Month</option>
                        <option value="last_3_months" <?php echo $date_range === 'last_3_months' ? 'selected' : ''; ?>>Last 3 Months</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MEAL Growth Overview -->
    <div class="card mb-4 border-success">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">MEAL Growth Overview - All Platforms Combined</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4">
                    <div class="mb-3">
                        <h3 class="<?php echo $overall_growth >= 10 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($overall_growth, 1); ?>%
                        </h3>
                        <h6>Overall Growth Percentage</h6>
                        <p class="text-muted">Combined follower & engagement growth</p>
                        <div class="<?php echo $overall_growth >= 10 ? 'text-success' : 'text-danger'; ?>">
                            <strong>
                                <?php echo $overall_growth >= 10 ? '✓ TARGET ACHIEVED' : '✗ BELOW TARGET'; ?>
                            </strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <h3 class="<?php echo $follower_growth >= 10 ? 'text-success' : 'text-warning'; ?>">
                            <?php echo number_format($follower_growth, 1); ?>%
                        </h3>
                        <h6>Follower Growth</h6>
                        <p class="text-muted">Total followers across all platforms</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <h3 class="<?php echo $engagement_growth >= 10 ? 'text-success' : 'text-warning'; ?>">
                            <?php echo number_format($engagement_growth, 1); ?>%
                        </h3>
                        <h6>Engagement Growth</h6>
                        <p class="text-muted">Total engagements across all platforms</p>
                    </div>
                </div>
            </div>
            
            <!-- Current Period Stats -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h4><?php echo number_format($current_period_stats['total_followers']); ?></h4>
                            <h6>Total Followers</h6>
                            <small class="text-muted">Across <?php echo $current_period_stats['platform_count']; ?> platforms</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h4><?php echo number_format($current_period_stats['total_engagements']); ?></h4>
                            <h6>Total Engagements</h6>
                            <small class="text-muted">All interactions combined</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Performance -->
    <div class="card mb-4">
        <div class="card-header">
            <h6>Platform Performance</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Current Followers</th>
                            <th>Current Engagements</th>
                            <th>Avg Daily Engagement</th>
                            <th>Days Tracked</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($platform_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['platform']); ?></strong></td>
                                <td><?php echo number_format($stat['current_followers']); ?></td>
                                <td><?php echo number_format($stat['current_engagements']); ?></td>
                                <td><?php echo number_format($stat['avg_engagements'], 1); ?></td>
                                <td><?php echo $stat['days_tracked']; ?></td>
                                <td>
                                    <?php 
                                    $engagement_rate = $stat['avg_engagements'];
                                    if ($engagement_rate >= 1000): ?>
                                        <span class="badge bg-success">Excellent</span>
                                    <?php elseif ($engagement_rate >= 500): ?>
                                        <span class="badge bg-warning">Good</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Average</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Country Performance -->
    <div class="card mb-4">
        <div class="card-header">
            <h6>Performance by Country</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>Total Followers</th>
                            <th>Total Engagements</th>
                            <th>Platforms Active</th>
                            <th>Avg Engagement</th>
                            <th>Rank</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($country_stats as $stat): 
                            $avg_engagement = $stat['total_engagements'] / max($stat['platforms_count'], 1);
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['country']); ?></strong></td>
                                <td><?php echo number_format($stat['total_followers']); ?></td>
                                <td><?php echo number_format($stat['total_engagements']); ?></td>
                                <td><?php echo $stat['platforms_count']; ?></td>
                                <td><?php echo number_format($avg_engagement, 1); ?></td>
                                <td>
                                    <?php if ($rank == 1): ?>
                                        <span class="badge bg-success">#1</span>
                                    <?php elseif ($rank == 2): ?>
                                        <span class="badge bg-warning">#2</span>
                                    <?php elseif ($rank == 3): ?>
                                        <span class="badge bg-info">#3</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">#<?php echo $rank; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>