<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Initialize variables
$success = '';
$error = '';
$editing = false;
$current_rule = null;

// Static PIN for protection
define('REQUIRED_PIN', 'Samuel@13');

/**
 * Lightweight formatter for rule descriptions
 * Converts markdown-like syntax to HTML safely
 */
function formatRuleDescription($text) {
    if (empty($text)) return '';
    
    // First escape HTML to prevent XSS
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Replace **bold** with <strong>
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    
    // Replace *italic* with <em>
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    
    // Replace bullet points: • or - or * at start of line
    $text = preg_replace('/^[•\-*]\s+(.*?)$/m', '<li>$1</li>', $text);
    
    // Wrap consecutive list items in <ul>
    $text = preg_replace('/(<li>.*?<\/li>\n?)+/', '<ul class="mb-2">$0</ul>', $text);
    
    // Replace numbered lists: 1. 2. etc
    $text = preg_replace('/^(\d+)\.\s+(.*?)$/m', '<li value="$1">$2</li>', $text);
    $text = preg_replace('/(<li value="\d+">.*?<\/li>\n?)+/', '<ol class="mb-2">$0</ol>', $text);
    
    // Replace line breaks with <br> for single newlines, but keep paragraphs
    $text = nl2br($text, false);
    
    // Clean up: remove empty paragraphs and fix spacing
    $text = str_replace('<br><br>', '</p><p>', $text);
    $text = '<div class="formatted-description">' . $text . '</div>';
    
    // Wrap consecutive <br> tags as paragraphs
    $text = preg_replace('/<br>\s*<br>/', '</p><p>', $text);
    
    return $text;
}

/**
 * Strip formatting for plain text preview
 */
function stripFormatting($text) {
    $text = str_replace('**', '', $text);
    $text = str_replace('*', '', $text);
    return $text;
}

// Handle delete request - requires PIN
if (isset($_POST['delete_rule_id']) && isset($_POST['auth_pin'])) {
    $auth_pin = $_POST['auth_pin'];
    if ($auth_pin === REQUIRED_PIN) {
        $delete_id = intval($_POST['delete_rule_id']);
        
        try {
            $sql = "DELETE FROM qr_rules WHERE id = ?";
            if (db_query($sql, [$delete_id])) {
                $success = "QA rule deleted successfully!";
            } else {
                $error = "Error deleting QA rule!";
            }
        } catch (Exception $e) {
            $error = "Error deleting: " . $e->getMessage();
        }
    } else {
        $error = "Invalid PIN! Rule not deleted.";
    }
}

// Handle toggle active status - requires PIN
if (isset($_POST['toggle_rule_id']) && isset($_POST['auth_pin'])) {
    $auth_pin = $_POST['auth_pin'];
    if ($auth_pin === REQUIRED_PIN) {
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
    } else {
        $error = "Invalid PIN! Status not updated.";
    }
}

// Handle form submission for adding/editing rules
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && !isset($_POST['delete_rule_id']) && !isset($_POST['toggle_rule_id'])) {
    $id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : null;
    
    // For editing existing rules, require PIN
    if ($id > 0 && (!isset($_POST['auth_pin']) || $_POST['auth_pin'] !== REQUIRED_PIN)) {
        $error = "Invalid PIN! Cannot edit rule.";
    } else {
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
                    } else {
                        $error = "Error adding rule!";
                    }
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
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
            <button type="button" class="btn btn-outline-success" onclick="openAddRuleModal()">
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

    <!-- Stats Cards -->
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
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
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

    <!-- QA Rules Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i> QA Rules List
                    <span class="badge bg-light text-dark ms-2"><?php echo $total_rules; ?></span>
                </h6>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($qr_rules)): ?>
                <div class="text-center py-5 empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-qrcode fa-4x text-muted"></i>
                    </div>
                    <h4 class="mt-4 text-muted">No QA Rules Found</h4>
                    <button type="button" class="btn btn-primary" onclick="openAddRuleModal()">
                        <i class="fas fa-plus-circle me-2"></i> Create First Rule
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50">Prio</th>
                                <th>Rule Details</th>
                                <th width="120">Type</th>
                                <th width="100">Filters</th>
                                <th width="100">Status</th>
                                <th width="140">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qr_rules as $rule): ?>
                                <tr class="rule-row">
                                    <td class="text-center">
                                        <span class="badge priority-badge priority-<?php echo $rule['priority']; ?>">
                                            <?php echo $rule['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <h6 class="mb-1 rule-title">
                                                <i class="fas fa-gavel text-primary me-2"></i>
                                                <?php echo htmlspecialchars($rule['title']); ?>
                                            </h6>
                                            <div class="rule-description-preview small text-muted">
                                                <?php 
                                                $plainDesc = strip_tags(str_replace('**', '', $rule['description']));
                                                echo strlen($plainDesc) > 100 ? substr($plainDesc, 0, 100) . '...' : $plainDesc;
                                                ?>
                                                <?php if (strlen($plainDesc) > 100): ?>
                                                    <a href="#" class="view-full-desc ms-1"
                                                       data-description="<?php echo htmlspecialchars($rule['description']); ?>"
                                                       data-title="<?php echo htmlspecialchars($rule['title']); ?>"
                                                       data-website_filter="<?php echo $rule['website_filter']; ?>"
                                                       data-poster_filter="<?php echo $rule['poster_filter']; ?>"
                                                       data-effective_date="<?php echo $rule['effective_date']; ?>"
                                                       data-expiry_date="<?php echo $rule['expiry_date']; ?>"
                                                       data-created_by="<?php echo $rule['created_by']; ?>"
                                                       data-created_at="<?php echo $rule['created_at']; ?>"
                                                       data-min_job_count="<?php echo $rule['min_job_count']; ?>"
                                                       data-max_job_count="<?php echo $rule['max_job_count']; ?>">
                                                        <small>Read more</small>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($rule['created_by']); ?>
                                                <i class="fas fa-clock ms-2 me-1"></i><?php echo date('M j, Y', strtotime($rule['created_at'])); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
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
                                    <td>
                                        <div class="small">
                                            <?php if ($rule['website_filter'] != 'all'): ?>
                                                <span class="badge bg-light text-dark mb-1 d-block">
                                                    <i class="fas fa-globe me-1"></i><?php echo htmlspecialchars($rule['website_filter']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($rule['poster_filter'] != 'all'): ?>
                                                <span class="badge bg-light text-dark d-block">
                                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($rule['poster_filter']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($rule['website_filter'] == 'all' && $rule['poster_filter'] == 'all'): ?>
                                                <span class="text-muted">All</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" class="d-inline" id="toggleForm_<?php echo $rule['id']; ?>" onsubmit="return false;">
                                            <input type="hidden" name="toggle_rule_id" value="<?php echo $rule['id']; ?>">
                                            <input type="hidden" name="auth_pin" id="togglePin_<?php echo $rule['id']; ?>" value="">
                                            <button type="button" class="btn btn-sm status-toggle <?php echo $rule['is_active'] ? 'btn-success' : 'btn-secondary'; ?>" 
                                                    onclick="requirePinForToggle(<?php echo $rule['id']; ?>, <?php echo $rule['is_active']; ?>)">
                                                <?php if ($rule['is_active']): ?>
                                                    <i class="fas fa-toggle-on me-1"></i> Active
                                                <?php else: ?>
                                                    <i class="fas fa-toggle-off me-1"></i> Inactive
                                                <?php endif; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick='viewRuleDetails(<?php echo json_encode($rule); ?>)'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick='requirePinForEdit(<?php echo json_encode($rule); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick='requirePinForDelete(<?php echo $rule['id']; ?>, "<?php echo addslashes($rule['title']); ?>")'>
                                                <i class="fas fa-trash"></i>
                                            </button>
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
</div>

<!-- View Full Description Modal -->
<div class="modal fade" id="viewDescriptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt me-2"></i> Rule Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="rule-details-container">
                    <h4 id="detailTitle" class="mb-3"></h4>
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-2">Description</h6>
                            <div id="detailDescription" class="formatted-description"></div>
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

<!-- Add/Edit Rule Modal -->
<div class="modal fade" id="editRuleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editRuleModalLabel">
                    <i class="fas fa-plus-circle me-2"></i> Add New QA Rule
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="ruleForm" onsubmit="return validateAndSubmit()">
                <input type="hidden" name="rule_id" id="rule_id" value="">
                <input type="hidden" name="auth_pin" id="editAuthPin" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Rule Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="rule_title" class="form-control" required
                                       placeholder="Enter rule title">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Rule Type</label>
                                <select name="rule_type" id="rule_type" class="form-select">
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
                            rows="10" placeholder="Write your rule description here..."></textarea>
                        <div class="form-text small mt-2">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Formatting Guide:</strong> Use **bold**, *italic*, 
                            • for bullet points, 1. for numbered lists.
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Priority</label>
                                <select name="priority" id="rule_priority" class="form-select">
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
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Website Filter</label>
                                <select name="website_filter" id="website_filter" class="form-select">
                                    <option value="all">All Websites</option>
                                    <option value="example.com">Example.com</option>
                                    <option value="test.com">Test.com</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Poster Filter</label>
                                <select name="poster_filter" id="poster_filter" class="form-select">
                                    <option value="all">All Posters</option>
                                    <option value="admin">Admin</option>
                                    <option value="user">User</option>
                                </select>
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
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Min Jobs</label>
                                <input type="number" name="min_job_count" id="min_job_count" 
                                       class="form-control" min="0" placeholder="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Max Jobs</label>
                                <input type="number" name="max_job_count" id="max_job_count" 
                                       class="form-control" min="1" placeholder="Unlimited">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Effective Date</label>
                                <input type="date" name="effective_date" id="effective_date" 
                                       class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Expiry Date</label>
                                <input type="date" name="expiry_date" id="expiry_date" 
                                       class="form-control" placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Live Preview Section -->
                    <div class="card mt-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-eye"></i> Live Preview (Formatted Output)</h6>
                        </div>
                        <div class="card-body">
                            <div id="livePreview" class="formatted-description preview-box">
                                <div class="text-muted">Start typing to see formatted preview...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EasyMDE CSS and JS from CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>

<style>
/* Formatted description styles */
.formatted-description {
    line-height: 1.6;
    font-size: 0.95rem;
}

.formatted-description strong {
    color: #2c3e50;
    font-weight: 600;
}

.formatted-description em {
    font-style: italic;
    color: #5a6e85;
}

.formatted-description ul, 
.formatted-description ol {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

.formatted-description li {
    margin: 0.25rem 0;
}

.formatted-description p {
    margin: 0.5rem 0;
}

.preview-box {
    min-height: 120px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
}

/* Stats cards */
.stats-card-primary { border-left: 4px solid #0d6efd; background: #f8f9fa; }
.stats-card-success { border-left: 4px solid #198754; background: #f8f9fa; }
.stats-card-info { border-left: 4px solid #0dcaf0; background: #f8f9fa; }
.stats-card-warning { border-left: 4px solid #ffc107; background: #f8f9fa; }

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stats-card-primary .stats-icon { background: rgba(13,110,253,0.1); color: #0d6efd; }
.stats-card-success .stats-icon { background: rgba(25,135,84,0.1); color: #198754; }
.stats-card-info .stats-icon { background: rgba(13,202,240,0.1); color: #0dcaf0; }
.stats-card-warning .stats-icon { background: rgba(255,193,7,0.1); color: #ffc107; }

.priority-badge {
    font-size: 0.8rem;
    padding: 0.3rem 0.7rem;
    border-radius: 20px;
    font-weight: 600;
}

.priority-1, .priority-2, .priority-3 { background: #dc3545; color: white; }
.priority-4, .priority-5 { background: #ffc107; color: #000; }
.priority-6, .priority-7, .priority-8 { background: #0dcaf0; color: #000; }
.priority-9, .priority-10 { background: #6c757d; color: white; }

.rule-type-badge { font-size: 0.7rem; padding: 0.3rem 0.6rem; border-radius: 6px; }
.status-toggle { min-width: 90px; border-radius: 20px; }
.rule-row:hover { background-color: rgba(0,123,255,0.02); }

/* EasyMDE customization */
.EasyMDEContainer .CodeMirror {
    border-radius: 8px;
    border: 1px solid #ced4da;
}

.EasyMDEContainer .editor-toolbar {
    border-radius: 8px 8px 0 0;
    border: 1px solid #ced4da;
    border-bottom: none;
}

.EasyMDEContainer .editor-toolbar button {
    border-radius: 4px;
}

.EasyMDEContainer .editor-toolbar button:hover {
    background: #e9ecef;
}

.modal-xl {
    max-width: 1200px;
}
</style>

<script>
// Initialize EasyMDE for the description field
let easyMDE = null;
let currentRule = null;
const REQUIRED_PIN = 'Samuel@13';

// Formatter function for live preview (mirrors PHP formatter)
function formatDescriptionForPreview(text) {
    if (!text || text.trim() === '') return '<span class="text-muted">Start typing to see formatted preview...</span>';
    
    // Escape HTML
    let formatted = text.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
    
    // Bold
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    // Italic
    formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
    // Bullet points
    formatted = formatted.replace(/^[•\-*]\s+(.*?)$/gm, '<li>$1</li>');
    formatted = formatted.replace(/(<li>.*?<\/li>\n?)+/g, '<ul class="mb-2">$&</ul>');
    // Numbered lists
    formatted = formatted.replace(/^(\d+)\.\s+(.*?)$/gm, '<li value="$1">$2</li>');
    formatted = formatted.replace(/(<li value="\d+">.*?<\/li>\n?)+/g, '<ol class="mb-2">$&</ol>');
    // Line breaks
    formatted = formatted.replace(/\n/g, '<br>');
    
    return formatted;
}

// Update live preview from EasyMDE
function updateLivePreview() {
    const preview = document.getElementById('livePreview');
    if (preview && easyMDE) {
        const markdown = easyMDE.value();
        preview.innerHTML = formatDescriptionForPreview(markdown);
    }
}

// Validate and submit form
function validateAndSubmit() {
    let description = '';
    if (easyMDE) {
        description = easyMDE.value();
    } else {
        description = document.getElementById('rule_description').value;
    }
    
    const title = document.getElementById('rule_title').value.trim();
    const ruleId = document.getElementById('rule_id').value;
    
    if (!title) {
        alert('Please enter a rule title');
        document.getElementById('rule_title').focus();
        return false;
    }
    
    if (!description || description.trim() === '') {
        alert('Please enter a rule description');
        if (easyMDE) {
            easyMDE.codemirror.focus();
        }
        return false;
    }
    
    // For editing existing rules, require PIN
    if (ruleId && ruleId !== '') {
        const pin = prompt('Enter PIN to edit this rule:');
        if (pin !== REQUIRED_PIN) {
            alert('Invalid PIN! Rule not saved.');
            return false;
        }
        document.getElementById('editAuthPin').value = pin;
    }
    
    // Set the textarea value before submit
    document.getElementById('rule_description').value = description;
    return true;
}

// Require PIN for toggle status
function requirePinForToggle(ruleId, currentStatus) {
    const pin = prompt('Enter security PIN to change rule status:');
    if (pin === null) return;
    
    if (pin === REQUIRED_PIN) {
        const form = document.getElementById('toggleForm_' + ruleId);
        const pinInput = document.getElementById('togglePin_' + ruleId);
        pinInput.value = pin;
        form.submit();
    } else {
        alert('Invalid PIN! Status not changed.');
    }
}

// Require PIN for delete
function requirePinForDelete(ruleId, ruleTitle) {
    const pin = prompt('Enter security PIN to delete this rule:');
    if (pin === null) return;
    
    if (pin === REQUIRED_PIN) {
        if (confirm(`Are you sure you want to delete "${ruleTitle}"? This action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const ruleIdInput = document.createElement('input');
            ruleIdInput.type = 'hidden';
            ruleIdInput.name = 'delete_rule_id';
            ruleIdInput.value = ruleId;
            
            const pinInput = document.createElement('input');
            pinInput.type = 'hidden';
            pinInput.name = 'auth_pin';
            pinInput.value = pin;
            
            form.appendChild(ruleIdInput);
            form.appendChild(pinInput);
            document.body.appendChild(form);
            form.submit();
        }
    } else {
        alert('Invalid PIN! Rule not deleted.');
    }
}

// Require PIN for edit
function requirePinForEdit(rule) {
    const pin = prompt('Enter security PIN to edit this rule:');
    if (pin === null) return;
    
    if (pin === REQUIRED_PIN) {
        loadRuleForEdit(rule);
    } else {
        alert('Invalid PIN! Cannot edit rule.');
    }
}

// View rule details
function viewRuleDetails(rule) {
    currentRule = rule;
    document.getElementById('detailTitle').textContent = rule.title;
    document.getElementById('detailDescription').innerHTML = formatDescriptionForPreview(rule.description);
    
    let filtersHtml = '';
    if (rule.website_filter === 'all' && rule.poster_filter === 'all') {
        filtersHtml = '<p class="mb-0">Applies to all websites and posters</p>';
    } else {
        if (rule.website_filter !== 'all') filtersHtml += `<p><i class="fas fa-globe me-2"></i>Website: ${rule.website_filter}</p>`;
        if (rule.poster_filter !== 'all') filtersHtml += `<p><i class="fas fa-user me-2"></i>Poster: ${rule.poster_filter}</p>`;
    }
    if (rule.min_job_count > 0) filtersHtml += `<p><i class="fas fa-sort-numeric-up me-2"></i>Min Jobs: ${rule.min_job_count}</p>`;
    if (rule.max_job_count) filtersHtml += `<p><i class="fas fa-sort-numeric-down me-2"></i>Max Jobs: ${rule.max_job_count}</p>`;
    document.getElementById('detailFilters').innerHTML = filtersHtml || '<p class="text-muted">No filters</p>';
    
    let datesHtml = `<p><i class="fas fa-calendar-check me-2"></i>Effective: ${new Date(rule.effective_date).toLocaleDateString()}</p>`;
    if (rule.expiry_date) {
        datesHtml += `<p><i class="fas fa-calendar-times me-2"></i>Expires: ${new Date(rule.expiry_date).toLocaleDateString()}</p>`;
    } else {
        datesHtml += `<p><i class="fas fa-infinity me-2"></i>No expiry</p>`;
    }
    datesHtml += `<p class="small text-muted mt-2"><i class="fas fa-user me-1"></i>${rule.created_by} · ${new Date(rule.created_at).toLocaleString()}</p>`;
    document.getElementById('detailDates').innerHTML = datesHtml;
    
    const modal = new bootstrap.Modal(document.getElementById('viewDescriptionModal'));
    modal.show();
}

// Edit current rule from view modal
function editCurrentRule() {
    if (currentRule) {
        bootstrap.Modal.getInstance(document.getElementById('viewDescriptionModal')).hide();
        setTimeout(() => requirePinForEdit(currentRule), 300);
    }
}

// Load rule for editing
function loadRuleForEdit(rule) {
    document.getElementById('editRuleModalLabel').innerHTML = `<i class="fas fa-edit me-2"></i> Edit: ${rule.title.substring(0, 40)}`;
    document.getElementById('rule_id').value = rule.id;
    document.getElementById('rule_title').value = rule.title;
    document.getElementById('rule_type').value = rule.rule_type;
    document.getElementById('rule_priority').value = rule.priority;
    document.getElementById('min_job_count').value = rule.min_job_count || 0;
    document.getElementById('max_job_count').value = rule.max_job_count || '';
    document.getElementById('is_active').checked = rule.is_active == 1;
    document.getElementById('website_filter').value = rule.website_filter || 'all';
    document.getElementById('poster_filter').value = rule.poster_filter || 'all';
    document.getElementById('effective_date').value = rule.effective_date;
    document.getElementById('expiry_date').value = rule.expiry_date || '';
    
    // Set EasyMDE content
    if (easyMDE) {
        easyMDE.value(rule.description);
        setTimeout(updateLivePreview, 100);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('editRuleModal'));
    modal.show();
}

// Open add rule modal
function openAddRuleModal() {
    document.getElementById('editRuleModalLabel').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Add New QA Rule';
    document.getElementById('ruleForm').reset();
    document.getElementById('rule_id').value = '';
    document.getElementById('effective_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('rule_priority').value = '5';
    document.getElementById('is_active').checked = true;
    document.getElementById('website_filter').value = 'all';
    document.getElementById('poster_filter').value = 'all';
    document.getElementById('min_job_count').value = '0';
    document.getElementById('max_job_count').value = '';
    document.getElementById('rule_title').value = '';
    document.getElementById('editAuthPin').value = '';
    
    // Clear EasyMDE content
    if (easyMDE) {
        easyMDE.value('');
        updateLivePreview();
    }
    
    const modal = new bootstrap.Modal(document.getElementById('editRuleModal'));
    modal.show();
}

// Apply quick rules template
function applyQuickRules() {
    const quickDesc = `**Rule Objective:** Ensure consistent daily job posting activity from all posting members.

**Requirements:**
• All posting members must post between **250 to 300 jobs** daily
• Minimum required: **250 jobs** per day
• Maximum allowed: **300 jobs** per day
• Applies to **all regular posting members** (non-admin)

**Compliance Criteria:**
✅ Member posts 250-300 jobs in a single day
❌ Member posts <250 jobs (under-quota)
❌ Member posts >300 jobs (over-quota)`;

    document.getElementById('rule_title').value = 'Daily Posting Quota - General Members';
    document.getElementById('rule_type').value = 'compliance';
    document.getElementById('rule_priority').value = '3';
    document.getElementById('min_job_count').value = '250';
    document.getElementById('max_job_count').value = '300';
    document.getElementById('website_filter').value = 'all';
    document.getElementById('poster_filter').value = 'all';
    
    if (easyMDE) {
        easyMDE.value(quickDesc);
        setTimeout(updateLivePreview, 100);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('editRuleModal'));
    modal.show();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize EasyMDE on the textarea
    const textarea = document.getElementById('rule_description');
    if (textarea) {
        easyMDE = new EasyMDE({
            element: textarea,
            autoDownloadFontAwesome: false,
            spellChecker: false,
            placeholder: "Write your rule description here... Use **bold**, *italic*, bullet points, and numbered lists.",
            toolbar: [
                "bold", "italic", "heading", "|",
                "unordered-list", "ordered-list", "|",
                "link", "image", "|",
                "preview", "side-by-side", "fullscreen", "|",
                "guide"
            ],
            renderingConfig: {
                singleLineBreaks: true,
                codeSyntaxHighlighting: false,
            },
            previewRender: function(plainText) {
                return formatDescriptionForPreview(plainText);
            }
        });
        
        // Update live preview when content changes
        easyMDE.codemirror.on('change', function() {
            updateLivePreview();
        });
    }
    
    // Handle "Read more" links
    document.querySelectorAll('.view-full-desc').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const rule = {
                title: this.getAttribute('data-title'),
                description: this.getAttribute('data-description'),
                website_filter: this.getAttribute('data-website_filter'),
                poster_filter: this.getAttribute('data-poster_filter'),
                effective_date: this.getAttribute('data-effective_date'),
                expiry_date: this.getAttribute('data-expiry_date'),
                created_by: this.getAttribute('data-created_by'),
                created_at: this.getAttribute('data-created_at'),
                min_job_count: this.getAttribute('data-min_job_count'),
                max_job_count: this.getAttribute('data-max_job_count')
            };
            viewRuleDetails(rule);
        });
    });
    
    // Ensure preview updates when modal is shown
    const editModal = document.getElementById('editRuleModal');
    if (editModal) {
        editModal.addEventListener('shown.bs.modal', function() {
            if (easyMDE) {
                easyMDE.codemirror.refresh();
                setTimeout(updateLivePreview, 100);
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>