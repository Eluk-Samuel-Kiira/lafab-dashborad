<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/schedule_logic.php';

// Initialize variables
$success = '';
$error = '';
$schedule = [];
$stats = [];

// Create schedule logic instance
$scheduleLogic = new ScheduleLogic();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle generate schedule
    if (isset($_POST['generate_schedule'])) {
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $schedule = $scheduleLogic->generateWeeklySchedule($startDate);
        $stats = $scheduleLogic->getWeeklyStats($schedule);
        $success = "Schedule generated for week starting " . date('F j, Y', strtotime($startDate));
    }
    
    // Handle report challenge
    elseif (isset($_POST['report_challenge'])) {
        $result = $scheduleLogic->reportChallenge(
            $_POST['poster_name'],
            $_POST['challenge_date'],
            $_POST['challenge_type'],
            $_POST['severity'],
            $_POST['notes'] ?? ''
        );
        
        if ($result['success']) {
            $success = $result['message'];
            // Regenerate schedule to show updated challenge
            $schedule = $scheduleLogic->generateWeeklySchedule();
            $stats = $scheduleLogic->getWeeklyStats($schedule);
        } else {
            $error = $result['message'] ?? "Failed to report challenge";
        }
    }
    
    // Handle resolve challenge - ONLY when this specific form is submitted
    elseif (isset($_POST['resolve_challenge']) && !empty($_POST['resolve_poster_name']) && !empty($_POST['resolve_date'])) {
        $result = $scheduleLogic->resolveChallenge(
            $_POST['resolve_poster_name'],
            $_POST['resolve_date']
        );
        
        if ($result['success']) {
            $success = $result['message'];
            // Regenerate schedule to reflect the change
            $schedule = $scheduleLogic->generateWeeklySchedule();
            $stats = $scheduleLogic->getWeeklyStats($schedule);
        } else {
            $error = $result['message'] ?? "Failed to resolve challenge";
        }
    }
    
    // Handle mark completed
    elseif (isset($_POST['mark_completed'])) {
        // In real implementation, update database
        $success = "Posting marked as completed for " . $_POST['completed_date'];
        // No need to regenerate schedule for this
    }
    
    // Handle other form submissions if any...
}

// Handle add manual backup
if (isset($_POST['add_manual_backup'])) {
    $result = $scheduleLogic->addManualBackup(
        $_POST['backup_date'],
        $_POST['backup_admin'],
        $_POST['backup_reason'] ?? 'Manual backup added'
    );
    
    if ($result['success']) {
        $success = $result['message'];
        // Regenerate schedule
        $schedule = $scheduleLogic->generateWeeklySchedule();
        $stats = $scheduleLogic->getWeeklyStats($schedule);
    } else {
        $error = $result['message'] ?? "Failed to add manual backup";
    }
}

// Handle remove manual backup
if (isset($_POST['remove_manual_backup'])) {
    $result = $scheduleLogic->removeManualBackup($_POST['remove_backup_date']);
    
    if ($result['success']) {
        $success = $result['message'];
        // Regenerate schedule
        $schedule = $scheduleLogic->generateWeeklySchedule();
        $stats = $scheduleLogic->getWeeklyStats($schedule);
    } else {
        $error = $result['message'] ?? "Failed to remove manual backup";
    }
}

// Handle resolve challenge with challenge ID
if (isset($_POST['resolve_challenge_with_id'])) {
    $result = $scheduleLogic->resolveChallenge(
        $_POST['challenge_id'],
        $_POST['resolve_date']
    );
    
    if ($result['success']) {
        $success = $result['message'];
        $schedule = $scheduleLogic->generateWeeklySchedule();
        $stats = $scheduleLogic->getWeeklyStats($schedule);
    } else {
        $error = $result['message'];
    }
}

// Handle force unassign admin
if (isset($_POST['force_unassign_admin'])) {
    $result = $scheduleLogic->forceUnassignAdmin(
        $_POST['unassign_date'],
        $_POST['unassign_admin']
    );
    
    if ($result['success']) {
        $success = $result['message'];
        $schedule = $scheduleLogic->generateWeeklySchedule();
        $stats = $scheduleLogic->getWeeklyStats($schedule);
    } else {
        $error = $result['message'];
    }
}

// Generate default schedule if none exists
if (empty($schedule)) {
    $schedule = $scheduleLogic->generateWeeklySchedule();
    $stats = $scheduleLogic->getWeeklyStats($schedule);
}

// Get team members for dropdowns
$postingTeam = ['Mukhwana Colette', 'Viola Charlotte', 'Juliet Kemgisha'];
$adminTeam = ['Evie', 'Mathias Kyam', 'Patricia Nakabugo', 'Samuel Kiira', 'Cassandra Leah'];
$allTeam = array_merge($postingTeam, $adminTeam);
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2 mb-2 mb-md-0">Posting Team Timetable</h1>
        <div class="btn-toolbar mt-2 mt-md-0">
            <div class="btn-group flex-wrap">
                <button type="button" class="btn btn-outline-primary btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#reportChallengeModal">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <span class="d-none d-md-inline">Report Challenge</span>
                </button>
                <button type="button" class="btn btn-outline-success btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#generateScheduleModal">
                    <i class="fas fa-calendar-alt"></i> 
                    <span class="d-none d-md-inline">Generate New Schedule</span>
                </button>
                <button type="button" class="btn btn-outline-info btn-sm mb-1" onclick="window.print()">
                    <i class="fas fa-print"></i> 
                    <span class="d-none d-md-inline">Print Timetable</span>
                </button>
                <button class="btn btn-outline-success btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#resolveChallengeModal">
                    <i class="fas fa-check-circle"></i> 
                    <span class="d-none d-md-inline">Resolve Challenge</span>
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#addBackupModal">
                    <i class="fas fa-user-plus"></i> 
                    <span class="d-none d-md-inline">Add Manual Backup</span>
                </button>
            </div>
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

    <!-- Weekly Stats Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">Consistency Score</h6>
                    <h2 class="display-6"><?php echo $stats['consistency_percentage'] ?? 0; ?>%</h2>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $stats['consistency_percentage'] ?? 0; ?>%"></div>
                    </div>
                    <small class="opacity-75">Target: >70%</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">Fully Staffed Days</h6>
                    <h2 class="display-6"><?php echo $stats['days_fully_staffed'] ?? 0; ?>/7</h2>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-info" style="width: <?php echo (($stats['days_fully_staffed'] ?? 0) / 7) * 100; ?>%"></div>
                    </div>
                    <small class="opacity-75">Perfect coverage days</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-dark border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">Challenges Reported</h6>
                    <h2 class="display-6"><?php echo $stats['challenges_count'] ?? 0; ?></h2>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-danger" style="width: <?php echo min(100, (($stats['challenges_count'] ?? 0) * 20)); ?>%"></div>
                    </div>
                    <small class="opacity-75">Issues affecting posting</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">Admin Coverage Days</h6>
                    <h2 class="display-6"><?php echo $stats['admin_coverage_days'] ?? 0; ?></h2>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-warning" style="width: <?php echo (($stats['admin_coverage_days'] ?? 0) / 7) * 100; ?>%"></div>
                    </div>
                    <small class="opacity-75">Admin support provided</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Timetable -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-calendar-week"></i> Weekly Posting Schedule</h5>
            <span class="badge bg-primary">Week of <?php echo date('F j, Y'); ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="12%">Date/Day</th>
                            <th width="22%">Primary Posters (Posting Team)</th>
                            <th width="22%">Admin Coverage (If Needed)</th>
                            <th width="22%">Backup Posters (Mandatory)</th>
                            <th width="12%">Challenges</th>
                            <th width="10%">Coverage Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedule as $date => $daySchedule): ?>
                            <?php 
                            $dayName = ucfirst($daySchedule['day']);
                            $dateFormatted = date('M j, D', strtotime($date));
                            $isWeekend = in_array($daySchedule['day'], ['saturday', 'sunday']);
                            $rowClass = $isWeekend ? 'table-warning' : '';
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td>
                                    <strong><?php echo $dateFormatted; ?></strong><br>
                                    <small class="text-muted"><?php echo $dayName; ?></small>
                                    <?php if ($isWeekend): ?>
                                        <br><span class="badge bg-warning">Weekend</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Primary Posters -->
                                <td>
                                    <?php if (!empty($daySchedule['primary_posters'])): ?>
                                        <div class="d-flex flex-column">
                                            <?php foreach ($daySchedule['primary_posters'] as $poster): ?>
                                                <div class="mb-1">
                                                    <i class="fas fa-user-check text-success me-1"></i>
                                                    <strong><?php echo $poster['name']; ?></strong>
                                                    <?php if (!empty($poster['notes'])): ?>
                                                        <small class="text-muted">(<?php echo $poster['notes']; ?>)</small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="fas fa-exclamation-circle"></i> No primary posters</span>
                                    <?php endif; ?>
                                </td>
                                
                                
                                <!-- Admin Coverage Column -->
                                <td>
                                    <?php if (!empty($daySchedule['admin_cover'])): ?>
                                        <div class="d-flex flex-column">
                                            <?php foreach ($daySchedule['admin_cover'] as $admin): ?>
                                                <div class="mb-2 p-2 border rounded">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <i class="fas fa-user-shield text-primary me-1"></i>
                                                            <strong><?php echo $admin['name']; ?></strong>
                                                            <small class="text-muted">(<?php echo $admin['department']; ?>)</small>
                                                        </div>
                                                        <div>
                                                            <?php if (!($admin['added_manually'] ?? false)): ?>
                                                                <form method="POST" style="display:inline;">
                                                                    <input type="hidden" name="unassign_date" value="<?php echo $date; ?>">
                                                                    <input type="hidden" name="unassign_admin" value="<?php echo $admin['name']; ?>">
                                                                    <button type="submit" name="force_unassign_admin" 
                                                                            class="btn btn-sm btn-outline-danger"
                                                                            onclick="return confirm('Unassign <?php echo $admin['name']; ?>? This may affect coverage.')">
                                                                        <i class="fas fa-user-minus"></i> Unassign
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Manual</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="mt-1">
                                                        <small><?php echo $admin['notes']; ?></small>
                                                        <?php if (isset($admin['challenge_id'])): ?>
                                                            <br><small class="text-muted">Covering challenge</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i> No admin cover needed</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Backup Posters -->
                                <td>
                                    <?php if (!empty($daySchedule['backup_posters'])): ?>
                                        <div class="d-flex flex-column">
                                            <?php foreach ($daySchedule['backup_posters'] as $backup): ?>
                                                <div class="mb-1">
                                                    <i class="fas fa-user-clock text-info me-1"></i>
                                                    <strong><?php echo $backup['name']; ?></strong>
                                                    <small class="text-muted">(<?php echo $backup['department']; ?>)</small>
                                                    <br><small><?php echo $backup['notes']; ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="fas fa-exclamation-triangle"></i> No backup assigned</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Challenges Column -->
                                <td>
                                    <?php if (!empty($daySchedule['challenges'])): ?>
                                        <div class="d-flex flex-column">
                                            <?php foreach ($daySchedule['challenges'] as $challenge): ?>
                                                <div class="mb-2 p-2 border rounded">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <i class="fas fa-exclamation-triangle text-danger me-1"></i>
                                                            <strong><?php echo $challenge['poster_name']; ?></strong>
                                                            <small class="text-muted">(<?php echo ucfirst($challenge['challenge_type']); ?>)</small>
                                                        </div>
                                                        <div>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="challenge_id" value="<?php echo $challenge['id']; ?>">
                                                                <input type="hidden" name="resolve_date" value="<?php echo $date; ?>">
                                                                <button type="submit" name="resolve_challenge_with_id" 
                                                                        class="btn btn-sm btn-success" 
                                                                        onclick="return confirm('Resolve challenge for <?php echo $challenge['poster_name']; ?>? Admin will be unassigned.')">
                                                                    <i class="fas fa-check"></i> Resolve
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <div class="mt-1">
                                                        <small>Severity: 
                                                            <span class="badge bg-<?php echo $challenge['severity'] >= 3 ? 'danger' : ($challenge['severity'] == 2 ? 'warning' : 'info'); ?>">
                                                                <?php echo $challenge['severity']; ?>
                                                            </span>
                                                        </small>
                                                        <?php if (!empty($challenge['notes'])): ?>
                                                            <br><small>Notes: <?php echo htmlspecialchars($challenge['notes']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i> No challenges</span>
                                    <?php endif; ?>
                                </td>

                                
                                <!-- Coverage Score -->
                                <td class="text-center">
                                    <?php 
                                    $score = $daySchedule['coverage_score'];
                                    if ($score >= 90) {
                                        $badgeClass = 'bg-success';
                                    } elseif ($score >= 70) {
                                        $badgeClass = 'bg-warning';
                                    } else {
                                        $badgeClass = 'bg-danger';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> p-2" style="font-size: 1.1em;">
                                        <?php echo $score; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Legend and Notes -->
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-key"></i> Legend & System Rules</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Team Roles:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-user-check text-success me-2"></i> <strong>Primary Posters:</strong> Mukhwana, Viola, Judith</li>
                                <li><i class="fas fa-user-shield text-primary me-2"></i> <strong>Admin Coverage:</strong> Activated when primary posters unavailable</li>
                                <li><i class="fas fa-user-clock text-info me-2"></i> <strong>Backup Posters:</strong> 2 admins always on standby</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Challenge Types:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-heartbeat text-danger me-2"></i> Sickness/Health issues</li>
                                <li><i class="fas fa-wifi text-danger me-2"></i> Internet connectivity</li>
                                <li><i class="fas fa-bolt text-danger me-2"></i> Electricity/power outage</li>
                                <li><i class="fas fa-exclamation-triangle text-danger me-2"></i> Personal emergencies</li>
                                <li><i class="fas fa-laptop text-danger me-2"></i> Computer/technical issues</li>
                            </ul>
                        </div>
                    </div>
                    <hr>
                    <h6>System Rules for Consistency (>70%):</h6>
                    <ol>
                        <li>Primary posters cover Monday-Friday (3 posters minimum per day)</li>
                        <li>Saturday requires 1-2 posters (at least one primary poster)</li>
                        <li>Admins cover gaps when primary posters unavailable</li>
                        <li>Two admins always on backup duty (mandatory)</li>
                        <li>Challenges automatically trigger backup activation</li>
                        <li>System maintains historical data for performance tracking</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-cogs"></i> Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reportChallengeModal">
                            <i class="fas fa-exclamation-triangle"></i> Report Challenge/Absence
                        </button>
                        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#generateScheduleModal">
                            <i class="fas fa-calendar-plus"></i> Generate New Schedule
                        </button>
                        <button class="btn btn-outline-info" onclick="exportSchedule()">
                            <i class="fas fa-download"></i> Export Schedule (PDF)
                        </button>
                        <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#viewStatsModal">
                            <i class="fas fa-chart-line"></i> View Performance Stats
                        </button>
                        <a href="team_availability.php" class="btn btn-outline-secondary">
                            <i class="fas fa-users"></i> Manage Team Availability
                        </a>
                    </div>
                    
                    <hr>
                    
                    <h6>Current Status:</h6>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> 
                        <strong>System Active:</strong> 
                        <?php 
                        $avgScore = $stats['average_coverage'] ?? 0;
                        if ($avgScore >= 70) {
                            echo "Meeting consistency target (" . $avgScore . "%)";
                        } else {
                            echo "Below target (" . $avgScore . "%), review needed";
                        }
                        ?>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Next Review:</strong> 
                        <?php echo date('F j, Y', strtotime('+1 week')); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Report Challenge Modal -->
<div class="modal fade" id="reportChallengeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Report Challenge/Absence</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Team Member</label>
                        <select name="poster_name" class="form-select" required>
                            <option value="">Select Team Member</option>
                            <optgroup label="Posting Team">
                                <?php foreach ($postingTeam as $member): ?>
                                    <option value="<?php echo $member; ?>"><?php echo $member; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Admin Team">
                                <?php foreach ($adminTeam as $member): ?>
                                    <option value="<?php echo $member; ?>"><?php echo $member; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date of Challenge</label>
                        <input type="date" name="challenge_date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Challenge Type</label>
                        <select name="challenge_type" class="form-select" required>
                            <option value="">Select Challenge Type</option>
                            <option value="sickness">Sickness/Health Issue</option>
                            <option value="internet">Internet Connectivity</option>
                            <option value="electricity">Electricity/Power Outage</option>
                            <option value="emergency">Personal Emergency</option>
                            <option value="computer">Computer/Technical Issue</option>
                            <option value="other">Other (Specify in notes)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Severity Level</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="severity" value="1" id="sev1">
                            <label class="form-check-label" for="sev1">
                                <span class="badge bg-success">Low</span> - Can post later in day
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="severity" value="2" id="sev2" checked>
                            <label class="form-check-label" for="sev2">
                                <span class="badge bg-warning">Medium</span> - Needs backup coverage
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="severity" value="3" id="sev3">
                            <label class="form-check-label" for="sev3">
                                <span class="badge bg-danger">High</span> - Cannot post at all
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Provide details about the challenge..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="report_challenge" class="btn btn-warning">
                        <i class="fas fa-paper-plane"></i> Report Challenge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generate Schedule Modal -->
<div class="modal fade" id="generateScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> Generate New Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        This will generate a new 7-day schedule starting from the selected date.
                        Existing assignments will be preserved where possible.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Schedule Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="consider_challenges" id="considerChallenges" checked>
                            <label class="form-check-label" for="considerChallenges">
                                Consider reported challenges
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="auto_assign_backup" id="autoAssignBackup" checked>
                            <label class="form-check-label" for="autoAssignBackup">
                                Automatically assign backup admins
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="optimize_coverage" id="optimizeCoverage" checked>
                            <label class="form-check-label" for="optimizeCoverage">
                                Optimize for maximum coverage (>70%)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="generate_schedule" class="btn btn-primary">
                        <i class="fas fa-magic"></i> Generate Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Stats Modal -->
<div class="modal fade" id="viewStatsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-chart-line"></i> Performance Statistics</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Weekly Performance:</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Average Coverage:</span>
                                <span class="badge bg-primary"><?php echo $stats['average_coverage'] ?? 0; ?>%</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Target Met (>70%):</span>
                                <span class="badge <?php echo ($stats['average_coverage'] ?? 0) >= 70 ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo ($stats['average_coverage'] ?? 0) >= 70 ? 'YES' : 'NO'; ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Fully Staffed Days:</span>
                                <span class="badge bg-success"><?php echo $stats['days_fully_staffed'] ?? 0; ?>/7</span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Challenge Analysis:</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total Challenges:</span>
                                <span class="badge bg-warning"><?php echo $stats['challenges_count'] ?? 0; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Admin Coverage Days:</span>
                                <span class="badge bg-info"><?php echo $stats['admin_coverage_days'] ?? 0; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>System Uptime:</span>
                                <span class="badge bg-success">100%</span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <hr>
                
                <h6>Team Performance:</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Team Member</th>
                                <th>Role</th>
                                <th>Scheduled Days</th>
                                <th>Challenges</th>
                                <th>Reliability</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Simulated team performance data
                            $teamPerformance = [
                                ['Mukhwana Colette', 'Primary Poster', 5, 0, '100%'],
                                ['Viola Charlotte', 'Primary Poster', 5, 0, '100%'],
                                ['Juliet Kemgisha', 'Primary Poster', 5, 0, '100%'],
                                ['Evie', 'Admin (HR)', 2, 0, '100%'],
                                ['Mathias Kyam', 'Admin (Operations)', 2, 0, '100%'],
                                ['Patricia Nakabugo', 'Admin (Business Dev)', 5, 1, '98%'],
                                ['Samuel Kiira', 'Admin (ICT)', 2, 0, '100%'],
                                ['Cassandra Leah', 'Admin (Maid Business)', 2, 0, '100%']
                            ];
                            
                            foreach ($teamPerformance as $member): ?>
                            <tr>
                                <td><?php echo $member[0]; ?></td>
                                <td><span class="badge bg-secondary"><?php echo $member[1]; ?></span></td>
                                <td><?php echo $member[2]; ?> days</td>
                                <td><?php echo $member[3]; ?> challenges</td>
                                <td><span class="badge bg-success"><?php echo $member[4]; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="generatePerformanceReport()">
                    <i class="fas fa-file-pdf"></i> Generate Detailed Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Manual Backup Modal -->
<div class="modal fade" id="addBackupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add Manual Backup</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Use this when the system fails to auto-assign enough admins. This will force-add an admin as backup.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="backup_date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Admin <span class="text-danger">*</span></label>
                        <select name="backup_admin" class="form-select" required>
                            <option value="">Select Admin</option>
                            <?php foreach ($adminTeam as $admin): ?>
                                <option value="<?php echo $admin; ?>"><?php echo $admin; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Manual Backup</label>
                        <textarea name="backup_reason" class="form-control" rows="3" 
                                  placeholder="Why are you adding manual backup? (System failed, emergency, etc.)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_manual_backup" class="btn btn-warning">
                        <i class="fas fa-plus-circle"></i> Add Manual Backup
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Add Resolve Challenge Modal -->
<div class="modal fade" id="resolveChallengeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Resolve Challenge</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="fas fa-info-circle"></i> 
                        This will mark the challenge as resolved and return the team member to their regular posting duties.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Team Member <span class="text-danger">*</span></label>
                        <select name="resolve_poster_name" class="form-select" required>
                            <option value="">Select Team Member</option>
                            <optgroup label="Posting Team">
                                <?php foreach ($postingTeam as $member): ?>
                                    <option value="<?php echo $member; ?>"><?php echo $member; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Admin Team">
                                <?php foreach ($adminTeam as $member): ?>
                                    <option value="<?php echo $member; ?>"><?php echo $member; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date of Challenge <span class="text-danger">*</span></label>
                        <input type="date" name="resolve_date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Resolution Notes</label>
                        <textarea name="resolve_notes" class="form-control" rows="3" 
                                  placeholder="How was the challenge resolved? (Internet restored, health improved, etc.)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="resolve_challenge" value="1" class="btn btn-success">
                        <i class="fas fa-check"></i> Mark as Resolved
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// JavaScript functions
function exportSchedule() {
    alert('Export feature would generate a PDF schedule for printing.');
    // In real implementation, this would call a PDF generation script
}

function generatePerformanceReport() {
    alert('Performance report generation would create a detailed PDF with charts and analysis.');
    // In real implementation, this would generate a comprehensive report
}

// Auto-refresh schedule every 5 minutes (optional)
setTimeout(function() {
    window.location.reload();
}, 300000); // 5 minutes

// Highlight today's date
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const cells = document.querySelectorAll('td:first-child');
    cells.forEach(cell => {
        if (cell.textContent.includes(today)) {
            cell.classList.add('bg-light', 'border-primary', 'border-2');
        }
    });
});
</script>

<style>
.card {
    transition: transform 0.2s ease-in-out;
    margin-bottom: 1rem;
}

.card:hover {
    transform: translateY(-3px);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transform: scale(1.002);
    transition: all 0.2s ease;
}

.badge {
    font-weight: 500;
}

.progress {
    border-radius: 10px;
}

/* Print styles */
@media print {
    .btn-group, .modal, .alert, .card-header .btn {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    
    .table {
        font-size: 12px;
    }
}

/* Highlight weekends */
.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

/* Challenge severity indicators */
.bg-success {
    background-color: #28a745 !important;
}

.bg-warning {
    background-color: #ffc107 !important;
}

.bg-danger {
    background-color: #dc3545 !important;
}

/* Icons in tables */
.fa-user-check, .fa-user-shield, .fa-user-clock {
    font-size: 1.1em;
}
</style>

<?php require_once '../includes/footer.php'; ?>