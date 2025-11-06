<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get filter parameters
$platform_filter = $_GET['platform'] ?? '';
$country_filter = $_GET['country'] ?? '';
$date_range = $_GET['date_range'] ?? 'last_month';

// Calculate date ranges for current and previous periods
$end_date = date('Y-m-d');
switch ($date_range) {
    case 'today':
        $start_date = date('Y-m-d');
        $previous_start_date = date('Y-m-d', strtotime('-1 day'));
        $previous_end_date = date('Y-m-d', strtotime('-1 day'));
        $period_label = 'Today';
        $trend_interval = 'daily';
        break;
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $previous_start_date = date('Y-m-d', strtotime('monday last week'));
        $previous_end_date = date('Y-m-d', strtotime('sunday last week'));
        $period_label = 'This Week';
        $trend_interval = 'daily';
        break;
    case 'last_week':
        $start_date = date('Y-m-d', strtotime('monday last week'));
        $end_date = date('Y-m-d', strtotime('sunday last week'));
        $previous_start_date = date('Y-m-d', strtotime('monday -2 weeks'));
        $previous_end_date = date('Y-m-d', strtotime('sunday -2 weeks'));
        $period_label = 'Last Week';
        $trend_interval = 'daily';
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $previous_start_date = date('Y-m-01', strtotime('-1 month'));
        $previous_end_date = date('Y-m-t', strtotime('-1 month'));
        $period_label = 'This Month';
        $trend_interval = 'weekly';
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        $previous_start_date = date('Y-m-01', strtotime('-2 months'));
        $previous_end_date = date('Y-m-t', strtotime('-2 months'));
        $period_label = 'Last Month';
        $trend_interval = 'weekly';
        break;
    case 'last_3_months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        $previous_start_date = date('Y-m-d', strtotime('-6 months'));
        $previous_end_date = date('Y-m-d', strtotime('-3 months -1 day'));
        $period_label = 'Last 3 Months';
        $trend_interval = 'monthly';
        break;
    case 'last_6_months':
        $start_date = date('Y-m-d', strtotime('-6 months'));
        $previous_start_date = date('Y-m-d', strtotime('-12 months'));
        $previous_end_date = date('Y-m-d', strtotime('-6 months -1 day'));
        $period_label = 'Last 6 Months';
        $trend_interval = 'monthly';
        break;
    default:
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        $previous_start_date = date('Y-m-01', strtotime('-2 months'));
        $previous_end_date = date('Y-m-t', strtotime('-2 months'));
        $period_label = 'Last Month';
        $trend_interval = 'weekly';
        break;
}

// Build WHERE clause for main queries
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

// Get platforms and countries for filters
$platforms = db_fetch_all("SELECT name FROM social_media_platforms ORDER BY name");
$countries = db_fetch_all("SELECT DISTINCT country FROM social_media_daily_stats WHERE country IS NOT NULL ORDER BY country");

// NEW: Get the last captured followers from previous period and current period
$growth_data = [];

foreach ($platforms as $platform) {
    $platform_name = $platform['name'];
    
    // Build platform-specific conditions
    $platform_conditions = ["stat_date BETWEEN ? AND ?", "p.name = ?"];
    $platform_params = [$start_date, $end_date, $platform_name];
    
    $prev_platform_conditions = ["stat_date BETWEEN ? AND ?", "p.name = ?"];
    $prev_platform_params = [$previous_start_date, $previous_end_date, $platform_name];
    
    if ($country_filter) {
        $platform_conditions[] = "sms.country = ?";
        $platform_params[] = $country_filter;
        $prev_platform_conditions[] = "sms.country = ?";
        $prev_platform_params[] = $country_filter;
    }
    
    $platform_where = implode(" AND ", $platform_conditions);
    $prev_platform_where = implode(" AND ", $prev_platform_conditions);
    
    // Get last captured followers from previous period
    $previous_followers_data = db_fetch_one("
        SELECT sms.followers, sms.stat_date
        FROM social_media_daily_stats sms
        JOIN social_media_platforms p ON sms.platform_id = p.id
        WHERE $prev_platform_where
        ORDER BY sms.stat_date DESC
        LIMIT 1
    ", $prev_platform_params);
    
    // Get latest captured followers from current period
    $current_followers_data = db_fetch_one("
        SELECT sms.followers, sms.stat_date
        FROM social_media_daily_stats sms
        JOIN social_media_platforms p ON sms.platform_id = p.id
        WHERE $platform_where
        ORDER BY sms.stat_date DESC
        LIMIT 1
    ", $platform_params);
    
    $previous_followers = $previous_followers_data ? $previous_followers_data['followers'] : 0;
    $current_followers = $current_followers_data ? $current_followers_data['followers'] : 0;
    
    // Calculate percentage growth
    $percentage_growth = 0;
    if ($previous_followers > 0) {
        $percentage_growth = (($current_followers - $previous_followers) / $previous_followers) * 100;
    } elseif ($current_followers > 0) {
        $percentage_growth = 100; // New platform with growth
    }
    
    $growth_data[$platform_name] = [
        'previous_followers' => $previous_followers,
        'current_followers' => $current_followers,
        'growth' => $percentage_growth,
        'follower_change' => $current_followers - $previous_followers,
        'has_data' => ($previous_followers > 0 || $current_followers > 0)
    ];
}

// Calculate average growth across all platforms
$total_growth = 0;
$platforms_with_data = 0;

foreach ($growth_data as $platform_data) {
    if ($platform_data['has_data']) {
        $total_growth += $platform_data['growth'];
        $platforms_with_data++;
    }
}

$average_growth = $platforms_with_data > 0 ? $total_growth / $platforms_with_data : 0;

// Get additional metrics for the current period
$current_metrics = db_fetch_one("
    SELECT 
        SUM(sms.followers) as total_followers,
        SUM(sms.engagements) as total_engagements,
        SUM(sms.likes) as total_likes,
        SUM(sms.shares) as total_shares,
        SUM(sms.comments) as total_comments,
        COUNT(DISTINCT p.id) as platform_count,
        COUNT(DISTINCT sms.country) as country_count
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
", $params);

// Get platform-country performance data
$platform_country_stats = db_fetch_all("
    SELECT 
        p.name as platform,
        sms.country,
        MAX(sms.followers) as current_followers,
        MAX(sms.engagements) as current_engagements,
        AVG(CASE WHEN sms.followers > 0 THEN (sms.engagements / sms.followers * 100) ELSE 0 END) as engagement_rate
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
    GROUP BY p.name, sms.country
    ORDER BY p.name, sms.country
", $params);

// Get country performance summary
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

// Get growth trends data for charts
$trends_where_conditions = ["stat_date BETWEEN ? AND ?"];
$trends_params = [$start_date, $end_date];

if ($platform_filter) {
    $trends_where_conditions[] = "p.name = ?";
    $trends_params[] = $platform_filter;
}

if ($country_filter) {
    $trends_where_conditions[] = "sms.country = ?";
    $trends_params[] = $country_filter;
}

$trends_where_clause = implode(" AND ", $trends_where_conditions);

// Determine the grouping for trends based on date range
switch ($trend_interval) {
    case 'daily':
        $date_format = '%Y-%m-%d';
        $group_by = "stat_date";
        break;
    case 'weekly':
        $date_format = '%Y-%W';
        $group_by = "strftime('%Y-%W', stat_date)";
        break;
    case 'monthly':
        $date_format = '%Y-%m';
        $group_by = "strftime('%Y-%m', stat_date)";
        break;
}

// Platform Growth Trends by Country
$platform_country_trends = db_fetch_all("
    SELECT 
        $group_by as period,
        p.name as platform,
        sms.country,
        MAX(sms.followers) as period_followers,
        SUM(sms.engagements) as period_engagements
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $trends_where_clause
    GROUP BY $group_by, p.name, sms.country
    ORDER BY period ASC, p.name, sms.country
", $trends_params);

// Prepare data for charts
$platform_colors = [
    'Facebook' => '#1877F2',
    'LinkedIn' => '#0A66C2',
    'Twitter' => '#1DA1F2',
    'Telegram' => '#0088CC',
    'TikTok' => '#000000',
    'WhatsApp' => '#25D366',
    'Instagram' => '#E4405F',
    'YouTube' => '#FF0000'
];

$country_colors = [
    'Uganda' => '#FF6B6B',
    'Kenya' => '#4ECDC4', 
    'Tanzania' => '#45B7D1',
    'Rwanda' => '#96CEB4',
    'Zambia' => '#FFEAA7',
    'Other' => '#BDC3C7'
];

// Prepare platform-country matrix
$platform_country_matrix = [];
foreach ($platform_country_stats as $stat) {
    $platform = $stat['platform'];
    $country = $stat['country'];
    if (!isset($platform_country_matrix[$platform])) {
        $platform_country_matrix[$platform] = [];
    }
    $platform_country_matrix[$platform][$country] = $stat;
}

// Prepare trends data
$period_platform_country_data = [];
$all_periods = [];

foreach ($platform_country_trends as $trend) {
    $period = $trend['period'];
    $platform = $trend['platform'];
    $country = $trend['country'];
    
    if (!in_array($period, $all_periods)) {
        $all_periods[] = $period;
    }
    
    if (!isset($period_platform_country_data[$platform])) {
        $period_platform_country_data[$platform] = [];
    }
    if (!isset($period_platform_country_data[$platform][$country])) {
        $period_platform_country_data[$platform][$country] = [];
    }
    
    $period_platform_country_data[$platform][$country][$period] = $trend['period_followers'];
}

// Sort periods chronologically
sort($all_periods);

// Format period labels based on interval
$formatted_periods = [];
foreach ($all_periods as $period) {
    switch ($trend_interval) {
        case 'daily':
            $formatted_periods[] = date('M j', strtotime($period));
            break;
        case 'weekly':
            $parts = explode('-', $period);
            $formatted_periods[] = 'Week ' . $parts[1] . ' ' . $parts[0];
            break;
        case 'monthly':
            $formatted_periods[] = date('M Y', strtotime($period . '-01'));
            break;
    }
}
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Social Media Growth Analytics</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <span class="text-muted"><?php echo date('F j, Y'); ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">Analytics Filters</h6>
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
                            <option value="<?php echo $country['country']; ?>" <?php echo $country_filter === $country['country'] ? 'selected' : ''; ?>>
                                <?php echo $country['country']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <select name="date_range" class="form-select">
                        <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="this_week" <?php echo $date_range === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="last_week" <?php echo $date_range === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                        <option value="this_month" <?php echo $date_range === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="last_month" <?php echo $date_range === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                        <option value="last_3_months" <?php echo $date_range === 'last_3_months' ? 'selected' : ''; ?>>Last 3 Months</option>
                        <option value="last_6_months" <?php echo $date_range === 'last_6_months' ? 'selected' : ''; ?>>Last 6 Months</option>
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

    <!-- Growth Overview Cards -->
    <div class="row mb-4">
        <!-- Average Growth Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stat-card h-100 bg-primary text-white">
                <div class="card-body text-center">
                    <h6 class="card-title text-white-50">Average Growth</h6>
                    <h2 class="<?php echo $average_growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo number_format($average_growth, 1); ?>%
                    </h2>
                    <small class="text-white-50">Across <?php echo $platforms_with_data; ?> Platforms</small>
                    <div class="mt-2">
                        <span class="badge bg-<?php echo $average_growth >= 10 ? 'success' : ($average_growth >= 0 ? 'warning' : 'danger'); ?>">
                            <?php echo $average_growth >= 10 ? 'Strong Growth' : ($average_growth >= 0 ? 'Stable' : 'Declining'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total Followers Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Followers</h6>
                    <h2 class="text-info"><?php echo number_format($current_metrics['total_followers']); ?></h2>
                    <small class="text-muted">Current Period</small>
                    <div class="mt-2">
                        <small class="text-muted">
                            <?php echo $current_metrics['platform_count']; ?> platforms, 
                            <?php echo $current_metrics['country_count']; ?> countries
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Platform Performance Summary -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Platform Performance</h6>
                    <div class="d-flex justify-content-around mt-3">
                        <div>
                            <div class="text-success">
                                <strong><?php echo count(array_filter($growth_data, fn($data) => $data['growth'] > 0 && $data['has_data'])); ?></strong>
                            </div>
                            <small class="text-muted">Growing</small>
                        </div>
                        <div>
                            <div class="text-warning">
                                <strong><?php echo count(array_filter($growth_data, fn($data) => $data['growth'] == 0 && $data['has_data'])); ?></strong>
                            </div>
                            <small class="text-muted">Stable</small>
                        </div>
                        <div>
                            <div class="text-danger">
                                <strong><?php echo count(array_filter($growth_data, fn($data) => $data['growth'] < 0 && $data['has_data'])); ?></strong>
                            </div>
                            <small class="text-muted">Declining</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Engagement Overview -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Engagement</h6>
                    <h2 class="text-success"><?php echo number_format($current_metrics['total_engagements']); ?></h2>
                    <small class="text-muted">Current Period</small>
                    <div class="mt-2">
                        <div class="row small text-muted">
                            <div class="col-4">
                                <div>Likes</div>
                                <strong><?php echo number_format($current_metrics['total_likes']); ?></strong>
                            </div>
                            <div class="col-4">
                                <div>Shares</div>
                                <strong><?php echo number_format($current_metrics['total_shares']); ?></strong>
                            </div>
                            <div class="col-4">
                                <div>Comments</div>
                                <strong><?php echo number_format($current_metrics['total_comments']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Growth Cards -->
    <div class="row mb-4">
        <?php foreach ($growth_data as $platform_name => $data): ?>
            <?php if ($data['has_data']): ?>
                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title mb-0"><?php echo $platform_name; ?></h6>
                                <span class="badge bg-<?php echo $data['growth'] >= 10 ? 'success' : ($data['growth'] >= 0 ? 'warning' : 'danger'); ?>">
                                    <?php echo number_format($data['growth'], 1); ?>%
                                </span>
                            </div>
                            
                            <div class="row text-center mt-3">
                                <div class="col-6">
                                    <small class="text-muted">Previous</small>
                                    <div class="fw-bold"><?php echo number_format($data['previous_followers']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Current</small>
                                    <div class="fw-bold text-primary"><?php echo number_format($data['current_followers']); ?></div>
                                </div>
                            </div>
                            
                            <div class="mt-3 text-center">
                                <small class="text-muted">Net Change</small>
                                <div class="fw-bold <?php echo $data['follower_change'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($data['follower_change'] >= 0 ? '+' : '') . number_format($data['follower_change']); ?>
                                </div>
                            </div>
                            
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-<?php echo $data['growth'] >= 0 ? 'success' : 'danger'; ?>" 
                                     style="width: <?php echo min(abs($data['growth']), 100); ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Enhanced Charts Section with Country Breakdown -->
    <div class="row mb-4">
        <!-- Platform Performance by Country -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Platform Followers by Country</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="platformCountryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Country Performance by Platform -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Country Performance by Platform</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="countryPlatformChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Growth Trends by Country -->
    <div class="row mb-4">
        <!-- Platform Growth Trends by Country -->
        <div class="col-lg-12 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Platform Growth Trends by Country</h6>
                    <span class="badge bg-info"><?php echo ucfirst($trend_interval); ?> View</span>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="platformGrowthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Performance Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Platform Performance Details by Country</h6>
            <span class="badge bg-info"><?php echo $period_label; ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Country</th>
                            <th>Current Followers</th>
                            <th>Engagements</th>
                            <th>Engagement Rate</th>
                            <th>Growth %</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($platform_country_stats as $stat): ?>
                            <?php 
                            $platform_growth = $growth_data[$stat['platform']]['growth'] ?? 0;
                            ?>
                            <tr>
                                <td><strong><?php echo $stat['platform']; ?></strong></td>
                                <td>
                                    <span class="flag-icon"><?php echo substr($stat['country'], 0, 2); ?></span>
                                    <?php echo $stat['country']; ?>
                                </td>
                                <td><?php echo number_format($stat['current_followers']); ?></td>
                                <td><?php echo number_format($stat['current_engagements']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $stat['engagement_rate'] >= 5 ? 'success' : ($stat['engagement_rate'] >= 2 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($stat['engagement_rate'], 2); ?>%
                                    </span>
                                </td>
                                <td class="<?php echo $platform_growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($platform_growth >= 0 ? '+' : '') . number_format($platform_growth, 1); ?>%
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $platform_growth >= 10 ? 'success' : ($platform_growth >= 0 ? 'warning' : 'danger'); ?>">
                                        <?php echo $platform_growth >= 10 ? 'Strong' : ($platform_growth >= 0 ? 'Stable' : 'Declining'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Platform Performance by Country Chart
const platformCountryCtx = document.getElementById('platformCountryChart').getContext('2d');
const platformCountryData = {
    labels: <?php echo json_encode(array_column($countries, 'country')); ?>,
    datasets: [
        <?php foreach ($platforms as $platform): ?>
        {
            label: '<?php echo $platform['name']; ?>',
            data: [
                <?php foreach ($countries as $country): ?>
                <?php 
                    $value = isset($platform_country_matrix[$platform['name']][$country['country']]) 
                        ? $platform_country_matrix[$platform['name']][$country['country']]['current_followers'] 
                        : 0;
                    echo $value . ',';
                ?>
                <?php endforeach; ?>
            ],
            backgroundColor: '<?php echo $platform_colors[$platform['name']] ?? '#666666'; ?>',
            borderColor: '<?php echo $platform_colors[$platform['name']] ?? '#666666'; ?>',
            borderWidth: 1
        },
        <?php endforeach; ?>
    ]
};

new Chart(platformCountryCtx, {
    type: 'bar',
    data: platformCountryData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Followers Distribution by Platform and Country'
            }
        },
        scales: {
            x: {
                stacked: false,
            },
            y: {
                stacked: false,
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        if (value >= 1000000) {
                            return (value / 1000000).toFixed(1) + 'M';
                        } else if (value >= 1000) {
                            return (value / 1000).toFixed(1) + 'K';
                        }
                        return value;
                    }
                }
            }
        }
    }
});

// Country Performance by Platform Chart
const countryPlatformCtx = document.getElementById('countryPlatformChart').getContext('2d');
const countryPlatformData = {
    labels: <?php echo json_encode(array_column($platforms, 'name')); ?>,
    datasets: [
        <?php foreach ($countries as $country): ?>
        {
            label: '<?php echo $country['country']; ?>',
            data: [
                <?php foreach ($platforms as $platform): ?>
                <?php 
                    $value = isset($platform_country_matrix[$platform['name']][$country['country']]) 
                        ? $platform_country_matrix[$platform['name']][$country['country']]['current_followers'] 
                        : 0;
                    echo $value . ',';
                ?>
                <?php endforeach; ?>
            ],
            backgroundColor: '<?php echo $country_colors[$country['country']] ?? '#666666'; ?>',
            borderColor: '<?php echo $country_colors[$country['country']] ?? '#666666'; ?>',
            borderWidth: 1
        },
        <?php endforeach; ?>
    ]
};

new Chart(countryPlatformCtx, {
    type: 'bar',
    data: countryPlatformData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Platform Performance Across Countries'
            }
        },
        scales: {
            x: {
                stacked: false,
            },
            y: {
                stacked: false,
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        if (value >= 1000000) {
                            return (value / 1000000).toFixed(1) + 'M';
                        } else if (value >= 1000) {
                            return (value / 1000).toFixed(1) + 'K';
                        }
                        return value;
                    }
                }
            }
        }
    }
});

// Platform Growth Trends by Country Chart
const platformGrowthCtx = document.getElementById('platformGrowthChart').getContext('2d');

// Prepare data for platform growth trends
const platformGrowthDatasets = [];
<?php foreach ($platforms as $platform): ?>
    <?php foreach ($countries as $country): ?>
        <?php if (isset($period_platform_country_data[$platform['name']][$country['country']])): ?>
            platformGrowthDatasets.push({
                label: '<?php echo $platform['name']; ?> - <?php echo $country['country']; ?>',
                data: [
                    <?php foreach ($all_periods as $period): ?>
                        <?php echo $period_platform_country_data[$platform['name']][$country['country']][$period] ?? 'null'; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: '<?php echo $platform_colors[$platform['name']] ?? '#666666'; ?>',
                backgroundColor: '<?php echo $platform_colors[$platform['name']] ?? '#666666'; ?>22',
                borderWidth: 2,
                fill: false,
                tension: 0.4
            });
        <?php endif; ?>
    <?php endforeach; ?>
<?php endforeach; ?>

const platformGrowthData = {
    labels: <?php echo json_encode($formatted_periods); ?>,
    datasets: platformGrowthDatasets
};

new Chart(platformGrowthCtx, {
    type: 'line',
    data: platformGrowthData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: '<?php echo ucfirst($trend_interval); ?> Follower Growth Trends'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        if (value >= 1000000) {
                            return (value / 1000000).toFixed(1) + 'M';
                        } else if (value >= 1000) {
                            return (value / 1000).toFixed(1) + 'K';
                        }
                        return value;
                    }
                }
            }
        }
    }
});
</script>

<style>
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}
.stat-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
.card-header {
    background: rgba(0,0,0,0.03);
    border-bottom: 1px solid rgba(0,0,0,0.125);
}
.progress {
    background-color: #e9ecef;
}
.flag-icon {
    display: inline-block;
    width: 20px;
    height: 15px;
    background: #ddd;
    margin-right: 5px;
    border-radius: 2px;
    text-align: center;
    line-height: 15px;
    font-size: 10px;
    font-weight: bold;
}
</style>

<?php require_once '../includes/footer.php'; ?>