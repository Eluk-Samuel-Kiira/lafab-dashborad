<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

// Define that we're including this file
define('INCLUDED', true);

// TABLE NAMES DEFINED HERE (not in config)
define('TABLE_JOBS_COMPANIES', 'icop0_js_job_companies');
define('TABLE_TEAM_COMPANIES', 'pc0ww__js_job_companies');
define('TABLE_SYNC_LOGS', 'pc0ww_company_sync_logs');

// Handle delete log action - MUST BE AT THE VERY TOP
if (isset($_GET['action']) && $_GET['action'] === 'delete_log' && isset($_GET['id'])) {
    $logId = (int)$_GET['id'];
    $deleteMessage = '';
    
    try {
        $teamDb = Database::getInstance(DB_TEAM);
        
        // Delete the log
        $delete_sql = "DELETE FROM " . TABLE_SYNC_LOGS . " WHERE id = ?";
        $teamDb->preparedQuery($delete_sql, [$logId]);
        
        $affectedRows = $teamDb->getAffectedRows();
        
        if ($affectedRows > 0) {
            $deleteMessage = "success:✅ Log entry deleted successfully!";
        } else {
            $deleteMessage = "error:❌ Log entry not found or already deleted.";
        }
        
        $teamDb->close();
        
        // Redirect with message
        header("Location: sync_companies.php?message=" . urlencode($deleteMessage));
        exit();
        
    } catch (Exception $e) {
        $deleteMessage = "error:❌ Failed to delete log: " . $e->getMessage();
        header("Location: sync_companies.php?message=" . urlencode($deleteMessage));
        exit();
    }
}

// Handle delete all logs action - MUST BE BEFORE ANY OUTPUT
if (isset($_POST['action']) && $_POST['action'] === 'delete_all_logs') {
    $deleteMessage = '';
    
    try {
        $teamDb = Database::getInstance(DB_TEAM);
        
        // Count logs before deletion
        $count_result = $teamDb->query("SELECT COUNT(*) as total FROM " . TABLE_SYNC_LOGS);
        $count = $count_result->fetch_assoc()['total'];
        
        // Delete all logs
        $delete_sql = "DELETE FROM " . TABLE_SYNC_LOGS;
        $teamDb->query($delete_sql);
        
        $affectedRows = $teamDb->getAffectedRows();
        
        if ($affectedRows > 0) {
            $deleteMessage = "success:✅ All $count log entries deleted successfully!";
        } else {
            $deleteMessage = "info:ℹ️ No logs to delete.";
        }
        
        $teamDb->close();
        
        // Redirect with message
        header("Location: sync_companies.php?message=" . urlencode($deleteMessage));
        exit();
        
    } catch (Exception $e) {
        $deleteMessage = "error:❌ Failed to delete logs: " . $e->getMessage();
        header("Location: sync_companies.php?message=" . urlencode($deleteMessage));
        exit();
    }
}

// NOW include the header and sidebar files AFTER all redirect logic
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Create logs table if not exists
function createLogsTable($teamDb) {
    $sql = "CREATE TABLE IF NOT EXISTS " . TABLE_SYNC_LOGS . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sync_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        total_companies INT DEFAULT 0,
        new_companies INT DEFAULT 0,
        updated_companies INT DEFAULT 0,
        errors INT DEFAULT 0,
        processing_time DECIMAL(5,2) DEFAULT 0,
        log_details TEXT,
        INDEX idx_sync_date (sync_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . DB_CHARSET;
    
    return $teamDb->query($sql);
}

// Bulk insert function
function bulkInsertCompanies($teamDb, $companies, $teamColumns) {
    if (empty($companies)) return 0;
    
    $now = date('Y-m-d H:i:s');
    $inserted = 0;
    
    // Process in chunks
    $chunks = array_chunk($companies, BULK_CHUNK_SIZE_INSERT);
    
    foreach ($chunks as $chunk) {
        $values = [];
        
        foreach ($chunk as $company) {
            $rowValues = [];
            
            // Build values based on team table columns
            foreach ($teamColumns as $column) {
                if ($column === 'source_id') {
                    $rowValues[] = (int)$company['id'];
                } elseif ($column === 'last_sync') {
                    $rowValues[] = $teamDb->escape($now);
                } elseif ($column === 'created' && !isset($company['created'])) {
                    $rowValues[] = $teamDb->escape($now);
                } elseif (isset($company[$column])) {
                    $rowValues[] = $teamDb->escape($company[$column]);
                } else {
                    $rowValues[] = 'NULL';
                }
            }
            
            $values[] = '(' . implode(', ', $rowValues) . ')';
        }
        
        if (empty($values)) continue;
        
        $sql = "INSERT INTO " . TABLE_TEAM_COMPANIES . " (" . implode(', ', $teamColumns) . ") 
                VALUES " . implode(', ', $values);
        
        $teamDb->query($sql);
        $inserted += $teamDb->getAffectedRows();
    }
    
    return $inserted;
}

// Bulk update function using INSERT ... ON DUPLICATE KEY UPDATE
function bulkUpdateCompanies($teamDb, $companies, $teamColumns) {
    if (empty($companies)) return 0;
    
    $now = date('Y-m-d H:i:s');
    $updated = 0;
    
    // Process in chunks
    $chunks = array_chunk($companies, BULK_CHUNK_SIZE_UPDATE);
    
    foreach ($chunks as $chunk) {
        $values = [];
        
        foreach ($chunk as $company) {
            $rowValues = [];
            
            foreach ($teamColumns as $column) {
                if ($column === 'id') {
                    $rowValues[] = (int)$company[$column];
                } elseif ($column === 'last_sync') {
                    $rowValues[] = $teamDb->escape($now);
                } elseif (isset($company[$column])) {
                    $rowValues[] = $teamDb->escape($company[$column]);
                } else {
                    $rowValues[] = 'NULL';
                }
            }
            
            $values[] = '(' . implode(', ', $rowValues) . ')';
        }
        
        if (empty($values)) continue;
        
        $sql = "INSERT INTO " . TABLE_TEAM_COMPANIES . " (" . implode(', ', $teamColumns) . ") 
                VALUES " . implode(', ', $values) . "
                ON DUPLICATE KEY UPDATE ";
        
        $updates = [];
        foreach ($teamColumns as $column) {
            if ($column !== 'id' && $column !== 'source_id') {
                $updates[] = "$column = VALUES($column)";
            }
        }
        
        $sql .= implode(', ', $updates);
        
        $teamDb->query($sql);
        // For ON DUPLICATE KEY, affected_rows returns 1 for insert, 2 for update
        $updated += $teamDb->getAffectedRows() > 0 ? count($chunk) : 0;
    }
    
    return $updated;
}

// Handle sync action
if (isset($_POST['action']) && $_POST['action'] === 'sync') {
    $success = '';
    $error = '';
    $start_time = microtime(true);
    
    $jobDb = null;
    $teamDb = null;
    
    try {
        // Get database instances using credentials from config
        $jobDb = Database::getInstance(DB_JOBS);
        $teamDb = Database::getInstance(DB_TEAM);
        
        // Create logs table if not exists
        createLogsTable($teamDb);
        
        // Get team table columns
        $teamColumns = $teamDb->getTableColumns(TABLE_TEAM_COMPANIES);
        
        // Add source_id column if it doesn't exist
        if (!in_array('source_id', $teamColumns)) {
            $teamDb->query("ALTER TABLE " . TABLE_TEAM_COMPANIES . " ADD COLUMN source_id INT NULL AFTER id");
            $teamDb->query("ALTER TABLE " . TABLE_TEAM_COMPANIES . " ADD INDEX idx_source_id (source_id)");
            $teamColumns = $teamDb->getTableColumns(TABLE_TEAM_COMPANIES); // Refresh
        }
        
        // Get ALL companies from Job site
        $result = $jobDb->query("SELECT * FROM " . TABLE_JOBS_COMPANIES);
        $total = $result->num_rows;
        $new = 0;
        $updated = 0;
        $errors = 0;
        $log_details = [];
        
        $log_details[] = "=== SYNC STARTED " . date('Y-m-d H:i:s') . " ===";
        $log_details[] = "Total companies to sync: " . $total;
        $log_details[] = "Team table has " . count($teamColumns) . " columns";
        
        // Fetch all companies at once
        $allCompanies = [];
        while ($company = $result->fetch_assoc()) {
            $allCompanies[$company['id']] = $company;
        }
        
        // Get existing company IDs from team site (BULK)
        $existingIds = [];
        
        if (!empty($allCompanies)) {
            $sourceIds = array_keys($allCompanies);
            $chunks = array_chunk($sourceIds, BULK_CHUNK_SIZE_FETCH);
            
            foreach ($chunks as $chunk) {
                $idsList = implode(',', $chunk);
                $check_sql = "SELECT id, source_id FROM " . TABLE_TEAM_COMPANIES . " WHERE source_id IN ($idsList)";
                $check_result = $teamDb->query($check_sql);
                
                if ($check_result) {
                    while ($row = $check_result->fetch_assoc()) {
                        $existingIds[$row['source_id']] = $row['id'];
                    }
                }
            }
        }
        
        $log_details[] = "Found " . count($existingIds) . " existing companies";
        
        // Separate new and existing companies
        $newCompanies = [];
        $updateCompanies = [];
        
        foreach ($allCompanies as $sourceId => $company) {
            if (isset($existingIds[$sourceId])) {
                $company['id'] = $existingIds[$sourceId];
                $updateCompanies[] = $company;
            } else {
                $newCompanies[] = $company;
            }
        }
        
        $log_details[] = "New companies to insert: " . count($newCompanies);
        $log_details[] = "Companies to update: " . count($updateCompanies);
        
        // Start transaction
        $teamDb->beginTransaction();
        
        try {
            // Bulk insert new companies
            if (!empty($newCompanies)) {
                $new = bulkInsertCompanies($teamDb, $newCompanies, $teamColumns);
                $log_details[] = "✓ Bulk inserted $new new companies";
            }
            
            // Bulk update existing companies
            if (!empty($updateCompanies)) {
                $updated = bulkUpdateCompanies($teamDb, $updateCompanies, $teamColumns);
                $log_details[] = "✓ Bulk updated $updated companies";
            }
            
            // Commit transaction
            $teamDb->commit();
            
        } catch (Exception $e) {
            // Rollback on error
            $teamDb->rollback();
            throw $e;
        }
        
        // Calculate processing time
        $processing_time = round(microtime(true) - $start_time, 2);
        
        // Save log
        $log_details[] = "=== SYNC COMPLETED IN {$processing_time}s ===";
        $log_details[] = "Total: $total | New: $new | Updated: $updated | Errors: $errors";
        
        $log_text = implode("\n", $log_details);
        $log_sql = "INSERT INTO " . TABLE_SYNC_LOGS . " 
                   (total_companies, new_companies, updated_companies, errors, processing_time, log_details) 
                   VALUES (?, ?, ?, ?, ?, ?)";
        
        $teamDb->preparedQuery($log_sql, [$total, $new, $updated, $errors, $processing_time, $log_text]);
        
        $success = "✅ Sync completed in {$processing_time} seconds!<br>
                   • Total processed: $total companies<br>
                   • New companies: $new<br>
                   • Updated companies: $updated<br>
                   • Errors: $errors";
        
    } catch (Exception $e) {
        $error = "❌ Sync failed: " . $e->getMessage();
        
        // Log error if possible
        if (isset($teamDb)) {
            try {
                $log_text = "=== SYNC FAILED " . date('Y-m-d H:i:s') . " ===\n";
                $log_text .= "Error: " . $e->getMessage() . "\n";
                $log_text .= "=== SYNC ABORTED ===";
                
                $teamDb->preparedQuery(
                    "INSERT INTO " . TABLE_SYNC_LOGS . " (total_companies, errors, log_details) VALUES (?, ?, ?)",
                    [0, 1, $log_text]
                );
            } catch (Exception $logError) {
                // Ignore log errors
            }
        }
    } finally {
        // Clean up connections
        if ($jobDb) $jobDb->close();
        if ($teamDb) $teamDb->close();
    }
}

// Check for delete messages in URL
$deleteMessage = '';
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    if (strpos($message, 'success:') === 0) {
        $deleteMessage = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> ' . htmlspecialchars(substr($message, 8)) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } elseif (strpos($message, 'error:') === 0) {
        $deleteMessage = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars(substr($message, 6)) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } elseif (strpos($message, 'info:') === 0) {
        $deleteMessage = '<div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle"></i> ' . htmlspecialchars(substr($message, 5)) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
}

// Get sync logs
try {
    $logsDb = Database::getInstance(DB_TEAM);
    $logs = [];
    
    $result = $logsDb->query(
        "SELECT * FROM " . TABLE_SYNC_LOGS . " ORDER BY sync_date DESC LIMIT " . MAX_LOG_ROWS
    );
    
    if ($result) {
        while ($log = $result->fetch_assoc()) {
            $logs[] = $log;
        }
    }
    $logsDb->close();
} catch (Exception $e) {
    $logs = [];
}

// Get statistics
try {
    $statsJobDb = Database::getInstance(DB_JOBS);
    $statsTeamDb = Database::getInstance(DB_TEAM);
    
    // Job site count
    $result = $statsJobDb->query("SELECT COUNT(*) as total FROM " . TABLE_JOBS_COMPANIES);
    $jobCount = $result->fetch_assoc()['total'];
    
    // Team site count and synced count
    $result = $statsTeamDb->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN source_id IS NOT NULL THEN 1 END) as synced
        FROM " . TABLE_TEAM_COMPANIES
    );
    
    $row = $result->fetch_assoc();
    $teamCount = $row['total'];
    $syncedCount = $row['synced'];
    
    $pending = $jobCount - $syncedCount;
    
    $statsJobDb->close();
    $statsTeamDb->close();
    
} catch (Exception $e) {
    $jobCount = $teamCount = $syncedCount = $pending = 0;
}
?>

<!-- RESTORE YOUR ORIGINAL UI STRUCTURE -->
<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-sync-alt"></i> Complete Company Sync</h1>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="window.location.href='dashboard.php'">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
        </div>
    </div>

    <?php 
    // Show delete message if exists
    echo $deleteMessage;
    
    // Show sync success/error messages
    if (!empty($success)): ?>
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

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body text-center">
                    <h3><?php echo $jobCount; ?></h3>
                    <p>Job Site Companies</p>
                    <small>All companies</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body text-center">
                    <h3><?php echo $teamCount; ?></h3>
                    <p>Team Site Companies</p>
                    <small>Total in team DB</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body text-center">
                    <h3><?php echo $syncedCount; ?></h3>
                    <p>Synced Companies</p>
                    <small>With source_id</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white <?php echo $pending > 0 ? 'bg-warning' : 'bg-secondary'; ?>">
                <div class="card-body text-center">
                    <h3><?php echo $pending; ?></h3>
                    <p>Pending Sync</p>
                    <small>To be copied</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Info -->
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle"></i> Optimized Sync Features:</h5>
        <ul class="mb-0">
            <li><strong>Dynamic column mapping</strong> - Automatically detects table structure</li>
            <li><strong>Bulk operations</strong> - Processes in chunks of 100 records</li>
            <li><strong>Transaction support</strong> - Faster commits and rollback safety</li>
            <li><strong>Auto-adds source_id column</strong> - Creates tracking column if missing</li>
            <li><strong>ON DUPLICATE KEY UPDATE</strong> - Single query for insert/update</li>
        </ul>
    </div>

    <!-- Sync Button -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-sync"></i> Sync Action</h5>
        </div>
        <div class="card-body text-center">
            <?php if ($pending > 0): ?>
                <p class="lead">Ready to sync <?php echo $pending; ?> companies</p>
                <p class="text-muted"><?php echo $jobCount; ?> total at Job site, <?php echo $syncedCount; ?> already synced</p>
            <?php else: ?>
                <p class="lead">All companies are already synced</p>
                <p class="text-muted">You can still sync to update any changes</p>
            <?php endif; ?>
            
            <form method="POST" id="syncForm">
                <input type="hidden" name="action" value="sync">
                <button type="submit" class="btn btn-primary btn-lg" id="syncButton">
                    <i class="fas fa-bolt"></i> Fast Sync All Companies
                </button>
            </form>
            
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-table"></i> 
                    Automatically maps columns between databases
                </small>
            </div>
        </div>
    </div>

    <!-- Sync Logs -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-history"></i> Recent Sync Logs</h5>
            <?php if (!empty($logs)): ?>
                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAllModal">
                    <i class="fas fa-trash-alt"></i> Delete All Logs
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p>No sync logs yet. Perform your first sync to see logs here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr class="table-light">
                                <th>Date & Time</th>
                                <th>Total</th>
                                <th>New</th>
                                <th>Updated</th>
                                <th>Errors</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('M j, Y', strtotime($log['sync_date'])); ?></strong><br>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($log['sync_date'])); ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo $log['total_companies']; ?></span></td>
                                    <td><span class="badge bg-success">+<?php echo $log['new_companies']; ?></span></td>
                                    <td><span class="badge bg-warning"><?php echo $log['updated_companies']; ?></span></td>
                                    <td>
                                        <span class="badge <?php echo $log['errors'] > 0 ? 'bg-danger' : 'bg-light text-dark'; ?>">
                                            <?php echo $log['errors']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $log['processing_time']; ?>s</span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info view-log-btn" 
                                                    data-log='<?php echo htmlspecialchars(json_encode($log['log_details']), ENT_QUOTES, 'UTF-8'); ?>'>
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <a href="?action=delete_log&id=<?php echo $log['id']; ?>" 
                                               class="btn btn-outline-danger delete-log-btn"
                                               onclick="return confirmDeleteLog()">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
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

<!-- Log Details Modal -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Complete Sync Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="logDetails" style="max-height: 500px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="copyLog()">
                    <i class="fas fa-copy"></i> Copy Log
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete All Confirmation Modal -->
<div class="modal fade" id="deleteAllModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Delete All Logs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="lead">Are you sure you want to delete ALL sync logs?</p>
                <p>This action will permanently delete <?php echo count($logs); ?> log entries.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Warning:</strong> This action cannot be undone!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="delete_all_logs">
                    <button type="submit" class="btn btn-danger">Delete All Logs</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Fix for view log button - using event delegation
document.addEventListener('DOMContentLoaded', function() {
    // Use event delegation for dynamically loaded content
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.view-log-btn')) {
            const button = e.target.closest('.view-log-btn');
            const logJson = button.getAttribute('data-log');
            
            try {
                // Parse the JSON string
                const logText = JSON.parse(logJson);
                viewLogDetails(logText);
            } catch (error) {
                console.error('Error parsing log JSON:', error);
                // Fallback: try to use the raw attribute (though it may be truncated)
                viewLogDetails(button.getAttribute('data-log') || 'Error loading log');
            }
        }
    });
    
    // Add event listener for delete buttons to show loading
    document.querySelectorAll('.delete-log-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (confirmDeleteLog()) {
                // Show loading on the button
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                btn.classList.add('disabled');
                
                // The page will redirect after deletion
            } else {
                e.preventDefault();
            }
        });
    });
});

function viewLogDetails(logText) {
    const logElement = document.getElementById('logDetails');
    
    // Format the log with colors
    let formattedLog = '';
    const lines = logText.split('\n');
    
    lines.forEach(line => {
        let className = '';
        if (line.includes('✓')) className = 'text-success fw-bold';
        else if (line.includes('✗')) className = 'text-danger fw-bold';
        else if (line.includes('===')) className = 'fw-bold border-top pt-2';
        
        const escapedLine = line.replace(/</g, '&lt;').replace(/>/g, '&gt;');
        formattedLog += `<div class="${className}">${escapedLine}</div>`;
    });
    
    logElement.innerHTML = formattedLog;
    
    const modal = new bootstrap.Modal(document.getElementById('logModal'));
    modal.show();
}

function copyLog() {
    const logText = document.getElementById('logDetails').innerText;
    navigator.clipboard.writeText(logText).then(() => {
        // Show a subtle notification instead of alert
        const copyBtn = document.querySelector('#logModal .btn-info');
        const originalHtml = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        copyBtn.classList.remove('btn-info');
        copyBtn.classList.add('btn-success');
        
        setTimeout(() => {
            copyBtn.innerHTML = originalHtml;
            copyBtn.classList.remove('btn-success');
            copyBtn.classList.add('btn-info');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy: ', err);
        alert('Failed to copy log to clipboard');
    });
}

// Delete confirmation function
function confirmDeleteLog() {
    return confirm('Are you sure you want to delete this log entry?\nThis action cannot be undone.');
}

// Add loading animation to sync button
document.getElementById('syncForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('syncButton');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fast Syncing...';
    btn.disabled = true;
    
    // Show progress alert
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-info mt-3';
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm me-2"></div>
            <div>
                <strong>Fast sync in progress...</strong><br>
                <small>Bulk processing <?php echo $jobCount; ?> companies. This will be much faster!</small>
            </div>
        </div>
    `;
    
    this.appendChild(alertDiv);
    
    // Re-enable button after 60 seconds (in case of error)
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alertDiv.remove();
    }, 60000);
});

// Auto-dismiss alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        setTimeout(() => {
            bsAlert.close();
        }, 5000);
    });
}, 5000);
</script>

<style>
.card {
    border-radius: 8px;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    border-radius: 8px 8px 0 0 !important;
}

.badge {
    font-size: 0.85em;
    padding: 0.35em 0.65em;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.03);
    transform: translateX(2px);
    transition: all 0.2s ease;
}

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1.1rem;
}

.alert-info {
    background-color: #e8f4fd;
    border-color: #b6e0fe;
    color: #0c5460;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.delete-log-btn:hover {
    background-color: #dc3545;
    color: white !important;
}

/* Animation for delete button */
@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

.fade-out {
    animation: fadeOut 0.5s ease-out;
}
</style>

<?php require_once '../includes/footer.php'; ?>