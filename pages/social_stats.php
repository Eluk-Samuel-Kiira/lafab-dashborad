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
        $period_label = 'Last 7 Days';
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        $period_label = 'Last Calendar Month';
        break;
    case 'last_3_months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        $period_label = 'Last 3 Months';
        break;
    case 'last_6_months':
        $start_date = date('Y-m-d', strtotime('-6 months'));
        $period_label = 'Last 6 Months';
        break;
    default: // last_30_days
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
        break;
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

// Calculate comprehensive growth metrics
$current_period_stats = db_fetch_one("
    SELECT 
        SUM(followers) as total_followers,
        SUM(engagements) as total_engagements,
        SUM(likes) as total_likes,
        SUM(shares) as total_shares,
        SUM(comments) as total_comments,
        SUM(impressions) as total_impressions,
        SUM(reach) as total_reach,
        SUM(video_views) as total_video_views,
        COUNT(DISTINCT platform_id) as platform_count,
        COUNT(DISTINCT country) as country_count,
        COUNT(*) as total_entries
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
", $params);

// Previous period for comparison
$previous_start_date = date('Y-m-d', strtotime($start_date . ' -1 ' . str_replace('last_', '', $date_range)));
$previous_end_date = date('Y-m-d', strtotime($end_date . ' -1 ' . str_replace('last_', '', $date_range)));

$previous_period_stats = db_fetch_one("
    SELECT 
        SUM(followers) as total_followers,
        SUM(engagements) as total_engagements,
        SUM(likes) as total_likes,
        SUM(shares) as total_shares,
        SUM(comments) as total_comments
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE stat_date BETWEEN ? AND ?
", [$previous_start_date, $previous_end_date]);

// Calculate growth percentages with error handling
function calculateGrowth($current, $previous) {
    if ($previous > 0) {
        return (($current - $previous) / $previous) * 100;
    }
    return $current > 0 ? 100 : 0;
}

$follower_growth = calculateGrowth($current_period_stats['total_followers'], $previous_period_stats['total_followers']);
$engagement_growth = calculateGrowth($current_period_stats['total_engagements'], $previous_period_stats['total_engagements']);
$like_growth = calculateGrowth($current_period_stats['total_likes'], $previous_period_stats['total_likes']);
$share_growth = calculateGrowth($current_period_stats['total_shares'], $previous_period_stats['total_shares']);
$comment_growth = calculateGrowth($current_period_stats['total_comments'], $previous_period_stats['total_comments']);

// Overall growth percentage (weighted average)
$overall_growth = ($follower_growth * 0.4) + ($engagement_growth * 0.6);

// Platform performance by country
$platform_country_stats = db_fetch_all("
    SELECT 
        p.name as platform,
        sms.country,
        MAX(sms.followers) as current_followers,
        MAX(sms.engagements) as current_engagements,
        AVG(sms.engagements) as avg_engagements,
        AVG(CASE WHEN sms.followers > 0 THEN (sms.engagements / sms.followers * 100) ELSE 0 END) as avg_engagement_rate,
        COUNT(sms.id) as days_tracked,
        SUM(sms.likes) as total_likes,
        SUM(sms.shares) as total_shares,
        SUM(sms.comments) as total_comments
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
    GROUP BY p.name, sms.country
    ORDER BY p.name, sms.country, current_followers DESC
", $params);

// Country performance
$country_stats = db_fetch_all("
    SELECT 
        country,
        SUM(followers) as total_followers,
        SUM(engagements) as total_engagements,
        COUNT(DISTINCT platform_id) as platforms_count,
        AVG(CASE WHEN followers > 0 THEN (engagements / followers * 100) ELSE 0 END) as avg_engagement_rate
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
    GROUP BY country
    ORDER BY total_followers DESC
", $params);

// Content performance
$content_stats = db_fetch_all("
    SELECT 
        content_type,
        COUNT(*) as post_count,
        AVG(engagements) as avg_engagement,
        SUM(engagements) as total_engagement,
        AVG(CASE WHEN engagements > 0 THEN 1 ELSE 0 END) * 100 as success_rate
    FROM social_media_posts
    WHERE post_date BETWEEN ? AND ?
    GROUP BY content_type
    ORDER BY avg_engagement DESC
", [$start_date, $end_date]);

// Monthly growth trends by platform and country
$monthly_trends = db_fetch_all("
    SELECT 
        strftime('%Y-%m', stat_date) as month,
        p.name as platform,
        sms.country,
        SUM(sms.followers) as monthly_followers,
        SUM(sms.engagements) as monthly_engagements
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE stat_date >= date('now', '-6 months')
    GROUP BY strftime('%Y-%m', stat_date), p.name, sms.country
    ORDER BY month ASC, p.name, sms.country
");

// Prepare data for charts
$platform_colors = [
    'Facebook' => '#1877F2',
    'LinkedIn' => '#0A66C2', 
    'Twitter' => '#1DA1F2',
    'Telegram' => '#0088CC',
    'TikTok' => '#000000',
    'WhatsApp' => '#25D366'
];

$country_colors = [
    'Uganda' => '#FF6B6B',
    'Kenya' => '#4ECDC4', 
    'Tanzania' => '#45B7D1',
    'Rwanda' => '#96CEB4',
    'Zambia' => '#FFEAA7'
];

// Prepare platform-country matrix for chart
$platform_country_matrix = [];
foreach ($platform_country_stats as $stat) {
    $platform = $stat['platform'];
    $country = $stat['country'];
    if (!isset($platform_country_matrix[$platform])) {
        $platform_country_matrix[$platform] = [];
    }
    $platform_country_matrix[$platform][$country] = $stat;
}

// Prepare monthly trends data
$monthly_platform_data = [];
$monthly_country_data = [];
foreach ($monthly_trends as $trend) {
    $month = $trend['month'];
    $platform = $trend['platform'];
    $country = $trend['country'];
    
    // Platform trends
    if (!isset($monthly_platform_data[$platform])) {
        $monthly_platform_data[$platform] = [];
    }
    $monthly_platform_data[$platform][$month] = $trend['monthly_followers'];
    
    // Country trends
    if (!isset($monthly_country_data[$country])) {
        $monthly_country_data[$country] = [];
    }
    $monthly_country_data[$country][$month] = $trend['monthly_followers'];
}
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Social Media Performance Analytics</h1>
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

    <!-- Growth Overview -->
    <div class="row mb-4">
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Overall Growth</h6>
                    <h3 class="<?php echo $overall_growth >= 10 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo number_format($overall_growth, 1); ?>%
                    </h3>
                    <small class="text-muted">Weighted Performance</small>
                    <div class="mt-2">
                        <?php if ($overall_growth >= 10): ?>
                            <span class="badge bg-success">✓ On Track</span>
                        <?php else: ?>
                            <span class="badge bg-danger">✗ Needs Boost</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Follower Growth</h6>
                    <h3 class="<?php echo $follower_growth >= 10 ? 'text-success' : 'text-warning'; ?>">
                        <?php echo number_format($follower_growth, 1); ?>%
                    </h3>
                    <small class="text-muted">Total Followers</small>
                    <div class="mt-2">
                        <small class="text-muted"><?php echo number_format($current_period_stats['total_followers']); ?> total</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Engagement Growth</h6>
                    <h3 class="<?php echo $engagement_growth >= 10 ? 'text-success' : 'text-warning'; ?>">
                        <?php echo number_format($engagement_growth, 1); ?>%
                    </h3>
                    <small class="text-muted">Total Engagements</small>
                    <div class="mt-2">
                        <small class="text-muted"><?php echo number_format($current_period_stats['total_engagements']); ?> total</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Active Platforms</h6>
                    <h3 class="text-info"><?php echo $current_period_stats['platform_count']; ?></h3>
                    <small class="text-muted">Platforms Tracked</small>
                    <div class="mt-2">
                        <small class="text-muted"><?php echo $current_period_stats['country_count']; ?> countries</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Engagement Rate</h6>
                    <h3 class="text-primary">
                        <?php 
                        $avg_engagement_rate = $current_period_stats['total_followers'] > 0 ? 
                            ($current_period_stats['total_engagements'] / $current_period_stats['total_followers'] * 100) : 0;
                        echo number_format($avg_engagement_rate, 2); ?>%
                    </h3>
                    <small class="text-muted">Average Rate</small>
                    <div class="mt-2">
                        <span class="badge bg-<?php echo $avg_engagement_rate >= 5 ? 'success' : ($avg_engagement_rate >= 2 ? 'warning' : 'danger'); ?>">
                            Industry: <?php echo $avg_engagement_rate >= 5 ? 'High' : ($avg_engagement_rate >= 2 ? 'Medium' : 'Low'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Content Performance</h6>
                    <h3 class="text-dark">
                        <?php echo !empty($content_stats) ? number_format(max(array_column($content_stats, 'avg_engagement')), 0) : 0; ?>
                    </h3>
                    <small class="text-muted">Top Avg Engagement</small>
                    <div class="mt-2">
                        <small class="text-muted">
                            <?php echo !empty($content_stats) ? $content_stats[0]['content_type'] : 'No data'; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Charts Row -->
    <div class="row mb-4">
        <!-- Platform Performance by Country -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Platform Followers by Country</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container bar">
                        <canvas id="platformCountryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Platform Growth Trends -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Platform Growth Trends</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container line">
                        <canvas id="platformGrowthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Country Performance Charts -->
    <div class="row mb-4">
        <!-- Country Performance by Platform -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Country Performance by Platform</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container bar">
                        <canvas id="countryPlatformChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Country Growth Trends -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Country Growth Trends</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container line">
                        <canvas id="countryGrowthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Performance Details -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">Platform Performance by Country</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Country</th>
                            <th>Followers</th>
                            <th>Engagements</th>
                            <th>Engagement Rate</th>
                            <th>Likes</th>
                            <th>Shares</th>
                            <th>Comments</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($platform_country_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['platform']); ?></strong></td>
                                <td>
                                    <span class="flag-icon"><?php echo substr($stat['country'], 0, 2); ?></span>
                                    <?php echo htmlspecialchars($stat['country']); ?>
                                </td>
                                <td><?php echo number_format($stat['current_followers']); ?></td>
                                <td><?php echo number_format($stat['current_engagements']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $stat['avg_engagement_rate'] >= 5 ? 'success' : ($stat['avg_engagement_rate'] >= 2 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($stat['avg_engagement_rate'], 2); ?>%
                                    </span>
                                </td>
                                <td><?php echo number_format($stat['total_likes']); ?></td>
                                <td><?php echo number_format($stat['total_shares']); ?></td>
                                <td><?php echo number_format($stat['total_comments']); ?></td>
                                <td>
                                    <?php 
                                    $performance_score = $stat['avg_engagement_rate'] * log10($stat['current_followers'] + 1);
                                    if ($performance_score >= 100): ?>
                                        <span class="badge bg-success">Excellent</span>
                                    <?php elseif ($performance_score >= 50): ?>
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
</div>

<script>
// Platform Performance by Country Chart
const platformCountryCtx = document.getElementById('platformCountryChart').getContext('2d');
const platformCountryData = {
    labels: <?php echo json_encode($countries); ?>,
    datasets: [
        <?php foreach ($platforms as $platform): ?>
        {
            label: '<?php echo $platform['name']; ?>',
            data: [
                <?php foreach ($countries as $country): ?>
                <?php 
                    $value = isset($platform_country_matrix[$platform['name']][$country]) 
                        ? $platform_country_matrix[$platform['name']][$country]['current_followers'] 
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

// Platform Growth Trends Chart
const platformGrowthCtx = document.getElementById('platformGrowthChart').getContext('2d');
const platformGrowthData = {
    labels: <?php echo json_encode(array_unique(array_column($monthly_trends, 'month'))); ?>,
    datasets: [
        <?php foreach ($platforms as $platform): ?>
        {
            label: '<?php echo $platform['name']; ?>',
            data: [
                <?php 
                $months = array_unique(array_column($monthly_trends, 'month'));
                foreach ($months as $month): 
                    $value = isset($monthly_platform_data[$platform['name']][$month]) 
                        ? $monthly_platform_data[$platform['name']][$month] 
                        : 0;
                    echo $value . ',';
                endforeach; 
                ?>
            ],
            borderColor: '<?php echo $platform_colors[$platform['name']] ?? '#666666'; ?>',
            backgroundColor: '<?php echo $platform_colors[$platform['name']] ?? '#666666'; ?>22',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        },
        <?php endforeach; ?>
    ]
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
                text: 'Monthly Follower Growth by Platform'
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

// Country Performance by Platform Chart
const countryPlatformCtx = document.getElementById('countryPlatformChart').getContext('2d');
const countryPlatformData = {
    labels: <?php echo json_encode(array_column($platforms, 'name')); ?>,
    datasets: [
        <?php foreach ($countries as $country): ?>
        {
            label: '<?php echo $country; ?>',
            data: [
                <?php foreach ($platforms as $platform): ?>
                <?php 
                    $value = isset($platform_country_matrix[$platform['name']][$country]) 
                        ? $platform_country_matrix[$platform['name']][$country]['current_followers'] 
                        : 0;
                    echo $value . ',';
                ?>
                <?php endforeach; ?>
            ],
            backgroundColor: '<?php echo $country_colors[$country] ?? '#666666'; ?>',
            borderColor: '<?php echo $country_colors[$country] ?? '#666666'; ?>',
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

// Country Growth Trends Chart
const countryGrowthCtx = document.getElementById('countryGrowthChart').getContext('2d');
const countryGrowthData = {
    labels: <?php echo json_encode(array_unique(array_column($monthly_trends, 'month'))); ?>,
    datasets: [
        <?php foreach ($countries as $country): ?>
        {
            label: '<?php echo $country; ?>',
            data: [
                <?php 
                $months = array_unique(array_column($monthly_trends, 'month'));
                foreach ($months as $month): 
                    $value = isset($monthly_country_data[$country][$month]) 
                        ? $monthly_country_data[$country][$month] 
                        : 0;
                    echo $value . ',';
                endforeach; 
                ?>
            ],
            borderColor: '<?php echo $country_colors[$country] ?? '#666666'; ?>',
            backgroundColor: '<?php echo $country_colors[$country] ?? '#666666'; ?>22',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        },
        <?php endforeach; ?>
    ]
};

new Chart(countryGrowthCtx, {
    type: 'line',
    data: countryGrowthData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Monthly Follower Growth by Country'
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
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.flag-icon {
    display: inline-block;
    width: 20px;
    height: 15px;
    background: #ddd;
    margin-right: 5px;
    border-radius: 2px;
}
</style>

<?php require_once '../includes/footer.php'; ?>