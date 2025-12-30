<?php
/**
 * Schedule Logic for Lafab Solutions Posting Team
 * Ensures >70% consistency in job posting
 * RULES:
 * 1. Challenges persist until explicitly resolved
 * 2. Manual admin assignments don't delete challenges
 * 3. Unassign admin when challenge is resolved
 */

class ScheduleLogic {
    
    // Core posting team members
    private $posting_team = [
        'Mukhwana Colette' => ['type' => 'poster', 'priority' => 1],
        'Viola Charlotte' => ['type' => 'poster', 'priority' => 2],
        'Judith Kiiza' => ['type' => 'poster', 'priority' => 3]
    ];
    
    // Admin team for backup
    private $admin_team = [
        'Evie' => [
            'type' => 'admin',
            'comfort_days' => ['monday', 'wednesday'],
            'department' => 'HR',
            'priority' => 4,
            'max_days_per_week' => 5
        ],
        'Mathias Kyam' => [
            'type' => 'admin',
            'comfort_days' => ['wednesday', 'thursday'],
            'department' => 'Operations',
            'priority' => 5,
            'max_days_per_week' => 5
        ],
        'Patricia Nakabugo' => [
            'type' => 'admin',
            'comfort_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'department' => 'Business Development',
            'priority' => 6,
            'max_days_per_week' => 5,
            'field_days' => []
        ],
        'Samuel Kiira' => [
            'type' => 'admin',
            'comfort_days' => ['tuesday', 'friday'],
            'department' => 'ICT',
            'priority' => 7,
            'max_days_per_week' => 5
        ]
    ];
    
    private $daily_requirements = [
        'monday' => ['min' => 3, 'ideal' => 5, 'max' => 6],
        'tuesday' => ['min' => 3, 'ideal' => 5, 'max' => 6],
        'wednesday' => ['min' => 3, 'ideal' => 5, 'max' => 6],
        'thursday' => ['min' => 3, 'ideal' => 5, 'max' => 6],
        'friday' => ['min' => 3, 'ideal' => 5, 'max' => 6],
        'saturday' => ['min' => 1, 'ideal' => 2, 'max' => 3],
        'sunday' => ['min' => 0, 'ideal' => 0, 'max' => 1]
    ];
    
    // STORAGE - Challenges persist until resolved
    private static $challenges = []; // ACTIVE challenges
    private static $resolvedChallenges = []; // RESOLVED challenges (history)
    private static $adminAssignments = []; // Track which admins are covering which challenges
    private static $manualBackups = [];
    private static $currentSchedule = null;
    private static $scheduleWeekStart = null;
    
    /**
     * Generate weekly schedule
     */
    public function generateWeeklySchedule($startDate = null, $forceRegenerate = false) {
        if (!$startDate) {
            $startDate = date('Y-m-d');
        }
        
        $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($startDate)));
        
        if (self::$currentSchedule !== null && 
            self::$scheduleWeekStart === $weekStart && 
            !$forceRegenerate) {
            return self::$currentSchedule;
        }
        
        self::$scheduleWeekStart = $weekStart;
        $schedule = [];
        
        for ($i = 0; $i < 7; $i++) {
            $currentDate = date('Y-m-d', strtotime($weekStart . " +{$i} days"));
            $dayName = strtolower(date('l', strtotime($currentDate)));
            
            $schedule[$currentDate] = $this->generateDailySchedule($currentDate, $dayName);
        }
        
        self::$currentSchedule = $schedule;
        return $schedule;
    }
    
    /**
     * Generate daily schedule - FIXED: Challenges don't auto-delete
     */
    private function generateDailySchedule($date, $dayName) {
        $dailySchedule = [
            'date' => $date,
            'day' => $dayName,
            'primary_posters' => [],
            'backup_posters' => [],
            'admin_cover' => [],
            'challenges' => $this->getChallengesForDate($date), // Get ALL active challenges
            'coverage_score' => 0,
            'status' => 'normal',
            'missing_coverage' => 0,
            'needs_manual_backup' => false
        ];
        
        // Get available posters (excluding those with ACTIVE challenges)
        $availablePosters = $this->getAvailablePosters($date, $dayName, $dailySchedule['challenges']);
        $dailySchedule['primary_posters'] = $this->selectPrimaryPosters($availablePosters, $dayName);
        
        // Calculate missing coverage
        $availablePrimaryCount = count($dailySchedule['primary_posters']);
        $requiredMin = $this->daily_requirements[$dayName]['min'];
        $missingPostersCount = max(0, $requiredMin - $availablePrimaryCount);
        $dailySchedule['missing_coverage'] = $missingPostersCount;
        
        // Assign admins to cover challenges
        if ($missingPostersCount > 0) {
            $dailySchedule['admin_cover'] = $this->assignAdminsForChallenges(
                $date, 
                $dayName, 
                $missingPostersCount, 
                $dailySchedule['challenges']
            );
            
            // Check if coverage is sufficient
            if (count($dailySchedule['admin_cover']) < $missingPostersCount) {
                $dailySchedule['needs_manual_backup'] = true;
                $dailySchedule['status'] = 'critical';
            }
        }
        
        // Assign backup admins
        $dailySchedule['backup_posters'] = $this->assignBackupPosters($date, $dayName, $dailySchedule['challenges'], $dailySchedule['admin_cover']);
        
        // Apply manual backups
        if (isset(self::$manualBackups[$date])) {
            $dailySchedule = $this->applyManualBackup($dailySchedule, $date);
        }
        
        // Calculate coverage score
        $dailySchedule['coverage_score'] = $this->calculateCoverageScore(
            $dailySchedule['primary_posters'],
            $dailySchedule['admin_cover'],
            $dailySchedule['backup_posters'],
            $dailySchedule['challenges'],
            $dayName
        );
        
        // Update status
        if ($dailySchedule['coverage_score'] < 70) {
            $dailySchedule['status'] = 'critical';
        } elseif ($dailySchedule['coverage_score'] < 90) {
            $dailySchedule['status'] = 'warning';
        }
        
        return $dailySchedule;
    }
    
    /**
     * Get available posters (exclude those with ACTIVE challenges)
     */
    private function getAvailablePosters($date, $dayName, $challenges) {
        $available = [];
        
        foreach ($this->posting_team as $name => $info) {
            $hasActiveChallenge = false;
            
            // Check if poster has an active challenge for this date
            foreach ($challenges as $challenge) {
                if ($challenge['poster_name'] === $name && $challenge['is_active']) {
                    $hasActiveChallenge = true;
                    break;
                }
            }
            
            if (!$hasActiveChallenge) {
                if ($dayName === 'saturday') {
                    $available[$name] = array_merge($info, [
                        'availability' => 'saturday_optional',
                        'mandatory' => false
                    ]);
                } elseif ($dayName === 'sunday') {
                    continue;
                } else {
                    $available[$name] = array_merge($info, [
                        'availability' => 'regular',
                        'mandatory' => true
                    ]);
                }
            }
        }
        
        return $available;
    }
    
    /**
     * Get ALL active challenges for date (PERSISTENT)
     */
    private function getChallengesForDate($date) {
        $challengesForDate = [];
        
        if (isset(self::$challenges[$date])) {
            foreach (self::$challenges[$date] as $challenge) {
                if ($challenge['is_active']) {
                    $challengesForDate[] = $challenge;
                }
            }
        }
        
        return $challengesForDate;
    }
    
    /**
     * Assign admins to cover active challenges
     */
    private function assignAdminsForChallenges($date, $dayName, $neededCount, $challenges) {
        $assignedAdmins = [];
        $adminUsage = $this->getAdminWeeklyUsage();
        
        // First, check if we already have admin assignments for these challenges
        if (isset(self::$adminAssignments[$date])) {
            foreach (self::$adminAssignments[$date] as $assignment) {
                if ($assignment['is_active']) {
                    $assignedAdmins[] = [
                        'name' => $assignment['admin_name'],
                        'role' => 'challenge_cover',
                        'department' => $this->admin_team[$assignment['admin_name']]['department'] ?? 'Unknown',
                        'notes' => 'Covering for: ' . $assignment['poster_name'],
                        'mandatory' => true,
                        'challenge_id' => $assignment['challenge_id'],
                        'assigned_at' => $assignment['assigned_at']
                    ];
                }
            }
        }
        
        // If we need more admins, assign new ones
        $currentCount = count($assignedAdmins);
        if ($currentCount < $neededCount) {
            $additionalNeeded = $neededCount - $currentCount;
            $newAssignments = $this->assignNewAdmins($date, $dayName, $additionalNeeded, $challenges, $adminUsage, $assignedAdmins);
            $assignedAdmins = array_merge($assignedAdmins, $newAssignments);
        }
        
        return $assignedAdmins;
    }
    
    /**
     * Assign new admins for challenges
     */
    private function assignNewAdmins($date, $dayName, $neededCount, $challenges, $adminUsage, $alreadyAssigned) {
        $newAssignments = [];
        $alreadyAssignedNames = array_column($alreadyAssigned, 'name');
        
        // Find challenges without admin assignments
        $uncoveredChallenges = [];
        if (isset(self::$challenges[$date])) {
            foreach (self::$challenges[$date] as $challenge) {
                if ($challenge['is_active']) {
                    $hasAdmin = false;
                    if (isset(self::$adminAssignments[$date])) {
                        foreach (self::$adminAssignments[$date] as $assignment) {
                            if ($assignment['challenge_id'] === $challenge['id'] && $assignment['is_active']) {
                                $hasAdmin = true;
                                break;
                            }
                        }
                    }
                    if (!$hasAdmin) {
                        $uncoveredChallenges[] = $challenge;
                    }
                }
            }
        }
        
        // Assign admins to uncovered challenges
        foreach ($uncoveredChallenges as $challenge) {
            if (count($newAssignments) >= $neededCount) break;
            
            $admin = $this->findAvailableAdmin($date, $dayName, $alreadyAssignedNames, $adminUsage);
            if ($admin) {
                // Create admin assignment
                $assignmentId = uniqid();
                self::$adminAssignments[$date][] = [
                    'id' => $assignmentId,
                    'challenge_id' => $challenge['id'],
                    'poster_name' => $challenge['poster_name'],
                    'admin_name' => $admin,
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'is_active' => true
                ];
                
                $newAssignments[] = [
                    'name' => $admin,
                    'role' => 'new_cover',
                    'department' => $this->admin_team[$admin]['department'] ?? 'Unknown',
                    'notes' => 'New assignment for: ' . $challenge['poster_name'],
                    'mandatory' => true,
                    'challenge_id' => $challenge['id']
                ];
                
                $alreadyAssignedNames[] = $admin;
                $adminUsage[$admin] = isset($adminUsage[$admin]) ? $adminUsage[$admin] + 1 : 1;
            }
        }
        
        return $newAssignments;
    }
    
    /**
     * Find available admin
     */
    private function findAvailableAdmin($date, $dayName, $excludeAdmins = [], $adminUsage = []) {
        foreach ($this->admin_team as $name => $info) {
            // Skip if excluded
            if (in_array($name, $excludeAdmins)) {
                continue;
            }
            
            // Check comfort days
            if (!in_array($dayName, $info['comfort_days'])) {
                continue;
            }
            
            // Check Patricia field days
            if ($name === 'Patricia Nakabugo' && $this->isPatriciaInField($date)) {
                continue;
            }
            
            // Check usage limit
            $maxDays = $info['max_days_per_week'] ?? 5;
            $currentUsage = isset($adminUsage[$name]) ? $adminUsage[$name] : 0;
            if ($currentUsage >= $maxDays) {
                continue;
            }
            
            return $name;
        }
        
        // If no admin found on comfort days, try any admin
        foreach ($this->admin_team as $name => $info) {
            if (in_array($name, $excludeAdmins)) continue;
            if ($name === 'Patricia Nakabugo' && $this->isPatriciaInField($date)) continue;
            
            return $name;
        }
        
        return null;
    }
    
    /**
     * Get admin weekly usage
     */
    private function getAdminWeeklyUsage() {
        $usage = [];
        
        if (self::$currentSchedule) {
            foreach (self::$currentSchedule as $date => $daySchedule) {
                foreach ($daySchedule['admin_cover'] as $admin) {
                    $name = $admin['name'];
                    $usage[$name] = isset($usage[$name]) ? $usage[$name] + 1 : 1;
                }
            }
        }
        
        return $usage;
    }
    
    /**
     * REPORT A CHALLENGE - PERSISTS until resolved
     */
    public function reportChallenge($posterName, $date, $challengeType, $severity = 2, $notes = '') {
        $challengeId = uniqid();
        $challenge = [
            'id' => $challengeId,
            'poster_name' => $posterName,
            'challenge_type' => $challengeType,
            'severity' => $severity,
            'notes' => $notes,
            'reported_at' => date('Y-m-d H:i:s'),
            'is_active' => true,
            'resolved_at' => null
        ];
        
        // Store challenge
        if (!isset(self::$challenges[$date])) {
            self::$challenges[$date] = [];
        }
        self::$challenges[$date][] = $challenge;
        
        // Regenerate schedule
        $this->regenerateScheduleForDate($date);
        
        return [
            'success' => true,
            'message' => "Challenge recorded for $posterName on $date",
            'challenge_id' => $challengeId,
            'action_required' => $severity >= 2 ? 'Admin coverage needed' : 'Monitor'
        ];
    }
    
    /**
     * RESOLVE CHALLENGE - Must be explicitly called
     */
    public function resolveChallenge($challengeId, $date) {
        if (!isset(self::$challenges[$date])) {
            return [
                'success' => false,
                'message' => "No challenges found for this date"
            ];
        }
        
        // Find and mark challenge as resolved
        foreach (self::$challenges[$date] as &$challenge) {
            if ($challenge['id'] === $challengeId && $challenge['is_active']) {
                $challenge['is_active'] = false;
                $challenge['resolved_at'] = date('Y-m-d H:i:s');
                
                // Move to resolved history
                self::$resolvedChallenges[] = $challenge;
                
                // UNASSIGN ADMIN if one was assigned
                if (isset(self::$adminAssignments[$date])) {
                    foreach (self::$adminAssignments[$date] as &$assignment) {
                        if ($assignment['challenge_id'] === $challengeId && $assignment['is_active']) {
                            $assignment['is_active'] = false;
                            $assignment['unassigned_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                }
                
                // Regenerate schedule
                $this->regenerateScheduleForDate($date);
                
                return [
                    'success' => true,
                    'message' => "Challenge resolved and admin unassigned (if any)",
                    'poster_name' => $challenge['poster_name'],
                    'action' => 'Poster returns to duty, admin unassigned'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => "Challenge not found or already resolved"
        ];
    }
    
    /**
     * GET ALL CHALLENGES (for display)
     */
    public function getAllChallenges() {
        $allChallenges = [];
        
        foreach (self::$challenges as $date => $challengesList) {
            foreach ($challengesList as $challenge) {
                if ($challenge['is_active']) {
                    $allChallenges[] = array_merge($challenge, ['date' => $date]);
                }
            }
        }
        
        return $allChallenges;
    }
    
    /**
     * MANUAL BACKUP - Doesn't affect challenges
     */
    public function addManualBackup($date, $adminName, $reason = '') {
        self::$manualBackups[$date] = [
            'admin_name' => $adminName,
            'reason' => $reason,
            'added_at' => date('Y-m-d H:i:s')
        ];
        
        $this->regenerateScheduleForDate($date);
        
        return [
            'success' => true,
            'message' => "Manual backup added for $date"
        ];
    }
    
    /**
     * REMOVE MANUAL BACKUP
     */
    public function removeManualBackup($date) {
        if (isset(self::$manualBackups[$date])) {
            unset(self::$manualBackups[$date]);
            $this->regenerateScheduleForDate($date);
            
            return [
                'success' => true,
                'message' => "Manual backup removed for $date"
            ];
        }
        
        return [
            'success' => false,
            'message' => "No manual backup found"
        ];
    }
    
    /**
     * Apply manual backup to schedule
     */
    private function applyManualBackup($dailySchedule, $date) {
        $manualBackup = self::$manualBackups[$date];
        
        $dailySchedule['admin_cover'][] = [
            'name' => $manualBackup['admin_name'],
            'role' => 'manual_backup',
            'department' => $this->admin_team[$manualBackup['admin_name']]['department'] ?? 'Unknown',
            'notes' => 'MANUAL: ' . $manualBackup['reason'],
            'mandatory' => true,
            'added_manually' => true
        ];
        
        return $dailySchedule;
    }
    
    /**
     * Get admin assignments for a date
     */
    public function getAdminAssignmentsForDate($date) {
        $assignments = [];
        
        if (isset(self::$adminAssignments[$date])) {
            foreach (self::$adminAssignments[$date] as $assignment) {
                if ($assignment['is_active']) {
                    $assignments[] = $assignment;
                }
            }
        }
        
        return $assignments;
    }
    
    /**
     * FORCE UNASSIGN ADMIN (manual button)
     */
    public function forceUnassignAdmin($date, $adminName) {
        if (!isset(self::$adminAssignments[$date])) {
            return [
                'success' => false,
                'message' => "No admin assignments found for this date"
            ];
        }
        
        foreach (self::$adminAssignments[$date] as &$assignment) {
            if ($assignment['admin_name'] === $adminName && $assignment['is_active']) {
                $assignment['is_active'] = false;
                $assignment['unassigned_at'] = date('Y-m-d H:i:s');
                $assignment['unassigned_reason'] = 'Manually unassigned';
                
                $this->regenerateScheduleForDate($date);
                
                return [
                    'success' => true,
                    'message' => "Admin $adminName unassigned from $date",
                    'poster_name' => $assignment['poster_name']
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => "Admin $adminName not found or already unassigned"
        ];
    }
    
    /**
     * Helper methods (unchanged)
     */
    private function selectPrimaryPosters($availablePosters, $dayName) {
        $selected = [];
        uasort($availablePosters, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        foreach ($availablePosters as $name => $info) {
            $selected[] = [
                'name' => $name,
                'role' => 'primary_poster',
                'priority' => $info['priority'],
                'mandatory' => $info['mandatory'] ?? false,
                'notes' => $info['availability'] === 'saturday_optional' ? 'Saturday posting' : 'Regular posting'
            ];
        }
        
        return $selected;
    }
    
    private function assignBackupPosters($date, $dayName, $challenges, $alreadyAssigned) {
        $backups = [];
        $alreadyAssignedNames = array_column($alreadyAssigned, 'name');
        
        foreach ($this->admin_team as $name => $info) {
            if (in_array($name, $alreadyAssignedNames)) continue;
            if (!in_array($dayName, $info['comfort_days'])) continue;
            if ($name === 'Patricia Nakabugo' && $this->isPatriciaInField($date)) continue;
            
            $backups[] = [
                'name' => $name,
                'role' => 'backup_admin',
                'department' => $info['department'],
                'notes' => 'Standby backup',
                'mandatory' => true
            ];
            
            if (count($backups) >= 2) break;
        }
        
        return $backups;
    }
    
    private function isPatriciaInField($date) {
        $dayOfWeek = date('N', strtotime($date));
        return in_array($dayOfWeek, [2, 4]);
    }
    
    private function calculateCoverageScore($primary, $adminCover, $backup, $challenges, $dayName) {
        $requiredMin = $this->daily_requirements[$dayName]['min'];
        $requiredIdeal = $this->daily_requirements[$dayName]['ideal'];
        
        $totalAssigned = count($primary) + count($adminCover);
        $mandatoryAssigned = 0;
        
        foreach ($primary as $poster) {
            if ($poster['mandatory'] ?? false) $mandatoryAssigned++;
        }
        foreach ($adminCover as $admin) {
            if ($admin['mandatory'] ?? false) $mandatoryAssigned++;
        }
        
        if ($mandatoryAssigned >= $requiredMin) {
            $baseScore = 70;
        } else {
            $baseScore = max(0, ($mandatoryAssigned / $requiredMin) * 70);
        }
        
        if ($totalAssigned >= $requiredIdeal) {
            $idealBonus = 30;
        } else {
            $idealBonus = ($totalAssigned / $requiredIdeal) * 30;
        }
        
        $backupBonus = count($backup) >= 2 ? 20 : (count($backup) * 10);
        
        $challengePenalty = 0;
        $severeChallenges = 0;
        foreach ($challenges as $challenge) {
            if ($challenge['severity'] >= 2) $severeChallenges++;
        }
        $challengePenalty = min(30, $severeChallenges * 10);
        
        $finalScore = $baseScore + $idealBonus + $backupBonus - $challengePenalty;
        return round(max(0, min(100, $finalScore)), 1);
    }
    
    private function regenerateScheduleForDate($date) {
        if (self::$currentSchedule === null || self::$scheduleWeekStart === null) return;
        
        $weekStart = self::$scheduleWeekStart;
        $dateTimestamp = strtotime($date);
        $weekStartTimestamp = strtotime($weekStart);
        $weekEndTimestamp = strtotime($weekStart . ' +6 days');
        
        if ($dateTimestamp >= $weekStartTimestamp && $dateTimestamp <= $weekEndTimestamp) {
            $this->generateWeeklySchedule($weekStart, true);
        }
    }
    
    public function getWeeklyStats($schedule) {
        $stats = [
            'total_days' => count($schedule),
            'days_fully_staffed' => 0,
            'days_minimum_met' => 0,
            'days_critical' => 0,
            'total_coverage_score' => 0,
            'active_challenges_count' => 0,
            'admin_coverage_days' => 0
        ];
        
        foreach ($schedule as $date => $daySchedule) {
            $totalPeople = count($daySchedule['primary_posters']) + count($daySchedule['admin_cover']);
            
            if ($totalPeople >= $this->daily_requirements[$daySchedule['day']]['ideal']) {
                $stats['days_fully_staffed']++;
            }
            
            if ($totalPeople >= $this->daily_requirements[$daySchedule['day']]['min']) {
                $stats['days_minimum_met']++;
            }
            
            if ($daySchedule['status'] === 'critical') {
                $stats['days_critical']++;
            }
            
            $stats['total_coverage_score'] += $daySchedule['coverage_score'];
            $stats['active_challenges_count'] += count($daySchedule['challenges']);
            
            if (count($daySchedule['admin_cover']) > 0) {
                $stats['admin_coverage_days']++;
            }
        }
        
        $stats['average_coverage'] = round($stats['total_coverage_score'] / $stats['total_days'], 1);
        $stats['consistency_percentage'] = $stats['average_coverage'];
        
        return $stats;
    }
}
?>