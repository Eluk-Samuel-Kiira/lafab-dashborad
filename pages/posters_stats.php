<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Helper function to determine performance tier
function getPerformanceTier($jobs_per_day, $consistency_score) {
    if ($jobs_per_day >= 20 && $consistency_score >= 80) return 'elite';
    if ($jobs_per_day >= 15 && $consistency_score >= 70) return 'excellent';
    if ($jobs_per_day >= 10 && $consistency_score >= 60) return 'good';
    if ($jobs_per_day >= 5 && $consistency_score >= 50) return 'average';
    return 'needs_improvement';
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$country_filter = $_GET['country'] ?? '';
$poster_filter = $_GET['poster'] ?? '';

// Countries and posters for filters
$countries = ['Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Zambia'];
$all_posters = db_fetch_all("SELECT name FROM posters WHERE is_active = 1 ORDER BY name");

// Build WHERE conditions for queries
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
        MIN(j.job_count) as min_jobs_per_post,
        MAX(j.job_count) as max_jobs_per_post,
        MIN(j.post_date) as first_post,
        MAX(j.post_date) as last_post,
        COUNT(DISTINCT j.website) as websites_worked_on,
        (SELECT website FROM job_postings WHERE poster_name = p.name GROUP BY website ORDER BY SUM(job_count) DESC LIMIT 1) as top_website
    FROM posters p
    LEFT JOIN job_postings j ON p.name = j.poster_name AND $where_clause
    WHERE p.is_active = 1
    GROUP BY p.id, p.name
    ORDER BY total_jobs DESC
";

$poster_stats = db_fetch_all($sql, $params);

// Get performance trends (last 3 months for comparison)
$trend_start = date('Y-m-d', strtotime($start_date . ' -3 months'));
$trend_data = db_fetch_all("
    SELECT 
        p.name as poster_name,
        strftime('%Y-%m', j.post_date) as month,
        SUM(j.job_count) as monthly_jobs
    FROM posters p
    JOIN job_postings j ON p.name = j.poster_name 
    WHERE j.post_date BETWEEN ? AND ?
    GROUP BY p.name, strftime('%Y-%m', j.post_date)
    ORDER BY p.name, month
", [$trend_start, $end_date]);

// Get country distribution for each poster
$country_distribution = db_fetch_all("
    SELECT 
        p.name as poster_name,
        CASE 
            WHEN j.website LIKE '%uganda%' THEN 'Uganda'
            WHEN j.website LIKE '%kenya%' THEN 'Kenya' 
            WHEN j.website LIKE '%tanzania%' THEN 'Tanzania'
            WHEN j.website LIKE '%rwanda%' THEN 'Rwanda'
            WHEN j.website LIKE '%zambia%' THEN 'Zambia'
            ELSE 'Other'
        END as country,
        SUM(j.job_count) as jobs
    FROM posters p
    JOIN job_postings j ON p.name = j.poster_name 
    WHERE $where_clause
    GROUP BY p.name, country
    ORDER BY p.name, jobs DESC
", $params);

// Calculate performance metrics
$performance_metrics = [];
foreach ($poster_stats as $stat) {
    if ($stat['total_jobs'] > 0 && $stat['first_post'] && $stat['last_post']) {
        $days_active = max(1, (strtotime($stat['last_post']) - strtotime($stat['first_post'])) / (60 * 60 * 24) + 1);
        $jobs_per_day = $stat['total_jobs'] / $days_active;
        $consistency_score = ($stat['posting_days'] / $days_active) * 100;
        
        $performance_metrics[$stat['poster_name']] = [
            'jobs_per_day' => $jobs_per_day,
            'consistency_score' => $consistency_score,
            'efficiency_ratio' => $stat['avg_jobs_per_post'],
            'performance_tier' => getPerformanceTier($jobs_per_day, $consistency_score)
        ];
    } else {
        $performance_metrics[$stat['poster_name']] = [
            'jobs_per_day' => 0,
            'consistency_score' => 0,
            'efficiency_ratio' => 0,
            'performance_tier' => 'needs_improvement'
        ];
    }
}
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Poster Performance Analytics</h1>
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
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <label class="form-label">Poster</label>
                    <select name="poster" class="form-select">
                        <option value="">All Posters</option>
                        <?php foreach ($all_posters as $poster): ?>
                            <option value="<?php echo $poster['name']; ?>" <?php echo $poster_filter === $poster['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($poster['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Performance Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Posters</h6>
                    <h3 class="text-primary"><?php echo count($poster_stats); ?></h3>
                    <small class="text-muted">Active Team</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Total Jobs</h6>
                    <h3 class="text-success"><?php echo array_sum(array_column($poster_stats, 'total_jobs')); ?></h3>
                    <small class="text-muted">All Posters</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Avg Jobs/Poster</h6>
                    <h3 class="text-info"><?php echo count($poster_stats) > 0 ? number_format(array_sum(array_column($poster_stats, 'total_jobs')) / count($poster_stats), 1) : 0; ?></h3>
                    <small class="text-muted">Per Poster</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Top Performer</h6>
                    <h3 class="text-warning" style="font-size: 1.1rem;"><?php echo !empty($poster_stats) ? (strlen($poster_stats[0]['poster_name']) > 12 ? substr($poster_stats[0]['poster_name'], 0, 12) . '...' : $poster_stats[0]['poster_name']) : 'N/A'; ?></h3>
                    <small class="text-muted">Most Jobs</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Avg Consistency</h6>
                    <h3 class="text-danger"><?php echo !empty($performance_metrics) ? number_format(array_sum(array_column($performance_metrics, 'consistency_score')) / count($performance_metrics), 1) . '%' : '0%'; ?></h3>
                    <small class="text-muted">Team Average</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <h6 class="card-title">Active Days</h6>
                    <h3 class="text-dark"><?php echo !empty($poster_stats) ? max(array_column($poster_stats, 'posting_days')) : 0; ?></h3>
                    <small class="text-muted">Most Active</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Performance Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container bar">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Country Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container doughnut">
                        <canvas id="countryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Tiers -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">Performance Tiers Analysis</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php
                $tiers = [
                    'elite' => ['count' => 0, 'color' => 'success', 'jobs' => 0],
                    'excellent' => ['count' => 0, 'color' => 'info', 'jobs' => 0],
                    'good' => ['count' => 0, 'color' => 'warning', 'jobs' => 0],
                    'average' => ['count' => 0, 'color' => 'primary', 'jobs' => 0],
                    'needs_improvement' => ['count' => 0, 'color' => 'danger', 'jobs' => 0]
                ];
                
                foreach ($performance_metrics as $poster_name => $metrics) {
                    $tier = $metrics['performance_tier'];
                    $tiers[$tier]['count']++;
                    // Find jobs for this poster
                    foreach ($poster_stats as $stat) {
                        if ($stat['poster_name'] === $poster_name) {
                            $tiers[$tier]['jobs'] += $stat['total_jobs'];
                            break;
                        }
                    }
                }
                ?>
                
                <?php foreach ($tiers as $tier => $data): ?>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="card border-<?php echo $data['color']; ?> h-100">
                            <div class="card-body text-center">
                                <h4 class="text-<?php echo $data['color']; ?>"><?php echo $data['count']; ?></h4>
                                <h6 class="text-capitalize text-<?php echo $data['color']; ?>" style="font-size: 0.9rem;">
                                    <?php echo str_replace('_', ' ', $tier); ?>
                                </h6>
                                <small class="text-muted"><?php echo $data['jobs']; ?> Jobs</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Detailed Performance Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Detailed Poster Performance (<?php echo $start_date . ' to ' . $end_date; ?>)</h6>
            <span class="badge bg-primary"><?php echo count($poster_stats); ?> Posters</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Poster Name</th>
                            <th>Performance Tier</th>
                            <th>Total Jobs</th>
                            <th>Posts</th>
                            <th>Avg Jobs/Post</th>
                            <th>Active Days</th>
                            <th>Consistency</th>
                            <th>Websites</th>
                            <th>Top Country</th>
                            <th>First Post</th>
                            <th>Last Post</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($poster_stats as $stat): 
                            $metrics = $performance_metrics[$stat['poster_name']] ?? [];
                            $tier = $metrics['performance_tier'] ?? 'needs_improvement';
                            $tier_colors = [
                                'elite' => 'success',
                                'excellent' => 'info', 
                                'good' => 'warning',
                                'average' => 'primary',
                                'needs_improvement' => 'danger'
                            ];
                            
                            // Get country distribution for this poster
                            $poster_countries = array_filter($country_distribution, function($item) use ($stat) {
                                return $item['poster_name'] === $stat['poster_name'];
                            });
                            $top_country = !empty($poster_countries) ? current($poster_countries)['country'] : 'N/A';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($stat['poster_name']); ?></strong>
                                    <?php if ($stat['top_website']): ?>
                                        <br><small class="text-muted">Top: <?php echo htmlspecialchars($stat['top_website']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $tier_colors[$tier]; ?> text-capitalize">
                                        <?php echo str_replace('_', ' ', $tier); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-primary"><?php echo $stat['total_jobs'] ?? 0; ?></strong>
                                </td>
                                <td><?php echo $stat['total_posts'] ?? 0; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($stat['avg_jobs_per_post'] ?? 0) >= 10 ? 'success' : 'warning'; ?>">
                                        <?php echo number_format($stat['avg_jobs_per_post'] ?? 0, 1); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $stat['posting_days'] ?? 0; ?>
                                    <?php if ($stat['first_post'] && $stat['last_post']): ?>
                                        <br><small class="text-muted">
                                            <?php echo round((strtotime($stat['last_post']) - strtotime($stat['first_post'])) / (60 * 60 * 24) + 1); ?> days
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($metrics['consistency_score'])): ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $metrics['consistency_score'] >= 70 ? 'success' : ($metrics['consistency_score'] >= 50 ? 'warning' : 'danger'); ?>" 
                                                 style="width: <?php echo $metrics['consistency_score']; ?>%">
                                                <?php echo number_format($metrics['consistency_score'], 0); ?>%
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $stat['websites_worked_on'] ?? 0; ?></span>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo $top_country; ?></small>
                                </td>
                                <td>
                                    <small><?php echo $stat['first_post'] ? date('M j', strtotime($stat['first_post'])) : 'N/A'; ?></small>
                                </td>
                                <td>
                                    <small><?php echo $stat['last_post'] ? date('M j', strtotime($stat['last_post'])) : 'N/A'; ?></small>
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
// Performance Distribution Chart
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
new Chart(performanceCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($poster_stats, 'poster_name')); ?>,
        datasets: [{
            label: 'Total Jobs',
            data: <?php echo json_encode(array_column($poster_stats, 'total_jobs')); ?>,
            backgroundColor: '#0d6efd',
            borderColor: '#0a58ca',
            borderWidth: 1
        }, {
            label: 'Active Days',
            data: <?php echo json_encode(array_column($poster_stats, 'posting_days')); ?>,
            backgroundColor: '#6c757d',
            borderColor: '#5a6268',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    font: {
                        size: 11
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: 11
                    },
                    maxRotation: 45
                }
            }
        }
    }
});

// Country Distribution Chart
const countryCtx = document.getElementById('countryChart').getContext('2d');

// Prepare country data
const countryData = {};
<?php foreach ($country_distribution as $dist): ?>
    if (!countryData['<?php echo $dist['country']; ?>']) {
        countryData['<?php echo $dist['country']; ?>'] = 0;
    }
    countryData['<?php echo $dist['country']; ?>'] += <?php echo $dist['jobs']; ?>;
<?php endforeach; ?>

new Chart(countryCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(countryData),
        datasets: [{
            data: Object.values(countryData),
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF8A65']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    font: {
                        size: 11
                    }
                }
            }
        },
        cutout: '50%'
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>