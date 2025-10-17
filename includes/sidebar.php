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
?>

<div class="col-md-3 col-lg-2 bg-dark sidebar">
    <div class="sidebar-sticky pt-3">
        <h4 class="text-white text-center mb-4">LaFab Solutions</h4>
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
        </ul>
    </div>
</div>