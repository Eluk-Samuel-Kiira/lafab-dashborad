<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

// Define that we're including this file
define('INCLUDED', true);

// Force country to Rwanda for this sync
define('FORCE_COUNTRY', 'Rwanda');

// TABLE NAMES DEFINED HERE (not in config)
define('TABLE_JOBS_COMPANIES', 'icop0_js_job_companies');
define('TABLE_TEAM_COMPANIES', 'pc0ww__js_job_companies');
define('TABLE_SYNC_LOGS', 'pc0ww_company_sync_logs');

// Handle delete log action - MUST BE AT THE VERY TOP
if (isset($_GET['action']) && $_GET['action'] === 'delete_log' && isset($_GET['id'])) {
    $logId = (int)$_GET['id'];
    $deleteMessage = '';
    
    try {
        $teamDb = Database::getInstance(DB_TEAM_NAME);
        
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
        header("Location: sync_companies_rw.php?message=" . urlencode($deleteMessage));
        exit();
        
    } catch (Exception $e) {
        $deleteMessage = "error:❌ Failed to delete log: " . $e->getMessage();
        header("Location: sync_companies_rw.php?message=" . urlencode($deleteMessage));
        exit();
    }
}

// Handle delete all logs action - MUST BE BEFORE ANY OUTPUT
if (isset($_POST['action']) && $_POST['action'] === 'delete_all_logs') {
    $deleteMessage = '';
    
    try {
        $teamDb = Database::getInstance(DB_TEAM_NAME);
        
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
        header("Location: sync_companies_rw.php?message=" . urlencode($deleteMessage));
        exit();
        
    } catch (Exception $e) {
        $deleteMessage = "error:❌ Failed to delete logs: " . $e->getMessage();
        header("Location: sync_companies_rw.php?message=" . urlencode($deleteMessage));
        exit();
    }
}

// NOW include the header and sidebar files AFTER all redirect logic
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Create logs table if not exists
function createLogsTable($teamDb) {
    // First check if logs table exists
    $check_sql = "SHOW TABLES LIKE '" . TABLE_SYNC_LOGS . "'";
    $result = $teamDb->query($check_sql);
    
    if ($result->num_rows == 0) {
        // Create new table with country field
        $sql = "CREATE TABLE " . TABLE_SYNC_LOGS . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sync_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            country VARCHAR(50) DEFAULT 'Rwanda',
            total_companies INT DEFAULT 0,
            new_companies INT DEFAULT 0,
            updated_companies INT DEFAULT 0,
            errors INT DEFAULT 0,
            processing_time DECIMAL(5,2) DEFAULT 0,
            log_details TEXT,
            INDEX idx_sync_date (sync_date),
            INDEX idx_country (country)
        ) ENGINE=InnoDB DEFAULT CHARSET=" . DB_CHARSET;
        
        return $teamDb->query($sql);
    } else {
        // Table exists, check if country column exists
        $check_column = "SHOW COLUMNS FROM " . TABLE_SYNC_LOGS . " LIKE 'country'";
        $column_result = $teamDb->query($check_column);
        
        if ($column_result->num_rows == 0) {
            // Add country column to existing table
            $alter_sql = "ALTER TABLE " . TABLE_SYNC_LOGS . " ADD COLUMN country VARCHAR(50) DEFAULT 'Rwanda' AFTER sync_date";
            $teamDb->query($alter_sql);
            
            // Add index for country
            $index_sql = "ALTER TABLE " . TABLE_SYNC_LOGS . " ADD INDEX idx_country (country)";
            $teamDb->query($index_sql);
        }
        return true;
    }
}

// Function to force country field to be Rwanda
function forceCountryToRwanda($company) {
    // Force country field to be Rwanda
    $company['country'] = FORCE_COUNTRY;
    
    // Update state if it contains other country names
    if (isset($company['state']) && !empty($company['state'])) {
        $company['state'] = str_ireplace(['Uganda', 'Kenya', 'Tanzania', 'Zambia', 'Malawi'], FORCE_COUNTRY, $company['state']);
    }
    
    // Update location if it contains other country names
    if (isset($company['location']) && !empty($company['location'])) {
        $company['location'] = str_ireplace(['Uganda', 'Kenya', 'Tanzania', 'Zambia', 'Malawi'], FORCE_COUNTRY, $company['location']);
    }
    
    // Update city if it contains other country names
    if (isset($company['city']) && !empty($company['city'])) {
        $company['city'] = str_ireplace(['Uganda', 'Kenya', 'Tanzania', 'Zambia', 'Malawi'], FORCE_COUNTRY, $company['city']);
    }
    
    return $company;
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
            // Force country to Rwanda
            $company = forceCountryToRwanda($company);
            
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
            // Force country to Rwanda
            $company = forceCountryToRwanda($company);
            
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
        $jobDb = Database::getInstance(DB_JOBS_NAME);
        $teamDb = Database::getInstance(DB_TEAM_NAME);
        
        // Create logs table if not exists (or update it)
        createLogsTable($teamDb);
        
        // Get team table columns
        $teamColumns = $teamDb->getTableColumns(TABLE_TEAM_COMPANIES);
        
        // Add source_id column if it doesn't exist
        if (!in_array('source_id', $teamColumns)) {
            $teamDb->query("ALTER TABLE " . TABLE_TEAM_COMPANIES . " ADD COLUMN source_id INT NULL AFTER id");
            $teamDb->query("ALTER TABLE " . TABLE_TEAM_COMPANIES . " ADD INDEX idx_source_id (source_id)");
            $teamColumns = $teamDb->getTableColumns(TABLE_TEAM_COMPANIES); // Refresh
        }
        
        // Get ALL companies from Job site (no country filter since all are Rwanda)
        $result = $jobDb->query("SELECT * FROM " . TABLE_JOBS_COMPANIES);
        
        $total = $result->num_rows;
        $new = 0;
        $updated = 0;
        $errors = 0;
        $log_details = [];
        
        $log_details[] = "=== RWANDA COMPANY SYNC STARTED " . date('Y-m-d H:i:s') . " ===";
        $log_details[] = "Total companies in source table: " . $total;
        $log_details[] = "Note: All companies are from Rwanda (table contains only Rwanda data)";
        $log_details[] = "Team table has " . count($teamColumns) . " columns";
        $log_details[] = "Country enforcement: All companies will be set to '" . FORCE_COUNTRY . "'";
        
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
        
        $log_details[] = "Found " . count($existingIds) . " existing companies in team site";
        
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
        
        // Save log with country info
        $log_details[] = "=== RWANDA SYNC COMPLETED IN {$processing_time}s ===";
        $log_details[] = "Total: $total | New: $new | Updated: $updated | Errors: $errors";
        $log_details[] = "All companies forced to country: '" . FORCE_COUNTRY . "'";
        
        $log_text = implode("\n", $log_details);
        $log_sql = "INSERT INTO " . TABLE_SYNC_LOGS . " 
                   (country, total_companies, new_companies, updated_companies, errors, processing_time, log_details) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $teamDb->preparedQuery($log_sql, [FORCE_COUNTRY, $total, $new, $updated, $errors, $processing_time, $log_text]);
        
        $success = "✅ Rwanda Company Sync completed in {$processing_time} seconds!<br>
                   • Total processed: $total companies<br>
                   • New companies: $new<br>
                   • Updated companies: $updated<br>
                   • Errors: $errors<br>
                   • All companies set to: " . FORCE_COUNTRY;
        
    } catch (Exception $e) {
        $error = "❌ Rwanda Company Sync failed: " . $e->getMessage();
        
        // Log error if possible
        if (isset($teamDb)) {
            try {
                $log_text = "=== RWANDA SYNC FAILED " . date('Y-m-d H:i:s') . " ===\n";
                $log_text .= "Error: " . $e->getMessage() . "\n";
                $log_text .= "=== SYNC ABORTED ===";
                
                $teamDb->preparedQuery(
                    "INSERT INTO " . TABLE_SYNC_LOGS . " (country, total_companies, errors, log_details) VALUES (?, ?, ?, ?)",
                    [FORCE_COUNTRY, 0, 1, $log_text]
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

// Get Rwanda sync logs only
try {
    $logsDb = Database::getInstance(DB_TEAM_NAME);
    $logs = [];
    
    $result = $logsDb->preparedQuery(
        "SELECT * FROM " . TABLE_SYNC_LOGS . " 
         WHERE country = ? 
         ORDER BY sync_date DESC LIMIT " . MAX_LOG_ROWS,
        [FORCE_COUNTRY]
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
    $statsJobDb = Database::getInstance(DB_JOBS_NAME);
    $statsTeamDb = Database::getInstance(DB_TEAM_NAME);
    
    // Job site count (all are Rwanda)
    $result = $statsJobDb->query("SELECT COUNT(*) as total FROM " . TABLE_JOBS_COMPANIES);
    $jobCount = $result->fetch_assoc()['total'];
    
    // Team site count for Rwanda
    $result = $statsTeamDb->preparedQuery(
        "SELECT COUNT(*) as total FROM " . TABLE_TEAM_COMPANIES . " WHERE country = ?",
        [FORCE_COUNTRY]
    );
    $teamCount = $result->fetch_assoc()['total'];
    
    // Team site synced count for Rwanda (with source_id)
    $result = $statsTeamDb->preparedQuery(
        "SELECT COUNT(*) as synced FROM " . TABLE_TEAM_COMPANIES . " 
         WHERE country = ? AND source_id IS NOT NULL",
        [FORCE_COUNTRY]
    );
    $syncedCount = $result->fetch_assoc()['synced'];
    
    // Pending sync count
    $pendingCount = $jobCount - $syncedCount;
    if ($pendingCount < 0) $pendingCount = 0;
    
    $statsJobDb->close();
    $statsTeamDb->close();
    
} catch (Exception $e) {
    $jobCount = $teamCount = $syncedCount = $pendingCount = 0;
}
?>


<!-- RWANDA-SPECIFIC UI -->
<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-flag"></i> Rwanda Company Sync</h1>
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

    <!-- Rwanda Info Alert -->
    <div class="alert alert-info">
        <h5><i class="fas fa-flag"></i> Rwanda-Specific Sync</h5>
        <p class="mb-0">
            <strong>Important:</strong> The source table contains only Rwanda companies. <br>
            No country filter is applied during sync. Country is forced to 'Rwanda' during insertion.
        </p>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body text-center">
                    <h3><?php echo $jobCount; ?></h3>
                    <p>Source Table Companies</p>
                    <small>(All are Rwanda companies)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body text-center">
                    <h3><?php echo $teamCount; ?></h3>
                    <p>TeamSite Rwanda Companies</p>
                    <small>With country = 'Rwanda'</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body text-center">
                    <h3><?php echo $syncedCount; ?></h3>
                    <p>Synced Rwanda Companies</p>
                    <small>With source_id</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white <?php echo $pendingCount > 0 ? 'bg-warning' : 'bg-secondary'; ?>">
                <div class="card-body text-center">
                    <h3><?php echo $pendingCount; ?></h3>
                    <p>Pending Sync</p>
                    <small>To be copied</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Info -->
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle"></i> Rwanda Sync Features:</h5>
        <ul class="mb-0">
            <li><strong>No country filter</strong> - Source table contains only Rwanda companies</li>
            <li><strong>Country enforcement</strong> - Forces country field to 'Rwanda' during sync</li>
            <li><strong>Location cleanup</strong> - Updates state, location, city fields to Rwanda</li>
            <li><strong>Bulk operations</strong> - Processes in chunks of 100 records</li>
            <li><strong>Transaction support</strong> - Faster commits and rollback safety</li>
            <li><strong>ON DUPLICATE KEY UPDATE</strong> - Single query for insert/update</li>
        </ul>
    </div>

    <!-- Database Info -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-database"></i> Database Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Source (JobSite):</h6>
                    <table class="table table-sm">
                        <tr><th>Database:</th><td><?php echo DB_JOBS_NAME; ?></td></tr>
                        <tr><th>Table:</th><td><?php echo TABLE_JOBS_COMPANIES; ?></td></tr>
                        <tr><th>Note:</th><td><span class="text-success">Only contains Rwanda companies</span></td></tr>
                        <tr><th>Total Companies:</th><td><?php echo $jobCount; ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Destination (TeamSite):</h6>
                    <table class="table table-sm">
                        <tr><th>Database:</th><td><?php echo DB_TEAM_NAME; ?></td></tr>
                        <tr><th>Table:</th><td><?php echo TABLE_TEAM_COMPANIES; ?></td></tr>
                        <tr><th>Country Enforcement:</th><td>All set to 'Rwanda'</td></tr>
                        <tr><th>Rwanda Companies:</th><td><?php echo $teamCount; ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Button -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-sync"></i> Rwanda Sync Action</h5>
        </div>
        <div class="card-body text-center">
            <?php if ($pendingCount > 0): ?>
                <p class="lead">Ready to sync <?php echo $pendingCount; ?> Rwanda companies</p>
                <p class="text-muted"><?php echo $jobCount; ?> companies in source table, <?php echo $syncedCount; ?> already synced</p>
            <?php else: ?>
                <p class="lead">All companies are already synced</p>
                <p class="text-muted">You can still sync to update any changes</p>
            <?php endif; ?>
            
            <form method="POST" id="syncForm">
                <input type="hidden" name="action" value="sync">
                <button type="submit" class="btn btn-primary btn-lg" id="syncButton">
                    <i class="fas fa-bolt"></i> Sync All Rwanda Companies
                </button>
            </form>
            
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-exclamation-circle"></i> 
                    All companies will be set to country = 'Rwanda' during sync
                </small>
            </div>
        </div>
    </div>

    <!-- Rwanda Sync Logs -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-history"></i> Rwanda Sync Logs</h5>
            <?php if (!empty($logs)): ?>
                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAllModal">
                    <i class="fas fa-trash-alt"></i> Delete All Rwanda Logs
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p>No Rwanda sync logs yet. Perform your first Rwanda sync to see logs here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr class="table-light">
                                <th>Date & Time</th>
                                <th>Country</th>
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
                                    <td><span class="badge bg-primary"><?php echo $log['country'] ?? 'Rwanda'; ?></span></td>
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
                <h5 class="modal-title">Rwanda Company Sync Log Details</h5>
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
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Delete All Rwanda Logs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="lead">Are you sure you want to delete ALL Rwanda sync logs?</p>
                <p>This action will permanently delete <?php echo count($logs); ?> Rwanda log entries.</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Warning:</strong> This action cannot be undone!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="delete_all_logs">
                    <button type="submit" class="btn btn-danger">Delete All Rwanda Logs</button>
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
        else if (line.includes('Rwanda')) className = 'fw-bold text-primary';
        else if (line.includes('forced to country')) className = 'fw-bold text-success';
        
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
    return confirm('Are you sure you want to delete this Rwanda log entry?\nThis action cannot be undone.');
}

// Add loading animation to sync button
document.getElementById('syncForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('syncButton');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing Rwanda Companies...';
    btn.disabled = true;
    
    // Show progress alert
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-info mt-3';
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm me-2"></div>
            <div>
                <strong>Rwanda company sync in progress...</strong><br>
                <small>Processing <?php echo $jobCount; ?> companies. Please wait...</small>
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