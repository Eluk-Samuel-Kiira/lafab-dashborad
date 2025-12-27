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
define('TABLE_JOB_SYNC_LOGS', 'pc0ww_job_sync_logs');  // Logs in team database

// Add country-specific suffix to logs table for separation
define('TABLE_JOB_SYNC_LOGS_COUNTRY', 'pc0ww_job_sync_logs_' . $current_country);

// Reduce chunk sizes even more to prevent MySQL timeout
define('SMALL_CHUNK_SIZE_INSERT', 20);   // Very small chunks
define('SMALL_CHUNK_SIZE_UPDATE', 10);   // Very small chunks  
define('SMALL_CHUNK_SIZE_FETCH', 100);   // Small fetch size

// Default values
define('DEFAULT_COMPANY_ID', 4171);  // Default company ID for Rwanda
define('DEFAULT_USER_ID', 13206);    // Default user ID for Rwanda

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

// Create logs table if not exists (country-specific)
function createJobSyncLogsTable($teamDb) {
    $sql = "CREATE TABLE IF NOT EXISTS " . TABLE_JOB_SYNC_LOGS_COUNTRY . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sync_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        country VARCHAR(10) DEFAULT '" . CURRENT_COUNTRY . "',
        total_jobs INT DEFAULT 0,
        new_jobs INT DEFAULT 0,
        updated_jobs INT DEFAULT 0,
        errors INT DEFAULT 0,
        processing_time DECIMAL(5,2) DEFAULT 0,
        log_details TEXT,
        INDEX idx_sync_date (sync_date),
        INDEX idx_country (country)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . DB_CHARSET;
    
    return $teamDb->query($sql);
}

// Function to alter team table to match job site structure (RUNS ONLY ONCE)
function alterTeamJobsTable($teamDb) {
    $alterations = [];
    
    // Check current structure
    $teamColumns = getTableColumns($teamDb, TABLE_TEAM_JOBS_EXPORT);
    
    // List of required columns from job site table
    $requiredColumns = [
        'uid' => 'INT(11) NOT NULL DEFAULT ' . DEFAULT_USER_ID,
        'companyid' => 'INT(11) NULL DEFAULT ' . DEFAULT_COMPANY_ID,
        'title' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
        'alias' => 'VARCHAR(225) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
        'jobcategory' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
        'jobtype' => 'TINYINT(1) UNSIGNED DEFAULT 0',
        'jobstatus' => 'TINYINT(3) NOT NULL DEFAULT 1',
        'jobsalaryrange' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'salaryrangetype' => 'VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'hidesalaryrange' => 'TINYINT(1) DEFAULT 1',
        'description' => 'TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'qualifications' => 'TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'prefferdskills' => 'TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'applyinfo' => 'TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'company' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
        'country' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'state' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'county' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'city' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'zipcode' => 'VARCHAR(25) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'address1' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'address2' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'companyurl' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'contactname' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'contactphone' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'contactemail' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'showcontact' => 'TINYINT(1) UNSIGNED DEFAULT 0',
        'noofjobs' => 'INT(11) UNSIGNED NOT NULL DEFAULT 1',
        'reference' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
        'duration' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL',
        'heighestfinisheducation' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'created' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'created_by' => 'INT(11) UNSIGNED NOT NULL DEFAULT 0',
        'modified' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'modified_by' => 'INT(11) UNSIGNED NOT NULL DEFAULT 0',
        'hits' => 'INT(11) UNSIGNED NOT NULL DEFAULT 0',
        'experience' => 'INT(11) DEFAULT 0',
        'startpublishing' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'stoppublishing' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'departmentid' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'shift' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'sendemail' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'metadescription' => 'TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'metakeywords' => 'TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'agreement' => 'TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'ordering' => 'TINYINT(3) NOT NULL DEFAULT 0',
        'aboutjobfile' => 'VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'status' => 'INT(11) DEFAULT 1',
        'educationminimax' => 'TINYINT(1) NULL',
        'educationid' => 'INT(11) NULL',
        'mineducationrange' => 'INT(11) NULL',
        'maxeducationrange' => 'INT(11) NULL',
        'iseducationminimax' => 'TINYINT(1) NULL',
        'degreetitle' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'careerlevel' => 'INT(11) NULL',
        'experienceminimax' => 'TINYINT(1) NULL',
        'experienceid' => 'INT(11) NULL',
        'minexperiencerange' => 'INT(11) NULL',
        'maxexperiencerange' => 'INT(11) NULL',
        'isexperienceminimax' => 'TINYINT(1) NULL',
        'experiencetext' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'workpermit' => 'VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'requiredtravel' => 'INT(11) NULL',
        'agefrom' => 'INT(11) NULL',
        'ageto' => 'INT(11) NULL',
        'salaryrangefrom' => 'INT(11) NULL',
        'salaryrangeto' => 'INT(11) NULL',
        'gender' => 'INT(5) NULL',
        'video' => 'VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'map' => 'VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'packageid' => 'INT(11) NULL',
        'paymenthistoryid' => 'INT(11) NULL',
        'subcategoryid' => 'INT(11) NULL',
        'currencyid' => 'INT(11) NULL',
        'jobid' => 'VARCHAR(25) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'longitude' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'latitude' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'isgoldjob' => 'TINYINT(1) DEFAULT 0',
        'isfeaturedjob' => 'TINYINT(1) DEFAULT 0',
        'notifications' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'raf_gender' => 'TINYINT(1) NULL',
        'raf_degreelevel' => 'TINYINT(1) NULL',
        'raf_experience' => 'TINYINT(1) NULL',
        'raf_age' => 'TINYINT(1) NULL',
        'raf_education' => 'TINYINT(1) NULL',
        'raf_category' => 'TINYINT(1) NULL',
        'raf_subcategory' => 'TINYINT(1) NULL',
        'raf_location' => 'TINYINT(1) NULL',
        'serverstatus' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'serverid' => 'INT(11) DEFAULT 0',
        'joblink' => 'VARCHAR(400) CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
        'jobapplylink' => 'TINYINT(1) NULL',
        'params' => 'LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL',
    ];
    
    
    // Add job_id column for matching with job site
    if (!in_array('job_id', $teamColumns)) {
        $alter_sql = "ALTER TABLE " . TABLE_TEAM_JOBS_EXPORT . " ADD COLUMN job_id VARCHAR(25) CHARACTER SET utf8 COLLATE utf8_general_ci NULL AFTER id";
        $teamDb->query($alter_sql);
        $alterations[] = "Added job_id column";
    }
    
    // Add sync tracking columns
    if (!in_array('sync_status', $teamColumns)) {
        $alter_sql = "ALTER TABLE " . TABLE_TEAM_JOBS_EXPORT . " ADD COLUMN sync_status VARCHAR(20) DEFAULT 'pending' AFTER params";
        $teamDb->query($alter_sql);
        $alterations[] = "Added sync_status column";
    }
    
    if (!in_array('last_sync_attempt', $teamColumns)) {
        $alter_sql = "ALTER TABLE " . TABLE_TEAM_JOBS_EXPORT . " ADD COLUMN last_sync_attempt DATETIME NULL AFTER sync_status";
        $teamDb->query($alter_sql);
        $alterations[] = "Added last_sync_attempt column";
    }
    
    if (!in_array('sync_error', $teamColumns)) {
        $alter_sql = "ALTER TABLE " . TABLE_TEAM_JOBS_EXPORT . " ADD COLUMN sync_error TEXT NULL AFTER last_sync_attempt";
        $teamDb->query($alter_sql);
        $alterations[] = "Added sync_error column";
    }
    
    if (!in_array('sync_country', $teamColumns)) {
        $alter_sql = "ALTER TABLE " . TABLE_TEAM_JOBS_EXPORT . " ADD COLUMN sync_country VARCHAR(10) NULL AFTER sync_error";
        $teamDb->query($alter_sql);
        $alterations[] = "Added sync_country column";
    }
    
    // Check and add missing columns
    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $teamColumns)) {
            $alter_sql = "ALTER TABLE " . TABLE_TEAM_JOBS_EXPORT . " ADD COLUMN $column $definition";
            
            try {
                $teamDb->query($alter_sql);
                $alterations[] = "Added $column column";
            } catch (Exception $e) {
                $alterations[] = "Failed to add $column: " . $e->getMessage();
            }
        }
    }
    
    // Update default values for existing rows
    if (in_array('uid', $teamColumns)) {
        $update_sql = "UPDATE " . TABLE_TEAM_JOBS_EXPORT . " SET uid = " . DEFAULT_USER_ID . " WHERE uid = 0 OR uid IS NULL";
        $teamDb->query($update_sql);
        $alterations[] = "Updated uid default values";
    }
    
    if (in_array('companyid', $teamColumns)) {
        $update_sql = "UPDATE " . TABLE_TEAM_JOBS_EXPORT . " SET companyid = " . DEFAULT_COMPANY_ID . " WHERE companyid IS NULL";
        $teamDb->query($update_sql);
        $alterations[] = "Updated companyid default values";
    }
    
    return $alterations;
}

// Function to check and alter job site tables to match structure
function harmonizeJobTables($teamDb, $jobsDb) {
    $alterations = [];
    
    // Get current structures
    $jobsColumns = getTableColumns($jobsDb, TABLE_JOBS_JOBS);
    
    // Check if source_id exists in jobs table (for tracking)
    if (!in_array('source_id', $jobsColumns)) {
        $alter_sql = "ALTER TABLE " . TABLE_JOBS_JOBS . " ADD COLUMN source_id INT NULL AFTER id";
        $jobsDb->query($alter_sql);
        $alterations[] = "Added source_id column to jobs table";
    }
    
    // Check if last_sync exists in jobs table
    if (!in_array('last_sync', $jobsColumns)) {
        $alter_sql = "ALTER TABLE " . TABLE_JOBS_JOBS . " ADD COLUMN last_sync DATETIME NULL AFTER source_id";
        $jobsDb->query($alter_sql);
        $alterations[] = "Added last_sync column to jobs table";
    }
    
    // Check if sync_source exists in jobs table
    if (!in_array('sync_source', $jobsColumns)) {
        $alter_sql = "ALTER TABLE " . TABLE_JOBS_JOBS . " ADD COLUMN sync_source VARCHAR(50) NULL AFTER last_sync";
        $jobsDb->query($alter_sql);
        $alterations[] = "Added sync_source column to jobs table";
    }
    
    // Check if sync_country exists in jobs table
    if (!in_array('sync_country', $jobsColumns)) {
        $alter_sql = "ALTER TABLE " . TABLE_JOBS_JOBS . " ADD COLUMN sync_country VARCHAR(10) NULL AFTER sync_source";
        $jobsDb->query($alter_sql);
        $alterations[] = "Added sync_country column to jobs table";
    }
    
    // Check if job_id exists in jobs table (for tracking with team site)
    if (!in_array('job_id', $jobsColumns)) {
        // Add job_id to store the team site's ID
        $alter_sql = "ALTER TABLE " . TABLE_JOBS_JOBS . " ADD COLUMN job_id INT NULL AFTER id";
        $jobsDb->query($alter_sql);
        $alterations[] = "Added job_id column to jobs table (will store team site ID)";
        
        // Add index for better performance
        $index_sql = "ALTER TABLE " . TABLE_JOBS_JOBS . " ADD INDEX idx_job_id (job_id)";
        $jobsDb->query($index_sql);
        $alterations[] = "Added index on job_id column";
    }
    
    return $alterations;
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

// Function to get field mapping between team and jobs tables
function getJobFieldMapping() {
    return [
        // Team table => Jobs table (all 94 fields mapped)
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
        'company' => 'company',
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

// Function to map team data to jobs structure - FORCE COUNTRY TO RWANDA, preserve other team values
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
        $mappedData['job_id'] = (int)$teamJob['id'];
    }
    
    // Also copy team's ID to source_id for tracking
    if (!empty($teamJob['id'])) {
        $mappedData['source_id'] = (int)$teamJob['id'];
    }
    
    return $mappedData;
}


function getDefaultValueForField($field) {
    $defaults = [
        'uid' => DEFAULT_USER_ID,
        'companyid' => DEFAULT_COMPANY_ID,
        'created_by' => 0,
        'modified_by' => 0,
        'hits' => 0,
        'ordering' => 0,
        'status' => 1,
        'notifications' => 0,
    ];
    
    return $defaults[$field] ?? null;
}

// Optimized bulk insert jobs function with connection checking
function bulkInsertJobs($jobsDb, $jobs, $jobsColumns, &$log_details) {
    if (empty($jobs)) return 0;
    
    $now = date('Y-m-d H:i:s');
    $inserted = 0;
    $total_chunks = ceil(count($jobs) / SMALL_CHUNK_SIZE_INSERT);
    
    $log_details[] = "Starting bulk insert of " . count($jobs) . " jobs in $total_chunks chunks...";
    
    $chunks = array_chunk($jobs, SMALL_CHUNK_SIZE_INSERT);
    $chunk_count = 0;
    
    foreach ($chunks as $chunk) {
        $chunk_count++;
        $values = [];
        
        // Check connection before each chunk
        checkConnection($jobsDb, $log_details);
        
        foreach ($chunk as $job) {
            $rowValues = [];
            
            foreach ($jobsColumns as $column) {
                if ($column === 'source_id') {
                    $rowValues[] = (int)$job['id']; // Use team job ID as source
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
        
        $sql = "INSERT INTO " . TABLE_JOBS_JOBS . " (" . implode(', ', $jobsColumns) . ") 
                VALUES " . implode(', ', $values);
        
        try {
            $jobsDb->query($sql);
            $chunk_inserted = $jobsDb->getAffectedRows();
            $inserted += $chunk_inserted;
            
            // Add progress log
            if ($chunk_count % 5 == 0 || $chunk_count == $total_chunks) {
                $log_details[] = "  • Chunk $chunk_count/$total_chunks: Inserted $chunk_inserted jobs (Total: $inserted)";
            }
            
        } catch (Exception $e) {
            // Check if it's a MySQL gone away error
            if (strpos($e->getMessage(), 'gone away') !== false) {
                $log_details[] = "  ⚡ MySQL connection lost, retrying...";
                // Try to reconnect and retry once
                try {
                    // Small delay before retry
                    sleep(1);
                    $jobsDb->query($sql);
                    $chunk_inserted = $jobsDb->getAffectedRows();
                    $inserted += $chunk_inserted;
                    $log_details[] = "  ✓ Retry successful!";
                } catch (Exception $retryError) {
                    $log_details[] = "  ✗ Retry failed: " . $retryError->getMessage();
                    throw $e;
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
    
    return $inserted;
}

// Optimized bulk update jobs function with connection checking
function bulkUpdateJobs($jobsDb, $jobs, $jobsColumns, &$log_details) {
    if (empty($jobs)) return 0;
    
    $now = date('Y-m-d H:i:s');
    $updated = 0;
    $total_chunks = ceil(count($jobs) / SMALL_CHUNK_SIZE_UPDATE);
    
    $log_details[] = "Starting bulk update of " . count($jobs) . " jobs in $total_chunks chunks...";
    
    $chunks = array_chunk($jobs, SMALL_CHUNK_SIZE_UPDATE);
    $chunk_count = 0;
    
    foreach ($chunks as $chunk) {
        $chunk_count++;
        $values = [];
        
        // Check connection before each chunk
        checkConnection($jobsDb, $log_details);
        
        foreach ($chunk as $job) {
            $rowValues = [];
            
            foreach ($jobsColumns as $column) {
                if ($column === 'id') {
                    $rowValues[] = (int)$job['jobs_id']; // Jobs table ID
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
        
        $sql = "INSERT INTO " . TABLE_JOBS_JOBS . " (" . implode(', ', $jobsColumns) . ") 
                VALUES " . implode(', ', $values) . "
                ON DUPLICATE KEY UPDATE ";
        
        $updates = [];
        foreach ($jobsColumns as $column) {
            if ($column !== 'id' && $column !== 'source_id' && $column !== 'sync_source' && $column !== 'sync_country') {
                $updates[] = "$column = VALUES($column)";
            }
        }
        $updates[] = "last_sync = VALUES(last_sync)";
        $updates[] = "sync_country = VALUES(sync_country)";
        
        $sql .= implode(', ', $updates);
        
        try {
            $jobsDb->query($sql);
            $chunk_updated = $jobsDb->getAffectedRows() > 0 ? count($chunk) : 0;
            $updated += $chunk_updated;
            
            // Add progress log
            if ($chunk_count % 5 == 0 || $chunk_count == $total_chunks) {
                $log_details[] = "  • Chunk $chunk_count/$total_chunks: Updated $chunk_updated jobs (Total: $updated)";
            }
            
        } catch (Exception $e) {
            // Check if it's a MySQL gone away error
            if (strpos($e->getMessage(), 'gone away') !== false) {
                $log_details[] = "  ⚡ MySQL connection lost, retrying...";
                // Try to reconnect and retry once
                try {
                    // Small delay before retry
                    sleep(1);
                    $jobsDb->query($sql);
                    $chunk_updated = $jobsDb->getAffectedRows() > 0 ? count($chunk) : 0;
                    $updated += $chunk_updated;
                    $log_details[] = "  ✓ Retry successful!";
                } catch (Exception $retryError) {
                    $log_details[] = "  ✗ Retry failed: " . $retryError->getMessage();
                    throw $e;
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

// Check and maintain MySQL connection
function checkConnection($db, &$log_details) {
    static $ping_count = 0;
    
    $ping_count++;
    if ($ping_count % 20 == 0) {
        try {
            // Simple query to check connection
            $db->query("SELECT 1");
        } catch (Exception $e) {
            $log_details[] = "  ⚡ Connection check failed: " . $e->getMessage();
            // Connection might be dead, but we'll let the next operation handle it
        }
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
        ini_set('mysql.connect_timeout', 300);
        ini_set('default_socket_timeout', 300);
        
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
        
        // ALTER TEAM TABLE TO MATCH JOB SITE STRUCTURE (runs only once)
        $teamAlterations = alterTeamJobsTable($teamDb);
        
        // Harmonize job site tables (add tracking columns if needed)
        $jobAlterations = harmonizeJobTables($teamDb, $jobsDb);
        
        // Get field mapping
        $fieldMapping = getJobFieldMapping();
        
        // Get jobs columns
        $jobsColumns = getTableColumns($jobsDb, TABLE_JOBS_JOBS);
        
        $log_details = [];
        $log_details[] = "=== JOB SYNC STARTED FOR RWANDA - " . date('Y-m-d H:i:s') . " ===";
        $log_details[] = "Database: " . DB_JOBS_NAME;
        $log_details[] = "Table: " . TABLE_JOBS_JOBS;
        $log_details[] = "Filter: Country = 'Rwanda' in team site";
        $log_details[] = "Default Company ID: " . DEFAULT_COMPANY_ID;
        $log_details[] = "Default User ID: " . DEFAULT_USER_ID;
        
        if (!empty($teamAlterations)) {
            $log_details[] = "Team table alterations made:";
            foreach ($teamAlterations as $alteration) {
                $log_details[] = "  • " . $alteration;
            }
        }
        
        if (!empty($jobAlterations)) {
            $log_details[] = "Job site table alterations made:";
            foreach ($jobAlterations as $alteration) {
                $log_details[] = "  • " . $alteration;
            }
        }
        
        // Get ONLY RWANDA jobs from Team site
        $log_details[] = "Fetching Rwanda jobs from Team site (Country = 'Rwanda')...";
        
        $total = 0;
        $offset = 0;
        $batch_size = 500;
        $allTeamJobs = [];
        
        // Fetch in batches to prevent memory issues
        while (true) {
            $sql = "SELECT * FROM " . TABLE_TEAM_JOBS_EXPORT . " 
                    WHERE Country = 'Rwanda' 
                    ORDER BY id 
                    LIMIT $offset, $batch_size";
            
            $result = $teamDb->query($sql);
            
            $batch_count = $result->num_rows;
            if ($batch_count == 0) break;
            
            while ($job = $result->fetch_assoc()) {
                $allTeamJobs[$job['id']] = $job;
            }
            
            $total += $batch_count;
            $offset += $batch_size;
            
            $log_details[] = "  • Fetched batch: $batch_count Rwanda jobs (Total so far: $total)";
            
            // Small pause between batches
            usleep(100000);
            
            // Check connection periodically
            if ($offset % 2000 == 0) {
                checkConnection($teamDb, $log_details);
            }
        }
        
        $log_details[] = "✓ Successfully fetched $total Rwanda jobs";
        
        if ($total == 0) {
            $log_details[] = "No Rwanda jobs found in team site";
            $success = "ℹ️ No Rwanda jobs found to sync.";
        } else {
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
                
                // Update team sync status for new jobs in batches
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
                
                // Update team sync status for updated jobs in batches
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
        }
        
        // Calculate processing time
        $processing_time = round(microtime(true) - $start_time, 2);
        
        // Save log to COUNTRY-SPECIFIC table
        $log_details[] = "=== JOB SYNC COMPLETED FOR RWANDA IN {$processing_time}s ===";
        $log_details[] = "Total Rwanda jobs: $total | New: $new | Updated: $updated | Errors: $errors";
        
        $log_text = implode("\n", $log_details);
        $log_sql = "INSERT INTO " . TABLE_JOB_SYNC_LOGS_COUNTRY . " 
                   (country, total_jobs, new_jobs, updated_jobs, errors, processing_time, log_details) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $teamDb->preparedQuery($log_sql, ['rw', $total, $new, $updated, $errors, $processing_time, $log_text]);
        
        if ($total == 0) {
            $success = $success;
        } else {
            $success = "✅ Job Sync for Rwanda completed in {$processing_time} seconds!<br>
                       • Total Rwanda jobs processed: $total jobs<br>
                       • New Rwanda jobs: $new<br>
                       • Updated Rwanda jobs: $updated<br>
                       • Errors: $errors<br>
                       • Default Company ID: " . DEFAULT_COMPANY_ID . "<br>
                       • Default User ID: " . DEFAULT_USER_ID;
        }
        
    } catch (Exception $e) {
        $error = "❌ Job Sync for Rwanda failed: " . $e->getMessage();
        
        // Log error to country-specific table
        if (isset($teamDb)) {
            try {
                $log_text = "=== JOB SYNC FAILED FOR RWANDA - " . date('Y-m-d H:i:s') . " ===\n";
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

    <!-- Table Structure Info -->
    <div class="alert alert-warning">
        <h5><i class="fas fa-table"></i> Table Structure Alignment:</h5>
        <p>The <code>pc0ww_JobsExport</code> table will be automatically altered to match the <code>icop0_js_job_jobs</code> structure with:</p>
        <ul class="mb-0">
            <li>94 columns added to match job site structure</li>
            <li>Default Company ID: <strong><?php echo DEFAULT_COMPANY_ID; ?></strong> for all jobs</li>
            <li>Default User ID: <strong><?php echo DEFAULT_USER_ID; ?></strong> for all jobs</li>
            <li><code>job_id</code> column added for cross-table matching</li>
            <li>Sync tracking columns added (sync_status, sync_country, etc.)</li>
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
                    <p>Synced to Rwanda JobSite</p>
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
                    <small>Ready to migrate</small>
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

    <!-- Sync Info -->
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle"></i> Rwanda Job Sync Features:</h5>
        <ul class="mb-0">
            <li><strong>Automatic Table Alignment</strong> - Adds 94 columns to team table to match job site</li>
            <li><strong>Default Values</strong> - Sets companyid=<?php echo DEFAULT_COMPANY_ID; ?> and uid=<?php echo DEFAULT_USER_ID; ?></li>
            <li><strong>Country-specific sync</strong> - Only syncs jobs where Country = 'Rwanda'</li>
            <li><strong>Force country to Rwanda</strong> - All jobs will have country field set to 'Rwanda'</li>
            <li><strong>Connection monitoring</strong> - Prevents MySQL "gone away" errors</li>
            <li><strong>Small chunk processing</strong> - Processes 20 jobs at a time to prevent timeouts</li>
        </ul>
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
                        <tr><th>Default Company ID:</th><td class="fw-bold"><?php echo DEFAULT_COMPANY_ID; ?></td></tr>
                        <tr><th>Default User ID:</th><td class="fw-bold"><?php echo DEFAULT_USER_ID; ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Destination Database (Rwanda):</h6>
                    <table class="table table-sm">
                        <tr><th>Host:</th><td><?php echo DB_JOBS_HOST; ?></td></tr>
                        <tr><th>Database:</th><td><?php echo DB_JOBS_NAME; ?></td></tr>
                        <tr><th>Table:</th><td>icop0_js_job_jobs</td></tr>
                        <tr><th>Sync Country:</th><td class="text-danger fw-bold">'rw'</td></tr>
                        <tr><th>Job ID Format:</th><td>JOB-RW-{id}</td></tr>
                        <tr><th>Columns:</th><td>94 columns (full match)</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Structure Preview -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-columns"></i> Table Structure Preview</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Automatic Table Alignment:</strong> The team table will be altered to have exactly 94 columns matching the job site structure.
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <h6>Team Table (Before):</h6>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Current Columns
                            <span class="badge bg-primary rounded-pill">
                                <?php 
                                try {
                                    $tempDb = Database::getInstance(DB_TEAM_NAME);
                                    $columns = getTableColumns($tempDb, TABLE_TEAM_JOBS_EXPORT);
                                    echo count($columns);
                                    $tempDb->close();
                                } catch (Exception $e) {
                                    echo "N/A";
                                }
                                ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Missing Columns
                            <span class="badge bg-warning rounded-pill">~80+</span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Team Table (After Alignment):</h6>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Columns
                            <span class="badge bg-success rounded-pill">94</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Default Company ID
                            <span class="badge bg-info rounded-pill"><?php echo DEFAULT_COMPANY_ID; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Default User ID
                            <span class="badge bg-info rounded-pill"><?php echo DEFAULT_USER_ID; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
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
                    <p class="mb-0">These jobs have not been migrated to the Rwanda job site yet.</p>
                </div>
                <p class="lead">Ready to sync <?php echo $pendingSync; ?> Rwanda jobs from Team to Rwanda JobSite</p>
                <p class="text-muted"><?php echo $teamRwandaCount; ?> total Rwanda jobs at Team site, <?php echo $teamSyncStats['synced']; ?> already synced</p>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>All Rwanda jobs are already synced!</strong>
                    <p class="mb-0">You can still sync to update any changes.</p>
                </div>
                <p class="lead">All Rwanda jobs are already synced</p>
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
                    <i class="fas fa-shield-alt"></i> 
                    Connection protected - Prevents MySQL timeout errors
                    <span class="ms-2"><i class="fas fa-table"></i> Automatic table alignment included</span>
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
                    <small class="text-muted">Logs are stored in: pc0ww_job_sync_logs_rw table</small>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-database"></i>
                    <strong>Log Source:</strong> pc0ww_job_sync_logs_rw table (Country-specific)
                </div>
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
                <p>This action will permanently delete <?php echo count($logs); ?> log entries from the pc0ww_job_sync_logs_rw table.</p>
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
        else if (line.includes('⚡')) className = 'text-warning';
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

// Add loading animation to sync button with connection protection
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
                <small>First: Altering table structure to match job site<br>
                Then: Syncing <?php echo $pendingSync; ?> Rwanda jobs with connection protection. Please wait...</small>
                <div class="progress mt-2" style="height: 5px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                </div>
            </div>
        </div>
    `;
    
    this.appendChild(alertDiv);
    
    // Keep-alive ping to prevent timeout
    const keepAliveInterval = setInterval(() => {
        fetch('keepalive.php?country=rw')
            .catch(() => console.log('Keep-alive ping failed'));
    }, 25000); // Every 25 seconds
    
    // Clean up after 10 minutes
    setTimeout(() => {
        clearInterval(keepAliveInterval);
        btn.innerHTML = originalText;
        btn.disabled = false;
        alertDiv.remove();
    }, 600000); // 10 minutes
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

.list-group-item {
    border-left: none;
    border-right: none;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}
</style>

<?php require_once '../includes/footer.php'; ?>