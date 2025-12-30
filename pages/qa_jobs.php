<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Initialize variables
$success = '';
$error = '';
$editing = false;
$current_rule = null;

// Handle delete request
if (isset($_POST['delete_rule_id'])) {
    $delete_id = intval($_POST['delete_rule_id']);
    
    try {
        $sql = "DELETE FROM qr_rules WHERE id = ?";
        if (db_query($sql, [$delete_id])) {
            // $success = "QA rule deleted successfully!";
        } else {
            $error = "Error deleting QA rule!";
        }
    } catch (Exception $e) {
        $error = "Error deleting: " . $e->getMessage();
    }
}

// Handle toggle active status
if (isset($_POST['toggle_rule_id'])) {
    $toggle_id = intval($_POST['toggle_rule_id']);
    
    try {
        // Get current status
        $rule = db_fetch_one("SELECT id, is_active FROM qr_rules WHERE id = ?", [$toggle_id]);
        if ($rule) {
            $new_status = $rule['is_active'] ? 0 : 1;
            $sql = "UPDATE qr_rules SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            if (db_query($sql, [$new_status, $toggle_id])) {
                $success = "QA rule status updated!";
            }
        }
    } catch (Exception $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Handle form submission for adding/editing rules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && !isset($_POST['delete_rule_id']) && !isset($_POST['toggle_rule_id'])) {
    $id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $rule_type = $_POST['rule_type'] ?? 'general';
    $priority = intval($_POST['priority']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $website_filter = $_POST['website_filter'] ?? 'all';
    $poster_filter = $_POST['poster_filter'] ?? 'all';
    $min_job_count = !empty($_POST['min_job_count']) ? intval($_POST['min_job_count']) : 0;
    $max_job_count = !empty($_POST['max_job_count']) ? intval($_POST['max_job_count']) : null;
    $effective_date = $_POST['effective_date'] ?? date('Y-m-d');
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $created_by = $_SESSION['username'] ?? 'System';
    
    // Validation
    if (empty($title) || empty($description)) {
        $error = "Title and description are required!";
    } elseif ($min_job_count < 0) {
        $error = "Minimum job count cannot be negative!";
    } elseif ($max_job_count !== null && $max_job_count < $min_job_count) {
        $error = "Maximum job count must be greater than minimum job count!";
    } else {
        try {
            if ($id) {
                // Update existing rule
                $sql = "UPDATE qr_rules SET 
                    title = ?, 
                    description = ?, 
                    rule_type = ?, 
                    priority = ?, 
                    is_active = ?, 
                    website_filter = ?, 
                    poster_filter = ?, 
                    min_job_count = ?, 
                    max_job_count = ?, 
                    effective_date = ?, 
                    expiry_date = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";
                
                $params = [
                    $title, $description, $rule_type, $priority, $is_active,
                    $website_filter, $poster_filter, $min_job_count, $max_job_count,
                    $effective_date, $expiry_date, $id
                ];
                
                if (db_query($sql, $params)) {
                    $success = "QA rule updated successfully!";
                } else {
                    $error = "Error updating rule!";
                }
            } else {
                // Insert new rule
                $sql = "INSERT INTO qr_rules 
                    (title, description, rule_type, priority, is_active, 
                     website_filter, poster_filter, min_job_count, max_job_count, 
                     effective_date, expiry_date, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $params = [
                    $title, $description, $rule_type, $priority, $is_active,
                    $website_filter, $poster_filter, $min_job_count, $max_job_count,
                    $effective_date, $expiry_date, $created_by
                ];
                
                if (db_query($sql, $params)) {
                    $success = "QA rule added successfully!";
                    // Clear form
                    $_POST = [];
                } else {
                    $error = "Error adding rule!";
                }
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Check if we're editing a rule
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $current_rule = db_fetch_one("SELECT * FROM qr_rules WHERE id = ?", [$edit_id]);
    if ($current_rule) {
        $editing = true;
    }
}

// Get all posters and websites for filters
$posters = db_fetch_all("SELECT DISTINCT name FROM posters WHERE is_active = 1 ORDER BY name");
$websites = $websites ?? ['Website A', 'Website B', 'Website C']; // From your config

// Get rule types
$rule_types = [
    'general' => 'General Rule',
    'technical' => 'Technical Requirement',
    'quality' => 'Quality Standard',
    'compliance' => 'Compliance Rule',
    'security' => 'Security Policy',
    'process' => 'Process Rule'
];

// Handle search and filter
$search_term = '';
$filter_type = '';
$filter_status = '';
$where_conditions = ['1=1'];
$query_params = [];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
    $search_param = "%{$search_term}%";
    $query_params[] = $search_param;
    $query_params[] = $search_param;
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $filter_type = $_GET['type'];
    $where_conditions[] = "rule_type = ?";
    $query_params[] = $filter_type;
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $filter_status = $_GET['status'];
    $where_conditions[] = "is_active = ?";
    $query_params[] = $filter_status;
}

// Build the WHERE clause
$where_clause = implode(' AND ', $where_conditions);

// Get all QA rules
$qr_rules_query = "
    SELECT 
        id,
        title,
        description,
        rule_type,
        priority,
        is_active,
        website_filter,
        poster_filter,
        min_job_count,
        max_job_count,
        effective_date,
        expiry_date,
        created_by,
        created_at,
        updated_at
    FROM qr_rules 
    WHERE {$where_clause}
    ORDER BY priority ASC, title ASC
";

$qr_rules = db_fetch_all($qr_rules_query, $query_params);

// Get stats
$total_rules = count($qr_rules);
$active_rules = count(array_filter($qr_rules, function($rule) {
    return $rule['is_active'];
}));

?>


<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-qrcode text-primary"></i> QA Rules Management
        </h1>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                <i class="fas fa-plus-circle"></i> Add New Rule
            </button>
            <button type="button" class="btn btn-outline-warning" onclick="applyQuickRules()">
                <i class="fas fa-bolt"></i> Quick Rules
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

    <!-- Stats Cards with improved UI -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm stats-card-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 text-uppercase text-muted small">Total Rules</h6>
                            <h2 class="mb-0"><?php echo $total_rules; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-qrcode fa-2x"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-history"></i> Last updated: Now
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm stats-card-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 text-uppercase text-muted small">Active Rules</h6>
                            <h2 class="mb-0"><?php echo $active_rules; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <?php echo $total_rules > 0 ? round(($active_rules/$total_rules)*100, 1) . '% of total' : '0% of total'; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm stats-card-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 text-uppercase text-muted small">High Priority</h6>
                            <h2 class="mb-0">
                                <?php 
                                $high_priority = count(array_filter($qr_rules, function($rule) {
                                    return $rule['priority'] <= 3;
                                }));
                                echo $high_priority;
                                ?>
                            </h2>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            Priority 1-3 (Critical)
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm stats-card-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-1 text-uppercase text-muted small">Rule Types</h6>
                            <h2 class="mb-0"><?php echo count($rule_types); ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-tags fa-2x"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <?php echo implode(', ', array_slice(array_keys($rule_types), 0, 2)); ?>...
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Improved Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3">
            <h6 class="mb-0"><i class="fas fa-filter me-2"></i> Filter Rules</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Search Rules</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" 
                               placeholder="Search by title or description..." 
                               value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Rule Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($rule_types as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filter_type === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>Inactive Only</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Apply
                    </button>
                </div>
                <?php if (!empty($search_term) || !empty($filter_type) || $filter_status !== ''): ?>
                    <div class="col-12 mt-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Filtered results
                            </small>
                            <a href="qa_jobs.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Clear Filters
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- QA Rules Table with Improved UI -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i> QA Rules List
                    <span class="badge bg-light text-dark ms-2"><?php echo $total_rules; ?></span>
                </h6>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i> Options
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="exportRules()">
                            <i class="fas fa-download me-2"></i> Export Rules
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="printRules()">
                            <i class="fas fa-print me-2"></i> Print Rules
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                            <i class="fas fa-plus me-2"></i> Add New Rule
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($qr_rules)): ?>
                <div class="text-center py-5 empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-qrcode fa-4x text-muted"></i>
                    </div>
                    <h4 class="mt-4 text-muted">No QA Rules Found</h4>
                    <p class="text-muted mb-4">
                        <?php if (!empty($search_term)): ?>
                            No rules matching "<?php echo htmlspecialchars($search_term); ?>"
                        <?php else: ?>
                            Start by creating your first QA rule
                        <?php endif; ?>
                    </p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                        <i class="fas fa-plus-circle me-2"></i> Create First Rule
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50" class="text-center">
                                    <i class="fas fa-star text-warning"></i>
                                </th>
                                <th>Rule Details</th>
                                <th width="120" class="text-center">Type</th>
                                <th width="100" class="text-center">Filters</th>
                                <th width="120" class="text-center">Date Range</th>
                                <th width="100" class="text-center">Status</th>
                                <th width="140" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qr_rules as $rule): ?>
                                <tr class="rule-row" data-rule-id="<?php echo $rule['id']; ?>">
                                    <td class="text-center">
                                        <div class="priority-indicator" data-priority="<?php echo $rule['priority']; ?>">
                                            <span class="badge priority-badge priority-<?php echo $rule['priority']; ?>">
                                                <?php echo $rule['priority']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 rule-title">
                                                    <i class="fas fa-gavel text-primary me-2"></i>
                                                    <?php echo htmlspecialchars($rule['title']); ?>
                                                </h6>
                                                <p class="mb-1 text-muted rule-description-preview">
                                                    <?php 
                                                    $desc = htmlspecialchars($rule['description']);
                                                    echo strlen($desc) > 120 ? substr($desc, 0, 120) . '...' : $desc;
                                                    ?>
                                                    <?php if (strlen($desc) > 120): ?>
                                                        <a href="#" class="view-full-desc" 
                                                           data-description="<?php echo htmlspecialchars($rule['description']); ?>"
                                                           data-title="<?php echo htmlspecialchars($rule['title']); ?>">
                                                            <small>Read more</small>
                                                        </a>
                                                    <?php endif; ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-user text-muted me-1"></i>
                                                    <?php echo htmlspecialchars($rule['created_by']); ?>
                                                    <i class="fas fa-clock text-muted ms-2 me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($rule['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rule-type-badge bg-<?php 
                                            switch($rule['rule_type']) {
                                                case 'general': echo 'primary'; break;
                                                case 'compliance': echo 'danger'; break;
                                                case 'quality': echo 'success'; break;
                                                case 'technical': echo 'info'; break;
                                                case 'security': echo 'warning'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($rule_types[$rule['rule_type']] ?? $rule['rule_type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="filter-info">
                                            <?php if ($rule['website_filter'] != 'all'): ?>
                                                <span class="badge bg-light text-dark mb-1" title="Website Filter">
                                                    <i class="fas fa-globe me-1"></i><?php echo htmlspecialchars($rule['website_filter']); ?>
                                                </span><br>
                                            <?php endif; ?>
                                            <?php if ($rule['poster_filter'] != 'all'): ?>
                                                <span class="badge bg-light text-dark" title="Poster Filter">
                                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($rule['poster_filter']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="date-range">
                                            <small class="d-block text-muted">
                                                <i class="fas fa-calendar-check me-1"></i>
                                                <?php echo date('M j, Y', strtotime($rule['effective_date'])); ?>
                                            </small>
                                            <?php if ($rule['expiry_date']): ?>
                                                <small class="d-block text-muted">
                                                    <i class="fas fa-calendar-times me-1"></i>
                                                    <?php echo date('M j, Y', strtotime($rule['expiry_date'])); ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-success">
                                                    <i class="fas fa-infinity"></i> No expiry
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="toggle_rule_id" value="<?php echo $rule['id']; ?>">
                                            <button type="submit" class="btn btn-sm status-toggle <?php echo $rule['is_active'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                <?php if ($rule['is_active']): ?>
                                                    <i class="fas fa-toggle-on me-1"></i> Active
                                                <?php else: ?>
                                                    <i class="fas fa-toggle-off me-1"></i> Inactive
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary btn-action" 
                                                    data-bs-toggle="tooltip" title="View Details"
                                                    onclick="viewRuleDetails(<?php echo htmlspecialchars(json_encode($rule)); ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-action" 
                                                    data-bs-toggle="tooltip" title="Edit Rule"
                                                    onclick="loadRuleForEdit(<?php echo htmlspecialchars(json_encode($rule)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline" 
                                                onsubmit="return confirmDeleteRule('<?php echo addslashes($rule['title']); ?>')">
                                                <input type="hidden" name="delete_rule_id" value="<?php echo $rule['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-action" 
                                                        data-bs-toggle="tooltip" title="Delete Rule">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-0 text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Showing <?php echo $total_rules; ?> rule(s)
                                    <?php if (!empty($search_term)): ?>
                                        matching "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" onclick="scrollToTop()">
                                    <i class="fas fa-arrow-up me-1"></i> Back to Top
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Full Description Modal -->
<div class="modal fade" id="viewDescriptionModal" tabindex="-1" aria-labelledby="viewDescriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="viewDescriptionModalLabel">
                    <i class="fas fa-file-alt me-2"></i> Rule Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="rule-details-container">
                    <h4 id="detailTitle" class="mb-3"></h4>
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-2">Description</h6>
                            <div id="detailDescription" class="rule-description-content"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-filter me-1"></i> Filters
                                    </h6>
                                    <div id="detailFilters"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-calendar me-1"></i> Date Range
                                    </h6>
                                    <div id="detailDates"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
                <button type="button" class="btn btn-primary" onclick="editCurrentRule()">
                    <i class="fas fa-edit me-1"></i> Edit Rule
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Rule Modal (Fixed) -->
<div class="modal fade" id="editRuleModal" tabindex="-1" aria-labelledby="editRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editRuleModalLabel">
                    <i class="fas fa-plus-circle me-2"></i> Add New QA Rule
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="ruleForm">
                <input type="hidden" name="rule_id" id="rule_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Rule Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="rule_title" class="form-control" required
                                       placeholder="Enter rule title (e.g., 'Minimum Job Count Requirement')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Rule Type <span class="text-danger">*</span></label>
                                <select name="rule_type" id="rule_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($rule_types as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                        <textarea name="description" id="rule_description" class="form-control" 
                                  rows="4" required placeholder="Detailed description of the rule..."></textarea>
                        <div class="form-text">Describe the rule in detail. This will be shown to users.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                                <select name="priority" id="rule_priority" class="form-select" required>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == 5 ? 'selected' : ''; ?>>
                                            <?php echo $i; ?> - <?php 
                                                echo $i == 1 ? 'Critical' : 
                                                    ($i <= 3 ? 'High' : 
                                                        ($i <= 5 ? 'Medium' : 'Low')); 
                                            ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <div class="form-text">1 = Highest, 10 = Lowest</div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Min Job Count</label>
                                <input type="number" name="min_job_count" id="min_job_count" 
                                       class="form-control" min="0" placeholder="0">
                                <div class="form-text">Minimum jobs to apply</div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Max Job Count</label>
                                <input type="number" name="max_job_count" id="max_job_count" 
                                       class="form-control" min="1" placeholder="Leave empty for unlimited">
                                <div class="form-text">Maximum jobs to apply</div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="is_active" id="is_active" 
                                           class="form-check-input" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Website Filter</label>
                                <select name="website_filter" id="website_filter" class="form-select">
                                    <option value="all">All Websites</option>
                                    <?php foreach ($websites as $site): ?>
                                        <option value="<?php echo htmlspecialchars($site); ?>">
                                            <?php echo htmlspecialchars($site); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Apply rule to specific website only</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Poster Filter</label>
                                <select name="poster_filter" id="poster_filter" class="form-select">
                                    <option value="all">All Posters</option>
                                    <?php foreach ($posters as $poster): ?>
                                        <option value="<?php echo htmlspecialchars($poster['name']); ?>">
                                            <?php echo htmlspecialchars($poster['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Apply rule to specific poster only</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Effective Date <span class="text-danger">*</span></label>
                                <input type="date" name="effective_date" id="effective_date" 
                                       class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Expiry Date</label>
                                <input type="date" name="expiry_date" id="expiry_date" 
                                       class="form-control" placeholder="Leave empty for no expiry">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview Section -->
                    <div class="card mt-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-eye"></i> Rule Preview</h6>
                        </div>
                        <div class="card-body">
                            <div id="rulePreview">
                                <div class="d-flex align-items-start">
                                    <span class="badge bg-info me-2 mt-1" id="previewPriority">5</span>
                                    <div>
                                        <strong id="previewTitle">Rule Title</strong>
                                        <p class="mb-1 text-muted" id="previewDescription">Rule description will appear here...</p>
                                        <small class="text-muted">
                                            <span id="previewType">Type: General</span> | 
                                            <span id="previewFilters">Filters: All websites, All posters</span> | 
                                            <span id="previewDates">Effective: <?php echo date('M j, Y'); ?></span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Rule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Rule Button Modal Trigger -->
<div class="modal fade" id="addRuleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <div class="welcome-icon">
                    <i class="fas fa-qrcode fa-4x text-primary mb-4"></i>
                </div>
                <h4>Create New QA Rule</h4>
                <p class="text-muted mb-4">Define rules for job posting quality, compliance, and requirements.</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary btn-lg" data-bs-dismiss="modal" 
                            onclick="openNewRuleForm()">
                        <i class="fas fa-plus-circle me-2"></i> Create New Rule
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// Current rule for details
let currentRule = null;

// Function to view rule details
function viewRuleDetails(rule) {
    currentRule = rule;
    
    // Set modal content
    document.getElementById('viewDescriptionModalLabel').innerHTML = 
        `<i class="fas fa-file-alt me-2"></i> ${rule.title}`;
    document.getElementById('detailTitle').textContent = rule.title;
    document.getElementById('detailDescription').innerHTML = 
        rule.description.replace(/\n/g, '<br>');
    
    // Set filters
    let filtersHtml = '';
    if (rule.website_filter === 'all' && rule.poster_filter === 'all') {
        filtersHtml = '<p class="mb-1"><i class="fas fa-check text-success me-2"></i>Applies to all websites and posters</p>';
    } else {
        if (rule.website_filter !== 'all') {
            filtersHtml += `<p class="mb-1"><i class="fas fa-globe me-2"></i>Website: ${rule.website_filter}</p>`;
        }
        if (rule.poster_filter !== 'all') {
            filtersHtml += `<p class="mb-1"><i class="fas fa-user me-2"></i>Poster: ${rule.poster_filter}</p>`;
        }
    }
    if (rule.min_job_count > 0) {
        filtersHtml += `<p class="mb-1"><i class="fas fa-sort-numeric-up me-2"></i>Minimum Jobs: ${rule.min_job_count}</p>`;
    }
    if (rule.max_job_count) {
        filtersHtml += `<p class="mb-1"><i class="fas fa-sort-numeric-down me-2"></i>Maximum Jobs: ${rule.max_job_count}</p>`;
    }
    document.getElementById('detailFilters').innerHTML = filtersHtml;
    
    // Set dates
    const effectiveDate = new Date(rule.effective_date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    let datesHtml = `<p class="mb-1"><i class="fas fa-calendar-check me-2"></i>Effective: ${effectiveDate}</p>`;
    if (rule.expiry_date) {
        const expiryDate = new Date(rule.expiry_date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        datesHtml += `<p class="mb-1"><i class="fas fa-calendar-times me-2"></i>Expires: ${expiryDate}</p>`;
    } else {
        datesHtml += '<p class="mb-1"><i class="fas fa-infinity me-2"></i>No expiry date</p>';
    }
    
    datesHtml += `<p class="mb-0 text-muted small"><i class="fas fa-clock me-2"></i>Created: ${new Date(rule.created_at).toLocaleString()}</p>`;
    document.getElementById('detailDates').innerHTML = datesHtml;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('viewDescriptionModal'));
    modal.show();
}

// Function to edit current rule from details modal
function editCurrentRule() {
    if (currentRule) {
        // Close the description modal first
        const descModal = bootstrap.Modal.getInstance(document.getElementById('viewDescriptionModal'));
        if (descModal) {
            descModal.hide();
        }
        
        // Wait a bit for modal to close completely
        setTimeout(() => {
            loadRuleForEdit(currentRule);
        }, 300);
    }
}

// Fix for modal backdrop issue
function fixModalBackdrop() {
    // Remove any lingering backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => {
        backdrop.parentNode.removeChild(backdrop);
    });
    
    // Remove modal-open class if no modal is showing
    const modals = document.querySelectorAll('.modal.show');
    if (modals.length === 0) {
        document.body.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
        document.body.style.paddingRight = '0';
    }
}

// Fixed loadRuleForEdit function
function loadRuleForEdit(rule) {
    // Store the rule data temporarily
    window.currentEditRule = rule;
    
    // Fix any backdrop issues first
    fixModalBackdrop();
    
    // Show the modal first
    const modal = new bootstrap.Modal(document.getElementById('editRuleModal'), {
        backdrop: 'static',
        keyboard: false
    });
    modal.show();
    
    // Wait for modal to be fully shown, then populate fields
    const modalElement = document.getElementById('editRuleModal');
    const handler = function() {
        // Now safely access the elements
        document.getElementById('editRuleModalLabel').innerHTML = 
            `<i class="fas fa-edit me-2"></i> Edit QA Rule: ${rule.title.substring(0, 30)}${rule.title.length > 30 ? '...' : ''}`;
        document.getElementById('rule_id').value = rule.id;
        document.getElementById('rule_title').value = rule.title;
        document.getElementById('rule_description').value = rule.description;
        document.getElementById('rule_type').value = rule.rule_type;
        document.getElementById('rule_priority').value = rule.priority;
        document.getElementById('min_job_count').value = rule.min_job_count || '';
        document.getElementById('max_job_count').value = rule.max_job_count || '';
        document.getElementById('is_active').checked = rule.is_active == 1;
        document.getElementById('website_filter').value = rule.website_filter;
        document.getElementById('poster_filter').value = rule.poster_filter;
        document.getElementById('effective_date').value = rule.effective_date;
        document.getElementById('expiry_date').value = rule.expiry_date || '';
        
        // Update preview
        updateRulePreview();
        
        // Remove the event listener after it runs once
        modalElement.removeEventListener('shown.bs.modal', handler);
    };
    
    modalElement.addEventListener('shown.bs.modal', handler);
}

// Fixed openNewRuleForm function
function openNewRuleForm() {
    fixModalBackdrop();
    
    // Show the modal first
    const modal = new bootstrap.Modal(document.getElementById('editRuleModal'), {
        backdrop: 'static',
        keyboard: false
    });
    modal.show();
    
    // Wait for modal to be fully shown, then reset fields
    const modalElement = document.getElementById('editRuleModal');
    const handler = function() {
        // Now safely access the elements
        document.getElementById('editRuleModalLabel').innerHTML = 
            '<i class="fas fa-plus-circle me-2"></i> Add New QA Rule';
        document.getElementById('ruleForm').reset();
        document.getElementById('rule_id').value = '';
        document.getElementById('effective_date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('rule_priority').value = '5';
        document.getElementById('is_active').checked = true;
        document.getElementById('website_filter').value = 'all';
        document.getElementById('poster_filter').value = 'all';
        
        // Update preview
        updateRulePreview();
        
        // Remove the event listener after it runs once
        modalElement.removeEventListener('shown.bs.modal', handler);
    };
    
    modalElement.addEventListener('shown.bs.modal', handler);
}

// Quick rules function
function applyQuickRules() {
    const quickRules = [
        {
            title: 'Daily Posting Quota - General Members',
            description: '**Rule Objective:** Ensure consistent daily job posting activity from all posting members.\n\n' +
                        '**Requirements:**\n• All posting members must post between **250 to 300 jobs** daily\n' +
                        '• Minimum required: **250 jobs** per day\n• Maximum allowed: **300 jobs** per day\n' +
                        '• Applies to **all regular posting members** (non-admin)\n\n' +
                        '**Compliance Criteria:**\n✅ Member posts 250-300 jobs in a single day\n' +
                        '❌ Member posts <250 jobs (under-quota)\n❌ Member posts >300 jobs (over-quota)',
            rule_type: 'compliance',
            priority: 3,
            min_job_count: 250,
            max_job_count: 300,
            website_filter: 'all',
            poster_filter: 'all'
        },
        {
            title: 'Admin Weekly Posting Requirement',
            description: '**Rule Objective:** Maintain consistent administrator engagement with job postings.\n\n' +
                        '**Requirements:**\n• Administrators must post **minimum 50 jobs** on qualifying days\n' +
                        '• Must meet requirement for **at least 2 days** per calendar week\n' +
                        '• Week runs **Monday through Sunday**\n• Qualifying day = any day with ≥50 job posts\n\n' +
                        '**Calculation Methodology:**\n1. Count jobs posted each day (Monday to Sunday)\n' +
                        '2. Flag days with ≥50 jobs as "qualifying days"\n' +
                        '3. Check if ≥2 qualifying days in the week\n4. Report weekly compliance status',
            rule_type: 'compliance',
            priority: 4,
            min_job_count: 50,
            website_filter: 'all',
            poster_filter: 'all'
        }
    ];
    
    const modal = new bootstrap.Modal(document.getElementById('editRuleModal'));
    modal.show();
}

// Safer updateRulePreview function
function updateRulePreview() {
    // Check if elements exist before accessing them
    const titleElement = document.getElementById('rule_title');
    const descriptionElement = document.getElementById('rule_description');
    const priorityElement = document.getElementById('rule_priority');
    const typeElement = document.getElementById('rule_type');
    const websiteFilterElement = document.getElementById('website_filter');
    const posterFilterElement = document.getElementById('poster_filter');
    const effectiveDateElement = document.getElementById('effective_date');
    
    // If any required element doesn't exist, return early
    if (!titleElement || !descriptionElement || !priorityElement || !typeElement || 
        !websiteFilterElement || !posterFilterElement || !effectiveDateElement) {
        console.log('Preview elements not found');
        return;
    }
    
    const title = titleElement.value || 'Rule Title';
    const description = descriptionElement.value || 'Rule description will appear here...';
    const priority = priorityElement.value || 5;
    const type = typeElement.options[typeElement.selectedIndex]?.text || 'General Rule';
    const websiteFilter = websiteFilterElement.value || 'all';
    const posterFilter = posterFilterElement.value || 'all';
    const effectiveDate = effectiveDateElement.value || '<?php echo date('Y-m-d'); ?>';
    
    // Format date
    const dateObj = new Date(effectiveDate);
    const formattedDate = dateObj.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
    
    // Build filters text
    let filtersText = '';
    if (websiteFilter === 'all' && posterFilter === 'all') {
        filtersText = 'All websites, All posters';
    } else if (websiteFilter === 'all') {
        filtersText = `All websites, Poster: ${posterFilter}`;
    } else if (posterFilter === 'all') {
        filtersText = `Website: ${websiteFilter}, All posters`;
    } else {
        filtersText = `Website: ${websiteFilter}, Poster: ${posterFilter}`;
    }
    
    // Update preview if elements exist
    const previewTitle = document.getElementById('previewTitle');
    const previewDescription = document.getElementById('previewDescription');
    const previewPriority = document.getElementById('previewPriority');
    const previewType = document.getElementById('previewType');
    const previewFilters = document.getElementById('previewFilters');
    const previewDates = document.getElementById('previewDates');
    
    if (previewTitle) previewTitle.textContent = title;
    if (previewDescription) {
        previewDescription.textContent = description.length > 100 ? 
            description.substring(0, 100) + '...' : description;
    }
    if (previewPriority) {
        previewPriority.textContent = priority;
        previewPriority.className = `badge me-2 mt-1 priority-badge priority-${priority}`;
    }
    if (previewType) previewType.textContent = `Type: ${type}`;
    if (previewFilters) previewFilters.textContent = `Filters: ${filtersText}`;
    if (previewDates) previewDates.textContent = `Effective: ${formattedDate}`;
}

// Helper functions
function confirmDeleteRule(title) {
    return confirm(`Are you sure you want to delete this rule?\n\n"${title}"\n\nThis action cannot be undone.`);
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function exportRules() {
    alert('Export functionality coming soon!');
}

function printRules() {
    window.print();
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Handle "Read more" clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-full-desc') || 
            e.target.parentElement.classList.contains('view-full-desc')) {
            e.preventDefault();
            const link = e.target.classList.contains('view-full-desc') ? e.target : e.target.parentElement;
            const description = link.getAttribute('data-description');
            const title = link.getAttribute('data-title');
            
            // Create a temporary rule object
            const tempRule = {
                title: title,
                description: description,
                website_filter: 'all',
                poster_filter: 'all',
                effective_date: '<?php echo date('Y-m-d'); ?>',
                created_at: 'Just now'
            };
            
            viewRuleDetails(tempRule);
        }
    });
    
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
    
    // Fix modal backdrop on close
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function() {
            setTimeout(fixModalBackdrop, 100);
        });
    });
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+Shift+N for new rule
        if (e.ctrlKey && e.shiftKey && e.key === 'N') {
            e.preventDefault();
            openNewRuleForm();
        }
        
        // Escape key
        if (e.key === 'Escape') {
            fixModalBackdrop();
        }
    });
    
    // Auto-focus search
    <?php if (!empty($search_term)): ?>
        document.querySelector('input[name="search"]').focus();
    <?php endif; ?>
    
    // Setup event listeners for preview updates
    const setupPreviewListeners = () => {
        const inputs = ['rule_title', 'rule_description', 'rule_priority', 'rule_type', 
                        'website_filter', 'poster_filter', 'effective_date'];
        
        inputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('input', updateRulePreview);
                input.addEventListener('change', updateRulePreview);
            }
        });
    };
    
    // Setup preview listeners when edit modal is shown
    const editModal = document.getElementById('editRuleModal');
    if (editModal) {
        editModal.addEventListener('shown.bs.modal', setupPreviewListeners);
    }
    
    <?php if ($editing && $current_rule): ?>
        // Load editing rule after page loads
        setTimeout(() => {
            loadRuleForEdit(<?php echo json_encode($current_rule); ?>);
        }, 500);
    <?php endif; ?>
    
    // Add event listeners for form validation
    const ruleForm = document.getElementById('ruleForm');
    if (ruleForm) {
        ruleForm.addEventListener('submit', function(e) {
            const minJob = document.getElementById('min_job_count')?.value;
            const maxJob = document.getElementById('max_job_count')?.value;
            const effectiveDate = document.getElementById('effective_date')?.value;
            const expiryDate = document.getElementById('expiry_date')?.value;
            
            // Validate job counts
            if (minJob && parseInt(minJob) < 0) {
                alert('Minimum job count cannot be negative!');
                e.preventDefault();
                return;
            }
            
            if (maxJob && parseInt(maxJob) < 0) {
                alert('Maximum job count cannot be negative!');
                e.preventDefault();
                return;
            }
            
            if (minJob && maxJob && parseInt(minJob) > parseInt(maxJob)) {
                alert('Minimum job count cannot be greater than maximum job count!');
                e.preventDefault();
                return;
            }
            
            // Validate dates
            if (expiryDate && new Date(expiryDate) < new Date(effectiveDate)) {
                alert('Expiry date cannot be before effective date!');
                e.preventDefault();
                return;
            }
        });
    }
});
</script>


<style>
    /* Improved CSS */
    .stats-card-primary {
        border-left: 4px solid #0d6efd;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .stats-card-success {
        border-left: 4px solid #198754;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .stats-card-info {
        border-left: 4px solid #0dcaf0;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .stats-card-warning {
        border-left: 4px solid #ffc107;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 123, 255, 0.1);
    }

    .stats-card-primary .stats-icon {
        background: rgba(13, 110, 253, 0.1);
        color: #0d6efd;
    }

    .stats-card-success .stats-icon {
        background: rgba(25, 135, 84, 0.1);
        color: #198754;
    }

    .stats-card-info .stats-icon {
        background: rgba(13, 202, 240, 0.1);
        color: #0dcaf0;
    }

    .stats-card-warning .stats-icon {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }

    /* Table improvements */
    .rule-row:hover {
        background-color: rgba(0, 123, 255, 0.02);
        transform: translateX(2px);
        transition: all 0.2s ease;
    }

    .priority-badge {
        font-size: 0.8em;
        padding: 0.4em 0.8em;
        border-radius: 20px;
        font-weight: 600;
    }

    .priority-1, .priority-2, .priority-3 {
        background: linear-gradient(45deg, #dc3545, #fd7e14);
        color: white;
    }

    .priority-4, .priority-5 {
        background: linear-gradient(45deg, #ffc107, #fd7e14);
        color: #000;
    }

    .priority-6, .priority-7, .priority-8 {
        background: linear-gradient(45deg, #0dcaf0, #20c997);
        color: white;
    }

    .priority-9, .priority-10 {
        background: linear-gradient(45deg, #6c757d, #adb5bd);
        color: white;
    }

    .rule-type-badge {
        font-size: 0.75em;
        padding: 0.35em 0.65em;
        border-radius: 6px;
    }

    .btn-action {
        border-radius: 6px;
        padding: 0.375rem 0.75rem;
        transition: all 0.2s ease;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .status-toggle {
        min-width: 90px;
        border-radius: 20px;
        transition: all 0.3s ease;
    }

    .status-toggle:hover {
        transform: scale(1.05);
    }

    .rule-description-preview {
        line-height: 1.5;
        color: #6c757d;
    }

    .view-full-desc {
        color: #0d6efd;
        text-decoration: none;
        font-weight: 500;
    }

    .view-full-desc:hover {
        text-decoration: underline;
    }

    /* Modal improvements */
    .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }

    .modal-header {
        border-bottom: 1px solid rgba(0,0,0,0.1);
        padding: 1.2rem 1.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        border-top: 1px solid rgba(0,0,0,0.1);
        padding: 1.2rem 1.5rem;
    }

    .rule-description-content {
        line-height: 1.7;
        font-size: 1.05em;
        color: #495057;
    }

    /* Empty state */
    .empty-state {
        padding: 4rem 2rem;
    }

    .empty-state-icon {
        opacity: 0.5;
        margin-bottom: 1.5rem;
    }

    /* Form improvements */
    .form-control, .form-select {
        border: 1px solid #ced4da;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
        transform: translateY(-1px);
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .stats-card .card-body {
            padding: 1rem;
        }
        
        .btn-group-sm .btn-action {
            padding: 0.25rem 0.5rem;
        }
        
        .modal-dialog {
            margin: 0.5rem;
        }
        
        .table-responsive {
            font-size: 0.9em;
        }
    }

    /* Animation for success alerts */
    @keyframes slideIn {
        from {
            transform: translateX(-100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .alert-success {
        animation: slideIn 0.3s ease-out;
    }

    /* Smooth transitions */
    .card, .btn, .form-control, .modal-content {
        transition: all 0.3s ease;
    }

    /* Better scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Print styles */
    @media print {
        .btn, .dropdown, .modal, .alert {
            display: none !important;
        }
        
        .card {
            border: 1px solid #dee2e6 !important;
            box-shadow: none !important;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>