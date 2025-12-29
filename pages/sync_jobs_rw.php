<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

// Define that we're including this file
define('INCLUDED', true);

// Get current country from config
$current_country = CURRENT_COUNTRY;
$current_country_name = CURRENT_COUNTRY_NAME;

// TABLE NAMES DEFINED HERE
define('TABLE_TEAM_JOBS_EXPORT', 'pc0ww_JobsExport');  // Team site jobs table
define('TABLE_JOBS_JOBS', 'icop0_js_job_jobs');        // Job site jobs table
define('TABLE_JOB_SYNC_LOGS_COUNTRY', 'pc0ww_job_sync_logs_' . $current_country);

// Chunk sizes for processing
define('SMALL_CHUNK_SIZE_INSERT', 20);   // Small chunks for insert
define('SMALL_CHUNK_SIZE_UPDATE', 10);   // Small chunks for update
define('SMALL_CHUNK_SIZE_FETCH', 100);   // Small fetch size

// Default values for Rwanda
define('DEFAULT_COMPANY_ID', 4171);  // Default company ID for Rwanda
define('DEFAULT_USER_ID', 13206);    // Default user ID for Rwanda
define('DEFAULT_COMPANY', 'Not Set Yet');    // Default user ID for Rwanda

// Handle delete log action
if (isset($_GET['action']) && $_GET['action'] === 'delete_log' && isset($_GET['id'])) {
    $logId = (int)$_GET['id'];
    $deleteMessage = '';
    
    try {
        $teamDb = Database::getInstance(DB_TEAM_NAME);
        
        $delete_sql = "DELETE FROM " . TABLE_JOB_SYNC_LOGS_COUNTRY . " WHERE id = ?";
        $teamDb->preparedQuery($delete_sql, [$logId]);
        
        $affectedRows = $teamDb->getAffectedRows();
        
        if ($affectedRows > 0) {
            $deleteMessage = "success:✅ Log entry deleted successfully!";
        } else {
            $deleteMessage = "error:❌ Log entry not found or already deleted.";
        }
        
        $teamDb->close();
        
        header("Location: sync_jobs_rw.php?country=" . $current_country . "&message=" . urlencode($deleteMessage) . "&t=" . time());
        exit();
        
    } catch (Exception $e) {
        $deleteMessage = "error:❌ Failed to delete log: " . $e->getMessage();
        header("Location: sync_jobs_rw.php?country=" . $current_country . "&message=" . urlencode($deleteMessage) . "&t=" . time());
        exit();
    }
}

// Handle delete all logs action
if (isset($_POST['action']) && $_POST['action'] === 'delete_all_logs') {
    $deleteMessage = '';
    
    try {
        $teamDb = Database::getInstance(DB_TEAM_NAME);
        
        $count_result = $teamDb->query("SELECT COUNT(*) as total FROM " . TABLE_JOB_SYNC_LOGS_COUNTRY);
        $count = $count_result->fetch_assoc()['total'];
        
        $delete_sql = "DELETE FROM " . TABLE_JOB_SYNC_LOGS_COUNTRY;
        $teamDb->query($delete_sql);
        
        $affectedRows = $teamDb->getAffectedRows();
        
        if ($affectedRows > 0) {
            $deleteMessage = "success:✅ All $count log entries deleted successfully!";
        } else {
            $deleteMessage = "info:ℹ️ No logs to delete.";
        }
        
        $teamDb->close();
        
        header("Location: sync_jobs_rw.php?country=" . $current_country . "&message=" . urlencode($deleteMessage) . "&t=" . time());
        exit();
        
    } catch (Exception $e) {
        $deleteMessage = "error:❌ Failed to delete logs: " . $e->getMessage();
        header("Location: sync_jobs_rw.php?country=" . $current_country . "&message=" . urlencode($deleteMessage) . "&t=" . time());
        exit();
    }
}

// NOW include the header and sidebar files AFTER all redirect logic
require_once '../includes/header.php';
require_once '../includes/sidebar.php';


// Function to create logs table if not exists (country-specific) and insert first row
function createJobSyncLogsTable($teamDb) {
    $sql = "CREATE TABLE IF NOT EXISTS " . TABLE_JOB_SYNC_LOGS_COUNTRY . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sync_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        country VARCHAR(10) DEFAULT '" . CURRENT_COUNTRY . "',
        last_sync_id INT DEFAULT 4140,  -- Last team ID that was synced
        total_jobs INT DEFAULT 0,
        new_jobs INT DEFAULT 0,
        updated_jobs INT DEFAULT 0,
        errors INT DEFAULT 0,
        processing_time DECIMAL(5,2) DEFAULT 0,
        log_details TEXT,
        INDEX idx_sync_date (sync_date),
        INDEX idx_country (country),
        INDEX idx_last_sync_id (last_sync_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . DB_CHARSET;
    
    // Create the table
    $teamDb->query($sql);
    
    // Check if table is empty (just created) and insert first row
    $check_sql = "SELECT COUNT(*) as row_count FROM " . TABLE_JOB_SYNC_LOGS_COUNTRY;
    $check_result = $teamDb->query($check_sql);
    
    if ($check_result) {
        $row = $check_result->fetch_assoc();
        if ($row['row_count'] == 0) {
            // Insert first row with initial data
            $initial_log_text = "=== RWANDA JOB SYNC LOGS TABLE CREATED ===\n";
            $initial_log_text .= "Date: " . date('Y-m-d H:i:s') . "\n";
            $initial_log_text .= "Country: " . CURRENT_COUNTRY . "\n";
            $initial_log_text .= "Initial last_sync_id: 4140\n";
            $initial_log_text .= "Table ready for sync operations";
            
            $insert_sql = "INSERT INTO " . TABLE_JOB_SYNC_LOGS_COUNTRY . " 
                          (country, last_sync_id, total_jobs, new_jobs, updated_jobs, errors, processing_time, log_details) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $teamDb->preparedQuery($insert_sql, [
                CURRENT_COUNTRY,
                4140, // Always put the last id for that country posted on the team site here
                0,
                0,
                0,
                0,
                0.00,
                $initial_log_text
            ]);
        }
    }
    
    return true;
}

// Helper function to get table columns
function getTableColumns($db, $tableName) {
    $columns = [];
    $result = $db->query("SHOW COLUMNS FROM $tableName");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    
    return $columns;
}

// Helper function to get table columns EXCLUDING id for insert
function getTableColumnsForInsert($db, $tableName) {
    $columns = [];
    $result = $db->query("SHOW COLUMNS FROM $tableName");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Skip the auto-increment id column
            if ($row['Field'] !== 'id') {
                $columns[] = $row['Field'];
            }
        }
    }
    
    return $columns;
}

// Function to get field mapping between team and jobs tables
function getJobFieldMapping() {
    return [
        // Team table => Jobs table (mapped fields)
        'id' => 'job_id',
        'uid' => 'uid',
        'companyid' => 'companyid',
        'title' => 'title',
        'alias' => 'alias',
        'jobcategory' => 'jobcategory',
        'jobtype' => 'jobtype',
        'jobstatus' => 'jobstatus',
        'jobsalaryrange' => 'jobsalaryrange',
        'salaryrangetype' => 'salaryrangetype',
        'hidesalaryrange' => 'hidesalaryrange',
        'description' => 'description',
        'qualifications' => 'qualifications',
        'prefferdskills' => 'prefferdskills',
        'applyinfo' => 'applyinfo',
        'Company' => 'company',
        'country' => 'country',
        'state' => 'state',
        'county' => 'county',
        'city' => 'city',
        'zipcode' => 'zipcode',
        'address1' => 'address1',
        'address2' => 'address2',
        'companyurl' => 'companyurl',
        'contactname' => 'contactname',
        'contactphone' => 'contactphone',
        'contactemail' => 'contactemail',
        'showcontact' => 'showcontact',
        'noofjobs' => 'noofjobs',
        'reference' => 'reference',
        'duration' => 'duration',
        'heighestfinisheducation' => 'heighestfinisheducation',
        'created' => 'created',
        'created_by' => 'created_by',
        'modified' => 'modified',
        'modified_by' => 'modified_by',
        'hits' => 'hits',
        'experience' => 'experience',
        'startpublishing' => 'startpublishing',
        'stoppublishing' => 'stoppublishing',
        'departmentid' => 'departmentid',
        'shift' => 'shift',
        'sendemail' => 'sendemail',
        'metadescription' => 'metadescription',
        'metakeywords' => 'metakeywords',
        'agreement' => 'agreement',
        'ordering' => 'ordering',
        'aboutjobfile' => 'aboutjobfile',
        'status' => 'status',
        'educationminimax' => 'educationminimax',
        'educationid' => 'educationid',
        'mineducationrange' => 'mineducationrange',
        'maxeducationrange' => 'maxeducationrange',
        'iseducationminimax' => 'iseducationminimax',
        'degreetitle' => 'degreetitle',
        'careerlevel' => 'careerlevel',
        'experienceminimax' => 'experienceminimax',
        'experienceid' => 'experienceid',
        'minexperiencerange' => 'minexperiencerange',
        'maxexperiencerange' => 'maxexperiencerange',
        'isexperienceminimax' => 'isexperienceminimax',
        'experiencetext' => 'experiencetext',
        'workpermit' => 'workpermit',
        'requiredtravel' => 'requiredtravel',
        'agefrom' => 'agefrom',
        'ageto' => 'ageto',
        'salaryrangefrom' => 'salaryrangefrom',
        'salaryrangeto' => 'salaryrangeto',
        'gender' => 'gender',
        'video' => 'video',
        'map' => 'map',
        'packageid' => 'packageid',
        'paymenthistoryid' => 'paymenthistoryid',
        'subcategoryid' => 'subcategoryid',
        'currencyid' => 'currencyid',
        'jobid' => 'jobid',
        'longitude' => 'longitude',
        'latitude' => 'latitude',
        'isgoldjob' => 'isgoldjob',
        'isfeaturedjob' => 'isfeaturedjob',
        'notifications' => 'notifications',
        'raf_gender' => 'raf_gender',
        'raf_degreelevel' => 'raf_degreelevel',
        'raf_experience' => 'raf_experience',
        'raf_age' => 'raf_age',
        'raf_education' => 'raf_education',
        'raf_category' => 'raf_category',
        'raf_subcategory' => 'raf_subcategory',
        'raf_location' => 'raf_location',
        'serverstatus' => 'serverstatus',
        'serverid' => 'serverid',
        'joblink' => 'joblink',
        'jobapplylink' => 'jobapplylink',
        'params' => 'params',
    ];
}

// Function to map team data to jobs structure - FORCE COUNTRY TO RWANDA
function mapTeamJobToJobs($teamJob, $fieldMapping) {
    $mappedData = [];
    
    foreach ($fieldMapping as $teamField => $jobsField) {
        // Skip mapping team's 'id' to job site's 'id'
        if ($jobsField === 'id') {
            continue; // Skip - job site will auto-generate its own id
        }
        
        // Check if the field exists in team data AND is not null/empty
        $hasValue = isset($teamJob[$teamField]) && $teamJob[$teamField] !== null && $teamJob[$teamField] !== '';
        
        if ($hasValue) {
            // FORCE COUNTRY FIELD TO BE "Rwanda" (always override)
            if ($jobsField === 'country') {
                $mappedData[$jobsField] = 'Rwanda';
            } else {
                // Preserve team value for all other fields
                $mappedData[$jobsField] = $teamJob[$teamField];
            }
        } else {
            // Field is missing/null/empty in team data, apply defaults
            if ($jobsField === 'country') {
                $mappedData[$jobsField] = 'Rwanda';
            } elseif ($jobsField === 'uid') {
                $mappedData[$jobsField] = DEFAULT_USER_ID;
            } elseif ($jobsField === 'companyid') {
                $mappedData[$jobsField] = DEFAULT_COMPANY_ID;
            } else {
                // For other fields, use specific defaults
                $mappedData[$jobsField] = getDefaultValueForField($jobsField);
            }
        }
    }
    
    // Copy team's ID to job site's job_id field for tracking
    if (!empty($teamJob['id'])) {
        $mappedData['job_id'] = (int)$teamJob['id']; // Store team's id in job_id column
    }
    
    // Also copy team's ID to source_id for tracking
    if (!empty($teamJob['id'])) {
        $mappedData['source_id'] = (int)$teamJob['id']; // Store team's id in source_id column
    }
    
    return $mappedData;
}

function getDefaultValueForField($field) {
    $defaults = [
        'uid' => DEFAULT_USER_ID,
        'companyid' => DEFAULT_COMPANY_ID,
        'company' => DEFAULT_COMPANY,
        'reference' => '',
        'duration' => '',
        'created_by' => 0,
        'modified_by' => 0,
        'hits' => 0,
        'ordering' => 0,
        'status' => 1,
        'notifications' => 0,
    ];
    
    return $defaults[$field] ?? null;
}


// Optimized bulk insert jobs function with duplicate checking
function bulkInsertJobs($jobsDb, $jobs, $jobsColumns, &$log_details) {
    if (empty($jobs)) return 0;
    
    $now = date('Y-m-d H:i:s');
    $inserted = 0;
    $skipped_duplicates = 0;
    $total_chunks = ceil(count($jobs) / SMALL_CHUNK_SIZE_INSERT);
    
    $log_details[] = "Starting bulk insert of " . count($jobs) . " jobs in $total_chunks chunks...";
    
    $chunks = array_chunk($jobs, SMALL_CHUNK_SIZE_INSERT);
    $chunk_count = 0;
    
    foreach ($chunks as $chunk) {
        $chunk_count++;
        $values = [];
        $validJobs = []; // Store jobs that pass duplicate check
        
        // First, check for duplicates in this chunk
        $jobIds = [];
        foreach ($chunk as $job) {
            $jobId = (int)$job['id']; // Team job ID
            $jobIds[] = $jobId;
        }
        
        if (!empty($jobIds)) {
            $idsList = implode(',', $jobIds);
            $check_sql = "SELECT source_id FROM " . TABLE_JOBS_JOBS . " 
                         WHERE source_id IN ($idsList) 
                         AND sync_country = 'rw'";
            $check_result = $jobsDb->query($check_sql);
            
            $existingIds = [];
            if ($check_result) {
                while ($row = $check_result->fetch_assoc()) {
                    $existingIds[] = $row['source_id'];
                }
            }
            
            // Filter out duplicates
            foreach ($chunk as $job) {
                $jobId = (int)$job['id'];
                if (!in_array($jobId, $existingIds)) {
                    $validJobs[] = $job;
                } else {
                    $skipped_duplicates++;
                    $log_details[] = "  ⚠️ Skipping duplicate job (source_id: $jobId)";
                }
            }
        }
        
        if (empty($validJobs)) {
            $log_details[] = "  • Chunk $chunk_count: All jobs in chunk were duplicates";
            continue;
        }
        
        foreach ($validJobs as $job) {
            $rowValues = [];
            
            foreach ($jobsColumns as $column) {
                // =========== KEY FIX HERE ===========
                // SKIP the 'id' column - let MySQL auto-increment it
                if ($column === 'id') {
                    continue; // Skip this column entirely for INSERT
                }
                // ====================================
                
                if ($column === 'source_id') {
                    $rowValues[] = (int)$job['id']; // Use team job ID as source
                } elseif ($column === 'job_id') {
                    $rowValues[] = (int)$job['id']; // Use team job ID as job_id
                } elseif ($column === 'last_sync') {
                    $rowValues[] = $jobsDb->escape($now);
                } elseif ($column === 'sync_source') {
                    $rowValues[] = $jobsDb->escape('teamsite_export');
                } elseif ($column === 'sync_country') {
                    $rowValues[] = $jobsDb->escape(CURRENT_COUNTRY); // 'rw' for Rwanda
                } elseif ($column === 'created' && !isset($job['created'])) {
                    $rowValues[] = $jobsDb->escape($now);
                } elseif ($column === 'modified' && !isset($job['modified'])) {
                    $rowValues[] = $jobsDb->escape($now);
                } elseif (isset($job[$column])) {
                    $rowValues[] = $jobsDb->escape($job[$column]);
                } else {
                    $rowValues[] = 'NULL';
                }
            }
            
            $values[] = '(' . implode(', ', $rowValues) . ')';
        }
        
        if (empty($values)) continue;
        
        // We need to build the column list excluding 'id'
        $insertColumns = [];
        foreach ($jobsColumns as $column) {
            if ($column !== 'id') {
                $insertColumns[] = $column;
            }
        }
        
        $sql = "INSERT INTO " . TABLE_JOBS_JOBS . " (" . implode(', ', $insertColumns) . ") 
                VALUES " . implode(', ', $values);
        
        try {
            $jobsDb->query($sql);
            $chunk_inserted = $jobsDb->getAffectedRows();
            $inserted += $chunk_inserted;
            
            // Add progress log
            if ($chunk_count % 5 == 0 || $chunk_count == $total_chunks) {
                $log_details[] = "  • Chunk $chunk_count/$total_chunks: Inserted $chunk_inserted jobs (Total: $inserted, Skipped duplicates: $skipped_duplicates)";
            }
            
        } catch (Exception $e) {
            // Check if it's a duplicate entry error
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $log_details[] = "  ✗ Chunk $chunk_count failed due to duplicate entry. Retrying individually...";
                
                // Retry each job individually
                foreach ($validJobs as $job) {
                    try {
                        $individualValues = [];
                        foreach ($jobsColumns as $column) {
                            if ($column === 'id') {
                                continue; // Skip id column
                            }
                            
                            if ($column === 'source_id') {
                                $individualValues[] = (int)$job['id'];
                            } elseif ($column === 'job_id') {
                                $individualValues[] = (int)$job['id'];
                            } elseif ($column === 'last_sync') {
                                $individualValues[] = $jobsDb->escape($now);
                            } elseif ($column === 'sync_source') {
                                $individualValues[] = $jobsDb->escape('teamsite_export');
                            } elseif ($column === 'sync_country') {
                                $individualValues[] = $jobsDb->escape(CURRENT_COUNTRY);
                            } elseif (isset($job[$column])) {
                                $individualValues[] = $jobsDb->escape($job[$column]);
                            } else {
                                $individualValues[] = 'NULL';
                            }
                        }
                        
                        $individualSql = "INSERT IGNORE INTO " . TABLE_JOBS_JOBS . " (" . implode(', ', $insertColumns) . ") 
                                         VALUES (" . implode(', ', $individualValues) . ")";
                        
                        $jobsDb->query($individualSql);
                        if ($jobsDb->getAffectedRows() > 0) {
                            $inserted++;
                        }
                        
                    } catch (Exception $indError) {
                        $log_details[] = "    ✗ Failed to insert job {$job['id']}: " . $indError->getMessage();
                    }
                }
                
            } else {
                $log_details[] = "  ✗ Chunk $chunk_count failed: " . $e->getMessage();
                throw $e;
            }
        }
        
        // Small delay to prevent overwhelming MySQL
        usleep(200000); // 0.2 second delay
        
        // Free memory periodically
        if ($chunk_count % 10 == 0) {
            gc_collect_cycles();
        }
    }
    
    if ($skipped_duplicates > 0) {
        $log_details[] = "⚠️ Skipped $skipped_duplicates duplicate jobs (already exist in job site)";
    }
    
    return $inserted;
}



// Optimized bulk update jobs function with duplicate checking
function bulkUpdateJobs($jobsDb, $jobs, $jobsColumns, &$log_details) {
    if (empty($jobs)) return 0;
    
    $now = date('Y-m-d H:i:s');
    $updated = 0;
    $skipped_duplicates = 0;
    $total_chunks = ceil(count($jobs) / SMALL_CHUNK_SIZE_UPDATE);
    
    $log_details[] = "Starting bulk update of " . count($jobs) . " jobs in $total_chunks chunks...";
    
    $chunks = array_chunk($jobs, SMALL_CHUNK_SIZE_UPDATE);
    $chunk_count = 0;
    
    foreach ($chunks as $chunk) {
        $chunk_count++;
        $values = [];
        $validJobs = []; // Store jobs that exist in job site
        
        // First, verify these jobs exist in job site
        $jobIds = [];
        foreach ($chunk as $job) {
            $jobId = (int)$job['id']; // Team job ID
            $jobIds[] = $jobId;
        }
        
        if (!empty($jobIds)) {
            $idsList = implode(',', $jobIds);
            $check_sql = "SELECT source_id, id as jobs_id FROM " . TABLE_JOBS_JOBS . " 
                         WHERE source_id IN ($idsList) 
                         AND sync_country = 'rw'";
            $check_result = $jobsDb->query($check_sql);
            
            $existingJobs = [];
            if ($check_result) {
                while ($row = $check_result->fetch_assoc()) {
                    $existingJobs[$row['source_id']] = $row['jobs_id'];
                }
            }
            
            // Only include jobs that exist
            foreach ($chunk as $job) {
                $jobId = (int)$job['id'];
                if (isset($existingJobs[$jobId])) {
                    $job['jobs_id'] = $existingJobs[$jobId];
                    $validJobs[] = $job;
                } else {
                    $skipped_duplicates++;
                    $log_details[] = "  ⚠️ Skipping job $jobId for update (doesn't exist in job site)";
                }
            }
        }
        
        if (empty($validJobs)) {
            $log_details[] = "  • Chunk $chunk_count: No valid jobs to update";
            continue;
        }
        
        foreach ($validJobs as $job) {
            $rowValues = [];
            
            foreach ($jobsColumns as $column) {
                // For updates, we NEED to include the 'id' column to match existing records
                if ($column === 'id') {
                    $rowValues[] = (int)$job['jobs_id']; // Jobs table ID (from auto-increment)
                } elseif ($column === 'job_id') {
                    $rowValues[] = (int)$job['id']; // Team's ID stored in job_id column
                } elseif ($column === 'source_id') {
                    $rowValues[] = (int)$job['id']; // Team's ID stored in source_id column
                } elseif ($column === 'last_sync') {
                    $rowValues[] = $jobsDb->escape($now);
                } elseif ($column === 'sync_source') {
                    $rowValues[] = $jobsDb->escape('teamsite_export');
                } elseif ($column === 'sync_country') {
                    $rowValues[] = $jobsDb->escape(CURRENT_COUNTRY); // 'rw' for Rwanda
                } elseif (isset($job[$column])) {
                    $rowValues[] = $jobsDb->escape($job[$column]);
                } else {
                    $rowValues[] = 'NULL';
                }
            }
            
            $values[] = '(' . implode(', ', $rowValues) . ')';
        }
        
        if (empty($values)) continue;
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle duplicates gracefully
        $sql = "INSERT INTO " . TABLE_JOBS_JOBS . " (" . implode(', ', $jobsColumns) . ") 
                VALUES " . implode(', ', $values) . "
                ON DUPLICATE KEY UPDATE ";
        
        $updates = [];
        foreach ($jobsColumns as $column) {
            // Don't update the id, source_id, job_id columns on duplicate
            if ($column !== 'id' && $column !== 'source_id' && $column !== 'job_id' && 
                $column !== 'sync_source' && $column !== 'sync_country') {
                $updates[] = "$column = VALUES($column)";
            }
        }
        $updates[] = "last_sync = VALUES(last_sync)";
        $updates[] = "sync_country = VALUES(sync_country)";
        
        $sql .= implode(', ', $updates);
        
        try {
            $jobsDb->query($sql);
            $chunk_updated = $jobsDb->getAffectedRows() > 0 ? count($validJobs) : 0;
            $updated += $chunk_updated;
            
            // Add progress log
            if ($chunk_count % 5 == 0 || $chunk_count == $total_chunks) {
                $log_details[] = "  • Chunk $chunk_count/$total_chunks: Updated $chunk_updated jobs (Total: $updated, Skipped: $skipped_duplicates)";
            }
            
        } catch (Exception $e) {
            $log_details[] = "  ✗ Chunk $chunk_count failed: " . $e->getMessage();
            throw $e;
        }
        
        // Small delay to prevent overwhelming MySQL
        usleep(200000); // 0.2 second delay
        
        // Free memory periodically
        if ($chunk_count % 10 == 0) {
            gc_collect_cycles();
        }
    }
    
    if ($skipped_duplicates > 0) {
        $log_details[] = "⚠️ Skipped $skipped_duplicates jobs for update (not found in job site)";
    }
    
    return $updated;
}



// Update team table sync status
function updateTeamSyncStatus($teamDb, $teamJobId, $status, $error = null) {
    $now = date('Y-m-d H:i:s');
    
    if ($error) {
        $sql = "UPDATE " . TABLE_TEAM_JOBS_EXPORT . " 
                SET sync_status = ?, last_sync_attempt = ?, sync_error = ?, sync_country = ?
                WHERE id = ?";
        $teamDb->preparedQuery($sql, [$status, $now, $error, CURRENT_COUNTRY, $teamJobId]);
    } else {
        $sql = "UPDATE " . TABLE_TEAM_JOBS_EXPORT . " 
                SET sync_status = ?, last_sync_attempt = ?, sync_error = NULL, sync_country = ?
                WHERE id = ?";
        $teamDb->preparedQuery($sql, [$status, $now, CURRENT_COUNTRY, $teamJobId]);
    }
}

// Handle sync action for RWANDA ONLY
if (isset($_POST['action']) && $_POST['action'] === 'sync_jobs_rw') {
    $success = '';
    $error = '';
    $start_time = microtime(true);
    
    $teamDb = null;
    $jobsDb = null;
    
    try {
        // Set PHP limits to prevent timeouts
        set_time_limit(600); // 10 minutes
        ignore_user_abort(true);
        
        // Get database instances
        $teamDb = Database::getInstance(DB_TEAM_NAME);
        $jobsDb = Database::getInstance(DB_JOBS_NAME);
        
        // Set longer timeout for MySQL connections
        $teamDb->query("SET SESSION wait_timeout = 600");
        $teamDb->query("SET SESSION interactive_timeout = 600");
        $jobsDb->query("SET SESSION wait_timeout = 600");
        $jobsDb->query("SET SESSION interactive_timeout = 600");
        
        // Create country-specific logs table if not exists
        createJobSyncLogsTable($teamDb);
        
        // Get field mapping
        $fieldMapping = getJobFieldMapping();
        
        // Get jobs columns
        $jobsColumns = getTableColumns($jobsDb, TABLE_JOBS_JOBS);
        
        $log_details = [];
        $log_details[] = "=== RWANDA JOB SYNC STARTED - " . date('Y-m-d H:i:s') . " ===";
        $log_details[] = "Database: " . DB_JOBS_NAME;
        $log_details[] = "Table: " . TABLE_JOBS_JOBS;
        $log_details[] = "Filter: Country = 'Rwanda' in team site";
        $log_details[] = "Default Company ID: " . DEFAULT_COMPANY_ID;
        $log_details[] = "Default User ID: " . DEFAULT_USER_ID;
        
        // ====== GET LAST SYNC ID FOR RWANDA ======
        $log_details[] = "Getting last sync ID for Rwanda...";
        $last_sync_id = 0;
        
        $last_sync_query = "SELECT MAX(last_sync_id) as last_sync_id 
                           FROM " . TABLE_JOB_SYNC_LOGS_COUNTRY . " 
                           WHERE country = 'rw' 
                           AND errors = 0
                           ORDER BY sync_date DESC 
                           LIMIT 1";
        
        $result = $teamDb->query($last_sync_query);
        if ($result && $row = $result->fetch_assoc()) {
            $last_sync_id = (int)$row['last_sync_id'];
            $log_details[] = "✓ Last sync ID for Rwanda: " . $last_sync_id;
        } else {
            $log_details[] = "ℹ️ No previous sync found for Rwanda, starting from ID 0";
        }
        
        // ====== GET PENDING RWANDA JOBS ======
        $log_details[] = "Fetching pending Rwanda jobs (ID > $last_sync_id)...";
        
        $sql = "SELECT * FROM " . TABLE_TEAM_JOBS_EXPORT . " 
                WHERE Country = 'Rwanda'
                AND (sync_status = 'pending' OR sync_status IS NULL)
                AND id > $last_sync_id
                ORDER BY id ASC
                LIMIT 500";  // Process in batches of 500
        
        $result = $teamDb->query($sql);
        $total = $result->num_rows;
        
        if ($total == 0) {
            $log_details[] = "No new Rwanda jobs to sync since last sync ID: $last_sync_id";
            $success = "ℹ️ No new Rwanda jobs to sync since last sync.";
        } else {
            $log_details[] = "✓ Found $total new Rwanda jobs to sync (ID > $last_sync_id)";
            
            // Store all team jobs
            $allTeamJobs = [];
            $max_job_id = 0; // Track the highest ID we process
            
            while ($job = $result->fetch_assoc()) {
                $allTeamJobs[$job['id']] = $job;
                if ($job['id'] > $max_job_id) {
                    $max_job_id = $job['id'];
                }
            }
            
            // Get existing job IDs from Rwanda jobs site
            $existingJobs = [];
            $log_details[] = "Checking for existing Rwanda jobs in JobSite...";
            

            if (!empty($allTeamJobs)) {
                $sourceIds = array_keys($allTeamJobs);
                $chunks = array_chunk($sourceIds, SMALL_CHUNK_SIZE_FETCH);
                
                foreach ($chunks as $chunk) {
                    $idsList = implode(',', $chunk);
                    $check_sql = "SELECT id, source_id, job_id FROM " . TABLE_JOBS_JOBS . " 
                                WHERE source_id IN ($idsList) 
                                AND sync_country = 'rw'";
                    $check_result = $jobsDb->query($check_sql);
                    
                    if ($check_result) {
                        while ($row = $check_result->fetch_assoc()) {
                            $existingJobs[$row['source_id']] = [
                                'id' => $row['id'],
                                'job_id' => $row['job_id']
                            ];
                        }
                    }
                    
                    // Small delay between checks
                    usleep(50000);
                }
            }

            $log_details[] = "Found " . count($existingJobs) . " existing Rwanda jobs in jobs site";
            
            // Separate new and existing jobs
            $newJobs = [];
            $updateJobs = [];
            
            foreach ($allTeamJobs as $teamJobId => $teamJob) {
                if (isset($existingJobs[$teamJobId])) {
                    // For update, add the jobs site ID and mapped data
                    $mappedData = mapTeamJobToJobs($teamJob, $fieldMapping);
                    $mappedData['jobs_id'] = $existingJobs[$teamJobId]['id']; // Jobs table ID
                    $mappedData['id'] = $teamJobId; // Keep team ID for source_id
                    
                    // Preserve existing job_id if available
                    if (!empty($existingJobs[$teamJobId]['job_id'])) {
                        $mappedData['job_id'] = $existingJobs[$teamJobId]['job_id'];
                    }
                    
                    $updateJobs[] = $mappedData;
                } else {
                    // For insert
                    $mappedData = mapTeamJobToJobs($teamJob, $fieldMapping);
                    $mappedData['id'] = $teamJobId; // Team ID
                    $newJobs[] = $mappedData;
                }
            }
            
            $log_details[] = "New Rwanda jobs to insert: " . count($newJobs);
            $log_details[] = "Rwanda jobs to update: " . count($updateJobs);
            
            // Process inserts and updates
            $new = 0;
            $updated = 0;
            $errors = 0;
            
            // Insert new Rwanda jobs
            if (!empty($newJobs)) {
                $new = bulkInsertJobs($jobsDb, $newJobs, $jobsColumns, $log_details);
                $log_details[] = "✓ Completed bulk insert of $new new Rwanda jobs";
                
                // Update team sync status for new jobs
                $updateBatch = [];
                foreach ($newJobs as $job) {
                    $updateBatch[] = $job['id'];
                    
                    if (count($updateBatch) >= 50) {
                        $idsList = implode(',', $updateBatch);
                        $now = date('Y-m-d H:i:s');
                        $sql = "UPDATE " . TABLE_TEAM_JOBS_EXPORT . " 
                                SET sync_status = 'synced', last_sync_attempt = ?, sync_country = 'rw'
                                WHERE id IN ($idsList)";
                        $teamDb->preparedQuery($sql, [$now]);
                        $updateBatch = [];
                    }
                }
                
                if (!empty($updateBatch)) {
                    $idsList = implode(',', $updateBatch);
                    $now = date('Y-m-d H:i:s');
                    $sql = "UPDATE " . TABLE_TEAM_JOBS_EXPORT . " 
                            SET sync_status = 'synced', last_sync_attempt = ?, sync_country = 'rw'
                            WHERE id IN ($idsList)";
                    $teamDb->preparedQuery($sql, [$now]);
                }
            }
            
            // Update existing Rwanda jobs
            if (!empty($updateJobs)) {
                $updated = bulkUpdateJobs($jobsDb, $updateJobs, $jobsColumns, $log_details);
                $log_details[] = "✓ Completed bulk update of $updated Rwanda jobs";
                
                // Update team sync status for updated jobs
                $updateBatch = [];
                foreach ($updateJobs as $job) {
                    $updateBatch[] = $job['id'];
                    
                    if (count($updateBatch) >= 50) {
                        $idsList = implode(',', $updateBatch);
                        $now = date('Y-m-d H:i:s');
                        $sql = "UPDATE " . TABLE_TEAM_JOBS_EXPORT . " 
                                SET sync_status = 'synced', last_sync_attempt = ?, sync_country = 'rw'
                                WHERE id IN ($idsList)";
                        $teamDb->preparedQuery($sql, [$now]);
                        $updateBatch = [];
                    }
                }
                
                if (!empty($updateBatch)) {
                    $idsList = implode(',', $updateBatch);
                    $now = date('Y-m-d H:i:s');
                    $sql = "UPDATE " . TABLE_TEAM_JOBS_EXPORT . " 
                            SET sync_status = 'synced', last_sync_attempt = ?, sync_country = 'rw'
                            WHERE id IN ($idsList)";
                    $teamDb->preparedQuery($sql, [$now]);
                }
            }
            
            // Calculate processing time
            $processing_time = round(microtime(true) - $start_time, 2);
            
            // Save log to COUNTRY-SPECIFIC table with last_sync_id
            $log_details[] = "=== RWANDA JOB SYNC COMPLETED IN {$processing_time}s ===";
            $log_details[] = "Total new Rwanda jobs: $total | New: $new | Updated: $updated | Errors: $errors";
            $log_details[] = "Last sync ID processed: $max_job_id";
            
            $log_text = implode("\n", $log_details);
            $log_sql = "INSERT INTO " . TABLE_JOB_SYNC_LOGS_COUNTRY . " 
                       (country, last_sync_id, total_jobs, new_jobs, updated_jobs, errors, processing_time, log_details) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $teamDb->preparedQuery($log_sql, ['rw', $max_job_id, $total, $new, $updated, $errors, $processing_time, $log_text]);
            
            $success = "✅ Rwanda Job Sync completed in {$processing_time} seconds!<br>
                       • Last sync ID: <strong>$max_job_id</strong><br>
                       • Total new Rwanda jobs processed: $total jobs<br>
                       • New Rwanda jobs: $new<br>
                       • Updated Rwanda jobs: $updated<br>
                       • Errors: $errors<br>
                       • Default Company ID: " . DEFAULT_COMPANY_ID . "<br>
                       • Default User ID: " . DEFAULT_USER_ID;
        }
        
    } catch (Exception $e) {
        $error = "❌ Rwanda Job Sync failed: " . $e->getMessage();
        
        // Log error to country-specific table
        if (isset($teamDb)) {
            try {
                $log_text = "=== RWANDA JOB SYNC FAILED - " . date('Y-m-d H:i:s') . " ===\n";
                $log_text .= "Error: " . $e->getMessage() . "\n";
                $log_text .= "=== SYNC ABORTED ===";
                
                $teamDb->preparedQuery(
                    "INSERT INTO " . TABLE_JOB_SYNC_LOGS_COUNTRY . " (country, total_jobs, errors, log_details) VALUES (?, ?, ?, ?)",
                    ['rw', 0, 1, $log_text]
                );
            } catch (Exception $logError) {
                // Ignore log errors
            }
        }
    } finally {
        // Clean up connections
        if ($teamDb) $teamDb->close();
        if ($jobsDb) $jobsDb->close();
    }
}

// Check for messages in URL
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

// Get job statistics for RWANDA ONLY
try {
    $teamStatsDb = Database::getInstance(DB_TEAM_NAME);
    $jobsStatsDb = Database::getInstance(DB_JOBS_NAME);
    
    // Team site job count for Rwanda
    $result = $teamStatsDb->preparedQuery(
        "SELECT COUNT(*) as total FROM " . TABLE_TEAM_JOBS_EXPORT . " WHERE Country = ?",
        ['Rwanda']
    );
    $teamRwandaCount = $result->fetch_assoc()['total'];
    
    // Total jobs in teamsite (all countries)
    $result = $teamStatsDb->query("SELECT COUNT(*) as total FROM " . TABLE_TEAM_JOBS_EXPORT);
    $teamTotalCount = $result->fetch_assoc()['total'];
    
    // Get sync status counts from team for Rwanda
    $result = $teamStatsDb->preparedQuery("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN sync_status = 'synced' AND sync_country = 'rw' THEN 1 END) as synced,
            COUNT(CASE WHEN (sync_status = 'pending' OR sync_status IS NULL) AND Country = 'Rwanda' THEN 1 END) as pending,
            COUNT(CASE WHEN sync_status = 'failed' AND sync_country = 'rw' THEN 1 END) as failed
        FROM " . TABLE_TEAM_JOBS_EXPORT . " 
        WHERE Country = 'Rwanda'",
        []
    );
    $teamSyncStats = $result->fetch_assoc();
    
    // Get last sync ID for Rwanda
    $result = $teamStatsDb->preparedQuery("
        SELECT MAX(last_sync_id) as last_sync_id 
        FROM " . TABLE_JOB_SYNC_LOGS_COUNTRY . " 
        WHERE country = 'rw' AND errors = 0",
        []
    );
    $lastSyncRow = $result->fetch_assoc();
    $lastSyncId = $lastSyncRow['last_sync_id'] ?? 0;
    
    // Jobs site count (synced jobs for Rwanda)
    $result = $jobsStatsDb->preparedQuery("
        SELECT COUNT(*) as total FROM " . TABLE_JOBS_JOBS . " 
        WHERE sync_country = 'rw' AND sync_source = 'teamsite_export'",
        []
    );
    $jobsSyncedCount = $result->fetch_assoc()['total'];
    
    // Total jobs in jobs site (for Rwanda - based on country field)
    $result = $jobsStatsDb->preparedQuery(
        "SELECT COUNT(*) as total FROM " . TABLE_JOBS_JOBS . " WHERE country = ?",
        ['Rwanda']
    );
    $jobsRwandaTotalCount = $result->fetch_assoc()['total'];
    
    $pendingSync = $teamSyncStats['pending'];
    
    $teamStatsDb->close();
    $jobsStatsDb->close();
    
} catch (Exception $e) {
    $teamRwandaCount = $teamTotalCount = $jobsRwandaTotalCount = $jobsSyncedCount = $pendingSync = $teamSyncStats = [
        'total' => 0, 'synced' => 0, 'pending' => 0, 'failed' => 0
    ];
    $lastSyncId = 0;
}

// Get sync logs for Rwanda
try {
    $logsDb = Database::getInstance(DB_TEAM_NAME);
    $logs = [];
    
    $result = $logsDb->preparedQuery(
        "SELECT * FROM " . TABLE_JOB_SYNC_LOGS_COUNTRY . " 
         WHERE country = 'rw' 
         ORDER BY sync_date DESC LIMIT " . MAX_LOG_ROWS,
        []
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
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-exchange-alt"></i> Rwanda Job Sync (Team → Rwanda JobSite)</h1>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="window.location.href='dashboard.php'">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </button>
            <button type="button" class="btn btn-outline-info" onclick="window.location.href='sync_companies.php'">
                <i class="fas fa-building"></i> Sync Companies
            </button>
        </div>
    </div>

    <!-- Rwanda-specific header -->
    <div class="alert alert-primary d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-flag-rw"></i> 
            <strong>Country:</strong> Rwanda (RW)
            <span class="badge bg-dark ms-2">Country-Specific Sync</span>
            <span class="badge bg-info ms-1">Default Company ID: <?php echo DEFAULT_COMPANY_ID; ?></span>
            <span class="badge bg-info ms-1">Default User ID: <?php echo DEFAULT_USER_ID; ?></span>
            <span class="badge bg-warning ms-1">Last Sync ID: <?php echo $lastSyncId; ?></span>
        </div>
        <div>
            <button class="btn btn-sm btn-outline-primary" onclick="window.location.href='?country=rw&refresh=' + Date.now()">
                <i class="fas fa-sync"></i> Refresh Stats
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

    <!-- Sync Info -->
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle"></i> Rwanda Job Sync Features:</h5>
        <ul class="mb-0">
            <li><strong>Last Sync ID Tracking</strong> - Only syncs jobs with ID > <?php echo $lastSyncId; ?></li>
            <li><strong>Country-specific sync</strong> - Only syncs jobs where Country = 'Rwanda'</li>
            <li><strong>Force country to Rwanda</strong> - All jobs will have country field set to 'Rwanda'</li>
            <li><strong>Default Values</strong> - Sets companyid=<?php echo DEFAULT_COMPANY_ID; ?> and uid=<?php echo DEFAULT_USER_ID; ?></li>
            <li><strong>Status-based sync</strong> - Only syncs jobs with sync_status = 'pending' or NULL</li>
            <li><strong>Batch processing</strong> - Processes 500 jobs at a time</li>
        </ul>
    </div>

    <!-- Rwanda-specific Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body text-center">
                    <h3><?php echo $teamRwandaCount; ?></h3>
                    <p>Rwanda Team Jobs</p>
                    <small>Country = 'Rwanda'</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body text-center">
                    <h3><?php echo $teamSyncStats['synced']; ?></h3>
                    <p>Synced to JobSite</p>
                    <small>sync_country = 'rw'</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white <?php echo $teamSyncStats['failed'] > 0 ? 'bg-danger' : 'bg-secondary'; ?>">
                <div class="card-body text-center">
                    <h3><?php echo $teamSyncStats['failed']; ?></h3>
                    <p>Failed Syncs</p>
                    <small>Requires attention</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white <?php echo $pendingSync > 0 ? 'bg-warning' : 'bg-secondary'; ?>">
                <div class="card-body text-center">
                    <h3><?php echo $pendingSync; ?></h3>
                    <p>Pending Sync</p>
                    <small>ID > <?php echo $lastSyncId; ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional stats row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="text-muted">Total Team Jobs (All Countries)</h5>
                    <h2 class="text-primary"><?php echo $teamTotalCount; ?></h2>
                    <small class="text-muted">pc0ww_JobsExport</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="text-muted">Rwanda Jobs in JobSite</h5>
                    <h2 class="text-info"><?php echo $jobsRwandaTotalCount; ?></h2>
                    <small class="text-muted">country = 'Rwanda'</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="text-muted">Synced from Team</h5>
                    <h2 class="text-success"><?php echo $jobsSyncedCount; ?></h2>
                    <small class="text-muted">sync_country = 'rw'</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Info -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-database"></i> Database Information (Rwanda)</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Source Database (Team):</h6>
                    <table class="table table-sm">
                        <tr><th>Host:</th><td><?php echo DB_TEAM_HOST; ?></td></tr>
                        <tr><th>Database:</th><td><?php echo DB_TEAM_NAME; ?></td></tr>
                        <tr><th>Table:</th><td>pc0ww_JobsExport</td></tr>
                        <tr><th>Filter:</th><td class="text-danger fw-bold">Country = 'Rwanda'</td></tr>
                        <tr><th>Last Sync ID:</th><td class="fw-bold text-warning"><?php echo $lastSyncId; ?></td></tr>
                        <tr><th>Pending Jobs:</th><td class="fw-bold">ID > <?php echo $lastSyncId; ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Destination Database (Rwanda JobSite):</h6>
                    <table class="table table-sm">
                        <tr><th>Host:</th><td><?php echo DB_JOBS_HOST; ?></td></tr>
                        <tr><th>Database:</th><td><?php echo DB_JOBS_NAME; ?></td></tr>
                        <tr><th>Table:</th><td>icop0_js_job_jobs</td></tr>
                        <tr><th>Sync Country:</th><td class="text-danger fw-bold">'rw'</td></tr>
                        <tr><th>Sync Source:</th><td class="fw-bold">teamsite_export</td></tr>
                        <tr><th>Tracking Fields:</th><td>source_id, sync_country, last_sync</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Query Info -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-database"></i> Sync Query Logic</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-light">
                <code class="text-dark">
                    SELECT * FROM pc0ww_JobsExport<br>
                    WHERE Country = 'Rwanda'<br>
                    AND (sync_status = 'pending' OR sync_status IS NULL)<br>
                    AND id > <?php echo $lastSyncId; ?><br>
                    ORDER BY id ASC<br>
                    LIMIT 500
                </code>
            </div>
            <p class="mb-0"><strong>This query will fetch:</strong></p>
            <ul class="mb-0">
                <li>Only Rwanda jobs (Country = 'Rwanda')</li>
                <li>Jobs that haven't been synced or have pending status</li>
                <li>Jobs with ID greater than last recorded sync ID (<?php echo $lastSyncId; ?>)</li>
                <li>Maximum 500 jobs per sync to prevent timeout</li>
            </ul>
        </div>
    </div>

    <!-- Sync Button -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-sync"></i> Rwanda Job Sync Action</h5>
        </div>
        <div class="card-body text-center">
            <?php if ($pendingSync > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-clock"></i>
                    <strong><?php echo $pendingSync; ?> Rwanda jobs pending sync!</strong>
                    <p class="mb-0">These jobs have ID > <?php echo $lastSyncId; ?> and haven't been synced to Rwanda JobSite yet.</p>
                </div>
                <p class="lead">Ready to sync <?php echo $pendingSync; ?> Rwanda jobs from Team to Rwanda JobSite</p>
                <p class="text-muted">Will process jobs with ID > <?php echo $lastSyncId; ?> (Last sync ID: <?php echo $lastSyncId; ?>)</p>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>All Rwanda jobs are already synced!</strong>
                    <p class="mb-0">Last sync ID: <?php echo $lastSyncId; ?>. You can still sync to update any changes.</p>
                </div>
                <p class="lead">All Rwanda jobs are already synced up to ID <?php echo $lastSyncId; ?></p>
                <p class="text-muted">You can still sync to update any changes</p>
            <?php endif; ?>
            
            <form method="POST" id="syncForm">
                <input type="hidden" name="action" value="sync_jobs_rw">
                <button type="submit" class="btn btn-primary btn-lg" id="syncButton">
                    <i class="fas fa-flag-rw"></i> Sync Rwanda Jobs (Team → Rwanda JobSite)
                </button>
            </form>
            
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-filter"></i> 
                    Filter: Country = 'Rwanda' AND ID > <?php echo $lastSyncId; ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Rwanda Sync Logs -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-history"></i> Rwanda Job Sync Logs</h5>
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
                    <p>No Rwanda job sync logs yet. Perform your first sync to see logs here.</p>
                    <small class="text-muted">Logs are stored in: <?php echo TABLE_JOB_SYNC_LOGS_COUNTRY; ?> table</small>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-database"></i>
                    <strong>Log Source:</strong> <?php echo TABLE_JOB_SYNC_LOGS_COUNTRY; ?> table (Country-specific)
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr class="table-light">
                                <th>Date & Time</th>
                                <th>Last Sync ID</th>
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
                                    <td>
                                        <span class="badge bg-dark"><?php echo $log['last_sync_id']; ?></span>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo $log['total_jobs']; ?></span></td>
                                    <td><span class="badge bg-success">+<?php echo $log['new_jobs']; ?></span></td>
                                    <td><span class="badge bg-warning"><?php echo $log['updated_jobs']; ?></span></td>
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
                                            <a href="?country=rw&action=delete_log&id=<?php echo $log['id']; ?>" 
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
                <h5 class="modal-title">Rwanda Job Sync Log Details</h5>
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
                <p class="lead">Are you sure you want to delete ALL Rwanda job sync logs?</p>
                <p>This action will permanently delete <?php echo count($logs); ?> log entries from the <?php echo TABLE_JOB_SYNC_LOGS_COUNTRY; ?> table.</p>
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
    // Clear URL parameters
    if (window.location.search.includes('message=') || window.location.search.includes('t=')) {
        const url = new URL(window.location);
        url.searchParams.delete('message');
        url.searchParams.delete('t');
        window.history.replaceState({}, document.title, url.toString());
    }
    
    // Use event delegation for dynamically loaded content
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.view-log-btn')) {
            const button = e.target.closest('.view-log-btn');
            const logJson = button.getAttribute('data-log');
            
            try {
                const logText = JSON.parse(logJson);
                viewLogDetails(logText);
            } catch (error) {
                console.error('Error parsing log JSON:', error);
                viewLogDetails(button.getAttribute('data-log') || 'Error loading log');
            }
        }
    });
    
    // Add event listener for delete buttons
    document.querySelectorAll('.delete-log-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (confirmDeleteLog()) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                btn.classList.add('disabled');
            } else {
                e.preventDefault();
            }
        });
    });
});

function viewLogDetails(logText) {
    const logElement = document.getElementById('logDetails');
    
    let formattedLog = '';
    const lines = logText.split('\n');
    
    lines.forEach(line => {
        let className = '';
        if (line.includes('✓')) className = 'text-success fw-bold';
        else if (line.includes('✗')) className = 'text-danger fw-bold';
        else if (line.includes('===')) className = 'fw-bold border-top pt-2';
        else if (line.includes('Rwanda')) className = 'text-primary fw-bold';
        else if (line.includes('Last sync ID')) className = 'text-warning fw-bold';
        else if (line.includes('Default Company ID') || line.includes('Default User ID')) className = 'text-info fw-bold';
        
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

function confirmDeleteLog() {
    return confirm('Are you sure you want to delete this Rwanda log entry?\nThis action cannot be undone.');
}

// Add loading animation to sync button
document.getElementById('syncForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('syncButton');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing Rwanda Jobs...';
    btn.disabled = true;
    
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-info mt-3';
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm me-2"></div>
            <div>
                <strong>Rwanda job sync in progress...</strong><br>
                <small>Syncing jobs with ID > <?php echo $lastSyncId; ?> (<?php echo $pendingSync; ?> pending jobs). Please wait...</small>
                <div class="progress mt-2" style="height: 5px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                </div>
            </div>
        </div>
    `;
    
    this.appendChild(alertDiv);
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

code {
    background-color: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}

.text-danger {
    color: #dc3545 !important;
}

.fa-flag-rw {
    color: #00a1de;
    background: linear-gradient(90deg, #00a1de 33%, #fad201 33%, #fad201 66%, #007a30 66%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>

<?php require_once '../includes/footer.php'; ?>