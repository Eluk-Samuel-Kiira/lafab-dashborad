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
        $trend_interval = 'daily';
        break;
    case 'last_30_days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
        $trend_interval = 'daily';
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        $period_label = 'Last Calendar Month';
        $trend_interval = 'weekly';
        break;
    case 'last_3_months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        $period_label = 'Last 3 Months';
        $trend_interval = 'weekly';
        break;
    case 'last_6_months':
        $start_date = date('Y-m-d', strtotime('-6 months'));
        $period_label = 'Last 6 Months';
        $trend_interval = 'monthly';
        break;
    case 'last_year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        $period_label = 'Last Year';
        $trend_interval = 'monthly';
        break;
    case 'last_2_years':
        $start_date = date('Y-m-d', strtotime('-2 years'));
        $period_label = 'Last 2 Years';
        $trend_interval = 'quarterly';
        break;
    case 'last_5_years':
        $start_date = date('Y-m-d', strtotime('-5 years'));
        $period_label = 'Last 5 Years';
        $trend_interval = 'yearly';
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
        $trend_interval = 'daily';
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

// Get platforms for filter
$platforms = db_fetch_all("SELECT name FROM social_media_platforms ORDER BY name");
$countries = ['Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Zambia'];

// Calculate comprehensive growth metrics - Only include dates with actual data
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
    AND sms.followers > 0  -- Only include entries with actual follower data
", $params);

// Previous period for comparison - Only include dates with actual data
$previous_start_date = date('Y-m-d', strtotime($start_date . ' -1 ' . str_replace('last_', '', $date_range)));
$previous_end_date = date('Y-m-d', strtotime($end_date . ' -1 ' . str_replace('last_', '', $date_range)));

// Build WHERE clause for previous period with same filters
$prev_where_conditions = ["stat_date BETWEEN ? AND ?"];
$prev_params = [$previous_start_date, $previous_end_date];

if ($platform_filter) {
    $prev_where_conditions[] = "p.name = ?";
    $prev_params[] = $platform_filter;
}

if ($country_filter) {
    $prev_where_conditions[] = "sms.country = ?";
    $prev_params[] = $country_filter;
}

$prev_where_clause = implode(" AND ", $prev_where_conditions);

$previous_period_stats = db_fetch_one("
    SELECT 
        SUM(followers) as total_followers,
        SUM(engagements) as total_engagements,
        SUM(likes) as total_likes,
        SUM(shares) as total_shares,
        SUM(comments) as total_comments
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $prev_where_clause
    AND sms.followers > 0  -- Only include entries with actual follower data
", $prev_params);

// NEW: Get platform-specific growth data
$platform_growth_data = [];
foreach ($platforms as $platform) {
    $platform_name = $platform['name'];
    
    // Current period stats for this platform
    $platform_current_where = ["stat_date BETWEEN ? AND ?", "p.name = ?", "sms.followers > 0"];
    $platform_current_params = [$start_date, $end_date, $platform_name];
    
    if ($country_filter) {
        $platform_current_where[] = "sms.country = ?";
        $platform_current_params[] = $country_filter;
    }
    
    $platform_current_where_clause = implode(" AND ", $platform_current_where);
    
    $platform_current_stats = db_fetch_one("
        SELECT 
            SUM(followers) as total_followers,
            SUM(engagements) as total_engagements
        FROM social_media_daily_stats sms
        JOIN social_media_platforms p ON sms.platform_id = p.id
        WHERE $platform_current_where_clause
    ", $platform_current_params);
    
    // Previous period stats for this platform
    $platform_prev_where = ["stat_date BETWEEN ? AND ?", "p.name = ?", "sms.followers > 0"];
    $platform_prev_params = [$previous_start_date, $previous_end_date, $platform_name];
    
    if ($country_filter) {
        $platform_prev_where[] = "sms.country = ?";
        $platform_prev_params[] = $country_filter;
    }
    
    $platform_prev_where_clause = implode(" AND ", $platform_prev_where);
    
    $platform_prev_stats = db_fetch_one("
        SELECT 
            SUM(followers) as total_followers,
            SUM(engagements) as total_engagements
        FROM social_media_daily_stats sms
        JOIN social_media_platforms p ON sms.platform_id = p.id
        WHERE $platform_prev_where_clause
    ", $platform_prev_params);
    
    // Calculate growth percentages
    $platform_follower_growth = calculateGrowth($platform_current_stats['total_followers'], $platform_prev_stats['total_followers']);
    $platform_engagement_growth = calculateGrowth($platform_current_stats['total_engagements'], $platform_prev_stats['total_engagements']);
    
    // Overall platform growth (weighted average)
    $platform_overall_growth = ($platform_follower_growth * 0.4) + ($platform_engagement_growth * 0.6);
    
    $platform_growth_data[$platform_name] = [
        'follower_growth' => $platform_follower_growth,
        'engagement_growth' => $platform_engagement_growth,
        'overall_growth' => $platform_overall_growth,
        'current_followers' => $platform_current_stats['total_followers'],
        'current_engagements' => $platform_current_stats['total_engagements']
    ];
}

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

// NEW: Calculate overall growth as average of platform growth percentages
$total_platform_growth = 0;
$active_platform_count = 0;

foreach ($platform_growth_data as $platform_data) {
    if ($platform_data['current_followers'] > 0) { // Only count platforms with actual data
        $total_platform_growth += $platform_data['overall_growth'];
        $active_platform_count++;
    }
}

$overall_growth = $active_platform_count > 0 ? ($total_platform_growth / $active_platform_count) : 0;

// Platform performance by country - Only include entries with actual data
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
    AND sms.followers > 0  -- Only include entries with actual follower data
    GROUP BY p.name, sms.country
    ORDER BY p.name, sms.country, current_followers DESC
", $params);

// Country performance - Only include entries with actual data
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
    AND sms.followers > 0  -- Only include entries with actual follower data
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
    AND engagements > 0  -- Only include posts with actual engagement
    GROUP BY content_type
    ORDER BY avg_engagement DESC
", [$start_date, $end_date]);

// DYNAMIC GROWTH TRENDS - Get only dates with actual data
$trends_where_conditions = ["stat_date BETWEEN ? AND ?", "sms.followers > 0"];
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
        $order_format = 'Y-m-d';
        break;
    case 'weekly':
        $date_format = '%Y-%W';
        $group_by = "strftime('%Y-%W', stat_date)";
        $order_format = 'Y-W';
        break;
    case 'monthly':
        $date_format = '%Y-%m';
        $group_by = "strftime('%Y-%m', stat_date)";
        $order_format = 'Y-m';
        break;
    case 'quarterly':
        $date_format = '%Y-Q';
        $group_by = "strftime('%Y', stat_date) || '-Q' || ((strftime('%m', stat_date) + 2) / 3)";
        $order_format = 'Y-Q';
        break;
    case 'yearly':
        $date_format = '%Y';
        $group_by = "strftime('%Y', stat_date)";
        $order_format = 'Y';
        break;
}

// FIXED: Platform Growth Trends - Group by platform only (not by country)
$platform_trends = db_fetch_all("
    SELECT 
        $group_by as period,
        p.name as platform,
        SUM(sms.followers) as period_followers,  -- SUM across all countries
        SUM(sms.engagements) as period_engagements
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $trends_where_clause
    GROUP BY $group_by, p.name
    ORDER BY period ASC, p.name
", $trends_params);

// FIXED: Country Growth Trends - Group by country only (not by platform)
$country_trends = db_fetch_all("
    SELECT 
        $group_by as period,
        sms.country,
        MAX(sms.followers) as period_followers,  -- Use MAX instead of SUM to get latest available value
        SUM(sms.engagements) as period_engagements
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $trends_where_clause
    GROUP BY $group_by, sms.country
    ORDER BY period ASC, sms.country
", $trends_params);

// Prepare data for charts
$platform_colors = [
    'Facebook' => '#1877F2',      // Keep Facebook blue
    'LinkedIn' => '#FF6B35',      // Changed to orange
    'Twitter' => '#1DA1F2',       // Keep Twitter blue
    'Telegram' => '#0088CC',      // Keep Telegram blue
    'TikTok' => '#000000',        // Keep TikTok black
    'WhatsApp' => '#25D366',      // Keep WhatsApp green
    'Instagram' => '#E4405F',     // Keep Instagram pink
    'YouTube' => '#FF0000'        // Keep YouTube red
];

$country_colors = [
    'Uganda' => '#FF6B6B',
    'Kenya' => '#4ECDC4', 
    'Tanzania' => '#45B7D1',
    'Rwanda' => '#96CEB4',
    'Zambia' => '#FFEAA7',
    'Other' => '#BDC3C7'
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

// Prepare dynamic trends data - ONLY include periods with actual data
$period_platform_data = [];
$period_country_data = [];
$all_periods = [];

// First, collect all unique periods that have actual data from BOTH queries
foreach ($platform_trends as $trend) {
    $period = $trend['period'];
    if (!in_array($period, $all_periods)) {
        $all_periods[] = $period;
    }
}
foreach ($country_trends as $trend) {
    $period = $trend['period'];
    if (!in_array($period, $all_periods)) {
        $all_periods[] = $period;
    }
}

// Sort periods chronologically
usort($all_periods, function($a, $b) use ($trend_interval) {
    if ($trend_interval === 'quarterly') {
        // Convert Q1-2024, Q2-2024 format for sorting
        $a_parts = explode('-Q', $a);
        $b_parts = explode('-Q', $b);
        return ($a_parts[0] * 4 + $a_parts[1]) <=> ($b_parts[0] * 4 + $b_parts[1]);
    }
    return $a <=> $b;
});

// Now populate PLATFORM data - ONLY include periods with actual data (no filling gaps)
foreach ($platform_trends as $trend) {
    $period = $trend['period'];
    $platform = $trend['platform'];
    
    // Platform trends - only add if we have data for this period
    if (!isset($period_platform_data[$platform])) {
        $period_platform_data[$platform] = [];
    }
    $period_platform_data[$platform][$period] = $trend['period_followers'];
}

// Now populate COUNTRY data - ONLY include periods with actual data (no filling gaps)
foreach ($country_trends as $trend) {
    $period = $trend['period'];
    $country = $trend['country'];
    
    // Country trends - only add if we have data for this period
    if (!isset($period_country_data[$country])) {
        $period_country_data[$country] = [];
    }
    $period_country_data[$country][$period] = $trend['period_followers'];
}

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
        case 'quarterly':
            $parts = explode('-Q', $period);
            $formatted_periods[] = 'Q' . $parts[1] . ' ' . $parts[0];
            break;
        case 'yearly':
            $formatted_periods[] = $period;
            break;
    }
}

// Prepare platform growth data for chart - only include platforms with data
$platform_growth_chart_data = [];
foreach ($platforms as $platform) {
    $platform_name = $platform['name'];
    if (isset($period_platform_data[$platform_name]) && !empty($period_platform_data[$platform_name])) {
        $platform_growth_chart_data[$platform_name] = $period_platform_data[$platform_name];
    }
}

// Prepare country growth data for chart - only include countries with data
$country_growth_chart_data = [];
foreach ($countries as $country) {
    if (isset($period_country_data[$country]) && !empty($period_country_data[$country])) {
        $country_growth_chart_data[$country] = $period_country_data[$country];
    }
}

// Generate unique colors for countries
$unique_country_colors = [];
$available_colors = [
    '#FF6B6B', '#4ECDC4', '#d14592ff', '#96CEB4', '#FFEAA7', 
    '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
    '#F8C471', '#82E0AA', '#F1948A', '#85C1E9', '#D7BDE2',
    '#F9E79F', '#ABEBC6', '#AED6F1', '#FAD7A0', '#236b5dff'
];

$color_index = 0;
foreach (array_keys($country_growth_chart_data) as $country) {
    $unique_country_colors[$country] = $available_colors[$color_index % count($available_colors)];
    $color_index++;
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
                        <option value="last_year" <?php echo $date_range === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                        <option value="last_2_years" <?php echo $date_range === 'last_2_years' ? 'selected' : ''; ?>>Last 2 Years</option>
                        <option value="last_5_years" <?php echo $date_range === 'last_5_years' ? 'selected' : ''; ?>>Last 5 Years</option>
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

    <!-- NEW: Platform Growth Overview Cards -->
    <div class="row mb-4">
        <!-- Overall Growth Summary Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stat-card h-100 bg-warning text-white">
                <div class="card-body text-center">
                    <h6 class="card-title text-white-50">Overall Platform Growth</h6>
                    <h3 class="<?php echo $overall_growth >= 10 ? 'text-success' : ($overall_growth >= 0 ? 'text-warning' : 'text-danger'); ?>">
                        <?php echo number_format($overall_growth, 1); ?>%
                    </h3>
                    <small class="text-white-50">Average of <?php echo $active_platform_count; ?> Platforms</small>
                    <div class="mt-2">
                        <span class="badge bg-<?php echo $overall_growth >= 10 ? 'success' : ($overall_growth >= 0 ? 'warning' : 'danger'); ?>">
                            <?php echo $overall_growth >= 10 ? 'Excellent' : ($overall_growth >= 0 ? 'Stable' : 'Declining'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Platform-specific Growth Cards -->
        <?php foreach ($platform_growth_data as $platform_name => $platform_data): ?>
            <?php if ($platform_data['current_followers'] > 0): ?>
                <div class="col-xl-3 col-lg-3 col-md-4 col-6 mb-3">
                    <div class="card stat-card h-100">
                        <div class="card-body text-center">
                            <h6 class="card-title"><?php echo $platform_name; ?></h6>
                            <h3 class="<?php echo $platform_data['overall_growth'] >= 10 ? 'text-success' : ($platform_data['overall_growth'] >= 0 ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo number_format($platform_data['overall_growth'], 1); ?>%
                            </h3>
                            <small class="text-muted">Overall Growth</small>
                            <div class="mt-2">
                                <div class="row small text-muted">
                                    <div class="col-6">
                                        <div>Followers</div>
                                        <strong class="<?php echo $platform_data['follower_growth'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($platform_data['follower_growth'], 1); ?>%
                                        </strong>
                                    </div>
                                    <div class="col-6">
                                        <div>Engagement</div>
                                        <strong class="<?php echo $platform_data['engagement_growth'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($platform_data['engagement_growth'], 1); ?>%
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Original Growth Overview (Keep for backward compatibility) -->
    <div class="row mb-4">
        <!-- <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Follower Growth</h6>
                    <h3 class="<?php echo $follower_growth >= 10 ? 'text-success' : 'text-warning'; ?>">
                        <?php echo number_format($follower_growth, 1); ?>%
                    </h3>
                    <small class=3text-muted">Total Followers</small>
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
        </div> -->
        <div class="col-xl-3 col-lg-3 col-md-4 col-6 mb-3">
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
        <div class="col-xl-3 col-lg-3 col-md-4 col-6 mb-3">
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
        <div class="col-xl-3 col-lg-3 col-md-4 col-6 mb-3">
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Platform Growth Trends</h6>
                    <span class="badge bg-info"><?php echo ucfirst($trend_interval); ?> View</span>
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
        <div class="col-lg-12 mb-4">
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

<!-- JavaScript remains the same -->
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
    labels: <?php echo json_encode($formatted_periods); ?>,
    datasets: [
        <?php foreach ($platforms as $platform): ?>
        {
            label: '<?php echo $platform['name']; ?>',
            data: [
                <?php 
                foreach ($all_periods as $period): 
                    $value = isset($period_platform_data[$platform['name']][$period]) 
                        ? $period_platform_data[$platform['name']][$period] 
                        : null;
                    echo $value !== null ? $value : 'null';
                    echo ',';
                endforeach; 
                ?>
            ],
            borderColor: '<?php echo $platform_colors[$platform['name']] ?? '#666666'; ?>',
            backgroundColor: '<?php echo $platform_colors[$platform['name']] ?? '#666666'; ?>22',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            spanGaps: true
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
                text: '<?php echo ucfirst($trend_interval); ?> Follower Growth by Platform'
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