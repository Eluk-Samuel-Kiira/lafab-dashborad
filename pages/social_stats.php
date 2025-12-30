<?php
require_once '../config.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get filter parameters
$platform_filter = $_GET['platform'] ?? '';
$country_filter = $_GET['country'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');

// Build WHERE clause
$where_conditions = ["strftime('%Y', stat_date) = ?"];
$params = [$year_filter];

if ($platform_filter) {
    $where_conditions[] = "p.name = ?";
    $params[] = $platform_filter;
}

if ($country_filter) {
    $where_conditions[] = "sms.country = ?";
    $params[] = $country_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get platforms and countries for filters
$platforms = db_fetch_all("SELECT name FROM social_media_platforms ORDER BY name");
$countries = db_fetch_all("SELECT DISTINCT country FROM social_media_daily_stats ORDER BY country");
$years = db_fetch_all("SELECT DISTINCT strftime('%Y', stat_date) as year FROM social_media_daily_stats ORDER BY year DESC");

// Get monthly summary
$monthly_summary = db_fetch_all("
    SELECT 
        strftime('%Y-%m', stat_date) as month,
        p.name as platform,
        sms.country,
        MAX(sms.followers) as followers,
        MAX(sms.engagements) as engagements,
        (MAX(sms.engagements) * 1.0 / MAX(sms.followers) * 100) as engagement_rate
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
    GROUP BY strftime('%Y-%m', stat_date), p.name, sms.country
    ORDER BY month DESC, p.name, sms.country
", $params);

// Get platform totals
$platform_totals = db_fetch_all("
    SELECT 
        p.name as platform,
        SUM(sms.followers) as total_followers,
        SUM(sms.engagements) as total_engagements
    FROM social_media_daily_stats sms
    JOIN social_media_platforms p ON sms.platform_id = p.id
    WHERE $where_clause
    GROUP BY p.name
    ORDER BY total_followers DESC
", $params);
?>

<div class="col-md-9 col-lg-10 main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Social Media Analytics</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="social_stats_entry.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Monthly Data
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Platform</label>
                    <select name="platform" class="form-select">
                        <option value="">All Platforms</option>
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?php echo $platform['name']; ?>" 
                                <?php echo $platform_filter === $platform['name'] ? 'selected' : ''; ?>>
                                <?php echo $platform['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Country</label>
                    <select name="country" class="form-select">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo $country['country']; ?>" 
                                <?php echo $country_filter === $country['country'] ? 'selected' : ''; ?>>
                                <?php echo $country['country']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year['year']; ?>" 
                                <?php echo $year_filter === $year['year'] ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Platform Totals -->
    <div class="row mb-4">
        <?php foreach ($platform_totals as $platform): ?>
            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <h6 class="card-title"><?php echo $platform['platform']; ?></h6>
                        <h4 class="text-primary"><?php echo number_format($platform['total_followers']); ?></h4>
                        <small class="text-muted">Total Followers</small>
                        <div class="mt-2">
                            <small class="text-muted">
                                Engagements: <?php echo number_format($platform['total_engagements']); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Monthly Data Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Monthly Social Media Data</h6>
        </div>
        <div class="card-body">
            <?php if (empty($monthly_summary)): ?>
                <p class="text-muted text-center py-4">No data found for selected filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Platform</th>
                                <th>Country</th>
                                <th>Followers</th>
                                <th>Engagements</th>
                                <th>Engagement Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_summary as $entry): 
                                $month_name = date('F Y', strtotime($entry['month'] . '-01'));
                            ?>
                                <tr>
                                    <td><strong><?php echo $month_name; ?></strong></td>
                                    <td><?php echo htmlspecialchars($entry['platform']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['country']); ?></td>
                                    <td><?php echo number_format($entry['followers']); ?></td>
                                    <td><?php echo number_format($entry['engagements']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $entry['engagement_rate'] >= 5 ? 'success' : 
                                                ($entry['engagement_rate'] >= 2 ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo number_format($entry['engagement_rate'], 2); ?>%
                                        </span>
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

<style>
.stat-card {
    transition: transform 0.2s;
    border: 1px solid #e9ecef;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.table th {
    background-color: #f8f9fa;
}
</style>

<?php require_once '../includes/footer.php'; ?>