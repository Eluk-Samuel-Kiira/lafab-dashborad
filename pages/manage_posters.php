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

// Get all posters
$posters = db_fetch_all("
    SELECT p.*, 
           (SELECT COUNT(*) FROM job_postings jp WHERE jp.poster_name = p.name) as job_count
    FROM posters p 
    ORDER BY p.is_active DESC, p.name ASC
");
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Manage Posters</h1>
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
        <div class="col-md-5">
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

            <!-- Quick Stats -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Posters Statistics</h6>
                </div>
                <div class="card-body">
                    <?php
                    $total_posters = count($posters);
                    $active_posters = count(array_filter($posters, function($p) { return $p['is_active']; }));
                    $inactive_posters = $total_posters - $active_posters;
                    ?>
                    <div class="row text-center">
                        <div class="col-6">
                            <h4 class="text-primary"><?php echo $total_posters; ?></h4>
                            <small class="text-muted">Total Posters</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $active_posters; ?></h4>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Posters List -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">All Posters</h6>
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
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($posters as $poster): ?>
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
                                                <?php if ($poster['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
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

            <!-- Bulk Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="job_entry.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus-circle"></i> Add Job Posts
                        </a>
                        <button type="button" class="btn btn-outline-info" onclick="window.location.reload()">
                            <i class="fas fa-sync"></i> Refresh List
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">Recent Poster Activity</h6>
        </div>
        <div class="card-body">
            <?php
            $recent_activity = db_fetch_all("
                SELECT jp.poster_name, jp.website, jp.job_count, jp.post_date, jp.created_at
                FROM job_postings jp
                ORDER BY jp.created_at DESC
                LIMIT 10
            ");
            ?>
            
            <?php if (empty($recent_activity)): ?>
                <p class="text-muted">No recent job posting activity.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Poster</th>
                                <th>Website</th>
                                <th>Jobs</th>
                                <th>Date</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $activity): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($activity['poster_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($activity['website']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $activity['job_count']; ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($activity['post_date'])); ?></td>
                                    <td><small class="text-muted"><?php echo date('H:i', strtotime($activity['created_at'])); ?></small></td>
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
</script>

<?php require_once '../includes/footer.php'; ?>