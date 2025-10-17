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
        break;
    case 'last_month':
        $start_date = date('Y-m-d', strtotime('-1 month'));
        break;
    case 'last_3_months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        break;
    default: // last_4_weeks
        $start_date = date('Y-m-d', strtotime('-4 weeks'));
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

// Get SEO statistics data with new structure

// 1. Overall position trends by week
$weekly_trends = db_fetch_all("
    SELECT 
        week_start,
        website,
        AVG($overall_position_calc) as avg_overall_position,
        COUNT(*) as keyword_count
    FROM seo_rankings 
    WHERE $where_clause
    GROUP BY week_start, website
    ORDER BY week_start DESC, website
", $params);

// 2. Top performing keywords
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
    HAVING COUNT(*) >= 1
    ORDER BY avg_overall_position ASC
    LIMIT 20
", $params);

// 3. Worst performing keywords
$worst_keywords = db_fetch_all("
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
    WHERE $where_clause AND $overall_position_calc <= 50
    GROUP BY keyword, website
    HAVING COUNT(*) >= 1
    ORDER BY avg_overall_position DESC
    LIMIT 20
", $params);

// 4. Position distribution
$position_distribution = db_fetch_all("
    SELECT 
        CASE 
            WHEN $overall_position_calc <= 3 THEN 'Top 3'
            WHEN $overall_position_calc <= 10 THEN 'Top 10'
            WHEN $overall_position_calc <= 20 THEN 'Top 20'
            WHEN $overall_position_calc <= 50 THEN 'Top 50'
            ELSE 'Below 50'
        END as position_range,
        COUNT(*) as count
    FROM seo_rankings 
    WHERE $where_clause
    GROUP BY position_range
    ORDER BY 
        CASE position_range
            WHEN 'Top 3' THEN 1
            WHEN 'Top 10' THEN 2
            WHEN 'Top 20' THEN 3
            WHEN 'Top 50' THEN 4
            ELSE 5
        END
", $params);

// 5. Page distribution
$page_distribution = db_fetch_all("
    SELECT 
        CASE 
            WHEN page_number = 1 THEN 'Page 1'
            WHEN page_number = 2 THEN 'Page 2'
            WHEN page_number = 3 THEN 'Page 3'
            WHEN page_number <= 5 THEN 'Pages 4-5'
            ELSE 'Pages 6+'
        END as page_range,
        COUNT(*) as count
    FROM seo_rankings 
    WHERE $where_clause
    GROUP BY page_range
    ORDER BY 
        CASE page_range
            WHEN 'Page 1' THEN 1
            WHEN 'Page 2' THEN 2
            WHEN 'Page 3' THEN 3
            WHEN 'Pages 4-5' THEN 4
            ELSE 5
        END
", $params);

// 6. Website performance comparison
$website_performance = db_fetch_all("
    SELECT 
        website,
        COUNT(DISTINCT keyword) as total_keywords,
        AVG($overall_position_calc) as avg_overall_position,
        SUM(CASE WHEN $overall_position_calc <= 10 THEN 1 ELSE 0 END) as top_10_count,
        SUM(CASE WHEN $overall_position_calc <= 3 THEN 1 ELSE 0 END) as top_3_count,
        SUM(CASE WHEN page_number = 1 THEN 1 ELSE 0 END) as page_1_count
    FROM seo_rankings 
    WHERE $where_clause
    GROUP BY website
    ORDER BY avg_overall_position ASC
", $params);

// Get unique keywords for filter
$all_keywords = db_fetch_all("
    SELECT DISTINCT keyword 
    FROM seo_rankings 
    ORDER BY keyword
");
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">SEO Statistics</h1>
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
                    <label class="form-label">Website</label>
                    <select name="website" class="form-select">
                        <option value="">All Websites</option>
                        <?php foreach ($websites as $site): ?>
                            <option value="<?php echo $site; ?>" <?php echo $website_filter === $site ? 'selected' : ''; ?>>
                                <?php echo $site; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Keyword</label>
                    <input type="text" name="keyword" class="form-control" value="<?php echo htmlspecialchars($keyword_filter); ?>" 
                           list="keywordList" placeholder="Filter by keyword...">
                    <datalist id="keywordList">
                        <?php foreach ($all_keywords as $keyword_item): ?>
                            <option value="<?php echo htmlspecialchars($keyword_item['keyword']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <select name="date_range" class="form-select">
                        <option value="last_week" <?php echo $date_range === 'last_week' ? 'selected' : ''; ?>>Last Week</option>
                        <option value="last_4_weeks" <?php echo $date_range === 'last_4_weeks' ? 'selected' : ''; ?>>Last 4 Weeks</option>
                        <option value="last_month" <?php echo $date_range === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
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

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="card stat-card border-primary">
                <div class="card-body">
                    <h6 class="card-title">Total Keywords Tracked</h6>
                    <h3><?php 
                        $total_keywords = db_fetch_one("SELECT COUNT(DISTINCT keyword) as total FROM seo_rankings WHERE $where_clause", $params);
                        echo $total_keywords['total'] ?? 0; 
                    ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-success">
                <div class="card-body">
                    <h6 class="card-title">Avg Overall Position</h6>
                    <h3><?php 
                        $avg_pos = db_fetch_one("SELECT AVG($overall_position_calc) as avg FROM seo_rankings WHERE $where_clause", $params);
                        echo number_format($avg_pos['avg'] ?? 0, 1); 
                    ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-warning">
                <div class="card-body">
                    <h6 class="card-title">Page 1 Rankings</h6>
                    <h3><?php 
                        $page1 = db_fetch_one("SELECT COUNT(*) as total FROM seo_rankings WHERE $where_clause AND page_number = 1", $params);
                        echo $page1['total'] ?? 0; 
                    ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card border-info">
                <div class="card-body">
                    <h6 class="card-title">Top 10 Positions</h6>
                    <h3><?php 
                        $top10 = db_fetch_one("SELECT COUNT(*) as total FROM seo_rankings WHERE $where_clause AND $overall_position_calc <= 10", $params);
                        echo $top10['total'] ?? 0; 
                    ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6>Position Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="positionDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6>Page Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="pageDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6>Website Performance</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="websitePerformanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Keywords -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6>Top Performing Keywords</h6>
            <span class="badge bg-success">Best Rankings</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Keyword</th>
                            <th>Website</th>
                            <th>Avg Ranking</th>
                            <th>Best</th>
                            <th>Worst</th>
                            <th>Weeks</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_keywords as $keyword): 
                            $best_page = floor(($keyword['best_overall_position'] - 1) / 10) + 1;
                            $best_pos = (($keyword['best_overall_position'] - 1) % 10) + 1;
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($keyword['keyword']); ?></strong></td>
                                <td><?php echo htmlspecialchars($keyword['website']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $keyword['avg_overall_position'] <= 10 ? 'success' : ($keyword['avg_overall_position'] <= 30 ? 'warning' : 'danger'); ?>">
                                        Page <?php echo number_format($keyword['avg_page'], 1); ?>, Pos <?php echo number_format($keyword['avg_position_on_page'], 1); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">#<?php echo number_format($keyword['avg_overall_position'], 1); ?> overall</small>
                                </td>
                                <td>
                                    <small class="text-success">
                                        Page <?php echo $best_page; ?>, Pos <?php echo $best_pos; ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-danger">
                                        #<?php echo $keyword['worst_overall_position']; ?>
                                    </small>
                                </td>
                                <td><?php echo $keyword['weeks_tracked']; ?></td>
                                <td>
                                    <?php if ($keyword['avg_overall_position'] <= 3): ?>
                                        <span class="text-success">★ Excellent</span>
                                    <?php elseif ($keyword['avg_overall_position'] <= 10): ?>
                                        <span class="text-warning">● Good</span>
                                    <?php else: ?>
                                        <span class="text-danger">▲ Needs Work</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Page 1 Rankings -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6>Page 1 Rankings</h6>
            <span class="badge bg-success">First Page Results</span>
        </div>
        <div class="card-body">
            <?php
            $page1_rankings = db_fetch_all("
                SELECT 
                    keyword,
                    website,
                    AVG(position_on_page) as avg_position,
                    COUNT(*) as appearances,
                    MIN(position_on_page) as best_position
                FROM seo_rankings 
                WHERE $where_clause AND page_number = 1
                GROUP BY keyword, website
                ORDER BY avg_position ASC
                LIMIT 15
            ", $params);
            ?>
            
            <?php if (empty($page1_rankings)): ?>
                <p class="text-muted">No Page 1 rankings found for the selected period.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Keyword</th>
                                <th>Website</th>
                                <th>Avg Position</th>
                                <th>Best Position</th>
                                <th>Appearances</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($page1_rankings as $ranking): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ranking['keyword']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ranking['website']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $ranking['avg_position'] <= 3 ? 'success' : ($ranking['avg_position'] <= 5 ? 'warning' : 'info'); ?>">
                                            Position <?php echo number_format($ranking['avg_position'], 1); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">#<?php echo $ranking['best_position']; ?></span>
                                    </td>
                                    <td><?php echo $ranking['appearances']; ?></td>
                                    <td>
                                        <?php if ($ranking['avg_position'] <= 3): ?>
                                            <span class="text-success">★ Top 3</span>
                                        <?php elseif ($ranking['avg_position'] <= 5): ?>
                                            <span class="text-warning">● Top 5</span>
                                        <?php else: ?>
                                            <span class="text-info">▲ Page 1</span>
                                        <?php endif; ?>
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
// Position Distribution Chart
const distCtx = document.getElementById('positionDistributionChart').getContext('2d');
new Chart(distCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($position_distribution, 'position_range')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($position_distribution, 'count')); ?>,
            backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545', '#6c757d']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Page Distribution Chart
const pageCtx = document.getElementById('pageDistributionChart').getContext('2d');
new Chart(pageCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($page_distribution, 'page_range')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($page_distribution, 'count')); ?>,
            backgroundColor: ['#28a745', '#20c997', '#ffc107', '#fd7e14', '#6c757d']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Website Performance Chart
const websiteCtx = document.getElementById('websitePerformanceChart').getContext('2d');
new Chart(websiteCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($website_performance, 'website')); ?>,
        datasets: [{
            label: 'Avg Overall Position',
            data: <?php echo json_encode(array_column($website_performance, 'avg_overall_position')); ?>,
            backgroundColor: '#0d6efd',
            yAxisID: 'y'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                reverse: true, // Lower numbers (better rankings) at top
                title: {
                    display: true,
                    text: 'Average Position'
                }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>