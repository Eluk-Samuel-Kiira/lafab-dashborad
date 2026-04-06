<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
    /* Dashboard specific styles that complement existing layout */
    .dashboard-content-wrapper {
        padding: 0;
    }

    /* welcome hero card */
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

    .welcome-message h2 i {
        color: #2c7da0;
        margin-right: 10px;
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

    .platform-pill i {
        margin-right: 6px;
    }

    .platform-pill:hover {
        background: #2c7da0;
        color: white;
        transform: translateY(-2px);
    }

    /* Grid layouts */
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

    /* Competitor rows */
    .competitor-list {
        margin-top: 6px;
    }

    .comp-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #edf2f7;
        font-size: 0.85rem;
    }

    .comp-name {
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rank-badge {
        background: #eef2ff;
        border-radius: 30px;
        padding: 3px 10px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .green-rank {
        color: #15803d;
        font-weight: 700;
    }

    .warning-rank {
        color: #b45309;
    }

    .keyword-score {
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
    }

    /* QA items */
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

    /* Process steps */
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
        font-size: 0.9rem;
    }

    .step-status {
        font-size: 0.7rem;
        font-weight: 600;
        background: #dff9e6;
        padding: 3px 10px;
        border-radius: 30px;
        color: #2b6e3c;
    }

    /* Social stats */
    .social-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
    }

    .social-item {
        flex: 1;
        text-align: center;
        background: #f9fafc;
        border-radius: 20px;
        padding: 10px 6px;
        transition: all 0.2s;
    }

    .social-item:hover {
        background: #eef2ff;
        transform: scale(1.02);
    }

    .social-item i {
        font-size: 1.6rem;
        margin-bottom: 6px;
        display: block;
        color: #3b82f6;
    }

    .social-count {
        font-weight: 800;
        font-size: 1.1rem;
    }

    .social-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        color: #4b5563;
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

    /* Bottom panels */
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
        font-size: 1.2rem;
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
        .welcome-message h2 { font-size: 1.2rem; }
        .card-header h3 { font-size: 1.1rem; }
        .stat-card { padding: 16px; }
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
            </div>
        </div>
        <div class="welcome-message">
            <h2><i class="fas fa-hand-sparkles"></i> Welcome back, Operations & Strategy Team</h2>
            <p>This dashboard provides real-time visibility into SEO rankings vs competitors, job posting quality assurance, business process adherence, efficiency best practices, and cross-platform social metrics. Powered by Lafab IT — driving data-informed recruitment excellence across Great Uganda Jobs, Great Kenya, Tanzania, Rwanda, Zambia, Malawi, and Great Australia Jobs.</p>
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
        <!-- SEO & Ranking Card -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i>
                <h3>SEO & Ranking vs Competitors</h3>
            </div>
            <div class="competitor-list">
                <div class="comp-row">
                    <div class="comp-name"><i class="fab fa-google"></i> GreatUgandaJobs</div>
                    <div><span class="rank-badge green-rank">#2 (↑1)</span> <span class="keyword-score">KW: 4.2k</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-name"><i class="fas fa-building"></i> BrighterMonday UG</div>
                    <div><span class="rank-badge">#3</span> <span class="keyword-score">KW: 3.1k</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-name"><i class="fab fa-google"></i> GreatKenya</div>
                    <div><span class="rank-badge green-rank">#1 (★)</span> <span class="keyword-score">KW: 8.7k</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-name"><i class="fas fa-chart-simple"></i> Fuzu KE</div>
                    <div><span class="rank-badge warning-rank">#4</span> <span class="keyword-score">KW: 2.9k</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-name"><i class="fab fa-google"></i> GreatAustraliaJobs</div>
                    <div><span class="rank-badge green-rank">#3 (↑2)</span> <span class="keyword-score">KW: 11.2k</span></div>
                </div>
                <div class="comp-row">
                    <div class="comp-name"><i class="fas fa-chart-simple"></i> Seek AU (benchmark)</div>
                    <div><span class="rank-badge">#1</span> <span class="keyword-score">KW: 24k</span></div>
                </div>
                <div class="insight-note">
                    <i class="fas fa-chart-simple"></i> Google ranking improved for TZ, RW, ZM — +15% visibility last 30 days.
                </div>
            </div>
        </div>

        <!-- Quality Assurance Card -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-clipboard-list"></i>
                <h3>Job Posting QA</h3>
            </div>
            <div class="qa-item">
                <div class="qa-header"><span>✅ Accuracy score (fields completeness)</span><span>94%</span></div>
                <div class="progress-bar"><div class="progress-fill fill-high" style="width:94%"></div></div>
            </div>
            <div class="qa-item">
                <div class="qa-header"><span>📝 Duplicate detection rate</span><span>99.2%</span></div>
                <div class="progress-bar"><div class="progress-fill fill-high" style="width:99%"></div></div>
            </div>
            <div class="qa-item">
                <div class="qa-header"><span>⚡ Formatting & grammar AI check</span><span>88%</span></div>
                <div class="progress-bar"><div class="progress-fill fill-mid" style="width:88%"></div></div>
            </div>
            <div class="qa-item">
                <div class="qa-header"><span>🏷️ Salary & location validation</span><span>91%</span></div>
                <div class="progress-bar"><div class="progress-fill fill-high" style="width:91%"></div></div>
            </div>
            <div class="insight-note">
                <i class="fas fa-eye"></i> Weekly QA audit: 310 postings verified; non-compliant flagged within 2h.
            </div>
        </div>

        <!-- Business Process Adherence Card -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-diagram-project"></i>
                <h3>Business Process Adherence</h3>
            </div>
            <div class="process-step">
                <div class="step-icon"><i class="fas fa-file-signature"></i></div>
                <div class="step-text">Client brief & role intake</div>
                <div class="step-status">100%</div>
            </div>
            <div class="process-step">
                <div class="step-icon"><i class="fas fa-search"></i></div>
                <div class="step-text">Sourcing & screening workflow</div>
                <div class="step-status">98%</div>
            </div>
            <div class="process-step">
                <div class="step-icon"><i class="fas fa-check-double"></i></div>
                <div class="step-text">Posting approval & QA gate</div>
                <div class="step-status">96%</div>
            </div>
            <div class="process-step">
                <div class="step-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="step-text">Client feedback loop & reporting</div>
                <div class="step-status">91%</div>
            </div>
            <div class="insight-note">
                <i class="fas fa-clock"></i> Process cycle time improved 22% MoM, standard SOP adoption across 7 domains.
            </div>
        </div>
    </div>

    <!-- SECOND ROW: Social Media Stats + Efficiency & Best Practices -->
    <div class="grid-stats">
        <!-- Social Media Performance -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-share-alt"></i>
                <h3>Social Media Performance</h3>
            </div>
            <div class="social-grid">
                <div class="social-item"><i class="fab fa-facebook-f"></i><div class="social-count">127.4K</div><div class="social-label">Followers</div><span style="font-size:10px;">+4.2%</span></div>
                <div class="social-item"><i class="fab fa-linkedin-in"></i><div class="social-count">89.2K</div><div class="social-label">Engagement</div><span style="font-size:10px;">+6.8%</span></div>
                <div class="social-item"><i class="fab fa-twitter"></i><div class="social-count">43.6K</div><div class="social-label">Mentions</div><span style="font-size:10px;">+2.1%</span></div>
                <div class="social-item"><i class="fab fa-instagram"></i><div class="social-count">61.3K</div><div class="social-label">Reach</div><span style="font-size:10px;">+12%</span></div>
            </div>
            <div class="social-grid" style="margin-top: 12px;">
                <div class="social-item"><i class="fab fa-tiktok"></i><div class="social-count">28.7K</div><div class="social-label">Shares (Jobs)</div></div>
                <div class="social-item"><i class="fab fa-youtube"></i><div class="social-count">9.2K</div><div class="social-label">Subs</div></div>
                <div class="social-item"><i class="fab fa-whatsapp"></i><div class="social-count">15k+</div><div class="social-label">Groups</div></div>
            </div>
            <div class="insight-note">
                <i class="fas fa-chart-line"></i> Top performing: LinkedIn job posts drive 38% of external traffic. Great AustraliaJobs social referral +17% WoW.
            </div>
        </div>

        <!-- Efficiency & Best Practices -->
        <div class="stat-card">
            <div class="card-header">
                <i class="fas fa-rocket"></i>
                <h3>Efficiency & Best Practices</h3>
            </div>
            <div class="practice-list">
                <li><i class="fas fa-check-circle"></i> <strong>Automated job scraping alerts</strong> – reduce time-to-fill by 28%</li>
                <li><i class="fas fa-check-circle"></i> <strong>AI-powered resume screening</strong> – 82% faster shortlisting</li>
                <li><i class="fas fa-check-circle"></i> <strong>Real-time SEO monitoring</strong> – weekly competitor heatmaps</li>
                <li><i class="fas fa-check-circle"></i> <strong>Cross-platform syndication</strong> – publish once, reach 7 job boards</li>
                <li><i class="fas fa-check-circle"></i> <strong>Internal process audits</strong> – 99.5% SLA compliance for posting</li>
            </div>
            <div class="efficiency-meter">
                <div style="display: flex; justify-content: space-between;"><span>Operational efficiency score</span><strong>93/100</strong></div>
                <div class="progress-bar" style="margin: 12px 0;"><div class="progress-fill fill-high" style="width:93%"></div></div>
                <div><i class="fas fa-charging-station"></i> Time saved: ≈ 14hrs/week via process automation & best practice implementation.</div>
            </div>
        </div>
    </div>

    <!-- BOTTOM DETAIL PANELS -->
    <div class="bottom-panels">
        <div class="info-panel">
            <h4><i class="fas fa-spinner"></i> Execution steps | Lafab workflow</h4>
            <div class="process-step" style="margin-bottom: 12px;">
                <div class="step-icon">1</div><div class="step-text">Requirement gathering & market mapping</div><div class="step-status">on track</div>
            </div>
            <div class="process-step" style="margin-bottom: 12px;">
                <div class="step-icon">2</div><div class="step-text">Multi-channel job posting & distribution</div><div class="step-status">active</div>
            </div>
            <div class="process-step" style="margin-bottom: 12px;">
                <div class="step-icon">3</div><div class="step-text">Candidate pre-screening & matching (AI)</div><div class="step-status">94% match</div>
            </div>
            <div class="process-step" style="margin-bottom: 12px;">
                <div class="step-icon">4</div><div class="step-text">Interview scheduling & feedback loops</div><div class="step-status">automated</div>
            </div>
            <div class="process-step">
                <div class="step-icon">5</div><div class="step-text">Placement & on-boarding analytics</div><div class="step-status">dashboard live</div>
            </div>
        </div>
        <div class="info-panel">
            <h4><i class="fas fa-chart-gantt"></i> Best practice impact — KPIs</h4>
            <ul class="practice-list">
                <li><i class="fas fa-chart-simple"></i> Posting-to-hire cycle reduced by 18% (target 25% Q4)</li>
                <li><i class="fas fa-chart-simple"></i> SEO visibility growth: +32% YoY for Great Kenya/Tanzania</li>
                <li><i class="fas fa-chart-simple"></i> Social share of job posts: 214% increase from automated campaigns</li>
                <li><i class="fas fa-chart-simple"></i> QA non-conformance below 3.5% for all regions</li>
                <li><i class="fas fa-chart-simple"></i> Australian market CTR +9.1% after schema markup</li>
            </ul>
            <hr>
            <div><i class="fas fa-tachometer-alt"></i> <strong>IT Efficiency insight:</strong> Google Core Web Vitals improved for all domains, driving better ranking against competitors.</div>
        </div>
    </div>

    <div class="dashboard-footer-note">
        <i class="fas fa-database"></i> Lafab Solutions IT Department · Live intelligence feed | Data refreshes every 6h · © 2025 HR Command Center
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>