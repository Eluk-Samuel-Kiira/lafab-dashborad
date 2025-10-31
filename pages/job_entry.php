<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Initialize variables
$success = '';
$error = '';

// Handle single delete request
if (isset($_POST['delete_job_id'])) {
    $delete_id = intval($_POST['delete_job_id']);
    
    try {
        // Get the entry details before deleting for confirmation message
        $entry = db_fetch_one("SELECT website, poster_name, post_date, job_count FROM job_postings WHERE id = ?", [$delete_id]);
        
        if ($entry) {
            $sql = "DELETE FROM job_postings WHERE id = ?";
            if (db_query($sql, [$delete_id])) {
                $success = "Successfully deleted job posting: {$entry['poster_name']} posted {$entry['job_count']} jobs on {$entry['website']} for {$entry['post_date']}";
            } else {
                $error = "Error deleting job posting!";
            }
        } else {
            $error = "Job posting not found!";
        }
        
    } catch (Exception $e) {
        $error = "Error deleting: " . $e->getMessage();
    }
}

// Handle form submission for adding new jobs
if ($_POST && !empty($_POST['website']) && !empty($_POST['poster_name']) && !isset($_POST['delete_job_id'])) {
    $website = $_POST['website'];
    $poster_name = trim($_POST['poster_name']);
    $job_count = intval($_POST['job_count']);
    $post_date = $_POST['post_date'];
    
    try {
        // Check if entry exists for this poster, website, and date
        $check_sql = "SELECT id, job_count FROM job_postings WHERE website = ? AND poster_name = ? AND post_date = ?";
        $existing = db_fetch_one($check_sql, [$website, $poster_name, $post_date]);
        
        if ($existing) {
            // Update existing entry - sum the job counts
            $new_job_count = $existing['job_count'] + $job_count;
            $sql = "UPDATE job_postings SET job_count = ? WHERE id = ?";
            if (db_query($sql, [$new_job_count, $existing['id']])) {
                $success = "Job posting updated successfully! Total jobs for $poster_name on $post_date: $new_job_count";
            } else {
                $error = "Error updating data!";
            }
        } else {
            // Insert new entry
            $sql = "INSERT INTO job_postings (website, poster_name, job_count, post_date) VALUES (?, ?, ?, ?)";
            if (db_query($sql, [$website, $poster_name, $job_count, $post_date])) {
                $success = "Job posting data saved successfully!";
            } else {
                $error = "Error saving data!";
            }
        }
        
        // Also add to posters table if not exists
        $check_sql = "SELECT id FROM posters WHERE name = ?";
        $existing_poster = db_fetch_one($check_sql, [$poster_name]);
        if (!$existing_poster) {
            db_query("INSERT OR IGNORE INTO posters (name) VALUES (?)", [$poster_name]);
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all posters for the dropdown
$posters = db_fetch_all("SELECT name FROM posters WHERE is_active = 1 ORDER BY name");

// Set default date to yesterday
$default_date = date('Y-m-d', strtotime('-1 day'));

// Get recent job postings (individual entries with IDs for deletion)
$recent_jobs = db_fetch_all("
    SELECT 
        id,
        website,
        poster_name,
        post_date,
        job_count,
        created_at
    FROM job_postings 
    ORDER BY post_date DESC, created_at DESC 
    LIMIT 20
");
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add Job Posts</h1>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="window.location.href='manage_posters.php'">
                <i class="fas fa-users"></i> Manage Posters
            </button>
            <button type="button" class="btn btn-outline-info" onclick="window.location.href='dashboard.php'">
                <i class="fas fa-chart-bar"></i> View Dashboard
            </button>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-plus-circle"></i> Add New Job Posting</h6>
        </div>
        <div class="card-body">
            <form method="POST" id="jobForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Website <span class="text-danger">*</span></label>
                            <select name="website" class="form-select border-0 bg-light" required>
                                <option value="">Select Website</option>
                                <?php foreach ($websites as $site): ?>
                                    <option value="<?php echo $site; ?>" <?php echo isset($_POST['website']) && $_POST['website'] === $site ? 'selected' : ''; ?>>
                                        <?php echo $site; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Poster Name <span class="text-danger">*</span></label>
                            <input type="text" name="poster_name" class="form-control border-0 bg-light" 
                                   value="<?php echo isset($_POST['poster_name']) ? htmlspecialchars($_POST['poster_name']) : ''; ?>" 
                                   list="postersList" placeholder="Enter poster name" required>
                            <datalist id="postersList">
                                <?php foreach ($posters as $poster): ?>
                                    <option value="<?php echo htmlspecialchars($poster['name']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Type poster name or select from list</div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Number of Jobs <span class="text-danger">*</span></label>
                            <input type="number" name="job_count" class="form-control border-0 bg-light" 
                                   min="1" value="<?php echo isset($_POST['job_count']) ? $_POST['job_count'] : '1'; ?>" required>
                            <div class="form-text">Enter the number of jobs posted</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Post Date <span class="text-danger">*</span></label>
                            <input type="date" name="post_date" class="form-control border-0 bg-light" 
                                   value="<?php echo isset($_POST['post_date']) ? $_POST['post_date'] : $default_date; ?>" required>
                            <div class="form-text">Default: Yesterday (<?php echo date('F j, Y', strtotime($default_date)); ?>)</div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats for Selected Poster/Date -->
                <div id="existingStats" class="alert alert-info d-none">
                    <i class="fas fa-info-circle"></i> 
                    <span id="statsText"></span>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-success btn-lg me-2">
                            <i class="fas fa-save"></i> Save Job Data
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="fillSampleData()">
                            <i class="fas fa-vial"></i> Fill Sample Data
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Recent Job Postings with Delete Options -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-history"></i> Recent Job Postings</h6>
            <span class="badge bg-primary"><?php echo count($recent_jobs); ?> entries</span>
        </div>
        <div class="card-body">
            <?php if (empty($recent_jobs)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No recent job postings found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Website</th>
                                <th>Poster</th>
                                <th>Jobs</th>
                                <th>Created</th>
                                <th width="80">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_jobs as $job): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('M j, Y', strtotime($job['post_date'])); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($job['website']); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user me-2 text-muted"></i>
                                            <?php echo htmlspecialchars($job['poster_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-success fs-6"><?php echo $job['job_count']; ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted" title="<?php echo $job['created_at']; ?>">
                                            <?php echo date('M j g:i A', strtotime($job['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete(<?php echo $job['id']; ?>, '<?php echo htmlspecialchars($job['poster_name']); ?>', <?php echo $job['job_count']; ?>, '<?php echo htmlspecialchars($job['website']); ?>', '<?php echo $job['post_date']; ?>')">
                                            <input type="hidden" name="delete_job_id" value="<?php echo $job['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Entry">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary Stats -->
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <h6 class="mb-0 text-primary"><?php echo count($recent_jobs); ?></h6>
                                <small class="text-muted">Total Entries</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <h6 class="mb-0 text-success">
                                    <?php 
                                    $total_jobs = array_sum(array_column($recent_jobs, 'job_count'));
                                    echo $total_jobs;
                                    ?>
                                </h6>
                                <small class="text-muted">Total Jobs</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <h6 class="mb-0 text-info">
                                    <?php 
                                    $unique_posters = count(array_unique(array_column($recent_jobs, 'poster_name')));
                                    echo $unique_posters;
                                    ?>
                                </h6>
                                <small class="text-muted">Unique Posters</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light border-0">
                            <div class="card-body text-center py-2">
                                <h6 class="mb-0 text-warning">
                                    <?php 
                                    $unique_dates = count(array_unique(array_column($recent_jobs, 'post_date')));
                                    echo $unique_dates;
                                    ?>
                                </h6>
                                <small class="text-muted">Unique Dates</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Function to fill sample data for testing
function fillSampleData() {
    const websites = <?php echo json_encode($websites); ?>;
    const posters = <?php echo json_encode(array_column($posters, 'name')); ?>;
    
    // Select random website and poster
    const randomWebsite = websites[Math.floor(Math.random() * websites.length)];
    const randomPoster = posters[Math.floor(Math.random() * posters.length)];
    const randomJobCount = Math.floor(Math.random() * 20) + 1;
    
    // Fill the form
    document.querySelector('select[name="website"]').value = randomWebsite;
    document.querySelector('input[name="poster_name"]').value = randomPoster;
    document.querySelector('input[name="job_count"]').value = randomJobCount;
    
    // Show confirmation
    alert(`Sample data filled:\nWebsite: ${randomWebsite}\nPoster: ${randomPoster}\nJobs: ${randomJobCount}`);
}

// Check for existing entries when poster or date changes
function checkExistingEntries() {
    const posterName = document.querySelector('input[name="poster_name"]').value;
    const postDate = document.querySelector('input[name="post_date"]').value;
    const website = document.querySelector('select[name="website"]').value;
    
    if (posterName && postDate && website) {
        // In a real implementation, you would make an AJAX call here
        // For now, we'll just show a generic message
        const statsDiv = document.getElementById('existingStats');
        const statsText = document.getElementById('statsText');
        
        statsText.textContent = `If ${posterName} has existing posts for ${website} on ${postDate}, the job counts will be combined.`;
        statsDiv.classList.remove('d-none');
    }
}

// Confirm single delete - returns true/false for form submission
function confirmDelete(id, posterName, jobCount, website, postDate) {
    const formattedDate = new Date(postDate).toLocaleDateString();
    return confirm(`Are you sure you want to delete this job posting?\n\nPoster: ${posterName}\nJobs: ${jobCount}\nWebsite: ${website}\nDate: ${formattedDate}`);
}

// Form validation
document.getElementById('jobForm').addEventListener('submit', function(e) {
    const jobCount = document.querySelector('input[name="job_count"]').value;
    const postDate = document.querySelector('input[name="post_date"]').value;
    
    if (jobCount < 1) {
        alert('Please enter a valid number of jobs (at least 1)');
        e.preventDefault();
        return;
    }
    
    const selectedDate = new Date(postDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate > today) {
        if (!confirm('You are selecting a future date. Are you sure you want to continue?')) {
            e.preventDefault();
            return;
        }
    }
});

// Add event listeners to check for existing entries
document.querySelector('input[name="poster_name"]').addEventListener('change', checkExistingEntries);
document.querySelector('input[name="post_date"]').addEventListener('change', checkExistingEntries);
document.querySelector('select[name="website"]').addEventListener('change', checkExistingEntries);
</script>

<style>
.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
    transform: translateX(2px);
    transition: all 0.2s ease;
}

.bg-light {
    background-color: #f8f9fa !important;
}

.badge.fs-6 {
    font-size: 0.9em !important;
    padding: 0.5em 0.75em;
}

/* Style for the delete form to appear inline */
form[style*="display: inline"] {
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
}

/* Uniform delete button styling */
.btn-outline-danger {
    border: 1px solid #dc3545;
    color: #dc3545;
    padding: 0.375rem 0.75rem;
    transition: all 0.15s ease-in-out;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    color: white;
    transform: scale(1.05);
}

.table td {
    vertical-align: middle;
}
</style>

<?php require_once '../includes/footer.php'; ?>