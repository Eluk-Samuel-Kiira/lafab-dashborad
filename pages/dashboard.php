<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get filter parameters
$country_filter = $_GET['country'] ?? '';
$date_filter = $_GET['date_range'] ?? 'month';

// Countries for filter
$countries = ['Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Zambia'];

// Calculate date ranges based on filter
$today = date('Y-m-d');
switch ($date_filter) {
    case 'today':
        $start_date = $today;
        $end_date = $today;
        $period_label = 'Today';
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = $today;
        $period_label = 'This Week';
        break;
    case 'quarter':
        $start_date = date('Y-m-d', strtotime(date('Y').'-'.((ceil(date('n')/3)-1)*3+1).'-01'));
        $end_date = $today;
        $period_label = 'This Quarter';
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = $today;
        $period_label = 'This Year';
        break;
    default: // month
        $start_date = date('Y-m-01');
        $end_date = $today;
        $period_label = 'This Month';
        break;
}

// Function to get job counts with filters
function getJobCount($start_date, $end_date = null, $country = '') {
    $sql = "SELECT website, SUM(job_count) as total FROM job_postings WHERE post_date >= ?";
    $params = [$start_date];
    
    if ($end_date) {
        $sql .= " AND post_date <= ?";
        $params[] = $end_date;
    }
    
    if ($country) {
        $sql .= " AND website LIKE ?";
        $params[] = "%$country%";
    }
    
    $sql .= " GROUP BY website";
    return db_fetch_all($sql, $params);
}

// Function to get total jobs count
function getTotalJobs($start_date, $end_date = null, $country = '') {
    $sql = "SELECT SUM(job_count) as total FROM job_postings WHERE post_date >= ?";
    $params = [$start_date];
    
    if ($end_date) {
        $sql .= " AND post_date <= ?";
        $params[] = $end_date;
    }
    
    if ($country) {
        $sql .= " AND website LIKE ?";
        $params[] = "%$country%";
    }
    
    $result = db_fetch_one($sql, $params);
    return $result['total'] ?? 0;
}

// Get all counts with filters
$daily_total = getTotalJobs($today, $today, $country_filter);
$weekly_total = getTotalJobs(date('Y-m-d', strtotime('monday this week')), $today, $country_filter);
$monthly_total = getTotalJobs(date('Y-m-01'), $today, $country_filter);
$quarterly_total = getTotalJobs(date('Y-m-d', strtotime(date('Y').'-'.((ceil(date('n')/3)-1)*3+1).'-01')), $today, $country_filter);
$yearly_total = getTotalJobs(date('Y-01-01'), $today, $country_filter);

// Get filtered data for charts
$filtered_jobs = getJobCount($start_date, $end_date, $country_filter);

// Get top posters with filter
$poster_sql = "SELECT poster_name, SUM(job_count) as total_jobs FROM job_postings WHERE post_date >= ?";
$poster_params = [$start_date];

if ($country_filter) {
    $poster_sql .= " AND website LIKE ?";
    $poster_params[] = "%$country_filter%";
}

$poster_sql .= " GROUP BY poster_name ORDER BY total_jobs DESC LIMIT 5";
$poster_stats = db_fetch_all($poster_sql, $poster_params);

// Get growth data
$previous_start_date = date('Y-m-d', strtotime($start_date . ' -1 month'));
$previous_end_date = date('Y-m-d', strtotime($end_date . ' -1 month'));
$previous_total = getTotalJobs($previous_start_date, $previous_end_date, $country_filter);
$growth_percentage = 0;

if ($previous_total > 0) {
    $growth_percentage = (($monthly_total - $previous_total) / $previous_total) * 100;
}

// Get daily trends for line chart
$daily_trends_sql = "SELECT post_date, SUM(job_count) as daily_total FROM job_postings WHERE post_date BETWEEN ? AND ?";
$daily_trends_params = [$start_date, $end_date];

if ($country_filter) {
    $daily_trends_sql .= " AND website LIKE ?";
    $daily_trends_params[] = "%$country_filter%";
}

$daily_trends_sql .= " GROUP BY post_date ORDER BY post_date ASC";
$daily_trends = db_fetch_all($daily_trends_sql, $daily_trends_params);

// Get weekly trends
$weekly_trends_sql = "SELECT 
    date(post_date, 'weekday 0', '-6 days') as week_start,
    SUM(job_count) as weekly_total 
    FROM job_postings 
    WHERE post_date BETWEEN ? AND ?";
$weekly_trends_params = [date('Y-m-d', strtotime($start_date . ' -6 months')), $end_date];

if ($country_filter) {
    $weekly_trends_sql .= " AND website LIKE ?";
    $weekly_trends_params[] = "%$country_filter%";
}

$weekly_trends_sql .= " GROUP BY week_start ORDER BY week_start ASC LIMIT 12";
$weekly_trends = db_fetch_all($weekly_trends_sql, $weekly_trends_params);

// Get recent posts with ALL entries (including duplicates)
$recent_sql = "SELECT * FROM job_postings WHERE 1=1";
$recent_params = [];

if ($country_filter) {
    $recent_sql .= " AND website LIKE ?";
    $recent_params[] = "%$country_filter%";
}

$recent_sql .= " ORDER BY post_date DESC, created_at DESC LIMIT 20";
$recent_posts = db_fetch_all($recent_sql, $recent_params);

// Get country breakdown
$country_breakdown_sql = "SELECT 
    CASE 
        WHEN website LIKE '%uganda%' THEN 'Uganda'
        WHEN website LIKE '%kenya%' THEN 'Kenya' 
        WHEN website LIKE '%tanzania%' THEN 'Tanzania'
        WHEN website LIKE '%rwanda%' THEN 'Rwanda'
        WHEN website LIKE '%zambia%' THEN 'Zambia'
        ELSE 'Other'
    END as country,
    SUM(job_count) as total_jobs,
    COUNT(*) as post_count
    FROM job_postings 
    WHERE post_date BETWEEN ? AND ?";
$country_breakdown_params = [$start_date, $end_date];

if ($country_filter) {
    $country_breakdown_sql .= " AND website LIKE ?";
    $country_breakdown_params[] = "%$country_filter%";
}

$country_breakdown_sql .= " GROUP BY country ORDER BY total_jobs DESC";
$country_breakdown = db_fetch_all($country_breakdown_sql, $country_breakdown_params);
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Job Posts Dashboard</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="add_job.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus-circle"></i> Add Job Post
                </a>
                <a href="jobs_dashboard.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync"></i> Refresh
                </a>
            </div>
            <span class="text-muted"><?php echo date('F j, Y'); ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-primary">
                    <i class="fas fa-filter me-2"></i>Dashboard Filters
                </h6>
                <span class="badge bg-light text-dark"><?php echo $period_label; ?></span>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-semibold">Country</label>
                    <select name="country" class="form-select border-0 bg-light">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo $country; ?>" <?php echo $country_filter === $country ? 'selected' : ''; ?>>
                                <?php echo $country; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-semibold">Date Range</label>
                    <select name="date_range" class="form-select border-0 bg-light">
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="quarter" <?php echo $date_filter === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                        <option value="year" <?php echo $date_filter === 'year' ? 'selected' : ''; ?>>This Year</option>
                    </select>
                </div>
                <div class="col-lg-4 col-md-12">
                    <label class="form-label fw-semibold invisible">Apply</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-chart-bar me-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-calendar-day fa-2x text-primary"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $daily_total; ?></h4>
                    <p class="text-muted small mb-0">Today</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-calendar-week fa-2x text-success"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $weekly_total; ?></h4>
                    <p class="text-muted small mb-0">This Week</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-calendar-alt fa-2x text-info"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $monthly_total; ?></h4>
                    <p class="text-muted small mb-0">This Month</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-chart-pie fa-2x text-warning"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $quarterly_total; ?></h4>
                    <p class="text-muted small mb-0">This Quarter</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-calendar fa-2x text-danger"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?php echo $yearly_total; ?></h4>
                    <p class="text-muted small mb-0">This Year</p>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body text-center p-3">
                    <div class="stat-icon mb-2">
                        <i class="fas fa-chart-line fa-2x <?php echo $growth_percentage >= 0 ? 'text-success' : 'text-danger'; ?>"></i>
                    </div>
                    <h4 class="fw-bold <?php echo $growth_percentage >= 0 ? 'text-success' : 'text-danger'; ?> mb-1">
                        <?php echo number_format($growth_percentage, 1); ?>%
                    </h4>
                    <p class="text-muted small mb-0">Monthly Growth</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row mb-4">
        <!-- Jobs by Website -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-globe me-2"></i>Jobs by Website
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="websiteChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Posters -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-users me-2"></i>Top Posters
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="posterChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Country Breakdown -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-flag me-2"></i>Country Breakdown
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="countryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 - Trends -->
    <div class="row mb-4">
        <!-- Daily Trends -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-chart-line me-2"></i>Daily Posting Trends
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="dailyTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Trends -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 text-primary">
                        <i class="fas fa-chart-bar me-2"></i>Weekly Trends (6 Months)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="weeklyTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity with Live Search -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-primary">
                <i class="fas fa-history me-2"></i>Recent Job Posts
            </h6>
            <div class="d-flex align-items-center">
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-light border-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" id="searchTable" class="form-control border-0 bg-light" placeholder="Search posts...">
                </div>
                <span class="badge bg-primary ms-2" id="resultCount"><?php echo count($recent_posts); ?> entries</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($recent_posts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No job posts found for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover" id="postsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Website</th>
                                <th>Poster</th>
                                <th>Jobs</th>
                                <th>Country</th>
                                <th>Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_posts as $post): 
                                $country = 'Other';
                                foreach ($countries as $c) {
                                    if (stripos($post['website'], $c) !== false) {
                                        $country = $c;
                                        break;
                                    }
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('M j, Y', strtotime($post['post_date'])); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($post['website']); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user me-2 text-muted"></i>
                                            <?php echo htmlspecialchars($post['poster_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $post['job_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $country; ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo date('M j g:i A', strtotime($post['created_at'])); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary Stats -->
                <div class="row mt-3">
                    <div class="col-md-3 col-6">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <h6 class="mb-0 text-primary"><?php echo count($recent_posts); ?></h6>
                                <small class="text-muted">Total Entries</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <h6 class="mb-0 text-success">
                                    <?php echo array_sum(array_column($recent_posts, 'job_count')); ?>
                                </h6>
                                <small class="text-muted">Total Jobs</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <h6 class="mb-0 text-warning">
                                    <?php echo count(array_unique(array_column($recent_posts, 'poster_name'))); ?>
                                </h6>
                                <small class="text-muted">Unique Posters</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <h6 class="mb-0 text-info">
                                    <?php echo count(array_unique(array_column($recent_posts, 'website'))); ?>
                                </h6>
                                <small class="text-muted">Websites</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Website distribution chart
const websiteCtx = document.getElementById('websiteChart').getContext('2d');
new Chart(websiteCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($filtered_jobs, 'website')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($filtered_jobs, 'total')); ?>,
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
                    padding: 10
                }
            }
        },
        cutout: '60%'
    }
});

// Top posters chart
const posterCtx = document.getElementById('posterChart').getContext('2d');
new Chart(posterCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($poster_stats, 'poster_name')); ?>,
        datasets: [{
            label: 'Jobs Posted',
            data: <?php echo json_encode(array_column($poster_stats, 'total_jobs')); ?>,
            backgroundColor: '#0d6efd'
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
                grid: {
                    display: false
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Country breakdown chart
const countryCtx = document.getElementById('countryChart').getContext('2d');
new Chart(countryCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($country_breakdown, 'country')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($country_breakdown, 'total_jobs')); ?>,
            backgroundColor: ['#dc3545', '#198754', '#0d6efd', '#ffc107', '#6f42c1', '#6c757d']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Daily trends chart
const dailyTrendsCtx = document.getElementById('dailyTrendsChart').getContext('2d');
new Chart(dailyTrendsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($day) { 
            return date('M j', strtotime($day['post_date'])); 
        }, $daily_trends)); ?>,
        datasets: [{
            label: 'Daily Jobs',
            data: <?php echo json_encode(array_column($daily_trends, 'daily_total')); ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    borderDash: [2, 4]
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Weekly trends chart
const weeklyTrendsCtx = document.getElementById('weeklyTrendsChart').getContext('2d');
new Chart(weeklyTrendsCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($week) { 
            return date('M j', strtotime($week['week_start'])); 
        }, $weekly_trends)); ?>,
        datasets: [{
            label: 'Weekly Jobs',
            data: <?php echo json_encode(array_column($weekly_trends, 'weekly_total')); ?>,
            backgroundColor: '#20c997'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    borderDash: [2, 4]
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Live search functionality
document.getElementById('searchTable').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const table = document.getElementById('postsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;

    for (let row of rows) {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }

    document.getElementById('resultCount').textContent = visibleCount + ' entries';
});

// Add hover effects and better styling
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<style>
.stat-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
}

.stat-card:nth-child(1) { border-left-color: #0d6efd; }
.stat-card:nth-child(2) { border-left-color: #198754; }
.stat-card:nth-child(3) { border-left-color: #0dcaf0; }
.stat-card:nth-child(4) { border-left-color: #ffc107; }
.stat-card:nth-child(5) { border-left-color: #dc3545; }
.stat-card:nth-child(6) { border-left-color: #6f42c1; }

.stat-icon {
    opacity: 0.8;
}

.chart-container {
    position: relative;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
    transform: translateX(2px);
    transition: all 0.2s ease;
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.bg-light {
    background-color: #f8f9fa !important;
}
</style>

<?php require_once '../includes/footer.php'; ?>