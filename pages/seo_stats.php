<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get filter parameters
$website_filter = $_GET['website'] ?? '';
$keyword_filter = $_GET['keyword'] ?? '';
$date_range = $_GET['date_range'] ?? 'last_4_weeks';

// Calculate date ranges
$end_date = date('Y-m-d');
switch ($date_range) {
    case 'last_week':
        $start_date = date('Y-m-d', strtotime('-1 week'));
        $period_label = 'Last Week';
        break;
    case 'last_month':
        $start_date = date('Y-m-d', strtotime('-1 month'));
        $period_label = 'Last Month';
        break;
    case 'last_3_months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        $period_label = 'Last 3 Months';
        break;
    default: // last_4_weeks
        $start_date = date('Y-m-d', strtotime('-4 weeks'));
        $period_label = 'Last 4 Weeks';
}

// Build WHERE clause for filters
$where_conditions = ["week_start BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if ($website_filter) {
    $where_conditions[] = "website = ?";
    $params[] = $website_filter;
}

if ($keyword_filter) {
    $where_conditions[] = "keyword LIKE ?";
    $params[] = "%$keyword_filter%";
}

$where_clause = implode(" AND ", $where_conditions);

// Calculate overall position for all queries
$overall_position_calc = "(page_number - 1) * 10 + position_on_page";

// Get comprehensive SEO statistics

// 1. Overall performance summary
$performance_summary = db_fetch_one("
    SELECT 
        COUNT(DISTINCT keyword) as total_keywords,
        COUNT(*) as total_rankings,
        AVG($overall_position_calc) as avg_overall_position,
        SUM(CASE WHEN $overall_position_calc <= 3 THEN 1 ELSE 0 END) as top_3_count,
        SUM(CASE WHEN $overall_position_calc <= 10 THEN 1 ELSE 0 END) as top_10_count,
        SUM(CASE WHEN page_number = 1 THEN 1 ELSE 0 END) as page_1_count,
        SUM(CASE WHEN $overall_position_calc <= 20 THEN 1 ELSE 0 END) as top_20_count,
        SUM(CASE WHEN $overall_position_calc > 50 THEN 1 ELSE 0 END) as below_50_count
    FROM seo_rankings 
    WHERE $where_clause
", $params);

// 2. Weekly trends for ALL websites (countries)
$weekly_trends_all = db_fetch_all("
    SELECT 
        week_start,
        website,
        AVG($overall_position_calc) as avg_position,
        COUNT(DISTINCT keyword) as keywords_tracked,
        SUM(CASE WHEN $overall_position_calc <= 10 THEN 1 ELSE 0 END) as top_10_keywords,
        SUM(CASE WHEN page_number = 1 THEN 1 ELSE 0 END) as page_1_keywords
    FROM seo_rankings 
    WHERE $where_clause
    GROUP BY week_start, website
    ORDER BY week_start ASC, website
", $params);

// 3. Process data for multi-line chart
$websites_data = [];
$all_weeks = [];
$country_colors = [
    'greatugandajobs.com' => '#dc3545',    // Red
    'greatkenyanjobs.com' => '#198754',     // Green  
    'greattanzaniajobs.com' => '#0d6efd',   // Blue
    'greatrwandajobs.com' => '#ffc107',     // Yellow
    'greatzambiajobs.com' => '#6f42c1'      // Purple
];

$country_names = [
    'greatugandajobs.com' => 'Uganda',
    'greatkenyanjobs.com' => 'Kenya',
    'greattanzaniajobs.com' => 'Tanzania', 
    'greatrwandajobs.com' => 'Rwanda',
    'greatzambiajobs.com' => 'Zambia'
];

// Initialize data structure for all websites
foreach ($websites as $site) {
    $websites_data[$site] = [
        'name' => $country_names[$site] ?? $site,
        'color' => $country_colors[$site] ?? '#6c757d',
        'data' => [],
        'trend' => 'stable'
    ];
}

// Process weekly trends data
foreach ($weekly_trends_all as $trend) {
    $week = $trend['week_start'];
    $website = $trend['website'];
    
    if (!in_array($week, $all_weeks)) {
        $all_weeks[] = $week;
    }
    
    if (isset($websites_data[$website])) {
        $websites_data[$website]['data'][$week] = floatval($trend['avg_position']);
    }
}

// Calculate trends (improving/declining) for each website
foreach ($websites_data as $site => $data) {
    if (count($data['data']) >= 2) {
        $weeks = array_keys($data['data']);
        $first_week = $weeks[0];
        $last_week = $weeks[count($weeks) - 1];
        
        $first_position = $data['data'][$first_week];
        $last_position = $data['data'][$last_week];
        
        if ($last_position < $first_position) {
            $websites_data[$site]['trend'] = 'improving';
        } elseif ($last_position > $first_position) {
            $websites_data[$site]['trend'] = 'declining';
        } else {
            $websites_data[$site]['trend'] = 'stable';
        }
    }
}

// 4. Website performance comparison
$website_performance = db_fetch_all("
    SELECT 
        website,
        COUNT(DISTINCT keyword) as total_keywords,
        AVG($overall_position_calc) as avg_overall_position,
        SUM(CASE WHEN $overall_position_calc <= 3 THEN 1 ELSE 0 END) as top_3_count,
        SUM(CASE WHEN $overall_position_calc <= 10 THEN 1 ELSE 0 END) as top_10_count,
        SUM(CASE WHEN page_number = 1 THEN 1 ELSE 0 END) as page_1_count,
        (SUM(CASE WHEN $overall_position_calc <= 10 THEN 1 ELSE 0 END) * 1.0 / COUNT(*)) * 100 as top_10_percentage
    FROM seo_rankings 
    WHERE $where_clause
    GROUP BY website
    ORDER BY avg_overall_position ASC
", $params);

// 5. Top performing keywords
$top_keywords = db_fetch_all("
    SELECT 
        keyword,
        website,
        AVG($overall_position_calc) as avg_overall_position,
        AVG(page_number) as avg_page,
        AVG(position_on_page) as avg_position_on_page,
        COUNT(DISTINCT week_start) as weeks_tracked,
        MIN($overall_position_calc) as best_overall_position,
        MAX($overall_position_calc) as worst_overall_position
    FROM seo_rankings 
    WHERE $where_clause
    GROUP BY keyword, website
    ORDER BY avg_overall_position ASC
    LIMIT 15
", $params);

// 6. Improvement opportunities
$improvement_opportunities = db_fetch_all("
    SELECT 
        keyword,
        website,
        AVG($overall_position_calc) as current_position,
        COUNT(*) as tracking_weeks,
        MIN($overall_position_calc) as best_position,
        MAX($overall_position_calc) as worst_position
    FROM seo_rankings 
    WHERE $where_clause AND $overall_position_calc BETWEEN 11 AND 100
    GROUP BY keyword, website
    ORDER BY current_position ASC
    LIMIT 15
", $params);

// Get unique keywords for filter
$all_keywords = db_fetch_all("
    SELECT DISTINCT keyword 
    FROM seo_rankings 
    ORDER BY keyword
    LIMIT 100
");
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">SEO Performance Dashboard</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="add_seo.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus-circle"></i> Add SEO Data
                </a>
                <a href="seo_stats.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync"></i> Refresh
                </a>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-primary">
                    <i class="fas fa-filter me-2"></i>Data Filters
                </h6>
                <span class="badge bg-light text-dark"><?php echo $period_label; ?></span>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-semibold">Website</label>
                    <select name="website" class="form-select border-0 bg-light">
                        <option value="">All Websites</option>
                        <?php foreach ($websites as $site): ?>
                            <option value="<?php echo $site; ?>" <?php echo $website_filter === $site ? 'selected' : ''; ?>>
                                <?php echo $site; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-semibold">Keyword</label>
                    <input type="text" name="keyword" class="form-control border-0 bg-light" 
                           value="<?php echo htmlspecialchars($keyword_filter); ?>" 
                           list="keywordList" placeholder="Search keyword...">
                    <datalist id="keywordList">
                        <?php foreach ($all_keywords as $keyword_item): ?>
                            <option value="<?php echo htmlspecialchars($keyword_item['keyword']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-semibold">Date Range</label>
                    <select name="date_range" class="form-select border-0 bg-light">
                        <option value="last_week" <?php echo $date_range === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                        <option value="last_4_weeks" <?php echo $date_range === 'last_4_weeks' ? 'selected' : ''; ?>>Last 4 Weeks</option>
                        <option value="last_month" <?php echo $date_range === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                        <option value="last_3_months" <?php echo $date_range === 'last_3_months' ? 'selected' : ''; ?>>Last 3 Months</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-semibold invisible">Apply</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-chart-line me-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Performance Overview Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-keyboard fa-2x text-primary"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $performance_summary['total_keywords'] ?? 0; ?></h4>
                    <p class="text-muted small mb-0">Keywords Tracked</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-trophy fa-2x text-warning"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $performance_summary['top_3_count'] ?? 0; ?></h4>
                    <p class="text-muted small mb-0">Top 3 Rankings</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-star fa-2x text-success"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $performance_summary['top_10_count'] ?? 0; ?></h4>
                    <p class="text-muted small mb-0">Top 10 Rankings</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-file-alt fa-2x text-info"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $performance_summary['page_1_count'] ?? 0; ?></h4>
                    <p class="text-muted small mb-0">Page 1 Rankings</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-chart-line fa-2x text-danger"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo number_format($performance_summary['avg_overall_position'] ?? 0, 1); ?></h4>
                    <p class="text-muted small mb-0">Avg Position</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-bullseye fa-2x text-purple"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">
                        <?php 
                        $success_rate = ($performance_summary['total_rankings'] ?? 0) > 0 ? 
                            (($performance_summary['top_10_count'] ?? 0) / ($performance_summary['total_rankings'] ?? 1) * 100) : 0;
                        echo number_format($success_rate, 1); 
                        ?>%
                    </h4>
                    <p class="text-muted small mb-0">Top 10 Success Rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Multi-Country Performance Trends -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-primary">
                            <i class="fas fa-chart-line me-2"></i>Multi-Country SEO Performance Trends
                        </h6>
                        <div class="trend-indicators">
                            <small class="text-muted">
                                <i class="fas fa-arrow-up text-success"></i> Improving
                                <i class="fas fa-arrow-down text-danger ms-2"></i> Declining
                                <i class="fas fa-minus text-warning ms-2"></i> Stable
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($weekly_trends_all)): ?>
                        <div class="chart-container" style="height: 350px;">
                            <canvas id="multiCountryTrendsChart"></canvas>
                        </div>
                        
                        <!-- Country Performance Summary -->
                        <div class="row mt-4">
                            <?php foreach ($websites_data as $site => $data): 
                                if (!empty($data['data'])): 
                                    $latest_position = end($data['data']);
                                    $trend_icon = $data['trend'] === 'improving' ? 'arrow-up text-success' : 
                                                ($data['trend'] === 'declining' ? 'arrow-down text-danger' : 'minus text-warning');
                            ?>
                                <div class="col-md-2 col-4 mb-2">
                                    <div class="country-summary text-center">
                                        <div class="country-color-indicator" style="background-color: <?php echo $data['color']; ?>; width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 5px;"></div>
                                        <small class="fw-semibold"><?php echo $data['name']; ?></small>
                                        <div class="mt-1">
                                            <span class="badge bg-light text-dark">#<?php echo number_format($latest_position, 1); ?></span>
                                            <i class="fas fa-<?php echo $trend_icon; ?> ms-1"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No multi-country trend data available for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Website Comparison -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-trophy me-2"></i>Country Performance Ranking
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($website_performance)): ?>
                        <div class="country-ranking">
                            <?php $rank = 1; ?>
                            <?php foreach ($website_performance as $website): ?>
                                <div class="country-rank-item d-flex align-items-center mb-3 p-2 border rounded">
                                    <div class="rank-number me-3">
                                        <span class="badge 
                                            <?php echo $rank == 1 ? 'bg-warning' : 
                                                  ($rank == 2 ? 'bg-secondary' : 
                                                  ($rank == 3 ? 'bg-info' : 'bg-light text-dark')); ?>">
                                            #<?php echo $rank; ?>
                                        </span>
                                    </div>
                                    <div class="country-info flex-grow-1">
                                        <div class="fw-semibold"><?php echo $country_names[$website['website']] ?? $website['website']; ?></div>
                                        <div class="small text-muted">
                                            Avg: #<?php echo number_format($website['avg_overall_position'], 1); ?> | 
                                            Top 10: <?php echo $website['top_10_count']; ?>
                                        </div>
                                    </div>
                                    <div class="country-stats text-end">
                                        <div class="text-success small">
                                            <?php echo number_format($website['top_10_percentage'], 1); ?>%
                                        </div>
                                        <div class="text-muted small">Success Rate</div>
                                    </div>
                                </div>
                                <?php $rank++; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No website performance data available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers & Improvement Opportunities -->
    <div class="row mb-4">
        <!-- Top Performing Keywords -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-success">
                        <i class="fas fa-trophy me-2"></i>Top Performing Keywords
                    </h6>
                    <span class="badge bg-success">Best Rankings</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_keywords)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Keyword</th>
                                        <th>Country</th>
                                        <th>Avg Position</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_keywords as $keyword): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="keyword-icon me-2">
                                                        <i class="fas fa-search text-muted"></i>
                                                    </div>
                                                    <div>
                                                        <strong class="d-block"><?php echo htmlspecialchars(mb_strimwidth($keyword['keyword'], 0, 25, '...')); ?></strong>
                                                        <small class="text-muted"><?php echo $keyword['weeks_tracked']; ?> weeks</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $country_names[$keyword['website']] ?? $keyword['website']; ?></span>
                                            </td>
                                            <td>
                                                <div class="position-display">
                                                    <span class="badge bg-<?php echo $keyword['avg_overall_position'] <= 3 ? 'success' : ($keyword['avg_overall_position'] <= 10 ? 'warning' : 'info'); ?>">
                                                        #<?php echo number_format($keyword['avg_overall_position'], 1); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($keyword['avg_overall_position'] <= 3): ?>
                                                    <span class="text-success">
                                                        <i class="fas fa-crown me-1"></i>Excellent
                                                    </span>
                                                <?php elseif ($keyword['avg_overall_position'] <= 10): ?>
                                                    <span class="text-warning">
                                                        <i class="fas fa-star me-1"></i>Good
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-info">
                                                        <i class="fas fa-chart-line me-1"></i>Improving
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No top performing keywords found.</p>
                            <a href="add_seo.php" class="btn btn-sm btn-outline-primary">Add SEO Data</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Improvement Opportunities -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-warning">
                        <i class="fas fa-bullseye me-2"></i>Improvement Opportunities
                    </h6>
                    <span class="badge bg-warning">Near Top 10</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($improvement_opportunities)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Keyword</th>
                                        <th>Country</th>
                                        <th>Current</th>
                                        <th>Target</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($improvement_opportunities as $keyword): 
                                        $positions_to_gain = max(1, $keyword['current_position'] - 10);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="keyword-icon me-2">
                                                        <i class="fas fa-target text-warning"></i>
                                                    </div>
                                                    <div>
                                                        <strong class="d-block"><?php echo htmlspecialchars(mb_strimwidth($keyword['keyword'], 0, 25, '...')); ?></strong>
                                                        <small class="text-muted"><?php echo $keyword['tracking_weeks']; ?> weeks</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $country_names[$keyword['website']] ?? $keyword['website']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">#<?php echo number_format($keyword['current_position'], 1); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">Top 10</span>
                                                <small class="text-muted d-block">Gain <?php echo number_format($positions_to_gain, 1); ?> positions</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bullseye fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No improvement opportunities found.</p>
                            <small class="text-muted">All keywords are either in top positions or need more data.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($weekly_trends_all)): ?>
<script>
// Multi-Country Performance Trends Chart
const multiTrendsCtx = document.getElementById('multiCountryTrendsChart').getContext('2d');

// Prepare datasets for all countries
const datasets = [];
<?php foreach ($websites_data as $site => $data): ?>
    <?php if (!empty($data['data'])): ?>
        datasets.push({
            label: '<?php echo $data['name']; ?>',
            data: [
                <?php 
                foreach ($all_weeks as $week) {
                    $value = isset($data['data'][$week]) ? $data['data'][$week] : null;
                    echo $value !== null ? $value . ',' : 'null,';
                }
                ?>
            ],
            borderColor: '<?php echo $data['color']; ?>',
            backgroundColor: '<?php echo $data['color']; ?>20',
            borderWidth: 3,
            fill: false,
            tension: 0.4,
            pointBackgroundColor: '<?php echo $data['color']; ?>',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        });
    <?php endif; ?>
<?php endforeach; ?>

new Chart(multiTrendsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($week) { 
            return date('M j', strtotime($week)); 
        }, $all_weeks)); ?>,
        datasets: datasets
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    maxRotation: 45
                }
            },
            y: {
                beginAtZero: false,
                reverse: true, // Lower numbers (better rankings) at top
                title: {
                    display: true,
                    text: 'Average Position (Lower is Better)'
                },
                grid: {
                    borderDash: [2, 4],
                }
            }
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleColor: '#fff',
                bodyColor: '#fff',
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': #' + context.parsed.y.toFixed(1);
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<style>
.stat-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
}

.stat-card:nth-child(1) { border-left-color: #0d6efd; }
.stat-card:nth-child(2) { border-left-color: #ffc107; }
.stat-card:nth-child(3) { border-left-color: #198754; }
.stat-card:nth-child(4) { border-left-color: #0dcaf0; }
.stat-card:nth-child(5) { border-left-color: #dc3545; }
.stat-card:nth-child(6) { border-left-color: #6f42c1; }

.stat-icon {
    opacity: 0.8;
}

.chart-container {
    position: relative;
}

.keyword-icon {
    width: 24px;
    text-align: center;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
    transform: translateX(2px);
    transition: all 0.2s ease;
}

.position-display .badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.text-purple {
    color: #6f42c1 !important;
}

.bg-purple {
    background-color: #6f42c1 !important;
}

.country-rank-item {
    transition: all 0.2s ease;
}

.country-rank-item:hover {
    background-color: rgba(0, 0, 0, 0.02);
    transform: translateX(2px);
}

.country-summary {
    padding: 8px;
    border-radius: 6px;
    background-color: rgba(0, 0, 0, 0.02);
}

.trend-indicators {
    font-size: 0.8rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>