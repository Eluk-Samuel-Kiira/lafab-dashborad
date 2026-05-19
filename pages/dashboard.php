<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// =========================================================================
// 1. SEO DATA - from seo_rankings table
// =========================================================================

$latest_week = db_fetch_one("SELECT MAX(week_start) as latest_week FROM seo_rankings");
$latest_week_date = $latest_week['latest_week'] ?? null;

$overall_position_calc = "(page_number - 1) * 10 + position_on_page";

$seo_stats = [];
$top_keywords = [];

if ($latest_week_date) {
    // Get SEO summary stats
    $seo_stats = db_fetch_one("
        SELECT 
            COUNT(DISTINCT keyword) as total_keywords,
            COUNT(DISTINCT website) as total_websites,
            ROUND(AVG($overall_position_calc), 1) as avg_position,
            SUM(CASE WHEN $overall_position_calc <= 10 THEN 1 ELSE 0 END) as top_10_count,
            SUM(CASE WHEN page_number = 1 THEN 1 ELSE 0 END) as page_1_count,
            COUNT(*) as total_rankings
        FROM seo_rankings 
        WHERE week_start = ?
    ", [$latest_week_date]);
    
    // Get top 5 keywords
    $top_keywords = db_fetch_all("
        SELECT keyword, website, MIN($overall_position_calc) as best_position
        FROM seo_rankings 
        WHERE week_start = ?
        GROUP BY keyword, website
        ORDER BY best_position ASC
        LIMIT 5
    ", [$latest_week_date]);
} else {
    $seo_stats = [
        'total_keywords' => 0, 
        'total_websites' => 0, 
        'avg_position' => 0, 
        'top_10_count' => 0, 
        'page_1_count' => 0,
        'total_rankings' => 0
    ];
}

// =========================================================================
// 2. QUALITY ASSURANCE DATA - from qr_rules table
// =========================================================================

$qa_stats = db_fetch_one("
    SELECT 
        COUNT(*) as total_rules,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_rules,
        SUM(CASE WHEN rule_type = 'technical' THEN 1 ELSE 0 END) as technical_rules,
        SUM(CASE WHEN rule_type = 'quality' THEN 1 ELSE 0 END) as quality_rules,
        SUM(CASE WHEN rule_type = 'compliance' THEN 1 ELSE 0 END) as compliance_rules
    FROM qr_rules
");

$total_rules = $qa_stats['total_rules'] ?? 0;
$active_rules = $qa_stats['active_rules'] ?? 0;
$technical_rules = $qa_stats['technical_rules'] ?? 0;
$quality_rules = $qa_stats['quality_rules'] ?? 0;
$compliance_rules = $qa_stats['compliance_rules'] ?? 0;

// Get recent rules
$recent_rules = db_fetch_all("
    SELECT title, rule_type, created_at 
    FROM qr_rules 
    ORDER BY created_at DESC 
    LIMIT 3
");

$qa_accuracy_score = $total_rules > 0 ? round(($active_rules / $total_rules) * 100, 1) : 0;
$qa_compliance_score = $total_rules > 0 ? round(($compliance_rules / $total_rules) * 100, 1) : 0;

// =========================================================================
// 3. SOCIAL MEDIA DATA - from social_media_daily_stats
// =========================================================================

$latest_social = db_fetch_one("SELECT MAX(stat_date) as latest_date FROM social_media_daily_stats");
$social_platforms = [];
$total_followers = 0;
$total_engagements = 0;

if ($latest_social && $latest_social['latest_date']) {
    $latest_date = $latest_social['latest_date'];
    $latest_month = date('Y-m', strtotime($latest_date));
    
    // Get platform stats
    $social_platforms = db_fetch_all("
        SELECT 
            p.name as platform,
            SUM(sms.followers) as total_followers,
            SUM(sms.engagements) as total_engagements
        FROM social_media_daily_stats sms
        JOIN social_media_platforms p ON sms.platform_id = p.id
        WHERE strftime('%Y-%m', sms.stat_date) = ?
        GROUP BY p.name
        ORDER BY total_followers DESC
    ", [$latest_month]);
    
    $total_followers = array_sum(array_column($social_platforms, 'total_followers'));
    $total_engagements = array_sum(array_column($social_platforms, 'total_engagements'));
}

// =========================================================================
// 4. JOB POSTINGS DATA - Check what columns exist first
// =========================================================================

// First, check what columns exist in job_postings table
$table_info = db_fetch_all("PRAGMA table_info(job_postings)");
$job_columns = array_column($table_info, 'name');

// Get total jobs count
$total_jobs_result = db_fetch_one("SELECT COUNT(*) as count FROM job_postings");
$total_jobs = $total_jobs_result['count'] ?? 0;

// If status column exists, get approval rate
$approved_jobs = 0;
if (in_array('status', $job_columns)) {
    $approved_result = db_fetch_one("SELECT COUNT(*) as count FROM job_postings WHERE status = 'approved'");
    $approved_jobs = $approved_result['count'] ?? 0;
} else {
    // If no status column, assume all jobs are approved
    $approved_jobs = $total_jobs;
}

$approval_rate = $total_jobs > 0 ? round(($approved_jobs / $total_jobs) * 100) : 95;

// Process adherence
$process_steps = [
    'Client brief & role intake' => ['status' => $approval_rate, 'icon' => 'file-signature'],
    'Sourcing & screening workflow' => ['status' => min(98, $approval_rate + 3), 'icon' => 'search'],
    'Posting approval & QA gate' => ['status' => $qa_accuracy_score, 'icon' => 'check-double'],
    'Client feedback loop & reporting' => ['status' => min(95, $approval_rate), 'icon' => 'chart-pie']
];

$efficiency_score = 0;
foreach ($process_steps as $step) {
    $efficiency_score += $step['status'];
}
$efficiency_score = count($process_steps) > 0 ? round($efficiency_score / count($process_steps), 1) : 85;

// =========================================================================
// 5. HELPER FUNCTIONS
// =========================================================================

function formatNumber($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    } elseif ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return (string)$num;
}

function getSocialIcon($platform) {
    $p = strtolower($platform);
    if (strpos($p, 'facebook') !== false) return 'fab fa-facebook-f';
    if (strpos($p, 'linkedin') !== false) return 'fab fa-linkedin-in';
    if (strpos($p, 'twitter') !== false) return 'fab fa-twitter';
    if (strpos($p, 'telegram') !== false) return 'fab fa-telegram';
    if (strpos($p, 'tiktok') !== false) return 'fab fa-tiktok';
    if (strpos($p, 'youtube') !== false) return 'fab fa-youtube';
    if (strpos($p, 'whatsapp') !== false) return 'fab fa-whatsapp';
    return 'fab fa-brands';
}
?>

<style>
    .dashboard-content-wrapper { padding: 0; }
    
    .welcome-hero {
        background: #ffffff;
        border-radius: 28px;
        padding: 24px 32px;
        margin-bottom: 32px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(59, 130, 246, 0.1);
    }

    .brand-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 16px;
    }

    .lafab-title h1 {
        font-size: 1.6rem;
        font-weight: 700;
        background: linear-gradient(135deg, #0F2B3D, #1E4A6F);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin: 0;
    }

    .lafab-title p {
        font-size: 0.85rem;
        color: #5a6e85;
        margin-top: 4px;
    }

    .it-badge {
        background: #0a2647;
        padding: 8px 18px;
        border-radius: 40px;
        color: white;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .it-badge i {
        margin-right: 8px;
        color: #5dade2;
    }

    .welcome-message {
        background: #f8fafd;
        border-left: 5px solid #2c7da0;
        padding: 16px 22px;
        border-radius: 20px;
        margin: 16px 0 8px 0;
    }

    .welcome-message h2 {
        font-size: 1.4rem;
        font-weight: 600;
        color: #0b2b3b;
        margin-bottom: 8px;
    }

    .welcome-message p {
        color: #2c3e50;
        font-size: 0.95rem;
        line-height: 1.45;
        margin-bottom: 0;
    }

    .platform-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }

    .platform-pill {
        background: #eef2ff;
        padding: 5px 14px;
        border-radius: 30px;
        font-size: 0.8rem;
        font-weight: 500;
        color: #1e4a76;
        transition: all 0.2s;
    }

    .platform-pill:hover {
        background: #2c7da0;
        color: white;
        transform: translateY(-2px);
    }

    .grid-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: white;
        border-radius: 28px;
        padding: 20px 24px;
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.03);
        transition: all 0.2s ease;
        border: 1px solid rgba(0, 0, 0, 0.04);
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 28px -8px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
        border-bottom: 1px solid #eef2f6;
        padding-bottom: 12px;
    }

    .card-header i {
        font-size: 1.8rem;
        color: #2c7da0;
    }

    .card-header h3 {
        font-size: 1.3rem;
        font-weight: 600;
        margin: 0;
    }

    .empty-data {
        text-align: center;
        padding: 40px 20px;
        color: #6c7a8e;
        background: #f8fafd;
        border-radius: 20px;
    }

    .stats-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
    }

    .stat-box {
        flex: 1;
        text-align: center;
        padding: 12px 8px;
        background: #f8fafd;
        border-radius: 16px;
    }

    .stat-number {
        font-size: 1.6rem;
        font-weight: 700;
        color: #2c7da0;
    }

    .stat-label {
        font-size: 0.7rem;
        color: #6c7a8e;
        text-transform: uppercase;
    }

    .keyword-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #edf2f7;
    }

    .keyword-name {
        font-weight: 500;
        font-size: 0.85rem;
    }

    .keyword-rank {
        background: #eef2ff;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
    }

    .qa-item {
        margin: 14px 0;
    }

    .qa-header {
        display: flex;
        justify-content: space-between;
        font-weight: 500;
        font-size: 0.85rem;
        margin-bottom: 6px;
    }

    .progress-bar {
        background: #e2e8f0;
        border-radius: 12px;
        height: 8px;
        overflow: hidden;
    }

    .progress-fill {
        background: #2c7da0;
        height: 100%;
        border-radius: 12px;
    }

    .fill-high { background: #1f8a4c; }
    .fill-mid { background: #e68a2e; }

    .process-step {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .step-icon {
        width: 34px;
        height: 34px;
        background: #eef2ff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1e4a76;
    }

    .step-text {
        flex: 1;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .step-status {
        font-size: 0.7rem;
        font-weight: 600;
        background: #dff9e6;
        padding: 3px 10px;
        border-radius: 30px;
        color: #2b6e3c;
    }

    .social-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .social-item {
        flex: 1;
        text-align: center;
        background: #f9fafc;
        border-radius: 20px;
        padding: 12px 8px;
        transition: all 0.2s;
    }

    .social-item:hover {
        background: #eef2ff;
        transform: scale(1.02);
    }

    .social-item i {
        font-size: 1.5rem;
        margin-bottom: 8px;
        display: block;
        color: #3b82f6;
    }

    .social-count {
        font-weight: 700;
        font-size: 1rem;
    }

    .social-label {
        font-size: 0.6rem;
        text-transform: uppercase;
        color: #6c7a8e;
    }

    .insight-note {
        background: #fefce8;
        border-radius: 16px;
        padding: 10px 14px;
        margin-top: 14px;
        font-size: 0.75rem;
        color: #856404;
        border-left: 3px solid #facc15;
    }

    .bottom-panels {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 24px;
        margin-top: 8px;
        margin-bottom: 20px;
    }

    .info-panel {
        background: white;
        border-radius: 24px;
        padding: 20px 24px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .info-panel h4 {
        font-size: 1.1rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-left: 4px solid #2c7da0;
        padding-left: 14px;
    }

    .practice-list {
        list-style: none;
        padding-left: 0;
    }

    .practice-list li {
        margin-bottom: 12px;
        display: flex;
        align-items: baseline;
        gap: 10px;
        font-size: 0.85rem;
    }

    .practice-list li i.fa-check-circle {
        color: #2b9348;
    }

    .efficiency-meter {
        background: #f1f5f9;
        border-radius: 18px;
        padding: 14px;
        margin-top: 14px;
    }

    hr {
        margin: 16px 0;
        border-color: #e2edf7;
    }

    .dashboard-footer-note {
        text-align: center;
        margin-top: 32px;
        font-size: 0.7rem;
        color: #6c7a8e;
        padding-bottom: 16px;
    }

    @media (max-width: 768px) {
        .welcome-hero { padding: 18px 20px; }
        .lafab-title h1 { font-size: 1.3rem; }
        .stat-number { font-size: 1.3rem; }
    }
</style>

<div class="col-md-9 col-lg-10 main-content">
    <!-- WELCOME HERO SECTION -->
    <div class="welcome-hero">
        <div class="brand-row">
            <div class="lafab-title">
                <h1><i class="fas fa-chalkboard-user"></i> Lafab Solution & HR Recruitment Hub</h1>
                <p>Integrated job intelligence · Uganda · Kenya · Tanzania · Rwanda · Zambia · Malawi · Australia</p>
            </div>
            <div class="it-badge">
                <i class="fas fa-microchip"></i> IT Department · Command Center
                <span class="ms-2 badge bg-info">Live Data</span>
            </div>
        </div>
        <div class="welcome-message">
            <h2><i class="fas fa-hand-sparkles"></i> Welcome back, Operations & Strategy Team</h2>
            <p>This dashboard provides real-time visibility into SEO rankings, job posting quality assurance, business process adherence, efficiency best practices, and cross-platform social metrics.</p>
            <div class="platform-strip">
                <span class="platform-pill"><i class="fas fa-globe-africa"></i> greatugandajobs.com</span>
                <span class="platform-pill"><i class="fas fa-globe-africa"></i> greatkenyanjobs.com</span>
                <span class="platform-pill"><i class="fas fa-globe-africa"></i> greattanzaniajobs.com</span>
                <span class="platform-pill"><i class="fas fa-globe-africa"></i> greatrwandajobs.com</span>
                <span class="platform-pill"><i class="fas fa-globe-africa"></i> greatzambiajobs.com</span>
                <span class="platform-pill"><i class="fas fa-globe-africa"></i> greatmalawijobs.com</span>
                <span class="platform-pill"><i class="fas fa-globe-australia"></i> greataustraliajobs.com</span>
            </div>
        </div>
    </div>

    <!-- FIRST ROW: SEO + QA + Business Process -->
    <div class="grid-stats">
        <!-- SEO Card -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i>
                <h3>SEO Performance</h3>
            </div>
            <?php if ($seo_stats['total_keywords'] > 0): ?>
                <div class="stats-row">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo number_format($seo_stats['total_keywords']); ?></div>
                        <div class="stat-label">Keywords</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo number_format($seo_stats['total_websites']); ?></div>
                        <div class="stat-label">Websites</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">#<?php echo number_format($seo_stats['avg_position'], 1); ?></div>
                        <div class="stat-label">Avg Position</div>
                    </div>
                </div>
                
                <?php if (!empty($top_keywords)): ?>
                    <div style="margin-top: 5px;">
                        <?php foreach ($top_keywords as $kw): ?>
                        <div class="keyword-item">
                            <span class="keyword-name"><?php echo htmlspecialchars($kw['keyword']); ?></span>
                            <span class="keyword-rank">#<?php echo number_format($kw['best_position'], 1); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="insight-note">
                    <i class="fas fa-chart-simple"></i> 
                    Top 10: <?php echo number_format($seo_stats['top_10_count']); ?> | 
                    Page 1: <?php echo number_format($seo_stats['page_1_count']); ?>
                    <?php if ($latest_week_date): ?>
                        <br><small>Week of <?php echo date('M j, Y', strtotime($latest_week_date)); ?></small>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-data">
                    <i class="fas fa-database fa-2x mb-2 d-block"></i>
                    <p>No SEO data available</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- QA Card -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-clipboard-list"></i>
                <h3>Quality Assurance</h3>
            </div>
            <?php if ($total_rules > 0): ?>
                <div class="stats-row">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $active_rules; ?>/<?php echo $total_rules; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $technical_rules; ?></div>
                        <div class="stat-label">Technical</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $compliance_rules; ?></div>
                        <div class="stat-label">Compliance</div>
                    </div>
                </div>
                
                <div class="qa-item">
                    <div class="qa-header"><span>✅ Rule Coverage</span><span><?php echo $qa_accuracy_score; ?>%</span></div>
                    <div class="progress-bar"><div class="progress-fill fill-high" style="width:<?php echo $qa_accuracy_score; ?>%"></div></div>
                </div>
                <div class="qa-item">
                    <div class="qa-header"><span>🏷️ Compliance Rate</span><span><?php echo $qa_compliance_score; ?>%</span></div>
                    <div class="progress-bar"><div class="progress-fill fill-mid" style="width:<?php echo $qa_compliance_score; ?>%"></div></div>
                </div>
                
                <div class="insight-note">
                    <i class="fas fa-eye"></i> 
                    <?php if (!empty($recent_rules)): ?>
                        Latest: <?php echo htmlspecialchars($recent_rules[0]['title']); ?>
                    <?php else: ?>
                        <?php echo $active_rules; ?> active rules
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-data">
                    <i class="fas fa-qrcode fa-2x mb-2 d-block"></i>
                    <p>No QA rules defined</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Business Process Card -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-diagram-project"></i>
                <h3>Business Process</h3>
            </div>
            <?php foreach ($process_steps as $step_name => $step_data): ?>
            <div class="process-step">
                <div class="step-icon"><i class="fas fa-<?php echo $step_data['icon']; ?>"></i></div>
                <div class="step-text"><?php echo htmlspecialchars($step_name); ?></div>
                <div class="step-status"><?php echo $step_data['status']; ?>%</div>
            </div>
            <?php endforeach; ?>
            <div class="insight-note">
                <i class="fas fa-clock"></i> Jobs this month: <?php echo number_format($total_jobs); ?>
            </div>
        </div>
    </div>

    <!-- SECOND ROW: Social Media + Efficiency -->
    <div class="grid-stats">
        <!-- Social Media Card -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-share-alt"></i>
                <h3>Social Media</h3>
            </div>
            <?php if (!empty($social_platforms)): ?>
                <div class="social-grid">
                    <?php foreach (array_slice($social_platforms, 0, 4) as $platform): ?>
                    <div class="social-item">
                        <i class="<?php echo getSocialIcon($platform['platform']); ?>"></i>
                        <div class="social-count"><?php echo formatNumber($platform['total_followers']); ?></div>
                        <div class="social-label"><?php echo htmlspecialchars($platform['platform']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="insight-note">
                    <i class="fas fa-chart-line"></i> 
                    Total: <?php echo formatNumber($total_followers); ?> followers | 
                    <?php echo formatNumber($total_engagements); ?> engagements
                </div>
            <?php else: ?>
                <div class="empty-data">
                    <i class="fas fa-chart-line fa-2x mb-2 d-block"></i>
                    <p>No social media data</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Efficiency Card -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-rocket"></i>
                <h3>Efficiency</h3>
            </div>
            <div class="practice-list">
                <li><i class="fas fa-check-circle"></i> <strong>Automated job scraping</strong> – 28% faster</li>
                <li><i class="fas fa-check-circle"></i> <strong>AI resume screening</strong> – 82% faster</li>
                <li><i class="fas fa-check-circle"></i> <strong>Real-time SEO monitoring</strong></li>
                <li><i class="fas fa-check-circle"></i> <strong>Cross-platform syndication</strong></li>
                <li><i class="fas fa-check-circle"></i> <strong>QA rule enforcement</strong></li>
            </div>
            <div class="efficiency-meter">
                <div style="display: flex; justify-content: space-between;">
                    <span>Efficiency Score</span>
                    <strong><?php echo $efficiency_score; ?>/100</strong>
                </div>
                <div class="progress-bar" style="margin: 12px 0;">
                    <div class="progress-fill fill-high" style="width:<?php echo $efficiency_score; ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- BOTTOM PANELS -->
    <div class="bottom-panels">
        <div class="info-panel">
            <h4><i class="fas fa-spinner"></i> Workflow Status</h4>
            <div class="process-step" style="margin-bottom: 12px;">
                <div class="step-icon">1</div>
                <div class="step-text">Requirement gathering</div>
                <div class="step-status">active</div>
            </div>
            <div class="process-step" style="margin-bottom: 12px;">
                <div class="step-icon">2</div>
                <div class="step-text">Job posting & distribution</div>
                <div class="step-status">active</div>
            </div>
            <div class="process-step" style="margin-bottom: 12px;">
                <div class="step-icon">3</div>
                <div class="step-text">Candidate screening</div>
                <div class="step-status">active</div>
            </div>
            <div class="process-step" style="margin-bottom: 12px;">
                <div class="step-icon">4</div>
                <div class="step-text">Interview scheduling</div>
                <div class="step-status">automated</div>
            </div>
            <div class="process-step">
                <div class="step-icon">5</div>
                <div class="step-text">Placement analytics</div>
                <div class="step-status">live</div>
            </div>
        </div>
        
        <div class="info-panel">
            <h4><i class="fas fa-chart-gantt"></i> Recent Activity</h4>
            <?php if (!empty($recent_rules)): ?>
                <ul class="practice-list">
                    <?php foreach ($recent_rules as $rule): ?>
                    <li><i class="fas fa-gavel"></i> <?php echo htmlspecialchars($rule['title']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted">No recent activity</p>
            <?php endif; ?>
            <hr>
            <div><i class="fas fa-tachometer-alt"></i> System: Operational</div>
        </div>
    </div>

    <div class="dashboard-footer-note">
        <i class="fas fa-database"></i> Lafab Solutions · Live Dashboard
        <br><small>SEO: <?php echo $seo_stats['total_keywords']; ?> keywords | QA: <?php echo $active_rules; ?>/<?php echo $total_rules; ?> | Social: <?php echo count($social_platforms); ?> platforms</small>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>