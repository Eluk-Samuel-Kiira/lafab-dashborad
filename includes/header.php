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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobs Dashboard - LaFab Solutions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Mobile Header -->
    <nav class="mobile-header d-lg-none">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <button class="menu-toggle" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                <a class="navbar-brand" href="dashboard.php">
                    LaFab Solutions
                </a>
                <div></div> <!-- Spacer for balance -->
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <div class="container-fluid">
        <div class="row">