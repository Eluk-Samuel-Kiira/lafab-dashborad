<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle delete action
if (isset($_POST['delete_stat_id'])) {
    $stat_id = intval($_POST['delete_stat_id']);
    db_query("DELETE FROM social_media_daily_stats WHERE id = ?", [$stat_id]);
    $success = "Monthly entry deleted successfully!";
}

// Get platforms and countries
$platforms = db_fetch_all("SELECT * FROM social_media_platforms ORDER BY name");
$countries = ['Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Zambia', 'Malawi'];

// Initialize form values
$platform_id = '';
$country = '';
$month = date('Y-m'); // Default to current month
$entry_exists = false;
$existing_entry = null;

// Check for existing entry when platform/country/month is selected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_entry'])) {
    $platform_id = intval($_POST['platform_id']);
    $country = $_POST['country'];
    $month = $_POST['month'];
    
    // Check if entry exists for this combination
    $existing_entry = db_fetch_one("
        SELECT * FROM social_media_daily_stats 
        WHERE platform_id = ? 
        AND country = ? 
        AND strftime('%Y-%m', stat_date) = ?
    ", [$platform_id, $country, $month]);
    
    $entry_exists = !empty($existing_entry);
}

// Save or update entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_entry'])) {
    $platform_id = intval($_POST['platform_id']);
    $country = $_POST['country'];
    $month = $_POST['month'];
    $followers = intval($_POST['followers']);
    $engagements = intval($_POST['engagements']);
    
    // Check if entry exists
    $existing = db_fetch_one("
        SELECT id FROM social_media_daily_stats 
        WHERE platform_id = ? 
        AND country = ? 
        AND strftime('%Y-%m', stat_date) = ?
    ", [$platform_id, $country, $month]);
    
    // Set full date (first day of month)
    $stat_date = $month . '-01';
    
    if ($existing) {
        // Update existing entry
        db_query("
            UPDATE social_media_daily_stats 
            SET followers = ?, engagements = ? 
            WHERE id = ?
        ", [$followers, $engagements, $existing['id']]);
        $success = "Monthly data updated successfully!";
    } else {
        // Insert new entry
        db_query("
            INSERT INTO social_media_daily_stats 
            (platform_id, country, stat_date, followers, engagements) 
            VALUES (?, ?, ?, ?, ?)
        ", [$platform_id, $country, $stat_date, $followers, $engagements]);
        $success = "Monthly data saved successfully!";
        
        // Reset form after successful save
        $platform_id = '';
        $country = '';
        $month = date('Y-m');
        $entry_exists = false;
        $existing_entry = null;
    }
}

// Get recent monthly entries
$recent_entries = db_fetch_all("
    SELECT sms.*, p.name as platform_name 
    FROM social_media_daily_stats sms 
    JOIN social_media_platforms p ON sms.platform_id = p.id 
    ORDER BY sms.stat_date DESC 
    LIMIT 10
");

// Get monthly summary
$monthly_summary = db_fetch_all("
    SELECT 
        strftime('%Y-%m', stat_date) as month,
        COUNT(*) as total_entries,
        SUM(followers) as total_followers,
        SUM(engagements) as total_engagements
    FROM social_media_daily_stats 
    GROUP BY strftime('%Y-%m', stat_date)
    ORDER BY month DESC
    LIMIT 6
");
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Monthly Social Media Data</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="social_stats_view.php" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar"></i> View Analytics
            </a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Entry Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-calendar-plus"></i> Add Monthly Data</h6>
                </div>
                <div class="card-body">
                    <!-- Step 1: Select Platform, Country & Month -->
                    <form method="POST" id="entryForm">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Platform</label>
                                    <select name="platform_id" class="form-select" required>
                                        <option value="">Select Platform</option>
                                        <?php foreach ($platforms as $platform): ?>
                                            <option value="<?php echo $platform['id']; ?>" 
                                                <?php echo $platform_id == $platform['id'] ? 'selected' : ''; ?>>
                                                <?php echo $platform['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Country</label>
                                    <select name="country" class="form-select" required>
                                        <option value="">Select Country</option>
                                        <?php foreach ($countries as $c): ?>
                                            <option value="<?php echo $c; ?>" 
                                                <?php echo $country == $c ? 'selected' : ''; ?>>
                                                <?php echo $c; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Month</label>
                                    <input type="month" name="month" class="form-control" 
                                           value="<?php echo $month; ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" name="check_entry" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Check Entry Status
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Step 2: Show Status and Data Entry Form -->
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_entry'])): ?>
                        <hr>
                        
                        <?php if ($entry_exists): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Entry Exists:</strong> Data already entered for 
                                <?php echo date('F Y', strtotime($month . '-01')); ?> 
                                for <?php echo $country; ?> on this platform.
                                <br>
                                <small>You can update the existing data below.</small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <strong>New Entry:</strong> No data found for this combination.
                                <br>
                                <small>You can add new monthly data below.</small>
                            </div>
                        <?php endif; ?>

                        <!-- Data Entry Form -->
                        <form method="POST">
                            <input type="hidden" name="platform_id" value="<?php echo $platform_id; ?>">
                            <input type="hidden" name="country" value="<?php echo $country; ?>">
                            <input type="hidden" name="month" value="<?php echo $month; ?>">
                            
                            <div class="card bg-light mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        Monthly Data for 
                                        <span class="text-primary"><?php echo date('F Y', strtotime($month . '-01')); ?></span>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Total Followers</label>
                                                <input type="number" name="followers" class="form-control" 
                                                       value="<?php echo $existing_entry['followers'] ?? 0; ?>" 
                                                       min="0" required>
                                                <small class="text-muted">Total followers at end of month</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Total Engagements</label>
                                                <input type="number" name="engagements" class="form-control" 
                                                       value="<?php echo $existing_entry['engagements'] ?? 0; ?>" 
                                                       min="0" required>
                                                <small class="text-muted">Total interactions during month</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Engagement Rate Display -->
                                    <?php if ($existing_entry): ?>
                                        <?php 
                                        $followers = $existing_entry['followers'];
                                        $engagements = $existing_entry['engagements'];
                                        $engagement_rate = $followers > 0 ? ($engagements / $followers * 100) : 0;
                                        ?>
                                        <div class="alert alert-info">
                                            <strong>Current Engagement Rate:</strong>
                                            <span class="badge bg-<?php 
                                                echo $engagement_rate >= 5 ? 'success' : 
                                                    ($engagement_rate >= 2 ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo number_format($engagement_rate, 2); ?>%
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" name="save_entry" class="btn btn-success">
                                        <i class="fas fa-save"></i>
                                        <?php echo $entry_exists ? 'Update Monthly Data' : 'Save Monthly Data'; ?>
                                    </button>
                                    <a href="social_stats_entry.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo"></i> Start New Entry
                                    </a>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats & Info -->
        <div class="col-lg-4">
            <!-- Monthly Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Recent Months Summary</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($monthly_summary)): ?>
                        <p class="text-muted">No data yet</p>
                    <?php else: ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($monthly_summary as $summary): ?>
                                <div class="mb-2 p-2 border rounded">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong><?php echo date('F Y', strtotime($summary['month'] . '-01')); ?></strong>
                                        <span class="badge bg-primary"><?php echo $summary['total_entries']; ?> entries</span>
                                    </div>
                                    <small class="text-muted">
                                        Followers: <?php echo number_format($summary['total_followers']); ?> | 
                                        Engagements: <?php echo number_format($summary['total_engagements']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Instructions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">How to Use</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6>Simple Steps:</h6>
                        <ol class="mb-0" style="font-size: 0.9rem;">
                            <li>Select Platform (Facebook, Twitter, etc.)</li>
                            <li>Select Country (Uganda, Kenya, etc.)</li>
                            <li>Select Month (e.g., Jan 2024)</li>
                            <li>Click "Check Entry Status"</li>
                            <li>Enter or update follower & engagement counts</li>
                            <li>Save your data</li>
                        </ol>
                        <p class="mt-2 mb-0 small">
                            <strong>Note:</strong> Only one entry per platform per country per month is allowed.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Entries Table -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Recent Monthly Entries</h6>
            <span class="badge bg-primary">Last 10 entries</span>
        </div>
        <div class="card-body">
            <?php if (empty($recent_entries)): ?>
                <p class="text-muted text-center py-3">No entries yet. Add your first monthly entry above.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Platform</th>
                                <th>Country</th>
                                <th>Followers</th>
                                <th>Engagements</th>
                                <th>Engagement Rate</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_entries as $entry): 
                                $month_name = date('F Y', strtotime($entry['stat_date']));
                                $engagement_rate = $entry['followers'] > 0 ? 
                                    ($entry['engagements'] / $entry['followers'] * 100) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-info"><?php echo $month_name; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($entry['platform_name']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($entry['country']); ?></td>
                                    <td><?php echo number_format($entry['followers']); ?></td>
                                    <td><?php echo number_format($entry['engagements']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $engagement_rate >= 5 ? 'success' : 
                                                ($engagement_rate >= 2 ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo number_format($engagement_rate, 2); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Delete this monthly entry?');">
                                            <input type="hidden" name="delete_stat_id" value="<?php echo $entry['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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

<script>
// Auto-calculate engagement rate
function calculateEngagementRate() {
    const followers = parseInt(document.querySelector('input[name="followers"]')?.value) || 0;
    const engagements = parseInt(document.querySelector('input[name="engagements"]')?.value) || 0;
    
    if (followers > 0) {
        const rate = (engagements / followers * 100).toFixed(2);
        return rate + '%';
    }
    return 'N/A';
}

// Update engagement rate when values change
document.addEventListener('DOMContentLoaded', function() {
    const followersInput = document.querySelector('input[name="followers"]');
    const engagementsInput = document.querySelector('input[name="engagements"]');
    
    if (followersInput && engagementsInput) {
        followersInput.addEventListener('input', updateEngagementPreview);
        engagementsInput.addEventListener('input', updateEngagementPreview);
    }
});

function updateEngagementPreview() {
    const preview = document.getElementById('engagementRatePreview');
    if (preview) {
        preview.textContent = 'Engagement Rate: ' + calculateEngagementRate();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>