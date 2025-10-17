<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if ($_POST && !empty($_POST['website']) && !empty($_POST['page_url']) && !empty($_POST['keyword'])) {
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
                $error = "Error saving SEO data!";
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
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Add SEO Data</h1>
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
                            <label class="form-label">Week Starting</label>
                            <input type="date" name="week_start" class="form-control" value="<?php echo isset($_POST['week_start']) ? $_POST['week_start'] : $current_week; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Page URL</label>
                            <input type="url" name="page_url" class="form-control" 
                                   placeholder="https://example.com/page" 
                                   value="<?php echo isset($_POST['page_url']) ? htmlspecialchars($_POST['page_url']) : ''; ?>" 
                                   list="pageSuggestions" required>
                            <datalist id="pageSuggestions">
                                <?php foreach ($recent_pages as $page): ?>
                                    <option value="<?php echo htmlspecialchars($page['page_url']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Enter the full page URL</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Keyword</label>
                            <input type="text" name="keyword" class="form-control" 
                                   placeholder="e.g., software developer jobs"
                                   value="<?php echo isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : ''; ?>" 
                                   list="keywordSuggestions" required>
                            <datalist id="keywordSuggestions">
                                <?php foreach ($recent_keywords as $keyword_item): ?>
                                    <option value="<?php echo htmlspecialchars($keyword_item['keyword']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Enter the search keyword</div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Google Page Number</label>
                            <select name="page_number" class="form-select" required>
                                <option value="">Select Page</option>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" 
                                        <?php echo isset($_POST['page_number']) && $_POST['page_number'] == $i ? 'selected' : ''; ?>>
                                        Page <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <div class="form-text">Which Google search results page?</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Position on Page</label>
                            <select name="position_on_page" class="form-select" required>
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
                
                <div class="row">
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Preview</label>
                            <div class="form-control bg-light">
                                <small class="text-muted" id="rankingPreview">
                                    <?php
                                    if (isset($_POST['page_number']) && isset($_POST['position_on_page'])) {
                                        echo "Page " . $_POST['page_number'] . ", Position " . $_POST['position_on_page'] . " (Overall Position: " . (($_POST['page_number'] - 1) * 10 + $_POST['position_on_page']) . ")";
                                    } else {
                                        echo "Select page and position to see preview";
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-save"></i> Save SEO Data
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> Reset Form
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Recent SEO Entries -->
    <div class="card mt-4">
        <div class="card-header">
            <h6>Recent SEO Entries (Last 2 Weeks)</h6>
        </div>
        <div class="card-body">
            <?php
            $recent_seo = db_fetch_all("
                SELECT website, page_url, keyword, page_number, position_on_page, week_start 
                FROM seo_rankings 
                WHERE week_start >= date('now', '-2 weeks') 
                ORDER BY week_start DESC, website, keyword 
                LIMIT 10
            ");
            ?>
            
            <?php if (empty($recent_seo)): ?>
                <p class="text-muted">No recent SEO entries found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Week</th>
                                <th>Website</th>
                                <th>Keyword</th>
                                <th>Ranking</th>
                                <th>Overall</th>
                                <th>Page</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_seo as $entry): 
                                $overall_position = (($entry['page_number'] - 1) * 10) + $entry['position_on_page'];
                            ?>
                                <tr>
                                    <td><?php echo date('M j', strtotime($entry['week_start'])); ?></td>
                                    <td><?php echo htmlspecialchars($entry['website']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['keyword']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $overall_position <= 10 ? 'success' : ($overall_position <= 30 ? 'warning' : 'danger'); ?>">
                                            Page <?php echo $entry['page_number']; ?>, Pos <?php echo $entry['position_on_page']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">#<?php echo $overall_position; ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted" title="<?php echo htmlspecialchars($entry['page_url']); ?>">
                                            <?php 
                                            $url_parts = parse_url($entry['page_url']);
                                            echo htmlspecialchars($url_parts['path'] ?? $entry['page_url']); 
                                            ?>
                                        </small>
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
document.addEventListener('DOMContentLoaded', function() {
    const pageSelect = document.querySelector('select[name="page_number"]');
    const positionSelect = document.querySelector('select[name="position_on_page"]');
    const preview = document.getElementById('rankingPreview');
    
    function updatePreview() {
        const page = parseInt(pageSelect.value);
        const position = parseInt(positionSelect.value);
        
        if (page && position) {
            const overall = ((page - 1) * 10) + position;
            preview.textContent = `Page ${page}, Position ${position} (Overall Position: ${overall})`;
        } else {
            preview.textContent = "Select page and position to see preview";
        }
    }
    
    pageSelect.addEventListener('change', updatePreview);
    positionSelect.addEventListener('change', updatePreview);
});
</script>

<?php require_once '../includes/footer.php'; ?>