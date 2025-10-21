<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle delete functionality
if (isset($_POST['delete_seo_id'])) {
    $seo_id = intval($_POST['delete_seo_id']);
    
    try {
        $stmt = $db->prepare("DELETE FROM seo_rankings WHERE id = ?");
        $stmt->bindValue(1, $seo_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $success = "SEO entry deleted successfully!";
        } else {
            $error = "Error deleting SEO entry!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle form submission for adding/updating SEO data
if ($_POST && !empty($_POST['website']) && !empty($_POST['page_url']) && !empty($_POST['keyword']) && !isset($_POST['delete_seo_id'])) {
    $website = $_POST['website'];
    $page_url = trim($_POST['page_url']);
    $keyword = trim($_POST['keyword']);
    $page_number = intval($_POST['page_number']);
    $position_on_page = intval($_POST['position_on_page']);
    $week_start = $_POST['week_start'];
    
    try {
        // Check if SEO entry already exists for this week, website, keyword and page
        $check_sql = "SELECT id FROM seo_rankings WHERE website = ? AND page_url = ? AND keyword = ? AND week_start = ?";
        $existing = db_fetch_one($check_sql, [$website, $page_url, $keyword, $week_start]);
        
        if ($existing) {
            // Update existing entry
            $sql = "UPDATE seo_rankings SET page_number = ?, position_on_page = ? WHERE id = ?";
            if (db_query($sql, [$page_number, $position_on_page, $existing['id']])) {
                $success = "SEO data updated successfully!";
            } else {
                $error = "Error updating SEO data!";
            }
        } else {
            // Insert new entry
            $sql = "INSERT INTO seo_rankings (website, page_url, keyword, page_number, position_on_page, week_start) VALUES (?, ?, ?, ?, ?, ?)";
            if (db_query($sql, [$website, $page_url, $keyword, $page_number, $position_on_page, $week_start])) {
                $success = "SEO data saved successfully!";
            } else {
                $error = "Error saving SEO data! Database error occurred.";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get Monday of current week
$current_week = date('Y-m-d', strtotime('monday this week'));

// Get recent pages for suggestions
$recent_pages = db_fetch_all("SELECT DISTINCT page_url FROM seo_rankings ORDER BY created_at DESC LIMIT 10");

// Get recent keywords for suggestions
$recent_keywords = db_fetch_all("SELECT DISTINCT keyword FROM seo_rankings ORDER BY created_at DESC LIMIT 10");

// Debug: Check if table exists and has data
try {
    $table_check = db_fetch_all("SELECT name FROM sqlite_master WHERE type='table' AND name='seo_rankings'");
    if (empty($table_check)) {
        $error = "SEO rankings table doesn't exist. Please run the database initialization script.";
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add SEO Data</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="seo_stats.php" class="btn btn-outline-primary">
                <i class="fas fa-chart-line"></i> View SEO Dashboard
            </a>
        </div>
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
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-search-plus"></i> Add SEO Ranking Data</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="seoForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Website <span class="text-danger">*</span></label>
                                    <select name="website" class="form-select" required>
                                        <option value="">Select Website</option>
                                        <?php foreach ($websites as $site): ?>
                                            <option value="<?php echo $site; ?>" 
                                                <?php echo isset($_POST['website']) && $_POST['website'] === $site ? 'selected' : ''; ?>>
                                                <?php echo $site; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Week Starting <span class="text-danger">*</span></label>
                                    <input type="date" name="week_start" class="form-control" 
                                           value="<?php echo isset($_POST['week_start']) ? $_POST['week_start'] : $current_week; ?>" 
                                           required>
                                    <div class="form-text">Select the Monday of the tracking week</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Page URL <span class="text-danger">*</span></label>
                                    <input type="url" name="page_url" class="form-control" 
                                           placeholder="https://example.com/page" 
                                           value="<?php echo isset($_POST['page_url']) ? htmlspecialchars($_POST['page_url']) : ''; ?>" 
                                           list="pageSuggestions" required>
                                    <datalist id="pageSuggestions">
                                        <?php foreach ($recent_pages as $page): ?>
                                            <option value="<?php echo htmlspecialchars($page['page_url']); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="form-text">Enter the full page URL including https://</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Keyword <span class="text-danger">*</span></label>
                                    <input type="text" name="keyword" class="form-control" 
                                           placeholder="e.g., software developer jobs in kampala"
                                           value="<?php echo isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : ''; ?>" 
                                           list="keywordSuggestions" required>
                                    <datalist id="keywordSuggestions">
                                        <?php foreach ($recent_keywords as $keyword_item): ?>
                                            <option value="<?php echo htmlspecialchars($keyword_item['keyword']); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="form-text">Enter the exact search keyword as tracked</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Google Page Number <span class="text-danger">*</span></label>
                                    <select name="page_number" class="form-select" required id="pageNumber">
                                        <option value="">Select Page</option>
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                            <option value="<?php echo $i; ?>" 
                                                <?php echo isset($_POST['page_number']) && $_POST['page_number'] == $i ? 'selected' : ''; ?>>
                                                Page <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="form-text">Which Google search results page (1-10)?</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Position on Page <span class="text-danger">*</span></label>
                                    <select name="position_on_page" class="form-select" required id="positionOnPage">
                                        <option value="">Select Position</option>
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                            <option value="<?php echo $i; ?>" 
                                                <?php echo isset($_POST['position_on_page']) && $_POST['position_on_page'] == $i ? 'selected' : ''; ?>>
                                                Position <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="form-text">Position on the search results page (1-10)</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview Section -->
                        <div class="card bg-light mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Ranking Preview</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div id="rankingPreview" class="text-center p-3">
                                            <div class="ranking-display">
                                                <span class="badge bg-secondary mb-2">OVERALL POSITION</span>
                                                <h3 class="text-primary" id="overallPosition">-</h3>
                                                <div class="small text-muted">
                                                    <span id="pageInfo">Page -, Position -</span>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge" id="rankingQuality">Select values to see ranking quality</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success btn-lg me-2">
                                    <i class="fas fa-save"></i> Save SEO Data
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
        </div>

        <!-- Quick Help & Recent Data -->
        <div class="col-lg-4">
            <!-- Quick Help -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Quick Help</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6>How to use:</h6>
                        <ul class="mb-0 small">
                            <li>Select website and tracking week</li>
                            <li>Enter full page URL including https://</li>
                            <li>Use exact keyword as searched</li>
                            <li>Select page number (1-10) and position (1-10)</li>
                            <li>System will calculate overall position</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Ranking Quality Guide -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-trophy"></i> Ranking Quality</h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-success me-2">1-10</span>
                            <span>Excellent (Page 1)</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-warning me-2">11-30</span>
                            <span>Good (Pages 2-3)</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-danger me-2">31-100</span>
                            <span>Needs Improvement</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-secondary me-2">100+</span>
                            <span>Not Visible</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Quick Stats</h6>
                </div>
                <div class="card-body">
                    <?php
                    $total_entries = db_fetch_one("SELECT COUNT(*) as count FROM seo_rankings");
                    $this_week_entries = db_fetch_one("SELECT COUNT(*) as count FROM seo_rankings WHERE week_start = ?", [$current_week]);
                    $top_keywords = db_fetch_all("SELECT keyword, COUNT(*) as count FROM seo_rankings GROUP BY keyword ORDER BY count DESC LIMIT 3");
                    ?>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h5 class="text-primary"><?php echo $total_entries['count'] ?? 0; ?></h5>
                            <small class="text-muted">Total Entries</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h5 class="text-success"><?php echo $this_week_entries['count'] ?? 0; ?></h5>
                            <small class="text-muted">This Week</small>
                        </div>
                    </div>
                    <?php if (!empty($top_keywords)): ?>
                        <div class="mt-3">
                            <h6>Top Keywords:</h6>
                            <?php foreach ($top_keywords as $keyword): ?>
                                <span class="badge bg-light text-dark mb-1"><?php echo htmlspecialchars($keyword['keyword']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent SEO Entries with Delete Functionality -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-history"></i> Recent SEO Entries</h6>
            <span class="badge bg-primary">Last 20 entries</span>
        </div>
        <div class="card-body">
            <?php
            $recent_seo = db_fetch_all("
                SELECT id, website, page_url, keyword, page_number, position_on_page, week_start, created_at
                FROM seo_rankings 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            ?>
            
            <?php if (empty($recent_seo)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No SEO entries found. Add your first entry above.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Website</th>
                                <th>Keyword</th>
                                <th>Ranking</th>
                                <th>Overall</th>
                                <th>Quality</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_seo as $entry): 
                                $overall_position = (($entry['page_number'] - 1) * 10) + $entry['position_on_page'];
                                $quality_class = $overall_position <= 10 ? 'success' : ($overall_position <= 30 ? 'warning' : 'danger');
                                $quality_text = $overall_position <= 10 ? 'Excellent' : ($overall_position <= 30 ? 'Good' : 'Needs Work');
                            ?>
                                <tr>
                                    <td>
                                        <small><?php echo date('M j', strtotime($entry['week_start'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($entry['website']); ?></span>
                                    </td>
                                    <td>
                                        <small title="<?php echo htmlspecialchars($entry['keyword']); ?>">
                                            <?php echo htmlspecialchars(mb_strimwidth($entry['keyword'], 0, 30, '...')); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            P<?php echo $entry['page_number']; ?>, #<?php echo $entry['position_on_page']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong>#<?php echo $overall_position; ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $quality_class; ?>">
                                            <?php echo $quality_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this SEO entry?');">
                                            <input type="hidden" name="delete_seo_id" value="<?php echo $entry['id']; ?>">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Update preview when page or position changes
function updatePreview() {
    const page = parseInt(document.getElementById('pageNumber').value);
    const position = parseInt(document.getElementById('positionOnPage').value);
    const overallPosition = document.getElementById('overallPosition');
    const pageInfo = document.getElementById('pageInfo');
    const rankingQuality = document.getElementById('rankingQuality');
    
    if (page && position) {
        const overall = ((page - 1) * 10) + position;
        overallPosition.textContent = `#${overall}`;
        pageInfo.textContent = `Page ${page}, Position ${position}`;
        
        // Update quality badge
        let qualityClass, qualityText;
        if (overall <= 10) {
            qualityClass = 'bg-success';
            qualityText = 'Excellent (Page 1)';
        } else if (overall <= 30) {
            qualityClass = 'bg-warning';
            qualityText = 'Good (Pages 2-3)';
        } else if (overall <= 100) {
            qualityClass = 'bg-danger';
            qualityText = 'Needs Improvement';
        } else {
            qualityClass = 'bg-secondary';
            qualityText = 'Not Visible';
        }
        
        rankingQuality.className = `badge ${qualityClass}`;
        rankingQuality.textContent = qualityText;
    } else {
        overallPosition.textContent = '-';
        pageInfo.textContent = 'Page -, Position -';
        rankingQuality.className = 'badge bg-secondary';
        rankingQuality.textContent = 'Select values to see ranking quality';
    }
}

// Fill sample data for testing
function fillSampleData() {
    document.querySelector('select[name="website"]').value = 'greatugandajobs.com';
    document.querySelector('input[name="page_url"]').value = 'https://greatugandajobs.com/jobs/software-developer';
    document.querySelector('input[name="keyword"]').value = 'software developer jobs kampala';
    document.querySelector('select[name="page_number"]').value = '1';
    document.querySelector('select[name="position_on_page"]').value = '3';
    updatePreview();
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('pageNumber').addEventListener('change', updatePreview);
    document.getElementById('positionOnPage').addEventListener('change', updatePreview);
    
    // Initialize preview
    updatePreview();
    
    // Form validation
    document.getElementById('seoForm').addEventListener('submit', function(e) {
        const pageUrl = document.querySelector('input[name="page_url"]').value;
        if (!pageUrl.startsWith('http')) {
            alert('Please enter a valid URL starting with http:// or https://');
            e.preventDefault();
        }
    });
});
</script>

<style>
.ranking-display {
    background: white;
    border-radius: 8px;
    padding: 20px;
    border: 2px solid #e9ecef;
}
#overallPosition {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 10px 0;
}
.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.075);
}
</style>

<?php require_once '../includes/footer.php'; ?>