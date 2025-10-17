<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get date range from query string or use current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get poster statistics
$sql = "
    SELECT 
        p.name as poster_name,
        COUNT(j.id) as posting_days,
        SUM(j.job_count) as total_jobs,
        AVG(j.job_count) as avg_jobs_per_day,
        MIN(j.post_date) as first_post,
        MAX(j.post_date) as last_post
    FROM posters p
    LEFT JOIN job_postings j ON p.name = j.poster_name 
        AND j.post_date BETWEEN ? AND ?
    WHERE p.is_active = 1
    GROUP BY p.id, p.name
    ORDER BY total_jobs DESC
";

$poster_stats = db_fetch_all($sql, [$start_date, $end_date]);
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Posters Statistics</h1>
    </div>

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Posters Statistics -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Poster Performance (<?php echo $start_date . ' to ' . $end_date; ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Poster Name</th>
                            <th>Total Jobs</th>
                            <th>Posting Days</th>
                            <th>Avg Jobs/Day</th>
                            <th>First Post</th>
                            <th>Last Post</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($poster_stats as $stat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['poster_name']); ?></td>
                                <td><strong><?php echo $stat['total_jobs'] ?? 0; ?></strong></td>
                                <td><?php echo $stat['posting_days'] ?? 0; ?></td>
                                <td><?php echo number_format($stat['avg_jobs_per_day'] ?? 0, 1); ?></td>
                                <td><?php echo $stat['first_post'] ?: 'N/A'; ?></td>
                                <td><?php echo $stat['last_post'] ?: 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>