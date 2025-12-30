<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form actions
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' && !empty(trim($_POST['name']))) {
        $name = trim($_POST['name']);
        try {
            // Insert new poster
            $sql = "INSERT OR IGNORE INTO posters (name) VALUES (?)";
            if (db_query($sql, [$name])) {
                $success = "Poster '$name' added successfully!";
            } else {
                $error = "Failed to add poster '$name'.";
            }
        } catch (Exception $e) {
            $error = "Error adding poster: " . $e->getMessage();
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $poster_name = urldecode($_GET['delete']);
    try {
        // Delete from posters table
        $sql = "DELETE FROM posters WHERE name = ?";
        if (db_query($sql, [$poster_name])) {
            // Also delete from payment_settings if exists
            $sql = "DELETE FROM payment_settings WHERE poster_name = ?";
            db_query($sql, [$poster_name]);
            
            $success = "Poster '$poster_name' deleted successfully!";
        }
    } catch (Exception $e) {
        $error = "Error deleting poster: " . $e->getMessage();
    }
}

// Get all posters
$posters = db_fetch_all("
    SELECT p.*, 
           (SELECT COUNT(*) FROM job_postings jp WHERE jp.poster_name = p.name) as job_count,
           (SELECT SUM(job_count) FROM job_postings jp WHERE jp.poster_name = p.name) as total_jobs
    FROM posters p 
    ORDER BY p.name ASC
");

// Get current month stats
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$monthly_stats = db_fetch_all("
    SELECT 
        poster_name,
        SUM(job_count) as monthly_jobs
    FROM job_postings 
    WHERE post_date BETWEEN ? AND ?
    GROUP BY poster_name
", [$current_month_start, $current_month_end]);
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-users"></i> Manage Posters
        </h1>
        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='add_jobs.php'">
            <i class="fas fa-arrow-left"></i> Back to Job Entry
        </button>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Add New Poster Form -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-user-plus"></i> Add New Poster</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Poster Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Enter poster name" required autofocus>
                            <div class="form-text">Enter the name of the poster</div>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-save"></i> Add Poster
                        </button>
                    </form>
                </div>
            </div>

            <!-- Payment Info Card -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-money-bill-wave"></i> Payment Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <h4 class="text-success mb-3">UGX 10,000</h4>
                        <p class="mb-2">For every</p>
                        <h3 class="text-primary mb-3">100 Jobs</h3>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle"></i> Each poster earns UGX 10,000 for every 100 jobs posted
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Quick Stats</h6>
                </div>
                <div class="card-body">
                    <?php
                    $total_posters = count($posters);
                    $total_jobs_all = array_sum(array_column($posters, 'total_jobs'));
                    $total_monthly_jobs = array_sum(array_column($monthly_stats, 'monthly_jobs'));
                    $total_earnings = floor($total_monthly_jobs / 100) * 10000;
                    ?>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h4 class="text-primary"><?php echo $total_posters; ?></h4>
                            <small class="text-muted">Total Posters</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h4 class="text-success"><?php echo $total_jobs_all; ?></h4>
                            <small class="text-muted">All Time Jobs</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h4 class="text-warning"><?php echo $total_monthly_jobs; ?></h4>
                            <small class="text-muted">This Month</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h4 class="text-danger">UGX <?php echo number_format($total_earnings); ?></h4>
                            <small class="text-muted">Monthly Earnings</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Posters List -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-list"></i> All Posters</h6>
                        <span class="badge bg-primary"><?php echo count($posters); ?> posters</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($posters)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Posters Found</h5>
                            <p class="text-muted">Add your first poster using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Poster Name</th>
                                        <th>Total Jobs</th>
                                        <th>This Month</th>
                                        <th>Earnings (UGX)</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($posters as $poster): 
                                        $monthly_data = array_filter($monthly_stats, function($m) use ($poster) { 
                                            return $m['poster_name'] === $poster['name']; 
                                        });
                                        $current_month_jobs = !empty($monthly_data) ? current($monthly_data)['monthly_jobs'] : 0;
                                        $payments_earned = floor($current_month_jobs / 100);
                                        $earnings = $payments_earned * 10000;
                                        $progress = ($current_month_jobs % 100);
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($poster['name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo $poster['job_count']; ?> posting(s)
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $poster['total_jobs'] ?: 0; ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-<?php echo $current_month_jobs > 0 ? 'success' : 'secondary'; ?> me-2">
                                                        <?php echo $current_month_jobs; ?>
                                                    </span>
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-<?php echo $progress >= 50 ? 'warning' : 'info'; ?>" 
                                                             style="width: <?php echo $progress; ?>%">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted ms-2">
                                                        <?php echo $progress; ?>/100
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="text-success"><?php echo number_format($earnings); ?></strong>
                                                <?php if ($payments_earned > 0): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $payments_earned; ?> payment(s)
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmDelete('<?php echo htmlspecialchars($poster['name']); ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Summary -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    Showing <?php echo count($posters); ?> poster(s) |
                                    Total Monthly Earnings: <strong>UGX <?php 
                                        $total_monthly_earnings = 0;
                                        foreach ($posters as $poster) {
                                            $monthly_data = array_filter($monthly_stats, function($m) use ($poster) { 
                                                return $m['poster_name'] === $poster['name']; 
                                            });
                                            $current_month_jobs = !empty($monthly_data) ? current($monthly_data)['monthly_jobs'] : 0;
                                            $total_monthly_earnings += floor($current_month_jobs / 100) * 10000;
                                        }
                                        echo number_format($total_monthly_earnings);
                                    ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Earnings Summary -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Monthly Earnings Summary</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Poster</th>
                                    <th>This Month Jobs</th>
                                    <th>Progress to 100</th>
                                    <th>Payments Earned</th>
                                    <th>Total Earnings (UGX)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $grand_total = 0;
                                foreach ($posters as $poster): 
                                    $monthly_data = array_filter($monthly_stats, function($m) use ($poster) { 
                                        return $m['poster_name'] === $poster['name']; 
                                    });
                                    $current_month_jobs = !empty($monthly_data) ? current($monthly_data)['monthly_jobs'] : 0;
                                    $payments_earned = floor($current_month_jobs / 100);
                                    $earnings = $payments_earned * 10000;
                                    $progress = $current_month_jobs % 100;
                                    $grand_total += $earnings;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($poster['name']); ?></strong></td>
                                        <td><?php echo $current_month_jobs; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                    <div class="progress-bar bg-<?php echo $progress >= 50 ? 'warning' : 'info'; ?>" 
                                                         style="width: <?php echo $progress; ?>%">
                                                        <?php echo $progress; ?>%
                                                    </div>
                                                </div>
                                                <small><?php echo $current_month_jobs; ?>/100</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $payments_earned > 0 ? 'success' : 'secondary'; ?>">
                                                <?php echo $payments_earned; ?>
                                            </span>
                                        </td>
                                        <td><strong class="text-success"><?php echo number_format($earnings); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-warning">
                                    <td colspan="4" class="text-end"><strong>Total Monthly Earnings:</strong></td>
                                    <td><strong class="text-success">UGX <?php echo number_format($grand_total); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Confirm deletion
function confirmDelete(posterName) {
    if (confirm(`Are you sure you want to delete "${posterName}"?\n\nThis action cannot be undone.`)) {
        window.location.href = `?delete=${encodeURIComponent(posterName)}`;
    }
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // Auto-focus on name input
    const nameInput = document.querySelector('input[name="name"]');
    if (nameInput) {
        nameInput.focus();
    }
});
</script>

<style>
.card {
    border-radius: 10px;
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.progress {
    border-radius: 10px;
}

.badge {
    font-size: 0.85em;
    padding: 0.4em 0.8em;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    color: white;
}

.alert {
    border-radius: 8px;
}
</style>

<?php require_once '../includes/footer.php'; ?>