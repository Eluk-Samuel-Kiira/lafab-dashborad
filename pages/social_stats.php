<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get filter parameters
$platform_filter = $_GET['platform'] ?? '';
$country_filter = $_GET['country'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');

// Build WHERE clause
$where_conditions = ["strftime('%Y', sms.stat_date) = ?"];
$params = [$year_filter];

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
$countries = db_fetch_all("SELECT DISTINCT country FROM social_media_daily_stats ORDER BY country");
$years = db_fetch_all("SELECT DISTINCT strftime('%Y', stat_date) as year FROM social_media_daily_stats ORDER BY year DESC");

// Get monthly summary
$monthly_summary = db_fetch_all("
    SELECT 
        strftime('%Y-%m', sms.stat_date) as month,
        p.name as platform,
        sms.country,
        MAX(sms.followers) as followers,
        MAX(sms.engagements) as engagements,
        (MAX(sms.engagements) * 1.0 / MAX(sms.followers) * 100) as engagement_rate
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
    GROUP BY strftime('%Y-%m', sms.stat_date), p.name, sms.country
    ORDER BY month DESC, p.name, sms.country
", $params);

// Get ALL platforms for the platform cards section (not filtered)
$all_platforms = db_fetch_all("SELECT name FROM social_media_platforms ORDER BY name");

// Calculate platform totals and growth for platform cards
$platform_cards_data = [];

foreach ($all_platforms as $platform) {
    $platform_name = $platform['name'];
    
    // Get latest month data for this platform (sum across all countries)
    $current_month_data = db_fetch_one("
        SELECT 
            strftime('%Y-%m', sms.stat_date) as month,
            SUM(sms.followers) as total_followers
        FROM social_media_daily_stats sms
        JOIN social_media_platforms p ON sms.platform_id = p.id
        WHERE p.name = ?
        AND strftime('%Y', sms.stat_date) = ?
        GROUP BY strftime('%Y-%m', sms.stat_date)
        ORDER BY month DESC
        LIMIT 1
    ", [$platform_name, $year_filter]);
    
    if ($current_month_data && $current_month_data['total_followers'] > 0) {
        $current_month = $current_month_data['month'];
        $current_followers = $current_month_data['total_followers'];
        
        // Get previous month data for this platform (sum across all countries)
        $prev_month_data = db_fetch_one("
            SELECT 
                strftime('%Y-%m', sms.stat_date) as month,
                SUM(sms.followers) as total_followers
            FROM social_media_daily_stats sms
            JOIN social_media_platforms p ON sms.platform_id = p.id
            WHERE p.name = ?
            AND strftime('%Y', sms.stat_date) = ?
            AND strftime('%Y-%m', sms.stat_date) < ?
            GROUP BY strftime('%Y-%m', sms.stat_date)
            ORDER BY month DESC
            LIMIT 1
        ", [$platform_name, $year_filter, $current_month]);
        
        $prev_followers = $prev_month_data['total_followers'] ?? 0;
        
        // Calculate growth percentage
        if ($prev_followers > 0) {
            $growth_percent = (($current_followers - $prev_followers) / $prev_followers) * 100;
        } else {
            $growth_percent = $current_followers > 0 ? 100 : 0;
        }
        
        $platform_cards_data[$platform_name] = [
            'current_followers' => $current_followers,
            'current_month' => $current_month,
            'prev_followers' => $prev_followers,
            'growth_percent' => $growth_percent,
            'has_data' => true
        ];
    } else {
        $platform_cards_data[$platform_name] = [
            'current_followers' => 0,
            'current_month' => null,
            'prev_followers' => 0,
            'growth_percent' => 0,
            'has_data' => false
        ];
    }
}

// Get overall latest month name for display
$latest_overall_month = db_fetch_one("
    SELECT strftime('%Y-%m', stat_date) as month 
    FROM social_media_daily_stats 
    WHERE strftime('%Y', stat_date) = ?
    ORDER BY stat_date DESC 
    LIMIT 1
", [$year_filter]);

$latest_month_name = $latest_overall_month ? date('F Y', strtotime($latest_overall_month['month'] . '-01')) : 'No data';

// Calculate overall statistics
$total_followers_all_platforms = 0;
$total_growth = 0;
$platforms_with_data = 0;
$highest_growth = ['platform' => '', 'percent' => -9999, 'followers' => 0];

foreach ($platform_cards_data as $platform_name => $data) {
    if ($data['has_data']) {
        $total_followers_all_platforms += $data['current_followers'];
        $total_growth += $data['growth_percent'];
        $platforms_with_data++;
        
        if ($data['growth_percent'] > $highest_growth['percent']) {
            $highest_growth['platform'] = $platform_name;
            $highest_growth['percent'] = $data['growth_percent'];
            $highest_growth['followers'] = $data['current_followers'];
        }
    }
}

$average_growth = $platforms_with_data > 0 ? $total_growth / $platforms_with_data : 0;

// Get data for line chart (last 6 months - total followers per platform)
$line_chart_data = db_fetch_all("
    SELECT 
        strftime('%Y-%m', sms.stat_date) as month,
        p.name as platform,
        SUM(sms.followers) as total_followers
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE strftime('%Y', sms.stat_date) = ?
    AND strftime('%Y-%m', sms.stat_date) IN (
        SELECT DISTINCT strftime('%Y-%m', stat_date) 
        FROM social_media_daily_stats 
        WHERE strftime('%Y', stat_date) = ?
        ORDER BY stat_date DESC 
        LIMIT 6
    )
    GROUP BY strftime('%Y-%m', sms.stat_date), p.name
    ORDER BY month ASC
", [$year_filter, $year_filter]);

// Organize line chart data by platform
$line_chart_by_platform = [];
$all_months = [];

foreach ($line_chart_data as $data) {
    $platform = $data['platform'];
    $month = $data['month'];
    
    if (!isset($line_chart_by_platform[$platform])) {
        $line_chart_by_platform[$platform] = [];
    }
    
    $line_chart_by_platform[$platform][$month] = $data['total_followers'];
    
    if (!in_array($month, $all_months)) {
        $all_months[] = $month;
    }
}

// Sort months chronologically
sort($all_months);
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Social Media Analytics Dashboard</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="social_stats_entry.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Monthly Data
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Platform</label>
                    <select name="platform" class="form-select">
                        <option value="">All Platforms</option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?php echo $platform['name']; ?>" 
                                <?php echo $platform_filter === $platform['name'] ? 'selected' : ''; ?>>
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
                            <option value="<?php echo $country['country']; ?>" 
                                <?php echo $country_filter === $country['country'] ? 'selected' : ''; ?>>
                                <?php echo $country['country']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year['year']; ?>" 
                                <?php echo $year_filter === $year['year'] ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <!-- Total Followers Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stat-card h-100 bg-primary text-white">
                <div class="card-body text-center">
                    <h6 class="card-title text-white-50">Total Followers</h6>
                    <h2><?php echo number_format($total_followers_all_platforms); ?></h2>
                    <small class="text-white-50">Latest Month: <?php echo $latest_month_name; ?></small>
                </div>
            </div>
        </div>
        
        <!-- Average Growth Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Average Growth</h6>
                    <h2 class="text-<?php echo $average_growth >= 0 ? 'success' : 'danger'; ?>">
                        <?php echo ($average_growth >= 0 ? '+' : '') . number_format($average_growth, 1); ?>%
                    </h2>
                    <small class="text-muted">Across all platforms</small>
                </div>
            </div>
        </div>
        
        <!-- Latest Month Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Current Month</h6>
                    <h4 class="text-info"><?php echo $latest_month_name; ?></h4>
                    <small class="text-muted">Latest data period</small>
                </div>
            </div>
        </div>
        
        <!-- Top Growth Platform Card -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Highest Growth</h6>
                    <?php if ($highest_growth['platform']): ?>
                        <h4 class="text-success">
                            <?php echo ($highest_growth['percent'] >= 0 ? '+' : '') . number_format($highest_growth['percent'], 1); ?>%
                        </h4>
                        <small class="text-muted">
                            <?php echo $highest_growth['platform']; ?><br>
                            <?php echo number_format($highest_growth['followers']); ?> followers
                        </small>
                    <?php else: ?>
                        <p class="text-muted">No growth data</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Performance Cards -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Platform Performance (Latest Month: <?php echo $latest_month_name; ?>)</h6>
                    <small class="text-muted">Showing total followers across all countries and growth from previous month</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($platform_cards_data as $platform_name => $data): 
                            if (!$data['has_data']) continue;
                            
                            $growth_class = $data['growth_percent'] >= 10 ? 'success' : 
                                          ($data['growth_percent'] >= 0 ? 'warning' : 'danger');
                            $growth_icon = $data['growth_percent'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                        ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                                <div class="card stat-card h-100">
                                    <div class="card-body text-center">
                                        <!-- Platform Name -->
                                        <h6 class="card-title text-primary">
                                            <i class="fas fa-chart-line me-1"></i>
                                            <?php echo $platform_name; ?>
                                        </h6>
                                        
                                        <!-- Total Followers (Sum across all countries) -->
                                        <h3 class="text-dark mb-2">
                                            <?php echo number_format($data['current_followers']); ?>
                                        </h3>
                                        <small class="text-muted">Total Followers</small>
                                        
                                        <!-- Growth Percentage -->
                                        <div class="mt-3">
                                            <span class="badge bg-<?php echo $growth_class; ?> p-2">
                                                <i class="fas <?php echo $growth_icon; ?> me-1"></i>
                                                <?php echo ($data['growth_percent'] >= 0 ? '+' : '') . number_format($data['growth_percent'], 1); ?>%
                                            </span>
                                            <small class="text-muted d-block mt-1">
                                                Monthly Growth
                                            </small>
                                        </div>
                                        
                                        <!-- Previous Month Comparison -->
                                        <div class="mt-3">
                                            <div class="progress" style="height: 8px;">
                                                <?php 
                                                $max_value = max($data['current_followers'], $data['prev_followers']);
                                                if ($max_value > 0) {
                                                    $current_width = ($data['current_followers'] / $max_value) * 100;
                                                    $prev_width = ($data['prev_followers'] / $max_value) * 100;
                                                } else {
                                                    $current_width = 0;
                                                    $prev_width = 0;
                                                }
                                                ?>
                                                <div class="progress-bar bg-secondary" 
                                                     style="width: <?php echo $prev_width; ?>%"
                                                     title="Previous Month: <?php echo number_format($data['prev_followers']); ?>">
                                                </div>
                                                <div class="progress-bar bg-primary" 
                                                     style="width: <?php echo $current_width - $prev_width; ?>%"
                                                     title="Growth: <?php echo number_format($data['current_followers'] - $data['prev_followers']); ?>">
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                Prev: <?php echo number_format($data['prev_followers']); ?> 
                                                | 
                                                <strong>Now: <?php echo number_format($data['current_followers']); ?></strong>
                                            </small>
                                        </div>
                                        
                                        <!-- Month Display -->
                                        <?php if ($data['current_month']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="far fa-calendar-alt me-1"></i>
                                                    <?php echo date('F Y', strtotime($data['current_month'] . '-01')); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php 
                    $platforms_without_data = array_filter($platform_cards_data, function($data) {
                        return !$data['has_data'];
                    });
                    
                    if (count($platforms_without_data) > 0): ?>
                        <div class="mt-3">
                            <h6 class="text-muted">Platforms without data for <?php echo $year_filter; ?>:</h6>
                            <?php foreach ($platforms_without_data as $platform_name => $data): ?>
                                <span class="badge bg-light text-muted border me-2 mb-1">
                                    <?php echo $platform_name; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <!-- Monthly Growth Trends (Line Chart) -->
        <div class="col-lg-12 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">6-Month Follower Trends (Total Followers per Platform)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 400px;">
                        <canvas id="growthTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Data Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Detailed Monthly Data</h6>
        </div>
        <div class="card-body">
            <?php if (empty($monthly_summary)): ?>
                <p class="text-muted text-center py-4">No data found for selected filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Platform</th>
                                <th>Country</th>
                                <th>Followers</th>
                                <th>Engagements</th>
                                <th>Engagement Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_summary as $entry): 
                                $month_name = date('F Y', strtotime($entry['month'] . '-01'));
                            ?>
                                <tr>
                                    <td><strong><?php echo $month_name; ?></strong></td>
                                    <td><?php echo htmlspecialchars($entry['platform']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['country']); ?></td>
                                    <td><?php echo number_format($entry['followers']); ?></td>
                                    <td><?php echo number_format($entry['engagements']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $entry['engagement_rate'] >= 5 ? 'success' : 
                                                ($entry['engagement_rate'] >= 2 ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo number_format($entry['engagement_rate'], 2); ?>%
                                        </span>
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

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Platform colors for charts
const platformColors = {
    'Facebook': '#1877F2',
    'Instagram': '#E4405F',
    'Twitter': '#1DA1F2',
    'LinkedIn': '#0A66C2',
    'TikTok': '#000000',
    'Telegram': '#0088CC',
    'WhatsApp': '#25D366',
    'YouTube': '#FF0000'
};

// Prepare data for Growth Trends Chart (Line Chart)
const allMonths = <?php echo json_encode($all_months); ?>;
const formattedMonths = allMonths.map(month => {
    const [year, monthNum] = month.split('-');
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${monthNames[parseInt(monthNum) - 1]} '${year.toString().slice(-2)}`;
});

const lineChartByPlatform = <?php echo json_encode($line_chart_by_platform); ?>;

const growthTrendsData = {
    labels: formattedMonths,
    datasets: [
        <?php 
        if (!empty($line_chart_by_platform)):
            foreach ($line_chart_by_platform as $platform_name => $platform_data): 
                if (Object.keys(platform_data).length > 0): // Only include platforms with data
        ?>
        {
            label: '<?php echo $platform_name; ?>',
            data: [
                <?php 
                foreach ($all_months as $month) {
                    if (platform_data[month] !== undefined) {
                        echo platform_data[month] . ',';
                    } else {
                        echo 'null,';
                    }
                }
                ?>
            ],
            borderColor: platformColors['<?php echo $platform_name; ?>'] || '#666666',
            backgroundColor: (platformColors['<?php echo $platform_name; ?>'] || '#666666') + '20',
            borderWidth: 3,
            fill: false,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: platformColors['<?php echo $platform_name; ?>'] || '#666666'
        },
        <?php 
                endif;
            endforeach;
        endif; 
        ?>
    ]
};

// Growth Trends Chart (Line Chart)
if (allMonths.length > 1 && Object.keys(lineChartByPlatform).length > 0) {
    const growthTrendsCtx = document.getElementById('growthTrendsChart').getContext('2d');
    new Chart(growthTrendsCtx, {
        type: 'line',
        data: growthTrendsData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        usePointStyle: true
                    }
                },
                title: {
                    display: true,
                    text: 'Total Followers Trend (Last 6 Months)',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat().format(context.parsed.y) + ' followers';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Total Followers'
                    },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) {
                                return (value / 1000000).toFixed(1) + 'M';
                            } else if (value >= 1000) {
                                return (value / 1000).toFixed(0) + 'K';
                            }
                            return value;
                        }
                    },
                    grid: {
                        borderDash: [3, 3]
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            elements: {
                line: {
                    cubicInterpolationMode: 'monotone'
                }
            }
        }
    });
} else {
    document.getElementById('growthTrendsChart').closest('.card-body').innerHTML = 
        '<p class="text-muted text-center py-4">Not enough data for trend analysis (need at least 2 months of data)</p>';
}
</script>

<style>
.chart-container {
    position: relative;
    width: 100%;
}
.stat-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-top: 4px solid #007bff;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}
.card-header {
    background: rgba(0,0,0,0.03);
    border-bottom: 1px solid rgba(0,0,0,0.125);
}
.badge {
    font-size: 0.9em;
    padding: 8px 12px;
    font-weight: 600;
}
.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}
.progress {
    border-radius: 10px;
    overflow: hidden;
}
.progress-bar {
    border-radius: 10px;
}
.bg-success {
    background-color: #28a745 !important;
}
.bg-warning {
    background-color: #ffc107 !important;
}
.bg-danger {
    background-color: #dc3545 !important;
}
.text-success {
    color: #28a745 !important;
}
.text-warning {
    color: #ffc107 !important;
}
.text-danger {
    color: #dc3545 !important;
}
</style>

<?php require_once '../includes/footer.php'; ?>