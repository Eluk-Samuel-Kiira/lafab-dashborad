<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form actions
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' && !empty(trim($_POST['name']))) {
        $name = trim($_POST['name']);
        try {
            $stmt = $db->prepare("INSERT OR IGNORE INTO posters (name) VALUES (?)");
            $stmt->bindValue(1, $name, SQLITE3_TEXT);
            if ($stmt->execute()) {
                $success = "Poster '$name' added successfully!";
                
                // Create default payment settings for this poster
                db_query("INSERT OR IGNORE INTO payment_settings (poster_name, jobs_per_payment, payment_amount) VALUES (?, ?, ?)", 
                        [$name, 50, 100.00]);
            } else {
                $error = "Failed to add poster '$name'.";
            }
        } catch (Exception $e) {
            $error = "Error adding poster: " . $e->getMessage();
        }
    }
    
    // Handle payment settings update
    if ($_POST['action'] === 'update_payment' && !empty($_POST['poster_name'])) {
        $poster_name = trim($_POST['poster_name']);
        $jobs_per_payment = intval($_POST['jobs_per_payment']);
        $payment_amount = floatval($_POST['payment_amount']);
        
        try {
            $sql = "INSERT OR REPLACE INTO payment_settings (poster_name, jobs_per_payment, payment_amount, updated_at) 
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
            if (db_query($sql, [$poster_name, $jobs_per_payment, $payment_amount])) {
                $success = "Payment settings updated for $poster_name!";
            } else {
                $error = "Failed to update payment settings.";
            }
        } catch (Exception $e) {
            $error = "Error updating payment settings: " . $e->getMessage();
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $poster_id = intval($_GET['delete']);
    try {
        // Check if poster has job postings
        $check_stmt = $db->prepare("SELECT COUNT(*) as job_count FROM job_postings WHERE poster_name IN (SELECT name FROM posters WHERE id = ?)");
        $check_stmt->bindValue(1, $poster_id, SQLITE3_INTEGER);
        $result = $check_stmt->execute();
        $job_count = $result->fetchArray(SQLITE3_ASSOC)['job_count'];
        
        if ($job_count > 0) {
            // Soft delete - set as inactive
            $stmt = $db->prepare("UPDATE posters SET is_active = 0 WHERE id = ?");
            $stmt->bindValue(1, $poster_id, SQLITE3_INTEGER);
            if ($stmt->execute()) {
                $success = "Poster marked as inactive (has $job_count job postings).";
            }
        } else {
            // Hard delete - no job postings
            $stmt = $db->prepare("DELETE FROM posters WHERE id = ?");
            $stmt->bindValue(1, $poster_id, SQLITE3_INTEGER);
            if ($stmt->execute()) {
                $success = "Poster deleted successfully!";
            }
        }
    } catch (Exception $e) {
        $error = "Error deleting poster: " . $e->getMessage();
    }
}

// Handle activate action
if (isset($_GET['activate'])) {
    $poster_id = intval($_GET['activate']);
    try {
        $stmt = $db->prepare("UPDATE posters SET is_active = 1 WHERE id = ?");
        $stmt->bindValue(1, $poster_id, SQLITE3_INTEGER);
        if ($stmt->execute()) {
            $success = "Poster activated successfully!";
        }
    } catch (Exception $e) {
        $error = "Error activating poster: " . $e->getMessage();
    }
}

// Get all posters with their payment settings
$posters = db_fetch_all("
    SELECT p.*, 
           ps.jobs_per_payment,
           ps.payment_amount,
           ps.currency,
           ps.is_active as payment_active,
           (SELECT COUNT(*) FROM job_postings jp WHERE jp.poster_name = p.name) as job_count
    FROM posters p 
    LEFT JOIN payment_settings ps ON p.name = ps.poster_name
    ORDER BY p.is_active DESC, p.name ASC
");

// Get current month stats for payment calculations
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$monthly_stats = db_fetch_all("
    SELECT 
        poster_name,
        SUM(job_count) as monthly_jobs,
        COUNT(DISTINCT post_date) as active_days
    FROM job_postings 
    WHERE post_date BETWEEN ? AND ?
    GROUP BY poster_name
", [$current_month_start, $current_month_end]);
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Manage Posters & Payment Settings</h1>
        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='job_entry.php'">
            <i class="fas fa-arrow-left"></i> Back to Job Entry
        </button>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Add New Poster Form -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-user-plus"></i> Add New Poster</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Poster Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Enter poster full name" required>
                            <div class="form-text">Enter the full name of the poster</div>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-save"></i> Add Poster
                        </button>
                    </form>
                </div>
            </div>

            <!-- Payment Settings Form -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-money-bill-wave"></i> Payment Settings</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="paymentForm">
                        <input type="hidden" name="action" value="update_payment">
                        <div class="mb-3">
                            <label class="form-label">Select Poster</label>
                            <select name="poster_name" class="form-select" required onchange="loadPaymentSettings(this.value)">
                                <option value="">Choose a poster...</option>
                                <?php foreach ($posters as $poster): ?>
                                    <?php if ($poster['is_active']): ?>
                                        <option value="<?php echo htmlspecialchars($poster['name']); ?>">
                                            <?php echo htmlspecialchars($poster['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jobs per Payment</label>
                            <input type="number" name="jobs_per_payment" class="form-control" min="1" value="100" required>
                            <div class="form-text">Number of jobs required for one payment</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Amount ($)</label>
                            <input type="number" name="payment_amount" class="form-control" min="0" step="0.01" value="18000.00" required>
                            <div class="form-text">Amount paid when target is reached</div>
                        </div>
                        <button type="submit" class="btn btn-info w-100">
                            <i class="fas fa-cog"></i> Update Payment Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Monthly Summary</h6>
                </div>
                <div class="card-body">
                    <?php
                    $total_posters = count(array_filter($posters, function($p) { return $p['is_active']; }));
                    $total_monthly_jobs = array_sum(array_column($monthly_stats, 'monthly_jobs'));
                    $estimated_payments = 0;
                    
                    foreach ($monthly_stats as $stat) {
                        $poster_settings = array_filter($posters, function($p) use ($stat) { 
                            return $p['name'] === $stat['poster_name'] && $p['is_active']; 
                        });
                        if (!empty($poster_settings)) {
                            $settings = current($poster_settings);
                            if ($settings['jobs_per_payment'] > 0) {
                                $estimated_payments += floor($stat['monthly_jobs'] / $settings['jobs_per_payment']) * $settings['payment_amount'];
                            }
                        }
                    }
                    ?>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h4 class="text-primary"><?php echo $total_posters; ?></h4>
                            <small class="text-muted">Active Posters</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h4 class="text-success"><?php echo $total_monthly_jobs; ?></h4>
                            <small class="text-muted">This Month Jobs</small>
                        </div>
                        <div class="col-12">
                            <h4 class="text-warning">UGX: <?php echo number_format($estimated_payments, 2); ?></h4>
                            <small class="text-muted">Estimated Payments</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Posters List with Payment Info -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">All Posters & Payment Status</h6>
                    <span class="badge bg-primary"><?php echo count($posters); ?> posters</span>
                </div>
                <div class="card-body">
                    <?php if (empty($posters)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No posters found. Add your first poster using the form.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Jobs Posted</th>
                                        <th>This Month</th>
                                        <th>Payment Rate</th>
                                        <th>Earnings</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($posters as $poster): 
                                        $monthly_data = array_filter($monthly_stats, function($m) use ($poster) { 
                                            return $m['poster_name'] === $poster['name']; 
                                        });
                                        $current_month_jobs = !empty($monthly_data) ? current($monthly_data)['monthly_jobs'] : 0;
                                        $payments_earned = $poster['jobs_per_payment'] > 0 ? floor($current_month_jobs / $poster['jobs_per_payment']) : 0;
                                        $earnings = $payments_earned * $poster['payment_amount'];
                                        $progress = $poster['jobs_per_payment'] > 0 ? ($current_month_jobs / $poster['jobs_per_payment']) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($poster['name']); ?></strong>
                                                <?php if ($poster['job_count'] > 0): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        First post: <?php 
                                                        $first_post = db_fetch_one("SELECT MIN(post_date) as first_date FROM job_postings WHERE poster_name = ?", [$poster['name']]);
                                                        echo $first_post ? date('M j, Y', strtotime($first_post['first_date'])) : 'N/A';
                                                        ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $poster['job_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $current_month_jobs > 0 ? 'success' : 'secondary'; ?>">
                                                    <?php echo $current_month_jobs; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo $poster['jobs_per_payment'] ?? 'N/A'; ?> jobs = $<?php echo number_format($poster['payment_amount'] ?? 0, 2); ?>
                                                </small>
                                                <?php if ($poster['jobs_per_payment'] > 0): ?>
                                                    <div class="progress mt-1" style="height: 5px;">
                                                        <div class="progress-bar bg-<?php echo $progress >= 100 ? 'success' : ($progress >= 50 ? 'warning' : 'info'); ?>" 
                                                             style="width: <?php echo min($progress, 100); ?>%">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo $current_month_jobs; ?>/<?php echo $poster['jobs_per_payment']; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong class="text-success">UGX: <?php echo number_format($earnings, 2); ?></strong>
                                                <?php if ($payments_earned > 0): ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo $payments_earned; ?> payment(s)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($poster['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-info" 
                                                            onclick="editPaymentSettings('<?php echo htmlspecialchars($poster['name']); ?>', <?php echo $poster['jobs_per_payment'] ?? 50; ?>, <?php echo $poster['payment_amount'] ?? 100.00; ?>)"
                                                            title="Edit Payment">
                                                        <i class="fas fa-money-bill"></i>
                                                    </button>
                                                    
                                                    <?php if (!$poster['is_active']): ?>
                                                        <a href="?activate=<?php echo $poster['id']; ?>" class="btn btn-outline-success" title="Activate">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($poster['job_count'] == 0): ?>
                                                        <a href="?delete=<?php echo $poster['id']; ?>" 
                                                           class="btn btn-outline-danger" 
                                                           title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($poster['name']); ?>?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="?delete=<?php echo $poster['id']; ?>" 
                                                           class="btn btn-outline-warning" 
                                                           title="Mark Inactive"
                                                           onclick="return confirm('This poster has <?php echo $poster['job_count']; ?> job postings. Mark as inactive instead?')">
                                                            <i class="fas fa-ban"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="card mt-4">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Monthly Payment Summary</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Poster</th>
                                    <th>This Month Jobs</th>
                                    <th>Target</th>
                                    <th>Progress</th>
                                    <th>Payments Earned</th>
                                    <th>Total Earnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_earnings = 0;
                                foreach ($posters as $poster): 
                                    if (!$poster['is_active']) continue;
                                    
                                    $monthly_data = array_filter($monthly_stats, function($m) use ($poster) { 
                                        return $m['poster_name'] === $poster['name']; 
                                    });
                                    $current_month_jobs = !empty($monthly_data) ? current($monthly_data)['monthly_jobs'] : 0;
                                    $payments_earned = $poster['jobs_per_payment'] > 0 ? floor($current_month_jobs / $poster['jobs_per_payment']) : 0;
                                    $earnings = $payments_earned * $poster['payment_amount'];
                                    $total_earnings += $earnings;
                                    $progress = $poster['jobs_per_payment'] > 0 ? ($current_month_jobs / $poster['jobs_per_payment']) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($poster['name']); ?></strong></td>
                                        <td><?php echo $current_month_jobs; ?></td>
                                        <td><?php echo $poster['jobs_per_payment'] ?? 'N/A'; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $progress >= 100 ? 'success' : ($progress >= 50 ? 'warning' : 'info'); ?>" 
                                                     style="width: <?php echo min($progress, 100); ?>%">
                                                    <?php echo number_format(min($progress, 100), 0); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $payments_earned > 0 ? 'success' : 'secondary'; ?>">
                                                <?php echo $payments_earned; ?>
                                            </span>
                                        </td>
                                        <td><strong class="text-success">UGX: <?php echo number_format($earnings, 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-warning">
                                    <td colspan="5" class="text-end"><strong>Total Estimated Payments:</strong></td>
                                    <td><strong class="text-success">UGX: <?php echo number_format($total_earnings, 2); ?></strong></td>
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
// Auto-focus on the name input
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.querySelector('input[name="name"]');
    if (nameInput) {
        nameInput.focus();
    }
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Load payment settings when poster is selected
function loadPaymentSettings(posterName) {
    // This would typically make an AJAX call to get the settings
    // For now, we'll just update the form values based on known data
    console.log('Loading settings for:', posterName);
}

// Quick edit payment settings
function editPaymentSettings(posterName, jobsPerPayment, paymentAmount) {
    document.querySelector('select[name="poster_name"]').value = posterName;
    document.querySelector('input[name="jobs_per_payment"]').value = jobsPerPayment;
    document.querySelector('input[name="payment_amount"]').value = paymentAmount;
    
    // Scroll to payment form
    document.getElementById('paymentForm').scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php require_once '../includes/footer.php'; ?>