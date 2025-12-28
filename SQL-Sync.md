-- ============================================
-- ADD MISSING COLUMNS TO TEAM TABLE (pc0ww_JobsExport)
-- ============================================

-- Add sync tracking columns (for tracking what has been synced)
ALTER TABLE pc0ww_JobsExport 
ADD COLUMN sync_status VARCHAR(20) DEFAULT 'pending',
ADD COLUMN last_sync_attempt DATETIME NULL,
ADD COLUMN sync_error TEXT NULL,
ADD COLUMN sync_country VARCHAR(10) NULL;

-- Add job_id column (to store the job site's auto-incremented ID after sync)
ALTER TABLE pc0ww_JobsExport 
ADD COLUMN job_id INT NULL;

-- Add index for better performance
ALTER TABLE pc0ww_JobsExport 
ADD INDEX idx_sync_status (sync_status),
ADD INDEX idx_sync_country (sync_country),
ADD INDEX idx_country (Country),
ADD INDEX idx_job_id (job_id);

-- Add missing columns from job site that don't exist in team table
-- (Only add columns that are missing, keep existing ones as-is)

-- 1. Basic job columns that are missing
ALTER TABLE pc0ww_JobsExport ADD COLUMN uid INT(11) NOT NULL DEFAULT 13206;
ALTER TABLE pc0ww_JobsExport ADD COLUMN companyid INT(11) NULL DEFAULT 4171;
ALTER TABLE pc0ww_JobsExport ADD COLUMN jobsalaryrange VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN salaryrangetype VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN hidesalaryrange TINYINT(1) DEFAULT 1;

-- 2. Job details columns (text fields)
ALTER TABLE pc0ww_JobsExport ADD COLUMN qualifications TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN prefferdskills TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN applyinfo TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;

-- 3. Location columns
ALTER TABLE pc0ww_JobsExport ADD COLUMN state VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN county VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN zipcode VARCHAR(25) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN address1 VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN address2 VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;

-- 4. Contact columns
ALTER TABLE pc0ww_JobsExport ADD COLUMN companyurl VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN contactname VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN contactphone VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN contactemail VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN showcontact TINYINT(1) UNSIGNED DEFAULT 0;

-- 5. Additional job info
ALTER TABLE pc0ww_JobsExport ADD COLUMN reference VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';
ALTER TABLE pc0ww_JobsExport ADD COLUMN duration VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';
ALTER TABLE pc0ww_JobsExport ADD COLUMN heighestfinisheducation VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;

-- 6. Timestamp and tracking columns
ALTER TABLE pc0ww_JobsExport ADD COLUMN created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE pc0ww_JobsExport ADD COLUMN created_by INT(11) UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE pc0ww_JobsExport ADD COLUMN modified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE pc0ww_JobsExport ADD COLUMN modified_by INT(11) UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE pc0ww_JobsExport ADD COLUMN hits INT(11) UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE pc0ww_JobsExport ADD COLUMN experience INT(11) DEFAULT 0;

-- 7. Metadata columns
ALTER TABLE pc0ww_JobsExport ADD COLUMN metadescription TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN metakeywords TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN agreement TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN ordering TINYINT(3) NOT NULL DEFAULT 0;
ALTER TABLE pc0ww_JobsExport ADD COLUMN aboutjobfile VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN status INT(11) DEFAULT 1;

-- 8. Education columns
ALTER TABLE pc0ww_JobsExport ADD COLUMN educationminimax TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN educationid INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN mineducationrange INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN maxeducationrange INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN iseducationminimax TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN degreetitle VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN careerlevel INT(11) NULL;

-- 9. Experience columns
ALTER TABLE pc0ww_JobsExport ADD COLUMN experienceminimax TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN experienceid INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN minexperiencerange INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN maxexperiencerange INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN isexperienceminimax TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN experiencetext VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;

-- 10. Additional requirements
ALTER TABLE pc0ww_JobsExport ADD COLUMN workpermit VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN requiredtravel INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN agefrom INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN ageto INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN salaryrangefrom INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN salaryrangeto INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN gender INT(5) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN video VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN map VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;

-- 11. System columns
ALTER TABLE pc0ww_JobsExport ADD COLUMN packageid INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN paymenthistoryid INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN subcategoryid INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN currencyid INT(11) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN jobid VARCHAR(25) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN isgoldjob TINYINT(1) DEFAULT 0;
ALTER TABLE pc0ww_JobsExport ADD COLUMN isfeaturedjob TINYINT(1) DEFAULT 0;
ALTER TABLE pc0ww_JobsExport ADD COLUMN notifications TINYINT(1) NOT NULL DEFAULT 0;

-- 12. RAF columns
ALTER TABLE pc0ww_JobsExport ADD COLUMN raf_gender TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN raf_degreelevel TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN raf_experience TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN raf_age TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN raf_education TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN raf_category TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN raf_subcategory TINYINT(1) NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN raf_location TINYINT(1) NULL;

-- 13. Server columns
ALTER TABLE pc0ww_JobsExport ADD COLUMN serverstatus VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;
ALTER TABLE pc0ww_JobsExport ADD COLUMN serverid INT(11) DEFAULT 0;
ALTER TABLE pc0ww_JobsExport ADD COLUMN joblink VARCHAR(400) CHARACTER SET utf8 COLLATE utf8_general_ci NULL;

-- 14. Copy data from existing columns to new columns where applicable
-- Note: 'Company' (with capital C) exists, we need to copy to 'company' (lowercase)
UPDATE pc0ww_JobsExport SET company = Company WHERE Company IS NOT NULL;

-- Copy city data (if exists in some other field, adjust as needed)
-- UPDATE pc0ww_JobsExport SET city = [existing_city_field] WHERE [existing_city_field] IS NOT NULL;

-- Copy departmentid/shift if they exist somewhere
-- UPDATE pc0ww_JobsExport SET departmentid = [existing_department_field];
-- UPDATE pc0ww_JobsExport SET shift = [existing_shift_field];








-- ============================================
-- ADD TRACKING COLUMNS TO JOB SITE TABLE
-- ============================================

-- Add job_id column (will store team's id for tracking)
ALTER TABLE icop0_js_job_jobs 
ADD COLUMN job_id INT NULL AFTER id;

-- Add source_id column (will also store team's id)
ALTER TABLE icop0_js_job_jobs 
ADD COLUMN source_id INT NULL AFTER id;

-- Add sync tracking columns
ALTER TABLE icop0_js_job_jobs 
ADD COLUMN last_sync DATETIME NULL,
ADD COLUMN sync_source VARCHAR(50) NULL,
ADD COLUMN sync_country VARCHAR(10) NULL;

-- Add indexes for better performance
ALTER TABLE icop0_js_job_jobs 
ADD INDEX idx_job_id (job_id),
ADD INDEX idx_source_id (source_id),
ADD INDEX idx_sync_country (sync_country),
ADD INDEX idx_last_sync (last_sync);




-- ============================================
-- CREATE SYNC LOGS TABLE ON TEAMS SITE
-- ============================================

CREATE TABLE IF NOT EXISTS pc0ww_job_sync_logs_rw (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    country VARCHAR(10) NOT NULL,
    last_sync_id INT DEFAULT 0,  -- Last team ID that was synced
    total_jobs INT DEFAULT 0,
    new_jobs INT DEFAULT 0,
    updated_jobs INT DEFAULT 0,
    errors INT DEFAULT 0,
    processing_time DECIMAL(5,2) DEFAULT 0,
    log_details TEXT,
    INDEX idx_sync_date (sync_date),
    INDEX idx_country (country),
    INDEX idx_last_sync_id (last_sync_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






Instructions
1. when using it on teams run the above sql first on the diff sites and teams speific
on the logs for the last_sync_id must be the latest id for that country on teams export table
 
