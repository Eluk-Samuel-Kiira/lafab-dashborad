<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form submission
if ($_POST && !empty($_POST['website']) && !empty($_POST['poster_name'])) {
    $website = $_POST['website'];
    $poster_name = trim($_POST['poster_name']);
    $job_count = intval($_POST['job_count']);
    $post_date = $_POST['post_date'];
    
    try {
        // Insert job posting
        $sql = "INSERT INTO job_postings (website, poster_name, job_count, post_date) VALUES (?, ?, ?, ?)";
        if (db_query($sql, [$website, $poster_name, $job_count, $post_date])) {
            $success = "Job posting data saved successfully!";
            
            // Also add to posters table if not exists
            $check_sql = "SELECT id FROM posters WHERE name = ?";
            $existing = db_fetch_one($check_sql, [$poster_name]);
            if (!$existing) {
                db_query("INSERT OR IGNORE INTO posters (name) VALUES (?)", [$poster_name]);
            }
        } else {
            $error = "Error saving data!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all posters for the dropdown
$posters = db_fetch_all("SELECT name FROM posters WHERE is_active = 1 ORDER BY name");
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add Job Posts</h1>
        <button type="button" class="btn btn-outline-primary" onclick="window.location.href='manage_posters.php'">
            <i class="fas fa-users"></i> Manage Posters
        </button>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Website</label>
                            <select name="website" class="form-select" required>
                                <option value="">Select Website</option>
                                <?php foreach ($websites as $site): ?>
                                    <option value="<?php echo $site; ?>"><?php echo $site; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Poster Name</label>
                            <input type="text" name="poster_name" class="form-control" list="postersList" required>
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
                            <label class="form-label">Number of Jobs</label>
                            <input type="number" name="job_count" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Post Date</label>
                            <input type="date" name="post_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Job Data</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>