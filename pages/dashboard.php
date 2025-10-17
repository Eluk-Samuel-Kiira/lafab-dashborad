<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get current date ranges
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$month_start = date('Y-m-01');
$quarter_start = date('Y-m-d', strtotime(date('Y').'-'.((ceil(date('n')/3)-1)*3+1).'-01'));
$year_start = date('Y-01-01');

// Function to get job counts
function getJobCount($start_date, $end_date = null) {
    $sql = "SELECT website, SUM(job_count) as total FROM job_postings WHERE post_date >= ?";
    $params = [$start_date];
    
    if ($end_date) {
        $sql .= " AND post_date <= ?";
        $params[] = $end_date;
    }
    
    $sql .= " GROUP BY website";
    return db_fetch_all($sql, $params);
}

// Get all counts
$daily_jobs = getJobCount($today);
$weekly_jobs = getJobCount($week_start);
$monthly_jobs = getJobCount($month_start);
$quarterly_jobs = getJobCount($quarter_start);
$yearly_jobs = getJobCount($year_start);

// Calculate totals
$daily_total = array_sum(array_column($daily_jobs, 'total'));
$weekly_total = array_sum(array_column($weekly_jobs, 'total'));
$monthly_total = array_sum(array_column($monthly_jobs, 'total'));
$quarterly_total = array_sum(array_column($quarterly_jobs, 'total'));
$yearly_total = array_sum(array_column($yearly_jobs, 'total'));

// Get top posters
$poster_stats = db_fetch_all("
    SELECT poster_name, SUM(job_count) as total_jobs 
    FROM job_postings 
    WHERE post_date >= ? 
    GROUP BY poster_name 
    ORDER BY total_jobs DESC 
    LIMIT 5", [$month_start]);
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Dashboard</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <span class="text-muted"><?php echo date('F j, Y'); ?></span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-2 col-6">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="card-title">Daily Jobs</h6>
                    <h3><?php echo $daily_total; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="card-title">Weekly Jobs</h6>
                    <h3><?php echo $weekly_total; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="card-title">Monthly Jobs</h6>
                    <h3><?php echo $monthly_total; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="card-title">Quarterly Jobs</h6>
                    <h3><?php echo $quarterly_total; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <div class="card stat-card">
                <div class="card-body">
                    <h6 class="card-title">Yearly Jobs</h6>
                    <h3><?php echo $yearly_total; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6>Jobs by Website (This Month)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="websiteChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6>Top Posters (This Month)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="posterChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Website distribution chart
const websiteCtx = document.getElementById('websiteChart').getContext('2d');
new Chart(websiteCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($monthly_jobs, 'website')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($monthly_jobs, 'total')); ?>,
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
        }]
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
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>