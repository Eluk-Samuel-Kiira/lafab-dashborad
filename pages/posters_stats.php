<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Helper function to determine performance tier
function getPerformanceTier($jobs_per_day, $consistency_score) {
    if ($jobs_per_day >= 20 && $consistency_score >= 80) return 'Top Performer';
    if ($jobs_per_day >= 15 && $consistency_score >= 70) return 'Strong';
    if ($jobs_per_day >= 10 && $consistency_score >= 60) return 'Good';
    if ($jobs_per_day >= 5 && $consistency_score >= 50) return 'Average';
    return 'Needs Support';
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$country_filter = $_GET['country'] ?? '';
$poster_filter = $_GET['poster'] ?? '';
$view_poster = $_GET['view'] ?? '';

// Get current month for progress tracking
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-d');

// Countries and posters for filters
$countries = ['Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Zambia', 'Malawi'];
$all_posters = db_fetch_all("SELECT name FROM posters WHERE is_active = 1 ORDER BY name");

// Build WHERE conditions
$where_conditions = ["j.post_date BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if ($country_filter) {
    $where_conditions[] = "j.website LIKE ?";
    $params[] = "%$country_filter%";
}

if ($poster_filter) {
    $where_conditions[] = "p.name = ?";
    $params[] = $poster_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get comprehensive poster statistics
$sql = "
    SELECT 
        p.name as poster_name,
        COUNT(DISTINCT j.post_date) as posting_days,
        COUNT(j.id) as total_posts,
        SUM(j.job_count) as total_jobs,
        AVG(j.job_count) as avg_jobs_per_post,
        MIN(j.post_date) as first_post,
        MAX(j.post_date) as last_post,
        ps.jobs_per_payment,
        ps.payment_amount,
        ps.currency
    FROM posters p
    LEFT JOIN job_postings j ON p.name = j.poster_name AND $where_clause
    LEFT JOIN payment_settings ps ON p.name = ps.poster_name
    WHERE p.is_active = 1
    GROUP BY p.id, p.name
    ORDER BY total_jobs DESC
";

$poster_stats = db_fetch_all($sql, $params);

// Calculate metrics
$metrics = [];
$total_earnings = 0;
$total_jobs = 0;
$poster_details = [];
$single_poster_trend = [];

foreach ($poster_stats as $stat) {
    $jobs = $stat['total_jobs'] ?? 0;
    $total_jobs += $jobs;
    
    // Calculate earnings
    if ($jobs > 0 && $stat['jobs_per_payment'] > 0) {
        $payments = floor($jobs / $stat['jobs_per_payment']);
        // $earnings = $payments * $stat['payment_amount'];
        $earnings = $payments * 10000;
        $total_earnings += $earnings;
    } else {
        $payments = 0;
        $earnings = 0;
    }
    
    // Calculate performance
    if ($stat['first_post'] && $stat['last_post']) {
        $days = max(1, (strtotime($stat['last_post']) - strtotime($stat['first_post'])) / 86400 + 1);
        $jobs_per_day = $jobs / $days;
        $consistency = ($stat['posting_days'] / $days) * 100;
    } else {
        $jobs_per_day = 0;
        $consistency = 0;
    }
    
    $metrics[$stat['poster_name']] = [
        'jobs' => $jobs,
        'earnings' => $earnings,
        'payments' => $payments,
        'jobs_per_day' => $jobs_per_day,
        'consistency' => $consistency,
        'performance' => getPerformanceTier($jobs_per_day, $consistency),
        'avg_per_post' => $stat['avg_jobs_per_post'] ?? 0,
        'posting_days' => $stat['posting_days'] ?? 0,
        'total_posts' => $stat['total_posts'] ?? 0,
        'first_post' => $stat['first_post'] ?? '',
        'last_post' => $stat['last_post'] ?? ''
    ];
    
    // Store details for single poster view
    if ($view_poster === $stat['poster_name']) {
        $poster_details = $metrics[$stat['poster_name']];
        $poster_details['name'] = $stat['poster_name'];
        
        // Get single poster trend data for last 6 months
        $six_months_ago = date('Y-m-d', strtotime('-6 months'));
        $single_poster_trend = db_fetch_all("
            SELECT 
                strftime('%Y-%m', j.post_date) as month,
                SUM(j.job_count) as monthly_jobs,
                COUNT(j.id) as monthly_posts
            FROM job_postings j
            WHERE j.poster_name = ? 
                AND j.post_date >= ?
            GROUP BY strftime('%Y-%m', j.post_date)
            ORDER BY month
        ", [$view_poster, $six_months_ago]);
    }
}

// Get current month posts data - SORTED BY NUMBER OF POSTS
$current_month_posts = db_fetch_all("
    SELECT 
        poster_name,
        COUNT(*) as post_count,
        SUM(job_count) as total_jobs
    FROM job_postings 
    WHERE post_date BETWEEN ? AND ?
    GROUP BY poster_name
    ORDER BY post_count DESC
    LIMIT 6
", [$current_month_start, $current_month_end]);

// Get country distribution
$country_data = db_fetch_all("
    SELECT 
        CASE 
            WHEN website LIKE '%uganda%' THEN 'Uganda'
            WHEN website LIKE '%kenya%' THEN 'Kenya' 
            WHEN website LIKE '%tanzania%' THEN 'Tanzania'
            WHEN website LIKE '%rwanda%' THEN 'Rwanda'
            WHEN website LIKE '%zambia%' THEN 'Zambia'
            WHEN website LIKE '%malawi%' THEN 'Malawi'
            ELSE 'Other'
        END as country,
        SUM(job_count) as jobs,
        COUNT(DISTINCT poster_name) as posters_count
    FROM job_postings 
    WHERE post_date BETWEEN ? AND ?
    GROUP BY country
    ORDER BY jobs DESC
", [$start_date, $end_date]);

// FIXED: Get earnings by month for the earnings chart - Get last 6 months of data
$six_months_ago = date('Y-m-d', strtotime('-6 months'));
$monthly_earnings_data = db_fetch_all("
    SELECT 
        strftime('%Y-%m', j.post_date) as month,
        SUM(j.job_count) as total_jobs,
        ps.payment_amount,
        ps.jobs_per_payment
    FROM job_postings j
    JOIN posters p ON j.poster_name = p.name
    LEFT JOIN payment_settings ps ON p.name = ps.poster_name
    WHERE j.post_date >= ?
        AND j.post_date <= ?
    GROUP BY strftime('%Y-%m', j.post_date)
    ORDER BY month
", [$six_months_ago, $end_date]);

// Calculate monthly earnings properly
$monthly_earnings = [];
$monthly_labels = [];
foreach ($monthly_earnings_data as $row) {
    $month = $row['month'];
    $jobs = $row['total_jobs'] ?? 0;
    $payment_amount = $row['payment_amount'] ?? 0;
    $jobs_per_payment = $row['jobs_per_payment'] ?? 1;
    
    // Convert month to readable format
    $date = DateTime::createFromFormat('Y-m', $month);
    $monthly_labels[$month] = $date->format('M Y');
    
    // Calculate earnings properly
    if ($jobs_per_payment > 0) {
        $payments = floor($jobs / $jobs_per_payment);
        $monthly_earnings[$month] = $payments * $payment_amount;
    } else {
        $monthly_earnings[$month] = 0;
    }
}

// Ensure we have data for all last 6 months
$all_months = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $all_months[] = $month;
    
    if (!isset($monthly_earnings[$month])) {
        $monthly_earnings[$month] = 0;
        $date = DateTime::createFromFormat('Y-m', $month);
        $monthly_labels[$month] = $date->format('M Y');
    }
}

// Sort by month
ksort($monthly_earnings);
ksort($monthly_labels);

// Generate recommendations
$recommendations = [];
foreach ($metrics as $poster => $data) {
    if ($data['performance'] === 'Needs Support') {
        $recommendations[] = [
            'poster' => $poster,
            'message' => "Only " . round($data['jobs_per_day'], 1) . " jobs/day. Consider increasing daily targets.",
            'type' => 'warning'
        ];
    } elseif ($data['performance'] === 'Top Performer') {
        $recommendations[] = [
            'poster' => $poster,
            'message' => "Excellent performer! " . round($data['jobs_per_day'], 1) . " jobs/day. Consider bonus reward.",
            'type' => 'success'
        ];
    }
    
    if ($data['consistency'] < 50) {
        $recommendations[] = [
            'poster' => $poster,
            'message' => "Low consistency (" . round($data['consistency']) . "%). Needs more regular posting.",
            'type' => 'danger'
        ];
    }
}

// Calculate key metrics
$avg_jobs_per_day = count($metrics) > 0 ? array_sum(array_column($metrics, 'jobs_per_day')) / count($metrics) : 0;
$total_payments = array_sum(array_column($metrics, 'payments'));
$avg_earnings_per_poster = count($metrics) > 0 ? array_sum(array_column($metrics, 'earnings')) / count($metrics) : 0;

// Find top performers
usort($poster_stats, function($a, $b) use ($metrics) {
    $jobs_a = $metrics[$a['poster_name']]['jobs'] ?? 0;
    $jobs_b = $metrics[$b['poster_name']]['jobs'] ?? 0;
    return $jobs_b - $jobs_a;
});
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">📊 Poster Analytics Dashboard</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => ''])); ?>" class="btn btn-sm btn-outline-secondary">Back to All</a>
                <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
            </div>
        </div>
    </div>

    <?php if ($view_poster && !empty($poster_details)): ?>
    <!-- Single Poster View -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">👤 Poster Details: <?php echo htmlspecialchars($poster_details['name']); ?></h5>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => ''])); ?>" class="btn btn-sm btn-outline-secondary">← Back to All</a>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Total Jobs</h6>
                                    <h3 class="text-primary"><?php echo $poster_details['jobs']; ?></h3>
                                    <small class="text-muted">Posted</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Total Earnings</h6>
                                    <h3 class="text-success">$<?php echo number_format($poster_details['earnings'], 2); ?></h3>
                                    <small class="text-muted">Earned</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Daily Average</h6>
                                    <h3 class="text-warning"><?php echo round($poster_details['jobs_per_day'], 1); ?></h3>
                                    <small class="text-muted">Jobs/Day</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card h-100">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Performance</h6>
                                    <h3>
                                        <span class="badge bg-<?php 
                                            echo match($poster_details['performance']) {
                                                'Top Performer' => 'success',
                                                'Strong' => 'info',
                                                'Good' => 'warning',
                                                'Average' => 'primary',
                                                default => 'danger'
                                            };
                                        ?>">
                                            <?php echo $poster_details['performance']; ?>
                                        </span>
                                    </h3>
                                    <small class="text-muted">Rating</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($single_poster_trend)): ?>
                    <!-- Single Poster Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">📈 <?php echo htmlspecialchars($poster_details['name']); ?>'s Performance (Last 6 Months)</h6>
                        </div>
                        <div class="card-body">
                            <div style="height: 300px;">
                                <canvas id="singlePosterChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-2 col-lg-4 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Posters</h6>
                    <h3 class="text-primary"><?php echo count($poster_stats); ?></h3>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Jobs</h6>
                    <h3 class="text-success"><?php echo number_format($total_jobs); ?></h3>
                    <small class="text-muted">Posted</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Earnings</h6>
                    <h3 class="text-warning">$<?php echo number_format($total_earnings, 2); ?></h3>
                    <small class="text-muted">Paid</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Avg Daily Jobs</h6>
                    <h3 class="text-info"><?php echo number_format($avg_jobs_per_day, 1); ?></h3>
                    <small class="text-muted">Per Poster</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Payments</h6>
                    <h3 class="text-dark"><?php echo $total_payments; ?></h3>
                    <small class="text-muted">Made</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Avg Earnings</h6>
                    <h3 class="text-danger">$<?php echo number_format($avg_earnings_per_poster, 2); ?></h3>
                    <small class="text-muted">Per Poster</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Month Progress -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">📅 Current Month Progress (<?php echo date('F Y'); ?>)</h6>
            <small class="text-muted">Sorted by number of posts</small>
        </div>
        <div class="card-body">
            <?php if (empty($current_month_posts)): ?>
                <div class="alert alert-info">
                    No posts found for the current month yet.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($current_month_posts as $poster_data): 
                        $poster_name = $poster_data['poster_name'];
                        $post_count = $poster_data['post_count'];
                        $total_jobs = $poster_data['total_jobs'];
                        
                        // Get poster's payment settings for accurate calculation
                        $payment_settings = db_fetch_one("
                            SELECT jobs_per_payment, payment_amount 
                            FROM payment_settings 
                            WHERE poster_name = ?
                        ", [$poster_name]);
                        
                        $jobs_per_payment = $payment_settings['jobs_per_payment'] ?? 100;
                        // $payment_amount = $payment_settings['payment_amount'] ?? 100;
                        $payment_amount = 10000;
                        
                        // Calculate payment progress
                        $progress_percent = $jobs_per_payment > 0 ? min(100, ($total_jobs % $jobs_per_payment) / $jobs_per_payment * 100) : 0;
                        $payments_earned = $jobs_per_payment > 0 ? floor($total_jobs / $jobs_per_payment) : 0;
                        $earnings = $payments_earned * $payment_amount;
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($poster_name); ?></h6>
                                    <span class="badge bg-primary"><?php echo $post_count; ?> posts</span>
                                </div>
                                
                                <!-- Jobs Progress -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Jobs Progress</small>
                                        <small><strong><?php echo $total_jobs; ?> jobs</strong></small>
                                    </div>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar bg-success" 
                                             style="width: <?php echo $progress_percent; ?>%">
                                            <?php echo $total_jobs % ($jobs_per_payment ?: 100); ?>/<?php echo $jobs_per_payment ?: 100; ?> jobs
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Posts vs Jobs Info -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted d-block">
                                            📝 <?php echo $post_count; ?> posts
                                        </small>
                                        <small class="text-muted d-block">
                                            💼 <?php echo $total_jobs; ?> jobs
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">Payments Earned:</small>
                                        <small class="text-success"><strong><?php echo $payments_earned; ?> × $<?php echo $payment_amount; ?></strong></small>
                                    </div>
                                </div>
                                
                                <!-- Earnings Summary -->
                                <div class="mt-2 pt-2 border-top">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Total Earnings:</small>
                                        <strong class="text-success">$<?php echo number_format($earnings); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Enhanced Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">📅 From</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">📅 To</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">🌍 Country</label>
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
                    <label class="form-label">👤 Select Poster</label>
                    <select name="poster" class="form-select">
                        <option value="">All Posters</option>
                        <?php foreach ($all_posters as $poster): ?>
                            <option value="<?php echo $poster['name']; ?>" <?php echo $poster_filter === $poster['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($poster['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">📊 Update Analytics</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SIMPLIFIED CHARTS -->
    <div class="row mb-4">
        <!-- Jobs by Country -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">🌍 Jobs by Country</h6>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="countryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Fixed Monthly Earnings Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">💰 Monthly Earnings (Last 6 Months)</h6>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Distribution Chart -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">📊 Performance Levels Distribution</h6>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">🏆 Top Performers</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Poster</th>
                            <th>Performance</th>
                            <th>Total Jobs</th>
                            <th>Daily Rate</th>
                            <th>Earnings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < min(10, count($poster_stats)); $i++): 
                            $poster = $poster_stats[$i];
                            $poster_metrics = $metrics[$poster['poster_name']] ?? [];
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?php echo $i < 3 ? 'warning' : 'secondary'; ?>">
                                    #<?php echo $i + 1; ?>
                                </span>
                            </td>
                            <td><strong><?php echo htmlspecialchars($poster['poster_name']); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($poster_metrics['performance'] ?? '') {
                                        'Top Performer' => 'success',
                                        'Strong' => 'info',
                                        'Good' => 'warning',
                                        'Average' => 'primary',
                                        default => 'danger'
                                    };
                                ?>">
                                    <?php echo $poster_metrics['performance'] ?? 'Needs Support'; ?>
                                </span>
                            </td>
                            <td><?php echo $poster_metrics['jobs'] ?? 0; ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?php echo ($poster_metrics['jobs_per_day'] ?? 0) >= 15 ? 'success' : (($poster_metrics['jobs_per_day'] ?? 0) >= 8 ? 'warning' : 'danger'); ?>" 
                                         style="width: <?php echo min(100, (($poster_metrics['jobs_per_day'] ?? 0) / 25) * 100); ?>%">
                                        <?php echo round($poster_metrics['jobs_per_day'] ?? 0, 1); ?>/day
                                    </div>
                                </div>
                            </td>
                            <td class="text-success">$<?php echo number_format($poster_metrics['earnings'] ?? 0, 2); ?></td>
                            <td>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => $poster['poster_name']])); ?>" 
                                   class="btn btn-sm btn-outline-primary">View Details</a>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    <?php if (!empty($recommendations)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">💡 Recommendations & Actions</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($recommendations as $rec): ?>
                <div class="col-md-6 mb-3">
                    <div class="alert alert-<?php echo $rec['type']; ?> mb-0">
                        <strong><?php echo $rec['poster']; ?>:</strong> <?php echo $rec['message']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- All Posters Summary -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">📋 All Posters Summary</h6>
            <span>Showing <?php echo count($poster_stats); ?> posters</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Poster</th>
                            <th>Performance</th>
                            <th>Total Jobs</th>
                            <th>Daily Avg</th>
                            <th>Consistency</th>
                            <th>Earnings</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($poster_stats as $poster): 
                            $data = $metrics[$poster['poster_name']] ?? [];
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($poster['poster_name']); ?></strong></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($data['performance'] ?? '') {
                                        'Top Performer' => 'success',
                                        'Strong' => 'info',
                                        'Good' => 'warning',
                                        'Average' => 'primary',
                                        default => 'danger'
                                    };
                                ?>">
                                    <?php echo $data['performance'] ?? 'Needs Support'; ?>
                                </span>
                            </td>
                            <td><?php echo $data['jobs'] ?? 0; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="me-2"><?php echo round($data['jobs_per_day'] ?? 0, 1); ?>/day</span>
                                    <?php if (($data['jobs_per_day'] ?? 0) >= 15): ?>
                                        <span class="badge bg-success">✓</span>
                                    <?php elseif (($data['jobs_per_day'] ?? 0) >= 8): ?>
                                        <span class="badge bg-warning">⚠</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (isset($data['consistency'])): ?>
                                    <?php if ($data['consistency'] >= 70): ?>
                                        <span class="text-success">Good (<?php echo round($data['consistency']); ?>%)</span>
                                    <?php elseif ($data['consistency'] >= 50): ?>
                                        <span class="text-warning">Fair (<?php echo round($data['consistency']); ?>%)</span>
                                    <?php else: ?>
                                        <span class="text-danger">Low (<?php echo round($data['consistency']); ?>%)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-success">$<?php echo number_format($data['earnings'] ?? 0, 2); ?></td>
                            <td>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => $poster['poster_name']])); ?>" 
                                   class="btn btn-sm btn-outline-primary">View</a>
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
// 1. Country Distribution Chart
const countryCtx = document.getElementById('countryChart').getContext('2d');
new Chart(countryCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($country_data, 'country')); ?>,
        datasets: [{
            label: 'Jobs Posted',
            data: <?php echo json_encode(array_column($country_data, 'jobs')); ?>,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                '#9966FF', '#FF9F40', '#8B93FF'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Jobs'
                }
            }
        }
    }
});

// 2. FIXED Monthly Earnings Chart - Last 6 Months
const earningsCtx = document.getElementById('earningsChart').getContext('2d');

// Get last 6 months labels
const last6Months = [];
const earningsByMonth = <?php echo json_encode($monthly_earnings); ?>;
const monthLabels = <?php echo json_encode($monthly_labels); ?>;

// Sort months chronologically
const sortedMonths = Object.keys(earningsByMonth).sort();
const last6SortedMonths = sortedMonths.slice(-6); // Get last 6 months

// Prepare data for chart
const chartMonths = last6SortedMonths.map(month => monthLabels[month]);
const chartEarnings = last6SortedMonths.map(month => earningsByMonth[month]);

new Chart(earningsCtx, {
    type: 'line',
    data: {
        labels: chartMonths,
        datasets: [{
            label: 'Earnings ($)',
            data: chartEarnings,
            borderColor: '#28a745',
            backgroundColor: '#28a74520',
            fill: true,
            tension: 0.4,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Earnings: $${context.raw.toLocaleString()}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Earnings ($)'
                },
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Month'
                }
            }
        }
    }
});

// 3. Performance Distribution Chart
const performanceCtx = document.getElementById('performanceChart').getContext('2d');

// Count posters by performance level
const performanceCounts = {
    'Top Performer': 0,
    'Strong': 0,
    'Good': 0,
    'Average': 0,
    'Needs Support': 0
};

<?php foreach ($metrics as $data): ?>
performanceCounts['<?php echo $data['performance']; ?>']++;
<?php endforeach; ?>

new Chart(performanceCtx, {
    type: 'pie',
    data: {
        labels: Object.keys(performanceCounts),
        datasets: [{
            data: Object.values(performanceCounts),
            backgroundColor: [
                '#28a745', // Top Performer - Green
                '#17a2b8', // Strong - Blue
                '#ffc107', // Good - Yellow
                '#0d6efd', // Average - Primary Blue
                '#dc3545'  // Needs Support - Red
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    boxWidth: 12,
                    font: {
                        size: 11
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label;
                        const value = context.raw;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} posters (${percentage}%)`;
                    }
                }
            }
        }
    }
});

<?php if ($view_poster && !empty($single_poster_trend)): ?>
// Single Poster Chart
const singlePosterCtx = document.getElementById('singlePosterChart').getContext('2d');
const singlePosterData = <?php echo json_encode($single_poster_trend); ?>;

// Prepare data for the chart
const posterMonths = singlePosterData.map(item => {
    const [year, month] = item.month.split('-');
    return new Date(year, month - 1).toLocaleString('default', { month: 'short' }) + ' ' + year.toString().slice(-2);
});
const posterJobs = singlePosterData.map(item => item.monthly_jobs || 0);

new Chart(singlePosterCtx, {
    type: 'bar',
    data: {
        labels: posterMonths,
        datasets: [{
            label: 'Jobs Posted',
            data: posterJobs,
            backgroundColor: '#0d6efd',
            borderColor: '#0a58ca',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Jobs'
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>