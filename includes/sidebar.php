<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Define navigation items
$nav_items = [
    'dashboard.php' => [
        'icon' => 'fa-tachometer-alt',
        'text' => 'Dashboard'
    ],
    'job_entry.php' => [
        'icon' => 'fa-plus-circle', 
        'text' => 'Add Job Posts'
    ],
    'posters_stats.php' => [
        'icon' => 'fa-users',
        'text' => 'Posters Stats'
    ],
    'manage_posters.php' => [
        'icon' => 'fa-user-cog',
        'text' => 'Manage Posters'
    ],
    'seo_stats.php' => [
        'icon' => 'fa-search',
        'text' => 'SEO Stats'
    ],
    'seo_entry.php' => [
        'icon' => 'fa-chart-line',
        'text' => 'Add SEO Data'
    ],
    'social_stats.php' => [
        'icon' => 'fa-chart-bar',
        'text' => 'Social Media Stats'
    ],
    'social_entry.php' => [
        'icon' => 'fa-share-alt',
        'text' => 'Add Social Media'
    ]
];

// Define country sync items with their respective file names and country codes
$country_sync_items = [
    'Uganda' => [
        'sync_companies' => 'sync_companies_ug.php',
        'sync_jobs' => 'sync_jobs_ug.php',
        'code' => 'ug'
    ],
    'Kenya' => [
        'sync_companies' => 'sync_companies_ke.php',
        'sync_jobs' => 'sync_jobs_ke.php',
        'code' => 'ke'
    ],
    'Tanzania' => [
        'sync_companies' => 'sync_companies_tz.php',
        'sync_jobs' => 'sync_jobs_tz.php',
        'code' => 'tz'
    ],
    'Rwanda' => [
        'sync_companies' => 'sync_companies_rw.php',
        'sync_jobs' => 'sync_jobs_rw.php',
        'code' => 'rw'
    ],
    'Zambia' => [
        'sync_companies' => 'sync_companies_zm.php',
        'sync_jobs' => 'sync_jobs_zm.php',
        'code' => 'zm'
    ],
    'Malawi' => [
        'sync_companies' => 'sync_companies_mw.php',
        'sync_jobs' => 'sync_jobs_mw.php',
        'code' => 'mw'
    ]
];
?>

<div class="col-md-3 col-lg-2 bg-dark sidebar">
    <div class="sidebar-sticky pt-3">
        <!-- Logo in Sidebar -->
        <div class="text-center mb-4">
            <a href="dashboard.php" class="d-inline-block">
                <img src="../logo.svg" alt="LaFab Solutions" style="height: 40px;">
            </a>
        </div>
        
        <ul class="nav flex-column">
            <?php foreach ($nav_items as $page => $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === $page ? 'active' : ''; ?>" 
                       href="<?php echo $page; ?>">
                        <i class="fas <?php echo $item['icon']; ?>"></i> 
                        <?php echo $item['text']; ?>
                    </a>
                </li>
            <?php endforeach; ?>
            
            <!-- Country Sync Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="countrySyncDropdown" 
                   role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-globe-africa"></i> Country Sync
                </a>
                <ul class="dropdown-menu bg-dark" aria-labelledby="countrySyncDropdown">
                    <?php foreach ($country_sync_items as $country => $sync_pages): ?>
                        <li class="dropdown-submenu">
                            <a class="dropdown-item dropdown-toggle" href="#">
                                <i class="fas fa-map-marker-alt"></i> <?php echo $country; ?>
                            </a>
                            <ul class="dropdown-menu bg-dark">
                                <li>
                                    <a class="dropdown-item <?php echo $current_page === $sync_pages['sync_companies'] ? 'active' : ''; ?>" 
                                       href="<?php echo $sync_pages['sync_companies']; ?>">
                                        <i class="fas fa-building"></i> Sync Companies
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo $current_page === $sync_pages['sync_jobs'] ? 'active' : ''; ?>" 
                                        href="<?php echo $sync_pages['sync_jobs']; ?>?country=<?php echo $sync_pages['code']; ?>">
                                        <i class="fas fa-exchange-alt"></i> Sync Jobs
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                    <?php endforeach; ?>
                </ul>
            </li>
        </ul>
    </div>
</div>

<!-- Add Bootstrap and custom CSS for nested dropdowns -->
<style>
.dropdown-submenu {
    position: relative;
}

.dropdown-submenu .dropdown-menu {
    top: 0;
    left: 100%;
    margin-top: -1px;
    margin-left: 1px;
    border-radius: 0 6px 6px 6px;
}

.dropdown-submenu:hover .dropdown-menu {
    display: block;
}

.dropdown-menu {
    background-color: #343a40;
    border: 1px solid rgba(255,255,255,.1);
}

.dropdown-item {
    color: rgba(255,255,255,.75);
    padding: 0.5rem 1.5rem;
}

.dropdown-item:hover, .dropdown-item.active {
    background-color: #495057;
    color: white;
}

.dropdown-divider {
    border-top: 1px solid rgba(255,255,255,.1);
}

.nav-link {
    color: rgba(255,255,255,.75);
}

.nav-link:hover, .nav-link.active {
    color: white;
    background-color: rgba(255,255,255,.1);
}
</style>